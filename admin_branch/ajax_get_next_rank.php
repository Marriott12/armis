<?php
/**
 * AJAX endpoint to determine the next rank based on current rank and promotion type
 * 
 * This script handles the automated selection of the next/previous rank
 * based on the current rank and promotion type (promotion or demotion).
 */

// Initialize response
$response = [
    'success' => false,
    'message' => '',
    'rankID' => null,
    'rankName' => null
];

// Validate inputs
if (!isset($_GET['current_rank']) || !is_numeric($_GET['current_rank'])) {
    $response['message'] = 'Invalid current rank';
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}

if (!isset($_GET['promotion_type']) || !in_array($_GET['promotion_type'], ['promotion', 'reversion'])) {
    $response['message'] = 'Invalid promotion type';
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}

// Get parameters
$currentRankId = (int)$_GET['current_rank'];
$promotionType = $_GET['promotion_type'];

try {
    // Include database connection
    require_once dirname(__DIR__) . '/shared/database_connection.php';
    $pdo = getDbConnection();
    
    // Get current rank information
    $stmt = $pdo->prepare("SELECT id, name, level FROM ranks WHERE id = ?");
    $stmt->execute([$currentRankId]);
    $currentRank = $stmt->fetch(PDO::FETCH_OBJ);
    
    if (!$currentRank) {
        $response['message'] = 'Current rank not found';
        header('Content-Type: application/json');
        echo json_encode($response);
        exit;
    }
    
    // Get all ranks
    $rankStmt = $pdo->query("SELECT id, name, level FROM ranks ORDER BY level ASC");
    $ranks = $rankStmt->fetchAll(PDO::FETCH_OBJ);
    
    // Determine rank category (Officer or NCO)
    $category = '';
    if ($currentRank->level >= 1 && $currentRank->level <= 14) {
        $category = 'Officer';
    } elseif ($currentRank->level >= 15 && $currentRank->level <= 26) {
        $category = 'NCO';
    }
    
    // Define valid range based on category
    $validRange = $category === 'Officer' ? range(1, 13) : ($category === 'NCO' ? range(15, 26) : []);
    $nextRankObj = null;
    
    // Find the appropriate next/previous rank
    if ($promotionType === 'promotion') {
        // For promotion, find the next higher rank
        foreach ($ranks as $r) {
            if ($r->level > $currentRank->level && in_array($r->level, $validRange)) {
                if ($nextRankObj === null || $r->level < $nextRankObj->level) {
                    $nextRankObj = $r;
                }
            }
        }
    } else {
        // For demotion/reversion, find the next lower rank
        foreach ($ranks as $r) {
            if ($r->level < $currentRank->level && in_array($r->level, $validRange)) {
                if ($nextRankObj === null || $r->level > $nextRankObj->level) {
                    $nextRankObj = $r;
                }
            }
        }
    }
    
    // Set the response
    if ($nextRankObj) {
        $response['success'] = true;
        $response['rankID'] = $nextRankObj->id;
        $response['rankName'] = $nextRankObj->name;
    } else {
        $response['message'] = 'No suitable ' . ($promotionType === 'promotion' ? 'higher' : 'lower') . ' rank found';
    }
    
} catch (Exception $e) {
    $response['message'] = 'Error: ' . $e->getMessage();
}

// Return the response
header('Content-Type: application/json');
echo json_encode($response);
