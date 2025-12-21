<?php
require_once __DIR__ . '/../includes/permissions.php';
require_role('segnalatore');
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/data.php';

$user = current_user();
$search = $_GET['search'] ?? '';
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 10;
$filters = ['created_by' => (int)$user['id'], 'exclude_urgent' => true];
if (!empty($search)) $filters['search'] = $search;
$total = count(filter_opportunities($filters));
$totalPages = ceil($total / $perPage);
$page = min($page, $totalPages ?: 1);
$filters['limit'] = $perPage;
$filters['offset'] = ($page - 1) * $perPage;
$opportunities = filter_opportunities($filters); // Rimuovo paginazione per DataTables

$pageTitle = 'Le mie segnalazioni';
$bottomNav = '
    <a class="nav-pill" href="/segnalatore/dashboard.php"><span class="dot"></span><span>Home</span></a>
    <a class="nav-pill" href="/segnalatore/new_opportunity.php"><span class="dot"></span><span>Nuova</span></a>
    <a class="nav-pill active" href="/segnalatore/segnalazioni.php"><span class="dot"></span><span>Le mie</span></a>
    <a class="nav-pill" href="/segnalatore/report.php"><span class="dot"></span><span>Report</span></a>
';
include __DIR__ . '/../includes/layout/header.php';
?>
<?php
$user = current_user();
$parts = explode(' ', $user['name']);
$name = $parts[0] . ' ' . (isset($parts[1]) ? substr($parts[1], 0, 1) . '.' : '');
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h1 class="h5 fw-bold mb-0">Le mie segnalazioni</h1>
    </div>
    <div class="d-flex gap-2">
        <button class="btn btn-outline-light btn-sm" data-toggle-theme aria-label="Tema">Tema</button>
        <a class="btn btn-outline-secondary btn-sm" href="/auth/logout.php">Logout</a>
    </div>
</div>

<form method="GET" class="mb-3">
    <div class="input-group">
        <input type="text" name="search" class="form-control" placeholder="Cerca per nome..." value="<?php echo sanitize($search); ?>">
        <button class="btn btn-outline-secondary" type="submit">Cerca</button>
    </div>
</form>

<table id="segnalazioniTable" class="table table-striped table-bordered">
    <thead>
        <tr>
            <th>Codice</th>
            <th>Cliente</th>
            <th>Offerta</th>
            <th>Gestore</th>
            <th>Stato</th>
            <th>Commissione</th>
            <th>Data</th>
            <th>Azioni</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($opportunities as $opp): ?>
        <tr>
            <td><?php echo sanitize($opp['opportunity_code']); ?></td>
            <td><?php echo sanitize($opp['first_name'] . ' ' . $opp['last_name']); ?></td>
            <td><?php echo sanitize($opp['offer_name']); ?></td>
            <td><?php echo sanitize($opp['manager_name']); ?></td>
            <td><?php echo sanitize($opp['status']); ?></td>
            <td>€ <?php echo number_format((float)$opp['commission'], 2, ',', '.'); ?></td>
            <td><?php echo strftime('%d/%m/%Y', strtotime($opp['created_at'])); ?></td>
            <td>
                <a href="dettagli.php?id=<?php echo $opp['id']; ?>" class="btn btn-sm btn-outline-primary">Dettagli</a>
            </td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<?php include __DIR__ . '/../includes/layout/footer.php'; ?>

<script>
$(document).ready(function() {
    $('#segnalazioniTable').DataTable({
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
