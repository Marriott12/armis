<?php
require_once __DIR__ . '/../shared/database_connection.php';

// 1) Show first 40 lines of failed_rows_dryrun.csv
$failedFile = __DIR__ . '/../tmp/failed_rows_dryrun.csv';
if (file_exists($failedFile)) {
    echo "--- Failed rows CSV (first 40 lines) ---\n";
    $fh = fopen($failedFile, 'r');
    $lineNo = 0;
    while (($line = fgets($fh)) !== false && $lineNo < 40) {
        echo str_pad(($lineNo+1) . ':', 6) . rtrim($line, "\n\r") . "\n";
        $lineNo++;
    }
    fclose($fh);
} else {
    echo "Failed rows CSV not found at: $failedFile\n";
}

// 2) Extract svcNos from most recent staff_imports record for NEW NORMINAL ROLL.csv
try {
    $pdo = getDbConnection();
    $stmt = $pdo->prepare("SELECT id, filename, import_date, import_log FROM staff_imports WHERE filename = ? ORDER BY import_date DESC LIMIT 1");
    $stmt->execute(['NEW NORMINAL ROLL.csv']);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        echo "No staff_imports entry found for NEW NORMINAL ROLL.csv\n";
        exit(0);
    }
    echo "\n--- Import log (id={$row['id']}, imported: {$row['import_date']}) ---\n";
    $log = $row['import_log'];
    // import_log may already be JSON; try to decode
    $decoded = json_decode($log, true);
    if ($decoded === null) {
        // try to unescape if it's double-encoded
        $decoded = json_decode(stripslashes($log), true);
    }
    $svcNos = [];
    if (is_array($decoded) && isset($decoded['debug']) && is_array($decoded['debug'])) {
        foreach ($decoded['debug'] as $entry) {
            if (!empty($entry['values']) && is_array($entry['values'])) {
                foreach ($entry['values'] as $val) {
                    // Trim and check if looks like a svcNo (1-6 digits, may have leading zeros)
                    $v = trim((string)$val);
                    if (preg_match('/^\d{1,6}$/', $v)) {
                        $svcNos[] = str_pad($v, 6, '0', STR_PAD_LEFT);
                    }
                }
            }
        }
    }
    $svcNos = array_values(array_unique($svcNos));
    echo "Found " . count($svcNos) . " svcNos in import_log debug entries.\n";
    if (count($svcNos) === 0) {
        echo "No svcNos could be extracted from import_log debug.\n";
        exit(0);
    }
    // Limit to first 500 to avoid huge IN lists
    $svcChunk = array_slice($svcNos, 0, 500);
    $placeholders = rtrim(str_repeat('?,', count($svcChunk)), ',');
    $q = "SELECT svcNo, fName, lName, rankId, corpsId, unitId, email FROM staff WHERE svcNo IN ($placeholders)";
    $s2 = $pdo->prepare($q);
    $s2->execute($svcChunk);
    $results = $s2->fetchAll(PDO::FETCH_ASSOC);
    echo "\n--- Staff rows for extracted svcNos (showing up to " . count($svcChunk) . ") ---\n";
    echo json_encode($results, JSON_PRETTY_PRINT) . "\n";

} catch (Exception $e) {
    echo "DB error: " . $e->getMessage() . "\n";
    exit(2);
}
