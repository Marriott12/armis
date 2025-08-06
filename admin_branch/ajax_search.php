<?php
/**
 * AJAX Search Handler for ARMIS Advanced Search
 * Handles search requests, filtering, and export functionality
 */
header('Content-Type: application/json');

if (!isLoggedIn()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Authentication required']);
    exit;
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
    $filters = $_POST['filters'] ?? [];
    $sortBy = $_POST['sort_by'] ?? 'last_name';
    $page = max(1, intval($_POST['page'] ?? 1));
    $pageSize = max(1, intval($_POST['page_size'] ?? 20));

    $whereConditions = [];
    $params = [];
    $types = '';

    if ($query !== '') {
        $whereConditions[] = '(s.first_name LIKE ? OR s.last_name LIKE ? OR s.service_number LIKE ?)';
        $params[] = "%$query%";
        $params[] = "%$query%";
        $params[] = "%$query%";
        $types .= 'sss';
    }

    if (!empty($filters['rank_id'])) {
        $whereConditions[] = 's.rank_id = ?';
        $params[] = $filters['rank_id'];
        $types .= 'i';
    }
    if (!empty($filters['unit_id'])) {
        $whereConditions[] = 's.unit_id = ?';
        $params[] = $filters['unit_id'];
        $types .= 'i';
    }

    $whereClause = '';
    if (!empty($whereConditions)) {
        $whereClause = 'WHERE ' . implode(' AND ', $whereConditions);
    }

    $allowedSortFields = [
        'last_name' => 's.last_name',
        'first_name' => 's.first_name',
        'service_number' => 's.service_number',
        'rank_id' => 'r.id',
        'unit_id' => 'u.id',
        'date_of_birth' => 's.date_of_birth',
        'attestDate' => 's.attestDate',
        'created_at' => 's.dateCreated'
    ];
    $orderBy = $allowedSortFields[$sortBy] ?? 's.last_name';
    $orderClause = "ORDER BY $orderBy ASC";

    $countSql = "
        SELECT COUNT(*)
        FROM staff s
        LEFT JOIN ranks r ON s.rank_id = r.id
        LEFT JOIN units u ON s.unit_id = u.id
        $whereClause
    ";
    $countStmt = $conn->prepare($countSql);
    if (!empty($params)) {
        $countStmt->bind_param($types, ...$params);
    }
    $countStmt->execute();
    $totalResults = $countStmt->get_result()->fetch_row()[0];

    $totalPages = ceil($totalResults / $pageSize);
    $offset = ($page - 1) * $pageSize;

    $sql = "
        SELECT 
            s.id,
            s.service_number,
            s.rank_id,
            s.last_name,
            s.first_name,
            s.NRC,
            s.passport,
            s.gender,
            s.unit_id,
            s.category,
            s.svcStatus,
            s.appt,
            s.subRank,
            s.subWef,
            s.tempRank,
            s.tempWef,
            s.localRank,
            s.localWef,
            s.attestDate,
            s.intake,
            s.DOB,
            s.height,
            s.province,
            s.district,
            s.corps,
            s.bloodGp,
            s.profession,
            s.religion,
            s.village,
            s.trade,
            s.digitalID,
            s.prefix,
            s.marital,
            s.initials,
            s.titles,
            s.nok,
            s.nokNrc,
            s.nokRelat,
            s.nokTel,
            s.altNok,
            s.altNokTel,
            s.altNokNrc,
            s.altNokRelat,
            s.email,
            s.profile_photo,
            s.tel,
            s.unitAtt,
            s.username,
            s.role,
            s.renewDate,
            s.accStatus,
            s.createdBy,
            s.dateCreated,
            s.updated_at,
            s.last_login,
            s.password_changed_at,
            s.date_of_birth,
            s.status,
            s.last_profile_update,
            r.name AS rank_name,
            r.level AS rank_level,
            u.unitName AS unit_name
        FROM staff s
        LEFT JOIN ranks r ON s.rank_id = r.id
        LEFT JOIN units u ON s.unit_id = u.id
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
            'start' => $offset + 1,
            'end' => min($offset + $pageSize, $totalResults)
        ]
    ]);
}

function handleGetFilterOptions() {
    $conn = getMysqliConnection();
    $ranksSql = "SELECT id as rankID, name as rankName, level FROM ranks WHERE level != 0 ORDER BY level";
    $ranksResult = $conn->query($ranksSql);
    $ranks = $ranksResult->fetch_all(MYSQLI_ASSOC);

    $unitsSql = "SELECT id, unitName FROM units ORDER BY unitName";
    $unitsResult = $conn->query($unitsSql);
    $units = $unitsResult->fetch_all(MYSQLI_ASSOC);

    $corpsSql = "SELECT DISTINCT corps FROM staff WHERE corps IS NOT NULL AND corps != '' ORDER BY corps";
    $corpsResult = $conn->query($corpsSql);
    $corps = array_map(function($row) {
        return ['corps' => $row['corps']];
    }, $corpsResult->fetch_all(MYSQLI_ASSOC));

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
                s.id,
                s.service_number,
                s.first_name,
                s.last_name,
                s.email,
                s.svcStatus,
                s.gender,
                s.date_of_birth,
                s.attestDate,
                r.name AS rank_name,
                r.level AS rank_level,
                u.unitName AS unit_name,
                s.corps
            FROM staff s
            LEFT JOIN ranks r ON s.rank_id = r.id
            LEFT JOIN units u ON s.unit_id = u.id
            WHERE s.id IN ($placeholders)
            ORDER BY s.last_name, s.first_name
        ";

        $stmt = $conn->prepare($sql);
        $types = str_repeat('i', count($selectedIds));
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
            $row['service_number'] ?? '',
            $row['first_name'] ?? '',
            $row['last_name'] ?? '',
            $row['rank_name'] ?? '',
            $row['rank_level'] ?? '',
            $row['unit_name'] ?? '',
            $row['gender'] ?? '',
            $row['date_of_birth'] ?? '',
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
            <td>' . htmlspecialchars(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? '')) . '</td>';
        echo '<td>' . htmlspecialchars($row['service_number'] ?? '') . '</td>';
        echo '<td>' . htmlspecialchars($row['rank_name'] ?? '') . '</td>';
        echo '<td>' . htmlspecialchars($row['rank_level'] ?? '') . '</td>';
        echo '<td>' . htmlspecialchars($row['unit_name'] ?? '') . '</td>';
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
    $rankId = $_GET['rank_id'] ?? $_POST['rank_id'] ?? '';
    $unitId = $_GET['unit_id'] ?? $_POST['unit_id'] ?? '';
    $q = trim($_GET['q'] ?? $_POST['q'] ?? '');
    if (empty($rankId)) {
        echo json_encode(['results' => []]);
        return;
    }
    $params = [$rankId];
    $types = 'i';
    $where = 's.rank_id = ?';
    if (!empty($unitId)) {
        $where .= ' AND s.unit_id = ?';
        $params[] = $unitId;
        $types .= 'i';
    }
    if (!empty($q)) {
        $where .= ' AND (s.first_name LIKE ? OR s.last_name LIKE ? OR s.service_number LIKE ?)';
        $params[] = "%$q%";
        $params[] = "%$q%";
        $params[] = "%$q%";
        $types .= 'sss';
    }
    $sql = "SELECT s.id, s.service_number, s.first_name, s.last_name, r.name AS rank_name, r.level AS rank_level, u.unitName AS unit_name FROM staff s LEFT JOIN ranks r ON s.rank_id = r.id LEFT JOIN units u ON s.unit_id = u.id WHERE $where ORDER BY s.last_name, s.first_name LIMIT 50";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    $data = [];
    while ($row = $result->fetch_assoc()) {
        $data[] = [
            'id' => $row['id'],
            'text' => $row['service_number'] . ' - ' . $row['last_name'] . ', ' . $row['first_name'] . ' (' . $row['rank_name'] . ' / ' . $row['unit_name'] . ')',
            'service_number' => $row['service_number'],
            'first_name' => $row['first_name'],
            'last_name' => $row['last_name'],
            'rank_name' => $row['rank_name'],
            'rank_level' => $row['rank_level'],
            'unit_name' => $row['unit_name']
        ];
    }
    echo json_encode(['results' => $data]);
    $stmt->close();
    $conn->close();
}