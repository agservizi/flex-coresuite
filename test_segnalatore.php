<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/data.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/permissions.php';

// Simula sessione segnalatore
session_start();
$_SESSION['user'] = [
    'id' => 10, // ID esistente
    'name' => 'Test Segnalatore',
    'email' => 'segnalatore@test.com',
    'role' => 'segnalatore'
];

// Simula dati POST
$_POST = [
    'first_name' => 'Luca',
    'last_name' => 'Verdi',
    'iban' => 'IT1234567890123456789012345',
    'offer_id' => '25', // ID esistente
];

// Simula FILES (vuoto per test, ma il codice richiede almeno un file)
$_FILES = [
    'docs' => [
        'name' => ['test.pdf'],
        'type' => ['application/pdf'],
        'tmp_name' => ['/tmp/test.pdf'], // Simula un file esistente
        'error' => [0],
        'size' => [1024]
    ]
];

// Simula il codice di new_opportunity.php per segnalatore
$user = current_user();
$message = null;
$error = null;

$first = sanitize($_POST['first_name'] ?? '');
$last = sanitize($_POST['last_name'] ?? '');
$iban = sanitize($_POST['iban'] ?? '');
$offerId = (int)($_POST['offer_id'] ?? 0);

// Simula controllo documenti (saltiamo upload per test)
$hasDocs = !empty($_FILES['docs']['name'][0]);

if (!$first || !$last || !$offerId || !$hasDocs) {
    $error = 'Compila tutti i campi obbligatori (nome, cognome, offerta, documenti).';
} elseif (strlen($first) > 120 || strlen($last) > 120) {
    $error = 'Verifica lunghezza dei campi.';
} else {
    // Simula notes
    $notes = 'Da segnalatore';
    if (!empty($iban)) {
        $notes .= ' - IBAN: ' . $iban;
    }
    $notes .= ' - Documenti caricati: 1'; // Simula
    $fileData = json_encode(['/uploads/segnalatore/test.pdf']); // Simula

    try {
        $opp = add_opportunity([
            'first_name' => $first,
            'last_name' => $last,
            'offer_id' => $offerId,
            'notes' => $notes,
            'created_by' => (int)$user['id'],
        ]);

        // Simula update notes con file
        $pdo = db();
        $pdo->prepare('UPDATE opportunities SET notes = CONCAT(notes, ?) WHERE id = ?')
            ->execute(['|' . $fileData, $opp['id']]);

        $message = 'Opportunity creata (#' . $opp['opportunity_code'] . ')';

        // Simula notifiche
        $admins = get_admins();
        foreach ($admins as $adm) {
            // create_notification((int)$adm['id'], 'Nuova segnalazione', $first . ' ' . $last, 'info');
        }
        $adminSubs = get_admin_push_subscriptions();
        send_push_notification($adminSubs, 'Nuova segnalazione', $first . ' ' . $last);

    } catch (Throwable $e) {
        $error = 'Errore: ' . $e->getMessage();
    }
}

if ($error) {
    echo "Errore: $error\n";
} elseif ($message) {
    echo "Successo: $message\n";
    echo "ID: " . $opp['id'] . "\n";
    echo "Codice: " . $opp['opportunity_code'] . "\n";
} else {
    echo "Nessuna azione\n";
}
?>