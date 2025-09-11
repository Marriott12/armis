<?php
/**
 * AJAX endpoint to retrieve staff profile information
 * 
 * Returns HTML content for display in the staff profile modal
 */

// Include database connection
require_once dirname(__DIR__) . '/shared/database_connection.php';
require_once dirname(__DIR__) . '/shared/functions.php';

// Authentication check
require_once dirname(__DIR__) . '/shared/auth.php';
requireAuth();

// Sanitize input
$serviceNumber = isset($_GET['service_number']) ? trim($_GET['service_number']) : '';

// Validate service number
if (empty($serviceNumber)) {
    echo '<div class="alert alert-danger">Error: No service number provided</div>';
    exit;
}

try {
    // Fetch staff information
    $stmt = $pdo->prepare("
        SELECT s.*, 
               r.name AS rank_name, 
               u.name AS unit_name,
               p.name AS position_name
        FROM staff s
        LEFT JOIN ranks r ON s.rank_id = r.id
        LEFT JOIN units u ON s.unit_id = u.id
        LEFT JOIN positions p ON s.position_id = p.id
        WHERE s.service_number = ?
    ");
    $stmt->execute([$serviceNumber]);
    $staff = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$staff) {
        echo '<div class="alert alert-warning">Staff member not found</div>';
        exit;
    }
    
    // Fetch promotion history
    $stmt = $pdo->prepare("
        SELECT 
            ph.id,
            ph.from_rank_id,
            ph.to_rank_id,
            fr.name AS from_rank_name,
            tr.name AS to_rank_name,
            ph.effective_date,
            ph.promotion_type,
            ph.authority,
            ph.remarks
        FROM promotion_history ph
        LEFT JOIN ranks fr ON ph.from_rank_id = fr.id
        LEFT JOIN ranks tr ON ph.to_rank_id = tr.id
        WHERE ph.service_number = ?
        ORDER BY ph.effective_date DESC
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
            <h4><?php echo htmlspecialchars($staff['rank_name'] . ' ' . $staff['first_name'] . ' ' . $staff['last_name']); ?></h4>
            <p class="text-muted"><?php echo htmlspecialchars($staff['service_number']); ?></p>
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
                    <td><?php echo !empty($staff['date_of_enlistment']) ? date('d M Y', strtotime($staff['date_of_enlistment'])) : 'N/A'; ?></td>
                </tr>
                <tr>
                    <th>Unit</th>
                    <td><?php echo htmlspecialchars($staff['unit_name'] ?? 'N/A'); ?></td>
                </tr>
                <tr>
                    <th>Position</th>
                    <td><?php echo htmlspecialchars($staff['position_name'] ?? 'N/A'); ?></td>
                </tr>
                <tr>
                    <th>Contact</th>
                    <td><?php echo htmlspecialchars($staff['phone'] ?? 'N/A'); ?></td>
                </tr>
                <tr>
                    <th>Email</th>
                    <td><?php echo htmlspecialchars($staff['email'] ?? 'N/A'); ?></td>
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
?>
