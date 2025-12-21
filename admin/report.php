<?php
require_once __DIR__ . '/../includes/permissions.php';
require_role('admin');
require_once __DIR__ . '/../includes/helpers.php';

$users = get_users();
$installerId = sanitize($_GET['installer_id'] ?? '');
$month = sanitize($_GET['month'] ?? '');

$ops = filter_opportunities([
    'installer_id' => $installerId,
    'month' => $month,
    'exclude_urgent' => true,
]);
$summary = summarize($ops);

// Dati per grafico mensile (ultimi 12 mesi)
$monthlyData = [];
for ($i = 11; $i >= 0; $i--) {
    $date = date('Y-m', strtotime("-$i months"));
    $monthOps = filter_opportunities([
        'installer_id' => $installerId,
        'month' => date('n', strtotime($date)),
        'year' => date('Y', strtotime($date)),
        'exclude_urgent' => true,
    ]);
    $monthlyData[] = [
        'label' => date('M Y', strtotime($date)),
        'total' => count($monthOps),
        'commission' => array_sum(array_map(fn($o) => (float)$o['commission'], $monthOps)),
    ];
}

$pageTitle = 'Report';
$bottomNav = '
    <a class="nav-pill" href="/admin/dashboard.php"><span class="dot"></span><span>Home</span></a>
    <a class="nav-pill" href="/admin/opportunities.php"><span class="dot"></span><span>Opportunity</span></a>
    <a class="nav-pill" href="/admin/new_urgent.php"><span class="dot"></span><span>Urgente</span></a>
    <a class="nav-pill active" href="/admin/report.php"><span class="dot"></span><span>Report</span></a>
    <a class="nav-pill" href="/admin/settings.php"><span class="dot"></span><span>Impostazioni</span></a>
';
include __DIR__ . '/../includes/layout/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <div class="bite">Admin</div>
        <h1 class="h5 fw-bold mb-0">Report mensili</h1>
    </div>
    <a class="btn btn-outline-secondary btn-sm" href="/auth/logout.php">Logout</a>
</div>

<form class="row g-2 mb-3" method="get" data-auto-submit="true" data-auto-save="filters-report">
    <div class="col-6">
        <select class="form-select" name="installer_id">
            <option value="">Tutti gli installer</option>
            <?php foreach ($users as $u): if ($u['role'] !== 'installer') continue; ?>
                <option value="<?php echo $u['id']; ?>" <?php echo ($installerId == $u['id']) ? 'selected' : ''; ?>><?php echo sanitize($u['name']); ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-6">
        <select class="form-select" name="month">
            <option value="">Tutti i mesi</option>
            <?php foreach (month_options() as $m): ?>
                <option value="<?php echo $m['value']; ?>" <?php echo ($month == $m['value']) ? 'selected' : ''; ?>><?php echo $m['label']; ?></option>
            <?php endforeach; ?>
        </select>
    </div>
</form>

<div class="d-flex justify-content-between align-items-center mb-3">
    <div></div>
    <button id="exportCsv" class="btn btn-outline-primary btn-sm">Esporta CSV</button>
</div>

<div class="card-soft p-3 mb-3">
    <h3 class="h6 fw-bold mb-3">Trend mensile (ultimi 12 mesi)</h3>
    <canvas id="monthlyChart" width="400" height="200"></canvas>
</div>

<div class="card-soft p-3 mb-3">
    <div class="row g-2">
        <div class="col-6">
            <div class="stat-chip">
                <div class="bite">Totali</div>
                <div class="h4 fw-bold mb-0"><?php echo $summary['total']; ?></div>
            </div>
        </div>
        <div class="col-6">
            <div class="stat-chip">
                <div class="bite">Provvigioni</div>
                <div class="h4 fw-bold mb-0">€ <?php echo number_format($summary['commission_total'], 2, ',', '.'); ?></div>
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

<h2 class="h6 fw-bold">Dettagli</h2>
<?php foreach ($ops as $op): ?>
    <div class="card-soft p-3 mb-2">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <div class="fw-bold"><?php echo sanitize($op['first_name'] . ' ' . $op['last_name']); ?></div>
                <div class="text-muted small"><?php echo sanitize($op['offer_name']); ?> · <?php echo sanitize($op['manager_name']); ?></div>
                <div class="small text-muted">Installer: <?php echo sanitize($op['installer_name']); ?></div>
            </div>
            <div class="text-end">
                <span class="badge bg-secondary"><?php echo sanitize($op['status']); ?></span>
                <div class="fw-bold">€ <?php echo number_format($op['commission'], 2, ',', '.'); ?></div>
            </div>
        </div>
    </div>
<?php endforeach; ?>

<?php if (empty($ops)): ?>
    <div class="alert alert-info">Nessun dato con i filtri selezionati.</div>
<?php endif; ?>

<script>
const monthlyLabels = <?php echo json_encode(array_column($monthlyData, 'label')); ?>;
const monthlyTotals = <?php echo json_encode(array_column($monthlyData, 'total')); ?>;
const monthlyCommissions = <?php echo json_encode(array_column($monthlyData, 'commission')); ?>;

const ctx = document.getElementById('monthlyChart').getContext('2d');
new Chart(ctx, {
    type: 'bar',
    data: {
        labels: monthlyLabels,
        datasets: [{
            label: 'Opportunità',
            data: monthlyTotals,
            backgroundColor: 'rgba(54, 162, 235, 0.5)',
            borderColor: 'rgba(54, 162, 235, 1)',
            borderWidth: 1,
            yAxisID: 'y',
        }, {
            label: 'Commissioni (€)',
            data: monthlyCommissions,
            backgroundColor: 'rgba(255, 99, 132, 0.5)',
            borderColor: 'rgba(255, 99, 132, 1)',
            borderWidth: 1,
            yAxisID: 'y1',
        }]
    },
    options: {
        responsive: true,
        scales: {
            y: {
                type: 'linear',
                display: true,
                position: 'left',
                title: {
                    display: true,
                    text: 'Opportunità'
                }
            },
            y1: {
                type: 'linear',
                display: true,
                position: 'right',
                title: {
                    display: true,
                    text: 'Commissioni (€)'
                },
                grid: {
                    drawOnChartArea: false,
                },
            }
        }
    }
});
</script>

<script>
document.getElementById('exportCsv').addEventListener('click', function() {
    const data = [
        ['Nome', 'Offerta', 'Manager', 'Installer', 'Stato', 'Provvigione']
    ];
    <?php foreach ($ops as $op): ?>
        data.push([
            '<?php echo addslashes($op['first_name'] . ' ' . $op['last_name']); ?>',
            '<?php echo addslashes($op['offer_name']); ?>',
            '<?php echo addslashes($op['manager_name']); ?>',
            '<?php echo addslashes($op['installer_name']); ?>',
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
    link.setAttribute("download", "report.csv");
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
});
</script>

<?php include __DIR__ . '/../includes/layout/footer.php'; ?>
