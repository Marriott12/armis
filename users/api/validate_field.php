<?php
/**
 * Real-time Field Validation API
 * Provides instant validation feedback for form fields
 */

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

// Set JSON content type
header('Content-Type: application/json');

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit();
}

try {
    require_once __DIR__ . '/../classes/ProfileValidator.php';
    
    $validator = new ProfileValidator();
    
    // Get validation request data
    $fieldName = $_POST['field'] ?? '';
    $fieldValue = $_POST['value'] ?? '';
    $context = json_decode($_POST['context'] ?? '{}', true);
    
    if (empty($fieldName)) {
        echo json_encode(['error' => 'Field name required']);
        exit();
    }
    
    // Perform validation
    $result = $validator->validateField($fieldName, $fieldValue, $context);
    
    // Add additional context-specific validation
    switch ($fieldName) {
        case 'email':
            // Check for duplicate email (excluding current user)
            if ($result['valid'] && !empty($fieldValue)) {
                require_once dirname(__DIR__, 2) . '/shared/database_connection.php';
                $pdo = getDbConnection();
                $stmt = $pdo->prepare("SELECT id FROM staff WHERE email = ? AND id != ?");
                $stmt->execute([$fieldValue, $_SESSION['user_id']]);
                if ($stmt->fetch()) {
                    $result = [
                        'valid' => false,
                        'error' => 'Email address already exists',
                        'warning' => null
                    ];
                }
            }
            break;
            
        case 'service_number':
            // Check for duplicate service number (excluding current user)
            if ($result['valid'] && !empty($fieldValue)) {
                require_once dirname(__DIR__, 2) . '/shared/database_connection.php';
                $pdo = getDbConnection();
                $stmt = $pdo->prepare("SELECT id FROM staff WHERE service_number = ? AND id != ?");
                $stmt->execute([$fieldValue, $_SESSION['user_id']]);
                if ($stmt->fetch()) {
                    $result = [
                        'valid' => false,
                        'error' => 'Service number already exists',
                        'warning' => null
                    ];
                }
            }
            break;
            
        case 'national_id':
            // Check for duplicate national ID (excluding current user)
            if ($result['valid'] && !empty($fieldValue)) {
                require_once dirname(__DIR__, 2) . '/shared/database_connection.php';
                $pdo = getDbConnection();
                $stmt = $pdo->prepare("SELECT id FROM staff WHERE national_id = ? AND id != ?");
                $stmt->execute([$fieldValue, $_SESSION['user_id']]);
                if ($stmt->fetch()) {
                    $result = [
                        'valid' => false,
                        'error' => 'National ID already exists',
                        'warning' => null
                    ];
                }
            }
            break;
            
        case 'date_of_birth':
            // Additional age-based warnings
            if ($result['valid'] && !empty($fieldValue)) {
                $dob = new DateTime($fieldValue);
                $now = new DateTime();
                $age = $now->diff($dob)->y;
                
                if ($age < 18) {
                    $result['warning'] = 'Personnel under 18 requires special authorization';
                } elseif ($age > 60) {
                    $result['warning'] = 'Personnel over 60 may require medical clearance';
                }
            }
            break;
    }
    
    // Add field-specific suggestions
    if (!$result['valid'] && !empty($fieldValue)) {
        $suggestions = [];
        
        switch ($fieldName) {
            case 'national_id':
                if (!preg_match('/^[0-9]{6}\/[0-9]{2}\/[0-9]$/', $fieldValue)) {
                    $suggestions[] = 'Example: 123456/78/9';
                    $suggestions[] = 'First 6 digits: DDMMYY (birth date)';
                    $suggestions[] = 'Next 2 digits: Province code';
                    $suggestions[] = 'Last digit: Gender (odd=male, even=female)';
                }
                break;
                
            case 'phone':
                if (!empty($fieldValue)) {
                    $suggestions[] = 'Include country code: +260 XXX XXX XXX';
                    $suggestions[] = 'Use spaces or dashes for readability';
                }
                break;
                
            case 'email':
                if (!empty($fieldValue) && !filter_var($fieldValue, FILTER_VALIDATE_EMAIL)) {
                    $suggestions[] = 'Example: john.doe@example.com';
                    $suggestions[] = 'Must include @ symbol and domain';
                }
                break;
        }
        
        if (!empty($suggestions)) {
            $result['suggestions'] = $suggestions;
        }
    }
    
    // Log validation request for analytics
    error_log("Field validation: {$fieldName} = " . (strlen($fieldValue) > 50 ? substr($fieldValue, 0, 50) . '...' : $fieldValue) . " -> " . ($result['valid'] ? 'valid' : 'invalid'));
    
    echo json_encode($result);
    
} catch (Exception $e) {
    error_log("Validation API error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'error' => 'Validation failed',
        'valid' => false
    ]);
}