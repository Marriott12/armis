<?php
// Lightweight REST API endpoint for analytics (read-only)
// URL example: api.php?report=units&unitID=1,2&rankID=3
require_once '../init.php';
header('Content-Type: application/json');

// RBAC: Only allowed users
if (!in_array($user->data()->permissions, [1,2])) {
    http_response_code(403);
    echo json_encode(['error' => 'Access denied.']);
    exit;
}
function get_filter_values($name) {
    if (isset($_GET[$name])) {
        if (is_array($_GET[$name])) return array_filter($_GET[$name]);
        $v = $_GET[$name];
        if (is_string($v) && strpos($v, ',') !== false) {
            return array_filter(explode(',', $v));
        }
        return $v !== '' ? [$v] : [];
    }
    return [];
}
$db = DB::getInstance();
// Filters
$unitIDs = get_filter_values('unitID');
$rankIDs = get_filter_values('rankID');
$categories = get_filter_values('category');
$courseIDs = get_filter_values('courseID');
$conds = []; $params = [];
if (!empty($unitIDs)) { $conds[] = 's.unitID IN (' . implode(',', array_fill(0, count($unitIDs), '?')) . ')'; $params = array_merge($params, $unitIDs);}
if (!empty($rankIDs)) { $conds[] = 's.rankID IN (' . implode(',', array_fill(0, count($rankIDs), '?')) . ')'; $params = array_merge($params, $rankIDs);}
if (!empty($categories)) { $conds[] = 's.category IN (' . implode(',', array_fill(0, count($categories), '?')) . ')'; $params = array_merge($params, $categories);}
if (!empty($courseIDs)) { $conds[] = 'EXISTS (SELECT 1 FROM staff_courses sc WHERE sc.svcNo = s.svcNo AND sc.courseID IN (' . implode(',', array_fill(0, count($courseIDs), '?')) . '))'; $params = array_merge($params, $courseIDs);}
$where = $conds ? ('WHERE ' . implode(' AND ', $conds)) : '';
$report = $_GET['report'] ?? 'units';
try {
    switch ($report) {
        case 'units':
            $data = $db->query(
                "SELECT u.unitID, u.unitName, COUNT(DISTINCT s.svcNo) AS staffCount
                 FROM staff s LEFT JOIN units u ON s.unitID = u.unitID
                 $where
                 GROUP BY u.unitID, u.unitName
                 ORDER BY staffCount DESC", $params
            )->results();
            break;
        case 'ranks':
            $data = $db->query(
                "SELECT r.rankID, r.rankName, COUNT(DISTINCT s.svcNo) AS staffCount
                 FROM staff s LEFT JOIN ranks r ON s.rankID = r.rankID
                 $where
                 GROUP BY r.rankID, r.rankName
                 ORDER BY r.rankName ASC", $params
            )->results();
            break;
        case 'courses':
            $data = $db->query(
                "SELECT c.courseID, c.courseName, COUNT(DISTINCT s.svcNo) AS staffCount
                 FROM staff s
                 INNER JOIN staff_courses sc ON s.svcNo = sc.svcNo
                 INNER JOIN courses c ON sc.courseID = c.courseID
                 $where
                 GROUP BY c.courseID, c.courseName
                 ORDER BY staffCount DESC", $params
            )->results();
            break;
        case 'data_quality':
            $data = $db->query(
                "SELECT 
                    SUM(CASE WHEN (s.DOB IS NULL OR s.DOB = '') THEN 1 ELSE 0 END) as missingDOB,
                    SUM(CASE WHEN (s.category IS NULL OR s.category = '') THEN 1 ELSE 0 END) as missingCategory
                 FROM staff s $where", $params
            )->first();
            break;
        default:
            http_response_code(400); echo json_encode(['error'=>'Unknown report']); exit;
    }
    echo json_encode(['data'=>$data]);
} catch(Exception $e) {
    http_response_code(500);
    echo json_encode(['error'=>'Server error','details'=>$e->getMessage()]);
}