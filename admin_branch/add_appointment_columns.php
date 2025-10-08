<?php
/**
 * Database Migration Script
 * Adds missing columns to staff_appointment table
 * 
 * This script adds:
 * - appointment_type: To track if appointment is permanent, acting, secondment, etc.
 * - end_date: To track when temporary appointments end
 * 
 * Run this once to update the database schema
 */

// Database connection
$host = 'localhost';
$dbname = 'armis1';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "✅ Connected to database<br>";
} catch (PDOException $e) {
    die("❌ Database connection failed: " . $e->getMessage());
}

try {
    // Add appointment_type column
    $sql1 = "ALTER TABLE staff_appointment 
             ADD COLUMN appointment_type VARCHAR(20) NULL 
             AFTER appointment_id";
    
    $pdo->exec($sql1);
    echo "✅ Added 'appointment_type' column successfully<br>";
    
    // Add end_date column
    $sql2 = "ALTER TABLE staff_appointment 
             ADD COLUMN end_date DATE NULL 
             AFTER appointment_date";
    
    $pdo->exec($sql2);
    echo "✅ Added 'end_date' column successfully<br>";
    
    echo "<br><strong>Migration completed successfully!</strong><br>";
    echo "You can now use the appointments system with the new features.";
    
} catch (PDOException $e) {
    if ($e->getCode() == '42S21') {
        echo "⚠️ Columns already exist. No changes needed.<br>";
        echo "Error: " . $e->getMessage();
    } else {
        echo "❌ Error: " . $e->getMessage() . "<br>";
        echo "Code: " . $e->getCode();
    }
}
?>
