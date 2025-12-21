<?php
require_once __DIR__ . '/../includes/permissions.php';
require_role('installer');
require_once __DIR__ . '/../includes/helpers.php';

setlocale(LC_TIME, 'it_IT.UTF-8');

$user = current_user();
$ops = filter_opportunities(['installer_id' => $user['id']]);
$summary = summarize($ops);
$latest = array_slice($ops, 0, 5);

// Dati per grafico stati
$statusData = [
    ['status' => 'OK', 'count' => $summary['ok'], 'color' => '#28a745'],
    ['status' => 'In attesa', 'count' => $summary['pending'], 'color' => '#ffc107'],
    ['status' => 'KO', 'count' => $summary['ko'], 'color' => '#dc3545']
];

$pageTitle = 'Dashboard';
$bottomNav = '
    <a class="nav-pill active" href="/installer/dashboard.php"><span class="dot"></span><span>Home</span></a>
    <a class="nav-pill" href="/installer/new_opportunity.php"><span class="dot"></span><span>Nuova</span></a>
    <a class="nav-pill" href="/installer/opportunities.php"><span class="dot"></span><span>Lista</span></a>
    <a class="nav-pill" href="/installer/report.php"><span class="dot"></span><span>Report</span></a>
';
include __DIR__ . '/../includes/layout/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <div class="bite">Installer</div>
        <h1 class="h5 fw-bold mb-0">Dashboard</h1>
    </div>
    <div class="d-flex gap-2">
        <button class="btn btn-outline-light btn-sm" data-toggle-theme aria-label="Tema">Tema</button>
        <a class="btn btn-outline-secondary btn-sm" href="/auth/logout.php">Logout</a>
    </div>
</div>

<div class="card-soft p-3 mb-3">
    <div class="d-flex justify-content-between align-items-center mb-2">
        <div>
            <div class="bite">Riepilogo</div>
            <h2 class="h5 fw-bold mb-0">Andamento rapido</h2>
        </div>
        <span class="badge badge-soft"><?php $formatter = new IntlDateFormatter('it_IT', IntlDateFormatter::NONE, IntlDateFormatter::NONE, null, null, 'MMM yyyy'); echo $formatter->format(time()); ?></span>
    </div>
    <div class="row g-2">
        <div class="col-6">
            <div class="stat-chip">
                <div class="bite">Totali</div>
                <div class="h4 mb-0 fw-bold"><?php echo $summary['total']; ?></div>
            </div>
        </div>
        <div class="col-6">
            <div class="stat-chip">
                <div class="bite">Provvigioni</div>
                <div class="h4 mb-0 fw-bold">€ <?php echo number_format($summary['commission_total'], 2, ',', '.'); ?></div>
            </div>
        </div>
        <div class="col-4">
            <div class="stat-chip text-ok">
                <div class="bite">OK</div>
                <div class="h5 mb-0 fw-bold"><?php echo $summary['ok']; ?></div>
            </div>
        </div>
        <div class="col-4">
            <div class="stat-chip text-warning">
                <div class="bite">Attesa</div>
                <div class="h5 mb-0 fw-bold"><?php echo $summary['pending']; ?></div>
            </div>
        </div>
        <div class="col-4">
            <div class="stat-chip text-danger-soft">
                <div class="bite">KO</div>
                <div class="h5 mb-0 fw-bold"><?php echo $summary['ko']; ?></div>
            </div>
        </div>
    </div>
</div>

<div class="card-soft p-3 mb-3">
    <div class="bite">Distribuzione stati</div>
    <canvas id="statusChart" width="400" height="200"></canvas>
</div>

<?php
$urgentOps = array_filter($ops, fn($op) => $op['status'] === 'In attesa' && strtotime($op['created_at']) < strtotime('-7 days'));
?>
<?php if (!empty($urgentOps)): ?>
<div class="card-soft p-3 mb-3">
    <div class="bite text-warning">Opportunity urgenti</div>
    <div class="list-group list-compact">
        <?php foreach (array_slice($urgentOps, 0, 3) as $op): ?>
        <div class="list-group-item">
            <div class="d-flex justify-content-between">
                <div>
                    <strong><?php echo sanitize($op['first_name'] . ' ' . $op['last_name']); ?></strong>
                    <div class="small text-muted"><?php echo sanitize($op['opportunity_code']); ?> · <?php echo date('d/m/Y', strtotime($op['created_at'])); ?></div>
                </div>
                <a href="/installer/dettagli.php?id=<?php echo $op['id']; ?>" class="btn btn-sm btn-outline-primary">Vedi</a>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<div class="d-flex justify-content-between align-items-center mb-2">
    <div class="bite">Ultime opportunity</div>
    <a href="/installer/opportunities.php" class="btn btn-sm btn-outline-primary">Vedi tutte</a>
</div>
<?php foreach ($latest as $op): ?>
    <div class="card-soft p-3 mb-2">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <div class="fw-bold"><?php echo sanitize($op['first_name'] . ' ' . $op['last_name']); ?></div>
                <div class="text-muted small"><?php echo sanitize($op['offer_name']); ?> · <?php echo sanitize($op['manager_name']); ?></div>
                <div class="small text-muted">Codice: <?php echo sanitize($op['opportunity_code'] ?? ''); ?></div>
            </div>
            <div class="text-end">
                <span class="badge bg-secondary"><?php echo sanitize($op['status']); ?></span>
                <div class="fw-bold">€ <?php echo number_format($op['commission'], 2, ',', '.'); ?></div>
            </div>
        </div>
    </div>
<?php endforeach; ?>

<div class="d-grid mt-4">
    <a class="btn btn-primary btn-pill" href="/installer/new_opportunity.php">+ Nuova opportunity</a>
</div>
<?php include __DIR__ . '/../includes/layout/footer.php'; ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const ctx = document.getElementById('statusChart').getContext('2d');
    const data = <?php echo json_encode($statusData); ?>;
    new Chart(ctx, {
        type: 'pie',
        data: {
            labels: data.map(d => d.status),
            datasets: [{
                data: data.map(d => d.count),
                backgroundColor: data.map(d => d.color)
            }]
        },
        options: {
            responsive: true
        }
    });
});
</script>
