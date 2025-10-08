<?php
/**
 * ARMIS Role-Based Access Control (RBAC) System
 * Centralizes permission management for all modules
 */

/**
 * Define role hierarchy and permissions
 */
define('ARMIS_ROLES', [
    'admin' => [
        'name' => 'Administrator',
        'level' => 100,
        'modules' => ['admin', 'admin_branch', 'command', 'operations', 'training', 'finance', 'ordinance', 'users'],
        'description' => 'Full system access'
    ],
    'command' => [
        'name' => 'Command Officer',
        'level' => 80,
        'modules' => ['command', 'operations', 'training', 'users'],
        'description' => 'Command and operational oversight'
    ],
    'training' => [
        'name' => 'Training Officer',
        'level' => 60,
        'modules' => ['training', 'users'],
        'description' => 'Training management only'
    ],
    'operations' => [
        'name' => 'Operations Officer',
        'level' => 60,
        'modules' => ['operations', 'users'],
        'description' => 'Operations management only'
    ],
    'admin_branch' => [
        'name' => 'Admin Branch Staff',
        'level' => 70,
        'modules' => ['admin_branch', 'users'],
        'description' => 'Personnel administration'
    ],
    'finance' => [
        'name' => 'Finance Officer',
        'level' => 60,
        'modules' => ['finance', 'users'],
        'description' => 'Financial management only'
    ],
    'ordinance' => [
        'name' => 'Ordinance Officer',
        'level' => 60,
        'modules' => ['ordinance', 'users'],
        'description' => 'Equipment and ordinance management'
    ],
    'user' => [
        'name' => 'Standard User',
        'level' => 10,
        'modules' => ['users'],
        'description' => 'Basic profile access only'
    ]
]);

// Only define the functions if they don't already exist
if (!function_exists('hasModuleAccess')) {
    /**
     * Check if current user has access to a specific module
     */
    function hasModuleAccess($module, $userRole = null) {
        if ($userRole === null) {
            $userRole = $_SESSION['role'] ?? 'user';
        }
        
        // Admin role always has access to all modules (case-insensitive check)
        $adminRoles = ['admin', 'administrator', 'admin_branch'];
        if (in_array(strtolower($userRole), $adminRoles)) {
            return true;
        }
    
    $roles = ARMIS_ROLES;
    
    // Check if role exists (try exact match first)
    if (!isset($roles[$userRole])) {
        // Try case-insensitive match
        $roleLower = strtolower($userRole);
        $foundMatch = false;
        
        foreach (array_keys($roles) as $definedRole) {
            if (strtolower($definedRole) === $roleLower) {
                $userRole = $definedRole; // Use the correctly cased role
                $foundMatch = true;
                break;
            }
        }
        
        if (!$foundMatch) {
            return false;
        }
    }
    
    // Check if user's role has access to the module
    return in_array($module, $roles[$userRole]['modules']);
}
}

if (!function_exists('requireModuleAccess')) {
/**
 * Require module access or redirect
 */
function requireModuleAccess($module, $redirectUrl = '/Armis2/unauthorized.php') {
    // Create a debugging log for troubleshooting
    error_log("RBAC check: Checking access to module '$module' for user role '" . ($_SESSION['role'] ?? 'none') . "'");
    
    if (!hasModuleAccess($module)) {
        error_log("RBAC denied: User with role '" . ($_SESSION['role'] ?? 'none') . "' denied access to module '$module'");
        header('Location: ' . $redirectUrl . '?from=' . urlencode($module));
        exit();
    }
    
    error_log("RBAC granted: User with role '" . ($_SESSION['role'] ?? 'none') . "' granted access to module '$module'");
}
}

if (!function_exists('getUserModules')) {
/**
 * Get user's accessible modules
 */
function getUserModules($userRole = null) {
    if ($userRole === null) {
        $userRole = $_SESSION['role'] ?? 'user';
    }
    
    $roles = ARMIS_ROLES;
    
    if (!isset($roles[$userRole])) {
        return ['users']; // Default to basic access
    }
    
    return $roles[$userRole]['modules'];
}
}

if (!function_exists('hasMinimumLevel')) {
/**
 * Check if user has higher or equal level access
 */
function hasMinimumLevel($requiredLevel, $userRole = null) {
    if ($userRole === null) {
        $userRole = $_SESSION['role'] ?? 'user';
    }
    
    $roles = ARMIS_ROLES;
    
    if (!isset($roles[$userRole])) {
        return false;
    }
    
    return $roles[$userRole]['level'] >= $requiredLevel;
}
}

if (!function_exists('getFilteredSidebarNavigation')) {
/**
 * Get filtered sidebar navigation based on user permissions
 */
function getFilteredSidebarNavigation() {
    $userModules = getUserModules();
    $navigation = [];
    
    // System Branches - only show modules user has access to
    $systemBranches = [
        'admin' => ['title' => 'System Admin', 'icon' => 'cogs', 'url' => '/Armis2/admin/', 'badge' => '!'],
        'admin_branch' => ['title' => 'Admin Branch', 'icon' => 'users-cog', 'url' => '/Armis2/admin_branch/'],
        'command' => ['title' => 'Command', 'icon' => 'chess-king', 'url' => '/Armis2/command/'],
        'operations' => ['title' => 'Operations', 'icon' => 'map-marked-alt', 'url' => '/Armis2/operations/'],
        'training' => ['title' => 'Training', 'icon' => 'graduation-cap', 'url' => '/Armis2/training/', 'badge' => '3'],
        'finance' => ['title' => 'Finance', 'icon' => 'calculator', 'url' => '/Armis2/finance/', 'badge' => '5'],
        'ordinance' => ['title' => 'Ordinance', 'icon' => 'shield-alt', 'url' => '/Armis2/ordinance/']
    ];
    
    foreach ($systemBranches as $module => $data) {
        if (in_array($module, $userModules)) {
            $navigation['system_branches'][] = $data;
        }
    }
    
    // User Options - always available
    $navigation['user_options'] = [
        ['title' => 'My Profile', 'icon' => 'user', 'url' => '/Armis2/users/'],
        ['title' => 'Download CV', 'icon' => 'download', 'url' => '/Armis2/users/cv_download.php'],
        ['title' => 'Logout', 'icon' => 'sign-out-alt', 'url' => '/Armis2/logout.php']
    ];
    
    return $navigation;
}
}

if (!function_exists('getRoleDashboardUrl')) {
/**
 * Get role-appropriate dashboard URL
 */
function getRoleDashboardUrl($userRole = null) {
    if ($userRole === null) {
        $userRole = $_SESSION['role'] ?? 'user';
    }
    
    $dashboards = [
        'admin' => '/Armis2/admin/index.php',
        'admin_branch' => '/Armis2/admin_branch/index.php',
        'command' => '/Armis2/command/index.php',
        'training' => '/Armis2/training/index.php',
        'operations' => '/Armis2/operations/index.php',
        'finance' => '/Armis2/finance/index.php',
        'ordinance' => '/Armis2/ordinance/index.php',
        'user' => '/Armis2/users/index.php'
    ];
    
    return $dashboards[$userRole] ?? '/Armis2/users/index.php';
}
}

if (!function_exists('redirectToRoleDashboard')) {
/**
 * Redirect user to their appropriate dashboard
 */
function redirectToRoleDashboard($userRole = null) {
    $dashboardUrl = getRoleDashboardUrl($userRole);
    header('Location: ' . $dashboardUrl);
    exit();
}
}

if (!function_exists('getRoleInfo')) {
/**
 * Get role display information
 */
function getRoleInfo($userRole = null) {
    if ($userRole === null) {
        $userRole = $_SESSION['role'] ?? 'user';
    }
    
    $roles = ARMIS_ROLES;
    return $roles[$userRole] ?? $roles['user'];
}
}

if (!function_exists('logAccess')) {
/**
 * Log access attempts for audit
 */
function logAccess($module, $action = 'access', $success = true) {
    $logFile = __DIR__ . '/logs/access.log';
    $logDir = dirname($logFile);
    
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }
    
    $logEntry = [
        'timestamp' => date('Y-m-d H:i:s'),
        'user_id' => $_SESSION['user_id'] ?? 'unknown',
        'username' => $_SESSION['username'] ?? 'unknown',
        'role' => $_SESSION['role'] ?? 'unknown',
        'module' => $module,
        'action' => $action,
        'success' => $success,
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
    ];
    
    file_put_contents($logFile, json_encode($logEntry) . "\n", FILE_APPEND | LOCK_EX);
}
}
?>
