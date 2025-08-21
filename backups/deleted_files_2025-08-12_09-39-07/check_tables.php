<?php
require_once 'shared/database_connection.php';

$pdo = getDbConnection();
echo "Connected to database\n";

// Check tables
$result = $pdo->query("SHOW TABLES LIKE 'staff_edit_log'");
echo "staff_edit_log exists: " . ($result->rowCount() > 0 ? "YES" : "NO") . "\n";

$result = $pdo->query("SHOW TABLES LIKE 'staff_operations'");
echo "staff_operations exists: " . ($result->rowCount() > 0 ? "YES" : "NO") . "\n";

$result = $pdo->query("SHOW TABLES LIKE 'staff_deployments'");
echo "staff_deployments exists: " . ($result->rowCount() > 0 ? "YES" : "NO") . "\n";

$result = $pdo->query("SHOW TABLES LIKE 'staff_education'");
echo "staff_education exists: " . ($result->rowCount() > 0 ? "YES" : "NO") . "\n";

$result = $pdo->query("SHOW TABLES LIKE 'staff_skills'");
echo "staff_skills exists: " . ($result->rowCount() > 0 ? "YES" : "NO") . "\n";
?>
