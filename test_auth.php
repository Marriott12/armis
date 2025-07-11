<?php
// Test script for Staff Authentication System
// This script tests the basic functionality without a web server

ini_set('display_errors', 1);
error_reporting(E_ALL);

// Include our authentication system
require_once 'staff_auth_standalone.php';

echo "=== ARMIS Staff Authentication System Test ===\n\n";

// Test 1: Database Connection
echo "1. Testing database connection...\n";
try {
    $db = getDatabase();
    echo "   ✓ Database connection successful\n";
} catch (Exception $e) {
    echo "   ✗ Database connection failed: " . $e->getMessage() . "\n";
    exit(1);
}

// Test 2: CSRF Token Generation
echo "\n2. Testing CSRF token generation...\n";
$token1 = CSRFToken::generate();
$token2 = CSRFToken::generate();
if ($token1 === $token2 && strlen($token1) === 64) {
    echo "   ✓ CSRF token generation working\n";
} else {
    echo "   ✗ CSRF token generation failed\n";
}

// Test 3: CSRF Token Validation
echo "\n3. Testing CSRF token validation...\n";
if (CSRFToken::validate($token1)) {
    echo "   ✓ CSRF token validation working\n";
} else {
    echo "   ✗ CSRF token validation failed\n";
}

// Test 4: Check if staff table exists and has required fields
echo "\n4. Testing staff table structure...\n";
try {
    $stmt = $db->prepare("DESCRIBE staff");
    $stmt->execute();
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    $required_fields = ['username', 'password', 'role', 'email', 'accStatus'];
    $optional_fields = ['must_reset_password', 'reset_token', 'reset_token_expiry', 'last_login'];
    
    foreach ($required_fields as $field) {
        if (in_array($field, $columns)) {
            echo "   ✓ Required field '$field' exists\n";
        } else {
            echo "   ✗ Required field '$field' missing\n";
        }
    }
    
    foreach ($optional_fields as $field) {
        if (in_array($field, $columns)) {
            echo "   ✓ Optional field '$field' exists\n";
        } else {
            echo "   ! Optional field '$field' missing (run update_staff_table.sql)\n";
        }
    }
} catch (Exception $e) {
    echo "   ✗ Error checking staff table: " . $e->getMessage() . "\n";
}

// Test 5: Test staff lookup
echo "\n5. Testing staff lookup...\n";
try {
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM staff WHERE accStatus = 'Active'");
    $stmt->execute();
    $result = $stmt->fetch();
    echo "   ✓ Found {$result['count']} active staff members\n";
    
    if ($result['count'] > 0) {
        $stmt = $db->prepare("SELECT svcNo, username, fname, lname FROM staff WHERE accStatus = 'Active' LIMIT 1");
        $stmt->execute();
        $staff = $stmt->fetch();
        echo "   ✓ Sample staff: {$staff['fname']} {$staff['lname']} (username: {$staff['username']})\n";
    }
} catch (Exception $e) {
    echo "   ✗ Error testing staff lookup: " . $e->getMessage() . "\n";
}

// Test 6: Test password hashing
echo "\n6. Testing password functions...\n";
$test_password = "TestPass123!";
$hashed = password_hash($test_password, PASSWORD_DEFAULT);
if (password_verify($test_password, $hashed)) {
    echo "   ✓ Password hashing and verification working\n";
} else {
    echo "   ✗ Password hashing failed\n";
}

// Test 7: Test temporary password generation
echo "\n7. Testing temporary password generation...\n";
$temp_pass = generateTempPassword(12);
if (strlen($temp_pass) === 12 && preg_match('/[A-Za-z0-9!@#$%^&*]/', $temp_pass)) {
    echo "   ✓ Temporary password generation working: $temp_pass\n";
} else {
    echo "   ✗ Temporary password generation failed\n";
}

// Test 8: Test session functions (without actual login)
echo "\n8. Testing session functions...\n";
if (!isLoggedIn()) {
    echo "   ✓ isLoggedIn() correctly returns false when not logged in\n";
} else {
    echo "   ✗ isLoggedIn() incorrectly returns true\n";
}

$user = getCurrentUser();
if ($user === null) {
    echo "   ✓ getCurrentUser() correctly returns null when not logged in\n";
} else {
    echo "   ✗ getCurrentUser() incorrectly returns data\n";
}

// Test 9: Test role checking functions
echo "\n9. Testing role functions...\n";
if (!isAdmin() && !isAdminBranch() && !hasRole('Admin')) {
    echo "   ✓ Role checking functions work correctly when not logged in\n";
} else {
    echo "   ✗ Role checking functions failed\n";
}

// Test 10: Test URL functions
echo "\n10. Testing URL functions...\n";
$login_url = getLoginUrl();
if (!empty($login_url)) {
    echo "   ✓ getLoginUrl() returned: $login_url\n";
} else {
    echo "   ✗ getLoginUrl() failed\n";
}

echo "\n=== Test Summary ===\n";
echo "Basic functionality tests completed.\n";
echo "Next steps:\n";
echo "1. Run update_staff_table.sql if optional fields are missing\n";
echo "2. Test login functionality with a web browser\n";
echo "3. Test staff creation and password reset workflows\n";
echo "4. Verify all protected pages work correctly\n\n";

// Clean up test session
session_destroy();
?>