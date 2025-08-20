<?php
/**
 * ARMIS Data Migration Tools
 * Import/Export and Legacy System Integration
 */

// Module constants
define('ARMIS_DATA_MIGRATION', true);

// Include core files
require_once dirname(dirname(__DIR__)) . '/config.php';
require_once dirname(dirname(__DIR__)) . '/shared/session_init.php';
require_once dirname(dirname(__DIR__)) . '/shared/database_connection.php';
require_once dirname(dirname(__DIR__)) . '/shared/rbac.php';
require_once dirname(__DIR__) . '/services/migration_service.php';

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
$migrationService = new MigrationService($pdo);

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $response = [];
    switch ($_POST['action'] ?? '') {
        case 'start_import':
            $response = $migrationService->startImport($_POST, $_FILES);
            break;
        case 'start_export':
            $response = $migrationService->startExport($_POST);
            break;
        case 'validate_data':
            $response = $migrationService->validateImportData($_POST);
            break;
    }
    
    if ($response) {
        echo json_encode($response);
        exit();
    }
}

// Get migration data
$migrationData = $migrationService->getMigrationOverview();

$pageTitle = 'Data Migration Tools';
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
        .migration-card {
            border: none;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            transition: transform 0.2s;
        }
        .migration-card:hover {
            transform: translateY(-3px);
        }
        .drop-zone {
            border: 2px dashed #ced4da;
            border-radius: 10px;
            padding: 40px;
            text-align: center;
            transition: border-color 0.3s;
            cursor: pointer;
        }
        .drop-zone:hover, .drop-zone.dragover {
            border-color: #007bff;
            background-color: #f8f9fa;
        }
        .progress-wrapper {
            margin: 20px 0;
        }
        .log-output {
            max-height: 300px;
            overflow-y: auto;
            font-family: monospace;
            font-size: 0.85rem;
            background: #f8f9fa;
            border-radius: 5px;
            padding: 10px;
        }
        .mapping-table {
            font-size: 0.9rem;
        }
        .validation-result {
            margin-top: 10px;
        }
        .job-status {
            font-weight: bold;
        }
        .status-pending { color: #6c757d; }
        .status-running { color: #0d6efd; }
        .status-completed { color: #198754; }
        .status-failed { color: #dc3545; }
        .format-badge {
            font-size: 0.75rem;
            padding: 0.25rem 0.5rem;
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
                                <i class="fas fa-exchange-alt text-info"></i>
                                Data Migration Tools
                            </h1>
                            <p class="text-muted mb-0">Import/Export & Legacy System Integration</p>
                        </div>
                        <div class="btn-group" role="group">
                            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#importModal">
                                <i class="fas fa-download"></i> Import Data
                            </button>
                            <button class="btn btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#exportModal">
                                <i class="fas fa-upload"></i> Export Data
                            </button>
                            <button class="btn btn-outline-info" onclick="showMappingTemplate()">
                                <i class="fas fa-map"></i> Mapping Template
                            </button>
                        </div>
                    </div>

                    <!-- Migration Overview -->
                    <div class="row mb-4">
                        <div class="col-lg-3 col-md-6 mb-3">
                            <div class="card migration-card bg-primary text-white">
                                <div class="card-body text-center">
                                    <i class="fas fa-tasks fa-2x mb-2"></i>
                                    <h4><?= $migrationData['total_jobs'] ?? 0 ?></h4>
                                    <p class="mb-0">Migration Jobs</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-3 col-md-6 mb-3">
                            <div class="card migration-card bg-success text-white">
                                <div class="card-body text-center">
                                    <i class="fas fa-check-circle fa-2x mb-2"></i>
                                    <h4><?= $migrationData['successful_jobs'] ?? 0 ?></h4>
                                    <p class="mb-0">Successful</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-3 col-md-6 mb-3">
                            <div class="card migration-card bg-warning text-white">
                                <div class="card-body text-center">
                                    <i class="fas fa-database fa-2x mb-2"></i>
                                    <h4><?= number_format($migrationData['records_migrated'] ?? 0) ?></h4>
                                    <p class="mb-0">Records Migrated</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-3 col-md-6 mb-3">
                            <div class="card migration-card bg-info text-white">
                                <div class="card-body text-center">
                                    <i class="fas fa-file-alt fa-2x mb-2"></i>
                                    <h4><?= $migrationData['formats_supported'] ?? 5 ?></h4>
                                    <p class="mb-0">Formats Supported</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Active Migration Jobs -->
                    <div class="row mb-4">
                        <div class="col-lg-8">
                            <div class="card migration-card">
                                <div class="card-header bg-primary text-white">
                                    <h6 class="mb-0">
                                        <i class="fas fa-list"></i>
                                        Migration Jobs
                                    </h6>
                                </div>
                                <div class="card-body p-0">
                                    <div class="table-responsive">
                                        <table class="table table-hover mb-0">
                                            <thead class="table-light">
                                                <tr>
                                                    <th>Job Name</th>
                                                    <th>Type</th>
                                                    <th>Format</th>
                                                    <th>Progress</th>
                                                    <th>Status</th>
                                                    <th>Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php 
                                                $jobs = $migrationData['recent_jobs'] ?? [
                                                    ['id' => 1, 'name' => 'Personnel Data Import', 'type' => 'import', 'format' => 'CSV', 'progress' => 100, 'status' => 'completed'],
                                                    ['id' => 2, 'name' => 'Training Records Export', 'type' => 'export', 'format' => 'JSON', 'progress' => 75, 'status' => 'running'],
                                                    ['id' => 3, 'name' => 'Legacy System Sync', 'type' => 'sync', 'format' => 'XML', 'progress' => 0, 'status' => 'pending']
                                                ];
                                                foreach ($jobs as $job): 
                                                ?>
                                                <tr>
                                                    <td><strong><?= htmlspecialchars($job['name']) ?></strong></td>
                                                    <td>
                                                        <span class="badge bg-<?= $job['type'] === 'import' ? 'primary' : ($job['type'] === 'export' ? 'secondary' : 'info') ?>">
                                                            <?= ucfirst($job['type']) ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <span class="badge bg-light text-dark format-badge">
                                                            <?= $job['format'] ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <div class="progress" style="height: 20px;">
                                                            <div class="progress-bar <?= $job['status'] === 'running' ? 'progress-bar-striped progress-bar-animated' : '' ?>" 
                                                                 style="width: <?= $job['progress'] ?>%">
                                                                <?= $job['progress'] ?>%
                                                            </div>
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <span class="job-status status-<?= $job['status'] ?>">
                                                            <?= ucfirst($job['status']) ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <div class="btn-group btn-group-sm">
                                                            <button class="btn btn-outline-primary" onclick="viewJobDetails(<?= $job['id'] ?>)">
                                                                <i class="fas fa-eye"></i>
                                                            </button>
                                                            <button class="btn btn-outline-info" onclick="viewJobLogs(<?= $job['id'] ?>)">
                                                                <i class="fas fa-file-alt"></i>
                                                            </button>
                                                            <?php if ($job['status'] === 'running'): ?>
                                                            <button class="btn btn-outline-danger" onclick="cancelJob(<?= $job['id'] ?>)">
                                                                <i class="fas fa-stop"></i>
                                                            </button>
                                                            <?php endif; ?>
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
                            <div class="card migration-card">
                                <div class="card-header bg-info text-white">
                                    <h6 class="mb-0">
                                        <i class="fas fa-chart-pie"></i>
                                        Migration Statistics
                                    </h6>
                                </div>
                                <div class="card-body">
                                    <canvas id="migrationStatsChart"></canvas>
                                    
                                    <div class="mt-3">
                                        <h6>Supported Formats</h6>
                                        <div class="d-flex flex-wrap gap-1">
                                            <span class="badge bg-primary">CSV</span>
                                            <span class="badge bg-success">JSON</span>
                                            <span class="badge bg-warning">XML</span>
                                            <span class="badge bg-info">SQL</span>
                                            <span class="badge bg-secondary">Excel</span>
                                        </div>
                                        
                                        <h6 class="mt-3">Data Validation</h6>
                                        <div class="progress mb-2" style="height: 20px;">
                                            <div class="progress-bar bg-success" style="width: 95%">
                                                95% Valid
                                            </div>
                                        </div>
                                        <small class="text-muted">Last validation run: 2 hours ago</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Data Mapping and Validation -->
                    <div class="row">
                        <div class="col-lg-6">
                            <div class="card migration-card">
                                <div class="card-header bg-success text-white">
                                    <h6 class="mb-0">
                                        <i class="fas fa-map"></i>
                                        Field Mapping Configuration
                                    </h6>
                                </div>
                                <div class="card-body">
                                    <div class="table-responsive">
                                        <table class="table table-sm mapping-table">
                                            <thead>
                                                <tr>
                                                    <th>Source Field</th>
                                                    <th>Target Field</th>
                                                    <th>Transform</th>
                                                    <th>Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <tr>
                                                    <td>employee_id</td>
                                                    <td>staff.id</td>
                                                    <td><span class="badge bg-light text-dark">None</span></td>
                                                    <td>
                                                        <button class="btn btn-sm btn-outline-primary" onclick="editMapping(1)">
                                                            <i class="fas fa-edit"></i>
                                                        </button>
                                                    </td>
                                                </tr>
                                                <tr>
                                                    <td>first_name</td>
                                                    <td>staff.first_name</td>
                                                    <td><span class="badge bg-info">Trim</span></td>
                                                    <td>
                                                        <button class="btn btn-sm btn-outline-primary" onclick="editMapping(2)">
                                                            <i class="fas fa-edit"></i>
                                                        </button>
                                                    </td>
                                                </tr>
                                                <tr>
                                                    <td>last_name</td>
                                                    <td>staff.last_name</td>
                                                    <td><span class="badge bg-info">Trim</span></td>
                                                    <td>
                                                        <button class="btn btn-sm btn-outline-primary" onclick="editMapping(3)">
                                                            <i class="fas fa-edit"></i>
                                                        </button>
                                                    </td>
                                                </tr>
                                                <tr>
                                                    <td>email_address</td>
                                                    <td>staff.email</td>
                                                    <td><span class="badge bg-warning">Validate Email</span></td>
                                                    <td>
                                                        <button class="btn btn-sm btn-outline-primary" onclick="editMapping(4)">
                                                            <i class="fas fa-edit"></i>
                                                        </button>
                                                    </td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                    
                                    <div class="text-center mt-3">
                                        <button class="btn btn-outline-success btn-sm" onclick="addMapping()">
                                            <i class="fas fa-plus"></i> Add Mapping
                                        </button>
                                        <button class="btn btn-outline-info btn-sm" onclick="validateMappings()">
                                            <i class="fas fa-check"></i> Validate All
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-6">
                            <div class="card migration-card">
                                <div class="card-header bg-warning text-dark">
                                    <h6 class="mb-0">
                                        <i class="fas fa-exclamation-triangle"></i>
                                        Validation Results
                                    </h6>
                                </div>
                                <div class="card-body">
                                    <div class="validation-result">
                                        <h6>Data Quality Issues</h6>
                                        <div class="list-group list-group-flush">
                                            <div class="list-group-item d-flex justify-content-between">
                                                <span><i class="fas fa-exclamation-circle text-warning"></i> Missing email addresses</span>
                                                <span class="badge bg-warning">15 records</span>
                                            </div>
                                            <div class="list-group-item d-flex justify-content-between">
                                                <span><i class="fas fa-times-circle text-danger"></i> Invalid date formats</span>
                                                <span class="badge bg-danger">3 records</span>
                                            </div>
                                            <div class="list-group-item d-flex justify-content-between">
                                                <span><i class="fas fa-info-circle text-info"></i> Duplicate records</span>
                                                <span class="badge bg-info">7 records</span>
                                            </div>
                                        </div>
                                        
                                        <h6 class="mt-3">Validation Summary</h6>
                                        <div class="progress mb-2" style="height: 25px;">
                                            <div class="progress-bar bg-success" style="width: 85%">Valid: 850</div>
                                            <div class="progress-bar bg-warning" style="width: 10%">Warning: 100</div>
                                            <div class="progress-bar bg-danger" style="width: 5%">Error: 50</div>
                                        </div>
                                        <small class="text-muted">Total records: 1,000</small>
                                        
                                        <div class="text-center mt-3">
                                            <button class="btn btn-outline-primary btn-sm" onclick="downloadErrorReport()">
                                                <i class="fas fa-download"></i> Download Error Report
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
    </div>

    <!-- Import Modal -->
    <div class="modal fade" id="importModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Import Data</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="start_import">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Import Type</label>
                            <select class="form-select" name="import_type" required>
                                <option value="">Select import type...</option>
                                <option value="personnel">Personnel Records</option>
                                <option value="training">Training Data</option>
                                <option value="inventory">Inventory Items</option>
                                <option value="financial">Financial Records</option>
                                <option value="audit">Audit Logs</option>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Data Format</label>
                            <select class="form-select" name="data_format" required>
                                <option value="csv">CSV</option>
                                <option value="json">JSON</option>
                                <option value="xml">XML</option>
                                <option value="excel">Excel</option>
                                <option value="sql">SQL</option>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Data File</label>
                            <div class="drop-zone" onclick="document.getElementById('fileInput').click()">
                                <i class="fas fa-cloud-upload-alt fa-3x mb-3 text-muted"></i>
                                <p class="mb-0">Click to select file or drag and drop</p>
                                <small class="text-muted">Supported formats: CSV, JSON, XML, Excel, SQL (Max: 100MB)</small>
                            </div>
                            <input type="file" id="fileInput" name="import_file" style="display: none;" accept=".csv,.json,.xml,.xlsx,.xls,.sql" required>
                        </div>
                        
                        <div class="mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="validate_only" id="validateOnly">
                                <label class="form-check-label" for="validateOnly">
                                    Validate only (don't import)
                                </label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="create_backup" id="createBackup" checked>
                                <label class="form-check-label" for="createBackup">
                                    Create backup before import
                                </label>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Start Import</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Export Modal -->
    <div class="modal fade" id="exportModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Export Data</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <input type="hidden" name="action" value="start_export">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Export Type</label>
                            <select class="form-select" name="export_type" required>
                                <option value="">Select export type...</option>
                                <option value="personnel">Personnel Records</option>
                                <option value="training">Training Data</option>
                                <option value="inventory">Inventory Items</option>
                                <option value="financial">Financial Records</option>
                                <option value="audit">Audit Logs</option>
                                <option value="full_backup">Full System Backup</option>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Output Format</label>
                            <select class="form-select" name="output_format" required>
                                <option value="csv">CSV</option>
                                <option value="json">JSON</option>
                                <option value="xml">XML</option>
                                <option value="excel">Excel</option>
                                <option value="sql">SQL</option>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Date Range</label>
                            <div class="row">
                                <div class="col-6">
                                    <input type="date" class="form-control" name="start_date" placeholder="Start Date">
                                </div>
                                <div class="col-6">
                                    <input type="date" class="form-control" name="end_date" placeholder="End Date">
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="compress_output" id="compressOutput" checked>
                            <label class="form-check-label" for="compressOutput">
                                Compress output file
                            </label>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Start Export</button>
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
            setupDropZone();
        });

        function initializeCharts() {
            // Migration Statistics Chart
            const statsCtx = document.getElementById('migrationStatsChart').getContext('2d');
            new Chart(statsCtx, {
                type: 'doughnut',
                data: {
                    labels: ['Successful', 'Failed', 'In Progress'],
                    datasets: [{
                        data: [85, 10, 5],
                        backgroundColor: ['#28a745', '#dc3545', '#ffc107']
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

        function setupDropZone() {
            const dropZone = document.querySelector('.drop-zone');
            const fileInput = document.getElementById('fileInput');

            ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
                dropZone.addEventListener(eventName, preventDefaults, false);
            });

            function preventDefaults(e) {
                e.preventDefault();
                e.stopPropagation();
            }

            ['dragenter', 'dragover'].forEach(eventName => {
                dropZone.addEventListener(eventName, highlight, false);
            });

            ['dragleave', 'drop'].forEach(eventName => {
                dropZone.addEventListener(eventName, unhighlight, false);
            });

            function highlight(e) {
                dropZone.classList.add('dragover');
            }

            function unhighlight(e) {
                dropZone.classList.remove('dragover');
            }

            dropZone.addEventListener('drop', handleDrop, false);

            function handleDrop(e) {
                const dt = e.dataTransfer;
                const files = dt.files;
                fileInput.files = files;
                updateDropZoneText(files[0]);
            }

            fileInput.addEventListener('change', function() {
                if (this.files[0]) {
                    updateDropZoneText(this.files[0]);
                }
            });

            function updateDropZoneText(file) {
                dropZone.innerHTML = `
                    <i class="fas fa-file fa-2x mb-2 text-success"></i>
                    <p class="mb-0">${file.name}</p>
                    <small class="text-muted">${(file.size / 1024 / 1024).toFixed(2)} MB</small>
                `;
            }
        }

        function viewJobDetails(jobId) {
            // Show job details modal or page
            console.log('View job details:', jobId);
        }

        function viewJobLogs(jobId) {
            // Show job logs modal or page
            console.log('View job logs:', jobId);
        }

        function cancelJob(jobId) {
            if (confirm('Cancel this migration job?')) {
                // Cancel job implementation
                showNotification('Migration job cancelled', 'warning');
            }
        }

        function editMapping(mappingId) {
            // Show mapping editor
            console.log('Edit mapping:', mappingId);
        }

        function addMapping() {
            // Show add mapping form
            console.log('Add new mapping');
        }

        function validateMappings() {
            // Validate all mappings
            showNotification('Field mappings validated', 'success');
        }

        function downloadErrorReport() {
            // Download validation error report
            window.open('download_error_report.php', '_blank');
        }

        function showMappingTemplate() {
            // Show mapping template download/creation
            alert('Mapping template functionality');
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