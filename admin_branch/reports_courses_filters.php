<?php
// Advanced AJAX filter chaining backend for reports_courses.php
// Features: multi-select support, RBAC, role-based scoping, caching, audit logging, data quality, error handling
require_once '../init.php';

// --- RBAC: Only permitted users ---
$allowed_roles = [1, 2]; // 1=admin, 2=analytics
if (!in_array($user->data()->permissions, $allowed_roles)) {
    http_response_code(403);
    echo json_encode(['error' => 'Access denied.']);
    error_log("Unauthorized AJAX filter access by user ID: " . $user->data()->id);
    exit;
}

// --- Audit Logging: Log filter AJAX accesses ---
function log_audit($user, $params) {
    $logfile = __DIR__ . '/logs/ajax_filter_audit.log';
    if (!is_dir(dirname($logfile))) mkdir(dirname($logfile), 0777, true);
    $line = date('Y-m-d H:i:s') . ' | UserID:' . $user->data()->id . ' | ' . json_encode($params) . "\n";
    file_put_contents($logfile, $line, FILE_APPEND | LOCK_EX);
}
log_audit($user, $_GET);

// --- Server-side Caching: 2 min (APCu or file) ---
function cache_get($key) {
    if (function_exists('apcu_fetch')) {
        return apcu_fetch($key);
    } else {
        $file = sys_get_temp_dir() . '/filtercache_' . md5($key);
        if (file_exists($file) && (filemtime($file) + 120 > time())) {
            return unserialize(file_get_contents($file));
        }
    }
    return false;
}
function cache_set($key, $value) {
    if (function_exists('apcu_store')) {
        apcu_store($key, $value, 120);
    } else {
        $file = sys_get_temp_dir() . '/filtercache_' . md5($key);
        file_put_contents($file, serialize($value));
    }
}

// --- Parse multi-select filter values ---
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

// --- Build filter params ---
$unitIDs = get_filter_values('unitID');
$rankIDs = get_filter_values('rankID');
$categories = get_filter_values('category');
$courseIDs = get_filter_values('courseID');

// --- Role-based Data Scoping: If not admin, restrict to user's unit ---
if ($user->data()->permissions != 1 && isset($user->data()->unitID) && $user->data()->unitID) {
    if (empty($unitIDs)) {
        $unitIDs[] = $user->data()->unitID;
    }
}

// --- Caching Key ---
$cacheKey = 'filter_opt_' . md5(json_encode([$unitIDs, $rankIDs, $categories, $courseIDs, $user->data()->id]));

// --- Try cache first ---
if ($cached = cache_get($cacheKey)) {
    header('Content-Type: application/json');
    echo $cached;
    exit;
}

$db = DB::getInstance();
$conds = [];
$params = [];
if (!empty($unitIDs)) {
    $placeholders = implode(',', array_fill(0, count($unitIDs), '?'));
    $conds[] = 's.unitID IN (' . $placeholders . ')';
    $params = array_merge($params, $unitIDs);
}
if (!empty($rankIDs)) {
    $placeholders = implode(',', array_fill(0, count($rankIDs), '?'));
    $conds[] = 's.rankID IN (' . $placeholders . ')';
    $params = array_merge($params, $rankIDs);
}
if (!empty($categories)) {
    $placeholders = implode(',', array_fill(0, count($categories), '?'));
    $conds[] = 's.category IN (' . $placeholders . ')';
    $params = array_merge($params, $categories);
}
if (!empty($courseIDs)) {
    $placeholders = implode(',', array_fill(0, count($courseIDs), '?'));
    $conds[] = 'EXISTS (SELECT 1 FROM staff_courses sc WHERE sc.svcNo = s.svcNo AND sc.courseID IN (' . $placeholders . '))';
    $params = array_merge($params, $courseIDs);
}
$where = $conds ? ('WHERE ' . implode(' AND ', $conds)) : '';

try {
    // --- Units available for selection
    $units = $db->query(
        "SELECT DISTINCT u.unitID, u.unitName FROM staff s
         LEFT JOIN units u ON s.unitID = u.unitID
         $where
         ORDER BY u.unitName ASC", $params
    )->results();

    // --- Ranks available for selection
    $ranks = $db->query(
        "SELECT DISTINCT r.rankID, r.rankName FROM staff s
         LEFT JOIN ranks r ON s.rankID = r.rankID
         $where
         ORDER BY r.rankIndex ASC", $params
    )->results();

    // --- Categories available for selection
    $categories_avail = $db->query(
        "SELECT DISTINCT s.category FROM staff s $where ORDER BY s.category ASC", $params
    )->results();
    $categories_avail = array_map(function($row){return $row->category;}, $categories_avail);

    // --- Courses available for selection
    $courses = $db->query(
        "SELECT DISTINCT c.courseID, c.courseName FROM staff s
         INNER JOIN staff_courses sc ON s.svcNo = sc.svcNo
         INNER JOIN courses c ON sc.courseID = c.courseID
         $where
         ORDER BY c.courseName ASC", $params
    )->results();

    // --- Data Quality: e.g. missing DOB, category, etc. ---
    $missingData = $db->query(
        "SELECT 
            SUM(CASE WHEN (s.DOB IS NULL OR s.DOB = '') THEN 1 ELSE 0 END) as missingDOB,
            SUM(CASE WHEN (s.category IS NULL OR s.category = '') THEN 1 ELSE 0 END) as missingCategory
         FROM staff s $where", $params
    )->first();

    // --- Return results ---
    $result = json_encode([
        'units' => $units,
        'ranks' => $ranks,
        'categories' => $categories_avail,
        'courses' => $courses,
        'data_quality' => [
            'missingDOB' => intval($missingData ? $missingData->missingDOB : 0),
            'missingCategory' => intval($missingData ? $missingData->missingCategory : 0)
        ]
    ]);
    header('Content-Type: application/json');
    cache_set($cacheKey, $result);
    echo $result;
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Server error. Please try again later.', 'details' => $e->getMessage()]);
}