<?php
/**
 * Drop the corps table from the database
 */

require_once __DIR__ . '/../shared/database_connection.php';

try {
    $pdo = getDbConnection();
    
    echo "Removing corps table...\n\n";
    
    // Drop the corps table
    $pdo->exec("DROP TABLE IF EXISTS corps");
    
    echo "✅ Corps table removed successfully!\n";
    echo "\nNote: Corps information will now be fetched directly from the staff table (corpsId column).\n";
    
} catch (PDOException $e) {
    echo "❌ DATABASE ERROR: " . $e->getMessage() . "\n";
}
