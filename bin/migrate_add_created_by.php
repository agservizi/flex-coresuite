<?php
require_once __DIR__ . '/../includes/db.php';

try {
    $pdo = db();
    $pdo->exec("ALTER TABLE opportunities ADD COLUMN created_by INT UNSIGNED NULL AFTER installer_id");
    $pdo->exec("ALTER TABLE opportunities ADD CONSTRAINT fk_opportunities_created_by FOREIGN KEY (created_by) REFERENCES users(id)");
    $pdo->exec("ALTER TABLE opportunities MODIFY COLUMN installer_id INT UNSIGNED NULL");
    echo "Migration completed: added created_by column and made installer_id nullable.\n";
} catch (Exception $e) {
    echo "Migration failed: " . $e->getMessage() . "\n";
}
?>