<?php
// Define module constants
define('ARMIS_ADMIN_BRANCH', true);

// Include admin branch authentication and database
require_once __DIR__ . '/includes/auth.php';
require_once dirname(__DIR__) . '/shared/database_connection.php';

// Require authentication
requireAuth();

$pageTitle = "Appointment Reports - Admin Branch";
$moduleName = "Admin Branch";
$moduleIcon = "users-cog";
$currentPage = "reports";

$sidebarLinks = [
    ['title' => 'Dashboard', 'url' => '/Armis2/admin_branch/index.php', 'icon' => 'tachometer-alt', 'page' => 'dashboard'],
    ['title' => 'Create Staff', 'url' => '/Armis2/admin_branch/create_staff.php', 'icon' => 'user-plus', 'page' => 'create_staff'],
    ['title' => 'Edit Staff', 'url' => '/Armis2/admin_branch/edit_staff.php', 'icon' => 'user-edit', 'page' => 'edit_staff'],
    ['title' => 'Appointments', 'url' => '/Armis2/admin_branch/appointments.php', 'icon' => 'briefcase', 'page' => 'appointments'],
    ['title' => 'Batch Appointments', 'url' => '/Armis2/admin_branch/batch_appointments.php', 'icon' => 'tasks', 'page' => 'batch_appointments'],
    ['title' => 'Pending Approvals', 'url' => '/Armis2/admin_branch/pending_appointments.php', 'icon' => 'clock', 'page' => 'pending_appointments'],
    ['title' => 'Appointment History', 'url' => '/Armis2/admin_branch/appointment_history.php', 'icon' => 'history', 'page' => 'appointment_history'],
    ['title' => 'Appointment Types', 'url' => '/Armis2/admin_branch/appointment_types.php', 'icon' => 'clipboard-list', 'page' => 'appointment_types'],
    ['title' => 'Medals', 'url' => '/Armis2/admin_branch/medals.php', 'icon' => 'medal', 'page' => 'medals'],
    [
        'title' => 'Reports',
        'icon' => 'chart-bar',
        'page' => 'reports',
        'children' => [
            ['title' => 'Appointments', 'url' => '/Armis2/admin_branch/reports_appointment.php'],
            ['title' => 'Seniority', 'url' => '/Armis2/admin_branch/reports_seniority.php'],
            ['title' => 'Unit List', 'url' => '/Armis2/admin_branch/reports_units.php'],
            ['title' => 'Contracts', 'url' => '/Armis2/admin_branch/reports_contract.php'],
            ['title' => 'Courses', 'url' => '/Armis2/admin_branch/reports_courses.php'],
            ['title' => 'Deceased', 'url' => '/Armis2/admin_branch/reports_deceased.php'],
            ['title' => 'Gender', 'url' => '/Armis2/admin_branch/reports_gender.php'],
            ['title' => 'Marital', 'url' => '/Armis2/admin_branch/reports_marital.php'],
            ['title' => 'Rank', 'url' => '/Armis2/admin_branch/reports_rank.php'],
            ['title' => 'Retired', 'url' => '/Armis2/admin_branch/reports_retired.php'],
            ['title' => 'Trade', 'url' => '/Armis2/admin_branch/reports_trade.php'],
            ['title' => 'Corps', 'url' => '/Armis2/admin_branch/reports_corps.php']
        ]
    ],
];
];

$pdo = getDbConnection();

$errors = [];
$reportData = null;
$pdo = getDbConnection();

// Get all appointment types
try {
    $typesStmt = $pdo->query("SELECT id, name FROM appointment_types ORDER BY name");
    $appointmentTypes = $typesStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $errors[] = "Error fetching appointment types: " . $e->getMessage();
    $appointmentTypes = [];
}

// Get all units
try {
    $unitsStmt = $pdo->query("SELECT id, name FROM units ORDER BY name");
    $units = $unitsStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $errors[] = "Error fetching units: " . $e->getMessage();
    $units = [];
}

// Define report types
$reportTypes = [
    'current_appointments' => 'Current Appointments',
    'appointments_by_type' => 'Appointments by Type',
    'appointments_by_unit' => 'Appointments by Unit',
    'temporary_appointments' => 'Temporary Appointments',
    'expired_appointments' => 'Expired Appointments',
    'upcoming_expirations' => 'Upcoming Expirations (30 Days)',
    'recent_appointments' => 'Recent Appointments (30 Days)',
    'approval_statistics' => 'Approval Statistics'
];

// Process report request
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['report_type'])) {
    $reportType = $_GET['report_type'];
    $unitId = isset($_GET['unit_id']) ? (int)$_GET['unit_id'] : null;
    $typeId = isset($_GET['type_id']) ? (int)$_GET['type_id'] : null;
    $startDate = isset($_GET['start_date']) ? $_GET['start_date'] : null;
    $endDate = isset($_GET['end_date']) ? $_GET['end_date'] : null;
    
    try {
        switch ($reportType) {
            case 'current_appointments':
                $sql = "
                    SELECT 
                        sa.id, 
                        s.service_number, 
                        CONCAT(s.rank_id, ' ', s.last_name, ', ', s.first_name) AS staff_name,
                        u.name AS unit_name,
                        at.name AS appointment_type,
                        at.is_temporary,
                        sa.position,
                        sa.start_date,
                        sa.end_date,
                        sa.status
                    FROM staff_appointment sa
                    JOIN staff s ON sa.staff_id = s.id
                    JOIN units u ON sa.unit_id = u.id
                    JOIN appointment_types at ON sa.appointment_type_id = at.id
                    WHERE sa.status = 'approved'
                ";
                
                // Apply filters
                if ($unitId) {
                    $sql .= " AND sa.unit_id = :unit_id";
                }
                if ($typeId) {
                    $sql .= " AND sa.appointment_type_id = :type_id";
                }
                
                $sql .= " ORDER BY u.name, s.last_name, s.first_name";
                
                $stmt = $pdo->prepare($sql);
                
                if ($unitId) {
                    $stmt->bindParam(':unit_id', $unitId);
                }
                if ($typeId) {
                    $stmt->bindParam(':type_id', $typeId);
                }
                
                $stmt->execute();
                $reportData = $stmt->fetchAll(PDO::FETCH_ASSOC);
                break;
                
            case 'appointments_by_type':
                $sql = "
                    SELECT 
                        at.name AS appointment_type,
                        at.is_temporary,
                        COUNT(sa.id) AS appointment_count
                    FROM appointment_types at
                    LEFT JOIN staff_appointment sa ON at.id = sa.appointment_type_id AND sa.status = 'approved'
                    GROUP BY at.id
                    ORDER BY appointment_count DESC, at.name
                ";
                $stmt = $pdo->prepare($sql);
                $stmt->execute();
                $reportData = $stmt->fetchAll(PDO::FETCH_ASSOC);
                break;
                
            case 'appointments_by_unit':
                $sql = "
                    SELECT 
                        u.name AS unit_name,
                        COUNT(sa.id) AS appointment_count
                    FROM units u
                    LEFT JOIN staff_appointment sa ON u.id = sa.unit_id AND sa.status = 'approved'
                    GROUP BY u.id
                    ORDER BY appointment_count DESC, u.name
                ";
                $stmt = $pdo->prepare($sql);
                $stmt->execute();
                $reportData = $stmt->fetchAll(PDO::FETCH_ASSOC);
                break;
                
            case 'temporary_appointments':
                $sql = "
                    SELECT 
                        sa.id, 
                        s.service_number, 
                        CONCAT(s.rank_id, ' ', s.last_name, ', ', s.first_name) AS staff_name,
                        u.name AS unit_name,
                        at.name AS appointment_type,
                        sa.position,
                        sa.start_date,
                        sa.end_date,
                        DATEDIFF(sa.end_date, CURDATE()) AS days_remaining
                    FROM staff_appointment sa
                    JOIN staff s ON sa.staff_id = s.id
                    JOIN units u ON sa.unit_id = u.id
                    JOIN appointment_types at ON sa.appointment_type_id = at.id
                    WHERE at.is_temporary = 1
                    AND sa.status = 'approved'
                    AND sa.end_date >= CURDATE()
                ";
                
                // Apply filters
                if ($unitId) {
                    $sql .= " AND sa.unit_id = :unit_id";
                }
                
                $sql .= " ORDER BY days_remaining, u.name, s.last_name";
                
                $stmt = $pdo->prepare($sql);
                
                if ($unitId) {
                    $stmt->bindParam(':unit_id', $unitId);
                }
                
                $stmt->execute();
                $reportData = $stmt->fetchAll(PDO::FETCH_ASSOC);
                break;
                
            case 'expired_appointments':
                $sql = "
                    SELECT 
                        sa.id, 
                        s.service_number, 
                        CONCAT(s.rank_id, ' ', s.last_name, ', ', s.first_name) AS staff_name,
                        u.name AS unit_name,
                        at.name AS appointment_type,
                        sa.position,
                        sa.start_date,
                        sa.end_date,
                        DATEDIFF(CURDATE(), sa.end_date) AS days_expired
                    FROM staff_appointment sa
                    JOIN staff s ON sa.staff_id = s.id
                    JOIN units u ON sa.unit_id = u.id
                    JOIN appointment_types at ON sa.appointment_type_id = at.id
                    WHERE at.is_temporary = 1
                    AND sa.status = 'approved'
                    AND sa.end_date < CURDATE()
                ";
                
                // Apply filters
                if ($unitId) {
                    $sql .= " AND sa.unit_id = :unit_id";
                }
                
                $sql .= " ORDER BY days_expired DESC, u.name, s.last_name";
                
                $stmt = $pdo->prepare($sql);
                
                if ($unitId) {
                    $stmt->bindParam(':unit_id', $unitId);
                }
                
                $stmt->execute();
                $reportData = $stmt->fetchAll(PDO::FETCH_ASSOC);
                break;
                
            case 'upcoming_expirations':
                $sql = "
                    SELECT 
                        sa.id, 
                        s.service_number, 
                        CONCAT(s.rank_id, ' ', s.last_name, ', ', s.first_name) AS staff_name,
                        u.name AS unit_name,
                        at.name AS appointment_type,
                        sa.position,
                        sa.start_date,
                        sa.end_date,
                        DATEDIFF(sa.end_date, CURDATE()) AS days_remaining
                    FROM staff_appointment sa
                    JOIN staff s ON sa.staff_id = s.id
                    JOIN units u ON sa.unit_id = u.id
                    JOIN appointment_types at ON sa.appointment_type_id = at.id
                    WHERE at.is_temporary = 1
                    AND sa.status = 'approved'
                    AND sa.end_date >= CURDATE()
                    AND sa.end_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)
                ";
                
                // Apply filters
                if ($unitId) {
                    $sql .= " AND sa.unit_id = :unit_id";
                }
                
                $sql .= " ORDER BY days_remaining, u.name, s.last_name";
                
                $stmt = $pdo->prepare($sql);
                
                if ($unitId) {
                    $stmt->bindParam(':unit_id', $unitId);
                }
                
                $stmt->execute();
                $reportData = $stmt->fetchAll(PDO::FETCH_ASSOC);
                break;
                
            case 'recent_appointments':
                $sql = "
                    SELECT 
                        sa.id, 
                        s.service_number, 
                        CONCAT(s.rank_id, ' ', s.last_name, ', ', s.first_name) AS staff_name,
                        u.name AS unit_name,
                        at.name AS appointment_type,
                        at.is_temporary,
                        sa.position,
                        sa.start_date,
                        sa.end_date,
                        sa.created_at
                    FROM staff_appointment sa
                    JOIN staff s ON sa.staff_id = s.id
                    JOIN units u ON sa.unit_id = u.id
                    JOIN appointment_types at ON sa.appointment_type_id = at.id
                    WHERE sa.created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
                    AND sa.status = 'approved'
                ";
                
                // Apply filters
                if ($unitId) {
                    $sql .= " AND sa.unit_id = :unit_id";
                }
                if ($typeId) {
                    $sql .= " AND sa.appointment_type_id = :type_id";
                }
                
                $sql .= " ORDER BY sa.created_at DESC, u.name, s.last_name";
                
                $stmt = $pdo->prepare($sql);
                
                if ($unitId) {
                    $stmt->bindParam(':unit_id', $unitId);
                }
                if ($typeId) {
                    $stmt->bindParam(':type_id', $typeId);
                }
                
                $stmt->execute();
                $reportData = $stmt->fetchAll(PDO::FETCH_ASSOC);
                break;
                
            case 'approval_statistics':
                $sql = "
                    SELECT 
                        status,
                        COUNT(*) AS count,
                        ROUND(COUNT(*) / (SELECT COUNT(*) FROM staff_appointment) * 100, 1) AS percentage
                    FROM staff_appointment
                    GROUP BY status
                    ORDER BY count DESC
                ";
                $stmt = $pdo->prepare($sql);
                $stmt->execute();
                $reportData = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                // Get average approval time
                $approvalTimeSql = "
                    SELECT 
                        AVG(TIMESTAMPDIFF(HOUR, sa.created_at, aa.updated_at)) AS avg_approval_hours
                    FROM staff_appointment sa
                    JOIN appointment_approvals aa ON sa.id = aa.appointment_id
                    WHERE sa.status = 'approved'
                    AND aa.status = 'approved'
                ";
                $approvalTimeStmt = $pdo->prepare($approvalTimeSql);
                $approvalTimeStmt->execute();
                $approvalTimeData = $approvalTimeStmt->fetch(PDO::FETCH_ASSOC);
                
                if ($approvalTimeData && $approvalTimeData['avg_approval_hours']) {
                    $reportData[] = [
                        'status' => 'Average Approval Time',
                        'count' => round($approvalTimeData['avg_approval_hours'], 1) . ' hours',
                        'percentage' => ''
                    ];
                }
                break;
                
            default:
                $errors[] = "Invalid report type selected.";
                break;
        }
    } catch (Exception $e) {
        $errors[] = "Error generating report: " . $e->getMessage();
    }
}
include dirname(__DIR__) . '/shared/header.php';
include dirname(__DIR__) . '/shared/sidebar.php';
?>

<!-- Main Content -->
<div class="content-wrapper with-sidebar">
    <div class="container-fluid">
        <div class="main-content">
            <div class="row">
                <div class="col-12">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h1 class="section-title">
                            <i class="fas fa-chart-bar"></i> Appointment Reports
                        </h1>
                        <div>
                            <a href="/Armis2/admin_branch/appointments.php" class="btn btn-outline-primary">
                                <i class="fas fa-briefcase"></i> Appointments
                            </a>
                            <a href="/Armis2/admin_branch/index.php" class="btn btn-outline-secondary">
                                <i class="fas fa-arrow-left"></i> Back to Dashboard
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <?php if ($errors): ?>
                <div class="alert alert-danger">
                    <ul class="mb-0">
                        <?php foreach ($errors as $error): ?>
                            <li><?= htmlspecialchars($error) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <div class="row">
                <div class="col-md-3">
                    <div class="card shadow-sm mb-4">
                        <div class="card-header bg-primary text-white">
                            <h4 class="mb-0"><i class="fas fa-filter"></i> Report Options</h4>
                        </div>
                        <div class="card-body">
                            <form action="" method="get" id="reportForm">
                                <div class="mb-3">
                                    <label for="report_type" class="form-label">Report Type</label>
                                    <select name="report_type" id="report_type" class="form-select" required>
                                        <option value="">Select Report Type</option>
                                        <?php foreach ($reportTypes as $key => $value): ?>
                                            <option value="<?= $key ?>" <?= isset($_GET['report_type']) && $_GET['report_type'] === $key ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($value) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div class="mb-3 filter-option" id="unit_filter">
                                    <label for="unit_id" class="form-label">Unit</label>
                                    <select name="unit_id" id="unit_id" class="form-select">
                                        <option value="">All Units</option>
                                        <?php foreach ($units as $unit): ?>
                                            <option value="<?= $unit['id'] ?>" <?= isset($_GET['unit_id']) && $_GET['unit_id'] == $unit['id'] ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($unit['name']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div class="mb-3 filter-option" id="type_filter">
                                    <label for="type_id" class="form-label">Appointment Type</label>
                                    <select name="type_id" id="type_id" class="form-select">
                                        <option value="">All Types</option>
                                        <?php foreach ($appointmentTypes as $type): ?>
                                            <option value="<?= $type['id'] ?>" <?= isset($_GET['type_id']) && $_GET['type_id'] == $type['id'] ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($type['name']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div class="mb-3 filter-option" id="date_filter">
                                    <label for="start_date" class="form-label">Start Date</label>
                                    <input type="date" name="start_date" id="start_date" class="form-control" value="<?= isset($_GET['start_date']) ? $_GET['start_date'] : '' ?>">
                                </div>
                                
                                <div class="mb-3 filter-option" id="end_date_filter">
                                    <label for="end_date" class="form-label">End Date</label>
                                    <input type="date" name="end_date" id="end_date" class="form-control" value="<?= isset($_GET['end_date']) ? $_GET['end_date'] : '' ?>">
                                </div>
                                
                                <div class="d-grid">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-search"></i> Generate Report
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                    
                    <div class="card shadow-sm">
                        <div class="card-header bg-info text-white">
                            <h4 class="mb-0"><i class="fas fa-download"></i> Export Options</h4>
                        </div>
                        <div class="card-body">
                            <div class="d-grid gap-2">
                                <button id="export-csv" class="btn btn-outline-primary" <?= $reportData ? '' : 'disabled' ?>>
                                    <i class="fas fa-file-csv"></i> Export as CSV
                                </button>
                                <button id="export-print" class="btn btn-outline-dark" <?= $reportData ? '' : 'disabled' ?>>
                                    <i class="fas fa-print"></i> Print Report
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-9">
                    <div class="card shadow-sm">
                        <div class="card-header bg-success text-white">
                            <h4 class="mb-0">
                                <i class="fas fa-table"></i> 
                                <?= isset($_GET['report_type']) && isset($reportTypes[$_GET['report_type']]) ? htmlspecialchars($reportTypes[$_GET['report_type']]) : 'Report Results' ?>
                            </h4>
                        </div>
                        <div class="card-body" id="report-container">
                            <?php if ($reportData === null): ?>
                                <div class="alert alert-info">
                                    <i class="fas fa-info-circle"></i> Select a report type and click "Generate Report" to view results.
                                </div>
                            <?php elseif (empty($reportData)): ?>
                                <div class="alert alert-warning">
                                    <i class="fas fa-exclamation-triangle"></i> No data found for the selected report criteria.
                                </div>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-striped table-hover" id="report-table">
                                        <thead>
                                            <tr>
                                                <?php
                                                // Generate headers based on the first row of data
                                                $headers = array_keys($reportData[0]);
                                                foreach ($headers as $header): 
                                                    $headerTitle = ucwords(str_replace('_', ' ', $header));
                                                ?>
                                                    <th><?= htmlspecialchars($headerTitle) ?></th>
                                                <?php endforeach; ?>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($reportData as $row): ?>
                                                <tr>
                                                    <?php foreach ($row as $key => $value): ?>
                                                        <td>
                                                            <?php 
                                                            // Format values based on column type
                                                            if ($key === 'is_temporary') {
                                                                echo $value ? 'Yes' : 'No';
                                                            } elseif (in_array($key, ['start_date', 'end_date']) && !empty($value)) {
                                                                echo date('Y-m-d', strtotime($value));
                                                            } elseif ($key === 'created_at' && !empty($value)) {
                                                                echo date('Y-m-d H:i', strtotime($value));
                                                            } elseif ($key === 'days_remaining' && !is_null($value)) {
                                                                echo $value . ' days';
                                                            } elseif ($key === 'days_expired' && !is_null($value)) {
                                                                echo $value . ' days';
                                                            } elseif ($key === 'status') {
                                                                switch ($value) {
                                                                    case 'approved':
                                                                        echo '<span class="badge bg-success">Approved</span>';
                                                                        break;
                                                                    case 'pending':
                                                                        echo '<span class="badge bg-warning">Pending</span>';
                                                                        break;
                                                                    case 'rejected':
                                                                        echo '<span class="badge bg-danger">Rejected</span>';
                                                                        break;
                                                                    default:
                                                                        echo htmlspecialchars($value);
                                                                }
                                                            } else {
                                                                echo htmlspecialchars($value);
                                                            }
                                                            ?>
                                                        </td>
                                                    <?php endforeach; ?>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                                
                                <div class="mt-3">
                                    <small class="text-muted">
                                        <i class="fas fa-info-circle"></i> 
                                        Showing <?= count($reportData) ?> results. 
                                        Generated on <?= date('Y-m-d H:i:s') ?>
                                    </small>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
    $(document).ready(function() {
        // Toggle filters based on report type
        $('#report_type').on('change', function() {
            const reportType = $(this).val();
            
            // Hide all filters first
            $('.filter-option').hide();
            
            // Show relevant filters based on report type
            switch(reportType) {
                case 'current_appointments':
                    $('#unit_filter, #type_filter').show();
                    break;
                case 'appointments_by_type':
                    // No filters needed
                    break;
                case 'appointments_by_unit':
                    // No filters needed
                    break;
                case 'temporary_appointments':
                case 'expired_appointments':
                case 'upcoming_expirations':
                    $('#unit_filter').show();
                    break;
                case 'recent_appointments':
                    $('#unit_filter, #type_filter').show();
                    break;
                case 'approval_statistics':
                    // No filters needed
                    break;
            }
        });
        
        // Trigger the change event to set initial state
        $('#report_type').trigger('change');
        
        // Export to CSV
        $('#export-csv').on('click', function() {
            if (!$('#report-table').length) {
                alert('No report data available to export');
                return;
            }
            
            let csvContent = "data:text/csv;charset=utf-8,";
            
            // Add headers
            const headers = [];
            $('#report-table thead th').each(function() {
                headers.push($(this).text());
            });
            csvContent += headers.join(',') + "\r\n";
            
            // Add rows
            $('#report-table tbody tr').each(function() {
                const row = [];
                $(this).find('td').each(function() {
                    // Clean up the text (remove HTML and extra spaces)
                    let cellText = $(this).text().trim().replace(/,/g, ';');
                    row.push('"' + cellText + '"');
                });
                csvContent += row.join(',') + "\r\n";
            });
            
            // Create download link
            const encodedUri = encodeURI(csvContent);
            const link = document.createElement("a");
            link.setAttribute("href", encodedUri);
            link.setAttribute("download", "appointment_report_" + $('#report_type option:selected').text().replace(/\s+/g, '_').toLowerCase() + "_" + new Date().toISOString().split('T')[0] + ".csv");
            document.body.appendChild(link);
            
            // Download
            link.click();
            document.body.removeChild(link);
        });
        
        // Print report
        $('#export-print').on('click', function() {
            if (!$('#report-table').length) {
                alert('No report data available to print');
                return;
            }
            
            const reportTitle = $('#report_type option:selected').text();
            const printWindow = window.open('', '_blank');
            
            printWindow.document.write(`
                <!DOCTYPE html>
                <html>
                <head>
                    <title>${reportTitle} Report</title>
                    <style>
                        body {
                            font-family: Arial, sans-serif;
                            margin: 20px;
                        }
                        h1 {
                            text-align: center;
                            margin-bottom: 20px;
                        }
                        table {
                            width: 100%;
                            border-collapse: collapse;
                            margin-bottom: 20px;
                        }
                        th, td {
                            border: 1px solid #ddd;
                            padding: 8px;
                            text-align: left;
                        }
                        th {
                            background-color: #f2f2f2;
                        }
                        tr:nth-child(even) {
                            background-color: #f9f9f9;
                        }
                        .footer {
                            text-align: center;
                            margin-top: 20px;
                            font-size: 12px;
                            color: #666;
                        }
                    </style>
                </head>
                <body>
                    <h1>${reportTitle} Report</h1>
                    ${$('#report-container').html()}
                    <div class="footer">
                        Generated on ${new Date().toLocaleString()} | Armis2 Appointment System
                    </div>
                    <script>
                        window.onload = function() { window.print(); }
                    </script>
                </body>
                </html>
            `);
            
            printWindow.document.close();
        });
    });
</script>

<?php include dirname(__DIR__) . '/shared/footer.php'; ?>
                <i class="fas fa-question-circle me-2"></i>
                <span>
                    Use the filters to narrow down staff by appointment, rank, unit, or category. Use the search box for instant filtering. Export, print, show/hide columns. Double-click row for details.
                </span>
                <button type="button" class="btn btn-sm btn-outline-info ms-auto" data-bs-toggle="modal" data-bs-target="#helpModal" title="Show Help"><i class="fa fa-info-circle"></i> Help</button>
            </div>
            <div class="modal fade" id="helpModal" tabindex="-1" aria-labelledby="helpModalLabel" aria-hidden="true">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="helpModalLabel">Report Help</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <ul>
                                <li>Use filters and search for instant results.</li>
                                <li>Export: CSV, Excel, PDF. Print for a print-friendly table.</li>
                                <li>Show/hide columns using the checkboxes.</li>
                                <li>Double-click row for history/audit details.</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
            <form class="row g-3 mb-4" method="get" action="">
                <div class="col-md-2">
                    <select name="appointment" id="apptFilter" class="form-select">
                        <option value="">All Appointments</option>
                        <?php foreach ($appts as $a): ?>
                            <option value="<?= htmlspecialchars($a) ?>" <?= ($filter_appt == $a) ? 'selected':''?>><?= htmlspecialchars($a) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <select name="rankID" id="rankFilter" class="form-select">
                        <option value="">Rank</option>
                        <?php foreach ($ranks as $r): ?>
                            <option value="<?= $r->id ?>" <?= ($filter_rank == $r->id) ? 'selected':''?>><?= htmlspecialchars($r->name) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <select name="unitID" id="unitFilter" class="form-select">
                        <option value="">Unit</option>
                        <?php foreach ($units as $u): ?>
                            <option value="<?= $u->id ?>" <?= ($filter_unit == $u->id) ? 'selected':''?>><?= htmlspecialchars($u->name) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <select name="category" id="categoryFilter" class="form-select">
                        <option value="">Category</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= htmlspecialchars($cat->category) ?>" <?= ($filter_category == $cat->category) ? 'selected' : '' ?>><?= htmlspecialchars($cat->category) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <input type="text" id="appointmentSearch" name="search" class="form-control" placeholder="Quick Search..." value="<?=htmlspecialchars($search)?>">
                </div>
                <div class="col-md-1">
                    <select name="per_page" class="form-select">
                        <?php foreach ([10,25,50,100] as $pp): ?>
                        <option value="<?= $pp ?>" <?= ($per_page == $pp) ? 'selected' : '' ?>><?= $pp ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </form>
            <div class="mb-2">
                <strong>Show/Hide Columns:</strong>
                <?php $columns = [
                    'appt'=>'Appointment','rank'=>'Rank','service_number'=>'Service No','surname'=>'Surname','first_name'=>'First Name(s)',
                    'unit'=>'Unit','category'=>'Category','DOB'=>'Date of Birth','attestDate'=>'Date of Enlistment'
                ];
                foreach ($columns as $key=>$label): ?>
                <label class="me-3"><input type="checkbox" class="toggle-col" data-col="<?= $key ?>" checked> <?= $label ?></label>
                <?php endforeach; ?>
            </div>
            <div class="table-responsive print-friendly">
                <table class="table table-bordered table-hover align-middle" id="appointmentTable">
                    <thead class="table-light">
                        <tr>
                            <th>#</th>
                            <?php foreach ($columns as $key => $label): ?>
                                <th class="col-<?= $key ?>"><?= $label ?></th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!$staff): ?>
                            <tr><td colspan="<?= count($columns)+1 ?>" class="text-center text-muted">No staff found.</td></tr>
                        <?php else: $i=1; foreach($staff as $s): ?>
                            <tr ondblclick="alert('Audit/History details coming soon.')">
                                <td><?= $i++ ?></td>
                                <td class="col-appt"><?= htmlspecialchars($s->appt ?? '') ?></td>
                                <td class="col-rank"><?= htmlspecialchars($s->rankName ?? '') ?></td>
                                <td class="col-service_number"><?= htmlspecialchars($s->service_number ?? '') ?></td>
                                <td class="col-surname"><?= htmlspecialchars($s->last_name ?? '') ?></td>
                                <td class="col-first_name"><?= htmlspecialchars($s->first_name ?? '') ?></td>
                                <td class="col-unit"><?= htmlspecialchars($s->unitName ?? '') ?></td>
                                <td class="col-category"><?= htmlspecialchars($s->category ?? '') ?></td>
                                <td class="col-DOB"><?= htmlspecialchars($s->DOB ?? '') ?></td>
                                <td class="col-attestDate"><?= htmlspecialchars($s->attestDate ?? '') ?></td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
                <div class="text-end mt-2">
                    <button onclick="window.print()" class="btn btn-outline-secondary btn-sm print-btn"><i class="fa fa-print"></i> Print Report</button>
                    <button id="exportCSVBtn" class="btn btn-outline-success btn-sm ms-2"><i class="fa fa-file-csv"></i> Export CSV</button>
                    <button id="exportExcelBtn" class="btn btn-outline-success btn-sm"><i class="fa fa-file-excel"></i> Excel</button>
                    <button id="exportPDFBtn" class="btn btn-outline-danger btn-sm"><i class="fa fa-file-pdf"></i> PDF</button>
                </div>
            </div>
            <div class="d-flex justify-content-center my-3">
                <nav aria-label="Appointments pagination">
                    <ul class="pagination pagination-sm">
                        <?php
                        $max_links=7;
                        $start=max(1,$page-intval($max_links/2));
                        $end=$start+$max_links-1;
                        for ($p=$start;$p<=$end;$p++): ?>
                        <li class="page-item<?= ($p==$page)?' active':''?>">
                            <a class="page-link" href="?<?= http_build_query(array_merge($_GET,['page'=>$p])) ?>"><?= $p ?></a>
                        </li>
                        <?php endfor; ?>
                    </ul>
                </nav>
            </div>
        </div>
    </div>
</div>
<style>
@media print {
    body * { visibility: hidden !important; }
    .print-friendly, .print-friendly * { visibility: visible !important; }
    .print-friendly { position: absolute !important; left: 0; top: 0; width: 100vw; }
}
</style>
<script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.20.0/xlsx.full.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script>
document.querySelectorAll('.toggle-col').forEach(function(box) {
    let saved = localStorage.getItem('col-' + box.dataset.col);
    if (saved !== null) box.checked = saved === 'true';
    box.addEventListener('change', function() {
        var col = this.getAttribute('data-col');
        var show = this.checked;
        localStorage.setItem('col-' + col, show);
        document.querySelectorAll('.col-' + col).forEach(function(cell) {
            cell.style.display = show ? '' : 'none';
        });
    });
    document.querySelectorAll('.col-' + box.dataset.col).forEach(function(cell) {
        cell.style.display = box.checked ? '' : 'none';
    });
});
document.getElementById('exportCSVBtn').addEventListener('click', function() {
    let table = document.getElementById('appointmentTable');
    let rows = Array.from(table.rows);
    let visibleCols = [];
    rows[0].querySelectorAll('th').forEach(function(th, idx) {
        if (th.offsetParent !== null) visibleCols.push(idx);
    });
    let csv = rows.map(row => {
        let cells = Array.from(row.children);
        return visibleCols.map(i => {
            let text = cells[i] ? cells[i].innerText.replace(/"/g, '""') : '';
            return '"' + text + '"';
        }).join(',');
    }).join('\n');
    let blob = new Blob([csv], {type:'text/csv'});
    let link = document.createElement('a');
    link.href = URL.createObjectURL(blob);
    link.download = 'appointments_report.csv';
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
});
document.getElementById('exportExcelBtn').addEventListener('click', function() {
    let table = document.getElementById('appointmentTable');
    let wb = XLSX.utils.table_to_book(table, {sheet:"Appointments"});
    XLSX.writeFile(wb, 'appointments_report.xlsx');
});
document.getElementById('exportPDFBtn').addEventListener('click', function(){
    let table = document.getElementById('appointmentTable');
    let rows = Array.from(table.rows).map(row => Array.from(row.cells).map(cell => cell.innerText));
    const { jsPDF } = window.jspdf;
    let doc = new jsPDF();
    let startY = 20;
    doc.text("Appointments Report", 14, startY);
    rows.forEach(function(row, idx){
        doc.text(row.join(" | "), 14, startY + 8 + idx*8);
    });
    doc.save("appointments_report.pdf");
});
document.querySelector('.print-btn').addEventListener('click', function(){
    window.print();
});
['apptFilter','unitFilter','rankFilter','categoryFilter'].forEach(function(id){
    document.getElementById(id).addEventListener('change', function(){
        document.forms[0].submit();
    });
});
document.getElementById('appointmentSearch').addEventListener('input', function() {
    const query = this.value.toLowerCase();
    document.querySelectorAll('#appointmentTable tbody tr').forEach(function(row) {
        const text = row.textContent.toLowerCase();
        row.style.display = text.includes(query) ? '' : 'none';
    });
});
</script>
<?php include dirname(__DIR__) . '/shared/footer.php'; ?>