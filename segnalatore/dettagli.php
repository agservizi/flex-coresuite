<?php
require_once __DIR__ . '/../includes/permissions.php';
require_role('segnalatore');
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/data.php';

$user = current_user();
$id = (int)($_GET['id'] ?? 0);
if (!$id) {
    header('Location: segnalazioni.php');
    exit;
}

$opp = get_opportunity($id);
if (!$opp || $opp['created_by'] != $user['id']) {
    header('Location: segnalazioni.php');
    exit;
}

$pageTitle = 'Dettagli segnalazione';
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
        <h1 class="h5 fw-bold mb-0">Dettagli segnalazione</h1>
        <div class="small text-muted">Codice: <?php echo sanitize($opp['opportunity_code']); ?></div>
    </div>
    <div class="d-flex gap-2">
        <button class="btn btn-outline-light btn-sm" data-toggle-theme aria-label="Tema">Tema</button>
        <a class="btn btn-outline-secondary btn-sm" href="/auth/logout.php">Logout</a>
    </div>
</div>

<div class="card-soft p-3 mb-3">
    <div class="d-flex justify-content-between align-items-start mb-3">
        <div>
            <div class="bite">Cliente</div>
            <h1 class="h6 fw-bold mb-0"><?php echo sanitize($opp['first_name'] . ' ' . $opp['last_name']); ?></h1>
        </div>
        <div class="text-end">
            <div class="badge bg-<?php echo $opp['status'] === 'OK' ? 'success' : ($opp['status'] === 'KO' ? 'danger' : 'warning'); ?>"><?php echo sanitize($opp['status']); ?></div>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-12">
            <div class="small text-muted">Offerta</div>
            <div class="fw-semibold"><?php echo sanitize($opp['offer_name']); ?> · <?php echo sanitize($opp['manager_name']); ?></div>
        </div>
        <div class="col-6">
            <div class="small text-muted">Commissione</div>
            <div class="fw-semibold">€ <?php echo number_format((float)$opp['commission'], 2, ',', '.'); ?></div>
        </div>
        <div class="col-6">
            <div class="small text-muted">Data creazione</div>
            <div class="fw-semibold"><?php echo sanitize($opp['created_at']); ?></div>
        </div>
        <?php if (!empty($opp['phone'])): ?>
        <div class="col-6">
            <div class="small text-muted">Telefono</div>
            <div class="fw-semibold"><?php echo sanitize($opp['phone']); ?></div>
        </div>
        <?php endif; ?>
        <?php if (!empty($opp['address'])): ?>
        <div class="col-6">
            <div class="small text-muted">Indirizzo</div>
            <div class="fw-semibold"><?php echo sanitize($opp['address']); ?><?php if (!empty($opp['city'])): ?>, <?php echo sanitize($opp['city']); ?><?php endif; ?></div>
        </div>
        <?php endif; ?>
    </div>

    <?php
    $files = [];
    $displayNotes = $opp['notes'];
    if (strpos($opp['notes'], '|') !== false) {
        $parts = explode('|', $opp['notes'], 2);
        if (count($parts) === 2) {
            $displayNotes = trim($parts[0]);
            $json = trim($parts[1]);
            $files = json_decode($json, true) ?: [];
        }
    }
    if (!empty($displayNotes)): ?>
    <div class="mt-3">
        <div class="small text-muted">Note</div>
        <div><?php echo nl2br(sanitize($displayNotes)); ?></div>
    </div>
    <?php endif; ?>

    <?php if (!empty($files)): ?>
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

<?php $audit = get_opportunity_audit($opp['id']); ?>
<?php if (!empty($audit)): ?>
<div class="card-soft p-3 mb-3">
    <div class="bite">Cronologia</div>
    <div class="list-group list-compact">
        <?php foreach ($audit as $entry): ?>
            <div class="list-group-item">
                <div class="d-flex justify-content-between">
                    <div>
                        <span class="badge bg-secondary"><?php echo sanitize($entry['old_status']); ?> → <?php echo sanitize($entry['new_status']); ?></span>
                    </div>
                    <div class="small text-muted">
                        <?php echo sanitize($entry['changed_by_name'] ?? 'Sistema'); ?> · <?php echo sanitize($entry['changed_at']); ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<div class="d-grid">
    <a href="segnalazioni.php" class="btn btn-outline-secondary">Torna alla lista</a>
</div>

<?php include __DIR__ . '/../includes/layout/footer.php'; ?>