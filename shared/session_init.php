<?php
/**
 * ARMIS Authentication Session Setup
 * Initializes proper session data for military formatting
 */

// Sample authentication function - replace with actual database authentication
function initializeUserSession($userId = null) {
    // Sample user data - replace with actual database query
    $sampleUsers = [
        1 => [
            'user_id' => 1,
            'username' => 'admin',
            'svcNo' => 'AR001001',
            'rank' => 'Colonel', 
            'rank_abbr' => 'Col',
            'fname' => 'John',
            'lname' => 'Smith',
            'category' => 'Officer',
            'role' => 'administrator',
            'unit' => 'Headquarters Command',
            'unit_name' => 'HQ Command',
            'corps' => 'Army Corps of Engineers',
            'corps_name' => 'Army Corps of Engineers',
            'corps_abbr' => 'ACE',
            'last_login' => date('Y-m-d H:i:s')
        ],
        2 => [
            'user_id' => 2,
            'username' => 'commander',
            'svcNo' => 'AR001002',
            'rank' => 'General',
            'rank_abbr' => 'Gen', 
            'fname' => 'Michael',
            'lname' => 'Johnson',
            'category' => 'Officer',
            'role' => 'command',
            'unit' => 'Headquarters Command',
            'unit_name' => 'HQ Command',
            'corps' => 'Infantry Corps',
            'corps_name' => 'Infantry Corps',
            'corps_abbr' => 'INF',
            'last_login' => date('Y-m-d H:i:s')
        ],
        3 => [
            'user_id' => 3,
            'username' => 'sergeant',
            'svcNo' => 'AR001006',
            'rank' => 'Sergeant',
            'rank_abbr' => 'Sgt',
            'fname' => 'Robert',
            'lname' => 'Miller',
            'category' => 'NCO',
            'role' => 'user',
            'unit' => 'Alpha Company',
            'unit_name' => 'Alpha Company',
            'corps' => 'Armoured Corps',
            'corps_name' => 'Armoured Corps',
            'corps_abbr' => 'ARM',
            'last_login' => date('Y-m-d H:i:s')
        ]
    ];
    
    // Default to user 1 if no specific user ID
    $userData = $sampleUsers[$userId] ?? $sampleUsers[1];
    
    // Set session variables
    foreach ($userData as $key => $value) {
        $_SESSION[$key] = $value;
    }
    
    // Set additional commonly used session variables
    $_SESSION['name'] = $userData['fname'] . ' ' . $userData['lname'];
    $_SESSION['first_name'] = $userData['fname'];
    $_SESSION['last_name'] = $userData['lname'];
    $_SESSION['service_number'] = $userData['svcNo'];
    
    return true;
}

// Check if session needs initialization
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Initialize session if user_id exists but other data is missing
if (isset($_SESSION['user_id']) && !isset($_SESSION['rank'])) {
    initializeUserSession($_SESSION['user_id']);
}

// If no session at all, create a default session for demonstration
if (!isset($_SESSION['user_id'])) {
    initializeUserSession(1); // Default to admin user
}
?>
