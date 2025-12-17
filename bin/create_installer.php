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
    'password' => password_hash('Giogiu2123@', PASSWORD_DEFAULT),
]);

echo "Installer created/updated.\n";
