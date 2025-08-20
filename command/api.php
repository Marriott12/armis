<?php
/**
 * ARMIS Command Module API
 * RESTful API endpoints for command operations
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Include required files
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/command_service.php';
require_once dirname(__DIR__) . '/shared/database_connection.php';

// Start session and validate access
session_start();

try {
    // Require authentication
    requireAuth();
    requireCommandAccess();
    
    // Initialize service
    $commandService = new CommandService();
    
    // Get request method and endpoint
    $method = $_SERVER['REQUEST_METHOD'];
    $endpoint = $_GET['endpoint'] ?? '';
    
    // Route requests
    switch ($endpoint) {
        case 'dashboard':
            handleDashboard($commandService, $method);
            break;
        
        case 'missions':
            handleMissions($commandService, $method);
            break;
        
        case 'mission':
            handleMission($commandService, $method);
            break;
        
        case 'personnel':
            handlePersonnel($commandService, $method);
            break;
        
        case 'communications':
            handleCommunications($commandService, $method);
            break;
        
        case 'reports':
            handleReports($commandService, $method);
            break;
        
        default:
            http_response_code(404);
            echo json_encode(['error' => 'Endpoint not found']);
            exit();
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
    logCommandActivity('api_error', $e->getMessage());
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
 * Handle missions requests
 */
function handleMissions($service, $method) {
    switch ($method) {
        case 'GET':
            $params = [
                'status' => $_GET['status'] ?? '',
                'priority' => $_GET['priority'] ?? '',
                'search' => $_GET['search'] ?? '',
                'limit' => $_GET['limit'] ?? 50
            ];
            
            $missions = $service->searchMissions($params);
            echo json_encode(['success' => true, 'data' => $missions]);
            break;
            
        case 'POST':
            if (!hasCommandPermission('manage_missions')) {
                http_response_code(403);
                echo json_encode(['error' => 'Insufficient permissions']);
                return;
            }
            
            $input = json_decode(file_get_contents('php://input'), true);
            $missionId = $service->createMission($input);
            
            if ($missionId) {
                echo json_encode(['success' => true, 'mission_id' => $missionId]);
            } else {
                http_response_code(400);
                echo json_encode(['error' => 'Failed to create mission']);
            }
            break;
            
        default:
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
    }
}

/**
 * Handle single mission requests
 */
function handleMission($service, $method) {
    $missionId = $_GET['id'] ?? 0;
    
    if (!$missionId) {
        http_response_code(400);
        echo json_encode(['error' => 'Mission ID required']);
        return;
    }
    
    switch ($method) {
        case 'GET':
            $mission = $service->getMissionDetails($missionId);
            if ($mission) {
                echo json_encode(['success' => true, 'data' => $mission]);
            } else {
                http_response_code(404);
                echo json_encode(['error' => 'Mission not found']);
            }
            break;
            
        case 'PUT':
            if (!hasCommandPermission('manage_missions')) {
                http_response_code(403);
                echo json_encode(['error' => 'Insufficient permissions']);
                return;
            }
            
            $input = json_decode(file_get_contents('php://input'), true);
            if (isset($input['status'])) {
                $result = $service->updateMissionStatus($missionId, $input['status']);
                echo json_encode(['success' => $result]);
            } else {
                http_response_code(400);
                echo json_encode(['error' => 'Status required']);
            }
            break;
            
        default:
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
    }
}

/**
 * Handle personnel requests
 */
function handlePersonnel($service, $method) {
    if ($method !== 'GET') {
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
        return;
    }
    
    $missionId = $_GET['mission_id'] ?? 0;
    if ($missionId) {
        $personnel = CommandUtils::getMissionPersonnel($missionId);
        echo json_encode(['success' => true, 'data' => $personnel]);
    } else {
        http_response_code(400);
        echo json_encode(['error' => 'Mission ID required']);
    }
}

/**
 * Handle communications requests
 */
function handleCommunications($service, $method) {
    switch ($method) {
        case 'GET':
            $limit = $_GET['limit'] ?? 50;
            $logs = CommandUtils::getCommunicationLogs($limit);
            echo json_encode(['success' => true, 'data' => $logs]);
            break;
            
        case 'POST':
            if (!hasCommandPermission('access_communications')) {
                http_response_code(403);
                echo json_encode(['error' => 'Insufficient permissions']);
                return;
            }
            
            $input = json_decode(file_get_contents('php://input'), true);
            $result = CommandUtils::logCommunication(
                $input['type'] ?? 'General',
                $input['message'] ?? '',
                $input['priority'] ?? 'Medium'
            );
            
            echo json_encode(['success' => $result]);
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
        $report = $service->generateCommandReport($reportType, $params);
        echo json_encode(['success' => true, 'data' => $report]);
    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode(['error' => $e->getMessage()]);
    }
}