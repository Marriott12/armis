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
    
    // Simple test query
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
                r.name as rank_name
            FROM staff s
            LEFT JOIN ranks r ON s.rank_id = r.id
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
    
    $sql .= " ORDER BY s.last_name ASC, s.first_name ASC LIMIT 50";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $results = $stmt->fetchAll();
    
    // Format for Select2
    $formatted = [];
    foreach ($results as $row) {
        $formatted[] = [
            'id' => $row['service_number'],
            'service_number' => $row['service_number'],
            'text' => $row['text'],
            'first_name' => $row['first_name'],
            'last_name' => $row['last_name'],
            'rank_id' => $row['rank_id'],
            'rank_name' => $row['rank_name']
        ];
    }
    
    // Clean any accidental output
    ob_clean();
    
    // Return clean JSON
    echo json_encode($formatted);
    
} catch (PDOException $e) {
    ob_clean();
    http_response_code(500);
    echo json_encode([
        'error' => 'Database error',
        'message' => $e->getMessage(),
        'code' => $e->getCode()
    ]);
    
} catch (Exception $e) {
    ob_clean();
    http_response_code(500);
    echo json_encode([
        'error' => 'General error',
        'message' => $e->getMessage()
    ]);
}

// Final cleanup
ob_end_flush();
?>
