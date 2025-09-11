<?php
/**
 * Enhanced Dashboard Page
 * 
 * Showcases all the advanced dashboard features including:
 * - Real-time data visualization
 * - Widget state persistence
 * - Notifications
 * - Advanced analytics
 * - Data export capabilities
 */

// Define module constants
define('ARMIS_ADMIN_BRANCH', true);

// Include required files
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/shared/session_init.php';
require_once dirname(__DIR__) . '/shared/database_connection.php';
require_once __DIR__ . '/includes/auth.php';

// Require authentication
requireAuth();
requireModuleAccess('admin_branch');

// Get current user info
$user = getCurrentUser();

// Generate CSRF Token for forms
$csrfToken = generateCSRFToken();

// Get database connection
$pdo = getDbConnection();

// Initialize DashboardService
require_once __DIR__ . '/includes/dashboard_service.php';
$dashboardService = new DashboardService($pdo);

// Get initial dashboard data
$kpiData = $dashboardService->getKPIData();
$personnelDistribution = $dashboardService->getPersonnelDistribution();
$recruitmentTrends = $dashboardService->getRecruitmentTrends();
$performanceMetrics = $dashboardService->getPerformanceMetrics();
$recentActivities = $dashboardService->getRecentActivities(5);
$rankDistribution = $dashboardService->getRankDistribution();

// Page title
$pageTitle = 'Enhanced Dashboard';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?php echo $csrfToken; ?>">
    <title><?php echo $pageTitle; ?> | ARMIS Admin</title>
    
    <!-- Bootstrap CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.0/dist/css/bootstrap.min.css">
    <!-- FontAwesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <!-- Dashboard Styles -->
    <link rel="stylesheet" href="css/dashboard.css">
    <!-- Enhanced Dashboard Styles -->
    <link rel="stylesheet" href="css/dashboard_enhanced.css">
</head>
<body>
    <!-- Top Navigation -->
    <?php include 'includes/header.php'; ?>
    
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar Navigation -->
            <?php include 'includes/sidebar.php'; ?>
            
            <!-- Main Content -->
            <main class="col-md-9 ml-sm-auto col-lg-10 px-md-4 py-4">
                <!-- Page Header -->
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2"><?php echo $pageTitle; ?></h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <div class="dropdown mr-2">
                            <button class="btn btn-sm btn-outline-secondary dropdown-toggle" id="export-dropdown" data-toggle="dropdown" aria-expanded="false">
                                <i class="fas fa-download mr-1"></i> Export
                            </button>
                            <div class="dropdown-menu dropdown-menu-right" aria-labelledby="export-dropdown">
                                <button class="dropdown-item export-button" data-format="csv" data-type="personnel">
                                    <i class="far fa-file-csv mr-1"></i> Export as CSV
                                </button>
                                <button class="dropdown-item export-button" data-format="excel" data-type="personnel">
                                    <i class="far fa-file-excel mr-1"></i> Export as Excel
                                </button>
                                <button class="dropdown-item export-button" data-format="json" data-type="personnel">
                                    <i class="far fa-file-code mr-1"></i> Export as JSON
                                </button>
                                <div class="dropdown-divider"></div>
                                <button class="dropdown-item export-button" data-format="csv" data-type="activities">
                                    <i class="far fa-file-alt mr-1"></i> Export Activity Log
                                </button>
                            </div>
                        </div>
                        <button type="button" class="btn btn-sm btn-outline-secondary" id="notification-toggle">
                            <i class="fas fa-bell"></i>
                            <span class="notification-badge d-none">0</span>
                        </button>
                    </div>
                </div>
                
                <!-- Notification Panel - DISABLED -->
                <!-- All notifications have been removed from the system -->
                
                <!-- Key Performance Indicators -->
                <div class="row">
                    <?php foreach ($kpiData as $kpi): ?>
                    <div class="col-md-3 col-sm-6 mb-4">
                        <div class="dashboard-widget" data-widget-id="kpi-<?php echo strtolower(str_replace(' ', '-', $kpi['label'])); ?>" data-widget-type="kpi" data-refresh-endpoint="dashboard_api.php?action=get_kpi" data-auto-refresh="true" data-refresh-interval="60000">
                            <div class="widget-content">
                                <div class="kpi-widget">
                                    <div class="kpi-icon">
                                        <i class="fas <?php echo $kpi['icon']; ?>"></i>
                                    </div>
                                    <div class="kpi-value"><?php echo $kpi['value']; ?></div>
                                    <div class="kpi-label"><?php echo $kpi['label']; ?></div>
                                    <?php if (isset($kpi['change'])): ?>
                                    <div class="kpi-change <?php echo $kpi['change'] >= 0 ? 'text-success' : 'text-danger'; ?>" data-display-option="showChange">
                                        <i class="fas <?php echo $kpi['change'] >= 0 ? 'fa-arrow-up' : 'fa-arrow-down'; ?>"></i>
                                        <?php echo abs($kpi['change']); ?>%
                                    </div>
                                    <?php endif; ?>
                                    <?php if (isset($kpi['trend'])): ?>
                                    <div class="kpi-sparkline" data-trend="<?php echo htmlspecialchars(json_encode($kpi['trend'])); ?>" data-display-option="showTrend"></div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="widget-footer">
                                <span class="widget-last-refreshed">Last updated: <?php echo date('H:i:s'); ?></span>
                                <div class="widget-actions">
                                    <button class="btn btn-sm btn-link widget-refresh" title="Refresh">
                                        <i class="fas fa-sync-alt"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                
                <!-- Dashboard Grid System -->
                <div class="dashboard-grid">
                    <!-- Personnel Distribution Chart -->
                    <div class="dashboard-widget grid-item-2x1" data-widget-id="personnel-distribution" data-widget-type="chart" data-refresh-endpoint="dashboard_api.php?action=get_personnel_distribution" data-auto-refresh="true" data-refresh-interval="300000">
                        <div class="widget-header">
                            <h2 class="widget-title">Personnel Distribution</h2>
                            <div class="widget-actions">
                                <button class="btn btn-sm btn-link widget-refresh" title="Refresh">
                                    <i class="fas fa-sync-alt"></i>
                                </button>
                                <button class="btn btn-sm btn-link widget-settings" title="Settings">
                                    <i class="fas fa-cog"></i>
                                </button>
                            </div>
                        </div>
                        <div class="widget-content">
                            <canvas id="personnelDistributionChart" height="250"></canvas>
                        </div>
                        <div class="widget-footer">
                            <span class="widget-last-refreshed">Last updated: <?php echo date('H:i:s'); ?></span>
                            <a href="#" class="view-details-link">View Details</a>
                        </div>
                    </div>
                    
                    <!-- Recruitment Trends Chart -->
                    <div class="dashboard-widget grid-item-2x1" data-widget-id="recruitment-trends" data-widget-type="chart" data-refresh-endpoint="dashboard_api.php?action=get_recruitment_trends" data-auto-refresh="true" data-refresh-interval="300000">
                        <div class="widget-header">
                            <h2 class="widget-title">Recruitment Trends</h2>
                            <div class="widget-actions">
                                <button class="btn btn-sm btn-link widget-refresh" title="Refresh">
                                    <i class="fas fa-sync-alt"></i>
                                </button>
                                <button class="btn btn-sm btn-link widget-settings" title="Settings">
                                    <i class="fas fa-cog"></i>
                                </button>
                            </div>
                        </div>
                        <div class="widget-content">
                            <canvas id="recruitmentTrendsChart" height="250"></canvas>
                        </div>
                        <div class="widget-footer">
                            <span class="widget-last-refreshed">Last updated: <?php echo date('H:i:s'); ?></span>
                            <a href="#" class="view-details-link">View Details</a>
                        </div>
                    </div>
                    
                    <!-- Rank Distribution Chart -->
                    <div class="dashboard-widget grid-item-1x1" data-widget-id="rank-distribution" data-widget-type="chart" data-refresh-endpoint="dashboard_api.php?action=get_rank_distribution" data-auto-refresh="true" data-refresh-interval="300000">
                        <div class="widget-header">
                            <h2 class="widget-title">Rank Distribution</h2>
                            <div class="widget-actions">
                                <button class="btn btn-sm btn-link widget-refresh" title="Refresh">
                                    <i class="fas fa-sync-alt"></i>
                                </button>
                                <button class="btn btn-sm btn-link widget-settings" title="Settings">
                                    <i class="fas fa-cog"></i>
                                </button>
                            </div>
                        </div>
                        <div class="widget-content">
                            <canvas id="rankDistributionChart" height="250"></canvas>
                        </div>
                        <div class="widget-footer">
                            <span class="widget-last-refreshed">Last updated: <?php echo date('H:i:s'); ?></span>
                            <a href="#" class="view-details-link">View Details</a>
                        </div>
                    </div>
                    
                    <!-- Recent Activities -->
                    <div class="dashboard-widget grid-item-1x1" data-widget-id="recent-activities" data-widget-type="table" data-refresh-endpoint="dashboard_api.php?action=get_recent_activities" data-auto-refresh="true" data-refresh-interval="60000">
                        <div class="widget-header">
                            <h2 class="widget-title">Recent Activities</h2>
                            <div class="widget-actions">
                                <button class="btn btn-sm btn-link widget-refresh" title="Refresh">
                                    <i class="fas fa-sync-alt"></i>
                                </button>
                                <button class="btn btn-sm btn-link widget-settings" title="Settings">
                                    <i class="fas fa-cog"></i>
                                </button>
                            </div>
                        </div>
                        <div class="widget-content">
                            <div class="table-responsive">
                                <table class="table table-sm">
                                    <thead>
                                        <tr>
                                            <th>Activity</th>
                                            <th>User</th>
                                            <th>Time</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($recentActivities as $activity): ?>
                                        <tr>
                                            <td><?php echo $activity['action']; ?></td>
                                            <td><?php echo $activity['user']; ?></td>
                                            <td><?php echo $activity['time']; ?></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <div class="widget-footer">
                            <span class="widget-last-refreshed">Last updated: <?php echo date('H:i:s'); ?></span>
                            <a href="#" class="view-details-link">View All</a>
                        </div>
                    </div>
                    
                    <!-- Performance Metrics -->
                    <div class="dashboard-widget grid-item-2x1" data-widget-id="performance-metrics" data-widget-type="chart" data-refresh-endpoint="dashboard_api.php?action=get_performance_metrics" data-auto-refresh="true" data-refresh-interval="300000">
                        <div class="widget-header">
                            <h2 class="widget-title">Performance Metrics</h2>
                            <div class="widget-actions">
                                <button class="btn btn-sm btn-link widget-refresh" title="Refresh">
                                    <i class="fas fa-sync-alt"></i>
                                </button>
                                <button class="btn btn-sm btn-link widget-settings" title="Settings">
                                    <i class="fas fa-cog"></i>
                                </button>
                            </div>
                        </div>
                        <div class="widget-content">
                            <canvas id="performanceMetricsChart" height="250"></canvas>
                        </div>
                        <div class="widget-footer">
                            <span class="widget-last-refreshed">Last updated: <?php echo date('H:i:s'); ?></span>
                            <a href="#" class="view-details-link">View Details</a>
                        </div>
                    </div>
                </div>
                
                <!-- Advanced Analytics Section -->
                <div class="card mt-4">
                    <div class="card-header">
                        <h3 class="card-title">Advanced Analytics</h3>
                    </div>
                    <div class="card-body">
                        <!-- Analytics Tabs -->
                        <div class="analytics-tabs">
                            <div class="analytics-tab active" data-tab="predictive">Predictive Attrition</div>
                            <div class="analytics-tab" data-tab="training">Training Completion</div>
                            <div class="analytics-tab" data-tab="cohort">Cohort Analysis</div>
                        </div>
                        
                        <!-- Analytics Content -->
                        <div id="analytics-predictive" class="analytics-content active">
                            <!-- Will be loaded via AJAX -->
                            <div class="text-center p-4">
                                <div class="spinner-border text-primary" role="status"></div>
                                <p class="mt-2">Loading predictive analytics...</p>
                            </div>
                        </div>
                        
                        <div id="analytics-training" class="analytics-content">
                            <!-- Will be loaded via AJAX -->
                        </div>
                        
                        <div id="analytics-cohort" class="analytics-content">
                            <!-- Will be loaded via AJAX -->
                        </div>
                    </div>
                </div>
                
                <!-- Personnel Data Table with Pagination and Filtering -->
                <div class="card mt-4">
                    <div class="card-header">
                        <h3 class="card-title">Personnel Database</h3>
                        <div class="float-right">
                            <button class="btn btn-sm btn-outline-primary export-button" data-format="csv" data-type="personnel">
                                <i class="fas fa-download mr-1"></i> Export
                            </button>
                        </div>
                    </div>
                    <div class="card-body">
                        <!-- Search and Filters -->
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <div class="input-group">
                                    <input type="text" class="form-control" id="personnel-search" placeholder="Search personnel...">
                                    <div class="input-group-append">
                                        <button class="btn btn-outline-secondary" type="button">
                                            <i class="fas fa-search"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <select class="form-control" id="rank-filter">
                                    <option value="">All Ranks</option>
                                    <?php foreach ($rankDistribution as $rank): ?>
                                    <option value="<?php echo $rank['rank']; ?>"><?php echo $rank['rank']; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <select class="form-control" id="category-filter">
                                    <option value="">All Categories</option>
                                    <?php foreach ($personnelDistribution as $category): ?>
                                    <option value="<?php echo $category['category']; ?>"><?php echo $category['category']; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        
                        <!-- Personnel Table -->
                        <div class="table-responsive">
                            <table class="table table-striped table-hover" id="personnel-table">
                                <thead>
                                    <tr>
                                        <th>Name</th>
                                        <th>Rank</th>
                                        <th>Service Number</th>
                                        <th>Category</th>
                                        <th>Unit</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <!-- Will be loaded via AJAX -->
                                    <tr>
                                        <td colspan="6" class="text-center">
                                            <div class="spinner-border text-primary" role="status"></div>
                                            <p class="mt-2">Loading personnel data...</p>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        
                        <!-- Pagination Controls -->
                        <div class="d-flex justify-content-between align-items-center mt-3">
                            <div class="pagination-info">
                                Showing <span id="showing-start">1</span> to <span id="showing-end">10</span> of <span id="total-records">0</span> records
                            </div>
                            <div>
                                <select class="form-control form-control-sm d-inline-block mr-2" style="width: auto;" id="page-size">
                                    <option value="10">10 rows</option>
                                    <option value="25">25 rows</option>
                                    <option value="50">50 rows</option>
                                    <option value="100">100 rows</option>
                                </select>
                                <nav aria-label="Personnel pagination">
                                    <ul class="pagination pagination-sm mb-0" id="pagination-controls">
                                        <li class="page-item disabled">
                                            <a class="page-link" href="#" aria-label="Previous">
                                                <span aria-hidden="true">&laquo;</span>
                                            </a>
                                        </li>
                                        <li class="page-item active"><a class="page-link" href="#">1</a></li>
                                        <li class="page-item disabled">
                                            <a class="page-link" href="#" aria-label="Next">
                                                <span aria-hidden="true">&raquo;</span>
                                            </a>
                                        </li>
                                    </ul>
                                </nav>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Footer -->
                <?php include 'includes/footer.php'; ?>
            </main>
        </div>
    </div>
    
    <!-- JavaScript Libraries -->
    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@2.9.4/dist/Chart.min.js"></script>
    
    <!-- Dashboard Scripts -->
    <script src="js/dashboard.js"></script>
    <script src="js/dashboard_enhanced.js"></script>
    
    <script>
        // Initialize charts when the DOM is loaded
        document.addEventListener('DOMContentLoaded', function() {
            // Personnel Distribution Chart
            var personnelCtx = document.getElementById('personnelDistributionChart').getContext('2d');
            var personnelChart = new Chart(personnelCtx, {
                type: 'bar',
                data: {
                    labels: <?php echo json_encode(array_column($personnelDistribution, 'category')); ?>,
                    datasets: [{
                        label: 'Personnel Count',
                        data: <?php echo json_encode(array_column($personnelDistribution, 'count')); ?>,
                        backgroundColor: 'rgba(54, 162, 235, 0.7)',
                        borderColor: 'rgba(54, 162, 235, 1)',
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    scales: {
                        yAxes: [{
                            ticks: {
                                beginAtZero: true
                            }
                        }]
                    }
                }
            });
            
            // Store chart reference for widget state management
            document.getElementById('personnelDistributionChart').chart = personnelChart;
            
            // Recruitment Trends Chart
            var recruitmentCtx = document.getElementById('recruitmentTrendsChart').getContext('2d');
            var recruitmentChart = new Chart(recruitmentCtx, {
                type: 'line',
                data: {
                    labels: <?php echo json_encode(array_column($recruitmentTrends, 'month')); ?>,
                    datasets: [{
                        label: 'New Recruits',
                        data: <?php echo json_encode(array_column($recruitmentTrends, 'count')); ?>,
                        backgroundColor: 'rgba(75, 192, 192, 0.2)',
                        borderColor: 'rgba(75, 192, 192, 1)',
                        borderWidth: 2,
                        tension: 0.4
                    }]
                },
                options: {
                    responsive: true,
                    scales: {
                        yAxes: [{
                            ticks: {
                                beginAtZero: true
                            }
                        }]
                    }
                }
            });
            
            // Store chart reference for widget state management
            document.getElementById('recruitmentTrendsChart').chart = recruitmentChart;
            
            // Rank Distribution Chart
            var rankCtx = document.getElementById('rankDistributionChart').getContext('2d');
            var rankChart = new Chart(rankCtx, {
                type: 'doughnut',
                data: {
                    labels: <?php echo json_encode(array_column($rankDistribution, 'rank')); ?>,
                    datasets: [{
                        data: <?php echo json_encode(array_column($rankDistribution, 'count')); ?>,
                        backgroundColor: [
                            'rgba(255, 99, 132, 0.7)',
                            'rgba(54, 162, 235, 0.7)',
                            'rgba(255, 206, 86, 0.7)',
                            'rgba(75, 192, 192, 0.7)',
                            'rgba(153, 102, 255, 0.7)',
                            'rgba(255, 159, 64, 0.7)',
                            'rgba(199, 199, 199, 0.7)',
                            'rgba(83, 102, 255, 0.7)',
                            'rgba(40, 159, 64, 0.7)',
                            'rgba(210, 199, 199, 0.7)'
                        ],
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    legend: {
                        position: 'right'
                    }
                }
            });
            
            // Store chart reference for widget state management
            document.getElementById('rankDistributionChart').chart = rankChart;
            
            // Performance Metrics Chart
            var performanceCtx = document.getElementById('performanceMetricsChart').getContext('2d');
            var performanceChart = new Chart(performanceCtx, {
                type: 'radar',
                data: {
                    labels: <?php echo json_encode(array_column($performanceMetrics, 'metric')); ?>,
                    datasets: [{
                        label: 'Current Period',
                        data: <?php echo json_encode(array_column($performanceMetrics, 'current')); ?>,
                        backgroundColor: 'rgba(54, 162, 235, 0.2)',
                        borderColor: 'rgba(54, 162, 235, 1)',
                        pointBackgroundColor: 'rgba(54, 162, 235, 1)',
                        pointBorderColor: '#fff',
                        pointHoverBackgroundColor: '#fff',
                        pointHoverBorderColor: 'rgba(54, 162, 235, 1)',
                        borderWidth: 1
                    }, {
                        label: 'Previous Period',
                        data: <?php echo json_encode(array_column($performanceMetrics, 'previous')); ?>,
                        backgroundColor: 'rgba(255, 99, 132, 0.2)',
                        borderColor: 'rgba(255, 99, 132, 1)',
                        pointBackgroundColor: 'rgba(255, 99, 132, 1)',
                        pointBorderColor: '#fff',
                        pointHoverBackgroundColor: '#fff',
                        pointHoverBorderColor: 'rgba(255, 99, 132, 1)',
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    scale: {
                        ticks: {
                            beginAtZero: true,
                            max: 100
                        }
                    }
                }
            });
            
            // Store chart reference for widget state management
            document.getElementById('performanceMetricsChart').chart = performanceChart;
            
            // Load personnel table data
            loadPersonnelTable();
            
            // Setup event listeners for personnel table filters
            document.getElementById('rank-filter').addEventListener('change', function() {
                loadPersonnelTable();
            });
            
            document.getElementById('category-filter').addEventListener('change', function() {
                loadPersonnelTable();
            });
            
            document.getElementById('page-size').addEventListener('change', function() {
                loadPersonnelTable();
            });
            
            document.getElementById('personnel-search').addEventListener('keyup', function(e) {
                if (e.key === 'Enter') {
                    loadPersonnelTable();
                }
            });
        });
        
        /**
         * Load personnel table data with pagination and filtering
         */
        function loadPersonnelTable(page = 1) {
            const table = document.getElementById('personnel-table').querySelector('tbody');
            const pageSize = document.getElementById('page-size').value;
            const rank = document.getElementById('rank-filter').value;
            const category = document.getElementById('category-filter').value;
            const search = document.getElementById('personnel-search').value;
            
            // Show loading state
            table.innerHTML = `
                <tr>
                    <td colspan="6" class="text-center">
                        <div class="spinner-border text-primary" role="status"></div>
                        <p class="mt-2">Loading personnel data...</p>
                    </td>
                </tr>
            `;
            
            // Build query parameters
            const params = new URLSearchParams({
                action: 'get_personnel_table',
                page: page,
                limit: pageSize
            });
            
            if (rank) params.append('rank', rank);
            if (category) params.append('category', category);
            if (search) params.append('search', search);
            
            // Fetch data from API
            fetch(`dashboard_api.php?${params.toString()}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.data) {
                        updatePersonnelTable(data.data);
                    } else {
                        table.innerHTML = `
                            <tr>
                                <td colspan="6" class="text-center text-danger">
                                    <i class="fas fa-exclamation-circle mr-2"></i>
                                    Failed to load personnel data
                                </td>
                            </tr>
                        `;
                    }
                })
                .catch(error => {
                    console.error('Error loading personnel data:', error);
                    table.innerHTML = `
                        <tr>
                            <td colspan="6" class="text-center text-danger">
                                <i class="fas fa-exclamation-circle mr-2"></i>
                                Error loading personnel data
                            </td>
                        </tr>
                    `;
                });
        }
        
        /**
         * Update personnel table with fetched data
         */
        function updatePersonnelTable(data) {
            const table = document.getElementById('personnel-table').querySelector('tbody');
            const pagination = data.pagination;
            
            // Clear table
            table.innerHTML = '';
            
            if (data.data.length === 0) {
                table.innerHTML = `
                    <tr>
                        <td colspan="6" class="text-center">
                            No records found
                        </td>
                    </tr>
                `;
                return;
            }
            
            // Add rows
            data.data.forEach(person => {
                const row = document.createElement('tr');
                row.innerHTML = `
                    <td>${person.name}</td>
                    <td>${person.rank}</td>
                    <td>${person.service_number}</td>
                    <td>${person.category}</td>
                    <td>${person.unit}</td>
                    <td>
                        <a href="edit_staff.php?id=${person.id}" class="btn btn-sm btn-outline-primary">
                            <i class="fas fa-edit"></i>
                        </a>
                        <button class="btn btn-sm btn-outline-info view-details" data-id="${person.id}">
                            <i class="fas fa-eye"></i>
                        </button>
                    </td>
                `;
                table.appendChild(row);
            });
            
            // Update pagination info
            document.getElementById('showing-start').textContent = ((pagination.page - 1) * pagination.limit) + 1;
            document.getElementById('showing-end').textContent = Math.min(pagination.page * pagination.limit, pagination.total);
            document.getElementById('total-records').textContent = pagination.total;
            
            // Update pagination controls
            updatePaginationControls(pagination);
        }
        
        /**
         * Update pagination controls
         */
        function updatePaginationControls(pagination) {
            const paginationControls = document.getElementById('pagination-controls');
            paginationControls.innerHTML = '';
            
            // Previous button
            const prevLi = document.createElement('li');
            prevLi.className = `page-item ${pagination.page <= 1 ? 'disabled' : ''}`;
            prevLi.innerHTML = `
                <a class="page-link" href="#" aria-label="Previous" ${pagination.page > 1 ? `onclick="loadPersonnelTable(${pagination.page - 1}); return false;"` : ''}>
                    <span aria-hidden="true">&laquo;</span>
                </a>
            `;
            paginationControls.appendChild(prevLi);
            
            // Page numbers
            const startPage = Math.max(1, pagination.page - 2);
            const endPage = Math.min(pagination.pages, pagination.page + 2);
            
            for (let i = startPage; i <= endPage; i++) {
                const pageLi = document.createElement('li');
                pageLi.className = `page-item ${i === pagination.page ? 'active' : ''}`;
                pageLi.innerHTML = `
                    <a class="page-link" href="#" onclick="loadPersonnelTable(${i}); return false;">${i}</a>
                `;
                paginationControls.appendChild(pageLi);
            }
            
            // Next button
            const nextLi = document.createElement('li');
            nextLi.className = `page-item ${pagination.page >= pagination.pages ? 'disabled' : ''}`;
            nextLi.innerHTML = `
                <a class="page-link" href="#" aria-label="Next" ${pagination.page < pagination.pages ? `onclick="loadPersonnelTable(${pagination.page + 1}); return false;"` : ''}>
                    <span aria-hidden="true">&raquo;</span>
                </a>
            `;
            paginationControls.appendChild(nextLi);
        }
    </script>
</body>
</html>
