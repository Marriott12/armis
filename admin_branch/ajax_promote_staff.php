<?php
/**
 * AJAX Promotion Handler
 * 
 * Handles staff promotion/reversion requests via AJAX
 */

// Start session and check authentication
session_start();

// Include required files
require_once 'includes/config.php';
require_once 'includes/auth.php';
require_once '../shared/permissions.php';

// Verify user is logged in
requireAuth();

// Check permissions
if (!hasPermission(PERM_PROMOTE_STAFF)) {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'error' => 'Insufficient permissions',
        'message' => 'You do not have permission to promote staff'
    ]);
    exit;
}

// Set JSON header
header('Content-Type: application/json');

try {
    // Only handle POST requests
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Only POST requests allowed');
    }
    
    // Get database connection
    $pdo = getDbConnection();
    
    // Validate CSRF token if available
    if (isset($_POST['csrf_token']) && isset($_SESSION['csrf_token'])) {
        if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
            throw new Exception('Invalid security token');
        }
    }
    
    // Get and validate input data
    $selectedStaff = $_POST['selected_staff'] ?? [];
    $nextRankId = $_POST['next_rank_id'] ?? '';
    $effectiveDate = $_POST['effective_date'] ?? '';
    $promotionType = $_POST['promotion_type'] ?? 'promotion';
    $notes = $_POST['notes'] ?? '';
    
    // Validation
    if (empty($selectedStaff)) {
        throw new Exception('No staff members selected');
    }
    
    if (empty($nextRankId)) {
        throw new Exception('Next rank not specified');
    }
    
    if (empty($effectiveDate)) {
        throw new Exception('Effective date not specified');
    }
    
    // Validate date format
    $dateTime = DateTime::createFromFormat('Y-m-d', $effectiveDate);
    if (!$dateTime || $dateTime->format('Y-m-d') !== $effectiveDate) {
        throw new Exception('Invalid date format');
    }
    
    // Validate promotion type
    if (!in_array($promotionType, ['promotion', 'reversion'])) {
        throw new Exception('Invalid promotion type');
    }
    
    // Convert single staff to array if needed
    if (!is_array($selectedStaff)) {
        $selectedStaff = [$selectedStaff];
    }
    
    // Validate staff service numbers
    if (count($selectedStaff) > 100) {
        throw new Exception('Too many staff members selected (max 100)');
    }
    
    // Start transaction
    $pdo->beginTransaction();
    
    $processedCount = 0;
    $errors = [];
    
    foreach ($selectedStaff as $serviceNumber) {
        try {
            // Validate service number format
            if (empty($serviceNumber) || !is_string($serviceNumber)) {
                $errors[] = "Invalid service number: " . htmlspecialchars($serviceNumber);
                continue;
            }
            
            // Get current staff data
            $stmt = $pdo->prepare("
                SELECT s.*, r.rankId as current_rank_name, r.rankIndex as current_rank_order
                FROM staff s 
                LEFT JOIN rank r ON s.rankId = r.rankId 
                WHERE s.svcNo = ? AND s.svcStatus = 'Active'
            ");
            $stmt->execute([$serviceNumber]);
            $staff = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$staff) {
                $errors[] = "Staff not found: " . htmlspecialchars($serviceNumber);
                continue;
            }

            if (function_exists('canAlterRecord') && !canAlterRecord($staff['branch_id'] ?? null)) {
                $errors[] = htmlspecialchars($serviceNumber) . " is outside your branch - skipped.";
                continue;
            }

            // Get new rank data
            $stmt = $pdo->prepare("SELECT * FROM rank WHERE id = ?");
            $stmt->execute([$nextRankId]);
            $newRank = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$newRank) {
                $errors[] = "Invalid rank specified for " . htmlspecialchars($serviceNumber);
                continue;
            }
            
            // Validate promotion logic
            if ($promotionType === 'promotion' && $newRank['rankIndex'] <= $staff['current_rank_order']) {
                $errors[] = "Cannot promote " . htmlspecialchars($serviceNumber) . " to a lower or same rank";
                continue;
            }
            
            if ($promotionType === 'reversion' && $newRank['rankIndex'] >= $staff['current_rank_order']) {
                $errors[] = "Cannot revert " . htmlspecialchars($serviceNumber) . " to a higher or same rank";
                continue;
            }
            
            // Update staff rank
            $stmt = $pdo->prepare("
                UPDATE staff 
                SET rankId = ?, updatedAt = NOW() 
                WHERE svcNo = ?
            ");
            $stmt->execute([$nextRankId, $serviceNumber]);
            
            // Get staff ID for promotion history
            $staffId = $staff['id'];
            
            // Log the promotion/reversion in staff_promotions table
            $stmt = $pdo->prepare("
                INSERT INTO staff_promotions 
                (svcNo, currentRank, newRank, dateFrom, dateTo, type, authority, remark, createdBy, createdAt) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([
                $staffId,
                $staff['rankId'],
                $nextRankId,
                $effectiveDate,
                $effectiveDate, // Using same date for both from and to for now
                $promotionType,
                'System Promotion', // Default authority
                $notes,
                $_SESSION['user_id']
            ]);
            
            $processedCount++;
            
        } catch (Exception $e) {
            $errors[] = "Error processing " . htmlspecialchars($serviceNumber) . ": " . $e->getMessage();
        }
    }
    
    // Commit transaction if we processed at least one staff member
    if ($processedCount > 0) {
        $pdo->commit();
        
        $successMessage = $processedCount . " staff member(s) successfully " . 
                         ($promotionType === 'promotion' ? 'promoted' : 'reverted');
        
        if (!empty($errors)) {
            $successMessage .= ". " . count($errors) . " error(s) occurred.";
        }
        
        echo json_encode([
            'success' => true,
            'message' => $successMessage,
            'processed_count' => $processedCount,
            'errors' => $errors,
            'promotion_type' => $promotionType
        ]);
        
    } else {
        $pdo->rollback();
        echo json_encode([
            'success' => false,
            'message' => 'No staff members were processed',
            'errors' => $errors
        ]);
    }
    
} catch (PDOException $e) {
    // Database error
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollback();
    }
    
    error_log("Promotion AJAX database error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Database error',
        'message' => 'A database error occurred while processing promotions'
    ]);
    
} catch (Exception $e) {
    // General error
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollback();
    }
    
    error_log("Promotion AJAX error: " . $e->getMessage());
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'Processing error',
        'message' => $e->getMessage()
    ]);
}
?>
