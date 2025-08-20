<?php
/**
 * ARMIS Advanced Reporting & Analytics Module
 * Custom report builder and advanced data visualization
 */

// Module constants
define('ARMIS_REPORTING', true);

// Include core files
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/shared/session_init.php';
require_once dirname(__DIR__) . '/shared/database_connection.php';
require_once dirname(__DIR__) . '/shared/rbac.php';
require_once __DIR__ . '/includes/reporting_service.php';

// Authentication and authorization
if (!isset($_SESSION['user_id']) || !isset($_SESSION['username'])) {
    header('Location: ' . ARMIS_BASE_URL . '/login.php');
    exit();
}

if (!hasModuleAccess('admin')) { // Reporting requires at least admin access
    header('Location: ' . ARMIS_BASE_URL . '/unauthorized.php?module=reporting');
    exit();
}

// Initialize services
$pdo = getDbConnection();
$reportingService = new ReportingService($pdo);

// Get reporting data
$reportingData = [
    'report_templates' => $reportingService->getReportTemplates(),
    'scheduled_reports' => $reportingService->getScheduledReports(),
    'recent_reports' => $reportingService->getRecentReports(),
    'analytics_summary' => $reportingService->getAnalyticsSummary()
];

$pageTitle = 'Advanced Reporting & Analytics';
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
                                <i class="fas fa-chart-bar text-primary"></i>
                                Advanced Reporting & Analytics
                            </h1>
                            <p class="text-muted mb-0">Custom Reports & Data Visualization</p>
                        </div>
                        <div class="btn-group" role="group">
                            <a href="create_report.php" class="btn btn-primary">
                                <i class="fas fa-plus"></i> Create Report
                            </a>
                            <a href="report_builder.php" class="btn btn-outline-primary">
                                <i class="fas fa-tools"></i> Report Builder
                            </a>
                            <a href="analytics_dashboard.php" class="btn btn-outline-info">
                                <i class="fas fa-chart-line"></i> Analytics Dashboard
                            </a>
                            <a href="../data-management/index.php" class="btn btn-outline-success">
                                <i class="fas fa-database"></i> Data Management
                            </a>
                        </div>
                    </div>

                    <!-- Analytics Summary Cards -->
                    <div class="row mb-4">
                        <div class="col-lg-3 col-md-6 mb-3">
                            <div class="card border-0 shadow-sm">
                                <div class="card-body">
                                    <div class="d-flex align-items-center">
                                        <div class="me-3">
                                            <div class="bg-primary bg-gradient rounded-circle p-3">
                                                <i class="fas fa-file-alt text-white"></i>
                                            </div>
                                        </div>
                                        <div>
                                            <h5 class="card-title mb-0"><?= count($reportingData['report_templates']) ?></h5>
                                            <p class="card-text text-muted">Report Templates</p>
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
                                                <i class="fas fa-clock text-white"></i>
                                            </div>
                                        </div>
                                        <div>
                                            <h5 class="card-title mb-0"><?= count(array_filter($reportingData['scheduled_reports'], function($r) { return $r['is_active']; })) ?></h5>
                                            <p class="card-text text-muted">Active Schedules</p>
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
                                                <i class="fas fa-chart-pie text-white"></i>
                                            </div>
                                        </div>
                                        <div>
                                            <h5 class="card-title mb-0"><?= $reportingData['analytics_summary']['total_data_points'] ?? 0 ?></h5>
                                            <p class="card-text text-muted">Data Points</p>
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
                                                <i class="fas fa-download text-white"></i>
                                            </div>
                                        </div>
                                        <div>
                                            <h5 class="card-title mb-0"><?= count($reportingData['recent_reports']) ?></h5>
                                            <p class="card-text text-muted">Recent Reports</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Main Content -->
                    <div class="row">
                        <div class="col-lg-8">
                            <!-- Report Templates -->
                            <div class="card border-0 shadow-sm mb-4">
                                <div class="card-header bg-primary bg-gradient text-white">
                                    <h6 class="mb-0">
                                        <i class="fas fa-layer-group"></i>
                                        Report Templates
                                    </h6>
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <?php if (empty($reportingData['report_templates'])): ?>
                                            <div class="col-12">
                                                <div class="text-center text-muted py-4">
                                                    <i class="fas fa-file-alt fa-3x mb-3"></i>
                                                    <p class="mb-0">No report templates available</p>
                                                    <a href="create_report.php" class="btn btn-outline-primary mt-2">
                                                        Create First Template
                                                    </a>
                                                </div>
                                            </div>
                                        <?php else: ?>
                                            <?php foreach ($reportingData['report_templates'] as $template): ?>
                                            <div class="col-md-6 col-lg-4 mb-3">
                                                <div class="card border report-template-card">
                                                    <div class="card-body">
                                                        <h6 class="card-title"><?= htmlspecialchars($template['name']) ?></h6>
                                                        <p class="card-text text-muted small">
                                                            <?= htmlspecialchars(substr($template['description'] ?? '', 0, 80)) ?>...
                                                        </p>
                                                        <div class="d-flex justify-content-between align-items-center">
                                                            <span class="badge bg-<?= $template['category'] === 'Personnel' ? 'primary' : ($template['category'] === 'Financial' ? 'success' : 'info') ?>">
                                                                <?= htmlspecialchars($template['category']) ?>
                                                            </span>
                                                            <div class="btn-group btn-group-sm">
                                                                <button class="btn btn-outline-primary" onclick="generateReport(<?= $template['id'] ?>)">
                                                                    <i class="fas fa-play"></i>
                                                                </button>
                                                                <button class="btn btn-outline-secondary" onclick="editTemplate(<?= $template['id'] ?>)">
                                                                    <i class="fas fa-edit"></i>
                                                                </button>
                                                                <button class="btn btn-outline-info" onclick="scheduleReport(<?= $template['id'] ?>)">
                                                                    <i class="fas fa-clock"></i>
                                                                </button>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>

                            <!-- Sample Analytics Dashboard -->
                            <div class="card border-0 shadow-sm">
                                <div class="card-header bg-info bg-gradient text-white">
                                    <h6 class="mb-0">
                                        <i class="fas fa-chart-line"></i>
                                        System Analytics Overview
                                    </h6>
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <canvas id="userActivityChart" width="400" height="200"></canvas>
                                        </div>
                                        <div class="col-md-6">
                                            <canvas id="moduleUsageChart" width="400" height="200"></canvas>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-lg-4">
                            <!-- Quick Report Generation -->
                            <div class="card border-0 shadow-sm mb-4">
                                <div class="card-header bg-success bg-gradient text-white">
                                    <h6 class="mb-0">
                                        <i class="fas fa-bolt"></i>
                                        Quick Reports
                                    </h6>
                                </div>
                                <div class="card-body">
                                    <div class="d-grid gap-2">
                                        <button class="btn btn-outline-primary" onclick="generateQuickReport('personnel_summary')">
                                            <i class="fas fa-users"></i> Personnel Summary
                                        </button>
                                        <button class="btn btn-outline-info" onclick="generateQuickReport('activity_log')">
                                            <i class="fas fa-list"></i> Activity Log
                                        </button>
                                        <button class="btn btn-outline-warning" onclick="generateQuickReport('system_health')">
                                            <i class="fas fa-heartbeat"></i> System Health
                                        </button>
                                        <button class="btn btn-outline-success" onclick="generateQuickReport('inventory_status')">
                                            <i class="fas fa-boxes"></i> Inventory Status
                                        </button>
                                        <button class="btn btn-outline-secondary" onclick="generateQuickReport('workflow_performance')">
                                            <i class="fas fa-tasks"></i> Workflow Performance
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <!-- Scheduled Reports -->
                            <div class="card border-0 shadow-sm mb-4">
                                <div class="card-header bg-warning bg-gradient text-white">
                                    <h6 class="mb-0">
                                        <i class="fas fa-calendar-alt"></i>
                                        Scheduled Reports
                                    </h6>
                                </div>
                                <div class="card-body">
                                    <?php if (empty($reportingData['scheduled_reports'])): ?>
                                        <p class="text-muted text-center mb-0">No scheduled reports</p>
                                    <?php else: ?>
                                        <?php foreach ($reportingData['scheduled_reports'] as $schedule): ?>
                                        <div class="d-flex justify-content-between align-items-center mb-2 p-2 border rounded">
                                            <div>
                                                <h6 class="mb-1 fs-6"><?= htmlspecialchars($schedule['name']) ?></h6>
                                                <small class="text-muted">
                                                    Next: <?= $schedule['next_run_at'] ? date('M j, g:i A', strtotime($schedule['next_run_at'])) : 'Not scheduled' ?>
                                                </small>
                                            </div>
                                            <span class="badge bg-<?= $schedule['is_active'] ? 'success' : 'secondary' ?>">
                                                <?= $schedule['is_active'] ? 'Active' : 'Inactive' ?>
                                            </span>
                                        </div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- Recent Reports -->
                            <div class="card border-0 shadow-sm">
                                <div class="card-header bg-secondary bg-gradient text-white">
                                    <h6 class="mb-0">
                                        <i class="fas fa-history"></i>
                                        Recent Reports
                                    </h6>
                                </div>
                                <div class="card-body">
                                    <?php if (empty($reportingData['recent_reports'])): ?>
                                        <p class="text-muted text-center mb-0">No recent reports</p>
                                    <?php else: ?>
                                        <div class="timeline">
                                            <?php foreach ($reportingData['recent_reports'] as $report): ?>
                                            <div class="timeline-item">
                                                <div class="d-flex justify-content-between align-items-start">
                                                    <div>
                                                        <h6 class="mb-1 fs-6"><?= htmlspecialchars($report['report_name']) ?></h6>
                                                        <small class="text-muted">
                                                            <?= date('M j, g:i A', strtotime($report['generated_at'])) ?>
                                                        </small>
                                                    </div>
                                                    <div class="btn-group btn-group-sm">
                                                        <button class="btn btn-outline-primary" onclick="viewReport(<?= $report['id'] ?>)">
                                                            <i class="fas fa-eye"></i>
                                                        </button>
                                                        <button class="btn btn-outline-success" onclick="downloadReport(<?= $report['id'] ?>)">
                                                            <i class="fas fa-download"></i>
                                                        </button>
                                                    </div>
                                                </div>
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
        // Initialize charts
        document.addEventListener('DOMContentLoaded', function() {
            initializeCharts();
        });

        function initializeCharts() {
            // User Activity Chart
            const userActivityCtx = document.getElementById('userActivityChart').getContext('2d');
            new Chart(userActivityCtx, {
                type: 'line',
                data: {
                    labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun'],
                    datasets: [{
                        label: 'Active Users',
                        data: [65, 75, 80, 85, 90, 95],
                        borderColor: '#007bff',
                        backgroundColor: 'rgba(0, 123, 255, 0.1)',
                        tension: 0.4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        title: {
                            display: true,
                            text: 'User Activity Trend'
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true
                        }
                    }
                }
            });

            // Module Usage Chart
            const moduleUsageCtx = document.getElementById('moduleUsageChart').getContext('2d');
            new Chart(moduleUsageCtx, {
                type: 'doughnut',
                data: {
                    labels: ['Admin Branch', 'Logistics', 'Workflow', 'Messaging', 'Finance'],
                    datasets: [{
                        data: [30, 25, 20, 15, 10],
                        backgroundColor: [
                            '#007bff',
                            '#28a745',
                            '#ffc107',
                            '#17a2b8',
                            '#6c757d'
                        ]
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        title: {
                            display: true,
                            text: 'Module Usage Distribution'
                        }
                    }
                }
            });
        }

        function generateReport(templateId) {
            window.open(`generate_report.php?template_id=${templateId}`, '_blank');
        }

        function editTemplate(templateId) {
            window.location.href = `edit_template.php?id=${templateId}`;
        }

        function scheduleReport(templateId) {
            window.location.href = `schedule_report.php?template_id=${templateId}`;
        }

        function generateQuickReport(reportType) {
            window.open(`quick_report.php?type=${reportType}`, '_blank');
        }

        function viewReport(reportId) {
            window.open(`view_report.php?id=${reportId}`, '_blank');
        }

        function downloadReport(reportId) {
            window.open(`download_report.php?id=${reportId}`, '_blank');
        }
    </script>
</body>
</html>