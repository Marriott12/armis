<?php
/**
 * ARMIS Admin Branch application-layer RBAC guard.
 * Every page/API endpoint must enforce authentication, module access and the
 * action permission appropriate to the operation. Row-level scope is checked
 * separately with canViewStaffRecord()/canAlterStaffRecord().
 */
require_once dirname(__DIR__, 2) . '/shared/csrf.php';
require_once __DIR__ . '/auth.php';
requireAuth();
requireModuleAccess('admin_branch');

function adminBranchDeny(string $message='Access denied.'): void {
    http_response_code(403);
    if (defined('ARMIS_JSON') && ARMIS_JSON) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success'=>false,'error'=>$message]);
        exit;
    }
    exit($message);
}
function adminBranchRequirePermission(string $permission): void {
    if (!hasPermission($permission)) adminBranchDeny('You do not have permission to perform this action.');
}
function adminBranchRequireAnyPermission(array $permissions): void {
    foreach ($permissions as $permission) if (hasPermission($permission)) return;
    adminBranchDeny('You do not have permission to perform this action.');
}
function adminBranchRequireWrite(): void {
    $role = getRoleInfo();
    if (!$role || ($role['access'] ?? 'none') !== 'write') adminBranchDeny('This area is read-only for your role.');
}
function adminBranchRequireCsrf(): void { require_csrf(); }
function adminBranchRequireStaffView(string $svcNo): void {
    if (!canViewStaffRecord($svcNo)) adminBranchDeny('You are not authorized to view this staff record.');
}
function adminBranchRequireStaffAlter(string $svcNo): void {
    if (!canAlterStaffRecord($svcNo)) adminBranchDeny('You are not authorized to alter this staff record.');
}
