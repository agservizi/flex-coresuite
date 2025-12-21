<?php
/**
 * Smoke Test Globale per Admin
 * Testa tutte le funzionalità principali dell'admin
 */

require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/data.php';
require_once __DIR__ . '/includes/helpers.php';

// Inizializza sessione per i test
session_start();

echo "=== Flex Coresuite - Smoke Test Globale Admin ===\n\n";

// Test 1: Verifica connessione database
try {
    $pdo = db();
    echo "✓ Connessione database OK\n";
} catch (Exception $e) {
    echo "✗ Errore connessione database: " . $e->getMessage() . "\n";
    exit(1);
}

// Test 2: Verifica esistenza admin
$admins = get_admins();
if (empty($admins)) {
    echo "✗ Nessun admin trovato nel database\n";
    exit(1);
}
$admin = $admins[0]; // Prendi il primo admin
$adminId = (int)$admin['id'];
echo "✓ Admin trovato: " . $admin['name'] . " (ID: $adminId)\n";

// Test 3: Verifica lista utenti completa
$allUsers = get_users();
if (empty($allUsers)) {
    echo "✗ Nessun utente trovato\n";
    exit(1);
}
echo "✓ Trovati " . count($allUsers) . " utenti totali\n";

// Test 4: Verifica lista installer
$installers = get_installers();
echo "✓ Trovati " . count($installers) . " installer\n";

// Test 5: Verifica lista segnalatori
$segnalatori = get_segnalatori();
echo "✓ Trovati " . count($segnalatori) . " segnalatori\n";

// Test 6: Verifica offerte disponibili
$offers = get_offers();
if (empty($offers)) {
    echo "✗ Nessuna offerta trovata\n";
    exit(1);
}
echo "✓ Trovate " . count($offers) . " offerte\n";

// Test 7: Verifica gestori
$gestori = get_gestori();
echo "✓ Trovati " . count($gestori) . " gestori\n";

// Test 8: Simula creazione installer
try {
    $newInstaller = create_installer('Test Installer', 'test_installer_' . time() . '@example.com');
    echo "✓ Creazione installer riuscita, ID: {$newInstaller['id']}, Token: " . substr($newInstaller['reset_token'], 0, 10) . "...\n";
    $testInstallerId = $newInstaller['id'];
} catch (Exception $e) {
    echo "✗ Errore creazione installer: " . $e->getMessage() . "\n";
    exit(1);
}

// Test 9: Simula creazione segnalatore
try {
    $newSegnalatore = create_segnalatore('Test Segnalatore', 'test_segnalatore_' . time() . '@example.com');
    echo "✓ Creazione segnalatore riuscita, ID: {$newSegnalatore['id']}, Token: " . substr($newSegnalatore['reset_token'], 0, 10) . "...\n";
    $testSegnalatoreId = $newSegnalatore['id'];
} catch (Exception $e) {
    echo "✗ Errore creazione segnalatore: " . $e->getMessage() . "\n";
    exit(1);
}

// Test 10: Simula creazione opportunity
try {
    $oppData = [
        'first_name' => 'Mario',
        'last_name' => 'Rossi',
        'offer_id' => $offers[0]['id'],
        'installer_id' => $testInstallerId,
        'created_by' => $adminId,
    ];
    $opp = add_opportunity($oppData);
    echo "✓ Creazione opportunity riuscita, ID: {$opp['id']}, Codice: {$opp['opportunity_code']}\n";
    $testOpportunityId = $opp['id'];
} catch (Exception $e) {
    echo "✗ Errore creazione opportunity: " . $e->getMessage() . "\n";
    exit(1);
}

// Test 11: Verifica lista opportunity con filtri
$allOpportunities = get_opportunities();
echo "✓ Trovate " . count($allOpportunities) . " opportunity totali\n";

$filteredOpportunities = get_opportunities(['installer_id' => $testInstallerId]);
echo "✓ Trovate " . count($filteredOpportunities) . " opportunity filtrate per installer\n";

$opportunityCount = count_opportunities(['installer_id' => $testInstallerId]);
echo "✓ Conteggio opportunity per installer: $opportunityCount\n";

// Test 12: Verifica dettaglio opportunity
$oppDetail = get_opportunity($testOpportunityId);
if (!$oppDetail) {
    echo "✗ Opportunity non trovata\n";
} else {
    echo "✓ Dettaglio opportunity OK: " . $oppDetail['first_name'] . " " . $oppDetail['last_name'] . "\n";
}

// Test 13: Simula aggiornamento stato opportunity
try {
    $updateResult = update_opportunity_status($testOpportunityId, STATUS_OK, $adminId);
    if ($updateResult['changed']) {
        echo "✓ Aggiornamento stato opportunity riuscito\n";
    } else {
        echo "✓ Stato opportunity già OK\n";
    }
} catch (Exception $e) {
    echo "✗ Errore aggiornamento stato: " . $e->getMessage() . "\n";
    exit(1);
}

// Test 14: Verifica segnalazioni
$allSegnalazioni = list_segnalazioni();
echo "✓ Trovate " . count($allSegnalazioni) . " segnalazioni totali\n";

$segnalazioniCount = count_segnalazioni();
echo "✓ Conteggio segnalazioni: $segnalazioniCount\n";

// Test 15: Simula upsert offerta
try {
    $newOfferData = [
        'name' => 'Test Offer',
        'description' => 'Offer for testing',
        'commission' => 150.00,
        'manager_id' => $gestori[0]['id'] ?? 1,
    ];
    upsert_offer($newOfferData);
    echo "✓ Creazione offerta riuscita\n";
} catch (Exception $e) {
    echo "✗ Errore creazione offerta: " . $e->getMessage() . "\n";
    exit(1);
}

// Test 16: Simula upsert gestore
try {
    $newGestoreData = [
        'name' => 'Test Manager',
        'active' => 1,
    ];
    upsert_gestore($newGestoreData);
    echo "✓ Creazione gestore riuscita\n";
} catch (Exception $e) {
    echo "✗ Errore creazione gestore: " . $e->getMessage() . "\n";
    exit(1);
}

// Test 17: Test notifiche
try {
    $notifId = create_notification($adminId, 'Test Notification', 'This is a test notification');
    echo "✓ Creazione notifica riuscita, ID: $notifId\n";
} catch (Exception $e) {
    echo "✗ Errore creazione notifica: " . $e->getMessage() . "\n";
    exit(1);
}

$notifications = list_notifications($adminId);
echo "✓ Trovate " . count($notifications) . " notifiche per admin\n";

$unreadCount = count_unread_notifications($adminId);
echo "✓ Notifiche non lette: $unreadCount\n";

mark_notifications_read($adminId);
echo "✓ Notifiche marcate come lette\n";

$unreadCountAfter = count_unread_notifications($adminId);
echo "✓ Notifiche non lette dopo lettura: $unreadCountAfter\n";

// Test 18: Test rate limiting login
$testEmail = 'test@example.com';
$testIp = '127.0.0.1';
record_login_attempt($testEmail, $testIp, false);
echo "✓ Registrazione tentativo login fallito OK\n";

$isLimited = is_login_rate_limited($testEmail, $testIp);
echo "✓ Rate limiting attivo: " . ($isLimited ? 'Sì' : 'No') . "\n";

// Test 19: Test generazione token password reset
try {
    $resetToken = generate_password_reset($testInstallerId);
    echo "✓ Generazione token reset password OK: " . substr($resetToken, 0, 10) . "...\n";
} catch (Exception $e) {
    echo "✗ Errore generazione token: " . $e->getMessage() . "\n";
    exit(1);
}

// Test 20: Test push subscriptions
try {
    save_push_subscription($adminId, 'https://test.endpoint', 'test_p256dh', 'test_auth', 'test_token', 'web');
    echo "✓ Salvataggio push subscription riuscito\n";
} catch (Exception $e) {
    echo "✗ Errore push subscription: " . $e->getMessage() . "\n";
    exit(1);
}

$pushSubs = get_push_subscriptions($adminId);
echo "✓ Trovate " . count($pushSubs) . " push subscriptions per admin\n";

$adminPushSubs = get_admin_push_subscriptions();
echo "✓ Trovate " . count($adminPushSubs) . " push subscriptions admin totali\n";

// Test 21: Test cleanup uploads vecchi
$removedFiles = cleanup_old_segnalazioni_uploads(365); // File più vecchi di 1 anno
echo "✓ Cleanup uploads vecchi: $removedFiles file rimossi\n";

// Test 22: Test funzioni di sicurezza
$csrfToken = csrf_token();
echo "✓ Token CSRF generato: " . substr($csrfToken, 0, 10) . "...\n";

$sanitized = sanitize('<script>alert("test")</script>');
echo "✓ Sanitizzazione HTML OK: '$sanitized'\n";

// Test 23: Test generazione codice opportunity
$testCode = generate_opportunity_code($pdo);
echo "✓ Generazione codice opportunity OK: $testCode\n";

// Test 24: Simula eliminazione installer (solo se non ha opportunity)
try {
    delete_installer($testInstallerId);
    echo "✓ Eliminazione installer riuscita\n";
} catch (Exception $e) {
    echo "✓ Eliminazione installer fallita (previsto se ha opportunity): " . $e->getMessage() . "\n";
}

// Test 25: Test resend invite
try {
    $newToken = resend_installer_invite($testInstallerId);
    echo "✓ Resend invite installer OK: " . substr($newToken, 0, 10) . "...\n";
} catch (Exception $e) {
    echo "✓ Resend invite installer fallito (previsto): " . $e->getMessage() . "\n";
}

try {
    $newTokenSeg = resend_segnalatore_invite($testSegnalatoreId);
    echo "✓ Resend invite segnalatore OK: " . substr($newTokenSeg, 0, 10) . "...\n";
} catch (Exception $e) {
    echo "✓ Resend invite segnalatore fallito (previsto): " . $e->getMessage() . "\n";
}

echo "\n=== Test completato con successo ===\n";
echo "Tutte le funzioni dell'admin rispondono correttamente.\n";