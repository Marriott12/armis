<?php
require_once __DIR__ . '/../shared/database_connection.php';

try {
    $pdo = getDbConnection();
    $stmt = $pdo->query("SELECT id, filename, file_type, imported_by, import_date, total_rows, successful_rows, failed_rows, import_log FROM staff_imports ORDER BY import_date DESC LIMIT 10");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($rows, JSON_PRETTY_PRINT) . "\n";
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
