<?php
require_once __DIR__ . '/../config.php';
try {
    $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
} catch (Exception $e) {
    echo "DB connect failed: " . $e->getMessage() . PHP_EOL; exit(1);
}

// Check duplicates for svcNo
$dupStmt = $pdo->query("SELECT svcNo, COUNT(*) c FROM staff WHERE svcNo IS NOT NULL AND svcNo <> '' GROUP BY svcNo HAVING c > 1");
$dups = $dupStmt->fetchAll(PDO::FETCH_ASSOC);
if (!empty($dups)) {
    echo "Found duplicate svcNo values. Aborting unique index creation.\n";
    foreach ($dups as $d) echo " - {$d['svcNo']} (count: {$d['c']})\n";
    echo "Please resolve duplicates before running migration.\n";
    exit(1);
}

// Create unique index if not exists
try {
    $pdo->exec("ALTER TABLE staff ADD UNIQUE INDEX ux_staff_svcNo (svcNo)");
    echo "Unique index ux_staff_svcNo created.\n";
} catch (Exception $e) {
    echo "Failed to create unique index: " . $e->getMessage() . PHP_EOL;
}

// Create index on email if not exists
try {
    $pdo->exec("ALTER TABLE staff ADD INDEX idx_staff_email (email)");
    echo "Index idx_staff_email created.\n";
} catch (Exception $e) {
    echo "Failed to create email index: " . $e->getMessage() . PHP_EOL;
}

exit(0);
