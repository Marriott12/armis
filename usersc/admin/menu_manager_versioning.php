<?php
// On every change (add/edit/delete/restore), store a JSON snapshot in menu_versions table
// menu_versions: id, snapshot JSON, timestamp, user_id, comment
if (!function_exists('save_menu_version')) {
function save_menu_version($comment = '') {
    global $db, $user;
    $menus = $db->query("SELECT * FROM menus")->results(true);
    $db->insert('menu_versions', [
        'snapshot' => json_encode($menus),
        'timestamp' => date('Y-m-d H:i:s'),
        'user_id' => $user->data()->id,
        'comment' => $comment
    ]);
}
}
?>