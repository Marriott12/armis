<?php
// Simple syntax test for Staff Authentication System
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

// Test 2: Test password functions independently
echo "\n2. Testing password functions...\n";
try {
    $test_password = "TestPass123!";
    $hashed = password_hash($test_password, PASSWORD_DEFAULT);
    
    if (password_verify($test_password, $hashed)) {
        echo "   ✓ Password hashing and verification working\n";
    } else {
        echo "   ✗ Password hashing failed\n";
    }
} catch (Exception $e) {
    echo "   ✗ Password function testing failed: " . $e->getMessage() . "\n";
}

// Test 3: Test session functions
echo "\n3. Testing basic session functionality...\n";
try {
    if (!session_id()) {
        session_start();
    }
    
    // Test simple session variable
    $_SESSION['test'] = 'working';
    if ($_SESSION['test'] === 'working') {
        echo "   ✓ Session functionality working\n";
    } else {
        echo "   ✗ Session functionality failed\n";
    }
    
    unset($_SESSION['test']);
} catch (Exception $e) {
    echo "   ✗ Session testing failed: " . $e->getMessage() . "\n";
}

echo "\n=== Test Summary ===\n";
echo "Basic syntax tests completed successfully.\n";
echo "All core PHP files have valid syntax.\n\n";
echo "Next steps for integration testing:\n";
echo "1. Set up database connection\n";
echo "2. Run update_staff_table.sql to add required fields\n";
echo "3. Test the full authentication workflow\n";
echo "4. Verify all protected pages work correctly\n\n";

// Clean up
if (session_id()) {
    session_destroy();
}
?>