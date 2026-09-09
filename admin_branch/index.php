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

// CHANGELOG (branch-scoping upgrade): Admin Branch's dashboard already shows
// whole-Army stats with no branch filter - that's correct, since personnel
// administration is inherently army-wide (see branches.is_org_wide). What
// this dashboard didn't previously do is distinguish between roles that may
// ACT on that data (Chief Clerk/Staff Officers) and roles that should only
// OBSERVE it (Adjutant General, Director General). $__armisReadOnly drives
// which sidebar links/action buttons render below. The actual security
// boundary is unchanged by this flag - it's already enforced server-side by
// hasPermission(PERM_EDIT_STAFF/PERM_DELETE_STAFF/...) and canAlterRecord()
// inside edit_staff.php, delete_staff.php, promote_staff.php and
// assign_medal.php, so this is a UX match for an already-real restriction,
// not a new one.
$__armisReadOnly = !(function_exists('hasPermission') && hasPermission(PERM_EDIT_STAFF));

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
            $startDate = $_GET['startDate'] ?? null;
            $endDate = $_GET['endDate'] ?? null;
            
            try {
                // Handle period filter
                if ($timeFilter === 'period' && $startDate && $endDate) {
                    $enhancedPersonnel = $dashboardService->getEnhancedPersonnelStatsByPeriod($startDate, $endDate);
                    $_SESSION['period_filter'] = [
                        'startDate' => $startDate,
                        'endDate' => $endDate,
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
            'enhanced_personnel' => $dashboardService->getEnhancedPersonnelStats(), // Add enhanced stats (includes military.cont for Contract Personnel)
            'analytics' => $dashboardService->getAnalyticsData() // Add comprehensive analytics (includes officer/soldier rank distribution)
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
        'recent_activities' => [],
        'enhanced_personnel' => [
            'military' => [
                'total' => 0, 'active' => 0, 'officers' => 0, 'ncos' => 0, 'cont' => 0,
                'by_gender' => ['male' => 0, 'female' => 0],
                'officers_by_gender' => ['male' => 0, 'female' => 0],
                'ncos_by_gender' => ['male' => 0, 'female' => 0],
                'cont_by_gender' => ['male' => 0, 'female' => 0],
                'recruit_officers' => 0, 'recruit_ncos' => 0,
                'recruit_officers_by_gender' => ['male' => 0, 'female' => 0],
                'recruit_ncos_by_gender' => ['male' => 0, 'female' => 0]
            ],
            'civilian' => [
                'total' => 0, 'active' => 0,
                'by_gender' => ['male' => 0, 'female' => 0],
                'current_by_gender' => ['male' => 0, 'female' => 0]
            ]
        ],
        'analytics' => [
            'officer_rank_distribution' => ['labels' => [], 'male' => [], 'female' => [], 'total' => []],
            'soldier_rank_distribution' => ['labels' => [], 'male' => [], 'female' => [], 'total' => []]
        ]
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

require_once __DIR__ . '/includes/sidebar_nav.php';

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

/* Section headers used consistently across Total Strength Analytics and
   Detailed Analytics, so both areas of the dashboard read as one family
   of "report sections" rather than visually unrelated blocks. */
.section-subhead {
    text-transform: uppercase;
    color: #6c757d;
    font-size: 0.8rem;
    font-weight: 700;
    letter-spacing: 0.04em;
    margin-bottom: 1rem;
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

/* Quick action cards on the bottom row: subtle lift on hover so the
   dashboard doesn't feel static, consistent with the rest of the page. */
.action-card {
    transition: transform 0.15s ease, box-shadow 0.15s ease;
}

.action-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.08);
}

/* Analytics chart cards */
.analytics-chart-card {
    height: 100%;
}

.analytics-chart-card .chart-holder,
.rank-chart-holder {
    position: relative;
    height: 280px;
}

.rank-chart-holder {
    height: 320px;
}

/* Loading skeleton shown inside a chart-holder until Chart.js finishes
   drawing into the canvas (or reports "no data") - removed by
   hideChartSkeleton() in JS. Prevents a blank white card while the
   chart library and/or data are still loading. */
.chart-skeleton {
    position: absolute;
    inset: 0.5rem;
    border-radius: 0.5rem;
    background: linear-gradient(90deg, #eef0f2 25%, #f7f8f9 37%, #eef0f2 63%);
    background-size: 400% 100%;
    animation: chart-skeleton-loading 1.4s ease infinite;
}

@keyframes chart-skeleton-loading {
    0% { background-position: 100% 50%; }
    100% { background-position: 0 50%; }
}

/* Print support: hide interactive chrome and let charts/tables flow
   naturally onto the page when the person prints or exports to PDF. */
@media print {
    .no-print,
    .btn,
    .btn-group,
    .modal,
    nav.breadcrumb,
    #export-snapshot,
    .card-header .badge {
        display: none !important;
    }

    .content-wrapper {
        overflow: visible !important;
    }

    .card {
        box-shadow: none !important;
        break-inside: avoid;
    }

    .chart-holder,
    .rank-chart-holder {
        height: 260px !important;
    }
}

/* ---------------------------------------------------------------------
   Global mobile-responsive rules.
--------------------------------------------------------------------- */
@media (max-width: 768px) {
    .dashboard-title { font-size: 1.35rem; }
    .card-title { font-size: 0.95rem; }
    .stat-value { font-size: 1.1rem; }

    .col-md-6 {
        margin-bottom: 1rem;
    }

    .btn-group {
        width: 100%;
    }

    .btn-group .btn {
        flex: 1;
    }
}

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

/* ---------------------------------------------------------------------
   Personnel Snapshot: mobile-responsive rules.
   The Officers/Soldiers/Contract Personnel/Civilian tables use several
   columns of numbers which get cramped on phones. Below the sm breakpoint
   we tighten padding/font-size and let the existing .table-responsive
   wrapper handle horizontal scrolling for any row that still doesn't fit,
   rather than letting cells wrap and break the layout.
--------------------------------------------------------------------- */
@media (max-width: 767.98px) {
    /* Personnel Snapshot header actions: let the All Time/1 Month/1 Year
       segmented control take the full row width and the Export button
       wrap to its own line, instead of squeezing five controls onto one
       line next to the card title. */
    .snapshot-header-actions {
        width: 100%;
    }

    .snapshot-header-actions .btn-group-sm .btn {
        padding-left: 0.4rem;
        padding-right: 0.4rem;
        font-size: 0.72rem;
    }

    /* Military/Civilian snapshot tables: smaller type and tighter cell
       padding so all four columns (Category/Male/Female/Total) fit
       without the table forcing the card wider than the viewport. */
    .personnel-row td,
    .table-sm td,
    .table-sm th {
        padding: 0.4rem 0.35rem;
        font-size: 0.8rem;
    }

    .table-dark tfoot th {
        font-size: 0.95rem !important;
        padding: 0.6rem 0.35rem !important;
    }

    .table-dark tfoot tr th[style*="font-size: 1.75rem"] {
        font-size: 1.15rem !important;
    }

    .table-dark tfoot tr th[style*="font-size: 1.25rem"] {
        font-size: 0.95rem !important;
    }

    /* Percentage sub-text under Male/Female counts: drop to its own line
       on narrow screens instead of forcing the cell wider. */
    .personnel-row td small.text-muted {
        display: block;
        margin-top: 0.1rem;
    }

    /* Cadets/Recruits sub-rows use ps-4/ps-5 indentation designed for
       desktop; reduce it so the label doesn't crowd the number columns. */
    .table-secondary td.ps-4,
    td.ps-5 {
        padding-left: 0.75rem !important;
    }

    /* Total Strength quick-glance strip: force 2-up instead of 4-up on
       tablets/phones so each figure stays legible instead of shrinking
       to fit four across (Bootstrap's col-6/col-lg-3 already gives 2-up
       below lg, this just tidies the vertical rhythm between rows). */
    .row.g-3.mb-4 > .col-6.col-lg-3 {
        margin-bottom: 0.5rem;
    }
}

@media (max-width: 575.98px) {
    /* Below ~576px even 2-up strength cards get tight; the value itself
       (1.9rem) is the biggest offender, so scale it down slightly. */
    .row.g-3.mb-4 > .col-6.col-lg-3 div[style*="font-size: 1.9rem"] {
        font-size: 1.5rem !important;
    }
}
</style>


<!-- Load Chart.js early to ensure it's available for dashboard charts -->
<script>
    // Chart.js loader with fallback CDN. If the primary CDN is blocked
    // (corporate proxy, ad blocker, CSP, offline), silently failing here
    // means every chart on this page just spins forever waiting for
    // `Chart` to become defined. This gives it a second source and flags
    // a clear, visible failure (window.CHART_JS_LOAD_FAILED) if both fail,
    // so the polling loops below can stop and show a message instead of
    // looping in the console indefinitely.
    (function loadChartJs(sources, index) {
        index = index || 0;
        if (typeof Chart !== 'undefined') return; // already loaded
        if (index >= sources.length) {
            console.error('Chart.js failed to load from all sources:', sources);
            window.CHART_JS_LOAD_FAILED = true;
            return;
        }
        var script = document.createElement('script');
        script.src = sources[index];
        script.onload = function() {
            console.log('Chart.js loaded from', sources[index]);
        };
        script.onerror = function() {
            console.warn('Chart.js failed to load from', sources[index], '- trying next source');
            loadChartJs(sources, index + 1);
        };
        document.head.appendChild(script);
        })([
            'https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js',
            'https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.9.1/chart.min.js'
        ]);
</script>

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
                            <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
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
                                <div class="d-flex flex-wrap align-items-center gap-3">
                                    <button type="button" class="btn btn-sm btn-light no-print" id="print-dashboard" title="Print or save this dashboard as PDF">
                                        <i class="fas fa-print me-1"></i>Print / Export
                                    </button>
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
            </div>

            <!-- Breadcrumbs -->
            <nav aria-label="breadcrumb" class="mb-3">
            <ol class="breadcrumb bg-light p-2 rounded shadow-sm">
                <li class="breadcrumb-item"><a href="/Armis2/admin_branch/index.php"><i class="fas fa-home"></i> Dashboard</a></li>
                <li class="breadcrumb-item"><a href="#">Admin Branch</a></li>
                <li class="breadcrumb-item active" aria-current="page">Overview</li>
            </ol>
            </nav>

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

            <!-- =================================================================
                 SECTION 1: TOTAL STRENGTH ANALYTICS
                 Quick-glance strength strip (Officers/Soldiers/Contract
                 Personnel with gender split). This is the "answer the
                 question in one glance" section, so it leads the page.
            ================================================================== -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="card shadow-sm border-0">
                        <div class="card-header d-flex align-items-center justify-content-between" style="background: linear-gradient(135deg, #212529 0%, #343a40 100%);">
                            <div class="d-flex align-items-center text-white">
                                <i class="fas fa-chart-bar me-2" style="color: #ffd700;"></i>
                                <h5 class="mb-0">Total Strength Analytics</h5>
                            </div>
                            <span class="badge bg-light text-dark" style="font-size: 0.7rem;">
                                <i class="fas fa-sync-alt me-1"></i>Live
                            </span>
                        </div>
                        <div class="card-body">
                            <?php
                            // Quick-glance strength strip: Officers / Soldiers / Contract Personnel
                            // with gender split. Figures mirror the Military Personnel table below;
                            // shown together here for at-a-glance strength composition.
                            $osOfficersTotal = $dashboardData['enhanced_personnel']['military']['officers'] ?? 0;
                            $osOfficersMale = $dashboardData['enhanced_personnel']['military']['officers_by_gender']['male'] ?? 0;
                            $osOfficersFemale = $dashboardData['enhanced_personnel']['military']['officers_by_gender']['female'] ?? 0;

                            $osSoldiersTotal = $dashboardData['enhanced_personnel']['military']['ncos'] ?? 0;
                            $osSoldiersMale = $dashboardData['enhanced_personnel']['military']['ncos_by_gender']['male'] ?? 0;
                            $osSoldiersFemale = $dashboardData['enhanced_personnel']['military']['ncos_by_gender']['female'] ?? 0;

                            $osContractTotal = $dashboardData['enhanced_personnel']['military']['cont'] ?? 0;
                            $osContractMale = $dashboardData['enhanced_personnel']['military']['cont_by_gender']['male'] ?? 0;
                            $osContractFemale = $dashboardData['enhanced_personnel']['military']['cont_by_gender']['female'] ?? 0;

                            $osTotalStrength = $dashboardData['enhanced_personnel']['military']['total'] ?? 0;

                            function strengthPct($part, $total) {
                                return $total > 0 ? round(($part / $total) * 100, 1) : 0;
                            }
                            ?>
                            <div class="row g-3 mb-4">
                                <div class="col-6 col-lg-3">
                                    <div class="p-3 rounded h-100" style="background: linear-gradient(135deg, #0d6efd 0%, #084298 100%); color: #fff;">
                                        <div style="font-size: 0.75rem; opacity: 0.85; text-transform: uppercase; letter-spacing: 0.03em;">Total Strength</div>
                                        <div style="font-size: 1.9rem; font-weight: 800;"><?php echo htmlspecialchars($osTotalStrength); ?></div>
                                        <div style="font-size: 0.75rem; opacity: 0.85;"><i class="fas fa-shield-alt me-1"></i>All Military Personnel</div>
                                    </div>
                                </div>
                                <div class="col-6 col-lg-3">
                                    <div class="p-3 rounded h-100 border">
                                        <div style="font-size: 0.75rem; color: #6c757d; text-transform: uppercase; letter-spacing: 0.03em;">Officers</div>
                                        <div style="font-size: 1.9rem; font-weight: 800; color: #0d6efd;"><?php echo htmlspecialchars($osOfficersTotal); ?></div>
                                        <div style="font-size: 0.75rem; color: #6c757d;">
                                            <i class="fas fa-male me-1"></i><?php echo htmlspecialchars($osOfficersMale); ?> (<?php echo strengthPct($osOfficersMale, $osOfficersTotal); ?>%)
                                            &nbsp;<i class="fas fa-female me-1"></i><?php echo htmlspecialchars($osOfficersFemale); ?> (<?php echo strengthPct($osOfficersFemale, $osOfficersTotal); ?>%)
                                        </div>
                                    </div>
                                </div>
                                <div class="col-6 col-lg-3">
                                    <div class="p-3 rounded h-100 border">
                                        <div style="font-size: 0.75rem; color: #6c757d; text-transform: uppercase; letter-spacing: 0.03em;">Soldiers</div>
                                        <div style="font-size: 1.9rem; font-weight: 800; color: #17a2b8;"><?php echo htmlspecialchars($osSoldiersTotal); ?></div>
                                        <div style="font-size: 0.75rem; color: #6c757d;">
                                            <i class="fas fa-male me-1"></i><?php echo htmlspecialchars($osSoldiersMale); ?> (<?php echo strengthPct($osSoldiersMale, $osSoldiersTotal); ?>%)
                                            &nbsp;<i class="fas fa-female me-1"></i><?php echo htmlspecialchars($osSoldiersFemale); ?> (<?php echo strengthPct($osSoldiersFemale, $osSoldiersTotal); ?>%)
                                        </div>
                                    </div>
                                </div>
                                <div class="col-6 col-lg-3">
                                    <div class="p-3 rounded h-100 border" style="border-color: #ffc107 !important;">
                                        <div style="font-size: 0.75rem; color: #6c757d; text-transform: uppercase; letter-spacing: 0.03em;">
                                            Contract Personnel <i class="fas fa-info-circle" title="Subset of Officers/Soldiers currently on contract status"></i>
                                        </div>
                                        <div style="font-size: 1.9rem; font-weight: 800; color: #b8860b;" id="strength-contract-total"><?php echo htmlspecialchars($osContractTotal); ?></div>
                                        <div style="font-size: 0.75rem; color: #6c757d;" id="strength-contract-breakdown">
                                            <i class="fas fa-male me-1"></i><?php echo htmlspecialchars($osContractMale); ?> (<?php echo strengthPct($osContractMale, $osContractTotal); ?>%)
                                            &nbsp;<i class="fas fa-female me-1"></i><?php echo htmlspecialchars($osContractFemale); ?> (<?php echo strengthPct($osContractFemale, $osContractTotal); ?>%)
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <!-- END Total Strength Analytics -->

            <!-- =================================================================
                 SECTION 2: PERSONNEL SNAPSHOT
                 Full Military/Civilian tables with Male/Female/Total breakdown,
                 filterable by time period and exportable to CSV.
            ================================================================== -->
            <div class="row mb-3">
                <div class="col-12">
                    <div class="card shadow-sm mb-2">
                        <div class="card-header bg-light py-2 d-flex flex-wrap justify-content-between align-items-center gap-2">
                            <div class="d-flex flex-wrap align-items-center gap-2">
                                <h5 class="mb-0">Personnel Snapshot</h5>
                                <a href="/Armis2/admin_branch/reports_rank.php" class="btn btn-sm btn-outline-secondary" title="Rank breakdown">Rank breakdown</a>
                            </div>
                            <div class="d-flex flex-wrap align-items-center gap-2 snapshot-header-actions">
                                <div class="btn-group btn-group-sm flex-grow-1 flex-sm-grow-0" role="group" aria-label="Filter Options">
                                    <input type="radio" class="btn-check" name="personnel-filter" id="filter-all" value="all" checked>
                                    <label class="btn btn-outline-primary" for="filter-all">All Time</label>

                                    <input type="radio" class="btn-check" name="personnel-filter" id="filter-1month" value="1_month">
                                    <label class="btn btn-outline-primary" for="filter-1month">1 Month</label>

                                    <input type="radio" class="btn-check" name="personnel-filter" id="filter-1year" value="1_year">
                                    <label class="btn btn-outline-primary" for="filter-1year">1 Year</label>
                                </div>
                                <button type="button" class="btn btn-outline-success btn-sm" id="export-snapshot" title="Export Personnel Snapshot">
                                    <i class="fas fa-download"></i>
                                    <span class="d-none d-md-inline ms-1">Export</span>
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
                                                            <td class="text-center fw-bold text-primary snapshot-count" id="military-officers-total"><?php echo isset($dashboardData['enhanced_personnel']['military']['officers']) ? htmlspecialchars($dashboardData['enhanced_personnel']['military']['officers']) : '-'; ?></td>
                                                        </tr>
                                                        <tr class="personnel-row clickable-row" data-category="military-ncos" data-type="NCOs" style="cursor: pointer;">
                                                            <td class="ps-3 fw-semibold">Soldiers</td>
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
                                                            <td class="text-center fw-bold text-primary snapshot-count" id="military-ncos-total"><?php echo isset($dashboardData['enhanced_personnel']['military']['ncos']) ? htmlspecialchars($dashboardData['enhanced_personnel']['military']['ncos']) : '-'; ?></td>
                                                        </tr>
                                                        <tr class="personnel-row clickable-row" data-category="military-cont" data-type="Contract Personnel" style="cursor: pointer;">
                                                            <td class="ps-3 fw-semibold">
                                                                Contract Personnel
                                                                <span class="badge bg-warning text-dark ms-1" style="font-size: 0.65rem;">On Contract</span>
                                                            </td>
                                                            <td class="text-center" id="military-cont-male">
                                                                <?php
                                                                $contMale = isset($dashboardData['enhanced_personnel']['military']['cont_by_gender']['male']) ? $dashboardData['enhanced_personnel']['military']['cont_by_gender']['male'] : 0;
                                                                $contTotal = isset($dashboardData['enhanced_personnel']['military']['cont']) ? $dashboardData['enhanced_personnel']['military']['cont'] : 0;
                                                                $contMalePerc = $contTotal > 0 ? round(($contMale / $contTotal) * 100, 1) : 0;
                                                                echo htmlspecialchars($contMale);
                                                                if ($contTotal > 0) {
                                                                    echo ' <small class="text-muted" style="font-size: 0.8rem;">(' . htmlspecialchars($contMalePerc) . '%)</small>';
                                                                }
                                                                ?>
                                                            </td>
                                                            <td class="text-center" id="military-cont-female">
                                                                <?php
                                                                $contFemale = isset($dashboardData['enhanced_personnel']['military']['cont_by_gender']['female']) ? $dashboardData['enhanced_personnel']['military']['cont_by_gender']['female'] : 0;
                                                                $contFemalePerc = $contTotal > 0 ? round(($contFemale / $contTotal) * 100, 1) : 0;
                                                                echo htmlspecialchars($contFemale);
                                                                if ($contTotal > 0) {
                                                                    echo ' <small class="text-muted" style="font-size: 0.8rem;">(' . htmlspecialchars($contFemalePerc) . '%)</small>';
                                                                }
                                                                ?>
                                                            </td>
                                                            <td class="text-center fw-bold text-warning-emphasis snapshot-count" id="military-cont-total"><?php echo isset($dashboardData['enhanced_personnel']['military']['cont']) ? htmlspecialchars($dashboardData['enhanced_personnel']['military']['cont']) : '-'; ?></td>
                                                        </tr>
                                                        <tr class="table-secondary">
                                                            <td class="ps-4 fw-semibold"><em>Cadets/ Recruits</em></td>
                                                            <td colspan="3" class="text-center fw-semibold"></td>
                                                        </tr>
                                                        <tr>
                                                            <td class="ps-5">├ Officer Cadets</td>
                                                            <td class="text-center" id="recruit-officers-male"><?php echo isset($dashboardData['enhanced_personnel']['military']['recruit_officers_by_gender']['male']) ? htmlspecialchars($dashboardData['enhanced_personnel']['military']['recruit_officers_by_gender']['male']) : '-'; ?></td>
                                                            <td class="text-center" id="recruit-officers-female"><?php echo isset($dashboardData['enhanced_personnel']['military']['recruit_officers_by_gender']['female']) ? htmlspecialchars($dashboardData['enhanced_personnel']['military']['recruit_officers_by_gender']['female']) : '-'; ?></td>
                                                            <td class="text-center fw-bold text-success snapshot-count" id="recruit-officers-total"><?php echo isset($dashboardData['enhanced_personnel']['military']['recruit_officers']) ? htmlspecialchars($dashboardData['enhanced_personnel']['military']['recruit_officers']) : '-'; ?></td>
                                                        </tr>
                                                        <tr>
                                                            <td class="ps-5">└ Recruits</td>
                                                            <td class="text-center" id="recruit-ncos-male"><?php echo isset($dashboardData['enhanced_personnel']['military']['recruit_ncos_by_gender']['male']) ? htmlspecialchars($dashboardData['enhanced_personnel']['military']['recruit_ncos_by_gender']['male']) : '-'; ?></td>
                                                            <td class="text-center" id="recruit-ncos-female"><?php echo isset($dashboardData['enhanced_personnel']['military']['recruit_ncos_by_gender']['female']) ? htmlspecialchars($dashboardData['enhanced_personnel']['military']['recruit_ncos_by_gender']['female']) : '-'; ?></td>
                                                            <td class="text-center fw-bold text-success snapshot-count" id="recruit-ncos-total"><?php echo isset($dashboardData['enhanced_personnel']['military']['recruit_ncos']) ? htmlspecialchars($dashboardData['enhanced_personnel']['military']['recruit_ncos']) : '-'; ?></td>
                                                        </tr>
                                                    </tbody>
                                                    <tfoot class="table-dark">
                                                        <tr class="fw-bold shadow-sm" style="background: linear-gradient(135deg, #0d6efd 0%, #084298 100%); color: white; border: 2px solid #0d6efd;">
                                                            <th class="ps-3 py-3" style="font-size: 1.25rem; text-shadow: 0 2px 4px rgba(0,0,0,0.3);">
                                                                <i class="fas fa-shield-alt me-2"></i>TOTAL MILITARY
                                                            </th>
                                                            <th class="text-center py-3" style="font-size: 1.15rem;" id="military-total-male"><?php echo isset($dashboardData['enhanced_personnel']['military']['by_gender']['male']) ? htmlspecialchars($dashboardData['enhanced_personnel']['military']['by_gender']['male']) : '-'; ?></th>
                                                            <th class="text-center py-3" style="font-size: 1.15rem;" id="military-total-female"><?php echo isset($dashboardData['enhanced_personnel']['military']['by_gender']['female']) ? htmlspecialchars($dashboardData['enhanced_personnel']['military']['by_gender']['female']) : '-'; ?></th>
                                                            <th class="text-center py-3" style="font-size: 1.75rem; font-weight: 900; text-shadow: 0 2px 4px rgba(0,0,0,0.3); color: #ffd700;" id="military-grand-total"><span class="snapshot-count"><?php echo isset($dashboardData['enhanced_personnel']['military']['total']) ? htmlspecialchars($dashboardData['enhanced_personnel']['military']['total']) : '-'; ?></span></th>
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
                                            <div class="px-3 py-2 border-top" style="font-size: 0.75rem; color: #6c757d; background: #f8f9fa;">
                                                <i class="fas fa-info-circle me-1"></i>
                                                "Contract Personnel" is a status held by some Officers and Soldiers, not a separate rank tier &mdash; it's shown here for visibility and is already included in the Officers/Soldiers rows above and in TOTAL MILITARY.
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
            <!-- END Personnel Snapshot -->

            <!-- =================================================================
                 SECTION 3: RANK DISTRIBUTION BY GENDER
            ================================================================== -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="card shadow-sm border-0">
                        <div class="card-header bg-white d-flex align-items-center">
                            <i class="fas fa-medal text-primary me-2"></i>
                            <h5 class="mb-0">Rank Distribution by Gender</h5>
                        </div>
                        <div class="card-body">
                            <div class="row g-3">
                                <div class="col-lg-6">
                                    <div class="card h-100 border-primary">
                                        <div class="card-header bg-primary text-white py-2">
                                            <i class="fas fa-shield-alt me-2"></i>Officers (Gen &ndash; OCdt)
                                        </div>
                                        <div class="card-body">
                                            <div class="rank-chart-holder">
                                                <div class="chart-skeleton"></div>
                                                <canvas id="officerRankChart" role="img" aria-label="Officer rank distribution by gender chart"></canvas>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-lg-6">
                                    <div class="card h-100 border-info">
                                        <div class="card-header bg-info text-white py-2">
                                            <i class="fas fa-users me-2"></i>Soldiers (WOI &ndash; Pte)
                                        </div>
                                        <div class="card-body">
                                            <div class="rank-chart-holder">
                                                <div class="chart-skeleton"></div>
                                                <canvas id="soldierRankChart" role="img" aria-label="Soldier rank distribution by gender chart"></canvas>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <!-- END Rank Distribution by Gender -->

            <!-- =================================================================
                 SECTION 4: DETAILED ANALYTICS
                 Organization and Demographics charts. Personnel Composition,
                 an overall Gender chart, and Recruitment/Performance Trends
                 were intentionally left out of this section: the first two
                 duplicate figures already shown in the Personnel Snapshot and
                 Total Strength tables above, and Trends had no reliable
                 backing data in this deployment. Re-add them here (matching
                 the same card markup as Units/Corps below) if you want them
                 back - the underlying JS (initPersonnelChart, initGenderChart,
                 initRecruitmentChart, initPerformanceChart) is already wired
                 up and only needs a <canvas> to render into.
            ================================================================== -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="card shadow-sm border-0">
                        <div class="card-header bg-white d-flex align-items-center justify-content-between">
                            <div class="d-flex align-items-center">
                                <i class="fas fa-chart-pie text-primary me-2"></i>
                                <h5 class="mb-0">Detailed Analytics</h5>
                            </div>
                            <small class="text-muted d-none d-sm-inline"><i class="fas fa-mouse-pointer me-1"></i>Click any chart for exact figures</small>
                        </div>
                        <div class="card-body">

                            <div class="section-subhead"><i class="fas fa-sitemap me-1"></i>Organization</div>
                            <div class="row g-4 mb-4">
                                <div class="col-lg-6">
                                    <div class="card h-100 border-0 shadow-sm analytics-chart-card">
                                        <div class="card-header bg-light py-2"><i class="fas fa-building me-2 text-success"></i>Unit Distribution</div>
                                        <div class="card-body chart-holder">
                                            <div class="chart-skeleton"></div>
                                            <canvas id="unitsChart" role="img" aria-label="Personnel distribution by unit chart"></canvas>
                                        </div>
                                        <div class="card-footer bg-white py-1 text-end small text-muted" id="unitsChart-total">&nbsp;</div>
                                    </div>
                                </div>
                                <div class="col-lg-6">
                                    <div class="card h-100 border-0 shadow-sm analytics-chart-card">
                                        <div class="card-header bg-light py-2"><i class="fas fa-shield-alt me-2 text-info"></i>Corps Distribution</div>
                                        <div class="card-body chart-holder">
                                            <div class="chart-skeleton"></div>
                                            <canvas id="corpsChart" role="img" aria-label="Personnel distribution by corps chart"></canvas>
                                        </div>
                                        <div class="card-footer bg-white py-1 text-end small text-muted" id="corpsChart-total">&nbsp;</div>
                                    </div>
                                </div>
                            </div>

                            <div class="section-subhead"><i class="fas fa-id-card me-1"></i>Demographics</div>
                            <div class="row g-4">
                                <div class="col-lg-4 col-md-6">
                                    <div class="card h-100 border-0 shadow-sm analytics-chart-card">
                                        <div class="card-header bg-light py-2"><i class="fas fa-birthday-cake me-2 text-warning"></i>Age Distribution</div>
                                        <div class="card-body chart-holder">
                                            <div class="chart-skeleton"></div>
                                            <canvas id="ageChart" role="img" aria-label="Age distribution chart"></canvas>
                                        </div>
                                        <div class="card-footer bg-white py-1 text-end small text-muted" id="ageChart-total">&nbsp;</div>
                                    </div>
                                </div>
                                <div class="col-lg-4 col-md-6">
                                    <div class="card h-100 border-0 shadow-sm analytics-chart-card">
                                        <div class="card-header bg-light py-2"><i class="fas fa-hourglass-half me-2 text-secondary"></i>Service Length</div>
                                        <div class="card-body chart-holder">
                                            <div class="chart-skeleton"></div>
                                            <canvas id="serviceLengthChart" role="img" aria-label="Service length distribution chart"></canvas>
                                        </div>
                                        <div class="card-footer bg-white py-1 text-end small text-muted" id="serviceLengthChart-total">&nbsp;</div>
                                    </div>
                                </div>
                                <div class="col-lg-4 col-md-6">
                                    <div class="card h-100 border-0 shadow-sm analytics-chart-card">
                                        <div class="card-header bg-light py-2"><i class="fas fa-ring me-2 text-danger"></i>Marital Status</div>
                                        <div class="card-body chart-holder">
                                            <div class="chart-skeleton"></div>
                                            <canvas id="maritalChart" role="img" aria-label="Marital status distribution chart"></canvas>
                                        </div>
                                        <div class="card-footer bg-white py-1 text-end small text-muted" id="maritalChart-total">&nbsp;</div>
                                    </div>
                                </div>
                            </div>

                        </div>
                    </div>
                </div>
            </div>
            <!-- END Detailed Analytics -->

            <!-- Chart Drilldown Modal -->
            <div class="modal fade" id="chartDrilldownModal" tabindex="-1" aria-labelledby="chartDrilldownModalLabel" aria-hidden="true">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="chartDrilldownModalLabel"><i class="fas fa-chart-pie me-2"></i>Breakdown</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <div id="chartDrilldownSubtitle" class="text-muted small mb-2"></div>
                            <div class="table-responsive">
                                <table class="table table-sm mb-0">
                                    <tbody id="chartDrilldownBody"></tbody>
                                </table>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <a href="#" id="chartDrilldownLink" class="btn btn-sm btn-outline-primary d-none" target="_blank" rel="noopener">
                                <i class="fas fa-external-link-alt me-1"></i>View Full Report
                            </a>
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
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
                                            <!-- Percentage breakdown for accessibility and quick glance -->
                                            <div class="row text-center mt-2">
                                                <div class="col-6">
                                                    <small class="text-muted">Male %</small>
                                                    <div id="modal-male-pct" class="fw-semibold">-</div>
                                                </div>
                                                <div class="col-6">
                                                    <small class="text-muted">Female %</small>
                                                    <div id="modal-female-pct" class="fw-semibold">-</div>
                                                </div>
                                            </div>
                                            <!-- ARIA live summary for screen readers -->
                                            <div id="modal-accessible-summary" class="visually-hidden" aria-live="polite" aria-atomic="true"></div>
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

            <!-- =================================================================
                 SECTION 5: QUICK ACTIONS
            ================================================================== -->
            <div class="row g-3 mb-4">
                <div class="col-12">
                    <div class="card shadow-sm h-100">
                        <div class="card-header d-flex justify-content-between align-items-center p-2 bg-light">
                            <h5 class="card-title h6 mb-0">
                                <i class="fas fa-bolt text-warning"></i> Quick Actions
                            </h5>
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
            </div>
            <!-- END Quick Actions -->

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
    const filterRadios = document.querySelectorAll('input[name="personnel-filter"]');

    filterRadios.forEach(radio => {
        radio.addEventListener('change', function() {
            if (this.checked) {
                updatePersonnelData(this.value);
            }
        });
    });

    function updatePersonnelData(timeFilter) {
        const cards = document.querySelectorAll('.personnel-card');
        cards.forEach(card => {
            card.classList.add('loading');
            const valueElement = card.querySelector('.card-value');
            if (valueElement) {
                valueElement.style.transition = 'opacity 0.3s ease';
                valueElement.style.opacity = '0.5';
            }
        });

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

                updateElementWithAnimation('#military-grand-total', ep.military.total);
                updateElementWithAnimation('#military-officers-total', ep.military.officers);
                updateElementWithAnimation('#military-ncos-total', ep.military.ncos);

                if (ep.military.by_gender) {
                    updateElementWithAnimation('#military-total-male', ep.military.by_gender.male || 0);
                    updateElementWithAnimation('#military-total-female', ep.military.by_gender.female || 0);
                }

                updateElementWithAnimation('#civilian-grand-total', ep.civilian.total);

                if (ep.civilian.by_gender) {
                    updateElementWithAnimation('#civilian-total-male', ep.civilian.by_gender.male || 0);
                    updateElementWithAnimation('#civilian-total-female', ep.civilian.by_gender.female || 0);
                }

                highlightFilteredMetrics(timeFilter);
            }
        })
        .catch(error => {
            console.error('Error fetching personnel data:', error);
        })
        .finally(() => {
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
        document.querySelectorAll('.highlight-filter').forEach(el => {
            el.classList.remove('highlight-filter', 'bg-warning', 'text-dark');
        });

        let targetCard = null;
        if (timeFilter === '1_month') {
            const el = document.querySelector('#civilian-new-1month');
            targetCard = el ? el.closest('.personnel-card') : null;
        } else if (timeFilter === '1_year') {
            const el = document.querySelector('#civilian-new-1year');
            targetCard = el ? el.closest('.personnel-card') : null;
        }

        if (targetCard) {
            targetCard.classList.add('highlight-filter');
            targetCard.style.animation = 'highlightPulse 1s ease-in-out';

            setTimeout(() => {
                targetCard.style.animation = '';
            }, 1000);
        }
    }

    // Add CSS animations used by the filter highlight above
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
    `;
    document.head.appendChild(animationCSS);
}

// Period Filter Functionality (only wires up if the period-filter UI is
// actually present on the page - it isn't part of this render, but the
// function stays defensive in case it's added back later)
function initializePeriodFilter() {
    const periodFilterForm = document.getElementById('periodFilterForm');
    if (!periodFilterForm) {
        return;
    }

    const quickPeriodButtons = document.querySelectorAll('.quick-period');
    const startDateInput = document.getElementById('filterStartDate');
    const endDateInput = document.getElementById('filterEndDate');
    const clearFilterBtn = document.getElementById('clearPeriodFilter');
    const exportFilterBtn = document.getElementById('exportFilteredData');
    const activeFilterBadge = document.getElementById('activeFilterBadge');
    const filterDateRange = document.getElementById('filterDateRange');
    const filteredCount = document.getElementById('filteredCount');

    quickPeriodButtons.forEach(btn => {
        btn.addEventListener('click', function() {
            const days = parseInt(this.dataset.period);
            const endDate = new Date();
            const startDate = new Date();
            startDate.setDate(startDate.getDate() - days);

            startDateInput.value = formatDate(startDate);
            endDateInput.value = formatDate(endDate);

            quickPeriodButtons.forEach(b => b.classList.remove('active'));
            this.classList.add('active');

            periodFilterForm.dispatchEvent(new Event('submit'));
        });
    });

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

        applyPeriodFilter(startDate, endDate);
    });

    clearFilterBtn.addEventListener('click', function() {
        startDateInput.value = '';
        endDateInput.value = formatDate(new Date());
        activeFilterBadge.style.display = 'none';
        quickPeriodButtons.forEach(b => b.classList.remove('active'));
        window.location.href = window.location.pathname;
    });

    exportFilterBtn.addEventListener('click', function() {
        const startDate = startDateInput.value;
        const endDate = endDateInput.value;

        if (!startDate || !endDate) {
            alert('Please apply a filter first before exporting');
            return;
        }

        window.location.href = `export_personnel.php?startDate=${startDate}&endDate=${endDate}`;
    });

    function applyPeriodFilter(startDate, endDate) {
        const cards = document.querySelectorAll('.personnel-card');
        cards.forEach(card => card.classList.add('loading'));

        fetch(`${window.location.pathname}?ajax=1&filter=period&startDate=${startDate}&endDate=${endDate}`, {
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

                updateElementWithAnimation('#military-grand-total', ep.military.total);
                updateElementWithAnimation('#military-officers-total', ep.military.officers);
                updateElementWithAnimation('#military-ncos-total', ep.military.ncos);

                if (ep.military.by_gender) {
                    updateElementWithAnimation('#military-total-male', ep.military.by_gender.male || 0);
                    updateElementWithAnimation('#military-total-female', ep.military.by_gender.female || 0);
                }

                updateElementWithAnimation('#civilian-grand-total', ep.civilian.total);

                if (ep.civilian.by_gender) {
                    updateElementWithAnimation('#civilian-total-male', ep.civilian.by_gender.male || 0);
                    updateElementWithAnimation('#civilian-total-female', ep.civilian.by_gender.female || 0);
                }

                const totalFiltered = ep.military.total + ep.civilian.total;

                activeFilterBadge.style.display = 'block';
                filterDateRange.textContent = `${formatDateDisplay(startDate)} to ${formatDateDisplay(endDate)}`;
                filteredCount.textContent = `${totalFiltered} staff members`;

                sessionStorage.setItem('periodFilter', JSON.stringify({
                    startDate: startDate,
                    endDate: endDate,
                    count: totalFiltered
                }));
            } else {
                alert('Error applying filter: ' + (data.error || 'Unknown error'));
            }
        })
        .catch(error => {
            console.error('Error fetching filtered data:', error);
            alert('Failed to apply filter. Please try again.');
        })
        .finally(() => {
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

// =========================================================================
// CHART DRILLDOWN SUPPORT
// Shared helpers + click handlers that open #chartDrilldownModal with the
// exact numbers for whatever was clicked.
// =========================================================================

let chartDrilldownModalInstance = null;

function getChartDrilldownModal() {
    if (chartDrilldownModalInstance) return chartDrilldownModalInstance;
    const el = document.getElementById('chartDrilldownModal');
    if (!el || typeof bootstrap === 'undefined') return null;
    chartDrilldownModalInstance = new bootstrap.Modal(el);
    return chartDrilldownModalInstance;
}

function escapeHtmlDrilldown(value) {
    return String(value ?? '').replace(/[&<>"']/g, function (char) {
        return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[char];
    });
}

function showChartDrilldown(title, subtitle, rows, opts) {
    opts = opts || {};
    const showShare = opts.showShare !== false;
    const suffix = opts.suffix || '';

    const modal = getChartDrilldownModal();
    if (!modal) {
        const text = rows.map(r => `${r.label}: ${r.value}${suffix}`).join('\n');
        alert(`${title}\n${subtitle}\n\n${text}`);
        return;
    }

    document.getElementById('chartDrilldownModalLabel').innerHTML =
        `<i class="fas fa-chart-pie me-2"></i>${escapeHtmlDrilldown(title)}`;
    document.getElementById('chartDrilldownSubtitle').textContent = subtitle || '';

    const total = rows.reduce((sum, r) => sum + (Number(r.value) || 0), 0);

    document.getElementById('chartDrilldownBody').innerHTML = rows.map(r => {
        const value = Number(r.value) || 0;
        const pct = total > 0 ? ((value / total) * 100).toFixed(1) : '0.0';
        const swatch = r.color
            ? `<span class="d-inline-block me-2" style="width:10px;height:10px;border-radius:2px;background:${escapeHtmlDrilldown(r.color)};"></span>`
            : '';
        const shareCell = showShare
            ? `<td class="text-end text-muted" style="width:70px;">${pct}%</td>`
            : '';
        return `
            <tr${r.highlight ? ' class="table-active"' : ''}>
                <td>${swatch}${escapeHtmlDrilldown(r.label)}</td>
                <td class="text-end fw-semibold">${value.toLocaleString()}${suffix}</td>
                ${shareCell}
            </tr>`;
    }).join('');

    const link = document.getElementById('chartDrilldownLink');
    if (opts.reportUrl) {
        link.href = opts.reportUrl;
        link.classList.remove('d-none');
    } else {
        link.classList.add('d-none');
    }

    modal.show();
}

function chartDrilldownOnHover(evt, elements) {
    if (evt && evt.native && evt.native.target) {
        evt.native.target.style.cursor = elements.length ? 'pointer' : 'default';
    }
}

function chartDrilldownOnClickSingleSeries(title, reportUrlBuilder, opts) {
    return function (evt, elements, chart) {
        if (!elements || elements.length === 0) return;

        const clickedIdx = elements[0].index;
        const dataset = chart.data.datasets[0];
        const colors = Array.isArray(dataset.backgroundColor) ? dataset.backgroundColor : null;

        const rows = chart.data.labels.map((label, i) => ({
            label: label,
            value: dataset.data[i],
            color: colors ? colors[i] : dataset.backgroundColor,
            highlight: i === clickedIdx
        }));

        const clickedLabel = chart.data.labels[clickedIdx];
        const reportUrl = typeof reportUrlBuilder === 'function' ? reportUrlBuilder(clickedLabel) : null;

        showChartDrilldown(title, `Selected: ${clickedLabel}`, rows, Object.assign({ reportUrl }, opts));
    };
}

function chartDrilldownOnClickMultiSeries(title, reportUrlBuilder, opts) {
    return function (evt, elements, chart) {
        if (!elements || elements.length === 0) return;

        const clickedIdx = elements[0].index;
        const label = chart.data.labels[clickedIdx];

        const rows = chart.data.datasets.map(ds => ({
            label: ds.label,
            value: ds.data[clickedIdx] || 0,
            color: Array.isArray(ds.backgroundColor) ? ds.backgroundColor[clickedIdx] : ds.backgroundColor
        }));

        const total = rows.reduce((sum, r) => sum + (Number(r.value) || 0), 0);
        rows.push({ label: 'Total', value: total, color: '#6c757d', highlight: true });

        const reportUrl = typeof reportUrlBuilder === 'function' ? reportUrlBuilder(label) : null;

        showChartDrilldown(title, `Rank: ${label}`, rows, Object.assign({ reportUrl, showShare: false }, opts));
    };
}

function officerRankReportUrl() {
    return '/Armis2/admin_branch/reports_rank.php?category=Officer';
}
function soldierRankReportUrl() {
    return '/Armis2/admin_branch/reports_rank.php?category=NCO,Warrant';
}

function hideChartSkeleton(canvas) {
    if (!canvas || !canvas.parentElement) return;
    const skeleton = canvas.parentElement.querySelector('.chart-skeleton');
    if (skeleton) skeleton.remove();
}

function setChartTotalBadge(footerId, value, label) {
    const el = document.getElementById(footerId);
    if (!el) return;
    label = label || 'Total';
    el.innerHTML = `<i class="fas fa-calculator me-1"></i>${label}: <strong>${Number(value).toLocaleString(undefined, { maximumFractionDigits: 1 })}</strong>`;
}

// Enhanced Analytics Charts Initialization
function initializeAnalyticsCharts() {
    if (typeof Chart === 'undefined') {
        console.error('Chart.js is not loaded! Charts cannot be initialized.');
        return;
    }

    const analyticsData = <?php echo json_encode($dashboardData['analytics'] ?? []); ?>;

    Chart.defaults.responsive = true;
    Chart.defaults.maintainAspectRatio = false;
    Chart.defaults.plugins.legend.position = 'bottom';
    Chart.defaults.font.size = 12;

    try {
        if (analyticsData.officer_rank_distribution) {
            initOfficerRankChart(analyticsData.officer_rank_distribution);
        } else {
            console.warn('No officer_rank_distribution data available');
        }

        if (analyticsData.soldier_rank_distribution) {
            initSoldierRankChart(analyticsData.soldier_rank_distribution);
        } else {
            console.warn('No soldier_rank_distribution data available');
        }

        if (analyticsData.unit_distribution) {
            initUnitsChart(analyticsData.unit_distribution);
        } else {
            console.warn('No unit_distribution data available');
        }

        if (analyticsData.corps_distribution) {
            initCorpsChart(analyticsData.corps_distribution);
        } else {
            console.warn('No corps_distribution data available');
        }

        if (analyticsData.age_distribution) {
            initAgeChart(analyticsData.age_distribution);
        } else {
            console.warn('No age_distribution data available');
        }

        if (analyticsData.service_length_distribution) {
            initServiceLengthChart(analyticsData.service_length_distribution);
        } else {
            console.warn('No service_length_distribution data available');
        }

        if (analyticsData.marital_status_distribution) {
            initMaritalChart(analyticsData.marital_status_distribution);
        } else {
            console.warn('No marital_status_distribution data available');
        }

        console.log('Chart initialization completed successfully');
    } catch (error) {
        console.error('Error initializing charts:', error);
    }
}

document.addEventListener('DOMContentLoaded', function() {
    initializePeriodFilter();

    var chartWaitAttempts = 0;
    var CHART_WAIT_MAX_ATTEMPTS = 150;
    function waitForChart() {
        if (typeof Chart !== 'undefined') {
            console.log('Chart.js loaded successfully');
            initializeAnalyticsCharts();
            return;
        }
        chartWaitAttempts++;
        if (window.CHART_JS_LOAD_FAILED || chartWaitAttempts >= CHART_WAIT_MAX_ATTEMPTS) {
            console.error('Chart.js never became available after ' + chartWaitAttempts + ' attempts - showing fallback message on all charts.');
            showChartLoadFailureMessage();
            return;
        }
        setTimeout(waitForChart, 100);
    }
    waitForChart();
});

function showChartLoadFailureMessage() {
    var canvasIds = [
        'officerRankChart', 'soldierRankChart', 'unitsChart', 'corpsChart',
        'ageChart', 'serviceLengthChart', 'maritalChart'
    ];
    canvasIds.forEach(function(id) {
        var canvas = document.getElementById(id);
        if (!canvas) return;
        var msg = document.createElement('div');
        msg.className = 'text-center text-muted p-4';
        msg.style.fontSize = '0.85rem';
        msg.innerHTML = '<i class="fas fa-exclamation-triangle text-warning me-2"></i>Charting library failed to load. Check your network/firewall access to the CDN, or contact your administrator.';
        canvas.replaceWith(msg);
    });
}

// Officer Rank Distribution (stacked by gender)
function initOfficerRankChart(data) {
    const ctx = document.getElementById('officerRankChart');
    if (!ctx) return;
    hideChartSkeleton(ctx);
    if (!data || !data.labels || data.labels.length === 0) {
        ctx.getContext('2d').fillText('No officer rank data available', 50, 50);
        return;
    }

    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: data.labels,
            datasets: [
                { label: 'Male', data: data.male, backgroundColor: '#0d6efd' },
                { label: 'Female', data: data.female, backgroundColor: '#e83e8c' }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            onClick: chartDrilldownOnClickMultiSeries('Officer Rank Distribution', officerRankReportUrl),
            onHover: chartDrilldownOnHover,
            plugins: {
                title: { display: true, text: 'Officer Rank Distribution by Gender' },
                legend: { position: 'bottom' }
            },
            scales: {
                x: { stacked: true },
                y: { stacked: true, beginAtZero: true, ticks: { stepSize: 1 } }
            }
        }
    });
}

// Soldier (NCO) Rank Distribution (stacked by gender)
function initSoldierRankChart(data) {
    const ctx = document.getElementById('soldierRankChart');
    if (!ctx) return;
    hideChartSkeleton(ctx);
    if (!data || !data.labels || data.labels.length === 0) {
        ctx.getContext('2d').fillText('No soldier rank data available', 50, 50);
        return;
    }

    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: data.labels,
            datasets: [
                { label: 'Male', data: data.male, backgroundColor: '#17a2b8' },
                { label: 'Female', data: data.female, backgroundColor: '#fd7e14' }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            onClick: chartDrilldownOnClickMultiSeries('Soldier Rank Distribution', soldierRankReportUrl),
            onHover: chartDrilldownOnHover,
            plugins: {
                title: { display: true, text: 'Soldier Rank Distribution by Gender' },
                legend: { position: 'bottom' }
            },
            scales: {
                x: { stacked: true },
                y: { stacked: true, beginAtZero: true, ticks: { stepSize: 1 } }
            }
        }
    });
}

// Unit Distribution Chart
function initUnitsChart(data) {
    const ctx = document.getElementById('unitsChart');
    if (!ctx) return;
    hideChartSkeleton(ctx);

    if (!data || !data.labels || !data.data || data.labels.length === 0) {
        ctx.getContext('2d').fillText('No unit data available', 50, 50);
        return;
    }

    setChartTotalBadge('unitsChart-total', data.data.reduce((a, b) => a + (Number(b) || 0), 0));

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
            onClick: chartDrilldownOnClickSingleSeries('Personnel Distribution by Unit'),
            onHover: chartDrilldownOnHover,
            plugins: {
                title: { display: true, text: 'Personnel Distribution by Unit' }
            },
            scales: {
                x: { beginAtZero: true, ticks: { stepSize: 1 } }
            }
        }
    });
}

// Corps Distribution Chart
function initCorpsChart(data) {
    const ctx = document.getElementById('corpsChart');
    if (!ctx) return;
    hideChartSkeleton(ctx);

    if (!data || !data.labels || !data.data || data.labels.length === 0) {
        ctx.getContext('2d').fillText('No corps data available', 50, 50);
        return;
    }

    setChartTotalBadge('corpsChart-total', data.data.reduce((a, b) => a + (Number(b) || 0), 0));

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
            onClick: chartDrilldownOnClickSingleSeries('Corps Distribution'),
            onHover: chartDrilldownOnHover,
            plugins: {
                title: { display: true, text: 'Corps Distribution' }
            }
        }
    });
}

// Age Distribution Chart
/**
 * Age Distribution comes back from the backend grouped by SQL, which
 * doesn't guarantee bucket order. Reorders labels/data/colors so the bar
 * chart always reads ascending: Under 25, 25-34, 35-44, 45-54, 55+, then
 * anything unrecognized (e.g. "Unknown") at the end.
 */
function reorderAgeData(data) {
    const desiredOrder = ['under 25', '25-34', '35-44', '45-54', '55+', 'unknown'];
    const indices = data.labels.map((_, i) => i);

    indices.sort((a, b) => {
        const posA = desiredOrder.indexOf(String(data.labels[a]).trim().toLowerCase());
        const posB = desiredOrder.indexOf(String(data.labels[b]).trim().toLowerCase());
        const rankA = posA === -1 ? desiredOrder.length : posA;
        const rankB = posB === -1 ? desiredOrder.length : posB;
        if (rankA !== rankB) return rankA - rankB;
        return String(data.labels[a]).localeCompare(String(data.labels[b]));
    });

    return {
        labels: indices.map(i => data.labels[i]),
        data: indices.map(i => data.data[i]),
        colors: Array.isArray(data.colors) ? indices.map(i => data.colors[i]) : data.colors
    };
}

function initAgeChart(data) {
    const ctx = document.getElementById('ageChart');
    if (!ctx) return;
    hideChartSkeleton(ctx);

    if (!data || !data.labels || !data.data || data.labels.length === 0) {
        ctx.getContext('2d').fillText('No age data available', 50, 50);
        return;
    }

    data = reorderAgeData(data);

    setChartTotalBadge('ageChart-total', data.data.reduce((a, b) => a + (Number(b) || 0), 0));

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
            onClick: chartDrilldownOnClickSingleSeries('Age Distribution'),
            onHover: chartDrilldownOnHover,
            plugins: {
                legend: { display: false }
            },
            scales: {
                y: { beginAtZero: true, ticks: { stepSize: 1 } }
            }
        }
    });
}

// Service Length Chart
function initServiceLengthChart(data) {
    const ctx = document.getElementById('serviceLengthChart');
    if (!ctx) return;
    hideChartSkeleton(ctx);

    if (!data || !data.labels || !data.data || data.labels.length === 0) {
        ctx.getContext('2d').fillText('No service length data available', 50, 50);
        return;
    }

    setChartTotalBadge('serviceLengthChart-total', data.data.reduce((a, b) => a + (Number(b) || 0), 0));

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
            onClick: chartDrilldownOnClickSingleSeries('Service Length Distribution'),
            onHover: chartDrilldownOnHover,
            plugins: {
                legend: { position: 'bottom' }
            }
        }
    });
}

// Marital Status Chart
function initMaritalChart(data) {
    const ctx = document.getElementById('maritalChart');
    if (!ctx) return;
    hideChartSkeleton(ctx);

    if (!data || !data.labels || !data.data || data.labels.length === 0) {
        ctx.getContext('2d').fillText('No marital status data available', 50, 50);
        return;
    }

    setChartTotalBadge('maritalChart-total', data.data.reduce((a, b) => a + (Number(b) || 0), 0));

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
            onClick: chartDrilldownOnClickSingleSeries('Marital Status Distribution'),
            onHover: chartDrilldownOnHover,
            plugins: {
                legend: { position: 'bottom' }
            }
        }
    });
}

// Real-time Data Refresh Functionality
let refreshInterval;
let isRefreshing = false;

function startAutoRefresh() {
    refreshInterval = setInterval(refreshPersonnelData, 300000);
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
        });
}

function updatePersonnelDisplay(personnelData) {
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

    if (personnelData.civilian) {
        updateElement('civilian-current-male', personnelData.civilian.current_by_gender?.male);
        updateElement('civilian-current-female', personnelData.civilian.current_by_gender?.female);
        updateElement('civilian-current-total', personnelData.civilian.active);

        updateElement('civilian-total-male', personnelData.civilian.by_gender?.male);
        updateElement('civilian-total-female', personnelData.civilian.by_gender?.female);
        updateElement('civilian-grand-total', personnelData.civilian.total);
    }
}

function updateElement(id, value) {
    const element = document.getElementById(id);
    if (element && value !== undefined) {
        element.textContent = value || '-';
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

// Export Functionality
function exportPersonnelSnapshot() {
    const currentFilter = document.querySelector('input[name="personnel-filter"]:checked')?.value || 'all';
    const timestamp = new Date().toLocaleString();

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
        total: {
            male: document.getElementById('civilian-total-male').textContent,
            female: document.getElementById('civilian-total-female').textContent,
            total: document.getElementById('civilian-grand-total').textContent
        }
    };

    const contractData = {
        male: document.getElementById('military-cont-male') ? document.getElementById('military-cont-male').textContent : '0',
        female: document.getElementById('military-cont-female') ? document.getElementById('military-cont-female').textContent : '0',
        total: document.getElementById('military-cont-total') ? document.getElementById('military-cont-total').textContent : '0'
    };

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
    csvContent += `TOTAL CIVILIAN,${civilianData.total.male},${civilianData.total.female},${civilianData.total.total}\\n\\n`;

    csvContent += "CONTRACT PERSONNEL\\n";
    csvContent += "Male,Female,Total\\n";
    csvContent += `${contractData.male},${contractData.female},${contractData.total}\\n`;

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
        console.error('Bootstrap is not loaded!');
        return null;
    }
    if (typeof bootstrap.Modal === 'undefined') {
        console.error('Bootstrap Modal is not available!');
        return null;
    }

    const modalElement = document.getElementById('personnelDetailModal');
    if (!modalElement) {
        console.error('Modal element not found!');
        return null;
    }

    try {
        return new bootstrap.Modal(modalElement);
    } catch (error) {
        console.error('Modal initialization failed:', error);
        return null;
    }
}

// Main initialization function
document.addEventListener('DOMContentLoaded', function() {
    const personnelModal = checkBootstrapAndInitModal();

    let currentCategory = '';
    let currentType = '';

    function updateModalContent(rowElement, category, type) {
        currentCategory = category;
        currentType = type;

        const modalLabel = document.getElementById('personnelDetailModalLabel');
        if (modalLabel) modalLabel.innerHTML = `<i class="fas fa-users me-2"></i>${type} Details`;

        const categoryTitle = document.getElementById('modal-category-title');
        if (categoryTitle) categoryTitle.textContent = type + ' Breakdown';

        try {
            const cells = rowElement.querySelectorAll('td');

            if (cells.length >= 4) {
                const maleText = cells[1].textContent.trim();
                const femaleText = cells[2].textContent.trim();
                const totalText = cells[3].textContent.trim();

                const maleCount = maleText.split('(')[0].trim();
                const femaleCount = femaleText.split('(')[0].trim();
                const totalCount = totalText.split('(')[0].trim();

                const updates = [
                    { id: 'modal-male-count', value: maleCount },
                    { id: 'modal-female-count', value: femaleCount },
                    { id: 'modal-total-count', value: totalCount }
                ];

                updates.forEach(update => {
                    const element = document.getElementById(update.id);
                    if (element) element.textContent = update.value;
                });

                try {
                    const maleNum = parseInt(maleCount.replace(/[^0-9\-]/g, '')) || 0;
                    const femaleNum = parseInt(femaleCount.replace(/[^0-9\-]/g, '')) || 0;
                    const totalNum = parseInt(totalCount.replace(/[^0-9\-]/g, '')) || (maleNum + femaleNum);

                    const malePctEl = document.getElementById('modal-male-pct');
                    const femalePctEl = document.getElementById('modal-female-pct');
                    const ariaSummary = document.getElementById('modal-accessible-summary');

                    let malePct = totalNum > 0 ? ((maleNum / totalNum) * 100) : 0;
                    let femalePct = totalNum > 0 ? ((femaleNum / totalNum) * 100) : 0;

                    malePct = Math.round(malePct * 10) / 10;
                    femalePct = Math.round(femalePct * 10) / 10;

                    if (malePctEl) malePctEl.textContent = `${malePct}%`;
                    if (femalePctEl) femalePctEl.textContent = `${femalePct}%`;

                    if (ariaSummary) {
                        ariaSummary.textContent = `${type} breakdown: ${totalNum} total, ${maleNum} male (${malePct}%), ${femaleNum} female (${femalePct}%).`;
                    }
                } catch (pctError) {
                    console.warn('Error computing percentages for modal:', pctError);
                }

                loadPersonnelList();
            } else {
                console.error('Not enough cells in row:', cells.length);
            }
        } catch (error) {
            console.error('Error extracting row data:', error);
        }
    }

    document.querySelectorAll('tr.personnel-row').forEach((row) => {
        row.addEventListener('click', function(event) {
            event.preventDefault();

            const rowCategory = this.getAttribute('data-category');
            const rowType = this.getAttribute('data-type');

            if (!rowCategory || !rowType) {
                console.error('Missing data attributes on clicked row');
                return;
            }

            updateModalContent(this, rowCategory, rowType);

            if (personnelModal) {
                try {
                    personnelModal.show();
                } catch (error) {
                    console.error('Error showing modal:', error);
                }
            }
        });

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

    setupModalActionButtons();
    initializePersonnelFilters();
    startAutoRefresh();

    // Print / Export Dashboard button - uses the browser's native print
    // dialog (which on most OSes offers "Save as PDF"), styled by the
    // @media print rules above to hide buttons/modals and let charts
    // and tables flow across printed pages.
    const printBtn = document.getElementById('print-dashboard');
    if (printBtn) {
        printBtn.addEventListener('click', function () {
            window.print();
        });
    }

    // Make snapshot totals clickable: open personnel modal and load the
    // corresponding category.
    document.querySelectorAll('.snapshot-count').forEach(el => {
        el.style.cursor = 'pointer';

        el.addEventListener('click', function () {
            const id = this.id || '';
            const mapping = {
                'military-officers-total': { category: 'military-officers', type: 'Officers' },
                'military-ncos-total': { category: 'military-ncos', type: 'NCOs' },
                'recruit-officers-total': { category: 'military-officers', type: 'Recruit Officers' },
                'recruit-ncos-total': { category: 'military-ncos', type: 'Recruit NCOs' },
                'military-grand-total': { category: 'military-all', type: 'Total Military' },
                'civilian-current-total': { category: 'civilian-current', type: 'Staff' },
                'civilian-grand-total': { category: 'civilian-current', type: 'Total Civilian' }
            };

            const info = mapping[id] || { category: 'military-all', type: this.textContent.trim() || 'Personnel' };
            currentCategory = info.category;
            currentType = info.type;

            const modalLabel = document.getElementById('personnelDetailModalLabel');
            if (modalLabel) modalLabel.innerHTML = `<i class="fas fa-users me-2"></i>${currentType} Details`;
            const categoryTitle = document.getElementById('modal-category-title');
            if (categoryTitle) categoryTitle.textContent = currentType + ' Breakdown';

            if (personnelModal) personnelModal.show();
            loadPersonnelList();
        });
    });

    const exportBtn = document.getElementById('export-snapshot');
    if (exportBtn) {
        exportBtn.addEventListener('click', exportPersonnelSnapshot);
    }

    function loadPersonnelList() {
        const loadingEl = document.getElementById('modal-loading');
        const personnelListEl = document.getElementById('modal-personnel-list');
        const listBody = document.getElementById('personnel-list-body');

        if (loadingEl) loadingEl.style.display = 'block';
        if (personnelListEl) personnelListEl.style.display = 'none';

        const isCivilian = currentCategory.includes('civilian');
        const colspanCount = isCivilian ? 4 : 5;

        fetch(`dashboard_api.php?action=get_personnel_by_category&category=${encodeURIComponent(currentCategory)}`)
            .then(response => response.json())
            .then(data => {
                if (loadingEl) loadingEl.style.display = 'none';
                if (personnelListEl) personnelListEl.style.display = 'block';

                if (data.success && data.data && data.data.personnel) {
                    const personnel = data.data.personnel;

                    if (personnel.length === 0) {
                        listBody.innerHTML = `
                            <tr><td colspan="${colspanCount}" class="text-center text-muted">
                                <i class="fas fa-info-circle me-2"></i>No personnel found in this category.
                            </td></tr>`;
                        return;
                    }

                    function generateRankAbbr(rankName) {
                        if (!rankName) return '';
                        const fillers = ['of', 'and', 'the', 'in', 'on', 'for', 'with', 'by', 'to'];
                        const parts = rankName.replace(/[^A-Za-z\s]/g, ' ').split(/\s+/).filter(w => w.length > 0);
                        const initials = parts.filter(w => !fillers.includes(w.toLowerCase())).map(w => w.charAt(0).toUpperCase());
                        if (initials.length === 0 && parts.length > 0) return parts[0].substring(0, 3).toUpperCase();
                        return initials.slice(0, 3).join('');
                    }

                    let rows = '';
                    personnel.forEach(person => {
                        const statusClass = person.status === 'Active' ? 'success' : 'secondary';
                        const unit = person.unit || 'N/A';
                        const rankAbbr = (person.rank_abbr && person.rank_abbr.trim()) ? person.rank_abbr : (person.rank ? generateRankAbbr(person.rank) : '');

                        const rawFirst = person.fName || '';
                        const rawLast = person.lName || '';
                        const formattedFirst = rawFirst.split(' ').map(w => w.charAt(0).toUpperCase() + w.slice(1).toLowerCase()).join(' ');
                        const formattedLast = rawLast.toUpperCase();
                        const displayName = formattedLast + ' ' + formattedFirst;

                        rows += `
                            <tr>
                                <td>${person.svcNo || ''}</td>
                                <td>${rankAbbr}</td>
                                <td>${displayName}</td>
                                <td>${unit}</td>
                                <td>${person.gender || ''}</td>
                                <td><span class="badge bg-${statusClass}">${person.status}</span></td>
                            </tr>`;
                    });

                    listBody.innerHTML = rows;
                } else {
                    listBody.innerHTML = `
                        <tr><td colspan="${colspanCount}" class="text-center text-danger">
                            <i class="fas fa-exclamation-triangle me-2"></i>${data.message || 'Error loading personnel data'}
                        </td></tr>`;
                }
            })
            .catch(error => {
                console.error('Fetch Error:', error);
                if (loadingEl) loadingEl.style.display = 'none';
                if (personnelListEl) personnelListEl.style.display = 'block';
                listBody.innerHTML = `
                    <tr><td colspan="${colspanCount}" class="text-center text-danger">
                        <i class="fas fa-exclamation-triangle me-2"></i>Network error. Please check your connection and try again.
                    </td></tr>`;
            });
    }

    function setupModalActionButtons() {
        const viewListBtn = document.getElementById('view-personnel-list');
        if (viewListBtn) {
            viewListBtn.addEventListener('click', () => loadPersonnelList());
        }

        const exportDataBtn = document.getElementById('export-personnel-data');
        if (exportDataBtn) {
            exportDataBtn.addEventListener('click', function() {
                const exportButton = this;
                const originalContent = exportButton.innerHTML;

                exportButton.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Exporting...';
                exportButton.disabled = true;

                fetch(`dashboard_api.php?action=get_personnel_by_category&category=${encodeURIComponent(currentCategory)}`)
                    .then(response => response.json())
                    .then(data => {
                        if (data.success && data.data && data.data.personnel) {
                            const personnel = data.data.personnel;
                            const csvData = [['Service Number', 'Rank', 'Name', 'Unit', 'Gender', 'Status', 'Joined Date']];

                            personnel.forEach(person => {
                                const rawFirst = person.fName || '';
                                const rawLast = person.lName || '';
                                const formattedFirst = rawFirst.split(' ').map(w => w.charAt(0).toUpperCase() + w.slice(1).toLowerCase()).join(' ');
                                const formattedLast = rawLast.toUpperCase();
                                const displayName = formattedLast + ', ' + formattedFirst;

                                csvData.push([
                                    person.svcNo || '',
                                    person.rank_abbr || person.rank || '',
                                    displayName,
                                    person.unit || 'N/A',
                                    person.gender || '',
                                    person.status || '',
                                    person.joined_date || 'N/A'
                                ]);
                            });

                            const csvContent = csvData.map(row =>
                                row.map(cell => {
                                    const cellStr = String(cell);
                                    if (cellStr.includes(',') || cellStr.includes('"') || cellStr.includes('\n')) {
                                        return '"' + cellStr.replace(/"/g, '""') + '"';
                                    }
                                    return cellStr;
                                }).join(',')
                            ).join('\n');

                            const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
                            const url = window.URL.createObjectURL(blob);
                            const a = document.createElement('a');
                            a.href = url;
                            a.download = `${currentType.replace(/[^a-zA-Z0-9]/g, '_')}_Export_${new Date().toISOString().split('T')[0]}.csv`;
                            document.body.appendChild(a);
                            a.click();
                            document.body.removeChild(a);
                            window.URL.revokeObjectURL(url);

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
                        console.error('Export Error:', error);
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
    }
});
</script>

<?php
// Include footer and allow script loading for Chart.js
include dirname(__DIR__) . '/shared/footer.php';
?>