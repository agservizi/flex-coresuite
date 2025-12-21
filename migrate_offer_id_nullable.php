<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';

try {
    $pdo = db();
    $pdo->exec("ALTER TABLE opportunities MODIFY COLUMN offer_id INT UNSIGNED NULL");
    echo "Colonna offer_id aggiornata per permettere NULL.\n";
} catch (Throwable $e) {
    echo "Errore: " . $e->getMessage() . "\n";
}
