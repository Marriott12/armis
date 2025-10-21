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
    $rankId = $_GET['rank_id'] ?? '';
    $limit = (int)($_GET['limit'] ?? 50);
    
    // Validate limit (allow higher limits for bulk operations like medal assignment)
    if ($limit > 2000) $limit = 2000;  // Maximum 2000 for safety
    if ($limit < 1) $limit = 50;
    
    // Build base SQL query with rank level for proper seniority sorting
    $sql = "SELECT 
                s.service_number,
                CONCAT(
                    COALESCE(r.name, 'No Rank'), ' ',
                    COALESCE(s.first_name, ''), ' ',
                    COALESCE(s.last_name, ''),
                    CASE 
                        WHEN s.service_number IS NOT NULL
                        THEN CONCAT(' (', s.service_number, ')')
                        ELSE ''
                    END
                ) as text,
                s.id,
                s.first_name,
                s.last_name,
                s.rank_id,
                r.name as rank_name,
                r.level as rank_level,
                r.abbreviation as rank_abbr,
                r.category as rank_category,
                u.name as unit_name,
                s.corps,
                s.svcStatus,
                s.attestDate,
                s.subWef,
                s.tempWef,
                s.DOB
            FROM staff s
            LEFT JOIN ranks r ON s.rank_id = r.id
            LEFT JOIN units u ON s.unit_id = u.id
            WHERE s.svcStatus = 'Active'";
    
    $params = [];
    
    // Add medal exclusion filter if specified
    if (!empty($_GET['exclude_medal_id'])) {
        $sql .= " AND s.id NOT IN (
            SELECT staff_id FROM staff_medals WHERE medal_id = :medal_id
        )";
        $params[':medal_id'] = $_GET['exclude_medal_id'];
    }
    
    // Add rank filter if specified
    if (!empty($rankId)) {
        $sql .= " AND s.rank_id = :rank_id";
        $params[':rank_id'] = $rankId;
    }
    
    // Add search filter if not "all"
    if (!empty($query) && $query !== 'all') {
        $sql .= " AND (
            s.first_name LIKE :query OR
            s.last_name LIKE :query OR
            s.service_number LIKE :query OR
            CONCAT(s.first_name, ' ', s.last_name) LIKE :query OR
            r.name LIKE :query
        )";
        $params[':query'] = '%' . $query . '%';
    }
    
    // Add military seniority ordering (same as reports_seniority.php)
    $sql .= " ORDER BY 
        r.level ASC,
        s.subWef ASC,
        s.tempWef ASC,
        s.attestDate ASC,
        s.service_number ASC 
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
            'id' => $row['service_number'],
            'service_number' => $row['service_number'],
            'staff_id' => $row['id'],
            'text' => $row['text'],
            'first_name' => $row['first_name'],
            'last_name' => $row['last_name'],
            'rank_id' => $row['rank_id'],
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