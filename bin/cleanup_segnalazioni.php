<?php
require_once __DIR__ . '/../includes/data.php';

$days = (int)($argv[1] ?? 30);
$removed = cleanup_old_segnalazioni_uploads($days);

echo "Removed {$removed} documents older than {$days} days for rejected segnalazioni" . PHP_EOL;
