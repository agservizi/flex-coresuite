<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
try {
    $pdo = db();
    echo "DB connection OK\n";
    $stmt = $pdo->query('SELECT id, email, role FROM users');
    while($row = $stmt->fetch()) {
        echo $row['id'] . ': ' . $row['username'] . ' (' . $row['role'] . ")\n";
    }    echo "\nOffers:\n";
    $stmt2 = $pdo->query('SELECT id, name FROM offers');
    while($row2 = $stmt2->fetch()) {
        echo $row2['id'] . ': ' . $row2['name'] . "\n";
    }} catch (Throwable $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

