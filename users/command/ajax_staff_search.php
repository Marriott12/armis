<?php
// filepath: c:\wamp64\www\Armis\users\command\ajax_staff_search.php
require_once '../init.php';

$search = trim($_GET['search'] ?? '');
$params = [];
$where = '';

if ($search !== '') {
    $where = "WHERE s.svcNo LIKE ? OR s.fname LIKE ? OR s.lname LIKE ?";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$db = DB::getInstance();
$staffList = $db->query(
    "SELECT s.svcNo, s.fname, s.lname, s.category,
            IFNULL(r.rankAbb, 'Unknown') as rankAbb, IFNULL(r.rankName, 'Unknown') as rankName,
            IFNULL(u.unitName, 'Unknown') as unitName
     FROM staff s
     LEFT JOIN ranks r ON s.rankID = r.rankID
     LEFT JOIN units u ON s.unitID = u.unitID
     $where
     ORDER BY s.lname ASC
     LIMIT 100",
    $params
)->results();

header('Content-Type: application/json');
echo json_encode($staffList);