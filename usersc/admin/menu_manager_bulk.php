<?php
// Bulk delete/restore actions for menu manager
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_action'])) {
    $ids = $_POST['menu_ids'] ?? [];
    if ($_POST['bulk_action'] === 'delete') {
        foreach ($ids as $id) {
            $db->update('menus', $id, ['deleted' => 1]);
            armis_log($user->data()->id, 'menu_delete', "Bulk deleted menu #$id", ['menu_id' => $id]);
        }
    } elseif ($_POST['bulk_action'] === 'restore') {
        foreach ($ids as $id) {
            $db->update('menus', $id, ['deleted' => 0]);
            armis_log($user->data()->id, 'menu_restore', "Bulk restored menu #$id", ['menu_id' => $id]);
        }
    }
    Redirect::to('menu_manager.php');
}
?>