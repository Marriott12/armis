<?php
/**
 * Minimal Staff Search - No includes, direct database connection
 */

// Prevent any output before JSON
ob_start();

// Set JSON header
header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');

try {
    // Direct database connection (using correct database name)
    $host = 'localhost';
    $dbname = 'armis1';  // Correct database name from config
    $username = 'root';
    $password = '';
    
    $dsn = "mysql:host=$host;dbname=$dbname;charset=utf8mb4";
    $pdo = new PDO($dsn, $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    
    // Get parameters
    $rankId = $_GET['rank_id'] ?? '';
    $query = $_GET['q'] ?? '';
    $noSearch = $_GET['no_search'] ?? false;
    
    // Handle no search case
    if ($noSearch || empty($rankId)) {
        ob_clean();
        echo json_encode([]);
        exit;
    }
    
    // Enhanced query with accurate time at current rank calculation
    $sql = "SELECT 
                s.service_number,
                CONCAT(
                    COALESCE(r.name, 'No Rank'), ' ',
                    COALESCE(s.first_name, ''), ' ',
                    COALESCE(s.last_name, ''),
                    ' (', s.service_number, ')'
                ) as text,
                s.first_name,
                s.last_name,
                s.rank_id,
                r.name as rank_name,
                COALESCE(r.abbreviation, r.name) as rank_abbreviation,
                COALESCE(u.name, 'N/A') as unit_name,
                s.corps,
                s.svcStatus,
                s.attestDate,
                s.subWef,
                s.tempWef,
                -- Calculate months at CURRENT rank using promotion to this rank
                TIMESTAMPDIFF(MONTH, 
                    COALESCE(
                        (SELECT MAX(sp.date_to) 
                         FROM staff_promotions sp 
                         WHERE sp.staff_id = s.id 
                         AND sp.new_rank = s.rank_id 
                         AND sp.type = 'promotion'),
                        s.subWef,
                        s.tempWef,
                        s.attestDate
                    ), 
                    CURDATE()
                ) as months_at_rank,
                -- Get the date when promoted/assigned to current rank
                COALESCE(
                    (SELECT MAX(sp.date_to) 
                     FROM staff_promotions sp 
                     WHERE sp.staff_id = s.id 
                     AND sp.new_rank = s.rank_id 
                     AND sp.type = 'promotion'),
                    s.subWef,
                    s.tempWef,
                    s.attestDate
                ) as rank_date,
                -- Last promotion (any rank)
                (SELECT MAX(sp.date_to) FROM staff_promotions sp WHERE sp.staff_id = s.id AND sp.type = 'promotion') as last_promotion_date,
                (SELECT DATEDIFF(CURDATE(), MAX(sp.date_to)) FROM staff_promotions sp WHERE sp.staff_id = s.id AND sp.type = 'promotion') as days_since_promotion
            FROM staff s
            LEFT JOIN ranks r ON s.rank_id = r.id
            LEFT JOIN units u ON s.unit_id = u.id
            WHERE s.svcStatus = 'Active'";
    
    $params = [];
    
    // Always require rank_id for promotion search
    $sql .= " AND s.rank_id = ?";
    $params[] = $rankId;
    
    // Add search filter if provided and not "all"
    if (!empty($query) && $query !== 'all') {
        $sql .= " AND (
            s.first_name LIKE ? OR
            s.last_name LIKE ? OR
            s.service_number LIKE ? OR
            CONCAT(s.first_name, ' ', s.last_name) LIKE ?
        )";
        $searchTerm = '%' . $query . '%';
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $params[] = $searchTerm;
    }
    
    $sql .= " ORDER BY 
        -- Order by time at current rank (oldest first = longest at rank)
        COALESCE(
            (SELECT MAX(sp.date_to) 
             FROM staff_promotions sp 
             WHERE sp.staff_id = s.id 
             AND sp.new_rank = s.rank_id 
             AND sp.type = 'promotion'),
            s.subWef,
            s.tempWef,
            s.attestDate,
            '1900-01-01'
        ) ASC,
        s.service_number ASC 
    LIMIT 50";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $results = $stmt->fetchAll();
    
    // Format for Select2 and DataTables
    $formatted = [];
    foreach ($results as $row) {
        $formatted[] = [
            'id' => $row['service_number'],
            'service_number' => $row['service_number'],
            'text' => $row['text'],
            'first_name' => $row['first_name'],
            'last_name' => $row['last_name'],
            'rank_id' => $row['rank_id'],
            'rank_name' => $row['rank_name'],
            'rank_abbreviation' => $row['rank_abbreviation'],
            'unit_name' => $row['unit_name'] ?? 'N/A',
            'corps' => $row['corps'] ?? null,
            'svcStatus' => $row['svcStatus'] ?? 'Active',
            'attestDate' => $row['attestDate'] ?? null,
            'subWef' => $row['subWef'] ?? null,
            'tempWef' => $row['tempWef'] ?? null,
            'months_at_rank' => $row['months_at_rank'] ?? 0,
            'rank_date' => $row['rank_date'] ?? null,  // When they got current rank
            'last_promotion_date' => $row['last_promotion_date'] ?? null,
            'days_since_promotion' => $row['days_since_promotion'] ?? null
        ];
    }
    
    // Clean any accidental output
    ob_clean();
    
    // Return clean JSON
    echo json_encode($formatted);
    
} catch (PDOException $e) {
    ob_clean();
    http_response_code(500);
    
    // Log the error for debugging
    error_log("search_staff_minimal.php PDO Error: " . $e->getMessage());
    error_log("SQL State: " . $e->getCode());
    error_log("Rank ID: " . ($rankId ?? 'not set'));
    
    echo json_encode([
        'error' => 'Database error',
        'message' => $e->getMessage(),
        'code' => $e->getCode(),
        'rank_id' => $rankId ?? null
    ]);
    
} catch (Exception $e) {
    ob_clean();
    http_response_code(500);
    
    // Log the error for debugging
    error_log("search_staff_minimal.php General Error: " . $e->getMessage());
    
    echo json_encode([
        'error' => 'General error',
        'message' => $e->getMessage()
    ]);
}

// Final cleanup
ob_end_flush();
?>
