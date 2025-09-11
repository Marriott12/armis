<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include configuration and database
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/shared/database_connection.php';

// Include RBAC system
require_once dirname(__DIR__) . '/shared/rbac.php';

// Initialize global database connection
try {
    $pdo = getDbConnection();
} catch (Exception $e) {
    error_log("Database connection failed in admin/reports.php: " . $e->getMessage());
    die("Database connection failed. Please check your configuration.");
}

$pageTitle = "System Reports";
$moduleName = "System Reports";
$moduleIcon = "chart-bar";
$currentPage = "reports";

$sidebarLinks = [
    ['title' => 'Dashboard', 'url' => '/Armis2/admin/index.php', 'icon' => 'tachometer-alt', 'page' => 'dashboard'],
    ['title' => 'User Management', 'url' => '/Armis2/admin/users.php', 'icon' => 'users', 'page' => 'users'],
    ['title' => 'System Settings', 'url' => '/Armis2/admin/settings.php', 'icon' => 'cogs', 'page' => 'settings'],
    ['title' => 'Database Management', 'url' => '/Armis2/admin/database.php', 'icon' => 'database', 'page' => 'database'],
    ['title' => 'Security Center', 'url' => '/Armis2/admin/security.php', 'icon' => 'shield-alt', 'page' => 'security'],
    ['title' => 'System Reports', 'url' => '/Armis2/admin/reports.php', 'icon' => 'chart-bar', 'page' => 'reports']
];

// Check if user is logged in and has admin privileges
if (!isset($_SESSION['user_id'])) {
    header('Location: ' . dirname($_SERVER['PHP_SELF']) . '/../login.php');
    exit();
}

// Check if user has access to admin module
requireModuleAccess('admin');

// Log access
logAccess('admin', 'reports_view', true);

// Sample report data (would come from actual queries in production)
$systemStats = [
    'total_users' => 247,
    'active_sessions' => 47,
    'total_staff' => 1823,
    'pending_approvals' => 12
];

$monthlyStats = [
    ['month' => 'Jan', 'users' => 45, 'logins' => 1250, 'errors' => 15],
    ['month' => 'Feb', 'users' => 52, 'logins' => 1380, 'errors' => 12],
    ['month' => 'Mar', 'users' => 48, 'logins' => 1420, 'errors' => 8],
    ['month' => 'Apr', 'users' => 61, 'logins' => 1550, 'errors' => 10],
    ['month' => 'May', 'users' => 58, 'logins' => 1480, 'errors' => 6],
    ['month' => 'Jun', 'users' => 67, 'logins' => 1650, 'errors' => 9]
];

$recentReports = [
    ['name' => 'User Activity Report', 'generated' => '2024-01-15 09:30:00', 'type' => 'Security', 'size' => '2.3 MB'],
    ['name' => 'System Performance Report', 'generated' => '2024-01-14 15:45:00', 'type' => 'Performance', 'size' => '1.8 MB'],
    ['name' => 'Database Backup Report', 'generated' => '2024-01-14 02:00:00', 'type' => 'Maintenance', 'size' => '892 KB'],
    ['name' => 'Security Audit Report', 'generated' => '2024-01-13 11:20:00', 'type' => 'Security', 'size' => '3.1 MB'],
    ['name' => 'Staff Statistics Report', 'generated' => '2024-01-12 16:15:00', 'type' => 'Personnel', 'size' => '1.2 MB']
];

include dirname(__DIR__) . '/shared/header.php';
include dirname(__DIR__) . '/shared/sidebar.php';
?>

<!-- Main Content -->
<div class="content-wrapper with-sidebar">
    <div class="container-fluid">
        <div class="main-content">
            <!-- Header Section -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h1 class="admin-section-title">
                                <i class="fas fa-chart-bar text-primary"></i> System Reports
                            </h1>
                            <p class="text-muted mb-0">Generate and view comprehensive system reports</p>
                        </div>
                        <div>
                            <div class="btn-group" role="group">
                                <button class="btn btn-success" onclick="generateReport()">
                                    <i class="fas fa-plus"></i> Generate Report
                                </button>
                                <button class="btn btn-primary" onclick="scheduleReport()">
                                    <i class="fas fa-clock"></i> Schedule Report
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Quick Stats -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="card bg-primary text-white">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h6 class="card-title">Total Users</h6>
                                    <h4 class="mb-0"><?= number_format($systemStats['total_users']) ?></h4>
                                </div>
                                <div class="align-self-center">
                                    <i class="fas fa-users fa-2x opacity-75"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-success text-white">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h6 class="card-title">Active Sessions</h6>
                                    <h4 class="mb-0"><?= number_format($systemStats['active_sessions']) ?></h4>
                                </div>
                                <div class="align-self-center">
                                    <i class="fas fa-circle fa-2x opacity-75"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-info text-white">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h6 class="card-title">Total Staff</h6>
                                    <h4 class="mb-0"><?= number_format($systemStats['total_staff']) ?></h4>
                                </div>
                                <div class="align-self-center">
                                    <i class="fas fa-user-tie fa-2x opacity-75"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-warning text-white">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h6 class="card-title">Pending Approvals</h6>
                                    <h4 class="mb-0"><?= number_format($systemStats['pending_approvals']) ?></h4>
                                </div>
                                <div class="align-self-center">
                                    <i class="fas fa-clock fa-2x opacity-75"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Report Categories -->
            <div class="row mb-4">
                <div class="col-md-4">
                    <div class="card h-100">
                        <div class="card-header bg-primary text-white">
                            <h5 class="card-title mb-0">
                                <i class="fas fa-shield-alt"></i> Security Reports
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="list-group list-group-flush">
                                <button class="list-group-item list-group-item-action" onclick="generateSecurityReport('login_activity')">
                                    <i class="fas fa-sign-in-alt"></i> Login Activity Report
                                </button>
                                <button class="list-group-item list-group-item-action" onclick="generateSecurityReport('failed_attempts')">
                                    <i class="fas fa-exclamation-triangle"></i> Failed Login Attempts
                                </button>
                                <button class="list-group-item list-group-item-action" onclick="generateSecurityReport('user_permissions')">
                                    <i class="fas fa-user-shield"></i> User Permissions Audit
                                </button>
                                <button class="list-group-item list-group-item-action" onclick="generateSecurityReport('access_log')">
                                    <i class="fas fa-list-alt"></i> System Access Log
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card h-100">
                        <div class="card-header bg-success text-white">
                            <h5 class="card-title mb-0">
                                <i class="fas fa-chart-line"></i> Performance Reports
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="list-group list-group-flush">
                                <button class="list-group-item list-group-item-action" onclick="generatePerformanceReport('system_usage')">
                                    <i class="fas fa-tachometer-alt"></i> System Usage Statistics
                                </button>
                                <button class="list-group-item list-group-item-action" onclick="generatePerformanceReport('database_performance')">
                                    <i class="fas fa-database"></i> Database Performance
                                </button>
                                <button class="list-group-item list-group-item-action" onclick="generatePerformanceReport('response_times')">
                                    <i class="fas fa-stopwatch"></i> Response Time Analysis
                                </button>
                                <button class="list-group-item list-group-item-action" onclick="generatePerformanceReport('resource_utilization')">
                                    <i class="fas fa-server"></i> Resource Utilization
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card h-100">
                        <div class="card-header bg-info text-white">
                            <h5 class="card-title mb-0">
                                <i class="fas fa-users"></i> Personnel Reports
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="list-group list-group-flush">
                                <button class="list-group-item list-group-item-action" onclick="generatePersonnelReport('staff_summary')">
                                    <i class="fas fa-clipboard-list"></i> Staff Summary Report
                                </button>
                                <button class="list-group-item list-group-item-action" onclick="generatePersonnelReport('rank_distribution')">
                                    <i class="fas fa-medal"></i> Rank Distribution
                                </button>
                                <button class="list-group-item list-group-item-action" onclick="generatePersonnelReport('unit_strength')">
                                    <i class="fas fa-flag"></i> Unit Strength Report
                                </button>
                                <button class="list-group-item list-group-item-action" onclick="generatePersonnelReport('training_status')">
                                    <i class="fas fa-graduation-cap"></i> Training Status Report
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Monthly Statistics Chart -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-chart-area"></i> Monthly Statistics
                    </h5>
                </div>
                <div class="card-body">
                    <canvas id="monthlyStatsChart" width="400" height="200"></canvas>
                </div>
            </div>

            <!-- Recent Reports -->
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-history"></i> Recent Reports
                    </h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead>
                                <tr>
                                    <th>Report Name</th>
                                    <th>Generated</th>
                                    <th>Type</th>
                                    <th>Size</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recentReports as $report): ?>
                                <tr>
                                    <td>
                                        <strong><?= htmlspecialchars($report['name']) ?></strong>
                                    </td>
                                    <td><?= date('M j, Y H:i', strtotime($report['generated'])) ?></td>
                                    <td>
                                        <?php
                                        $typeClass = match($report['type']) {
                                            'Security' => 'danger',
                                            'Performance' => 'success',
                                            'Personnel' => 'info',
                                            'Maintenance' => 'warning',
                                            default => 'secondary'
                                        };
                                        ?>
                                        <span class="badge bg-<?= $typeClass ?>"><?= $report['type'] ?></span>
                                    </td>
                                    <td><?= $report['size'] ?></td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <button class="btn btn-outline-primary" onclick="viewReport('<?= $report['name'] ?>')">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <button class="btn btn-outline-success" onclick="downloadReport('<?= $report['name'] ?>')">
                                                <i class="fas fa-download"></i>
                                            </button>
                                            <button class="btn btn-outline-danger" onclick="deleteReport('<?= $report['name'] ?>')">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Report Generation Modal -->
<div class="modal fade" id="reportModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Generate Custom Report</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="reportForm">
                    <div class="mb-3">
                        <label for="reportType" class="form-label">Report Type</label>
                        <select class="form-select" id="reportType" required>
                            <option value="">Select report type...</option>
                            <option value="security">Security Report</option>
                            <option value="performance">Performance Report</option>
                            <option value="personnel">Personnel Report</option>
                            <option value="maintenance">Maintenance Report</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="dateRange" class="form-label">Date Range</label>
                        <select class="form-select" id="dateRange" required>
                            <option value="today">Today</option>
                            <option value="week">Last 7 days</option>
                            <option value="month" selected>Last 30 days</option>
                            <option value="quarter">Last 3 months</option>
                            <option value="year">Last year</option>
                            <option value="custom">Custom range</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="format" class="form-label">Output Format</label>
                        <select class="form-select" id="format" required>
                            <option value="pdf">PDF</option>
                            <option value="excel">Excel</option>
                            <option value="csv">CSV</option>
                            <option value="html">HTML</option>
                        </select>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="submitReportGeneration()">Generate Report</button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// Initialize monthly statistics chart
const ctx = document.getElementById('monthlyStatsChart').getContext('2d');
const monthlyStatsChart = new Chart(ctx, {
    type: 'line',
    data: {
        labels: <?= json_encode(array_column($monthlyStats, 'month')) ?>,
        datasets: [{
            label: 'User Registrations',
            data: <?= json_encode(array_column($monthlyStats, 'users')) ?>,
            borderColor: 'rgb(75, 192, 192)',
            backgroundColor: 'rgba(75, 192, 192, 0.1)',
            tension: 0.1
        }, {
            label: 'Logins',
            data: <?= json_encode(array_column($monthlyStats, 'logins')) ?>,
            borderColor: 'rgb(255, 99, 132)',
            backgroundColor: 'rgba(255, 99, 132, 0.1)',
            tension: 0.1
        }, {
            label: 'Errors',
            data: <?= json_encode(array_column($monthlyStats, 'errors')) ?>,
            borderColor: 'rgb(255, 205, 86)',
            backgroundColor: 'rgba(255, 205, 86, 0.1)',
            tension: 0.1
        }]
    },
    options: {
        responsive: true,
        plugins: {
            title: {
                display: true,
                text: 'System Activity Trends'
            }
        },
        scales: {
            y: {
                beginAtZero: true
            }
        }
    }
});

function generateReport() {
    const modal = new bootstrap.Modal(document.getElementById('reportModal'));
    modal.show();
}

function scheduleReport() {
    alert('Report scheduling interface would be displayed.');
}

function generateSecurityReport(type) {
    alert('Generating security report: ' + type);
}

function generatePerformanceReport(type) {
    alert('Generating performance report: ' + type);
}

function generatePersonnelReport(type) {
    alert('Generating personnel report: ' + type);
}

function viewReport(name) {
    alert('Viewing report: ' + name);
}

function downloadReport(name) {
    alert('Downloading report: ' + name);
}

function deleteReport(name) {
    if (confirm('Are you sure you want to delete the report: ' + name + '?')) {
        alert('Report would be deleted: ' + name);
    }
}

function submitReportGeneration() {
    const form = document.getElementById('reportForm');
    if (form.checkValidity()) {
        const reportType = document.getElementById('reportType').value;
        const dateRange = document.getElementById('dateRange').value;
        const format = document.getElementById('format').value;
        
        alert(`Generating ${reportType} report for ${dateRange} in ${format} format...`);
        
        const modal = bootstrap.Modal.getInstance(document.getElementById('reportModal'));
        modal.hide();
    } else {
        form.reportValidity();
    }
}
</script>

<?php include dirname(__DIR__) . '/shared/footer.php'; ?>
