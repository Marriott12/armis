<?php
// AJAX endpoint for staff profile modal
require_once dirname(__DIR__) . '/shared/database_connection.php';
require_once dirname(__DIR__) . '/shared/military_formatting.php';

// Set content type based on request type
$wantJson = isset($_GET['format']) && $_GET['format'] === 'json';
if ($wantJson) {
    header('Content-Type: application/json; charset=UTF-8');
} else {
    header('Content-Type: text/html; charset=UTF-8');
}

// Debug log
error_log("ajax_staff_profile.php called with: " . json_encode($_GET));

// Validate service number
$serviceNumber = $_GET['svcNo'] ?? '';
if (!$serviceNumber || !preg_match('/^[A-Z0-9\/-]+$/i', $serviceNumber)) {
    if ($wantJson) {
        echo json_encode([
            'success' => false,
            'message' => 'Invalid or missing service number',
            'data' => null
        ]);
    } else {
        echo '<div class="alert alert-danger">Invalid or missing service number.</div>';
    }
    error_log("ajax_staff_profile.php: Invalid or missing service number: " . $serviceNumber);
    exit;
}

try {
    $pdo = getDbConnection();
    
    // Get staff with rank name
    $sql = '
        SELECT 
            s.*, 
            s.attestDate as attestation_date,
            s.subWef as sub_wef,
            r.rankId as rank_name, 
            r.rankId as rank_short_name,
            u.name as unit_name,
            u.code as unit_code
        FROM staff s 
        LEFT JOIN `rank` r ON s.rankId = r.rankId
        LEFT JOIN unit u ON s.unitId = u.unitId
        WHERE s.svcNo = ? 
        LIMIT 1
    ';
    error_log("ajax_staff_profile.php: Executing query: $sql with svcNo=$serviceNumber");
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$serviceNumber]);
    $staff = $stmt->fetch(PDO::FETCH_OBJ);

    if (!$staff) {
        if ($wantJson) {
            echo json_encode([
                'success' => false,
                'message' => 'Staff member not found',
                'data' => null
            ]);
        } else {
            echo '<div class="alert alert-warning">Staff member not found: ' . htmlspecialchars($serviceNumber) . '</div>';
        }
        error_log("ajax_staff_profile.php: Staff member not found: $serviceNumber");
        exit;
    }

    error_log("ajax_staff_profile.php: Staff found: " . json_encode($staff));

    // Get current appointment date from staff_appointment (latest active appointment)
    $apptSql = '
        SELECT appointment_date
        FROM staff_appointment
        WHERE svcNo = ?
          AND (endDate IS NULL OR endDate >= CURDATE())
        ORDER BY appointment_date DESC
        LIMIT 1
    ';
    $apptStmt = $pdo->prepare($apptSql);
    $apptStmt->execute([$serviceNumber]);
    $currentAppointmentDateRow = $apptStmt->fetch(PDO::FETCH_ASSOC);
    $currentAppointmentDate = $currentAppointmentDateRow ? $currentAppointmentDateRow['appointment_date'] : null;

    // Get promotion history if available
    $promotionHistory = [];
    $stmt = $pdo->prepare('
        SELECT 
            sp.*, 
            r1.rankId as old_rank_name, 
            r1.abbreviation as old_rank_short_name,
            r2.rankId as new_rank_name,
            r2.abbreviation as new_rank_short_name
        FROM staff_promotions sp
        LEFT JOIN `rank` r1 ON sp.currentRank = r1.rankId
        LEFT JOIN `rank` r2 ON sp.newRank = r2.rankId
        WHERE sp.svcNo = (SELECT id FROM staff WHERE svcNo = ?)
        ORDER BY sp.dateTo DESC
        LIMIT 5
    ');
    $stmt->execute([$serviceNumber]);
    $promotionHistory = $stmt->fetchAll(PDO::FETCH_OBJ);

    // If JSON response is requested, return data as JSON
    if ($wantJson) {
        echo json_encode([
            'success' => true,
            'message' => 'Staff data retrieved successfully',
            'data' => [
                'staff' => $staff,
                'promotionHistory' => $promotionHistory,
                'currentAppointmentDate' => $currentAppointmentDate
            ]
        ]);
        exit;
    }
    
} catch (Exception $e) {
    if ($wantJson) {
        echo json_encode([
            'success' => false,
            'message' => 'Database error: ' . $e->getMessage(),
            'data' => null
        ]);
    } else {
        echo '<div class="alert alert-danger">Database error: ' . htmlspecialchars($e->getMessage()) . '</div>';
    }
    error_log("ajax_staff_profile.php error: " . $e->getMessage());
    exit;
}
// HTML response format - only reached if not returning JSON
?>
<div class="container-fluid profile-container">
    <div class="row mb-3">
        <div class="col-md-12">
            <?php
                // Use military formatting for name (no comma after last name)
                $formattedName = formatMilitaryName(
                    $staff->rank_name ?? '',
                    $staff->rank_short_name ?? '',
                    $staff->fName ?? '',
                    $staff->lName ?? '',
                    $staff->category ?? ''
                );
                // Get current appointment and unit (if available)
                $currentAppointment = $staff->appt ?? '';
                $currentUnit = $staff->unit_name ?? '';
            ?>
            <h5 class="staff-name"><?=htmlspecialchars($formattedName)?></h5>
            <p><strong>Service Number:</strong> <span class="service-number"><?=htmlspecialchars($staff->svcNo)?></span></p>
            <p><strong>Rank:</strong> <span class="rank"><?=htmlspecialchars($staff->rank_short_name ?? $staff->rank_name ?? $staff->rankId)?></span></p>
            <p><strong>Unit:</strong> <span class="unit"><?=htmlspecialchars($currentUnit)?></span></p>
            <p><strong>Current Appointment:</strong> <span class="appointment"><?=htmlspecialchars($currentAppointment)?></span></p>
            <p><strong>Appointment Date:</strong> <span class="appointment-date">
                <?php
                if ($currentAppointmentDate) {
                    $today = date('Y-m-d');
                    $isToday = ($currentAppointmentDate === $today);
                    echo htmlspecialchars(date('d-M-Y', strtotime($currentAppointmentDate)));
                    if ($isToday) {
                        echo ' <span class="badge bg-success">Appointed Today</span>';
                    }
                } else {
                    echo '-';
                }
                ?>
            </span></p>
            <p><strong>Date of Birth:</strong> <span class="dob"><?=htmlspecialchars($staff->DOB ?? '-')?></span></p>
            <p><strong>Gender:</strong> <span class="gender"><?=htmlspecialchars($staff->gender ?? '-')?></span></p>
            <p><strong>Email:</strong> <span class="email"><?=htmlspecialchars($staff->email ?? '-')?></span></p>
            <p><strong>Phone:</strong> <span class="phone"><?=htmlspecialchars($staff->tel ?? $staff->phone ?? '-')?></span></p>
            <p><strong>Address:</strong> <span class="address"><?=htmlspecialchars($staff->address ?? '-')?></span></p>
            <p><strong>Status:</strong> <span class="status <?=strtolower($staff->status ?? '')?>"><?=htmlspecialchars($staff->status ?? '-')?></span></p>
            <p><strong>Date of Attestation:</strong> <span class="attest-date"><?=htmlspecialchars($staff->attestation_date ?? '-')?></span></p>
            <p><strong>Current Rank Since:</strong> <span class="rank-date"><?=htmlspecialchars($staff->sub_wef ?? '-')?></span></p>
        </div>
    </div>
    
    <?php if (!empty($promotionHistory)): ?>
    <div class="row mt-3">
        <div class="col-12">
            <h6 class="promotion-history-title">Promotion History</h6>
            <div class="table-responsive">
                <table class="table table-sm table-bordered promotion-history-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Type</th>
                            <th>From</th>
                            <th>To</th>
                            <th>Authority</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($promotionHistory as $p): ?>
                        <tr>
                            <td><?=htmlspecialchars(date('d-M-Y', strtotime($p->dateTo)))?></td>
                            <td><?=htmlspecialchars(ucfirst($p->type))?></td>
                            <td><?=htmlspecialchars($p->old_rank_name)?></td>
                            <td><?=htmlspecialchars($p->new_rank_name)?></td>
                            <td><?=htmlspecialchars($p->authority)?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>
