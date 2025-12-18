<?php
require_once __DIR__ . '/../includes/permissions.php';
require_role('admin');
require_once __DIR__ . '/../includes/helpers.php';

$users = get_users();
$user = current_user();
$gestori = get_gestori();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['op_id'], $_POST['status'])) {
    if (!verify_csrf()) {
        $error = 'Sessione scaduta, ricarica la pagina.';
    } else {
        $opId = (int)$_POST['op_id'];
        $status = sanitize($_POST['status']);
        $change = update_opportunity_status($opId, $status, (int)$user['id']);
        if (!empty($change['changed']) && !empty($change['installer_id'])) {
            $info = get_opportunity_install_info($opId);
            $code = $info['opportunity_code'] ?? '';
            create_notification((int)$change['installer_id'], 'Opportunity aggiornata', 'Stato: ' . $status . ($code ? ' · ' . $code : ''), 'info');
            $subs = get_push_subscriptions((int)$change['installer_id']);
            send_push_notification($subs, 'Opportunity aggiornata', 'Stato: ' . $status . ($code ? ' · ' . $code : ''));
            notify_installer_status_change((int)$change['installer_id'], $info['installer_name'] ?? '', $info['installer_email'] ?? '', $code, $status);
        }
    }
}

// Handle segnalazioni actions
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
                $oppId = update_segnalazione_status($segId, 'Accettata', (int)$user['id']);
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
                update_segnalazione_status($segId, 'Rifiutata', (int)$user['id']);
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

$installerId = sanitize($_GET['installer_id'] ?? '');
$status = sanitize($_GET['status'] ?? '');
$month = sanitize($_GET['month'] ?? '');
$manager = sanitize($_GET['manager'] ?? '');
$origin = sanitize($_GET['origin'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 10;
$offset = ($page - 1) * $perPage;

$baseFilters = [
    'installer_id' => $installerId,
    'status' => $status,
    'month' => $month,
    'manager' => $manager,
    'origin' => $origin,
];

$totalOps = count_opportunities($baseFilters);
$ops = filter_opportunities($baseFilters + [
    'limit' => $perPage,
    'offset' => $offset,
]);
$totalPages = max(1, (int)ceil($totalOps / $perPage));

$segnalazioni = list_segnalazioni(['status' => 'In attesa']);

$pageTitle = 'Opportunity';
$bottomNav = '
    <a class="nav-pill" href="/admin/dashboard.php"><span class="dot"></span><span>Home</span></a>
    <a class="nav-pill active" href="/admin/opportunities.php"><span class="dot"></span><span>Opportunity</span></a>
    <a class="nav-pill" href="/admin/report.php"><span class="dot"></span><span>Report</span></a>
    <a class="nav-pill" href="/admin/settings.php"><span class="dot"></span><span>Impostazioni</span></a>
';
include __DIR__ . '/../includes/layout/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <div class="bite">Admin</div>
        <h1 class="h5 fw-bold mb-0">Opportunity</h1>
    </div>
    <a class="btn btn-outline-secondary btn-sm" href="/auth/logout.php">Logout</a>
</div>

<form class="row g-2 mb-3" method="get" data-auto-submit="true" data-auto-save="filters-opportunities">
    <div class="col-6">
        <select class="form-select" name="installer_id">
            <option value="">Installer</option>
            <?php foreach ($users as $u): if ($u['role'] !== 'installer') continue; ?>
                <option value="<?php echo $u['id']; ?>" <?php echo ($installerId == $u['id']) ? 'selected' : ''; ?>><?php echo sanitize($u['name']); ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-6">
        <select class="form-select" name="month">
            <option value="">Mese</option>
            <?php foreach (month_options() as $m): ?>
                <option value="<?php echo $m['value']; ?>" <?php echo ($month == $m['value']) ? 'selected' : ''; ?>><?php echo $m['label']; ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-6">
        <select class="form-select" name="status">
            <option value="">Stato</option>
            <option value="<?php echo STATUS_PENDING; ?>" <?php echo ($status === STATUS_PENDING) ? 'selected' : ''; ?>>In attesa</option>
            <option value="<?php echo STATUS_OK; ?>" <?php echo ($status === STATUS_OK) ? 'selected' : ''; ?>>OK</option>
            <option value="<?php echo STATUS_KO; ?>" <?php echo ($status === STATUS_KO) ? 'selected' : ''; ?>>KO</option>
        </select>
    </div>
    <div class="col-6">
        <select class="form-select" name="manager">
            <option value="">Gestore</option>
            <?php foreach ($gestori as $g): ?>
                <option value="<?php echo sanitize($g['name']); ?>" <?php echo ($manager === $g['name']) ? 'selected' : ''; ?>><?php echo sanitize($g['name']); ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-12">
        <select class="form-select" name="origin">
            <option value="">Origine</option>
            <option value="admin_installer" <?php echo ($origin === 'admin_installer') ? 'selected' : ''; ?>>Admin/Installer</option>
            <option value="segnalatore" <?php echo ($origin === 'segnalatore') ? 'selected' : ''; ?>>Segnalatore</option>
        </select>
    </div>
</form>

<?php foreach ($ops as $op): ?>
    <div class="card-soft p-3 mb-2">
        <div class="d-flex justify-content-between align-items-start mb-2">
            <div>
                <div class="fw-bold"><?php echo sanitize($op['first_name'] . ' ' . $op['last_name']); ?></div>
                <div class="text-muted small"><?php echo sanitize($op['offer_name']); ?> · <?php echo sanitize($op['manager_name']); ?></div>
                <div class="small text-muted">Codice: <?php echo sanitize($op['opportunity_code'] ?? ''); ?></div>
                <div class="small text-muted">Installer: <?php echo sanitize($op['installer_name']); ?></div>
            </div>
            <div class="text-end">
                <div class="fw-bold">€ <?php echo number_format($op['commission'], 2, ',', '.'); ?></div>
                <div class="text-muted small"><?php echo sanitize($op['created_at']); ?></div>
            </div>
        </div>
        <form method="post" class="card-action">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="op_id" value="<?php echo $op['id']; ?>">
            <div class="state">Stato attuale: <?php echo sanitize($op['status']); ?></div>
            <div class="d-flex gap-2">
                <button name="status" value="<?php echo STATUS_PENDING; ?>" class="btn btn-outline-secondary btn-sm">In attesa</button>
                <button name="status" value="<?php echo STATUS_OK; ?>" class="btn btn-outline-success btn-sm">OK</button>
                <button name="status" value="<?php echo STATUS_KO; ?>" class="btn btn-outline-danger btn-sm">KO</button>
            </div>
        </form>
    </div>
<?php endforeach; ?>

<?php if (empty($ops)): ?>
    <div class="alert alert-info">Nessuna opportunity trovata.</div>
<?php endif; ?>

<?php if ($totalPages > 1): ?>
    <?php
        $queryBase = [
            'installer_id' => $installerId,
            'status' => $status,
            'month' => $month,
            'manager' => $manager,
            'origin' => $origin,
        ];
    ?>
    <nav class="d-flex justify-content-between align-items-center mt-3">
        <a class="btn btn-outline-secondary btn-sm <?php echo $page <= 1 ? 'disabled' : ''; ?>" href="/admin/opportunities.php?<?php echo http_build_query($queryBase + ['page' => max(1, $page - 1)]); ?>">Precedente</a>
        <div class="small text-muted">Pagina <?php echo $page; ?> di <?php echo $totalPages; ?></div>
        <a class="btn btn-outline-secondary btn-sm <?php echo $page >= $totalPages ? 'disabled' : ''; ?>" href="/admin/opportunities.php?<?php echo http_build_query($queryBase + ['page' => min($totalPages, $page + 1)]); ?>">Successiva</a>
    </nav>
<?php endif; ?>

<?php if ($message): ?>
    <div data-flash data-type="success" data-title="OK" data-flash="<?php echo sanitize($message); ?>"></div>
<?php endif; ?>
<?php if ($error): ?>
    <div data-flash data-type="error" data-title="Errore" data-flash="<?php echo sanitize($error); ?>"></div>
<?php endif; ?>

<?php if (!empty($segnalazioni)): ?>
<h2 class="h6 fw-bold mb-3">Segnalazioni in attesa</h2>
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
        <form method="post" class="d-flex gap-2 align-items-center mt-2 flex-wrap">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="seg_id" value="<?php echo $seg['id']; ?>">
            <button class="btn btn-success btn-sm" name="action" value="accept">Accetta</button>
            <button class="btn btn-outline-danger btn-sm" name="action" value="reject">Rifiuta</button>
        </form>
    </div>
<?php endforeach; ?>
<?php endif; ?>

<?php include __DIR__ . '/../includes/layout/footer.php'; ?>
