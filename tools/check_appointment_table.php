<?php
/**
 * Check and create missing appointment_type table
 */

require_once __DIR__ . '/../shared/database_connection.php';

try {
    $pdo = getDbConnection();
    
    // Check if appointment_type table exists (might be called appointment)
    $stmt = $pdo->query("SHOW TABLES LIKE 'appointment'");
    $appointmentExists = $stmt->fetch();
    
    if ($appointmentExists) {
        echo "✅ 'appointment' table exists (used for appointment types).\n";
        
        // Check the structure
        echo "\nChecking table structure...\n";
        $stmt = $pdo->query("DESCRIBE appointment");
        $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
        echo "Columns: " . implode(', ', $columns) . "\n";
        
        // Check if we need to add missing columns
        if (!in_array('apptType', $columns) && in_array('type', $columns)) {
            echo "\nNote: Table uses 'type' column instead of 'apptType'\n";
        }
        
    } else {
        echo "⚠️  'appointment' table not found!\n";
        echo "Creating appointment table...\n\n";
        
        // Create the appointment table (appointment types)
        $createTableSQL = "CREATE TABLE IF NOT EXISTS `appointment` (
            `apptId` varchar(20) NOT NULL,
            `apptType` varchar(100) NOT NULL,
            `authorityId` varchar(20) DEFAULT NULL,
            PRIMARY KEY (`apptId`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";
        
        $pdo->exec($createTableSQL);
        echo "✅ appointment table created successfully!\n\n";
        
        // Insert some default appointment types
        echo "Inserting default appointment data...\n";
        $defaultAppointments = [
            ['CMD', 'Commander', 'AUTH001'],
            ['2IC', 'Second in Command', 'AUTH001'],
            ['ADJ', 'Adjutant', 'AUTH001'],
            ['QM', 'Quartermaster', 'AUTH001'],
            ['RSM', 'Regimental Sergeant Major', 'AUTH001'],
            ['CSM', 'Company Sergeant Major', 'AUTH001'],
            ['PLCOMD', 'Platoon Commander', 'AUTH001'],
            ['SECCOMD', 'Section Commander', 'AUTH001']
        ];
        
        $insertStmt = $pdo->prepare("INSERT INTO appointment (apptId, apptType, authorityId) VALUES (?, ?, ?)");
        foreach ($defaultAppointments as $appt) {
            $insertStmt->execute($appt);
            echo "  ✓ Added: {$appt[1]} ({$appt[0]})\n";
        }
        
        echo "\n✅ Default appointment data inserted successfully!\n";
    }
    
} catch (PDOException $e) {
    echo "❌ DATABASE ERROR: " . $e->getMessage() . "\n";
}
