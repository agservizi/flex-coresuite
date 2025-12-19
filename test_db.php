<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
try {
    $pdo = db();
    echo "DB connection OK\n";
    $stmt = $pdo->query('SELECT 1');
    echo "Query OK\n";
} catch (Throwable $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
