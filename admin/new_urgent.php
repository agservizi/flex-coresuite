<?php
require_once __DIR__ . '/../includes/permissions.php';
require_role('admin');
require_once __DIR__ . '/../includes/helpers.php';

$users = get_users();
$gestori = get_gestori();
$message = null;
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        $error = 'Sessione scaduta, ricarica la pagina.';
    } else {
        $first = sanitize($_POST['first_name'] ?? '');
        $last = sanitize($_POST['last_name'] ?? '');
        $phone = sanitize($_POST['phone'] ?? '');
        $address = sanitize($_POST['address'] ?? '');
        $city = sanitize($_POST['city'] ?? '');
        $managerId = (int)($_POST['manager_id'] ?? 0);
        $installerId = (int)($_POST['installer_id'] ?? 0);

        if (!$first || !$last || !$phone || !$address || !$city || !$managerId || !$installerId) {
            $error = 'Compila tutti i campi obbligatori.';
        } else {
            try {
                $opp = add_opportunity([
                    'first_name' => $first,
                    'last_name' => $last,
                    'phone' => $phone,
                    'address' => $address,
                    'city' => $city,
                    'notes' => 'Urgente fibra',
                    'offer_id' => 0, // No offer for urgent
                    'commission' => 0.00, // No commission for urgent
                    'installer_id' => $installerId,
                    'manager_id' => $managerId,
                ]);

                // Notifica installer
                create_notification($installerId, 'Nuova segnalazione urgente', "Cliente: $first $last - Cell: $phone - $address, $city", 'warning');
                $subs = get_push_subscriptions($installerId);
                send_push_notification($subs, 'Nuova segnalazione urgente', "Cliente: $first $last - $address, $city");

                // Email all'installer
                $installer = array_find($users, fn($u) => $u['id'] == $installerId);
                if ($installer && !empty($installer['email'])) {
                    $subject = 'Nuova segnalazione urgente #' . $opp['opportunity_code'];
                    $body = '<p>Ciao ' . htmlspecialchars($installer['name']) . ',</p>'
                        . '<p>Hai ricevuto una nuova segnalazione urgente.</p>'
                        . '<table style="border-collapse:collapse;width:100%;font-size:14px;">'
                        . '<tr><td style="padding:6px 0;color:#6c757d;">Codice</td><td style="padding:6px 0;font-weight:600;">' . htmlspecialchars($opp['opportunity_code']) . '</td></tr>'
                        . '<tr><td style="padding:6px 0;color:#6c757d;">Cliente</td><td style="padding:6px 0;font-weight:600;">' . htmlspecialchars("$first $last") . '</td></tr>'
                        . '<tr><td style="padding:6px 0;color:#6c757d;">Cellulare</td><td style="padding:6px 0;">' . htmlspecialchars($phone) . '</td></tr>'
                        . '<tr><td style="padding:6px 0;color:#6c757d;">Indirizzo</td><td style="padding:6px 0;">' . htmlspecialchars("$address, $city") . '</td></tr>'
                        . '</table>'
                        . '<p style="color:#6c757d;font-size:13px;">Inviato il ' . strftime('%d/%m/%Y %H:%M') . '.</p>';

                    $html = render_email_wrapper('Nuova segnalazione urgente', $body, null, null, APP_NAME . ' · ' . (getenv('COMPANY_NAME') ?: ''));

                    $text = "Ciao {$installer['name']},\n"
                        . 'Nuova segnalazione urgente' . "\n"
                        . 'Codice: ' . $opp['opportunity_code'] . "\n"
                        . 'Cliente: ' . "$first $last" . "\n"
                        . 'Cellulare: ' . $phone . "\n"
                        . 'Indirizzo: ' . "$address, $city" . "\n"
                        . 'Inviato il: ' . strftime('%d/%m/%Y %H:%M');

                    send_resend_email($installer['email'], $subject, $html, $text);
                }

                $message = 'Segnalazione urgente inviata (#' . $opp['opportunity_code'] . ')';

                // Redirect alla lista opportunity
                header("Location: /admin/opportunities.php");
                exit;
            } catch (Throwable $e) {
                $error = 'Errore: ' . $e->getMessage();
            }
        }
    }
}

$pageTitle = 'Nuova segnalazione urgente';
$bottomNav = '
    <a class="nav-pill" href="/admin/dashboard.php"><span class="dot"></span><span>Home</span></a>
    <a class="nav-pill" href="/admin/opportunities.php"><span class="dot"></span><span>Opportunity</span></a>
    <a class="nav-pill active" href="/admin/new_urgent.php"><span class="dot"></span><span>Urgente</span></a>
    <a class="nav-pill" href="/admin/report.php"><span class="dot"></span><span>Report</span></a>
    <a class="nav-pill" href="/admin/settings.php"><span class="dot"></span><span>Impostazioni</span></a>
';
include __DIR__ . '/../includes/layout/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <div class="bite">Admin</div>
        <h1 class="h5 fw-bold mb-0">Nuova segnalazione urgente</h1>
    </div>
    <a class="btn btn-outline-secondary btn-sm" href="/auth/logout.php">Logout</a>
</div>

<?php if ($message): ?>
    <div class="alert alert-success"><?php echo sanitize($message); ?></div>
<?php endif; ?>
<?php if ($error): ?>
    <div class="alert alert-danger"><?php echo sanitize($error); ?></div>
<?php endif; ?>

<form method="post">
    <?php echo csrf_field(); ?>
    <div class="row g-3">
        <div class="col-md-6">
            <label class="form-label">Nome *</label>
            <input type="text" name="first_name" class="form-control" required>
        </div>
        <div class="col-md-6">
            <label class="form-label">Cognome *</label>
            <input type="text" name="last_name" class="form-control" required>
        </div>
        <div class="col-md-6">
            <label class="form-label">Cellulare *</label>
            <input type="tel" name="phone" class="form-control" inputmode="numeric" required>
        </div>
        <div class="col-md-6">
            <label class="form-label">Indirizzo *</label>
            <input type="text" name="address" class="form-control" required>
        </div>
        <div class="col-md-6">
            <label class="form-label">Città *</label>
            <input type="text" name="city" class="form-control" required>
        </div>
        <div class="col-md-6 position-relative">
            <select class="visually-hidden position-absolute" style="opacity:0; height:0; width:0; pointer-events:none;" id="manager_id" name="manager_id" data-manager-select data-native-select required>
                <option value="">Seleziona gestore</option>
                <?php foreach ($gestori as $g): ?>
                    <option value="<?php echo $g['id']; ?>"><?php echo sanitize($g['name']); ?></option>
                <?php endforeach; ?>
            </select>
            <input type="text" class="form-control manager-picker-trigger" id="manager_display" placeholder="Gestore" readonly data-manager-picker-trigger data-manager-label value="Seleziona gestore" required>
            <label for="manager_display">Gestore *</label>
        </div>
        <div class="col-12 position-relative">
            <select class="visually-hidden position-absolute" style="opacity:0; height:0; width:0; pointer-events:none;" id="installer_id" name="installer_id" data-installer-select data-native-select required>
                <option value="">Seleziona installer</option>
                <?php foreach ($users as $u): if ($u['role'] !== 'installer') continue; ?>
                    <option value="<?php echo $u['id']; ?>"><?php echo sanitize($u['name']); ?></option>
                <?php endforeach; ?>
            </select>
            <input type="text" class="form-control installer-picker-trigger" id="installer_display" placeholder="Installer" readonly data-installer-picker-trigger data-installer-label value="Seleziona installer" required>
            <label for="installer_display">Installer *</label>
        </div>
        <div class="col-12 d-flex justify-content-end">
            <button type="submit" class="btn btn-primary">Invia segnalazione urgente</button>
        </div>
    </div>
</form>
<div class="sheet-backdrop" data-manager-picker-backdrop></div>
<div class="sheet" data-manager-picker>
    <div class="sheet-handle"></div>
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div class="fw-bold">Scegli gestore</div>
        <button type="button" class="btn btn-sm btn-outline-secondary" data-manager-picker-close>Chiudi</button>
    </div>
    <div class="list-group">
        <?php foreach ($gestori as $g): ?>
            <button type="button"
                    class="list-group-item list-group-item-action d-flex justify-content-between align-items-center manager-option"
                    data-manager-option
                    data-id="<?php echo $g['id']; ?>"
                    data-label="<?php echo sanitize($g['name']); ?>">
                <div class="fw-semibold"><?php echo sanitize($g['name']); ?></div>
            </button>
        <?php endforeach; ?>
    </div>
</div>
<div class="sheet-backdrop" data-installer-picker-backdrop></div>
<div class="sheet" data-installer-picker>
    <div class="sheet-handle"></div>
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div class="fw-bold">Scegli installer</div>
        <button type="button" class="btn btn-sm btn-outline-secondary" data-installer-picker-close>Chiudi</button>
    </div>
    <div class="list-group">
        <?php foreach ($users as $u): if ($u['role'] !== 'installer') continue; ?>
            <button type="button"
                    class="list-group-item list-group-item-action d-flex justify-content-between align-items-center installer-option"
                    data-installer-option
                    data-id="<?php echo $u['id']; ?>"
                    data-label="<?php echo sanitize($u['name']); ?>">
                <div class="fw-semibold"><?php echo sanitize($u['name']); ?></div>
            </button>
        <?php endforeach; ?>
    </div>
</div>
<?php include __DIR__ . '/../includes/layout/footer.php'; ?>