<?php
/**
 * ARMIS Admin Branch Dashboard API - Enhanced with Database Analytics
 * Provides JSON data for dashboard widgets and AJAX requests using enhanced database schema
 */

// Define module constants
define('ARMIS_ADMIN_BRANCH', true);
define('ARMIS_DEVELOPMENT', true);

// Include admin branch authentication and database
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/analytics.php';

// Require authentication
if (!isAuthenticated()) {
    http_response_code(401);
    echo json_encode(['error' => 'Authentication required']);
    exit;
}

// Set JSON response header
header('Content-Type: application/json');

// Get the action from the request
$action = $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'dashboard_stats':
            $stats = EnhancedAnalytics::getDashboardStats();
            echo json_encode([
                'success' => true,
                'data' => $stats,
                'timestamp' => time()
            ]);
            break;
            
        case 'personnel_chart_data':
            $type = $_GET['type'] ?? 'rank_distribution';
            $data = EnhancedAnalytics::getPersonnelChartData($type);
            echo json_encode([
                'success' => true,
                'data' => $data
            ]);
            break;
            
        case 'recent_activities':
            $limit = (int)($_GET['limit'] ?? 10);
            $activities = EnhancedAnalytics::getRecentActivities($limit);
            echo json_encode([
                'success' => true,
                'data' => $activities
            ]);
            break;
            
        case 'system_alerts':
            $alerts = EnhancedAnalytics::getSystemAlerts();
            echo json_encode([
                'success' => true,
                'data' => $alerts
            ]);
            break;
            
        case 'search_staff':
            $query = $_GET['q'] ?? '';
            if (strlen($query) < 2) {
                echo json_encode([
                    'success' => false,
                    'error' => 'Query must be at least 2 characters'
                ]);
                break;
            }
            
            $results = EnhancedAnalytics::searchStaff($query);
            echo json_encode([
                'success' => true,
                'data' => $results
            ]);
            break;
            
        case 'get_notifications':
            // Return sample notifications for now to prevent errors
            $notifications = [
                [
                    'id' => 1,
                    'title' => 'System Update',
                    'message' => 'System maintenance scheduled for tonight',
                    'type' => 'info',
                    'time' => date('Y-m-d H:i:s', strtotime('-1 hour')),
                    'read' => false
                ],
                [
                    'id' => 2,
                    'title' => 'New Staff Member',
                    'message' => 'Welcome new personnel to the system',
                    'type' => 'success',
                    'time' => date('Y-m-d H:i:s', strtotime('-2 hours')),
                    'read' => false
                ]
            ];
            
            echo json_encode([
                'success' => true,
                'data' => $notifications,
                'unread_count' => 2
            ]);
            break;
            
        default:
            echo json_encode([
                'success' => false,
                'error' => 'Invalid action specified'
            ]);
            break;
    }
    
} catch (Exception $e) {
    error_log("API Error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'An error occurred while processing your request'
    ]);
}
