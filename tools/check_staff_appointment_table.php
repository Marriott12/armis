<?php
/**
 * Check and create missing staff_appointment table
 */

require_once __DIR__ . '/../shared/database_connection.php';

try {
    $pdo = getDbConnection();
    
    // Check if staff_appointment table exists
    $stmt = $pdo->query("SHOW TABLES LIKE 'staff_appointment'");
    $tableExists = $stmt->fetch();
    
    if (!$tableExists) {
        echo "⚠️  'staff_appointment' table not found!\n";
        echo "Creating staff_appointment table...\n\n";
        
        // Create the staff_appointment table based on conversation context
        $createTableSQL = "CREATE TABLE IF NOT EXISTS `staff_appointment` (
            `id` int NOT NULL AUTO_INCREMENT,
            `apptId` varchar(20) DEFAULT NULL,
            `svcNo` varchar(20) NOT NULL,
            `apptType` varchar(50) DEFAULT NULL,
            `unitId` varchar(10) DEFAULT NULL,
            `apptWef` date DEFAULT NULL,
            `powers` varchar(100) DEFAULT NULL,
            `endDate` date DEFAULT NULL,
            `durationMonths` int DEFAULT NULL,
            `authorityId` varchar(20) DEFAULT NULL,
            `remarks` text,
            PRIMARY KEY (`id`),
            KEY `idx_svcNo` (`svcNo`),
            KEY `idx_apptId` (`apptId`),
            KEY `idx_unitId` (`unitId`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";
        
        $pdo->exec($createTableSQL);
        echo "✅ staff_appointment table created successfully!\n";
    } else {
        echo "✅ staff_appointment table already exists.\n";
    }
    
} catch (PDOException $e) {
    echo "❌ DATABASE ERROR: " . $e->getMessage() . "\n";
}
