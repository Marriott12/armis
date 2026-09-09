<?php
/**
 * ARMIS Role-Based Access Control (RBAC) System — v2
 * ---------------------------------------------------
 * THIS FILE IS NOW THE SINGLE CANONICAL SOURCE for hasModuleAccess(),
 * requireModuleAccess(), getUserModules(), etc. shared/rbac_compat.php and
 * shared/permissions.php have been updated to delegate to it rather than
 * define their own competing versions (see CHANGELOG.md for why that
 * mattered — permissions.php's copies were previously winning silently in
 * admin_branch/* because they loaded first and weren't function_exists-
 * guarded).
 *
 * Branches and roles live in the database (`branches`, `roles`,
 * `role_modules`) instead of a hardcoded PHP array, so an Admin can create a
 * brand-new branch from the Manage Branches screen and every check below
 * picks it up immediately — no code change, no deploy.
 *
 * PERMISSION MODEL
 * -----------------
 * Two independent things determine what a user can do:
 *
 *   1. roles.access   — 'write' (cc/soi/soii/soiii/admin) | 'read' (ag/dg) | 'none'
 *   2. REACH          — how much data that write/read applies to:
 *        - AG (roles.scope = 'org') is always whole-Army, not tied to a branch.
 *        - Everyone else's reach comes from the branch they're POSTED TO
 *          (staff.branch_id): if that branch has `is_org_wide` = 1 (Admin
 *          Branch only), their reach is the whole Army too — because
 *          personnel administration is inherently army-wide. Otherwise
 *          their reach is limited to records in that same branch.
 *
 * This is why scope isn't just a fixed property of the role code: a Chief
 * Clerk posted to Admin Branch and a Chief Clerk posted to Training Branch
 * hold the same role, but very different reach — reach follows the branch.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/database_connection.php';

// -----------------------------------------------------------------------
// Internal per-request caches
// -----------------------------------------------------------------------
$GLOBALS['__armis_roles_cache']    = null;
$GLOBALS['__armis_branches_cache'] = null;

if (!function_exists('assertBranchMigrationsApplied')) {
    /**
     * Fails fast with a clear, actionable message instead of a raw
     * PDOException stack trace if the branches/roles migrations under
     * database/migrations/ haven't been run against this database yet.
     */
    function assertBranchMigrationsApplied() {
        static $checked = false;
        if ($checked) return;
        $checked = true;

        $pdo = getDbConnection();
        try {
            $pdo->query("SELECT 1 FROM roles LIMIT 1");
            $pdo->query("SELECT 1 FROM branches LIMIT 1");
        } catch (PDOException $e) {
            http_response_code(500);
            die(
                '<div style="font-family:sans-serif;max-width:640px;margin:60px auto;padding:24px;border:1px solid #d98a7a;border-radius:6px;background:#fdf2f0;">'
                . '<h2 style="margin-top:0;color:#8a3b2e;">ARMIS setup incomplete</h2>'
                . '<p>The <code>branches</code>/<code>roles</code> tables don\'t exist yet in this database. '
                . 'Run the SQL files in <code>database/migrations/</code>, in filename order, against this database, then reload this page.</p>'
                . '<p style="color:#7c8a9a;font-size:13px;">(Original error: ' . htmlspecialchars($e->getMessage()) . ')</p>'
                . '</div>'
            );
        }
    }
}

if (!function_exists('getAllRoles')) {
    /** @return array<string,array> roles keyed by code, 'modules' merged in */
    function getAllRoles($activeOnly = true) {
        if ($GLOBALS['__armis_roles_cache'] !== null) {
            return $GLOBALS['__armis_roles_cache'];
        }
        assertBranchMigrationsApplied();
        $pdo = getDbConnection();
        $sql = "SELECT * FROM roles" . ($activeOnly ? " WHERE status = 'Active'" : "");
        $rows = $pdo->query($sql)->fetchAll();

        $modules = [];
        foreach ($pdo->query("SELECT role_code, module_code FROM role_modules")->fetchAll() as $m) {
            $modules[$m['role_code']][] = $m['module_code'];
        }

        $roles = [];
        foreach ($rows as $r) {
            $r['modules'] = $modules[$r['code']] ?? [];
            $roles[$r['code']] = $r;
        }
        $GLOBALS['__armis_roles_cache'] = $roles;
        return $roles;
    }
}

if (!function_exists('getAllBranches')) {
    /** @return array<int,array> branches keyed by id */
    function getAllBranches($activeOnly = true) {
        if ($GLOBALS['__armis_branches_cache'] !== null) {
            return $GLOBALS['__armis_branches_cache'];
        }
        $pdo = getDbConnection();
        $sql = "SELECT * FROM branches" . ($activeOnly ? " WHERE status = 'Active'" : "") . " ORDER BY name";
        $out = [];
        foreach ($pdo->query($sql)->fetchAll() as $r) {
            $out[$r['id']] = $r;
        }
        $GLOBALS['__armis_branches_cache'] = $out;
        return $out;
    }
}

if (!function_exists('getBranchByCode')) {
    function getBranchByCode($code) {
        foreach (getAllBranches(false) as $b) {
            if (strtolower($b['code']) === strtolower((string)$code)) return $b;
        }
        return null;
    }
}

if (!function_exists('getBranchById')) {
    function getBranchById($id) {
        if (!$id) return null;
        $branches = getAllBranches(false);
        return $branches[$id] ?? null;
    }
}

// Backward compatibility: rbac_compat.php and dashboard_access.php read the
// ARMIS_ROLES constant directly. Build it once, from the DB, so both still
// work without modification.
if (!defined('ARMIS_ROLES')) {
    $__armis_roles_for_constant = [];
    foreach (getAllRoles(false) as $code => $r) {
        $__armis_roles_for_constant[$code] = [
            'name'        => $r['name'],
            'level'       => (int)$r['level'],
            'modules'     => $r['modules'],
            'description' => $r['description'],
        ];
    }
    define('ARMIS_ROLES', $__armis_roles_for_constant);
    unset($__armis_roles_for_constant);
}

// -----------------------------------------------------------------------
// Session helpers
// -----------------------------------------------------------------------
if (!function_exists('getUserBranch')) {
    /** @return int|null branch_id the logged-in user is posted to */
    function getUserBranch() {
        return isset($_SESSION['branch_id']) && $_SESSION['branch_id'] !== ''
            ? (int)$_SESSION['branch_id']
            : null;
    }
}

if (!function_exists('getRoleInfo')) {
    function getRoleInfo($userRole = null) {
        if ($userRole === null) $userRole = $_SESSION['role'] ?? 'user';
        $roles = getAllRoles(false);
        return $roles[$userRole] ?? ($roles['user'] ?? null);
    }
}

// -----------------------------------------------------------------------
// Module access
// -----------------------------------------------------------------------
if (!function_exists('hasModuleAccess')) {
    function hasModuleAccess($module, $userRole = null) {
        if ($userRole === null) $userRole = $_SESSION['role'] ?? 'user';
        $branchId = getUserBranch();

        $roles = getAllRoles(false);
        if (!isset($roles[$userRole])) return false;
        $role = $roles[$userRole];

        if (in_array(strtolower($userRole), ['admin', 'administrator', 'superadmin'], true)) {
            return true;
        }

        if (in_array($module, $role['modules'], true)) return true;

        // Branch-scoped roles get implicit access to their OWN branch's module
        if ($branchId) {
            $branch = getBranchById($branchId);
            if ($branch && strtolower($branch['code']) === strtolower($module)) {
                return true;
            }
        }

        // AG (org scope by role) can view every branch's module
        if (($role['scope'] ?? '') === 'org' && getBranchByCode($module)) {
            return true;
        }

        return false;
    }
}

if (!function_exists('requireModuleAccess')) {
    function requireModuleAccess($module, $redirectUrl = '/Armis2/unauthorized.php') {
        $role = $_SESSION['role'] ?? 'none';
        error_log("RBAC check: module '$module' for role '$role'");

        if (!hasModuleAccess($module)) {
            error_log("RBAC denied: role '$role' denied access to module '$module'");
            logAccess($module, 'access', false);
            header('Location: ' . $redirectUrl . '?from=' . urlencode($module));
            exit();
        }

        error_log("RBAC granted: role '$role' granted access to module '$module'");
        logAccess($module, 'access', true);
    }
}

if (!function_exists('getUserModules')) {
    function getUserModules($userRole = null) {
        if ($userRole === null) $userRole = $_SESSION['role'] ?? 'user';
        $branchId = getUserBranch();

        $roles = getAllRoles(false);
        if (!isset($roles[$userRole])) return ['users'];
        $modules = $roles[$userRole]['modules'];
        $role = $roles[$userRole];

        if (in_array(strtolower($userRole), ['admin', 'administrator', 'superadmin'], true)) {
            foreach (getAllBranches(true) as $b) $modules[] = $b['code'];
            return array_values(array_unique($modules));
        }

        if ($branchId) {
            $branch = getBranchById($branchId);
            if ($branch) $modules[] = $branch['code'];
        }
        if (($role['scope'] ?? '') === 'org') {
            foreach (getAllBranches(true) as $b) $modules[] = $b['code'];
        }

        return array_values(array_unique($modules));
    }
}

if (!function_exists('hasMinimumLevel')) {
    function hasMinimumLevel($requiredLevel, $userRole = null) {
        $roleInfo = getRoleInfo($userRole);
        return $roleInfo && (int)$roleInfo['level'] >= $requiredLevel;
    }
}

// -----------------------------------------------------------------------
// THE TWO CORE PERMISSION CHECKS
// -----------------------------------------------------------------------
if (!function_exists('canAlterRecord')) {
    /**
     * Row-level write guard. Call before ANY create/update/delete on a staff
     * record. $recordBranchId must be the TARGET record's own branch_id,
     * loaded fresh from the DB — never a value submitted by the client.
     */
    function canAlterRecord($recordBranchId) {
        $role = $_SESSION['role'] ?? 'user';
        $roleInfo = getRoleInfo($role);
        if (!$roleInfo) return false;

        if (in_array(strtolower($role), ['admin', 'administrator', 'superadmin'], true)) return true;

        // AG and DG are read-only by design
        if (($roleInfo['access'] ?? '') !== 'write') return false;

        $myBranchId = getUserBranch();
        if (!$myBranchId) return false;

        $myBranch = getBranchById($myBranchId);
        if ($myBranch && !empty($myBranch['is_org_wide'])) {
            return true; // Admin Branch CC/SO: whole-Army write access
        }

        return $recordBranchId !== null && (int)$myBranchId === (int)$recordBranchId;
    }
}

if (!function_exists('canAlterStaffRecord')) {
    /** Convenience wrapper: loads the target staff row's branch_id for you. */
    function canAlterStaffRecord($svcNo) {
        if (in_array(strtolower($_SESSION['role'] ?? ''), ['admin', 'administrator', 'superadmin'], true)) {
            return true;
        }
        $pdo = getDbConnection();
        $stmt = $pdo->prepare("SELECT branch_id FROM staff WHERE svcNo = :svcNo");
        $stmt->execute(['svcNo' => $svcNo]);
        $row = $stmt->fetch();
        if (!$row) return false;
        return canAlterRecord($row['branch_id']);
    }
}

if (!function_exists('getSnapshotScope')) {
    /**
     * What personnel set a dashboard/report is allowed to query:
     *   ['scope' => 'all']                     -> admin / AG / anyone posted to an org-wide branch
     *   ['scope' => 'branch', 'branch_id' => N] -> DG / CC / SO on a normal branch
     *   ['scope' => 'none']                     -> plain user
     */
    function getSnapshotScope() {
        $role = $_SESSION['role'] ?? 'user';
        $roleInfo = getRoleInfo($role);
        if (!$roleInfo) return ['scope' => 'none'];

        if (in_array(strtolower($role), ['admin', 'administrator', 'superadmin'], true)) {
            return ['scope' => 'all'];
        }
        if (($roleInfo['scope'] ?? '') === 'org') {
            return ['scope' => 'all']; // AG
        }

        $myBranchId = getUserBranch();
        if ($myBranchId) {
            $myBranch = getBranchById($myBranchId);
            if ($myBranch && !empty($myBranch['is_org_wide'])) {
                return ['scope' => 'all']; // Admin Branch DG/CC/SO
            }
            return ['scope' => 'branch', 'branch_id' => $myBranchId];
        }

        return ['scope' => 'none'];
    }
}

// -----------------------------------------------------------------------
// Navigation / dashboards
// -----------------------------------------------------------------------
if (!function_exists('getFilteredSidebarNavigation')) {
    function getFilteredSidebarNavigation() {
        $userModules = getUserModules();
        $navigation = [];

        foreach (getAllBranches(true) as $branch) {
            if (in_array($branch['code'], $userModules, true)) {
                $navigation['system_branches'][] = [
                    'title' => $branch['name'],
                    'icon'  => $branch['icon'],
                    'url'   => $branch['url_path'] ?? ('/Armis2/' . $branch['code'] . '/'),
                ];
            }
        }

        $navigation['user_options'] = [
            ['title' => 'My Profile',  'icon' => 'user',         'url' => '/Armis2/users/'],
            ['title' => 'Download CV', 'icon' => 'download',     'url' => '/Armis2/users/cv_download.php'],
            ['title' => 'Logout',      'icon' => 'sign-out-alt', 'url' => '/Armis2/logout.php'],
        ];

        if (in_array('branches', $userModules, true)) {
            $navigation['admin_options'][] = ['title' => 'Manage Branches', 'icon' => 'sitemap', 'url' => '/Armis2/admin/branches.php'];
        }

        return $navigation;
    }
}

if (!function_exists('getRoleDashboardUrl')) {
    function getRoleDashboardUrl($userRole = null) {
        if ($userRole === null) $userRole = $_SESSION['role'] ?? 'user';
        $roleInfo = getRoleInfo($userRole);

        if (in_array(strtolower($userRole), ['admin', 'administrator', 'superadmin'], true)) {
            return '/Armis2/admin/index.php';
        }
        if ($userRole === 'ag') return '/Armis2/admin_branch/index.php';
        if ($roleInfo && ($roleInfo['scope'] ?? '') === 'branch') {
            $branch = getBranchById(getUserBranch());
            if ($branch) return $branch['url_path'] ?? ('/Armis2/' . $branch['code'] . '/index.php');
        }

        return '/Armis2/users/index.php';
    }
}

if (!function_exists('redirectToRoleDashboard')) {
    function redirectToRoleDashboard($userRole = null) {
        header('Location: ' . getRoleDashboardUrl($userRole));
        exit();
    }
}

// -----------------------------------------------------------------------
// Admin-only: dynamic branch management
// -----------------------------------------------------------------------
if (!function_exists('requireBranchAdmin')) {
    // Named distinctly from admin_branch/includes/auth.php's requireAdmin()
    // to avoid a function-redeclaration collision between the two modules.
    function requireBranchAdmin() {
        $role = strtolower($_SESSION['role'] ?? 'none');
        if (!in_array($role, ['admin', 'administrator', 'superadmin'], true)) {
            http_response_code(403);
            die('Forbidden: administrator access required.');
        }
    }
}

if (!function_exists('slugifyBranchCode')) {
    function slugifyBranchCode($name) {
        $slug = strtolower(trim((string)$name));
        $slug = preg_replace('/[^a-z0-9]+/', '_', $slug);
        return trim($slug, '_');
    }
}

if (!function_exists('createBranch')) {
    function createBranch($name, $description, $icon, $color, $createdBySvcNo, $isOrgWide = false) {
        $pdo = getDbConnection();
        $name = trim((string)$name);
        if ($name === '') return ['ok' => false, 'message' => 'Branch name is required.', 'id' => null];

        $code = slugifyBranchCode($name);
        if ($code === '') return ['ok' => false, 'message' => 'Could not derive a valid branch code from that name.', 'id' => null];

        $exists = $pdo->prepare("SELECT id FROM branches WHERE code = :code");
        $exists->execute(['code' => $code]);
        if ($exists->fetch()) {
            return ['ok' => false, 'message' => "A branch with code '$code' already exists.", 'id' => null];
        }

        $stmt = $pdo->prepare(
            "INSERT INTO branches (code, name, description, icon, color, url_path, is_org_wide, created_by, status)
             VALUES (:code, :name, :description, :icon, :color, :url_path, :is_org_wide, :created_by, 'Active')"
        );
        $stmt->execute([
            'code' => $code, 'name' => $name, 'description' => $description,
            'icon' => $icon ?: 'folder', 'color' => $color ?: '#8a6d2f',
            'url_path' => '/Armis2/' . $code . '/', 'is_org_wide' => $isOrgWide ? 1 : 0,
            'created_by' => $createdBySvcNo,
        ]);

        $GLOBALS['__armis_branches_cache'] = null;
        return ['ok' => true, 'message' => "Branch '$name' created.", 'id' => (int)$pdo->lastInsertId()];
    }
}

if (!function_exists('updateBranch')) {
    function updateBranch($id, $name, $description, $icon, $color, $isOrgWide = null) {
        $pdo = getDbConnection();
        $sql = "UPDATE branches SET name = :name, description = :description, icon = :icon, color = :color";
        $params = ['name' => $name, 'description' => $description, 'icon' => $icon, 'color' => $color, 'id' => $id];
        if ($isOrgWide !== null) {
            $sql .= ", is_org_wide = :is_org_wide";
            $params['is_org_wide'] = $isOrgWide ? 1 : 0;
        }
        $sql .= " WHERE id = :id";
        $pdo->prepare($sql)->execute($params);
        $GLOBALS['__armis_branches_cache'] = null;
        return ['ok' => true, 'message' => 'Branch updated.'];
    }
}

if (!function_exists('setBranchStatus')) {
    function setBranchStatus($id, $status) {
        $pdo = getDbConnection();
        $status = ($status === 'Active') ? 'Active' : 'Inactive';
        $pdo->prepare("UPDATE branches SET status = :status WHERE id = :id")->execute(['status' => $status, 'id' => $id]);
        $GLOBALS['__armis_branches_cache'] = null;
        return ['ok' => true, 'message' => "Branch marked $status."];
    }
}

// -----------------------------------------------------------------------
// Audit logging
// -----------------------------------------------------------------------
if (!function_exists('logAccess')) {
    function logAccess($module, $action = 'access', $success = true, $details = '') {
        $logFile = __DIR__ . '/../logs/access.log';
        $logDir = dirname($logFile);
        if (!is_dir($logDir)) @mkdir($logDir, 0755, true);

        $logEntry = [
            'timestamp'  => date('Y-m-d H:i:s'),
            'user_id'    => $_SESSION['user_id'] ?? ($_SESSION['svcNo'] ?? 'unknown'),
            'username'   => $_SESSION['username'] ?? 'unknown',
            'role'       => $_SESSION['role'] ?? 'unknown',
            'branch_id'  => getUserBranch(),
            'module'     => $module,
            'action'     => $action,
            'success'    => $success,
            'details'    => $details,
            'ip'         => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
        ];
        @file_put_contents($logFile, json_encode($logEntry) . "\n", FILE_APPEND | LOCK_EX);
    }
}

if (!function_exists('logStaffAlteration')) {
    /** Structured before/after diff, tagged with branch + module, for traceability. */
    function logStaffAlteration($svcNo, $branchId, $module, $editedBySvcNo, array $before, array $after) {
        $diff = [];
        foreach ($after as $key => $newVal) {
            $oldVal = $before[$key] ?? null;
            if ($oldVal !== $newVal) $diff[$key] = ['from' => $oldVal, 'to' => $newVal];
        }
        if (empty($diff)) return;

        $pdo = getDbConnection();
        $stmt = $pdo->prepare(
            "INSERT INTO branch_alteration_log (svcNo, branch_id, module, edited_by, changes, ip_address)
             VALUES (:svcNo, :branch_id, :module, :edited_by, :changes, :ip)"
        );
        $stmt->execute([
            'svcNo' => $svcNo, 'branch_id' => $branchId, 'module' => $module,
            'edited_by' => $editedBySvcNo, 'changes' => json_encode($diff),
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        ]);
    }
}
