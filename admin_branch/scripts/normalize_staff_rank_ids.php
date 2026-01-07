<?php
// normalize_staff_rank_ids.php
// Usage (CLI): php normalize_staff_rank_ids.php [--apply] [--active-only]
// Default is dry-run. --apply will perform updates inside a transaction.

if (php_sapi_name() !== 'cli') {
    echo "This script is intended to be run from the command line.\n";
    exit(1);
}

require_once __DIR__ . '/../../shared/database_connection.php';

$apply = in_array('--apply', $argv, true);
$activeOnly = in_array('--active-only', $argv, true) || in_array('--active-only=true', $argv, true);

try {
    $pdo = getDbConnection();
} catch (Exception $e) {
    echo "Failed to get DB connection: " . $e->getMessage() . "\n";
    exit(1);
}

$whereActive = $activeOnly ? "AND s.svcStatus = 'Active'" : '';

// Candidate selection: staff rows where staff.rankId equals the rank.level
// but is different from the canonical rank.rankId
$countSql = "SELECT COUNT(*) as c FROM staff s JOIN `rank` r ON CAST(s.rankId AS CHAR) = CAST(r.level AS CHAR) WHERE s.rankId <> r.rankId $whereActive";
$stmt = $pdo->query($countSql);
$count = $stmt->fetch(PDO::FETCH_ASSOC)['c'] ?? 0;

echo "Candidates where staff.rankId appears to be a level and differs from rank.rankId: $count\n";

$sampleSql = "SELECT s.id, s.svcNo, s.rankId AS staff_rank_id, r.rankId AS expected_rank_id, r.level, s.svcStatus FROM staff s JOIN `rank` r ON CAST(s.rankId AS CHAR) = CAST(r.level AS CHAR) WHERE s.rankId <> r.rankId $whereActive LIMIT 20";
$sampleStmt = $pdo->query($sampleSql);
$samples = $sampleStmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($samples)) {
    echo "No sample rows to show.\n";
} else {
    echo "Sample rows:\n";
    foreach ($samples as $row) {
        echo sprintf("id=%s svc=%s staff.rankId=%s -> expected rankId=%s level=%s status=%s\n",
            $row['id'], $row['svcNo'], $row['staff_rank_id'], $row['expected_rank_id'], $row['level'], $row['svcStatus'] ?? '');
    }
}

if (!$apply) {
    echo "\nDry-run only. To apply these changes run with --apply.\n";
    echo "Recommendation: take a DB backup before applying. Example:\n";
    echo "mysqldump -u <db_user> -p <database_name> > C:\\path\\to\\backup_armis_before_rank_fix.sql\n";
    exit(0);
}

// Apply updates inside transaction
try {
    echo "\nApplying updates...\n";
    $pdo->beginTransaction();

    $updateSql = "UPDATE staff s JOIN `rank` r ON CAST(s.rankId AS CHAR) = CAST(r.level AS CHAR) SET s.rankId = r.rankId WHERE s.rankId <> r.rankId $whereActive";
    $countBeforeStmt = $pdo->query("SELECT COUNT(*) as c FROM staff");
    $beforeTotal = $countBeforeStmt->fetch(PDO::FETCH_ASSOC)['c'] ?? 'unknown';

    $affected = $pdo->exec($updateSql);
    echo "Update executed, rows affected: " . ($affected === false ? 'unknown (exec failed)' : $affected) . "\n";

    // Verify a few changed rows
    $verifySql = "SELECT s.id, s.svcNo, s.rankId, r.level FROM staff s LEFT JOIN `rank` r ON s.rankId = r.rankId WHERE CAST(s.rankId AS CHAR) IN (SELECT CAST(level AS CHAR) FROM `rank`) LIMIT 10";
    $verifyStmt = $pdo->query($verifySql);
    $verifyRows = $verifyStmt->fetchAll(PDO::FETCH_ASSOC);
    if (!empty($verifyRows)) {
        echo "\nPost-update verification sample:\n";
        foreach ($verifyRows as $r) {
            echo sprintf("id=%s svc=%s rankId=%s (rank.level match?) %s\n", $r['id'], $r['svcNo'], $r['rankId'], $r['level'] ?? '');
        }
    }

    $pdo->commit();
    echo "Transaction committed.\n";
} catch (Exception $e) {
    $pdo->rollBack();
    echo "Update failed, transaction rolled back: " . $e->getMessage() . "\n";
    exit(1);
}

echo "Done.\n";
