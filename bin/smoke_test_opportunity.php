<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/data.php';

log_debug('Starting smoke test for opportunity saving');

try {
    // Simula dati per un normale
    $testData = [
        'first_name' => 'Test',
        'last_name' => 'User',
        'notes' => 'Smoke test opportunity',
        'offer_id' => 28, // Da POST dell'utente
        'installer_id' => 6, // Assumi un installer esistente
        'installer_name' => 'Test Installer',
    ];

    log_debug('Test data: ' . json_encode($testData));

    $result = add_opportunity($testData);

    log_debug('Opportunity created: ' . json_encode($result));

    echo "SUCCESS: Opportunity created with code " . ($result['opportunity_code'] ?? 'unknown') . "\n";

} catch (Throwable $e) {
    log_debug('ERROR: ' . $e->getMessage());
    echo "ERROR: " . $e->getMessage() . "\n";
}

log_debug('Smoke test completed');
?>