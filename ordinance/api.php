<?php
/**
 * Ordinance Module API
 * AJAX endpoint for dynamic ordinance data
 */

// Define module constants
define('ARMIS_ORDINANCE', true);
define('ARMIS_DEVELOPMENT', true);

// Include required files
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/shared/session_init.php';
require_once dirname(__DIR__) . '/shared/database_connection.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/ordinance_service.php';

// Require authentication
requireOrdinanceAccess();

// Set JSON headers
header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

// Handle different API endpoints
$action = $_GET['action'] ?? '';

try {
    // Get database connection
    $pdo = getDbConnection();
    if (!$pdo) {
        throw new Exception('Database connection not available');
    }
    
    // Log API access
    logOrdinanceActivity('api_access', "Ordinance API accessed: {$action}");
    
    $service = new OrdinanceService($pdo);
    $response = ['success' => true, 'data' => null, 'timestamp' => date('c')];
    
    switch ($action) {
        case 'get_kpi':
            $response['data'] = $service->getKPIData();
            break;
            
        case 'get_recent_activities':
            $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
            $response['data'] = $service->getRecentActivities($limit);
            break;
            
        case 'get_inventory_overview':
            $response['data'] = $service->getInventoryOverview();
            break;
            
        case 'get_maintenance_schedule':
            $response['data'] = $service->getMaintenanceSchedule();
            break;
            
        case 'get_weapons_status':
            $response['data'] = $service->getWeaponsStatus();
            break;
            
        case 'get_ammunition_status':
            $response['data'] = $service->getAmmunitionStatus();
            break;
            
        case 'get_security_alerts':
            $response['data'] = $service->getSecurityAlerts();
            break;
            
        case 'get_all_dashboard_data':
        default:
            $response['data'] = [
                'kpi' => $service->getKPIData(),
                'recent_activities' => $service->getRecentActivities(5),
                'inventory_overview' => $service->getInventoryOverview(),
                'maintenance_schedule' => $service->getMaintenanceSchedule(),
                'weapons_status' => $service->getWeaponsStatus(),
                'ammunition_status' => $service->getAmmunitionStatus(),
                'security_alerts' => $service->getSecurityAlerts()
            ];
            break;
    }
    
    // Log successful API response
    logOrdinanceActivity('api_success', "Ordinance API response sent: {$action}");
    
} catch (Exception $e) {
    error_log("Ordinance API error: " . $e->getMessage());
    logOrdinanceActivity('api_error', "Ordinance API error: {$e->getMessage()}");
    
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
            if ($method === 'POST') {
                if (!hasOrdinancePermission('approve_transactions')) {
                    throw new Exception('Insufficient permissions');
                }
                $input = json_decode(file_get_contents('php://input'), true);
                $transactionId = $service->createTransaction($input);
                echo json_encode(['success' => true, 'transaction_id' => $transactionId]);
            }
            break;
            
        case 'inventory':
            if ($method === 'GET') {
                $category = $_GET['category'] ?? null;
                $inventory = OrdinanceUtils::getInventoryItems($category);
                echo json_encode(['success' => true, 'data' => $inventory]);
            }
            break;
            
        default:
            http_response_code(404);
            echo json_encode(['error' => 'Endpoint not found']);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
    logOrdinanceActivity('api_error', $e->getMessage());
}