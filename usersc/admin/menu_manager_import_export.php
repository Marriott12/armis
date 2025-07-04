<?php
// Export
if (isset($_GET['export']) && $_GET['export'] === 'json') {
    $menus = $db->query("SELECT * FROM menus")->results(true);
    header('Content-Type: application/json');
    echo json_encode($menus);
    exit;
}
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $menus = $db->query("SELECT * FROM menus")->results(true);
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="menus.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, array_keys($menus[0]));
    foreach ($menus as $row) fputcsv($out, $row);
    fclose($out);
    exit;
}
// Import
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['menu_import'])) {
    $data = json_decode(file_get_contents($_FILES['menu_import']['tmp_name']), true);
    foreach ($data as $menu) {
        $db->insert('menus', $menu);
    }
    armis_log($user->data()->id, 'menu_import', "Imported menus", ['count' => count($data)]);
    Redirect::to('menu_manager.php');
}
?>