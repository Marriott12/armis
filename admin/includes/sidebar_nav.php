<?php
/**
 * ARMIS Admin — shared sidebar navigation
 * ----------------------------------------
 * Every admin/*.php page used to hardcode its own copy of this exact
 * same array. Single source of truth now, matching the pattern
 * already established for admin_branch (see
 * admin_branch/includes/sidebar_nav.php).
 *
 * The whole /admin module is already gated to admin-level users via
 * requireModuleAccess('admin') on every page, and there's no finer
 * role tier defined anywhere else in this module (unlike admin_branch,
 * which has distinct read/write roles) — so all links are shown to
 * everyone who can reach the module at all. If finer-grained roles are
 * introduced later, this is the one place to add the filtering.
 *
 * Usage, at the top of any admin/*.php page, AFTER requireModuleAccess
 * has already run:
 *   require_once __DIR__ . '/includes/sidebar_nav.php';
 */

$sidebarLinks = [
    ['title' => 'Dashboard', 'url' => '/Armis2/admin/index.php', 'icon' => 'tachometer-alt', 'page' => 'dashboard'],
    ['title' => 'User Management', 'url' => '/Armis2/admin/users.php', 'icon' => 'users', 'page' => 'users'],
    ['title' => 'Manage Branches', 'url' => '/Armis2/admin/branches.php', 'icon' => 'sitemap', 'page' => 'branches'],
    ['title' => 'System Settings', 'url' => '/Armis2/admin/settings.php', 'icon' => 'cogs', 'page' => 'settings'],
    ['title' => 'Database Management', 'url' => '/Armis2/admin/database.php', 'icon' => 'database', 'page' => 'database'],
    ['title' => 'Security Center', 'url' => '/Armis2/admin/security.php', 'icon' => 'shield-alt', 'page' => 'security'],
    ['title' => 'System Reports', 'url' => '/Armis2/admin/reports.php', 'icon' => 'chart-bar', 'page' => 'reports'],
    ['title' => 'System Health', 'url' => '/Armis2/admin/health.php', 'icon' => 'heartbeat', 'page' => 'health'],
];
