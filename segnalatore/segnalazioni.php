<?php
require_once __DIR__ . '/../includes/permissions.php';
require_role('segnalatore');
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/data.php';

$user = current_user();
$search = $_GET['search'] ?? '';
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 10;
$filters = ['created_by' => (int)$user['id']];
if (!empty($search)) $filters['search'] = $search;
$total = count(filter_opportunities($filters));
$totalPages = ceil($total / $perPage);
$page = min($page, $totalPages ?: 1);
$filters['limit'] = $perPage;
$filters['offset'] = ($page - 1) * $perPage;
$opportunities = filter_opportunities($filters);
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

<?php if (empty($opportunities)): ?>
    <div class="alert alert-info">Nessuna opportunity trovata.</div>
<?php endif; ?>

<?php foreach ($opportunities as $opp): ?>
    <div class="card-soft p-3 mb-2">
        <div class="d-flex justify-content-between align-items-start">
            <div>
                <div class="fw-bold"><?php echo sanitize($opp['first_name'] . ' ' . $opp['last_name']); ?></div>
                <div class="text-muted small"><?php echo sanitize($opp['offer_name']); ?> · <?php echo sanitize($opp['manager_name']); ?></div>
                <div class="small text-muted">Stato: <?php echo sanitize($opp['status']); ?></div>
                <div class="small text-muted">Codice: <?php echo sanitize($opp['opportunity_code']); ?></div>
            </div>
            <div class="text-end">
                <div class="text-muted small"><?php echo sanitize($opp['created_at']); ?></div>
            </div>
        </div>
    </div>
<?php endforeach; ?>

<?php if ($totalPages > 1): ?>
    <nav aria-label="Paginazione segnalazioni">
        <ul class="pagination justify-content-center">
            <?php if ($page > 1): ?>
                <li class="page-item">
                    <a class="page-link" href="?page=<?php echo $page - 1; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>">Precedente</a>
                </li>
            <?php endif; ?>
            <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                    <a class="page-link" href="?page=<?php echo $i; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>"><?php echo $i; ?></a>
                </li>
            <?php endfor; ?>
            <?php if ($page < $totalPages): ?>
                <li class="page-item">
                    <a class="page-link" href="?page=<?php echo $page + 1; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>">Successivo</a>
                </li>
            <?php endif; ?>
        </ul>
    </nav>
<?php endif; ?>

<?php include __DIR__ . '/../includes/layout/footer.php'; ?>
