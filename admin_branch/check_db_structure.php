<?php
/**
 * Check staff_appointment table structure
 */

$host = 'localhost';
$dbname = 'armis1';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "=== staff_appointment Table Structure ===\n\n";
    
    $stmt = $pdo->query("DESCRIBE staff_appointment");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($columns as $column) {
        echo sprintf("%-30s %-20s %-10s %-10s\n", 
            $column['Field'], 
            $column['Type'], 
            $column['Null'], 
            $column['Key']
        );
    }
    
    echo "\n=== appointment_type Table Structure ===\n\n";
    
    // Check if appointment_type table exists
    $tableCheck = $pdo->query("SHOW TABLES LIKE 'appointment_type'");
    if ($tableCheck->rowCount() > 0) {
        $stmt2 = $pdo->query("DESCRIBE appointment_type");
        $columns2 = $stmt2->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($columns2 as $column) {
            echo sprintf("%-30s %-20s %-10s %-10s\n", 
                $column['Field'], 
                $column['Type'], 
                $column['Null'], 
                $column['Key']
            );
        }
        
        echo "\n=== appointment_type Data ===\n\n";
        $data = $pdo->query("SELECT * FROM appointment_type");
        foreach ($data->fetchAll(PDO::FETCH_ASSOC) as $row) {
            print_r($row);
        }
    } else {
        echo "Table 'appointment_type' does not exist.\n";
    }
    
} catch (PDOException $e) {
    die("Error: " . $e->getMessage());
}
?>
