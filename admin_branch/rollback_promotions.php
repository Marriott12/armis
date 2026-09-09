<?php
/**
 * Promotion Rollback Handler
 * 
 * Allows administrators to undo promotions within a specified time window
 * Default: 24 hours after promotion
 * 
 * @author Admin Branch System
 * @created 2025-10-07
 */

// Define module constants
define('ARMIS_ADMIN_BRANCH', true);

// Include required files
require_once __DIR__ . '/includes/auth.php';
require_once dirname(__DIR__) . '/shared/database_connection.php';

// Require authentication
requireAuth();

// Require system-admin-level permission. This was previously missing
// entirely — any authenticated admin_branch user (including read-only
// AG/DG roles) could reach this destructive action with only a login,
// no permission check at all.
require_once dirname(__DIR__) . '/shared/permissions.php';
if (!hasPermission(PERM_SYSTEM_SETTINGS)) {
    http_response_code(403);
    die('Access denied. Rolling back a promotion requires system-administrator permission.');
}

// Generate CSRF token
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$pageTitle = "Promotion Rollback - Admin Branch";
$moduleName = "Admin Branch";
$moduleIcon = "undo";
$currentPage = "promotions";

// Sidebar navigation
$sidebarLinks = []; // set by shared nav include below
require_once __DIR__ . '/includes/sidebar_nav.php';

$errors = [];
$success = false;
$successMessage = '';

try {
    $pdo = getDbConnection();
} catch (Exception $e) {
    die("Database Connection Error: " . htmlspecialchars($e->getMessage()));
}

// Handle rollback request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['rollback_promotion'])) {
    // CSRF Protection
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], (string)$_POST['csrf_token'])) {
        $errors[] = "Invalid security token. Please refresh the page and try again.";
    }
    
    $promotionId = $_POST['promotion_id'] ?? '';
    $reason = trim($_POST['reason'] ?? '');
    
    if (empty($promotionId)) {
        $errors[] = "Invalid promotion ID.";
    }
    
    if (empty($reason)) {
        $errors[] = "Please provide a reason for the rollback.";
    }
    
    if (empty($errors)) {
        try {
            $pdo->beginTransaction();
            
            // Get promotion details
                 $stmt = $pdo->prepare("
                  SELECT sp.*, s.fName, s.lName, s.rankId AS staff_rank_id,
                      r_from.rankId AS from_rank_name, r_to.rankId AS to_rank_name
                  FROM staff_promotion sp
                  JOIN staff s ON sp.svcNo = s.svcNo
                  LEFT JOIN `rank` r_from ON sp.currentRank = r_from.rankId
                  LEFT JOIN `rank` r_to ON sp.newRank = r_to.rankId
                  WHERE sp.id = ?
                    AND sp.createdAt >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
                    AND (sp.remark IS NULL OR sp.remark NOT LIKE '%[ROLLED BACK:%')
                 ");
            $stmt->execute([$promotionId]);
            $promotion = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$promotion) {
                throw new Exception("Promotion not found or cannot be rolled back.");
            }
            
            if ($promotion['staff_rank_id'] !== $promotion['newRank']) {
                throw new Exception("This promotion is no longer the staff member's current rank and cannot be rolled back safely.");
            }
            
            // Store current state for audit
            $beforeData = [
                'staff_rank_id' => $promotion['staff_rank_id'],
                'promotion_status' => 'completed'
            ];
            
            // Revert staff rank
            $updateStmt = $pdo->prepare("UPDATE staff SET rankId = ? WHERE svcNo = ? AND rankId = ?");
            $updateStmt->execute([$promotion['currentRank'], $promotion['svcNo'], $promotion['newRank']]);
            if ($updateStmt->rowCount() !== 1) {
                throw new Exception('The staff rank changed while this rollback was being processed. No changes were applied.');
            }
            
            // Mark promotion as rolled back
            $rollbackStmt = $pdo->prepare("UPDATE staff_promotion
                SET remark = CONCAT(COALESCE(remark, ''), '\n[ROLLED BACK: ', ?, ']')
                WHERE id = ? AND (remark IS NULL OR remark NOT LIKE '%[ROLLED BACK:%')");
            $rollbackStmt->execute([
                $reason,
                $promotionId
            ]);
            if ($rollbackStmt->rowCount() !== 1) {
                throw new Exception('This promotion was already rolled back or changed.');
            }
            
            // Store after data
            $afterData = [
                'staff_rank_id' => $promotion['currentRank'],
                'promotion_status' => 'rolled_back',
                'rollback_reason' => $reason
            ];
            
            // Log the rollback
            $description = sprintf(
                "Rolled back %s for %s %s (Service: %s) from %s to %s. Reason: %s",
                $promotion['type'],
                $promotion['fName'],
                $promotion['lName'],
                $promotion['svcNo'],
                $promotion['to_rank_name'],
                $promotion['from_rank_name'],
                $reason
            );
            
            logActivity('promotion_rollback', $description);
            
            $pdo->commit();
            $success = true;
            $successMessage = sprintf(
                "Successfully rolled back %s for %s %s from %s to %s.",
                $promotion['type'],
                $promotion['fName'],
                $promotion['lName'],
                $promotion['to_rank_name'],
                $promotion['from_rank_name']
            );
            
        } catch (Exception $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
            $errors[] = "Rollback failed: " . $e->getMessage();
            error_log("Rollback error: " . $e->getMessage());
        }
    }
}

// Get rollbackable promotions
$rollbackStmt = $pdo->query("SELECT sp.*, s.fName, s.lName, s.rankId AS staff_rank_id,
                r_from.rankId AS from_rank_name, r_to.rankId AS to_rank_name,
                TIMESTAMPDIFF(MINUTE, sp.createdAt, NOW()) AS minutes_since_creation
        FROM staff_promotion sp
        JOIN staff s ON sp.svcNo = s.svcNo
        LEFT JOIN `rank` r_from ON sp.currentRank = r_from.rankId
        LEFT JOIN `rank` r_to ON sp.newRank = r_to.rankId
        WHERE sp.createdAt >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
            AND (sp.remark IS NULL OR sp.remark NOT LIKE '%[ROLLED BACK:%')
        ORDER BY sp.createdAt DESC");
$rollbackablePromotions = $rollbackStmt->fetchAll(PDO::FETCH_ASSOC);

include dirname(__DIR__) . '/shared/header.php';
include dirname(__DIR__) . '/shared/sidebar.php';
?>

<!-- DataTables CSS -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/datatables.net-bs5@1.13.6/css/dataTables.bootstrap5.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/datatables.net-responsive-bs5@2.5.0/css/responsive.bootstrap5.min.css">

<style>
.rollback-warning {
    background: #fff3cd;
    border-left: 4px solid #ffc107;
    padding: 15px;
    margin-bottom: 20px;
}

.promotion-card {
    border-left: 4px solid #0d6efd;
    transition: all 0.3s ease;
}

.promotion-card:hover {
    box-shadow: 0 4px 8px rgba(0,0,0,0.1);
    transform: translateY(-2px);
}

.promotion-card .card-body {
    min-width: 0;
}

.time-badge {
    font-family: monospace;
    font-weight: bold;
}
</style>

<div class="content-wrapper with-sidebar">
    <div class="container-fluid">
            <div class="row">
                <div class="col-12">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h1 class="section-title">
                            <i class="fas fa-undo"></i> Promotion Rollback
                        </h1>
                        <div>
                            <a href="/Armis2/admin_branch/promote_staff.php" class="btn btn-outline-primary">
                                <i class="fas fa-arrow-left"></i> Back to Promotions
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-12">
                    <div class="card shadow-sm">
                        <div class="card-header bg-warning text-dark">
                            <h4 class="mb-0"><i class="fa fa-undo"></i> Rollback Recent Promotions</h4>
                        </div>
                        <div class="card-body">
                            <?php if ($success): ?>
                                <div class="alert alert-success alert-dismissible fade show">
                                    <i class="fas fa-check-circle"></i> <?= htmlspecialchars($successMessage) ?>
                                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                                </div>
                            <?php endif; ?>
                            
                            <?php if ($errors): ?>
                                <div class="alert alert-danger alert-dismissible fade show">
                                    <ul class="mb-0">
                                        <?php foreach ($errors as $err): ?>
                                            <li><?= htmlspecialchars($err) ?></li>
                                        <?php endforeach; ?>
                                    </ul>
                                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                                </div>
                            <?php endif; ?>

                            <div class="rollback-warning">
                                <h5><i class="fas fa-exclamation-triangle"></i> Important Information</h5>
                                <ul class="mb-0">
                                    <li><strong>Rollback Window:</strong> Promotions can only be rolled back within <strong>24 hours</strong> of creation.</li>
                                    <li><strong>Effect:</strong> Rolling back a promotion will revert the staff member's rank to the previous rank.</li>
                                    <li><strong>Audit Trail:</strong> All rollback actions are logged and cannot be undone.</li>
                                    <li><strong>Reason Required:</strong> You must provide a valid reason for the rollback.</li>
                                </ul>
                            </div>

                            <?php if (empty($rollbackablePromotions)): ?>
                                <div class="alert alert-info">
                                    <i class="fas fa-info-circle"></i> No promotions available for rollback. Only promotions created within the last 24 hours can be rolled back.
                                </div>
                            <?php else: ?>
                                <h5 class="mb-3">Promotions Available for Rollback (<?= count($rollbackablePromotions) ?>)</h5>
                                
                                <div class="row">
                                    <?php foreach ($rollbackablePromotions as $promo): ?>
                                        <?php
                                        $minutesSince = $promo['minutes_since_creation'];
                                        $hoursRemaining = 24 - ($minutesSince / 60);
                                        $timeClass = $hoursRemaining < 2 ? 'danger' : ($hoursRemaining < 12 ? 'warning' : 'success');
                                        ?>
                                        <div class="col-md-6 col-lg-4 mb-3">
                                            <div class="card promotion-card">
                                                <div class="card-body">
                                                    <h6 class="card-title">
                                                        <?= htmlspecialchars($promo['svcNo']) ?> - 
                                                        <?= htmlspecialchars($promo['fName'] . ' ' . $promo['lName']) ?>
                                                    </h6>
                                                    <p class="card-text">
                                                        <strong>Type:</strong> <?= ucfirst($promo['type']) ?><br>
                                                        <strong>From:</strong> <?= htmlspecialchars($promo['from_rank_name']) ?><br>
                                                        <strong>To:</strong> <?= htmlspecialchars($promo['to_rank_name']) ?><br>
                                                        <strong>Date:</strong> <?= date('d M Y H:i', strtotime($promo['createdAt'])) ?><br>
                                                        <strong>Effective:</strong> <?= date('d M Y', strtotime($promo['dateTo'])) ?>
                                                    </p>
                                                    <div class="d-flex justify-content-between align-items-center">
                                                        <span class="badge bg-<?= $timeClass ?> time-badge">
                                                            <?= number_format($hoursRemaining, 1) ?>h remaining
                                                        </span>
                                                        <button type="button" class="btn btn-sm btn-danger rollback-btn" 
                                                                data-promotion-id="<?= $promo['id'] ?>"
                                                                data-staff-name="<?= htmlspecialchars($promo['fName'] . ' ' . $promo['lName']) ?>"
                                                                data-from-rank="<?= htmlspecialchars($promo['from_rank_name']) ?>"
                                                                data-to-rank="<?= htmlspecialchars($promo['to_rank_name']) ?>"
                                                                data-type="<?= htmlspecialchars($promo['type']) ?>">
                                                            <i class="fas fa-undo"></i> Rollback
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
    </div>
</div>

<!-- Rollback Confirmation Modal -->
<div class="modal fade" id="rollbackModal" tabindex="-1" aria-labelledby="rollbackModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title" id="rollbackModalLabel">
                    <i class="fas fa-exclamation-triangle"></i> Confirm Rollback
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="post" action="" id="rollbackForm">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                <input type="hidden" name="promotion_id" id="rollback_promotion_id">
                <input type="hidden" name="rollback_promotion" value="1">
                
                <div class="modal-body">
                    <div class="alert alert-warning">
                        <strong>Warning:</strong> This action cannot be undone!
                    </div>
                    
                    <p><strong>Staff Member:</strong> <span id="rollback_staff_name"></span></p>
                    <p><strong>Type:</strong> <span id="rollback_type"></span></p>
                    <p><strong>Current Rank:</strong> <span id="rollback_to_rank"></span></p>
                    <p><strong>Will Revert To:</strong> <span id="rollback_from_rank"></span></p>
                    
                    <div class="mb-3">
                        <label for="reason" class="form-label">Reason for Rollback *</label>
                        <textarea name="reason" id="reason" class="form-control" rows="3" required 
                                  placeholder="Enter the reason for rolling back this promotion..."></textarea>
                        <small class="text-muted">This reason will be logged in the audit trail.</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">
                        <i class="fas fa-undo"></i> Confirm Rollback
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Core JS (jQuery/Bootstrap) are loaded centrally in shared/footer.php. -->

<script>
$(document).ready(function() {
    // Handle rollback button click
    $('.rollback-btn').on('click', function() {
        const promotionId = $(this).data('promotion-id');
        const staffName = $(this).data('staff-name');
        const fromRank = $(this).data('from-rank');
        const toRank = $(this).data('to-rank');
        const type = $(this).data('type');
        
        // Populate modal
        $('#rollback_promotion_id').val(promotionId);
        $('#rollback_staff_name').text(staffName);
        $('#rollback_type').text(type.charAt(0).toUpperCase() + type.slice(1));
        $('#rollback_from_rank').text(fromRank);
        $('#rollback_to_rank').text(toRank);
        $('#reason').val('');
        
        // Show modal
        const modal = new bootstrap.Modal(document.getElementById('rollbackModal'));
        modal.show();
    });
    
    // Form validation
    $('#rollbackForm').on('submit', function(e) {
        const reason = $('#reason').val().trim();
        if (reason.length < 10) {
            e.preventDefault();
            alert('Please provide a detailed reason (at least 10 characters).');
            return false;
        }
        
        // Show loading state
        $(this).find('button[type="submit"]').prop('disabled', true)
              .html('<i class="fas fa-spinner fa-spin"></i> Processing...');
    });
});
</script>

<?php include dirname(__DIR__) . '/shared/footer.php'; ?>
