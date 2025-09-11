<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include RBAC system
require_once dirname(__DIR__) . '/shared/rbac.php';
require_once 'operations_manager.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ' . dirname($_SERVER['PHP_SELF']) . '/../login.php');
    exit();
}

// Check if user has access to operations module
requireModuleAccess('operations');

// Check if mission ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: missions.php');
    exit();
}

$missionId = (int)$_GET['id'];

// Log access
logAccess('operations', 'mission_details_view', true);

$pageTitle = "Operations | Mission Details";
$moduleName = "Operations";
$moduleIcon = "shield-alt";
$currentPage = "missions";

$sidebarLinks = [
    ['title' => 'Dashboard', 'url' => '/Armis2/operations/index.php', 'icon' => 'tachometer-alt', 'page' => 'dashboard'],
    ['title' => 'Mission Planning', 'url' => '/Armis2/operations/missions.php', 'icon' => 'map-marked-alt', 'page' => 'missions'],
    ['title' => 'Deployments', 'url' => '/Armis2/operations/deployments.php', 'icon' => 'plane', 'page' => 'deployments'],
    ['title' => 'Resource Allocation', 'url' => '/Armis2/operations/resources.php', 'icon' => 'boxes', 'page' => 'resources'],
    ['title' => 'Status Reports', 'url' => '/Armis2/operations/reports.php', 'icon' => 'clipboard-list', 'page' => 'reports'],
    ['title' => 'Field Operations', 'url' => '/Armis2/operations/field.php', 'icon' => 'crosshairs', 'page' => 'field'],
    ['title' => 'Setup Database', 'url' => '/Armis2/operations/setup_database.php', 'icon' => 'database', 'page' => 'setup']
];

// Initialize operations manager
try {
    $operationsManager = new OperationsManager($_SESSION['user_id']);
    
    // Get mission details
    $mission = $operationsManager->getMissionDetails($missionId);
    
    if (!$mission) {
        throw new Exception("Mission not found");
    }
    
    // Get mission resources
    $resources = $operationsManager->getMissionResources($missionId);
    
    // Get mission personnel
    $personnel = $operationsManager->getMissionPersonnel($missionId);
    
} catch (Exception $e) {
    $error = $e->getMessage();
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
                            <i class="fas fa-map-marked-alt"></i> Mission Details
                        </h1>
                        <div class="btn-group">
                            <a href="missions.php" class="btn btn-secondary">
                                <i class="fas fa-arrow-left"></i> Back to Missions
                            </a>
                            <a href="mission_edit.php?id=<?php echo $missionId; ?>" class="btn btn-primary">
                                <i class="fas fa-edit"></i> Edit Mission
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            
            <?php if (isset($error)): ?>
                <div class="alert alert-danger">
                    <?php echo $error; ?>
                </div>
            <?php elseif (isset($mission)): ?>
            
            <?php if (isset($_GET['success'])): ?>
                <div class="alert alert-success">Mission created successfully!</div>
            <?php endif; ?>
            
            <?php if (isset($_GET['updated'])): ?>
                <div class="alert alert-success">Mission updated successfully!</div>
            <?php endif; ?>
            
            <!-- Mission Overview -->
            <div class="card shadow mb-4">
                <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                    <h6 class="m-0 font-weight-bold text-primary">Mission Overview</h6>
                    <?php 
                        $statusBadge = 'secondary';
                        if ($mission['status'] === 'active') $statusBadge = 'success';
                        if ($mission['status'] === 'planned') $statusBadge = 'info';
                        if ($mission['status'] === 'completed') $statusBadge = 'primary';
                        if ($mission['status'] === 'cancelled') $statusBadge = 'danger';
                        
                        $priorityBadge = 'secondary';
                        if ($mission['priority'] === 'low') $priorityBadge = 'info';
                        if ($mission['priority'] === 'medium') $priorityBadge = 'primary';
                        if ($mission['priority'] === 'high') $priorityBadge = 'warning';
                        if ($mission['priority'] === 'critical') $priorityBadge = 'danger';
                    ?>
                    <div>
                        <span class="badge badge-<?php echo $statusBadge; ?>"><?php echo ucfirst($mission['status']); ?></span>
                        <span class="badge badge-<?php echo $priorityBadge; ?>"><?php echo ucfirst($mission['priority']); ?> Priority</span>
                    </div>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <h3><?php echo htmlspecialchars($mission['mission_name']); ?></h3>
                            <p class="text-muted"><?php echo htmlspecialchars($mission['mission_code']); ?></p>
                            
                            <h5 class="mt-4">Description</h5>
                            <p><?php echo nl2br(htmlspecialchars($mission['description'] ?? 'No description provided.')); ?></p>
                            
                            <h5 class="mt-4">Timeline</h5>
                            <div class="row">
                                <div class="col-md-6">
                                    <p><strong>Start Date:</strong> <?php echo date('M d, Y', strtotime($mission['start_date'])); ?></p>
                                </div>
                                <div class="col-md-6">
                                    <p><strong>End Date:</strong> <?php echo date('M d, Y', strtotime($mission['end_date'])); ?></p>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="card bg-light mb-4">
                                <div class="card-body">
                                    <h5>Location Details</h5>
                                    <p><strong>Location:</strong> <?php echo htmlspecialchars($mission['location_name'] ?? 'N/A'); ?></p>
                                    <p><strong>Country:</strong> <?php echo htmlspecialchars($mission['country'] ?? 'N/A'); ?></p>
                                    <p><strong>Coordinates:</strong> <?php echo htmlspecialchars($mission['coordinates'] ?? 'N/A'); ?></p>
                                </div>
                            </div>
                            
                            <div class="card bg-light">
                                <div class="card-body">
                                    <h5>Mission Details</h5>
                                    <p><strong>Created By:</strong> <?php echo htmlspecialchars($mission['created_by_name'] ?? 'Unknown'); ?></p>
                                    <p><strong>Created:</strong> <?php echo date('M d, Y H:i', strtotime($mission['created_at'])); ?></p>
                                    <p><strong>Last Updated:</strong> <?php echo date('M d, Y H:i', strtotime($mission['updated_at'])); ?></p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="row">
                <!-- Mission Resources -->
                <div class="col-lg-6">
                    <div class="card shadow mb-4">
                        <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                            <h6 class="m-0 font-weight-bold text-primary">Mission Resources</h6>
                            <a href="resource_assign.php?mission_id=<?php echo $missionId; ?>" class="btn btn-sm btn-primary">
                                <i class="fas fa-plus"></i> Assign Resources
                            </a>
                        </div>
                        <div class="card-body">
                            <?php if (empty($resources)): ?>
                                <div class="text-center text-muted py-4">
                                    <p>No resources assigned to this mission</p>
                                    <a href="resource_assign.php?mission_id=<?php echo $missionId; ?>" class="btn btn-primary">Assign Resources</a>
                                </div>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-bordered table-sm">
                                        <thead>
                                            <tr>
                                                <th>Resource</th>
                                                <th>Type</th>
                                                <th>Quantity</th>
                                                <th>Status</th>
                                                <th>Assigned By</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($resources as $resource): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($resource['resource_name'] ?? 'N/A'); ?></td>
                                                <td><?php echo htmlspecialchars($resource['resource_type_name'] ?? 'N/A'); ?></td>
                                                <td><?php echo $resource['quantity']; ?></td>
                                                <td><?php echo ucfirst($resource['status']); ?></td>
                                                <td><?php echo htmlspecialchars($resource['assigned_by_name'] ?? 'N/A'); ?></td>
                                                <td>
                                                    <div class="btn-group btn-group-sm">
                                                        <a href="resource_details.php?id=<?php echo $resource['resource_id']; ?>" class="btn btn-info">
                                                            <i class="fas fa-eye"></i>
                                                        </a>
                                                    </div>
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
                
                <!-- Mission Personnel -->
                <div class="col-lg-6">
                    <div class="card shadow mb-4">
                        <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                            <h6 class="m-0 font-weight-bold text-primary">Mission Personnel</h6>
                            <a href="personnel_assign.php?mission_id=<?php echo $missionId; ?>" class="btn btn-sm btn-primary">
                                <i class="fas fa-plus"></i> Assign Personnel
                            </a>
                        </div>
                        <div class="card-body">
                            <?php if (empty($personnel)): ?>
                                <div class="text-center text-muted py-4">
                                    <p>No personnel assigned to this mission</p>
                                    <a href="personnel_assign.php?mission_id=<?php echo $missionId; ?>" class="btn btn-primary">Assign Personnel</a>
                                </div>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-bordered table-sm">
                                        <thead>
                                            <tr>
                                                <th>Name</th>
                                                <th>Rank</th>
                                                <th>Service Number</th>
                                                <th>Role</th>
                                                <th>Status</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($personnel as $person): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($person['first_name'] . ' ' . $person['last_name']); ?></td>
                                                <td><?php echo htmlspecialchars($person['rank'] ?? 'N/A'); ?></td>
                                                <td><?php echo htmlspecialchars($person['service_number'] ?? 'N/A'); ?></td>
                                                <td><?php echo htmlspecialchars($person['role_name'] ?? 'N/A'); ?></td>
                                                <td><?php echo ucfirst($person['status']); ?></td>
                                                <td>
                                                    <div class="btn-group btn-group-sm">
                                                        <a href="../admin_branch/view_staff.php?id=<?php echo $person['id']; ?>" class="btn btn-info">
                                                            <i class="fas fa-eye"></i>
                                                        </a>
                                                    </div>
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

<?php include dirname(__DIR__) . '/shared/footer.php'; ?>
