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
logAccess('operations', 'mission_edit', true);

$pageTitle = "Operations | Edit Mission";
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
    
    // Get locations for dropdown
    $locations = $operationsManager->getLocations();
    
    // Handle form submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_mission') {
        // Validate and sanitize input
        $missionData = [
            'mission_name' => htmlspecialchars($_POST['mission_name']),
            'mission_code' => htmlspecialchars($_POST['mission_code']),
            'description' => htmlspecialchars($_POST['description']),
            'status' => htmlspecialchars($_POST['status']),
            'priority' => htmlspecialchars($_POST['priority']),
            'location_id' => (int)$_POST['location_id'],
            'startDate' => htmlspecialchars($_POST['startDate']),
            'endDate' => htmlspecialchars($_POST['endDate'])
        ];
        
        $result = $operationsManager->updateMission($missionId, $missionData);
        
        if ($result['success']) {
            $successMessage = $result['message'];
            // Redirect to prevent form resubmission
            header('Location: mission_details.php?id=' . $missionId . '&updated=1');
            exit;
        } else {
            $errorMessage = $result['message'];
        }
    }
    
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
                            <i class="fas fa-edit"></i> Edit Mission
                        </h1>
                        <div class="btn-group">
                            <a href="mission_details.php?id=<?php echo $missionId; ?>" class="btn btn-secondary">
                                <i class="fas fa-arrow-left"></i> Back to Details
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
            
            <?php if (isset($errorMessage)): ?>
                <div class="alert alert-danger"><?php echo $errorMessage; ?></div>
            <?php endif; ?>
            
            <?php if (isset($successMessage)): ?>
                <div class="alert alert-success"><?php echo $successMessage; ?></div>
            <?php endif; ?>
            
            <!-- Edit Mission Form -->
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Edit Mission: <?php echo htmlspecialchars($mission['mission_name']); ?></h6>
                </div>
                <div class="card-body">
                    <form method="POST" action="">
                        <input type="hidden" name="action" value="update_mission">
                        <input type="hidden" name="mission_id" value="<?php echo $missionId; ?>">
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="mission_name">Mission Name</label>
                                    <input type="text" class="form-control" id="mission_name" name="mission_name" value="<?php echo htmlspecialchars($mission['mission_name']); ?>" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="mission_code">Mission Code</label>
                                    <input type="text" class="form-control" id="mission_code" name="mission_code" value="<?php echo htmlspecialchars($mission['mission_code']); ?>" required>
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="description">Description</label>
                            <textarea class="form-control" id="description" name="description" rows="3"><?php echo htmlspecialchars($mission['description'] ?? ''); ?></textarea>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="status">Status</label>
                                    <select class="form-control" id="status" name="status" required>
                                        <option value="planned" <?php echo ($mission['status'] == 'planned') ? 'selected' : ''; ?>>Planned</option>
                                        <option value="active" <?php echo ($mission['status'] == 'active') ? 'selected' : ''; ?>>Active</option>
                                        <option value="completed" <?php echo ($mission['status'] == 'completed') ? 'selected' : ''; ?>>Completed</option>
                                        <option value="cancelled" <?php echo ($mission['status'] == 'cancelled') ? 'selected' : ''; ?>>Cancelled</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="priority">Priority</label>
                                    <select class="form-control" id="priority" name="priority" required>
                                        <option value="low" <?php echo ($mission['priority'] == 'low') ? 'selected' : ''; ?>>Low</option>
                                        <option value="medium" <?php echo ($mission['priority'] == 'medium') ? 'selected' : ''; ?>>Medium</option>
                                        <option value="high" <?php echo ($mission['priority'] == 'high') ? 'selected' : ''; ?>>High</option>
                                        <option value="critical" <?php echo ($mission['priority'] == 'critical') ? 'selected' : ''; ?>>Critical</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="location_id">Location</label>
                                    <select class="form-control" id="location_id" name="location_id" required>
                                        <option value="">Select Location</option>
                                        <?php foreach ($locations as $location): ?>
                                            <option value="<?php echo $location['location_id']; ?>" <?php echo ($mission['location_id'] == $location['location_id']) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($location['location_name'] . ' (' . $location['country'] . ')'); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="startDate">Start Date</label>
                                    <input type="date" class="form-control" id="startDate" name="startDate" value="<?php echo $mission['startDate']; ?>" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="endDate">End Date</label>
                                    <input type="date" class="form-control" id="endDate" name="endDate" value="<?php echo $mission['endDate']; ?>" required>
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-group text-right">
                            <a href="mission_details.php?id=<?php echo $missionId; ?>" class="btn btn-secondary">Cancel</a>
                            <button type="submit" class="btn btn-primary">Update Mission</button>
                        </div>
                    </form>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include dirname(__DIR__) . '/shared/footer.php'; ?>
