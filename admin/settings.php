<?php
require_once __DIR__ . '/../includes/permissions.php';
require_role('admin');
require_once __DIR__ . '/../includes/helpers.php';

$gestori = get_gestori();
$offers = get_offers();
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
            $id = (int)($_POST['id'] ?? 0);
            $name = sanitize($_POST['name'] ?? '');
            $emailRaw = trim($_POST['email'] ?? '');

            if (!$name || strlen($name) > 120) {
                $error = 'Nome installer non valido';
            } elseif (!filter_var($emailRaw, FILTER_VALIDATE_EMAIL)) {
                $error = 'Email non valida';
            } else {
                try {
                    if ($id > 0) {
                        // Modifica esistente
                        $pdo = db();
                        $stmtCheck = $pdo->prepare('SELECT id FROM users WHERE email = :email AND id != :id LIMIT 1');
                        $stmtCheck->execute(['email' => $emailRaw, 'id' => $id]);
                        if ($stmtCheck->fetch()) {
                            $error = 'Email già esistente';
                        } else {
                            $stmt = $pdo->prepare('UPDATE users SET name = :name, email = :email WHERE id = :id');
                            $stmt->execute(['name' => $name, 'email' => $emailRaw, 'id' => $id]);
                            $message = 'Installer modificato';
                        }
                    } else {
                        // Crea nuovo
                        $created = create_installer($name, $emailRaw, null, true);
                        if (!empty($created['reset_token'])) {
                            notify_installer_credentials($name, $emailRaw, $created['reset_token']);
                            $message = 'Installer creato, email inviata per impostare la password';
                        } else {
                            $message = 'Installer creato';
                        }
                    }
                } catch (InvalidArgumentException $e) {
                    $error = $e->getMessage();
                } catch (Throwable $e) {
                    $error = 'Errore durante la creazione/modifica: ' . $e->getMessage();
                }
            }
        }
        if (isset($_POST['resend_invite'])) {
            $id = (int)$_POST['resend_invite'];
            try {
                $token = resend_installer_invite($id);
                $message = 'Invito reinviato';
            } catch (Throwable $e) {
                $error = $e->getMessage();
            }
        }
        $gestori = get_gestori();
        $offers = get_offers();
    }
}

$pageTitle = 'Impostazioni';
$bottomNav = '
    <a class="nav-pill" href="/admin/dashboard.php"><span class="dot"></span><span>Home</span></a>
    <a class="nav-pill" href="/admin/opportunities.php"><span class="dot"></span><span>Opportunity</span></a>
    <a class="nav-pill" href="/admin/new_urgent.php"><span class="dot"></span><span>Urgente</span></a>
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
    <div data-flash data-type="success" data-title="OK" data-flash="<?php echo sanitize($message); ?>"></div>
<?php endif; ?>
<?php if ($error): ?>
    <div data-flash data-type="error" data-title="Errore" data-flash="<?php echo sanitize($error); ?>"></div>
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
        <input type="hidden" name="id" id="gestoreId">
        <div class="col-6">
            <input type="text" class="form-control" name="name" id="gestoreName" placeholder="Nome gestore" required>
        </div>
        <div class="col-3 form-check">
            <input class="form-check-input" type="checkbox" name="active" id="gestoreActive" checked>
            <label class="form-check-label small" for="gestoreActive">Attivo</label>
        </div>
        <div class="col-3">
            <button class="btn btn-primary w-100 btn-sm" id="gestoreBtn">Salva</button>
        </div>
    </form>
    <div class="list-group list-compact mt-2">
        <?php foreach ($gestori as $g): ?>
            <div class="list-group-item d-flex justify-content-between align-items-center">
                <div><?php echo sanitize($g['name']); ?></div>
                <div>
                    <button class="btn btn-sm btn-outline-primary me-1" onclick="editGestore(<?php echo $g['id']; ?>, '<?php echo addslashes($g['name']); ?>', <?php echo $g['active'] ? 'true' : 'false'; ?>)">Modifica</button>
                    <span class="badge <?php echo $g['active'] ? 'bg-success' : 'bg-secondary'; ?>"><?php echo $g['active'] ? 'Attivo' : 'Off'; ?></span>
                </div>
            </div>
        <?php endforeach; ?>
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
        <input type="hidden" name="id" id="offerId">
        <div class="col-6">
            <input type="text" class="form-control" name="name" id="offerName" placeholder="Nome offerta" required>
        </div>
        <div class="col-6">
            <input type="text" class="form-control" name="description" id="offerDesc" placeholder="Descrizione">
        </div>
        <div class="col-4">
            <select class="form-select" name="manager_id" id="offerManager" required>
                <option value="">Gestore</option>
                <?php foreach ($gestori as $g): ?>
                    <option value="<?php echo $g['id']; ?>"><?php echo sanitize($g['name']); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-4">
            <input type="number" step="0.01" class="form-control" name="commission" id="offerCommission" placeholder="Provvigione" required>
        </div>
        <div class="col-4">
            <button class="btn btn-primary w-100 btn-sm" id="offerBtn">Salva</button>
        </div>
    </form>
    <div class="list-group list-compact mt-2">
        <?php foreach ($offers as $offer): ?>
            <div class="list-group-item d-flex justify-content-between align-items-center">
                <div>
                    <div class="fw-semibold"><?php echo sanitize($offer['name']); ?></div>
                    <div class="small text-muted">Gestore: <?php echo sanitize($offer['manager_name']); ?> · € <?php echo number_format($offer['commission'], 2, ',', '.'); ?></div>
                </div>
                <button class="btn btn-sm btn-outline-primary" onclick="editOffer(<?php echo $offer['id']; ?>, '<?php echo addslashes($offer['name']); ?>', '<?php echo addslashes($offer['description'] ?? ''); ?>', <?php echo $offer['manager_id']; ?>, <?php echo $offer['commission']; ?>)">Modifica</button>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<div class="card-soft p-3 mb-3">
    <div class="d-flex justify-content-between align-items-center mb-2">
        <div>
            <div class="bite">Installer</div>
            <h2 class="h6 fw-bold mb-0">Crea / modifica</h2>
        </div>
    </div>
    <form method="post" class="row g-2 align-items-center">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="entity" value="installer">
        <input type="hidden" name="id" id="installerId">
        <div class="col-12 col-md-5">
            <input type="text" class="form-control" name="name" id="installerName" placeholder="Nome completo" required>
        </div>
        <div class="col-12 col-md-5">
            <input type="email" class="form-control" name="email" id="installerEmail" placeholder="Email" required>
        </div>
        <div class="col-12 col-md-2">
            <button class="btn btn-primary w-100 btn-sm" id="installerBtn">Invia invito</button>
        </div>
    </form>
    <div class="list-group list-compact mt-2">
        <?php $installers = get_installers(); ?>
        <?php foreach ($installers as $i): ?>
            <div class="list-group-item d-flex justify-content-between align-items-center">
                <div>
                    <div class="fw-semibold"><?php echo sanitize($i['name']); ?></div>
                    <div class="small text-muted"><?php echo sanitize($i['email']); ?></div>
                </div>
                <div>
                    <button class="btn btn-sm btn-outline-primary me-1" onclick="editInstaller(<?php echo $i['id']; ?>, '<?php echo addslashes($i['name']); ?>', '<?php echo addslashes($i['email']); ?>')">Modifica</button>
                    <button class="btn btn-sm btn-outline-secondary" onclick="resendInvite(<?php echo $i['id']; ?>)">Reinvia invito</button>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>
<script>
function editGestore(id, name, active) {
    document.getElementById('gestoreId').value = id;
    document.getElementById('gestoreName').value = name;
    document.getElementById('gestoreActive').checked = active;
    document.getElementById('gestoreBtn').textContent = 'Modifica';
}

function editOffer(id, name, description, managerId, commission) {
    document.getElementById('offerId').value = id;
    document.getElementById('offerName').value = name;
    document.getElementById('offerDesc').value = description;
    document.getElementById('offerManager').value = managerId;
    document.getElementById('offerCommission').value = commission;
    document.getElementById('offerBtn').textContent = 'Modifica';
}

function editInstaller(id, name, email) {
    document.getElementById('installerId').value = id;
    document.getElementById('installerName').value = name;
    document.getElementById('installerEmail').value = email;
    document.getElementById('installerBtn').textContent = 'Modifica';
}

function resendInvite(id) {
    if (confirm('Reinviare l\'invito?')) {
        // Per semplicità, facciamo un form nascosto o redirect
        var form = document.createElement('form');
        form.method = 'post';
        form.action = '';
        form.innerHTML = '<?php echo csrf_field(); ?><input type="hidden" name="resend_invite" value="' + id + '">';
        document.body.appendChild(form);
        form.submit();
    }
}
</script>
<?php include __DIR__ . '/../includes/layout/footer.php'; ?>
