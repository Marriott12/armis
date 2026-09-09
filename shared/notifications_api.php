<?php
/**
 * Shared notifications AJAX endpoint. Used by shared/js/notifications.js
 * from every module (any page that includes shared/header.php), so
 * this is the one place this logic lives instead of a per-module copy.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/database_connection.php';
require_once __DIR__ . '/notifications_helper.php';
require_once __DIR__ . '/csrf.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Not authenticated']);
    exit;
}

// $_SESSION['user_id'] is actually svcNo — see notifications_helper.php.
$svcNo = (string) $_SESSION['user_id'];
$action = $_GET['action'] ?? $_POST['action'] ?? 'list';

switch ($action) {
    case 'list':
        echo json_encode([
            'notifications' => getUserNotifications($svcNo, 20),
            'unread_count' => getUnreadNotificationCount($svcNo),
        ]);
        break;

    case 'unread_count':
        echo json_encode(['unread_count' => getUnreadNotificationCount($svcNo)]);
        break;

    case 'mark_read':
        require_csrf();
        $id = (int) ($_POST['id'] ?? 0);
        if ($id <= 0) {
            http_response_code(400);
            echo json_encode(['error' => 'Missing notification id']);
            break;
        }
        echo json_encode(['success' => markNotificationRead($id, $svcNo)]);
        break;

    case 'mark_all_read':
        require_csrf();
        echo json_encode(['success' => markAllNotificationsRead($svcNo)]);
        break;

    default:
        http_response_code(400);
        echo json_encode(['error' => 'Unknown action']);
}
