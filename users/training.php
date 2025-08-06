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

$pageTitle = "Training History";
$moduleName = "User Profile";
$moduleIcon = "graduation-cap";
$currentPage = "training";

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
    $trainingRecords = $profileManager->getTrainingRecords();
    $educationRecords = $profileManager->getEducationRecords();
    $skills = $profileManager->getSkills();
    
} catch (Exception $e) {
    error_log("Training history page error: " . $e->getMessage());
    $userData = null;
    $trainingRecords = [];
    $educationRecords = [];
    $skills = [];
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
                            <i class="fas fa-graduation-cap"></i> Training & Education History
                        </h1>
                        <a href="/Armis2/users/index.php" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i> Back to Profile
                        </a>
                    </div>
                </div>
            </div>

            <!-- Statistics Cards -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="card bg-primary text-white">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h3 class="mb-0"><?= count($trainingRecords) ?></h3>
                                    <p class="mb-0">Training Courses</p>
                                </div>
                                <i class="fas fa-chalkboard-teacher fa-2x opacity-75"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-success text-white">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h3 class="mb-0"><?= count($educationRecords) ?></h3>
                                    <p class="mb-0">Education Records</p>
                                </div>
                                <i class="fas fa-university fa-2x opacity-75"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-warning text-white">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h3 class="mb-0"><?= count($skills) ?></h3>
                                    <p class="mb-0">Skills & Competencies</p>
                                </div>
                                <i class="fas fa-tools fa-2x opacity-75"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-info text-white">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h3 class="mb-0">
                                        <?= count(array_filter($trainingRecords, function($t) { 
                                            return $t->status === 'completed'; 
                                        })) ?>
                                    </h3>
                                    <p class="mb-0">Completed</p>
                                </div>
                                <i class="fas fa-check-circle fa-2x opacity-75"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Training Records -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="fas fa-chalkboard-teacher"></i> Military Training Courses</h5>
                        </div>
                        <div class="card-body">
                            <?php if (empty($trainingRecords)): ?>
                                <div class="text-center py-4">
                                    <i class="fas fa-chalkboard-teacher fa-3x text-muted mb-3"></i>
                                    <h5 class="text-muted">No Training Records</h5>
                                    <p class="text-muted">Your military training courses will appear here when recorded.</p>
                                </div>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-striped">
                                        <thead>
                                            <tr>
                                                <th>Course Name</th>
                                                <th>Course Code</th>
                                                <th>Duration</th>
                                                <th>Status</th>
                                                <th>Score</th>
                                                <th>Location</th>
                                                <th>Instructor</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($trainingRecords as $training): ?>
                                                <tr>
                                                    <td>
                                                        <strong><?= htmlspecialchars($training->course_name) ?></strong>
                                                    </td>
                                                    <td><?= htmlspecialchars($training->course_code ?? 'N/A') ?></td>
                                                    <td>
                                                        <?php if ($training->start_date): ?>
                                                            <?= date('M j, Y', strtotime($training->start_date)) ?>
                                                            <?php if ($training->end_date): ?>
                                                                - <?= date('M j, Y', strtotime($training->end_date)) ?>
                                                            <?php endif; ?>
                                                        <?php else: ?>
                                                            Not specified
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <span class="badge bg-<?= 
                                                            $training->status === 'completed' ? 'success' : 
                                                            ($training->status === 'in_progress' ? 'primary' : 
                                                            ($training->status === 'failed' ? 'danger' : 'secondary')) 
                                                        ?>">
                                                            <?= ucfirst($training->status) ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <?php if ($training->score): ?>
                                                            <span class="badge bg-<?= 
                                                                $training->score >= 80 ? 'success' : 
                                                                ($training->score >= 60 ? 'warning' : 'danger') 
                                                            ?>">
                                                                <?= htmlspecialchars($training->score) ?>%
                                                            </span>
                                                        <?php else: ?>
                                                            N/A
                                                        <?php endif; ?>
                                                    </td>
                                                    <td><?= htmlspecialchars($training->location ?? 'N/A') ?></td>
                                                    <td><?= htmlspecialchars($training->instructor ?? 'N/A') ?></td>
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

            <!-- Education Records -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="fas fa-university"></i> Education & Qualifications</h5>
                        </div>
                        <div class="card-body">
                            <?php if (empty($educationRecords)): ?>
                                <div class="text-center py-4">
                                    <i class="fas fa-university fa-3x text-muted mb-3"></i>
                                    <h5 class="text-muted">No Education Records</h5>
                                    <p class="text-muted">Your educational qualifications will appear here when recorded.</p>
                                </div>
                            <?php else: ?>
                                <div class="row">
                                    <?php foreach ($educationRecords as $education): ?>
                                        <div class="col-md-6 col-lg-4 mb-3">
                                            <div class="card border-left-success">
                                                <div class="card-body">
                                                    <div class="d-flex align-items-start">
                                                        <div class="flex-shrink-0">
                                                            <i class="fas fa-graduation-cap fa-2x text-success"></i>
                                                        </div>
                                                        <div class="flex-grow-1 ms-3">
                                                            <h6 class="mb-1"><?= htmlspecialchars($education->qualification ?? $education->level) ?></h6>
                                                            <p class="text-muted mb-1">
                                                                <strong><?= htmlspecialchars($education->institution) ?></strong>
                                                            </p>
                                                            <p class="text-muted mb-1">
                                                                <span class="badge bg-info">
                                                                    <?= htmlspecialchars($education->level) ?>
                                                                </span>
                                                            </p>
                                                            <p class="text-muted mb-0">
                                                                <small>
                                                                    <?php if ($education->year_started && $education->year_completed): ?>
                                                                        <?= $education->year_started ?> - <?= $education->year_completed ?>
                                                                    <?php elseif ($education->year_completed): ?>
                                                                        Completed: <?= $education->year_completed ?>
                                                                    <?php else: ?>
                                                                        Year not specified
                                                                    <?php endif; ?>
                                                                </small>
                                                            </p>
                                                            <?php if ($education->field_of_study): ?>
                                                                <p class="text-muted mb-0">
                                                                    <small><?= htmlspecialchars($education->field_of_study) ?></small>
                                                                </p>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
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

            <!-- Skills and Competencies -->
            <div class="row">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="fas fa-tools"></i> Skills & Competencies</h5>
                        </div>
                        <div class="card-body">
                            <?php if (empty($skills)): ?>
                                <div class="text-center py-4">
                                    <i class="fas fa-tools fa-3x text-muted mb-3"></i>
                                    <h5 class="text-muted">No Skills Recorded</h5>
                                    <p class="text-muted">Your skills and competencies will appear here when recorded.</p>
                                </div>
                            <?php else: ?>
                                <?php 
                                // Group skills by category
                                $skillsByCategory = [];
                                foreach ($skills as $skill) {
                                    $category = $skill->skill_category ?? 'Other';
                                    if (!isset($skillsByCategory[$category])) {
                                        $skillsByCategory[$category] = [];
                                    }
                                    $skillsByCategory[$category][] = $skill;
                                }
                                ?>
                                
                                <?php foreach ($skillsByCategory as $category => $categorySkills): ?>
                                    <div class="mb-4">
                                        <h6 class="text-primary mb-3">
                                            <i class="fas fa-<?= 
                                                $category === 'Technical' ? 'cogs' : 
                                                ($category === 'Leadership' ? 'users-cog' : 
                                                ($category === 'Communication' ? 'comments' : 
                                                ($category === 'Military' ? 'shield-alt' : 'tools'))) 
                                            ?>"></i>
                                            <?= htmlspecialchars($category) ?> Skills
                                        </h6>
                                        <div class="row">
                                            <?php foreach ($categorySkills as $skill): ?>
                                                <div class="col-md-6 col-lg-4 mb-2">
                                                    <div class="d-flex justify-content-between align-items-center bg-light p-2 rounded">
                                                        <span><?= htmlspecialchars($skill->skill_name) ?></span>
                                                        <span class="badge bg-<?= 
                                                            $skill->proficiency_level === 'Expert' ? 'success' : 
                                                            ($skill->proficiency_level === 'Advanced' ? 'info' : 
                                                            ($skill->proficiency_level === 'Intermediate' ? 'warning' : 'secondary')) 
                                                        ?> rounded-pill">
                                                            <?= htmlspecialchars($skill->proficiency_level) ?>
                                                        </span>
                                                    </div>
                                                    <?php if ($skill->years_experience): ?>
                                                        <small class="text-muted">
                                                            <?= $skill->years_experience ?> years experience
                                                        </small>
                                                    <?php endif; ?>
                                                    <?php if ($skill->certification): ?>
                                                        <br><small class="text-success">
                                                            <i class="fas fa-certificate"></i> <?= htmlspecialchars($skill->certification) ?>
                                                        </small>
                                                    <?php endif; ?>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include dirname(__DIR__) . '/shared/footer.php'; ?>
