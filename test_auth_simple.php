<?php
// Simple syntax and logic test for Staff Authentication System
// This tests the functions without requiring a database connection

ini_set('display_errors', 1);
error_reporting(E_ALL);

echo "=== ARMIS Staff Authentication System - Syntax Test ===\n\n";

// Test 1: Check if main files have valid syntax
echo "1. Testing file syntax...\n";
$files = [
    'staff_auth_standalone.php',
    'login.php', 
    'reset_password.php',
    'logout.php'
];

foreach ($files as $file) {
    $output = [];
    $return_var = 0;
    exec("php -l $file 2>&1", $output, $return_var);
    
    if ($return_var === 0) {
        echo "   ✓ $file - No syntax errors\n";
    } else {
        echo "   ✗ $file - Syntax errors found:\n";
        foreach ($output as $line) {
            echo "     $line\n";
        }
    }
}

// Test 2: Test that we can include the auth system without errors
echo "\n2. Testing authentication system inclusion...\n";
try {
    // Mock database function to avoid connection errors
    function getDatabase() {
        throw new Exception("Database connection mocked");
    }
    
    // Include the auth system
    require_once 'staff_auth_standalone.php';
    echo "   ✓ Authentication system loaded successfully\n";
} catch (Exception $e) {
    echo "   ✗ Error loading authentication system: " . $e->getMessage() . "\n";
}

// Test 3: Test CSRF functions
echo "\n3. Testing CSRF token functions...\n";
try {
    if (!session_id()) {
        session_start();
    }
    
    $token1 = CSRFToken::generate();
    $token2 = CSRFToken::generate();
    
    if ($token1 === $token2 && strlen($token1) === 64) {
        echo "   ✓ CSRF token generation working\n";
    } else {
        echo "   ✗ CSRF token generation failed\n";
    }
    
    if (CSRFToken::validate($token1)) {
        echo "   ✓ CSRF token validation working\n";
    } else {
        echo "   ✗ CSRF token validation failed\n";
    }
    
    $field = CSRFToken::getField();
    if (strpos($field, 'csrf_token') !== false && strpos($field, $token1) !== false) {
        echo "   ✓ CSRF field generation working\n";
    } else {
        echo "   ✗ CSRF field generation failed\n";
    }
} catch (Exception $e) {
    echo "   ✗ CSRF testing failed: " . $e->getMessage() . "\n";
}

// Test 4: Test session functions (without login)
echo "\n4. Testing session functions...\n";
try {
    if (!isLoggedIn()) {
        echo "   ✓ isLoggedIn() correctly returns false\n";
    } else {
        echo "   ✗ isLoggedIn() incorrectly returns true\n";
    }
    
    $user = getCurrentUser();
    if ($user === null) {
        echo "   ✓ getCurrentUser() correctly returns null\n";
    } else {
        echo "   ✗ getCurrentUser() incorrectly returns data\n";
    }
    
    if (!isAdmin() && !isAdminBranch()) {
        echo "   ✓ Role checking functions work correctly\n";
    } else {
        echo "   ✗ Role checking functions failed\n";
    }
} catch (Exception $e) {
    echo "   ✗ Session function testing failed: " . $e->getMessage() . "\n";
}

// Test 5: Test password functions
echo "\n5. Testing password functions...\n";
try {
    $test_password = "TestPass123!";
    $hashed = password_hash($test_password, PASSWORD_DEFAULT);
    
    if (password_verify($test_password, $hashed)) {
        echo "   ✓ Password hashing and verification working\n";
    } else {
        echo "   ✗ Password hashing failed\n";
    }
    
    $temp_pass = generateTempPassword(12);
    if (strlen($temp_pass) === 12) {
        echo "   ✓ Temporary password generation working: $temp_pass\n";
    } else {
        echo "   ✗ Temporary password generation failed\n";
    }
} catch (Exception $e) {
    echo "   ✗ Password function testing failed: " . $e->getMessage() . "\n";
}

// Test 6: Test utility functions
echo "\n6. Testing utility functions...\n";
try {
    $login_url = getLoginUrl();
    if (!empty($login_url)) {
        echo "   ✓ getLoginUrl() working: $login_url\n";
    } else {
        echo "   ✗ getLoginUrl() failed\n";
    }
    
    // Test message functions
    setMessage('Test message', 'success');
    $messages = getMessages();
    if (count($messages) === 1 && $messages[0]['message'] === 'Test message') {
        echo "   ✓ Message functions working\n";
    } else {
        echo "   ✗ Message functions failed\n";
    }
} catch (Exception $e) {
    echo "   ✗ Utility function testing failed: " . $e->getMessage() . "\n";
}

echo "\n=== Test Summary ===\n";
echo "Syntax and basic function tests completed.\n";
echo "The authentication system is ready for testing with a live database.\n\n";
echo "Next steps for live testing:\n";
echo "1. Configure database connection in staff_auth_standalone.php\n";
echo "2. Run update_staff_table.sql to add required fields\n";
echo "3. Test login functionality through a web browser\n";
echo "4. Create a test staff member and verify the workflow\n\n";

// Clean up
session_destroy();
?>