<?php
require_once __DIR__ . '/../includes/permissions.php';
require_role('installer');
require_once __DIR__ . '/../includes/helpers.php';

$user = current_user();
$offers = get_offers();
$gestori = get_gestori();
$message = null;
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    log_debug('POST data: ' . json_encode($_POST));
    if (!verify_csrf()) {
        $error = 'Sessione scaduta, ricarica la pagina.';
    } else {
        $first = sanitize($_POST['first_name'] ?? '');
        $last = sanitize($_POST['last_name'] ?? '');
        $offerId = (int)($_POST['offer_id'] ?? 0);
        $notes = sanitize($_POST['notes'] ?? '');

        if (!$first || !$last || !$offerId || !$notes) {
            $error = 'Compila tutti i campi obbligatori.';
        } elseif (strlen($first) > 120 || strlen($last) > 120 || strlen($notes) > 500) {
            $error = 'Verifica lunghezza dei campi.';
        } else {
            log_debug('Saving opportunity for installer ' . $user['id'] . ', offer ' . $offerId . ', first=' . $first . ', last=' . $last . ', notes=' . substr($notes, 0, 50));
            try {
                $created = add_opportunity([
                    'first_name' => $first,
                    'last_name' => $last,
                    'notes' => $notes,
                    'offer_id' => $offerId,
                    'installer_id' => $user['id'],
                    'installer_name' => $user['name'],
                ]);
                log_debug('Opportunity created successfully: ' . ($created['opportunity_code'] ?? 'no code'));
                $created['installer_email'] = $user['email'] ?? '';
                notify_new_opportunity_email($created);
                $adminSubs = get_admin_push_subscriptions();
                send_push_notification($adminSubs, 'Nuova opportunity', 'Un installer ha inviato una nuova segnalazione.');
                $message = 'Opportunity creata con successo';
                header('Location: /installer/opportunities.php?success=1');
                exit;
            } catch (Throwable $e) {
                log_debug('Error saving opportunity: ' . $e->getMessage());
                $error = 'Errore durante il salvataggio. ' . $e->getMessage();
            }
        }
    }
}

$pageTitle = 'Nuova opportunity';
$bottomNav = '
    <a class="nav-pill" href="/installer/dashboard.php"><span class="dot"></span><span>Home</span></a>
    <a class="nav-pill active" href="/installer/new_opportunity.php"><span class="dot"></span><span>Nuova</span></a>
    <a class="nav-pill" href="/installer/opportunities.php"><span class="dot"></span><span>Lista</span></a>
    <a class="nav-pill" href="/installer/report.php"><span class="dot"></span><span>Report</span></a>
';
include __DIR__ . '/../includes/layout/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <div class="bite">Installer</div>
        <h1 class="h5 fw-bold mb-0">Nuova opportunity</h1>
    </div>
    <a class="btn btn-outline-secondary btn-sm" href="/auth/logout.php">Logout</a>
</div>
<div class="card-soft p-3">
    <div class="d-flex justify-content-between align-items-center mb-2">
        <div>
            <div class="bite">Inserimento rapido</div>
            <h1 class="h5 fw-bold mb-0">Nuova opportunity</h1>
        </div>
        <span class="badge bg-secondary">Stato: In attesa</span>
    </div>
    <?php if ($message): ?>
            <div data-flash data-type="success" data-title="OK" data-flash="<?php echo sanitize($message); ?>"></div>
    <?php endif; ?>
    <?php if ($error): ?>
            <div data-flash data-type="error" data-title="Errore" data-flash="<?php echo sanitize($error); ?>"></div>
    <?php endif; ?>

    <form method="post" class="needs-validation" novalidate>
        <?php echo csrf_field(); ?>
        <div class="form-floating mb-3">
            <input type="text" class="form-control" id="first_name" name="first_name" placeholder="Nome" required>
            <label for="first_name">Nome</label>
        </div>
        <div class="form-floating mb-3">
            <input type="text" class="form-control" id="last_name" name="last_name" placeholder="Cognome" required>
            <label for="last_name">Cognome</label>
        </div>
        <div class="form-floating mb-3 position-relative">
            <select class="visually-hidden position-absolute" style="opacity:0; height:0; width:0; pointer-events:none;" id="offer_id" name="offer_id" data-offer-select data-native-select required>
                <option value="">Seleziona offerta</option>
                <?php foreach ($offers as $offer): ?>
                    <option value="<?php echo $offer['id']; ?>"><?php echo sanitize($offer['name'] . ' · € ' . number_format($offer['commission'], 2, ',', '.')); ?></option>
                <?php endforeach; ?>
            </select>
            <input type="text" class="form-control offer-picker-trigger" id="offer_display" placeholder="Tipologia prodotto" readonly data-offer-picker-trigger data-offer-label value="Seleziona offerta">
            <label for="offer_display">Tipologia prodotto</label>
        </div>
        <div class="form-floating mb-3">
            <textarea class="form-control" placeholder="Note" id="notes" name="notes" style="height:120px;" required></textarea>
            <label for="notes">Campo descrittivo</label>
        </div>
        <button class="btn btn-primary w-100 btn-pill">Salva opportunity</button>
    </form>
</div>
<div class="sheet-backdrop" data-offer-picker-backdrop></div>
<div class="sheet" data-offer-picker>
    <div class="sheet-handle"></div>
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div class="fw-bold">Scegli tipologia prodotto</div>
        <button type="button" class="btn btn-sm btn-outline-secondary" data-offer-picker-close>Chiudi</button>
    </div>
    <div class="list-group">
        <?php foreach ($offers as $offer): ?>
            <button type="button"
                    class="list-group-item list-group-item-action d-flex justify-content-between align-items-center offer-option"
                    data-offer-option
                    data-id="<?php echo $offer['id']; ?>"
                    data-label="<?php echo sanitize($offer['name'] . ' · € ' . number_format($offer['commission'], 2, ',', '.')); ?>">
                <div>
                    <div class="fw-semibold"><?php echo sanitize($offer['name']); ?></div>
                    <div class="small text-muted"><?php echo sanitize($offer['manager_name']); ?></div>
                </div>
                <div class="fw-bold text-primary">€ <?php echo number_format($offer['commission'], 2, ',', '.'); ?></div>
            </button>
        <?php endforeach; ?>
    </div>
</div>
<?php include __DIR__ . '/../includes/layout/footer.php'; ?>
