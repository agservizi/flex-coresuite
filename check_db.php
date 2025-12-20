<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';

try {
    $pdo = db();
    $stmt = $pdo->query("SELECT o.id, o.opportunity_code, o.first_name, o.last_name, o.commission, o.created_by, u.name AS creator_name, of.name AS offer_name, of.commission AS offer_commission
                         FROM opportunities o
                         LEFT JOIN users u ON o.created_by = u.id
                         LEFT JOIN offers of ON o.offer_id = of.id
                         WHERE o.created_by IS NOT NULL
                         ORDER BY o.created_at DESC
                         LIMIT 10");
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Segnalazioni del segnalatore (ultime 10):\n";
    foreach ($results as $row) {
        echo "ID: {$row['id']}, Codice: {$row['opportunity_code']}, Cliente: {$row['first_name']} {$row['last_name']}, Commissione: {$row['commission']}, Creato da: {$row['creator_name']}, Offerta: {$row['offer_name']}, Comm. Offerta: {$row['offer_commission']}\n";
    }
    
    if (empty($results)) {
        echo "Nessuna segnalazione trovata.\n";
    }
} catch (Exception $e) {
    echo "Errore: " . $e->getMessage() . "\n";
}
?>