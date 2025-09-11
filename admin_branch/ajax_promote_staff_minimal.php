<?php
/**
 * Minimal AJAX Promotion Handler - No includes, direct database connection
 */

// Prevent any output before JSON
ob_start();

// Set JSON header
header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');

// Only handle POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ob_clean();
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Only POST requests allowed']);
    exit;
}

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
    
    // Get and validate input data
    $selectedStaff = $_POST['selected_staff'] ?? [];
    $nextRankId = $_POST['next_rank_id'] ?? '';
    $effectiveDate = $_POST['effective_date'] ?? '';
    $promotionType = $_POST['promotion_type'] ?? 'promotion';
    $notes = $_POST['notes'] ?? '';
    
    // Basic validation
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
    
    // Convert single staff to array if needed
    if (!is_array($selectedStaff)) {
        $selectedStaff = [$selectedStaff];
    }
    
    // Validate staff count
    if (count($selectedStaff) > 100) {
        throw new Exception('Too many staff members selected (max 100)');
    }
    
    // Start transaction
    $pdo->beginTransaction();
    
    $processedCount = 0;
    $errors = [];
    
    foreach ($selectedStaff as $serviceNumber) {
        try {
            // Validate service number
            if (empty($serviceNumber)) {
                $errors[] = "Invalid service number";
                continue;
            }
            
            // Get current staff data
            $stmt = $pdo->prepare("
                SELECT s.*, r.name as current_rank_name, r.level as current_rank_level
                FROM staff s 
                LEFT JOIN ranks r ON s.rank_id = r.id 
                WHERE s.service_number = ? AND s.svcStatus = 'Active'
            ");
            $stmt->execute([$serviceNumber]);
            $staff = $stmt->fetch();
            
            if (!$staff) {
                $errors[] = "Staff not found: " . htmlspecialchars($serviceNumber);
                continue;
            }
            
            // Get new rank data
            $stmt = $pdo->prepare("SELECT * FROM ranks WHERE id = ?");
            $stmt->execute([$nextRankId]);
            $newRank = $stmt->fetch();
            
            if (!$newRank) {
                $errors[] = "Invalid rank specified for " . htmlspecialchars($serviceNumber);
                continue;
            }
            
            // Update staff rank
            $stmt = $pdo->prepare("
                UPDATE staff 
                SET rank_id = ? 
                WHERE service_number = ?
            ");
            $stmt->execute([$nextRankId, $serviceNumber]);
            
            // Log the promotion in staff_promotions table if it exists
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO staff_promotions 
                    (staff_id, current_rank, new_rank, date_from, date_to, type, authority, remark, created_at) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
                ");
                $stmt->execute([
                    $staff['id'],
                    $staff['rank_id'],
                    $nextRankId,
                    $effectiveDate,
                    $effectiveDate,
                    $promotionType,
                    'System Promotion',
                    $notes
                ]);
            } catch (PDOException $e) {
                // If staff_promotions table doesn't exist, just continue
                error_log("Promotion history logging failed: " . $e->getMessage());
            }
            
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
        
        ob_clean();
        echo json_encode([
            'success' => true,
            'message' => $successMessage,
            'processed_count' => $processedCount,
            'errors' => $errors,
            'promotion_type' => $promotionType
        ]);
        
    } else {
        $pdo->rollback();
        ob_clean();
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
    
    ob_clean();
    error_log("Promotion database error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Database error',
        'message' => 'A database error occurred while processing promotions',
        'debug' => $e->getMessage()
    ]);
    
} catch (Exception $e) {
    // General error
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollback();
    }
    
    ob_clean();
    error_log("Promotion error: " . $e->getMessage());
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'Processing error',
        'message' => $e->getMessage()
    ]);
}

// Final cleanup
ob_end_flush();
?>
