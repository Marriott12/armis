<?php
/**
 * Finance Module API
 * AJAX endpoint for dynamic finance data
 */

// Define module constants
define('ARMIS_FINANCE', true);
define('ARMIS_DEVELOPMENT', true);

// Include required files
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/shared/session_init.php';
require_once dirname(__DIR__) . '/shared/database_connection.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/finance_service.php';

// Require authentication
requireFinanceAccess();

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
    logFinanceActivity('api_access', "Finance API accessed: {$action}");
    
    $service = new FinanceService($pdo);
    $response = ['success' => true, 'data' => null, 'timestamp' => date('c')];
    
    switch ($action) {
        case 'get_kpi':
            $response['data'] = $service->getKPIData();
            break;
            
        case 'get_recent_activities':
            $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
            $response['data'] = $service->getRecentActivities($limit);
            break;
            
        case 'get_budget_overview':
            $response['data'] = $service->getBudgetOverview();
            break;
            
        case 'get_expense_breakdown':
            $response['data'] = $service->getExpenseBreakdown();
            break;
            
        case 'get_monthly_trends':
            $response['data'] = $service->getMonthlyTrends();
            break;
            
        case 'get_pending_transactions':
            $response['data'] = $service->getPendingTransactions();
            break;
            
        case 'get_contract_overview':
            $response['data'] = $service->getContractOverview();
            break;
            
        case 'get_financial_alerts':
            $response['data'] = $service->getFinancialAlerts();
            break;
            
        case 'get_all_dashboard_data':
        default:
            $response['data'] = [
                'kpi' => $service->getKPIData(),
                'recent_activities' => $service->getRecentActivities(5),
                'budget_overview' => $service->getBudgetOverview(),
                'expense_breakdown' => $service->getExpenseBreakdown(),
                'monthly_trends' => $service->getMonthlyTrends(),
                'pending_transactions' => $service->getPendingTransactions(),
                'contract_overview' => $service->getContractOverview(),
                'financial_alerts' => $service->getFinancialAlerts()
            ];
            break;
    }
    
    // Log successful API response
    logFinanceActivity('api_success', "Finance API response sent: {$action}");
    
} catch (Exception $e) {
    error_log("Finance API error: " . $e->getMessage());
    logFinanceActivity('api_error', "Finance API error: {$e->getMessage()}");
    
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