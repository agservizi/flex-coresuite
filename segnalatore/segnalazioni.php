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
$total = count_segnalazioni($filters);
$totalPages = ceil($total / $perPage);
$page = min($page, $totalPages ?: 1);
$filters['limit'] = $perPage;
$filters['offset'] = ($page - 1) * $perPage;
$segnalazioni = list_segnalazioni($filters);
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

<?php if (empty($segnalazioni)): ?>
    <div class="alert alert-info">Nessuna segnalazione trovata.</div>
<?php endif; ?>

<?php foreach ($segnalazioni as $seg): ?>
    <div class="card-soft p-3 mb-2">
        <div class="d-flex justify-content-between align-items-start">
            <div>
                <div class="fw-bold"><?php echo sanitize($seg['first_name'] . ' ' . $seg['last_name']); ?></div>
                <div class="text-muted small"><?php echo sanitize($seg['offer_name']); ?> · <?php echo sanitize($seg['manager_name']); ?></div>
                <div class="small text-muted">Stato: <?php echo sanitize($seg['status']); ?></div>
                <div class="small text-muted">Doc: <?php echo (int)$seg['doc_count']; ?></div>
                <?php if ($seg['doc_count'] > 0): ?>
                    <div class="d-flex flex-wrap gap-1 mt-1">
                        <?php foreach (get_segnalazione_docs($seg['id']) as $doc): ?>
                            <a class="badge bg-light text-dark" href="/download.php?id=<?php echo (int)$doc['id']; ?>" target="_blank" rel="noopener">Scarica</a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
            <div class="text-end">
                <div class="text-muted small"><?php echo sanitize($seg['created_at']); ?></div>
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
