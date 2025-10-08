<?php
session_start();

// Check if logout is due to timeout
$reason = $_GET['reason'] ?? 'manual';

// Clear all session data
$_SESSION = [];

// Destroy the session cookie if it exists
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Destroy the session
session_destroy();

// Redirect to login page with reason
if ($reason === 'timeout') {
    header('Location: /Armis2/login.php?reason=timeout');
} else {
    header('Location: /Armis2/login.php');
}
exit();
?>
