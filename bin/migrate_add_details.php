<?php
// Migration to add missing tables and columns for opportunity details
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';

$pdo = db();

echo "Adding missing tables and columns...\n";

// Ensure opportunity_comments table exists
$pdo->exec('CREATE TABLE IF NOT EXISTS opportunity_comments (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    opportunity_id INT UNSIGNED NOT NULL,
    comment TEXT NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_comments_opportunity FOREIGN KEY (opportunity_id) REFERENCES opportunities(id),
    CONSTRAINT fk_comments_user FOREIGN KEY (user_id) REFERENCES users(id),
    INDEX idx_comments_opportunity_time (opportunity_id, created_at)
)');

// Ensure opportunity_docs table exists
$pdo->exec('CREATE TABLE IF NOT EXISTS opportunity_docs (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    opportunity_id INT UNSIGNED NOT NULL,
    path VARCHAR(500) NOT NULL,
    original_name VARCHAR(255) NOT NULL,
    mime VARCHAR(100) NOT NULL,
    size INT UNSIGNED NOT NULL,
    uploaded_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_docs_opportunity FOREIGN KEY (opportunity_id) REFERENCES opportunities(id),
    INDEX idx_docs_opportunity_time (opportunity_id, uploaded_at)
)');

// Ensure phone, address, city columns in opportunities
$pdo->exec('ALTER TABLE opportunities ADD COLUMN IF NOT EXISTS phone VARCHAR(20) NULL');
$pdo->exec('ALTER TABLE opportunities ADD COLUMN IF NOT EXISTS address VARCHAR(255) NULL');
$pdo->exec('ALTER TABLE opportunities ADD COLUMN IF NOT EXISTS city VARCHAR(100) NULL');

echo "Migration completed.\n";
?>