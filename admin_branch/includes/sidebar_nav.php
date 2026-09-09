<?php
/**
 * ARMIS Admin Branch — shared, role-filtered sidebar navigation
 * ----------------------------------------------------------------
 * Every admin_branch/*.php page used to hardcode its own copy of
 * $sidebarLinks (30 separate copies, several already out of sync with
 * each other). This file is the single source of truth instead.
 *
 * Usage, at the top of any admin_branch page, AFTER requireAuth() and
 * requireModuleAccess('admin_branch') have already run:
 *
 *   $currentPage = 'dashboard'; // used to highlight the active link
 *   require_once __DIR__ . '/includes/sidebar_nav.php';
 *   ...
 *   include dirname(__DIR__) . '/shared/header.php';
 *   include dirname(__DIR__) . '/shared/sidebar.php';
 *
 * This sets $sidebarLinks, ready for shared/sidebar.php to render.
 *
 * VISIBILITY VS ENFORCEMENT: hiding a link here is a UX convenience,
 * not the security boundary. Every page this points to must still do
 * its own hasPermission()/canAlterRecord() check when loaded directly
 * — see CHANGELOG notes in shared/permissions.php and shared/rbac.php.
 * Where a page was found to be missing that check (rollback_promotions.php),
 * it was fixed separately, not papered over by hiding the link.
 */

require_once dirname(dirname(__DIR__)) . '/shared/permissions.php'; // pulls in rbac.php too

// Whether the current user can perform write actions at all (AG/DG are
// read-only oversight roles by design; see shared/rbac.php). Same flag
// admin_branch/index.php already computed locally — centralized here
// so every page agrees.
$__armisReadOnly = !hasPermission(PERM_EDIT_STAFF);

// System-administration-level tools (rank renumbering, rolling back a
// promotion after the fact) are a level above ordinary write access —
// gated on PERM_SYSTEM_SETTINGS, which only admin/superadmin hold (see
// shared/permissions.php's $rolePermissions map), not on every write
// role (cc/soi/soii/soiii).
$__armisIsSystemAdmin = hasPermission(PERM_SYSTEM_SETTINGS);

$sidebarLinks = [
    ['title' => 'Dashboard', 'url' => '/Armis2/admin_branch/index.php', 'icon' => 'tachometer-alt', 'page' => 'dashboard'],
];

if (hasPermission(PERM_VIEW_STAFF)) {
    $sidebarLinks[] = ['title' => 'Staff Management', 'url' => '/Armis2/admin_branch/edit_staff.php', 'icon' => 'users', 'page' => 'staff'];
    $sidebarLinks[] = ['title' => 'Advanced Search', 'url' => '/Armis2/admin_branch/advanced_search.php', 'icon' => 'search', 'page' => 'search'];
}

if (hasPermission(PERM_CREATE_STAFF)) {
    $sidebarLinks[] = ['title' => 'Create Staff', 'url' => '/Armis2/admin_branch/create_staff.php', 'icon' => 'user-plus', 'page' => 'create'];
}
if (hasPermission(PERM_DELETE_STAFF)) {
    $sidebarLinks[] = ['title' => 'Delete Staff', 'url' => '/Armis2/admin_branch/delete_staff.php', 'icon' => 'user-times', 'page' => 'delete'];
}
if (hasPermission(PERM_PROMOTE_STAFF)) {
    $sidebarLinks[] = ['title' => 'Promotions & Reversions', 'url' => '/Armis2/admin_branch/promote_staff.php', 'icon' => 'arrow-up', 'page' => 'promotions'];
}
if (hasPermission(PERM_MANAGE_APPOINTMENTS)) {
    $sidebarLinks[] = ['title' => 'Postings & Appointments', 'url' => '/Armis2/admin_branch/appointments.php', 'icon' => 'user-tie', 'page' => 'appointments'];
    $sidebarLinks[] = ['title' => 'Appointment Types', 'url' => '/Armis2/admin_branch/appointment_types.php', 'icon' => 'list', 'page' => 'appointment_types'];
}
if (hasPermission(PERM_ASSIGN_MEDALS)) {
    $sidebarLinks[] = ['title' => 'Honors and Awards', 'url' => '/Armis2/admin_branch/assign_medal.php', 'icon' => 'medal', 'page' => 'medals'];
}

if (hasPermission(PERM_VIEW_REPORTS)) {
    $sidebarLinks[] = [
        'title' => 'Seniority Rolls',
        'icon' => 'users',
        'children' => [
            ['title' => 'Officer Seniority', 'url' => '/Armis2/admin_branch/reports_seniority.php?report_type=officer'],
            ['title' => 'NCO Seniority', 'url' => '/Armis2/admin_branch/reports_nco_seniority.php?report_type=nco'],
            ['title' => 'CE Seniority', 'url' => '/Armis2/admin_branch/reports_ce_seniority.php?report_type=ce'],
        ],
    ];
    $sidebarLinks[] = [
        'title' => 'Nominal Rolls',
        'icon' => 'bars',
        'children' => [
            ['title' => 'Officer Nominal Roll', 'url' => '/Armis2/admin_branch/reports_officer_norminal.php?report_type=officer'],
            ['title' => 'NCO Nominal Roll', 'url' => '/Armis2/admin_branch/reports_nco_norminal.php?report_type=nco'],
            ['title' => 'CE Nominal Roll', 'url' => '/Armis2/admin_branch/reports_ce_norminal.php?report_type=ce'],
        ],
    ];
    $sidebarLinks[] = [
        'title' => 'Reports',
        'icon' => 'chart-bar',
        'page' => 'reports',
        'children' => [
            ['title' => 'Unit List', 'url' => '/Armis2/admin_branch/reports_units.php'],
            ['title' => 'Appointments', 'url' => '/Armis2/admin_branch/reports_appointment.php'],
            ['title' => 'Contracts', 'url' => '/Armis2/admin_branch/reports_contract.php'],
            ['title' => 'Courses', 'url' => '/Armis2/admin_branch/reports_courses.php'],
            ['title' => 'Deceased', 'url' => '/Armis2/admin_branch/reports_deceased.php'],
            ['title' => 'Gender', 'url' => '/Armis2/admin_branch/reports_gender.php'],
            ['title' => 'Marital', 'url' => '/Armis2/admin_branch/reports_marital.php'],
            ['title' => 'Rank', 'url' => '/Armis2/admin_branch/reports_rank.php'],
            ['title' => 'Retired', 'url' => '/Armis2/admin_branch/reports_retired.php'],
            ['title' => 'Trade', 'url' => '/Armis2/admin_branch/reports_trade.php'],
            ['title' => 'Corps', 'url' => '/Armis2/admin_branch/reports_corps.php'],
        ],
    ];
}

// System-admin-only tools. Previously admin_rank_tools.php was linked
// from nowhere (dead page reachable only by typing the URL) and
// rollback_promotions.php had no permission check at all beyond being
// logged in — both fixed to actually require PERM_SYSTEM_SETTINGS.
if ($__armisIsSystemAdmin) {
    $sidebarLinks[] = ['title' => 'Rank Tools', 'url' => '/Armis2/admin_branch/admin_rank_tools.php', 'icon' => 'sort-numeric-down', 'page' => 'rank_tools'];
    $sidebarLinks[] = ['title' => 'Rollback Promotions', 'url' => '/Armis2/admin_branch/rollback_promotions.php', 'icon' => 'undo', 'page' => 'rollback'];
    // Previously omitted: system_settings.php didn't exist (dead 404
    // link). It's been built now (see admin_branch/system_settings.php),
    // backed by a real settings table instead of the demo-only pattern
    // admin/settings.php uses.
    $sidebarLinks[] = ['title' => 'System Settings', 'url' => '/Armis2/admin_branch/system_settings.php', 'icon' => 'cogs', 'page' => 'settings'];
}
