<?php
require_once '../init.php';
header('Content-Type: application/json');

$q = isset($_GET['q']) ? trim($_GET['q']) : '';
$rankID = isset($_GET['rankID']) ? trim($_GET['rankID']) : '';

$db = DB::getInstance();
$data = [];

// If neither q nor rankID is provided, return empty
if ($q === '' && $rankID === '') {
    echo json_encode([]);
    exit;
}

// Excluded ranks (consistent everywhere)
$excludedRanks = ['Officer Cadet', 'Recruit', 'Mister', 'Miss'];
$excludedRankIds = [];
$ranks = $db->query("SELECT rankID, rankName, rankIndex FROM ranks")->results();
foreach ($ranks as $r) {
    if (in_array($r->rankName, $excludedRanks)) {
        $excludedRankIds[] = $r->rankID;
    }
}

// Compose WHERE and params
$where = [];
$params = [];

// If rankID is set, apply range logic (Officer/NCO)
if ($rankID !== '') {
    $currentRank = $db->query("SELECT * FROM ranks WHERE rankID = ?", [$rankID])->first();
    if ($currentRank) {
        // Determine Officer/NCO and valid range
        if ($currentRank->rankIndex >= 1 && $currentRank->rankIndex <= 13) {
            $validRange = range(1, 13);
        } elseif ($currentRank->rankIndex >= 15 && $currentRank->rankIndex <= 26) {
            $validRange = range(15, 26);
        } else {
            // Not a valid range, return empty
            echo json_encode([]);
            exit;
        }
        $where[] = "s.rankID = ?";
        $params[] = $rankID;
        $where[] = "r.rankIndex IN (" . implode(',', $validRange) . ")";
    } else {
        echo json_encode([]);
        exit;
    }
    if (count($excludedRankIds)) {
        $where[] = "s.rankID NOT IN (" . implode(',', $excludedRankIds) . ")";
    }
}

// Always support search by q (svcNo, fname, lname)
if ($q !== '') {
    $where[] = "(s.svcNo LIKE ? OR s.fname LIKE ? OR s.lname LIKE ?)";
    $params[] = "%$q%";
    $params[] = "%$q%";
    $params[] = "%$q%";
}

if (empty($where)) {
    // No filter, return empty for safety
    echo json_encode([]);
    exit;
}

$sql = "
    SELECT s.svcNo, s.fname, s.lname, r.rankIndex, r.rankName
    FROM staff s
    JOIN ranks r ON s.rankID = r.rankID
    WHERE " . implode(" AND ", $where) . "
    ORDER BY r.rankIndex ASC, s.lname ASC, s.fname ASC
    LIMIT 20
";

$results = $db->query($sql, $params)->results();

foreach ($results as $staff) {
    $data[] = [
        'id' => $staff->svcNo,
        'text' => $staff->svcNo . ' - ' . $staff->rankName . ' ' . $staff->lname . ' ' . $staff->fname
    ];
}

echo json_encode($data);