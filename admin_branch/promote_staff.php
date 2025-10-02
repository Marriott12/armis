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
        $filename = "{$actionType}_{$fromRank->name}_to_{$toRank->name}_{$timestamp}.csv";
        $filepath = $reportsDir . '/' . $filename;
        
        // Prepare the CSV file
        $file = fopen($filepath, 'w');
        
        // Write headers
        fputcsv($file, [
            'Service Number',
            'Rank',
            'Name',
            'Unit',
            'Action',
            'From Rank',
            'To Rank',
            'Effective Date',
            'Authority',
            'Remarks'
        ]);
        
        // Write data for each staff member
        $placeholders = rtrim(str_repeat('?,', count($serviceNumbers)), ',');
        $stmt = $pdo->prepare("
            SELECT s.service_number, r.name as rank_name, s.first_name, s.last_name, 
                   u.name as unit_name
            FROM staff s
            LEFT JOIN ranks r ON s.rank_id = r.id
            LEFT JOIN units u ON s.unit_id = u.id
            WHERE s.service_number IN ($placeholders)
        ");
        $stmt->execute($serviceNumbers);
        
        while ($staff = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $effectiveDateFormatted = date('d M Y', strtotime($effectiveDate));
            
            fputcsv($file, [
                $staff['service_number'],
                $staff['rank_name'],
                $staff['first_name'] . ' ' . $staff['last_name'],
                $staff['unit_name'],
                $promotionType === 'promotion' ? 'Promotion' : 'Reversion',
                $fromRank->name,
                $toRank->name,
                $effectiveDateFormatted,
                'HQ Authority',  // This could be customized
                'Regular ' . ucfirst($promotionType)  // This could be customized
            ]);
        }
        
        fclose($file);
        
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

// Fetch ranks for selection
try {
    $rankStmt = $pdo->query("SELECT id, name, level, category FROM ranks ORDER BY level ASC");
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
        $stmt = $pdo->prepare("SELECT id, name, level, category FROM ranks WHERE id = ?");
        $stmt->execute([$currentRankId]);
        $currentRank = $stmt->fetch(PDO::FETCH_OBJ);
        
        if ($currentRank) {
            // Get category directly from database
            $category = $currentRank->category;
            $currentRankName = $currentRank->name; // Add this for JavaScript fallback
            $currentRankLevel = $currentRank->level; // Set level for JavaScript use
            
            // Fetch staff at the selected rank
            $staffStmt = $pdo->prepare("
                SELECT s.id, s.service_number, s.first_name, s.last_name, s.rank_id, 
                       s.attestDate, s.unit_id,
                       u.name as unit_name
                FROM staff s
                LEFT JOIN units u ON s.unit_id = u.id
                WHERE s.rank_id = ? 
                ORDER BY s.last_name, s.first_name
            ");
            $staffStmt->execute([$currentRankId]);
            $eligibleStaff = $staffStmt->fetchAll(PDO::FETCH_OBJ);
            $staffCount = count($eligibleStaff);
            
            // Mark eligibility for each staff member
            foreach ($eligibleStaff as &$staff) {
                // Default to eligible, can implement business rules here
                $staff->eligible = true;
                $staff->eligibilityReasons = [];
                
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
            $errors[] = "Invalid next rank.";
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
            $stmt = $pdo->prepare("SELECT name FROM ranks WHERE id = ?");
            $stmt->execute([$nextRankId]);
            $nextRankObj = $stmt->fetch(PDO::FETCH_OBJ);
            
            if (!$currentRankObj || !$nextRankObj) {
                $errors[] = "Could not retrieve rank information.";
            } else {
                // Start transaction
                try {
                    $pdo->beginTransaction();
                    $timestamp = date('Y-m-d H:i:s');
                    
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
                        
                        // Get rank details to determine if it's temporal or substantive
                        $newRankName = $nextRankObj->name;
                        $newRankCategory = $nextRankObj->category;
                        
                        // Check if rank name starts with 'Temporal' (case-insensitive)
                        $isTemporal = stripos($newRankName, 'Temporal') === 0;
                        
                        // Prepare the update statement based on rank type
                        if ($isTemporal) {
                            // For temporal ranks: update tempWef and clear subWef
                            $updateStmt = $pdo->prepare("UPDATE staff SET 
                                rank_id = ?, 
                                tempWef = ?,
                                subWef = NULL,
                                category = ?,
                                updated_by = ?,
                                updated_at = ?
                                WHERE id = ?");
                            
                            $updateStmt->execute([
                                $nextRankId,
                                $promotionDate,
                                $newRankCategory,
                                $userId,
                                $timestamp,
                                $staffId
                            ]);
                        } else {
                            // For substantive ranks: update subWef and clear tempWef
                            $updateStmt = $pdo->prepare("UPDATE staff SET 
                                rank_id = ?, 
                                subWef = ?,
                                tempWef = NULL,
                                category = ?,
                                updated_by = ?,
                                updated_at = ?
                                WHERE id = ?");
                            
                            $updateStmt->execute([
                                $nextRankId,
                                $promotionDate,
                                $newRankCategory,
                                $userId,
                                $timestamp,
                                $staffId
                            ]);
                        }
                        
                        // Record the promotion/reversion in the history table
                        $insertStmt = $pdo->prepare("INSERT INTO staff_promotions (
                            staff_id, 
                            current_rank, 
                            date_from, 
                            date_to, 
                            type, 
                            new_rank, 
                            authority, 
                            remark, 
                            created_by, 
                            created_at, 
                            before_json, 
                            user_ip, 
                            user_agent
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                        
                        $insertStmt->execute([
                            $staffId,
                            $currentRankId,
                            date('Y-m-d'),
                            $promotionDate,
                            $promotionType,
                            $nextRankId,
                            $perStaffAuthority[$serviceNumber] ?? '',
                            $perStaffRemark[$serviceNumber] ?? '',
                            $userId,
                            $timestamp,
                            json_encode($beforeStaff),
                            $_SERVER['REMOTE_ADDR'] ?? '',
                            $_SERVER['HTTP_USER_AGENT'] ?? ''
                        ]);
                        
                        // Log the action to the activity log
                        $stmt = $pdo->prepare("SELECT * FROM staff WHERE id = ? LIMIT 1");
                        $stmt->execute([$staffId]);
                        $afterStaff = $stmt->fetch(PDO::FETCH_ASSOC);
                        
                        // Log the promotion/demotion action
                        $actionType = $promotionType === 'promotion' ? 'Promotion' : 'Reversion';
                        $changeDescription = $promotionType === 'promotion' 
                            ? "Promoted from {$currentRankObj->name} to {$nextRankObj->name}"
                            : "Reverted from {$currentRankObj->name} to {$nextRankObj->name}";
                        
                        $logStmt = $pdo->prepare("INSERT INTO staff_activity_log (
                            staff_id, 
                            action, 
                            description, 
                            old_value, 
                            new_value, 
                            created_by, 
                            created_at
                        ) VALUES (?, ?, ?, ?, ?, ?, ?)");
                        
                        $logStmt->execute([
                            $staffId,
                            $actionType,
                            $changeDescription,
                            json_encode($beforeStaff),
                            json_encode($afterStaff),
                            $userId,
                            $timestamp
                        ]);
                        
                        // Also update the staff_rank_history table if it exists
                        try {
                            $historyStmt = $pdo->prepare("INSERT INTO staff_rank_history (
                                staff_id,
                                service_number,
                                rank_id,
                                promotion_date,
                                authority,
                                remarks,
                                created_by,
                                created_at
                            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                            
                            $historyStmt->execute([
                                $staffId,
                                $serviceNumber,
                                $nextRankId,
                                $promotionDate,
                                $perStaffAuthority[$serviceNumber] ?? '',
                                $perStaffRemark[$serviceNumber] ?? '',
                                $userId,
                                $timestamp
                            ]);
                        } catch (Exception $e) {
                            // Log error but continue - non-critical
                            error_log("Could not update rank history: " . $e->getMessage());
                        }
                        
                        $successMessages[] = "$serviceNumber " . 
                            ($promotionType === 'promotion' ? "promoted" : "reverted") . 
                            " from {$currentRankObj->name} to {$nextRankObj->name}";
                    }
                    
                    $pdo->commit();
                    $success = true;
                    
                    // Build a comprehensive success message
                    $totalProcessed = count($selectedStaff);
                    $actionWord = $promotionType === 'promotion' ? 'promoted' : 'reverted';
                    
                    // Add summary message
                    array_unshift($successMessages, "<strong>Successfully $actionWord $totalProcessed staff members from {$currentRankObj->name} to {$nextRankObj->name}.</strong>");
                    
                    // Add date information
                    $formattedDate = date('d M Y', strtotime($promotionDate));
                    array_unshift($successMessages, "<strong>Effective Date:</strong> $formattedDate");
                    
                    // Generate export report if requested
                    if (isset($_POST['export_report']) && $ENABLE_EXPORT_REPORT) {
                        // Implementation for report generation would go here
                        $reportPath = generatePromotionReport($selectedStaff, $currentRankObj, $nextRankObj, $promotionDate, $promotionType);
                        if ($reportPath) {
                            $successMessages[] = "<a href='$reportPath' class='btn btn-sm btn-primary mt-2' download><i class='fas fa-download'></i> Download Promotion Report</a>";
                        }
                    }
                    
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
$nextRankIdValue = '';
if ($currentRank && isset($_POST['promotion_type'])) {
    $promotionType = strtolower(trim($_POST['promotion_type']));
    $currentCategory = $currentRank->category ?? '';
    $currentRankLevel = $currentRank->level ?? null;
    
    if ($promotionType === 'promotion' && $currentRankLevel !== null) {
        foreach ($ranks as $r) {
            // For promotion, find the next higher rank in the same category (lower level number)
            if ($r->level < $currentRankLevel && $r->category === $currentCategory) {
                if ($nextRankObj === null || $r->level > $nextRankObj->level) {
                    $nextRankObj = $r;
                }
            }
        }
    } elseif (in_array($promotionType, ['reversion', 'demotion']) && $currentRankLevel !== null) {
        foreach ($ranks as $r) {
            // For demotion, find the next lower rank in the same category (higher level number)
            if ($r->level > $currentRankLevel && $r->category === $currentCategory) {
                if ($nextRankObj === null || $r->level < $nextRankObj->level) {
                    $nextRankObj = $r;
                }
            }
        }
    }
    if ($nextRankObj) {
        $nextRankName = $nextRankObj->name;
        $nextRankIdValue = $nextRankObj->id;
    }
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

<div class="content-wrapper with-sidebar">
    <div class="container-fluid">
        <div class="main-content">
            <div class="card shadow-sm">
                <div class="card-header bg-success text-white">
                    <h4 class="mb-0"><i class="fa fa-arrow-up"></i> Staff Promotion / Reversion</h4>
                </div>
                <div class="card-body">
                    <?php if ($success): ?>
                        <div class="alert alert-success">
                            Promotion/Demotion successful for all selected staff.
                            <?php if (!empty($successMessages)): ?>
                                <ul class="mb-0">
                                    <?php foreach ($successMessages as $msg): ?>
                                        <li><?=htmlspecialchars($msg)?></li>
                                    <?php endforeach; ?>
                                </ul>
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
                    <div class="mb-3">
                        <ul class="stepper mb-0">
                            <li class="step active">Select Rank</li>
                            <li class="step <?=($currentRankId?'active':'')?>">Select Staff</li>
                            <li class="step <?=($nextRankIdValue?'active':'')?>">Details</li>
                            <li class="step">Confirm</li>
                            <li class="step <?=($success?'active':'')?>">Success</li>
                        </ul>
                    </div>

                    <!-- Step 1: Select current rank -->
                    <form class="mb-4" id="rankForm" method="get" autocomplete="off">
                        <div class="row">
                            <div class="col-md-6 mb-2">
                                <label class="form-label">Current Rank Being Promoted / Reversed *</label>
                                <select name="current_rank" id="current_rank" class="form-select" required>
                                    <option value="">Select Current Rank...</option>
                                    <?php foreach ($ranks as $r): ?>
                                        <option value="<?=$r->id?>" <?=($currentRankId==$r->id)?'selected':''?>><?=$r->name?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-4 mb-2">
                                <?php if ($staffCount>0 && $currentRank): ?>
                                    <div class="alert alert-info mb-0">
                                        <strong><?=htmlspecialchars($staffCount)?></strong>
                                        staff member<?=($staffCount!=1?'s':'')?> at rank <strong><?=htmlspecialchars($currentRank->name ?? '')?></strong> found.
                                    </div>
                                <?php elseif ($currentRank): ?>
                                    <div class="alert alert-warning mb-0">
                                        No staff found at rank <strong><?=htmlspecialchars($currentRank->name ?? '')?></strong>.
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="row">
                        <div class="col-md-12 text-end">
                            <button type="submit" class="btn btn-primary mt-2">Submit Rank Selection</button>
                        </div>
                    </div>
                    </form>

                    <!-- Step 2: Bulk Promotion/Demotion Form -->
                    <?php if ($currentRankId && $category && $staffCount>0): ?>
                    <form method="post" action="" id="promotionForm" autocomplete="off">
                        <input type="hidden" name="csrf_token" value="<?=htmlspecialchars($csrf_token)?>">
                        <input type="hidden" name="promote_staff" value="1">
                        <input type="hidden" name="current_rank" value="<?=htmlspecialchars($currentRankId)?>">
                        <div class="row mb-3">
                            <div class="col-md-12 mb-2">
                                <label class="form-label">Select Staff Members *</label>
                                <select name="selected_staff[]" id="selected_staff" class="form-select select2-bootstrap5" multiple="multiple" required style="width:100%; min-width: 250px;">
                                    <!-- Staff will be available via search -->
                                </select>
                                <small class="text-muted">Search staff at rank <strong><?=htmlspecialchars($currentRank->rankName ?? '')?></strong></small>
                                
                                <!-- Debug section for auto-loading -->
                                <div id="search_debug" class="d-none mt-2"></div>
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
                            <div class="col-md-4 mb-2">
                                <label class="form-label">Promotion / Reversion *</label>
                                <select name="promotion_type" id="promotion_type" class="form-select" required>
                                    <option value="">Select Type</option>
                                    <option value="promotion" <?=isset($_POST['promotion_type']) && strtolower($_POST['promotion_type'])=='promotion'?'selected':''?>>Promotion</option>
                                    <option value="reversion" <?=isset($_POST['promotion_type']) && strtolower($_POST['promotion_type'])=='reversion'?'selected':''?>>Reversion/Demotion</option>
                                </select>
                            </div>
                            <div class="col-md-4 mb-2">
                                <label class="form-label">Next / Previous Rank *</label>
                                <input type="text" class="form-control" id="next_rank_display" value="<?=htmlspecialchars($nextRankName)?>" readonly>
                                <input type="hidden" name="next_rank" id="next_rank" value="<?=htmlspecialchars($nextRankIdValue)?>">
                            </div>
                            <div class="col-md-4 mb-2">
                                <label class="form-label">Date of Promotion *</label>
                                <input type="date" name="promotion_date" class="form-control" required value="<?=htmlspecialchars($_POST['promotion_date'] ?? date('Y-m-d'))?>">
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-6 mb-2">
                                <label class="form-label">Bulk Authority <span class="text-muted" aria-label="Apply this authority to all staff" data-bs-toggle="tooltip" title="Apply this authority to all selected staff."><i class="fa fa-info-circle"></i></span></label>
                                <input type="text" id="bulk_authority" class="form-control" placeholder="Apply authority to all">
                                <button type="button" id="apply_bulk_authority" class="btn btn-sm btn-outline-primary mt-2"><i class="fa fa-check"></i> Apply to All</button>
                            </div>
                            <div class="col-md-6 mb-2">
                                <label class="form-label">Bulk Remark <span class="text-muted" aria-label="Apply this remark to all staff" data-bs-toggle="tooltip" title="Apply this remark to all selected staff."><i class="fa fa-info-circle"></i></span></label>
                                <input type="text" id="bulk_remark" class="form-control" placeholder="Apply remark to all">
                                <button type="button" id="apply_bulk_remark" class="btn btn-sm btn-outline-primary mt-2"><i class="fa fa-check"></i> Apply to All</button>
                            </div>
                        </div>
                        <div class="text-end">
                            <button type="button" id="showConfirmModal" class="btn btn-primary px-5 py-2" aria-label="Review and confirm promotion/demotion" disabled><i class="fa fa-arrow-up"></i> Promote/Demote</button>
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
                                <h5 class="modal-title" id="confirmModalLabel">Confirm Promotion/Demotion</h5>
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
                                        <h5 class="modal-title">Bulk Promotion Confirmation</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                    </div>
                                    <div class="modal-body">
                                        <p>You are about to promote <?=count($_POST['selected_staff']??[])?> staff. Are you sure?</p>
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
</style>

<!-- Scripts moved to the end of body for better loading order -->
<!-- Global data for JavaScript -->
<script>
    // Make ranks data available globally
    window.ranksDataFromServer = <?= json_encode($ranks) ?>;
    window.rankCategory = <?= json_encode($category ?? '') ?>;
    window.currentRankLevel = <?= json_encode($currentRankLevel ?? null) ?>;
    window.currentRankId = <?= json_encode($currentRankId ?? null) ?>;
    console.log('Ranks data loaded:', window.ranksDataFromServer.length, 'ranks');
    console.log('Current rank level:', window.currentRankLevel);
    console.log('Current rank ID:', window.currentRankId);
</script>

<!-- Core library scripts with integrity checks and fallbacks -->
<script src="https://cdn.jsdelivr.net/npm/jquery@3.6.0/dist/jquery.min.js"></script>
<script>
    // Fallback if jQuery CDN fails - immediately load local jQuery
    if (typeof jQuery === 'undefined') {
        console.warn('CDN jQuery failed to load, using local fallback');
        var fallbackScript = document.createElement('script');
        fallbackScript.src = '../assets/js/jquery-3.6.0.min.js';
        fallbackScript.onload = function() {
            console.log('Local jQuery loaded successfully');
            // Trigger an event to indicate jQuery is now available
            var jQueryReadyEvent = new Event('jQueryReady');
            document.dispatchEvent(jQueryReadyEvent);
        };
        fallbackScript.onerror = function() {
            console.error('Failed to load local jQuery fallback');
            document.body.innerHTML += '<div class="alert alert-danger fixed-top">Error: jQuery failed to load completely. Please check your server configuration or contact support.</div>';
        };
        document.head.appendChild(fallbackScript);
    } else {
        // jQuery from CDN loaded successfully
        document.dispatchEvent(new Event('jQueryReady'));
    }
</script>

<!-- Load other dependencies after jQuery -->
<script>
    // Function to load dependent scripts in sequence
    function loadDependentScripts() {
        console.log('Loading dependent scripts...');
        
        // Load Select2
        var select2Script = document.createElement('script');
        select2Script.src = 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js';
        select2Script.onload = function() {
            console.log('Select2 loaded successfully');
            
            // Load Bootstrap after Select2
            var bootstrapScript = document.createElement('script');
            bootstrapScript.src = 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js';
            bootstrapScript.onload = function() {
                console.log('Bootstrap loaded successfully');
                
                // Load Flatpickr CSS
                var flatpickrCSS = document.createElement('link');
                flatpickrCSS.rel = 'stylesheet';
                flatpickrCSS.href = 'https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css';
                document.head.appendChild(flatpickrCSS);
                
                // Load Flatpickr JS
                var flatpickrScript = document.createElement('script');
                flatpickrScript.src = 'https://cdn.jsdelivr.net/npm/flatpickr';
                flatpickrScript.onload = function() {
                    console.log('Flatpickr loaded successfully');
                    
                    // Load external promotion JS module
                    var promotionScript = document.createElement('script');
                    promotionScript.src = 'js/promote_staff.js';
                    promotionScript.onload = function() {
                        console.log('Promotion module loaded successfully');
                        
                        // Initialize promotion page after external script loads
                        if (typeof initPromotionPage === 'function') {
                            initPromotionPage();
                        }
                        
                        // Load application script after all dependencies are loaded
                        initializePromotionApp();
                    };
                    promotionScript.onerror = function() {
                        console.log('Promotion module failed to load, using inline functions');
                        // Load application script even if external fails
                        initializePromotionApp();
                    };
                    document.head.appendChild(promotionScript);
                };
                flatpickrScript.onerror = function() {
                    console.log('Flatpickr failed to load, continuing without date enhancement');
                    
                    // Load external promotion JS module even if flatpickr fails
                    var promotionScript = document.createElement('script');
                    promotionScript.src = 'js/promote_staff.js';
                    promotionScript.onload = function() {
                        console.log('Promotion module loaded successfully');
                        
                        // Initialize promotion page after external script loads
                        if (typeof initPromotionPage === 'function') {
                            initPromotionPage();
                        }
                        
                        // Load application script after all dependencies are loaded
                        initializePromotionApp();
                    };
                    promotionScript.onerror = function() {
                        console.log('Promotion module failed to load, using inline functions');
                        // Load application script even if external fails
                        initializePromotionApp();
                    };
                    document.head.appendChild(promotionScript);
                };
                document.head.appendChild(flatpickrScript);
            };
            document.head.appendChild(bootstrapScript);
        };
        document.head.appendChild(select2Script);
    }
    
    function initializePromotionApp() {
        // Load application script after all dependencies are loaded
            var appScript = document.createElement('script');
            appScript.textContent = `
                // Initialize the app features
                $(document).ready(function() {
                    console.log('Initializing application features...');
                    
                    // Global variables
                    const staffDetailsCache = {};
                    window.staffDetailsCache = staffDetailsCache; // Make it globally accessible
                    let staffSearchLoading = false;
                    
                    // Auto-submit rank form on change AND auto-load staff
                    function initFormHandlers() {
                        $('#current_rank').on('change', function() {
                            const rankId = $(this).val();
                            if (rankId) {
                                // Show preparation message immediately
                                $('#search_debug').removeClass('d-none')
                                    .html('<div class="alert alert-info mt-2 p-2">� Rank selected. Setting up staff search interface...</div>');
                                
                                // If we're already in Step 2 (staff selection visible), setup search immediately
                                if ($('#selected_staff').length > 0 && $('#selected_staff').is(':visible')) {
                                    console.log('Setting up staff search for newly selected rank:', rankId);
                                    
                                    // Setup staff search for the new rank without auto-loading
                                    setTimeout(function() {
                                        if (typeof loadStaffForRank === 'function') {
                                            loadStaffForRank(rankId);
                                        } else {
                                            console.error('loadStaffForRank function not available');
                                            $('#rankForm').submit();
                                        }
                                    }, 500);
                                } else {
                                    // Submit the form to reload with the selected rank (Step 1 → Step 2)
                                    $('#rankForm').submit();
                                }
                            }
                        });
                    }
                    
                    // Staff panel rendering function
                    function renderStaffPanels(selected) {
                        $('#staff_promotion_details').empty();
                        
                        selected.forEach(svcNo => {
                            let staffText = $('#selected_staff option[value="' + svcNo + '"]').text() || svcNo;
                            let panel = $(
                                '<div class="card mb-3">' +
                                    '<div class="card-body">' +
                                        '<div class="row align-items-center">' +
                                            '<div class="col-md-3 mb-2">' +
                                                '<strong class="profile-trigger" data-svcno="' + svcNo + '" style="cursor: pointer; color: #0d6efd; text-decoration: underline;">' + staffText + '</strong>' +
                                            '</div>' +
                                            '<div class="col-md-3 mb-2">' +
                                                '<label class="form-label mb-0">Authority</label>' +
                                                '<input type="text" name="promotion_authority[' + svcNo + ']" class="form-control authority-input" aria-label="Authority for ' + staffText + '">' +
                                            '</div>' +
                                            '<div class="col-md-3 mb-2">' +
                                                '<label class="form-label mb-0">Remark</label>' +
                                                '<input type="text" name="promotion_remark[' + svcNo + ']" class="form-control remark-input" aria-label="Remark for ' + staffText + '">' +
                                            '</div>' +
                                            '<div class="col-md-3 mb-2">' +
                                                '<span class="badge bg-info">Current Unit: <span class="current-unit">' + ($('#selected_staff option[value="' + svcNo + '"]').text().split('(')[1] ? $('#selected_staff option[value="' + svcNo + '"]').text().split('(')[1].replace(')', '') : '') + '</span></span>' +
                                            '</div>' +
                                        '</div>' +
                                    '</div>' +
                                '</div>'
                            );
                            $('#staff_promotion_details').append(panel);
                        });
                        
                        // Profile handlers are set up by initProfileModal() - no duplicates needed
                        
                        enablePromoteButton();
                        $('.authority-input, .remark-input').on('input', enablePromoteButton);
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
                    
                    // Initialize Select2 with manual search requirement
                    $('#selected_staff').select2({
                        width: '100%',
                        placeholder: 'First select a rank, then search by name or service number...',
                        allowClear: true,
                        multiple: true,
                        minimumInputLength: 2, // Require at least 2 characters to search
                        closeOnSelect: false, // Keep dropdown open for multi-selection
                        dropdownAutoWidth: true,
                        ajax: {
                            url: 'search_staff_minimal.php',
                            dataType: 'json',
                            delay: 250,
                            method: 'GET',
                            data: function(params) {
                                const rankId = getCurrentRankId();
                                
                                // Don't search if no rank is selected
                                if (!rankId) {
                                    return { q: '', rank_id: '', no_search: true };
                                }
                                
                                const queryData = {
                                    q: params.term || '',
                                    rank_id: rankId,
                                    limit: 50
                                };
                                
                                console.log('Staff search params:', queryData);
                                const currentSelections = $('#selected_staff').val() || [];
                                $('#search_debug').removeClass('d-none')
                                    .html('<div class="alert alert-info mt-2 p-2">🔍 Searching for: "' + 
                                          queryData.q + '"' + 
                                          (currentSelections.length > 0 ? ' (Currently selected: ' + currentSelections.length + ')' : '') + 
                                          '</div>');
                                
                                return queryData;
                            },
                            processResults: function(data, params) {
                                console.log('Staff search results:', data);
                                console.log('Search params:', params);
                                
                                // Handle case where no rank is selected or invalid response
                                if (!Array.isArray(data)) {
                                    console.error('Invalid data format received:', data);
                                    $('#search_debug').html('<div class="alert alert-warning mt-2 p-2">⚠️ Invalid search response. Please check rank selection.</div>');
                                    return { results: [] };
                                }
                                
                                // Handle empty results
                                if (data.length === 0) {
                                    $('#search_debug').html('<div class="alert alert-info mt-2 p-2">ℹ️ No staff found matching your search criteria.</div>');
                                    return { results: [] };
                                }
                                
                                // Cache staff details for easy access
                                data.forEach(staff => {
                                    if (staff.service_number) {
                                        staffDetailsCache[staff.service_number] = staff;
                                    }
                                });
                                
                                const currentSelections = $('#selected_staff').val() || [];
                                $('#search_debug').html('<div class="alert alert-success mt-2 p-2">✅ Found ' + data.length + ' staff member(s)' + 
                                    (currentSelections.length > 0 ? '. Already selected: ' + currentSelections.length : '') + 
                                    '. Click to select for promotion.</div>');
                                
                                return {
                                    results: data.map(function(staff) {
                                        return {
                                            id: staff.service_number,
                                            text: staff.text || (staff.service_number + ' - ' + (staff.first_name || '') + ' ' + (staff.last_name || '')),
                                            service_number: staff.service_number,
                                            first_name: staff.first_name || '',
                                            last_name: staff.last_name || '',
                                            rank_name: staff.rank_name || ''
                                        };
                                    })
                                };
                            },
                            error: function(xhr, status, error) {
                                console.error('Promotion AJAX Error Details:', {
                                    status: status,
                                    error: error,
                                    responseText: xhr.responseText,
                                    statusCode: xhr.status
                                });
                                $('#search_debug').html('<div class="alert alert-danger mt-2 p-2">❌ Search failed: ' + error + ' (Status: ' + xhr.status + ')<br>Response: ' + xhr.responseText.substring(0, 200) + '</div>');
                            },
                            cache: true
                        },
                        templateSelection: function(selection) {
                            // Custom template for selected items to show more info
                            if (selection.id === '') {
                                return selection.text; // Placeholder
                            }
                            return selection.text || selection.id;
                        }
                    });
                    
                    // Enhanced selection event handlers
                    $('#selected_staff').on('select2:select', function(e) {
                        const selectedData = e.params.data;
                        console.log('Staff selected:', selectedData);
                        
                        // Update feedback message
                        const currentSelections = $(this).val() || [];
                        $('#search_debug').html('<div class="alert alert-success mt-2 p-2">✅ Selected ' + currentSelections.length + ' staff member(s). Continue searching to add more.</div>');
                        
                        // Auto-focus back to search field for easier multi-selection
                        setTimeout(function() {
                            $('.select2-search__field').focus();
                        }, 100);
                    });
                    
                    $('#selected_staff').on('select2:unselect', function(e) {
                        const unselectedData = e.params.data;
                        console.log('Staff unselected:', unselectedData);
                        
                        // Update feedback message
                        const currentSelections = $(this).val() || [];
                        if (currentSelections.length > 0) {
                            $('#search_debug').html('<div class="alert alert-info mt-2 p-2">ℹ️ Selected ' + currentSelections.length + ' staff member(s). Continue searching to add more.</div>');
                        } else {
                            $('#search_debug').html('<div class="alert alert-info mt-2 p-2">💡 Search for staff members to select for promotion.</div>');
                        }
                    });
                    
                    $('#selected_staff').on('select2:open', function() {
                        // Clear search field when dropdown opens for better UX
                        setTimeout(function() {
                            $('.select2-search__field').val('');
                        }, 50);
                    });
                    
                    // Function to load all staff at a rank for promotions
                    function loadStaffForRank(rankId) {
                        if (!rankId) return;
                        
                        console.log('Setting up staff selection for rank ID:', rankId);
                        
                        // Clear the dropdown and set placeholder
                        $('#selected_staff').empty().append(new Option('Search by name or service number...', '', false, false));
                        
                        // Update the Select2 configuration for this rank
                        $('#selected_staff').select2('destroy').select2({
                            placeholder: 'Search by name or service number...',
                            allowClear: true,
                            multiple: true,
                            minimumInputLength: 2, // Require at least 2 characters to search
                            closeOnSelect: false, // Keep dropdown open for multi-selection
                            dropdownAutoWidth: true,
                            ajax: {
                                url: 'search_staff_minimal.php',
                                dataType: 'json',
                                delay: 250,
                                method: 'GET',
                                data: function(params) {
                                    return {
                                        q: params.term || '',
                                        rank_id: rankId,
                                        limit: 50
                                    };
                                },
                                processResults: function(data) {
                                    return {
                                        results: data.map(function(item) {
                                            return {
                                                id: item.service_number,
                                                text: item.text,
                                                service_number: item.service_number,
                                                rank_name: item.rank_name
                                            };
                                        })
                                    };
                                },
                                cache: true
                            },
                            escapeMarkup: function(markup) {
                                return markup;
                            },
                            templateSelection: function(selection) {
                                if (selection.id === '') {
                                    return selection.text; // Placeholder
                                }
                                return selection.text || selection.id;
                            }
                        });
                        
                        // Add enhanced event handlers for this rank's staff selection
                        $('#selected_staff').off('select2:select.rankSpecific select2:unselect.rankSpecific select2:open.rankSpecific')
                            .on('select2:select.rankSpecific', function(e) {
                                const selectedData = e.params.data;
                                const currentSelections = $(this).val() || [];
                                $('#search_debug').html('<div class="alert alert-success mt-2 p-2">✅ Selected ' + currentSelections.length + ' staff member(s) for promotion. Continue searching to add more.</div>');
                                
                                // Auto-focus back to search field
                                setTimeout(function() {
                                    $('.select2-search__field').focus();
                                }, 100);
                            })
                            .on('select2:unselect.rankSpecific', function(e) {
                                const currentSelections = $(this).val() || [];
                                if (currentSelections.length > 0) {
                                    $('#search_debug').html('<div class="alert alert-info mt-2 p-2">ℹ️ Selected ' + currentSelections.length + ' staff member(s). Continue searching to add more.</div>');
                                } else {
                                    $('#search_debug').html('<div class="alert alert-info mt-2 p-2">💡 Search to select staff members for promotion.</div>');
                                }
                            })
                            .on('select2:open.rankSpecific', function() {
                                // Clear search field when dropdown opens
                                setTimeout(function() {
                                    $('.select2-search__field').val('');
                                }, 50);
                            });
                        
                        // Ready for staff selection - search interface configured
                    }
                    
                    // Setup staff selection on page load if rank is selected
                    <?php if ($currentRankId && $currentRank): ?>
                    console.log('Setting up staff selection for rank ' + <?=json_encode($currentRankId)?> + ' (' + <?=json_encode($currentRank->rankName ?? 'Unknown')?> + ')');
                    
                    // Show initial instruction
                    $('#search_debug').removeClass('d-none')
                        .html('<div class="alert alert-info mt-2 p-2">� <strong>Rank Selected:</strong> <?=htmlspecialchars($currentRank->rankName ?? 'Unknown')?> (ID: <?=$currentRankId?>). Use the dropdown above to search and select staff members to promote.</div>');
                    
                    // Small delay to ensure Select2 is fully initialized
                    setTimeout(function() {
                        if (typeof loadStaffForRank === 'function') {
                            console.log('✅ Setting up staff selection for rank <?=json_encode($currentRankId)?>');
                            loadStaffForRank(<?=json_encode($currentRankId)?>);
                        } else {
                            console.error('❌ loadStaffForRank function is not defined!');
                            $('#search_debug').html('<div class="alert alert-danger mt-2 p-2">❌ ERROR: Setup failed - function not defined!</div>');
                        }
                    }, 1000);
                    <?php else: ?>
                    console.log('No current rank selected - user must select rank first');
                    $('#search_debug').html('<div class="alert alert-warning mt-2 p-2">⚠️ Please select a rank first to enable staff selection.</div>');
                    <?php endif; ?>
                    
                    // Staff selection change event handler
                    $('#selected_staff').on('change', function() {
                        const selected = $(this).val() || [];
                        console.log('Promotion staff selection changed:', selected);
                        // Additional staff selection handling can be added here
                    });
                    
                    // Profile pop-up handling - handled by initProfileModal(), no duplicate needed
                    
                    // Enable tooltips
                    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
                    tooltipTriggerList.forEach(function (tooltipTriggerEl) {
                        new bootstrap.Tooltip(tooltipTriggerEl);
                    });
                    
                    // Initialize modules
                    initProfileModal();
                    initFormHandlers();
                    
                    // Initialize staff selection handler
                    $('#selected_staff').on('change', function() {
                        const selected = $(this).val() || [];
                        renderStaffPanels(selected);
                    });
                    
                    // Run initial UI setup
                    if (typeof enablePromoteButton === 'function') {
                        enablePromoteButton();
                    }
                    
                    console.log('Promotion application initialization complete');
                });
            `;
            document.body.appendChild(appScript);
    }

    // Wait for jQuery to be ready (either from CDN or local fallback)
    if (typeof jQuery !== 'undefined') {
        // jQuery already available, load dependent scripts
        loadDependentScripts();
    } else {
        // Wait for jQuery to become available via the fallback
        document.addEventListener('jQueryReady', loadDependentScripts);
    }
</script>

<?php include dirname(__DIR__) . '/shared/footer.php'; ?>
