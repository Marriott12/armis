<?php
/**
 * ARMIS Operations Module API
 * RESTful API endpoints for operations management
 */

header('Content-Type: application/json');

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/operations_service.php';

session_start();

try {
    requireAuth();
    requireOperationsAccess();
    
    $service = new OperationsService();
    $method = $_SERVER['REQUEST_METHOD'];
    $endpoint = $_GET['endpoint'] ?? '';
    
    switch ($endpoint) {
        case 'dashboard':
            if ($method === 'GET') {
                $data = $service->getDashboardData();
                echo json_encode(['success' => true, 'data' => $data]);
            }
            break;
            
        case 'operations':
            if ($method === 'GET') {
                $params = [
                    'status' => $_GET['status'] ?? '',
                    'classification' => $_GET['classification'] ?? '',
                    'search' => $_GET['search'] ?? '',
                    'limit' => $_GET['limit'] ?? 50
                ];
                $operations = $service->searchOperations($params);
                echo json_encode(['success' => true, 'data' => $operations]);
            } elseif ($method === 'POST') {
                if (!hasOperationsPermission('manage_missions')) {
                    throw new Exception('Insufficient permissions');
                }
                $input = json_decode(file_get_contents('php://input'), true);
                $operationId = $service->createOperation($input);
                echo json_encode(['success' => true, 'operation_id' => $operationId]);
            }
            break;
            
        default:
            http_response_code(404);
            echo json_encode(['error' => 'Endpoint not found']);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}