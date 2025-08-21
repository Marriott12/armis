<?php
require_once '../config.php';

try {
    echo "<h3>Staff Contact Info Table Analysis</h3>";
    
    // Check if table exists
    $tables = $pdo->query("SHOW TABLES LIKE 'staff_contact_info'")->fetchAll();
    if (empty($tables)) {
        echo "<p style='color: red;'>❌ staff_contact_info table does not exist</p>";
        echo "<p>Creating table...</p>";
        
        $createSql = "
            CREATE TABLE IF NOT EXISTS staff_contact_info (
                id INT AUTO_INCREMENT PRIMARY KEY,
                staff_id INT NOT NULL,
                contact_type VARCHAR(50) NOT NULL,
                contact_value VARCHAR(255) NOT NULL,
                contact_name VARCHAR(255) DEFAULT NULL,
                relationship VARCHAR(100) DEFAULT NULL,
                is_primary BOOLEAN DEFAULT FALSE,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (staff_id) REFERENCES staff(id) ON DELETE CASCADE
            )
        ";
        $pdo->exec($createSql);
        echo "<p style='color: green;'>✅ staff_contact_info table created with enhanced fields</p>";
    } else {
        echo "<p style='color: green;'>✅ staff_contact_info table exists</p>";
        
        // Check current structure
        $columns = $pdo->query('DESCRIBE staff_contact_info')->fetchAll(PDO::FETCH_ASSOC);
        echo "<h4>Current Table Structure:</h4>";
        echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
        echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th></tr>";
        foreach ($columns as $col) {
            echo "<tr>";
            echo "<td>{$col['Field']}</td>";
            echo "<td>{$col['Type']}</td>";
            echo "<td>{$col['Null']}</td>";
            echo "<td>{$col['Key']}</td>";
            echo "<td>{$col['Default']}</td>";
            echo "</tr>";
        }
        echo "</table>";
        
        // Check if new columns need to be added
        $existingColumns = array_column($columns, 'Field');
        $neededColumns = ['contact_name', 'relationship'];
        $missingColumns = array_diff($neededColumns, $existingColumns);
        
        if (!empty($missingColumns)) {
            echo "<h4>Adding Missing Columns:</h4>";
            foreach ($missingColumns as $column) {
                try {
                    if ($column === 'contact_name') {
                        $pdo->exec("ALTER TABLE staff_contact_info ADD COLUMN contact_name VARCHAR(255) DEFAULT NULL");
                        echo "<p style='color: green;'>✅ Added contact_name column</p>";
                    } elseif ($column === 'relationship') {
                        $pdo->exec("ALTER TABLE staff_contact_info ADD COLUMN relationship VARCHAR(100) DEFAULT NULL");
                        echo "<p style='color: green;'>✅ Added relationship column</p>";
                    }
                } catch (Exception $e) {
                    echo "<p style='color: red;'>❌ Failed to add $column: " . $e->getMessage() . "</p>";
                }
            }
        } else {
            echo "<p style='color: blue;'>All required columns exist</p>";
        }
    }
    
    // Show sample data if any exists
    $count = $pdo->query("SELECT COUNT(*) FROM staff_contact_info")->fetchColumn();
    echo "<p>Total contact records: $count</p>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>Error: " . $e->getMessage() . "</p>";
}
?>
