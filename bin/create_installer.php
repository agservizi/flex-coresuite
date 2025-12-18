<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';

$pdo = db();
$stmt = $pdo->prepare('INSERT INTO users (role, name, email, password) VALUES (:role,:name,:email,:password)
    ON DUPLICATE KEY UPDATE role=VALUES(role), name=VALUES(name), password=VALUES(password)');
$stmt->execute([
    'role' => 'installer',
    'name' => 'Installer Flex',
    'email' => 'installer@agservizi.it',
    'password' => null,
]);

echo "Installer created/updated with null password (requires reset token).\n";
