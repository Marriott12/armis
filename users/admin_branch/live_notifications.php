<?php
require_once '../init.php';

if (!isset($user) || !$user->isLoggedIn()) {
    http_response_code(403); echo json_encode(['notifications'=>[]]); exit;
}
$user_id = $user->data()->id;
$since = isset($_GET['since']) ? intval($_GET['since']) : 0;

// Fetch unread and not archived notifications for this user, not already read, not archived
$notifs = $db->query(
    "SELECT id, message, class, date_created 
     FROM notifications 
     WHERE user_id = ? 
       AND is_read = 0 
       AND is_archived = 0
       AND id > ?
     ORDER BY id ASC LIMIT 10",
    [$user_id, $since]
)->results();

$result = [];
foreach ($notifs as $n) {
    // Convert your 'class' field to a bootstrap alert type if used ('success', 'info', 'warning', 'danger')
    $type = $n->class ?: 'info';
    $result[] = [
        'id'      => (int)$n->id,
        'message' => $n->message,
        'type'    => $type,
        'timeout' => 8000
    ];
}

header('Content-Type: application/json');
echo json_encode(['notifications' => $result]);