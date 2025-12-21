<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/data.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/permissions.php';

// Simula sessione
session_start();
$_SESSION['user'] = [
    'id' => 1, // Assumi un ID valido
    'name' => 'Test Installer',
    'email' => 'installer@test.com',
    'role' => 'installer'
];

// Simula dati POST
$_POST = [
    'first_name' => 'Mario',
    'last_name' => 'Rossi',
    'offer_id' => '25', // ID esistente
    'notes' => 'Test opportunity',
];

// Simula il codice
$user = current_user();
$message = null;
$error = null;

$first = sanitize($_POST['first_name'] ?? '');
$last = sanitize($_POST['last_name'] ?? '');
$offerId = (int)($_POST['offer_id'] ?? 0);
$notes = sanitize($_POST['notes'] ?? '');

if (!$first || !$last || !$offerId || !$notes) {
    $error = 'Compila tutti i campi obbligatori.';
} elseif (strlen($first) > 120 || strlen($last) > 120 || strlen($notes) > 500) {
    $error = 'Verifica lunghezza dei campi.';
} else {
    try {
        $created = add_opportunity([
            'first_name' => $first,
            'last_name' => $last,
            'notes' => $notes,
            'offer_id' => $offerId,
            'installer_id' => $user['id'],
            'installer_name' => $user['name'],
        ]);
        $created['installer_email'] = $user['email'] ?? '';
        $message = 'Opportunity creata con successo';
    } catch (Throwable $e) {
        $error = 'Errore durante il salvataggio. ' . $e->getMessage();
    }
}

if ($error) {
    echo "Errore: $error\n";
} elseif ($message) {
    echo "Successo: $message\n";
    echo "ID: " . $created['id'] . "\n";
    echo "Codice: " . $created['opportunity_code'] . "\n";
} else {
    echo "Nessuna azione\n";
}
?>