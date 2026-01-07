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
    $rankId = $_GET['rankId'] ?? '';
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
                s.svcNo,
                CONCAT(
                    COALESCE(r.rankId, 'No Rank'), ' ',
                    COALESCE(s.fName, ''), ' ',
                    COALESCE(s.lName, ''),
                    ' (', s.svcNo, ')'
                ) as text,
                s.fName,
                s.lName,
                s.rankId,
                r.rankId as rank_name,
                COALESCE(r.rankId, r.rankId) as rank_abbreviation,
                COALESCE(u.code, 'N/A') as unit_name,
                s.corpsId as corps,
                s.svcStatus,
                s.attestDate,
                s.subWef,
                s.tempWef,
                -- Calculate months at CURRENT rank using promotion to this rank
                TIMESTAMPDIFF(MONTH, 
                    COALESCE(
                        (SELECT MAX(sp.dateTo) 
                         FROM staff_promotion sp 
                         WHERE sp.svcNo = s.svcNo 
                         AND sp.newRank = s.rankId 
                         AND sp.type = 'promotion'),
                        s.subWef,
                        s.tempWef,
                        s.attestDate
                    ), 
                    CURDATE()
                ) as months_at_rank,
                -- Get the date when promoted/assigned to current rank
                COALESCE(
                    (SELECT MAX(sp.dateTo) 
                     FROM staff_promotion sp 
                     WHERE sp.svcNo = s.svcNo 
                     AND sp.newRank = s.rankId 
                     AND sp.type = 'promotion'),
                    s.subWef,
                    s.tempWef,
                    s.attestDate
                ) as rank_date,
                -- Last promotion (any rank)
                (SELECT MAX(sp.dateTo) FROM staff_promotion sp WHERE sp.svcNo = s.svcNo AND sp.type = 'promotion') as last_promotion_date,
                (SELECT DATEDIFF(CURDATE(), MAX(sp.dateTo)) FROM staff_promotion sp WHERE sp.svcNo = s.svcNo AND sp.type = 'promotion') as days_since_promotion
            FROM staff s
            LEFT JOIN `rank` r ON s.rankId = r.rankId
            LEFT JOIN unit u ON s.unitId = u.unitId
            WHERE s.svcStatus = 'Active'";
    
    $params = [];
    
    // Always require rankId for promotion search
    $sql .= " AND s.rankId = ?";
    $params[] = $rankId;
    
    // Add search filter if provided and not "all"
    if (!empty($query) && $query !== 'all') {
        $sql .= " AND (
            s.fName LIKE ? OR
            s.lName LIKE ? OR
            s.svcNo LIKE ? OR
            CONCAT(s.fName, ' ', s.lName) LIKE ?
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
            (SELECT MAX(sp.dateTo) 
             FROM staff_promotion sp 
             WHERE sp.svcNo = s.svcNo 
             AND sp.newRank = s.rankId 
             AND sp.type = 'promotion'),
            s.subWef,
            s.tempWef,
            s.attestDate,
            '1900-01-01'
        ) ASC,
        s.svcNo ASC 
    LIMIT 50";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $results = $stmt->fetchAll();
    
    // Format for Select2 and DataTables
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
        'rankId' => $rankId ?? null
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
