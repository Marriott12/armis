<?php
/**
 * AJAX Endpoint: Get Appointment History for Staff Member
 * Returns HTML table of all appointments for a given service number
 */

// Start session and include required files
session_start();
require_once 'includes/config.php';
require_once 'includes/db.php';

// Security: Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo '<div class="alert alert-danger">Unauthorized access. Please log in.</div>';
    exit;
}

// Get service number from POST
$serviceNumber = trim($_POST['service_number'] ?? '');

if (empty($serviceNumber)) {
    echo '<div class="alert alert-danger"><i class="fas fa-exclamation-triangle"></i> Invalid service number provided.</div>';
    exit;
}

try {
    $pdo = getDbConnection();
    
    if (!$pdo) {
        throw new Exception("Database connection failed");
    }
    
    // Get staff basic info
    $staffStmt = $pdo->prepare("
        SELECT s.id, s.service_number, s.first_name, s.last_name, 
               r.abbreviation as rank_abbr, r.name as rank_name
        FROM staff s
        LEFT JOIN ranks r ON s.rank_id = r.id
        WHERE s.service_number = ?
        LIMIT 1
    ");
    $staffStmt->execute([$serviceNumber]);
    $staff = $staffStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$staff) {
        echo '<div class="alert alert-warning"><i class="fas fa-info-circle"></i> Staff member not found.</div>';
        exit;
    }
    
    // Get appointment history with appointment type information
    $apptStmt = $pdo->prepare("
        SELECT sa.*, 
               u.name as unit_name,
               at.type_name as appointment_type_name,
               at.is_temporary,
               CASE 
                   WHEN sa.end_date IS NULL THEN 'Active (Permanent)'
                   WHEN sa.end_date >= CURDATE() THEN 'Active'
                   ELSE 'Ended'
               END as status,
               CASE 
                   WHEN sa.end_date IS NULL OR sa.end_date >= CURDATE() THEN 1
                   ELSE 0
               END as is_current,
               DATEDIFF(COALESCE(sa.end_date, CURDATE()), sa.appointment_date) as duration_days
        FROM staff_appointment sa
        LEFT JOIN units u ON sa.unit_id = u.id
        LEFT JOIN appointment_type at ON sa.appointment_type = at.id
        WHERE sa.service_number = ?
        ORDER BY sa.appointment_date DESC, sa.created_at DESC
    ");
    $apptStmt->execute([$serviceNumber]);
    $appointments = $apptStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Display staff info header
    echo '<div class="mb-3">';
    echo '<h6 class="mb-2"><strong>Service Number:</strong> ' . htmlspecialchars($serviceNumber) . '</h6>';
    echo '<h6 class="mb-2"><strong>Name:</strong> ' . htmlspecialchars($staff['first_name'] . ' ' . $staff['last_name']) . '</h6>';
    echo '<h6 class="mb-0"><strong>Rank:</strong> ' . htmlspecialchars($staff['rank_abbr'] ?? $staff['rank_name'] ?? 'N/A') . '</h6>';
    echo '</div>';
    
    if (empty($appointments)) {
        echo '<div class="alert alert-info">';
        echo '<i class="fas fa-info-circle"></i> No appointment history found for this staff member.';
        echo '</div>';
        exit;
    }
    
    // Display appointments table
    echo '<div class="table-responsive">';
    echo '<table class="table table-sm table-striped table-hover">';
    echo '<thead class="table-light">';
    echo '<tr>';
    echo '<th>#</th>';
    echo '<th>Appointment Date</th>';
    echo '<th>Position/Role</th>';
    echo '<th>Unit</th>';
    echo '<th>Type</th>';
    echo '<th>End Date</th>';
    echo '<th>Duration</th>';
    echo '<th>Status</th>';
    echo '<th>Remarks</th>';
    echo '</tr>';
    echo '</thead>';
    echo '<tbody>';
    
    $rowNum = 1;
    foreach ($appointments as $appt) {
        // Calculate duration
        $durationDays = $appt['duration_days'];
        $durationYears = floor($durationDays / 365);
        $durationMonths = floor(($durationDays % 365) / 30);
        $durationText = '';
        
        if ($durationYears > 0) {
            $durationText .= $durationYears . 'y ';
        }
        if ($durationMonths > 0 || $durationYears > 0) {
            $durationText .= $durationMonths . 'm';
        }
        if (empty($durationText)) {
            $durationText = $durationDays . ' days';
        }
        
        // Status badge with Current indicator
        $statusBadge = '';
        $currentBadge = '';
        
        if ($appt['is_current'] == 1) {
            // Active appointment - show Current badge
            $currentBadge = '<span class="badge bg-primary me-1"><i class="fas fa-check-circle"></i> Current</span>';
            if ($appt['status'] === 'Active (Permanent)') {
                $statusBadge = '<span class="badge bg-success">Active (Permanent)</span>';
            } else {
                $statusBadge = '<span class="badge bg-success">Active</span>';
            }
        } else {
            // Ended appointment
            $statusBadge = '<span class="badge bg-secondary">Ended</span>';
        }
        
        // Appointment type badge
        $typeBadge = '';
        if (!empty($appt['appointment_type_name'])) {
            $typeClass = $appt['is_temporary'] ? 'bg-warning text-dark' : 'bg-info';
            $typeBadge = '<span class="badge ' . $typeClass . '">' . htmlspecialchars($appt['appointment_type_name']) . '</span>';
        } else {
            $typeBadge = '<span class="badge bg-secondary">N/A</span>';
        }
        
        // Format dates
        $apptDate = $appt['appointment_date'] ? date('d M Y', strtotime($appt['appointment_date'])) : 'N/A';
        $endDate = $appt['end_date'] ? date('d M Y', strtotime($appt['end_date'])) : '<em class="text-muted">Ongoing</em>';
        
        // Get position/role from appointment_id field
        $position = htmlspecialchars($appt['appointment_id'] ?? 'N/A');
        
        // Get remarks (use remarks field, fallback to comment)
        $remarks = htmlspecialchars($appt['remarks'] ?? $appt['comment'] ?? '');
        if (strlen($remarks) > 60) {
            $remarks = '<span title="' . $remarks . '">' . substr($remarks, 0, 60) . '...</span>';
        }
        if (empty(trim(strip_tags($remarks)))) {
            $remarks = '<span class="text-muted">-</span>';
        }
        
        echo '<tr>';
        echo '<td>' . $rowNum++ . '</td>';
        echo '<td>' . $apptDate . '</td>';
        echo '<td><strong>' . $position . '</strong></td>';
        echo '<td>' . htmlspecialchars($appt['unit_name'] ?? 'N/A') . '</td>';
        echo '<td>' . $typeBadge . '</td>';
        echo '<td>' . $endDate . '</td>';
        echo '<td><em>' . $durationText . '</em></td>';
        echo '<td>' . $currentBadge . $statusBadge . '</td>';
        echo '<td><small>' . $remarks . '</small></td>';
        echo '</tr>';
    }
    
    echo '</tbody>';
    echo '</table>';
    echo '</div>';
    
    // Summary statistics
    $activeCount = 0;
    $endedCount = 0;
    $totalDuration = 0;
    
    foreach ($appointments as $appt) {
        if ($appt['status'] !== 'Ended') {
            $activeCount++;
        } else {
            $endedCount++;
        }
        $totalDuration += $appt['duration_days'];
    }
    
    echo '<div class="mt-3 p-3 bg-light rounded">';
    echo '<div class="row text-center">';
    echo '<div class="col-md-4">';
    echo '<h6 class="text-muted mb-1">Total Appointments</h6>';
    echo '<h4 class="mb-0">' . count($appointments) . '</h4>';
    echo '</div>';
    echo '<div class="col-md-4">';
    echo '<h6 class="text-muted mb-1">Active</h6>';
    echo '<h4 class="mb-0 text-success">' . $activeCount . '</h4>';
    echo '</div>';
    echo '<div class="col-md-4">';
    echo '<h6 class="text-muted mb-1">Ended</h6>';
    echo '<h4 class="mb-0 text-secondary">' . $endedCount . '</h4>';
    echo '</div>';
    echo '</div>';
    echo '</div>';
    
} catch (Exception $e) {
    error_log("Error in ajax_get_appointment_history.php: " . $e->getMessage());
    echo '<div class="alert alert-danger">';
    echo '<i class="fas fa-exclamation-triangle"></i> ';
    echo 'An error occurred while loading appointment history. Please try again.';
    echo '</div>';
}
?>
