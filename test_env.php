<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/helpers.php';

// Test VAPID keys
echo "VAPID Public Key: " . (get_vapid_public_key() ? "Presente" : "Mancante") . "\n";
echo "VAPID Private Key: " . (get_vapid_private_key() ? "Presente" : "Mancante") . "\n";

// Test FCM
echo "FCM Project ID: " . (getenv('FCM_PROJECT_ID') ?: "Mancante") . "\n";
echo "FCM Service Account: " . (getenv('FCM_SERVICE_ACCOUNT_KEY') ? "Presente" : "Mancante") . "\n";
echo "FCM Server Key: " . (getenv('FCM_SERVER_KEY') ? "Presente" : "Mancante") . "\n";

// Test FCM access token
$accessToken = get_fcm_access_token();
echo "FCM Access Token: " . ($accessToken ? "Ottenuto" : "Fallito") . "\n";

if (!$accessToken) {
    echo "Errore nel service account JSON o configurazione.\n";
}
?>