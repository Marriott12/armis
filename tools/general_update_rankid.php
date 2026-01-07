<?php
require_once __DIR__ . '/../config.php';
// General rankId updater: backs up affected rows, applies tempRank then subRank mappings
try {
    $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
} catch (Exception $e) {
    echo "DB connect failed: " . $e->getMessage() . PHP_EOL;
    exit(1);
}

$timestamp = date('Ymd_His');
$backupFile = __DIR__ . '/../tmp/general_rank_update_backup_' . $timestamp . '.csv';
$svcFile = __DIR__ . '/../tmp/general_rank_update_svcnos_' . $timestamp . '.csv';

// Identify affected rows: prefer tempRank mapping, fallback to subRank mapping
$fetchSql = "SELECT s.*,
  COALESCE(r_temp.rankId, r_sub.rankId) AS matchedRankId,
  r_temp.rankId AS tempMatch, r_sub.rankId AS subMatch
FROM staff s
LEFT JOIN `rank` r_temp ON r_temp.rankId = CONCAT('T', s.tempRank)
LEFT JOIN `rank` r_sub ON r_sub.rankId = s.subRank
WHERE (r_temp.rankId IS NOT NULL OR r_sub.rankId IS NOT NULL)
  AND (s.rankId IS NULL OR s.rankId <> COALESCE(r_temp.rankId, r_sub.rankId))";

try {
    $stmt = $pdo->prepare($fetchSql);
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (empty($rows)) {
        echo "No rows require rankId updates.\n";
        exit(0);
    }
    // Write backup
    $fp = fopen($backupFile, 'w');
    fputcsv($fp, array_keys($rows[0]));
    foreach ($rows as $r) fputcsv($fp, $r);
    fclose($fp);
    echo "Backup of " . count($rows) . " rows written to: $backupFile\n";
} catch (Exception $e) {
    echo "Failed to fetch/backup rows: " . $e->getMessage() . PHP_EOL;
    exit(1);
}

// Apply updates in a transaction
try {
    $pdo->beginTransaction();

    // 1) Apply tempRank mappings (prefer these)
        $sql1 = "UPDATE staff s JOIN `rank` r ON r.rankId = CONCAT('T', s.tempRank) SET s.rankId = r.rankId WHERE s.tempRank IS NOT NULL AND s.tempRank <> '' AND (s.rankId IS NULL OR s.rankId <> r.rankId)";
    $affected1 = $pdo->exec($sql1);

    // 2) Apply subRank mappings for remaining rows
        $sql2 = "UPDATE staff s JOIN `rank` r ON r.rankId = s.subRank SET s.rankId = r.rankId WHERE s.subRank IS NOT NULL AND s.subRank <> '' AND (s.rankId IS NULL OR s.rankId <> r.rankId)";
    $affected2 = $pdo->exec($sql2);

    $pdo->commit();

    $totalAffected = ($affected1 ?: 0) + ($affected2 ?: 0);
    echo "Update completed. Rows affected: $totalAffected (tempRank: " . ($affected1 ?: 0) . ", subRank: " . ($affected2 ?: 0) . ")\n";

    // Write a small CSV of svcNos that were changed (from the backup rows)
    $svcFp = fopen($svcFile, 'w');
    fputcsv($svcFp, ['svcNo', 'oldRankId', 'tempMatch', 'subMatch', 'matchedRankId']);
    foreach ($rows as $r) {
        $svc = $r['svcNo'] ?? '';
        $old = $r['rankId'] ?? '';
        $temp = $r['tempMatch'] ?? '';
        $sub = $r['subMatch'] ?? '';
        $match = $r['matchedRankId'] ?? '';
        fputcsv($svcFp, [$svc, $old, $temp, $sub, $match]);
    }
    fclose($svcFp);
    echo "SvcNo list written to: $svcFile\n";

    exit(0);
} catch (Exception $e) {
    try { if ($pdo->inTransaction()) $pdo->rollBack(); } catch (Exception $ex) {}
    echo "Update failed, transaction rolled back: " . $e->getMessage() . PHP_EOL;
    exit(1);
}

?>
