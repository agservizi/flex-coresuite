<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/data.php';
require_once __DIR__ . '/includes/helpers.php';

// Test notifiche push
$adminSubs = get_admin_push_subscriptions();
echo "Subscription trovate: " . count($adminSubs) . "\n";

if (!empty($adminSubs)) {
    echo "Prima subscription:\n";
    print_r($adminSubs[0]);
    echo "\n";

    // Invia una notifica di test
    send_push_notification($adminSubs, 'Test Push', 'Questa è una notifica di test da smoke test.');

    echo "Notifica inviata. Controlla i log di errore per i dettagli.\n";
} else {
    echo "Nessuna subscription trovata per admin.\n";
}
?>