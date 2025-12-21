<?php
require_once __DIR__ . '/../includes/permissions.php';
require_role('admin');
require_once __DIR__ . '/../includes/helpers.php';

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
$ops = filter_opportunities($baseFilters); // Rimuovo limit e offset per DataTables

$pageTitle = 'Opportunity';
$bottomNav = '
    <a class="nav-pill" href="/admin/dashboard.php"><span class="dot"></span><span>Home</span></a>
    <a class="nav-pill active" href="/admin/opportunities.php"><span class="dot"></span><span>Opportunity</span></a>
    <a class="nav-pill" href="/admin/new_urgent.php"><span class="dot"></span><span>Urgente</span></a>
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

<table id="opportunitiesTable" class="table table-striped table-bordered">
    <thead>
        <tr>
            <th><input type="checkbox" id="selectAll"></th>
            <th>Codice</th>
            <th>Cliente</th>
            <th>Offerta</th>
            <th>Gestore</th>
            <th>Installer</th>
            <th>Stato</th>
            <th>Provvigione</th>
            <th>Data</th>
            <th>Azioni</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($ops as $op): ?>
        <tr>
            <td><input type="checkbox" class="rowCheckbox" value="<?php echo $op['id']; ?>"></td>
            <td><?php echo sanitize($op['opportunity_code'] ?? ''); ?></td>
            <td><?php echo sanitize($op['first_name'] . ' ' . $op['last_name']); ?></td>
            <td><?php echo sanitize($op['offer_name']); ?></td>
            <td><?php echo sanitize($op['manager_name']); ?></td>
            <td><?php echo sanitize($op['installer_name']); ?></td>
            <td><?php echo sanitize($op['status']); ?></td>
            <td><?php echo $op['product_type'] == 0 ? 'Urgente' : '€ ' . number_format($op['commission'], 2, ',', '.'); ?></td>
            <td><?php echo strftime('%d/%m/%Y', strtotime($op['created_at'])); ?></td>
            <td>
                <a href="dettagli.php?id=<?php echo $op['id']; ?>" class="btn btn-sm btn-outline-primary">Dettagli</a>
                <?php if ($op['product_type'] > 0): ?>
                <form method="post" class="d-inline">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="op_id" value="<?php echo $op['id']; ?>">
                    <button name="status" value="<?php echo STATUS_OK; ?>" class="btn btn-sm btn-outline-success">OK</button>
                    <button name="status" value="<?php echo STATUS_KO; ?>" class="btn btn-sm btn-outline-danger">KO</button>
                </form>
                <?php endif; ?>
            </td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<div class="d-none" id="bulkActions">
    <div class="card-soft p-3 mb-3">
        <div class="bite">Azioni Bulk</div>
        <div class="d-flex gap-2">
            <button id="bulkStatusOk" class="btn btn-outline-success">Imposta OK</button>
            <button id="bulkStatusKo" class="btn btn-outline-danger">Imposta KO</button>
            <button id="bulkAssign" class="btn btn-outline-primary">Assegna Installer</button>
        </div>
    </div>
</div>

<?php if ($message): ?>
    <div data-flash data-type="success" data-title="OK" data-flash="<?php echo sanitize($message); ?>"></div>
<?php endif; ?>
<?php if ($error): ?>
    <div data-flash data-type="error" data-title="Errore" data-flash="<?php echo sanitize($error); ?>"></div>
<?php endif; ?>

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
            },
            {
                extend: 'excel',
                text: 'Esporta Excel',
                className: 'btn btn-outline-primary btn-sm'
            }
        ],
        language: {
            url: '//cdn.datatables.net/plug-ins/1.13.7/i18n/it-IT.json'
        },
        pageLength: 25,
        order: [[8, 'desc']] // Ordina per data decrescente
    });

    // Checkbox select all
    $('#selectAll').on('change', function() {
        $('.rowCheckbox').prop('checked', this.checked);
        toggleBulkActions();
    });

    $('.rowCheckbox').on('change', function() {
        $('#selectAll').prop('checked', $('.rowCheckbox:checked').length === $('.rowCheckbox').length);
        toggleBulkActions();
    });

    function toggleBulkActions() {
        if ($('.rowCheckbox:checked').length > 0) {
            $('#bulkActions').removeClass('d-none');
        } else {
            $('#bulkActions').addClass('d-none');
        }
    }

    // Azioni bulk
    $('#bulkStatusOk').on('click', function() {
        bulkUpdateStatus('<?php echo STATUS_OK; ?>');
    });

    $('#bulkStatusKo').on('click', function() {
        bulkUpdateStatus('<?php echo STATUS_KO; ?>');
    });

    function bulkUpdateStatus(status) {
        const selected = $('.rowCheckbox:checked').map(function() { return this.value; }).get();
        if (selected.length === 0) return;

        if (confirm('Confermi l\'aggiornamento di ' + selected.length + ' opportunity?')) {
            selected.forEach(id => {
                $.post('', { op_id: id, status: status, csrf_token: '<?php echo csrf_token(); ?>' });
            });
            location.reload();
        }
    }
});
</script>
