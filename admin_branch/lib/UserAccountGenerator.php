<?php
/**
 * User Account Generator Class
 * Handles automatic user account creation for imported staff
 * 
 * @package Armis
 * @subpackage AdminBranch
 * @version 2.0
 */

class UserAccountGenerator {
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    /**
     * Create user account for staff member
     * 
     * @param int $staffId Staff ID
     * @param string $firstName First name
     * @param string $lastName Last name
     * @param string $email Email address
     * @return array Result with success status, username, and password
     */
    public function createAccount($staffId, $firstName, $lastName, $email) {
        try {
            // Generate username
            $username = $this->generateUsername($firstName, $lastName);
            
            // Generate temporary password
            $tempPassword = $this->generateTempPassword();
            
            // Hash password
            $hashedPassword = password_hash($tempPassword, PASSWORD_DEFAULT);
            
            // Check if users table exists, create if not
            $this->ensureUsersTableExists();
            
            // Insert user account
            $stmt = $this->pdo->prepare("
                INSERT INTO users (
                    username, 
                    email, 
                    password, 
                    svcNo, 
                    must_change_password,
                    role_id,
                    createdAt
                ) VALUES (?, ?, ?, ?, 1, ?, NOW())
            ");
            
            // Default role_id = 3 (Staff User), can be changed based on your role system
            $defaultRoleId = 3;
            
            $stmt->execute([
                $username,
                $email,
                $hashedPassword,
                $staffId,
                $defaultRoleId
            ]);
            
            // Send welcome email (optional - implement if email system exists)
            // $this->sendWelcomeEmail($email, $username, $tempPassword, $firstName, $lastName);
            
            return [
                'success' => true,
                'username' => $username,
                'password' => $tempPassword,
                'message' => 'User account created successfully'
            ];
            
        } catch (PDOException $e) {
            // Handle duplicate username
            if ($e->getCode() == 23000) { // Duplicate entry
                // Try with numbered username
                $username = $this->generateUsername($firstName, $lastName, true);
                $tempPassword = $this->generateTempPassword();
                $hashedPassword = password_hash($tempPassword, PASSWORD_DEFAULT);
                
                try {
                    $stmt = $this->pdo->prepare("
                        INSERT INTO users (
                            username, 
                            email, 
                            password, 
                            svcNo, 
                            must_change_password,
                            role_id,
                            createdAt
                        ) VALUES (?, ?, ?, ?, 1, 3, NOW())
                    ");
                    
                    $stmt->execute([
                        $username,
                        $email,
                        $hashedPassword,
                        $staffId
                    ]);
                    
                    return [
                        'success' => true,
                        'username' => $username,
                        'password' => $tempPassword,
                        'message' => 'User account created with alternate username'
                    ];
                    
                } catch (Exception $e2) {
                    return [
                        'success' => false,
                        'message' => 'Failed to create user account: ' . $e2->getMessage()
                    ];
                }
            }
            
            return [
                'success' => false,
                'message' => 'Failed to create user account: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Generate username from first and last name
     * Format: firstname.lastname or firstname.lastname.XXX if duplicate
     * 
     * @param string $firstName First name
     * @param string $lastName Last name
     * @param bool $addNumber Add random number for uniqueness
     * @return string Generated username
     */
    private function generateUsername($firstName, $lastName, $addNumber = false) {
        // Clean names - remove special characters, convert to lowercase
        $firstName = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $firstName));
        $lastName = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $lastName));
        
        $username = $firstName . '.' . $lastName;
        
        if ($addNumber) {
            // Add random 3-digit number for uniqueness
            $username .= '.' . rand(100, 999);
        }
        
        // Ensure username is not too long (max 50 chars)
        if (strlen($username) > 50) {
            $username = substr($firstName, 0, 10) . '.' . substr($lastName, 0, 10);
            if ($addNumber) {
                $username .= '.' . rand(100, 999);
            }
        }
        
        return $username;
    }
    
    /**
     * Generate secure temporary password
     * Format: 8 uppercase letters + 4 digits
     * 
     * @return string Generated password
     */
    private function generateTempPassword() {
        // Generate readable password: 8 uppercase letters + 4 digits
        $letters = 'ABCDEFGHJKLMNPQRSTUVWXYZ'; // Excluding I and O to avoid confusion
        $numbers = '23456789'; // Excluding 0 and 1 to avoid confusion
        
        $password = '';
        
        // 8 random letters
        for ($i = 0; $i < 8; $i++) {
            $password .= $letters[rand(0, strlen($letters) - 1)];
        }
        
        // 4 random numbers
        for ($i = 0; $i < 4; $i++) {
            $password .= $numbers[rand(0, strlen($numbers) - 1)];
        }
        
        return $password;
    }
    
    /**
     * Ensure users table exists with required columns
     */
    private function ensureUsersTableExists() {
        try {
            // Check if table exists
            $result = $this->pdo->query("SHOW TABLES LIKE 'users'");
            
            if ($result->rowCount() === 0) {
                // Create users table if it doesn't exist
                $this->pdo->exec("
                    CREATE TABLE users (
                        id INT AUTO_INCREMENT PRIMARY KEY,
                        username VARCHAR(50) UNIQUE NOT NULL,
                        email VARCHAR(100) UNIQUE NOT NULL,
                        password VARCHAR(255) NOT NULL,
                        svcNo INT UNIQUE,
                        role_id INT DEFAULT 3,
                        must_change_password TINYINT(1) DEFAULT 1,
                        is_active TINYINT(1) DEFAULT 1,
                        createdAt DATETIME,
                        updatedAt DATETIME,
                        lastLogin DATETIME,
                        FOREIGN KEY (svcNo) REFERENCES staff(id) ON DELETE CASCADE,
                        INDEX idx_username (username),
                        INDEX idx_email (email),
                        INDEX idx_staff_id (svcNo)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                ");
            } else {
                // Check if must_change_password column exists
                $columns = $this->pdo->query("SHOW COLUMNS FROM users LIKE 'must_change_password'");
                
                if ($columns->rowCount() === 0) {
                    // Add must_change_password column if missing
                    $this->pdo->exec("
                        ALTER TABLE users 
                        ADD COLUMN must_change_password TINYINT(1) DEFAULT 1 AFTER password
                    ");
                }
            }
        } catch (Exception $e) {
            error_log("Failed to ensure users table exists: " . $e->getMessage());
        }
    }
    
    /**
     * Send welcome email to new user (placeholder)
     * Implement this based on your email system
     * 
     * @param string $email Email address
     * @param string $username Username
     * @param string $password Temporary password
     * @param string $firstName First name
     * @param string $lastName Last name
     * @return bool Success status
     */
    private function sendWelcomeEmail($email, $username, $password, $firstName, $lastName) {
        // Placeholder for email functionality
        // Implement using PHPMailer or your preferred email library
        
        $subject = "Welcome to ARMIS - Your Account Details";
        $message = "
            Dear $firstName $lastName,
            
            Your account has been created in the ARMIS system.
            
            Login Details:
            Username: $username
            Temporary Password: $password
            
            Please log in and change your password immediately.
            
            Login URL: " . ($_SERVER['HTTP_HOST'] ?? 'localhost') . "/Armis2/login.php
            
            Best regards,
            ARMIS Admin Team
        ";
        
        // For now, just log it
        error_log("Welcome email would be sent to: $email");
        
        // Uncomment and implement when email system is ready
        // return mail($email, $subject, $message);
        
        return true;
    }
}
