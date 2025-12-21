<?php
require_once __DIR__ . '/../includes/permissions.php';
require_role('installer');
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/data.php';

$user = current_user();
$id = (int)($_GET['id'] ?? 0);
if (!$id) {
    header('Location: opportunities.php');
    exit;
}

$op = get_opportunity($id);
if (!$op || $op['installer_id'] != $user['id']) {
    header('Location: opportunities.php');
    exit;
}

$message = null;
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        $error = 'Sessione scaduta, ricarica la pagina.';
    } else {
        $action = $_POST['action'] ?? '';
        if ($action === 'update_status') {
            $newStatus = $_POST['status'] ?? '';
            if (!in_array($newStatus, ['In attesa', 'OK', 'KO'])) {
                $error = 'Stato non valido.';
            } else {
                try {
                    update_opportunity_status($id, $newStatus, $user['id']);
                    $message = 'Status aggiornato con successo.';
                    $op = get_opportunity($id); // Refresh
                } catch (Throwable $e) {
                    $error = 'Errore nell\'aggiornamento: ' . $e->getMessage();
                }
            }
        } elseif ($action === 'update_details') {
            $phone = sanitize($_POST['phone'] ?? '');
            $address = sanitize($_POST['address'] ?? '');
            $city = sanitize($_POST['city'] ?? '');
            $notes = sanitize($_POST['notes'] ?? '');
            try {
                update_opportunity_details($id, [
                    'phone' => $phone,
                    'address' => $address,
                    'city' => $city,
                    'notes' => $notes,
                ], $user['id']);
                $message = 'Dettagli aggiornati con successo.';
                $op = get_opportunity($id); // Refresh
            } catch (Throwable $e) {
                $error = 'Errore nell\'aggiornamento: ' . $e->getMessage();
            }
        }
    }
}

$pageTitle = 'Dettagli opportunity';
$bottomNav = '
    <a class="nav-pill" href="/installer/dashboard.php"><span class="dot"></span><span>Home</span></a>
    <a class="nav-pill" href="/installer/new_opportunity.php"><span class="dot"></span><span>Nuova</span></a>
    <a class="nav-pill active" href="/installer/opportunities.php"><span class="dot"></span><span>Lista</span></a>
    <a class="nav-pill" href="/installer/report.php"><span class="dot"></span><span>Report</span></a>
';
include __DIR__ . '/../includes/layout/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <div class="bite">Opportunity</div>
        <h1 class="h5 fw-bold mb-0">Dettagli scheda</h1>
        <div class="small text-muted">Codice: <?php echo sanitize($op['opportunity_code']); ?></div>
    </div>
    <div class="d-flex gap-2">
        <a class="btn btn-outline-secondary btn-sm" href="/auth/logout.php">Logout</a>
        <a class="btn btn-primary btn-sm" href="/installer/new_opportunity.php">+ Nuova</a>
    </div>
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
    <?php if ($message): ?>
        <div data-flash data-type="success" data-title="OK" data-flash="<?php echo sanitize($message); ?>"></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div data-flash data-type="error" data-title="Errore" data-flash="<?php echo sanitize($error); ?>"></div>
    <?php endif; ?>

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
            <div class="small text-muted"><i class="bi bi-telephone"></i> Telefono</div>
            <div class="fw-semibold"><?php echo sanitize($op['phone']); ?></div>
        </div>
        <?php endif; ?>
        <?php if (!empty($op['address'])): ?>
        <div class="col-6">
            <div class="small text-muted"><i class="bi bi-geo-alt"></i> Indirizzo</div>
            <div class="fw-semibold"><?php echo sanitize($op['address']); ?><?php if (!empty($op['city'])): ?>, <?php echo sanitize($op['city']); ?><?php endif; ?></div>
        </div>
        <?php endif; ?>
        <?php if (!empty($op['segnalatore_name'])): ?>
        <div class="col-6">
            <div class="small text-muted"><i class="bi bi-person"></i> Segnalatore</div>
            <div class="fw-semibold"><?php echo sanitize($op['segnalatore_name']); ?></div>
        </div>
        <?php endif; ?>
    </div>

    <?php
    $files = [];
    $displayNotes = $op['notes'];
    if (strpos($op['notes'], '|') !== false) {
        $parts = explode('|', $op['notes'], 2);
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
                <?php
                $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
                $isImage = in_array($ext, ['jpg', 'jpeg', 'png', 'gif']);
                ?>
                <?php if ($isImage): ?>
                    <a href="<?php echo sanitize($file); ?>" target="_blank" class="btn btn-outline-primary btn-sm">
                        <i class="bi bi-image"></i> Anteprima
                    </a>
                <?php else: ?>
                    <a href="<?php echo sanitize($file); ?>" target="_blank" class="btn btn-outline-primary btn-sm">
                        <i class="bi bi-file-earmark"></i> Visualizza
                    </a>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- Update Status -->
<div class="card-soft p-3 mb-3">
    <div class="bite">Aggiorna Status</div>
    <form method="post" class="needs-validation" novalidate>
        <?php echo csrf_field(); ?>
        <input type="hidden" name="action" value="update_status">
        <div class="mb-3">
            <label class="form-label">Status attuale: <span class="badge bg-<?php echo $op['status'] === 'OK' ? 'success' : ($op['status'] === 'KO' ? 'danger' : 'warning'); ?>"><?php echo sanitize($op['status']); ?></span></label>
            <select name="status" class="form-select" required>
                <option value="In attesa" <?php echo $op['status'] === 'In attesa' ? 'selected' : ''; ?>>In attesa</option>
                <option value="OK" <?php echo $op['status'] === 'OK' ? 'selected' : ''; ?>>OK</option>
                <option value="KO" <?php echo $op['status'] === 'KO' ? 'selected' : ''; ?>>KO</option>
            </select>
        </div>
        <button type="submit" class="btn btn-primary">Aggiorna Status</button>
    </form>
</div>

<!-- Update Details -->
<div class="card-soft p-3 mb-3">
    <div class="bite">Modifica Dettagli</div>
    <form method="post" class="needs-validation" novalidate>
        <?php echo csrf_field(); ?>
        <input type="hidden" name="action" value="update_details">
        <div class="row g-3">
            <div class="col-6">
                <label class="form-label">Telefono</label>
                <input type="text" name="phone" class="form-control" value="<?php echo sanitize($op['phone'] ?? ''); ?>">
            </div>
            <div class="col-6">
                <label class="form-label">Indirizzo</label>
                <input type="text" name="address" class="form-control" value="<?php echo sanitize($op['address'] ?? ''); ?>">
            </div>
            <div class="col-6">
                <label class="form-label">Città</label>
                <input type="text" name="city" class="form-control" value="<?php echo sanitize($op['city'] ?? ''); ?>">
            </div>
            <div class="col-12">
                <label class="form-label">Note</label>
                <textarea name="notes" class="form-control" rows="3"><?php echo sanitize($displayNotes); ?></textarea>
            </div>
        </div>
        <button type="submit" class="btn btn-primary mt-3">Salva Modifiche</button>
    </form>
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
                        <?php echo sanitize($entry['changed_by_name'] ?? 'Sistema'); ?> · <?php echo time_ago($entry['changed_at']); ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<div class="d-grid gap-2">
    <div class="d-flex gap-2 flex-wrap">
        <?php if (!empty($op['phone'])): ?>
            <a href="tel:<?php echo sanitize($op['phone']); ?>" class="btn btn-outline-primary btn-sm">
                <i class="bi bi-telephone"></i> Chiama
            </a>
        <?php endif; ?>
        <?php if (!empty($op['address'])): ?>
            <a href="https://maps.google.com/?q=<?php echo urlencode($op['address'] . ', ' . $op['city']); ?>" target="_blank" class="btn btn-outline-primary btn-sm">
                <i class="bi bi-geo-alt"></i> Naviga
            </a>
        <?php endif; ?>
        <button class="btn btn-outline-secondary btn-sm" onclick="navigator.share({title: 'Opportunity <?php echo sanitize($op['opportunity_code']); ?>', text: 'Cliente: <?php echo sanitize($op['first_name'] . ' ' . $op['last_name']); ?>'})" style="display: none;" id="shareBtn">
            <i class="bi bi-share"></i> Condividi
        </button>
        <a href="opportunities.php" class="btn btn-outline-secondary">Torna alla lista</a>
    </div>
</div>

<script>
if (navigator.share) {
    document.getElementById('shareBtn').style.display = 'inline-block';
}
</script>

<?php include __DIR__ . '/../includes/layout/footer.php'; ?>