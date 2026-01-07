<?php
require_once __DIR__ . '/../config.php';
try {
    $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
} catch (Exception $e) {
    echo "DB connect failed: " . $e->getMessage() . PHP_EOL; exit(1);
}

echo "Unmapped tempRank values:\n";
$stmt = $pdo->query("SELECT DISTINCT tempRank FROM staff WHERE tempRank IS NOT NULL AND tempRank <> ''");
$temps = $stmt->fetchAll(PDO::FETCH_COLUMN);
foreach ($temps as $t) {
    $check = $pdo->prepare("SELECT COUNT(*) FROM `rank` WHERE rankId = CONCAT('T', ?) OR rankId = ?");
    $check->execute([$t, $t]);
    if ($check->fetchColumn() == 0) echo " - $t\n";
}

echo "\nUnmapped subRank values:\n";
$stmt = $pdo->query("SELECT DISTINCT subRank FROM staff WHERE subRank IS NOT NULL AND subRank <> ''");
$subs = $stmt->fetchAll(PDO::FETCH_COLUMN);
foreach ($subs as $s) {
    $check = $pdo->prepare("SELECT COUNT(*) FROM `rank` WHERE rankId = ?");
    $check->execute([$s]);
    if ($check->fetchColumn() == 0) echo " - $s\n";
}

exit(0);
