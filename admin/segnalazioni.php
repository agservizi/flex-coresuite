<?php
require_once __DIR__ . '/../includes/permissions.php';
require_role('admin');
require_once __DIR__ . '/../includes/helpers.php';

$users = get_users();
$installers = array_filter($users, fn($u) => $u['role'] === 'installer');
$status = sanitize($_GET['status'] ?? '');
$message = null;
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        $error = 'Sessione scaduta, ricarica la pagina.';
    } else {
        $action = $_POST['action'] ?? '';
        $segId = (int)($_POST['seg_id'] ?? 0);
        try {
            if ($action === 'accept') {
                $installerId = (int)($_POST['installer_id'] ?? 0);
                $oppId = update_segnalazione_status($segId, 'Accettata', (int)current_user()['id'], $installerId);
                if ($oppId) {
                    $seg = get_segnalazione($segId);
                    if ($seg) {
                        create_notification((int)$seg['created_by'], 'Segnalazione accettata', 'Convertita in opportunity #' . $oppId, 'success');
                        if (!empty($seg['creator_email'])) {
                            send_resend_email($seg['creator_email'], 'Segnalazione accettata', '<p>La tua segnalazione è stata accettata e convertita in opportunity #' . (int)$oppId . '.</p>');
                        }
                    }
                }
                $message = 'Segnalazione accettata';
            } elseif ($action === 'reject') {
                update_segnalazione_status($segId, 'Rifiutata', (int)current_user()['id']);
                $seg = get_segnalazione($segId);
                if ($seg) {
                    create_notification((int)$seg['created_by'], 'Segnalazione rifiutata', 'La tua segnalazione è stata rifiutata', 'error');
                    if (!empty($seg['creator_email'])) {
                        send_resend_email($seg['creator_email'], 'Segnalazione rifiutata', '<p>La tua segnalazione è stata rifiutata.</p>');
                    }
                }
                $message = 'Segnalazione rifiutata';
            }
        } catch (Throwable $e) {
            $error = $e->getMessage();
        }
    }
}

$segnalazioni = list_segnalazioni(['status' => $status]);
$pageTitle = 'Segnalazioni';
$bottomNav = '
    <a class="nav-pill" href="/admin/dashboard.php"><span class="dot"></span><span>Home</span></a>
    <a class="nav-pill" href="/admin/opportunities.php"><span class="dot"></span><span>Opportunity</span></a>
    <a class="nav-pill" href="/admin/report.php"><span class="dot"></span><span>Report</span></a>
    <a class="nav-pill active" href="/admin/segnalazioni.php"><span class="dot"></span><span>Segnalazioni</span></a>
    <a class="nav-pill" href="/admin/segnalatori.php"><span class="dot"></span><span>Segnalatori</span></a>
    <a class="nav-pill" href="/admin/settings.php"><span class="dot"></span><span>Impostazioni</span></a>
';
include __DIR__ . '/../includes/layout/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <div class="bite">Admin</div>
        <h1 class="h5 fw-bold mb-0">Segnalazioni</h1>
    </div>
    <form method="get" class="d-flex align-items-center gap-2">
        <select class="form-select" name="status" onchange="this.form.submit()">
            <option value="">Tutte</option>
            <option value="In attesa" <?php echo $status === 'In attesa' ? 'selected' : ''; ?>>In attesa</option>
            <option value="Accettata" <?php echo $status === 'Accettata' ? 'selected' : ''; ?>>Accettate</option>
            <option value="Rifiutata" <?php echo $status === 'Rifiutata' ? 'selected' : ''; ?>>Rifiutate</option>
        </select>
    </form>
</div>

<?php if ($message): ?>
    <div data-flash data-type="success" data-title="OK" data-flash="<?php echo sanitize($message); ?>"></div>
<?php endif; ?>
<?php if ($error): ?>
    <div data-flash data-type="error" data-title="Errore" data-flash="<?php echo sanitize($error); ?>"></div>
<?php endif; ?>

<?php foreach ($segnalazioni as $seg): ?>
    <div class="card-soft p-3 mb-2">
        <div class="d-flex justify-content-between align-items-start">
            <div>
                <div class="fw-bold"><?php echo sanitize($seg['first_name'] . ' ' . $seg['last_name']); ?></div>
                <div class="text-muted small"><?php echo sanitize($seg['offer_name']); ?> · <?php echo sanitize($seg['manager_name']); ?></div>
                <div class="small text-muted">Segnalata da <?php echo sanitize($seg['creator_name']); ?></div>
                <div class="small text-muted">Doc: <?php echo (int)$seg['doc_count']; ?></div>
                <?php if ($seg['doc_count'] > 0): ?>
                    <div class="d-flex flex-wrap gap-1 mt-1">
                        <?php foreach (get_segnalazione_docs($seg['id']) as $doc): ?>
                            <a class="badge bg-light text-dark" href="/download.php?id=<?php echo (int)$doc['id']; ?>" target="_blank" rel="noopener">Scarica</a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
            <div class="text-end">
                <span class="badge bg-secondary"><?php echo sanitize($seg['status']); ?></span>
                <div class="text-muted small"><?php echo sanitize($seg['created_at']); ?></div>
            </div>
        </div>
        <?php if ($seg['status'] === 'In attesa'): ?>
            <form method="post" class="d-flex gap-2 align-items-center mt-2 flex-wrap">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="seg_id" value="<?php echo $seg['id']; ?>">
                <select class="form-select" name="installer_id" required>
                    <option value="">Installer</option>
                    <?php foreach ($installers as $ins): ?>
                        <option value="<?php echo $ins['id']; ?>"><?php echo sanitize($ins['name']); ?></option>
                    <?php endforeach; ?>
                </select>
                <button class="btn btn-success btn-sm" name="action" value="accept">Accetta</button>
                <button class="btn btn-outline-danger btn-sm" name="action" value="reject">Rifiuta</button>
            </form>
        <?php endif; ?>
    </div>
<?php endforeach; ?>

<?php if (empty($segnalazioni)): ?>
    <div class="alert alert-info">Nessuna segnalazione.</div>
<?php endif; ?>
<?php include __DIR__ . '/../includes/layout/footer.php'; ?>
