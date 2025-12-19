<?php
require_once __DIR__ . '/../includes/db.php';

try {
    $pdo = db();
    $pdo->exec("ALTER TABLE opportunities ADD COLUMN phone VARCHAR(20) NULL AFTER commission");
    $pdo->exec("ALTER TABLE opportunities ADD COLUMN address TEXT NULL AFTER phone");
    $pdo->exec("ALTER TABLE opportunities ADD COLUMN city VARCHAR(100) NULL AFTER address");
    echo "Migration completed: added phone, address, city columns to opportunities.\n";
} catch (Exception $e) {
    echo "Migration failed: " . $e->getMessage() . "\n";
}
?>