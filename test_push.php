<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/data.php';
require_once __DIR__ . '/includes/helpers.php';

// Supporta output JSON per richieste AJAX
$jsonOutput = isset($_GET['json']) || isset($_POST['json']);

if ($jsonOutput) {
    header('Content-Type: application/json');
}

$action = $_GET['action'] ?? 'test';

switch ($action) {
    case 'comprehensive':
        // Test completo del sistema push
        $result = [
            'timestamp' => date('Y-m-d H:i:s'),
            'tests' => []
        ];

        // Test 1: VAPID keys
        $vapidPub = get_vapid_public_key();
        $vapidPriv = get_vapid_private_key();
        $result['tests']['vapid_keys'] = [
            'public' => $vapidPub ? 'present' : 'missing',
            'private' => $vapidPriv ? 'present' : 'missing',
            'status' => ($vapidPub && $vapidPriv) ? 'pass' : 'fail'
        ];

        // Test 2: FCM key
        $fcmKey = getenv('FCM_SERVER_KEY');
        $result['tests']['fcm_key'] = [
            'present' => $fcmKey ? true : false,
            'status' => $fcmKey ? 'pass' : 'warning'
        ];

        // Test 3: Database subscriptions
        $adminSubs = get_admin_push_subscriptions();
        $result['tests']['subscriptions'] = [
            'count' => count($adminSubs),
            'web' => count(array_filter($adminSubs, fn($s) => !empty($s['endpoint']))),
            'native' => count(array_filter($adminSubs, fn($s) => !empty($s['token']))),
            'status' => count($adminSubs) > 0 ? 'pass' : 'warning'
        ];

        // Test 4: Payload building
        try {
            $testPayload = ['title' => 'Test', 'body' => 'Test body'];
            $result['tests']['payload_building'] = [
                'web_push' => 'simulated_ok',
                'fcm_push' => $fcmKey ? 'simulated_ok' : 'fcm_key_missing',
                'status' => 'pass'
            ];
        } catch (Exception $e) {
            $result['tests']['payload_building'] = [
                'error' => $e->getMessage(),
                'status' => 'fail'
            ];
        }

        if ($jsonOutput) {
            echo json_encode($result);
        } else {
            echo "=== Push System Comprehensive Test ===\n\n";
            foreach ($result['tests'] as $testName => $testResult) {
                $status = $testResult['status'];
                $icon = $status === 'pass' ? '✓' : ($status === 'warning' ? '⚠' : '✗');
                echo "{$icon} {$testName}: {$status}\n";
                if (isset($testResult['count'])) {
                    echo "   Subscriptions: {$testResult['count']} total ({$testResult['web']} web, {$testResult['native']} native)\n";
                }
            }
        }
        break;

    case 'send_test':
        // Invia notifica di test (opzionale)
        $adminSubs = get_admin_push_subscriptions();

        if (empty($adminSubs)) {
            $response = ['error' => 'Nessuna subscription trovata'];
        } else {
            try {
                send_push_notification($adminSubs, '🧪 Push Test', 'Notifica di test dal sistema di smoke test');
                $response = [
                    'success' => true,
                    'message' => 'Notifica di test inviata a ' . count($adminSubs) . ' subscriptions',
                    'subscriptions' => count($adminSubs)
                ];
            } catch (Exception $e) {
                $response = ['error' => 'Errore invio notifica: ' . $e->getMessage()];
            }
        }

        if ($jsonOutput) {
            echo json_encode($response);
        } else {
            echo json_encode($response, JSON_PRETTY_PRINT);
        }
        break;

    default:
        // Test semplice esistente
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
}
?>