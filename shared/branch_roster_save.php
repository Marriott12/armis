<?php
/**
 * NEW FILE (branch-scoping upgrade). Backend for shared/branch_roster.php's
 * edit modal, used by command/operations/training/finance/ordinance rosters.
 */

require_once dirname(__DIR__) . '/config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/session_guard.php';
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/database_connection.php';
require_once __DIR__ . '/rbac.php';

header('Content-Type: application/json');
enforceSessionTimeout();

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'message' => 'Not authenticated.']);
    exit();
}

require_csrf();

$svcNo = trim($_POST['svcNo'] ?? '');
$module = $_POST['module'] ?? '';
$newStatus = $_POST['svcStatus'] ?? '';

if ($svcNo === '' || !in_array($newStatus, ['Active', 'Retired', 'Deceased', 'AWOL', 'Discharged', 'On Contract'], true)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'message' => 'Missing or invalid fields.']);
    exit();
}

$pdo = getDbConnection();

// The submitted module must resolve to a real branch. This prevents a
// client from using a different module name while attempting a cross-branch
// roster update. Admin remains the only unrestricted role.
$branchStmt = $pdo->prepare("SELECT id, code, is_org_wide FROM branches WHERE code = :code LIMIT 1");
$branchStmt->execute(['code' => $module]);
$targetBranch = $branchStmt->fetch(PDO::FETCH_ASSOC);
if (!$targetBranch) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'message' => 'Invalid roster branch.']);
    exit();
}

$role = strtolower($_SESSION['role'] ?? '');
if ($role !== 'admin' && empty($targetBranch['is_org_wide']) && (int)getUserBranch() !== (int)$targetBranch['id']) {
    http_response_code(403);
    logAccess($module, 'roster_edit_denied', false, 'Attempted update outside posted branch.');
    echo json_encode(['ok' => false, 'message' => 'You can only update personnel attached to your posted branch.']);
    exit();
}

$stmt = $pdo->prepare("SELECT svcNo, branch_id, svcStatus FROM staff WHERE svcNo = :svcNo");
$stmt->execute(['svcNo' => $svcNo]);
$before = $stmt->fetch();

if (!$before) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'message' => 'Staff record not found.']);
    exit();
}

// The record itself must belong to the roster branch. This is the final
// server-side row-level guard, independent of whatever the browser submits.
if (empty($targetBranch['is_org_wide']) && (int)$before['branch_id'] !== (int)$targetBranch['id']) {
    http_response_code(403);
    logAccess($module, 'roster_edit_denied', false, 'Record does not belong to requested roster branch.');
    echo json_encode(['ok' => false, 'message' => 'That personnel record is not attached to this branch.']);
    exit();
}

if (!canAlterRecord($before['branch_id'])) {
    http_response_code(403);
    logAccess($module, 'roster_edit_denied', false);
    echo json_encode(['ok' => false, 'message' => 'You are not permitted to alter records for this branch.']);
    exit();
}

$pdo->prepare("UPDATE staff SET svcStatus = :status WHERE svcNo = :svcNo")
    ->execute(['status' => $newStatus, 'svcNo' => $svcNo]);

logStaffAlteration(
    $svcNo,
    $before['branch_id'],
    $module,
    $_SESSION['svcNo'] ?? ($_SESSION['user_id'] ?? 'unknown'),
    ['svcStatus' => $before['svcStatus']],
    ['svcStatus' => $newStatus]
);

echo json_encode(['ok' => true, 'message' => 'Record updated.']);
