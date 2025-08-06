<?php
// Fetch all operations for select options
require_once dirname(__DIR__) . '/shared/database_connection.php';
$pdo = getDbConnection();
$ops = $pdo->query("SELECT id, name, code FROM operations WHERE status = 'Active' ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
header('Content-Type: application/json');
echo json_encode($ops);
