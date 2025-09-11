<?php
/**
 * RBAC Compatibility Layer
 * 
 * This file provides compatibility with the newer permissions.php system.
 * It checks if functions exist before declaring them to prevent conflicts.
 */

// Define ARMIS roles if not already defined
if (!defined('ARMIS_ROLES')) {
    define('ARMIS_ROLES', [
        'admin' => [
            'name' => 'Administrator',
            'level' => 100,
            'modules' => ['admin', 'admin_branch', 'staff_management', 'operations', 'command', 'finance', 'users', 'settings'],
            'description' => 'Full system access'
        ],
        'administrator' => [
            'name' => 'Administrator',
            'level' => 100,
            'modules' => ['admin', 'admin_branch', 'staff_management', 'operations', 'command', 'finance', 'users', 'settings'],
            'description' => 'Full system access'
        ],
        'hr_officer' => [
            'name' => 'HR Officer',
            'level' => 80,
            'modules' => ['staff_management', 'admin_branch', 'users'],
            'description' => 'HR and personnel management'
        ],
        'records_officer' => [
            'name' => 'Records Officer',
            'level' => 70,
            'modules' => ['staff_management', 'admin_branch', 'users'],
            'description' => 'Records management'
        ],
        'commander' => [
            'name' => 'Commander',
            'level' => 90,
            'modules' => ['command', 'operations', 'users'],
            'description' => 'Command functionality'
        ],
        'finance_officer' => [
            'name' => 'Finance Officer',
            'level' => 75,
            'modules' => ['finance', 'users'],
            'description' => 'Finance and budget management'
        ],
        'operations_officer' => [
            'name' => 'Operations Officer',
            'level' => 85,
            'modules' => ['operations', 'users'],
            'description' => 'Operations planning and execution'
        ],
        'ordinance_officer' => [
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
}

// Check if function exists before declaring it
if (!function_exists('hasModuleAccess')) {
    function hasModuleAccess($module, $userRole = null) {
        if ($userRole === null) {
            $userRole = $_SESSION['role'] ?? 'user';
        }
        
        // Admin role always has access to all modules (case-insensitive check)
        if (strtolower($userRole) === 'admin' || strtolower($userRole) === 'administrator') {
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
    function getFilteredSidebarNavigation() {
        $userModules = getUserModules();
        $navigation = [];
        
        // Navigation items should be dynamically filtered based on user's accessible modules
        
        // This is just a placeholder - replace with actual navigation structure
        $allNavigation = [
            [
                'label' => 'Dashboard', 
                'url' => '/Armis2/index.php', 
                'icon' => 'tachometer-alt',
                'module' => 'users'
            ],
            [
                'label' => 'Staff Management', 
                'url' => '/Armis2/admin_branch/index.php', 
                'icon' => 'users',
                'module' => 'admin_branch'
            ],
            // Add other navigation items here
        ];
        
        // Filter navigation items based on user's module access
        foreach ($allNavigation as $item) {
            if (in_array($item['module'], $userModules)) {
                $navigation[] = $item;
            }
        }
        
        return $navigation;
    }
}

if (!function_exists('getRoleDashboardUrl')) {
    function getRoleDashboardUrl($userRole = null) {
        if ($userRole === null) {
            $userRole = $_SESSION['role'] ?? 'user';
        }
        
        // Default dashboard mapping for each role
        $dashboards = [
            'admin' => '/Armis2/admin/index.php',
            'administrator' => '/Armis2/admin/index.php',
            'hr_officer' => '/Armis2/admin_branch/index.php',
            'records_officer' => '/Armis2/admin_branch/index.php',
            'commander' => '/Armis2/command/index.php',
            'finance_officer' => '/Armis2/finance/index.php',
            'operations_officer' => '/Armis2/operations/index.php',
            'ordinance_officer' => '/Armis2/ordinance/index.php',
            'user' => '/Armis2/users/profile.php'
        ];
        
        return $dashboards[$userRole] ?? '/Armis2/index.php';
    }
}

if (!function_exists('redirectToRoleDashboard')) {
    function redirectToRoleDashboard($userRole = null) {
        $url = getRoleDashboardUrl($userRole);
        header('Location: ' . $url);
        exit();
    }
}

if (!function_exists('getRoleInfo')) {
    function getRoleInfo($userRole = null) {
        if ($userRole === null) {
            $userRole = $_SESSION['role'] ?? 'user';
        }
        
        $roles = ARMIS_ROLES;
        return $roles[$userRole] ?? null;
    }
}

if (!function_exists('logAccess')) {
    function logAccess($module, $action = 'access', $success = true) {
        // Log access for audit purposes
        $userId = $_SESSION['user_id'] ?? 0;
        $userName = $_SESSION['name'] ?? 'Unknown';
        $userRole = $_SESSION['role'] ?? 'Unknown';
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        
        $status = $success ? 'SUCCESS' : 'DENIED';
        error_log("[RBAC AUDIT] $status - User: $userName (ID:$userId, Role:$userRole) - Action: $action - Module: $module - IP: $ipAddress");
        
        // Optionally, this could also write to a database
    }
}
