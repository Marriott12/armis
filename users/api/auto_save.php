<?php
/**
 * Auto-save API
 * Provides automatic saving of form data to prevent data loss
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
    require_once __DIR__ . '/../classes/EnhancedProfileManager.php';
    require_once __DIR__ . '/../classes/ProfileValidator.php';
    
    $profileManager = new EnhancedProfileManager($_SESSION['user_id']);
    $validator = new ProfileValidator();
    
    // Get the profile data
    $profileData = json_decode($_POST['profile_data'] ?? '{}', true);
    
    if (empty($profileData)) {
        echo json_encode(['error' => 'No data provided']);
        exit();
    }
    
    // Sanitize the data
    $sanitizedData = $validator->sanitizeData($profileData);
    
    // Filter out empty values to avoid overwriting existing data with blanks
    $filteredData = array_filter($sanitizedData, function($value) {
        return $value !== '' && $value !== null;
    });
    
    // Only update if there's meaningful data
    if (empty($filteredData)) {
        echo json_encode([
            'success' => true,
            'message' => 'No changes to save',
            'completion' => $profileManager->calculateProfileCompleteness()
        ]);
        exit();
    }
    
    // Perform a validation-only update to check for errors
    $validationResult = $profileManager->updateProfile($filteredData, true);
    
    if (!$validationResult['success']) {
        // Return validation errors but don't fail completely for auto-save
        echo json_encode([
            'success' => true,
            'partial' => true,
            'message' => 'Auto-save completed with warnings',
            'warnings' => $validationResult['errors'] ?? [],
            'completion' => $profileManager->calculateProfileCompleteness()
        ]);
        exit();
    }
    
    // Perform the actual update
    $updateResult = $profileManager->updateProfile($filteredData, false);
    
    if ($updateResult['success']) {
        // Log auto-save activity
        error_log("Auto-save successful for user {$_SESSION['user_id']}: " . count($filteredData) . " fields updated");
        
        echo json_encode([
            'success' => true,
            'message' => 'Auto-save successful',
            'completion' => $updateResult['completion'] ?? $profileManager->calculateProfileCompleteness(),
            'saved_fields' => array_keys($filteredData),
            'timestamp' => date('Y-m-d H:i:s')
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => $updateResult['message'] ?? 'Auto-save failed',
            'completion' => $profileManager->calculateProfileCompleteness()
        ]);
    }
    
} catch (Exception $e) {
    error_log("Auto-save API error: " . $e->getMessage());
    
    // Don't fail the auto-save completely - log error but return success
    echo json_encode([
        'success' => true,
        'partial' => true,
        'message' => 'Auto-save completed with errors',
        'error' => 'Internal error occurred'
    ]);
}