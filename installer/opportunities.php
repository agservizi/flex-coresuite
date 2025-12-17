<?php
require_once __DIR__ . '/../includes/permissions.php';
require_role('installer');
require_once __DIR__ . '/../includes/helpers.php';

$user = current_user();
$status = sanitize($_GET['status'] ?? '');
$month = sanitize($_GET['month'] ?? '');

$ops = filter_opportunities([
    'installer_id' => $user['id'],
    'status' => $status,
    'month' => $month,
]);

$pageTitle = 'Le tue opportunity';
$bottomNav = '
    <a class="nav-pill" href="/installer/dashboard.php"><span class="dot"></span><span>Home</span></a>
    <a class="nav-pill" href="/installer/new_opportunity.php"><span class="dot"></span><span>Nuova</span></a>
    <a class="nav-pill active" href="/installer/opportunities.php"><span class="dot"></span><span>Lista</span></a>
    <a class="nav-pill" href="/installer/report.php"><span class="dot"></span><span>Report</span></a>
';
include __DIR__ . '/../includes/layout/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <div class="bite">Opportunity</div>
        <h1 class="h5 fw-bold mb-0">Le tue schede</h1>
    </div>
    <div class="d-flex gap-2">
        <a class="btn btn-outline-secondary btn-sm" href="/auth/logout.php">Logout</a>
        <a class="btn btn-primary btn-sm" href="/installer/new_opportunity.php">+ Nuova</a>
    </div>
</div>

<form class="row g-2 mb-3" method="get" data-auto-submit="true">
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
</form>

<?php foreach ($ops as $op): ?>
    <div class="card-soft p-3 mb-2">
        <div class="d-flex justify-content-between align-items-center mb-1">
            <div>
                <div class="fw-bold"><?php echo sanitize($op['first_name'] . ' ' . $op['last_name']); ?></div>
                <div class="text-muted small"><?php echo sanitize($op['offer_name']); ?> · <?php echo sanitize($op['manager_name']); ?></div>
                <div class="small text-muted">Codice: <?php echo sanitize($op['opportunity_code'] ?? ''); ?></div>
            </div>
            <span class="badge bg-secondary"><?php echo sanitize($op['status']); ?></span>
        </div>
        <div class="d-flex justify-content-between align-items-center">
            <div class="text-muted small">Inserita il <?php echo sanitize($op['created_at']); ?></div>
            <div class="fw-bold text-primary">€ <?php echo number_format($op['commission'], 2, ',', '.'); ?></div>
        </div>
    </div>
<?php endforeach; ?>

<?php if (empty($ops)): ?>
    <div class="alert alert-info">Nessuna opportunity con i filtri applicati.</div>
<?php endif; ?>

<?php include __DIR__ . '/../includes/layout/footer.php'; ?>
