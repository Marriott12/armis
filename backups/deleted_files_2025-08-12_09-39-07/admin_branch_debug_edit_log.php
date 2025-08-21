<?php
require_once '../config.php';

try {
    echo "<h3>Staff Edit Log Diagnostic</h3>";
    
    // Check if staff_edit_log table exists
    $tables = $pdo->query("SHOW TABLES LIKE 'staff_edit_log'")->fetchAll();
    if (empty($tables)) {
        echo "<p style='color: red;'>❌ staff_edit_log table does not exist</p>";
        
        // Create the table
        echo "<p>Creating staff_edit_log table...</p>";
        $createSql = "
            CREATE TABLE IF NOT EXISTS staff_edit_log (
                id INT AUTO_INCREMENT PRIMARY KEY,
                service_number VARCHAR(20) NOT NULL,
                edited_by INT NOT NULL,
                edited_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                changes TEXT,
                ip_address VARCHAR(45)
            )
        ";
        $pdo->exec($createSql);
        echo "<p style='color: green;'>✅ staff_edit_log table created</p>";
    } else {
        echo "<p style='color: green;'>✅ staff_edit_log table exists</p>";
        
        // Show table structure
        $columns = $pdo->query('DESCRIBE staff_edit_log')->fetchAll(PDO::FETCH_ASSOC);
        echo "<h4>Table Structure:</h4>";
        echo "<table border='1' style='border-collapse: collapse;'>";
        echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th></tr>";
        foreach ($columns as $col) {
            echo "<tr><td>{$col['Field']}</td><td>{$col['Type']}</td><td>{$col['Null']}</td><td>{$col['Key']}</td></tr>";
        }
        echo "</table>";
        
        // Check for recent records
        $count = $pdo->query("SELECT COUNT(*) FROM staff_edit_log")->fetchColumn();
        echo "<p>Total records in staff_edit_log: $count</p>";
        
        if ($count > 0) {
            // Show sample data
            $sample = $pdo->query("SELECT * FROM staff_edit_log ORDER BY edited_at DESC LIMIT 3")->fetchAll(PDO::FETCH_ASSOC);
            echo "<h4>Sample Records:</h4>";
            foreach ($sample as $record) {
                echo "<pre>" . print_r($record, true) . "</pre>";
            }
        }
    }
    
    // Test the query that's causing issues
    echo "<h4>Testing Recent Activity Query:</h4>";
    $stmt = $pdo->prepare("
        SELECT 
            sel.service_number,
            s.first_name,
            s.last_name,
            sel.edited_by,
            sel.edited_at,
            sel.changes,
            u.username as edited_by_username
        FROM staff_edit_log sel
        LEFT JOIN staff s ON sel.service_number = s.service_number
        LEFT JOIN users u ON sel.edited_by = u.id
        ORDER BY sel.edited_at DESC
        LIMIT 5
    ");
    $stmt->execute();
    $results = $stmt->fetchAll(PDO::FETCH_OBJ);
    
    echo "<p>Query returned " . count($results) . " records</p>";
    if (!empty($results)) {
        echo "<h5>Sample Results:</h5>";
        foreach ($results as $result) {
            echo "<pre>";
            print_r($result);
            echo "</pre>";
        }
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>Error: " . $e->getMessage() . "</p>";
}
?>
