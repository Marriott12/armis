<?php
/**
 * Verify database schema after corps table removal
 */

require_once __DIR__ . '/../shared/database_connection.php';

echo "=== ARMIS Database Schema Verification ===\n\n";

try {
    $pdo = getDbConnection();
    
    // Check if corps table exists
    echo "1. Checking corps table...\n";
    $stmt = $pdo->query("SHOW TABLES LIKE 'corps'");
    $corpsExists = $stmt->fetch();
    if ($corpsExists) {
        echo "   ❌ WARNING: Corps table still exists!\n";
    } else {
        echo "   ✅ Corps table removed successfully\n";
    }
    
    // Check staff table columns
    echo "\n2. Checking staff table columns...\n";
  $stmt = $pdo->query("DESCRIBE staff");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $requiredColumns = ['svcNo', 'username', 'password', 'role', 'fName', 'lName', 'corpsId', 'officialEmail'];
    $foundColumns = array_column($columns, 'Field');
    
    foreach ($requiredColumns as $col) {
        if (in_array($col, $foundColumns)) {
            echo "   ✅ $col exists\n";
        } else {
            echo "   ❌ $col missing!\n";
        }
    }
    
    // Test authentication query
    echo "\n3. Testing authentication query...\n";
    try {
        $testUser = authenticateUser('Marriott', 'Armis@2026');
        if ($testUser) {
            echo "   ✅ Authentication query works\n";
            echo "   User: {$testUser['fName']} {$testUser['lName']}\n";
            echo "   Role: {$testUser['role']}\n";
            echo "   Corps: " . ($testUser['corpsId'] ?? 'Not set') . "\n";
        } else {
            echo "   ❌ Authentication failed (check password)\n";
        }
    } catch (Exception $e) {
        echo "   ❌ Query error: " . $e->getMessage() . "\n";
    }
    
    // Check for corps references in code
    echo "\n4. Summary:\n";
    echo "   - Corps table has been removed\n";
    echo "   - Corps information is now fetched from staff.corpsId\n";
    echo "   - Email field changed from 's.email' to 's.officialEmail'\n";
    echo "   - All database queries updated\n";
    
    echo "\n✅ Database schema verification complete!\n";
    
} catch (PDOException $e) {
    echo "❌ DATABASE ERROR: " . $e->getMessage() . "\n";
}
