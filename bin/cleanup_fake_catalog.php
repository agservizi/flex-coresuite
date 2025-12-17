<?php
// Rimuove gestori e offerte finte (FastWave, FiberPlus, MobileX) e relative opportunity.
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';

$pdo = db();
$names = ['FastWave', 'FiberPlus', 'MobileX'];

if (empty($names)) {
    echo "Nessun gestore da rimuovere.\n";
    exit(0);
}

$in = implode(',', array_fill(0, count($names), '?'));

$pdo->beginTransaction();
try {
    // Trova i gestori target
    $stmt = $pdo->prepare("SELECT id FROM gestori WHERE name IN ($in)");
    $stmt->execute($names);
    $managerIds = $stmt->fetchAll(PDO::FETCH_COLUMN);

    if (!$managerIds) {
        $pdo->commit();
        echo "Nessun gestore finto trovato.\n";
        exit(0);
    }

    // Trova offerte collegate ai gestori target
    $inManagers = implode(',', array_fill(0, count($managerIds), '?'));
    $offerStmt = $pdo->prepare("SELECT id FROM offers WHERE manager_id IN ($inManagers)");
    $offerStmt->execute($managerIds);
    $offerIds = $offerStmt->fetchAll(PDO::FETCH_COLUMN);

    // Cancella opportunity collegate
    if ($offerIds) {
        $inOffers = implode(',', array_fill(0, count($offerIds), '?'));
        $delOpp = $pdo->prepare("DELETE FROM opportunities WHERE offer_id IN ($inOffers)");
        $delOpp->execute($offerIds);
    }
    $delOppMgr = $pdo->prepare("DELETE FROM opportunities WHERE manager_id IN ($inManagers)");
    $delOppMgr->execute($managerIds);

    // Cancella offerte
    if ($offerIds) {
        $inOffers = implode(',', array_fill(0, count($offerIds), '?'));
        $delOffers = $pdo->prepare("DELETE FROM offers WHERE id IN ($inOffers)");
        $delOffers->execute($offerIds);
    }

    // Cancella gestori
    $delManagers = $pdo->prepare("DELETE FROM gestori WHERE id IN ($inManagers)");
    $delManagers->execute($managerIds);

    $pdo->commit();
    echo "Pulizia completata. Gestori rimossi: " . count($managerIds) . "\n";
} catch (Throwable $e) {
    $pdo->rollBack();
    fwrite(STDERR, "Errore durante la pulizia: " . $e->getMessage() . "\n");
    exit(1);
}
