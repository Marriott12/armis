<?php
/**
 * Check database tables and create missing corps table
 */

require_once __DIR__ . '/../shared/database_connection.php';

try {
    $pdo = getDbConnection();
    
    // Check existing tables
    echo "Checking existing tables in armis1 database...\n\n";
    $stmt = $pdo->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    echo "Found " . count($tables) . " tables:\n";
    foreach ($tables as $table) {
        echo "  - $table\n";
    }
    
    // Check if corps table exists
    if (!in_array('corps', $tables)) {
        echo "\n⚠️  'corps' table not found!\n";
        echo "Creating corps table...\n\n";
        
        // Create the corps table
        $createTableSQL = "CREATE TABLE IF NOT EXISTS `corps` (
            `corpsId` varchar(10) NOT NULL,
            `name` varchar(100) NOT NULL,
            `abbreviation` varchar(20) DEFAULT NULL,
            PRIMARY KEY (`corpsId`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";
        
        $pdo->exec($createTableSQL);
        echo "✅ Corps table created successfully!\n\n";
        
        // Insert some default corps data
        echo "Inserting default corps data...\n";
        $defaultCorps = [
            ['INF', 'Infantry', 'INF'],
            ['ARM', 'Armoured', 'ARM'],
            ['ART', 'Artillery', 'ART'],
            ['ENG', 'Engineers', 'ENG'],
            ['SIG', 'Signals', 'SIG'],
            ['INT', 'Intelligence', 'INT'],
            ['LOG', 'Logistics', 'LOG'],
            ['MED', 'Medical', 'MED'],
            ['MP', 'Military Police', 'MP'],
            ['ADM', 'Administration', 'ADM']
        ];
        
        $insertStmt = $pdo->prepare("INSERT INTO corps (corpsId, name, abbreviation) VALUES (?, ?, ?)");
        foreach ($defaultCorps as $corps) {
            $insertStmt->execute($corps);
            echo "  ✓ Added: {$corps[1]} ({$corps[2]})\n";
        }
        
        echo "\n✅ Default corps data inserted successfully!\n";
    } else {
        echo "\n✅ Corps table exists.\n";
        
        // Show corps count
        $stmt = $pdo->query("SELECT COUNT(*) FROM corps");
        $count = $stmt->fetchColumn();
        echo "Total corps records: $count\n";
    }
    
} catch (PDOException $e) {
    echo "❌ DATABASE ERROR: " . $e->getMessage() . "\n";
}
