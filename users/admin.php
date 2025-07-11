<?php
require_once '../staff_auth_standalone.php';
requireAdmin();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ARMIS - Admin Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link rel="stylesheet" href="css/armis_custom.css">
</head>
<body>
    <?php include 'includes/sidebar.php'; ?>
    
    <div class="main-content">
        <div class="dashboard-header">
            <div class="container-fluid">
                <h1><i class="fas fa-tachometer-alt"></i> Admin Dashboard</h1>
                <p class="mb-0">Welcome back, <?php echo htmlspecialchars(getCurrentUser()['username']); ?>! Here's your system overview.</p>
            </div>
        </div>

        <div class="container-fluid">
            <?php displayMessages(); ?>
            
            <!-- Dashboard Statistics -->
            <div class="dashboard-stats">
                <div class="stat-card">
                    <div class="stat-number"><?php echo getTotalEmployees(); ?></div>
                    <div class="stat-label">Total Employees</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo getActiveStaff(); ?></div>
                    <div class="stat-label">Active Staff</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo getPendingReports(); ?></div>
                    <div class="stat-label">Pending Reports</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo getCompletedTrainings(); ?></div>
                    <div class="stat-label">Completed Trainings</div>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="dashboard-card">
                <h3><i class="fas fa-bolt"></i> Quick Actions</h3>
                <div class="quick-actions">
                    <a href="employees.php" class="action-btn">
                        <i class="fas fa-users"></i>
                        <span>Manage Employees</span>
                    </a>
                    <a href="admin_branch.php" class="action-btn">
                        <i class="fas fa-cogs"></i>
                        <span>Admin Branch</span>
                    </a>
                    <a href="command_reports.php" class="action-btn">
                        <i class="fas fa-chart-bar"></i>
                        <span>Command Reports</span>
                    </a>
                    <a href="training/courses.php" class="action-btn">
                        <i class="fas fa-graduation-cap"></i>
                        <span>Training Courses</span>
                    </a>
                    <a href="admin_branch/create_staff.php" class="action-btn">
                        <i class="fas fa-user-plus"></i>
                        <span>Create Staff</span>
                    </a>
                    <a href="admin_branch/medals.php" class="action-btn">
                        <i class="fas fa-medal"></i>
                        <span>Manage Medals</span>
                    </a>
                </div>
            </div>

            <!-- Recent Activity -->
            <div class="dashboard-card">
                <h3><i class="fas fa-clock"></i> Recent Activity</h3>
                <div class="activity-list">
                    <div class="activity-item">
                        <div class="activity-icon"><i class="fas fa-user-plus"></i></div>
                        <div class="activity-content">
                            <div class="activity-title">New staff member created</div>
                            <div class="activity-time">2 hours ago</div>
                        </div>
                    </div>
                    <div class="activity-item">
                        <div class="activity-icon"><i class="fas fa-medal"></i></div>
                        <div class="activity-content">
                            <div class="activity-title">Medal awarded to staff member</div>
                            <div class="activity-time">4 hours ago</div>
                        </div>
                    </div>
                    <div class="activity-item">
                        <div class="activity-icon"><i class="fas fa-file-alt"></i></div>
                        <div class="activity-content">
                            <div class="activity-title">New report generated</div>
                            <div class="activity-time">1 day ago</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- System Status -->
            <div class="dashboard-card">
                <h3><i class="fas fa-server"></i> System Status</h3>
                <div class="row">
                    <div class="col-md-6">
                        <div class="status-item">
                            <span class="status-label">Database Connection</span>
                            <span class="status-value status-online">Online</span>
                        </div>
                        <div class="status-item">
                            <span class="status-label">Last Backup</span>
                            <span class="status-value">Today, 2:00 AM</span>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="status-item">
                            <span class="status-label">System Version</span>
                            <span class="status-value">ARMIS v2.0</span>
                        </div>
                        <div class="status-item">
                            <span class="status-label">Active Users</span>
                            <span class="status-value"><?php echo getTotalUsers(); ?></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

<?php
// Dashboard helper functions
function getTotalEmployees() {
    // In a real application, this would query the database
    return 156; // Sample data
}

function getActiveStaff() {
    // In a real application, this would query the database
    return 143; // Sample data
}

function getPendingReports() {
    // In a real application, this would query the database
    return 8; // Sample data
}

function getCompletedTrainings() {
    // In a real application, this would query the database
    return 89; // Sample data
}
?>

