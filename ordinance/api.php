<?php
/**
 * ARMIS Ordinance Module API
 * RESTful API endpoints for ordinance management
 */

header('Content-Type: application/json');

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/ordinance_service.php';

session_start();

try {
    requireAuth();
    requireOrdinanceAccess();
    
    $service = new OrdinanceService();
    $method = $_SERVER['REQUEST_METHOD'];
    $endpoint = $_GET['endpoint'] ?? '';
    
    switch ($endpoint) {
        case 'dashboard':
            if ($method === 'GET') {
                $data = $service->getDashboardData();
                echo json_encode(['success' => true, 'data' => $data]);
            }
            break;
            
        case 'weapons':
            if ($method === 'GET') {
                $params = [
                    'status' => $_GET['status'] ?? '',
                    'weapon_type' => $_GET['weapon_type'] ?? '',
                    'search' => $_GET['search'] ?? '',
                ];
                $weapons = $service->searchWeapons($params);
                echo json_encode(['success' => true, 'data' => $weapons]);
            } elseif ($method === 'POST') {
                if (!hasOrdinancePermission('manage_weapons')) {
                    throw new Exception('Insufficient permissions');
                }
                $input = json_decode(file_get_contents('php://input'), true);
                $weaponId = $service->createWeapon($input);
                echo json_encode(['success' => true, 'weapon_id' => $weaponId]);
            }
            break;
            
        case 'transactions':
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