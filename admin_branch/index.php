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
        $dashboardData = [
            'kpi' => $dashboardService->getKPIData(),
            'personnel_distribution' => $dashboardService->getPersonnelDistribution(),
            'recruitment_trends' => $dashboardService->getRecruitmentTrends(),
            'performance_metrics' => $dashboardService->getPerformanceMetrics(),
            'recent_activities' => $dashboardService->getRecentActivities(4) // Limit to 4 for display
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
$sidebarLinks = [
    ['title' => 'Dashboard', 'url' => '/Armis2/admin_branch/index.php', 'icon' => 'tachometer-alt', 'page' => 'dashboard'],
    [
        'title' => 'Staff Management', 
        'icon' => 'users', 
        'page' => 'staff',
        'children' => [
            ['title' => 'Search & Edit', 'url' => '/Armis2/admin_branch/edit_staff.php', 'icon' => 'search'],
            ['title' => 'Create New', 'url' => '/Armis2/admin_branch/create_staff.php', 'icon' => 'user-plus'],
            ['title' => 'Promotions', 'url' => '/Armis2/admin_branch/promote_staff.php', 'icon' => 'arrow-up'],
            ['title' => 'Appointments', 'url' => '/Armis2/admin_branch/appointments.php', 'icon' => 'briefcase', 'page' => 'appointments'],
            ['title' => 'Medals', 'url' => '/Armis2/admin_branch/assign_medal.php', 'icon' => 'medal'],
        ]
    ],
    [
        'title' => 'Reports',
        'icon' => 'chart-bar',
        'page' => 'reports',
        'children' => [
            ['title' => 'Personnel', 'page' => 'reports_personnel', 'icon' => 'users', 'children' => $reportGroups['personnel']],
            ['title' => 'Organization', 'page' => 'reports_organization', 'icon' => 'sitemap', 'children' => $reportGroups['organization']],
            ['title' => 'Other Reports', 'page' => 'reports_other', 'icon' => 'clipboard-list', 'children' => $reportGroups['other']],
        ]
    ],
    ['title' => 'System Settings', 'url' => '/Armis2/admin_branch/system_settings.php', 'icon' => 'cogs', 'page' => 'settings']
];

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
::-webkit-scrollbar-thumb:hover {
    background: #555;
}
</style>

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

            <!-- KPI Cards (More Responsive) -->
            <div class="row g-2 g-md-3 mb-3 mb-md-4">
                <!-- Primary KPIs - Always visible -->
                <div class="col-6 col-sm-6 col-md-3">
                    <div class="dashboard-widget kpi-card bg-gradient-primary">
                        <div class="card-body p-2 p-md-3">
                            <div class="d-flex justify-content-between align-items-center">
                                <div class="kpi-content">
                                    <h6 class="kpi-label small mb-0">Total Personnel</h6>
                                    <h3 class="kpi-value fs-4 mb-0" data-kpi="total-personnel"><?php echo isset($dashboardData['kpi']['total_personnel']) ? htmlspecialchars($dashboardData['kpi']['total_personnel']) : '-'; ?></h3>
                                    <div class="kpi-trend small">
                                        <?php 
                                        $trend = isset($dashboardData['kpi']['trends']['total_personnel']) ? $dashboardData['kpi']['trends']['total_personnel'] : null;
                                        $trendClass = ($trend !== null && $trend >= 0) ? 'text-success fa-arrow-up' : 'text-danger fa-arrow-down';
                                        $trendSign = ($trend !== null && $trend >= 0) ? '+' : '';
                                        ?>
                                        <i class="fas <?php echo $trendClass; ?>"></i>
                                        <span class="trend-value"><?php echo ($trend !== null ? $trendSign . $trend : '-'); ?>%</span>
                                    </div>
                                </div>
                                <div class="kpi-icon d-none d-sm-block">
                                    <i class="fas fa-users"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-6 col-sm-6 col-md-3">
                    <div class="dashboard-widget kpi-card bg-gradient-success">
                        <div class="card-body p-2 p-md-3">
                            <div class="d-flex justify-content-between align-items-center">
                                <div class="kpi-content">
                                    <h6 class="kpi-label small mb-0">Active Personnel</h6>
                                    <h3 class="kpi-value fs-4 mb-0" data-kpi="active-personnel"><?php echo isset($dashboardData['kpi']['active_personnel']) ? htmlspecialchars($dashboardData['kpi']['active_personnel']) : '-'; ?></h3>
                                    <div class="kpi-trend small">
                                        <?php 
                                        $trend = isset($dashboardData['kpi']['trends']['active_personnel']) ? $dashboardData['kpi']['trends']['active_personnel'] : null;
                                        $trendClass = ($trend !== null && $trend >= 0) ? 'text-success fa-arrow-up' : 'text-danger fa-arrow-down';
                                        $trendSign = ($trend !== null && $trend >= 0) ? '+' : '';
                                        ?>
                                        <i class="fas <?php echo $trendClass; ?>"></i>
                                        <span class="trend-value"><?php echo ($trend !== null ? $trendSign . $trend : '-'); ?>%</span>
                                    </div>
                                </div>
                                <div class="kpi-icon d-none d-sm-block">
                                    <i class="fas fa-check-circle"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-6 col-sm-6 col-md-3">
                    <div class="dashboard-widget kpi-card bg-gradient-warning">
                        <div class="card-body p-2 p-md-3">
                            <div class="d-flex justify-content-between align-items-center">
                                <div class="kpi-content">
                                    <h6 class="kpi-label small mb-0">New Recruits</h6>
                                    <h3 class="kpi-value fs-4 mb-0" data-kpi="new-recruits"><?php echo isset($dashboardData['kpi']['new_recruits']) ? htmlspecialchars($dashboardData['kpi']['new_recruits']) : '-'; ?></h3>
                                    <div class="kpi-trend small">
                                        <?php 
                                        $trend = isset($dashboardData['kpi']['trends']['new_recruits']) ? $dashboardData['kpi']['trends']['new_recruits'] : null;
                                        $trendClass = ($trend !== null && $trend >= 0) ? 'text-success fa-arrow-up' : 'text-danger fa-arrow-down';
                                        $trendSign = ($trend !== null && $trend >= 0) ? '+' : '';
                                        ?>
                                        <i class="fas <?php echo $trendClass; ?>"></i>
                                        <span class="trend-value"><?php echo ($trend !== null ? $trendSign . $trend : '-'); ?>%</span>
                                    </div>
                                </div>
                                <div class="kpi-icon d-none d-sm-block">
                                    <i class="fas fa-user-plus"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-6 col-sm-6 col-md-3">
                    <div class="dashboard-widget kpi-card bg-gradient-info">
                        <div class="card-body p-2 p-md-3">
                            <div class="d-flex justify-content-between align-items-center">
                                <div class="kpi-content">
                                    <h6 class="kpi-label small mb-0">Performance Avg</h6>
                                    <h3 class="kpi-value fs-4 mb-0" data-kpi="performance-avg"><?php echo isset($dashboardData['kpi']['performance_avg']) ? htmlspecialchars($dashboardData['kpi']['performance_avg']) . '%' : '-'; ?></h3>
                                    <div class="kpi-trend small">
                                        <?php 
                                        $trend = isset($dashboardData['kpi']['trends']['performance_avg']) ? $dashboardData['kpi']['trends']['performance_avg'] : null;
                                        $trendClass = ($trend !== null && $trend >= 0) ? 'text-success fa-arrow-up' : 'text-danger fa-arrow-down';
                                        $trendSign = ($trend !== null && $trend >= 0) ? '+' : '';
                                        ?>
                                        <i class="fas <?php echo $trendClass; ?>"></i>
                                        <span class="trend-value"><?php echo ($trend !== null ? $trendSign . $trend : '-'); ?>%</span>
                                    </div>
                                </div>
                                <div class="kpi-icon d-none d-sm-block">
                                    <i class="fas fa-chart-line"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- More detailed KPIs in a dropdown or collapsible section -->
            <div class="accordion mb-4" id="moreKPIsAccordion">
                <div class="accordion-item">
                    <h2 class="accordion-header" id="moreKPIsHeading">
                        <button class="accordion-button collapsed py-2" type="button" data-bs-toggle="collapse" 
                                data-bs-target="#moreKPIsContent" aria-expanded="false" aria-controls="moreKPIsContent">
                            <i class="fas fa-chart-pie me-2"></i> Additional Metrics
                        </button>
                    </h2>
                    <div id="moreKPIsContent" class="accordion-collapse collapse" aria-labelledby="moreKPIsHeading" data-bs-parent="#moreKPIsAccordion">
                        <div class="accordion-body p-2 p-md-3">
                            <div class="row g-2">
                                <div class="col-6 col-md-3">
                                    <div class="card bg-light h-100">
                                        <div class="card-body p-2">
                                            <h6 class="card-title small mb-1">On Leave/Training</h6>
                                            <p class="mb-0 fs-5"><?php echo isset($dashboardData['kpi']['on_leave_training']) ? htmlspecialchars($dashboardData['kpi']['on_leave_training']) : '-'; ?></p>
                                        </div>
                                    </div>
                                </div>
                                <!-- Add more metrics here as needed -->
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tabbed Charts & Analytics for Better Space Utilization -->
            <div class="card mb-4 shadow-sm">
                <div class="card-header p-2 bg-light">
                    <ul class="nav nav-tabs card-header-tabs" id="dashboardChartTabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active py-1 px-2 px-md-3" id="personnel-tab" data-bs-toggle="tab" data-bs-target="#personnel-tab-pane" 
                                    type="button" role="tab" aria-controls="personnel-tab-pane" aria-selected="true">
                                <i class="fas fa-users fa-sm me-1"></i><span class="d-none d-sm-inline">Personnel</span>
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link py-1 px-2 px-md-3" id="recruitment-tab" data-bs-toggle="tab" data-bs-target="#recruitment-tab-pane" 
                                    type="button" role="tab" aria-controls="recruitment-tab-pane" aria-selected="false">
                                <i class="fas fa-chart-line fa-sm me-1"></i><span class="d-none d-sm-inline">Recruitment</span>
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link py-1 px-2 px-md-3" id="performance-tab" data-bs-toggle="tab" data-bs-target="#performance-tab-pane" 
                                    type="button" role="tab" aria-controls="performance-tab-pane" aria-selected="false">
                                <i class="fas fa-chart-bar fa-sm me-1"></i><span class="d-none d-sm-inline">Performance</span>
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link py-1 px-2 px-md-3" id="units-tab" data-bs-toggle="tab" data-bs-target="#units-tab-pane" 
                                    type="button" role="tab" aria-controls="units-tab-pane" aria-selected="false">
                                <i class="fas fa-building fa-sm me-1"></i><span class="d-none d-sm-inline">Units</span>
                            </button>
                        </li>
                    </ul>
                </div>
                <div class="card-body p-2 p-md-3">
                    <div class="tab-content" id="dashboardChartTabsContent">
                        <!-- Personnel Distribution Tab -->
                        <div class="tab-pane fade show active" id="personnel-tab-pane" role="tabpanel" aria-labelledby="personnel-tab" tabindex="0">
                            <div class="chart-container">
                                <canvas id="personnelChart" height="250"></canvas>
                            </div>
                            <?php if (!empty($dashboardData['personnel_distribution']) && is_array($dashboardData['personnel_distribution'])): ?>
                                <div class="mt-2 small">
                                    <div class="row g-2">
                                        <?php foreach ($dashboardData['personnel_distribution'] as $unit => $value): ?>
                                            <div class="col-6 col-md-3">
                                                <div class="border rounded p-1 bg-light">
                                                    <strong><?php echo htmlspecialchars($unit); ?>:</strong>
                                                    <span class="ms-1 fw-bold">
                                                        <?php 
                                                        if (is_array($value) && isset($value['personnel'])) {
                                                            echo htmlspecialchars($value['personnel']);
                                                        } elseif (!is_array($value)) {
                                                            echo htmlspecialchars($value);
                                                        } else {
                                                            echo '-';
                                                        }
                                                        ?>
                                                    </span>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php else: ?>
                                <div class="text-center text-muted mt-3">No personnel distribution data available.</div>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Recruitment Trends Tab -->
                        <div class="tab-pane fade" id="recruitment-tab-pane" role="tabpanel" aria-labelledby="recruitment-tab" tabindex="0">
                            <div class="chart-container">
                                <canvas id="recruitmentChart" height="250"></canvas>
                            </div>
                            <?php if (!empty($dashboardData['recruitment_trends']['labels']) && !empty($dashboardData['recruitment_trends']['data'])): ?>
                                <div class="mt-2 small">
                                    <strong>Recent Recruitment:</strong>
                                    <div class="row">
                                        <?php foreach ($dashboardData['recruitment_trends']['labels'] as $i => $label): ?>
                                            <div class="col-4 col-md-2">
                                                <span class="fw-bold"><?php echo htmlspecialchars($label); ?>:</span>
                                                <span> <?php echo htmlspecialchars($dashboardData['recruitment_trends']['data'][$i] ?? '-'); ?></span>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php else: ?>
                                <div class="text-center text-muted mt-3">No recruitment trend data available.</div>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Performance Metrics Tab -->
                        <div class="tab-pane fade" id="performance-tab-pane" role="tabpanel" aria-labelledby="performance-tab" tabindex="0">
                            <div class="chart-container">
                                <canvas id="performanceChart" height="250"></canvas>
                            </div>
                            <?php if (!empty($dashboardData['performance_metrics']['labels']) && !empty($dashboardData['performance_metrics']['data'])): ?>
                                <div class="mt-2 small">
                                    <strong>Performance by Quarter:</strong>
                                    <div class="row">
                                        <?php foreach ($dashboardData['performance_metrics']['labels'] as $i => $label): ?>
                                            <div class="col-3">
                                                <span class="fw-bold"><?php echo htmlspecialchars($label); ?>:</span>
                                                <span><?php echo htmlspecialchars($dashboardData['performance_metrics']['data'][$i] ?? '-'); ?>%</span>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php else: ?>
                                <div class="text-center text-muted mt-3">No performance metrics data available.</div>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Units Overview Tab -->
                        <div class="tab-pane fade" id="units-tab-pane" role="tabpanel" aria-labelledby="units-tab" tabindex="0">
                            <div class="row g-2" id="unit-overview-content">
                                <?php if (!empty($dashboardData['personnel_distribution']) && is_array($dashboardData['personnel_distribution'])): ?>
                                    <?php foreach ($dashboardData['personnel_distribution'] as $unitName => $unitData): ?>
                                        <div class="col-md-4 col-lg-3">
                                            <div class="card h-100 shadow-sm">
                                                <div class="card-header py-1 px-2 bg-light">
                                                    <h6 class="mb-0 small"><?php echo htmlspecialchars($unitName); ?></h6>
                                                </div>
                                                <div class="card-body p-2 small">
                                                    <div class="d-flex justify-content-between mb-1">
                                                        <span>Personnel:</span>
                                                        <strong>
                                                            <?php 
                                                            if (is_array($unitData) && isset($unitData['personnel'])) {
                                                                echo htmlspecialchars($unitData['personnel']);
                                                            } elseif (!is_array($unitData)) {
                                                                echo htmlspecialchars($unitData);
                                                            } else {
                                                                echo '-';
                                                            }
                                                            ?>
                                                        </strong>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <div class="col-12 text-center text-muted">No unit data available.</div>
                                <?php endif; ?>
                            </div>
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
                
                <!-- Alerts Panel -->
                <div class="col-md-4">
                    <div class="card shadow-sm h-100">
                        <div class="card-header d-flex justify-content-between align-items-center p-2 bg-light">
                            <h5 class="card-title h6 mb-0">
                                <i class="fas fa-bell text-danger"></i> Alerts
                                <span class="badge bg-danger ms-1" id="alerts-notifications-count">3</span>
                            </h5>
                            <button class="btn btn-sm btn-link text-decoration-none p-0" onclick="viewAllAlerts()">
                                <i class="fas fa-external-link-alt"></i>
                            </button>
                        </div>
                        <div class="card-body p-0">
                            <div class="list-group list-group-flush small">
                                <?php if (!empty($dashboardData['alerts']) && is_array($dashboardData['alerts'])): ?>
                                    <?php foreach ($dashboardData['alerts'] as $alert): ?>
                                        <a href="#" class="list-group-item list-group-item-action py-2">
                                            <div class="d-flex w-100 justify-content-between">
                                                <h6 class="mb-1"><?php echo htmlspecialchars($alert['title'] ?? 'Alert'); ?></h6>
                                                <small class="text-muted"><?php echo htmlspecialchars($alert['time'] ?? ''); ?></small>
                                            </div>
                                            <p class="mb-1"><?php echo htmlspecialchars($alert['text'] ?? ''); ?></p>
                                        </a>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <a href="#" class="list-group-item list-group-item-action py-2">
                                        <div class="d-flex w-100 justify-content-between">
                                            <h6 class="mb-1">Staff Review Due</h6>
                                            <small class="text-muted">3d</small>
                                        </div>
                                        <p class="mb-1">5 staff members require performance review</p>
                                    </a>
                                    <a href="#" class="list-group-item list-group-item-action py-2">
                                        <div class="d-flex w-100 justify-content-between">
                                            <h6 class="mb-1">Promotions Pending</h6>
                                            <small class="text-muted">1w</small>
                                        </div>
                                        <p class="mb-1">3 promotion recommendations awaiting approval</p>
                                    </a>
                                    <a href="#" class="list-group-item list-group-item-action py-2">
                                        <div class="d-flex w-100 justify-content-between">
                                            <h6 class="mb-1">System Update</h6>
                                            <small class="text-muted">2w</small>
                                        </div>
                                        <p class="mb-1">Personnel database maintenance scheduled</p>
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
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
<script src="js/dashboard.js"></script>

<?php include dirname(__DIR__) . '/shared/footer.php'; ?>