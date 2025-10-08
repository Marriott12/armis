<?php
/**
 * Admin Branch Dashboard API
 * AJAX endpoint for dynamic dashboard data
 */

// Define module constants
define('ARMIS_ADMIN_BRANCH', true);
define('ARMIS_DEVELOPMENT', true);

// Include required files
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/shared/session_init.php';
require_once dirname(__DIR__) . '/shared/database_connection.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/dashboard_service.php';

// Require authentication
requireAuth();
requireModuleAccess('admin_branch');

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
    
    // Verify which database we're connected to
    $stmt = $pdo->query("SELECT DATABASE() as current_db");
    $dbInfo = $stmt->fetch(PDO::FETCH_ASSOC);
    error_log("Dashboard API: Connected to database: " . $dbInfo['current_db']);
    
    $service = new DashboardService($pdo);
    $response = ['success' => true, 'data' => null, 'timestamp' => date('c'), 'database' => $dbInfo['current_db']];
    
    switch ($action) {
        case 'get_kpi':
            $response['data'] = $service->getKPIData();
            break;
            
        case 'get_personnel_distribution':
            $response['data'] = $service->getPersonnelDistribution();
            break;
            
        case 'get_recruitment_trends':
            $response['data'] = $service->getRecruitmentTrends();
            break;
            
        case 'get_performance_metrics':
            $response['data'] = $service->getPerformanceMetrics();
            break;
            
        case 'get_recent_activities':
            $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
            $response['data'] = $service->getRecentActivities($limit);
            break;
            
        case 'get_rank_distribution':
            $response['data'] = $service->getRankDistribution();
            break;
            
        // Drill-down endpoints
        case 'get_drill_down':
            $target = $_GET['target'] ?? '';
            switch ($target) {
                case 'personnel-details':
                    $response['data'] = $service->getPersonnelDetails();
                    break;
                case 'active-personnel':
                    $response['data'] = $service->getActivePersonnelBreakdown();
                    break;
                case 'recruitment':
                    $response['data'] = $service->getRecruitmentAnalytics();
                    break;
                case 'performance':
                    $response['data'] = $service->getPerformanceAnalytics();
                    break;
                default:
                    throw new Exception('Invalid drill-down target: ' . $target);
            }
            break;
            
        case 'get_unit_overview':
            $response['data'] = $service->getUnitOverview();
            break;
            
        case 'get_alerts':
            $response['data'] = $service->getAlerts();
            break;
            
        case 'get_upcoming_events':
            $response['data'] = $service->getUpcomingEvents();
            break;
            
        case 'get_quick_action_stats':
            $response['data'] = $service->getQuickActionStats();
            break;
            
        case 'get_dynamic_activities':
            $response['data'] = $service->getDynamicRecentActivities();
            break;
            
        case 'get_dynamic_units':
            $response['data'] = $service->getDynamicUnitOverview();
            break;
            
        // Advanced analytics endpoints
        case 'get_predictive_attrition':
            $response['data'] = $service->getPredictiveAttrition();
            break;
            
        case 'get_training_completion':
            $response['data'] = $service->getTrainingCompletionRates();
            break;
            
        case 'get_personnel_by_category':
            $category = $_GET['category'] ?? '';
            $gender = $_GET['gender'] ?? null;
            
            if (empty($category)) {
                $response['success'] = false;
                $response['message'] = 'Category parameter is required';
            } else {
                $response['data'] = $service->getPersonnelByCategory($category, $gender);
            }
            break;
            
        case 'get_cohort_analysis':
            $response['data'] = $service->getCohortAnalysis();
            break;
            
        // Data table with pagination and filtering
        case 'get_personnel_table':
            $params = $_GET;
            $response['data'] = $service->getPersonnelTableData($params);
            break;
            
        // Export functionality
        case 'export_data':
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                $params = $_POST;
                // Verify CSRF token if implemented
                $exportResult = $service->exportDashboardData($params);
                
                // Handle file download if requested
                if ($exportResult['success'] && isset($_POST['download']) && $_POST['download'] == 1) {
                    $response['data'] = is_array($exportResult) ? $exportResult : [];
                    if (!is_array($response['data'])) {
                        $response['data'] = [];
                    }
                } else {
                    $response['data'] = $exportResult;
                }
            } else {
                $response['success'] = false;
                $response['message'] = 'Export requires POST method';
            }
            break;
            
        // Widget state management
        case 'widget_state':
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                $params = $_POST;
                // Verify CSRF token if implemented
                $response['data'] = $service->handleWidgetState($params);
            } else {
                // For GET requests, load widget states
                $widgetId = $_GET['widget_id'] ?? '';
                if ($widgetId) {
                    $params = ['action' => 'load', 'widget_id' => $widgetId];
                    $response['data'] = $service->handleWidgetState($params);
                } else {
                    $response['success'] = false;
                    $response['message'] = 'No widget specified';
                }
            }
            break;
            
        // Real-time notifications - DISABLED
        case 'get_notifications':
            // Notifications completely disabled
            $response['data'] = ['notifications' => [], 'count' => 0, 'unread' => 0];
            break;
            
        // Mark notification as read - DISABLED
        case 'mark_notification_read':
            // Notifications completely disabled
            $response['success'] = false;
            $response['message'] = 'Notifications are disabled';
            break;
            
        case 'get_all_dashboard_data':
        default:
            $response['data'] = [
                'kpi' => $service->getKPIData(),
                'personnel_distribution' => $service->getPersonnelDistribution(),
                'recruitment_trends' => $service->getRecruitmentTrends(),
                'performance_metrics' => $service->getPerformanceMetrics(),
                'recent_activities' => $service->getRecentActivities(),
                'rank_distribution' => $service->getRankDistribution()
            ];
            break;
    }
    
    echo json_encode($response);
    
} catch (Exception $e) {
    error_log("Dashboard API Error: " . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Failed to load dashboard data',
        'message' => ARMIS_DEVELOPMENT ? $e->getMessage() : 'Internal server error'
    ]);
}
?>
