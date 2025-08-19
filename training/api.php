<?php
/**
 * Training Module API
 * AJAX endpoint for dynamic training data
 */

// Define module constants
define('ARMIS_TRAINING', true);
define('ARMIS_DEVELOPMENT', true);

// Include required files
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/shared/session_init.php';
require_once dirname(__DIR__) . '/shared/database_connection.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/training_service.php';

// Require authentication
requireTrainingAccess();

// Set JSON headers
header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

// Handle different API endpoints
$action = $_GET['action'] ?? '';
$type = $_GET['type'] ?? 'all';

try {
    // Get database connection
    $pdo = getDbConnection();
    if (!$pdo) {
        throw new Exception('Database connection not available');
    }
    
    // Log API access
    logTrainingActivity('api_access', "Training API accessed: {$action}");
    
    $service = new TrainingService($pdo);
    $response = ['success' => true, 'data' => null, 'timestamp' => date('c')];
    
    switch ($action) {
        case 'get_kpi':
            $response['data'] = $service->getKPIData();
            break;
            
        case 'get_recent_activities':
            $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
            $response['data'] = $service->getRecentActivities($limit);
            break;
            
        case 'get_course_stats':
            $response['data'] = $service->getCourseStats();
            break;
            
        case 'get_enrollment_overview':
            $response['data'] = $service->getEnrollmentOverview();
            break;
            
        case 'get_progress_metrics':
            $response['data'] = $service->getProgressMetrics();
            break;
            
        case 'get_upcoming_sessions':
            $response['data'] = $service->getUpcomingSessions();
            break;
            
        case 'get_certification_status':
            $response['data'] = $service->getCertificationStatus();
            break;
            
        case 'get_instructor_overview':
            $response['data'] = $service->getInstructorOverview();
            break;
            
        case 'get_all_dashboard_data':
        default:
            $response['data'] = [
                'kpi' => $service->getKPIData(),
                'recent_activities' => $service->getRecentActivities(5),
                'course_stats' => $service->getCourseStats(),
                'enrollment_overview' => $service->getEnrollmentOverview(),
                'progress_metrics' => $service->getProgressMetrics(),
                'upcoming_sessions' => $service->getUpcomingSessions(),
                'certification_status' => $service->getCertificationStatus()
            ];
            break;
    }
    
    // Log successful API response
    logTrainingActivity('api_success', "Training API response sent: {$action}");
    
} catch (Exception $e) {
    error_log("Training API error: " . $e->getMessage());
    logTrainingActivity('api_error', "Training API error: {$e->getMessage()}");
    
    $response = [
        'success' => false,
        'error' => ARMIS_DEVELOPMENT ? $e->getMessage() : 'An error occurred while processing your request',
        'timestamp' => date('c')
    ];
    
    http_response_code(500);
}

// Send JSON response
echo json_encode($response);
?>