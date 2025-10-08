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
            $timeFilter = $_GET['filter'] ?? null;
            $startDate = $_GET['start_date'] ?? null;
            $endDate = $_GET['end_date'] ?? null;
            
            try {
                // Handle period filter
                if ($timeFilter === 'period' && $startDate && $endDate) {
                    $enhancedPersonnel = $dashboardService->getEnhancedPersonnelStatsByPeriod($startDate, $endDate);
                    $_SESSION['period_filter'] = [
                        'start_date' => $startDate,
                        'end_date' => $endDate,
                        'applied_at' => date('Y-m-d H:i:s')
                    ];
                } else {
                    // Handle existing time filters (1_month, 1_year)
                    $enhancedPersonnel = $dashboardService->getEnhancedPersonnelStats($timeFilter);
                }
                
                echo json_encode([
                    'success' => true,
                    'enhanced_personnel' => $enhancedPersonnel,
                    'filter_applied' => $timeFilter,
                    'period' => ($timeFilter === 'period') ? ['start' => $startDate, 'end' => $endDate] : null
                ]);
            } catch (Exception $e) {
                echo json_encode([
                    'success' => false,
                    'error' => $e->getMessage()
                ]);
            }
            exit;
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
        'title' => 'Seniority Rolls',
        'icon' => 'users',
        'children' => [
            ['title' => 'Officer Seniority', 'url' => '/Armis2/admin_branch/reports_seniority.php?report_type=officer'],
            ['title' => 'NCO Seniority', 'url' => '/Armis2/admin_branch/reports_nco_seniority.php?report_type=nco'],
            ['title' => 'CE Seniority', 'url' => '/Armis2/admin_branch/reports_ce_seniority.php?report_type=ce'],
        ]
    ],
    [
        'title' => 'Norminal Rolls',
        'icon' => 'bars',
        'children' => [
            ['title' => 'Officer Norminal Roll', 'url' => '/Armis2/admin_branch/reports_officer_norminal.php?report_type=officer'],
            ['title' => 'NCO Norminal Roll', 'url' => '/Armis2/admin_branch/reports_nco_norminal.php?report_type=nco'],
            ['title' => 'CE Norminal Roll', 'url' => '/Armis2/admin_branch/reports_ce_norminal.php?report_type=ce'],
        ]
    ],
    [
        'title' => 'Reports',
        'icon' => 'chart-bar',
        'page' => 'reports',
        'children' => [
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
        ]
    ],
];

// Ensure shared admin branch CSS is loaded
echo '<link rel="stylesheet" href="/Armis2/assets/css/admin_branch.css">';
echo '<link rel="stylesheet" href="css/armis-unified.css">';
include dirname(__DIR__) . '/shared/header.php';
include dirname(__DIR__) . '/shared/sidebar.php'; 
?>

<!-- ARMIS Dashboard - Unified Design System Applied -->
<style>
/* Dashboard-specific styles using unified system */
.content-wrapper {
    overflow-x: hidden;
}

/* Gender percentage styling - consistent with admin_branch design system */
.text-muted {
    color: #6c757d !important;
    font-weight: 500;
}

/* Hover effect for cells with percentages */
td:has(.text-muted):hover .text-muted {
    color: #0d6efd !important;
    font-weight: 600;
    transition: all 0.2s ease-in-out;
}

/* Apply unified table styling to existing tables */
/* To apply .armis-table styles, add both classes in HTML or copy styles here if needed. */

.table tfoot th {
    font-size: 1.25rem !important;
    font-weight: 700 !important;
    text-shadow: 0 2px 4px rgba(0,0,0,0.3);
    padding: 1rem 0.75rem !important;
}

.table-dark tfoot th {
    border: 2px solid transparent !important;
}

/* Military table styling */
.table-dark tfoot tr[style*="linear-gradient(135deg, #0d6efd"] th {
    background: var(--military-blue-gradient) !important;
    color: var(--text-white) !important;
    border-color: var(--military-blue) !important;
}

.table-dark tfoot tr[style*="linear-gradient(135deg, #0d6efd"] th[style*="color: #ffd700"] {
    color: var(--gold-accent) !important;
    font-size: 1.75rem !important;
    font-weight: 900 !important;
}

/* Civilian table styling */
.table-dark tfoot tr[style*="linear-gradient(135deg, #17a2b8"] th {
    background: var(--civilian-teal-gradient) !important;
    color: var(--text-white) !important;
    border-color: var(--civilian-teal) !important;
}

.table-dark tfoot tr[style*="linear-gradient(135deg, #17a2b8"] th[style*="color: #ffd700"] {
    color: var(--gold-accent) !important;
    font-size: 1.75rem !important;
    font-weight: 900 !important;
}

/* Responsive design */
@media (max-width: 768px) {
    .dashboard-title { font-size: 1.35rem; }
    .card-title { font-size: 0.95rem; }
    .stat-value { font-size: 1.1rem; }
    
    .personnel-card {
        min-height: 100px;
        padding: 15px 10px;
    }
    
    .personnel-card .card-value {
        font-size: 1.8rem;
    }
    
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

/* Responsive for smaller screens */
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
    
    .card-body {
        padding: 0.75rem;
    }
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
</style>


<!-- Load Chart.js early to ensure it's available for dashboard charts -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.umd.min.js"></script>

<!-- Session Management and State Restoration -->
<script src="/Armis2/assets/js/session-manager.js"></script>
<script>
    // Pass PHP session restore flag to JavaScript
    var PHP_SESSION_RESTORE_STATE = <?php echo isset($_SESSION['restore_state']) && $_SESSION['restore_state'] === true ? 'true' : 'false'; ?>;
    <?php
    // Clear the flag after passing to JavaScript
    if (isset($_SESSION['restore_state'])) {
        unset($_SESSION['restore_state']);
    }
    ?>
</script>
<script src="/Armis2/assets/js/state-restoration.js"></script>

<!-- Modern Admin Branch Dashboard -->
<div class="content-wrapper with-sidebar">
    <div class="container-fluid p-0 p-sm-2 p-md-3">
        <div class="main-content">

            <!-- Module Header -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="card border-0 shadow-sm" style="background: linear-gradient(135deg, #0d6efd 0%, #0a58ca 100%);">
                        <div class="card-body py-3">
                            <div class="d-flex align-items-center justify-content-between">
                                <div class="d-flex align-items-center">
                                    <div class="me-3" style="font-size: 2.5rem; color: #ffd700;">
                                        <i class="fas fa-<?php echo htmlspecialchars($moduleIcon); ?>"></i>
                                    </div>
                                    <div>
                                        <h2 class="mb-0 text-white" style="font-weight: 700; text-shadow: 0 2px 4px rgba(0,0,0,0.2);">
                                            <?php echo htmlspecialchars($moduleName); ?> Dashboard
                                        </h2>
                                        <p class="mb-0 text-white" style="opacity: 0.9; font-size: 0.95rem;">
                                            <i class="fas fa-calendar-alt me-1"></i>
                                            <?php echo date('l, F j, Y'); ?>
                                        </p>
                                    </div>
                                </div>
                                <div class="text-end text-white">
                                    <div style="font-size: 0.9rem; opacity: 0.9;">
                                        <i class="fas fa-user me-1"></i>
                                        Welcome, <strong><?php echo htmlspecialchars($_SESSION['user_name'] ?? 'User'); ?></strong>
                                    </div>
                                    <div style="font-size: 0.85rem; opacity: 0.8;">
                                        <i class="fas fa-shield-alt me-1"></i>
                                        <?php echo htmlspecialchars($_SESSION['user_role'] ?? 'Administrator'); ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Breadcrumbs -->
            <nav aria-label="breadcrumb" class="mb-3">
            <ol class="breadcrumb bg-light p-2 rounded shadow-sm">
                <li class="breadcrumb-item"><a href="/Armis2/admin_branch/index.php"><i class="fas fa-home"></i> Dashboard</a></li>
                <li class="breadcrumb-item"><a href="#">Admin Branch</a></li>
                <li class="breadcrumb-item active" aria-current="page">Overview</li>
            </ol>
            </nav>

            <!-- Period Filter Panel -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-white border-0 py-2">
                            <div class="d-flex align-items-center justify-content-between">
                                <h6 class="mb-0 text-primary">
                                    <i class="fas fa-filter me-2"></i>Filter by Enlistment Period
                                </h6>
                                <button class="btn btn-sm btn-link text-decoration-none" type="button" data-bs-toggle="collapse" data-bs-target="#periodFilterCollapse" aria-expanded="true" aria-controls="periodFilterCollapse">
                                    <i class="fas fa-chevron-down"></i>
                                </button>
                            </div>
                        </div>
                        <div class="collapse show" id="periodFilterCollapse">
                            <div class="card-body py-3">
                                <form id="periodFilterForm" class="row g-3 align-items-end">
                                    <!-- Quick Period Buttons -->
                                    <div class="col-md-6">
                                        <label class="form-label fw-semibold small">Quick Periods</label>
                                        <div class="btn-group w-100" role="group">
                                            <button type="button" class="btn btn-outline-primary btn-sm quick-period" data-period="30">30 Days</button>
                                            <button type="button" class="btn btn-outline-primary btn-sm quick-period" data-period="90">3 Months</button>
                                            <button type="button" class="btn btn-outline-primary btn-sm quick-period" data-period="180">6 Months</button>
                                            <button type="button" class="btn btn-outline-primary btn-sm quick-period" data-period="365">1 Year</button>
                                        </div>
                                    </div>
                                    
                                    <!-- Custom Date Range -->
                                    <div class="col-md-3">
                                        <label for="filterStartDate" class="form-label fw-semibold small">Start Date</label>
                                        <input type="date" class="form-control form-control-sm" id="filterStartDate" name="start_date">
                                    </div>
                                    <div class="col-md-3">
                                        <label for="filterEndDate" class="form-label fw-semibold small">End Date</label>
                                        <input type="date" class="form-control form-control-sm" id="filterEndDate" name="end_date" value="<?php echo date('Y-m-d'); ?>">
                                    </div>
                                    
                                    <!-- Action Buttons -->
                                    <div class="col-12">
                                        <div class="d-flex gap-2">
                                            <button type="submit" class="btn btn-primary btn-sm">
                                                <i class="fas fa-search me-1"></i>Apply Filter
                                            </button>
                                            <button type="button" class="btn btn-outline-secondary btn-sm" id="clearPeriodFilter">
                                                <i class="fas fa-times me-1"></i>Clear Filter
                                            </button>
                                            <button type="button" class="btn btn-outline-success btn-sm" id="exportFilteredData">
                                                <i class="fas fa-file-excel me-1"></i>Export Results
                                            </button>
                                        </div>
                                    </div>
                                    
                                    <!-- Active Filter Badge -->
                                    <div class="col-12" id="activeFilterBadge" style="display: none;">
                                        <div class="alert alert-info alert-dismissible fade show mb-0 py-2" role="alert">
                                            <i class="fas fa-info-circle me-2"></i>
                                            <strong>Active Filter:</strong> <span id="filterDateRange"></span>
                                            <span class="ms-3">
                                                <span id="filteredCount" class="badge bg-primary"></span>
                                            </span>
                                        </div>
                                    </div>
                                </form>
                            </div>
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
                                                            <td class="text-center" id="military-officers-male">
                                                                <?php 
                                                                $officersMale = isset($dashboardData['enhanced_personnel']['military']['officers_by_gender']['male']) ? $dashboardData['enhanced_personnel']['military']['officers_by_gender']['male'] : 0;
                                                                $officersTotal = isset($dashboardData['enhanced_personnel']['military']['officers']) ? $dashboardData['enhanced_personnel']['military']['officers'] : 0;
                                                                $officersMalePerc = $officersTotal > 0 ? round(($officersMale / $officersTotal) * 100, 1) : 0;
                                                                echo htmlspecialchars($officersMale);
                                                                if ($officersTotal > 0) {
                                                                    echo ' <small class="text-muted" style="font-size: 0.8rem;">(' . htmlspecialchars($officersMalePerc) . '%)</small>';
                                                                }
                                                                ?>
                                                            </td>
                                                            <td class="text-center" id="military-officers-female">
                                                                <?php 
                                                                $officersFemale = isset($dashboardData['enhanced_personnel']['military']['officers_by_gender']['female']) ? $dashboardData['enhanced_personnel']['military']['officers_by_gender']['female'] : 0;
                                                                $officersFemalePerc = $officersTotal > 0 ? round(($officersFemale / $officersTotal) * 100, 1) : 0;
                                                                echo htmlspecialchars($officersFemale);
                                                                if ($officersTotal > 0) {
                                                                    echo ' <small class="text-muted" style="font-size: 0.8rem;">(' . htmlspecialchars($officersFemalePerc) . '%)</small>';
                                                                }
                                                                ?>
                                                            </td>
                                                            <td class="text-center fw-bold text-primary" id="military-officers-total"><?php echo isset($dashboardData['enhanced_personnel']['military']['officers']) ? htmlspecialchars($dashboardData['enhanced_personnel']['military']['officers']) : '-'; ?></td>
                                                        </tr>
                                                        <tr class="personnel-row clickable-row" data-category="military-ncos" data-type="NCOs" style="cursor: pointer;">
                                                            <td class="ps-3 fw-semibold">NCOs</td>
                                                            <td class="text-center" id="military-ncos-male">
                                                                <?php 
                                                                $ncosMale = isset($dashboardData['enhanced_personnel']['military']['ncos_by_gender']['male']) ? $dashboardData['enhanced_personnel']['military']['ncos_by_gender']['male'] : 0;
                                                                $ncosTotal = isset($dashboardData['enhanced_personnel']['military']['ncos']) ? $dashboardData['enhanced_personnel']['military']['ncos'] : 0;
                                                                $ncosMalePerc = $ncosTotal > 0 ? round(($ncosMale / $ncosTotal) * 100, 1) : 0;
                                                                echo htmlspecialchars($ncosMale);
                                                                if ($ncosTotal > 0) {
                                                                    echo ' <small class="text-muted" style="font-size: 0.8rem;">(' . htmlspecialchars($ncosMalePerc) . '%)</small>';
                                                                }
                                                                ?>
                                                            </td>
                                                            <td class="text-center" id="military-ncos-female">
                                                                <?php 
                                                                $ncosFemale = isset($dashboardData['enhanced_personnel']['military']['ncos_by_gender']['female']) ? $dashboardData['enhanced_personnel']['military']['ncos_by_gender']['female'] : 0;
                                                                $ncosFemalePerc = $ncosTotal > 0 ? round(($ncosFemale / $ncosTotal) * 100, 1) : 0;
                                                                echo htmlspecialchars($ncosFemale);
                                                                if ($ncosTotal > 0) {
                                                                    echo ' <small class="text-muted" style="font-size: 0.8rem;">(' . htmlspecialchars($ncosFemalePerc) . '%)</small>';
                                                                }
                                                                ?>
                                                            </td>
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
                                                    <tfoot class="table-dark">
                                                        <tr class="fw-bold shadow-sm" style="background: linear-gradient(135deg, #0d6efd 0%, #084298 100%); color: white; border: 2px solid #0d6efd;">
                                                            <th class="ps-3 py-3" style="font-size: 1.25rem; text-shadow: 0 2px 4px rgba(0,0,0,0.3);">
                                                                <i class="fas fa-shield-alt me-2"></i>TOTAL MILITARY
                                                            </th>
                                                            <th class="text-center py-3" style="font-size: 1.15rem;" id="military-total-male"><?php echo isset($dashboardData['enhanced_personnel']['military']['by_gender']['male']) ? htmlspecialchars($dashboardData['enhanced_personnel']['military']['by_gender']['male']) : '-'; ?></th>
                                                            <th class="text-center py-3" style="font-size: 1.15rem;" id="military-total-female"><?php echo isset($dashboardData['enhanced_personnel']['military']['by_gender']['female']) ? htmlspecialchars($dashboardData['enhanced_personnel']['military']['by_gender']['female']) : '-'; ?></th>
                                                            <th class="text-center py-3" style="font-size: 1.75rem; font-weight: 900; text-shadow: 0 2px 4px rgba(0,0,0,0.3); color: #ffd700;" id="military-grand-total"><?php echo isset($dashboardData['enhanced_personnel']['military']['total']) ? htmlspecialchars($dashboardData['enhanced_personnel']['military']['total']) : '-'; ?></th>
                                                        </tr>
                                                        <?php 
                                                        // Calculate gender percentages for military
                                                        $militaryTotal = isset($dashboardData['enhanced_personnel']['military']['total']) ? $dashboardData['enhanced_personnel']['military']['total'] : 0;
                                                        $militaryMale = isset($dashboardData['enhanced_personnel']['military']['by_gender']['male']) ? $dashboardData['enhanced_personnel']['military']['by_gender']['male'] : 0;
                                                        $militaryFemale = isset($dashboardData['enhanced_personnel']['military']['by_gender']['female']) ? $dashboardData['enhanced_personnel']['military']['by_gender']['female'] : 0;
                                                        $malePercentage = $militaryTotal > 0 ? round(($militaryMale / $militaryTotal) * 100, 1) : 0;
                                                        $femalePercentage = $militaryTotal > 0 ? round(($militaryFemale / $militaryTotal) * 100, 1) : 0;
                                                        ?>
                                                        <tr style="background: linear-gradient(135deg, #084298 0%, #052c65 100%); color: white; border-top: 1px solid rgba(255,255,255,0.2);">
                                                            <th class="ps-3 py-2" style="font-size: 0.9rem; opacity: 0.95;">
                                                                <i class="fas fa-chart-pie me-2"></i>Gender Distribution
                                                            </th>
                                                            <th class="text-center py-2" style="font-size: 0.95rem; opacity: 0.95;">
                                                                <span class="badge" style="background: rgba(13, 110, 253, 0.3); color: #fff; font-size: 0.85rem; padding: 0.4rem 0.6rem;">
                                                                    <i class="fas fa-male me-1"></i><?php echo htmlspecialchars($malePercentage); ?>%
                                                                </span>
                                                            </th>
                                                            <th class="text-center py-2" style="font-size: 0.95rem; opacity: 0.95;">
                                                                <span class="badge" style="background: rgba(220, 53, 69, 0.3); color: #fff; font-size: 0.85rem; padding: 0.4rem 0.6rem;">
                                                                    <i class="fas fa-female me-1"></i><?php echo htmlspecialchars($femalePercentage); ?>%
                                                                </span>
                                                            </th>
                                                            <th class="text-center py-2" style="font-size: 0.9rem; opacity: 0.9;">
                                                                <small><i class="fas fa-info-circle me-1"></i>Breakdown</small>
                                                            </th>
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
                                                        <tr class="personnel-row clickable-row" data-category="civilian-current" data-type="Staff" style="cursor: pointer;">
                                                            <td class="ps-3 fw-semibold">Staff</td>
                                                            <td class="text-center" id="civilian-current-male"><?php echo isset($dashboardData['enhanced_personnel']['civilian']['current_by_gender']['male']) ? htmlspecialchars($dashboardData['enhanced_personnel']['civilian']['current_by_gender']['male']) : '-'; ?></td>
                                                            <td class="text-center" id="civilian-current-female"><?php echo isset($dashboardData['enhanced_personnel']['civilian']['current_by_gender']['female']) ? htmlspecialchars($dashboardData['enhanced_personnel']['civilian']['current_by_gender']['female']) : '-'; ?></td>
                                                            <td class="text-center fw-bold text-info" id="civilian-current-total"><?php echo isset($dashboardData['enhanced_personnel']['civilian']['active']) ? htmlspecialchars($dashboardData['enhanced_personnel']['civilian']['active']) : '-'; ?></td>
                                                        </tr>
                                                    </tbody>
                                                    <tfoot class="table-dark">
                                                        <tr class="fw-bold shadow-sm" style="background: linear-gradient(135deg, #17a2b8 0%, #138496 100%); color: white; border: 2px solid #17a2b8;">
                                                            <th class="ps-3 py-3" style="font-size: 1.25rem; text-shadow: 0 2px 4px rgba(0,0,0,0.3);">
                                                                <i class="fas fa-briefcase me-2"></i>TOTAL CIVILIAN
                                                            </th>
                                                            <th class="text-center py-3" style="font-size: 1.15rem;" id="civilian-total-male"><?php echo isset($dashboardData['enhanced_personnel']['civilian']['by_gender']['male']) ? htmlspecialchars($dashboardData['enhanced_personnel']['civilian']['by_gender']['male']) : '-'; ?></th>
                                                            <th class="text-center py-3" style="font-size: 1.15rem;" id="civilian-total-female"><?php echo isset($dashboardData['enhanced_personnel']['civilian']['by_gender']['female']) ? htmlspecialchars($dashboardData['enhanced_personnel']['civilian']['by_gender']['female']) : '-'; ?></th>
                                                            <th class="text-center py-3" style="font-size: 1.75rem; font-weight: 900; text-shadow: 0 2px 4px rgba(0,0,0,0.3); color: #ffd700;" id="civilian-grand-total"><?php echo isset($dashboardData['enhanced_personnel']['civilian']['total']) ? htmlspecialchars($dashboardData['enhanced_personnel']['civilian']['total']) : '-'; ?></th>
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
// Utility function for animated element updates (global scope)
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
                updateElementWithAnimation('#military-grand-total', ep.military.total);
                updateElementWithAnimation('#military-officers-total', ep.military.officers);
                updateElementWithAnimation('#military-ncos-total', ep.military.ncos);
                
                // Update military by gender
                if (ep.military.by_gender) {
                    updateElementWithAnimation('#military-total-male', ep.military.by_gender.male || 0);
                    updateElementWithAnimation('#military-total-female', ep.military.by_gender.female || 0);
                }
                
                // Update civilian stats with animation
                updateElementWithAnimation('#civilian-grand-total', ep.civilian.total);
                
                // Update civilian by gender
                if (ep.civilian.by_gender) {
                    updateElementWithAnimation('#civilian-total-male', ep.civilian.by_gender.male || 0);
                    updateElementWithAnimation('#civilian-total-female', ep.civilian.by_gender.female || 0);
                }
                
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

// Period Filter Functionality
function initializePeriodFilter() {
    const periodFilterForm = document.getElementById('periodFilterForm');
    const quickPeriodButtons = document.querySelectorAll('.quick-period');
    const startDateInput = document.getElementById('filterStartDate');
    const endDateInput = document.getElementById('filterEndDate');
    const clearFilterBtn = document.getElementById('clearPeriodFilter');
    const exportFilterBtn = document.getElementById('exportFilteredData');
    const activeFilterBadge = document.getElementById('activeFilterBadge');
    const filterDateRange = document.getElementById('filterDateRange');
    const filteredCount = document.getElementById('filteredCount');
    
    // Quick period button handlers
    quickPeriodButtons.forEach(btn => {
        btn.addEventListener('click', function() {
            const days = parseInt(this.dataset.period);
            const endDate = new Date();
            const startDate = new Date();
            startDate.setDate(startDate.getDate() - days);
            
            // Set input values
            startDateInput.value = formatDate(startDate);
            endDateInput.value = formatDate(endDate);
            
            // Highlight active button
            quickPeriodButtons.forEach(b => b.classList.remove('active'));
            this.classList.add('active');
            
            // Auto-submit form
            periodFilterForm.dispatchEvent(new Event('submit'));
        });
    });
    
    // Form submission handler
    periodFilterForm.addEventListener('submit', function(e) {
        e.preventDefault();
        
        const startDate = startDateInput.value;
        const endDate = endDateInput.value;
        
        if (!startDate || !endDate) {
            alert('Please select both start and end dates');
            return;
        }
        
        if (new Date(startDate) > new Date(endDate)) {
            alert('Start date cannot be after end date');
            return;
        }
        
        // Apply filter
        applyPeriodFilter(startDate, endDate);
    });
    
    // Clear filter handler
    clearFilterBtn.addEventListener('click', function() {
        startDateInput.value = '';
        endDateInput.value = formatDate(new Date());
        activeFilterBadge.style.display = 'none';
        quickPeriodButtons.forEach(b => b.classList.remove('active'));
        
        // Reload page to show all data
        window.location.href = window.location.pathname;
    });
    
    // Export filtered data handler
    exportFilterBtn.addEventListener('click', function() {
        const startDate = startDateInput.value;
        const endDate = endDateInput.value;
        
        if (!startDate || !endDate) {
            alert('Please apply a filter first before exporting');
            return;
        }
        
        // Redirect to export endpoint
        window.location.href = `export_personnel.php?start_date=${startDate}&end_date=${endDate}`;
    });
    
    function applyPeriodFilter(startDate, endDate) {
        // Show loading state
        const cards = document.querySelectorAll('.personnel-card');
        cards.forEach(card => card.classList.add('loading'));
        
        // Make AJAX request for filtered data
        fetch(`${window.location.pathname}?ajax=1&filter=period&start_date=${startDate}&end_date=${endDate}`, {
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
                    
                    // Update military stats
                    updateElementWithAnimation('#military-grand-total', ep.military.total);
                    updateElementWithAnimation('#military-officers-total', ep.military.officers);
                    updateElementWithAnimation('#military-ncos-total', ep.military.ncos);
                    
                    // Update military by gender
                    if (ep.military.by_gender) {
                        updateElementWithAnimation('#military-total-male', ep.military.by_gender.male || 0);
                        updateElementWithAnimation('#military-total-female', ep.military.by_gender.female || 0);
                    }
                    
                    // Update civilian stats
                    updateElementWithAnimation('#civilian-grand-total', ep.civilian.total);
                    
                    // Update civilian by gender
                    if (ep.civilian.by_gender) {
                        updateElementWithAnimation('#civilian-total-male', ep.civilian.by_gender.male || 0);
                        updateElementWithAnimation('#civilian-total-female', ep.civilian.by_gender.female || 0);
                    }
                    
                    // Calculate total filtered
                    const totalFiltered = ep.military.total + ep.civilian.total;
                    
                    // Show active filter badge
                    activeFilterBadge.style.display = 'block';
                    filterDateRange.textContent = `${formatDateDisplay(startDate)} to ${formatDateDisplay(endDate)}`;
                    filteredCount.textContent = `${totalFiltered} staff members`;
                    
                    // Store filter in session
                    sessionStorage.setItem('periodFilter', JSON.stringify({
                        startDate: startDate,
                        endDate: endDate,
                        count: totalFiltered
                    }));
                    
                    // Success notification (optional)
                    console.log('Period filter applied successfully');
                } else {
                    alert('Error applying filter: ' + (data.error || 'Unknown error'));
                }
            })
            .catch(error => {
                console.error('Error fetching filtered data:', error);
                alert('Failed to apply filter. Please try again.');
            })
            .finally(() => {
                // Remove loading state
                setTimeout(() => {
                    cards.forEach(card => card.classList.remove('loading'));
                }, 300);
            });
    }
    
    function formatDate(date) {
        const year = date.getFullYear();
        const month = String(date.getMonth() + 1).padStart(2, '0');
        const day = String(date.getDate()).padStart(2, '0');
        return `${year}-${month}-${day}`;
    }
    
    function formatDateDisplay(dateStr) {
        const date = new Date(dateStr);
        return date.toLocaleDateString('en-US', { 
            year: 'numeric', 
            month: 'short', 
            day: 'numeric' 
        });
    }
    
    // Restore filter from session if exists
    const savedFilter = sessionStorage.getItem('periodFilter');
    if (savedFilter) {
        try {
            const filter = JSON.parse(savedFilter);
            startDateInput.value = filter.startDate;
            endDateInput.value = filter.endDate;
            activeFilterBadge.style.display = 'block';
            filterDateRange.textContent = `${formatDateDisplay(filter.startDate)} to ${formatDateDisplay(filter.endDate)}`;
            filteredCount.textContent = `${filter.count} staff members`;
        } catch (e) {
            console.error('Error restoring filter:', e);
        }
    }
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
    // Initialize period filter
    initializePeriodFilter();
    
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
        
        // Store current category for API calls
        let currentCategory = '';
        let currentType = '';
        
        // Function to update modal content
        function updateModalContent(rowElement, category, type) {
            console.log('🔄 Updating modal content for:', type, 'Category:', category);
            
            // Store category for later use
            currentCategory = category;
            currentType = type;
            
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
                console.log('📊 Row element:', rowElement);
                console.log('📊 Category:', category, 'Type:', type);
                
                if (cells.length >= 4) {
                    // Extract text content - handle percentages in parentheses
                    const maleText = cells[1].textContent.trim();
                    const femaleText = cells[2].textContent.trim();
                    const totalText = cells[3].textContent.trim();
                    
                    // Remove percentage text if present (e.g., "15 (45.5%)" -> "15")
                    const maleCount = maleText.split('(')[0].trim();
                    const femaleCount = femaleText.split('(')[0].trim();
                    const totalCount = totalText.split('(')[0].trim();
                    
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
                        // Automatically load personnel list after modal content is updated
                        loadPersonnelList();
                    } else {
                        console.warn(`⚠️ Only ${successCount}/3 elements updated successfully`);
                    }
                } else {
                    console.error('❌ Not enough cells in row:', cells.length, 'Need at least 4 cells');
                    console.error('❌ Row HTML:', rowElement.innerHTML);
                    alert('Error: Row data format is incorrect. Please refresh the page.');
                }
            } catch (error) {
                console.error('❌ Error extracting row data:', error);
                console.error('❌ Error stack:', error.stack);
                console.error('❌ Row element:', rowElement);
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
        
        // Function to load personnel list (defined inside DOMContentLoaded for access to currentCategory)
        function loadPersonnelList() {
            console.log('📋 Loading personnel list for category:', currentCategory);
            
            const loadingEl = document.getElementById('modal-loading');
            const personnelListEl = document.getElementById('modal-personnel-list');
            const listBody = document.getElementById('personnel-list-body');
            
            // Show loading, hide list
            if (loadingEl) loadingEl.style.display = 'block';
            if (personnelListEl) personnelListEl.style.display = 'none';
            
            // Determine if this is civilian category
            const isCivilian = currentCategory.includes('civilian');
            
            // Update table headers based on category type
            const tableHeaders = document.querySelector('#modal-personnel-list thead tr');
            if (tableHeaders) {
                if (isCivilian) {
                    // Civilian: Name, Unit, Gender, Status (no rank column)
                    tableHeaders.innerHTML = `
                        <th>Name</th>
                        <th>Unit</th>
                        <th>Gender</th>
                        <th>Status</th>
                    `;
                } else {
                    // Military: Name, Rank, Unit, Gender, Status
                    tableHeaders.innerHTML = `
                        <th>Name</th>
                        <th>Rank</th>
                        <th>Unit</th>
                        <th>Gender</th>
                        <th>Status</th>
                    `;
                }
            }
            
            // Fetch real personnel data from API
            console.log('📡 Fetching personnel data for category:', currentCategory);
            
            fetch(`dashboard_api.php?action=get_personnel_by_category&category=${encodeURIComponent(currentCategory)}`)
                .then(response => response.json())
                .then(data => {
                    console.log('📊 API Response:', data);
                    console.log('📊 Data object:', data.data);
                    console.log('📊 Personnel array:', data.data?.personnel);
                    console.log('📊 Personnel count:', data.data?.count);
                    
                    // Hide loading, show list
                    if (loadingEl) loadingEl.style.display = 'none';
                    if (personnelListEl) personnelListEl.style.display = 'block';
                    
                    const colspanCount = isCivilian ? 4 : 5;
                    
                    if (data.success && data.data && data.data.personnel) {
                        const personnel = data.data.personnel;
                        
                        if (personnel.length === 0) {
                            listBody.innerHTML = `
                                <tr>
                                    <td colspan="${colspanCount}" class="text-center text-muted">
                                        <i class="fas fa-info-circle me-2"></i>No personnel found in this category.
                                    </td>
                                </tr>
                            `;
                        } else {
                            // Build table rows with real data
                            let rows = '';
                            personnel.forEach(person => {
                                const statusClass = person.status === 'Active' ? 'success' : 'secondary';
                                const unit = person.unit || 'N/A';
                                
                                if (isCivilian) {
                                    // Civilian: Name, Unit, Gender, Status
                                    rows += `
                                        <tr>
                                            <td>${person.name}</td>
                                            <td>${unit}</td>
                                            <td>${person.gender}</td>
                                            <td><span class="badge bg-${statusClass}">${person.status}</span></td>
                                        </tr>
                                    `;
                                } else {
                                    // Military: Name, Rank, Unit, Gender, Status
                                    const rank = person.rank || 'N/A';
                                    rows += `
                                        <tr>
                                            <td>${person.name}</td>
                                            <td>${rank}</td>
                                            <td>${unit}</td>
                                            <td>${person.gender}</td>
                                            <td><span class="badge bg-${statusClass}">${person.status}</span></td>
                                        </tr>
                                    `;
                                }
                            });
                            
                            listBody.innerHTML = rows;
                            console.log(`✅ Displayed ${personnel.length} personnel records`);
                        }
                    } else {
                        listBody.innerHTML = `
                            <tr>
                                <td colspan="${colspanCount}" class="text-center text-danger">
                                    <i class="fas fa-exclamation-triangle me-2"></i>
                                    ${data.message || 'Error loading personnel data'}
                                </td>
                            </tr>
                        `;
                        console.error('❌ API Error:', data.message);
                    }
                })
                .catch(error => {
                    console.error('❌ Fetch Error:', error);
                    
                    const colspanCount = isCivilian ? 4 : 5;
                    
                    if (loadingEl) loadingEl.style.display = 'none';
                    if (personnelListEl) personnelListEl.style.display = 'block';
                    
                    listBody.innerHTML = `
                        <tr>
                            <td colspan="${colspanCount}" class="text-center text-danger">
                                <i class="fas fa-exclamation-triangle me-2"></i>
                                Network error. Please check your connection and try again.
                            </td>
                        </tr>
                    `;
                });
        }
        
        // Modal action buttons functionality (defined inside DOMContentLoaded for access to currentCategory)
        function setupModalActionButtons() {
            console.log('⚙️ Setting up modal action buttons...');
            
            // View Personnel List button
            const viewListBtn = document.getElementById('view-personnel-list');
            if (viewListBtn) {
                viewListBtn.addEventListener('click', function() {
                    console.log('📋 View Personnel List button clicked');
                    loadPersonnelList();
                });
                console.log('✅ View Personnel List button handler attached');
            } else {
                console.warn('⚠️ View Personnel List button not found');
            }
            
            // Export Data button
            const exportBtn = document.getElementById('export-personnel-data');
            if (exportBtn) {
                exportBtn.addEventListener('click', function() {
                    console.log('📊 Export Data clicked for category:', currentCategory);
                    
                    const exportButton = this;
                    const originalContent = exportButton.innerHTML;
                    
                    // Show loading state
                    exportButton.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Exporting...';
                    exportButton.disabled = true;
                    
                    // Fetch data from API for export
                    fetch(`dashboard_api.php?action=get_personnel_by_category&category=${encodeURIComponent(currentCategory)}`)
                        .then(response => response.json())
                        .then(data => {
                            if (data.success && data.data && data.data.personnel) {
                                const personnel = data.data.personnel;
                                
                                // Create CSV header
                                const csvData = [['Name', 'Rank', 'Unit', 'Gender', 'Status', 'Joined Date']];
                                
                                // Add data rows
                                personnel.forEach(person => {
                                    const rank = person.rank_abbr ? `${person.rank} (${person.rank_abbr})` : person.rank;
                                    const unit = person.unit || 'N/A';
                                    const joinedDate = person.joined_date || 'N/A';
                                    
                                    csvData.push([
                                        person.name,
                                        rank,
                                        unit,
                                        person.gender,
                                        person.status,
                                        joinedDate
                                    ]);
                                });
                                
                                // Convert to CSV string (properly escape quotes)
                                const csvContent = csvData.map(row => 
                                    row.map(cell => {
                                        // Escape quotes and wrap in quotes if contains comma or quote
                                        const cellStr = String(cell);
                                        if (cellStr.includes(',') || cellStr.includes('"') || cellStr.includes('\n')) {
                                            return '"' + cellStr.replace(/"/g, '""') + '"';
                                        }
                                        return cellStr;
                                    }).join(',')
                                ).join('\n');
                                
                                // Create and download file
                                const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
                                const url = window.URL.createObjectURL(blob);
                                const a = document.createElement('a');
                                a.href = url;
                                a.download = `${currentType.replace(/[^a-zA-Z0-9]/g, '_')}_Export_${new Date().toISOString().split('T')[0]}.csv`;
                                document.body.appendChild(a);
                                a.click();
                                document.body.removeChild(a);
                                window.URL.revokeObjectURL(url);
                                
                                console.log(`✅ Exported ${personnel.length} records as CSV`);
                                
                                // Show success message
                                exportButton.innerHTML = '<i class="fas fa-check me-1"></i>Exported!';
                                exportButton.classList.remove('btn-outline-secondary');
                                exportButton.classList.add('btn-success');
                                
                                setTimeout(() => {
                                    exportButton.innerHTML = originalContent;
                                    exportButton.classList.remove('btn-success');
                                    exportButton.classList.add('btn-outline-secondary');
                                    exportButton.disabled = false;
                                }, 2000);
                            } else {
                                throw new Error(data.message || 'Failed to fetch personnel data');
                            }
                        })
                        .catch(error => {
                            console.error('❌ Export Error:', error);
                            
                            exportButton.innerHTML = '<i class="fas fa-times me-1"></i>Export Failed';
                            exportButton.classList.remove('btn-outline-secondary');
                            exportButton.classList.add('btn-danger');
                            
                            setTimeout(() => {
                                exportButton.innerHTML = originalContent;
                                exportButton.classList.remove('btn-danger');
                                exportButton.classList.add('btn-outline-secondary');
                                exportButton.disabled = false;
                            }, 2000);
                        });
                });
            }
            
            console.log('✅ Modal action buttons set up successfully!');
        }
    });
</script>
<!-- <script src="js/dashboard.js"></script> Disabled - conflicts with inline charts -->

<?php 
// Include footer and allow script loading for Chart.js
include dirname(__DIR__) . '/shared/footer.php'; 
?>