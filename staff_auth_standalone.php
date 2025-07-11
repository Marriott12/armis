<?php
// Standalone Staff Authentication System
// This version doesn't depend on UserSpice init.php

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Database configuration - adjust these values for your setup
$DB_CONFIG = [
    'host' => 'localhost',
    'port' => '3306',
    'dbname' => 'armis',
    'username' => 'root',
    'password' => '',
    'charset' => 'utf8mb4'
];

// CSRF Token Functions
class CSRFToken {
    public static function generate() {
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }
    
    public static function validate($token) {
        if (!isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
            return false;
        }
        return true;
    }
    
    public static function getField() {
        $token = self::generate();
        return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($token) . '">';
    }
}

// Database connection function
function getDatabase() {
    global $DB_CONFIG;
    static $pdo = null;
    
    if ($pdo !== null) {
        return $pdo;
    }
    
    try {
        $dsn = "mysql:host={$DB_CONFIG['host']};port={$DB_CONFIG['port']};dbname={$DB_CONFIG['dbname']};charset={$DB_CONFIG['charset']}";
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];
        $pdo = new PDO($dsn, $DB_CONFIG['username'], $DB_CONFIG['password'], $options);
        return $pdo;
    } catch (PDOException $e) {
        error_log("Database connection failed: " . $e->getMessage());
        throw new Exception("Database connection failed");
    }
}

// Staff Authentication Functions
function loginUser($username, $password) {
    try {
        $db = getDatabase();
        
        // Get staff member by username
        $stmt = $db->prepare("SELECT * FROM staff WHERE username = ? AND accStatus = 'Active'");
        $stmt->execute([$username]);
        $staff = $stmt->fetch();
        
        if (!$staff) {
            logFailedLogin($username, 'Invalid username');
            return false;
        }
        
        // Check if account is locked
        if ($staff['locked_until'] && new DateTime() < new DateTime($staff['locked_until'])) {
            logFailedLogin($username, 'Account locked');
            return false;
        }
        
        // Verify password
        if (!password_verify($password, $staff['password'])) {
            // Increment failed login attempts
            incrementFailedLogins($staff['svcNo']);
            logFailedLogin($username, 'Invalid password');
            return false;
        }
        
        // Reset failed login attempts on successful login
        resetFailedLogins($staff['svcNo']);
        
        // Update last login time
        updateLastLogin($staff['svcNo']);
        
        // Set session data
        $_SESSION['user'] = [
            'svcNo' => $staff['svcNo'],
            'username' => $staff['username'],
            'fname' => $staff['fname'],
            'lname' => $staff['lname'],
            'email' => $staff['email'],
            'role' => $staff['role'],
            'must_reset_password' => isset($staff['must_reset_password']) ? (bool)$staff['must_reset_password'] : false,
            'logged_in' => true,
            'login_time' => time(),
            'last_activity' => time()
        ];
        
        // Log successful login
        logActivity($staff['svcNo'], 'login', 'User logged in successfully');
        
        return true;
        
    } catch (Exception $e) {
        error_log("Login error: " . $e->getMessage());
        return false;
    }
}

function incrementFailedLogins($svcNo) {
    try {
        $db = getDatabase();
        
        // Check if failed_login_attempts column exists
        $stmt = $db->prepare("SHOW COLUMNS FROM staff LIKE 'failed_login_attempts'");
        $stmt->execute();
        if (!$stmt->fetch()) {
            // Column doesn't exist, skip this functionality
            return;
        }
        
        $stmt = $db->prepare("UPDATE staff SET failed_login_attempts = failed_login_attempts + 1 WHERE svcNo = ?");
        $stmt->execute([$svcNo]);
        
        // Check if we need to lock the account (after 5 failed attempts)
        $stmt = $db->prepare("SELECT failed_login_attempts FROM staff WHERE svcNo = ?");
        $stmt->execute([$svcNo]);
        $attempts = $stmt->fetchColumn();
        
        if ($attempts >= 5) {
            // Lock account for 15 minutes
            $lockUntil = (new DateTime())->add(new DateInterval('PT15M'))->format('Y-m-d H:i:s');
            $stmt = $db->prepare("UPDATE staff SET locked_until = ? WHERE svcNo = ?");
            $stmt->execute([$lockUntil, $svcNo]);
            
            logActivity($svcNo, 'security', 'Account locked due to failed login attempts');
        }
    } catch (Exception $e) {
        error_log("Failed to increment failed logins: " . $e->getMessage());
    }
}

function resetFailedLogins($svcNo) {
    try {
        $db = getDatabase();
        
        // Check if columns exist before updating
        $stmt = $db->prepare("SHOW COLUMNS FROM staff LIKE 'failed_login_attempts'");
        $stmt->execute();
        if ($stmt->fetch()) {
            $stmt = $db->prepare("UPDATE staff SET failed_login_attempts = 0, locked_until = NULL WHERE svcNo = ?");
            $stmt->execute([$svcNo]);
        }
    } catch (Exception $e) {
        error_log("Failed to reset failed logins: " . $e->getMessage());
    }
}

function updateLastLogin($svcNo) {
    try {
        $db = getDatabase();
        
        // Check if last_login column exists
        $stmt = $db->prepare("SHOW COLUMNS FROM staff LIKE 'last_login'");
        $stmt->execute();
        if ($stmt->fetch()) {
            $stmt = $db->prepare("UPDATE staff SET last_login = NOW() WHERE svcNo = ?");
            $stmt->execute([$svcNo]);
        }
    } catch (Exception $e) {
        error_log("Failed to update last login: " . $e->getMessage());
    }
}

function logFailedLogin($username, $reason) {
    try {
        $db = getDatabase();
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        
        // Check if logs table exists
        $stmt = $db->prepare("SHOW TABLES LIKE 'logs'");
        $stmt->execute();
        if ($stmt->fetch()) {
            $stmt = $db->prepare("INSERT INTO logs (user_id, logtype, lognote, ip) VALUES (0, 'Login Fail', ?, ?)");
            $stmt->execute(["Failed login attempt for '$username': $reason", $ip]);
        }
    } catch (Exception $e) {
        error_log("Failed to log failed login: " . $e->getMessage());
    }
}

function logActivity($svcNo, $type, $note) {
    try {
        $db = getDatabase();
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        
        // Check if logs table exists
        $stmt = $db->prepare("SHOW TABLES LIKE 'logs'");
        $stmt->execute();
        if ($stmt->fetch()) {
            $stmt = $db->prepare("INSERT INTO logs (user_id, logtype, lognote, ip) VALUES (?, ?, ?, ?)");
            $stmt->execute([$svcNo, $type, $note, $ip]);
        }
    } catch (Exception $e) {
        error_log("Failed to log activity: " . $e->getMessage());
    }
}

function logoutUser() {
    if (isset($_SESSION['user'])) {
        logActivity($_SESSION['user']['svcNo'], 'logout', 'User logged out');
    }
    
    // Clear user session data
    unset($_SESSION['user']);
    unset($_SESSION['csrf_token']);
    
    // Destroy session
    session_destroy();
}

function getCurrentUser() {
    return isset($_SESSION['user']) ? $_SESSION['user'] : null;
}

function isLoggedIn() {
    return isset($_SESSION['user']) && $_SESSION['user']['logged_in'] === true;
}

function mustResetPassword() {
    $user = getCurrentUser();
    return $user && isset($user['must_reset_password']) && $user['must_reset_password'];
}

// Permission checking functions
function hasRole($role) {
    $user = getCurrentUser();
    return $user && $user['role'] === $role;
}

function isAdmin() {
    return hasRole('Admin') || hasRole('Super Admin');
}

function isAdminBranch() {
    return hasRole('Admin Branch') || isAdmin();
}

function canAccessPage($page) {
    if (!isLoggedIn()) {
        return false;
    }
    
    // If user must reset password, only allow access to password reset page
    if (mustResetPassword()) {
        $allowedPages = ['reset_password.php', 'logout.php'];
        return in_array(basename($page), $allowedPages);
    }
    
    return true;
}

// Page protection functions
function requireLogin() {
    if (!isLoggedIn()) {
        $current_url = $_SERVER['REQUEST_URI'];
        $login_url = getLoginUrl() . '?redirect=' . urlencode($current_url);
        
        // If this is an AJAX request, return JSON response
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
            header('Content-Type: application/json');
            http_response_code(401);
            echo json_encode(['error' => 'Authentication required']);
            exit;
        }
        
        header("Location: $login_url");
        exit;
    }
    
    // Check if user must reset password
    if (mustResetPassword()) {
        $currentPage = basename($_SERVER['PHP_SELF']);
        if (!in_array($currentPage, ['reset_password.php', 'logout.php'])) {
            header("Location: reset_password.php");
            exit;
        }
    }
}

function requireAdmin() {
    requireLogin();
    
    if (!isAdmin()) {
        setMessage('Administrative privileges required.', 'error');
        redirectToDashboard();
    }
}

function requireAdminBranch() {
    requireLogin();
    
    if (!isAdminBranch()) {
        setMessage('Admin Branch privileges required.', 'error');
        redirectToDashboard();
    }
}

// Password reset functions
function generateResetToken($svcNo) {
    try {
        $db = getDatabase();
        
        // Check if reset token columns exist
        $stmt = $db->prepare("SHOW COLUMNS FROM staff LIKE 'reset_token'");
        $stmt->execute();
        if (!$stmt->fetch()) {
            return false; // Columns don't exist
        }
        
        // Generate secure token
        $token = bin2hex(random_bytes(32));
        $expiry = (new DateTime())->add(new DateInterval('PT1H'))->format('Y-m-d H:i:s'); // 1 hour expiry
        
        // Store token in database
        $stmt = $db->prepare("UPDATE staff SET reset_token = ?, reset_token_expiry = ? WHERE svcNo = ?");
        $stmt->execute([$token, $expiry, $svcNo]);
        
        return $token;
    } catch (Exception $e) {
        error_log("Failed to generate reset token: " . $e->getMessage());
        return false;
    }
}

function validateResetToken($token) {
    try {
        $db = getDatabase();
        
        $stmt = $db->prepare("SELECT * FROM staff WHERE reset_token = ? AND reset_token_expiry > NOW() AND accStatus = 'Active'");
        $stmt->execute([$token]);
        return $stmt->fetch();
        
    } catch (Exception $e) {
        error_log("Failed to validate reset token: " . $e->getMessage());
        return false;
    }
}

function resetPassword($token, $newPassword) {
    try {
        $staff = validateResetToken($token);
        if (!$staff) {
            return false;
        }
        
        $db = getDatabase();
        
        // Hash new password
        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
        
        // Update password and clear reset token
        $stmt = $db->prepare("UPDATE staff SET password = ?, reset_token = NULL, reset_token_expiry = NULL, must_reset_password = 0 WHERE svcNo = ?");
        $stmt->execute([$hashedPassword, $staff['svcNo']]);
        
        logActivity($staff['svcNo'], 'password_reset', 'Password reset successfully');
        
        return true;
        
    } catch (Exception $e) {
        error_log("Failed to reset password: " . $e->getMessage());
        return false;
    }
}

// Staff creation function
function createStaff($staffData, $createdBy) {
    try {
        $db = getDatabase();
        
        // Generate temporary password
        $tempPassword = generateTempPassword();
        $hashedPassword = password_hash($tempPassword, PASSWORD_DEFAULT);
        
        // Prepare staff data with authentication fields
        $staffData['password'] = $hashedPassword;
        if (hasColumn('staff', 'must_reset_password')) {
            $staffData['must_reset_password'] = 1; // Force password reset on first login
        }
        $staffData['accStatus'] = 'Active';
        $staffData['createdBy'] = $createdBy;
        
        // Build SQL for insertion
        $fields = array_keys($staffData);
        $placeholders = str_repeat('?,', count($fields) - 1) . '?';
        $sql = "INSERT INTO staff (" . implode(',', $fields) . ") VALUES ($placeholders)";
        
        $stmt = $db->prepare($sql);
        $stmt->execute(array_values($staffData));
        
        logActivity($createdBy, 'staff_creation', "Created staff member: {$staffData['username']}");
        
        return [
            'success' => true,
            'svcNo' => $staffData['svcNo'],
            'tempPassword' => $tempPassword
        ];
        
    } catch (Exception $e) {
        error_log("Failed to create staff: " . $e->getMessage());
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

function hasColumn($table, $column) {
    try {
        $db = getDatabase();
        $stmt = $db->prepare("SHOW COLUMNS FROM $table LIKE ?");
        $stmt->execute([$column]);
        return $stmt->fetch() !== false;
    } catch (Exception $e) {
        return false;
    }
}

function generateTempPassword($length = 12) {
    $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*';
    return substr(str_shuffle($chars), 0, $length);
}

// Redirection functions
function redirectToDashboard() {
    $user = getCurrentUser();
    
    if (!$user) {
        redirect(getLoginUrl());
        return;
    }
    
    // If user must reset password, go to reset page
    if (mustResetPassword()) {
        redirect('reset_password.php');
        return;
    }
    
    // Admin Branch users go to admin branch dashboard
    if (isAdminBranch()) {
        redirect('users/admin_branch.php');
        return;
    }
    
    // Regular users go to account page
    redirect('users/account_staff.php');
}

function getLoginUrl() {
    // Get the correct login URL based on current location
    $current_dir = dirname($_SERVER['SCRIPT_NAME']);
    
    if (strpos($current_dir, '/users') !== false) {
        return '../login.php';
    } else {
        return 'login.php';
    }
}

function redirect($url) {
    header("Location: $url");
    exit;
}

// Success/Error message functions
function setMessage($message, $type = 'success') {
    $_SESSION['messages'][] = ['message' => $message, 'type' => $type];
}

function getMessages() {
    $messages = isset($_SESSION['messages']) ? $_SESSION['messages'] : [];
    unset($_SESSION['messages']);
    return $messages;
}

function displayMessages() {
    $messages = getMessages();
    foreach ($messages as $msg) {
        $class = $msg['type'] === 'error' ? 'alert-danger' : 'alert-success';
        echo '<div class="alert ' . $class . ' alert-dismissible fade show" role="alert">';
        echo htmlspecialchars($msg['message']);
        echo '<button type="button" class="btn-close" data-bs-dismiss="alert"></button>';
        echo '</div>';
    }
}

// Session management
function checkSessionTimeout() {
    if (!isLoggedIn()) {
        return;
    }
    
    $timeout = 30 * 60; // 30 minutes
    $current_time = time();
    
    if (isset($_SESSION['user']['last_activity'])) {
        $elapsed = $current_time - $_SESSION['user']['last_activity'];
        
        if ($elapsed > $timeout) {
            logoutUser();
            setMessage('Session expired. Please login again.', 'error');
            redirect(getLoginUrl());
        }
    }
    
    // Update last activity
    $_SESSION['user']['last_activity'] = $current_time;
}

// Call session timeout check on each page load
if (isLoggedIn()) {
    checkSessionTimeout();
}
?>