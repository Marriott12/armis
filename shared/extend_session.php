<?php
/**
 * Session Extension Handler
 * Extends user session when requested
 */

session_start();

// Set JSON header
header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'No active session'
    ]);
    exit;
}

// Check request method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid request method'
    ]);
    exit;
}

// Get POST data
$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['extend']) || $input['extend'] !== true) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid request'
    ]);
    exit;
}

try {
    // Regenerate session ID for security
    session_regenerate_id(true);
    
    // Update last activity timestamp
    $_SESSION['last_activity'] = time();
    
    // Log the extension
    if (function_exists('logActivity')) {
        logActivity('session_extended', 'User extended session due to activity');
    }
    
    // Return success
    echo json_encode([
        'success' => true,
        'message' => 'Session extended successfully',
        'timestamp' => time(),
        'expires_in' => SESSION_TIMEOUT ?? 3600
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Failed to extend session',
        'error' => $e->getMessage()
    ]);
}
?>
