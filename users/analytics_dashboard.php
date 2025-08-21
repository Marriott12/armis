<?php
/**
 * Enhanced Military Analytics Dashboard
 * Comprehensive military personnel analytics and monitoring
 */

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ' . dirname($_SERVER['PHP_SELF']) . '/../login.php');
    exit();
}

$pageTitle = "Military Analytics Dashboard";
$moduleName = "User Profile";
$moduleIcon = "chart-bar";
$currentPage = "analytics";

try {
    require_once __DIR__ . '/classes/EnhancedProfileManager.php';
    $profileManager = new EnhancedProfileManager($_SESSION['user_id']);
    
    // Get comprehensive analytics data
    $userProfile = $profileManager->getUserProfile();
    $rankProgression = $profileManager->getRankProgression();
    $securityClearance = $profileManager->getSecurityClearance();
    $medicalReadiness = $profileManager->getMedicalReadiness();
    $deploymentHistory = $profileManager->getDeploymentHistory();
    $trainingRecords = $profileManager->getTrainingRecords();
    $familyMembers = $profileManager->getFamilyMembers();
    
    // Calculate analytics
    $totalDeployments = count($deploymentHistory);
    $activeDeployments = count(array_filter($deploymentHistory, function($d) {
        return $d->status === 'Deployed';
    }));
    
    $completedTrainings = count(array_filter($trainingRecords, function($t) {
        return isset($t->status) && $t->status === 'completed';
    }));
    
    $emergencyContacts = count(array_filter($familyMembers, function($f) {
        return $f->is_emergency_contact;
    }));
    
} catch (Exception $e) {
    error_log("Analytics dashboard error: " . $e->getMessage());
    $userProfile = null;
}

include dirname(__DIR__) . '/shared/header.php';
include dirname(__DIR__) . '/shared/sidebar.php';
?>

<!-- Main Content -->
<div class="content-wrapper with-sidebar">
    <div class="container-fluid">
        <div class="main-content">
            <!-- Header Section -->
            <div class="row">
                <div class="col-12">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h1 class="section-title">
                            <i class="fas fa-chart-bar"></i> Military Analytics Dashboard
                        </h1>
                        <div class="d-flex align-items-center">
                            <span class="badge bg-primary me-2">Last Updated: <?= date('M j, Y H:i') ?></span>
                            <a href="/users/index.php" class="btn btn-secondary">
                                <i class="fas fa-arrow-left"></i> Back to Profile
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Military Status Overview -->
            <div class="row mb-4">
                <div class="col-lg-3 col-md-6 mb-3">
                    <div class="card bg-primary text-white h-100">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h4 class="mb-0"><?= htmlspecialchars($userProfile->service_years ?? 'N/A') ?></h4>
                                    <small>Years of Service</small>
                                </div>
                                <i class="fas fa-medal fa-2x opacity-75"></i>
                            </div>
                            <div class="mt-2">
                                <small>Since <?= $userProfile->enlistment_date ? date('M Y', strtotime($userProfile->enlistment_date)) : 'N/A' ?></small>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-3 col-md-6 mb-3">
                    <div class="card bg-success text-white h-100">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h4 class="mb-0"><?= $userProfile->profile_completion ?? 0 ?>%</h4>
                                    <small>Profile Complete</small>
                                </div>
                                <i class="fas fa-user-check fa-2x opacity-75"></i>
                            </div>
                            <div class="mt-2">
                                <div class="progress" style="height: 4px;">
                                    <div class="progress-bar bg-light" style="width: <?= $userProfile->profile_completion ?? 0 ?>%"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-3 col-md-6 mb-3">
                    <div class="card bg-warning text-white h-100">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h4 class="mb-0"><?= $totalDeployments ?></h4>
                                    <small>Total Deployments</small>
                                </div>
                                <i class="fas fa-globe fa-2x opacity-75"></i>
                            </div>
                            <div class="mt-2">
                                <small><?= $activeDeployments ?> Currently Active</small>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-3 col-md-6 mb-3">
                    <div class="card bg-info text-white h-100">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h4 class="mb-0"><?= $completedTrainings ?></h4>
                                    <small>Training Courses</small>
                                </div>
                                <i class="fas fa-graduation-cap fa-2x opacity-75"></i>
                            </div>
                            <div class="mt-2">
                                <small>Completed Successfully</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Military Information Grid -->
            <div class="row">
                <!-- Rank Progression -->
                <div class="col-lg-6 mb-4">
                    <div class="card h-100">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="fas fa-chart-line"></i> Rank Progression</h5>
                        </div>
                        <div class="card-body">
                            <?php if ($rankProgression && $rankProgression['current']): ?>
                                <div class="d-flex align-items-center mb-3">
                                    <div class="rank-badge me-3">
                                        <i class="fas fa-star-of-david fa-2x text-warning"></i>
                                    </div>
                                    <div>
                                        <h6 class="mb-0">Current Rank</h6>
                                        <h4 class="text-primary"><?= htmlspecialchars($rankProgression['current']->name) ?></h4>
                                        <small class="text-muted"><?= htmlspecialchars($rankProgression['current']->category) ?> - <?= htmlspecialchars($rankProgression['current']->pay_grade ?? 'N/A') ?></small>
                                    </div>
                                </div>
                                
                                <?php if ($rankProgression['next']): ?>
                                <div class="border-top pt-3">
                                    <h6 class="text-muted">Next Rank</h6>
                                    <p class="mb-1"><strong><?= htmlspecialchars($rankProgression['next']->name) ?></strong></p>
                                    <small class="text-muted"><?= htmlspecialchars($rankProgression['next']->category) ?> - <?= htmlspecialchars($rankProgression['next']->pay_grade ?? 'N/A') ?></small>
                                    
                                    <?php if ($rankProgression['eligible_for_promotion']['eligible']): ?>
                                        <div class="mt-2">
                                            <span class="badge bg-success">Eligible for Promotion</span>
                                        </div>
                                    <?php else: ?>
                                        <div class="mt-2">
                                            <span class="badge bg-warning">Requirements Pending</span>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <?php endif; ?>
                            <?php else: ?>
                                <p class="text-muted">No rank information available</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Security Clearance -->
                <div class="col-lg-6 mb-4">
                    <div class="card h-100">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="fas fa-shield-alt"></i> Security Clearance</h5>
                        </div>
                        <div class="card-body">
                            <div class="d-flex align-items-center mb-3">
                                <div class="clearance-badge me-3">
                                    <i class="fas fa-certificate fa-2x text-danger"></i>
                                </div>
                                <div>
                                    <h6 class="mb-0">Clearance Level</h6>
                                    <h4 class="text-danger"><?= htmlspecialchars($securityClearance['level'] ?? 'None') ?></h4>
                                    <span class="badge bg-<?= $securityClearance['renewal_required'] ? 'warning' : 'success' ?>">
                                        <?= htmlspecialchars($securityClearance['status'] ?? 'Unknown') ?>
                                    </span>
                                </div>
                            </div>
                            
                            <?php if (!empty($securityClearance['expiry_date'])): ?>
                            <div class="border-top pt-3">
                                <div class="row text-center">
                                    <div class="col-6">
                                        <h6 class="text-muted mb-1">Expires</h6>
                                        <p class="mb-0"><?= date('M j, Y', strtotime($securityClearance['expiry_date'])) ?></p>
                                    </div>
                                    <div class="col-6">
                                        <h6 class="text-muted mb-1">Days Remaining</h6>
                                        <p class="mb-0 <?= $securityClearance['days_until_expiry'] <= 90 ? 'text-warning' : 'text-success' ?>">
                                            <?= $securityClearance['days_until_expiry'] ?? 'N/A' ?>
                                        </p>
                                    </div>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Medical Readiness -->
                <div class="col-lg-6 mb-4">
                    <div class="card h-100">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="fas fa-heartbeat"></i> Medical Readiness</h5>
                        </div>
                        <div class="card-body">
                            <div class="d-flex align-items-center mb-3">
                                <div class="medical-badge me-3">
                                    <i class="fas fa-user-md fa-2x text-success"></i>
                                </div>
                                <div>
                                    <h6 class="mb-0">Fitness Status</h6>
                                    <h4 class="text-success"><?= htmlspecialchars($userProfile->medical_fitness_status ?? 'Unknown') ?></h4>
                                    <span class="badge bg-<?= $medicalReadiness['exam_required'] ? 'warning' : 'success' ?>">
                                        <?= htmlspecialchars($medicalReadiness['status'] ?? 'Unknown') ?>
                                    </span>
                                </div>
                            </div>
                            
                            <?php if (!empty($medicalReadiness['next_due'])): ?>
                            <div class="border-top pt-3">
                                <div class="row text-center">
                                    <div class="col-6">
                                        <h6 class="text-muted mb-1">Next Exam</h6>
                                        <p class="mb-0"><?= date('M j, Y', strtotime($medicalReadiness['next_due'])) ?></p>
                                    </div>
                                    <div class="col-6">
                                        <h6 class="text-muted mb-1">Days Until Due</h6>
                                        <p class="mb-0 <?= $medicalReadiness['days_until_due'] <= 30 ? 'text-warning' : 'text-success' ?>">
                                            <?= $medicalReadiness['days_until_due'] ?? 'N/A' ?>
                                        </p>
                                    </div>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Family & Emergency Contacts -->
                <div class="col-lg-6 mb-4">
                    <div class="card h-100">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="fas fa-users"></i> Family & Emergency Contacts</h5>
                        </div>
                        <div class="card-body">
                            <div class="row text-center mb-3">
                                <div class="col-6">
                                    <h3 class="text-primary mb-0"><?= count($familyMembers) ?></h3>
                                    <small class="text-muted">Family Members</small>
                                </div>
                                <div class="col-6">
                                    <h3 class="text-success mb-0"><?= $emergencyContacts ?></h3>
                                    <small class="text-muted">Emergency Contacts</small>
                                </div>
                            </div>
                            
                            <?php if (!empty($familyMembers)): ?>
                            <div class="border-top pt-3">
                                <h6 class="text-muted mb-2">Recent Family Members</h6>
                                <?php foreach (array_slice($familyMembers, 0, 3) as $member): ?>
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <span><?= htmlspecialchars($member->name) ?></span>
                                    <small class="text-muted"><?= htmlspecialchars($member->relationship) ?></small>
                                </div>
                                <?php endforeach; ?>
                                
                                <?php if (count($familyMembers) > 3): ?>
                                <small class="text-muted">And <?= count($familyMembers) - 3 ?> more...</small>
                                <?php endif; ?>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="row">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="fas fa-bolt"></i> Quick Military Actions</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-3 mb-3">
                                    <a href="/users/enhanced_personal.php" class="btn btn-outline-primary w-100 p-3">
                                        <i class="fas fa-user-edit fa-2x mb-2"></i><br>
                                        Update Profile
                                    </a>
                                </div>
                                <div class="col-md-3 mb-3">
                                    <a href="/users/service.php" class="btn btn-outline-success w-100 p-3">
                                        <i class="fas fa-medal fa-2x mb-2"></i><br>
                                        Service Record
                                    </a>
                                </div>
                                <div class="col-md-3 mb-3">
                                    <a href="#" class="btn btn-outline-warning w-100 p-3">
                                        <i class="fas fa-calendar-alt fa-2x mb-2"></i><br>
                                        Schedule Medical
                                    </a>
                                </div>
                                <div class="col-md-3 mb-3">
                                    <a href="#" class="btn btn-outline-danger w-100 p-3">
                                        <i class="fas fa-shield-alt fa-2x mb-2"></i><br>
                                        Renew Clearance
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.rank-badge, .clearance-badge, .medical-badge {
    width: 60px;
    height: 60px;
    border-radius: 50%;
    background: rgba(255,255,255,0.1);
    display: flex;
    align-items: center;
    justify-content: center;
}

.card {
    box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
    border: 1px solid rgba(0, 0, 0, 0.125);
}

.card-header {
    background-color: rgba(0, 0, 0, 0.03);
    border-bottom: 1px solid rgba(0, 0, 0, 0.125);
}

.badge {
    font-size: 0.75em;
}
</style>

<?php include dirname(__DIR__) . '/shared/footer.php'; ?>