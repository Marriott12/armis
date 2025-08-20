<?php
/**
 * ARMIS System Administration - Enhanced Dashboard
 * Complete system management and monitoring tools
 */

// Module constants
define('ARMIS_ADMIN', true);

// Include core files
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/shared/session_init.php';
require_once dirname(__DIR__) . '/shared/database_connection.php';
require_once dirname(__DIR__) . '/shared/rbac.php';
require_once __DIR__ . '/includes/admin_service.php';

// Authentication and authorization
if (!isset($_SESSION['user_id']) || !isset($_SESSION['username'])) {
    header('Location: ' . ARMIS_BASE_URL . '/login.php');
    exit();
}

if (!hasModuleAccess('admin')) {
    header('Location: ' . ARMIS_BASE_URL . '/unauthorized.php?module=admin');
    exit();
}

// Initialize services
$pdo = getDbConnection();
$adminService = new AdminService($pdo);

// Get system status and metrics
$systemData = [
    'system_health' => $adminService->getSystemHealth(),
    'performance_metrics' => $adminService->getPerformanceMetrics(),
    'security_status' => $adminService->getSecurityStatus(),
    'user_activity' => $adminService->getUserActivity(),
    'system_logs' => $adminService->getRecentSystemLogs(10),
    'database_status' => $adminService->getDatabaseStatus(),
    'module_status' => $adminService->getModuleStatus()
];

// Page title
$pageTitle = 'System Administration Dashboard';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?> - ARMIS</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <!-- Custom CSS -->
    <link href="css/admin.css" rel="stylesheet">
</head>
<body>
    <!-- Include header -->
    <?php include dirname(__DIR__) . '/shared/header.php'; ?>

    <div class="container-fluid">
        <div class="row">
            <!-- Include sidebar -->
            <div class="col-md-2 px-0">
                <?php include dirname(__DIR__) . '/shared/sidebar.php'; ?>
            </div>

            <!-- Main content -->
            <div class="col-md-10">
                <div class="content-area admin-dashboard">
                    <!-- Page header -->
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <div>
                            <h1 class="h3 mb-0">
                                <i class="fas fa-cogs text-primary"></i>
                                System Administration
                            </h1>
                            <p class="text-muted mb-0">System Management & Monitoring Dashboard</p>
                        </div>
                        <div class="btn-group" role="group">
                            <a href="system_config.php" class="btn btn-primary">
                                <i class="fas fa-cog"></i> System Config
                            </a>
                            <a href="user_management.php" class="btn btn-outline-primary">
                                <i class="fas fa-users"></i> User Management
                            </a>
                            <a href="security_center.php" class="btn btn-outline-warning">
                                <i class="fas fa-shield-alt"></i> Security Center
                            </a>
                            <button class="btn btn-outline-secondary" onclick="refreshDashboard()">
                                <i class="fas fa-sync-alt"></i> Refresh
                            </button>
                        </div>
                    </div>

                    <!-- System Health Overview -->
                    <div class="row mb-4">
                        <div class="col-lg-3 col-md-6 mb-3">
                            <div class="card border-0 shadow-sm health-card <?= $systemData['system_health']['overall_status'] === 'HEALTHY' ? 'bg-success' : ($systemData['system_health']['overall_status'] === 'WARNING' ? 'bg-warning' : 'bg-danger') ?>">
                                <div class="card-body text-white">
                                    <div class="d-flex align-items-center">
                                        <div class="me-3">
                                            <i class="fas fa-heart fa-2x"></i>
                                        </div>
                                        <div>
                                            <h5 class="card-title mb-0"><?= $systemData['system_health']['overall_status'] ?></h5>
                                            <p class="card-text mb-0">System Health</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-lg-3 col-md-6 mb-3">
                            <div class="card border-0 shadow-sm">
                                <div class="card-body">
                                    <div class="d-flex align-items-center">
                                        <div class="me-3">
                                            <div class="bg-info bg-gradient rounded-circle p-3">
                                                <i class="fas fa-users text-white"></i>
                                            </div>
                                        </div>
                                        <div>
                                            <h5 class="card-title mb-0"><?= $systemData['user_activity']['active_users'] ?? 0 ?></h5>
                                            <p class="card-text text-muted">Active Users</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-lg-3 col-md-6 mb-3">
                            <div class="card border-0 shadow-sm">
                                <div class="card-body">
                                    <div class="d-flex align-items-center">
                                        <div class="me-3">
                                            <div class="bg-primary bg-gradient rounded-circle p-3">
                                                <i class="fas fa-database text-white"></i>
                                            </div>
                                        </div>
                                        <div>
                                            <h5 class="card-title mb-0"><?= number_format($systemData['database_status']['total_size_mb'], 1) ?> MB</h5>
                                            <p class="card-text text-muted">Database Size</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-lg-3 col-md-6 mb-3">
                            <div class="card border-0 shadow-sm">
                                <div class="card-body">
                                    <div class="d-flex align-items-center">
                                        <div class="me-3">
                                            <div class="bg-success bg-gradient rounded-circle p-3">
                                                <i class="fas fa-server text-white"></i>
                                            </div>
                                        </div>
                                        <div>
                                            <h5 class="card-title mb-0"><?= $systemData['performance_metrics']['uptime_days'] ?? 0 ?> days</h5>
                                            <p class="card-text text-muted">System Uptime</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Main Dashboard Content -->
                    <div class="row">
                        <div class="col-lg-8">
                            <!-- Performance Metrics -->
                            <div class="card border-0 shadow-sm mb-4">
                                <div class="card-header bg-primary bg-gradient text-white">
                                    <h6 class="mb-0">
                                        <i class="fas fa-chart-line"></i>
                                        Performance Metrics
                                    </h6>
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <canvas id="performanceChart" width="400" height="200"></canvas>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="metric-item mb-3">
                                                <div class="d-flex justify-content-between align-items-center">
                                                    <span>CPU Usage</span>
                                                    <span class="fw-bold text-primary"><?= $systemData['performance_metrics']['cpu_usage'] ?? 0 ?>%</span>
                                                </div>
                                                <div class="progress mt-1" style="height: 6px;">
                                                    <div class="progress-bar bg-primary" style="width: <?= $systemData['performance_metrics']['cpu_usage'] ?? 0 ?>%"></div>
                                                </div>
                                            </div>
                                            
                                            <div class="metric-item mb-3">
                                                <div class="d-flex justify-content-between align-items-center">
                                                    <span>Memory Usage</span>
                                                    <span class="fw-bold text-info"><?= $systemData['performance_metrics']['memory_usage'] ?? 0 ?>%</span>
                                                </div>
                                                <div class="progress mt-1" style="height: 6px;">
                                                    <div class="progress-bar bg-info" style="width: <?= $systemData['performance_metrics']['memory_usage'] ?? 0 ?>%"></div>
                                                </div>
                                            </div>
                                            
                                            <div class="metric-item mb-3">
                                                <div class="d-flex justify-content-between align-items-center">
                                                    <span>Disk Usage</span>
                                                    <span class="fw-bold text-warning"><?= $systemData['performance_metrics']['disk_usage'] ?? 0 ?>%</span>
                                                </div>
                                                <div class="progress mt-1" style="height: 6px;">
                                                    <div class="progress-bar bg-warning" style="width: <?= $systemData['performance_metrics']['disk_usage'] ?? 0 ?>%"></div>
                                                </div>
                                            </div>
                                            
                                            <div class="metric-item">
                                                <div class="d-flex justify-content-between align-items-center">
                                                    <span>Database Load</span>
                                                    <span class="fw-bold text-success"><?= $systemData['database_status']['connection_usage'] ?? 0 ?>%</span>
                                                </div>
                                                <div class="progress mt-1" style="height: 6px;">
                                                    <div class="progress-bar bg-success" style="width: <?= $systemData['database_status']['connection_usage'] ?? 0 ?>%"></div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- System Logs -->
                            <div class="card border-0 shadow-sm">
                                <div class="card-header bg-secondary bg-gradient text-white d-flex justify-content-between align-items-center">
                                    <h6 class="mb-0">
                                        <i class="fas fa-list-alt"></i>
                                        Recent System Logs
                                    </h6>
                                    <a href="system_logs.php" class="btn btn-sm btn-light">
                                        View All
                                    </a>
                                </div>
                                <div class="card-body p-0">
                                    <?php if (empty($systemData['system_logs'])): ?>
                                        <div class="text-center text-muted py-4">
                                            <i class="fas fa-clipboard-list fa-3x mb-3"></i>
                                            <p class="mb-0">No recent system logs</p>
                                        </div>
                                    <?php else: ?>
                                        <div class="table-responsive">
                                            <table class="table table-sm mb-0">
                                                <thead class="table-light">
                                                    <tr>
                                                        <th>Time</th>
                                                        <th>Level</th>
                                                        <th>Module</th>
                                                        <th>Message</th>
                                                        <th>User</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($systemData['system_logs'] as $log): ?>
                                                    <tr>
                                                        <td>
                                                            <small><?= date('H:i:s', strtotime($log['created_at'])) ?></small>
                                                        </td>
                                                        <td>
                                                            <span class="badge bg-<?= $log['severity'] === 'CRITICAL' ? 'danger' : ($log['severity'] === 'HIGH' ? 'warning' : 'info') ?>">
                                                                <?= $log['severity'] ?>
                                                            </span>
                                                        </td>
                                                        <td><?= htmlspecialchars($log['module']) ?></td>
                                                        <td><?= htmlspecialchars(substr($log['action'], 0, 50)) ?>...</td>
                                                        <td><?= htmlspecialchars($log['user_name'] ?? 'System') ?></td>
                                                    </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <div class="col-lg-4">
                            <!-- Quick Actions -->
                            <div class="card border-0 shadow-sm mb-4">
                                <div class="card-header bg-success bg-gradient text-white">
                                    <h6 class="mb-0">
                                        <i class="fas fa-bolt"></i>
                                        Quick Actions
                                    </h6>
                                </div>
                                <div class="card-body">
                                    <div class="d-grid gap-2">
                                        <a href="system_config.php" class="btn btn-outline-primary">
                                            <i class="fas fa-cog"></i> System Configuration
                                        </a>
                                        <a href="user_management.php" class="btn btn-outline-info">
                                            <i class="fas fa-users-cog"></i> User Management
                                        </a>
                                        <a href="backup_restore.php" class="btn btn-outline-warning">
                                            <i class="fas fa-database"></i> Backup & Restore
                                        </a>
                                        <a href="security_center.php" class="btn btn-outline-danger">
                                            <i class="fas fa-shield-alt"></i> Security Center
                                        </a>
                                        <a href="system_maintenance.php" class="btn btn-outline-secondary">
                                            <i class="fas fa-wrench"></i> System Maintenance
                                        </a>
                                        <a href="audit_logs.php" class="btn btn-outline-dark">
                                            <i class="fas fa-eye"></i> Audit Logs
                                        </a>
                                    </div>
                                </div>
                            </div>

                            <!-- Module Status -->
                            <div class="card border-0 shadow-sm mb-4">
                                <div class="card-header bg-info bg-gradient text-white">
                                    <h6 class="mb-0">
                                        <i class="fas fa-puzzle-piece"></i>
                                        Module Status
                                    </h6>
                                </div>
                                <div class="card-body">
                                    <?php foreach ($systemData['module_status'] as $module => $status): ?>
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <span class="text-capitalize"><?= str_replace('_', ' ', $module) ?></span>
                                        <span class="badge bg-<?= $status['enabled'] ? 'success' : 'secondary' ?>">
                                            <?= $status['enabled'] ? 'Active' : 'Disabled' ?>
                                        </span>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>

                            <!-- Security Status -->
                            <div class="card border-0 shadow-sm mb-4">
                                <div class="card-header bg-warning bg-gradient text-white">
                                    <h6 class="mb-0">
                                        <i class="fas fa-shield-alt"></i>
                                        Security Status
                                    </h6>
                                </div>
                                <div class="card-body">
                                    <div class="security-metric mb-3">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <span>Failed Login Attempts (24h)</span>
                                            <span class="badge bg-danger"><?= $systemData['security_status']['failed_logins_24h'] ?? 0 ?></span>
                                        </div>
                                    </div>
                                    
                                    <div class="security-metric mb-3">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <span>Security Incidents</span>
                                            <span class="badge bg-warning"><?= $systemData['security_status']['open_incidents'] ?? 0 ?></span>
                                        </div>
                                    </div>
                                    
                                    <div class="security-metric mb-3">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <span>Last Security Scan</span>
                                            <small class="text-muted"><?= $systemData['security_status']['last_scan'] ?? 'Never' ?></small>
                                        </div>
                                    </div>
                                    
                                    <div class="d-grid">
                                        <a href="security_center.php" class="btn btn-sm btn-outline-warning">
                                            <i class="fas fa-shield-alt"></i> Security Center
                                        </a>
                                    </div>
                                </div>
                            </div>

                            <!-- Database Status -->
                            <div class="card border-0 shadow-sm">
                                <div class="card-header bg-secondary bg-gradient text-white">
                                    <h6 class="mb-0">
                                        <i class="fas fa-database"></i>
                                        Database Status
                                    </h6>
                                </div>
                                <div class="card-body">
                                    <div class="db-metric mb-2">
                                        <div class="d-flex justify-content-between">
                                            <span>Total Tables</span>
                                            <span class="fw-bold"><?= $systemData['database_status']['table_count'] ?? 0 ?></span>
                                        </div>
                                    </div>
                                    
                                    <div class="db-metric mb-2">
                                        <div class="d-flex justify-content-between">
                                            <span>Total Records</span>
                                            <span class="fw-bold"><?= number_format($systemData['database_status']['total_records'] ?? 0) ?></span>
                                        </div>
                                    </div>
                                    
                                    <div class="db-metric mb-3">
                                        <div class="d-flex justify-content-between">
                                            <span>Last Backup</span>
                                            <small class="text-muted"><?= $systemData['database_status']['last_backup'] ?? 'Never' ?></small>
                                        </div>
                                    </div>
                                    
                                    <div class="d-grid gap-2">
                                        <a href="database_management.php" class="btn btn-sm btn-outline-primary">
                                            <i class="fas fa-database"></i> Manage Database
                                        </a>
                                        <button class="btn btn-sm btn-outline-success" onclick="createBackup()">
                                            <i class="fas fa-download"></i> Create Backup
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Include footer -->
    <?php include dirname(__DIR__) . '/shared/footer.php'; ?>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Custom JS -->
    <script src="js/admin.js"></script>

    <script>
        // Initialize dashboard on page load
        document.addEventListener('DOMContentLoaded', function() {
            console.log('Admin dashboard loaded');
            initPerformanceChart();
            ARMIS_ADMIN.init();
        });

        function refreshDashboard() {
            location.reload();
        }

        function createBackup() {
            if (confirm('Are you sure you want to create a database backup?')) {
                ARMIS_ADMIN.backup.create();
            }
        }

        function initPerformanceChart() {
            const ctx = document.getElementById('performanceChart').getContext('2d');
            const chart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: ['6h ago', '5h ago', '4h ago', '3h ago', '2h ago', '1h ago', 'Now'],
                    datasets: [{
                        label: 'CPU %',
                        data: [<?= implode(',', array_slice(array_merge(array_fill(0, 7, 0), [$systemData['performance_metrics']['cpu_usage'] ?? 0]), -7)) ?>],
                        borderColor: '#007bff',
                        backgroundColor: 'rgba(0, 123, 255, 0.1)',
                        tension: 0.4
                    }, {
                        label: 'Memory %',
                        data: [<?= implode(',', array_slice(array_merge(array_fill(0, 7, 0), [$systemData['performance_metrics']['memory_usage'] ?? 0]), -7)) ?>],
                        borderColor: '#17a2b8',
                        backgroundColor: 'rgba(23, 162, 184, 0.1)',
                        tension: 0.4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            max: 100
                        }
                    },
                    plugins: {
                        legend: {
                            position: 'top'
                        }
                    }
                }
            });
        }
    </script>
</body>
</html>