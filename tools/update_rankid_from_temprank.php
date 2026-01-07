<?php
require_once __DIR__ . '/../config.php';
// Create PDO
try {
    $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
} catch (Exception $e) {
    echo "Failed to connect to database: " . $e->getMessage() . PHP_EOL;
    exit(1);
}

// Backup affected rows
$backupFile = __DIR__ . '/../tmp/rankid_update_backup_' . date('Ymd_His') . '.csv';
try {
    // The `rank` table in this schema uses `rankId` as the primary identifier and (in some places)
    // as the abbreviation token. Join on rankId = CONCAT('T', tempRank).
    $stmt = $pdo->prepare("SELECT s.* FROM staff s JOIN `rank` r ON r.rankId = CONCAT('T', s.tempRank) WHERE s.rankId IS NULL AND s.tempRank IS NOT NULL");
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (!empty($rows)) {
        $fp = fopen($backupFile, 'w');
        fputcsv($fp, array_keys($rows[0]));
        foreach ($rows as $r) fputcsv($fp, $r);
        fclose($fp);
        echo "Backup of " . count($rows) . " rows written to: $backupFile" . PHP_EOL;
    } else {
        echo "No rows to back up (no matching rows found)." . PHP_EOL;
    }
} catch (Exception $e) {
    echo "Failed to backup rows: " . $e->getMessage() . PHP_EOL;
    exit(1);
}

// Run update inside transaction
try {
    $pdo->beginTransaction();
    $sql = "UPDATE staff s JOIN `rank` r ON r.rankId = CONCAT('T', s.tempRank) SET s.rankId = r.rankId WHERE s.rankId IS NULL AND s.tempRank IS NOT NULL";
    $affected = $pdo->exec($sql);
    $pdo->commit();
    echo "Update completed. Rows affected: $affected" . PHP_EOL;
    exit(0);
} catch (Exception $e) {
    try { $pdo->rollBack(); } catch (Exception $ex) {}
    echo "Update failed: " . $e->getMessage() . PHP_EOL;
    exit(1);
}

?>
