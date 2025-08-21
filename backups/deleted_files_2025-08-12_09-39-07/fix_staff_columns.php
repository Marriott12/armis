<?php
// Direct database connection without includes to avoid potential issues
$host = 'localhost';
$dbname = 'armis';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "<h3>Database Column Fix Script</h3>";
    echo "<p>Adding missing columns to staff table...</p>";
    
    // List of missing columns with their definitions
    $missingColumns = [
        'address' => 'TEXT',
        'nok' => 'VARCHAR(255)',
        'nokTel' => 'VARCHAR(20)',
        'nokNrc' => 'VARCHAR(20)',
        'nokRelat' => 'VARCHAR(100)',
        'profession' => 'VARCHAR(255)',
        'trade' => 'VARCHAR(255)',
        'specialization' => 'VARCHAR(255)',
        'combatSize' => 'VARCHAR(10)',
        'bsize' => 'VARCHAR(10)',
        'ssize' => 'VARCHAR(10)',
        'hdress' => 'VARCHAR(10)',
        'attestDate' => 'DATE',
        'lastPromotion' => 'DATE',
        'postingHistory' => 'TEXT',
        'awards' => 'TEXT',
        'disciplinaryRecord' => 'TEXT'
    ];
    
    // Get existing columns
    $columns = $pdo->query('DESCRIBE staff')->fetchAll(PDO::FETCH_ASSOC);
    $existingColumns = array_column($columns, 'Field');
    
    echo "<h4>Current Columns in Staff Table:</h4>";
    echo "<ul>";
    foreach ($existingColumns as $col) {
        echo "<li>$col</li>";
    }
    echo "</ul>";
    
    echo "<h4>Adding Missing Columns:</h4>";
    $added = 0;
    $errors = 0;
    
    foreach ($missingColumns as $column => $definition) {
        if (!in_array($column, $existingColumns)) {
            try {
                $sql = "ALTER TABLE staff ADD COLUMN `$column` $definition";
                $pdo->exec($sql);
                echo "<p style='color: green;'>✓ Added column: $column ($definition)</p>";
                $added++;
            } catch (Exception $e) {
                echo "<p style='color: red;'>✗ Failed to add column $column: " . $e->getMessage() . "</p>";
                $errors++;
            }
        } else {
            echo "<p style='color: blue;'>- Column '$column' already exists</p>";
        }
    }
    
    echo "<h4>Summary:</h4>";
    echo "<p>Columns added: $added</p>";
    echo "<p>Errors: $errors</p>";
    
    if ($added > 0) {
        echo "<p style='color: green; font-weight: bold;'>✓ Database schema updated successfully! You can now try updating staff records again.</p>";
    } else {
        echo "<p style='color: blue;'>No changes needed - all columns already exist.</p>";
    }
    
    // Show updated table structure
    $updatedColumns = $pdo->query('DESCRIBE staff')->fetchAll(PDO::FETCH_ASSOC);
    echo "<h4>Updated Staff Table Structure:</h4>";
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th></tr>";
    foreach ($updatedColumns as $col) {
        echo "<tr>";
        echo "<td>{$col['Field']}</td>";
        echo "<td>{$col['Type']}</td>";
        echo "<td>{$col['Null']}</td>";
        echo "<td>{$col['Key']}</td>";
        echo "<td>{$col['Default']}</td>";
        echo "</tr>";
    }
    echo "</table>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>Database Error: " . $e->getMessage() . "</p>";
}
?>
