<?php
// Notification system for operations module
require_once dirname(__DIR__) . '/shared/database_connection.php';

function sendNotification($userId, $message, $type = 'info') {
    $db = getDbConnection();
    $stmt = $db->prepare('INSERT INTO operations_notifications (user_id, message, type) VALUES (?, ?, ?)');
    $stmt->execute([$userId, $message, $type]);
}

function getNotifications($userId) {
    $db = getDbConnection();
    $stmt = $db->prepare('SELECT * FROM operations_notifications WHERE user_id = ? ORDER BY createdAt DESC');
    $stmt->execute([$userId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function markNotificationRead($notificationId) {
    $db = getDbConnection();
    $stmt = $db->prepare('UPDATE operations_notifications SET is_read = 1 WHERE notification_id = ?');
    $stmt->execute([$notificationId]);
}
?>
