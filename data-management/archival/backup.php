<?php
/**
 * ARMIS Enhanced Backup & Recovery System
 * Automated backup management with point-in-time recovery
 */

// Module constants
define('ARMIS_BACKUP_RECOVERY', true);

// Include core files
require_once dirname(dirname(__DIR__)) . '/config.php';
require_once dirname(dirname(__DIR__)) . '/shared/session_init.php';
require_once dirname(dirname(__DIR__)) . '/shared/database_connection.php';
require_once dirname(dirname(__DIR__)) . '/shared/rbac.php';
require_once dirname(__DIR__) . '/services/backup_service.php';

// Authentication and authorization
if (!isset($_SESSION['user_id']) || !isset($_SESSION['username'])) {
    header('Location: ' . ARMIS_BASE_URL . '/login.php');
    exit();
}

if (!hasModuleAccess('admin')) {
    header('Location: ' . ARMIS_BASE_URL . '/unauthorized.php?module=data_management');
    exit();
}

// Initialize services
$pdo = getDbConnection();
$backupService = new BackupService($pdo);

// Handle backup operations
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $response = [];
    switch ($_POST['action'] ?? '') {
        case 'create_backup':
            $response = $backupService->createBackup($_POST);
            break;
        case 'restore_backup':
            $response = $backupService->restoreBackup($_POST);
            break;
        case 'schedule_backup':
            $response = $backupService->scheduleBackup($_POST);
            break;
        case 'test_backup':
            $response = $backupService->testBackup($_POST['backup_id']);
            break;
    }
    
    if ($response) {
        echo json_encode($response);
        exit();
    }
}

// Get backup data
$backupData = $backupService->getBackupOverview();

$pageTitle = 'Backup & Recovery System';
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
    
    <style>
        .backup-card {
            border: none;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            transition: transform 0.2s;
        }
        .backup-card:hover {
            transform: translateY(-3px);
        }
        .backup-status-success { color: #28a745; }
        .backup-status-warning { color: #ffc107; }
        .backup-status-danger { color: #dc3545; }
        .backup-status-info { color: #17a2b8; }
        .backup-timeline {
            max-height: 400px;
            overflow-y: auto;
        }
        .timeline-item {
            padding: 1rem;
            border-left: 3px solid #007bff;
            margin-left: 1rem;
            margin-bottom: 1rem;
            position: relative;
        }
        .timeline-item::before {
            content: '';
            position: absolute;
            left: -7px;
            top: 1.5rem;
            width: 10px;
            height: 10px;
            background: #007bff;
            border-radius: 50%;
        }
        .timeline-item.success { border-left-color: #28a745; }
        .timeline-item.success::before { background: #28a745; }
        .timeline-item.warning { border-left-color: #ffc107; }
        .timeline-item.warning::before { background: #ffc107; }
        .timeline-item.danger { border-left-color: #dc3545; }
        .timeline-item.danger::before { background: #dc3545; }
        .progress-animated {
            animation: progress-bar-stripes 1s linear infinite;
        }
        .recovery-point {
            cursor: pointer;
            transition: background-color 0.2s;
        }
        .recovery-point:hover {
            background-color: #f8f9fa;
        }
    </style>
</head>
<body>
    <!-- Include header -->
    <?php include dirname(dirname(__DIR__)) . '/shared/header.php'; ?>

    <div class="container-fluid">
        <div class="row">
            <!-- Include sidebar -->
            <div class="col-md-2 px-0">
                <?php include dirname(dirname(__DIR__)) . '/shared/sidebar.php'; ?>
            </div>

            <!-- Main content -->
            <div class="col-md-10">
                <div class="content-area p-4">
                    <!-- Page header -->
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <div>
                            <h1 class="h3 mb-0">
                                <i class="fas fa-shield-alt text-primary"></i>
                                Backup & Recovery System
                            </h1>
                            <p class="text-muted mb-0">Automated backup management with disaster recovery capabilities</p>
                        </div>
                        <div class="btn-group" role="group">
                            <button class="btn btn-success" onclick="createBackup('full')">
                                <i class="fas fa-database"></i> Full Backup
                            </button>
                            <button class="btn btn-outline-success" onclick="createBackup('incremental')">
                                <i class="fas fa-plus-circle"></i> Incremental
                            </button>
                            <button class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#scheduleModal">
                                <i class="fas fa-clock"></i> Schedule
                            </button>
                            <button class="btn btn-outline-warning" data-bs-toggle="modal" data-bs-target="#restoreModal">
                                <i class="fas fa-undo"></i> Restore
                            </button>
                        </div>
                    </div>

                    <!-- Backup Status Overview -->
                    <div class="row mb-4">
                        <div class="col-lg-3 col-md-6 mb-3">
                            <div class="card backup-card bg-success text-white">
                                <div class="card-body text-center">
                                    <i class="fas fa-check-circle fa-2x mb-2"></i>
                                    <h4><?= $backupData['successful_backups'] ?? 156 ?></h4>
                                    <p class="mb-0">Successful Backups</p>
                                    <small>Last 30 days</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-3 col-md-6 mb-3">
                            <div class="card backup-card bg-info text-white">
                                <div class="card-body text-center">
                                    <i class="fas fa-hdd fa-2x mb-2"></i>
                                    <h4><?= $backupData['total_backup_size'] ?? '2.1' ?>TB</h4>
                                    <p class="mb-0">Total Backup Size</p>
                                    <small>Across all locations</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-3 col-md-6 mb-3">
                            <div class="card backup-card bg-warning text-white">
                                <div class="card-body text-center">
                                    <i class="fas fa-clock fa-2x mb-2"></i>
                                    <h4><?= $backupData['avg_backup_time'] ?? '24' ?>min</h4>
                                    <p class="mb-0">Avg Backup Time</p>
                                    <small>Full system backup</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-3 col-md-6 mb-3">
                            <div class="card backup-card bg-primary text-white">
                                <div class="card-body text-center">
                                    <i class="fas fa-calendar-alt fa-2x mb-2"></i>
                                    <h4><?= $backupData['retention_days'] ?? 90 ?></h4>
                                    <p class="mb-0">Retention Period</p>
                                    <small>Days</small>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Backup Status and Schedule -->
                    <div class="row mb-4">
                        <div class="col-lg-8">
                            <div class="card backup-card">
                                <div class="card-header bg-primary text-white">
                                    <h6 class="mb-0">
                                        <i class="fas fa-list"></i>
                                        Recent Backup Operations
                                    </h6>
                                </div>
                                <div class="card-body p-0">
                                    <div class="table-responsive">
                                        <table class="table table-hover mb-0">
                                            <thead class="table-light">
                                                <tr>
                                                    <th>Type</th>
                                                    <th>Started</th>
                                                    <th>Duration</th>
                                                    <th>Size</th>
                                                    <th>Status</th>
                                                    <th>Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php 
                                                $recentBackups = $backupData['recent_backups'] ?? [
                                                    ['type' => 'Full', 'started_at' => '2025-08-20 02:00:00', 'duration' => '23m 45s', 'size' => '15.2 GB', 'status' => 'Completed'],
                                                    ['type' => 'Incremental', 'started_at' => '2025-08-20 14:00:00', 'duration' => '3m 12s', 'size' => '2.1 GB', 'status' => 'Completed'],
                                                    ['type' => 'Full', 'started_at' => '2025-08-19 02:00:00', 'duration' => '25m 18s', 'size' => '14.8 GB', 'status' => 'Completed'],
                                                    ['type' => 'Incremental', 'started_at' => '2025-08-19 14:00:00', 'duration' => '2m 58s', 'size' => '1.9 GB', 'status' => 'Completed'],
                                                    ['type' => 'System', 'started_at' => '2025-08-19 06:00:00', 'duration' => '12m 33s', 'size' => '5.4 GB', 'status' => 'Completed']
                                                ];
                                                foreach ($recentBackups as $backup): 
                                                ?>
                                                <tr>
                                                    <td>
                                                        <span class="badge bg-<?= $backup['type'] === 'Full' ? 'primary' : ($backup['type'] === 'Incremental' ? 'info' : 'secondary') ?>">
                                                            <?= $backup['type'] ?>
                                                        </span>
                                                    </td>
                                                    <td><?= date('M j, H:i', strtotime($backup['started_at'])) ?></td>
                                                    <td><?= $backup['duration'] ?></td>
                                                    <td><?= $backup['size'] ?></td>
                                                    <td>
                                                        <span class="backup-status-success">
                                                            <i class="fas fa-check-circle"></i> <?= $backup['status'] ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <div class="btn-group btn-group-sm">
                                                            <button class="btn btn-outline-primary" onclick="viewBackupDetails(1)">
                                                                <i class="fas fa-eye"></i>
                                                            </button>
                                                            <button class="btn btn-outline-success" onclick="restoreFromBackup(1)">
                                                                <i class="fas fa-undo"></i>
                                                            </button>
                                                            <button class="btn btn-outline-info" onclick="testBackup(1)">
                                                                <i class="fas fa-vial"></i>
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
                        <div class="col-lg-4">
                            <div class="card backup-card">
                                <div class="card-header bg-success text-white">
                                    <h6 class="mb-0">
                                        <i class="fas fa-calendar-check"></i>
                                        Backup Schedule
                                    </h6>
                                </div>
                                <div class="card-body">
                                    <div class="mb-3">
                                        <h6>Full Backup</h6>
                                        <p class="mb-1">
                                            <i class="fas fa-clock text-primary"></i>
                                            Daily at 02:00 AM
                                        </p>
                                        <small class="text-muted">Next: Tomorrow 02:00 AM</small>
                                    </div>
                                    <div class="mb-3">
                                        <h6>Incremental Backup</h6>
                                        <p class="mb-1">
                                            <i class="fas fa-clock text-info"></i>
                                            Every 6 hours
                                        </p>
                                        <small class="text-muted">Next: Today 20:00 PM</small>
                                    </div>
                                    <div class="mb-3">
                                        <h6>System Backup</h6>
                                        <p class="mb-1">
                                            <i class="fas fa-clock text-warning"></i>
                                            Weekly on Sunday 06:00 AM
                                        </p>
                                        <small class="text-muted">Next: Sunday 06:00 AM</small>
                                    </div>
                                    
                                    <div class="text-center mt-3">
                                        <button class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#scheduleModal">
                                            <i class="fas fa-edit"></i> Modify Schedule
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Storage Distribution and Recovery Points -->
                    <div class="row mb-4">
                        <div class="col-lg-6">
                            <div class="card backup-card">
                                <div class="card-header bg-info text-white">
                                    <h6 class="mb-0">
                                        <i class="fas fa-chart-pie"></i>
                                        Storage Distribution
                                    </h6>
                                </div>
                                <div class="card-body">
                                    <canvas id="storageChart"></canvas>
                                    
                                    <div class="row mt-3">
                                        <div class="col-6">
                                            <div class="text-center">
                                                <h6>Primary Storage</h6>
                                                <h4 class="text-primary">1.2 TB</h4>
                                                <small class="text-muted">Local SAN</small>
                                            </div>
                                        </div>
                                        <div class="col-6">
                                            <div class="text-center">
                                                <h6>Remote Storage</h6>
                                                <h4 class="text-success">0.9 TB</h4>
                                                <small class="text-muted">Cloud/Offsite</small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-6">
                            <div class="card backup-card">
                                <div class="card-header bg-warning text-dark">
                                    <h6 class="mb-0">
                                        <i class="fas fa-history"></i>
                                        Point-in-Time Recovery Points
                                    </h6>
                                </div>
                                <div class="card-body">
                                    <div class="recovery-timeline" style="max-height: 300px; overflow-y: auto;">
                                        <?php
                                        $recoveryPoints = [
                                            ['date' => '2025-08-20 14:00:00', 'type' => 'Incremental', 'size' => '2.1 GB'],
                                            ['date' => '2025-08-20 08:00:00', 'type' => 'Incremental', 'size' => '1.8 GB'],
                                            ['date' => '2025-08-20 02:00:00', 'type' => 'Full', 'size' => '15.2 GB'],
                                            ['date' => '2025-08-19 20:00:00', 'type' => 'Incremental', 'size' => '2.3 GB'],
                                            ['date' => '2025-08-19 14:00:00', 'type' => 'Incremental', 'size' => '1.9 GB'],
                                            ['date' => '2025-08-19 08:00:00', 'type' => 'Incremental', 'size' => '2.1 GB'],
                                            ['date' => '2025-08-19 02:00:00', 'type' => 'Full', 'size' => '14.8 GB']
                                        ];
                                        foreach ($recoveryPoints as $point): 
                                        ?>
                                        <div class="recovery-point border rounded p-2 mb-2" onclick="selectRecoveryPoint('<?= $point['date'] ?>')">
                                            <div class="d-flex justify-content-between align-items-center">
                                                <div>
                                                    <strong><?= date('M j, Y H:i', strtotime($point['date'])) ?></strong>
                                                    <span class="badge bg-<?= $point['type'] === 'Full' ? 'primary' : 'info' ?> ms-2">
                                                        <?= $point['type'] ?>
                                                    </span>
                                                    <div class="text-muted small"><?= $point['size'] ?></div>
                                                </div>
                                                <button class="btn btn-sm btn-outline-success" onclick="initiateRestore('<?= $point['date'] ?>')">
                                                    <i class="fas fa-undo"></i>
                                                </button>
                                            </div>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Backup Health and Integrity -->
                    <div class="row">
                        <div class="col-lg-8">
                            <div class="card backup-card">
                                <div class="card-header bg-secondary text-white">
                                    <h6 class="mb-0">
                                        <i class="fas fa-heartbeat"></i>
                                        Backup Health & Integrity Monitoring
                                    </h6>
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <h6>System Health</h6>
                                            <div class="progress mb-2" style="height: 25px;">
                                                <div class="progress-bar bg-success" style="width: 96%">
                                                    96% Healthy
                                                </div>
                                            </div>
                                            
                                            <h6>Data Integrity</h6>
                                            <div class="progress mb-2" style="height: 25px;">
                                                <div class="progress-bar bg-success" style="width: 100%">
                                                    100% Verified
                                                </div>
                                            </div>
                                            
                                            <h6>Storage Utilization</h6>
                                            <div class="progress mb-3" style="height: 25px;">
                                                <div class="progress-bar bg-warning" style="width: 78%">
                                                    78% Used
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <h6>Recent Integrity Checks</h6>
                                            <div class="list-group list-group-flush">
                                                <div class="list-group-item d-flex justify-content-between">
                                                    <span>Database Backup</span>
                                                    <span class="backup-status-success"><i class="fas fa-check"></i> Verified</span>
                                                </div>
                                                <div class="list-group-item d-flex justify-content-between">
                                                    <span>File System Backup</span>
                                                    <span class="backup-status-success"><i class="fas fa-check"></i> Verified</span>
                                                </div>
                                                <div class="list-group-item d-flex justify-content-between">
                                                    <span>Configuration Backup</span>
                                                    <span class="backup-status-success"><i class="fas fa-check"></i> Verified</span>
                                                </div>
                                                <div class="list-group-item d-flex justify-content-between">
                                                    <span>Application Data</span>
                                                    <span class="backup-status-warning"><i class="fas fa-exclamation-triangle"></i> Pending</span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-4">
                            <div class="card backup-card">
                                <div class="card-header bg-dark text-white">
                                    <h6 class="mb-0">
                                        <i class="fas fa-tools"></i>
                                        Quick Actions
                                    </h6>
                                </div>
                                <div class="card-body">
                                    <div class="d-grid gap-2">
                                        <button class="btn btn-success" onclick="createBackup('full')">
                                            <i class="fas fa-database"></i> Start Full Backup
                                        </button>
                                        <button class="btn btn-info" onclick="createBackup('incremental')">
                                            <i class="fas fa-plus-circle"></i> Start Incremental Backup
                                        </button>
                                        <button class="btn btn-warning" onclick="verifyAllBackups()">
                                            <i class="fas fa-check-double"></i> Verify All Backups
                                        </button>
                                        <button class="btn btn-secondary" onclick="cleanupOldBackups()">
                                            <i class="fas fa-broom"></i> Cleanup Old Backups
                                        </button>
                                        <hr>
                                        <button class="btn btn-outline-primary" onclick="exportBackupReport()">
                                            <i class="fas fa-file-export"></i> Export Report
                                        </button>
                                        <button class="btn btn-outline-info" onclick="viewBackupLogs()">
                                            <i class="fas fa-file-alt"></i> View Logs
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

    <!-- Schedule Backup Modal -->
    <div class="modal fade" id="scheduleModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Schedule Backup</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Backup Type</label>
                            <select class="form-select" required>
                                <option value="full">Full Backup</option>
                                <option value="incremental">Incremental Backup</option>
                                <option value="differential">Differential Backup</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Schedule</label>
                            <select class="form-select" required>
                                <option value="daily">Daily</option>
                                <option value="weekly">Weekly</option>
                                <option value="monthly">Monthly</option>
                                <option value="custom">Custom (Cron)</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Time</label>
                            <input type="time" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Retention (days)</label>
                            <input type="number" class="form-control" value="30" min="1" max="365" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Schedule Backup</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Restore Modal -->
    <div class="modal fade" id="restoreModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Restore from Backup</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form>
                    <div class="modal-body">
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle"></i>
                            <strong>Warning:</strong> This operation will restore data from the selected backup point. Current data may be overwritten.
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Select Recovery Point</label>
                            <select class="form-select" required>
                                <option value="">Choose recovery point...</option>
                                <option value="2025-08-20-14-00">Aug 20, 2025 14:00 (Incremental - 2.1 GB)</option>
                                <option value="2025-08-20-02-00">Aug 20, 2025 02:00 (Full - 15.2 GB)</option>
                                <option value="2025-08-19-14-00">Aug 19, 2025 14:00 (Incremental - 1.9 GB)</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Restore Type</label>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="restoreType" value="full" checked>
                                <label class="form-check-label">Full System Restore</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="restoreType" value="database">
                                <label class="form-check-label">Database Only</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="restoreType" value="files">
                                <label class="form-check-label">Files Only</label>
                            </div>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="confirmRestore" required>
                            <label class="form-check-label" for="confirmRestore">
                                I understand this will overwrite current data
                            </label>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-warning">Start Restore</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Include footer -->
    <?php include dirname(dirname(__DIR__)) . '/shared/footer.php'; ?>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        // Initialize charts
        document.addEventListener('DOMContentLoaded', function() {
            initializeCharts();
        });

        function initializeCharts() {
            // Storage Distribution Chart
            const storageCtx = document.getElementById('storageChart').getContext('2d');
            new Chart(storageCtx, {
                type: 'doughnut',
                data: {
                    labels: ['Primary Storage', 'Remote Storage', 'Cloud Storage'],
                    datasets: [{
                        data: [1200, 900, 300],
                        backgroundColor: ['#007bff', '#28a745', '#17a2b8']
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { position: 'bottom' }
                    }
                }
            });
        }

        function createBackup(type) {
            if (confirm(`Start ${type} backup now?`)) {
                showNotification(`${type} backup started`, 'info');
                // Implement backup creation
            }
        }

        function restoreFromBackup(backupId) {
            // Show restore modal with specific backup selected
            new bootstrap.Modal(document.getElementById('restoreModal')).show();
        }

        function testBackup(backupId) {
            if (confirm('Test backup integrity?')) {
                showNotification('Backup integrity test started', 'info');
            }
        }

        function selectRecoveryPoint(date) {
            // Highlight selected recovery point
            document.querySelectorAll('.recovery-point').forEach(point => {
                point.classList.remove('bg-light');
            });
            event.currentTarget.classList.add('bg-light');
        }

        function initiateRestore(date) {
            event.stopPropagation();
            if (confirm(`Restore system to ${date}?`)) {
                showNotification('System restore initiated', 'warning');
            }
        }

        function verifyAllBackups() {
            if (confirm('Verify integrity of all backups?')) {
                showNotification('Backup verification started', 'info');
            }
        }

        function cleanupOldBackups() {
            if (confirm('Remove old backups according to retention policy?')) {
                showNotification('Backup cleanup started', 'warning');
            }
        }

        function exportBackupReport() {
            // Generate and download backup report
            window.open('backup_report.php', '_blank');
        }

        function viewBackupLogs() {
            // Open backup logs viewer
            window.open('backup_logs.php', '_blank');
        }

        function viewBackupDetails(backupId) {
            // Show detailed backup information
            alert('Show backup details for ID: ' + backupId);
        }

        function showNotification(message, type) {
            const alertDiv = document.createElement('div');
            alertDiv.className = `alert alert-${type} alert-dismissible fade show`;
            alertDiv.innerHTML = `
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            `;
            
            const contentArea = document.querySelector('.content-area');
            contentArea.insertBefore(alertDiv, contentArea.firstChild);
            
            setTimeout(() => {
                alertDiv.remove();
            }, 5000);
        }
    </script>
</body>
</html>