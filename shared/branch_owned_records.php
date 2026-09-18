<?php
/**
 * Shared helpers for branch-owned personnel records.
 * Operations and Training modules use staff.branch_id as the scope boundary.
 */
require_once __DIR__ . '/rbac.php';

function armisScopedStaffWhere(string $alias = 's'): array {
    $role = strtolower((string)($_SESSION['role'] ?? ''));
    if ($role === 'admin') return ['1=1', []];
    $branchId = getUserBranch();
    if (!$branchId) return ['1=0', []];
    return ["{$alias}.branch_id = :branch_id", ['branch_id' => (int)$branchId]];
}

function armisScopedStaffExists(PDO $pdo, string $svcNo): ?array {
    $role = strtolower((string)($_SESSION['role'] ?? ''));
    $sql = "SELECT s.svcNo, s.fName, s.mName, s.lName, s.rankId, s.unitId, s.branch_id, s.svcStatus
            FROM staff s WHERE s.svcNo = :svc LIMIT 1";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['svc' => $svcNo]);
    $staff = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$staff) return null;
    if ($role === 'admin') return $staff;
    $branchId = getUserBranch();
    if (!$branchId || (int)$staff['branch_id'] !== (int)$branchId) return null;
    return $staff;
}
