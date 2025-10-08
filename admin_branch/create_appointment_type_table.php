<?php
/**
 * Create and populate appointment_type lookup table
 */

$host = 'localhost';
$dbname = 'armis1';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "Creating appointment_type table...\n";
    
    // Create the table
    $createTable = "
    CREATE TABLE IF NOT EXISTS appointment_type (
        id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
        type_name VARCHAR(50) NOT NULL UNIQUE,
        description VARCHAR(255) DEFAULT NULL,
        is_temporary TINYINT(1) DEFAULT 0,
        default_duration_months INT DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci
    ";
    
    $pdo->exec($createTable);
    echo "✅ Table created successfully\n\n";
    
    // Insert the three appointment types
    echo "Inserting appointment types...\n";
    
    $insert = $pdo->prepare("
        INSERT INTO appointment_type (type_name, description, is_temporary, default_duration_months) 
        VALUES (?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE 
            description = VALUES(description),
            is_temporary = VALUES(is_temporary),
            default_duration_months = VALUES(default_duration_months)
    ");
    
    $types = [
        ['Acting', 'Acting appointment - temporary position', 1, 12],
        ['Secondment', 'Secondment to another unit/organization', 1, 24],
        ['Substantive', 'Permanent substantive appointment', 0, null]
    ];
    
    foreach ($types as $type) {
        $insert->execute($type);
        echo "✅ Inserted: {$type[0]}\n";
    }
    
    echo "\n=== Appointment Types in Database ===\n\n";
    $result = $pdo->query("SELECT * FROM appointment_type ORDER BY id");
    foreach ($result->fetchAll(PDO::FETCH_ASSOC) as $row) {
        echo "ID: {$row['id']}, Type: {$row['type_name']}, Temporary: " . ($row['is_temporary'] ? 'Yes' : 'No');
        if ($row['default_duration_months']) {
            echo ", Duration: {$row['default_duration_months']} months";
        }
        echo "\n";
    }
    
    echo "\n✅ Migration completed successfully!\n";
    
} catch (PDOException $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}
?>
