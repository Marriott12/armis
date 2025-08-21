<?php
require_once 'shared/database_connection.php';
$pdo = getDbConnection();
$columns = $pdo->query('DESCRIBE staff')->fetchAll(PDO::FETCH_ASSOC);
foreach ($columns as $col) {
    echo $col['Field'] . "\n";
}
?>
