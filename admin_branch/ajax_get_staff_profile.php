<?php
/**
 * AJAX endpoint to retrieve staff profile information
 * 
 * Returns HTML content for display in the staff profile modal
 */

// Include database connection.
// FIX: `shared/auth.php` and `shared/functions.php` do not exist anywhere
// in this codebase - both requires fatal-errored ("Failed to open
// required file") before a single line of this endpoint's own logic ever
// ran. The real auth helpers live in admin_branch/includes/auth.php
// (requireAuth() etc., used consistently across admin_branch).
require_once dirname(__DIR__) . '/shared/database_connection.php';
require_once __DIR__ . '/includes/auth.php';
requireAuth();

// FIX: requiring database_connection.php only defines getDbConnection() -
// it does not create a $pdo variable, which every query below needed.
$pdo = getDbConnection();

// Sanitize input
$serviceNumber = isset($_GET['svcNo']) ? trim($_GET['svcNo']) : '';

// Validate service number
if (empty($serviceNumber)) {
    echo '<div class="alert alert-danger">Error: No service number provided</div>';
    exit;
}

try {
    // Fetch staff information.
    // FIX: `unit` only has unitId/unitLoc - no `name` column, so unitId
    // itself is the display value (the convention used everywhere else in
    // this app, e.g. profile_manager.php). There is no `positions` table
    // or `position_id` column anywhere in the schema, so that join/field
    // is dropped rather than querying something that doesn't exist.
    $stmt = $pdo->prepare("
     SELECT s.*, 
         r.rankId AS rank_name, 
         u.unitId AS unit_name
     FROM staff s
     LEFT JOIN `rank` r ON s.rankId = r.rankId
     LEFT JOIN unit u ON s.unitId = u.unitId
     WHERE s.svcNo = ?
    ");
    $stmt->execute([$serviceNumber]);
    $staff = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$staff) {
        echo '<div class="alert alert-warning">Staff member not found</div>';
        exit;
    }
    
    // Fetch promotion history.
    // FIX: the real table is `staff_promotion` (currentRank, wefDate,
    // type, newRank, authID, remark), not `promotion_history` - the
    // original table/column names here don't exist anywhere in the
    // schema.
    $stmt = $pdo->prepare("
        SELECT 
            sp.id,
            sp.currentRank AS from_rank_id,
            sp.newRank AS to_rank_id,
            fr.rankId AS from_rank_name,
            tr.rankId AS to_rank_name,
            sp.wefDate AS effective_date,
            sp.type AS promotion_type,
            a.description AS authority,
            sp.remark AS remarks
        FROM staff_promotion sp
        LEFT JOIN `rank` fr ON sp.currentRank = fr.rankId
        LEFT JOIN `rank` tr ON sp.newRank = tr.rankId
        LEFT JOIN authority a ON sp.authID = a.authID
        WHERE sp.svcNo = ?
        ORDER BY sp.wefDate DESC
        LIMIT 5
    ");
    $stmt->execute([$serviceNumber]);
    $promotionHistory = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get photo path
    $photoPath = '../uploads/photos/' . $serviceNumber . '.jpg';
    $defaultPhoto = '../assets/img/default-profile.png';
    
    $photoUrl = file_exists($photoPath) ? $photoPath : $defaultPhoto;
    
    // Format the output
    ?>
    <div class="row">
        <div class="col-md-4 text-center">
            <img src="<?php echo htmlspecialchars($photoUrl); ?>" alt="Staff Photo" class="img-fluid rounded mb-3" style="max-height:200px;">
            <h4><?php echo htmlspecialchars($staff['rank_name'] . ' ' . $staff['fName'] . ' ' . $staff['lName']); ?></h4>
            <p class="text-muted"><?php echo htmlspecialchars($staff['svcNo']); ?></p>
        </div>
        <div class="col-md-8">
            <h5>Personal Information</h5>
            <table class="table table-bordered table-sm">
                <tr>
                    <th width="35%">Date of Birth</th>
                    <td><?php echo !empty($staff['DOB']) ? date('d M Y', strtotime($staff['DOB'])) : 'N/A'; ?></td>
                </tr>
                <tr>
                    <th>Date of Enlistment</th>
                    <td><?php echo !empty($staff['attestDate']) ? date('d M Y', strtotime($staff['attestDate'])) : 'N/A'; ?></td>
                </tr>
                <tr>
                    <th>Unit</th>
                    <td><?php echo htmlspecialchars($staff['unit_name'] ?? 'N/A'); ?></td>
                </tr>
                <tr>
                    <th>Contact</th>
                    <td><?php echo htmlspecialchars($staff['telNo'] ?? 'N/A'); ?></td>
                </tr>
                <tr>
                    <th>Email</th>
                    <td><?php echo htmlspecialchars($staff['officialEmail'] ?? 'N/A'); ?></td>
                </tr>
            </table>
        </div>
    </div>
    
    <div class="row mt-3">
        <div class="col-12">
            <h5>Promotion History</h5>
            <?php if (empty($promotionHistory)): ?>
                <p class="text-muted">No promotion history available.</p>
            <?php else: ?>
                <table class="table table-striped table-sm">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>From Rank</th>
                            <th>To Rank</th>
                            <th>Type</th>
                            <th>Authority</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($promotionHistory as $record): ?>
                            <tr>
                                <td><?php echo date('d M Y', strtotime($record['effective_date'])); ?></td>
                                <td><?php echo htmlspecialchars($record['from_rank_name']); ?></td>
                                <td><?php echo htmlspecialchars($record['to_rank_name']); ?></td>
                                <td><?php echo ucfirst(htmlspecialchars($record['promotion_type'])); ?></td>
                                <td><?php echo htmlspecialchars($record['authority']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
    <?php
} catch (PDOException $e) {
    // Log the error and show a generic message
    error_log('Error in ajax_get_staff_profile.php: ' . $e->getMessage());
    echo '<div class="alert alert-danger">An error occurred while retrieving staff information. Please try again later.</div>';
}
