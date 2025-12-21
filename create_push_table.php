<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';

try {
    $pdo = db();
    $pdo->exec("CREATE TABLE IF NOT EXISTS push_subscriptions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        endpoint TEXT,
        p256dh TEXT,
        auth TEXT,
        token TEXT,
        platform VARCHAR(50),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_user_id (user_id)
    )");
    echo "Tabella push_subscriptions creata o già esistente.\n";
} catch (Throwable $e) {
    echo "Errore: " . $e->getMessage() . "\n";
}
?>