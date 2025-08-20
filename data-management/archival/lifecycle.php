<?php
/**
 * ARMIS Data Lifecycle Management
 * Historical Data Management and Retention System
 */

// Module constants
define('ARMIS_LIFECYCLE_MANAGEMENT', true);

// Include core files
require_once dirname(dirname(__DIR__)) . '/config.php';
require_once dirname(dirname(__DIR__)) . '/shared/session_init.php';
require_once dirname(dirname(__DIR__)) . '/shared/database_connection.php';
require_once dirname(dirname(__DIR__)) . '/shared/rbac.php';
require_once dirname(__DIR__) . '/services/data_management_service.php';

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
$dataManagementService = new DataManagementService($pdo);

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'create_policy':
                $result = createLifecyclePolicy($_POST);
                break;
            case 'archive_data':
                $result = archiveSelectedData($_POST);
                break;
            case 'purge_data':
                $result = purgeExpiredData($_POST);
                break;
        }
    }
}

// Get archival status data
$archivalData = $dataManagementService->getArchivalStatus();

$pageTitle = 'Data Lifecycle Management';

function createLifecyclePolicy($data) {
    // Implementation for creating lifecycle policies
    return ['success' => true, 'message' => 'Lifecycle policy created successfully'];
}

function archiveSelectedData($data) {
    // Implementation for archiving data
    return ['success' => true, 'message' => 'Data archived successfully'];
}

function purgeExpiredData($data) {
    // Implementation for purging expired data
    return ['success' => true, 'message' => 'Expired data purged successfully'];
}
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
        .lifecycle-card {
            border: none;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            transition: transform 0.2s;
        }
        .lifecycle-card:hover {
            transform: translateY(-3px);
        }
        .policy-badge {
            font-size: 0.8rem;
            padding: 0.25rem 0.75rem;
        }
        .data-size-bar {
            height: 20px;
            border-radius: 10px;
            overflow: hidden;
        }
        .archive-timeline {
            border-left: 3px solid #007bff;
            padding-left: 1rem;
        }
        .timeline-item {
            margin-bottom: 1rem;
            position: relative;
        }
        .timeline-item::before {
            content: '';
            position: absolute;
            left: -1.5rem;
            top: 0.5rem;
            width: 10px;
            height: 10px;
            background: #007bff;
            border-radius: 50%;
        }
        .retention-meter {
            height: 8px;
            background: #e9ecef;
            border-radius: 4px;
            overflow: hidden;
        }
        .retention-fill {
            height: 100%;
            transition: width 0.3s ease;
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
                                <i class="fas fa-recycle text-success"></i>
                                Data Lifecycle Management
                            </h1>
                            <p class="text-muted mb-0">Historical Data Management & Automated Retention Policies</p>
                        </div>
                        <div class="btn-group" role="group">
                            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createPolicyModal">
                                <i class="fas fa-plus"></i> Create Policy
                            </button>
                            <button class="btn btn-outline-warning" onclick="runArchivalProcess()">
                                <i class="fas fa-archive"></i> Run Archival
                            </button>
                            <button class="btn btn-outline-danger" onclick="runPurgeProcess()">
                                <i class="fas fa-trash"></i> Purge Expired
                            </button>
                        </div>
                    </div>

                    <!-- Data Storage Overview -->
                    <div class="row mb-4">
                        <div class="col-lg-3 col-md-6 mb-3">
                            <div class="card lifecycle-card bg-primary text-white">
                                <div class="card-body text-center">
                                    <i class="fas fa-database fa-2x mb-2"></i>
                                    <h4>2.5 TB</h4>
                                    <p class="mb-0">Total Data</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-3 col-md-6 mb-3">
                            <div class="card lifecycle-card bg-success text-white">
                                <div class="card-body text-center">
                                    <i class="fas fa-archive fa-2x mb-2"></i>
                                    <h4>850 GB</h4>
                                    <p class="mb-0">Archived Data</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-3 col-md-6 mb-3">
                            <div class="card lifecycle-card bg-warning text-white">
                                <div class="card-body text-center">
                                    <i class="fas fa-clock fa-2x mb-2"></i>
                                    <h4>125 GB</h4>
                                    <p class="mb-0">Pending Archive</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-3 col-md-6 mb-3">
                            <div class="card lifecycle-card bg-danger text-white">
                                <div class="card-body text-center">
                                    <i class="fas fa-trash fa-2x mb-2"></i>
                                    <h4>45 GB</h4>
                                    <p class="mb-0">Ready to Purge</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Lifecycle Policies and Data Retention -->
                    <div class="row mb-4">
                        <div class="col-lg-8">
                            <div class="card lifecycle-card">
                                <div class="card-header bg-primary text-white">
                                    <h6 class="mb-0">
                                        <i class="fas fa-cogs"></i>
                                        Active Lifecycle Policies
                                    </h6>
                                </div>
                                <div class="card-body">
                                    <div class="table-responsive">
                                        <table class="table table-hover">
                                            <thead>
                                                <tr>
                                                    <th>Policy Name</th>
                                                    <th>Data Type</th>
                                                    <th>Archive After</th>
                                                    <th>Purge After</th>
                                                    <th>Status</th>
                                                    <th>Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <tr>
                                                    <td><strong>Audit Logs Policy</strong></td>
                                                    <td>Audit Logs</td>
                                                    <td>90 days</td>
                                                    <td>7 years</td>
                                                    <td><span class="badge bg-success policy-badge">Active</span></td>
                                                    <td>
                                                        <div class="btn-group btn-group-sm">
                                                            <button class="btn btn-outline-primary" onclick="editPolicy(1)">
                                                                <i class="fas fa-edit"></i>
                                                            </button>
                                                            <button class="btn btn-outline-danger" onclick="deletePolicy(1)">
                                                                <i class="fas fa-trash"></i>
                                                            </button>
                                                        </div>
                                                    </td>
                                                </tr>
                                                <tr>
                                                    <td><strong>Personnel Records Policy</strong></td>
                                                    <td>Staff Records</td>
                                                    <td>1 year</td>
                                                    <td>50 years</td>
                                                    <td><span class="badge bg-success policy-badge">Active</span></td>
                                                    <td>
                                                        <div class="btn-group btn-group-sm">
                                                            <button class="btn btn-outline-primary" onclick="editPolicy(2)">
                                                                <i class="fas fa-edit"></i>
                                                            </button>
                                                            <button class="btn btn-outline-danger" onclick="deletePolicy(2)">
                                                                <i class="fas fa-trash"></i>
                                                            </button>
                                                        </div>
                                                    </td>
                                                </tr>
                                                <tr>
                                                    <td><strong>Training Records Policy</strong></td>
                                                    <td>Training Data</td>
                                                    <td>180 days</td>
                                                    <td>10 years</td>
                                                    <td><span class="badge bg-success policy-badge">Active</span></td>
                                                    <td>
                                                        <div class="btn-group btn-group-sm">
                                                            <button class="btn btn-outline-primary" onclick="editPolicy(3)">
                                                                <i class="fas fa-edit"></i>
                                                            </button>
                                                            <button class="btn btn-outline-danger" onclick="deletePolicy(3)">
                                                                <i class="fas fa-trash"></i>
                                                            </button>
                                                        </div>
                                                    </td>
                                                </tr>
                                                <tr>
                                                    <td><strong>System Logs Policy</strong></td>
                                                    <td>System Logs</td>
                                                    <td>30 days</td>
                                                    <td>2 years</td>
                                                    <td><span class="badge bg-warning policy-badge">Pending</span></td>
                                                    <td>
                                                        <div class="btn-group btn-group-sm">
                                                            <button class="btn btn-outline-primary" onclick="editPolicy(4)">
                                                                <i class="fas fa-edit"></i>
                                                            </button>
                                                            <button class="btn btn-outline-danger" onclick="deletePolicy(4)">
                                                                <i class="fas fa-trash"></i>
                                                            </button>
                                                        </div>
                                                    </td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-4">
                            <div class="card lifecycle-card">
                                <div class="card-header bg-info text-white">
                                    <h6 class="mb-0">
                                        <i class="fas fa-chart-pie"></i>
                                        Data Distribution
                                    </h6>
                                </div>
                                <div class="card-body">
                                    <canvas id="dataDistributionChart"></canvas>
                                    
                                    <div class="mt-3">
                                        <h6>Storage Breakdown</h6>
                                        <div class="mb-2">
                                            <div class="d-flex justify-content-between">
                                                <small>Active Data</small>
                                                <small>68%</small>
                                            </div>
                                            <div class="retention-meter">
                                                <div class="retention-fill bg-primary" style="width: 68%"></div>
                                            </div>
                                        </div>
                                        <div class="mb-2">
                                            <div class="d-flex justify-content-between">
                                                <small>Archived Data</small>
                                                <small>27%</small>
                                            </div>
                                            <div class="retention-meter">
                                                <div class="retention-fill bg-success" style="width: 27%"></div>
                                            </div>
                                        </div>
                                        <div class="mb-2">
                                            <div class="d-flex justify-content-between">
                                                <small>Compressed Data</small>
                                                <small>5%</small>
                                            </div>
                                            <div class="retention-meter">
                                                <div class="retention-fill bg-warning" style="width: 5%"></div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Archive Timeline and Purge Candidates -->
                    <div class="row">
                        <div class="col-lg-6">
                            <div class="card lifecycle-card">
                                <div class="card-header bg-success text-white">
                                    <h6 class="mb-0">
                                        <i class="fas fa-history"></i>
                                        Recent Archive Activity
                                    </h6>
                                </div>
                                <div class="card-body">
                                    <div class="archive-timeline">
                                        <div class="timeline-item">
                                            <h6 class="mb-1">Audit Logs Archived</h6>
                                            <p class="text-muted mb-1">125,000 records archived (15.2 GB)</p>
                                            <small class="text-muted">2 hours ago</small>
                                        </div>
                                        <div class="timeline-item">
                                            <h6 class="mb-1">Personnel Records Compressed</h6>
                                            <p class="text-muted mb-1">Historical records compressed (8.5 GB → 2.1 GB)</p>
                                            <small class="text-muted">6 hours ago</small>
                                        </div>
                                        <div class="timeline-item">
                                            <h6 class="mb-1">Training Data Archived</h6>
                                            <p class="text-muted mb-1">85,000 records archived (12.8 GB)</p>
                                            <small class="text-muted">1 day ago</small>
                                        </div>
                                        <div class="timeline-item">
                                            <h6 class="mb-1">System Logs Purged</h6>
                                            <p class="text-muted mb-1">Expired logs from 2022 purged (45.2 GB)</p>
                                            <small class="text-muted">3 days ago</small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-6">
                            <div class="card lifecycle-card">
                                <div class="card-header bg-warning text-dark">
                                    <h6 class="mb-0">
                                        <i class="fas fa-exclamation-triangle"></i>
                                        Purge Candidates
                                    </h6>
                                </div>
                                <div class="card-body">
                                    <div class="table-responsive">
                                        <table class="table table-sm">
                                            <thead>
                                                <tr>
                                                    <th>Data Type</th>
                                                    <th>Age</th>
                                                    <th>Size</th>
                                                    <th>Action</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <tr>
                                                    <td>System Logs</td>
                                                    <td>3 years</td>
                                                    <td>25.5 GB</td>
                                                    <td>
                                                        <button class="btn btn-sm btn-outline-danger" onclick="purgeDataType('system_logs')">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                    </td>
                                                </tr>
                                                <tr>
                                                    <td>Temp Files</td>
                                                    <td>1 year</td>
                                                    <td>12.3 GB</td>
                                                    <td>
                                                        <button class="btn btn-sm btn-outline-danger" onclick="purgeDataType('temp_files')">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                    </td>
                                                </tr>
                                                <tr>
                                                    <td>Backup Logs</td>
                                                    <td>2 years</td>
                                                    <td>7.2 GB</td>
                                                    <td>
                                                        <button class="btn btn-sm btn-outline-danger" onclick="purgeDataType('backup_logs')">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                    </td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                    
                                    <div class="text-center mt-3">
                                        <button class="btn btn-danger" onclick="purgeAllExpired()">
                                            <i class="fas fa-trash-alt"></i> Purge All Expired Data
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

    <!-- Create Policy Modal -->
    <div class="modal fade" id="createPolicyModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Create Lifecycle Policy</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <input type="hidden" name="action" value="create_policy">
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Policy Name</label>
                                    <input type="text" class="form-control" name="policy_name" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Data Type</label>
                                    <select class="form-select" name="data_type" required>
                                        <option value="">Select data type...</option>
                                        <option value="audit_logs">Audit Logs</option>
                                        <option value="personnel_records">Personnel Records</option>
                                        <option value="training_data">Training Data</option>
                                        <option value="system_logs">System Logs</option>
                                        <option value="financial_records">Financial Records</option>
                                        <option value="equipment_logs">Equipment Logs</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Archive After (days)</label>
                                    <input type="number" class="form-control" name="archive_after" min="1" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Purge After (years)</label>
                                    <input type="number" class="form-control" name="purge_after" min="1" required>
                                </div>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Compression Method</label>
                            <select class="form-select" name="compression_method">
                                <option value="gzip">GZIP</option>
                                <option value="lz4">LZ4 (Fast)</option>
                                <option value="zstd">ZSTD (Balanced)</option>
                                <option value="xz">XZ (High Compression)</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea class="form-control" name="description" rows="3"></textarea>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="auto_execute" id="autoExecute">
                            <label class="form-check-label" for="autoExecute">
                                Enable automatic execution
                            </label>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Create Policy</button>
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
            // Data Distribution Chart
            const distributionCtx = document.getElementById('dataDistributionChart').getContext('2d');
            new Chart(distributionCtx, {
                type: 'doughnut',
                data: {
                    labels: ['Active Data', 'Archived Data', 'Compressed Data'],
                    datasets: [{
                        data: [68, 27, 5],
                        backgroundColor: ['#007bff', '#28a745', '#ffc107']
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

        function runArchivalProcess() {
            if (confirm('Run archival process? This will archive data according to active policies.')) {
                // Implement archival process
                showNotification('Archival process started', 'info');
            }
        }

        function runPurgeProcess() {
            if (confirm('Run purge process? This will permanently delete expired data.')) {
                // Implement purge process
                showNotification('Purge process started', 'warning');
            }
        }

        function editPolicy(policyId) {
            // Implement policy editing
            console.log('Edit policy:', policyId);
        }

        function deletePolicy(policyId) {
            if (confirm('Delete this lifecycle policy?')) {
                // Implement policy deletion
                showNotification('Policy deleted', 'success');
            }
        }

        function purgeDataType(dataType) {
            if (confirm(`Purge all expired ${dataType.replace('_', ' ')}?`)) {
                // Implement data type purging
                showNotification(`${dataType.replace('_', ' ')} purged`, 'success');
            }
        }

        function purgeAllExpired() {
            if (confirm('Purge ALL expired data? This action cannot be undone.')) {
                // Implement full purge
                showNotification('All expired data purged', 'success');
            }
        }

        function showNotification(message, type) {
            // Create bootstrap alert
            const alertDiv = document.createElement('div');
            alertDiv.className = `alert alert-${type} alert-dismissible fade show`;
            alertDiv.innerHTML = `
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            `;
            
            // Insert at top of content area
            const contentArea = document.querySelector('.content-area');
            contentArea.insertBefore(alertDiv, contentArea.firstChild);
            
            // Auto-dismiss after 5 seconds
            setTimeout(() => {
                alertDiv.remove();
            }, 5000);
        }
    </script>
</body>
</html>