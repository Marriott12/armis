<?php
/**
 * AJAX Validation Endpoint for Real-time Form Validation
 * Provides instant feedback for form fields
 */

// Security headers
header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');

// Define constants
define('ARMIS_ADMIN_BRANCH', true);

// Include required files
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/partials/create_staff_config.php';
require_once dirname(__DIR__) . '/partials/create_staff_validation.php';

// Check authentication
if (!isLoggedIn()) {
    http_response_code(403);
    echo json_encode(['error' => 'Access denied']);
    exit;
}

// Validate request method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// Get and validate input
$input = json_decode(file_get_contents('php://input'), true);

if (!$input || !isset($input['field']) || !isset($input['value'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid request data']);
    exit;
}

$field = trim($input['field']);
$value = trim($input['value']);
$context = isset($input['context']) ? $input['context'] : '';

try {
    $conn = getDBConnection();
    $response = processValidationRequest($field, $value, $context, $conn);
    echo json_encode($response);
    
} catch (Exception $e) {
    error_log("AJAX validation error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Validation failed']);
}

/**
 * Process validation request
 */
function processValidationRequest($field, $value, $context, $conn) {
    global $validationRules;
    
    // Get validation rules for the field
    $rules = getFieldValidationRules($field, $validationRules);
    
    if (empty($rules)) {
        return ['valid' => true, 'message' => ''];
    }
    
    // Create validator and validate field
    $validator = new StaffFormValidator($conn, $validationRules);
    $validator->validateSingleField($field, $value, $rules);
    
    $errors = $validator->getFieldErrors($field);
    $isValid = empty($errors);
    
    $response = [
        'valid' => $isValid,
        'message' => $isValid ? '' : implode(', ', $errors),
        'field' => $field
    ];
    
    // Add additional context-specific validation
    if ($isValid) {
        $response = addContextualValidation($field, $value, $context, $conn, $response);
    }
    
    return $response;
}

/**
 * Get validation rules for a specific field
 */
function getFieldValidationRules($field, $validationRules) {
    foreach ($validationRules as $section => $rules) {
        if (isset($rules[$field])) {
            return $rules[$field];
        }
    }
    return [];
}

/**
 * Add contextual validation (suggestions, warnings, etc.)
 */
function addContextualValidation($field, $value, $context, $conn, $response) {
    switch ($field) {
        case 'email':
            $response = validateEmailContext($value, $conn, $response);
            break;
            
        case 'serviceNumber':
            $response = validateServiceNumberContext($value, $conn, $response);
            break;
            
        case 'nationalID':
            $response = validateNationalIDContext($value, $conn, $response);
            break;
            
        case 'phone':
            $response = validatePhoneContext($value, $response);
            break;
            
        case 'rankID':
            $response = validateRankContext($value, $conn, $response);
            break;
            
        case 'unitID':
            $response = validateUnitContext($value, $conn, $response);
            break;
    }
    
    return $response;
}

/**
 * Validate email context
 */
function validateEmailContext($email, $conn, $response) {
    // Check for similar emails
    $domain = substr(strrchr($email, "@"), 1);
    
    $stmt = $conn->prepare("SELECT email FROM staff WHERE email LIKE ? LIMIT 3");
    $searchEmail = "%{$domain}";
    $stmt->bind_param('s', $searchEmail);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $similarEmails = [];
    while ($row = $result->fetch_assoc()) {
        if ($row['email'] !== $email) {
            $similarEmails[] = $row['email'];
        }
    }
    
    if (!empty($similarEmails)) {
        $response['suggestions'] = [
            'type' => 'similar_emails',
            'message' => 'Similar emails found in system',
            'data' => $similarEmails
        ];
    }
    
    // Check if it's a military domain
    $militaryDomains = ['mod.gov.zm', 'zaf.mil.zm', 'army.gov.zm'];
    if (in_array(strtolower($domain), $militaryDomains)) {
        $response['info'] = 'Military email domain detected';
    }
    
    return $response;
}

/**
 * Validate service number context
 */
function validateServiceNumberContext($serviceNumber, $conn, $response) {
    // Suggest proper format if needed
    if (!preg_match('/^[A-Z]{2}[0-9]{6,8}$/', strtoupper($serviceNumber))) {
        $response['suggestions'] = [
            'type' => 'format',
            'message' => 'Service number format: ZA123456 (2 letters + 6-8 digits)',
            'example' => 'ZA' . str_pad(rand(100000, 999999), 6, '0', STR_PAD_LEFT)
        ];
    }
    
    // Check for sequential numbers that might indicate bulk entry
    $prefix = substr(strtoupper($serviceNumber), 0, 2);
    $number = substr($serviceNumber, 2);
    
    if (is_numeric($number)) {
        $stmt = $conn->prepare("SELECT serviceNumber FROM staff WHERE serviceNumber LIKE ? ORDER BY serviceNumber DESC LIMIT 5");
        $searchPattern = $prefix . '%';
        $stmt->bind_param('s', $searchPattern);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $recentNumbers = [];
        while ($row = $result->fetch_assoc()) {
            $recentNumbers[] = $row['serviceNumber'];
        }
        
        if (!empty($recentNumbers)) {
            $response['context'] = [
                'type' => 'recent_numbers',
                'message' => 'Recent service numbers with same prefix',
                'data' => $recentNumbers
            ];
        }
    }
    
    return $response;
}

/**
 * Validate national ID context
 */
function validateNationalIDContext($nationalID, $conn, $response) {
    // Basic format validation for Zambian National ID
    if (strlen($nationalID) >= 9) {
        // Check if it follows Zambian ID format patterns
        if (preg_match('/^[0-9]{9,12}$/', $nationalID)) {
            $response['info'] = 'Zambian National ID format detected';
        }
    }
    
    return $response;
}

/**
 * Validate phone context
 */
function validatePhoneContext($phone, $response) {
    // Clean phone number
    $cleanPhone = preg_replace('/[^\d+]/', '', $phone);
    
    // Detect country/region based on prefix
    $countryInfo = detectPhoneCountry($cleanPhone);
    
    if ($countryInfo) {
        $response['info'] = "Detected: {$countryInfo['country']} ({$countryInfo['format']})";
    }
    
    // Suggest formatting
    $formatted = formatPhoneNumber($cleanPhone);
    if ($formatted !== $phone) {
        $response['suggestions'] = [
            'type' => 'formatting',
            'message' => 'Suggested format',
            'formatted' => $formatted
        ];
    }
    
    return $response;
}

/**
 * Validate rank context
 */
function validateRankContext($rankID, $conn, $response) {
    if (is_numeric($rankID)) {
        $stmt = $conn->prepare("SELECT name, category, level FROM ranks WHERE id = ?");
        $stmt->bind_param('i', $rankID);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($row = $result->fetch_assoc()) {
            $response['info'] = "{$row['name']} ({$row['category']}, Level {$row['level']})";
        }
    }
    
    return $response;
}

/**
 * Validate unit context
 */
function validateUnitContext($unitID, $conn, $response) {
    if (is_numeric($unitID)) {
        $stmt = $conn->prepare("SELECT name, code, type, location FROM units WHERE id = ?");
        $stmt->bind_param('i', $unitID);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($row = $result->fetch_assoc()) {
            $info = "{$row['name']} ({$row['code']})";
            if (!empty($row['location'])) {
                $info .= " - {$row['location']}";
            }
            $response['info'] = $info;
        }
    }
    
    return $response;
}

/**
 * Detect phone number country
 */
function detectPhoneCountry($phone) {
    $patterns = [
        'Zambia' => [
            'pattern' => '/^(\+260|260|0)?([789][0-9]{7})$/',
            'format' => '+260 XX XXX XXXX'
        ],
        'South Africa' => [
            'pattern' => '/^(\+27|27|0)?([1-9][0-9]{8})$/',
            'format' => '+27 XX XXX XXXX'
        ],
        'Zimbabwe' => [
            'pattern' => '/^(\+263|263|0)?([1-9][0-9]{7,8})$/',
            'format' => '+263 X XXX XXXX'
        ]
    ];
    
    foreach ($patterns as $country => $info) {
        if (preg_match($info['pattern'], $phone)) {
            return ['country' => $country, 'format' => $info['format']];
        }
    }
    
    return null;
}

/**
 * Format phone number
 */
function formatPhoneNumber($phone) {
    // Remove leading zeros and add international prefix for Zambian numbers
    if (preg_match('/^0([789][0-9]{7})$/', $phone, $matches)) {
        return '+260 ' . $matches[1];
    }
    
    // Format international numbers
    if (preg_match('/^\+260([789][0-9]{7})$/', $phone, $matches)) {
        $number = $matches[1];
        return '+260 ' . substr($number, 0, 2) . ' ' . substr($number, 2, 3) . ' ' . substr($number, 5);
    }
    
    return $phone;
}
?>
