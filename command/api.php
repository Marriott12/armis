<?php
/**
 * Command API Endpoint
 * Provides dynamic configuration and data for the command module
 */

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include required files
require_once dirname(__DIR__) . '/shared/rbac.php';
require_once __DIR__ . '/includes/CommandConfigService.php';
require_once __DIR__ . '/includes/CommandHandlerRegistry.php';

// Check authentication and authorization
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized', 'message' => 'Authentication required']);
    exit();
}

// Check module access
try {
    requireModuleAccess('command');
} catch (Exception $e) {
    http_response_code(403);
    echo json_encode(['error' => 'Forbidden', 'message' => $e->getMessage()]);
    exit();
}

// Set JSON headers
header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

// Get action parameter
$action = $_GET['action'] ?? '';

try {
    $configService = new CommandConfigService();
    $handlerRegistry = CommandHandlerRegistry::getInstance();
    $response = ['success' => true, 'timestamp' => date('c')];
    
    // Add validation middleware
    $handlerRegistry->addMiddleware(new CommandValidationMiddleware());
    $handlerRegistry->addMiddleware(new CommandLoggingMiddleware());
    
    switch ($action) {
        case 'get_config':
            $response['data'] = $configService->loadConfig();
            break;
            
        case 'get_navigation':
            $navigation = $configService->getNavigation();
            // Process through handler registry for consistency
            $processedNavigation = [];
            foreach ($navigation as $item) {
                try {
                    $result = $handlerRegistry->executeCommand('navigation_item', $item, getRequestContext());
                    $processedNavigation[] = $result['data'];
                } catch (Exception $e) {
                    error_log("Navigation item processing failed: " . $e->getMessage());
                    $processedNavigation[] = $item; // Fallback to original
                }
            }
            $response['data'] = $processedNavigation;
            break;
            
        case 'get_dashboard_modules':
            $userPermissions = getUserPermissions($_SESSION['user_id'] ?? 0);
            $modules = $configService->getDashboardModules(true, $userPermissions);
            
            // Process through handler registry
            $processedModules = [];
            foreach ($modules as $module) {
                try {
                    $context = array_merge(getRequestContext(), ['user_permissions' => $userPermissions]);
                    $result = $handlerRegistry->executeCommand('dashboard_module', $module, $context);
                    $processedModules[] = $result['data'];
                } catch (Exception $e) {
                    error_log("Dashboard module processing failed for {$module['id']}: " . $e->getMessage());
                    // Skip invalid modules instead of including them
                }
            }
            $response['data'] = $processedModules;
            break;
            
        case 'get_overview_stats':
            $stats = $configService->getOverviewStats();
            
            // Process through handler registry
            $processedStats = [];
            foreach ($stats as $stat) {
                try {
                    $result = $handlerRegistry->executeCommand('stat_widget', $stat, getRequestContext());
                    $processedStats[] = $result['data'];
                } catch (Exception $e) {
                    error_log("Stat widget processing failed for {$stat['id']}: " . $e->getMessage());
                    $processedStats[] = $stat; // Fallback to original
                }
            }
            $response['data'] = $processedStats;
            break;
            
        case 'get_settings':
            $response['data'] = $configService->getSettings();
            break;
            
        case 'get_stats_data':
            $response['data'] = getStatsData($_GET['type'] ?? 'all');
            break;
            
        case 'execute_command':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                throw new Exception('POST method required for command execution');
            }
            
            $commandType = $_POST['command_type'] ?? '';
            $commandData = json_decode($_POST['command_data'] ?? '{}', true);
            
            if (empty($commandType)) {
                throw new Exception('Command type is required');
            }
            
            $result = $handlerRegistry->executeCommand($commandType, $commandData, getRequestContext());
            $response['data'] = $result;
            $response['message'] = 'Command executed successfully';
            break;
            
        case 'get_registered_handlers':
            $response['data'] = $handlerRegistry->getRegisteredHandlers();
            break;
            
        case 'update_module':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                throw new Exception('POST method required for updates');
            }
            
            $moduleId = $_POST['module_id'] ?? '';
            $moduleData = json_decode($_POST['module_data'] ?? '{}', true);
            
            if (empty($moduleId)) {
                throw new Exception('Module ID is required');
            }
            
            // Validate through handler before saving
            try {
                $handlerRegistry->executeCommand('dashboard_module', $moduleData, getRequestContext());
            } catch (Exception $e) {
                throw new Exception('Module validation failed: ' . $e->getMessage());
            }
            
            $configService->updateDashboardModule($moduleId, $moduleData);
            $response['message'] = 'Module updated successfully';
            break;
            
        case 'remove_module':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                throw new Exception('POST method required for removal');
            }
            
            $moduleId = $_POST['module_id'] ?? '';
            if (empty($moduleId)) {
                throw new Exception('Module ID is required');
            }
            
            $configService->removeDashboardModule($moduleId);
            $response['message'] = 'Module removed successfully';
            break;
            
        default:
            throw new Exception('Invalid action specified');
    }
    
    echo json_encode($response);
    
} catch (Exception $e) {
    error_log("Command API Error: " . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'API Error',
        'message' => $e->getMessage()
    ]);
}

/**
 * Get request context for command execution
 */
function getRequestContext() {
    return [
        'user_id' => $_SESSION['user_id'] ?? 0,
        'user_role' => $_SESSION['user_role'] ?? 'user',
        'session_id' => session_id(),
        'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
        'timestamp' => time()
    ];
}

/**
 * Get user permissions (enhanced implementation)
 */
function getUserPermissions($userId) {
    // This would normally check the database for user-specific permissions
    // Enhanced to be more granular based on role
    $role = getUserRoleById($userId);
    
    $permissions = [];
    switch ($role) {
        case 'admin':
            $permissions = [
                'command.view',
                'command.edit',
                'command.delete',
                'command.reports',
                'command.profiles',
                'command.search',
                'operations.view',
                'operations.edit',
                'system.admin'
            ];
            break;
        case 'command':
            $permissions = [
                'command.view',
                'command.reports',
                'command.profiles',
                'command.search',
                'operations.view'
            ];
            break;
        case 'operations':
            $permissions = [
                'command.view',
                'operations.view'
            ];
            break;
        default:
            $permissions = ['command.view'];
            break;
    }
    
    return $permissions;
}

/**
 * Get actual statistics data (enhanced)
 */
function getStatsData($type) {
    // This would normally connect to the database and fetch real data
    // Enhanced to provide more realistic sample data
    
    switch ($type) {
        case 'operations':
            return [
                'value' => rand(10, 25),
                'label' => 'Active Operations',
                'trend' => rand(0, 1) ? 'up' : 'down',
                'change' => (rand(0, 1) ? '+' : '-') . rand(1, 5) . ' from last week',
                'details' => [
                    'domestic' => rand(5, 15),
                    'international' => rand(0, 10),
                    'training' => rand(2, 8)
                ]
            ];
            
        case 'personnel':
            $value = rand(85, 98);
            return [
                'value' => $value . '%',
                'label' => 'Personnel Ready',
                'trend' => 'stable',
                'change' => $value > 90 ? 'Excellent readiness' : 'Within acceptable range',
                'details' => [
                    'available' => $value,
                    'training' => rand(2, 8),
                    'leave' => rand(5, 15)
                ]
            ];
            
        case 'alerts':
            $count = rand(0, 8);
            return [
                'value' => $count,
                'label' => 'Active Alerts',
                'trend' => $count <= 3 ? 'down' : 'up',
                'change' => ($count <= 3 ? '-' : '+') . rand(1, 3) . ' from yesterday',
                'details' => [
                    'critical' => min($count, rand(0, 2)),
                    'warning' => min($count, rand(0, 4)),
                    'info' => max(0, $count - 2)
                ]
            ];
            
        case 'mission':
            $statuses = ['GREEN', 'YELLOW', 'AMBER'];
            $status = $statuses[array_rand($statuses)];
            return [
                'value' => $status,
                'label' => 'Mission Status',
                'trend' => 'stable',
                'change' => $status === 'GREEN' ? 'All systems nominal' : 'Monitoring required',
                'details' => [
                    'overall_status' => $status,
                    'last_update' => date('H:i'),
                    'next_review' => date('H:i', strtotime('+4 hours'))
                ]
            ];
            
        default:
            return [
                'operations' => getStatsData('operations'),
                'personnel' => getStatsData('personnel'),
                'alerts' => getStatsData('alerts'),
                'mission' => getStatsData('mission')
            ];
    }
}