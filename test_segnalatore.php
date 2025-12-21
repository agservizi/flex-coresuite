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

// Simula FILES (crea un file temporaneo per test)
$tempFile = tempnam(sys_get_temp_dir(), 'test');
file_put_contents($tempFile, '%PDF-1.4 test content'); // Contenuto PDF fittizio
echo "Temp file creato: $tempFile\n";
$_FILES = [
    'docs' => [
        'name' => ['test.pdf'],
        'type' => ['application/pdf'],
        'tmp_name' => [$tempFile],
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

// Simula controllo documenti (con upload reale)
$uploadedFiles = [];
if (!empty($_FILES['docs']['name'][0])) {
    $uploadDir = __DIR__ . '/uploads/segnalatore/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    foreach ($_FILES['docs']['tmp_name'] as $key => $tmpName) {
        if (!empty($tmpName)) {
            $originalName = $_FILES['docs']['name'][$key];
            $fileSize = $_FILES['docs']['size'][$key];
            $fileType = $_FILES['docs']['type'][$key];
            if ($fileSize > 5 * 1024 * 1024) { // 5MB max
                $error = 'File troppo grande (max 5MB).';
                break;
            }
            $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'application/pdf'];
            if (!in_array($fileType, $allowedTypes)) {
                $error = 'Tipo file non supportato.';
                break;
            }
            $fileName = uniqid('doc_', true) . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '', $originalName);
            $filePath = $uploadDir . $fileName;
            if (copy($tmpName, $filePath)) { // Usa copy invece di move_uploaded_file per test
                $uploadedFiles[] = '/uploads/segnalatore/' . $fileName;
                unlink($tmpName); // Rimuovi temp file
            } else {
                $error = 'Errore nel caricamento del file.';
                break;
            }
        }
    }
}

$hasDocs = !empty($uploadedFiles);

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
    $notes .= ' - Documenti caricati: ' . count($uploadedFiles);
    if (!empty($uploadedFiles)) {
        $fileData = json_encode($uploadedFiles);
    } else {
        $fileData = null;
    }

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