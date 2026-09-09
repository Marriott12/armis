<?php
/**
 * AJAX Search Handler for ARMIS Advanced Search
 * Handles search requests, filtering, and export functionality
 */
// FIX: isLoggedIn() was called below with no include anywhere in this file
// that defines it - every request fatal-errored immediately with "Call to
// undefined function isLoggedIn()", before ever reaching the search logic.
require_once __DIR__ . '/includes/auth.php';
require_once dirname(__DIR__) . '/shared/database_connection.php';
require_once dirname(__DIR__) . '/shared/rank_levels.php';
require_once __DIR__ . '/includes/settings.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Authentication required']);
    exit;
}

/**
 * Return a mysqli connection instance.
 * This will attempt to include a nearby config.php file if present and use DB_* constants,
 * otherwise it falls back to sensible defaults for a local WAMP environment.
 *
 * Throws Exception on connection failure.
 *
 * Note: Adjust DB_HOST/DB_USER/DB_PASS/DB_NAME in a config file or replace defaults as needed.
 */
function getMysqliConnection() {
    static $conn = null;
    if ($conn instanceof mysqli) {
        return $conn;
    }

    // Try to include common config locations so projects can define DB_HOST/DB_USER/etc.
    $configCandidates = [
        __DIR__ . '/config.php',
        __DIR__ . '/../config.php',
        __DIR__ . '/../../config.php',
    ];
    foreach ($configCandidates as $cfg) {
        if (file_exists($cfg)) {
            include_once $cfg;
            break;
        }
    }

    $dbHost = defined('DB_HOST') ? DB_HOST : 'localhost';
    $dbUser = defined('DB_USER') ? DB_USER : 'root';
    $dbPass = defined('DB_PASS') ? DB_PASS : '';
    $dbName = defined('DB_NAME') ? DB_NAME : 'armis1';

    $conn = new mysqli($dbHost, $dbUser, $dbPass, $dbName);
    if ($conn->connect_errno) {
        throw new Exception('Database connection failed: ' . $conn->connect_error);
    }
    $conn->set_charset('utf8mb4');
    return $conn;
}

try {
    $action = $_REQUEST['action'] ?? '';
    switch ($action) {
        case 'search':
            handleSearch();
            break;
        case 'get_filter_options':
            handleGetFilterOptions();
            break;
        case 'export':
            handleExport();
            break;
        case 'dropdown_by_rank':
            handleDropdownByRank();
            break;
        default:
            throw new Exception('Invalid action');
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

/*
 * Handle search request
 */
function handleSearch() {
    $conn = getMysqliConnection();
    $query = trim($_POST['query'] ?? '');
    $searchType = $_POST['search_type'] ?? 'contains';
    // FIX: the search form previously submitted its filter fields
    // (rankId, unitId, corps, status, gender, marital) as flat,
    // top-level form fields, but this always expected them nested
    // under a 'filters' key — so $_POST['filters'] was always empty
    // and every filter dropdown on the page silently did nothing.
    // js/advanced-search.js now sends 'filters' as a JSON string
    // instead, matching this.
    $filters = json_decode($_POST['filters'] ?? '{}', true) ?: [];
    $sortBy = $_POST['sort_by'] ?? 'lname';
    $page = max(1, intval($_POST['page'] ?? 1));
    $pageSize = max(1, intval($_POST['page_size'] ?? getAdminBranchSetting('staff_list_page_size', '20')));

    $whereConditions = [];
    $params = [];
    $types = '';

    if ($query !== '') {
        switch ($searchType) {
            case 'exact':
                $pattern = $query;
                break;
            case 'starts_with':
                $pattern = $query . '%';
                break;
            case 'ends_with':
                $pattern = '%' . $query;
                break;
            case 'contains':
            default:
                $pattern = '%' . $query . '%';
                break;
        }

        $whereConditions[] = '(s.fName LIKE ? OR s.lName LIKE ? OR s.svcNo LIKE ? OR COALESCE(s.officialEmail, "") LIKE ? OR COALESCE(s.NRC, "") LIKE ?)';
        $params[] = $pattern;
        $params[] = $pattern;
        $params[] = $pattern;
        $params[] = $pattern;
        $params[] = $pattern;
        $types .= 'sssss';
    }

    if (!empty($filters['rankId'])) {
        $whereConditions[] = 's.rankId = ?';
        $params[] = $filters['rankId'];
        $types .= 's';
    }
    if (!empty($filters['unitId'])) {
        $whereConditions[] = 's.unitId = ?';
        $params[] = $filters['unitId'];
        $types .= 's';
    }
    // These four filters existed in the UI (advanced_search.php) with
    // no corresponding handling here at all — dropdowns that looked
    // functional but were silently ignored regardless of the
    // transport bug fixed above.
    if (!empty($filters['corps'])) {
        $whereConditions[] = 's.corps = ?';
        $params[] = $filters['corps'];
        $types .= 's';
    }
    if (!empty($filters['status'])) {
        $whereConditions[] = 's.svcStatus = ?';
        $params[] = $filters['status'];
        $types .= 's';
    }
    if (!empty($filters['gender'])) {
        $whereConditions[] = 's.gender = ?';
        $params[] = $filters['gender'];
        $types .= 's';
    }
    if (!empty($filters['marital'])) {
        $whereConditions[] = 's.marital = ?';
        $params[] = $filters['marital'];
        $types .= 's';
    }
    // Category filter (Officer / NCO / Civilian Employee), derived from
    // the actual rank level via the same shared logic reports_seniority.php
    // uses — not from staff.category, which is a separately-stored column
    // that could drift out of sync with a staff member's current rank.
    if (!empty($filters['category'])) {
        $categoryMap = ['officers' => 'Officer', 'ncos' => 'NCO', 'ce' => 'Civilian Employee'];
        $categoryName = $categoryMap[$filters['category']] ?? null;
        if ($categoryName) {
            $whereConditions[] = getRankCategorySQL($categoryName, 'r');
        }
    }

    $ageMin = trim((string)($_POST['age_min'] ?? ''));
    $ageMax = trim((string)($_POST['age_max'] ?? ''));
    if ($ageMin !== '' && ctype_digit($ageMin)) {
        $whereConditions[] = 'TIMESTAMPDIFF(YEAR, s.DOB, CURDATE()) >= ?';
        $params[] = (int)$ageMin;
        $types .= 'i';
    }
    if ($ageMax !== '' && ctype_digit($ageMax)) {
        $whereConditions[] = 'TIMESTAMPDIFF(YEAR, s.DOB, CURDATE()) <= ?';
        $params[] = (int)$ageMax;
        $types .= 'i';
    }

    $serviceMin = trim((string)($_POST['service_min'] ?? ''));
    $serviceMax = trim((string)($_POST['service_max'] ?? ''));
    if ($serviceMin !== '' && ctype_digit($serviceMin)) {
        $whereConditions[] = 'TIMESTAMPDIFF(YEAR, s.attestDate, CURDATE()) >= ?';
        $params[] = (int)$serviceMin;
        $types .= 'i';
    }
    if ($serviceMax !== '' && ctype_digit($serviceMax)) {
        $whereConditions[] = 'TIMESTAMPDIFF(YEAR, s.attestDate, CURDATE()) <= ?';
        $params[] = (int)$serviceMax;
        $types .= 'i';
    }

    $enlistmentFrom = trim((string)($_POST['enlistment_from'] ?? ''));
    $enlistmentTo = trim((string)($_POST['enlistment_to'] ?? ''));
    if ($enlistmentFrom !== '') {
        $whereConditions[] = 's.attestDate >= ?';
        $params[] = $enlistmentFrom;
        $types .= 's';
    }
    if ($enlistmentTo !== '') {
        $whereConditions[] = 's.attestDate <= ?';
        $params[] = $enlistmentTo;
        $types .= 's';
    }

    $birthFrom = trim((string)($_POST['birth_from'] ?? ''));
    $birthTo = trim((string)($_POST['birth_to'] ?? ''));
    if ($birthFrom !== '') {
        $whereConditions[] = 's.DOB >= ?';
        $params[] = $birthFrom;
        $types .= 's';
    }
    if ($birthTo !== '') {
        $whereConditions[] = 's.DOB <= ?';
        $params[] = $birthTo;
        $types .= 's';
    }

    $whereClause = '';
    if (!empty($whereConditions)) {
        $whereClause = 'WHERE ' . implode(' AND ', $whereConditions);
    }

    $allowedSortFields = [
        'lname' => 's.lName',
        'lName' => 's.lName',
        'fname' => 's.fName',
        'fName' => 's.fName',
        'svcNo' => 's.svcNo',
        'rankID' => 'r.rankIndex',
        'rankId' => 'r.rankIndex',
        'unitId' => 'u.unitId',
        'dateOfBirth' => 's.DOB',
        'DOB' => 's.DOB',
        'enlistmentDate' => 's.attestDate',
        'attestDate' => 's.attestDate',
        'createdAt' => 's.dateCreated'
    ];
    $orderBy = $allowedSortFields[$sortBy] ?? 's.lName';
    $orderClause = "ORDER BY $orderBy ASC";

    $countSql = "
        SELECT COUNT(*)
        FROM staff s
        LEFT JOIN `rank` r ON s.rankId = r.rankId
        LEFT JOIN unit u ON s.unitId = u.unitId
        $whereClause
    ";
    $countStmt = $conn->prepare($countSql);
    if (!empty($params)) {
        $countStmt->bind_param($types, ...$params);
    }
    $countStmt->execute();
    $totalResults = $countStmt->get_result()->fetch_row()[0];

    $totalPages = max(1, (int)ceil($totalResults / $pageSize));
    $offset = ($page - 1) * $pageSize;

    $sql = "
        SELECT
            s.svcNo AS id,
            s.svcNo,
            s.fName AS fname,
            s.lName AS lname,
            s.rankId,
            s.unitId,
            s.svcStatus,
            s.gender,
            s.marital,
            s.DOB,
            s.attestDate,
            s.NRC,
            s.corps,
            s.trade,
            s.officialEmail AS email,
            s.profilePhoto,
            " . getRankCategoryCaseSQL('r') . " AS rankCategory,
            r.rankId AS rankName,
            r.rankIndex AS rankIndex,
            u.unitId AS unitName
        FROM staff s
        LEFT JOIN `rank` r ON s.rankId = r.rankId
        LEFT JOIN unit u ON s.unitId = u.unitId
        $whereClause
        $orderClause
        LIMIT ? OFFSET ?
    ";
    $stmt = $conn->prepare($sql);
    $params2 = $params;
    $types2 = $types;
    $params2[] = $pageSize;
    $params2[] = $offset;
    $types2 .= 'ii';
    $stmt->bind_param($types2, ...$params2);
    $stmt->execute();
    $result = $stmt->get_result();
    $results = $result->fetch_all(MYSQLI_ASSOC);

    logSearch($_SESSION['user_id'] ?? 0, $query, $_POST, count($results));

    echo json_encode([
        'success' => true,
        'results' => $results,
        'pagination' => [
            'current_page' => $page,
            'total_pages' => $totalPages,
            'total' => $totalResults,
            'page_size' => $pageSize,
            'start' => $totalResults > 0 ? $offset + 1 : 0,
            'end' => min($offset + $pageSize, $totalResults)
        ]
    ]);
}

function handleGetFilterOptions() {
    $conn = getMysqliConnection();
    // Use `rank` table which stores rank identifiers in `rankId`
    $ranksSql = "SELECT rankId as rankID, rankId as rankName, rankIndex FROM `rank` WHERE rankIndex IS NOT NULL ORDER BY rankIndex ASC";
    $ranksResult = $conn->query($ranksSql);
    $ranks = $ranksResult ? $ranksResult->fetch_all(MYSQLI_ASSOC) : [];

    $unitsSql = "SELECT unitId as id, unitId as unitName FROM unit ORDER BY unitId ASC";
    $unitsResult = $conn->query($unitsSql);
    $units = $unitsResult ? $unitsResult->fetch_all(MYSQLI_ASSOC) : [];

    $corpsSql = "SELECT DISTINCT corps FROM staff WHERE corps IS NOT NULL AND corps != '' ORDER BY corps";
    $corpsResult = $conn->query($corpsSql);
    $corpsRows = $corpsResult ? $corpsResult->fetch_all(MYSQLI_ASSOC) : [];
    $corps = array_map(function($row) {
        return ['corps' => $row['corps']];
    }, $corpsRows);

    echo json_encode([
        'success' => true,
        'ranks' => $ranks,
        'units' => $units,
        'corps' => $corps
    ]);
}

function handleExport() {
    $format = $_POST['format'] ?? 'excel';
    $selectedIds = json_decode($_POST['selected_ids'] ?? '[]', true);

    if (empty($selectedIds)) {
        $_POST['page_size'] = 10000;
        ob_start();
        handleSearch();
        $searchData = json_decode(ob_get_clean(), true);

        if (!$searchData['success']) {
            throw new Exception('Failed to get search results for export');
        }

        $results = $searchData['results'];
    } else {
        $conn = getMysqliConnection();
        $placeholders = str_repeat('?,', count($selectedIds) - 1) . '?';

        $sql = "
            SELECT 
                s.svcNo AS id,
                s.svcNo,
                s.fName,
                s.lName,
                s.officialEmail AS email,
                s.svcStatus,
                s.gender,
                s.DOB,
                s.attestDate,
                r.rankId AS rank_name,
                r.rankIndex AS rank_level,
                u.unitId AS unit_name,
                s.corps
            FROM staff s
            LEFT JOIN `rank` r ON s.rankId = r.rankId
            LEFT JOIN unit u ON s.unitId = u.unitId
            WHERE s.svcNo IN ($placeholders)
            ORDER BY s.lName, s.fName
        ";

        $stmt = $conn->prepare($sql);
        $types = str_repeat('s', count($selectedIds));
        $stmt->bind_param($types, ...$selectedIds);
        $stmt->execute();
        $result = $stmt->get_result();
        $results = $result->fetch_all(MYSQLI_ASSOC);
    }

    switch ($format) {
        case 'excel':
            exportToExcel($results);
            break;
        case 'csv':
            exportToCSV($results);
            break;
        case 'pdf':
            exportToPDF($results);
            break;
        default:
            throw new Exception('Invalid export format');
    }
}

function exportToExcel($results) {
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="staff_export_' . date('Y-m-d') . '.xlsx"');
    exportToCSV($results, false);
}

function exportToCSV($results, $setHeaders = true) {
    if ($setHeaders) {
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="staff_export_' . date('Y-m-d') . '.csv"');
    }

    $output = fopen('php://output', 'w');
    $headers = [
        'ID', 'Service Number', 'First Name', 'Last Name', 'Rank', 'Rank Level', 'Unit', 'Gender', 'DOB', 'Status', 'Email', 'Corps'
    ];
    fputcsv($output, $headers);

    foreach ($results as $row) {
        $csvRow = [
            $row['id'] ?? '',
            $row['svcNo'] ?? '',
            $row['fName'] ?? ($row['fname'] ?? ''),
            $row['lName'] ?? ($row['lname'] ?? ''),
            $row['rank_name'] ?? ($row['rankName'] ?? ''),
            $row['rank_level'] ?? ($row['rankIndex'] ?? ''),
            $row['unit_name'] ?? ($row['unitName'] ?? ''),
            $row['gender'] ?? '',
            $row['DOB'] ?? '',
            $row['svcStatus'] ?? '',
            $row['email'] ?? '',
            $row['corps'] ?? '',
        ];
        fputcsv($output, $csvRow);
    }
    fclose($output);
}

function exportToPDF($results) {
    header('Content-Type: text/html');
    header('Content-Disposition: attachment; filename="staff_export_' . date('Y-m-d') . '.html"');
    echo '<!DOCTYPE html>
    <html>
    <head>
        <title>Staff Export - ' . date('Y-m-d') . '</title>
        <style>
            body { font-family: Arial, sans-serif; font-size: 12px; }
            table { width: 100%; border-collapse: collapse; }
            th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
            th { background-color: #f2f2f2; }
        </style>
    </head>
    <body>
        <h1>ARMIS Staff Export</h1>
        <p>Generated on: ' . date('Y-m-d H:i:s') . '</p>
        <table>
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Service Number</th>
                    <th>Rank</th>
                    <th>Rank Level</th>
                    <th>Unit</th>
                    <th>Status</th>
                    <th>Email</th>
                    <th>Corps</th>
                </tr>
            </thead>
            <tbody>';

    foreach ($results as $row) {
        echo '<tr>
            <td>' . htmlspecialchars(($row['fName'] ?? ($row['fname'] ?? '')) . ' ' . ($row['lName'] ?? ($row['lname'] ?? ''))) . '</td>';
        echo '<td>' . htmlspecialchars($row['svcNo'] ?? '') . '</td>';
        echo '<td>' . htmlspecialchars($row['rank_name'] ?? ($row['rankName'] ?? '')) . '</td>';
        echo '<td>' . htmlspecialchars($row['rank_level'] ?? ($row['rankIndex'] ?? '')) . '</td>';
        echo '<td>' . htmlspecialchars($row['unit_name'] ?? ($row['unitName'] ?? '')) . '</td>';
        echo '<td>' . htmlspecialchars($row['svcStatus'] ?? '') . '</td>';
        echo '<td>' . htmlspecialchars($row['email'] ?? '') . '</td>';
        echo '<td>' . htmlspecialchars($row['corps'] ?? '') . '</td>';
        echo '</tr>';
    }

    echo '</tbody>
        </table>
    </body>
    </html>';
}

function logSearch($userId, $query, $filters, $resultCount) {
    try {
        $conn = getMysqliConnection();
        $sql = "INSERT INTO search_history (user_id, search_query, search_filters, results_count) VALUES (?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $filtersJson = json_encode($filters);
        $stmt->bind_param('issi', $userId, $query, $filtersJson, $resultCount);
        $stmt->execute();
    } catch (Exception $e) {
        error_log("Failed to log search: " . $e->getMessage());
    }
}

function handleDropdownByRank() {
    $conn = getMysqliConnection();
    $rankId = $_GET['rankId'] ?? $_POST['rankId'] ?? $_GET['rank_id'] ?? $_POST['rank_id'] ?? '';
    $unitId = $_GET['unitId'] ?? $_POST['unitId'] ?? $_GET['unit_id'] ?? $_POST['unit_id'] ?? '';
    $q = trim($_GET['q'] ?? $_POST['q'] ?? '');
    if (empty($rankId)) {
        echo json_encode(['results' => []]);
        return;
    }
    $params = [$rankId];
    $types = 's';
    $where = 's.rankId = ?';
    if (!empty($unitId)) {
        $where .= ' AND s.unitId = ?';
        $params[] = $unitId;
        $types .= 's';
    }
    if (!empty($q)) {
        $where .= ' AND (s.fName LIKE ? OR s.lName LIKE ? OR s.svcNo LIKE ?)';
        $params[] = "%$q%";
        $params[] = "%$q%";
        $params[] = "%$q%";
        $types .= 'sss';
    }
    // Use canonical rank table and safe COALESCE for rank display
    $sql = "SELECT s.svcNo AS id, s.svcNo, s.fName, s.lName, r.rankId AS rank_name, r.rankIndex AS rank_level, u.unitId AS unit_name FROM staff s LEFT JOIN `rank` r ON s.rankId = r.rankId LEFT JOIN unit u ON s.unitId = u.unitId WHERE $where ORDER BY s.lName, s.fName LIMIT 50";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    $data = [];
    while ($row = $result->fetch_assoc()) {
        $data[] = [
            'id' => $row['id'],
            'text' => $row['svcNo'] . ' - ' . $row['lName'] . ', ' . $row['fName'] . ' (' . $row['rank_name'] . ' / ' . $row['unit_name'] . ')',
            'svcNo' => $row['svcNo'],
            'fName' => $row['fName'],
            'lName' => $row['lName'],
            'rank_name' => $row['rank_name'],
            'rank_level' => $row['rank_level'],
            'unit_name' => $row['unit_name']
        ];
    }
    echo json_encode(['results' => $data]);
    $stmt->close();
    $conn->close();
}