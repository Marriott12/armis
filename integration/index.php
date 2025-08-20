<?php
/**
 * ARMIS Integration & Data Management Module
 * Provides integration capabilities and data management tools
 */

// Module constants
define('ARMIS_INTEGRATION', true);

// Include core files
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/shared/session_init.php';
require_once dirname(__DIR__) . '/shared/database_connection.php';
require_once dirname(__DIR__) . '/shared/rbac.php';
require_once __DIR__ . '/includes/integration_service.php';

// Authentication and authorization
if (!isset($_SESSION['user_id']) || !isset($_SESSION['username'])) {
    header('Location: ' . ARMIS_BASE_URL . '/login.php');
    exit();
}

if (!hasModuleAccess('admin')) { // Integration requires admin access
    header('Location: ' . ARMIS_BASE_URL . '/unauthorized.php?module=integration');
    exit();
}

// Initialize services
$pdo = getDbConnection();
$integrationService = new IntegrationService($pdo);

// Get integration status and data
$integrationData = [
    'api_endpoints' => $integrationService->getApiEndpoints(),
    'sync_jobs' => $integrationService->getSyncJobs(),
    'recent_logs' => $integrationService->getRecentSyncLogs(),
    'system_status' => $integrationService->getIntegrationStatus()
];

$pageTitle = 'Integration & Data Management';
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
                <div class="content-area">
                    <!-- Page header -->
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <div>
                            <h1 class="h3 mb-0">
                                <i class="fas fa-exchange-alt text-primary"></i>
                                Integration & Data Management
                            </h1>
                            <p class="text-muted mb-0">System Integration & Data Synchronization</p>
                        </div>
                        <div class="btn-group" role="group">
                            <a href="create_endpoint.php" class="btn btn-primary">
                                <i class="fas fa-plus"></i> New API Endpoint
                            </a>
                            <a href="create_sync_job.php" class="btn btn-outline-primary">
                                <i class="fas fa-sync"></i> New Sync Job
                            </a>
                            <a href="export_data.php" class="btn btn-outline-info">
                                <i class="fas fa-download"></i> Export Data
                            </a>
                        </div>
                    </div>

                    <!-- Status Cards -->
                    <div class="row mb-4">
                        <div class="col-lg-3 col-md-6 mb-3">
                            <div class="card border-0 shadow-sm">
                                <div class="card-body">
                                    <div class="d-flex align-items-center">
                                        <div class="me-3">
                                            <div class="bg-primary bg-gradient rounded-circle p-3">
                                                <i class="fas fa-plug text-white"></i>
                                            </div>
                                        </div>
                                        <div>
                                            <h5 class="card-title mb-0"><?= count($integrationData['api_endpoints']) ?></h5>
                                            <p class="card-text text-muted">API Endpoints</p>
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
                                                <i class="fas fa-sync text-white"></i>
                                            </div>
                                        </div>
                                        <div>
                                            <h5 class="card-title mb-0"><?= count(array_filter($integrationData['sync_jobs'], function($job) { return $job['status'] === 'ACTIVE'; })) ?></h5>
                                            <p class="card-text text-muted">Active Sync Jobs</p>
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
                                                <i class="fas fa-cloud text-white"></i>
                                            </div>
                                        </div>
                                        <div>
                                            <h5 class="card-title mb-0"><?= $integrationData['system_status']['external_systems'] ?? 0 ?></h5>
                                            <p class="card-text text-muted">Connected Systems</p>
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
                                            <div class="bg-warning bg-gradient rounded-circle p-3">
                                                <i class="fas fa-exclamation-triangle text-white"></i>
                                            </div>
                                        </div>
                                        <div>
                                            <h5 class="card-title mb-0"><?= count(array_filter($integrationData['recent_logs'], function($log) { return $log['status'] === 'ERROR'; })) ?></h5>
                                            <p class="card-text text-muted">Recent Errors</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Main Content -->
                    <div class="row">
                        <div class="col-lg-8">
                            <!-- API Endpoints -->
                            <div class="card border-0 shadow-sm mb-4">
                                <div class="card-header bg-primary bg-gradient text-white">
                                    <h6 class="mb-0">
                                        <i class="fas fa-plug"></i>
                                        API Endpoints
                                    </h6>
                                </div>
                                <div class="card-body">
                                    <?php if (empty($integrationData['api_endpoints'])): ?>
                                        <div class="text-center text-muted py-4">
                                            <i class="fas fa-plug fa-3x mb-3"></i>
                                            <p class="mb-0">No API endpoints configured</p>
                                        </div>
                                    <?php else: ?>
                                        <div class="table-responsive">
                                            <table class="table table-sm">
                                                <thead>
                                                    <tr>
                                                        <th>Name</th>
                                                        <th>Method</th>
                                                        <th>URL</th>
                                                        <th>Status</th>
                                                        <th>Rate Limit</th>
                                                        <th>Actions</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($integrationData['api_endpoints'] as $endpoint): ?>
                                                    <tr>
                                                        <td><?= htmlspecialchars($endpoint['name']) ?></td>
                                                        <td>
                                                            <span class="badge bg-<?= $endpoint['method'] === 'GET' ? 'primary' : ($endpoint['method'] === 'POST' ? 'success' : 'secondary') ?>">
                                                                <?= $endpoint['method'] ?>
                                                            </span>
                                                        </td>
                                                        <td><code><?= htmlspecialchars($endpoint['endpoint_url']) ?></code></td>
                                                        <td>
                                                            <span class="badge bg-<?= $endpoint['is_active'] ? 'success' : 'secondary' ?>">
                                                                <?= $endpoint['is_active'] ? 'Active' : 'Inactive' ?>
                                                            </span>
                                                        </td>
                                                        <td><?= $endpoint['rate_limit_per_hour'] ?>/hr</td>
                                                        <td>
                                                            <div class="btn-group btn-group-sm">
                                                                <button class="btn btn-outline-primary" onclick="testEndpoint(<?= $endpoint['id'] ?>)">
                                                                    <i class="fas fa-play"></i>
                                                                </button>
                                                                <button class="btn btn-outline-secondary" onclick="editEndpoint(<?= $endpoint['id'] ?>)">
                                                                    <i class="fas fa-edit"></i>
                                                                </button>
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

                            <!-- Sync Jobs -->
                            <div class="card border-0 shadow-sm">
                                <div class="card-header bg-success bg-gradient text-white">
                                    <h6 class="mb-0">
                                        <i class="fas fa-sync"></i>
                                        Data Synchronization Jobs
                                    </h6>
                                </div>
                                <div class="card-body">
                                    <?php if (empty($integrationData['sync_jobs'])): ?>
                                        <div class="text-center text-muted py-4">
                                            <i class="fas fa-sync fa-3x mb-3"></i>
                                            <p class="mb-0">No sync jobs configured</p>
                                        </div>
                                    <?php else: ?>
                                        <div class="table-responsive">
                                            <table class="table table-sm">
                                                <thead>
                                                    <tr>
                                                        <th>Name</th>
                                                        <th>Source → Target</th>
                                                        <th>Type</th>
                                                        <th>Status</th>
                                                        <th>Last Run</th>
                                                        <th>Next Run</th>
                                                        <th>Actions</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($integrationData['sync_jobs'] as $job): ?>
                                                    <tr>
                                                        <td><?= htmlspecialchars($job['name']) ?></td>
                                                        <td>
                                                            <small>
                                                                <?= htmlspecialchars($job['source_system']) ?>
                                                                <i class="fas fa-arrow-right mx-1"></i>
                                                                <?= htmlspecialchars($job['target_system']) ?>
                                                            </small>
                                                        </td>
                                                        <td>
                                                            <span class="badge bg-info"><?= $job['sync_type'] ?></span>
                                                        </td>
                                                        <td>
                                                            <span class="badge bg-<?= $job['status'] === 'ACTIVE' ? 'success' : ($job['status'] === 'ERROR' ? 'danger' : 'secondary') ?>">
                                                                <?= $job['status'] ?>
                                                            </span>
                                                        </td>
                                                        <td>
                                                            <small><?= $job['last_run_at'] ? date('M j, g:i A', strtotime($job['last_run_at'])) : 'Never' ?></small>
                                                        </td>
                                                        <td>
                                                            <small><?= $job['next_run_at'] ? date('M j, g:i A', strtotime($job['next_run_at'])) : 'Not scheduled' ?></small>
                                                        </td>
                                                        <td>
                                                            <div class="btn-group btn-group-sm">
                                                                <button class="btn btn-outline-success" onclick="runSyncJob(<?= $job['id'] ?>)">
                                                                    <i class="fas fa-play"></i>
                                                                </button>
                                                                <button class="btn btn-outline-secondary" onclick="editSyncJob(<?= $job['id'] ?>)">
                                                                    <i class="fas fa-edit"></i>
                                                                </button>
                                                                <button class="btn btn-outline-info" onclick="viewSyncLogs(<?= $job['id'] ?>)">
                                                                    <i class="fas fa-list"></i>
                                                                </button>
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

                        <div class="col-lg-4">
                            <!-- Quick Actions -->
                            <div class="card border-0 shadow-sm mb-4">
                                <div class="card-header bg-warning bg-gradient text-white">
                                    <h6 class="mb-0">
                                        <i class="fas fa-bolt"></i>
                                        Quick Actions
                                    </h6>
                                </div>
                                <div class="card-body">
                                    <div class="d-grid gap-2">
                                        <a href="create_endpoint.php" class="btn btn-outline-primary">
                                            <i class="fas fa-plus"></i> Create API Endpoint
                                        </a>
                                        <a href="create_sync_job.php" class="btn btn-outline-success">
                                            <i class="fas fa-sync"></i> Create Sync Job
                                        </a>
                                        <a href="import_data.php" class="btn btn-outline-info">
                                            <i class="fas fa-upload"></i> Import Data
                                        </a>
                                        <a href="export_data.php" class="btn btn-outline-warning">
                                            <i class="fas fa-download"></i> Export Data
                                        </a>
                                        <a href="api_documentation.php" class="btn btn-outline-secondary">
                                            <i class="fas fa-book"></i> API Documentation
                                        </a>
                                    </div>
                                </div>
                            </div>

                            <!-- Recent Sync Logs -->
                            <div class="card border-0 shadow-sm">
                                <div class="card-header bg-info bg-gradient text-white">
                                    <h6 class="mb-0">
                                        <i class="fas fa-list-alt"></i>
                                        Recent Sync Logs
                                    </h6>
                                </div>
                                <div class="card-body">
                                    <?php if (empty($integrationData['recent_logs'])): ?>
                                        <p class="text-muted text-center mb-0">No recent sync activities</p>
                                    <?php else: ?>
                                        <div class="timeline">
                                            <?php foreach ($integrationData['recent_logs'] as $log): ?>
                                            <div class="timeline-item">
                                                <div class="d-flex justify-content-between align-items-start">
                                                    <div>
                                                        <span class="badge bg-<?= $log['status'] === 'SUCCESS' ? 'success' : ($log['status'] === 'ERROR' ? 'danger' : 'warning') ?>">
                                                            <?= $log['status'] ?>
                                                        </span>
                                                        <small class="d-block text-muted mt-1">
                                                            <?= date('M j, g:i A', strtotime($log['start_time'])) ?>
                                                        </small>
                                                    </div>
                                                    <small class="text-muted">
                                                        <?= $log['records_processed'] ?? 0 ?> records
                                                    </small>
                                                </div>
                                                <?php if ($log['error_message']): ?>
                                                <p class="mb-0 small text-danger mt-1">
                                                    <?= htmlspecialchars(substr($log['error_message'], 0, 100)) ?>...
                                                </p>
                                                <?php endif; ?>
                                            </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
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

    <script>
        function testEndpoint(endpointId) {
            console.log('Testing endpoint:', endpointId);
            // Implementation for testing API endpoint
        }

        function editEndpoint(endpointId) {
            window.location.href = `edit_endpoint.php?id=${endpointId}`;
        }

        function runSyncJob(jobId) {
            if (confirm('Are you sure you want to run this sync job now?')) {
                console.log('Running sync job:', jobId);
                // Implementation for running sync job
            }
        }

        function editSyncJob(jobId) {
            window.location.href = `edit_sync_job.php?id=${jobId}`;
        }

        function viewSyncLogs(jobId) {
            window.location.href = `sync_logs.php?job_id=${jobId}`;
        }
    </script>
</body>
</html>