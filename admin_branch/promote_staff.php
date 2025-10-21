<?php
/**
 * Staff Promotion Management Script
 * 
 * This script handles the promotion and reversion of staff members,
 * managing their rank changes and maintaining a historical record.
 * 
 * Enhanced with better validation, error handling, and CSRF protection.
 */

// Initialize variables
$errors = [];
$successMessages = [];
$success = false;
$currentRankId = '';
$currentRank = null;
$eligibleStaff = [];
$staffCount = 0;
$category = '';
$ranks = [];
$showStaffProfileModal = false;

// Constants (can be moved to config file)
$BULK_CONFIRMATION_THRESHOLD = 10;
$ENABLE_EXPORT_REPORT = true;
$ENABLE_PROFILE_POPUP = true;

/**
 * Generate a promotion/reversion report in CSV format
 * 
 * @param array $serviceNumbers Array of service numbers
 * @param object $fromRank Current rank object
 * @param object $toRank Next rank object 
 * @param string $effectiveDate Promotion effective date
 * @param string $promotionType 'promotion' or 'reversion'
 * @return string|false Path to report or false on failure
 */
function generatePromotionReport($serviceNumbers, $fromRank, $toRank, $effectiveDate, $promotionType) {
    global $pdo;
    if (empty($serviceNumbers)) {
        return false;
    }
    try {
        // Create directory if it doesn't exist
        $reportsDir = __DIR__ . '/../reports';
        if (!file_exists($reportsDir)) {
            mkdir($reportsDir, 0755, true);
        }
        // Generate filename
        $timestamp = date('Ymd_His');
        $actionType = $promotionType === 'promotion' ? 'Promotion' : 'Reversion';
        $fromAbbr = str_replace(' ', '_', $fromRank->abbreviation ?? $fromRank->name ?? '');
        $toAbbr = str_replace(' ', '_', $toRank->abbreviation ?? $toRank->name ?? '');
        $filename = "{$actionType}_{$fromAbbr}_to_{$toAbbr}_{$timestamp}.pdf";
        $filepath = $reportsDir . '/' . $filename;

        // Fetch staff data
        $placeholders = rtrim(str_repeat('?,', count($serviceNumbers)), ',');
        $stmt = $pdo->prepare("
            SELECT s.service_number, r.abbreviation as rank_abbr, s.first_name, s.last_name, 
                   u.name as unit_name
            FROM staff s
            LEFT JOIN ranks r ON s.rank_id = r.id
            LEFT JOIN units u ON s.unit_id = u.id
            WHERE s.service_number IN ($placeholders)
        ");
        $stmt->execute($serviceNumbers);
        $staffRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Prepare HTML for PDF
        $effectiveDateFormatted = date('d M Y', strtotime($effectiveDate));
        $html = '<h2 style="text-align:center;">' . htmlspecialchars($actionType) . ' Report</h2>';
        $fromRankAbbr = $fromRank->abbreviation ?? '';
        $toRankAbbr = $toRank->abbreviation ?? '';
        $html .= '<p><strong>From Rank:</strong> ' . htmlspecialchars($fromRankAbbr) .
            ' &nbsp; <strong>To Rank:</strong> ' . htmlspecialchars($toRankAbbr) .
            ' &nbsp; <strong>Effective Date:</strong> ' . htmlspecialchars($effectiveDateFormatted) . '</p>';
        $html .= '<table border="1" cellpadding="5" cellspacing="0" width="100%">';
        $html .= '<thead><tr>';
        $headers = [
            'Service Number', 'Rank', 'Name', 'Unit', 'Action', 'From Rank', 'To Rank', 'Effective Date', 'Authority', 'Remarks'
        ];
        foreach ($headers as $header) {
            $html .= '<th>' . htmlspecialchars($header) . '</th>';
        }
        $html .= '</tr></thead><tbody>';
        foreach ($staffRows as $staff) {
            $html .= '<tr>';
            $html .= '<td>' . htmlspecialchars($staff['service_number']) . '</td>';
            $html .= '<td>' . htmlspecialchars($staff['rank_abbr']) . '</td>';
            $html .= '<td>' . htmlspecialchars($staff['first_name'] . ' ' . $staff['last_name']) . '</td>';
            $html .= '<td>' . htmlspecialchars($staff['unit_name']) . '</td>';
            $html .= '<td>' . htmlspecialchars($actionType) . '</td>';
            $html .= '<td>' . htmlspecialchars($staff['rank_abbr']) . '</td>';
            $html .= '<td>' . htmlspecialchars($toRank->abbreviation ?? $toRank->name ?? '') . '</td>';
            $html .= '<td>' . htmlspecialchars($effectiveDateFormatted) . '</td>';
            $html .= '<td>HQ Authority</td>';
            $html .= '<td>Regular ' . htmlspecialchars(ucfirst($promotionType)) . '</td>';
            $html .= '</tr>';
        }
        $html .= '</tbody></table>';

        // Generate PDF using mPDF
    // Use correct path to vendor/autoload.php (project root)
    require_once dirname(__DIR__, 1) . '/vendor/autoload.php';
        $mpdf = new \Mpdf\Mpdf();
        $mpdf->WriteHTML($html);
        $mpdf->Output($filepath, \Mpdf\Output\Destination::FILE);

        // Return the web-accessible path
        return '/Armis2/reports/' . $filename;
    } catch (Exception $e) {
        error_log("Failed to generate promotion report: " . $e->getMessage());
        return false;
    }
}

// Authentication and database connection
require_once __DIR__ . '/includes/auth.php';
require_once dirname(__DIR__) . '/shared/database_connection.php';
require_once dirname(__DIR__) . '/shared/permissions.php';
require_once dirname(__DIR__) . '/shared/AuditLogger.php';
$pdo = getDbConnection();

// Use requireAuth function from auth.php
requireAuth();

// Check admin_branch access - the original file uses this pattern
requireModuleAccess('admin_branch');

// CSRF Protection - using existing session
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

// Initialize Audit Logger
$auditLogger = new AuditLogger($pdo);

// Check permission for staff promotion
if (!hasPermission(PERM_PROMOTE_STAFF)) {
    header('Location: unauthorized.php?reason=promote_staff');
    exit;
}

// Get the authenticated user
$userId = $_SESSION['user_id'] ?? 0;
if (!$userId) {
    header('Location: ../login.php');
    exit;
}

// Check for session success message (from redirect after promotion)
if (isset($_SESSION['promotion_success']) && isset($_SESSION['promotion_success_time'])) {
    // Only show message if it's less than 10 seconds old (prevents showing stale messages)
    if (time() - $_SESSION['promotion_success_time'] < 10) {
        $successMessages[] = $_SESSION['promotion_success'];
        $success = true;
    }
    // Clear the session message
    unset($_SESSION['promotion_success']);
    unset($_SESSION['promotion_success_time']);
}

// Fetch ranks for selection (excluding non-promotable ranks)
try {
    // Exclude: Mister, Miss, Recruit, Officer Cadet (these are not promotable ranks)
    $rankStmt = $pdo->query("
        SELECT r.id, r.name, r.abbreviation, r.level, r.category, COUNT(s.id) as staff_count
        FROM ranks r
        LEFT JOIN staff s ON s.rank_id = r.id AND (s.svcStatus IS NULL OR s.svcStatus = 'active')
        WHERE r.name NOT IN ('Mister', 'Miss', 'Recruit', 'Officer Cadet')
        GROUP BY r.id, r.name, r.abbreviation, r.level, r.category
        ORDER BY r.level ASC
    ");
    $ranks = $rankStmt->fetchAll(PDO::FETCH_OBJ);
} catch (Exception $e) {
    $errors[] = "Failed to load ranks: " . htmlspecialchars($e->getMessage());
}

// Initialize variables
$currentRankLevel = null;

// Process the current rank selection
if (isset($_GET['current_rank']) && is_numeric($_GET['current_rank'])) {
    $currentRankId = (int)$_GET['current_rank'];
    try {
        // Get the current rank details
        $stmt = $pdo->prepare("SELECT id, name, abbreviation, level, category FROM ranks WHERE id = ?");
        $stmt->execute([$currentRankId]);
        $currentRank = $stmt->fetch(PDO::FETCH_OBJ);
        
        if ($currentRank) {
            // Get category directly from database
            $category = $currentRank->category;
            $currentRankName = $currentRank->name; // Add this for JavaScript fallback
            $currentRankLevel = $currentRank->level; // Set level for JavaScript use
            
            // Fetch staff at the selected rank with promotion history
            $staffStmt = $pdo->prepare("
                SELECT s.id, s.service_number, s.first_name, s.last_name, s.rank_id, 
                       s.attestDate, s.unit_id, s.subWef, s.tempWef, s.corps, s.svcStatus,
                       u.name as unit_name,
                       r.name as rank_name,
                       COALESCE(r.abbreviation, r.name) as rank_abbreviation,
                       -- Date when they got current rank (for display)
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
                       -- Last promotion (any rank) for reference
                       (SELECT MAX(sp.date_to) 
                        FROM staff_promotions sp 
                        WHERE sp.staff_id = s.id 
                        AND sp.type = 'promotion') as last_promotion_date,
                       (SELECT DATEDIFF(CURDATE(), MAX(sp.date_to))
                        FROM staff_promotions sp 
                        WHERE sp.staff_id = s.id 
                        AND sp.type = 'promotion') as days_since_last_promotion,
                       (SELECT COUNT(*) 
                        FROM staff_promotions sp 
                        WHERE sp.staff_id = s.id 
                        AND sp.type = 'promotion'
                        AND sp.date_to >= DATE_SUB(CURDATE(), INTERVAL 1 YEAR)) as promotions_this_year,
                       -- Calculate months at CURRENT rank
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
                       ) as calculated_months_at_rank
                FROM staff s
                LEFT JOIN units u ON s.unit_id = u.id
                LEFT JOIN ranks r ON s.rank_id = r.id
                WHERE s.rank_id = ? 
                ORDER BY 
                    -- Order by time at current rank (oldest date first = longest time at rank)
                    -- Priority: Last promotion date → Substantive date → Temporal date → Attestation date
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
                    -- Tiebreaker: Service number (for same date promotions)
                    s.service_number ASC
            ");
            $staffStmt->execute([$currentRankId]);
            $eligibleStaff = $staffStmt->fetchAll(PDO::FETCH_OBJ);
            $staffCount = count($eligibleStaff);

            
            
            // Mark eligibility for each staff member with business rules
            foreach ($eligibleStaff as &$staff) {
                $staff->eligible = true;
                $staff->eligibilityReasons = [];
                $staff->warnings = [];
                
                // Use database-calculated months at rank (already calculated in SQL)
                $monthsAtRank = $staff->calculated_months_at_rank ?? 0;
                $staff->months_at_rank = $monthsAtRank;
                
                // Business Rule: Minimum 12 months at rank (configurable)
                $minMonthsRequired = 12;
                if ($monthsAtRank < $minMonthsRequired) {
                    $staff->eligible = false;
                    $staff->eligibilityReasons[] = "Only $monthsAtRank months at rank (minimum: $minMonthsRequired months)";
                }
                
                // Warn if no rank date available
                if (!$staff->subWef && !$staff->tempWef && !$staff->attestDate) {
                    $staff->warnings[] = "No rank date recorded";
                }
                
                // Business Rule: Maximum 2 promotions per year
                if ($staff->promotions_this_year >= 2) {
                    $staff->eligible = false;
                    $staff->eligibilityReasons[] = "Already promoted {$staff->promotions_this_year} times this year";
                }
                
                // Use database-calculated days since promotion
                $daysSince = $staff->days_since_last_promotion ?? null;
                $staff->days_since_promotion = $daysSince;
                
                // Warning: Recently promoted (within 6 months)
                if ($daysSince !== null && $daysSince < 180) { // 6 months
                    $staff->warnings[] = "Promoted $daysSince days ago";
                }
                
                // Example eligibility check - uncomment to activate
                /* 
                // Check time in rank if promotion date exists
                if (!empty($staff->promotion_date)) {
                    $timeInRank = (new DateTime())->diff(new DateTime($staff->promotion_date));
                    $monthsInRank = ($timeInRank->y * 12) + $timeInRank->m;
                    
                    if ($monthsInRank < 12) { // Example: must be in rank for at least 12 months
                        $staff->eligible = false;
                        $staff->eligibilityReasons[] = "Less than 12 months in current rank";
                    }
                }
                */
            }
        } else {
            $errors[] = "Invalid rank selected.";
        }
    } catch (Exception $e) {
        $errors[] = "Error loading staff data: " . htmlspecialchars($e->getMessage());
    }
}

// Process promotion/reversion submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['promote_staff'])) {
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $errors[] = "Security validation failed. Please try again.";
    } else {
        $currentRankId = $_POST['current_rank'] ?? 0;
        $nextRankId = $_POST['next_rank'] ?? 0;
        $promotionType = strtolower($_POST['promotion_type'] ?? '');
        $promotionDate = $_POST['promotion_date'] ?? '';
        $selectedStaff = $_POST['selected_staff'] ?? [];
        $perStaffAuthority = $_POST['promotion_authority'] ?? [];
        $perStaffRemark = $_POST['promotion_remark'] ?? [];
        
        // Basic validation
        if (!$currentRankId || !is_numeric($currentRankId)) {
            $errors[] = "Invalid current rank.";
        }
        if (!$nextRankId || !is_numeric($nextRankId)) {
            $errors[] = "No lower rank available for demotion. Demotion is not possible from this rank.";
        }
        // Accept 'demotion' as an alias for 'reversion'
        if ($promotionType === 'demotion') {
            $promotionType = 'reversion';
        }
        if (!in_array($promotionType, ['promotion', 'reversion'])) {
            $errors[] = "Invalid promotion type.";
        }
        if (!$promotionDate) {
            $errors[] = "Promotion date is required.";
        } else if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $promotionDate)) {
            $errors[] = "Invalid date format. Use YYYY-MM-DD.";
        }
        if (empty($selectedStaff)) {
            $errors[] = "No staff members selected.";
        }
        
        // If no errors, proceed with promotion/reversion
        if (empty($errors)) {
            // Get the current rank details for logging
            $stmt = $pdo->prepare("SELECT name FROM ranks WHERE id = ?");
            $stmt->execute([$currentRankId]);
            $currentRankObj = $stmt->fetch(PDO::FETCH_OBJ);
            
            // Get the next rank details for logging
            $stmt = $pdo->prepare("SELECT name, abbreviation FROM ranks WHERE id = ?");
            $stmt->execute([$nextRankId]);
            $nextRankObj = $stmt->fetch(PDO::FETCH_OBJ);
            if (!$currentRankObj || !$nextRankObj) {
                $errors[] = "Could not retrieve rank information.";
            } else {
                // Start transaction
                try {
                    $pdo->beginTransaction();
                    $timestamp = date('Y-m-d H:i:s');

                    // Insert authority order row for this action
                    $orderType = ($promotionType === 'reversion') ? 'Demotion' : 'Promotion';
                    $currentYear = date('Y');
                    $orderQuery = $pdo->prepare("SELECT MAX(order_number) as max_order FROM authority_orders WHERE type = ? AND year = ?");
                    $orderQuery->execute([$orderType, $currentYear]);
                    $orderNum = ($orderQuery->fetchColumn() ?: 0) + 1;
                    $authorityText = $orderType . " Order " . $orderNum . "-" . $currentYear;
                    $desc = $orderType . " for rank change from " . ($currentRankObj->name ?? '') . " to " . ($nextRankObj->name ?? '') . " on $timestamp";
                    $insertOrder = $pdo->prepare("INSERT INTO authority_orders (type, year, order_number, description, created_at) VALUES (?, ?, ?, ?, ?)");
                    $insertOrder->execute([$orderType, $currentYear, $orderNum, $desc, $timestamp]);

                    foreach ($selectedStaff as $serviceNumber) {
                        // Get staff ID and current details
                        $stmt = $pdo->prepare("SELECT * FROM staff WHERE service_number = ? LIMIT 1");
                        $stmt->execute([$serviceNumber]);
                        $beforeStaff = $stmt->fetch(PDO::FETCH_ASSOC);

                        if (!$beforeStaff) {
                            $errors[] = "Staff member $serviceNumber not found.";
                            continue;
                        }

                        $staffId = $beforeStaff['id'];

                        // DUPLICATE PREVENTION CHECK 1: Verify staff is at the expected current rank
                        if ($beforeStaff['rank_id'] != $currentRankId) {
                            $errors[] = "Staff $serviceNumber is not at the selected rank. Current rank mismatch detected.";
                            continue;
                        }
                        
                        // DUPLICATE PREVENTION CHECK 2: Verify staff is not already at target rank
                        if ($beforeStaff['rank_id'] == $nextRankId) {
                            $errors[] = "Staff $serviceNumber is already at the target rank. No promotion needed.";
                            continue;
                        }
                        
                        // DUPLICATE PREVENTION CHECK 3: Check for recent duplicate promotion
                        $duplicateCheck = $pdo->prepare("
                            SELECT id, date_to, new_rank 
                            FROM staff_promotions 
                            WHERE staff_id = ? 
                            AND new_rank = ? 
                            AND date_to = ?
                            AND created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
                            LIMIT 1
                        ");
                        $duplicateCheck->execute([$staffId, $nextRankId, $promotionDate]);
                        $duplicatePromotion = $duplicateCheck->fetch(PDO::FETCH_ASSOC);
                        
                        if ($duplicatePromotion) {
                            $errors[] = "Staff $serviceNumber was already promoted to this rank on $promotionDate within the last hour. Duplicate promotion prevented.";
                            continue;
                        }
                        
                        // DUPLICATE PREVENTION CHECK 4: Check for any promotion on the same date
                        $sameDateCheck = $pdo->prepare("
                            SELECT id, new_rank, type 
                            FROM staff_promotions 
                            WHERE staff_id = ? 
                            AND date_to = ?
                            ORDER BY created_at DESC
                            LIMIT 1
                        ");
                        $sameDateCheck->execute([$staffId, $promotionDate]);
                        $sameDatePromotion = $sameDateCheck->fetch(PDO::FETCH_ASSOC);
                        
                        if ($sameDatePromotion) {
                            // Get rank name for better error message
                            $rankStmt = $pdo->prepare("SELECT name FROM ranks WHERE id = ?");
                            $rankStmt->execute([$sameDatePromotion['new_rank']]);
                            $existingRank = $rankStmt->fetch(PDO::FETCH_OBJ);
                            
                            $errors[] = "Warning: Staff $serviceNumber already has a {$sameDatePromotion['type']} record on $promotionDate to {$existingRank->name}. Please verify before proceeding.";
                            // Continue but warn - admin may want to override
                        }
                        
                        // Get rank details to determine if it's temporal or substantive
                        $newRankName = $nextRankObj->name;
                        $newRankCategory = isset($nextRankObj->category) ? $nextRankObj->category : '';
                        
                        // Check if rank name starts with 'Temporal' (case-insensitive)
                        $isTemporal = stripos($newRankName, 'Temporal') === 0;
                        
                        // Prepare the update statement based on rank type
                        if ($isTemporal) {
                            // For temporal ranks: update tempWef and clear subWef
                            $updateStmt = $pdo->prepare("UPDATE staff SET 
                                rank_id = ?, 
                                tempWef = ?,
                                subWef = NULL,
                                category = ?
                                WHERE id = ?");
                            
                            $updateStmt->execute([
                                $nextRankId,
                                $promotionDate,
                                $newRankCategory,
                                $staffId
                            ]);
                        } else {
                            // For substantive ranks: update subWef and clear tempWef
                            $updateStmt = $pdo->prepare("UPDATE staff SET 
                                rank_id = ?, 
                                subWef = ?,
                                tempWef = NULL,
                                category = ?
                                WHERE id = ?");
                            
                            $updateStmt->execute([
                                $nextRankId,
                                $promotionDate,
                                $newRankCategory,
                                $staffId
                            ]);
                        }
                        
                        // Record the promotion/reversion in the history table
                        $insertStmt = $pdo->prepare("INSERT INTO staff_promotions (
                            staff_id, 
                            current_rank, 
                            new_rank, 
                            date_from, 
                            date_to, 
                            type, 
                            authority, 
                            remark, 
                            created_by, 
                            created_at
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
                        
                        // Use per-staff authority if provided, otherwise use generated authorityText
                        $authorityValue = isset($perStaffAuthority[$serviceNumber]) && $perStaffAuthority[$serviceNumber] !== ''
                            ? $perStaffAuthority[$serviceNumber]
                            : $authorityText;
                        $insertStmt->execute([
                            $staffId,
                            $currentRankId,
                            $nextRankId,
                            date('Y-m-d'),
                            $promotionDate,
                            $promotionType,
                            $authorityValue,
                            $perStaffRemark[$serviceNumber] ?? '',
                            $userId
                        ]);
                        
                        // Promotion successfully recorded in staff_promotions table
                        $successMessages[] = "<div>$serviceNumber " . 
                            ($promotionType === 'promotion' ? "promoted" : "reverted") . 
                            " from {$currentRankObj->name} to {$nextRankObj->name}</div>";
                    }
                    
                    $pdo->commit();
                    $success = true;
                    
                    // Build a comprehensive success message
                    $totalProcessed = count($selectedStaff);
                    $actionWord = $promotionType === 'promotion' ? 'promoted' : 'reverted';

                    // Prepare formatted effective date once
                    $formattedDate = $promotionDate ? date('d M Y', strtotime($promotionDate)) : '';

                    // Add summary message (only one Effective Date entry)
                    $fromAbbr = $currentRankObj->abbreviation ?? $currentRankObj->name ?? '';
                    $toAbbr = $nextRankObj->abbreviation ?? $nextRankObj->name ?? '';
                    $actionLabel = ($promotionType === 'promotion') ? 'Promotion' : 'Demotion';

                    $summaryMsg = '';
                    if ($formattedDate !== '') {
                        $summaryMsg .= "<div><strong>Effective Date:</strong> $formattedDate</div>";
                    }
                    $summaryMsg .= "<div>$actionLabel successful for all selected personnel.</div>";
                    $summaryMsg .= "<div><strong>Successfully $actionWord $totalProcessed staff member" . ($totalProcessed > 1 ? 's' : '') . " from $fromAbbr to $toAbbr.</strong></div>";
                    array_unshift($successMessages, $summaryMsg);
                    
                    // Always generate and show export report button for both promotion and demotion
                    if ($ENABLE_EXPORT_REPORT) {
                        $reportPath = generatePromotionReport($selectedStaff, $currentRankObj, $nextRankObj, $promotionDate, $promotionType);
                        if ($reportPath) {
                            $actionLabel = ($promotionType === 'promotion') ? 'Promotion' : (($promotionType === 'reversion' || $promotionType === 'demotion') ? 'Demotion' : ucfirst($promotionType));
                            $successMessages[] = "<div class='mt-3'><a href='$reportPath' class='btn btn-primary btn-lg' download><i class='fas fa-download'></i> Download $actionLabel Report</a></div>";
                        }
                    }
                    
                    // Store success message in session and redirect to refresh staff count
                    // All success messages are wrapped in <div>, so join with an empty string for clean HTML output.
                    $_SESSION['promotion_success'] = implode('', $successMessages);
                    $_SESSION['promotion_success_time'] = time();
                    // Redirect to same page with rank parameter to refresh staff count
                    header("Location: promote_staff.php?current_rank=" . $currentRankId . "&action_type=" . $promotionType);
                    exit;
                    
                } catch (Exception $e) {
                    $pdo->rollBack();
                    $errors[] = "Transaction failed: " . htmlspecialchars($e->getMessage());
                    error_log("Promotion transaction failed: " . $e->getMessage());
                }
            }
        }
    }
}

// Prepare next/previous rank for display and submission
$nextRankObj = null;
$nextRankName = '';
$nextRankAbbr = '';
$nextRankIdValue = '';
$authorityText = '';
if ($currentRank) {
    $promotionType = strtolower(trim($_POST['promotion_type'] ?? $_GET['action_type'] ?? ''));
    $currentCategory = $currentRank->category ?? '';
    $currentRankLevel = $currentRank->level ?? null;
    if ($promotionType === 'promotion' && $currentRankLevel !== null) {
        // Promotion: Find next higher rank in same category (smaller level number)
        $higherRanks = array_filter($ranks, function($r) use ($currentRankLevel, $currentCategory) {
            return $r->level < $currentRankLevel && $r->category === $currentCategory;
        });
        // Pick the rank with the largest level less than current (closest higher rank)
        if (!empty($higherRanks)) {
            $nextRankObj = array_reduce($higherRanks, function($carry, $item) {
                return ($carry === null || $item->level > $carry->level) ? $item : $carry;
            }, null);
        }
    } elseif (in_array($promotionType, ['reversion', 'demotion']) && $currentRankLevel !== null) {
        // Demotion: Find next lower rank in same category (larger level number)
        $lowerRanks = array_filter($ranks, function($r) use ($currentRankLevel, $currentCategory) {
            return $r->level > $currentRankLevel && $r->category === $currentCategory;
        });
        // Pick the rank with the smallest level greater than current (closest lower rank)
        if (!empty($lowerRanks)) {
            $nextRankObj = array_reduce($lowerRanks, function($carry, $item) {
                return ($carry === null || $item->level < $carry->level) ? $item : $carry;
            }, null);
        }
    }
    if ($nextRankObj) {
        $nextRankName = $nextRankObj->name;
        $nextRankAbbr = $nextRankObj->abbreviation ?? $nextRankObj->name;
        $nextRankIdValue = $nextRankObj->id;
    }
    // Set authority text for display (use same logic as backend summary)
    $orderType = ($promotionType === 'reversion' || $promotionType === 'demotion') ? 'Demotion' : 'Promotion';
    $currentYear = date('Y');
    // Get next order number for this type and year
    $orderQuery = $pdo->prepare("SELECT MAX(order_number) as max_order FROM authority_orders WHERE type = ? AND year = ?");
    $orderQuery->execute([$orderType, $currentYear]);
    $orderNum = ($orderQuery->fetchColumn() ?: 0) + 1;
    $authorityText = $orderType . " Order " . $orderNum . " - " . $currentYear;
}

// Debug output for server-side next rank calculation
error_log("Server-side next rank calculation: " . json_encode([
    'promotion_type' => $promotionType ?? 'not set',
    'current_rank_level' => $currentRankLevel ?? 'not set',
    'category' => $currentCategory ?? 'not set',
    'next_rank_name' => $nextRankName ?? 'not found',
    'next_rank_id' => $nextRankIdValue ?? 'not found'
]));



$pageTitle = "Promotions - Admin Branch";
$moduleName = "Admin Branch";
$moduleIcon = "users-cog";
$currentPage = "promotions";
// Ensure $actionType is always set before use
if (!isset($actionType)) {
    if (isset($_GET['action_type'])) {
        $actionType = $_GET['action_type'];
    } elseif (isset($_POST['promotion_type'])) {
        $actionType = $_POST['promotion_type'];
    } else {
        $actionType = '';
    }
}
// Sidebar navigation
$sidebarLinks = [
    ['title' => 'Dashboard', 'url' => '/Armis2/admin_branch/index.php', 'icon' => 'tachometer-alt', 'page' => 'dashboard'],
    ['title' => 'Staff Management', 'url' => '/Armis2/admin_branch/edit_staff.php', 'icon' => 'users', 'page' => 'staff'],
    ['title' => 'Create Staff', 'url' => '/Armis2/admin_branch/create_staff.php', 'icon' => 'user-plus', 'page' => 'create'],
    ['title' => 'Promotions', 'url' => '/Armis2/admin_branch/promote_staff.php', 'icon' => 'arrow-up', 'page' => 'promotions'],
    ['title' => 'Appointments', 'url' => '/Armis2/admin_branch/appointments.php', 'icon' => 'user-tie', 'page' => 'appointments'],
    ['title' => 'Medals', 'url' => '/Armis2/admin_branch/assign_medal.php', 'icon' => 'medal', 'page' => 'medals'],
    [
        'title' => 'Reports',
        'icon' => 'chart-bar',
        'page' => 'reports',
        'children' => [
            ['title' => 'Seniority', 'url' => '/Armis2/admin_branch/reports_seniority.php'],
            ['title' => 'Unit List', 'url' => '/Armis2/admin_branch/reports_units.php'],
            ['title' => 'Appointments', 'url' => '/Armis2/admin_branch/reports_appointment.php'],
            ['title' => 'Contracts', 'url' => '/Armis2/admin_branch/reports_contract.php'],
            ['title' => 'Courses', 'url' => '/Armis2/admin_branch/reports_courses.php'],
            ['title' => 'Deceased', 'url' => '/Armis2/admin_branch/reports_deceased.php'],
            ['title' => 'Gender', 'url' => '/Armis2/admin_branch/reports_gender.php'],
            ['title' => 'Marital', 'url' => '/Armis2/admin_branch/reports_marital.php'],
            ['title' => 'Rank', 'url' => '/Armis2/admin_branch/reports_rank.php'],
            ['title' => 'Retired', 'url' => '/Armis2/admin_branch/reports_retired.php'],
            ['title' => 'Trade', 'url' => '/Armis2/admin_branch/reports_trade.php'],
            ['title' => 'Corps', 'url' => '/Armis2/admin_branch/reports_corps.php'],
            ['title' => 'Units', 'url' => '/Armis2/admin_branch/reports_units.php'],
            ['title' => 'Medals', 'url' => '/Armis2/admin_branch/reports_medals.php'],
        ]
    ],
];

include dirname(__DIR__) . '/shared/header.php';
include dirname(__DIR__) . '/shared/sidebar.php';
?>

<!-- DataTables CSS -->

<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/datatables/1.13.6/css/responsive.bootstrap5.min.css">

<!-- jQuery (required for Bootstrap JS) -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
<!-- Bootstrap JS Bundle (includes Popper) -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<div class="content-wrapper with-sidebar">
    <div class="container-fluid">
        <div class="main-content">
            <div class="card shadow-sm">
                <div class="card-header bg-success text-white">
                    <h4 class="mb-0">
                        <i class="fa fa-arrow-up"></i>
                        Staff <?=($actionType === 'demotion') ? 'Demotion' : 'Promotion'?>
                    </h4>
                </div>
                <div class="card-body">
                    <?php if ($success): ?>
                        <div class="alert alert-success">
                            <?php if (!empty($successMessages)): ?>
                                <?php foreach ($successMessages as $msg): ?>
                                    <?= $msg ?>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                    <?php if ($errors): ?>
                        <div class="alert alert-danger">
                            <ul class="mb-0">
                                <?php foreach ($errors as $err): ?>
                                    <li><?=htmlspecialchars($err)?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>

                    <!-- Stepper bar (UX enhancement) -->
                    <?php
                    function stepClass($current, $target) {
                        if ($current > $target) return 'completed';
                        if ($current === $target) return 'active';
                        return '';
                    }

                    $actionType = $_GET['action_type'] ?? '';
                    $step = 1;
                    if (empty($actionType)) {
                        $step = 1; // Choose Action
                        ?>
                        <!-- Step 0: Choose Action (Promotion or Demotion) -->
                        <form class="mb-4" id="actionTypeForm" method="get" autocomplete="off">
                            <div class="row">
                                <div class="col-md-6 mb-2">
                                    <label class="form-label">What action do you want to perform?</label>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="action_type" id="promotionRadio" value="promotion" required <?=($actionType==='promotion')?'checked':''?>>
                                        <label class="form-check-label" for="promotionRadio">Promotion</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="action_type" id="demotionRadio" value="demotion" required <?=($actionType==='demotion')?'checked':''?>>
                                        <label class="form-check-label" for="demotionRadio">Demotion</label>
                                    </div>
                                </div>
                                <div class="col-md-6 mb-2 d-flex align-items-end">
                                    <button type="submit" class="btn btn-primary btn-lg">Continue</button>
                                </div>
                            </div>
                        </form>
                        <?php
                        return;
                    }
                    elseif (empty($currentRankId)) {
                        $step = 2; // Select Rank
                    }
                    elseif ($currentRankId && (!$category || $staffCount <= 0)) {
                        $step = 2; // Still on Select Rank if no staff
                    }
                    elseif ($currentRankId && $category && $staffCount > 0 && empty($nextRankIdValue)) {
                        $step = 3; // Select Staff
                    }
                    elseif (!empty($nextRankIdValue)) {
                        $step = 4; // Details
                    }
                    elseif ($success) {
                        $step = 6; // Success
                    }
                    ?>
                    <div class="mb-3">
                        <ul class="stepper mb-0">
                            <li class="step <?=stepClass($step, 1)?>">
                                <?php if ($step > 1): ?>
                                    <a href="promote_staff.php" style="color:inherit;text-decoration:none;">Choose Action</a>
                                <?php else: ?>
                                    Choose Action
                                <?php endif; ?>
                            </li>
                            <li class="step <?=stepClass($step, 2)?>">
                                <?php if ($step > 2): ?>
                                    <a href="promote_staff.php?action_type=<?=htmlspecialchars($actionType)?>" style="color:inherit;text-decoration:none;">Select Rank</a>
                                <?php else: ?>
                                    Select Rank
                                <?php endif; ?>
                            </li>
                            <li class="step <?=stepClass($step, 3)?>">Select Staff</li>
                            <li class="step <?=stepClass($step, 4)?>">Details</li>
                            <li class="step <?=stepClass($step, 5)?>">Confirm</li>
                            <li class="step <?=stepClass($step, 6)?>">Success</li>
                        </ul>
                    </div>

                    <!-- Step 1: Select current rank -->
                    <form class="mb-4" id="rankForm" method="get" autocomplete="off">
                        <input type="hidden" name="action_type" value="<?=htmlspecialchars($actionType)?>">
                        <div class="row">
                            <div class="col-md-6 mb-2">
                                <label class="form-label">Current Rank Being <?=($actionType === 'demotion') ? 'Demotion' : 'Promotion'?></label>
                                <select name="current_rank" id="current_rank" class="form-select" required>
                                    <option value="">Select Current Rank...</option>
                                    <?php foreach ($ranks as $r): ?>
                                        <option value="<?=$r->id?>" <?=($currentRankId==$r->id)?'selected':''?>><?=$r->abbreviation?> (<?=$r->staff_count?> Personnel)</option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-4 mb-2">
                                <?php if ($staffCount>0 && $currentRank): ?>
                                    <div class="alert alert-info mb-0">
                                        <strong><?=htmlspecialchars($staffCount)?></strong>
                                        staff member<?=($staffCount!=1?'s':'')?> at rank <strong><?=htmlspecialchars($currentRank->abbreviation ?? '')?></strong> found.
                                    </div>
                                <?php elseif ($currentRank): ?>
                                    <div class="alert alert-warning mb-0">
                                        No staff found at rank <strong><?=htmlspecialchars($currentRank->abbreviation ?? '')?></strong>.
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </form>

                    <!-- Step 2: Bulk Promotion/Demotion Form -->
                    <?php if ($currentRankId && $category && $staffCount>0): ?>
                    <form method="post" action="" id="promotionForm" autocomplete="off">
                        <input type="hidden" name="csrf_token" value="<?=htmlspecialchars($csrf_token)?>">
                        <input type="hidden" name="promote_staff" value="1">
                        <input type="hidden" name="current_rank" value="<?=htmlspecialchars($currentRankId)?>">
                        <input type="hidden" id="promotion_type" name="promotion_type" value="<?=htmlspecialchars($actionType)?>">
                        <div class="row mb-3">
                            <div class="col-md-12 mb-2">
                                <label class="form-label">Select Staff Members for <?=($actionType === 'demotion') ? 'Demotion' : 'Promotion'?> *</label>
                                <p class="text-muted">
                                    <i class="fas fa-info-circle me-1"></i>
                                    Staff at rank <strong><?=htmlspecialchars($currentRank->rankAbbr ?? '')?></strong> - 
                                    <span class="badge bg-primary"><?= $staffCount ?> members</span>
                                </p>
                                
                                <!-- Staff Selection Controls -->
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <div>
                                        <button type="button" class="btn btn-sm btn-success" id="selectAllBtn">
                                            <i class="fa fa-check-double"></i> Select All
                                        </button>
                                        <button type="button" class="btn btn-sm btn-warning" id="deselectAllBtn">
                                            <i class="fa fa-times"></i> Deselect All
                                        </button>
                                    </div>
                                </div>
                                
                                <!-- Staff Selection Count -->
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <div class="text-muted">
                                        <strong>Total:</strong> <span id="totalStaffCount">0</span> staff member(s)
                                    </div>
                                    <div class="text-primary">
                                        <strong>Selected:</strong> <span id="selectionCount">0</span> staff member(s)
                                    </div>
                                </div>
                                
                                <!-- Staff Selection Table -->
                                <div class="table-responsive" id="staffTableContainer">
                                    <table class="table table-hover table-sm" id="staffSelectionTable" style="width:100%">
                                        <thead class="table-light">
                                            <tr>
                                                <th style="width: 40px;">
                                                    <input type="checkbox" id="masterCheckbox" class="form-check-input" title="Select/Deselect All">
                                                </th>
                                                <th>Service No.</th>
                                                <th>Rank</th>
                                                <th>Name</th>
                                                <th>Unit</th>
                                                <th>Corps</th>
                                                <th>Status</th>
                                                <th>Last Promoted</th>
                                                <th>Time at Rank</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <!-- DataTables will populate this -->
                                        </tbody>
                                    </table>
                                </div>
                                
                                <!-- Hidden container to store selected staff for form submission -->
                                <div id="selectedStaffInputs"></div>
                            </div>
                        </div>
                        <!-- Profile pop-up enhancement: move modal outside loop -->
                        <?php if ($ENABLE_PROFILE_POPUP): ?>
                        <div id="staffProfileModal" class="modal fade" tabindex="-1" role="dialog" aria-labelledby="staffProfileModalLabel" aria-hidden="true">
                            <div class="modal-dialog modal-lg" role="document">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title" id="staffProfileModalLabel">Staff Profile</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                    </div>
                                    <div class="modal-body" id="staffProfileContent">
                                        <!-- Filled by JS -->
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" id="closeProfileModal">Close</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                        <div id="staffDetailsPanel"></div>
                        <div class="row mb-3">
                            <!-- Promotion/Reversion and Next/Previous Rank fields removed; details now shown in staff card -->
                            <div class="col-md-4 mb-2">
                                <label class="form-label">Effective Date of <?=($actionType === 'demotion') ? 'Demotion' : 'Promotion'?> *</label>
                                <?php
                                $today = date('Y-m-d');
                                $minDate = date('Y-m-d', strtotime('-2 months'));
                                ?>
                                <input type="date" name="promotion_date" id="promotion_date" class="form-control" required value="<?=htmlspecialchars($_POST['promotion_date'] ?? $today)?>" min="<?=$minDate?>" max="<?=$today?>">
                            </div>
                        </div>
                        <!-- Hidden next rank fields for backend validation -->
                        <input type="hidden" id="next_rank" name="next_rank" value="<?=htmlspecialchars($nextRankIdValue)?>">
                        <input type="hidden" id="next_rank_display" name="next_rank_display" value="<?=htmlspecialchars($nextRankName)?>">
                        <!-- Bulk authority and remark fields removed -->
                        <div class="text-end">
                            <button type="button" id="showConfirmModal" class="btn btn-primary px-5 py-2" aria-label="Review and confirm promotion/demotion" disabled>
                                <i class="fa fa-arrow-up"></i> <?=($actionType === 'demotion') ? 'Demote' : 'Promote'?>
                            </button>
                            <?php if ($ENABLE_EXPORT_REPORT): ?>
                            <div class="mt-2 text-start">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="export_report" id="export_report" value="1" checked>
                                    <label class="form-check-label" for="export_report">
                                        Generate downloadable promotion report
                                    </label>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                        <div class="modal fade" id="confirmModal" tabindex="-1" aria-labelledby="confirmModalLabel" aria-hidden="true">
                        <div class="modal-dialog modal-xl">
                            <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title" id="confirmModalLabel">Confirm <?=($actionType === 'demotion') ? 'Demotion' : 'Promotion'?></h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body">
                                <div id="confirmSummary"></div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                <button type="button" id="confirmSubmitBtn" class="btn btn-primary">Confirm</button>
                            </div>
                            </div>
                        </div>
                        </div>
                        <!-- Bulk confirmation modal -->
                        <div class="modal fade" id="bulkConfirmModal" tabindex="-1">
                            <div class="modal-dialog">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title">Bulk <?=($actionType === 'demotion') ? 'Demotion' : 'Promotion'?> Confirmation</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                    </div>
                                    <div class="modal-body">
                                        <p>You are about to <?=($actionType === 'demotion') ? 'demote' : 'promote'?> <?=count($_POST['selected_staff']??[])?> staff. Are you sure?</p>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                        <button type="button" id="bulkConfirmSubmitBtn" class="btn btn-primary">Yes, Confirm</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </form>
                    <?php elseif ($currentRankId && !$category): ?>
                        <div class="alert alert-warning">No valid Officer or NCO rank selected.</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.stepper { list-style: none; padding: 0; display: flex; gap: 10px; }
.step { padding: 4px 10px; border-radius: 12px; background: #eee; color: #333; }
.step.active { background: #28a745; color: #fff; font-weight: bold; }

/* DataTables Custom Styling - Matching appointments.php */
#staffSelectionTable {
    font-size: 0.9rem;
}

#staffSelectionTable thead th {
    background-color: #f8f9fa;
    font-weight: 600;
    border-bottom: 2px solid #dee2e6;
    padding: 12px 8px;
}

#staffSelectionTable tbody tr {
    transition: background-color 0.2s ease;
}

#staffSelectionTable tbody tr:hover {
    background-color: #f1f3f5;
    cursor: pointer;
}

/* Selected row styling - Matching appointments.php */
#staffSelectionTable tbody tr.selected {
    background-color: #cfe2ff !important;
    border-left: 4px solid #0d6efd !important;
}

#staffSelectionTable tbody tr.selected:hover {
    background-color: #b6d4fe !important;
}

.staff-checkbox {
    cursor: pointer;
    width: 18px;
    height: 18px;
}

#masterCheckbox {
    cursor: pointer;
    width: 18px;
    height: 18px;
}

/* Loading state */
#staffTableContainer.loading {
    position: relative;
    opacity: 0.6;
    pointer-events: none;
}

#staffTableContainer.loading::after {
    content: 'Loading staff data...';
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    background: rgba(255, 255, 255, 0.95);
    padding: 20px 40px;
    border-radius: 8px;
    box-shadow: 0 4px 6px rgba(0,0,0,0.1);
    font-weight: 600;
    color: #0d6efd;
}

/* Mobile responsive - Matching appointments.php */
@media (max-width: 768px) {
    #staffSelectionTable {
        font-size: 0.8rem;
    }
    
    #staffSelectionTable thead th,
    #staffSelectionTable tbody td {
        padding: 0.5rem 0.25rem;
    }
    
    /* Hide less important columns on mobile */
    #staffSelectionTable th:nth-child(6),
    #staffSelectionTable td:nth-child(6),
    #staffSelectionTable th:nth-child(7),
    #staffSelectionTable td:nth-child(7),
    #staffSelectionTable th:nth-child(8),
    #staffSelectionTable td:nth-child(8) {
        display: none;
    }
}
</style>

<!-- Scripts moved to the end of body for better loading order -->
<!-- Global data for JavaScript -->
<script>
    // Make ranks data available globally
    window.ranksDataFromServer = <?= json_encode($ranks) ?>;
    window.rankCategory = <?= json_encode($category ?? '') ?>;
    window.currentRankLevel = <?= json_encode($currentRankLevel ?? null) ?>;
    window.currentRankId = <?= json_encode($currentRankId ?? null) ?>;
    window.eligibleStaff = <?= json_encode($eligibleStaff ?? []) ?>;
    window.nextRankAbbr = <?= json_encode($nextRankAbbr) ?>;
    window.authorityText = <?= json_encode($authorityText) ?>;
    window.actionType = <?= json_encode($promotionType) ?>;
    console.log('Ranks data loaded:', window.ranksDataFromServer.length, 'ranks');
    console.log('Current rank level:', window.currentRankLevel);
    console.log('Current rank ID:', window.currentRankId);
    console.log('Eligible staff loaded:', window.eligibleStaff.length, 'members');
    console.log('Next rank abbreviation:', window.nextRankAbbr);
</script>

<!-- Core library scripts with integrity checks and fallbacks -->
<script src="https://cdn.jsdelivr.net/npm/jquery@3.6.0/dist/jquery.min.js"></script>

<!-- DataTables CSS (loaded in head via shared/header.php, but include again if needed) -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/datatables.net-bs5@1.13.6/css/dataTables.bootstrap5.min.css">

<!-- DataTables Scripts -->
<script src="https://cdn.jsdelivr.net/npm/datatables.net@1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/datatables.net-bs5@1.13.6/js/dataTables.bootstrap5.min.js"></script>

<!-- Bootstrap Bundle -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

<!-- Flatpickr for date picking -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        var today = new Date();
        var minDate = new Date();
        minDate.setMonth(today.getMonth() - 2);
        // If today is 17 Oct 2025, minDate is 17 Aug 2025
        flatpickr('#promotion_date', {
            dateFormat: 'Y-m-d',
            minDate: minDate,
            maxDate: today,
            disableMobile: true // Always use Flatpickr UI
        });
    });
</script>


<!-- Chart.js CDN (required for dashboard charts) -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<!-- External promotion module (optional) -->
<script src="js/promote_staff.js"></script>

<script>
    // Initialize app immediately after all libraries are loaded
    $(document).ready(function() {
        console.log('✅ Document ready, all libraries loaded');
        console.log('jQuery:', typeof jQuery !== 'undefined' ? 'Available' : 'Missing');
        console.log('Bootstrap:', typeof bootstrap !== 'undefined' ? 'Available' : 'Missing');  
        console.log('DataTable:', typeof $.fn.DataTable !== 'undefined' ? 'Available' : 'Missing');
        
        if (typeof $.fn.DataTable === 'undefined') {
            console.error('❌ DataTables is not loaded!');
            return;
        }
                    
                    // Global variables
                    const staffDetailsCache = {};
                    window.staffDetailsCache = staffDetailsCache; // Make it globally accessible
                    let staffSearchLoading = false;
                    
                    // Auto-submit rank form on change - attach directly
                    console.log('Setting up rank change handler');
                    console.log('Looking for element #current_rank:', $('#current_rank').length > 0 ? 'FOUND' : 'NOT FOUND');
                    
                    $(document).on('change', '#current_rank', function() {
                        const rankId = $(this).val();
                        console.log('🔔 Rank dropdown changed! New value:', rankId);
                        if (rankId) {
                            console.log('✅ Submitting rankForm to server...');
                            const form = $('#rankForm').get(0);
                            console.log('Form element:', form);
                            if (form) {
                                form.submit();
                            } else {
                                console.error('❌ Form element not found!');
                            }
                        } else {
                            console.log('⚠️ No rank selected (empty value)');
                        }
                    });
                    
                    // Form handlers initialization function (for other handlers)
                    function initFormHandlers() {
                        console.log('initFormHandlers called - additional form handlers');
                        // Other form handlers can go here
                    }
                    
                    // Calculate and update next rank based on promotion type
                    function updateNextRank() {
                        const promotionType = $('#promotion_type').val();
                        const currentRankLevel = window.currentRankLevel;
                        const allRanks = window.ranksDataFromServer || [];
                        
                        if (!promotionType || !currentRankLevel) {
                            $('#next_rank_display').val('');
                            $('#next_rank').val('');
                            console.log('⚠️ Missing required data for next rank calculation');
                            return;
                        }
                        
                        // Find current rank to get its category (same as external JS)
                        let rankCategory = '';
                        for (let i = 0; i < allRanks.length; i++) {
                            if (parseInt(allRanks[i].level) === currentRankLevel) {
                                rankCategory = allRanks[i].category;
                                break;
                            }
                        }
                        
                        console.log('🎯 Calculating next rank:', {
                            promotionType,
                            currentRankLevel,
                            rankCategory,
                            availableRanks: allRanks.length
                        });
                        
                        if (!rankCategory) {
                            $('#next_rank_display').val('');
                            $('#next_rank').val('');
                            console.log('⚠️ Could not find category for current rank level:', currentRankLevel);
                            return;
                        }
                        
                        let nextRank = null;
                        let targetLevel = null;
                        
                    if (promotionType === 'promotion') {
                        // For promotion, find next higher rank (lower level number) in same category
                        // Target level is currentLevel - 1
                        targetLevel = currentRankLevel - 1;
                        console.log('🔍 Looking for promotion: current level', currentRankLevel, '→ target level', targetLevel);
                        allRanks.forEach(rank => {
                            if (rank.category === rankCategory && parseInt(rank.level) === targetLevel) {
                                nextRank = rank;
                                console.log('✅ Found matching rank:', rank.name, 'at level', rank.level);
                            }
                        });
                    } else if (promotionType === 'reversion') {
                        // For reversion/demotion, find next lower rank (higher level number) in same category
                        // Target level is currentLevel + 1
                        targetLevel = currentRankLevel + 1;
                        console.log('🔍 Looking for reversion: current level', currentRankLevel, '→ target level', targetLevel);
                        allRanks.forEach(rank => {
                            if (rank.category === rankCategory && parseInt(rank.level) === targetLevel) {
                                nextRank = rank;
                                console.log('✅ Found matching rank:', rank.name, 'at level', rank.level);
                            }
                        });
                    }                        if (nextRank) {
                            $('#next_rank_display').val(nextRank.name);
                            $('#next_rank').val(nextRank.id);
                            console.log('✅ Next rank set to:', nextRank.name, '(ID:', nextRank.id + ')');
                        } else {
                            $('#next_rank_display').val('No rank available');
                            $('#next_rank').val('');
                            console.log('⚠️ No next rank found');
                        }
                    }
                    
                    // Staff panel rendering function
                    function renderStaffPanels(selected) {
                        console.log('🎨 renderStaffPanels called with:', selected);
                        $('#staffDetailsPanel').empty();
                        
                        if (!selected || selected.length === 0) {
                            console.log('⚠️ No staff selected, clearing panels');
                            return;
                        }
                        
                        console.log('✅ Rendering panels for', selected.length, 'staff members');
                        
                        selected.forEach(svcNo => {
                            // Find staff data from the DataTable
                            const staffData = window.eligibleStaff && Array.isArray(window.eligibleStaff) 
                                ? window.eligibleStaff.find(staff => staff.service_number === svcNo) 
                                : null;
                            
                            // Format name as: LAST NAME First Name (matching appointments.php)
                            const staffName = staffData 
                                ? `${(staffData.last_name || '').toUpperCase()} ${staffData.first_name || ''}` 
                                : svcNo;
                            
                            const unitInfo = staffData && staffData.unit_name ? staffData.unit_name : 'N/A';
                            const currentRankAbbr = staffData && staffData.rank_abbreviation ? staffData.rank_abbreviation : '';
                            const nextRankAbbr = window.nextRankAbbr || '';
                            const isDemotion = (window.actionType === 'demotion');
                            const actionTypeText = isDemotion ? 'Demoted' : 'Promoted';
                            const actionPhrase = isDemotion ? 'Demoted to' : 'Promoted to';
                            const authorityText = window.authorityText || '';

                            const militaryStatement = `<strong>Authority:</strong> ${authorityText ? authorityText : 'N/A'}<br>
                                <strong>Action:</strong> ${currentRankAbbr} ${actionTypeText} to <span class='badge bg-secondary'>${nextRankAbbr ? nextRankAbbr : 'N/A'}</span>`;

                            const panel = $(`
                                <div class="card mb-3 staff-detail-card" data-svcno="${svcNo}">
                                    <div class="card-header bg-light">
                                        <h5 class="mb-0">
                                            <span class="badge bg-primary me-2">${svcNo}</span>
                                            ${staffName}
                                            <small class="text-muted ms-2">(${currentRankAbbr} ${actionTypeText} to <span class='badge bg-secondary'>${nextRankAbbr ? nextRankAbbr : 'N/A'}</span>)</small>
                                        </h5>
                                    </div>
                                    <div class="card-body">
                                        <div class="row align-items-center">
                                            <div class="col-md-6 mb-2">
                                                <div class="form-control-plaintext">${militaryStatement}</div>
                                            </div>
                                            <div class="col-md-6 mb-2">
                                                <label class="form-label mb-1">Current Unit</label>
                                                <div class="form-control-plaintext">
                                                    <span class="badge bg-info">${unitInfo}</span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            `);
                    
                            $('#staffDetailsPanel').append(panel);
                            console.log('✅ Panel added for:', svcNo, staffName);
                        });
                        
                        console.log('📝 All panels rendered. Total:', selected.length);
                        
                        // Enable/disable promote button based on authority fields
                        enablePromoteButton();
                        $('.authority-input, .remark-input').on('input', enablePromoteButton);
                        
                        console.log('✅ renderStaffPanels complete');
                    }
                    
                    // Enable promote button function
                    function enablePromoteButton() {
                        let allFilled = true;
                        $('.authority-input').each(function() { 
                            if (!$(this).val()) allFilled = false; 
                        });
                        $('#showConfirmModal').prop('disabled', !allFilled);
                    }
                    
                    // Helper function to get current rank ID consistently
                    function getCurrentRankId() {
                        return $('#current_rank').val() || '';
                    }
                    
                    // Initialize Staff DataTable
                    console.log('Initializing DataTable with', window.eligibleStaff.length, 'staff members');
                    
                    const staffTable = $('#staffSelectionTable').DataTable({
                        processing: true,
                        serverSide: false,
                        pageLength: 25,
                        lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, "All"]],
                        searching: true,
                        ordering: true,
                        info: true,
                        responsive: {
                            details: {
                                type: 'column',
                                target: 'tr'
                            }
                        },
                        autoWidth: false,
                        data: window.eligibleStaff || [],
                        order: [[8, 'asc']], // Sort by Time at Rank (longest serving first)
                        columns: [
                            {
                                data: null,
                                orderable: false,
                                searchable: false,
                                width: "40px",
                                className: "text-center",
                                responsivePriority: 1, // Always visible
                                render: function(data, type, row) {
                                    const disabled = row.eligible === false ? ' disabled title="Not eligible for promotion"' : '';
                                    return '<input type="checkbox" class="form-check-input staff-checkbox" value="' + row.service_number + '"' + disabled + '>';
                                }
                            },
                            { 
                                data: 'service_number', 
                                title: 'Service No.', 
                                width: "110px",
                                responsivePriority: 2 // High priority
                            },
                            { 
                                data: 'rank_abbreviation', 
                                title: 'Rank', 
                                width: "100px",
                                responsivePriority: 4,
                                render: function(data, type, row) {
                                    // Use abbreviation from row object directly
                                    const rankDisplay = row.rank_abbreviation || row.rank_name || data || 'N/A';
                                    return '<span class="badge bg-primary">' + rankDisplay + '</span>';
                                }
                            },
                            { 
                                data: null, 
                                title: 'Name',
                                responsivePriority: 3, // High priority
                                render: function(data, type, row) {
                                    // Helper function for proper title case
                                    function toTitleCase(str) {
                                        if (!str) return '';
                                        return str.trim().split(/\s+/).map(word => {
                                            if (word.length === 0) return '';
                                            // Handle hyphenated names and apostrophes
                                            return word.split('-').map(part => 
                                                part.split("'").map(subpart => 
                                                    subpart.charAt(0).toUpperCase() + subpart.slice(1).toLowerCase()
                                                ).join("'")
                                            ).join('-');
                                        }).join(' ');
                                    }
                                    
                                    const firstName = toTitleCase(row.first_name || '');
                                    const lastName = toTitleCase(row.last_name || '');
                                    
                                    let fullName = '';
                                    if (firstName && lastName) {
                                        fullName = firstName + ' ' + lastName;
                                    } else if (lastName) {
                                        fullName = lastName;
                                    } else if (firstName) {
                                        fullName = firstName;
                                    } else {
                                        fullName = 'N/A';
                                    }
                                    
                                    let name = '<div class="fw-bold">' + fullName + '</div>';
                                    if (row.eligible === false) {
                                        name += ' <i class="fas fa-exclamation-triangle text-warning" title="' + (row.eligibilityReasons ? row.eligibilityReasons.join(', ') : 'Not eligible') + '"></i>';
                                    } else if (row.warnings && row.warnings.length > 0) {
                                        name += ' <i class="fas fa-info-circle text-info" title="' + row.warnings.join(', ') + '"></i>';
                                    }
                                    return name;
                                }
                            },
                            { 
                                data: 'unit_name', 
                                title: 'Unit', 
                                width: "140px",
                                responsivePriority: 6,
                                defaultContent: 'N/A'
                            },
                            { 
                                data: 'corps', 
                                title: 'Corps',
                                width: "100px",
                                responsivePriority: 8,
                                defaultContent: 'N/A'
                            },
                            { 
                                data: null,
                                title: 'Status',
                                width: "100px",
                                responsivePriority: 9,
                                render: function(data, type, row) {
                                    const status = row.svcStatus || 'Active';
                                    const statusClass = status === 'Active' ? 'bg-success' : 'bg-secondary';
                                    return '<span class="badge ' + statusClass + '">' + status + '</span>';
                                }
                            },
                            { 
                                data: 'rank_date', 
                                title: 'Last Promoted', 
                                width: "140px",
                                responsivePriority: 10,
                                render: function(data, type, row) {
                                    // If no rank_date, show no record
                                    if (!data) {
                                        return '<small class="text-muted">No record</small>';
                                    }
                                    
                                    // Format the date nicely
                                    const rankDate = new Date(data);
                                    const formattedDate = rankDate.toLocaleDateString('en-GB', {day: '2-digit', month: 'short', year: 'numeric'});
                                    
                                    // Calculate days since achieving current rank
                                    const now = new Date();
                                    const days = Math.floor((now - rankDate) / (1000 * 60 * 60 * 24));
                                    
                                    let badge = 'secondary';
                                    let label = '';
                                    if (days < 180) {
                                        badge = 'warning';
                                        label = days + ' days ago';
                                    } else if (days < 365) {
                                        badge = 'info';
                                        label = Math.floor(days / 30) + ' months ago';
                                    } else {
                                        badge = 'success';
                                        const years = Math.floor(days / 365);
                                        label = years + ' year' + (years > 1 ? 's' : '') + ' ago';
                                    }
                                    
                                    return '<span class="badge bg-' + badge + '" title="' + formattedDate + '">' + label + '</span>';
                                }
                            },
                            { 
                                data: 'months_at_rank', 
                                title: 'Time at Rank', 
                                width: "110px",
                                responsivePriority: 5,
                                render: function(data, type, row) {
                                    // Use calculated value from database, default to 0 if null
                                    const months = Math.floor(data ?? 0);
                                    const years = Math.floor(months / 12);
                                    const remainingMonths = months % 12;
                                    
                                    // Determine badge color
                                    let badge = 'secondary';
                                    if (months >= 12) {
                                        badge = 'success'; // Eligible
                                    } else if (months >= 6) {
                                        badge = 'warning'; // Getting close
                                    } else {
                                        badge = 'danger'; // Too soon
                                    }
                                    
                                    // Format display text
                                    let text = '';
                                    if (years > 0) {
                                        text = years + 'y ' + remainingMonths + 'm';
                                    } else if (months > 0) {
                                        text = months + ' month' + (months !== 1 ? 's' : '');
                                    } else {
                                        text = 'New';
                                    }
                                    
                                    return '<span class="badge bg-' + badge + '">' + text + '</span>';
                                }
                            }
                        ],
                        language: {
                            emptyTable: "Select a rank to view eligible staff for promotion",
                            zeroRecords: "No staff found matching search criteria",
                            info: "Showing _START_ to _END_ of _TOTAL_ staff members",
                            infoEmpty: "No staff members available",
                            infoFiltered: "(filtered from _MAX_ total staff members)",
                            search: "Search staff:",
                            lengthMenu: "Show _MENU_ staff per page"
                        },
                        dom: '<"row"<"col-sm-12 col-md-6"l><"col-sm-12 col-md-6"f>>' +
                             '<"row"<"col-sm-12"tr>>' +
                             '<"row"<"col-sm-12 col-md-5"i><"col-sm-12 col-md-7"p>>',
                        drawCallback: function() {
                            // Update selection counter after table redraw
                            updateSelectionCounter();
                            updateMasterCheckbox();
                            updateSelectedStaffInputs();
                        }
                    });

                    // Handle individual checkbox changes
                    $('#staffSelectionTable').on('change', '.staff-checkbox', function() {
                        updateSelectionCounter();
                        updateMasterCheckbox();
                        updateSelectedStaffInputs();
                        
                        const isChecked = $(this).is(':checked');
                        const serviceNumber = $(this).val();
                        
                        if (isChecked) {
                            console.log('Staff selected:', serviceNumber);
                        } else {
                            console.log('Staff unselected:', serviceNumber);
                        }
                        
                        // Render staff panels for selected staff
                        const selectedServiceNumbers = $('.staff-checkbox:checked').map(function() {
                            return $(this).val();
                        }).get();
                        
                        if (typeof renderStaffPanels === 'function') {
                            renderStaffPanels(selectedServiceNumbers);
                        }
                    });

                    // Handle master checkbox (select/deselect all)
                    $('#masterCheckbox').on('change', function() {
                        const isChecked = $(this).is(':checked');
                        $('.staff-checkbox').prop('checked', isChecked);
                        updateSelectionCounter();
                        updateSelectedStaffInputs();
                        
                        console.log('Master checkbox toggled:', isChecked);
                        
                        // Render staff panels for selected staff
                        const selectedServiceNumbers = $('.staff-checkbox:checked').map(function() {
                            return $(this).val();
                        }).get();
                        
                        if (typeof renderStaffPanels === 'function') {
                            renderStaffPanels(selectedServiceNumbers);
                        }
                    });

                    // Update master checkbox state based on individual checkboxes
                    function updateMasterCheckbox() {
                        const totalCheckboxes = $('.staff-checkbox').length;
                        const checkedCheckboxes = $('.staff-checkbox:checked').length;
                        
                        if (totalCheckboxes === 0) {
                            $('#masterCheckbox').prop('indeterminate', false).prop('checked', false);
                        } else if (checkedCheckboxes === totalCheckboxes) {
                            $('#masterCheckbox').prop('indeterminate', false).prop('checked', true);
                        } else if (checkedCheckboxes > 0) {
                            $('#masterCheckbox').prop('indeterminate', true);
                        } else {
                            $('#masterCheckbox').prop('indeterminate', false).prop('checked', false);
                        }
                    }

                    // Update hidden inputs for form submission
                    function updateSelectedStaffInputs() {
                        const selectedServiceNumbers = $('.staff-checkbox:checked').map(function() {
                            return $(this).val();
                        }).get();
                        
                        // Clear existing hidden inputs
                        $('#selectedStaffInputs').empty();
                        
                        // Add hidden input for each selected staff member
                        selectedServiceNumbers.forEach(function(serviceNumber) {
                            $('#selectedStaffInputs').append(
                                '<input type="hidden" name="selected_staff[]" value="' + serviceNumber + '">'
                            );
                        });
                        
                        console.log('Updated form inputs for:', selectedServiceNumbers);
                    }
                    
                    // Update selection counter function (matching appointments.php)
                    function updateSelectionCounter() {
                        const selectedCount = $('.staff-checkbox:checked').length;
                        const totalCount = $('.staff-checkbox').length;
                        
                        $('#selectionCount').text(selectedCount);
                        $('#totalStaffCount').text(totalCount);
                        
                        // Update master checkbox state
                        updateMasterCheckbox();
                    }
                    
                    // Select All button handler (matching appointments.php)
                    $('#selectAllBtn').on('click', function() {
                        $('#masterCheckbox').prop('checked', true).trigger('change');
                    });
                    
                    // Deselect All button handler (matching appointments.php)
                    $('#deselectAllBtn').on('click', function() {
                        $('#masterCheckbox').prop('checked', false).trigger('change');
                    });
                    
                    // Handle row click to toggle selection (matching appointments.php)
                    $('#staffSelectionTable').on('click', 'tbody tr', function(e) {
                        if (!$(e.target).is('input[type="checkbox"], a, button')) {
                            const checkbox = $(this).find('.staff-checkbox');
                            if (!checkbox.prop('disabled')) {
                                checkbox.prop('checked', !checkbox.prop('checked')).trigger('change');
                            }
                        }
                    });
                    
                    // Add selected row highlighting (matching appointments.php)
                    $('#staffSelectionTable').on('change', '.staff-checkbox', function() {
                        const row = $(this).closest('tr');
                        if ($(this).is(':checked')) {
                            row.addClass('selected');
                        } else {
                            row.removeClass('selected');
                        }
                    });
                    
                    // Initialize total count on page load
                    $('#totalStaffCount').text(window.eligibleStaff.length);

                    // Update selection counter
                    function updateSelectionCounter() {
                        const selectedCount = $('.staff-checkbox:checked').length;
                        const totalCount = $('.staff-checkbox').length;
                        
                        if (selectedCount > 0) {
                            $('#search_debug').html('<div class="alert alert-success mt-2 p-2">✅ Selected ' + selectedCount + ' of ' + totalCount + ' staff member(s) for promotion.</div>');
                        } else if (totalCount > 0) {
                            $('#search_debug').html('<div class="alert alert-info mt-2 p-2">💡 Select staff members for promotion using the checkboxes.</div>');
                        } else {
                            $('#search_debug').html('<div class="alert alert-info mt-2 p-2">💡 Select a rank to view eligible staff for promotion.</div>');
                        }
                        
                        // Update master checkbox and form inputs
                        updateMasterCheckbox();
                        updateSelectedStaffInputs();
                    }
                    
                    // Function to load all staff at a rank for promotions
                    function loadStaffForRank(rankId) {
                        if (!rankId) {
                            // Clear table when no rank selected
                            staffTable.clear().draw();
                            updateSelectionCounter();
                            return;
                        }
                        
                        console.log('Loading staff for rank ID:', rankId);
                        
                        // Show loading state
                        $('#search_debug').html('<div class="alert alert-info mt-2 p-2">🔄 Loading staff for selected rank...</div>');
                        
                        // Load staff data for the selected rank
                        $.ajax({
                            url: 'search_staff_minimal.php',
                            method: 'GET',
                            dataType: 'json',
                            data: {
                                rank_id: rankId,
                                load_all: true // Flag to load all staff for this rank
                            },
                            success: function(data) {
                                console.log('Staff data loaded:', data);
                                
                                if (Array.isArray(data) && data.length > 0) {
                                    // Clear table and add new data
                                    staffTable.clear();
                                    staffTable.rows.add(data);
                                    staffTable.draw();
                                    
                                    // Store staff data globally for easy access
                                    window.eligibleStaff = data;
                                    
                                    $('#search_debug').html('<div class="alert alert-success mt-2 p-2">✅ Loaded ' + data.length + ' eligible staff member(s). Select staff for promotion using checkboxes.</div>');
                                } else {
                                    // No staff found
                                    staffTable.clear().draw();
                                    $('#search_debug').html('<div class="alert alert-warning mt-2 p-2">⚠️ No eligible staff found for the selected rank.</div>');
                                }
                                
                                updateSelectionCounter();
                            },
                            error: function(xhr, status, error) {
                                console.error('Error loading staff:', error);
                                console.error('XHR status:', xhr.status);
                                console.error('Response:', xhr.responseText);
                                
                                staffTable.clear().draw();
                                
                                let errorMsg = 'Error loading staff: ' + error;
                                
                                // Try to parse JSON error response
                                try {
                                    const errorData = JSON.parse(xhr.responseText);
                                    if (errorData.message) {
                                        errorMsg = 'Database Error: ' + errorData.message;
                                    }
                                } catch (e) {
                                    // Not JSON, use status text
                                    if (xhr.status === 500) {
                                        errorMsg = 'Server Error (500). Check PHP error logs for details.';
                                    }
                                }
                                
                                $('#search_debug').html('<div class="alert alert-danger mt-2 p-2">❌ ' + errorMsg + '</div>');
                                updateSelectionCounter();
                            }
                        });
                    }
                    
                    // Handle promotion type change to update next rank
                    $('#promotion_type').on('change', function() {
                        console.log('🔔 Promotion type changed to:', $(this).val());
                        updateNextRank();
                    });
                    
                    // Calculate next rank on page load if promotion type is already selected
                    if ($('#promotion_type').val()) {
                        console.log('📋 Promotion type already selected on page load:', $('#promotion_type').val());
                        updateNextRank();
                    }
                    
                    // Bulk authority application
                    $('#apply_bulk_authority').on('click', function() {
                        const bulkValue = $('#bulk_authority').val().trim();
                        if (bulkValue) {
                            $('.authority-input').val(bulkValue);
                            console.log('✅ Applied bulk authority to all staff:', bulkValue);
                            // Trigger validation
                            if (typeof enablePromoteButton === 'function') {
                                enablePromoteButton();
                            }
                        } else {
                            console.log('⚠️ Bulk authority field is empty');
                        }
                    });
                    
                    // Bulk remark application
                    $('#apply_bulk_remark').on('click', function() {
                        const bulkValue = $('#bulk_remark').val().trim();
                        if (bulkValue) {
                            $('.remark-input').val(bulkValue);
                            console.log('✅ Applied bulk remark to all staff:', bulkValue);
                        } else {
                            console.log('⚠️ Bulk remark field is empty');
                        }
                    });
                    
                    // Show confirmation modal with staff summary
                    $('#showConfirmModal').on('click', function() {
                        const promotionType = $('#promotion_type').val();
                        const nextRankName = $('#next_rank_display').val();
                        const promotionDate = $('input[name="promotion_date"]').val();
                        const selectedServiceNumbers = $('.staff-checkbox:checked').map(function() {
                            return $(this).val();
                        }).get();
                        
                        // Build summary HTML
                        let summaryHtml = '<div class="table-responsive">';
                        summaryHtml += '<h6 class="mb-3">';
                        summaryHtml += promotionType === 'promotion' ? '📈 Promotion' : '📉 Reversion/Demotion';
                        summaryHtml += ' to <strong>' + nextRankAbbr + '</strong>';
                        summaryHtml += ' effective <strong>' + promotionDate + '</strong></h6>';
                        summaryHtml += '<table class="table table-sm table-bordered">';
                        summaryHtml += '<thead class="table-light">';
                        summaryHtml += '<tr><th>Service No.</th><th>Name</th><th>Current Rank</th><th>Unit</th><th>Authority</th></tr>';
                        summaryHtml += '</thead><tbody>';
                        
                        selectedServiceNumbers.forEach(svcNo => {
                            const staffData = window.eligibleStaff && Array.isArray(window.eligibleStaff) 
                                ? window.eligibleStaff.find(staff => staff.service_number === svcNo) 
                                : null;
                            const staffName = staffData 
                                ? `${(staffData.last_name || '').toUpperCase()} ${staffData.first_name || ''}` 
                                : svcNo;
                            const rankAbbr = staffData && staffData.rank_abbreviation ? staffData.rank_abbreviation : '';
                            const unitName = staffData && staffData.unit_name ? staffData.unit_name : 'N/A';
                            const authority = $('input[name="promotion_authority[' + svcNo + ']"]').val() || '';
                            const remark = $('input[name="promotion_remark[' + svcNo + ']"]').val() || '';
                            summaryHtml += '<tr>';
                            summaryHtml += '<td>' + svcNo + '</td>';
                            summaryHtml += '<td>' + staffName + '</td>';
                            summaryHtml += '<td>' + rankAbbr + '</td>';
                            summaryHtml += '<td>' + unitName + '</td>';
                            summaryHtml += '<td>' + authorityText + '</td>';
                            summaryHtml += '</tr>';
                        });
                        
                        summaryHtml += '</tbody></table></div>';
                        summaryHtml += '<div class="alert alert-warning mt-3"><i class="fa fa-exclamation-triangle"></i> ';
                        summaryHtml += 'Please review carefully. This action will update <strong>' + selectedServiceNumbers.length + '</strong> staff record(s).</div>';
                        
                        $('#confirmSummary').html(summaryHtml);
                        
                        // Show the modal
                        const confirmModal = new bootstrap.Modal(document.getElementById('confirmModal'));
                        confirmModal.show();
                    });
                    
                    // Handle confirm button in modal
                    $('#confirmSubmitBtn').on('click', function() {
                        console.log('✅ User confirmed promotion/demotion');
                        // Close modal
                        bootstrap.Modal.getInstance(document.getElementById('confirmModal')).hide();
                        // Submit the form
                        $('#promotionForm').submit();
                    });
                    
                    // Enable tooltips
                    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
                    tooltipTriggerList.forEach(function (tooltipTriggerEl) {
                        new bootstrap.Tooltip(tooltipTriggerEl);
                    });
                    
                    // Run initial UI setup
                    if (typeof enablePromoteButton === 'function') {
                        enablePromoteButton();
                    }
                    
                    // Setup staff selection on page load if rank is selected
                    <?php if ($currentRankId && $currentRank): ?>
                    console.log('Setting up staff selection for rank <?=json_encode($currentRankId)?>');
                    $('#search_debug').removeClass('d-none')
                        .html('<div class="alert alert-info mt-2 p-2">🔄 <strong>Rank Selected:</strong> <?=htmlspecialchars($currentRank->name ?? 'Unknown')?> (ID: <?=$currentRankId?>). Staff table will load automatically.</div>');
                    
                    setTimeout(function() {
                        if (typeof loadStaffForRank === 'function') {
                            console.log('✅ Loading staff for rank <?=json_encode($currentRankId)?>');
                            loadStaffForRank(<?=json_encode($currentRankId)?>);
                        }
                    }, 500);
                    <?php else: ?>
                    console.log('No current rank selected');
                    <?php endif; ?>
                    
                    console.log('Promotion application initialization complete');
    });
</script>

<?php include dirname(__DIR__) . '/shared/footer.php'; ?>