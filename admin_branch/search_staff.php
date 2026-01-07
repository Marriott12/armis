<?php
/**
 * Staff Search AJAX Endpoint
 * 
 * Provides staff search functionality for dropdowns and forms
 */

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Start session and check authentication
session_start();

// Include required files
require_once __DIR__ . '/includes/auth.php';
require_once dirname(__DIR__) . '/shared/database_connection.php';
require_once dirname(__DIR__) . '/shared/rank_levels.php';

// Verify user is logged in
requireAuth();

// Set JSON header

/**
 * Staff Search AJAX Endpoint
 * 
 * Provides staff search functionality for dropdowns and forms
 */

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Start session and check authentication
session_start();

// Include required files
require_once __DIR__ . '/includes/auth.php';
require_once dirname(__DIR__) . '/shared/database_connection.php';

// Verify user is logged in
requireAuth();

// Set JSON header
header('Content-Type: application/json');

try {
    // Get database connection
    $pdo = getDbConnection();
    
    // Get parameters
    $query = $_GET['q'] ?? '';
    $rankId = $_GET['rankId'] ?? '';
    $limit = (int)($_GET['limit'] ?? 50);
    
    // Validate limit (allow higher limits for bulk operations like medal assignment)
    if ($limit > 2000) $limit = 2000;  // Maximum 2000 for safety
    if ($limit < 1) $limit = 50;
    
    // Build base SQL query with rank level for proper seniority sorting
    $sql = "SELECT 
                s.svcNo,
                CONCAT(
                    COALESCE(r.rankId, 'No Rank'), ' ',
                    COALESCE(s.fName, ''), ' ',
                    COALESCE(s.lName, ''),
                    CASE 
                        WHEN s.svcNo IS NOT NULL
                        THEN CONCAT(' (', s.svcNo, ')')
                        ELSE ''
                    END
                ) as text,
                s.id,
                s.fName,
                s.lName,
                s.rankId,
                r.rankId as rank_name,
                r.level as rank_level,
                r.rankId as rank_abbr,
                " . getRankCategoryCaseSQL('r') . " as rank_category,
                u.code as unit_name,
                s.corpsId as corps,
                s.svcStatus,
                s.attestDate,
                s.subWef,
                s.tempWef,
                s.DOB
            FROM staff s
            LEFT JOIN `rank` r ON s.rankId = r.rankId
            LEFT JOIN `unit` u ON s.unitId = u.unitId
            WHERE s.svcStatus = 'Active'";
    
    $params = [];
    
    // Add medal exclusion filter if specified
    if (!empty($_GET['exclude_medal_id'])) {
        $sql .= " AND s.id NOT IN (
            SELECT svcNo FROM staff_medals WHERE medal_id = :medal_id
        )";
        $params[':medal_id'] = $_GET['exclude_medal_id'];
    }
    
    // Add rank filter if specified
    if (!empty($rankId)) {
        $sql .= " AND s.rankId = :rankId";
        $params[':rankId'] = $rankId;
    }
    
    // Add search filter if not "all"
    if (!empty($query) && $query !== 'all') {
        $sql .= " AND (
            s.fName LIKE :query OR
            s.lName LIKE :query OR
            s.svcNo LIKE :query OR
            CONCAT(s.fName, ' ', s.lName) LIKE :query OR
            r.rankId LIKE :query
        )";
        $params[':query'] = '%' . $query . '%';
    }
    
    // Add military seniority ordering (same as reports_seniority.php)
    $sql .= " ORDER BY 
        r.level ASC,
        s.subWef ASC,
        s.tempWef ASC,
        s.attestDate ASC,
        s.svcNo ASC 
        LIMIT :limit";
    
    // Prepare and execute query
    $stmt = $pdo->prepare($sql);
    
    // Bind parameters
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    
    $stmt->execute();
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Format results for Select2 or general use
    $formatted = [];
    foreach ($results as $row) {
        $formatted[] = [
            'id' => $row['svcNo'],
            'svcNo' => $row['svcNo'],
            'text' => $row['text'],
            'fName' => $row['fName'],
            'lName' => $row['lName'],
            'rankId' => $row['rankId'],
            'rank_name' => $row['rank_name'],
            'rank_level' => $row['rank_level'],
            'rank_abbr' => $row['rank_abbr'],
            'rank_category' => $row['rank_category'],
            'unit_name' => $row['unit_name'],
            'corps' => $row['corps'],
            'status' => $row['svcStatus'],
            'attestDate' => $row['attestDate'],
            'subWef' => $row['subWef'],
            'tempWef' => $row['tempWef'],
            'DOB' => $row['DOB']
        ];
    }
    
    // Return JSON response
    echo json_encode($formatted);
    
} catch (PDOException $e) {
    // Database error
    error_log("Staff search database error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'error' => 'Database error occurred',
        'message' => 'Unable to search staff members',
        'debug' => $e->getMessage()
    ]);
    
} catch (Exception $e) {
    // General error
    error_log("Staff search error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'error' => 'Search error occurred',
        'message' => 'Unable to complete search request',
        'debug' => $e->getMessage()
    ]);
}
?>