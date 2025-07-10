<?php
// Native PHP Authentication and CSRF System
// Replaces UserSpice functionality

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

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

// Authentication Functions
function isAdminLoggedIn() {
    return isset($_SESSION['user']) && $_SESSION['user']['is_admin'] === true;
}

function isUserLoggedIn() {
    return isset($_SESSION['user']) && $_SESSION['user']['logged_in'] === true;
}

function requireAdmin() {
    if (!isAdminLoggedIn()) {
        // Redirect to login page with return URL
        $current_url = $_SERVER['REQUEST_URI'];
        $login_url = getLoginUrl() . '?redirect=' . urlencode($current_url);
        
        // If this is an AJAX request, return JSON response
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
            header('Content-Type: application/json');
            http_response_code(401);
            echo json_encode(['error' => 'Administrator privileges required']);
            exit;
        }
        
        // Otherwise redirect to login
        header("Location: $login_url");
        exit;
    }
}

function requireUser() {
    if (!isUserLoggedIn()) {
        // Redirect to login page with return URL
        $current_url = $_SERVER['REQUEST_URI'];
        $login_url = getLoginUrl() . '?redirect=' . urlencode($current_url);
        
        header("Location: $login_url");
        exit;
    }
}

function loginUser($username, $password) {
    // For now, use hardcoded admin credentials
    // In production, this should check against a database
    if ($username === 'admin' && $password === 'password') {
        $_SESSION['user'] = [
            'id' => 1,
            'username' => 'admin',
            'is_admin' => true,
            'logged_in' => true,
            'login_time' => time(),
            'last_activity' => time()
        ];
        return true;
    }
    return false;
}

function logoutUser() {
    // Clear user session data
    unset($_SESSION['user']);
    unset($_SESSION['csrf_token']);
    session_destroy();
}

function getCurrentUser() {
    return isset($_SESSION['user']) ? $_SESSION['user'] : null;
}

// Smart redirection based on user roles and permissions
function redirectToDashboard() {
    $user = getCurrentUser();
    
    if (!$user) {
        redirect(getLoginUrl());
        return;
    }
    
    // Admin users go to admin dashboard
    if ($user['is_admin']) {
        redirect('users/admin.php');
        return;
    }
    
    // Regular users go to their appropriate dashboard
    // For now, everyone goes to admin dashboard since we only have admin user
    redirect('users/admin.php');
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

// Redirect function
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

// Helper function to check if user is logged in
function isLoggedIn() {
    return isset($_SESSION['user']) && $_SESSION['user']['logged_in'] === true;
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

// Permission checking functions
function hasPermission($permission) {
    $user = getCurrentUser();
    
    if (!$user) {
        return false;
    }
    
    // Admin users have all permissions
    if ($user['is_admin']) {
        return true;
    }
    
    // Add more granular permission checking here
    return false;
}

function canAccessPage($page) {
    $user = getCurrentUser();
    
    if (!$user) {
        return false;
    }
    
    // Define page access rules
    $admin_pages = [
        'admin.php',
        'admin_branch.php',
        'system_settings.php',
        'user_profile.php',
        'employees.php',
        'command_reports.php'
    ];
    
    // Check if user can access admin pages
    if (in_array($page, $admin_pages)) {
        return $user['is_admin'];
    }
    
    // Default: allow access to logged-in users
    return true;
}

// Call session timeout check on each page load
if (isLoggedIn()) {
    checkSessionTimeout();
}
?>