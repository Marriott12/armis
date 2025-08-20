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
            break;
        
        case 'enroll':
            handleEnrollment($trainingService, $method);
            break;
        
        case 'training-records':
            handleTrainingRecords($trainingService, $method);
            break;
        
        case 'certifications':
            handleCertifications($trainingService, $method);
            break;
        
        case 'reports':
            handleReports($trainingService, $method);
            break;
        
        default:
            http_response_code(404);
            echo json_encode(['error' => 'Endpoint not found']);
            exit();
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
    logTrainingActivity('api_error', $e->getMessage());
}

/**
 * Handle dashboard requests
 */
function handleDashboard($service, $method) {
    if ($method !== 'GET') {
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
        return;
    }
    
    $data = $service->getDashboardData();
    echo json_encode(['success' => true, 'data' => $data]);
}

/**
 * Handle courses requests
 */
function handleCourses($service, $method) {
    switch ($method) {
        case 'GET':
            $params = [
                'status' => $_GET['status'] ?? '',
                'category' => $_GET['category'] ?? '',
                'difficulty' => $_GET['difficulty'] ?? '',
                'search' => $_GET['search'] ?? '',
                'limit' => $_GET['limit'] ?? 50
            ];
            
            $courses = $service->searchCourses($params);
            echo json_encode(['success' => true, 'data' => $courses]);
            break;
            
        case 'POST':
            if (!hasTrainingPermission('manage_courses')) {
                http_response_code(403);
                echo json_encode(['error' => 'Insufficient permissions']);
                return;
            }
            
            $input = json_decode(file_get_contents('php://input'), true);
            $courseId = $service->createCourse($input);
            
            if ($courseId) {
                echo json_encode(['success' => true, 'course_id' => $courseId]);
            } else {
                http_response_code(400);
                echo json_encode(['error' => 'Failed to create course']);
            }
            break;
            
        default:
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
    }
}

/**
 * Handle single course requests
 */
function handleCourse($service, $method) {
    $courseId = $_GET['id'] ?? 0;
    
    if (!$courseId) {
        http_response_code(400);
        echo json_encode(['error' => 'Course ID required']);
        return;
    }
    
    switch ($method) {
        case 'GET':
            $course = TrainingUtils::getCourseById($courseId);
            if ($course) {
                echo json_encode(['success' => true, 'data' => $course]);
            } else {
                http_response_code(404);
                echo json_encode(['error' => 'Course not found']);
            }
            break;
            
        default:
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
    }
}

/**
 * Handle enrollment requests
 */
function handleEnrollment($service, $method) {
    if ($method !== 'POST') {
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
        return;
    }
    
    if (!hasTrainingPermission('enroll_students')) {
        http_response_code(403);
        echo json_encode(['error' => 'Insufficient permissions']);
        return;
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    $staffId = $input['staff_id'] ?? 0;
    $courseId = $input['course_id'] ?? 0;
    
    if (!$staffId || !$courseId) {
        http_response_code(400);
        echo json_encode(['error' => 'Staff ID and Course ID required']);
        return;
    }
    
    try {
        $result = $service->enrollStaff($staffId, $courseId);
        echo json_encode(['success' => $result]);
    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode(['error' => $e->getMessage()]);
    }
}

/**
 * Handle training records requests
 */
function handleTrainingRecords($service, $method) {
    switch ($method) {
        case 'GET':
            $staffId = $_GET['staff_id'] ?? 0;
            if ($staffId) {
                $records = TrainingUtils::getStaffTrainingRecords($staffId);
                echo json_encode(['success' => true, 'data' => $records]);
            } else {
                http_response_code(400);
                echo json_encode(['error' => 'Staff ID required']);
            }
            break;
            
        case 'PUT':
            if (!hasTrainingPermission('manage_records')) {
                http_response_code(403);
                echo json_encode(['error' => 'Insufficient permissions']);
                return;
            }
            
            $input = json_decode(file_get_contents('php://input'), true);
            $recordId = $input['record_id'] ?? 0;
            $action = $input['action'] ?? '';
            
            if (!$recordId || !$action) {
                http_response_code(400);
                echo json_encode(['error' => 'Record ID and action required']);
                return;
            }
            
            try {
                if ($action === 'complete') {
                    $score = $input['score'] ?? null;
                    $result = $service->completeTraining($recordId, $score);
                } else {
                    $result = TrainingUtils::updateTrainingStatus($recordId, $action);
                }
                
                echo json_encode(['success' => $result]);
            } catch (Exception $e) {
                http_response_code(400);
                echo json_encode(['error' => $e->getMessage()]);
            }
            break;
            
        default:
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
    }
}

/**
 * Handle certifications requests
 */
function handleCertifications($service, $method) {
    switch ($method) {
        case 'GET':
            $staffId = $_GET['staff_id'] ?? 0;
            if ($staffId) {
                $certifications = TrainingUtils::getStaffCertifications($staffId);
                echo json_encode(['success' => true, 'data' => $certifications]);
            } else {
                http_response_code(400);
                echo json_encode(['error' => 'Staff ID required']);
            }
            break;
            
        case 'POST':
            if (!hasTrainingPermission('manage_certifications')) {
                http_response_code(403);
                echo json_encode(['error' => 'Insufficient permissions']);
                return;
            }
            
            $input = json_decode(file_get_contents('php://input'), true);
            $staffId = $input['staff_id'] ?? 0;
            $certType = $input['certification_type'] ?? '';
            $validityMonths = $input['validity_months'] ?? 24;
            
            if (!$staffId || !$certType) {
                http_response_code(400);
                echo json_encode(['error' => 'Staff ID and certification type required']);
                return;
            }
            
            try {
                $certId = $service->issueCertification($staffId, $certType, $validityMonths);
                echo json_encode(['success' => true, 'certification_id' => $certId]);
            } catch (Exception $e) {
                http_response_code(400);
                echo json_encode(['error' => $e->getMessage()]);
            }
            break;
            
        default:
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
    }
}

/**
 * Handle reports requests
 */
function handleReports($service, $method) {
    if ($method !== 'GET') {
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
        return;
    }
    
    $reportType = $_GET['type'] ?? '';
    $params = $_GET;
    unset($params['endpoint'], $params['type']);
    
    try {
        $report = $service->generateTrainingReport($reportType, $params);
        echo json_encode(['success' => true, 'data' => $report]);
    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode(['error' => $e->getMessage()]);
    }
}