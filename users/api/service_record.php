<?php
/**
 * Service Record Management API
 * Handles deployment tracking, security clearance, and medical readiness
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

require_once dirname(__DIR__) . '/classes/ServiceRecordManager.php';

try {
    $serviceManager = new ServiceRecordManager($_SESSION['user_id']);
    $response = ['success' => false];
    
    switch ($_SERVER['REQUEST_METHOD']) {
        case 'POST':
            $action = $_POST['action'] ?? '';
            
            switch ($action) {
                case 'add_deployment':
                    $response = $serviceManager->addDeployment($_POST);
                    break;
                    
                case 'update_deployment_status':
                    $deploymentId = $_POST['deployment_id'] ?? null;
                    $status = $_POST['status'] ?? null;
                    $endDate = $_POST['actual_end_date'] ?? null;
                    
                    if (!$deploymentId || !$status) {
                        $response = ['success' => false, 'message' => 'Deployment ID and status required'];
                        break;
                    }
                    
                    $result = $serviceManager->updateDeploymentStatus($deploymentId, $status, $endDate);
                    $response = ['success' => $result, 'message' => $result ? 'Status updated' : 'Update failed'];
                    break;
                    
                case 'add_security_clearance':
                    $response = $serviceManager->addSecurityClearance($_POST);
                    break;
                    
                case 'update_medical_readiness':
                    $response = $serviceManager->updateMedicalReadiness($_POST);
                    break;
                    
                case 'add_training_compliance':
                    $response = $serviceManager->addTrainingCompliance($_POST);
                    break;
                    
                case 'add_rank_progression':
                    $response = $serviceManager->addRankProgression($_POST);
                    break;
                    
                default:
                    $response = ['success' => false, 'message' => 'Invalid action'];
            }
            break;
            
        case 'GET':
            $action = $_GET['action'] ?? '';
            
            switch ($action) {
                case 'deployment_history':
                    $response = [
                        'success' => true,
                        'deployments' => $serviceManager->getDeploymentHistory()
                    ];
                    break;
                    
                case 'security_clearance':
                    $response = [
                        'success' => true,
                        'clearance' => $serviceManager->getSecurityClearanceStatus()
                    ];
                    break;
                    
                case 'medical_readiness':
                    $response = [
                        'success' => true,
                        'medical' => $serviceManager->getMedicalReadiness()
                    ];
                    break;
                    
                case 'training_compliance':
                    $response = [
                        'success' => true,
                        'training' => $serviceManager->getTrainingCompliance()
                    ];
                    break;
                    
                case 'rank_progression':
                    $response = [
                        'success' => true,
                        'progression' => $serviceManager->getRankProgressionHistory()
                    ];
                    break;
                    
                case 'promotion_eligibility':
                    $response = [
                        'success' => true,
                        'eligibility' => $serviceManager->checkPromotionEligibility()
                    ];
                    break;
                    
                case 'expiring_items':
                    $daysAhead = $_GET['days'] ?? 90;
                    $response = [
                        'success' => true,
                        'expiring_items' => $serviceManager->getExpiringItems($daysAhead)
                    ];
                    break;
                    
                case 'service_summary':
                    $response = [
                        'success' => true,
                        'summary' => [
                            'deployments' => $serviceManager->getDeploymentHistory(),
                            'security_clearance' => $serviceManager->getSecurityClearanceStatus(),
                            'medical_readiness' => $serviceManager->getMedicalReadiness(),
                            'training_compliance' => $serviceManager->getTrainingCompliance(),
                            'rank_progression' => $serviceManager->getRankProgressionHistory(),
                            'promotion_eligibility' => $serviceManager->checkPromotionEligibility(),
                            'expiring_items' => $serviceManager->getExpiringItems(30)
                        ]
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
    error_log("Service Record API error: " . $e->getMessage());
    $response = ['success' => false, 'message' => 'Internal server error'];
    http_response_code(500);
}

echo json_encode($response);