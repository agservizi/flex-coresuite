<?php
require_once __DIR__ . '/../includes/permissions.php';
require_role('admin');
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/data.php';

$id = (int)($_GET['id'] ?? 0);
if (!$id) {
    header('Location: opportunities.php');
    exit;
}

$op = get_opportunity($id);
if (!$op) {
    header('Location: opportunities.php');
    exit;
}

$pageTitle = 'Dettagli opportunity';
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
        <h1 class="h5 fw-bold mb-0">Dettagli opportunity</h1>
        <div class="small text-muted">Codice: <?php echo sanitize($op['opportunity_code']); ?></div>
    </div>
    <a class="btn btn-outline-secondary btn-sm" href="/auth/logout.php">Logout</a>
</div>

<div class="card-soft p-3 mb-3">
    <div class="d-flex justify-content-between align-items-start mb-3">
        <div>
            <div class="bite">Cliente</div>
            <h1 class="h6 fw-bold mb-0"><?php echo sanitize($op['first_name'] . ' ' . $op['last_name']); ?></h1>
        </div>
        <div class="text-end">
            <div class="badge bg-<?php echo $op['status'] === 'OK' ? 'success' : ($op['status'] === 'KO' ? 'danger' : 'warning'); ?>"><?php echo sanitize($op['status']); ?></div>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-12">
            <div class="small text-muted">Offerta</div>
            <div class="fw-semibold"><?php echo sanitize($op['offer_name']); ?> · <?php echo sanitize($op['manager_name']); ?></div>
        </div>
        <div class="col-6">
            <div class="small text-muted">Commissione</div>
            <div class="fw-semibold">€ <?php echo number_format((float)$op['commission'], 2, ',', '.'); ?></div>
        </div>
        <div class="col-6">
            <div class="small text-muted">Data creazione</div>
            <div class="fw-semibold"><?php echo sanitize($op['created_at']); ?></div>
        </div>
        <?php if (!empty($op['phone'])): ?>
        <div class="col-6">
            <div class="small text-muted">Telefono</div>
            <div class="fw-semibold"><?php echo sanitize($op['phone']); ?></div>
        </div>
        <?php endif; ?>
        <?php if (!empty($op['address'])): ?>
        <div class="col-6">
            <div class="small text-muted">Indirizzo</div>
            <div class="fw-semibold"><?php echo sanitize($op['address']); ?><?php if (!empty($op['city'])): ?>, <?php echo sanitize($op['city']); ?><?php endif; ?></div>
        </div>
        <?php endif; ?>
        <?php if (!empty($op['installer_name'])): ?>
        <div class="col-6">
            <div class="small text-muted">Installer</div>
            <div class="fw-semibold"><?php echo sanitize($op['installer_name']); ?></div>
        </div>
        <?php endif; ?>
        <?php if (!empty($op['segnalatore_name'])): ?>
        <div class="col-6">
            <div class="small text-muted">Segnalatore</div>
            <div class="fw-semibold"><?php echo sanitize($op['segnalatore_name']); ?></div>
        </div>
        <?php endif; ?>
    </div>

    <?php if (!empty($op['notes'])): ?>
    <div class="mt-3">
        <div class="small text-muted">Note</div>
        <div><?php echo nl2br(sanitize($op['notes'])); ?></div>
    </div>
    <?php endif; ?>

    <?php
    $files = [];
    if (strpos($op['notes'], '|') !== false) {
        $parts = explode('|', $op['notes'], 2);
        if (count($parts) === 2) {
            $json = trim($parts[1]);
            $files = json_decode($json, true) ?: [];
        }
    }
    if (!empty($files)): ?>
    <div class="mt-3">
        <div class="small text-muted">Documenti allegati</div>
        <div class="d-flex flex-wrap gap-2 mt-2">
            <?php foreach ($files as $file): ?>
                <a href="<?php echo sanitize($file); ?>" target="_blank" class="btn btn-outline-primary btn-sm">
                    <i class="bi bi-file-earmark"></i> Visualizza
                </a>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<div class="d-grid gap-2">
    <form method="post" class="d-inline">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="op_id" value="<?php echo $op['id']; ?>">
        <select name="status" class="form-select d-inline w-auto me-2" required>
            <option value="">Cambia stato</option>
            <option value="<?php echo STATUS_PENDING; ?>" <?php echo $op['status'] === STATUS_PENDING ? 'selected' : ''; ?>>In attesa</option>
            <option value="<?php echo STATUS_OK; ?>" <?php echo $op['status'] === STATUS_OK ? 'selected' : ''; ?>>OK</option>
            <option value="<?php echo STATUS_KO; ?>" <?php echo $op['status'] === STATUS_KO ? 'selected' : ''; ?>>KO</option>
        </select>
        <button type="submit" class="btn btn-primary btn-sm">Aggiorna</button>
    </form>
    <a href="opportunities.php" class="btn btn-outline-secondary">Torna alla lista</a>
</div>

<?php include __DIR__ . '/../includes/layout/footer.php'; ?>