<?php
/**
 * Script to insert or update admin user: Marriott
 * Service Number: 007414
 */

require_once __DIR__ . '/../shared/database_connection.php';

try {
    $pdo = getDbConnection();
    
    // Check if user exists
    $checkStmt = $pdo->prepare("SELECT svcNo FROM staff WHERE svcNo = ?");
    $checkStmt->execute(['007414']);
    $userExists = $checkStmt->fetch();
    
    // Hash the password
    $passwordHash = password_hash('Armis@2026', PASSWORD_DEFAULT);
    
    if ($userExists) {
        echo "⚠️  User already exists. Updating...\n\n";
        
        // Update existing user
        $sql = "UPDATE staff SET 
            fName = :fName, 
            mName = :mName, 
            lName = :lName, 
            username = :username, 
            password = :password, 
            role = :role, 
            accStatus = :accStatus, 
            isFirstLogin = :isFirstLogin,
            updatedAt = NOW()
        WHERE svcNo = :svcNo";
        
        $stmt = $pdo->prepare($sql);
        
        $result = $stmt->execute([
            ':svcNo' => '007414',
            ':fName' => 'Marriott',
            ':mName' => 'Gift',
            ':lName' => 'Mumba',
            ':username' => 'Marriott',
            ':password' => $passwordHash,
            ':role' => 'admin,admin_branch',
            ':accStatus' => 'active',
            ':isFirstLogin' => 0
        ]);
        
        if ($result) {
            echo "✅ SUCCESS: Admin user updated successfully!\n\n";
        } else {
            echo "❌ ERROR: Failed to update user.\n";
        }
        
    } else {
        // Insert new user
        $sql = "INSERT INTO staff (
            svcNo, 
            fName, 
            mName, 
            lName, 
            username, 
            password, 
            role, 
            accStatus, 
            isFirstLogin,
            dateCreated
        ) VALUES (
            :svcNo,
            :fName,
            :mName,
            :lName,
            :username,
            :password,
            :role,
            :accStatus,
            :isFirstLogin,
            NOW()
        )";
        
        $stmt = $pdo->prepare($sql);
        
        $result = $stmt->execute([
            ':svcNo' => '007414',
            ':fName' => 'Marriott',
            ':mName' => 'Gift',
            ':lName' => 'Mumba',
            ':username' => 'Marriott',
            ':password' => $passwordHash,
            ':role' => 'admin,admin_branch',
            ':accStatus' => 'active',
            ':isFirstLogin' => 0
        ]);
        
        if ($result) {
            echo "✅ SUCCESS: Admin user created successfully!\n\n";
        } else {
            echo "❌ ERROR: Failed to insert user.\n";
        }
    }
    
    echo "Login credentials:\n";
    echo "==================\n";
    echo "Username: Marriott\n";
    echo "Password: Armis@2026\n";
    echo "Service No: 007414\n";
    echo "Role: admin,admin_branch\n";
    echo "Status: active\n";
    echo "\nYou can now login with these credentials.\n";
    
} catch (PDOException $e) {
    echo "❌ DATABASE ERROR: " . $e->getMessage() . "\n";
}

