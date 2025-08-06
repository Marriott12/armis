<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ' . dirname($_SERVER['PHP_SELF']) . '/../login.php');
    exit();
}

$pageTitle = "Service Record";
$moduleName = "User Profile";
$moduleIcon = "medal";
$currentPage = "service";

$sidebarLinks = [
    ['title' => 'My Profile', 'url' => '/Armis2/users/index.php', 'icon' => 'user', 'page' => 'profile'],
    ['title' => 'Personal Info', 'url' => '/Armis2/users/personal.php', 'icon' => 'id-card', 'page' => 'personal'],
    ['title' => 'Service Record', 'url' => '/Armis2/users/service.php', 'icon' => 'medal', 'page' => 'service'],
    ['title' => 'Training History', 'url' => '/Armis2/users/training.php', 'icon' => 'graduation-cap', 'page' => 'training'],
    ['title' => 'Family Members', 'url' => '/Armis2/users/family.php', 'icon' => 'users', 'page' => 'family'],
    ['title' => 'Download CV', 'url' => '/Armis2/users/cv_download.php', 'icon' => 'download', 'page' => 'cv_download'],
    ['title' => 'Account Settings', 'url' => '/Armis2/users/settings.php', 'icon' => 'cogs', 'page' => 'settings']
];

// Load user profile data
require_once __DIR__ . '/profile_manager.php';

try {
    $profileManager = new UserProfileManager($_SESSION['user_id']);
    $userData = $profileManager->getUserProfile();
    $awards = $profileManager->getAwards();
    $deployments = $profileManager->getDeployments();
    $medicalInfo = $profileManager->getMedicalInfo(false); // Basic medical info only
    
} catch (Exception $e) {
    error_log("Service record page error: " . $e->getMessage());
    $userData = null;
    $awards = [];
    $deployments = [];
    $medicalInfo = null;
}

include dirname(__DIR__) . '/shared/header.php';
include dirname(__DIR__) . '/shared/sidebar.php';
?>

<!-- Main Content -->
<div class="content-wrapper with-sidebar">
    <div class="container-fluid">
        <div class="main-content">
            <!-- Header -->
            <div class="row">
                <div class="col-12">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h1 class="section-title">
                            <i class="fas fa-medal"></i> Service Record
                        </h1>
                        <div>
                            <a href="/Armis2/users/cv_download.php" class="btn btn-primary me-2">
                                <i class="fas fa-download"></i> Download Full Record
                            </a>
                            <a href="/Armis2/users/index.php" class="btn btn-secondary">
                                <i class="fas fa-arrow-left"></i> Back to Profile
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Service Overview -->
            <div class="row mb-4">
                <div class="col-lg-8">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="fas fa-id-badge"></i> Service Information</h5>
                        </div>
                        <div class="card-body">
                            <!-- Profile Photo Section -->
                            <div class="row mb-4">
                                <div class="col-md-12 text-center">
                                    <img src="<?= $profileManager->getProfilePhotoURL() ?>" 
                                         alt="Profile Photo" 
                                         class="rounded-circle border" 
                                         style="width: 120px; height: 120px; object-fit: cover;">
                                    <h5 class="mt-2 mb-0"><?= htmlspecialchars(($userData && isset($userData->fullName)) ? $userData->fullName : 'N/A') ?></h5>
                                    <p class="text-muted"><?= htmlspecialchars(($userData && isset($userData->svcNo)) ? $userData->svcNo : 'N/A') ?></p>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label text-muted small">Full Name</label>
                                    <p class="fw-bold"><?= htmlspecialchars(($userData && isset($userData->fullName)) ? $userData->fullName : 'N/A') ?></p>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label text-muted small">Service Number</label>
                                    <p class="fw-bold"><?= htmlspecialchars(($userData && isset($userData->svcNo)) ? $userData->svcNo : 'N/A') ?></p>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label text-muted small">Current Rank</label>
                                    <p class="fw-bold"><?= htmlspecialchars(($userData && isset($userData->displayRank)) ? $userData->displayRank : 'N/A') ?></p>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label text-muted small">Unit</label>
                                    <p class="fw-bold"><?= htmlspecialchars(($userData && isset($userData->unitName)) ? $userData->unitName : 'N/A') ?></p>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label text-muted small">Corps</label>
                                    <p class="fw-bold"><?= htmlspecialchars(($userData && (isset($userData->corps_name) || isset($userData->corps))) ? ($userData->corps_name ?? $userData->corps) : 'N/A') ?></p>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label text-muted small">Service Status</label>
                                    <p class="fw-bold">
                                        <span class="badge bg-<?= (($userData && isset($userData->svcStatus) && $userData->svcStatus) === 'active') ? 'success' : 'secondary' ?>">
                                            <?= ucfirst(($userData && isset($userData->svcStatus)) ? $userData->svcStatus : 'Unknown') ?>
                                        </span>
                                    </p>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label text-muted small">Date of Attestation</label>
                                    <p class="fw-bold">
                                        <?php if ($userData && isset($userData->attestDate) && $userData->attestDate): ?>
                                            <?= date('F j, Y', strtotime($userData->attestDate)) ?>
                                        <?php else: ?>
                                            Not Available
                                        <?php endif; ?>
                                    </p>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label text-muted small">Years of Service</label>
                                    <p class="fw-bold"><?= htmlspecialchars(($userData && isset($userData->serviceYears)) ? $userData->serviceYears : 'N/A') ?></p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Medical Category -->
                <div class="col-lg-4">
                    <div class="card h-100">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="fas fa-heartbeat"></i> Medical Category</h5>
                        </div>
                        <div class="card-body">
                            <?php if ($medicalInfo): ?>
                                <div class="text-center">
                                    <div class="display-4 text-primary mb-2">
                                        <?= htmlspecialchars($medicalInfo->medical_category ?? 'N/A') ?>
                                    </div>
                                    <p class="text-muted">Medical Category</p>
                                    
                                    <div class="mt-3">
                                        <span class="badge bg-<?= 
                                            ($medicalInfo->fitness_status ?? '') === 'Fit' ? 'success' : 
                                            (($medicalInfo->fitness_status ?? '') === 'Limited Duties' ? 'warning' : 'danger') 
                                        ?> p-2">
                                            <?= htmlspecialchars($medicalInfo->fitness_status ?? 'Unknown') ?>
                                        </span>
                                    </div>
                                    
                                    <?php if ($medicalInfo->last_medical_exam): ?>
                                        <p class="text-muted mt-3 mb-0">
                                            <small>Last Exam: <?= date('M j, Y', strtotime($medicalInfo->last_medical_exam)) ?></small>
                                        </p>
                                    <?php endif; ?>
                                    
                                    <?php if ($medicalInfo->next_medical_due): ?>
                                        <p class="text-muted mb-0">
                                            <small>Next Due: <?= date('M j, Y', strtotime($medicalInfo->next_medical_due)) ?></small>
                                        </p>
                                    <?php endif; ?>
                                </div>
                            <?php else: ?>
                                <div class="text-center text-muted">
                                    <i class="fas fa-medical-kit fa-3x mb-3 opacity-50"></i>
                                    <p>No medical information available</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Awards and Decorations -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="fas fa-trophy"></i> Awards and Decorations</h5>
                        </div>
                        <div class="card-body">
                            <?php if (empty($awards)): ?>
                                <div class="text-center py-4">
                                    <i class="fas fa-trophy fa-3x text-muted mb-3"></i>
                                    <h5 class="text-muted">No Awards Recorded</h5>
                                    <p class="text-muted">Awards and decorations will appear here when they are recorded in your service file.</p>
                                </div>
                            <?php else: ?>
                                <div class="row">
                                    <?php foreach ($awards as $award): ?>
                                        <div class="col-md-6 col-lg-4 mb-3">
                                            <div class="card border-left-primary">
                                                <div class="card-body">
                                                    <div class="d-flex align-items-center">
                                                        <div class="flex-shrink-0">
                                                            <i class="fas fa-medal fa-2x text-warning"></i>
                                                        </div>
                                                        <div class="flex-grow-1 ms-3">
                                                            <h6 class="mb-1"><?= htmlspecialchars($award->award_name) ?></h6>
                                                            <p class="text-muted mb-1">
                                                                <small><?= htmlspecialchars($award->award_type) ?></small>
                                                            </p>
                                                            <p class="text-muted mb-0">
                                                                <small><?= date('M j, Y', strtotime($award->date_awarded)) ?></small>
                                                            </p>
                                                        </div>
                                                    </div>
                                                    <?php if ($award->citation): ?>
                                                        <div class="mt-2">
                                                            <small class="text-muted"><?= htmlspecialchars($award->citation) ?></small>
                                                        </div>
                                                    <?php endif; ?>
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

            <!-- Deployment History -->
            <div class="row">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="fas fa-globe"></i> Deployment History</h5>
                        </div>
                        <div class="card-body">
                            <?php if (empty($deployments)): ?>
                                <div class="text-center py-4">
                                    <i class="fas fa-globe fa-3x text-muted mb-3"></i>
                                    <h5 class="text-muted">No Deployments Recorded</h5>
                                    <p class="text-muted">Your deployment history will appear here when recorded.</p>
                                </div>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-striped">
                                        <thead>
                                            <tr>
                                                <th>Deployment</th>
                                                <th>Location</th>
                                                <th>Type</th>
                                                <th>Role</th>
                                                <th>Duration</th>
                                                <th>Status</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($deployments as $deployment): ?>
                                                <tr>
                                                    <td>
                                                        <strong><?= htmlspecialchars($deployment->deployment_name) ?></strong>
                                                    </td>
                                                    <td><?= htmlspecialchars($deployment->location) ?></td>
                                                    <td>
                                                        <span class="badge bg-info">
                                                            <?= htmlspecialchars($deployment->deployment_type) ?>
                                                        </span>
                                                    </td>
                                                    <td><?= htmlspecialchars($deployment->role ?? 'N/A') ?></td>
                                                    <td>
                                                        <?= date('M j, Y', strtotime($deployment->start_date)) ?>
                                                        <?php if ($deployment->end_date): ?>
                                                            - <?= date('M j, Y', strtotime($deployment->end_date)) ?>
                                                        <?php else: ?>
                                                            - Ongoing
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <span class="badge bg-<?= 
                                                            $deployment->status === 'Deployed' ? 'primary' : 
                                                            ($deployment->status === 'Returned' ? 'success' : 'secondary') 
                                                        ?>">
                                                            <?= htmlspecialchars($deployment->status) ?>
                                                        </span>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include dirname(__DIR__) . '/shared/footer.php'; ?>
