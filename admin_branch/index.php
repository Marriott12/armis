<?php
// Define module constants
define('ARMIS_ADMIN_BRANCH', true);
define('ARMIS_DEVELOPMENT', true); // Set to false in production

// Include admin branch authentication and database
require_once __DIR__ . '/includes/auth.php';

// Include RBAC system
require_once dirname(__DIR__) . '/shared/rbac.php';

// Include database connection
require_once dirname(__DIR__) . '/shared/database_connection.php';

// Include dashboard service
require_once __DIR__ . '/includes/dashboard_service.php';

// Require authentication and admin privileges
requireAuth();

// Check if user has access to admin_branch module
requireModuleAccess('admin_branch');

// Log page access
logActivity('admin_branch_dashboard_access', 'Accessed Admin Branch Dashboard');
logAccess('admin_branch', 'dashboard_view', true);

// Initialize dashboard service
$pdo = getDbConnection();
$dashboardService = null;
$dashboardData = null;

try {
    if ($pdo) {
        $dashboardService = new DashboardService($pdo);
        
        // Handle AJAX requests for filtered data
        if (isset($_GET['ajax']) && $_GET['ajax'] === '1') {
            header('Content-Type: application/json');
            
            try {
                $timeFilter = $_GET['filter'] ?? null;
                $enhancedPersonnel = $dashboardService->getEnhancedPersonnelStats($timeFilter);
                
                echo json_encode([
                    'success' => true,
                    'enhanced_personnel' => $enhancedPersonnel,
                    'filter_applied' => $timeFilter
                ]);
                exit;
            } catch (Exception $e) {
                echo json_encode([
                    'success' => false,
                    'error' => $e->getMessage()
                ]);
                exit;
            }
        }
        
        $dashboardData = [
            'kpi' => $dashboardService->getKPIData(),
            'personnel_distribution' => $dashboardService->getPersonnelDistribution(),
            'recruitment_trends' => $dashboardService->getRecruitmentTrends(),
            'performance_metrics' => $dashboardService->getPerformanceMetrics(),
            'recent_activities' => $dashboardService->getRecentActivities(4), // Limit to 4 for display
            'enhanced_personnel' => $dashboardService->getEnhancedPersonnelStats(), // Add enhanced stats
            'analytics' => $dashboardService->getAnalyticsData() // Add comprehensive analytics
        ];
        
        // Log successful initialization
        error_log("Dashboard initialization successful - KPI Total Personnel: " . $dashboardData['kpi']['total_personnel']);
    } else {
        throw new Exception('Database connection failed');
    }
} catch (Exception $e) {
    error_log("Dashboard initialization error: " . $e->getMessage());
    // Use default data if database fails
    $dashboardData = [
        'kpi' => [
            'total_personnel' => 310,
            'active_personnel' => 245,
            'new_recruits' => 18,
            'on_leave_training' => 47,
            'performance_avg' => 88.5,
            'trends' => [
                'total_personnel' => 5.2,
                'active_personnel' => 2.1,
                'new_recruits' => -3.8,
                'performance_avg' => 1.2
            ]
        ],
        'personnel_distribution' => [
            'active' => 245,
            'leave' => 15,
            'training' => 32,
            'deployed' => 18
        ],
        'recruitment_trends' => [
            'labels' => ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun'],
            'data' => [12, 19, 15, 25, 22, 18]
        ],
        'performance_metrics' => [
            'labels' => ['Q1', 'Q2', 'Q3', 'Q4'],
            'data' => [85, 88, 92, 89]
        ],
        'recent_activities' => []
    ];
}

$pageTitle = "Admin Branch";
$moduleName = "Admin Branch";
$moduleIcon = "users-cog";
$currentPage = "dashboard";

// Group reports into categories for better organization
$reportGroups = [
    'personnel' => [
        ['title' => 'Seniority', 'url' => '/Armis2/admin_branch/reports_seniority.php', 'icon' => 'sort-amount-down'],
        ['title' => 'Rank', 'url' => '/Armis2/admin_branch/reports_rank.php', 'icon' => 'medal'],
        ['title' => 'Gender', 'url' => '/Armis2/admin_branch/reports_gender.php', 'icon' => 'venus-mars'],
        ['title' => 'Marital', 'url' => '/Armis2/admin_branch/reports_marital.php', 'icon' => 'ring'],
    ],
    'organization' => [
        ['title' => 'Units', 'url' => '/Armis2/admin_branch/reports_units.php', 'icon' => 'building'],
        ['title' => 'Corps', 'url' => '/Armis2/admin_branch/reports_corps.php', 'icon' => 'shield-alt'],
        ['title' => 'Appointments', 'url' => '/Armis2/admin_branch/reports_appointment.php', 'icon' => 'user-tie'],
    ],
    'other' => [
        ['title' => 'Contracts', 'url' => '/Armis2/admin_branch/reports_contract.php', 'icon' => 'file-contract'],
        ['title' => 'Courses', 'url' => '/Armis2/admin_branch/reports_courses.php', 'icon' => 'graduation-cap'],
        ['title' => 'Retired', 'url' => '/Armis2/admin_branch/reports_retired.php', 'icon' => 'user-clock'],
        ['title' => 'Deceased', 'url' => '/Armis2/admin_branch/reports_deceased.php', 'icon' => 'cross'],
        ['title' => 'Trade', 'url' => '/Armis2/admin_branch/reports_trade.php', 'icon' => 'tools'],
    ]
];

// Optimize sidebar links to reduce clutter
// Sidebar navigation
$sidebarLinks = [
    ['title' => 'Dashboard', 'url' => '/Armis2/admin_branch/index.php', 'icon' => 'tachometer-alt', 'page' => 'dashboard'],
    ['title' => 'Staff Management', 'url' => '/Armis2/admin_branch/edit_staff.php', 'icon' => 'users', 'page' => 'staff'],
    ['title' => 'Create Staff', 'url' => '/Armis2/admin_branch/create_staff.php', 'icon' => 'user-plus', 'page' => 'create'],
    ['title' => 'Promotions', 'url' => '/Armis2/admin_branch/promote_staff.php', 'icon' => 'arrow-up', 'page' => 'promotions'],
    ['title' => 'Appointments', 'url' => '/Armis2/admin_branch/appointments.php', 'icon' => 'user-tie', 'page' => 'appointments'],
    ['title' => 'Medals', 'url' => '/Armis2/admin_branch/assign_medal.php', 'icon' => 'medal', 'page' => 'medals'],
    [
        'title' => 'Reports',
        'icon' => 'chart-bar',
        'page' => 'reports',
        'children' => [
            ['title' => 'Seniority', 'url' => '/Armis2/admin_branch/reports_seniority.php'],
            ['title' => 'Unit List', 'url' => '/Armis2/admin_branch/reports_units.php'],
            ['title' => 'Appointments', 'url' => '/Armis2/admin_branch/reports_appointment.php'],
            ['title' => 'Contracts', 'url' => '/Armis2/admin_branch/reports_contract.php'],
            ['title' => 'Courses', 'url' => '/Armis2/admin_branch/reports_courses.php'],
            ['title' => 'Deceased', 'url' => '/Armis2/admin_branch/reports_deceased.php'],
            ['title' => 'Gender', 'url' => '/Armis2/admin_branch/reports_gender.php'],
            ['title' => 'Marital', 'url' => '/Armis2/admin_branch/reports_marital.php'],
            ['title' => 'Rank', 'url' => '/Armis2/admin_branch/reports_rank.php'],
            ['title' => 'Retired', 'url' => '/Armis2/admin_branch/reports_retired.php'],
            ['title' => 'Trade', 'url' => '/Armis2/admin_branch/reports_trade.php'],
            ['title' => 'Corps', 'url' => '/Armis2/admin_branch/reports_corps.php'],
            ['title' => 'Units', 'url' => '/Armis2/admin_branch/reports_units.php'],
            ['title' => 'Medals', 'url' => '/Armis2/admin_branch/reports_medals.php'],
        ]
    ],
];

// Ensure shared admin branch CSS is loaded
echo '<link rel="stylesheet" href="/Armis2/assets/css/admin_branch.css">';
include dirname(__DIR__) . '/shared/header.php';
include dirname(__DIR__) . '/shared/sidebar.php'; 
?>
<!-- Custom CSS for Dashboard Optimizations -->
<style>
/* Custom Dashboard Optimizations */
.content-wrapper {
    overflow-x: hidden; /* Prevent horizontal scrolling */
}

/* Responsive adjustments for small screens */
@media (max-width: 768px) {
    .dashboard-title {
        font-size: 1.35rem;
    }
    .card-title {
        font-size: 0.95rem;
    }
    .stat-card .card-title {
        font-size: 0.8rem;
    }
    .stat-value {
        font-size: 1.1rem;
    }
}

/* Compact Card Designs */
.stat-card {
    transition: all 0.2s ease;
    border-left: 4px solid transparent;
}
.stat-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 0.25rem 0.75rem rgba(0,0,0,0.15) !important;
}

/* Make charts more responsive */
.chart-container {
    position: relative;
    min-height: 180px;
    max-height: 250px;
    width: 100%;
}

/* Quick action cards */
.icon-circle {
    transition: all 0.2s ease;
}
.card:hover .icon-circle {
    transform: scale(1.1);
}

/* Tab optimizations */
.nav-tabs .nav-link {
    font-size: 0.85rem;
    padding: 0.25rem 0.5rem;
}

/* Activity and event items */
.list-group-item {
    transition: background-color 0.2s ease;
}
.list-group-item:hover {
    background-color: rgba(0,0,0,0.02);
}

/* Collapse transitions */
.collapse, .collapsing {
    transition: all 0.2s ease-in-out;
}

/* Optimized scrollbars for webkit browsers */
::-webkit-scrollbar {
    width: 6px;
    height: 6px;
}
::-webkit-scrollbar-track {
    background: #f1f1f1;
}
::-webkit-scrollbar-thumb {
    background: #888;
    border-radius: 3px;
}

/* Enhanced Personnel Card Styles */
.dashboard-container {
    max-width: 1400px;
    margin: 0 auto;
    padding: 20px;
}

.stat-card {
    background: white;
    border-radius: 10px;
    margin-bottom: 15px;
    transition: all 0.3s ease;
    border: 1px solid #e3e6f0;
    box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
}

.stat-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 0.3rem 2rem 0 rgba(58, 59, 69, 0.25);
}

.stat-value {
    font-weight: bold;
    font-family: 'Arial', sans-serif;
}

.stat-label {
    color: #858796;
    font-weight: 500;
}

.loading-spinner {
    display: none;
    border: 3px solid #f3f3f3;
    border-top: 3px solid #3498db;
    border-radius: 50%;
    width: 20px;
    height: 20px;
    animation: spin 1s linear infinite;
    margin: 0 auto;
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

.card-header {
    border-bottom: 1px solid #e3e6f0;
}

.text-xs {
    font-size: 0.75rem;
}

.snapshot-drilldown {
    cursor: pointer;
    transition: all 0.2s ease;
}

.snapshot-drilldown:hover {
    transform: scale(1.02);
    box-shadow: 0 4px 8px rgba(0,0,0,0.1);
}

/* Personnel Snapshot Section */
.personnel-snapshot {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border-radius: 15px;
    padding: 25px;
    margin-bottom: 30px;
    box-shadow: 0 10px 30px rgba(0,0,0,0.1);
}

.personnel-snapshot h5 {
    color: white;
    margin-bottom: 25px;
    font-weight: 600;
    font-size: 1.4rem;
}

.personnel-filter {
    background: rgba(255,255,255,0.2);
    border: none;
    color: white;
    border-radius: 8px;
    padding: 10px 15px;
    margin-bottom: 25px;
    width: 100%;
    max-width: 300px;
}

.personnel-filter::placeholder {
    color: rgba(255,255,255,0.7);
}

.personnel-filter:focus {
    outline: none;
    background: rgba(255,255,255,0.3);
    box-shadow: 0 0 0 2px rgba(255,255,255,0.3);
}

/* Enhanced Personnel Cards */
.personnel-card {
    background: rgba(255,255,255,0.98);
    border-radius: 15px;
    padding: 20px 15px;
    margin-bottom: 15px;
    border: none;
    box-shadow: 0 5px 20px rgba(0,0,0,0.08);
    transition: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1);
    color: #333;
    text-align: center;
    cursor: pointer;
    min-height: 120px;
    display: flex;
    flex-direction: column;
    justify-content: center;
    position: relative;
    overflow: hidden;
}

.personnel-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 4px;
    background: linear-gradient(90deg, #007bff, #0056b3);
    transform: scaleX(0);
    transform-origin: left;
    transition: transform 0.3s ease;
}

.personnel-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 12px 35px rgba(0,0,0,0.15);
}

.personnel-card:hover::before {
    transform: scaleX(1);
}

        /* Specific card color themes */
        .personnel-card.military-total::before { background: linear-gradient(90deg, #007bff, #0056b3); }
        .personnel-card.military-active::before { background: linear-gradient(90deg, #28a745, #1e7e34); }
        .personnel-card.officers::before { background: linear-gradient(90deg, #ffc107, #e0a800); }
        .personnel-card.ncos::before { background: linear-gradient(90deg, #fd7e14, #dc6502); }
        .personnel-card.recruits::before { background: linear-gradient(90deg, #28a745, #1e7e34); }
        .personnel-card.retired::before { background: linear-gradient(90deg, #6c757d, #545b62); }
        .personnel-card.civilian-total::before { background: linear-gradient(90deg, #17a2b8, #138496); }
        .personnel-card.civilian-active::before { background: linear-gradient(90deg, #20c997, #1aa179); }
        .personnel-card.new-month::before { background: linear-gradient(90deg, #ffc107, #e0a800); }
        .personnel-card.new-year::before { background: linear-gradient(90deg, #fd7e14, #dc6502); }
        .personnel-card.gender-male::before { background: linear-gradient(90deg, #007bff, #0056b3); }
        .personnel-card.gender-female::before { background: linear-gradient(90deg, #e83e8c, #d91a72); }.personnel-card .card-icon {
    font-size: 2rem;
    margin-bottom: 12px;
    opacity: 0.8;
    transition: all 0.3s ease;
}

.personnel-card:hover .card-icon {
    transform: scale(1.1);
    opacity: 1;
}

        /* Icon colors */
        .personnel-card.military-total .card-icon { color: #007bff; }
        .personnel-card.military-active .card-icon { color: #28a745; }
        .personnel-card.officers .card-icon { color: #ffc107; }
        .personnel-card.ncos .card-icon { color: #fd7e14; }
        .personnel-card.recruits .card-icon { color: #28a745; }
        .personnel-card.retired .card-icon { color: #6c757d; }
        .personnel-card.civilian-total .card-icon { color: #17a2b8; }
        .personnel-card.civilian-active .card-icon { color: #20c997; }
        .personnel-card.new-month .card-icon { color: #ffc107; }
        .personnel-card.new-year .card-icon { color: #fd7e14; }
        .personnel-card.gender-male .card-icon { color: #007bff; }
        .personnel-card.gender-female .card-icon { color: #e83e8c; }.personnel-card .card-content {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 8px;
}

.personnel-card .card-value {
    font-size: 2.2rem;
    font-weight: 700;
    margin: 0;
    color: #2c3e50;
    line-height: 1;
    font-family: 'Segoe UI', system-ui, sans-serif;
}

.personnel-card .card-label {
    font-size: 0.9rem;
    color: #6c757d;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.8px;
    margin: 0;
    line-height: 1.2;
}

/* Section headers */
.personnel-snapshot h6 {
    font-size: 1.1rem;
    font-weight: 600;
    margin-bottom: 20px;
    padding-bottom: 10px;
    border-bottom: 2px solid rgba(255,255,255,0.2);
}

/* Responsive adjustments */
@media (max-width: 768px) {
    .personnel-card {
        min-height: 100px;
        padding: 15px 10px;
    }
    
    .personnel-card .card-value {
        font-size: 1.8rem;
    }
    
    .personnel-card .card-icon {
        font-size: 1.5rem;
    }
    
    .personnel-card .card-label {
        font-size: 0.8rem;
    }
}

/* Loading state */
.personnel-card.loading {
    opacity: 0.6;
    pointer-events: none;
}

.personnel-card.loading .card-value {
    opacity: 0.3;
}

/* Clickable personnel rows */
.clickable-row {
    transition: all 0.2s ease;
}

.clickable-row:hover {
    background-color: rgba(0, 123, 255, 0.05);
    transform: translateX(2px);
}

.clickable-row:active {
    background-color: rgba(0, 123, 255, 0.1);
}

/* Enhanced Mobile Responsiveness */
@media (max-width: 576px) {
    .card-header h5 {
        font-size: 1rem;
    }
    
    .btn-group-sm .btn {
        font-size: 0.75rem;
        padding: 0.25rem 0.5rem;
    }
    
    .table-responsive table {
        font-size: 0.85rem;
    }
    
    .modal-dialog {
        margin: 0.5rem;
    }
    
    .personnel-snapshot {
        margin-bottom: 1rem;
    }
    
    .card-body {
        padding: 0.75rem;
    }
}

@media (max-width: 768px) {
    .col-md-6 {
        margin-bottom: 1rem;
    }
    
    .d-flex.align-items-center.gap-2 {
        flex-direction: column;
        gap: 0.5rem !important;
        align-items: stretch !important;
    }
    
    .btn-group {
        width: 100%;
    }
    
    .btn-group .btn {
        flex: 1;
    }
}
::-webkit-scrollbar-thumb:hover {
    background: #555;
}
</style>

<!-- Load Chart.js early to ensure it's available for dashboard charts -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.umd.min.js"></script>

<!-- Modern Admin Branch Dashboard -->
<div class="content-wrapper with-sidebar">
    <div class="container-fluid p-0 p-sm-2 p-md-3">
        <div class="main-content">
            <!-- Header Section with Breadcrumbs -->
            <div class="row mb-2 mb-md-3">
                <div class="col-12">
                    <nav aria-label="breadcrumb" class="d-none d-md-block mb-2">
                        <ol class="breadcrumb bg-light py-1 px-3 rounded">
                            <li class="breadcrumb-item"><a href="/Armis2/">Home</a></li>
                            <li class="breadcrumb-item active">Admin Branch</li>
                        </ol>
                    </nav>
                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <div>
                            <h1 class="dashboard-title h3 mb-0">
                                <i class="fas fa-users-cog text-primary"></i> Admin Branch
                            </h1>
                            <p class="text-muted mb-0 small">Personnel Management & Administrative Operations</p>
                        </div>
                        <div class="d-flex gap-2 flex-wrap">
                            <div class="dropdown d-inline-block">
                                <button class="btn btn-sm btn-outline-primary dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                                    <i class="fas fa-plus"></i> <span class="d-none d-sm-inline">Quick Actions</span>
                                </button>
                                <ul class="dropdown-menu dropdown-menu-end">
                                    <li><a class="dropdown-item" href="/Armis2/admin_branch/create_staff.php"><i class="fas fa-user-plus fa-fw me-1"></i> Add New Staff</a></li>
                                    <li><a class="dropdown-item" href="/Armis2/admin_branch/edit_staff.php"><i class="fas fa-search fa-fw me-1"></i> Search Staff</a></li>
                                    <li><a class="dropdown-item" href="/Armis2/admin_branch/promote_staff.php"><i class="fas fa-arrow-up fa-fw me-1"></i> Manage Promotions</a></li>
                                    <li><hr class="dropdown-divider"></li>
                                    <li><a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#reportsModal"><i class="fas fa-chart-bar fa-fw me-1"></i> Generate Reports</a></li>
                                </ul>
                            </div>
                            <div class="dropdown d-inline-block">
                                <button class="btn btn-sm btn-outline-success dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                                    <i class="fas fa-download"></i> <span class="d-none d-sm-inline">Export</span>
                                </button>
                                <ul class="dropdown-menu dropdown-menu-end">
                                    <li><a class="dropdown-item" href="#" onclick="exportReport('personnel_summary', 'csv')"><i class="fas fa-users fa-fw me-1"></i> Personnel Summary</a></li>
                                    <li><a class="dropdown-item" href="#" onclick="exportReport('unit_report', 'csv')"><i class="fas fa-building fa-fw me-1"></i> Unit Report</a></li>
                                    <li><a class="dropdown-item" href="#" onclick="exportReport('kpi_report', 'csv')"><i class="fas fa-chart-line fa-fw me-1"></i> KPI Report</a></li>
                                </ul>
                            </div>
                            <button class="btn btn-sm btn-outline-secondary" onclick="refreshDashboard()" title="Refresh Dashboard">
                                <i class="fas fa-sync-alt"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Snapshot Summary by Category and Gender -->
            <!-- Drilldown Modal for Snapshot Cards -->
            <div class="modal fade" id="snapshotDrilldownModal" tabindex="-1">
                <div class="modal-dialog modal-lg">
                    <div class="modal-content">
                        <div class="modal-header bg-primary text-white">
                            <h5 class="modal-title" id="drilldownModalTitle">Personnel Drilldown</h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body" id="drilldownModalBody">
                            <!-- Drilldown content will be injected here -->
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        </div>
                    </div>
                </div>
            </div>
            <!-- Enhanced Personnel Snapshot -->
            <div class="row mb-3">
                <div class="col-12">
                    <div class="card shadow-sm mb-2">
                        <div class="card-header bg-light py-2 d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">Personnel Snapshot</h5>
                            <div class="d-flex align-items-center gap-2">
                                <div class="btn-group btn-group-sm" role="group" aria-label="Filter Options">
                                    <input type="radio" class="btn-check" name="personnel-filter" id="filter-all" value="all" checked>
                                    <label class="btn btn-outline-primary" for="filter-all">All Time</label>
                                    
                                    <input type="radio" class="btn-check" name="personnel-filter" id="filter-1month" value="1_month">
                                    <label class="btn btn-outline-primary" for="filter-1month">1 Month</label>
                                    
                                    <input type="radio" class="btn-check" name="personnel-filter" id="filter-1year" value="1_year">
                                    <label class="btn btn-outline-primary" for="filter-1year">1 Year</label>
                                </div>
                                <button type="button" class="btn btn-outline-success btn-sm" id="export-snapshot" title="Export Personnel Snapshot">
                                    <i class="fas fa-download"></i>
                                </button>
                            </div>
                        </div>
                        <div class="card-body py-3">
                            <div class="row g-4">
                                <!-- Military Personnel Table Card -->
                                <div class="col-md-6">
                                    <div class="card h-100 border-primary">
                                        <div class="card-header bg-primary text-white d-flex align-items-center">
                                            <i class="fas fa-shield-alt me-2"></i>
                                            <h6 class="mb-0">Military Personnel</h6>
                                        </div>
                                        <div class="card-body p-0">
                                            <div class="table-responsive">
                                                <table class="table table-sm mb-0">
                                                    <thead class="table-light">
                                                        <tr>
                                                            <th class="border-0 ps-3">Category</th>
                                                            <th class="border-0 text-center">Male</th>
                                                            <th class="border-0 text-center">Female</th>
                                                            <th class="border-0 text-center">Total</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <tr class="personnel-row clickable-row" data-category="military-officers" data-type="Officers" style="cursor: pointer;">
                                                            <td class="ps-3 fw-semibold">Officers</td>
                                                            <td class="text-center" id="military-officers-male"><?php echo isset($dashboardData['enhanced_personnel']['military']['officers_by_gender']['male']) ? htmlspecialchars($dashboardData['enhanced_personnel']['military']['officers_by_gender']['male']) : '-'; ?></td>
                                                            <td class="text-center" id="military-officers-female"><?php echo isset($dashboardData['enhanced_personnel']['military']['officers_by_gender']['female']) ? htmlspecialchars($dashboardData['enhanced_personnel']['military']['officers_by_gender']['female']) : '-'; ?></td>
                                                            <td class="text-center fw-bold text-primary" id="military-officers-total"><?php echo isset($dashboardData['enhanced_personnel']['military']['officers']) ? htmlspecialchars($dashboardData['enhanced_personnel']['military']['officers']) : '-'; ?></td>
                                                        </tr>
                                                        <tr class="personnel-row clickable-row" data-category="military-ncos" data-type="NCOs" style="cursor: pointer;">
                                                            <td class="ps-3 fw-semibold">NCOs</td>
                                                            <td class="text-center" id="military-ncos-male"><?php echo isset($dashboardData['enhanced_personnel']['military']['ncos_by_gender']['male']) ? htmlspecialchars($dashboardData['enhanced_personnel']['military']['ncos_by_gender']['male']) : '-'; ?></td>
                                                            <td class="text-center" id="military-ncos-female"><?php echo isset($dashboardData['enhanced_personnel']['military']['ncos_by_gender']['female']) ? htmlspecialchars($dashboardData['enhanced_personnel']['military']['ncos_by_gender']['female']) : '-'; ?></td>
                                                            <td class="text-center fw-bold text-primary" id="military-ncos-total"><?php echo isset($dashboardData['enhanced_personnel']['military']['ncos']) ? htmlspecialchars($dashboardData['enhanced_personnel']['military']['ncos']) : '-'; ?></td>
                                                        </tr>
                                                        <tr class="table-secondary">
                                                            <td class="ps-4 fw-semibold"><em>Cadets/ Recruits</em></td>
                                                            <td colspan="3" class="text-center fw-semibold"></td>
                                                        </tr>
                                                        <tr>
                                                            <td class="ps-5">├ Officer Cadets</td>
                                                            <td class="text-center" id="recruit-officers-male"><?php echo isset($dashboardData['enhanced_personnel']['military']['recruit_officers_by_gender']['male']) ? htmlspecialchars($dashboardData['enhanced_personnel']['military']['recruit_officers_by_gender']['male']) : '-'; ?></td>
                                                            <td class="text-center" id="recruit-officers-female"><?php echo isset($dashboardData['enhanced_personnel']['military']['recruit_officers_by_gender']['female']) ? htmlspecialchars($dashboardData['enhanced_personnel']['military']['recruit_officers_by_gender']['female']) : '-'; ?></td>
                                                            <td class="text-center fw-bold text-success" id="recruit-officers-total"><?php echo isset($dashboardData['enhanced_personnel']['military']['recruit_officers']) ? htmlspecialchars($dashboardData['enhanced_personnel']['military']['recruit_officers']) : '-'; ?></td>
                                                        </tr>
                                                        <tr>
                                                            <td class="ps-5">└ Recruits</td>
                                                            <td class="text-center" id="recruit-ncos-male"><?php echo isset($dashboardData['enhanced_personnel']['military']['recruit_ncos_by_gender']['male']) ? htmlspecialchars($dashboardData['enhanced_personnel']['military']['recruit_ncos_by_gender']['male']) : '-'; ?></td>
                                                            <td class="text-center" id="recruit-ncos-female"><?php echo isset($dashboardData['enhanced_personnel']['military']['recruit_ncos_by_gender']['female']) ? htmlspecialchars($dashboardData['enhanced_personnel']['military']['recruit_ncos_by_gender']['female']) : '-'; ?></td>
                                                            <td class="text-center fw-bold text-success" id="recruit-ncos-total"><?php echo isset($dashboardData['enhanced_personnel']['military']['recruit_ncos']) ? htmlspecialchars($dashboardData['enhanced_personnel']['military']['recruit_ncos']) : '-'; ?></td>
                                                        </tr>
                                                    </tbody>
                                                    <tfoot class="table-primary">
                                                        <tr>
                                                            <th class="ps-3">TOTAL MILITARY</th>
                                                            <th class="text-center" id="military-total-male"><?php echo isset($dashboardData['enhanced_personnel']['military']['by_gender']['male']) ? htmlspecialchars($dashboardData['enhanced_personnel']['military']['by_gender']['male']) : '-'; ?></th>
                                                            <th class="text-center" id="military-total-female"><?php echo isset($dashboardData['enhanced_personnel']['military']['by_gender']['female']) ? htmlspecialchars($dashboardData['enhanced_personnel']['military']['by_gender']['female']) : '-'; ?></th>
                                                            <th class="text-center fs-5 fw-bold text-white" id="military-grand-total"><?php echo isset($dashboardData['enhanced_personnel']['military']['total']) ? htmlspecialchars($dashboardData['enhanced_personnel']['military']['total']) : '-'; ?></th>
                                                        </tr>
                                                    </tfoot>
                                                </table>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Civilian Employees Table Card -->
                                <div class="col-md-6">
                                    <div class="card h-100 border-info">
                                        <div class="card-header bg-info text-white d-flex align-items-center">
                                            <i class="fas fa-briefcase me-2"></i>
                                            <h6 class="mb-0">Civilian Employees</h6>
                                        </div>
                                        <div class="card-body p-0">
                                            <div class="table-responsive">
                                                <table class="table table-sm mb-0">
                                                    <thead class="table-light">
                                                        <tr>
                                                            <th class="border-0 ps-3">Category</th>
                                                            <th class="border-0 text-center">Male</th>
                                                            <th class="border-0 text-center">Female</th>
                                                            <th class="border-0 text-center">Total</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <tr class="personnel-row clickable-row" data-category="civilian-current" data-type="Current Staff" style="cursor: pointer;">
                                                            <td class="ps-3 fw-semibold">Current Staff</td>
                                                            <td class="text-center" id="civilian-current-male"><?php echo isset($dashboardData['enhanced_personnel']['civilian']['current_by_gender']['male']) ? htmlspecialchars($dashboardData['enhanced_personnel']['civilian']['current_by_gender']['male']) : '-'; ?></td>
                                                            <td class="text-center" id="civilian-current-female"><?php echo isset($dashboardData['enhanced_personnel']['civilian']['current_by_gender']['female']) ? htmlspecialchars($dashboardData['enhanced_personnel']['civilian']['current_by_gender']['female']) : '-'; ?></td>
                                                            <td class="text-center fw-bold text-info" id="civilian-current-total"><?php echo isset($dashboardData['enhanced_personnel']['civilian']['active']) ? htmlspecialchars($dashboardData['enhanced_personnel']['civilian']['active']) : '-'; ?></td>
                                                        </tr>
                                                        <tr class="personnel-row clickable-row" data-category="civilian-new" data-type="New Hires" style="cursor: pointer;">
                                                            <td class="ps-3 fw-semibold">New Entrants</td>
                                                            <td class="text-center" id="civilian-new-male"><?php echo isset($dashboardData['enhanced_personnel']['civilian']['new_by_gender']['male']) ? htmlspecialchars($dashboardData['enhanced_personnel']['civilian']['new_by_gender']['male']) : '-'; ?></td>
                                                            <td class="text-center" id="civilian-new-female"><?php echo isset($dashboardData['enhanced_personnel']['civilian']['new_by_gender']['female']) ? htmlspecialchars($dashboardData['enhanced_personnel']['civilian']['new_by_gender']['female']) : '-'; ?></td>
                                                            <td class="text-center fw-bold text-success" id="civilian-new-total"><?php echo isset($dashboardData['enhanced_personnel']['civilian']['new_1_year']) ? htmlspecialchars($dashboardData['enhanced_personnel']['civilian']['new_1_year']) : '-'; ?></td>
                                                        </tr>
                                                    </tbody>
                                                    <tfoot class="table-info">
                                                        <tr>
                                                            <th class="ps-3">TOTAL CIVILIAN</th>
                                                            <th class="text-center" id="civilian-total-male"><?php echo isset($dashboardData['enhanced_personnel']['civilian']['by_gender']['male']) ? htmlspecialchars($dashboardData['enhanced_personnel']['civilian']['by_gender']['male']) : '-'; ?></th>
                                                            <th class="text-center" id="civilian-total-female"><?php echo isset($dashboardData['enhanced_personnel']['civilian']['by_gender']['female']) ? htmlspecialchars($dashboardData['enhanced_personnel']['civilian']['by_gender']['female']) : '-'; ?></th>
                                                            <th class="text-center fs-5 fw-bold text-white" id="civilian-grand-total"><?php echo isset($dashboardData['enhanced_personnel']['civilian']['total']) ? htmlspecialchars($dashboardData['enhanced_personnel']['civilian']['total']) : '-'; ?></th>
                                                        </tr>
                                                    </tfoot>
                                                </table>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Personnel Detail Modal -->
            <div class="modal fade" id="personnelDetailModal" tabindex="-1" aria-labelledby="personnelDetailModalLabel" aria-hidden="true">
                <div class="modal-dialog modal-lg">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="personnelDetailModalLabel">
                                <i class="fas fa-users me-2"></i>Personnel Details
                            </h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <h6 class="text-primary" id="modal-category-title">Category Details</h6>
                                    <div class="card">
                                        <div class="card-body">
                                            <div class="row text-center">
                                                <div class="col-4">
                                                    <div class="border-end">
                                                        <h4 class="text-primary mb-0" id="modal-male-count">-</h4>
                                                        <small class="text-muted">Male</small>
                                                    </div>
                                                </div>
                                                <div class="col-4">
                                                    <div class="border-end">
                                                        <h4 class="text-danger mb-0" id="modal-female-count">-</h4>
                                                        <small class="text-muted">Female</small>
                                                    </div>
                                                </div>
                                                <div class="col-4">
                                                    <h4 class="text-success mb-0" id="modal-total-count">-</h4>
                                                    <small class="text-muted">Total</small>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <h6 class="text-secondary">Quick Actions</h6>
                                    <div class="d-grid gap-2">
                                        <button type="button" class="btn btn-outline-primary btn-sm" id="view-personnel-list">
                                            <i class="fas fa-list me-1"></i>View Personnel List
                                        </button>
                                        <button type="button" class="btn btn-outline-secondary btn-sm" id="export-personnel-data">
                                            <i class="fas fa-download me-1"></i>Export Data
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <div id="modal-loading" class="text-center py-3" style="display: none;">
                                <div class="spinner-border text-primary" role="status">
                                    <span class="visually-hidden">Loading...</span>
                                </div>
                                <p class="mt-2 text-muted">Loading personnel details...</p>
                            </div>
                            <div id="modal-personnel-list" style="display: none;">
                                <h6 class="border-bottom pb-2">Personnel List</h6>
                                <div class="table-responsive">
                                    <table class="table table-sm">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Name</th>
                                                <th>Rank</th>
                                                <th>Unit</th>
                                                <th>Gender</th>
                                                <th>Status</th>
                                            </tr>
                                        </thead>
                                        <tbody id="personnel-list-body">
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Quick Actions & Alerts Row -->
            <div class="row g-3 mb-4">
                <!-- Quick Actions Panel (3 cols on mobile, 4 on desktop) -->
                <div class="col-md-8">
                    <div class="card shadow-sm h-100">
                        <div class="card-header d-flex justify-content-between align-items-center p-2 bg-light">
                            <h5 class="card-title h6 mb-0">
                                <i class="fas fa-bolt text-warning"></i> Quick Actions
                            </h5>
                            <button class="btn btn-sm btn-link text-decoration-none p-0" type="button" data-bs-toggle="modal" data-bs-target="#quickActionModal">
                                <i class="fas fa-ellipsis-h"></i>
                            </button>
                        </div>
                        <div class="card-body p-2 p-md-3">
                            <div class="row g-2">
                                <div class="col-6 col-sm-3">
                                    <a href="/Armis2/admin_branch/create_staff.php" class="text-decoration-none">
                                        <div class="card bg-light h-100 action-card">
                                            <div class="card-body p-2 text-center">
                                                <i class="fas fa-user-plus text-success mb-2"></i>
                                                <p class="card-text mb-0 small">Add Staff</p>
                                            </div>
                                        </div>
                                    </a>
                                </div>
                                
                                <div class="col-6 col-sm-3">
                                    <a href="/Armis2/admin_branch/edit_staff.php" class="text-decoration-none">
                                        <div class="card bg-light h-100 action-card">
                                            <div class="card-body p-2 text-center">
                                                <i class="fas fa-search text-primary mb-2"></i>
                                                <p class="card-text mb-0 small">Search Staff</p>
                                            </div>
                                        </div>
                                    </a>
                                </div>
                                
                                <div class="col-6 col-sm-3">
                                    <a href="/Armis2/admin_branch/promote_staff.php" class="text-decoration-none">
                                        <div class="card bg-light h-100 action-card">
                                            <div class="card-body p-2 text-center">
                                                <i class="fas fa-arrow-up text-info mb-2"></i>
                                                <p class="card-text mb-0 small">Promotions</p>
                                            </div>
                                        </div>
                                    </a>
                                </div>
                                
                                <div class="col-6 col-sm-3">
                                    <a href="#" class="text-decoration-none" data-bs-toggle="modal" data-bs-target="#reportsModal">
                                        <div class="card bg-light h-100 action-card">
                                            <div class="card-body p-2 text-center">
                                                <i class="fas fa-file-alt text-secondary mb-2"></i>
                                                <p class="card-text mb-0 small">Reports</p>
                                            </div>
                                        </div>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Alerts Panel - DISABLED -->
                <!-- Notifications have been completely removed from the system -->
            </div>
        </div>
    </div>
</div>

            
<!-- Reports Modal -->
<div class="modal fade" id="reportsModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-dark text-white">
                <h5 class="modal-title">
                    <i class="fas fa-chart-bar"></i> Personnel Reports
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-3">
                <div class="row g-3">
                    <div class="col-md-4">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-body text-center p-3">
                                <i class="fas fa-users fa-2x text-primary mb-3"></i>
                                <h5 class="card-title h6">Personnel Reports</h5>
                                <div class="d-grid gap-1">
                                    <a href="reports_rank.php" class="btn btn-sm btn-outline-primary">Rank Distribution</a>
                                    <a href="reports_trade.php" class="btn btn-sm btn-outline-primary">Trade Distribution</a>
                                    <a href="reports_gender.php" class="btn btn-sm btn-outline-primary">Gender Distribution</a>
                                    <a href="reports_marital.php" class="btn btn-sm btn-outline-primary">Marital Status</a>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-body text-center p-3">
                                <i class="fas fa-building fa-2x text-success mb-3"></i>
                                <h5 class="card-title h6">Organizational Reports</h5>
                                <div class="d-grid gap-1">
                                    <a href="reports_units.php" class="btn btn-sm btn-outline-success">Unit Distribution</a>
                                    <a href="reports_corps.php" class="btn btn-sm btn-outline-success">Corps Distribution</a>
                                    <a href="reports_seniority.php" class="btn btn-sm btn-outline-success">Seniority List</a>
                                    <a href="reports_appointment.php" class="btn btn-sm btn-outline-success">Appointments</a>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-body text-center p-3">
                                <i class="fas fa-clipboard-list fa-2x text-info mb-3"></i>
                                <h5 class="card-title h6">Status Reports</h5>
                                <div class="d-grid gap-1">
                                    <a href="reports_courses.php" class="btn btn-sm btn-outline-info">Courses & Quals</a>
                                    <a href="reports_contract.php" class="btn btn-sm btn-outline-info">Contract Status</a>
                                    <a href="reports_retired.php" class="btn btn-sm btn-outline-info">Retirement</a>
                                    <a href="reports_deceased.php" class="btn btn-sm btn-outline-info">Deceased</a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer p-2">
                <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Include Dashboard JavaScript -->
<script>
// Filter functionality moved to main initialization to avoid conflicts
function initializePersonnelFilters() {
    // Enhanced personnel filter functionality with smooth animations
    const filterRadios = document.querySelectorAll('input[name="personnel-filter"]');
    
    filterRadios.forEach(radio => {
        radio.addEventListener('change', function() {
            if (this.checked) {
                updatePersonnelData(this.value);
            }
        });
    });
    
    function updatePersonnelData(timeFilter) {
        // Add loading state to all cards
        const cards = document.querySelectorAll('.personnel-card');
        cards.forEach(card => {
            card.classList.add('loading');
            const valueElement = card.querySelector('.card-value');
            if (valueElement) {
                valueElement.style.transition = 'opacity 0.3s ease';
                valueElement.style.opacity = '0.5';
            }
        });
        
        // Make AJAX request for filtered data
        fetch(`${window.location.pathname}?ajax=1&filter=${timeFilter}`, {
            method: 'GET',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Content-Type': 'application/json'
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success && data.enhanced_personnel) {
                const ep = data.enhanced_personnel;
                
                // Update military stats with animation
                updateElementWithAnimation('#military-total', ep.military.total);
                updateElementWithAnimation('#military-active', ep.military.active);
                updateElementWithAnimation('#military-officers', ep.military.officers);
                updateElementWithAnimation('#military-ncos', ep.military.ncos);
                updateElementWithAnimation('#retirees-total', ep.retirees);
                
                // Update civilian stats with animation
                updateElementWithAnimation('#civilian-total', ep.civilian.total);
                updateElementWithAnimation('#civilian-active', ep.civilian.active);
                updateElementWithAnimation('#civilian-new-1month', ep.civilian.new_1_month);
                updateElementWithAnimation('#civilian-new-1year', ep.civilian.new_1_year);
                updateElementWithAnimation('#civilian-male', ep.civilian.by_gender.male);
                updateElementWithAnimation('#civilian-female', ep.civilian.by_gender.female);
                
                // Highlight filtered metrics based on selection
                highlightFilteredMetrics(timeFilter);
                
                // Notifications disabled
            }
        })
        .catch(error => {
            console.error('Error fetching personnel data:', error);
            // Notifications disabled
        })
        .finally(() => {
            // Remove loading state
            setTimeout(() => {
                cards.forEach(card => {
                    card.classList.remove('loading');
                    const valueElement = card.querySelector('.card-value');
                    if (valueElement) {
                        valueElement.style.opacity = '1';
                    }
                });
            }, 300);
        });
    }
    
    function updateElementWithAnimation(selector, value) {
        const element = document.querySelector(selector);
        if (element) {
            // Fade out, update, fade in with scale effect
            element.style.transition = 'all 0.2s ease-out';
            element.style.opacity = '0.3';
            element.style.transform = 'scale(0.95)';
            
            setTimeout(() => {
                element.textContent = value || '-';
                element.style.opacity = '1';
                element.style.transform = 'scale(1.05)';
                element.style.transition = 'all 0.3s ease-in';
                
                setTimeout(() => {
                    element.style.transform = 'scale(1)';
                }, 200);
            }, 100);
        }
    }
    
    function highlightFilteredMetrics(timeFilter) {
        // Remove previous highlights
        document.querySelectorAll('.highlight-filter').forEach(el => {
            el.classList.remove('highlight-filter', 'bg-warning', 'text-dark');
        });
        
        // Add highlight based on filter with enhanced animation
        let targetCard = null;
        if (timeFilter === '1_month') {
            targetCard = document.querySelector('#civilian-new-1month').closest('.personnel-card');
        } else if (timeFilter === '1_year') {
            targetCard = document.querySelector('#civilian-new-1year').closest('.personnel-card');
        }
        
        if (targetCard) {
            targetCard.classList.add('highlight-filter');
            targetCard.style.animation = 'highlightPulse 1s ease-in-out';
            
            setTimeout(() => {
                targetCard.style.animation = '';
            }, 1000);
        }
    }
    
    // Enhanced snapshot drilldown functionality
    document.querySelectorAll('.snapshot-drilldown').forEach(card => {
        card.addEventListener('click', function(e) {
            // Add click animation
            this.style.transform = 'scale(0.95)';
            setTimeout(() => {
                this.style.transform = '';
            }, 150);
            
            const category = this.dataset.category;
            showCategoryDetails(category);
        });
        
        // Add enhanced hover effects
        card.addEventListener('mouseenter', function() {
            this.style.transition = 'all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1)';
            const icon = this.querySelector('.card-icon');
            if (icon) {
                icon.style.transform = 'scale(1.1) rotate(5deg)';
                icon.style.transition = 'all 0.3s ease';
            }
        });
        
        card.addEventListener('mouseleave', function() {
            const icon = this.querySelector('.card-icon');
            if (icon) {
                icon.style.transform = 'scale(1) rotate(0deg)';
            }
        });
    });
    
    function showCategoryDetails(category) {
        // Enhanced category details with better UX
        const reportUrls = {
            'Officers': '/Armis2/admin_branch/reports_rank.php?category=Officer',
            'NCOs': '/Armis2/admin_branch/reports_rank.php?category=NCO,Warrant',
            'Total-Military': '/Armis2/admin_branch/reports_rank.php',
            'Active-Military': '/Armis2/admin_branch/reports_rank.php?status=Active',
            'Total-Civilian': '/Armis2/admin_branch/reports_rank.php?category=CE',
            'Active-Civilian': '/Armis2/admin_branch/reports_rank.php?category=CE&status=Active',
            'New-1Month': '/Armis2/admin_branch/reports_rank.php?category=CE&period=1month',
            'New-1Year': '/Armis2/admin_branch/reports_rank.php?category=CE&period=1year',
            'Retired': '/Armis2/admin_branch/reports_retired.php'
        };
        
        if (reportUrls[category]) {
            // Notifications disabled
            window.open(reportUrls[category], '_blank');
        } else {
            // Notifications disabled
        }
    }
    
    // Notification system completely disabled
    
    // Add CSS animations for enhanced UX (notification animations removed)
    const animationCSS = document.createElement('style');
    animationCSS.textContent = `
        @keyframes highlightPulse {
            0% { box-shadow: 0 5px 20px rgba(0,0,0,0.08); }
            50% { box-shadow: 0 15px 35px rgba(255,193,7,0.4); }
            100% { box-shadow: 0 5px 20px rgba(0,0,0,0.08); }
        }
        .highlight-filter {
            border: 2px solid #ffc107 !important;
            background: linear-gradient(135deg, rgba(255,193,7,0.1), rgba(255,193,7,0.05)) !important;
        }
        
        .chart-container {
            position: relative;
            margin: 10px 0;
        }
        
        .chart-loading {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            z-index: 10;
        }
    `;
    document.head.appendChild(animationCSS);
}

// Enhanced Analytics Charts Initialization
function initializeAnalyticsCharts() {
    // Check if Chart.js is available
    if (typeof Chart === 'undefined') {
        console.error('Chart.js is not loaded! Charts cannot be initialized.');
        return;
    }
    
    // Get analytics data from PHP
    const analyticsData = <?php echo json_encode($dashboardData['analytics'] ?? []); ?>;
    const personnelData = <?php echo json_encode($dashboardData['personnel_distribution'] ?? []); ?>;
    const recruitmentData = <?php echo json_encode($dashboardData['recruitment_trends'] ?? []); ?>;
    const performanceData = <?php echo json_encode($dashboardData['performance_metrics'] ?? []); ?>;
    
    console.log('Dashboard data loaded:');
    console.log('Analytics data:', analyticsData);
    console.log('Personnel data:', personnelData);
    console.log('Recruitment data:', recruitmentData);
    console.log('Performance data:', performanceData);
    
    // Verify Chart.js is properly loaded
    console.log('Chart.js version:', Chart.version || 'Unknown');
    
    // Chart.js default options
    Chart.defaults.responsive = true;
    Chart.defaults.maintainAspectRatio = false;
    Chart.defaults.plugins.legend.position = 'bottom';
    Chart.defaults.font.size = 12;
    
    // Initialize charts with error handling
    try {
        // Initialize Personnel Status Chart (Doughnut)
        console.log('Initializing personnel chart...');
        initPersonnelChart(personnelData);
        
        // Initialize Military vs Civilian Chart (Pie)
        if (analyticsData.military_vs_civilian) {
            console.log('Initializing military vs civilian chart...');
            initMilitaryCivilianChart(analyticsData.military_vs_civilian);
        } else {
            console.warn('No military_vs_civilian data available');
        }
        
        // Initialize Rank Distribution Chart (Bar)
        if (analyticsData.rank_distribution) {
            console.log('Initializing ranks chart...');
            initRanksChart(analyticsData.rank_distribution);
        } else {
            console.warn('No rank_distribution data available');
        }
        
        // Initialize Unit Distribution Chart (Horizontal Bar)
        if (analyticsData.unit_distribution) {
            console.log('Initializing units chart...');
            initUnitsChart(analyticsData.unit_distribution);
        } else {
            console.warn('No unit_distribution data available');
        }
        
        // Initialize Corps Distribution Chart (Doughnut)
        if (analyticsData.corps_distribution) {
            console.log('Initializing corps chart...');
            initCorpsChart(analyticsData.corps_distribution);
        } else {
            console.warn('No corps_distribution data available');
        }
        
        // Initialize Demographics Charts
        if (analyticsData.gender_distribution) {
            console.log('Initializing gender chart...');
            initGenderChart(analyticsData.gender_distribution);
        } else {
            console.warn('No gender_distribution data available');
        }
        
        if (analyticsData.age_distribution) {
            console.log('Initializing age chart...');
            initAgeChart(analyticsData.age_distribution);
        } else {
            console.warn('No age_distribution data available');
        }
        
        if (analyticsData.service_length_distribution) {
            console.log('Initializing service length chart...');
            initServiceLengthChart(analyticsData.service_length_distribution);
        } else {
            console.warn('No service_length_distribution data available');
        }
        
        if (analyticsData.marital_status_distribution) {
            console.log('Initializing marital status chart...');
            initMaritalChart(analyticsData.marital_status_distribution);
        } else {
            console.warn('No marital_status_distribution data available');
        }
        
        // Initialize Recruitment Trends Chart
        if (recruitmentData && Object.keys(recruitmentData).length > 0) {
            console.log('Initializing recruitment chart...');
            initRecruitmentChart(recruitmentData);
        } else {
            console.warn('No recruitment data available');
        }
        
        // Initialize Performance Metrics Chart
        if (performanceData && Object.keys(performanceData).length > 0) {
            console.log('Initializing performance chart...');
            initPerformanceChart(performanceData);
        } else {
            console.warn('No performance data available');
        }
        
        console.log('Chart initialization completed successfully');
        
    } catch (error) {
        console.error('Error initializing charts:', error);
    }
}

// Initialize analytics charts when DOM is ready and Chart.js is loaded
document.addEventListener('DOMContentLoaded', function() {
    // Wait for Chart.js to load before initializing charts
    function waitForChart() {
        if (typeof Chart !== 'undefined') {
            console.log('Chart.js loaded successfully');
            initializeAnalyticsCharts();
        } else {
            console.log('Waiting for Chart.js to load...');
            setTimeout(waitForChart, 100);
        }
    }
    waitForChart();
});

// Personnel Status Distribution Chart
function initPersonnelChart(data) {
    const ctx = document.getElementById('personnelChart');
    if (!ctx) return;
    
    // Validate data
    if (!data || typeof data !== 'object' || Object.keys(data).length === 0) {
        ctx.getContext('2d').fillText('No personnel data available', 50, 50);
        return;
    }
    
    new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: Object.keys(data).map(key => key.charAt(0).toUpperCase() + key.slice(1)),
            datasets: [{
                data: Object.values(data),
                backgroundColor: [
                    '#28a745', // Active - Green
                    '#ffc107', // Leave - Yellow
                    '#17a2b8', // Training - Info
                    '#fd7e14', // Deployed - Orange
                    '#6c757d'  // Retired - Gray
                ],
                borderWidth: 2,
                borderColor: '#fff'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                title: {
                    display: true,
                    text: 'Personnel Status Distribution'
                },
                legend: {
                    position: 'right'
                }
            }
        }
    });
}

// Military vs Civilian Chart
function initMilitaryCivilianChart(data) {
    const ctx = document.getElementById('militaryCivilianChart');
    if (!ctx) return;
    
    // Validate data
    if (!data || !data.labels || !data.data || data.labels.length === 0) {
        ctx.getContext('2d').fillText('No data available', 50, 50);
        return;
    }
    
    new Chart(ctx, {
        type: 'pie',
        data: {
            labels: data.labels,
            datasets: [{
                data: data.data,
                backgroundColor: data.colors || ['#007bff', '#28a745'],
                borderWidth: 2,
                borderColor: '#fff'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                title: {
                    display: true,
                    text: 'Military vs Civilian Personnel'
                }
            }
        }
    });
}

// Rank Distribution Chart
function initRanksChart(data) {
    const ctx = document.getElementById('ranksChart');
    if (!ctx) return;
    
    // Validate data
    if (!data || !data.labels || !data.data || data.labels.length === 0) {
        ctx.getContext('2d').fillText('No rank data available', 50, 50);
        return;
    }
    
    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: data.labels,
            datasets: [{
                label: 'Personnel Count',
                data: data.data,
                backgroundColor: data.colors || '#007bff',
                borderWidth: 1,
                borderColor: data.colors ? data.colors.map(color => color + '80') : '#0056b3'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                title: {
                    display: true,
                    text: 'Personnel Distribution by Rank'
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        stepSize: 1
                    }
                }
            }
        }
    });
}

// Unit Distribution Chart
function initUnitsChart(data) {
    const ctx = document.getElementById('unitsChart');
    if (!ctx) return;
    
    // Validate data
    if (!data || !data.labels || !data.data || data.labels.length === 0) {
        ctx.getContext('2d').fillText('No unit data available', 50, 50);
        return;
    }
    
    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: data.labels,
            datasets: [{
                label: 'Personnel Count',
                data: data.data,
                backgroundColor: data.colors || '#28a745',
                borderWidth: 1,
                borderColor: data.colors ? data.colors.map(color => color + '80') : '#1e7e34'
            }]
        },
        options: {
            indexAxis: 'y',
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                title: {
                    display: true,
                    text: 'Personnel Distribution by Unit'
                }
            },
            scales: {
                x: {
                    beginAtZero: true,
                    ticks: {
                        stepSize: 1
                    }
                }
            }
        }
    });
}

// Corps Distribution Chart
function initCorpsChart(data) {
    const ctx = document.getElementById('corpsChart');
    if (!ctx) return;
    
    new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: data.labels,
            datasets: [{
                data: data.data,
                backgroundColor: data.colors,
                borderWidth: 2,
                borderColor: '#fff'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                title: {
                    display: true,
                    text: 'Corps Distribution'
                }
            }
        }
    });
}

// Gender Distribution Chart
function initGenderChart(data) {
    const ctx = document.getElementById('genderChart');
    if (!ctx) return;
    
    new Chart(ctx, {
        type: 'pie',
        data: {
            labels: data.labels,
            datasets: [{
                data: data.data,
                backgroundColor: data.colors,
                borderWidth: 2,
                borderColor: '#fff'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom'
                }
            }
        }
    });
}

// Age Distribution Chart
function initAgeChart(data) {
    const ctx = document.getElementById('ageChart');
    if (!ctx) return;
    
    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: data.labels,
            datasets: [{
                label: 'Personnel Count',
                data: data.data,
                backgroundColor: data.colors,
                borderWidth: 1,
                borderColor: data.colors.map(color => color + '80')
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        stepSize: 1
                    }
                }
            }
        }
    });
}

// Service Length Chart
function initServiceLengthChart(data) {
    const ctx = document.getElementById('serviceLengthChart');
    if (!ctx) return;
    
    new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: data.labels,
            datasets: [{
                data: data.data,
                backgroundColor: data.colors,
                borderWidth: 2,
                borderColor: '#fff'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom'
                }
            }
        }
    });
}

// Marital Status Chart
function initMaritalChart(data) {
    const ctx = document.getElementById('maritalChart');
    if (!ctx) return;
    
    new Chart(ctx, {
        type: 'pie',
        data: {
            labels: data.labels,
            datasets: [{
                data: data.data,
                backgroundColor: data.colors,
                borderWidth: 2,
                borderColor: '#fff'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom'
                }
            }
        }
    });
}

// Recruitment Trends Chart
function initRecruitmentChart(data) {
    const ctx = document.getElementById('recruitmentChart');
    if (!ctx) return;
    
    new Chart(ctx, {
        type: 'line',
        data: {
            labels: data.labels,
            datasets: [{
                label: 'New Recruits',
                data: data.data,
                borderColor: '#007bff',
                backgroundColor: 'rgba(0, 123, 255, 0.1)',
                borderWidth: 3,
                fill: true,
                tension: 0.4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                title: {
                    display: true,
                    text: 'Recruitment Trends (Last 6 Months)'
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        stepSize: 1
                    }
                }
            }
        }
    });
}

// Performance Metrics Chart
function initPerformanceChart(data) {
    const ctx = document.getElementById('performanceChart');
    if (!ctx) return;
    
    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: data.labels,
            datasets: [{
                label: 'Performance %',
                data: data.data,
                backgroundColor: 'rgba(40, 167, 69, 0.8)',
                borderColor: '#28a745',
                borderWidth: 2
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                title: {
                    display: true,
                    text: 'Quarterly Performance Metrics'
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    max: 100,
                    ticks: {
                        callback: function(value) {
                            return value + '%';
                        }
                    }
                }
            }
        }
    });
}

// Additional dashboard styles for enhanced filters
const additionalCSS = `
.highlight-filter {
    transform: scale(1.05);
    transition: all 0.3s ease;
    box-shadow: 0 4px 8px rgba(0,0,0,0.2) !important;
}
`;

// Inject additional styles
const styleSheet = document.createElement('style');
styleSheet.textContent = additionalCSS;
document.head.appendChild(styleSheet);

// Old drilldown functionality removed - using new modal system
                                + '<span>' + rank.rank + '</span>'
                                + '<span class="badge bg-primary rounded-pill">' + rank.count + '</span>'
                                + '</li>';
                        });
                        html += '</ul></div>';
                        html += '<div id="femaleRanksBlock" style="display:none;">'
                            + '<h6>Female</h6><ul class="list-group mb-3">';
                        data.ranks.female.forEach(function(rank) {
                            html += '<li class="list-group-item d-flex justify-content-between align-items-center">'
                                + '<span>' + rank.rank + '</span>'
                                + '<span class="badge bg-pink rounded-pill">' + rank.count + '</span>'
                                + '</li>';
                        });
                        html += '</ul></div>';
                        modalBody.innerHTML = html;
                        // Add toggle logic
                        document.getElementById('showMaleRanks').addEventListener('click', function() {
                            document.getElementById('maleRanksBlock').style.display = '';
                            document.getElementById('femaleRanksBlock').style.display = 'none';
                        });
                        document.getElementById('showFemaleRanks').addEventListener('click', function() {
                            document.getElementById('maleRanksBlock').style.display = 'none';
                            document.getElementById('femaleRanksBlock').style.display = '';
                        });
                    } else {
                        modalBody.innerHTML = '<div class="text-center text-muted">No rank breakdown data available.</div>';
                    }
                })
                .catch(function() {
                    modalBody.innerHTML = '<div class="text-center text-danger">Failed to load drilldown data.</div>';
                });
        });
    });


    });

    // Real-time Data Refresh Functionality
    let refreshInterval;
    let isRefreshing = false;
    
    function startAutoRefresh() {
        // Refresh every 5 minutes (300,000 ms)
        refreshInterval = setInterval(refreshPersonnelData, 300000);
        
        // Add visual indicator
        addRefreshIndicator();
    }
    
    function refreshPersonnelData() {
        if (isRefreshing) return;
        
        isRefreshing = true;
        showRefreshIndicator();
        
        const currentFilter = document.querySelector('input[name="personnel-filter"]:checked')?.value || 'all';
        
        fetch(`index.php?ajax=1&filter=${currentFilter}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    updatePersonnelDisplay(data.enhanced_personnel);
                    showRefreshSuccess();
                } else {
                    showRefreshError();
                }
            })
            .catch(error => {
                console.error('Refresh error:', error);
                showRefreshError();
            })
            .finally(() => {
                isRefreshing = false;
                hideRefreshIndicator();
            });
    }
    
    function updatePersonnelDisplay(personnelData) {
        // Update military data
        if (personnelData.military) {
            updateElement('military-officers-male', personnelData.military.officers_by_gender?.male);
            updateElement('military-officers-female', personnelData.military.officers_by_gender?.female);
            updateElement('military-officers-total', personnelData.military.officers);
            
            updateElement('military-ncos-male', personnelData.military.ncos_by_gender?.male);
            updateElement('military-ncos-female', personnelData.military.ncos_by_gender?.female);
            updateElement('military-ncos-total', personnelData.military.ncos);
            
            updateElement('military-total-male', personnelData.military.by_gender?.male);
            updateElement('military-total-female', personnelData.military.by_gender?.female);
            updateElement('military-grand-total', personnelData.military.total);
        }
        
        // Update civilian data
        if (personnelData.civilian) {
            updateElement('civilian-current-male', personnelData.civilian.current_by_gender?.male);
            updateElement('civilian-current-female', personnelData.civilian.current_by_gender?.female);
            updateElement('civilian-current-total', personnelData.civilian.active);
            
            updateElement('civilian-new-male', personnelData.civilian.new_by_gender?.male);
            updateElement('civilian-new-female', personnelData.civilian.new_by_gender?.female);
            updateElement('civilian-new-total', personnelData.civilian.new_1_year);
            
            updateElement('civilian-total-male', personnelData.civilian.by_gender?.male);
            updateElement('civilian-total-female', personnelData.civilian.by_gender?.female);
            updateElement('civilian-grand-total', personnelData.civilian.total);
        }
    }
    
    function updateElement(id, value) {
        const element = document.getElementById(id);
        if (element && value !== undefined) {
            element.textContent = value || '-';
            // Add brief highlight animation
            element.style.backgroundColor = '#d4edda';
            setTimeout(() => {
                element.style.backgroundColor = '';
            }, 1000);
        }
    }
    
    function addRefreshIndicator() {
        const header = document.querySelector('.card-header');
        if (header && !document.getElementById('refresh-indicator')) {
            const indicator = document.createElement('div');
            indicator.id = 'refresh-indicator';
            indicator.innerHTML = `
                <small class="text-muted me-2">
                    <i class="fas fa-sync-alt" id="refresh-icon"></i>
                    <span id="refresh-status">Auto-refresh enabled</span>
                </small>
            `;
            header.appendChild(indicator);
        }
    }
    
    function showRefreshIndicator() {
        const icon = document.getElementById('refresh-icon');
        const status = document.getElementById('refresh-status');
        if (icon && status) {
            icon.className = 'fas fa-sync-alt fa-spin text-primary';
            status.textContent = 'Refreshing...';
        }
    }
    
    function showRefreshSuccess() {
        const icon = document.getElementById('refresh-icon');
        const status = document.getElementById('refresh-status');
        if (icon && status) {
            icon.className = 'fas fa-check text-success';
            status.textContent = 'Updated';
            setTimeout(() => {
                icon.className = 'fas fa-sync-alt text-muted';
                status.textContent = 'Auto-refresh enabled';
            }, 2000);
        }
    }
    
    function showRefreshError() {
        const icon = document.getElementById('refresh-icon');
        const status = document.getElementById('refresh-status');
        if (icon && status) {
            icon.className = 'fas fa-exclamation-triangle text-warning';
            status.textContent = 'Refresh failed';
            setTimeout(() => {
                icon.className = 'fas fa-sync-alt text-muted';
                status.textContent = 'Auto-refresh enabled';
            }, 3000);
        }
    }
    
    function hideRefreshIndicator() {
        // Refresh indicator stays visible, just update status
    }
    
    // Export Functionality
    function exportPersonnelSnapshot() {
        const currentFilter = document.querySelector('input[name="personnel-filter"]:checked')?.value || 'all';
        const timestamp = new Date().toLocaleString();
        
        // Collect current data from the dashboard
        const militaryData = {
            officers: {
                male: document.getElementById('military-officers-male').textContent,
                female: document.getElementById('military-officers-female').textContent,
                total: document.getElementById('military-officers-total').textContent
            },
            ncos: {
                male: document.getElementById('military-ncos-male').textContent,
                female: document.getElementById('military-ncos-female').textContent,
                total: document.getElementById('military-ncos-total').textContent
            },
            total: {
                male: document.getElementById('military-total-male').textContent,
                female: document.getElementById('military-total-female').textContent,
                total: document.getElementById('military-grand-total').textContent
            }
        };
        
        const civilianData = {
            current: {
                male: document.getElementById('civilian-current-male').textContent,
                female: document.getElementById('civilian-current-female').textContent,
                total: document.getElementById('civilian-current-total').textContent
            },
            new: {
                male: document.getElementById('civilian-new-male').textContent,
                female: document.getElementById('civilian-new-female').textContent,
                total: document.getElementById('civilian-new-total').textContent
            },
            total: {
                male: document.getElementById('civilian-total-male').textContent,
                female: document.getElementById('civilian-total-female').textContent,
                total: document.getElementById('civilian-grand-total').textContent
            }
        };
        
        // Create CSV content
        let csvContent = "Personnel Snapshot Report\\n";
        csvContent += `Generated: ${timestamp}\\n`;
        csvContent += `Filter: ${currentFilter}\\n\\n`;
        
        csvContent += "MILITARY PERSONNEL\\n";
        csvContent += "Category,Male,Female,Total\\n";
        csvContent += `Officers,${militaryData.officers.male},${militaryData.officers.female},${militaryData.officers.total}\\n`;
        csvContent += `NCOs,${militaryData.ncos.male},${militaryData.ncos.female},${militaryData.ncos.total}\\n`;
        csvContent += `TOTAL MILITARY,${militaryData.total.male},${militaryData.total.female},${militaryData.total.total}\\n\\n`;
        
        csvContent += "CIVILIAN EMPLOYEES\\n";
        csvContent += "Category,Male,Female,Total\\n";
        csvContent += `Current Staff,${civilianData.current.male},${civilianData.current.female},${civilianData.current.total}\\n`;
        csvContent += `New Hires,${civilianData.new.male},${civilianData.new.female},${civilianData.new.total}\\n`;
        csvContent += `TOTAL CIVILIAN,${civilianData.total.male},${civilianData.total.female},${civilianData.total.total}\\n`;
        
        // Create and download file
        const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
        const link = document.createElement('a');
        const url = URL.createObjectURL(blob);
        link.setAttribute('href', url);
        link.setAttribute('download', `personnel_snapshot_${currentFilter}_${new Date().toISOString().split('T')[0]}.csv`);
        link.style.visibility = 'hidden';
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    }
    
    // Bootstrap check and modal setup
    function checkBootstrapAndInitModal() {
        if (typeof bootstrap === 'undefined') {
            console.error('❌ Bootstrap is not loaded!');
            return null;
        }
        if (typeof bootstrap.Modal === 'undefined') {
            console.error('❌ Bootstrap Modal is not available!');
            return null;
        }
        
        const modalElement = document.getElementById('personnelDetailModal');
        if (!modalElement) {
            console.error('❌ Modal element not found!');
            return null;
        }
        
        try {
            const modal = new bootstrap.Modal(modalElement);
            console.log('✅ Bootstrap modal initialized successfully');
            return modal;
        } catch (error) {
            console.error('❌ Modal initialization failed:', error);
            return null;
        }
    }



    // Main initialization function
    document.addEventListener('DOMContentLoaded', function() {
        console.log('🚀 Initializing Admin Branch Dashboard...');
        
        // Initialize Bootstrap modal with error handling
        const personnelModal = checkBootstrapAndInitModal();
        
        // Function to update modal content
        function updateModalContent(rowElement, category, type) {
            console.log('🔄 Updating modal content for:', type, 'Category:', category);
            
            try {
                // Update modal title
                const modalLabel = document.getElementById('personnelDetailModalLabel');
                if (modalLabel) {
                    modalLabel.innerHTML = `<i class="fas fa-users me-2"></i>${type} Details`;
                    console.log('✅ Modal title updated to:', type + ' Details');
                } else {
                    console.warn('⚠️ Modal title element not found');
                }
                
                // Update category title
                const categoryTitle = document.getElementById('modal-category-title');
                if (categoryTitle) {
                    categoryTitle.textContent = type + ' Breakdown';
                    console.log('✅ Category title updated to:', type + ' Breakdown');
                } else {
                    console.warn('⚠️ Category title element not found');
                }
            } catch (error) {
                console.error('❌ Error updating modal titles:', error);
            }
            
            try {
                // Get data from row cells
                const cells = rowElement.querySelectorAll('td');
                console.log('📊 Row cells found:', cells.length);
                
                if (cells.length >= 4) {
                    const maleCount = cells[1].textContent.trim();
                    const femaleCount = cells[2].textContent.trim();
                    const totalCount = cells[3].textContent.trim();
                    
                    console.log('📈 Extracted data:', {
                        male: maleCount,
                        female: femaleCount,
                        total: totalCount
                    });
                    
                    // Update modal display elements with validation
                    const updates = [
                        { id: 'modal-male-count', value: maleCount, label: 'Male' },
                        { id: 'modal-female-count', value: femaleCount, label: 'Female' },
                        { id: 'modal-total-count', value: totalCount, label: 'Total' }
                    ];
                    
                    let successCount = 0;
                    updates.forEach(update => {
                        const element = document.getElementById(update.id);
                        if (element) {
                            element.textContent = update.value;
                            successCount++;
                            console.log(`✅ ${update.label} count updated:`, update.value);
                        } else {
                            console.warn(`⚠️ ${update.label} count element not found (${update.id})`);
                        }
                    });
                    
                    if (successCount === 3) {
                        console.log('🎉 All modal data updated successfully!');
                    } else {
                        console.warn(`⚠️ Only ${successCount}/3 elements updated successfully`);
                    }
                } else {
                    console.error('❌ Not enough cells in row:', cells.length, 'Need at least 4 cells');
                    alert('Error: Row data format is incorrect. Please refresh the page.');
                }
            } catch (error) {
                console.error('❌ Error extracting row data:', error);
                alert('Error processing row data. Please try again.');
            }
        }
        
        // Set up personnel row click handlers
        console.log('🎯 Setting up personnel row click handlers...');
        
        // Find all personnel rows
        const personnelRows = document.querySelectorAll('tr.personnel-row');
        console.log(`📋 Found ${personnelRows.length} personnel rows`);
        
        if (personnelRows.length === 0) {
            console.warn('⚠️ No personnel rows found! Check if data is loaded.');
        }
        
        personnelRows.forEach((row, index) => {
            const type = row.getAttribute('data-type');
            const category = row.getAttribute('data-category');
            console.log(`📝 Row ${index + 1}: ${type} (${category})`);
            
            // Add click handler
            row.addEventListener('click', function(event) {
                event.preventDefault(); // Prevent any default behavior
                
                console.log('🖱️ Row clicked:', this.getAttribute('data-type'));
                
                const rowCategory = this.getAttribute('data-category');
                const rowType = this.getAttribute('data-type');
                
                if (!rowCategory || !rowType) {
                    console.error('❌ Missing data attributes on clicked row');
                    alert('Error: Row data is incomplete. Please refresh the page.');
                    return;
                }
                
                // Update modal content
                updateModalContent(this, rowCategory, rowType);
                
                // Show modal with error handling
                if (personnelModal) {
                    try {
                        personnelModal.show();
                        console.log('✅ Modal displayed successfully for:', rowType);
                    } catch (error) {
                        console.error('❌ Error showing modal:', error);
                        alert('Error displaying modal. Please refresh the page and try again.');
                    }
                } else {
                    console.error('❌ Modal not initialized - cannot display');
                    alert('Modal system not initialized. Please refresh the page.');
                }
            });
            
            // Add visual feedback on hover
            row.addEventListener('mouseenter', function() {
                this.style.backgroundColor = '#f8f9fa';
                this.style.transform = 'scale(1.01)';
                this.style.transition = 'all 0.2s ease';
            });
            
            row.addEventListener('mouseleave', function() {
                this.style.backgroundColor = '';
                this.style.transform = '';
            });
        });
        
        console.log('✅ Personnel row handlers set up successfully!');
        
        // Set up modal action buttons
        setupModalActionButtons();
        
        // Initialize personnel filters
        initializePersonnelFilters();
        
        // Initialize auto-refresh
        startAutoRefresh();
        
        // Add export button functionality
        const exportBtn = document.getElementById('export-snapshot');
        if (exportBtn) {
            exportBtn.addEventListener('click', exportPersonnelSnapshot);
        }
        
        console.log('🎉 Dashboard initialization complete!');
    });
    
    // Modal action buttons functionality
    function setupModalActionButtons() {
        console.log('⚙️ Setting up modal action buttons...');
        
        // View Personnel List button
        const viewListBtn = document.getElementById('view-personnel-list');
        if (viewListBtn) {
            viewListBtn.addEventListener('click', function() {
                console.log('📋 View Personnel List clicked');
                
                const loadingEl = document.getElementById('modal-loading');
                const personnelListEl = document.getElementById('modal-personnel-list');
                const listBody = document.getElementById('personnel-list-body');
                
                // Show loading
                if (loadingEl) loadingEl.style.display = 'block';
                if (personnelListEl) personnelListEl.style.display = 'none';
                
                // Simulate loading personnel data (replace with actual API call)
                setTimeout(() => {
                    if (loadingEl) loadingEl.style.display = 'none';
                    if (personnelListEl) personnelListEl.style.display = 'block';
                    
                    if (listBody) {
                        listBody.innerHTML = `
                            <tr>
                                <td>Sample Person 1</td>
                                <td>Captain</td>
                                <td>1st Battalion</td>
                                <td>Male</td>
                                <td><span class="badge bg-success">Active</span></td>
                            </tr>
                            <tr>
                                <td>Sample Person 2</td>
                                <td>Lieutenant</td>
                                <td>2nd Battalion</td>
                                <td>Female</td>
                                <td><span class="badge bg-success">Active</span></td>
                            </tr>
                            <tr>
                                <td colspan="5" class="text-center text-muted">
                                    <em>This is sample data. Connect to API for real personnel list.</em>
                                </td>
                            </tr>
                        `;
                    }
                    
                    console.log('✅ Personnel list displayed');
                }, 1500);
            });
        }
        
        // Export Data button
        const exportBtn = document.getElementById('export-personnel-data');
        if (exportBtn) {
            exportBtn.addEventListener('click', function() {
                console.log('📊 Export Data clicked');
                
                const modalTitle = document.getElementById('personnelDetailModalLabel');
                const categoryType = modalTitle ? modalTitle.textContent.replace(' Details', '') : 'Personnel';
                
                // Create sample CSV data
                const csvData = [
                    ['Name', 'Rank', 'Unit', 'Gender', 'Status'],
                    ['Sample Person 1', 'Captain', '1st Battalion', 'Male', 'Active'],
                    ['Sample Person 2', 'Lieutenant', '2nd Battalion', 'Female', 'Active']
                ];
                
                // Convert to CSV string
                const csvContent = csvData.map(row => row.join(',')).join('\n');
                
                // Create and download file
                const blob = new Blob([csvContent], { type: 'text/csv' });
                const url = window.URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                a.download = `${categoryType.replace(/[^a-zA-Z0-9]/g, '_')}_Export_${new Date().getTime()}.csv`;
                document.body.appendChild(a);
                a.click();
                document.body.removeChild(a);
                window.URL.revokeObjectURL(url);
                
                console.log('✅ Data exported as CSV');
                
                // Show success message
                this.innerHTML = '<i class="fas fa-check me-1"></i>Exported!';
                this.classList.remove('btn-outline-secondary');
                this.classList.add('btn-success');
                
                setTimeout(() => {
                    this.innerHTML = '<i class="fas fa-download me-1"></i>Export Data';
                    this.classList.remove('btn-success');
                    this.classList.add('btn-outline-secondary');
                }, 2000);
            });
        }
        
        console.log('✅ Modal action buttons set up successfully!');
    }
</script>
<!-- <script src="js/dashboard.js"></script> Disabled - conflicts with inline charts -->

<?php 
// Include footer and allow script loading for Chart.js
include dirname(__DIR__) . '/shared/footer.php'; 
?>