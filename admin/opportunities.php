<?php
require_once __DIR__ . '/../includes/permissions.php';
require_role('admin');
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/data.php';

$users = get_users();
$user = current_user();
$gestori = get_gestori();

$message = null;
$error = null;

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

$pageTitle = 'Opportunity';
$bottomNav = '
    <a class="nav-pill" href="/admin/dashboard.php"><span class="dot"></span><span>Home</span></a>
    <a class="nav-pill active" href="/admin/opportunities.php"><span class="dot"></span><span>Opportunity</span></a>
    <a class="nav-pill" href="/admin/new_urgent.php"><span class="dot"></span><span>Urgente</span></a>
    <a class="nav-pill" href="/admin/report.php"><span class="dot"></span><span>Report</span></a>
    <a class="nav-pill" href="/admin/settings.php"><span class="dot"></span><span>Impostazioni</span></a>
';

// JSON output for desktop app
if (isset($_GET['format']) && $_GET['format'] === 'json') {
    header('Content-Type: application/json');
    echo json_encode([
        'opportunities' => $ops,
        'total' => $totalOps,
        'page' => $page,
        'perPage' => $perPage,
        'totalPages' => $totalPages
    ]);
    exit;
}

include __DIR__ . '/../includes/layout/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <div class="bite">Admin</div>
        <h1 class="h5 fw-bold mb-0">Opportunity</h1>
    </div>
    <div class="d-flex gap-2">
        <button id="exportCsv" class="btn btn-outline-primary btn-sm">Esporta CSV</button>
        <a class="btn btn-outline-secondary btn-sm" href="/auth/logout.php">Logout</a>
    </div>
</div>

<form class="row g-2 mb-3" method="get" id="filterForm">
    <div class="col-md-2">
        <select class="form-select" name="installer_id">
            <option value="">Tutti Installer</option>
            <?php foreach ($users as $u): if ($u['role'] !== 'installer') continue; ?>
                <option value="<?php echo $u['id']; ?>" <?php echo ($installerId == $u['id']) ? 'selected' : ''; ?>><?php echo sanitize($u['name']); ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-md-2">
        <select class="form-select" name="month">
            <option value="">Tutti Mesi</option>
            <?php foreach (month_options() as $m): ?>
                <option value="<?php echo $m['value']; ?>" <?php echo ($month == $m['value']) ? 'selected' : ''; ?>><?php echo $m['label']; ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-md-2">
        <select class="form-select" name="status">
            <option value="">Tutti Stati</option>
            <option value="<?php echo STATUS_PENDING; ?>" <?php echo ($status === STATUS_PENDING) ? 'selected' : ''; ?>>In attesa</option>
            <option value="<?php echo STATUS_OK; ?>" <?php echo ($status === STATUS_OK) ? 'selected' : ''; ?>>OK</option>
            <option value="<?php echo STATUS_KO; ?>" <?php echo ($status === STATUS_KO) ? 'selected' : ''; ?>>KO</option>
        </select>
    </div>
    <div class="col-md-2">
        <select class="form-select" name="manager">
            <option value="">Tutti Gestori</option>
            <?php foreach ($gestori as $g): ?>
                <option value="<?php echo sanitize($g['name']); ?>" <?php echo ($manager === $g['name']) ? 'selected' : ''; ?>><?php echo sanitize($g['name']); ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-md-2">
        <select class="form-select" name="origin">
            <option value="">Tutte Origini</option>
            <option value="segnalatore" <?php echo ($origin === 'segnalatore') ? 'selected' : ''; ?>>Segnalatore</option>
            <option value="admin_installer" <?php echo ($origin === 'admin_installer') ? 'selected' : ''; ?>>Admin/Installer</option>
        </select>
    </div>
    <div class="col-md-2">
        <button type="submit" class="btn btn-primary w-100">Filtra</button>
    </div>
</form>

<?php foreach ($ops as $op): ?>
    <a href="dettagli.php?id=<?php echo $op['id']; ?>" class="text-decoration-none">
        <div class="card-soft p-3 mb-2">
            <div class="d-flex justify-content-between align-items-start mb-2">
                <div>
                    <div class="fw-bold"><?php echo sanitize($op['first_name'] . ' ' . $op['last_name']); ?></div>
                    <div class="text-muted small"><?php echo sanitize($op['offer_name']); ?> · <?php echo sanitize($op['manager_name']); ?></div>
                    <div class="small text-muted">Codice: <?php echo sanitize($op['opportunity_code'] ?? ''); ?></div>
                    <div class="small text-muted">Installer: <?php echo sanitize($op['installer_name']); ?></div>
                    <?php if (!empty($op['segnalatore_name'])): ?>
                        <div class="small text-muted">Segnalatore: <?php echo sanitize($op['segnalatore_name']); ?></div>
                    <?php endif; ?>
                    <?php if (!empty($op['notes'])): ?>
                        <?php
                        $notes = $op['notes'];
                        $fileLinks = '';
                        if (strpos($notes, '|') !== false) {
                            list($text, $json) = explode('|', $notes, 2);
                            $fileData = json_decode($json, true);
                            if ($fileData) {
                                $fileLinks = '<br>Documenti: ';
                                foreach ($fileData as $idx => $filePath) {
                                    $fileName = basename($filePath);
                                    $fileLinks .= '<a href="/download.php?type=opportunity&id=' . $op['id'] . '&index=' . $idx . '" target="_blank">' . sanitize($fileName) . '</a> ';
                                }
                            }
                            $notes = $text;
                        }
                        ?>
                        <div class="small text-muted">Note: <?php echo sanitize($notes) . $fileLinks; ?></div>
                    <?php endif; ?>
                    <?php if (!empty($op['phone'])): ?>
                        <div class="small text-muted">Cellulare: <?php echo sanitize($op['phone']); ?></div>
                    <?php endif; ?>
                    <?php if (!empty($op['address'])): ?>
                        <div class="small text-muted">Indirizzo: <?php echo sanitize($op['address']); ?></div>
                    <?php endif; ?>
                    <?php if (!empty($op['city'])): ?>
                        <div class="small text-muted">Città: <?php echo sanitize($op['city']); ?></div>
                    <?php endif; ?>
                </div>
                <div class="text-end">
                    <div class="fw-bold"><?php echo $op['product_type'] == 0 ? 'Urgente' : '€ ' . number_format($op['commission'], 2, ',', '.'); ?></div>
                    <div class="text-muted small"><?php $formatter = new IntlDateFormatter('it_IT', IntlDateFormatter::NONE, IntlDateFormatter::NONE, null, null, 'dd MMMM yyyy'); echo $formatter->format(strtotime($op['created_at'])); ?></div>
                </div>
            </div>
            <?php if ($op['product_type'] > 0): ?>
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
            <?php endif; ?>
        </div>
    </a>
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

<?php include __DIR__ . '/../includes/layout/footer.php'; ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Rimuovi DataTables, torna alla lista originale
});
</script>
