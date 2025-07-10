<?php
require_once '../auth.php';
requireAdmin();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ARMIS - Admin Branch Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link rel="stylesheet" href="css/armis_custom.css">
</head>
<body>
    <?php include 'includes/sidebar.php'; ?>
    
    <div class="main-content">
        <div class="dashboard-header">
            <div class="container-fluid">
                <h1><i class="fas fa-cogs"></i> Admin Branch Dashboard</h1>
                <p class="mb-0">Comprehensive staff management and reporting system</p>
            </div>
        </div>

        <div class="container-fluid">
            <?php displayMessages(); ?>
            
            <!-- Staff Management Section -->
            <div class="dashboard-card">
                <h3><i class="fas fa-users-cog"></i> Staff Management</h3>
                <div class="row">
                    <div class="col-md-3 col-sm-6 mb-3">
                        <a href="admin_branch/create_staff.php" class="btn btn-success w-100 py-3">
                            <i class="fas fa-user-plus"></i><br>Create Staff Member
                        </a>
                    </div>
                    <div class="col-md-3 col-sm-6 mb-3">
                        <a href="admin_branch/edit_staff.php" class="btn btn-primary w-100 py-3">
                            <i class="fas fa-edit"></i><br>Edit Staff Details
                        </a>
                    </div>
                    <div class="col-md-3 col-sm-6 mb-3">
                        <a href="admin_branch/delete_staff.php" class="btn btn-danger w-100 py-3">
                            <i class="fas fa-trash"></i><br>Delete Staff Member
                        </a>
                    </div>
                    <div class="col-md-3 col-sm-6 mb-3">
                        <a href="admin_branch/promote_staff.php" class="btn btn-warning w-100 py-3">
                            <i class="fas fa-arrow-up"></i><br>Promote Staff
                        </a>
                    </div>
                </div>
            </div>

            <!-- Awards and Recognition -->
            <div class="dashboard-card">
                <h3><i class="fas fa-medal"></i> Awards & Recognition</h3>
                <div class="row">
                    <div class="col-md-4 col-sm-6 mb-3">
                        <a href="admin_branch/medals.php" class="btn btn-outline-info w-100 py-3">
                            <i class="fas fa-medal"></i><br>Create Medals
                        </a>
                    </div>
                    <div class="col-md-4 col-sm-6 mb-3">
                        <a href="admin_branch/assign_medal.php" class="btn btn-outline-success w-100 py-3">
                            <i class="fas fa-award"></i><br>Assign Medals
                        </a>
                    </div>
                    <div class="col-md-4 col-sm-6 mb-3">
                        <a href="admin_branch/appointments.php" class="btn btn-outline-dark w-100 py-3">
                            <i class="fas fa-briefcase"></i><br>Appointments
                        </a>
                    </div>
                </div>
            </div>

            <!-- Reports and Analytics -->
            <div class="dashboard-card">
                <h3><i class="fas fa-chart-bar"></i> Reports & Analytics</h3>
                <div class="row mb-3">
                    <div class="col-12">
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-search"></i></span>
                            <input type="text" id="reportSearch" class="form-control" placeholder="Search reports...">
                        </div>
                    </div>
                </div>
                <div id="reportsGrid" class="row">
                    <div class="col-md-2 col-sm-4 mb-3">
                        <a href="admin_branch/reports_seniority.php" class="btn btn-light w-100 py-3 report-btn">
                            <i class="fas fa-list-ol"></i><br><small>Seniority Roll</small>
                        </a>
                    </div>
                    <div class="col-md-2 col-sm-4 mb-3">
                        <a href="admin_branch/reports_units.php" class="btn btn-light w-100 py-3 report-btn">
                            <i class="fas fa-building"></i><br><small>Unit List</small>
                        </a>
                    </div>
                    <div class="col-md-2 col-sm-4 mb-3">
                        <a href="admin_branch/reports_rank.php" class="btn btn-light w-100 py-3 report-btn">
                            <i class="fas fa-medal"></i><br><small>By Ranks</small>
                        </a>
                    </div>
                    <div class="col-md-2 col-sm-4 mb-3">
                        <a href="admin_branch/reports_corps.php" class="btn btn-light w-100 py-3 report-btn">
                            <i class="fas fa-shield-alt"></i><br><small>By Corps</small>
                        </a>
                    </div>
                    <div class="col-md-2 col-sm-4 mb-3">
                        <a href="admin_branch/reports_gender.php" class="btn btn-light w-100 py-3 report-btn">
                            <i class="fas fa-venus-mars"></i><br><small>By Gender</small>
                        </a>
                    </div>
                    <div class="col-md-2 col-sm-4 mb-3">
                        <a href="admin_branch/reports_appointment.php" class="btn btn-light w-100 py-3 report-btn">
                            <i class="fas fa-briefcase"></i><br><small>By Appointment</small>
                        </a>
                    </div>
                    <div class="col-md-2 col-sm-4 mb-3">
                        <a href="admin_branch/reports_courses.php" class="btn btn-light w-100 py-3 report-btn">
                            <i class="fas fa-graduation-cap"></i><br><small>Courses Done</small>
                        </a>
                    </div>
                    <div class="col-md-2 col-sm-4 mb-3">
                        <a href="admin_branch/reports_retired.php" class="btn btn-light w-100 py-3 report-btn">
                            <i class="fas fa-user-times"></i><br><small>Retired Staff</small>
                        </a>
                    </div>
                    <div class="col-md-2 col-sm-4 mb-3">
                        <a href="admin_branch/reports_contract.php" class="btn btn-light w-100 py-3 report-btn">
                            <i class="fas fa-file-contract"></i><br><small>Contract Staff</small>
                        </a>
                    </div>
                    <div class="col-md-2 col-sm-4 mb-3">
                        <a href="admin_branch/reports_deceased.php" class="btn btn-light w-100 py-3 report-btn">
                            <i class="fas fa-user-slash"></i><br><small>Deceased Staff</small>
                        </a>
                    </div>
                    <div class="col-md-2 col-sm-4 mb-3">
                        <a href="admin_branch/reports_marital.php" class="btn btn-light w-100 py-3 report-btn">
                            <i class="fas fa-ring"></i><br><small>Marital Status</small>
                        </a>
                    </div>
                    <div class="col-md-2 col-sm-4 mb-3">
                        <a href="admin_branch/reports_trade.php" class="btn btn-light w-100 py-3 report-btn">
                            <i class="fas fa-tools"></i><br><small>By Trade</small>
                        </a>
                    </div>
                </div>
            </div>

            <!-- Quick Statistics -->
            <div class="dashboard-card">
                <h3><i class="fas fa-chart-pie"></i> Quick Statistics</h3>
                <div class="row">
                    <div class="col-md-3 col-sm-6">
                        <div class="stat-card">
                            <div class="stat-number"><?php echo getActiveStaff(); ?></div>
                            <div class="stat-label">Active Staff</div>
                        </div>
                    </div>
                    <div class="col-md-3 col-sm-6">
                        <div class="stat-card">
                            <div class="stat-number"><?php echo getPendingPromotions(); ?></div>
                            <div class="stat-label">Pending Promotions</div>
                        </div>
                    </div>
                    <div class="col-md-3 col-sm-6">
                        <div class="stat-card">
                            <div class="stat-number"><?php echo getRecentMedals(); ?></div>
                            <div class="stat-label">Recent Medals</div>
                        </div>
                    </div>
                    <div class="col-md-3 col-sm-6">
                        <div class="stat-card">
                            <div class="stat-number"><?php echo getNewAppointments(); ?></div>
                            <div class="stat-label">New Appointments</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Help and Information -->
            <div class="dashboard-card">
                <h3><i class="fas fa-info-circle"></i> Information</h3>
                <div class="alert alert-info">
                    <strong>Admin Branch Management:</strong> Use the tools above to manage all aspects of staff administration, including creation, editing, promotion, postings, medals, and comprehensive reporting. The search functionality helps you quickly find specific reports.
                </div>
            </div>
        </div>
    </div>

    <script>
        // Report search functionality
        document.getElementById('reportSearch').addEventListener('keyup', function() {
            const query = this.value.toLowerCase();
            const reportButtons = document.querySelectorAll('.report-btn');
            
            reportButtons.forEach(function(btn) {
                const text = btn.textContent.toLowerCase();
                const parent = btn.closest('.col-md-2, .col-sm-4');
                
                if (text.includes(query)) {
                    parent.style.display = '';
                } else {
                    parent.style.display = 'none';
                }
            });
        });
    </script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

<?php
// Helper functions for dashboard statistics
function getPendingPromotions() {
    return 5; // Sample data
}

function getRecentMedals() {
    return 12; // Sample data
}

function getNewAppointments() {
    return 3; // Sample data
}
?>