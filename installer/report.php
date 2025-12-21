<?php
require_once __DIR__ . '/../includes/permissions.php';
require_role('installer');
require_once __DIR__ . '/../includes/helpers.php';

$user = current_user();
$month = sanitize($_GET['month'] ?? date('n'));

$ops = filter_opportunities([
    'installer_id' => $user['id'],
    'month' => $month,
]);
$summary = summarize($ops);

$pageTitle = 'Report mensile';
$bottomNav = '
    <a class="nav-pill" href="/installer/dashboard.php"><span class="dot"></span><span>Home</span></a>
    <a class="nav-pill" href="/installer/new_opportunity.php"><span class="dot"></span><span>Nuova</span></a>
    <a class="nav-pill" href="/installer/opportunities.php"><span class="dot"></span><span>Lista</span></a>
    <a class="nav-pill active" href="/installer/report.php"><span class="dot"></span><span>Report</span></a>
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
                <div class="bite">OK</div>
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
                <div class="bite">KO</div>
                <div class="h4 fw-bold mb-0"><?php echo $summary['ko']; ?></div>
            </div>
        </div>
    </div>
    <div class="mt-3 p-3 rounded" style="background: var(--bg-elevated);">
        <div class="d-flex justify-content-between align-items-center">
            <div class="bite">Provvigioni maturate</div>
            <div class="h4 fw-bold mb-0">€ <?php echo number_format($summary['commission_total'], 2, ',', '.'); ?></div>
        </div>
    </div>
</div>

<div class="card-soft p-3 mb-3">
    <h3 class="h6 fw-bold mb-3">Distribuzione stati</h3>
    <canvas id="statusChart" width="400" height="200"></canvas>
</div>

<div class="d-flex justify-content-between align-items-center mb-3">
    <div></div>
    <button id="exportCsv" class="btn btn-outline-primary btn-sm">Esporta CSV</button>
</div>

<h2 class="h6 fw-bold">Dettagli</h2>
<?php foreach ($ops as $op): ?>
    <div class="card-soft p-3 mb-2">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <div class="fw-bold"><?php echo sanitize($op['first_name'] . ' ' . $op['last_name']); ?></div>
                <div class="text-muted small"><?php echo sanitize($op['offer_name']); ?></div>
                <div class="small text-muted">Codice: <?php echo sanitize($op['opportunity_code'] ?? ''); ?></div>
            </div>
            <div class="text-end">
                <span class="badge bg-secondary"><?php echo sanitize($op['status']); ?></span>
                <div class="fw-bold">€ <?php echo number_format($op['commission'], 2, ',', '.'); ?></div>
            </div>
        </div>
    </div>
<?php endforeach; ?>

<?php if (empty($ops)): ?>
    <div class="alert alert-info">Nessuna opportunity per il mese selezionato.</div>
<?php endif; ?>

<script>
const statusData = {
    labels: ['OK', 'In attesa', 'KO'],
    datasets: [{
        data: [<?php echo $summary['ok']; ?>, <?php echo $summary['pending']; ?>, <?php echo $summary['ko']; ?>],
        backgroundColor: [
            'rgba(40, 167, 69, 0.8)',
            'rgba(255, 193, 7, 0.8)',
            'rgba(220, 53, 69, 0.8)'
        ],
        borderColor: [
            'rgba(40, 167, 69, 1)',
            'rgba(255, 193, 7, 1)',
            'rgba(220, 53, 69, 1)'
        ],
        borderWidth: 1
    }]
};

const ctx = document.getElementById('statusChart').getContext('2d');
new Chart(ctx, {
    type: 'pie',
    data: statusData,
    options: {
        responsive: true,
        plugins: {
            legend: {
                position: 'bottom',
            }
        }
    }
});
</script>

<script>
document.getElementById('exportCsv').addEventListener('click', function() {
    const data = [
        ['Nome', 'Offerta', 'Codice', 'Stato', 'Provvigione']
    ];
    <?php foreach ($ops as $op): ?>
        data.push([
            '<?php echo addslashes($op['first_name'] . ' ' . $op['last_name']); ?>',
            '<?php echo addslashes($op['offer_name']); ?>',
            '<?php echo addslashes($op['opportunity_code'] ?? ''); ?>',
            '<?php echo addslashes($op['status']); ?>',
            '<?php echo $op['commission']; ?>'
        ]);
    <?php endforeach; ?>

    let csvContent = "data:text/csv;charset=utf-8,";
    data.forEach(function(rowArray) {
        let row = rowArray.join(",");
        csvContent += row + "\r\n";
    });

    const encodedUri = encodeURI(csvContent);
    const link = document.createElement("a");
    link.setAttribute("href", encodedUri);
    link.setAttribute("download", "report_installer.csv");
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
});
</script>

<?php include __DIR__ . '/../includes/layout/footer.php'; ?>
