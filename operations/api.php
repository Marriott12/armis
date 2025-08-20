<?php
/**
 * Operations Module API
 * AJAX endpoint for dynamic operations data
 */

// Define module constants
define('ARMIS_OPERATIONS', true);
define('ARMIS_DEVELOPMENT', true);

// Include required files
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/shared/session_init.php';
require_once dirname(__DIR__) . '/shared/database_connection.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/operations_service.php';

// Require authentication
requireOperationsAccess();

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
    logOperationsActivity('api_access', "Operations API accessed: {$action}");
    
    $service = new OperationsService($pdo);
    $response = ['success' => true, 'data' => null, 'timestamp' => date('c')];
    
    switch ($action) {
        case 'get_kpi':
            $response['data'] = $service->getKPIData();
            break;
            
        case 'get_recent_activities':
            $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
            $response['data'] = $service->getRecentActivities($limit);
            break;
            
        case 'get_mission_stats':
            $response['data'] = $service->getMissionStats();
            break;
            
        case 'get_deployment_overview':
            $response['data'] = $service->getDeploymentOverview();
            break;
            
        case 'get_resource_allocation':
            $response['data'] = $service->getResourceAllocation();
            break;
            
        case 'get_active_alerts':
            $response['data'] = $service->getActiveAlerts();
            break;
            
        case 'get_readiness_metrics':
            $response['data'] = $service->getReadinessMetrics();
            break;
            
        case 'get_all_dashboard_data':
        default:
            $response['data'] = [
                'kpi' => $service->getKPIData(),
                'recent_activities' => $service->getRecentActivities(5),
                'mission_stats' => $service->getMissionStats(),
                'deployment_overview' => $service->getDeploymentOverview(),
                'resource_allocation' => $service->getResourceAllocation(),
                'readiness_metrics' => $service->getReadinessMetrics()
            ];
            break;
    }
    
    // Log successful API response
    logOperationsActivity('api_success', "Operations API response sent: {$action}");
    
} catch (Exception $e) {
    error_log("Operations API error: " . $e->getMessage());
    logOperationsActivity('api_error', "Operations API error: {$e->getMessage()}");
    
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