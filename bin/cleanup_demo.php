<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';

$pdo = db();
$pdo->exec("DELETE FROM opportunities WHERE installer_id IN (SELECT id FROM users WHERE email IN ('admin@coresuite.local','luca@coresuite.local'));");
$pdo->exec("DELETE FROM users WHERE email IN ('admin@coresuite.local','luca@coresuite.local');");
echo "Demo users and related opportunities removed.\n";
