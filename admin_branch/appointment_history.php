<?php
// Define module constants
define('ARMIS_ADMIN_BRANCH', true);

// Include admin branch authentication and database
require_once __DIR__ . '/includes/auth.php';
require_once dirname(__DIR__) . '/shared/database_connection.php';

// Require authentication
requireAuth();

$pageTitle = "Appointment History - Admin Branch";
$moduleName = "Admin Branch";
$moduleIcon = "users-cog";
$currentPage = "appointment_history";

$sidebarLinks = [
    ['title' => 'Dashboard', 'url' => '/Armis2/admin_branch/index.php', 'icon' => 'tachometer-alt', 'page' => 'dashboard'],
    ['title' => 'Create Staff', 'url' => '/Armis2/admin_branch/create_staff.php', 'icon' => 'user-plus', 'page' => 'create_staff'],
    ['title' => 'Edit Staff', 'url' => '/Armis2/admin_branch/edit_staff.php', 'icon' => 'user-edit', 'page' => 'edit_staff'],
    ['title' => 'Appointments', 'url' => '/Armis2/admin_branch/appointments.php', 'icon' => 'briefcase', 'page' => 'appointments'],
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
            // Other report links...
        ]
    ],
];

$errors = [];
$pdo = getDbConnection();

// Get search parameters
$serviceNumber = isset($_GET['svcNo']) ? trim($_GET['svcNo']) : '';
$unitId = isset($_GET['unitId']) ? (int)$_GET['unitId'] : 0;
$startDate = isset($_GET['startDate']) ? trim($_GET['startDate']) : '';
$endDate = isset($_GET['endDate']) ? trim($_GET['endDate']) : '';
$status = isset($_GET['status']) ? trim($_GET['status']) : '';
$appointmentTypeId = isset($_GET['appointment_type_id']) ? (int)$_GET['appointment_type_id'] : 0;

// Build query and parameters for appointments
$where = [];
$params = [];
$sql = "
    SELECT 
        sa.id, 
        sa.svcNo, 
        s.fName, 
        s.lName, 
        r.rankId as rank_name,
        u.name as unit_name, 
        at.name as appointment_type, 
        at.is_temporary,
        sa.position,
        sa.appointment_date, 
        sa.startDate,
        sa.endDate,
        sa.comment, 
        sa.status,
        sa.createdAt,
        sa.approval_date,
        u2.username as createdBy,
        u3.username as approved_by
    FROM 
        staff_appointment sa
    JOIN 
        staff s ON sa.svcNo = s.id
    JOIN 
        ranks r ON s.rankId = r.id
    JOIN 
        units u ON sa.unitId = u.id
    JOIN 
        appointment_types at ON sa.appointment_type_id = at.id
    JOIN 
        users u2 ON sa.createdBy = u2.id
    LEFT JOIN 
        users u3 ON sa.approved_by = u3.id
";

if (!empty($serviceNumber)) {
    $where[] = "sa.svcNo LIKE ?";
    $params[] = "%$serviceNumber%";
}

if ($unitId > 0) {
    $where[] = "sa.unitId = ?";
    $params[] = $unitId;
}

if (!empty($startDate)) {
    $where[] = "sa.startDate >= ?";
    $params[] = $startDate;
}

if (!empty($endDate)) {
    $where[] = "sa.startDate <= ?";
    $params[] = $endDate;
}

if (!empty($status)) {
    $where[] = "sa.status = ?";
    $params[] = $status;
}

if ($appointmentTypeId > 0) {
    $where[] = "sa.appointment_type_id = ?";
    $params[] = $appointmentTypeId;
}

// Add WHERE clause if filters are applied
if (!empty($where)) {
    $sql .= " WHERE " . implode(" AND ", $where);
}

$sql .= " ORDER BY sa.createdAt DESC LIMIT 100";

// Get all units for filter dropdown
try {
    $unitsStmt = $pdo->query("SELECT unitId as id, code as name FROM unit ORDER BY code");
    $units = $unitsStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $errors[] = "Error fetching units: " . $e->getMessage();
    $units = [];
}

// Get all appointment types for filter dropdown
try {
    $typesStmt = $pdo->query("SELECT id, name FROM appointment_types ORDER BY name");
    $appointmentTypes = $typesStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $errors[] = "Error fetching appointment types: " . $e->getMessage();
    $appointmentTypes = [];
}

// Execute the main query
try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $errors[] = "Error fetching appointments: " . $e->getMessage();
    $appointments = [];
}

// Individual appointment details if requested
$appointmentDetails = null;
$appointmentHistory = [];
if (isset($_GET['appointment_id']) && is_numeric($_GET['appointment_id'])) {
    $appointmentId = (int)$_GET['appointment_id'];
    
    try {
        // Get appointment details
        $detailStmt = $pdo->prepare("
            SELECT 
                sa.id, 
                sa.svcNo, 
                s.fName, 
                s.lName, 
                r.rankId as rank_name,
                u.name as unit_name, 
                at.name as appointment_type, 
                at.is_temporary,
                sa.position,
                sa.appointment_date, 
                sa.startDate,
                sa.endDate,
                sa.comment, 
                sa.status,
                sa.createdAt,
                sa.approval_date,
                u2.username as createdBy,
                u3.username as approved_by
            FROM 
                staff_appointment sa
            JOIN 
                staff s ON sa.svcNo = s.id
            JOIN 
                ranks r ON s.rankId = r.id
            JOIN 
                units u ON sa.unitId = u.id
            JOIN 
                appointment_types at ON sa.appointment_type_id = at.id
            JOIN 
                users u2 ON sa.createdBy = u2.id
            LEFT JOIN 
                users u3 ON sa.approved_by = u3.id
            WHERE 
                sa.id = ?
        ");
        $detailStmt->execute([$appointmentId]);
        $appointmentDetails = $detailStmt->fetch(PDO::FETCH_ASSOC);
        
        if ($appointmentDetails) {
            // Get appointment history
            $historyStmt = $pdo->prepare("
                SELECT 
                    ah.*, 
                    u.username as changed_by_username
                FROM 
                    appointment_history ah
                JOIN 
                    users u ON ah.changed_by = u.id
                WHERE 
                    ah.appointment_id = ?
                ORDER BY 
                    ah.changed_at DESC
            ");
            $historyStmt->execute([$appointmentId]);
            $appointmentHistory = $historyStmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Get approval records
            $approvalStmt = $pdo->prepare("
                SELECT 
                    aa.*, 
                    u.username as approver_username
                FROM 
                    appointment_approvals aa
                JOIN 
                    users u ON aa.approver_id = u.id
                WHERE 
                    aa.appointment_id = ?
                ORDER BY 
                    aa.createdAt DESC
            ");
            $approvalStmt->execute([$appointmentId]);
            $appointmentApprovals = $approvalStmt->fetchAll(PDO::FETCH_ASSOC);
        }
    } catch (Exception $e) {
        $errors[] = "Error fetching appointment details: " . $e->getMessage();
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
                            <i class="fas fa-history"></i> Appointment History
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

            <!-- Search and Filter Form -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="card shadow-sm">
                        <div class="card-header bg-primary text-white">
                            <h4 class="mb-0"><i class="fa fa-search"></i> Search Appointments</h4>
                        </div>
                        <div class="card-body">
                            <form method="get" action="" class="row g-3">
                                <div class="col-md-4">
                                    <label for="svcNo" class="form-label">Service Number</label>
                                    <input type="text" class="form-control" id="svcNo" name="svcNo" value="<?= htmlspecialchars($serviceNumber) ?>">
                                </div>
                                <div class="col-md-4">
                                    <label for="unitId" class="form-label">Unit</label>
                                    <select class="form-select" id="unitId" name="unitId">
                                        <option value="">All Units</option>
                                        <?php foreach ($units as $unit): ?>
                                            <option value="<?= $unit['id'] ?>" <?= ($unitId == $unit['id']) ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($unit['name']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label for="appointment_type_id" class="form-label">Appointment Type</label>
                                    <select class="form-select" id="appointment_type_id" name="appointment_type_id">
                                        <option value="">All Types</option>
                                        <?php foreach ($appointmentTypes as $type): ?>
                                            <option value="<?= $type['id'] ?>" <?= ($appointmentTypeId == $type['id']) ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($type['name']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label for="startDate" class="form-label">Start Date</label>
                                    <input type="date" class="form-control" id="startDate" name="startDate" value="<?= htmlspecialchars($startDate) ?>">
                                </div>
                                <div class="col-md-3">
                                    <label for="endDate" class="form-label">End Date</label>
                                    <input type="date" class="form-control" id="endDate" name="endDate" value="<?= htmlspecialchars($endDate) ?>">
                                </div>
                                <div class="col-md-3">
                                    <label for="status" class="form-label">Status</label>
                                    <select class="form-select" id="status" name="status">
                                        <option value="">All Statuses</option>
                                        <option value="pending" <?= ($status === 'pending') ? 'selected' : '' ?>>Pending</option>
                                        <option value="approved" <?= ($status === 'approved') ? 'selected' : '' ?>>Approved</option>
                                        <option value="rejected" <?= ($status === 'rejected') ? 'selected' : '' ?>>Rejected</option>
                                        <option value="completed" <?= ($status === 'completed') ? 'selected' : '' ?>>Completed</option>
                                        <option value="cancelled" <?= ($status === 'cancelled') ? 'selected' : '' ?>>Cancelled</option>
                                    </select>
                                </div>
                                <div class="col-md-3 d-flex align-items-end">
                                    <button type="submit" class="btn btn-primary me-2">
                                        <i class="fas fa-search"></i> Search
                                    </button>
                                    <a href="appointment_history.php" class="btn btn-secondary">
                                        <i class="fas fa-redo"></i> Reset
                                    </a>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            <?php if ($appointmentDetails): ?>
            <!-- Appointment Details -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="card shadow-sm">
                        <div class="card-header bg-info text-white">
                            <h4 class="mb-0"><i class="fa fa-info-circle"></i> Appointment Details</h4>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <h5>Staff Information</h5>
                                    <table class="table table-bordered">
                                        <tr>
                                            <th width="40%">Service Number</th>
                                            <td><?= htmlspecialchars($appointmentDetails['svcNo']) ?></td>
                                        </tr>
                                        <tr>
                                            <th>Name</th>
                                            <td><?= htmlspecialchars($appointmentDetails['lName'] . ', ' . $appointmentDetails['fName']) ?></td>
                                        </tr>
                                        <tr>
                                            <th>Rank</th>
                                            <td><?= htmlspecialchars($appointmentDetails['rank_name']) ?></td>
                                        </tr>
                                        <tr>
                                            <th>Unit</th>
                                            <td><?= htmlspecialchars($appointmentDetails['unit_name']) ?></td>
                                        </tr>
                                        <tr>
                                            <th>Position</th>
                                            <td><?= htmlspecialchars($appointmentDetails['position'] ?? 'N/A') ?></td>
                                        </tr>
                                    </table>
                                </div>
                                <div class="col-md-6">
                                    <h5>Appointment Information</h5>
                                    <table class="table table-bordered">
                                        <tr>
                                            <th width="40%">Type</th>
                                            <td>
                                                <?= htmlspecialchars($appointmentDetails['appointment_type']) ?>
                                                <?php if ($appointmentDetails['is_temporary']): ?>
                                                    <span class="badge bg-warning">Temporary</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th>Status</th>
                                            <td>
                                                <?php 
                                                $statusBadge = '';
                                                switch($appointmentDetails['status']) {
                                                    case 'pending': $statusBadge = 'bg-warning text-dark'; break;
                                                    case 'approved': $statusBadge = 'bg-success'; break;
                                                    case 'rejected': $statusBadge = 'bg-danger'; break;
                                                    case 'completed': $statusBadge = 'bg-info'; break;
                                                    case 'cancelled': $statusBadge = 'bg-secondary'; break;
                                                    default: $statusBadge = 'bg-secondary';
                                                }
                                                ?>
                                                <span class="badge <?= $statusBadge ?>"><?= ucfirst(htmlspecialchars($appointmentDetails['status'])) ?></span>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th>Start Date</th>
                                            <td><?= date('d M Y', strtotime($appointmentDetails['startDate'])) ?></td>
                                        </tr>
                                        <tr>
                                            <th>End Date</th>
                                            <td>
                                                <?= $appointmentDetails['endDate'] ? date('d M Y', strtotime($appointmentDetails['endDate'])) : 'N/A' ?>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th>Comment</th>
                                            <td><?= nl2br(htmlspecialchars($appointmentDetails['comment'] ?? 'N/A')) ?></td>
                                        </tr>
                                    </table>
                                </div>
                            </div>
                            
                            <div class="row mt-4">
                                <div class="col-md-6">
                                    <h5>Administrative Information</h5>
                                    <table class="table table-bordered">
                                        <tr>
                                            <th width="40%">Created By</th>
                                            <td><?= htmlspecialchars($appointmentDetails['createdBy']) ?></td>
                                        </tr>
                                        <tr>
                                            <th>Created Date</th>
                                            <td><?= date('d M Y H:i', strtotime($appointmentDetails['createdAt'])) ?></td>
                                        </tr>
                                        <tr>
                                            <th>Approved By</th>
                                            <td><?= $appointmentDetails['approved_by'] ? htmlspecialchars($appointmentDetails['approved_by']) : 'N/A' ?></td>
                                        </tr>
                                        <tr>
                                            <th>Approval Date</th>
                                            <td><?= $appointmentDetails['approval_date'] ? date('d M Y H:i', strtotime($appointmentDetails['approval_date'])) : 'N/A' ?></td>
                                        </tr>
                                    </table>
                                </div>
                                <div class="col-md-6">
                                    <h5>Approval Information</h5>
                                    <?php if (empty($appointmentApprovals)): ?>
                                        <div class="alert alert-info">No approval records found.</div>
                                    <?php else: ?>
                                        <table class="table table-bordered">
                                            <thead>
                                                <tr>
                                                    <th>Approver</th>
                                                    <th>Status</th>
                                                    <th>Date</th>
                                                    <th>Comments</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($appointmentApprovals as $approval): ?>
                                                    <tr>
                                                        <td><?= htmlspecialchars($approval['approver_username']) ?></td>
                                                        <td>
                                                            <?php 
                                                            $approvalStatusBadge = '';
                                                            switch($approval['status']) {
                                                                case 'pending': $approvalStatusBadge = 'bg-warning text-dark'; break;
                                                                case 'approved': $approvalStatusBadge = 'bg-success'; break;
                                                                case 'rejected': $approvalStatusBadge = 'bg-danger'; break;
                                                                default: $approvalStatusBadge = 'bg-secondary';
                                                            }
                                                            ?>
                                                            <span class="badge <?= $approvalStatusBadge ?>"><?= ucfirst(htmlspecialchars($approval['status'])) ?></span>
                                                        </td>
                                                        <td><?= $approval['approval_date'] ? date('d M Y', strtotime($approval['approval_date'])) : 'N/A' ?></td>
                                                        <td><?= nl2br(htmlspecialchars($approval['comments'] ?? 'No comments')) ?></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <div class="row mt-4">
                                <div class="col-12">
                                    <h5>Appointment History</h5>
                                    <?php if (empty($appointmentHistory)): ?>
                                        <div class="alert alert-info">No history records found.</div>
                                    <?php else: ?>
                                        <table class="table table-striped">
                                            <thead>
                                                <tr>
                                                    <th>Date</th>
                                                    <th>User</th>
                                                    <th>Field</th>
                                                    <th>Old Value</th>
                                                    <th>New Value</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($appointmentHistory as $history): ?>
                                                    <tr>
                                                        <td><?= date('d M Y H:i', strtotime($history['changed_at'])) ?></td>
                                                        <td><?= htmlspecialchars($history['changed_by_username']) ?></td>
                                                        <td><?= htmlspecialchars($history['field_changed']) ?></td>
                                                        <td><?= htmlspecialchars($history['old_value'] ?? 'N/A') ?></td>
                                                        <td><?= htmlspecialchars($history['new_value']) ?></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <div class="text-center mt-4">
                                <a href="appointment_history.php" class="btn btn-primary">
                                    <i class="fas fa-arrow-left"></i> Back to Appointments List
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php else: ?>
            <!-- Appointments List -->
            <div class="row">
                <div class="col-12">
                    <div class="card shadow-sm">
                        <div class="card-header bg-success text-white">
                            <h4 class="mb-0"><i class="fa fa-list"></i> Appointments List</h4>
                        </div>
                        <div class="card-body">
                            <?php if (empty($appointments)): ?>
                                <div class="alert alert-info">
                                    No appointments found matching your criteria.
                                </div>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-striped table-hover">
                                        <thead>
                                            <tr>
                                                <th>Service Number</th>
                                                <th>Name</th>
                                                <th>Rank</th>
                                                <th>Appointment Type</th>
                                                <th>Unit</th>
                                                <th>Date</th>
                                                <th>Status</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($appointments as $appointment): ?>
                                                <tr>
                                                    <td><?= htmlspecialchars($appointment['svcNo']) ?></td>
                                                    <td><?= htmlspecialchars($appointment['lName'] . ', ' . $appointment['fName']) ?></td>
                                                    <td><?= htmlspecialchars($appointment['rank_name']) ?></td>
                                                    <td>
                                                        <?= htmlspecialchars($appointment['appointment_type']) ?>
                                                        <?php if ($appointment['is_temporary']): ?>
                                                            <span class="badge bg-warning">Temporary</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td><?= htmlspecialchars($appointment['unit_name']) ?></td>
                                                    <td>
                                                        <small>
                                                            <strong>Start:</strong> <?= date('d M Y', strtotime($appointment['startDate'])) ?>
                                                            <?php if ($appointment['endDate']): ?>
                                                                <br>
                                                                <strong>End:</strong> <?= date('d M Y', strtotime($appointment['endDate'])) ?>
                                                            <?php endif; ?>
                                                        </small>
                                                    </td>
                                                    <td>
                                                        <?php 
                                                        $statusBadge = '';
                                                        switch($appointment['status']) {
                                                            case 'pending': $statusBadge = 'bg-warning text-dark'; break;
                                                            case 'approved': $statusBadge = 'bg-success'; break;
                                                            case 'rejected': $statusBadge = 'bg-danger'; break;
                                                            case 'completed': $statusBadge = 'bg-info'; break;
                                                            case 'cancelled': $statusBadge = 'bg-secondary'; break;
                                                            default: $statusBadge = 'bg-secondary';
                                                        }
                                                        ?>
                                                        <span class="badge <?= $statusBadge ?>"><?= ucfirst(htmlspecialchars($appointment['status'])) ?></span>
                                                    </td>
                                                    <td>
                                                        <a href="?appointment_id=<?= $appointment['id'] ?>" class="btn btn-sm btn-primary">
                                                            <i class="fas fa-info-circle"></i> Details
                                                        </a>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Core JS (jQuery/Bootstrap) are loaded centrally in shared/footer.php. -->
<?php include dirname(__DIR__) . '/shared/footer.php'; ?>
