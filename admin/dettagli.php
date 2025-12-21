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

$message = null;
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        $error = 'Sessione scaduta, ricarica la pagina.';
    } else {
        if (isset($_POST['action']) && $_POST['action'] === 'add_comment') {
            $comment = sanitize($_POST['comment'] ?? '');
            if ($comment) {
                add_opportunity_comment($id, $comment, current_user()['id']);
                $message = 'Commento aggiunto';
            } else {
                $error = 'Commento vuoto';
            }
        } elseif (isset($_POST['action']) && $_POST['action'] === 'update_details') {
            $phone = sanitize($_POST['phone'] ?? '');
            $address = sanitize($_POST['address'] ?? '');
            $city = sanitize($_POST['city'] ?? '');
            $notes = sanitize($_POST['notes'] ?? '');
            $pdo = db();
            $stmt = $pdo->prepare('UPDATE opportunities SET phone = :phone, address = :address, city = :city, notes = :notes WHERE id = :id');
            $stmt->execute([
                'phone' => $phone,
                'address' => $address,
                'city' => $city,
                'notes' => $notes,
                'id' => $id,
            ]);
            $message = 'Dettagli aggiornati';
            $op = get_opportunity($id); // refresh
        } elseif (isset($_POST['op_id'])) {
            // existing status update
            $newStatus = $_POST['status'] ?? '';
            if (in_array($newStatus, [STATUS_PENDING, STATUS_OK, STATUS_KO], true)) {
                $change = update_opportunity_status($id, $newStatus, current_user()['id']);
                if ($change['changed']) {
                    $message = 'Stato aggiornato';
                }
                $op = get_opportunity($id); // refresh
            } else {
                $error = 'Stato non valido';
            }
        }
    }
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
<?php if ($message): ?>
    <div data-flash data-type="success" data-title="OK" data-flash="<?php echo sanitize($message); ?>"></div>
<?php endif; ?>
<?php if ($error): ?>
    <div data-flash data-type="error" data-title="Errore" data-flash="<?php echo sanitize($error); ?>"></div>
<?php endif; ?>

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
        <?php if ($op['offer_id'] > 0): ?>
        <div class="col-6">
            <div class="small text-muted">Commissione</div>
            <div class="fw-semibold">€ <?php echo number_format((float)$op['commission'], 2, ',', '.'); ?></div>
        </div>
        <?php endif; ?>
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
            <div class="fw-semibold">
                <?php echo sanitize($op['address']); ?><?php if (!empty($op['city'])): ?>, <?php echo sanitize($op['city']); ?><?php endif; ?>
                <?php if (!empty($op['address'])): ?>
                    <a href="https://www.google.com/maps/search/?api=1&query=<?php echo urlencode($op['address'] . ', ' . $op['city']); ?>" target="_blank" class="ms-2 small">
                        <i class="bi bi-geo-alt"></i> Mappa
                    </a>
                <?php endif; ?>
            </div>
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

<div class="card-soft p-3 mb-3">
    <div class="d-flex justify-content-between align-items-center">
        <div class="bite">Modifica Dettagli</div>
        <button class="btn btn-outline-primary btn-sm" type="button" data-bs-toggle="collapse" data-bs-target="#editDetails" aria-expanded="false">
            Modifica
        </button>
    </div>
    <div class="collapse mt-3" id="editDetails">
        <form method="post">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="action" value="update_details">
            <input type="hidden" name="op_id" value="<?php echo $op['id']; ?>">
            <div class="row g-3">
                <div class="col-6">
                    <label class="form-label">Telefono</label>
                    <input type="text" class="form-control" name="phone" value="<?php echo sanitize($op['phone'] ?? ''); ?>">
                </div>
                <div class="col-6">
                    <label class="form-label">Indirizzo</label>
                    <input type="text" class="form-control" name="address" value="<?php echo sanitize($op['address'] ?? ''); ?>">
                </div>
                <div class="col-6">
                    <label class="form-label">Città</label>
                    <input type="text" class="form-control" name="city" value="<?php echo sanitize($op['city'] ?? ''); ?>">
                </div>
                <div class="col-12">
                    <label class="form-label">Note</label>
                    <textarea class="form-control" name="notes" rows="3"><?php echo sanitize($op['notes'] ?? ''); ?></textarea>
                </div>
                <div class="col-12">
                    <button type="submit" class="btn btn-primary">Salva Modifiche</button>
                </div>
            </div>
        </form>
    </div>
</div>

<div class="card-soft p-3 mb-3">
    <div class="bite">Commenti Interni</div>
    <form method="post" class="mb-3">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="action" value="add_comment">
        <input type="hidden" name="op_id" value="<?php echo $op['id']; ?>">
        <div class="input-group">
            <input type="text" class="form-control" name="comment" placeholder="Aggiungi commento..." required>
            <button class="btn btn-outline-primary" type="submit">Invia</button>
        </div>
    </form>
    <?php $comments = get_opportunity_comments($op['id']); ?>
    <?php if (!empty($comments)): ?>
        <div class="list-group list-compact">
            <?php foreach ($comments as $comment): ?>
                <div class="list-group-item">
                    <div class="fw-semibold small"><?php echo sanitize($comment['user_name']); ?> · <?php echo sanitize($comment['created_at']); ?></div>
                    <div><?php echo nl2br(sanitize($comment['comment'])); ?></div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php $audit = get_opportunity_audit($op['id']); ?>
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
    <div class="d-flex gap-2">
        <button class="btn btn-outline-secondary btn-sm" onclick="window.print()">Stampa</button>
        <a href="opportunities.php" class="btn btn-outline-secondary">Torna alla lista</a>
    </div>
</div>

<?php include __DIR__ . '/../includes/layout/footer.php'; ?>