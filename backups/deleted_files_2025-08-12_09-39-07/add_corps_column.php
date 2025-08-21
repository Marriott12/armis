<?php
require_once 'shared/database_connection.php';

try {
    $pdo = getDbConnection();
    
    echo "Adding corps_id column to staff table...\n";
    
    // Add corps_id column to staff table
    $sql = 'ALTER TABLE staff ADD COLUMN corps_id INT NULL AFTER unit_id';
    $pdo->exec($sql);
    echo "Successfully added corps_id column to staff table\n";
    
    // Add foreign key constraint
    $sql = 'ALTER TABLE staff ADD CONSTRAINT fk_staff_corps FOREIGN KEY (corps_id) REFERENCES corps(id) ON DELETE SET NULL';
    $pdo->exec($sql);
    echo "Successfully added foreign key constraint for corps_id\n";
    
} catch (Exception $e) {
    if (strpos($e->getMessage(), 'Duplicate column') !== false) {
        echo "Column corps_id already exists\n";
    } elseif (strpos($e->getMessage(), 'Duplicate key') !== false) {
        echo "Foreign key constraint already exists\n";
    } else {
        echo "Error: " . $e->getMessage() . "\n";
    }
}
?>
