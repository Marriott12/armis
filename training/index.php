<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include RBAC system
require_once dirname(__DIR__) . '/shared/rbac.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ' . dirname($_SERVER['PHP_SELF']) . '/../login.php');
    exit();
}

// Check if user has access to training module
requireModuleAccess('training');

// Log access
logAccess('training', 'dashboard_view', true);

$pageTitle = "Training";
$moduleName = "Training";
$moduleIcon = "graduation-cap";
$currentPage = "dashboard";

$sidebarLinks = [
    ['title' => 'Dashboard', 'url' => '/Armis2/training/index.php', 'icon' => 'tachometer-alt', 'page' => 'dashboard'],
    ['title' => 'Course Catalog', 'url' => '/Armis2/training/courses.php', 'icon' => 'book', 'page' => 'courses'],
    ['title' => 'Training Records', 'url' => '/Armis2/training/records.php', 'icon' => 'certificate', 'page' => 'records'],
    ['title' => 'Schedule', 'url' => '/Armis2/training/schedule.php', 'icon' => 'calendar', 'page' => 'schedule'],
    ['title' => 'Certifications', 'url' => '/Armis2/training/certifications.php', 'icon' => 'award', 'page' => 'certifications']
];

include dirname(__DIR__) . '/shared/header.php';
include dirname(__DIR__) . '/shared/sidebar.php';
?>

<!-- Main Content -->
<div class="content-wrapper with-sidebar">
    <div class="container-fluid">
        <div class="main-content">
            <div class="row">
                <div class="col-12">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h1 class="section-title">
                            <i class="fas fa-graduation-cap"></i> Training Dashboard
                        </h1>
                        <span class="badge status-badge">Training Center</span>
                    </div>
                </div>
            </div>
            
            <div class="row g-4">
                <div class="col-md-6 col-lg-3">
                    <div class="card module-card">
                        <div class="card-body text-center">
                            <div class="mb-3">
                                <i class="fas fa-book fa-2x text-primary"></i>
                            </div>
                            <h5 class="card-title">Course Catalog</h5>
                            <p class="card-text">Browse available training courses and programs</p>
                            <a href="/training/courses" class="btn btn-armis">View Courses</a>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-6 col-lg-3">
                    <div class="card module-card">
                        <div class="card-body text-center">
                            <div class="mb-3">
                                <i class="fas fa-certificate fa-2x text-success"></i>
                            </div>
                            <h5 class="card-title">Training Records</h5>
                            <p class="card-text">Track individual and unit training progress</p>
                            <a href="/training/records" class="btn btn-armis">View Records</a>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-6 col-lg-3">
                    <div class="card module-card">
                        <div class="card-body text-center">
                            <div class="mb-3">
                                <i class="fas fa-calendar fa-2x text-warning"></i>
                            </div>
                            <h5 class="card-title">Training Schedule</h5>
                            <p class="card-text">View and manage training schedules</p>
                            <a href="/training/schedule" class="btn btn-armis">View Schedule</a>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-6 col-lg-3">
                    <div class="card module-card">
                        <div class="card-body text-center">
                            <div class="mb-3">
                                <i class="fas fa-award fa-2x text-info"></i>
                            </div>
                            <h5 class="card-title">Certifications</h5>
                            <p class="card-text">Manage certifications and qualifications</p>
                            <a href="/training/certifications" class="btn btn-armis">View Certs</a>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Training Stats -->
            <div class="row mt-5">
                <div class="col-12">
                    <h3 class="section-title mb-4">Training Statistics</h3>
                </div>
                <div class="col-md-6 col-lg-3">
                    <div class="card bg-primary text-white">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h5>Active Courses</h5>
                                    <h2>28</h2>
                                </div>
                                <i class="fas fa-book-open fa-2x"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-6 col-lg-3">
                    <div class="card bg-success text-white">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h5>Completion Rate</h5>
                                    <h2>87%</h2>
                                </div>
                                <i class="fas fa-chart-line fa-2x"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-6 col-lg-3">
                    <div class="card bg-warning text-white">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h5>Pending Certs</h5>
                                    <h2>12</h2>
                                </div>
                                <i class="fas fa-clock fa-2x"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-6 col-lg-3">
                    <div class="card bg-info text-white">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h5>Instructors</h5>
                                    <h2>18</h2>
                                </div>
                                <i class="fas fa-chalkboard-teacher fa-2x"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php include dirname(__DIR__) . '/shared/footer.php'; ?>