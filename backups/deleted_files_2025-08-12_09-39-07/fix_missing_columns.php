<?php
require_once 'shared/database_connection.php';

try {
    $pdo = getDbConnection();
    
    echo "=== CHECKING STAFF TABLE STRUCTURE ===\n\n";
    
    // Get current columns
    $columns = $pdo->query('DESCRIBE staff')->fetchAll(PDO::FETCH_ASSOC);
    $existingColumns = array_column($columns, 'Field');
    
    echo "Current columns:\n";
    foreach ($existingColumns as $col) {
        echo "- $col\n";
    }
    
    // List of columns that should exist based on the UPDATE query
    $requiredColumns = [
        'first_name', 'last_name', 'rank_id', 'unit_id', 'corps_id', 'NRC', 'DOB', 
        'gender', 'svcStatus', 'tel', 'email', 'address', 'nok', 'nokTel', 'nokNrc', 
        'nokRelat', 'profession', 'trade', 'specialization', 'combatSize', 'bsize', 
        'ssize', 'hdress', 'attestDate', 'lastPromotion', 'postingHistory', 'awards', 
        'disciplinaryRecord'
    ];
    
    echo "\n=== MISSING COLUMNS ===\n";
    $missingColumns = [];
    
    foreach ($requiredColumns as $col) {
        if (!in_array($col, $existingColumns)) {
            echo "❌ Missing: $col\n";
            $missingColumns[] = $col;
        } else {
            echo "✅ Exists: $col\n";
        }
    }
    
    // Add missing columns
    if (!empty($missingColumns)) {
        echo "\n=== ADDING MISSING COLUMNS ===\n";
        
        $columnDefinitions = [
            'address' => 'TEXT',
            'nok' => 'VARCHAR(100)',
            'nokTel' => 'VARCHAR(20)',
            'nokNrc' => 'VARCHAR(50)',
            'nokRelat' => 'VARCHAR(50)',
            'profession' => 'VARCHAR(100)',
            'trade' => 'VARCHAR(100)',
            'specialization' => 'VARCHAR(100)',
            'combatSize' => 'VARCHAR(20)',
            'bsize' => 'VARCHAR(20)',
            'ssize' => 'VARCHAR(20)',
            'hdress' => 'VARCHAR(20)',
            'attestDate' => 'DATE',
            'lastPromotion' => 'DATE',
            'postingHistory' => 'TEXT',
            'awards' => 'TEXT',
            'disciplinaryRecord' => 'TEXT'
        ];
        
        foreach ($missingColumns as $col) {
            if (isset($columnDefinitions[$col])) {
                $sql = "ALTER TABLE staff ADD COLUMN $col {$columnDefinitions[$col]}";
                try {
                    $pdo->exec($sql);
                    echo "✅ Added column: $col\n";
                } catch (Exception $e) {
                    echo "❌ Failed to add $col: {$e->getMessage()}\n";
                }
            }
        }
        
        echo "\n=== COLUMNS ADDED SUCCESSFULLY ===\n";
    } else {
        echo "\n=== ALL COLUMNS EXIST ===\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
