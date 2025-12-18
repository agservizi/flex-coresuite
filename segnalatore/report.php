<?php
require_once __DIR__ . '/../includes/permissions.php';
require_role('segnalatore');
require_once __DIR__ . '/../includes/helpers.php';

$user = current_user();
$month = sanitize($_GET['month'] ?? date('n'));

$segnalazioni = list_segnalazioni([
    'created_by' => (int)$user['id'],
    'month' => $month,
]);
$summary = [
    'total' => count($segnalazioni),
    'pending' => count(array_filter($segnalazioni, fn($s) => $s['status'] === 'In attesa')),
    'ok' => count(array_filter($segnalazioni, fn($s) => $s['status'] === 'OK')),
    'ko' => count(array_filter($segnalazioni, fn($s) => $s['status'] === 'KO')),
];

$pageTitle = 'Report mensile';
$bottomNav = '
    <a class="nav-pill" href="/segnalatore/dashboard.php"><span class="dot"></span><span>Home</span></a>
    <a class="nav-pill" href="/segnalatore/new_opportunity.php"><span class="dot"></span><span>Nuova</span></a>
    <a class="nav-pill" href="/segnalatore/segnalazioni.php"><span class="dot"></span><span>Le mie</span></a>
    <a class="nav-pill active" href="/segnalatore/report.php"><span class="dot"></span><span>Report</span></a>
';
include __DIR__ . '/../includes/layout/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <div class="bite">Report</div>
        <h1 class="h5 fw-bold mb-0">Mensile personale</h1>
    </div>
    <div class="d-flex align-items-center gap-2">
        <form method="get" class="d-flex align-items-center gap-2 mb-0">
            <select class="form-select" name="month" onchange="this.form.submit()">
                <?php foreach (month_options() as $m): ?>
                    <option value="<?php echo $m['value']; ?>" <?php echo ($month == $m['value']) ? 'selected' : ''; ?>><?php echo $m['label']; ?></option>
                <?php endforeach; ?>
            </select>
        </form>
        <a class="btn btn-outline-secondary btn-sm" href="/auth/logout.php">Logout</a>
    </div>
</div>

<div class="card-soft p-3 mb-3">
    <div class="row g-2">
        <div class="col-6">
            <div class="stat-chip">
                <div class="bite">Totale inviate</div>
                <div class="h4 fw-bold mb-0"><?php echo $summary['total']; ?></div>
            </div>
        </div>
        <div class="col-6">
            <div class="stat-chip text-ok">
                <div class="bite">Approvate</div>
                <div class="h4 fw-bold mb-0"><?php echo $summary['ok']; ?></div>
            </div>
        </div>
        <div class="col-6">
            <div class="stat-chip text-warning">
                <div class="bite">In attesa</div>
                <div class="h4 fw-bold mb-0"><?php echo $summary['pending']; ?></div>
            </div>
        </div>
        <div class="col-6">
            <div class="stat-chip text-danger-soft">
                <div class="bite">Rifiutate</div>
                <div class="h4 fw-bold mb-0"><?php echo $summary['ko']; ?></div>
            </div>
        </div>
    </div>
</div>

<div class="card-soft p-3">
    <div class="bite">Dettagli segnalazioni</div>
    <?php if (empty($segnalazioni)): ?>
        <div class="text-center text-muted py-3">Nessuna segnalazione in questo mese.</div>
    <?php else: ?>
        <div class="list-group">
            <?php foreach ($segnalazioni as $seg): ?>
                <div class="list-group-item d-flex justify-content-between align-items-center">
                    <div>
                        <div class="fw-bold"><?php echo sanitize($seg['first_name'] . ' ' . $seg['last_name']); ?></div>
                        <div class="text-muted small"><?php echo sanitize($seg['offer_name']); ?> · <?php echo date('d/m/Y', strtotime($seg['created_at'])); ?></div>
                    </div>
                    <span class="badge bg-secondary"><?php echo sanitize($seg['status']); ?></span>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
<?php include __DIR__ . '/../includes/layout/footer.php'; ?>