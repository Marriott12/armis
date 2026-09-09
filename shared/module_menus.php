<?php
/**
 * ARMIS Module Menu Registry - single source of truth for each module's
 * INNER sidebar menu, replacing hardcoded $sidebarLinks arrays that were
 * copy-pasted (and drifted out of sync) across every page.
 */
if (!function_exists('getModuleMenu')) {
    function getModuleMenu($moduleCode) {
        $registry = __armisModuleMenuRegistry();
        return __armisFilterMenuByAccess($registry[$moduleCode] ?? []);
    }
}

if (!function_exists('__armisFilterMenuByAccess')) {
    function __armisFilterMenuByAccess(array $items) {
        $roleInfo = function_exists('getRoleInfo') ? getRoleInfo() : null;
        $canWrite = ($roleInfo && (($roleInfo['access'] ?? '') === 'write'))
            || in_array(strtolower($_SESSION['role'] ?? ''), ['admin', 'administrator', 'superadmin'], true);
        $out = [];
        foreach ($items as $item) {
            if (isset($item['children'])) {
                $item['children'] = __armisFilterMenuByAccess($item['children']);
                if (empty($item['children'])) continue;
            }
            if (($item['minAccess'] ?? null) === 'write' && !$canWrite) continue;
            if (!empty($item['permission']) && function_exists('hasPermission') && !hasPermission($item['permission'])) continue;
            unset($item['minAccess'], $item['permission']);
            $out[] = $item;
        }
        return $out;
    }
}

if (!function_exists('__armisModuleMenuRegistry')) {
    function __armisModuleMenuRegistry() {
        return [
            'command' => [
                ['title' => 'Dashboard',         'url' => '/Armis2/command/index.php',    'icon' => 'tachometer-alt', 'page' => 'dashboard'],
                ['title' => 'Branch Roster',     'url' => '/Armis2/command/roster.php',   'icon' => 'users',          'page' => 'roster'],
                ['title' => 'Staff Profiles',    'url' => '/Armis2/command/profiles.php', 'icon' => 'id-card',        'page' => 'profiles'],
                ['title' => 'Operational Reports','url' => '/Armis2/command/op_reports.php','icon' => 'file-alt',     'page' => 'op_reports'],
                ['title' => 'Command Reports',   'url' => '/Armis2/command/reports.php',  'icon' => 'chart-line',     'page' => 'reports'],
                ['title' => 'Course Records',    'url' => '/Armis2/command/courses.php',  'icon' => 'graduation-cap', 'page' => 'courses'],
            ],
            'operations' => [
                ['title' => 'Dashboard',            'url' => '/Armis2/operations/index.php',                 'icon' => 'tachometer-alt', 'page' => 'dashboard'],
                ['title' => 'Branch Roster',         'url' => '/Armis2/operations/roster.php',                'icon' => 'users',          'page' => 'roster'],
                ['title' => 'Mission Planning',      'url' => '/Armis2/operations/missions.php',              'icon' => 'map-marked-alt', 'page' => 'missions'],
                ['title' => 'Deployments',           'url' => '/Armis2/operations/deployments.php',           'icon' => 'plane',          'page' => 'deployments'],
                ['title' => 'Field Operations',      'url' => '/Armis2/operations/field.php',                 'icon' => 'crosshairs',     'page' => 'field'],
                ['title' => 'Resource Allocation',   'url' => '/Armis2/operations/resources.php',             'icon' => 'boxes',          'page' => 'resources'],
                ['title' => 'Personnel Assignment',  'url' => '/Armis2/operations/personnel_assignment.php',  'icon' => 'user-tag',       'page' => 'personnel_assignment'],
                ['title' => 'Status Reports',        'url' => '/Armis2/operations/reports.php',               'icon' => 'clipboard-list', 'page' => 'reports'],
                ['title' => 'Analytics',             'url' => '/Armis2/operations/analytics_dashboard.php',   'icon' => 'chart-pie',      'page' => 'analytics'],
                ['title' => 'Notifications Center',  'url' => '/Armis2/operations/notifications_center.php',  'icon' => 'bell',           'page' => 'notifications'],
                ['title' => 'Audit Log',             'url' => '/Armis2/operations/audit_log.php',             'icon' => 'history',        'page' => 'audit_log', 'minAccess' => 'write'],
            ],
            'training' => [
                ['title' => 'Dashboard',    'url' => '/Armis2/training/index.php',        'icon' => 'tachometer-alt', 'page' => 'dashboard'],
                ['title' => 'Branch Roster', 'url' => '/Armis2/training/roster.php',       'icon' => 'users',          'page' => 'roster'],
                ['title' => 'Course Catalog','url' => '/Armis2/training/courses.php',      'icon' => 'book',           'page' => 'courses'],
                ['title' => 'Training Records', 'url' => '/Armis2/training/records.php',   'icon' => 'certificate',    'page' => 'records'],
                ['title' => 'Sessions',     'url' => '/Armis2/training/sessions.php',      'icon' => 'calendar-alt',   'page' => 'sessions'],
                ['title' => 'Assignments',  'url' => '/Armis2/training/assignments.php',   'icon' => 'tasks',          'page' => 'assignments'],
            ],
            'finance' => [
                ['title' => 'Dashboard',    'url' => '/Armis2/finance/index.php',  'icon' => 'tachometer-alt', 'page' => 'dashboard'],
                ['title' => 'Branch Roster', 'url' => '/Armis2/finance/roster.php', 'icon' => 'users',          'page' => 'roster'],
            ],
            'ordinance' => [
                ['title' => 'Dashboard',    'url' => '/Armis2/ordinance/index.php',  'icon' => 'tachometer-alt', 'page' => 'dashboard'],
                ['title' => 'Branch Roster', 'url' => '/Armis2/ordinance/roster.php', 'icon' => 'users',          'page' => 'roster'],
            ],
        ];
    }
}
