<?php
/**
 * Enhanced CV Processing API
 * Handles CV upload, processing, and template generation
 */

// Set JSON content type
header('Content-Type: application/json');

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check authentication
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Authentication required']);
    exit;
}

require_once dirname(__DIR__) . '/classes/CVProcessor.php';

try {
    $cvProcessor = new CVProcessor($_SESSION['user_id']);
    $response = ['success' => false];
    
    switch ($_SERVER['REQUEST_METHOD']) {
        case 'POST':
            $action = $_POST['action'] ?? '';
            
            switch ($action) {
                case 'upload':
                    if (!isset($_FILES['cv_file'])) {
                        $response = ['success' => false, 'message' => 'No file uploaded'];
                        break;
                    }
                    
                    $templateType = $_POST['template_type'] ?? null;
                    $response = $cvProcessor->processCV($_FILES['cv_file'], $templateType);
                    break;
                    
                case 'generate':
                    $cvId = $_POST['cv_id'] ?? null;
                    $templateType = $_POST['template_type'] ?? CVProcessor::TEMPLATE_CIVILIAN;
                    $format = $_POST['format'] ?? 'html';
                    
                    if (!$cvId) {
                        $response = ['success' => false, 'message' => 'CV ID required'];
                        break;
                    }
                    
                    $response = $cvProcessor->generateMilitaryCV($cvId, $templateType, $format);
                    break;
                    
                case 'approve':
                    $cvId = $_POST['cv_id'] ?? null;
                    $approverId = $_SESSION['user_id'];
                    
                    if (!$cvId) {
                        $response = ['success' => false, 'message' => 'CV ID required'];
                        break;
                    }
                    
                    $result = $cvProcessor->approveCV($cvId, $approverId);
                    $response = ['success' => $result, 'message' => $result ? 'CV approved' : 'Approval failed'];
                    break;
                    
                default:
                    $response = ['success' => false, 'message' => 'Invalid action'];
            }
            break;
            
        case 'GET':
            $action = $_GET['action'] ?? '';
            
            switch ($action) {
                case 'templates':
                    $response = [
                        'success' => true,
                        'templates' => $cvProcessor->getAvailableTemplates()
                    ];
                    break;
                    
                default:
                    $response = ['success' => false, 'message' => 'Invalid action'];
            }
            break;
            
        default:
            $response = ['success' => false, 'message' => 'Method not allowed'];
    }
    
} catch (Exception $e) {
    error_log("CV API error: " . $e->getMessage());
    $response = ['success' => false, 'message' => 'Internal server error'];
    http_response_code(500);
}

echo json_encode($response);