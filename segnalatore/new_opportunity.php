<?php
require_once __DIR__ . '/../includes/permissions.php';
require_role('segnalatore');
require_once __DIR__ . '/../includes/helpers.php';

$user = current_user();
$offers = get_offers();
$message = null;
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        $error = 'Sessione scaduta, ricarica la pagina.';
    } else {
        $first = sanitize($_POST['first_name'] ?? '');
        $last = sanitize($_POST['last_name'] ?? '');
        $offerId = (int)($_POST['offer_id'] ?? 0);

        if (!$first || !$last || !$offerId) {
            $error = 'Compila tutti i campi obbligatori.';
        } elseif (strlen($first) > 120 || strlen($last) > 120) {
            $error = 'Verifica lunghezza dei campi.';
        } else {
            try {
                $segId = create_segnalazione([
                    'first_name' => $first,
                    'last_name' => $last,
                    'offer_id' => $offerId,
                ], $_FILES, (int)$user['id']);
                $message = 'Segnalazione inviata (#' . $segId . ')';

                // Notifica admin
                $admins = get_admins();
                foreach ($admins as $adm) {
                    create_notification((int)$adm['id'], 'Nuova segnalazione', $first . ' ' . $last, 'info');
                }
                $adminSubs = get_admin_push_subscriptions();
                send_push_notification($adminSubs, 'Nuova segnalazione', $first . ' ' . $last);
            } catch (Throwable $e) {
                $error = 'Errore: ' . $e->getMessage();
            }
        }
    }
}

$pageTitle = 'Nuova segnalazione';
$bottomNav = '
    <a class="nav-pill" href="/segnalatore/dashboard.php"><span class="dot"></span><span>Home</span></a>
    <a class="nav-pill active" href="/segnalatore/new_opportunity.php"><span class="dot"></span><span>Nuova</span></a>
    <a class="nav-pill" href="/segnalatore/segnalazioni.php"><span class="dot"></span><span>Le mie</span></a>
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
        <div class="bite">Segnalatore</div>
        <h1 class="h5 fw-bold mb-0">Nuova segnalazione</h1>
    </div>
    <div class="d-flex gap-2">
        <span class="text-muted small"><?php echo sanitize($name); ?></span>
        <button class="btn btn-outline-light btn-sm" data-toggle-theme aria-label="Tema">Tema</button>
        <a class="btn btn-outline-secondary btn-sm" href="/auth/logout.php">Logout</a>
    </div>
</div>

<div class="card-soft p-3 mb-3">
    <div class="d-flex justify-content-between align-items-center mb-2">
        <div>
            <div class="bite">Dati cliente</div>
            <h1 class="h6 fw-bold mb-0">Inserisci i dati</h1>
        </div>
    </div>
    <?php if ($message): ?>
        <div data-flash data-type="success" data-title="OK" data-flash="<?php echo sanitize($message); ?>"></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div data-flash data-type="error" data-title="Errore" data-flash="<?php echo sanitize($error); ?>"></div>
    <?php endif; ?>

    <form method="post" enctype="multipart/form-data" class="needs-validation" novalidate>
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
            <select class="visually-hidden position-absolute" style="opacity:0; height:0; width:0; pointer-events:none;" id="offer_id" name="offer_id" data-offer-select data-native-select>
                <option value="">Seleziona offerta</option>
                <?php foreach ($offers as $offer): ?>
                    <option value="<?php echo $offer['id']; ?>"><?php echo sanitize($offer['name']); ?></option>
                <?php endforeach; ?>
            </select>
            <input type="text" class="form-control offer-picker-trigger" id="offer_display" placeholder="Tipologia prodotto" readonly data-offer-picker-trigger data-offer-label value="Seleziona offerta">
            <label for="offer_display">Tipologia prodotto</label>
        </div>

        <div class="mb-3">
            <label class="form-label fw-semibold">Documenti ammessi (CIE, Patente IT, Passaporto, Tessera sanitaria) - foto o PDF</label>
            <input type="file" class="form-control" name="docs[]" id="docs" accept="image/*,application/pdf" multiple data-doc-preview>
            <div class="small text-muted mt-1">Max 5MB ciascuno · puoi usare la fotocamera del telefono per scattare le foto</div>
            <div class="doc-preview mt-2" data-doc-preview-list></div>
        </div>

        <div class="d-grid mt-3">
            <button class="btn btn-primary btn-pill" type="submit">Invia segnalazione</button>
        </div>
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
                    data-label="<?php echo sanitize($offer['name']); ?>">
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
