<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>ARMIS Profile Diagnostic</h2>\n";

// Check session
if (!isset($_SESSION['user_id'])) {
    echo "<p style='color: red;'>❌ User not logged in - no user_id in session</p>\n";
    exit;
}

$user_id = $_SESSION['user_id'];
echo "<p style='color: green;'>✅ User logged in with ID: $user_id</p>\n";

// Include database connection
try {
    require_once '../shared/database_connection.php';
    $pdo = getDbConnection();
    echo "<p style='color: green;'>✅ Database connection successful</p>\n";
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Database connection failed: " . $e->getMessage() . "</p>\n";
    exit;
}

// Check if staff table exists and user has record
try {
    $stmt = $pdo->prepare("DESCRIBE staff");
    $stmt->execute();
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
    echo "<p style='color: green;'>✅ Staff table exists with " . count($columns) . " columns</p>\n";
    
    // Show available columns
    echo "<details><summary>Staff Table Columns</summary><ul>\n";
    foreach ($columns as $column) {
        echo "<li>$column</li>\n";
    }
    echo "</ul></details>\n";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Staff table error: " . $e->getMessage() . "</p>\n";
}

// Check user record
try {
    $stmt = $pdo->prepare("SELECT * FROM staff WHERE id = ?");
    $stmt->execute([$user_id]);
    $staff = $stmt->fetch(PDO::FETCH_OBJ);
    
    if ($staff) {
        echo "<p style='color: green;'>✅ Staff record found</p>\n";
        echo "<p>Name: " . ($staff->first_name ?? 'N/A') . " " . ($staff->last_name ?? 'N/A') . "</p>\n";
        echo "<p>Service Number: " . ($staff->service_number ?? $staff->svcNo ?? 'N/A') . "</p>\n";
    } else {
        echo "<p style='color: red;'>❌ No staff record found for user ID: $user_id</p>\n";
        
        // Check users table
        $userStmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
        $userStmt->execute([$user_id]);
        $user = $userStmt->fetch(PDO::FETCH_OBJ);
        
        if ($user) {
            echo "<p style='color: orange;'>⚠️ User exists in users table but not in staff table</p>\n";
            echo "<p>Username: " . ($user->username ?? 'N/A') . "</p>\n";
        }
    }
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Error checking user record: " . $e->getMessage() . "</p>\n";
}

// Check education table
try {
    $stmt = $pdo->prepare("DESCRIBE staff_education");
    $stmt->execute();
    $eduColumns = $stmt->fetchAll(PDO::FETCH_COLUMN);
    echo "<p style='color: green;'>✅ Education table exists with " . count($eduColumns) . " columns</p>\n";
    
    echo "<details><summary>Education Table Columns</summary><ul>\n";
    foreach ($eduColumns as $column) {
        echo "<li>$column</li>\n";
    }
    echo "</ul></details>\n";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Education table error: " . $e->getMessage() . "</p>\n";
    
    // Try to create education table
    echo "<p>Attempting to create education table...</p>\n";
    try {
        $createTable = "CREATE TABLE IF NOT EXISTS staff_education (
            id INT AUTO_INCREMENT PRIMARY KEY,
            staff_id INT NOT NULL,
            institution VARCHAR(255),
            qualification VARCHAR(255),
            level ENUM('Primary', 'Secondary', 'Certificate', 'Diploma', 'Degree', 'Masters', 'PhD', 'Other'),
            field_of_study VARCHAR(255),
            year_started YEAR,
            year_completed YEAR,
            grade_obtained VARCHAR(50),
            status ENUM('Completed', 'In Progress', 'Discontinued'),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (staff_id) REFERENCES staff(id) ON DELETE CASCADE
        )";
        $pdo->exec($createTable);
        echo "<p style='color: green;'>✅ Education table created successfully</p>\n";
    } catch (Exception $e) {
        echo "<p style='color: red;'>❌ Failed to create education table: " . $e->getMessage() . "</p>\n";
    }
}

// Check contact info table
try {
    $stmt = $pdo->prepare("DESCRIBE staff_contact_info");
    $stmt->execute();
    $contactColumns = $stmt->fetchAll(PDO::FETCH_COLUMN);
    echo "<p style='color: green;'>✅ Contact info table exists with " . count($contactColumns) . " columns</p>\n";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Contact info table error: " . $e->getMessage() . "</p>\n";
    
    // Try to create contact info table
    try {
        $createContactTable = "CREATE TABLE IF NOT EXISTS staff_contact_info (
            id INT AUTO_INCREMENT PRIMARY KEY,
            staff_id INT NOT NULL,
            contact_type ENUM('Mobile', 'Home', 'Work', 'Emergency', 'Email', 'Other') DEFAULT 'Mobile',
            contact_name VARCHAR(255),
            relationship VARCHAR(100),
            phone VARCHAR(20),
            email VARCHAR(255),
            address TEXT,
            is_primary BOOLEAN DEFAULT FALSE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (staff_id) REFERENCES staff(id) ON DELETE CASCADE
        )";
        $pdo->exec($createContactTable);
        echo "<p style='color: green;'>✅ Contact info table created successfully</p>\n";
    } catch (Exception $e) {
        echo "<p style='color: red;'>❌ Failed to create contact info table: " . $e->getMessage() . "</p>\n";
    }
}

echo "<h3>Profile Manager Test</h3>\n";
try {
    require_once 'profile_manager.php';
    $profileManager = new UserProfileManager($user_id);
    $userData = $profileManager->getUserProfile();
    
    if ($userData) {
        echo "<p style='color: green;'>✅ Profile Manager working correctly</p>\n";
        echo "<p>Full Name: " . ($userData->fullName ?? 'N/A') . "</p>\n";
    } else {
        echo "<p style='color: red;'>❌ Profile Manager returned null</p>\n";
    }
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Profile Manager error: " . $e->getMessage() . "</p>\n";
}

echo "<p><a href='personal.php'>Return to Personal Form</a></p>\n";
?>
