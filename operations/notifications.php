<?php
/**
 * Notification helpers for the operations module.
 *
 * FIX: these used to talk directly to `operations_notifications`, a
 * table that was never created in any migration — every call here
 * (sendNotification/getNotifications) silently failed or returned
 * nothing. Delegates to the shared, real notifications system instead
 * (see shared/notifications_helper.php and
 * database/migrations/2026_08_27_add_notifications_table.sql).
 *
 * The old local markNotificationRead() here was dropped rather than
 * fixed — it was dead code (nothing called it by this name; only
 * OperationsManager::markNotificationRead(), a different function, is
 * actually used), and keeping it would collide with
 * shared/notifications_helper.php's function of the same name if both
 * ever load in the same request.
 */
require_once dirname(__DIR__) . '/shared/notifications_helper.php';

function sendNotification($userId, $message, $type = 'info') {
    // $userId here is $_SESSION['user_id'], which is actually svcNo —
    // see notifications_helper.php's doc comment.
    createNotification((string) $userId, 'operations', $type, $message);
}

function getNotifications($userId) {
    // Translate the shared schema's field names (status/title/created_at)
    // back to what operations/index.php's and deployments.php's existing
    // templates expect (is_read/message/createdAt), so those templates
    // don't need to change.
    $rows = getUserNotifications((string) $userId);
    return array_map(function ($row) {
        return [
            'notification_id' => $row['id'],
            'user_id' => $userId,
            'message' => $row['message'] ?? $row['title'],
            'type' => $row['type'],
            'is_read' => $row['status'] === 'read' ? 1 : 0,
            'createdAt' => $row['created_at'],
            'link' => $row['link'],
        ];
    }, $rows);
}
