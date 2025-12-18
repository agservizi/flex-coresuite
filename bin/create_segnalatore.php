<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';

$pdo = db();
$stmt = $pdo->prepare('INSERT INTO users (role, name, email, password) VALUES (:role,:name,:email,:password)
    ON DUPLICATE KEY UPDATE role=VALUES(role), name=VALUES(name), password=VALUES(password)');
$stmt->execute([
    'role' => 'segnalatore',
    'name' => 'Segnalatore Demo',
    'email' => 'segnalatore@coresuite.local',
    'password' => null,
]);

echo "Segnalatore demo creato/aggiornato con password nulla (richiede reset)." . PHP_EOL;
