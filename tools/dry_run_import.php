<?php
// Dry-run import script for ImportProcessor
// Usage: php tools/dry_run_import.php "C:\\path\\to\\file.csv"

if ($argc < 2) {
    echo "Usage: php tools/dry_run_import.php <csv-file-path>\n";
    exit(1);
}

$csvPath = $argv[1];
if (!file_exists($csvPath)) {
    echo "File not found: $csvPath\n";
    exit(2);
}

// Start session so ImportProcessor can set $_SESSION['quick_fix_csv']
if (session_status() === PHP_SESSION_NONE) session_start();

// Adjust working dir to repo root
chdir(dirname(__DIR__));

require_once __DIR__ . '/../admin_branch/lib/ImportProcessor.php';
require_once __DIR__ . '/../shared/database_connection.php';

// Obtain PDO via helper
$pdo = null;
try {
    $pdo = getDbConnection();
} catch (Exception $e) {
    echo "Failed to obtain DB connection: " . $e->getMessage() . "\n";
    exit(3);
}

$userId = $_SESSION['user_id'] ?? 0;
$processor = new ImportProcessor($pdo, $userId);

try {
    // Begin transaction to avoid persisting changes during dry-run
    if (!$pdo->inTransaction()) $pdo->beginTransaction();

    // Run rankId update inside the same transaction so it is rolled back by the dry-run
    try {
        $rankUpdate = $processor->updateRankIdsFromTempRank(false);
        if (!empty($rankUpdate['affected'])) echo "Rank update (tempRank->rankId) executed (dry-run scope), affected: " . $rankUpdate['affected'] . "\n";
    } catch (Exception $e) {
        echo "Rank update failed (dry-run scope): " . $e->getMessage() . "\n";
    }

    $result = $processor->processCSV($csvPath);

    // Rollback everything to keep dry-run safe
    if ($pdo->inTransaction()) $pdo->rollBack();

    echo "--- Dry-run Import Summary ---\n";
    echo "Successful rows: " . count($result['success']) . "\n";
    echo "Failed rows: " . ($result['failed_rows_count'] ?? 0) . "\n";
    echo "Errors: " . count($result['errors']) . "\n";
    if (!empty($result['credentials'])) {
        echo "Generated credentials for " . count($result['credentials']) . " accounts (not persisted)\n";
    }

    // Write failed CSV from session if present
    $outDir = __DIR__ . '/../tmp';
    if (!is_dir($outDir)) mkdir($outDir, 0777, true);
    $outFile = $outDir . '/failed_rows_dryrun.csv';
    if (!empty($_SESSION['quick_fix_csv'])) {
        file_put_contents($outFile, $_SESSION['quick_fix_csv']);
        echo "Failed rows CSV written to: $outFile\n";
    } else {
        echo "No failed rows CSV was produced.\n";
    }

    // Optionally write credentials to a CSV
    if (!empty($result['credentials'])) {
        $credFile = $outDir . '/dryrun_credentials.csv';
        $fh = fopen($credFile, 'w');
        fputcsv($fh, ['Name', 'Email', 'Username', 'Temporary Password']);
        foreach ($result['credentials'] as $c) fputcsv($fh, [$c['name'], $c['email'], $c['username'], $c['password']]);
        fclose($fh);
        echo "Credentials CSV written to: $credFile\n";
    }

    // Print first 20 success and errors for quick inspection
    if (!empty($result['success'])) {
        echo "\nSample successes:\n";
        foreach (array_slice($result['success'], 0, 20) as $s) echo " - $s\n";
    }
    if (!empty($result['errors'])) {
        echo "\nErrors:\n";
        foreach ($result['errors'] as $e) echo " - $e\n";
    }

    exit(0);
} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo "Dry-run failed: " . $e->getMessage() . "\n";
    exit(5);
}
