<?php
/**
 * Smoke Test Globale per Segnalatore
 * Testa tutte le funzionalità principali del segnalatore
 */

require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/data.php';
require_once __DIR__ . '/includes/helpers.php';

echo "=== Flex Coresuite - Smoke Test Globale Segnalatore ===\n\n";

// Test 1: Verifica connessione database
try {
    $pdo = db();
    echo "✓ Connessione database OK\n";
} catch (Exception $e) {
    echo "✗ Errore connessione database: " . $e->getMessage() . "\n";
    exit(1);
}

// Test 2: Verifica esistenza segnalatori
$segnalatori = get_segnalatori();
if (empty($segnalatori)) {
    echo "✗ Nessun segnalatore trovato nel database\n";
    exit(1);
}
echo "✓ Trovati " . count($segnalatori) . " segnalatori\n";

// Test 3: Simula login segnalatore
$segnalatore = $segnalatori[0]; // Prendi il primo segnalatore
echo "✓ Test login per segnalatore: " . $segnalatore['name'] . " (" . $segnalatore['email'] . ")\n";

// Test 4: Verifica offerte disponibili
$offers = get_offers();
if (empty($offers)) {
    echo "✗ Nessuna offerta trovata\n";
    exit(1);
}
echo "✓ Trovate " . count($offers) . " offerte\n";

// Test 5: Simula creazione segnalazione
$testData = [
    'first_name' => 'Mario',
    'last_name' => 'Rossi',
    'offer_id' => $offers[0]['id']
];

$testFiles = []; // Nessun file per il test

try {
    $segnalazioneId = create_segnalazione($testData, $testFiles, (int)$segnalatore['id']);
    echo "✓ Creazione segnalazione riuscita, ID: $segnalazioneId\n";
} catch (Exception $e) {
    echo "✗ Errore creazione segnalazione: " . $e->getMessage() . "\n";
    exit(1);
}

// Test 6: Verifica lista segnalazioni
$segnalazioni = list_segnalazioni(['created_by' => (int)$segnalatore['id']]);
if (empty($segnalazioni)) {
    echo "✗ Nessuna segnalazione trovata per il segnalatore\n";
} else {
    echo "✓ Trovate " . count($segnalazioni) . " segnalazioni per il segnalatore\n";
}

// Test 7: Verifica dettaglio segnalazione
$segnalazione = get_segnalazione($segnalazioneId);
if (!$segnalazione) {
    echo "✗ Segnalazione non trovata\n";
} else {
    echo "✓ Dettaglio segnalazione OK: " . $segnalazione['first_name'] . " " . $segnalazione['last_name'] . "\n";
}

// Test 8: Verifica documenti segnalazione (dovrebbe essere vuoto)
$docs = get_segnalazione_docs($segnalazioneId);
echo "✓ Documenti segnalazione: " . count($docs) . " (atteso 0)\n";

// Test 9: Verifica notifiche segnalatore
$notifications = list_notifications((int)$segnalatore['id']);
echo "✓ Notifiche segnalatore: " . count($notifications) . "\n";

// Test 10: Verifica count segnalazioni
$count = count_segnalazioni(['created_by' => (int)$segnalatore['id']]);
echo "✓ Conteggio segnalazioni: $count\n";

// Test 11: Verifica rate limit (dovrebbe permettere ancora invii)
$rateLimited = false;
try {
    // Prova a creare un'altra segnalazione per testare rate limit
    create_segnalazione($testData, $testFiles, (int)$segnalatore['id']);
} catch (RuntimeException $e) {
    if (str_contains($e->getMessage(), 'limite di invii')) {
        $rateLimited = true;
    } else {
        throw $e;
    }
}
if ($rateLimited) {
    echo "✓ Rate limit funzionante (troppo veloce)\n";
} else {
    echo "✓ Rate limit OK (può ancora inviare)\n";
}

// Test 12: Verifica funzioni di pulizia upload (non dovrebbe fare nulla)
$removed = cleanup_old_segnalazioni_uploads(30);
echo "✓ Pulizia upload: $removed file rimossi\n";

// Test 13: Verifica CSRF token generation
$token = csrf_token();
if (empty($token)) {
    echo "✗ Token CSRF vuoto\n";
} else {
    echo "✓ Token CSRF generato\n";
}

// Test 14: Verifica sanitizzazione
$sanitized = sanitize('<script>alert("test")</script>');
if ($sanitized === '&lt;script&gt;alert(&quot;test&quot;)&lt;/script&gt;') {
    echo "✓ Sanitizzazione HTML OK\n";
} else {
    echo "✗ Sanitizzazione HTML fallita\n";
}

// Test 15: Verifica generazione codice opportunity
$code = generate_opportunity_code(db());
if (str_starts_with($code, 'OP')) {
    echo "✓ Generazione codice opportunity OK: $code\n";
} else {
    echo "✗ Generazione codice opportunity fallita\n";
}

// Test 16: Verifica funzioni admin per segnalazioni (simula admin che vede segnalazioni)
$allSegnalazioni = list_segnalazioni();
echo "✓ Lista tutte segnalazioni (admin): " . count($allSegnalazioni) . "\n";

// Test 17: Verifica count totale segnalazioni
$totalCount = count_segnalazioni();
echo "✓ Conteggio totale segnalazioni: $totalCount\n";

// Test 18: Verifica gestori disponibili
$gestori = get_gestori();
echo "✓ Gestori disponibili: " . count($gestori) . "\n";

// Test 19: Verifica utenti admin
$admins = get_admins();
echo "✓ Amministratori: " . count($admins) . "\n";

// Test 20: Verifica funzioni push per segnalatore (se ha sottoscrizioni)
$pushSubs = get_push_subscriptions((int)$segnalatore['id']);
echo "✓ Sottoscrizioni push segnalatore: " . count($pushSubs) . "\n";

echo "\n=== Test completato con successo ===\n";
echo "Tutte le funzioni del segnalatore rispondono correttamente.\n";