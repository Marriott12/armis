<?php
require_once __DIR__ . '/permissions.php';
/**
 * ARMIS module navigation.
 * Visibility is derived from the same RBAC permissions used by the server-side
 * page guards. Hidden links are a UX feature; page/API guards remain the
 * security boundary.
 */

if (!function_exists('__armisNavigationAllowed')) {
    function __armisNavigationAllowed(array $link, string $module): bool
    {
        if (function_exists('hasModuleAccess') && !hasModuleAccess($module)) return false;
        if (!empty($link['permission']) && function_exists('hasPermission') && !hasPermission($link['permission'])) return false;
        if (($link['minAccess'] ?? null) === 'write') {
            $role = function_exists('getRoleInfo') ? getRoleInfo() : null;
            if (!$role || (($role['access'] ?? 'none') !== 'write' && strtolower((string)($_SESSION['role'] ?? '')) !== 'admin')) return false;
        }
        return true;
    }
}

if (!function_exists('__armisFilterNavigation')) {
    function __armisFilterNavigation(array $links, string $module): array
    {
        $out = [];
        foreach ($links as $link) {
            if (isset($link['children']) && is_array($link['children'])) {
                $link['children'] = __armisFilterNavigation($link['children'], $module);
                if (!$link['children']) continue;
            }
            if (!__armisNavigationAllowed($link, $module)) continue;
            unset($link['permission'], $link['minAccess']);
            $out[] = $link;
        }
        return $out;
    }
}

if (!function_exists('__armisModuleDashboardUrl')) {
    function __armisModuleDashboardUrl(string $module): string
    {
        $role = strtolower((string)($_SESSION['role'] ?? ''));
        if ($role === 'admin' || $role === 'ag') return '/Armis2/' . $module . '/index.php';
        if ($role === 'dg') return '/Armis2/oversight_dashboard.php';
        if (in_array($role, ['dag','ddg','cc','soi','soii','soiii'], true)) return '/Armis2/personnel_dashboard.php';
        return '/Armis2/' . $module . '/index.php';
    }
}

if (!function_exists('getModuleSidebarLinks')) {
    function getModuleSidebarLinks(string $module): array
    {
        $links = [];
        if ($module === 'command') $links = [
            ['title' => 'Dashboard', 'url' => __armisModuleDashboardUrl('command'), 'icon' => 'tachometer-alt', 'page' => 'dashboard', 'permission' => 'view_dashboard'],
            ['title' => 'Branch Roster', 'url' => '/Armis2/command/roster.php', 'icon' => 'users', 'page' => 'roster', 'permission' => 'view_staff'],
            ['title' => 'Staff Profiles', 'url' => '/Armis2/command/profiles.php', 'icon' => 'id-card', 'page' => 'profiles', 'permission' => 'view_staff'],
            ['title' => 'Operational Reports', 'url' => '/Armis2/command/op_reports.php', 'icon' => 'chart-bar', 'page' => 'op_reports', 'permission' => 'view_reports'],
            ['title' => 'Command Reports', 'url' => '/Armis2/command/reports.php', 'icon' => 'chart-line', 'page' => 'reports', 'permission' => 'view_reports'],
            ['title' => 'Course Records', 'url' => '/Armis2/command/courses.php', 'icon' => 'graduation-cap', 'page' => 'courses', 'permission' => 'view_reports'],
        ];
        elseif ($module === 'operations') $links = [
            ['title' => 'Dashboard', 'url' => __armisModuleDashboardUrl('operations'), 'icon' => 'tachometer-alt', 'page' => 'dashboard', 'permission' => 'view_dashboard'],
            ['title' => 'Branch Roster', 'url' => '/Armis2/operations/roster.php', 'icon' => 'users', 'page' => 'roster', 'permission' => 'view_staff'],
            ['title' => 'Mission Planning', 'url' => '/Armis2/operations/missions.php', 'icon' => 'map-marked-alt', 'page' => 'missions', 'permission' => 'manage_operations'],
            ['title' => 'Deployments', 'url' => '/Armis2/operations/deployments.php', 'icon' => 'plane', 'page' => 'deployments', 'permission' => 'manage_operations'],
            ['title' => 'Resource Allocation', 'url' => '/Armis2/operations/resources.php', 'icon' => 'boxes', 'page' => 'resources', 'permission' => 'manage_operations'],
            ['title' => 'Personnel Assignment', 'url' => '/Armis2/operations/personnel_assignment.php', 'icon' => 'user-plus', 'page' => 'assignments', 'permission' => 'manage_operations'],
            ['title' => 'Personnel Operations', 'url' => '/Armis2/operations/personnel_records.php', 'icon' => 'user-shield', 'page' => 'personnel_records', 'permission' => 'manage_operations'],
            ['title' => 'Status Reports', 'url' => '/Armis2/operations/reports.php', 'icon' => 'clipboard-list', 'page' => 'reports', 'permission' => 'view_reports'],
            ['title' => 'Field Operations', 'url' => '/Armis2/operations/field.php', 'icon' => 'crosshairs', 'page' => 'field', 'permission' => 'manage_operations'],
            ['title' => 'Analytics', 'url' => '/Armis2/operations/analytics.php', 'icon' => 'chart-pie', 'page' => 'analytics', 'permission' => 'view_reports'],
            ['title' => 'Setup Database', 'url' => '/Armis2/operations/setup_database.php', 'icon' => 'database', 'page' => 'setup', 'permission' => 'system_settings'],
        ];
        elseif ($module === 'training') $links = [
            ['title' => 'Dashboard', 'url' => __armisModuleDashboardUrl('training'), 'icon' => 'tachometer-alt', 'page' => 'dashboard', 'permission' => 'view_dashboard'],
            ['title' => 'Branch Roster', 'url' => '/Armis2/training/roster.php', 'icon' => 'users', 'page' => 'roster', 'permission' => 'view_staff'],
            ['title' => 'Course Catalog', 'url' => '/Armis2/training/courses.php', 'icon' => 'book', 'page' => 'courses', 'permission' => 'manage_education'],
            ['title' => 'Training Sessions', 'url' => '/Armis2/training/sessions.php', 'icon' => 'calendar-alt', 'page' => 'sessions', 'permission' => 'manage_education'],
            ['title' => 'Training Schedule', 'url' => '/Armis2/training/schedule.php', 'icon' => 'calendar', 'page' => 'schedule', 'permission' => 'manage_education'],
            ['title' => 'Training Records', 'url' => '/Armis2/training/records.php', 'icon' => 'certificate', 'page' => 'records', 'permission' => 'manage_education'],
            ['title' => 'Assignments', 'url' => '/Armis2/training/assignments.php', 'icon' => 'user-check', 'page' => 'assignments', 'permission' => 'manage_education'],
            ['title' => 'Certifications', 'url' => '/Armis2/training/certifications.php', 'icon' => 'award', 'page' => 'certifications', 'permission' => 'manage_education'],
            ['title' => 'Setup Database', 'url' => '/Armis2/training/setup_database.php', 'icon' => 'database', 'page' => 'setup', 'permission' => 'system_settings'],
        ];
        elseif ($module === 'finance') $links = [
            ['title' => 'Dashboard', 'url' => '/Armis2/finance/index.php', 'icon' => 'tachometer-alt', 'page' => 'dashboard', 'permission' => 'view_dashboard'],
            ['title' => 'Branch Roster', 'url' => '/Armis2/finance/roster.php', 'icon' => 'users', 'page' => 'roster', 'permission' => 'view_staff'],
            ['title' => 'Budget Planning', 'url' => '/Armis2/finance/budget.php', 'icon' => 'chart-line', 'page' => 'budget', 'minAccess' => 'write'],
            ['title' => 'Expenditures', 'url' => '/Armis2/finance/expenditures.php', 'icon' => 'money-bill-wave', 'page' => 'expenditures', 'minAccess' => 'write'],
            ['title' => 'Procurement', 'url' => '/Armis2/finance/procurement.php', 'icon' => 'shopping-cart', 'page' => 'procurement', 'minAccess' => 'write'],
            ['title' => 'Reports', 'url' => '/Armis2/finance/reports.php', 'icon' => 'chart-bar', 'page' => 'reports', 'permission' => 'view_reports'],
            ['title' => 'Audit Log', 'url' => '/Armis2/finance/audit.php', 'icon' => 'clipboard-list', 'page' => 'audit', 'permission' => 'view_reports'],
        ];
        elseif ($module === 'ordinance') $links = [
            ['title' => 'Dashboard', 'url' => '/Armis2/ordinance/index.php', 'icon' => 'tachometer-alt', 'page' => 'dashboard', 'permission' => 'view_dashboard'],
            ['title' => 'Branch Roster', 'url' => '/Armis2/ordinance/roster.php', 'icon' => 'users', 'page' => 'roster', 'permission' => 'view_staff'],
        ];
        return __armisFilterNavigation($links, $module);
    }
}
