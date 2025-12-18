<?php
// Simple migration runner for Flex using PDO and .env settings.
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';

function runStatements(PDO $pdo, string $sql): void
{
    $statements = array_filter(array_map('trim', explode(';', $sql)), fn($s) => $s !== '');
    foreach ($statements as $stmt) {
        $pdo->exec($stmt);
    }
}

$pdo = db();

// Apply schema
$schemaPath = __DIR__ . '/../schema/mysql.sql';
if (!is_file($schemaPath)) {
    fwrite(STDERR, "Schema file not found: {$schemaPath}\n");
    exit(1);
}
$schemaSql = file_get_contents($schemaPath);
runStatements($pdo, $schemaSql);

// Ensure password reset columns exist and password is nullable
$pdo->exec('ALTER TABLE users MODIFY COLUMN password VARCHAR(255) NULL');
$pdo->exec('ALTER TABLE users ADD COLUMN IF NOT EXISTS password_reset_token VARCHAR(255) NULL');
$pdo->exec('ALTER TABLE users ADD COLUMN IF NOT EXISTS password_reset_expires DATETIME NULL');
$pdo->exec('CREATE INDEX IF NOT EXISTS idx_user_reset_token ON users (password_reset_token)');

// Ensure opportunity_code column exists and is populated uniquely
$pdo->exec('ALTER TABLE opportunities ADD COLUMN IF NOT EXISTS opportunity_code VARCHAR(32) NULL AFTER id');

// Backfill codes for existing rows
$stmtMissing = $pdo->query("SELECT id FROM opportunities WHERE opportunity_code IS NULL OR opportunity_code = ''");
$rows = $stmtMissing->fetchAll();

if ($rows) {
    $genStmt = $pdo->prepare('SELECT COUNT(*) FROM opportunities WHERE opportunity_code = :code');
    $updateStmt = $pdo->prepare('UPDATE opportunities SET opportunity_code = :code WHERE id = :id');

    $generateCode = function () use ($genStmt): string {
        do {
            $code = 'OP' . date('Ymd') . random_int(100000, 999999);
            $genStmt->execute(['code' => $code]);
        } while ((int)$genStmt->fetchColumn() > 0);
        return $code;
    };

    foreach ($rows as $row) {
        $code = $generateCode();
        $updateStmt->execute(['code' => $code, 'id' => $row['id']]);
    }
}

$pdo->exec('ALTER TABLE opportunities MODIFY COLUMN opportunity_code VARCHAR(32) NOT NULL');
$pdo->exec('ALTER TABLE opportunities ADD UNIQUE INDEX IF NOT EXISTS idx_opportunity_code (opportunity_code)');

// Nessun seed automatico: gestori e offerte vanno inseriti dall'admin.

echo "Migrations and seed completed.\n";
