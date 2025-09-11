<?php
// snapshot_drilldown.php
define('ARMIS_ADMIN_BRANCH', true);
require_once __DIR__ . '/dashboard_service.php';
require_once dirname(__DIR__, 2) . '/shared/database_connection.php';
header('Content-Type: application/json');

$pdo = getDbConnection();
$dashboardService = new DashboardService($pdo);

$category = isset($_GET['category']) ? strtolower(trim($_GET['category'])) : '';

// Prepare result structure
$result = [
    'ranks' => [
        'male' => [],
        'female' => []
    ]
];

try {
    $stmt = $pdo->prepare("SELECT r.name as rank, s.gender, COUNT(*) as count
        FROM staff s
        LEFT JOIN ranks r ON s.rankID = r.id
        WHERE ((LOWER(TRIM(s.category)) = :cat AND s.category IS NOT NULL) OR (LOWER(TRIM(s.svcStatus)) = 'retired' AND :cat = 'retired'))
        GROUP BY r.name, s.gender");
    $stmt->execute(['cat' => $category]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $hasData = false;
    foreach ($rows as $row) {
        $gender = strtolower(trim($row['gender']));
        $rank = $row['rank'] ? $row['rank'] : 'Unknown';
        $count = (int)$row['count'];
        if ($count > 0) $hasData = true;
        if ($gender === 'male') {
            $result['ranks']['male'][] = ['rank' => $rank, 'count' => $count];
        } elseif ($gender === 'female') {
            $result['ranks']['female'][] = ['rank' => $rank, 'count' => $count];
        }
    }
    // If no data, try fallback for unknown ranks
    if (!$hasData) {
        $stmt2 = $pdo->prepare("SELECT gender, COUNT(*) as count FROM staff WHERE (LOWER(TRIM(category)) = :cat OR (LOWER(TRIM(svcStatus)) = 'retired' AND :cat = 'retired')) GROUP BY gender");
        $stmt2->execute(['cat' => $category]);
        $rows2 = $stmt2->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows2 as $row) {
            $gender = strtolower(trim($row['gender']));
            $count = (int)$row['count'];
            if ($gender === 'male') {
                $result['ranks']['male'][] = ['rank' => 'Unknown', 'count' => $count];
            } elseif ($gender === 'female') {
                $result['ranks']['female'][] = ['rank' => 'Unknown', 'count' => $count];
            }
        }
    }
    echo json_encode($result);
} catch (Exception $e) {
    echo json_encode(['error' => 'Failed to load rank breakdown']);
}
