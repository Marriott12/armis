<?php
/**
 * Live notifications feed, backed by the real `activity_log` table (there
 * is no dedicated notifications table in the schema, and no messaging
 * table at all - so this surfaces genuine recent system activity rather
 * than inventing data). Replaces the sidebar's old hardcoded badge ("3"
 * that never changed) and its no-op click handler.
 *
 * GET /shared/notifications_data.php  -> { count, items: [...] }
 */
if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/database_connection.php';
require_once __DIR__ . '/rbac.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Not authenticated.']);
    exit();
}

$pdo = getDbConnection();

// Org-wide/admin roles see the full recent-activity feed; branch-scoped
// roles see activity attributed to their own account plus general system
// actions (activity_log has no branch_id column to join on directly).
$scope = getSnapshotScope();
$since = date('Y-m-d H:i:s', strtotime('-7 days'));

if ($scope['scope'] === 'all') {
    $stmt = $pdo->prepare("
        SELECT id, username, action, details, createdAt
        FROM activity_log
        WHERE createdAt >= :since
        ORDER BY createdAt DESC
        LIMIT 15
    ");
    $stmt->execute(['since' => $since]);
} else {
    $stmt = $pdo->prepare("
        SELECT id, username, action, details, createdAt
        FROM activity_log
        WHERE createdAt >= :since AND user_id = :user_id
        ORDER BY createdAt DESC
        LIMIT 15
    ");
    $stmt->execute(['since' => $since, 'user_id' => $_SESSION['user_id']]);
}
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

function humanizeAction($action) {
    return ucwords(str_replace(['_', '-'], ' ', $action));
}
function iconForAction($action) {
    if (str_contains($action, 'login')) return 'sign-in-alt';
    if (str_contains($action, 'export')) return 'file-export';
    if (str_contains($action, 'search')) return 'search';
    if (str_contains($action, 'edit') || str_contains($action, 'update')) return 'edit';
    if (str_contains($action, 'create') || str_contains($action, 'add')) return 'plus-circle';
    if (str_contains($action, 'delete') || str_contains($action, 'remove')) return 'trash-alt';
    if (str_contains($action, 'view') || str_contains($action, 'access')) return 'eye';
    return 'bell';
}
function timeAgo($datetime) {
    $diff = time() - strtotime($datetime);
    if ($diff < 60) return 'just now';
    if ($diff < 3600) return floor($diff / 60) . 'm ago';
    if ($diff < 86400) return floor($diff / 3600) . 'h ago';
    return floor($diff / 86400) . 'd ago';
}

$items = array_map(function ($row) {
    return [
        'icon'  => iconForAction($row['action']),
        'title' => humanizeAction($row['action']),
        'who'   => $row['username'],
        'time'  => timeAgo($row['createdAt']),
        'at'    => $row['createdAt'],
    ];
}, $rows);

echo json_encode(['success' => true, 'count' => count($items), 'items' => $items]);
