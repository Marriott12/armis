<?php
// Inspect duplicate PK candidates
// Usage: php tools/inspect_dupe.php 004704 004724
if ($argc < 2) {
    echo "Usage: php tools/inspect_dupe.php <svcNo> [svcNo...]
";
    exit(1);
}
chdir(dirname(__DIR__));
require_once __DIR__ . '/../shared/database_connection.php';
try {
    $pdo = getDbConnection();
} catch (Exception $e) {
    echo "DB connection failed: " . $e->getMessage() . "\n";
    exit(2);
}
$svc = array_slice($argv,1);
$placeholders = implode(',', array_fill(0, count($svc), '?'));
$sql = "SELECT * FROM staff WHERE svcNo IN ($placeholders)";
$stmt = $pdo->prepare($sql);
$stmt->execute($svc);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
if (empty($rows)) {
    echo "No matching staff rows found for: " . implode(', ', $svc) . "\n";
    exit(0);
}
foreach ($rows as $r) {
    echo "--- svcNo: " . ($r['svcNo'] ?? '') . " ---\n";
    foreach ($r as $k => $v) {
        echo "$k: $v\n";
    }
    echo "\n";
}

// Also show any incoming CSV rows that reference these svcNos in failed CSV
$failedCsv = __DIR__ . '/../tmp/failed_rows_import.csv';
if (file_exists($failedCsv)) {
    echo "Checked failed CSV rows in $failedCsv for these svcNos:\n";
    $h = fopen($failedCsv,'r');
    $header = fgetcsv($h);
    $i = 1;
    while (($row = fgetcsv($h)) !== false) {
        $i++;
        if (count($row)!==count($header)) continue;
        $r = array_combine($header,$row);
        $svcnorm = isset($r['SVC NO NORMALIZED']) ? $r['SVC NO NORMALIZED'] : ($r['SVC NO'] ?? '');
        if (in_array($svcnorm, $svc)) {
            echo "CSV row $i matches svcNo $svcnorm:\n";
            print_r($r);
            echo "\n";
        }
    }
    fclose($h);
}
