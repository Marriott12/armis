<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include RBAC system
require_once dirname(__DIR__) . '/shared/rbac.php';
require_once dirname(__DIR__) . '/shared/database_connection.php';

$pageTitle = "System Reports";
$moduleName = "System Admin";
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

// Generate sample report data
$reportData = [];
$reportMetrics = [];

try {
    $pdo = getDbConnection();
    
    // User statistics
    $stmt = $pdo->query("SELECT 
        COUNT(*) as total_users,
        SUM(CASE WHEN accStatus = 'active' THEN 1 ELSE 0 END) as active_users,
        SUM(CASE WHEN accStatus != 'active' THEN 1 ELSE 0 END) as inactive_users
        FROM staff");
    $userStats = $stmt->fetch();
    
    // Role distribution
    $stmt = $pdo->query("SELECT role, COUNT(*) as count FROM staff GROUP BY role ORDER BY count DESC");
    $roleDistribution = $stmt->fetchAll();
    
    // Unit distribution
    $stmt = $pdo->query("SELECT u.name as unit_name, COUNT(s.id) as count 
                        FROM units u 
                        LEFT JOIN staff s ON u.id = s.unit_id 
                        GROUP BY u.id, u.name 
                        ORDER BY count DESC 
                        LIMIT 10");
    $unitDistribution = $stmt->fetchAll();
    
    // Recent activity (simulated)
    $recentActivity = [
        ['date' => date('Y-m-d', strtotime('-1 day')), 'logins' => rand(45, 85), 'users' => rand(20, 40)],
        ['date' => date('Y-m-d', strtotime('-2 days')), 'logins' => rand(40, 80), 'users' => rand(18, 38)],
        ['date' => date('Y-m-d', strtotime('-3 days')), 'logins' => rand(50, 90), 'users' => rand(25, 45)],
        ['date' => date('Y-m-d', strtotime('-4 days')), 'logins' => rand(35, 75), 'users' => rand(15, 35)],
        ['date' => date('Y-m-d', strtotime('-5 days')), 'logins' => rand(55, 95), 'users' => rand(30, 50)],
        ['date' => date('Y-m-d', strtotime('-6 days')), 'logins' => rand(42, 82), 'users' => rand(22, 42)],
        ['date' => date('Y-m-d', strtotime('-7 days')), 'logins' => rand(48, 88), 'users' => rand(26, 46)]
    ];
    
    $reportData = [
        'user_stats' => $userStats,
        'role_distribution' => $roleDistribution,
        'unit_distribution' => $unitDistribution,
        'recent_activity' => array_reverse($recentActivity)
    ];
    
} catch (Exception $e) {
    error_log("Reports error: " . $e->getMessage());
    $reportData = [
        'user_stats' => ['total_users' => 0, 'active_users' => 0, 'inactive_users' => 0],
        'role_distribution' => [],
        'unit_distribution' => [],
        'recent_activity' => []
    ];
}

// Available report types
$reportTypes = [
    'user_summary' => ['title' => 'User Summary Report', 'icon' => 'users', 'description' => 'Complete overview of all system users'],
    'activity_report' => ['title' => 'Activity Report', 'icon' => 'chart-line', 'description' => 'User login and activity statistics'],
    'security_audit' => ['title' => 'Security Audit', 'icon' => 'shield-alt', 'description' => 'Security events and access logs'],
    'system_health' => ['title' => 'System Health Report', 'icon' => 'heartbeat', 'description' => 'Database and system performance metrics'],
    'role_analysis' => ['title' => 'Role Analysis', 'icon' => 'chart-pie', 'description' => 'User role distribution and permissions'],
    'unit_breakdown' => ['title' => 'Unit Breakdown', 'icon' => 'sitemap', 'description' => 'Personnel distribution by unit']
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
                            <p class="text-muted mb-0">Generate comprehensive system reports and analytics</p>
                        </div>
                        <div>
                            <button class="btn btn-primary" onclick="showReportGenerator()">
                                <i class="fas fa-plus"></i> Generate Report
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Report Quick Stats -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="card bg-primary text-white">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <div class="h4 mb-0"><?php echo number_format($reportData['user_stats']['total_users']); ?></div>
                                    <div>Total Users</div>
                                </div>
                                <div class="align-self-center">
                                    <i class="fas fa-users fa-2x opacity-50"></i>
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
                                    <div class="h4 mb-0"><?php echo number_format($reportData['user_stats']['active_users']); ?></div>
                                    <div>Active Users</div>
                                </div>
                                <div class="align-self-center">
                                    <i class="fas fa-user-check fa-2x opacity-50"></i>
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
                                    <div class="h4 mb-0"><?php echo count($reportData['role_distribution']); ?></div>
                                    <div>User Roles</div>
                                </div>
                                <div class="align-self-center">
                                    <i class="fas fa-user-tag fa-2x opacity-50"></i>
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
                                    <div class="h4 mb-0"><?php echo count($reportData['unit_distribution']); ?></div>
                                    <div>Units</div>
                                </div>
                                <div class="align-self-center">
                                    <i class="fas fa-sitemap fa-2x opacity-50"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Available Reports -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="fas fa-file-alt"></i> Available Reports</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <?php foreach ($reportTypes as $key => $report): ?>
                                <div class="col-md-4 mb-3">
                                    <div class="card border">
                                        <div class="card-body text-center">
                                            <div class="mb-3">
                                                <i class="fas fa-<?php echo $report['icon']; ?> fa-3x text-primary"></i>
                                            </div>
                                            <h6 class="card-title"><?php echo $report['title']; ?></h6>
                                            <p class="card-text small text-muted"><?php echo $report['description']; ?></p>
                                            <button class="btn btn-outline-primary btn-sm" onclick="generateReport('<?php echo $key; ?>')">
                                                <i class="fas fa-download"></i> Generate
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Data Visualization -->
            <div class="row mb-4">
                <div class="col-md-6">
                    <!-- Role Distribution Chart -->
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="fas fa-chart-pie"></i> Role Distribution</h5>
                        </div>
                        <div class="card-body">
                            <?php if (!empty($reportData['role_distribution'])): ?>
                            <div class="table-responsive">
                                <table class="table table-sm">
                                    <thead>
                                        <tr>
                                            <th>Role</th>
                                            <th>Count</th>
                                            <th>Percentage</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php 
                                        $totalUsers = $reportData['user_stats']['total_users'];
                                        foreach ($reportData['role_distribution'] as $role): 
                                            $percentage = $totalUsers > 0 ? round(($role['count'] / $totalUsers) * 100, 1) : 0;
                                        ?>
                                        <tr>
                                            <td>
                                                <strong><?php echo ucfirst($role['role'] ?? 'Unknown'); ?></strong>
                                            </td>
                                            <td><?php echo number_format($role['count']); ?></td>
                                            <td>
                                                <div class="progress" style="height: 20px;">
                                                    <div class="progress-bar" role="progressbar" style="width: <?php echo $percentage; ?>%">
                                                        <?php echo $percentage; ?>%
                                                    </div>
                                                </div>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <?php else: ?>
                            <div class="text-center text-muted py-3">
                                <i class="fas fa-chart-pie fa-2x mb-2"></i>
                                <div>No role data available</div>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <!-- Unit Distribution -->
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="fas fa-sitemap"></i> Top Units by Personnel</h5>
                        </div>
                        <div class="card-body">
                            <?php if (!empty($reportData['unit_distribution'])): ?>
                            <div class="table-responsive">
                                <table class="table table-sm">
                                    <thead>
                                        <tr>
                                            <th>Unit</th>
                                            <th>Personnel</th>
                                            <th>Distribution</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php 
                                        $maxCount = !empty($reportData['unit_distribution']) ? max(array_column($reportData['unit_distribution'], 'count')) : 1;
                                        foreach ($reportData['unit_distribution'] as $unit): 
                                            $percentage = $maxCount > 0 ? round(($unit['count'] / $maxCount) * 100, 1) : 0;
                                        ?>
                                        <tr>
                                            <td>
                                                <strong><?php echo htmlspecialchars($unit['unit_name'] ?? 'Unknown'); ?></strong>
                                            </td>
                                            <td><?php echo number_format($unit['count']); ?></td>
                                            <td>
                                                <div class="progress" style="height: 20px;">
                                                    <div class="progress-bar bg-success" role="progressbar" style="width: <?php echo $percentage; ?>%">
                                                        <?php echo $percentage; ?>%
                                                    </div>
                                                </div>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <?php else: ?>
                            <div class="text-center text-muted py-3">
                                <i class="fas fa-sitemap fa-2x mb-2"></i>
                                <div>No unit data available</div>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Activity Timeline -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="fas fa-chart-line"></i> 7-Day Activity Summary</h5>
                        </div>
                        <div class="card-body">
                            <?php if (!empty($reportData['recent_activity'])): ?>
                            <div class="table-responsive">
                                <table class="table table-sm">
                                    <thead>
                                        <tr>
                                            <th>Date</th>
                                            <th>Total Logins</th>
                                            <th>Active Users</th>
                                            <th>Activity Level</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php 
                                        $maxLogins = !empty($reportData['recent_activity']) ? max(array_column($reportData['recent_activity'], 'logins')) : 1;
                                        foreach ($reportData['recent_activity'] as $day): 
                                            $activityLevel = $maxLogins > 0 ? round(($day['logins'] / $maxLogins) * 100, 1) : 0;
                                            $levelClass = $activityLevel > 80 ? 'success' : ($activityLevel > 50 ? 'warning' : 'info');
                                        ?>
                                        <tr>
                                            <td>
                                                <strong><?php echo date('M j, Y', strtotime($day['date'])); ?></strong>
                                                <br><small class="text-muted"><?php echo date('l', strtotime($day['date'])); ?></small>
                                            </td>
                                            <td><?php echo number_format($day['logins']); ?></td>
                                            <td><?php echo number_format($day['users']); ?></td>
                                            <td>
                                                <div class="progress" style="height: 20px;">
                                                    <div class="progress-bar bg-<?php echo $levelClass; ?>" role="progressbar" style="width: <?php echo $activityLevel; ?>%">
                                                        <?php echo $activityLevel; ?>%
                                                    </div>
                                                </div>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <?php else: ?>
                            <div class="text-center text-muted py-3">
                                <i class="fas fa-chart-line fa-2x mb-2"></i>
                                <div>No activity data available</div>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Report Export Options -->
            <div class="row">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="fas fa-download"></i> Export Options</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-3">
                                    <button class="btn btn-outline-success w-100 mb-2" onclick="exportToExcel()">
                                        <i class="fas fa-file-excel"></i> Export to Excel
                                    </button>
                                </div>
                                <div class="col-md-3">
                                    <button class="btn btn-outline-danger w-100 mb-2" onclick="exportToPDF()">
                                        <i class="fas fa-file-pdf"></i> Export to PDF
                                    </button>
                                </div>
                                <div class="col-md-3">
                                    <button class="btn btn-outline-info w-100 mb-2" onclick="exportToCSV()">
                                        <i class="fas fa-file-csv"></i> Export to CSV
                                    </button>
                                </div>
                                <div class="col-md-3">
                                    <button class="btn btn-outline-secondary w-100 mb-2" onclick="scheduleReport()">
                                        <i class="fas fa-clock"></i> Schedule Report
                                    </button>
                                </div>
                            </div>
                            <div class="alert alert-info mt-3">
                                <i class="fas fa-info-circle"></i> Reports can be exported in multiple formats or scheduled for automatic generation.
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function generateReport(reportType) {
    const reportTitles = {
        'user_summary': 'User Summary Report',
        'activity_report': 'Activity Report',
        'security_audit': 'Security Audit',
        'system_health': 'System Health Report',
        'role_analysis': 'Role Analysis',
        'unit_breakdown': 'Unit Breakdown'
    };
    
    const title = reportTitles[reportType] || 'System Report';
    alert(`Generating ${title}...\n\nThis would compile data and create a comprehensive report including:\n• Statistical analysis\n• Charts and graphs\n• Detailed data tables\n• Recommendations\n\nThis is a demonstration interface.`);
}

function showReportGenerator() {
    alert('Report Generator would allow you to:\n\n' +
          '• Select custom date ranges\n' +
          '• Choose specific data fields\n' +
          '• Apply filters and criteria\n' +
          '• Customize report format\n' +
          '• Schedule automatic generation\n\n' +
          'This is a demonstration interface.');
}

function exportToExcel() {
    alert('Excel export would:\n\n' +
          '• Generate .xlsx file with multiple sheets\n' +
          '• Include charts and formatting\n' +
          '• Provide raw data for analysis\n' +
          '• Support filtering and sorting\n\n' +
          'This is a demonstration interface.');
}

function exportToPDF() {
    alert('PDF export would:\n\n' +
          '• Create formatted PDF report\n' +
          '• Include charts and graphics\n' +
          '• Provide professional layout\n' +
          '• Ready for printing or sharing\n\n' +
          'This is a demonstration interface.');
}

function exportToCSV() {
    alert('CSV export would:\n\n' +
          '• Generate comma-separated values file\n' +
          '• Include raw data only\n' +
          '• Compatible with spreadsheet applications\n' +
          '• Lightweight format for data analysis\n\n' +
          'This is a demonstration interface.');
}

function scheduleReport() {
    alert('Schedule Report would allow:\n\n' +
          '• Set recurring report generation\n' +
          '• Email reports automatically\n' +
          '• Choose frequency (daily, weekly, monthly)\n' +
          '• Configure recipients and format\n\n' +
          'This is a demonstration interface.');
}
</script>

<?php include dirname(__DIR__) . '/shared/footer.php'; ?>