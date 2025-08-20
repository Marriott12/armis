<?php
/**
 * ARMIS Finance Module API
 * RESTful API endpoints for finance management
 */

header('Content-Type: application/json');

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/finance_service.php';

session_start();

try {
    requireAuth();
    requireFinanceAccess();
    
    $service = new FinanceService();
    $method = $_SERVER['REQUEST_METHOD'];
    $endpoint = $_GET['endpoint'] ?? '';
    
    switch ($endpoint) {
        case 'dashboard':
            if ($method === 'GET') {
                $data = $service->getDashboardData();
                echo json_encode(['success' => true, 'data' => $data]);
            }
            break;
            
        case 'budgets':
            if ($method === 'GET') {
                $params = [
                    'status' => $_GET['status'] ?? '',
                    'fiscal_year' => $_GET['fiscal_year'] ?? '',
                ];
                $budgets = $service->searchBudgets($params);
                echo json_encode(['success' => true, 'data' => $budgets]);
            } elseif ($method === 'POST') {
                if (!hasFinancePermission('manage_budgets')) {
                    throw new Exception('Insufficient permissions');
                }
                $input = json_decode(file_get_contents('php://input'), true);
                $budgetId = $service->createBudget($input);
                echo json_encode(['success' => true, 'budget_id' => $budgetId]);
            }
            break;
            
        case 'expenditures':
            if ($method === 'POST') {
                if (!hasFinancePermission('approve_expenditures')) {
                    throw new Exception('Insufficient permissions');
                }
                $input = json_decode(file_get_contents('php://input'), true);
                $expenditureId = $service->createExpenditure($input);
                echo json_encode(['success' => true, 'expenditure_id' => $expenditureId]);
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