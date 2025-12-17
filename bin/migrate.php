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

// Seed catalog (no demo users or opportunities)
$seedSql = <<<'SQL'
INSERT INTO gestori (name, active) VALUES
('FastWave',1),
('FiberPlus',1),
('MobileX',0)
ON DUPLICATE KEY UPDATE name = VALUES(name), active = VALUES(active);

INSERT INTO offers (name, description, commission, manager_id) VALUES
('FW 100','Fibra 100Mbps',35.00,1),
('FW 1000','Fibra 1Gbps',55.00,1),
('FiberPlus Casa','FTTH casa',45.00,2),
('MobileX Sim Only','Voce + 100GB',22.00,3)
ON DUPLICATE KEY UPDATE name = VALUES(name), description = VALUES(description), commission = VALUES(commission), manager_id = VALUES(manager_id);
;
SQL;

runStatements($pdo, $seedSql);

echo "Migrations and seed completed.\n";
