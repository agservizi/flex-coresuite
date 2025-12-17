<?php
require_once __DIR__ . '/../includes/permissions.php';
require_role('admin');
require_once __DIR__ . '/../includes/helpers.php';

$gestori = get_gestori();
$offers = get_offers();
$users = get_users();
$message = null;
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        $error = 'Sessione scaduta, ricarica la pagina.';
    } else {
        if (isset($_POST['entity']) && $_POST['entity'] === 'gestore') {
            $name = sanitize($_POST['name'] ?? '');
            $active = isset($_POST['active']);
            if ($name && strlen($name) <= 120) {
                $gestore = [
                    'id' => (int)($_POST['id'] ?? 0),
                    'name' => $name,
                    'active' => $active,
                ];
                upsert_gestore($gestore);
                $message = 'Gestore salvato';
            } else {
                $error = 'Nome gestore non valido';
            }
        }
        if (isset($_POST['entity']) && $_POST['entity'] === 'offer') {
            $name = sanitize($_POST['name'] ?? '');
            $desc = sanitize($_POST['description'] ?? '');
            $commission = (float)($_POST['commission'] ?? 0);
            $managerId = (int)($_POST['manager_id'] ?? 0);
            if ($name && $managerId && strlen($name) <= 120 && strlen($desc) <= 255) {
                $offer = [
                    'id' => (int)($_POST['id'] ?? 0),
                    'name' => $name,
                    'description' => $desc,
                    'commission' => $commission,
                    'manager_id' => $managerId,
                ];
                upsert_offer($offer);
                $message = 'Offerta salvata';
            } else {
                $error = 'Dati offerta non validi';
            }
        }
        if (isset($_POST['entity']) && $_POST['entity'] === 'installer') {
            $name = sanitize($_POST['name'] ?? '');
            $emailRaw = trim($_POST['email'] ?? '');
            $password = $_POST['password'] ?? '';

            if (!$name || strlen($name) > 120) {
                $error = 'Nome installer non valido';
            } elseif (!filter_var($emailRaw, FILTER_VALIDATE_EMAIL)) {
                $error = 'Email non valida';
            } elseif (strlen($password) < 8 || strlen($password) > 128) {
                $error = 'Password non valida (min 8)';
            } else {
                try {
                    create_installer($name, $emailRaw, $password);
                    notify_installer_credentials($name, $emailRaw, $password);
                    $message = 'Installer creato e email inviata';
                } catch (InvalidArgumentException $e) {
                    $error = $e->getMessage();
                } catch (Throwable $e) {
                    $error = 'Errore durante la creazione: ' . $e->getMessage();
                }
            }
        }
        $gestori = get_gestori();
        $offers = get_offers();
        $users = get_users();
    }
}

$pageTitle = 'Impostazioni';
$bottomNav = '
    <a class="nav-pill" href="/admin/dashboard.php"><span class="dot"></span><span>Home</span></a>
    <a class="nav-pill" href="/admin/opportunities.php"><span class="dot"></span><span>Opportunity</span></a>
    <a class="nav-pill" href="/admin/report.php"><span class="dot"></span><span>Report</span></a>
    <a class="nav-pill active" href="/admin/settings.php"><span class="dot"></span><span>Impostazioni</span></a>
';
include __DIR__ . '/../includes/layout/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <div class="bite">Admin</div>
        <h1 class="h5 fw-bold mb-0">Impostazioni</h1>
    </div>
    <a class="btn btn-outline-secondary btn-sm" href="/auth/logout.php">Logout</a>
</div>

<?php if ($message): ?>
    <div class="alert alert-success py-2"><?php echo sanitize($message); ?></div>
<?php endif; ?>
<?php if ($error): ?>
    <div class="alert alert-danger py-2"><?php echo sanitize($error); ?></div>
<?php endif; ?>

<div class="card-soft p-3 mb-3">
    <div class="d-flex justify-content-between align-items-center mb-2">
        <div>
            <div class="bite">Gestori</div>
            <h2 class="h6 fw-bold mb-0">Crea / modifica</h2>
        </div>
    </div>
    <form method="post" class="row g-2 align-items-center">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="entity" value="gestore">
        <div class="col-6">
            <input type="text" class="form-control" name="name" placeholder="Nome gestore" required>
        </div>
        <div class="col-3 form-check">
            <input class="form-check-input" type="checkbox" name="active" id="gestoreActive" checked>
            <label class="form-check-label small" for="gestoreActive">Attivo</label>
        </div>
        <div class="col-6">
            <input type="text" class="form-control" name="description" placeholder="Descrizione">
        </div>
    </form>
    <div class="list-group list-compact mt-2">
        <?php foreach ($gestori as $g): ?>
            <div class="list-group-item d-flex justify-content-between align-items-center">
                <div><?php echo sanitize($g['name']); ?></div>
                <span class="badge <?php echo $g['active'] ? 'bg-success' : 'bg-secondary'; ?>"><?php echo $g['active'] ? 'Attivo' : 'Off'; ?></span>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<div class="card-soft p-3 mb-3">
    <div class="d-flex justify-content-between align-items-center mb-2">
        <div>
            <div class="bite">Installer</div>
            <h2 class="h6 fw-bold mb-0">Crea nuovo</h2>
        </div>
    </div>
    <form method="post" class="row g-2 align-items-center">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="entity" value="installer">
        <div class="col-12">
            <input type="text" class="form-control" name="name" placeholder="Nome completo" required>
        </div>
        <div class="col-12">
            <input type="email" class="form-control" name="email" placeholder="Email aziendale" required>
        </div>
        <div class="col-12">
            <input type="password" class="form-control" name="password" placeholder="Password (min 8)" required>
        </div>
        <div class="col-12">
            <button class="btn btn-primary w-100 btn-sm">Crea installer</button>
        </div>
    </form>
    <div class="list-group list-compact mt-2">
        <?php foreach ($users as $u): if ($u['role'] !== 'installer') continue; ?>
            <div class="list-group-item d-flex justify-content-between align-items-center">
                <div><?php echo sanitize($u['name']); ?> <span class="text-muted small"><?php echo sanitize($u['email']); ?></span></div>
                <span class="badge bg-secondary">Installer</span>
            </div>
        <?php endforeach; ?>
        <?php if (!array_filter($users, fn($u) => $u['role'] === 'installer')): ?>
            <div class="list-group-item text-muted">Nessun installer presente.</div>
        <?php endif; ?>
    </div>
</div>

<div class="card-soft p-3 mb-3">
    <div class="d-flex justify-content-between align-items-center mb-2">
        <div>
            <div class="bite">Offerte</div>
            <h2 class="h6 fw-bold mb-0">Gestione</h2>
        </div>
    </div>
    <form method="post" class="row g-2 align-items-center">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="entity" value="offer">
        <div class="col-6">
            <input type="text" class="form-control" name="name" placeholder="Nome offerta" required>
        </div>
        <div class="col-6">
            <input type="text" class="form-control" name="description" placeholder="Descrizione">
        </div>
        <div class="col-4">
            <select class="form-select" name="manager_id" required>
                <option value="">Gestore</option>
                <?php foreach ($gestori as $g): ?>
                    <option value="<?php echo $g['id']; ?>"><?php echo sanitize($g['name']); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-4">
            <input type="number" step="0.01" class="form-control" name="commission" placeholder="Provvigione" required>
        </div>
        <div class="col-4">
            <button class="btn btn-primary w-100 btn-sm">Salva</button>
        </div>
    </form>
    <div class="list-group list-compact mt-2">
        <?php foreach ($offers as $offer): ?>
            <div class="list-group-item">
                <div class="fw-semibold"><?php echo sanitize($offer['name']); ?></div>
                <div class="small text-muted">Gestore: <?php echo sanitize($offer['manager_name']); ?> · € <?php echo number_format($offer['commission'], 2, ',', '.'); ?></div>
            </div>
        <?php endforeach; ?>
    </div>
</div>
<?php include __DIR__ . '/../includes/layout/footer.php'; ?>
