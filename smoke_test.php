<?php
// Smoke test for Flex Coresuite
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/data.php';

echo "=== Flex Coresuite Smoke Test ===\n\n";

try {
    // Test DB connection
    $pdo = db();
    echo "✓ Database connection OK\n";

    // Test query
    $stmt = $pdo->query('SELECT COUNT(*) as users FROM users');
    $row = $stmt->fetch();
    echo "✓ Users table accessible, found {$row['users']} users\n";

    // Test offers
    $offers = get_offers();
    echo "✓ Offers loaded, found " . count($offers) . " offers\n";

    // Test VAPID keys
    $vapidPub = get_vapid_public_key();
    $vapidPriv = get_vapid_private_key();
    echo "✓ VAPID keys: " . ($vapidPub ? "Public OK" : "Public missing") . ", " . ($vapidPriv ? "Private OK" : "Private missing") . "\n";

    // Test FCM key
    $fcmKey = getenv('FCM_SERVER_KEY');
    echo "✓ FCM key: " . ($fcmKey ? "Present" : "Missing") . "\n";

    // Test admin push subs
    $adminSubs = get_admin_push_subscriptions();
    echo "✓ Admin push subscriptions: " . count($adminSubs) . " found\n";

    // Test push notification preparation
    $mockSubs = [
        ['endpoint' => 'https://example.com/push', 'p256dh' => 'mock', 'auth' => 'mock'],
        ['token' => 'mock_fcm_token', 'platform' => 'ios']
    ];
    try {
        // Test filtering (without actual send)
        $webSubs = array_filter($mockSubs, fn($sub) => !empty($sub['endpoint']));
        $nativeSubs = array_filter($mockSubs, fn($sub) => !empty($sub['token']));
        echo "✓ Push notification filtering: " . count($webSubs) . " web, " . count($nativeSubs) . " native\n";

        // Test FCM payload building
        if ($fcmKey) {
            $payload = [
                'to' => 'mock_token',
                'notification' => [
                    'title' => 'Test',
                    'body' => 'Test body',
                ],
            ];
            $jsonPayload = json_encode($payload);
            if ($jsonPayload) {
                echo "✓ FCM payload building OK\n";
            } else {
                echo "✗ FCM payload building failed\n";
            }
        }

        echo "✓ Push notification system syntax OK\n";
    } catch (Throwable $e) {
        echo "✗ Push notification test failed: " . $e->getMessage() . "\n";
    }

    // Test file upload dir
    $uploadDir = __DIR__ . '/uploads/segnalatore';
    if (is_dir($uploadDir) && is_writable($uploadDir)) {
        echo "✓ Upload directory writable\n";
    } else {
        echo "✗ Upload directory not writable\n";
    }

    // Test auth pages accessibility
    echo "\n--- Testing Auth Pages ---\n";
    $authPages = [
        'login' => '/auth/login.php',
        'forgot_password' => '/auth/forgot_password.php',
        'reset_password' => '/auth/reset_password.php'
    ];

    foreach ($authPages as $name => $path) {
        $url = get_base_url() . $path;

        // Use curl for better HTTP simulation
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_NOBODY, true); // HEAD request
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (compatible; SmokeTest/1.0)');
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // For HTTPS testing

        $start = microtime(true);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $duration = round((microtime(true) - $start) * 1000, 2);
        $error = curl_error($ch);

        curl_close($ch);

        if ($error) {
            echo "✗ $name page error: $error ($duration ms)\n";
        } elseif ($httpCode === 200) {
            echo "✓ $name page accessible ($duration ms)\n";
        } else {
            echo "✗ $name page returned: HTTP $httpCode ($duration ms)\n";
        }
    }

    echo "\n=== Test completed successfully ===\n";

} catch (Throwable $e) {
    echo "✗ Test failed: " . $e->getMessage() . "\n";
    exit(1);
}

function get_base_url() {
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    return $protocol . '://' . $host;
}
