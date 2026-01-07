<?php
// Apply import script - backs up affected staff rows, applies rank updates, then runs the real import.
// Usage: php tools/apply_import.php "C:\path\to\file.csv" [importMode]

if ($argc < 2) {
    echo "Usage: php tools/apply_import.php <csv-file-path> [importMode=update]\n";
    exit(1);
}

$csvPath = $argv[1];
$importMode = $argv[2] ?? 'update';

if (!file_exists($csvPath)) { echo "File not found: $csvPath\n"; exit(2); }

chdir(dirname(__DIR__));
require_once __DIR__ . '/../shared/database_connection.php';
require_once __DIR__ . '/../admin_branch/lib/ImportProcessor.php';

try {
    $pdo = getDbConnection();
} catch (Exception $e) {
    echo "DB connection failed: " . $e->getMessage() . "\n";
    exit(3);
}

// Read svcNos from CSV to back up existing rows
$handle = fopen($csvPath, 'r');
if ($handle === false) { echo "Failed to open CSV\n"; exit(4); }
$header = fgetcsv($handle);
if (!$header) { fclose($handle); echo "CSV empty or invalid\n"; exit(5); }

$svcNos = [];
while (($row = fgetcsv($handle)) !== false) {
    if (!is_array($row) || count($row) !== count($header)) continue;
    $r = array_combine($header, $row);
    $svc = $r['service number'] ?? ($r['SVC NO'] ?? '');
    $digits = preg_replace('/\D/', '', $svc);
    if ($digits !== '') $svc = str_pad($digits, 6, '0', STR_PAD_LEFT);
    if ($svc !== '') $svcNos[$svc] = true;
}
fclose($handle);

$timestamp = date('Ymd_His');
$outDir = __DIR__ . '/../tmp'; if (!is_dir($outDir)) mkdir($outDir, 0777, true);
$backupFile = $outDir . '/import_staff_backup_' . $timestamp . '.csv';

if (!empty($svcNos)) {
    $placeholders = implode(',', array_fill(0, count($svcNos), '?'));
    $sql = "SELECT * FROM staff WHERE svcNo IN ($placeholders)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(array_keys($svcNos));
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (!empty($rows)) {
        $fp = fopen($backupFile, 'w');
        fputcsv($fp, array_keys($rows[0]));
        foreach ($rows as $r) fputcsv($fp, $r);
        fclose($fp);
        echo "Backup of existing staff rows written to: $backupFile\n";
    } else {
        echo "No existing staff rows matched for backup.\n";
    }
} else {
    echo "No svcNo values found in CSV to back up.\n";
}

$processor = new ImportProcessor($pdo, 0);

// Run rank update (will create its own backup)
try {
    $rankRes = $processor->updateRankIdsFromTempRank(true);
    echo "Rank update applied. Affected: " . ($rankRes['affected'] ?? 0) . "\n";
    if (!empty($rankRes['backup'])) echo "Rank update backup: " . $rankRes['backup'] . "\n";
} catch (Exception $e) {
    echo "Rank update failed: " . $e->getMessage() . "\n";
    echo "Aborting import.\n";
    exit(6);
}

// Run the real import (non-dry)
echo "Starting real import (mode: $importMode) ...\n";
$res = $processor->processCSV($csvPath, $importMode, false);

// Report results
echo "Import completed.\n";
echo "Imported: " . count($res['success']) . "\n";
echo "Failed rows: " . ($res['failed_rows_count'] ?? 0) . "\n";
if (!empty($res['errors'])) {
    echo "Errors (sample):\n";
    foreach (array_slice($res['errors'], 0, 50) as $e) echo " - $e\n";
}
if (!empty($res['credentials'])) {
    echo "Created accounts: " . count($res['credentials']) . "\n";
}
if (!empty($_SESSION['quick_fix_csv'])) {
    $failedFile = $outDir . '/failed_rows_import.csv';
    file_put_contents($failedFile, $_SESSION['quick_fix_csv']);
    echo "Failed rows CSV: $failedFile\n";
}

echo "Staff backup file: $backupFile\n";
echo "Done.\n";
exit(0);
