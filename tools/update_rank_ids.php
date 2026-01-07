<?php
require_once __DIR__ . '/../config.php';

try {
    $pdo = new PDO('mysql:host=' . DB_HOST . ';dbname=' . DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "Step 1: Getting rank mapping from rank table...\n";
    $stmt = $pdo->query('SELECT rankId, rankId as rankName FROM `rank`');
    $rankMapping = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        // Map full rank name to abbreviated rankId
        $rankMapping[$row['rankName']] = $row['rankId'];
        echo "  {$row['rankName']} -> {$row['rankId']}\n";
    }
    echo "Found " . count($rankMapping) . " rank mappings\n\n";
    
    echo "Step 2: Checking current staff rankId values...\n";
    $stmt = $pdo->query('SELECT DISTINCT rankId, COUNT(*) as cnt FROM staff GROUP BY rankId ORDER BY cnt DESC');
    $currentRanks = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $currentRanks[] = $row;
        echo "  '{$row['rankId']}' - {$row['cnt']} staff\n";
    }
    echo "\n";
    
    echo "Step 3: Updating staff table with abbreviated rank IDs...\n";
    $pdo->exec('SET FOREIGN_KEY_CHECKS=0');
    
    $updateStmt = $pdo->prepare('UPDATE staff SET rankId = ? WHERE rankId = ?');
    $totalUpdated = 0;
    
    foreach ($rankMapping as $fullName => $abbrevId) {
        $result = $updateStmt->execute([$abbrevId, $fullName]);
        $affected = $updateStmt->rowCount();
        if ($affected > 0) {
            echo "  Updated $affected records: '$fullName' -> '$abbrevId'\n";
            $totalUpdated += $affected;
        }
    }
    
    $pdo->exec('SET FOREIGN_KEY_CHECKS=1');
    
    echo "\n✅ SUCCESS: Updated $totalUpdated staff records with abbreviated rank IDs\n\n";
    
    // Verify
    echo "Step 4: Verification - Staff rankId values after update:\n";
    $stmt = $pdo->query('SELECT s.rankId, r.rankId, COUNT(*) as cnt 
                         FROM staff s 
                         LEFT JOIN `rank` r ON s.rankId = r.rankId 
                         GROUP BY s.rankId 
                         ORDER BY cnt DESC 
                         LIMIT 15');
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $rankName = $row['rankName'] ?? 'UNMAPPED';
        echo "  RankID: '{$row['rankId']}' ({$rankName}) - Count: {$row['cnt']}\n";
    }
    
    // Check for unmapped ranks
    echo "\nChecking for unmapped ranks...\n";
    $stmt = $pdo->query('SELECT DISTINCT s.rankId, COUNT(*) as cnt 
                         FROM staff s 
                         LEFT JOIN `rank` r ON s.rankId = r.rankId 
                         WHERE r.rankId IS NULL 
                         GROUP BY s.rankId');
    $unmapped = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (count($unmapped) > 0) {
        echo "⚠️  WARNING: Found " . count($unmapped) . " unmapped rank values:\n";
        foreach ($unmapped as $row) {
            echo "  '{$row['rankId']}' - {$row['cnt']} staff members\n";
        }
    } else {
        echo "✅ All staff ranks are properly mapped!\n";
    }
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}
