<?php

if (!function_exists('getModuleSidebarLinks')) {
    function getModuleSidebarLinks(string $module): array
    {
        if ($module === 'command') return [
            ['title' => 'Dashboard', 'url' => '/Armis2/command/index.php', 'icon' => 'tachometer-alt', 'page' => 'dashboard'],
            ['title' => 'Branch Roster', 'url' => '/Armis2/command/roster.php', 'icon' => 'users', 'page' => 'roster'],
            ['title' => 'Staff Profiles', 'url' => '/Armis2/command/profiles.php', 'icon' => 'id-card', 'page' => 'profiles'],
            ['title' => 'Operational Reports', 'url' => '/Armis2/command/op_reports.php', 'icon' => 'chart-bar', 'page' => 'op_reports'],
            ['title' => 'Command Reports', 'url' => '/Armis2/command/reports.php', 'icon' => 'chart-line', 'page' => 'reports'],
            ['title' => 'Course Records', 'url' => '/Armis2/command/courses.php', 'icon' => 'graduation-cap', 'page' => 'courses'],
        ];
        if ($module === 'operations') {
            $links = [
            ['title' => 'Dashboard', 'url' => '/Armis2/operations/index.php', 'icon' => 'tachometer-alt', 'page' => 'dashboard'],
            ['title' => 'Branch Roster', 'url' => '/Armis2/operations/roster.php', 'icon' => 'users', 'page' => 'roster'],
            ['title' => 'Mission Planning', 'url' => '/Armis2/operations/missions.php', 'icon' => 'map-marked-alt', 'page' => 'missions'],
            ['title' => 'Deployments', 'url' => '/Armis2/operations/deployments.php', 'icon' => 'plane', 'page' => 'deployments'],
            ['title' => 'Resource Allocation', 'url' => '/Armis2/operations/resources.php', 'icon' => 'boxes', 'page' => 'resources'],
            ['title' => 'Personnel Assignment', 'url' => '/Armis2/operations/personnel_assignment.php', 'icon' => 'user-plus', 'page' => 'assignments'],
            ['title' => 'Status Reports', 'url' => '/Armis2/operations/reports.php', 'icon' => 'clipboard-list', 'page' => 'reports'],
            ['title' => 'Field Operations', 'url' => '/Armis2/operations/field.php', 'icon' => 'crosshairs', 'page' => 'field'],
            ];
            if (function_exists('getRoleInfo') && (getRoleInfo()['access'] ?? 'none') !== 'write') {
                $links = array_values(array_filter($links, static fn(array $link): bool => in_array($link['page'], ['dashboard', 'roster', 'reports'], true)));
            }
            if (in_array(strtolower($_SESSION['role'] ?? ''), ['admin', 'administrator', 'superadmin'], true)) {
                $links[] = ['title' => 'Setup Database', 'url' => '/Armis2/operations/setup_database.php', 'icon' => 'database', 'page' => 'setup'];
            }
            return $links;
        }
        if ($module === 'training') {
            $links = [
                ['title' => 'Dashboard', 'url' => '/Armis2/training/index.php', 'icon' => 'tachometer-alt', 'page' => 'dashboard'],
                ['title' => 'Branch Roster', 'url' => '/Armis2/training/roster.php', 'icon' => 'users', 'page' => 'roster'],
                ['title' => 'Course Catalog', 'url' => '/Armis2/training/courses.php', 'icon' => 'book', 'page' => 'courses'],
                ['title' => 'Training Sessions', 'url' => '/Armis2/training/sessions.php', 'icon' => 'calendar-alt', 'page' => 'sessions'],
                ['title' => 'Training Schedule', 'url' => '/Armis2/training/schedule.php', 'icon' => 'calendar', 'page' => 'schedule'],
                ['title' => 'Training Records', 'url' => '/Armis2/training/records.php', 'icon' => 'certificate', 'page' => 'records'],
                ['title' => 'Assignments', 'url' => '/Armis2/training/assignments.php', 'icon' => 'user-check', 'page' => 'assignments'],
                ['title' => 'Certifications', 'url' => '/Armis2/training/certifications.php', 'icon' => 'award', 'page' => 'certifications'],
            ];
            if (function_exists('getRoleInfo') && (getRoleInfo()['access'] ?? 'none') !== 'write') {
                $links = array_values(array_filter($links, static fn(array $link): bool => in_array($link['page'], ['dashboard', 'roster', 'records', 'schedule', 'certifications'], true)));
            }
            if (in_array(strtolower($_SESSION['role'] ?? ''), ['admin', 'administrator', 'superadmin'], true)) {
                $links[] = ['title' => 'Setup Database', 'url' => '/Armis2/training/setup_database.php', 'icon' => 'database', 'page' => 'setup'];
            }
            return $links;
        }
        if ($module === 'finance') {
            $links = [
                ['title' => 'Dashboard', 'url' => '/Armis2/finance/index.php', 'icon' => 'tachometer-alt', 'page' => 'dashboard'],
                ['title' => 'Branch Roster', 'url' => '/Armis2/finance/roster.php', 'icon' => 'users', 'page' => 'roster'],
                ['title' => 'Budget Planning', 'url' => '/Armis2/finance/budget.php', 'icon' => 'chart-line', 'page' => 'budget'],
                ['title' => 'Expenditures', 'url' => '/Armis2/finance/expenditures.php', 'icon' => 'money-bill-wave', 'page' => 'expenditures'],
                ['title' => 'Procurement', 'url' => '/Armis2/finance/procurement.php', 'icon' => 'shopping-cart', 'page' => 'procurement'],
                ['title' => 'Reports', 'url' => '/Armis2/finance/reports.php', 'icon' => 'chart-bar', 'page' => 'reports'],
                ['title' => 'Audit Log', 'url' => '/Armis2/finance/audit.php', 'icon' => 'clipboard-list', 'page' => 'audit'],
            ];
            if (function_exists('getRoleInfo') && (getRoleInfo()['access'] ?? 'none') !== 'write') $links = array_values(array_filter($links, static fn(array $link): bool => in_array($link['page'], ['dashboard', 'roster', 'reports', 'audit'], true)));
            return $links;
        }
        if ($module === 'ordinance') return [
            ['title' => 'Dashboard', 'url' => '/Armis2/ordinance/index.php', 'icon' => 'tachometer-alt', 'page' => 'dashboard'],
            ['title' => 'Branch Roster', 'url' => '/Armis2/ordinance/roster.php', 'icon' => 'users', 'page' => 'roster'],
        ];
        return [];
    }
}