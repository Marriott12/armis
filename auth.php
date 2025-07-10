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

function requireAdmin() {
    if (!isAdminLoggedIn()) {
        http_response_code(403);
        echo "Access denied. Administrator privileges required.";
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
            'logged_in' => true
        ];
        return true;
    }
    return false;
}

function logoutUser() {
    unset($_SESSION['user']);
    session_destroy();
}

function getCurrentUser() {
    return isset($_SESSION['user']) ? $_SESSION['user'] : null;
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
?>