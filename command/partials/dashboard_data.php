<?php
define('ML_FORECAST_API', 'http://127.0.0.1:5000/forecast'); // Python ML microservice

function get_filters() {
    return [
        'category' => $_GET['category'] ?? '',
        'province' => $_GET['province'] ?? '',
        'unitID'   => $_GET['unitID'] ?? '',
    ];
}
function getDashboardData($db, $filters = []) {
    $where = [];
    $params = [];
    if (!empty($filters['category'])) { $where[] = 'category = ?'; $params[] = $filters['category']; }
    if (!empty($filters['province'])) { $where[] = 'province = ?'; $params[] = $filters['province']; }
    if (!empty($filters['unitID']))   { $where[] = 'unitID = ?'; $params[] = $filters['unitID']; }
    $whereSQL = $where ? 'WHERE ' . implode(' AND ', $where) : '';
    $totalUsers     = $db->query("SELECT COUNT(*) as total FROM Staff $whereSQL", $params)->first()->total ?? 0;
    $totalOfficers  = $db->query("SELECT COUNT(*) as total FROM Staff $whereSQL AND category = 'Officer'", $params)->first()->total ?? 0;
    $totalNCOs      = $db->query("SELECT COUNT(*) as total FROM Staff $whereSQL AND category = 'Non-Commissioned Officer'", $params)->first()->total ?? 0;
    $totalRecruits  = $db->query("SELECT COUNT(*) as total FROM Staff $whereSQL AND category = 'Recruit'", $params)->first()->total ?? 0;
    $totalCivilians = $db->query("SELECT COUNT(*) as total FROM Staff $whereSQL AND category = 'Civilian Employee'", $params)->first()->total ?? 0;
    $totalMales     = $db->query("SELECT COUNT(*) as total FROM Staff $whereSQL AND gender = 'Male'", $params)->first()->total ?? 0;
    $totalFemales   = $db->query("SELECT COUNT(*) as total FROM Staff $whereSQL AND gender = 'Female'", $params)->first()->total ?? 0;
    $totalUnits     = $db->query("SELECT COUNT(DISTINCT unitID) as total FROM Staff $whereSQL", $params)->first()->total ?? 0;
    $totalCourses   = $db->query("SELECT COUNT(DISTINCT courseName) as total FROM Courses")->first()->total ?? 0;
    $totalOps       = $db->query("SELECT COUNT(*) as total FROM Operations")->first()->total ?? 0;
    $totalCorps     = $db->query("SELECT COUNT(DISTINCT corps) as total FROM Staff $whereSQL", $params)->first()->total ?? 0;

    $genderByCategory = $db->query("
        SELECT category, SUM(gender = 'Male') as males, SUM(gender = 'Female') as females, COUNT(*) as total 
        FROM Staff $whereSQL GROUP BY category", $params)->results();
    $staffByRank = $db->query("SELECT rank, COUNT(*) as total FROM Staff $whereSQL GROUP BY rank ORDER BY total DESC", $params)->results();
    $staffByUnit = $db->query("SELECT unitID, COUNT(*) as total FROM Staff $whereSQL GROUP BY unitID ORDER BY total DESC", $params)->results();
    $staffByProvince = $db->query("SELECT province, COUNT(*) as total FROM Staff $whereSQL GROUP BY province ORDER BY total DESC", $params)->results();
    $staffByCourse = $db->query("SELECT c.courseName, COUNT(sc.svcNo) as total 
        FROM StaffCourses sc JOIN Courses c ON sc.courseID = c.id "
        .($whereSQL ? "JOIN Staff s ON s.svcNo = sc.svcNo $whereSQL" : "")
        ." GROUP BY c.courseName ORDER BY total DESC", $params)->results();
    $opsByType = $db->query("SELECT opType, COUNT(*) as total FROM Operations GROUP BY opType")->results();

    $chartData = function($arr, $label, $data) {
        $labels = []; $vals = [];
        foreach($arr as $row) { $labels[] = $row->$label; $vals[] = (int)$row->$data; }
        return ['labels' => $labels, 'data' => $vals];
    };
    $genderCatLabels = []; $genderCatMale = []; $genderCatFemale = [];
    foreach($genderByCategory as $row) {
        $genderCatLabels[] = $row->category;
        $genderCatMale[] = (int)$row->males;
        $genderCatFemale[] = (int)$row->females;
    }
    $rankChart = $chartData($staffByRank, 'rank', 'total');
    $unitChart = $chartData($staffByUnit, 'unitID', 'total');
    $provinceChart = $chartData($staffByProvince, 'province', 'total');
    $courseChart = $chartData($staffByCourse, 'courseName', 'total');
    $opsTypeChart = $chartData($opsByType, 'opType', 'total');

    $monthly = $db->query("SELECT DATE_FORMAT(created_at, '%Y-%m') as ym, COUNT(*) as total FROM Staff "
        .($whereSQL ? $whereSQL.' AND ' : ' WHERE ')." created_at IS NOT NULL GROUP BY ym ORDER BY ym ASC", $params)->results();
    $forecastLabels = []; $forecastVals = [];
    foreach($monthly as $m) { $forecastLabels[] = $m->ym; $forecastVals[] = (int)$m->total; }
    return [
        'kpis' => [
            'totalUsers' => $totalUsers, 'totalOfficers' => $totalOfficers, 'totalNCOs' => $totalNCOs,
            'totalRecruits' => $totalRecruits, 'totalCivilians' => $totalCivilians,
            'totalMales' => $totalMales, 'totalFemales' => $totalFemales,
            'totalUnits' => $totalUnits, 'totalCourses' => $totalCourses, 'totalOps' => $totalOps, 'totalCorps' => $totalCorps,
        ],
        'gender' => ['labels' => $genderCatLabels, 'male' => $genderCatMale, 'female' => $genderCatFemale],
        'rank' => $rankChart,
        'unit' => $unitChart,
        'province' => $provinceChart,
        'course' => $courseChart,
        'opsType' => $opsTypeChart,
        'forecastLabels' => $forecastLabels,
        'forecastVals' => $forecastVals,
    ];
}

// AJAX dashboard JSON
if(isset($_GET['ajax']) && $_GET['ajax'] == 1) {
    $filters = get_filters();
    echo json_encode(getDashboardData($db, $filters));
    exit;
}

// AJAX AI Forecast
if(isset($_GET['ajax']) && $_GET['ajax'] == 'forecast') {
    $labels = $_POST['labels'] ?? [];
    $vals = $_POST['vals'] ?? [];
    $periods = $_POST['periods'] ?? 6;
    // Call Python ML microservice
    $ch = curl_init(ML_FORECAST_API);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query(['labels'=>json_encode($labels),'series'=>json_encode($vals),'periods'=>$periods]));
    curl_setopt($ch, CURLOPT_POST, 1);
    $result = curl_exec($ch);
    curl_close($ch);
    header('Content-Type: application/json');
    echo $result;
    exit;
}

// CSV Export
if(isset($_GET['export']) && $_GET['export'] == 'csv') {
    $filters = get_filters();
    $data = getDashboardData($db, $filters)['unit'];
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment;filename=staff_by_unit.csv');
    echo "Unit,Total\n";
    foreach ($data['labels'] as $i=>$unit) {
        echo "\"$unit\",{$data['data'][$i]}\n";
    }
    exit;
}