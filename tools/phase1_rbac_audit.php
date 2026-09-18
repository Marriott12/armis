<?php
/** ARMIS Phase 1 CLI audit. Run from project root after configuring DB access. */
require_once __DIR__ . '/../shared/database_connection.php';
$pdo = getDbConnection();

echo "ARMIS Phase 1 RBAC Audit\n=========================\n";
$roles = $pdo->query("SELECT r.code, r.name, r.scope, r.access, r.status, COUNT(DISTINCT s.svcNo) AS staff_count
    FROM roles r LEFT JOIN staff s ON s.role = r.code GROUP BY r.id ORDER BY r.level DESC, r.code")->fetchAll(PDO::FETCH_ASSOC);
foreach ($roles as $r) {
    printf("%-18s scope=%-6s access=%-5s status=%-8s staff=%d\n", $r['code'], $r['scope'], $r['access'], $r['status'], $r['staff_count']);
}

$orphan = $pdo->query("SELECT DISTINCT s.role FROM staff s LEFT JOIN roles r ON r.code=s.role WHERE r.code IS NULL AND s.role <> '' ORDER BY s.role")->fetchAll(PDO::FETCH_COLUMN);
echo "\nOrphan staff roles: " . ($orphan ? implode(', ', $orphan) : 'none') . "\n";

$missingBranch = $pdo->query("SELECT COUNT(*) FROM staff s JOIN roles r ON r.code=s.role WHERE r.is_branch_assignable=1 AND s.branch_id IS NULL AND s.accStatus='Active'")->fetchColumn();
echo "Active branch-assignable accounts without branch: {$missingBranch}\n";

$permissionCount = $pdo->query("SELECT COUNT(*) FROM role_permissions")->fetchColumn();
echo "Centralized role permissions: {$permissionCount}\n";
