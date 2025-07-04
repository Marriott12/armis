<?php
require_once '../init.php';
if (!isset($user) || !$user->isLoggedIn()) { http_response_code(403); exit; }
$id = intval($_POST['id'] ?? 0);
if ($id > 0) {
    $db->update('user_notifications', $id, ['is_read'=>1]);
}
echo "OK";