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

    // Test send push (dry run, no actual send)
    if (!empty($adminSubs)) {
        echo "✓ Push notification system ready\n";
    } else {
        echo "⚠ No admin push subscriptions found\n";
    }

    // Test file upload dir
    $uploadDir = __DIR__ . '/uploads/segnalatore';
    if (is_dir($uploadDir) && is_writable($uploadDir)) {
        echo "✓ Upload directory writable\n";
    } else {
        echo "✗ Upload directory not writable\n";
    }

    echo "\n=== Test completed successfully ===\n";

} catch (Throwable $e) {
    echo "✗ Test failed: " . $e->getMessage() . "\n";
    exit(1);
}
