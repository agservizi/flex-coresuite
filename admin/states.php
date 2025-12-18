<?php
require_once __DIR__ . '/../includes/permissions.php';
require_role('admin');
require_once __DIR__ . '/../includes/helpers.php';

$pageTitle = 'Stati';
$bottomNav = '
    <a class="nav-pill" href="/admin/dashboard.php"><span class="dot"></span><span>Home</span></a>
    <a class="nav-pill" href="/admin/opportunities.php"><span class="dot"></span><span>Opportunity</span></a>
    <a class="nav-pill" href="/admin/report.php"><span class="dot"></span><span>Report</span></a>
    <a class="nav-pill" href="/admin/settings.php"><span class="dot"></span><span>Impostazioni</span></a>
';
include __DIR__ . '/../includes/layout/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <div class="bite">Admin</div>
        <h1 class="h5 fw-bold mb-0">Stati Opportunity</h1>
    </div>
    <a class="btn btn-outline-secondary btn-sm" href="/auth/logout.php">Logout</a>
</div>

<div class="card-soft p-3">
    <p class="text-muted small mb-3">Gli stati sono fissi per coerenza con la suite. Aggiornali direttamente dalla lista opportunity.</p>
    <div class="list-group list-compact">
        <div class="list-group-item d-flex justify-content-between align-items-center">
            <div>
                <div class="fw-bold">In attesa</div>
                <div class="small text-muted">Nuova opportunity, in verifica</div>
            </div>
            <span class="badge bg-secondary">Default</span>
        </div>
        <div class="list-group-item d-flex justify-content-between align-items-center">
            <div>
                <div class="fw-bold">OK</div>
                <div class="small text-muted">Approvata, provvigione erogabile</div>
            </div>
            <span class="badge bg-success">Pagabile</span>
        </div>
        <div class="list-group-item d-flex justify-content-between align-items-center">
            <div>
                <div class="fw-bold">KO</div>
                <div class="small text-muted">Annulata / non attivabile</div>
            </div>
            <span class="badge bg-danger">Stop</span>
        </div>
    </div>
    <div class="d-grid mt-3">
        <a class="btn btn-primary btn-pill" href="/admin/opportunities.php">Vai alla lista</a>
    </div>
</div>
<?php include __DIR__ . '/../includes/layout/footer.php'; ?>
