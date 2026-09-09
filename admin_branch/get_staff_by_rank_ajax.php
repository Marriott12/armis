<?php
/**
 * AJAX Endpoint for DataTables - Get Staff by Rank
 * Returns staff members at a specific rank in DataTables format
 * 
 * @author Armis Development Team
 * @date October 3, 2025
 */

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Set JSON header
header('Content-Type: application/json');

// Include database connection
require_once dirname(__DIR__) . '/shared/database_connection.php';
require_once __DIR__ . '/includes/auth.php';

// This endpoint previously had NO authentication check at all — it
// returns real staff records (names, service numbers) to anyone who
// requested the URL.
requireAuth();

try {
    // Get rank ID from request
    $rankId = isset($_GET['rankId']) ? (int)$_GET['rankId'] : 0;
    
    // Validate rank ID
    if ($rankId <= 0) {
        echo json_encode([
            'data' => [],
            'recordsTotal' => 0,
            'recordsFiltered' => 0,
            'error' => 'Invalid rank ID'
        ]);
        exit;
    }
    
    // Get database connection
    $pdo = getDbConnection();
    
    // Verify rank exists (using `rank` table with rankId)
    $rankCheck = $pdo->prepare("SELECT rankId as id, rankId as name, level, category FROM `rank` WHERE rankId = ?");
    $rankCheck->execute([$rankId]);
    $rankData = $rankCheck->fetch(PDO::FETCH_OBJ);
    
    if (!$rankData) {
        echo json_encode([
            'data' => [],
            'recordsTotal' => 0,
            'recordsFiltered' => 0,
            'error' => 'Rank not found'
        ]);
        exit;
    }
    
    // Fetch all staff at the selected rank
    $stmt = $pdo->prepare("
        SELECT 
            s.id,
            s.svcNo,
            s.fName,
            s.lName,
            CONCAT(s.fName, ' ', s.lName) as full_name,
            s.DOB,
            s.attestDate,
            s.rankId,
            s.unitId,
            u.name as unit_name,
            u.code as unit_code,
            COALESCE(r.rankId, r.rankId) as rank_name,
            r.rankId as rank_abbr,
            r.level as rank_level,
            YEAR(CURDATE()) - YEAR(s.DOB) - (DATE_FORMAT(CURDATE(), '%m%d') < DATE_FORMAT(s.DOB, '%m%d')) as age,
            TIMESTAMPDIFF(YEAR, s.attestDate, CURDATE()) as years_of_service
        FROM staff s
        LEFT JOIN unit u ON s.unitId = u.unitId
        LEFT JOIN `rank` r ON s.rankId = r.rankId
        WHERE s.rankId = ? AND s.status = 'active'
        ORDER BY s.attestDate ASC, s.lName ASC, s.fName ASC
    ");
    
    $stmt->execute([$rankId]);
    $staff = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Format dates for display
    foreach ($staff as &$member) {
        // Format DOB
        if (!empty($member['DOB']) && $member['DOB'] !== '0000-00-00') {
            $dob = new DateTime($member['DOB']);
            $member['DOB_formatted'] = $dob->format('d M Y');
        } else {
            $member['DOB_formatted'] = 'N/A';
            $member['age'] = 'N/A';
        }
        
        // Format attestation date
        if (!empty($member['attestDate']) && $member['attestDate'] !== '0000-00-00') {
            $attestDate = new DateTime($member['attestDate']);
            $member['attestDate_formatted'] = $attestDate->format('d M Y');
        } else {
            $member['attestDate_formatted'] = 'N/A';
            $member['years_of_service'] = 'N/A';
        }
        
        // Combine unit name with code if available
        if (!empty($member['unit_code'])) {
            $member['unit_display'] = $member['unit_name'] . ' (' . $member['unit_code'] . ')';
        } else {
            $member['unit_display'] = $member['unit_name'] ?? 'N/A';
        }
        
        // Combine rank with abbreviation
        if (!empty($member['rank_abbr'])) {
            $member['rank_display'] = $member['rank_abbr'];
        } else {
            $member['rank_display'] = $member['rank_name'] ?? 'N/A';
        }
        
        // Clean up null values
        $member['full_name'] = trim($member['full_name']);
        $member['unit_name'] = $member['unit_name'] ?? 'Unassigned';
    }
    
    // Return data in DataTables format
    echo json_encode([
        'data' => $staff,
        'recordsTotal' => count($staff),
        'recordsFiltered' => count($staff),
        'rank' => [
            'id' => $rankData->id,
            'name' => $rankData->name,
            'level' => $rankData->level,
            'category' => $rankData->category
        ]
    ]);
    
} catch (PDOException $e) {
    // Log error (in production, use proper logging)
    error_log("Database error in get_staff_by_rank_ajax.php: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    
    // Return error response with more details for debugging
    echo json_encode([
        'data' => [],
        'recordsTotal' => 0,
        'recordsFiltered' => 0,
        'error' => 'Database error: ' . $e->getMessage(),
        'debug' => [
            'message' => $e->getMessage(),
            'code' => $e->getCode(),
            'file' => $e->getFile(),
            'line' => $e->getLine()
        ]
    ]);
    
} catch (Exception $e) {
    // Log error
    error_log("Error in get_staff_by_rank_ajax.php: " . $e->getMessage());
    
    // Return error response
    echo json_encode([
        'data' => [],
        'recordsTotal' => 0,
        'recordsFiltered' => 0,
        'error' => 'An error occurred. Please try again.'
    ]);
}
