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
        'modules' => ['admin_branch', 'command', 'operations', 'training', 'finance', 'ordinance', 'users', 'admin'],
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

/**
 * Check if current user has access to a specific module
 */
function hasModuleAccess($module, $userRole = null) {
    if ($userRole === null) {
        $userRole = $_SESSION['role'] ?? 'user';
    }
    
    $roles = ARMIS_ROLES;
    
    // Check if role exists
    if (!isset($roles[$userRole])) {
        return false;
    }
    
    // Check if user's role has access to the module
    return in_array($module, $roles[$userRole]['modules']);
}

/**
 * Require module access or redirect
 */
function requireModuleAccess($module, $redirectUrl = '/Armis2/unauthorized.php') {
    if (!hasModuleAccess($module)) {
        header('Location: ' . $redirectUrl);
        exit();
    }
}

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

/**
 * Redirect user to their appropriate dashboard
 */
function redirectToRoleDashboard($userRole = null) {
    $dashboardUrl = getRoleDashboardUrl($userRole);
    header('Location: ' . $dashboardUrl);
    exit();
}

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
?>
