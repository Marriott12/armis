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

$pageTitle = "User Profile";
$moduleName = "User Profile";
$moduleIcon = "user";
$currentPage = "profile";

$sidebarLinks = [
    ['title' => 'My Profile', 'url' => '/Armis2/users/index.php', 'icon' => 'user', 'page' => 'profile'],
    ['title' => 'Personal Info', 'url' => '/Armis2/users/personal.php', 'icon' => 'id-card', 'page' => 'personal'],
    ['title' => 'Service Record', 'url' => '/Armis2/users/service.php', 'icon' => 'medal', 'page' => 'service'],
    ['title' => 'Training History', 'url' => '/Armis2/users/training.php', 'icon' => 'graduation-cap', 'page' => 'training'],
    ['title' => 'Family Members', 'url' => '/Armis2/users/family.php', 'icon' => 'users', 'page' => 'family'],
    ['title' => 'Upload CV', 'url' => '/Armis2/users/cv_upload.php', 'icon' => 'file-upload', 'page' => 'cv_upload'],
    ['title' => 'Download CV', 'url' => '/Armis2/users/cv_download.php', 'icon' => 'download', 'page' => 'cv_download'],
    ['title' => 'Account Settings', 'url' => '/Armis2/users/settings.php', 'icon' => 'cogs', 'page' => 'settings']
];

// Load database connection directly for better performance
require_once dirname(__DIR__) . '/shared/database_connection.php';

// Load ProfileManager for photo functionality
require_once __DIR__ . '/profile_manager.php';

try {
    $pdo = getDbConnection();
    
    // Initialize ProfileManager
    $profileManager = new UserProfileManager($_SESSION['user_id']);
    
    // Get comprehensive user profile data - simplified query first
    $stmt = $pdo->prepare("
        SELECT 
            s.*
        FROM staff s
        WHERE s.id = ?
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $userData = $stmt->fetch(PDO::FETCH_OBJ);
    
    // Add calculated fields manually
    if ($userData) {
        $userData->age = (isset($userData->DOB) && $userData->DOB) ? floor((time() - strtotime($userData->DOB)) / (365.25 * 24 * 3600)) : 'N/A';
        $userData->serviceYears = (isset($userData->attestDate) && $userData->attestDate) ? floor((time() - strtotime($userData->attestDate)) / (365.25 * 24 * 3600)) : 'N/A';
        $userData->fullName = trim(($userData->prefix ?? '') . ' ' . ($userData->first_name ?? '') . ' ' . ($userData->last_name ?? ''));
        
        // Map database column names to expected property names for compatibility
        $userData->fname = $userData->first_name ?? null;
        $userData->lname = $userData->last_name ?? null;
        $userData->svcNo = $userData->service_number ?? null;
        $userData->rankID = $userData->rank_id ?? null;
        $userData->unitID = $userData->unit_id ?? null;
        
        // Get rank information if rank_id exists
        if (isset($userData->rank_id) && $userData->rank_id) {
            try {
                $rankStmt = $pdo->prepare("SELECT name, abbreviation FROM ranks WHERE id = ?");
                $rankStmt->execute([$userData->rank_id]);
                $rankData = $rankStmt->fetch(PDO::FETCH_OBJ);
                if ($rankData) {
                    $userData->rank_name = $rankData->name;
                    $userData->rank_abbr = $rankData->abbreviation;
                    $userData->displayRank = $rankData->name ?? $rankData->abbreviation;
                } else {
                    $userData->displayRank = 'N/A';
                }
            } catch (Exception $e) {
                $userData->displayRank = 'N/A';
                error_log("Rank query error: " . $e->getMessage());
            }
        } else {
            $userData->displayRank = 'N/A';
        }
        
        // Get unit information if unit_id exists
        if (isset($userData->unit_id) && $userData->unit_id) {
            try {
                $unitStmt = $pdo->prepare("SELECT name, code FROM units WHERE id = ?");
                $unitStmt->execute([$userData->unit_id]);
                $unitData = $unitStmt->fetch(PDO::FETCH_OBJ);
                if ($unitData) {
                    $userData->unit_name = $unitData->name;
                    $userData->unit_code = $unitData->code;
                } else {
                    $userData->unit_name = 'No Unit Assigned';
                }
            } catch (Exception $e) {
                $userData->unit_name = 'No Unit Assigned';
                error_log("Unit query error: " . $e->getMessage());
            }
        } else {
            $userData->unit_name = 'No Unit Assigned';
        }
    }
    
    // Get family members count
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM staff_family_members WHERE staff_id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $familyCount = $stmt->fetch(PDO::FETCH_OBJ)->count ?? 0;
    } catch (Exception $e) {
        $familyCount = 0;
        error_log("Family count query error: " . $e->getMessage());
    }
    
    // Get training records count
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM training_records WHERE staff_id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $trainingCount = $stmt->fetch(PDO::FETCH_OBJ)->count ?? 0;
    } catch (Exception $e) {
        // Table might not exist yet
        $trainingCount = 0;
        error_log("Training count query error (table may not exist): " . $e->getMessage());
    }
    
    // Get contact info count
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM staff_contact_info WHERE staff_id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $contactCount = $stmt->fetch(PDO::FETCH_OBJ)->count ?? 0;
    } catch (Exception $e) {
        $contactCount = 0;
        error_log("Contact count query error: " . $e->getMessage());
    }
    
    // Calculate profile completeness
    $completenessFields = [
        'first_name', 'last_name', 'DOB', 'attestDate', 'email', 'tel', 
        'NRC', 'gender', 'marital', 'unit_id', 'rank_id'
    ];
    $completedFields = 0;
    if ($userData) {
        foreach ($completenessFields as $field) {
            if (isset($userData->$field) && !empty($userData->$field)) {
                $completedFields++;
            }
        }
    }
    $profileCompleteness = round(($completedFields / count($completenessFields)) * 100);
    
    // Get recent activity (mock data for now)
    $recentActivity = [
        [
            'date' => date('Y-m-d', strtotime('-2 days')),
            'type' => 'Profile Update',
            'description' => 'Updated contact information',
            'status' => 'Completed',
            'icon' => 'user-edit',
            'color' => 'success'
        ],
        [
            'date' => date('Y-m-d', strtotime('-1 week')),
            'type' => 'Training Record',
            'description' => 'Added new training certificate',
            'status' => 'Verified',
            'icon' => 'graduation-cap',
            'color' => 'info'
        ]
    ];
    
    // Handle case where user profile doesn't exist
    if (!$userData) {
        $userData = (object)[
            'service_number' => 'Not Available',
            'svcNo' => 'Not Available',
            'fullName' => 'User Profile',
            'displayRank' => 'N/A',
            'unit_name' => 'N/A',
            'email' => 'No email on file',
            'tel' => 'No phone on file',
            'DOB' => null,
            'attestDate' => null,
            'age' => 'N/A',
            'serviceYears' => 'N/A',
            'svcStatus' => 'Unknown',
            'first_name' => 'Unknown',
            'last_name' => 'User',
            'fname' => 'Unknown',
            'lname' => 'User',
            'NRC' => null,
            'gender' => null,
            'marital' => null,
            'unit_id' => null,
            'rank_id' => null,
            'prefix' => null,
            'rank_name' => null,
            'rank_abbr' => null,
            'unit_code' => null
        ];
        $profileCompleteness = 0;
        $familyCount = 0;
        $trainingCount = 0;
        $contactCount = 0;
    }
    
} catch (Exception $e) {
    error_log("Profile loading error: " . $e->getMessage());
    $userData = (object)[
        'service_number' => 'Error',
        'svcNo' => 'Error',
        'fullName' => 'Error Loading Profile',
        'displayRank' => 'N/A',
        'unit_name' => 'N/A',
        'email' => 'Error',
        'tel' => 'Error',
        'age' => 'N/A',
        'serviceYears' => 'N/A',
        'svcStatus' => 'Unknown',
        'DOB' => null,
        'attestDate' => null,
        'first_name' => 'Error',
        'last_name' => 'Loading',
        'fname' => 'Error',
        'lname' => 'Loading',
        'NRC' => null,
        'gender' => null,
        'marital' => null,
        'unit_id' => null,
        'rank_id' => null,
        'prefix' => null,
        'rank_name' => null,
        'rank_abbr' => null,
        'unit_code' => null
    ];
    $recentActivity = [];
    $profileCompleteness = 0;
    $familyCount = 0;
    $trainingCount = 0;
    $contactCount = 0;
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
                            <i class="fas fa-user"></i> My Profile
                        </h1>
                        <div>
                            <span class="badge bg-<?= (isset($userData->svcStatus) && $userData->svcStatus === 'active') ? 'success' : 'secondary' ?> me-2">
                                <?= ucfirst($userData->svcStatus ?? 'Unknown') ?>
                            </span>
                            <a href="/Armis2/users/cv_download.php" class="btn btn-primary">
                                <i class="fas fa-download"></i> Download CV
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Profile Completeness Alert -->
            <?php if ($profileCompleteness < 80): ?>
            <div class="row mb-3">
                <div class="col-12">
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle"></i>
                        <strong>Profile Incomplete</strong> - Your profile is <?= $profileCompleteness ?>% complete. 
                        <a href="/Armis2/users/personal.php" class="alert-link">Complete your profile</a> to access all features.
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Profile Overview -->
            <div class="row">
                <div class="col-lg-4 mb-4">
                    <div class="card dashboard-card h-100">
                        <div class="card-body text-center">
                            <div class="mb-3">
                                <img src="<?= $profileManager->getProfilePhotoURL() ?>" 
                                     alt="Profile Picture" 
                                     class="rounded-circle" 
                                     style="width: 120px; height: 120px; object-fit: cover; border: 3px solid #dee2e6;"
                                     onerror="this.src='/Armis2/shared/default_avatar.php'">
                            </div>
                            <h4><?= htmlspecialchars($userData->fullName ?? 'Unknown User') ?></h4>
                            <p class="text-muted"><?= htmlspecialchars($userData->svcNo ?? 'N/A') ?></p>
                            <p class="text-muted">
                                <strong><?= htmlspecialchars($userData->displayRank ?? 'N/A') ?></strong><br>
                                <?= htmlspecialchars($userData->unit_name ?? 'No Unit Assigned') ?>
                            </p>
                            
                            <!-- Profile Completeness Progress -->
                            <div class="mt-3">
                                <small class="text-muted">Profile Completeness</small>
                                <div class="progress mb-2" style="height: 6px;">
                                    <div class="progress-bar bg-<?= $profileCompleteness >= 80 ? 'success' : ($profileCompleteness >= 60 ? 'warning' : 'danger') ?>" 
                                         style="width: <?= $profileCompleteness ?>%"></div>
                                </div>
                                <small class="text-muted"><?= $profileCompleteness ?>% Complete</small>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-8 mb-4">
                    <div class="card dashboard-card h-100">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="fas fa-info-circle"></i> Personal Information</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label text-muted small">Service Number</label>
                                    <p class="fw-bold"><?= htmlspecialchars($userData->svcNo ?? 'Not Available') ?></p>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label text-muted small">Current Rank</label>
                                    <p class="fw-bold"><?= htmlspecialchars($userData->displayRank ?? 'N/A') ?></p>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label text-muted small">Email Address</label>
                                    <p class="fw-bold"><?= htmlspecialchars($userData->email ?? 'Not provided') ?></p>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label text-muted small">Phone Number</label>
                                    <p class="fw-bold"><?= htmlspecialchars($userData->tel ?? 'Not provided') ?></p>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label text-muted small">Age</label>
                                    <p class="fw-bold">
                                        <?= htmlspecialchars($userData->age ?? 'N/A') ?>
                                        <?php if (isset($userData->DOB) && $userData->DOB): ?>
                                            <small class="text-muted">(<?= date('M j, Y', strtotime($userData->DOB)) ?>)</small>
                                        <?php endif; ?>
                                    </p>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label text-muted small">Years of Service</label>
                                    <p class="fw-bold">
                                        <?= htmlspecialchars($userData->serviceYears ?? 'N/A') ?>
                                        <?php if (isset($userData->attestDate) && $userData->attestDate): ?>
                                            <small class="text-muted">(Since <?= date('M Y', strtotime($userData->attestDate)) ?>)</small>
                                        <?php endif; ?>
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Statistics Cards -->
            <div class="row mb-4">
                <div class="col-md-3 mb-3">
                    <div class="card bg-primary text-white">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h4 class="mb-0"><?= $trainingCount ?></h4>
                                    <small>Training Courses</small>
                                </div>
                                <i class="fas fa-graduation-cap fa-2x opacity-75"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="card bg-success text-white">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h4 class="mb-0"><?= $familyCount ?></h4>
                                    <small>Family Members</small>
                                </div>
                                <i class="fas fa-users fa-2x opacity-75"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="card bg-warning text-white">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h4 class="mb-0"><?= $contactCount ?></h4>
                                    <small>Contact Methods</small>
                                </div>
                                <i class="fas fa-address-book fa-2x opacity-75"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="card bg-info text-white">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h4 class="mb-0"><?= $profileCompleteness ?>%</h4>
                                    <small>Profile Complete</small>
                                </div>
                                <i class="fas fa-chart-pie fa-2x opacity-75"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="row">
                <div class="col-12">
                    <div class="card dashboard-card">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="fas fa-bolt"></i> Quick Actions</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-3 mb-3">
                                    <a href="/Armis2/users/personal.php" class="btn btn-outline-primary w-100 p-3">
                                        <i class="fas fa-id-card fa-2x mb-2"></i><br>
                                        Update Personal Info
                                    </a>
                                </div>
                                <div class="col-md-3 mb-3">
                                    <a href="/Armis2/users/service.php" class="btn btn-outline-success w-100 p-3">
                                        <i class="fas fa-medal fa-2x mb-2"></i><br>
                                        Service Record
                                    </a>
                                </div>
                                <div class="col-md-3 mb-3">
                                    <a href="/Armis2/users/training.php" class="btn btn-outline-warning w-100 p-3">
                                        <i class="fas fa-graduation-cap fa-2x mb-2"></i><br>
                                        Training History
                                    </a>
                                </div>
                                <div class="col-md-3 mb-3">
                                    <a href="/Armis2/users/family.php" class="btn btn-outline-info w-100 p-3">
                                        <i class="fas fa-users fa-2x mb-2"></i><br>
                                        Family Members
                                    </a>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <a href="/Armis2/users/my_cvs.php" class="btn btn-outline-primary w-100 p-3">
                                        <i class="fas fa-file-alt fa-2x mb-2"></i><br>
                                        My CVs
                                    </a>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <a href="/Armis2/users/cv_download.php" class="btn btn-outline-danger w-100 p-3">
                                        <i class="fas fa-download fa-2x mb-2"></i><br>
                                        Download CV
                                    </a>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <a href="/Armis2/users/settings.php" class="btn btn-outline-secondary w-100 p-3">
                                        <i class="fas fa-cogs fa-2x mb-2"></i><br>
                                        Account Settings
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent Activity -->
            <div class="row">
                <div class="col-12">
                    <div class="card dashboard-card">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="fas fa-clock"></i> Recent Activity</h5>
                        </div>
                        <div class="card-body">
                            <?php if (empty($recentActivity)): ?>
                                <div class="text-center py-4">
                                    <i class="fas fa-history fa-3x text-muted mb-3"></i>
                                    <h5 class="text-muted">No Recent Activity</h5>
                                    <p class="text-muted">Your recent activities will appear here as you use the system.</p>
                                </div>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-striped">
                                        <thead>
                                            <tr>
                                                <th>Date</th>
                                                <th>Activity</th>
                                                <th>Details</th>
                                                <th>Status</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($recentActivity as $activity): ?>
                                            <tr>
                                                <td><?= date('M j, Y', strtotime($activity['date'])) ?></td>
                                                <td>
                                                    <i class="fas fa-<?= $activity['icon'] ?> text-<?= $activity['color'] ?> me-2"></i>
                                                    <?= htmlspecialchars($activity['type']) ?>
                                                </td>
                                                <td><?= htmlspecialchars($activity['description']) ?></td>
                                                <td>
                                                    <span class="badge bg-<?= $activity['color'] ?>">
                                                        <?= htmlspecialchars($activity['status']) ?>
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
