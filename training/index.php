<?php
// Define module constants
define('ARMIS_TRAINING', true);
define('ARMIS_DEVELOPMENT', true);

// Include training authentication and services
require_once __DIR__ . '/includes/auth.php';
require_once dirname(__DIR__) . '/shared/database_connection.php';
require_once __DIR__ . '/includes/training_service.php';

// Require authentication and training access
requireTrainingAccess();

// Log page access
logTrainingActivity('dashboard_access', 'Accessed Training Dashboard');

// Initialize training service
$pdo = getDbConnection();
$trainingService = null;
$dashboardData = null;

try {
    if ($pdo) {
        $trainingService = new TrainingService($pdo);
        $dashboardData = [
            'kpi' => $trainingService->getKPIData(),
            'recent_activities' => $trainingService->getRecentActivities(4),
            'course_stats' => $trainingService->getCourseStats(),
            'enrollment_overview' => $trainingService->getEnrollmentOverview(),
            'progress_metrics' => $trainingService->getProgressMetrics(),
            'upcoming_sessions' => $trainingService->getUpcomingSessions(),
            'certification_status' => $trainingService->getCertificationStatus()
        ];
        
        error_log("Training dashboard initialization successful");
    } else {
        throw new Exception('Database connection failed');
    }
} catch (Exception $e) {
    error_log("Training dashboard initialization error: " . $e->getMessage());
    // Set fallback data
    $dashboardData = [
        'kpi' => ['active_courses' => 0, 'enrolled_personnel' => 0, 'completion_rate' => 0, 'upcoming_sessions' => 0, 'pending_certifications' => 0],
        'recent_activities' => [],
        'course_stats' => ['status_distribution' => [], 'type_distribution' => []],
        'enrollment_overview' => [],
        'progress_metrics' => ['by_type' => [], 'enrollment_trends' => []],
        'upcoming_sessions' => [],
        'certification_status' => []
    ];
}

$pageTitle = "Training Dashboard";
$moduleName = "Training";
$moduleIcon = "graduation-cap";
$currentPage = "dashboard";

$sidebarLinks = [
    ['title' => 'Dashboard', 'url' => '/training/index.php', 'icon' => 'tachometer-alt', 'page' => 'dashboard'],
    ['title' => 'Course Management', 'url' => '/training/courses.php', 'icon' => 'book', 'page' => 'courses'],
    ['title' => 'Enrollments', 'url' => '/training/enrollments.php', 'icon' => 'user-graduate', 'page' => 'enrollments'],
    ['title' => 'Training Schedule', 'url' => '/training/schedule.php', 'icon' => 'calendar-alt', 'page' => 'schedule'],
    ['title' => 'Instructors', 'url' => '/training/instructors.php', 'icon' => 'chalkboard-teacher', 'page' => 'instructors'],
    ['title' => 'Certifications', 'url' => '/training/certifications.php', 'icon' => 'certificate', 'page' => 'certifications'],
    ['title' => 'Progress Reports', 'url' => '/training/reports.php', 'icon' => 'chart-line', 'page' => 'reports']
];

include dirname(__DIR__) . '/shared/header.php';
include dirname(__DIR__) . '/shared/sidebar.php';
?>

<!-- Training Module CSS -->
<link href="/training/css/training.css" rel="stylesheet">

<!-- Main Content -->
<div class="content-wrapper with-sidebar">
    <div class="container-fluid">
        <!-- Loading Indicator -->
        <div id="dashboardLoading" class="training-loading" style="display: none;">
            <div class="loading-spinner"></div>
            <p>Loading training data...</p>
        </div>

        <div class="main-content">
            <!-- Header Section -->
            <div class="row">
                <div class="col-12">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h1 class="section-title">
                            <i class="fas fa-graduation-cap"></i> Training Dashboard
                        </h1>
                        <div class="d-flex align-items-center">
                            <div class="form-check form-switch me-3">
                                <input class="form-check-input" type="checkbox" id="realTimeUpdates" checked>
                                <label class="form-check-label" for="realTimeUpdates">Real-time Updates</label>
                            </div>
                            <button class="btn btn-training-primary refresh-dashboard">
                                <i class="fas fa-sync-alt"></i> Refresh
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- KPI Cards -->
            <div class="row g-4 mb-4">
                <div class="col-md-6 col-lg-2-4">
                    <div class="card training-kpi-card text-white h-100" data-target="courses">
                        <div class="card-body d-flex justify-content-between align-items-center">
                            <div>
                                <h5 class="card-title">Active Courses</h5>
                                <h2 class="mb-0" id="activeCourses"><?= $dashboardData['kpi']['active_courses'] ?? 0 ?></h2>
                            </div>
                            <i class="fas fa-book fa-2x opacity-75"></i>
                        </div>
                    </div>
                </div>
                <div class="col-md-6 col-lg-2-4">
                    <div class="card training-kpi-card success text-white h-100" data-target="enrolled">
                        <div class="card-body d-flex justify-content-between align-items-center">
                            <div>
                                <h5 class="card-title">Enrolled Personnel</h5>
                                <h2 class="mb-0" id="enrolledPersonnel"><?= $dashboardData['kpi']['enrolled_personnel'] ?? 0 ?></h2>
                            </div>
                            <i class="fas fa-user-graduate fa-2x opacity-75"></i>
                        </div>
                    </div>
                </div>
                <div class="col-md-6 col-lg-2-4">
                    <div class="card training-kpi-card info text-white h-100" data-target="completion">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <h5 class="card-title mb-0">Completion Rate</h5>
                                <i class="fas fa-chart-line fa-2x opacity-75"></i>
                            </div>
                            <h2 class="mb-2" id="completionRate"><?= $dashboardData['kpi']['completion_rate'] ?? 0 ?>%</h2>
                            <div class="progress" style="height: 6px;">
                                <div class="progress-bar bg-white" id="completionProgress" style="width: <?= $dashboardData['kpi']['completion_rate'] ?? 0 ?>%"></div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-6 col-lg-2-4">
                    <div class="card training-kpi-card warning text-white h-100" data-target="sessions">
                        <div class="card-body d-flex justify-content-between align-items-center">
                            <div>
                                <h5 class="card-title">Upcoming Sessions</h5>
                                <h2 class="mb-0" id="upcomingSessions"><?= $dashboardData['kpi']['upcoming_sessions'] ?? 0 ?></h2>
                            </div>
                            <i class="fas fa-calendar-alt fa-2x opacity-75"></i>
                        </div>
                    </div>
                </div>
                <div class="col-md-6 col-lg-2-4">
                    <div class="card training-kpi-card secondary text-white h-100" data-target="certifications">
                        <div class="card-body d-flex justify-content-between align-items-center">
                            <div>
                                <h5 class="card-title">Pending Certifications</h5>
                                <h2 class="mb-0" id="pendingCertifications"><?= $dashboardData['kpi']['pending_certifications'] ?? 0 ?></h2>
                            </div>
                            <i class="fas fa-certificate fa-2x opacity-75"></i>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Main Dashboard Content -->
            <div class="row g-4">
                <!-- Recent Activities -->
                <div class="col-lg-6">
                    <div class="card training-card h-100">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="fas fa-clock"></i> Recent Training Activities</h5>
                        </div>
                        <div class="card-body">
                            <div class="list-group list-group-flush" id="recentActivities">
                                <!-- Dynamic content loaded via JavaScript -->
                                <?php if (!empty($dashboardData['recent_activities'])): ?>
                                    <?php foreach ($dashboardData['recent_activities'] as $activity): ?>
                                        <div class="list-group-item d-flex justify-content-between align-items-center">
                                            <div>
                                                <h6 class="mb-1"><?= htmlspecialchars($activity['title']) ?></h6>
                                                <p class="mb-1"><?= htmlspecialchars($activity['description'] ?? '') ?></p>
                                                <small class="text-muted"><?= date('M j, H:i', strtotime($activity['timestamp'])) ?></small>
                                            </div>
                                            <span class="badge enrollment-status enrollment-<?= $activity['status'] ?>"><?= ucfirst($activity['status']) ?></span>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <div class="text-center text-muted">No recent activities</div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Upcoming Sessions -->
                <div class="col-lg-6">
                    <div class="card training-card h-100">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="fas fa-calendar-check"></i> Upcoming Training Sessions</h5>
                        </div>
                        <div class="card-body">
                            <div id="upcomingSessions">
                                <!-- Dynamic content loaded via JavaScript -->
                                <?php if (!empty($dashboardData['upcoming_sessions'])): ?>
                                    <?php foreach (array_slice($dashboardData['upcoming_sessions'], 0, 4) as $session): ?>
                                        <div class="session-schedule">
                                            <div class="d-flex justify-content-between align-items-start">
                                                <div>
                                                    <h6 class="mb-1"><?= htmlspecialchars($session['session_name']) ?></h6>
                                                    <p class="mb-1 text-muted"><?= htmlspecialchars($session['course_name']) ?></p>
                                                    <div class="session-time"><?= date('M j, Y - H:i', strtotime($session['start_date'])) ?></div>
                                                    <div class="session-location">
                                                        <i class="fas fa-map-marker-alt"></i> <?= htmlspecialchars($session['location'] ?? 'TBD') ?>
                                                    </div>
                                                </div>
                                                <div class="text-end">
                                                    <span class="badge course-status status-<?= $session['status'] ?>"><?= ucfirst($session['status']) ?></span>
                                                    <br>
                                                    <small class="text-muted"><?= $session['registered_count'] ?? 0 ?> registered</small>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <div class="text-center text-muted">No upcoming sessions</div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Secondary Dashboard Row -->
            <div class="row g-4 mt-4">
                <!-- Course Statistics -->
                <div class="col-lg-6">
                    <div class="card training-card">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="fas fa-chart-pie"></i> Course Statistics</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <canvas id="courseStatusChart" height="200"></canvas>
                                </div>
                                <div class="col-md-6">
                                    <canvas id="courseTypeChart" height="200"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Enrollment Trends -->
                <div class="col-lg-6">
                    <div class="card training-card">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="fas fa-trending-up"></i> Enrollment Trends</h5>
                        </div>
                        <div class="card-body">
                            <canvas id="enrollmentTrendsChart" height="200"></canvas>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Course Progress by Type -->
            <div class="row mt-4">
                <div class="col-12">
                    <div class="card training-card">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="fas fa-chart-bar"></i> Progress by Course Type</h5>
                        </div>
                        <div class="card-body">
                            <div class="row" id="progressByType">
                                <!-- Dynamic content loaded via JavaScript -->
                                <?php if (!empty($dashboardData['progress_metrics']['by_type'])): ?>
                                    <?php foreach ($dashboardData['progress_metrics']['by_type'] as $type => $data): ?>
                                        <div class="col-md-6 col-lg-4 mb-3">
                                            <div class="card training-card">
                                                <div class="card-body text-center">
                                                    <h6 class="card-title"><?= htmlspecialchars($type) ?></h6>
                                                    <div class="training-progress mb-2">
                                                        <div class="training-progress-bar bg-success" style="width: <?= $data['completion_rate'] ?>%">
                                                            <span class="progress-text"><?= $data['completion_rate'] ?>%</span>
                                                        </div>
                                                    </div>
                                                    <small class="text-muted"><?= $data['completed'] ?>/<?= $data['total'] ?> completed</small>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <div class="text-center text-muted">No progress data available</div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Toast Container -->
<div class="position-fixed bottom-0 end-0 p-3" style="z-index: 11">
    <div id="toastContainer"></div>
</div>

<!-- Chart.js for statistics -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<!-- Training Dashboard JavaScript -->
<script src="/training/js/training_dashboard.js"></script>

<?php include dirname(__DIR__) . '/shared/footer.php'; ?>