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

<form class="row g-2 mb-3" method="get">
    <div class="col-6">
        <select class="form-select" name="month">
            <option value="">Tutti Mesi</option>
            <?php foreach (month_options() as $m): ?>
                <option value="<?php echo $m['value']; ?>" <?php echo ($month == $m['value']) ? 'selected' : ''; ?>><?php echo $m['label']; ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-6">
        <select class="form-select" name="status">
            <option value="">Tutti Stati</option>
            <option value="<?php echo STATUS_PENDING; ?>" <?php echo ($status === STATUS_PENDING) ? 'selected' : ''; ?>>In attesa</option>
            <option value="<?php echo STATUS_OK; ?>" <?php echo ($status === STATUS_OK) ? 'selected' : ''; ?>>OK</option>
            <option value="<?php echo STATUS_KO; ?>" <?php echo ($status === STATUS_KO) ? 'selected' : ''; ?>>KO</option>
        </select>
    </div>
</form>

<table id="opportunitiesTable" class="table table-striped table-bordered">
    <thead>
        <tr>
            <th>Codice</th>
            <th>Cliente</th>
            <th>Offerta</th>
            <th>Gestore</th>
            <th>Stato</th>
            <th>Provvigione</th>
            <th>Data</th>
            <th>Azioni</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($ops as $op): ?>
        <tr>
            <td><?php echo sanitize($op['opportunity_code'] ?? ''); ?></td>
            <td><?php echo sanitize($op['first_name'] . ' ' . $op['last_name']); ?></td>
            <td><?php echo sanitize($op['offer_name']); ?></td>
            <td><?php echo sanitize($op['manager_name']); ?></td>
            <td><?php echo sanitize($op['status']); ?></td>
            <td><?php echo $op['commission'] == 0 ? 'Urgente' : '€ ' . number_format($op['commission'], 2, ',', '.'); ?></td>
            <td><?php echo strftime('%d/%m/%Y', strtotime($op['created_at'])); ?></td>
            <td>
                <a href="dettagli.php?id=<?php echo $op['id']; ?>" class="btn btn-sm btn-outline-primary">Dettagli</a>
            </td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<?php include __DIR__ . '/../includes/layout/footer.php'; ?>

<script>
$(document).ready(function() {
    $('#opportunitiesTable').DataTable({
        dom: 'Bfrtip',
        buttons: [
            {
                extend: 'csv',
                text: 'Esporta CSV',
                className: 'btn btn-outline-primary btn-sm'
            }
        ],
        language: {
            url: '//cdn.datatables.net/plug-ins/1.13.7/i18n/it-IT.json'
        },
        pageLength: 25,
        order: [[6, 'desc']] // Ordina per data
    });
});
</script>
