<?php
require_once __DIR__ . '/../includes/permissions.php';
require_role('segnalatore');
require_once __DIR__ . '/../includes/helpers.php';

setlocale(LC_TIME, 'it_IT.UTF-8');

$user = current_user();
$opportunities = filter_opportunities(['created_by' => (int)$user['id'], 'exclude_urgent' => true]);
$summary = [
    'total' => count($opportunities),
    'pending' => count(array_filter($opportunities, fn($o) => $o['status'] === 'In attesa')),
    'ok' => count(array_filter($opportunities, fn($o) => $o['status'] === 'OK')),
    'ko' => count(array_filter($opportunities, fn($o) => $o['status'] === 'KO')),
];
$latest = array_slice($opportunities, 0, 5);

$pageTitle = 'Dashboard';
$bottomNav = '
    <a class="nav-pill active" href="/segnalatore/dashboard.php"><span class="dot"></span><span>Home</span></a>
    <a class="nav-pill" href="/segnalatore/new_opportunity.php"><span class="dot"></span><span>Nuova</span></a>
    <a class="nav-pill" href="/segnalatore/segnalazioni.php"><span class="dot"></span><span>Le mie</span></a>
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
        <span class="badge badge-soft"><?php echo strftime('%b %Y'); ?></span>
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
                <div class="bite">Inviate</div>
                <div class="h4 mb-0 fw-bold"><?php echo $summary['total']; ?></div>
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

<div class="d-flex justify-content-between align-items-center mb-2">
    <div class="bite">Ultime opportunità</div>
    <a href="/segnalatore/segnalazioni.php" class="btn btn-sm btn-outline-primary">Vedi tutte</a>
</div>
<?php if (empty($latest)): ?>
    <div class="card-soft p-3 text-center text-muted">
        Nessuna opportunità ancora creata.
    </div>
<?php else: ?>
    <?php foreach ($latest as $opp): ?>
        <div class="card-soft p-3 mb-2">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <div class="fw-bold"><?php echo sanitize($opp['first_name'] . ' ' . $opp['last_name']); ?></div>
                    <div class="text-muted small"><?php echo sanitize($opp['offer_name']); ?></div>
                    <div class="small text-muted">Creata: <?php echo strftime('%d/%m/%Y', strtotime($opp['created_at'])); ?></div>
                </div>
                <div class="text-end">
                    <span class="badge bg-secondary"><?php echo sanitize($opp['status']); ?></span>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
<?php endif; ?>

<div class="d-grid mt-4">
    <a class="btn btn-primary btn-pill" href="/segnalatore/new_opportunity.php">+ Nuova opportunità</a>
</div>
<?php include __DIR__ . '/../includes/layout/footer.php'; ?>