<?php
/**
 * ARMIS Data Management Expansions Module
 * Main dashboard for advanced data management capabilities
 */

// Module constants
define('ARMIS_DATA_MANAGEMENT', true);

// Include core files
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/shared/session_init.php';
require_once dirname(__DIR__) . '/shared/database_connection.php';
require_once dirname(__DIR__) . '/shared/rbac.php';
require_once __DIR__ . '/services/data_management_service.php';

// Authentication and authorization
if (!isset($_SESSION['user_id']) || !isset($_SESSION['username'])) {
    header('Location: ' . ARMIS_BASE_URL . '/login.php');
    exit();
}

if (!hasModuleAccess('admin')) { // Data management requires admin access
    header('Location: ' . ARMIS_BASE_URL . '/unauthorized.php?module=data_management');
    exit();
}

// Initialize services
$pdo = getDbConnection();
$dataManagementService = new DataManagementService($pdo);

// Get data management summary
$summary = $dataManagementService->getDataManagementSummary();

$pageTitle = 'Data Management Expansions';
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
        .data-management-card {
            transition: transform 0.2s, box-shadow 0.2s;
            border: none;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .data-management-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.15);
        }
        .metric-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 10px;
        }
        .module-icon {
            font-size: 2.5rem;
            margin-bottom: 1rem;
        }
    </style>
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
                <div class="content-area p-4">
                    <!-- Page header -->
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <div>
                            <h1 class="h3 mb-0">
                                <i class="fas fa-database text-primary"></i>
                                Data Management Expansions
                            </h1>
                            <p class="text-muted mb-0">Advanced Data Handling, Analytics, Reporting & Archival</p>
                        </div>
                    </div>

                    <!-- Summary Cards -->
                    <div class="row mb-4">
                        <div class="col-lg-3 col-md-6 mb-3">
                            <div class="card metric-card">
                                <div class="card-body text-center">
                                    <i class="fas fa-chart-line module-icon"></i>
                                    <h4><?= $summary['total_reports'] ?? 0 ?></h4>
                                    <p class="mb-0">Active Reports</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-3 col-md-6 mb-3">
                            <div class="card metric-card">
                                <div class="card-body text-center">
                                    <i class="fas fa-archive module-icon"></i>
                                    <h4><?= $summary['archived_records'] ?? 0 ?></h4>
                                    <p class="mb-0">Archived Records</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-3 col-md-6 mb-3">
                            <div class="card metric-card">
                                <div class="card-body text-center">
                                    <i class="fas fa-hdd module-icon"></i>
                                    <h4><?= $summary['backup_size_gb'] ?? 0 ?>GB</h4>
                                    <p class="mb-0">Backup Storage</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-3 col-md-6 mb-3">
                            <div class="card metric-card">
                                <div class="card-body text-center">
                                    <i class="fas fa-exchange-alt module-icon"></i>
                                    <h4><?= $summary['sync_jobs'] ?? 0 ?></h4>
                                    <p class="mb-0">Active Sync Jobs</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Module Access Cards -->
                    <div class="row">
                        <!-- Advanced Reporting -->
                        <div class="col-lg-6 col-md-6 mb-4">
                            <div class="card data-management-card h-100">
                                <div class="card-body">
                                    <div class="d-flex align-items-center mb-3">
                                        <div class="me-3">
                                            <div class="bg-primary bg-gradient rounded-circle p-3">
                                                <i class="fas fa-chart-bar text-white fa-2x"></i>
                                            </div>
                                        </div>
                                        <div>
                                            <h5 class="card-title mb-0">Advanced Reporting</h5>
                                            <p class="text-muted mb-0">Executive Dashboards & Analytics</p>
                                        </div>
                                    </div>
                                    <p class="card-text">
                                        Access executive dashboards, operational intelligence, compliance reporting, 
                                        and performance analytics for comprehensive data insights.
                                    </p>
                                    <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                        <a href="reporting/executive.php" class="btn btn-outline-primary">
                                            <i class="fas fa-tachometer-alt"></i> Executive Dashboard
                                        </a>
                                        <a href="reporting/operational.php" class="btn btn-outline-info">
                                            <i class="fas fa-satellite-dish"></i> Operations Center
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Data Archival -->
                        <div class="col-lg-6 col-md-6 mb-4">
                            <div class="card data-management-card h-100">
                                <div class="card-body">
                                    <div class="d-flex align-items-center mb-3">
                                        <div class="me-3">
                                            <div class="bg-success bg-gradient rounded-circle p-3">
                                                <i class="fas fa-archive text-white fa-2x"></i>
                                            </div>
                                        </div>
                                        <div>
                                            <h5 class="card-title mb-0">Data Archival System</h5>
                                            <p class="text-muted mb-0">Lifecycle & Storage Management</p>
                                        </div>
                                    </div>
                                    <p class="card-text">
                                        Manage data lifecycle, historical data retention, automated backup systems, 
                                        and data warehousing for optimal storage and retrieval.
                                    </p>
                                    <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                        <a href="archival/lifecycle.php" class="btn btn-outline-success">
                                            <i class="fas fa-recycle"></i> Lifecycle Mgmt
                                        </a>
                                        <a href="archival/backup.php" class="btn btn-outline-warning">
                                            <i class="fas fa-shield-alt"></i> Backup & Recovery
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Data Migration -->
                        <div class="col-lg-6 col-md-6 mb-4">
                            <div class="card data-management-card h-100">
                                <div class="card-body">
                                    <div class="d-flex align-items-center mb-3">
                                        <div class="me-3">
                                            <div class="bg-info bg-gradient rounded-circle p-3">
                                                <i class="fas fa-exchange-alt text-white fa-2x"></i>
                                            </div>
                                        </div>
                                        <div>
                                            <h5 class="card-title mb-0">Data Migration Tools</h5>
                                            <p class="text-muted mb-0">Legacy System Integration</p>
                                        </div>
                                    </div>
                                    <p class="card-text">
                                        Import/export data from legacy systems, validate data integrity, 
                                        and manage batch processing for seamless data migration.
                                    </p>
                                    <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                        <a href="migration/import.php" class="btn btn-outline-info">
                                            <i class="fas fa-download"></i> Import Data
                                        </a>
                                        <a href="migration/export.php" class="btn btn-outline-secondary">
                                            <i class="fas fa-upload"></i> Export Data
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Data Warehousing -->
                        <div class="col-lg-6 col-md-6 mb-4">
                            <div class="card data-management-card h-100">
                                <div class="card-body">
                                    <div class="d-flex align-items-center mb-3">
                                        <div class="me-3">
                                            <div class="bg-warning bg-gradient rounded-circle p-3">
                                                <i class="fas fa-warehouse text-white fa-2x"></i>
                                            </div>
                                        </div>
                                        <div>
                                            <h5 class="card-title mb-0">Data Warehousing</h5>
                                            <p class="text-muted mb-0">Strategic Data Storage</p>
                                        </div>
                                    </div>
                                    <p class="card-text">
                                        Access data warehouse, create data marts, manage ETL processes, 
                                        and optimize queries for high-performance analytics.
                                    </p>
                                    <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                        <a href="warehouse/dashboard.php" class="btn btn-outline-warning">
                                            <i class="fas fa-database"></i> Data Warehouse
                                        </a>
                                        <a href="warehouse/etl.php" class="btn btn-outline-dark">
                                            <i class="fas fa-cogs"></i> ETL Processes
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Recent Activity -->
                    <div class="row mt-4">
                        <div class="col-12">
                            <div class="card">
                                <div class="card-header bg-light">
                                    <h6 class="mb-0">
                                        <i class="fas fa-clock"></i>
                                        Recent Data Management Activity
                                    </h6>
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <h6>Recent Reports Generated</h6>
                                            <div class="list-group list-group-flush">
                                                <?php foreach ($summary['recent_reports'] ?? [] as $report): ?>
                                                <div class="list-group-item d-flex justify-content-between">
                                                    <span><?= htmlspecialchars($report['name']) ?></span>
                                                    <small class="text-muted"><?= $report['created_at'] ?></small>
                                                </div>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <h6>Recent Backup Operations</h6>
                                            <div class="list-group list-group-flush">
                                                <?php foreach ($summary['recent_backups'] ?? [] as $backup): ?>
                                                <div class="list-group-item d-flex justify-content-between">
                                                    <span><?= htmlspecialchars($backup['type']) ?></span>
                                                    <small class="text-muted"><?= $backup['completed_at'] ?></small>
                                                </div>
                                                <?php endforeach; ?>
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
    </div>

    <!-- Include footer -->
    <?php include dirname(__DIR__) . '/shared/footer.php'; ?>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>