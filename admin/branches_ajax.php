<?php
/**
 * NEW FILE (branch-scoping upgrade). Backend for admin/branches.php.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once dirname(__DIR__) . '/shared/database_connection.php';
require_once dirname(__DIR__) . '/shared/rbac.php';
require_once dirname(__DIR__) . '/shared/csrf.php';

header('Content-Type: application/json');
requireBranchAdmin(); // hard stop for non-admins - this endpoint mutates data
require_csrf();

$action = $_POST['action'] ?? '';
$adminSvcNo = $_SESSION['svcNo'] ?? ($_SESSION['user_id'] ?? 'unknown');

switch ($action) {
    case 'create':
        $result = createBranch(
            $_POST['name'] ?? '',
            $_POST['description'] ?? '',
            $_POST['icon'] ?? 'folder',
            $_POST['color'] ?? '#8a6d2f',
            $adminSvcNo,
            !empty($_POST['is_org_wide'])
        );
        echo json_encode($result);
        break;

    case 'update':
        $id = (int)($_POST['id'] ?? 0);
        if (!$id) { echo json_encode(['ok' => false, 'message' => 'Missing branch id.']); break; }
        $result = updateBranch(
            $id,
            $_POST['name'] ?? '',
            $_POST['description'] ?? '',
            $_POST['icon'] ?? 'folder',
            $_POST['color'] ?? '#8a6d2f',
            !empty($_POST['is_org_wide'])
        );
        echo json_encode($result);
        break;

    case 'toggle_status':
        $id = (int)($_POST['id'] ?? 0);
        $status = $_POST['status'] ?? 'Inactive';
        if (!$id) { echo json_encode(['ok' => false, 'message' => 'Missing branch id.']); break; }
        echo json_encode(setBranchStatus($id, $status));
        break;

    default:
        echo json_encode(['ok' => false, 'message' => 'Unknown action.']);
}
