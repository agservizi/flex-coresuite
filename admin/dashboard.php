<?php
require_once __DIR__ . '/../includes/permissions.php';
require_role('admin');
require_once __DIR__ . '/../includes/helpers.php';

$ops = get_opportunities();
$summary = summarize($ops);
$users = get_users();
$invalid_users = array_filter($users, function($user) {
    return !in_array($user['role'], ['admin', 'installer', 'segnalatore']) || empty($user['role']);
});

$pageTitle = 'Dashboard Admin';
$bottomNav = '
    <a class="nav-pill active" href="/admin/dashboard.php"><span class="dot"></span><span>Home</span></a>
    <a class="nav-pill" href="/admin/opportunities.php"><span class="dot"></span><span>Opportunity</span></a>
    <a class="nav-pill" href="/admin/new_urgent.php"><span class="dot"></span><span>Urgente</span></a>
    <a class="nav-pill" href="/admin/report.php"><span class="dot"></span><span>Report</span></a>
    <a class="nav-pill" href="/admin/settings.php"><span class="dot"></span><span>Impostazioni</span></a>
';
include __DIR__ . '/../includes/layout/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <div class="bite">Admin</div>
        <h1 class="h5 fw-bold mb-0">Panoramica</h1>
    </div>
    <a class="btn btn-outline-secondary btn-sm" href="/auth/logout.php">Logout</a>
</div>

<div class="card-soft p-3 mb-3">
    <div class="row g-2">
        <div class="col-6">
            <div class="stat-chip">
                <div class="bite">Opportunity totali</div>
                <div class="h4 fw-bold mb-0"><?php echo $summary['total']; ?></div>
            </div>
        </div>
        <div class="col-6">
            <div class="stat-chip">
                <div class="bite">Provvigioni globali</div>
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
                <div class="bite">In attesa</div>
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

<?php if (count($invalid_users) > 0): ?>
<div class="card-soft p-3 mb-3">
    <div class="d-flex justify-content-between align-items-center mb-2">
        <div class="bite text-danger">Utenti con ruolo non valido</div>
        <span class="badge bg-danger"><?php echo count($invalid_users); ?></span>
    </div>
    <div class="list-group">
        <?php foreach ($invalid_users as $user): ?>
        <div class="list-group-item d-flex justify-content-between align-items-center">
            <div>
                <strong><?php echo htmlspecialchars($user['name']); ?></strong> (<?php echo htmlspecialchars($user['email']); ?>)
            </div>
            <div>
                Ruolo attuale: <code><?php echo htmlspecialchars($user['role'] ?: 'vuoto'); ?></code>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<div class="card-soft p-3">
    <div class="d-flex justify-content-between align-items-center mb-2">
        <div class="bite">Azioni rapide</div>
        <a class="btn btn-sm btn-primary" href="/admin/opportunities.php">Gestisci</a>
    </div>
    <div class="d-grid gap-2">
        <a class="btn btn-outline-secondary w-100 btn-pill" href="/admin/installers.php">Installer</a>
        <a class="btn btn-outline-secondary w-100 btn-pill" href="/admin/segnalatori.php">Segnalatori</a>
        <a class="btn btn-outline-secondary w-100 btn-pill" href="/admin/opportunities.php">Opportunity</a>
        <a class="btn btn-outline-secondary w-100 btn-pill" href="/admin/report.php">Report</a>
        <a class="btn btn-outline-secondary w-100 btn-pill" href="/admin/settings.php">Impostazioni</a>
    </div>
</div>
<?php include __DIR__ . '/../includes/layout/footer.php'; ?>
