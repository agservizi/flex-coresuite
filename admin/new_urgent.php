<?php
require_once __DIR__ . '/../includes/permissions.php';
require_role('admin');
require_once __DIR__ . '/../includes/helpers.php';

$users = get_users();
$gestori = get_gestori();
$message = null;
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        $error = 'Sessione scaduta, ricarica la pagina.';
    } else {
        $first = sanitize($_POST['first_name'] ?? '');
        $last = sanitize($_POST['last_name'] ?? '');
        $phone = sanitize($_POST['phone'] ?? '');
        $address = sanitize($_POST['address'] ?? '');
        $city = sanitize($_POST['city'] ?? '');
        $managerId = (int)($_POST['manager_id'] ?? 0);
        $installerId = (int)($_POST['installer_id'] ?? 0);

        if (!$first || !$last || !$phone || !$address || !$city || !$managerId || !$installerId) {
            $error = 'Compila tutti i campi obbligatori.';
        } else {
            try {
                $opp = add_opportunity([
                    'first_name' => $first,
                    'last_name' => $last,
                    'phone' => $phone,
                    'address' => $address,
                    'city' => $city,
                    'notes' => 'Urgente fibra',
                    'offer_id' => 1, // Assume fibra 100
                    'commission' => 35.00, // Assume standard
                    'installer_id' => $installerId,
                    'manager_id' => $managerId,
                ]);

                // Notifica installer
                create_notification($installerId, 'Nuova segnalazione urgente', "Cliente: $first $last - Cell: $phone - $address, $city", 'warning');
                $subs = get_push_subscriptions($installerId);
                send_push_notification($subs, 'Nuova segnalazione urgente', "Cliente: $first $last - $address, $city");

                $message = 'Segnalazione urgente inviata (#' . $opp['opportunity_code'] . ')';
            } catch (Throwable $e) {
                $error = 'Errore: ' . $e->getMessage();
            }
        }
    }
}

$pageTitle = 'Nuova segnalazione urgente';
$bottomNav = '
    <a class="nav-pill" href="/admin/dashboard.php"><span class="dot"></span><span>Home</span></a>
    <a class="nav-pill" href="/admin/opportunities.php"><span class="dot"></span><span>Opportunity</span></a>
    <a class="nav-pill active" href="/admin/new_urgent.php"><span class="dot"></span><span>Urgente</span></a>
    <a class="nav-pill" href="/admin/settings.php"><span class="dot"></span><span>Impostazioni</span></a>
';
include __DIR__ . '/../includes/layout/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <div class="bite">Admin</div>
        <h1 class="h5 fw-bold mb-0">Nuova segnalazione urgente</h1>
    </div>
    <a class="btn btn-outline-secondary btn-sm" href="/auth/logout.php">Logout</a>
</div>

<?php if ($message): ?>
    <div class="alert alert-success"><?php echo sanitize($message); ?></div>
<?php endif; ?>
<?php if ($error): ?>
    <div class="alert alert-danger"><?php echo sanitize($error); ?></div>
<?php endif; ?>

<form method="post">
    <?php echo csrf_field(); ?>
    <div class="row g-3">
        <div class="col-md-6">
            <label class="form-label">Nome *</label>
            <input type="text" name="first_name" class="form-control" required>
        </div>
        <div class="col-md-6">
            <label class="form-label">Cognome *</label>
            <input type="text" name="last_name" class="form-control" required>
        </div>
        <div class="col-md-6">
            <label class="form-label">Cellulare *</label>
            <input type="tel" name="phone" class="form-control" required>
        </div>
        <div class="col-md-6">
            <label class="form-label">Indirizzo *</label>
            <input type="text" name="address" class="form-control" required>
        </div>
        <div class="col-md-6">
            <label class="form-label">Città *</label>
            <input type="text" name="city" class="form-control" required>
        </div>
        <div class="col-md-6">
            <label class="form-label">Gestore *</label>
            <select name="manager_id" class="form-select" required>
                <option value="">Seleziona gestore</option>
                <?php foreach ($gestori as $g): ?>
                    <option value="<?php echo $g['id']; ?>"><?php echo sanitize($g['name']); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-12">
            <label class="form-label">Installer *</label>
            <select name="installer_id" class="form-select" required>
                <option value="">Seleziona installer</option>
                <?php foreach ($users as $u): if ($u['role'] !== 'installer') continue; ?>
                    <option value="<?php echo $u['id']; ?>"><?php echo sanitize($u['name']); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-12">
            <button type="submit" class="btn btn-primary">Invia segnalazione urgente</button>
        </div>
    </div>
</form>

<?php include __DIR__ . '/../includes/layout/footer.php'; ?>