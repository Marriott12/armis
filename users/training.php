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

// Load shared navigation
require_once dirname(__DIR__) . '/shared/user_navigation.php';
$sidebarLinks = $userNavigationItems;

// Load components
require_once dirname(__DIR__) . '/shared/components/stat_card.php';
require_once dirname(__DIR__) . '/shared/components/empty_state.php';

// Load user profile data
require_once __DIR__ . '/profile_manager.php';
require_once dirname(__DIR__) . '/shared/csrf.php';

try {
    $profileManager = new UserProfileManager($_SESSION['user_id']);
    $userData = $profileManager->getUserProfile();
    $trainingRecords = $profileManager->getTrainingRecords();
    $educationRecords = $profileManager->getEducationRecords();
    $languageRecords = $profileManager->getLanguageRecords();
    $skills = $profileManager->getSkills();
    
} catch (Exception $e) {
    error_log("Training history page error: " . $e->getMessage());
    $userData = null;
    $trainingRecords = [];
    $educationRecords = [];
    $languageRecords = [];
    $skills = [];
}

// FIX: adding/editing education and language records used to only be
// possible from a form embedded in users/personal.php (which had
// nothing to do with training/education otherwise) — this page could
// only ever display them. Real add/edit handling now lives here,
// where the records are actually shown.
$success = '';
$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_education_language'])) {
    require_csrf();
    $successMessages = [];
    $educationResult = $profileManager->updateEducationRecords($_POST['education'] ?? []);
    if ($educationResult['success']) {
        $successMessages[] = $educationResult['message'];
        $educationRecords = $profileManager->getEducationRecords();
    } else {
        $errors[] = $educationResult['message'];
    }

    $languageResult = $profileManager->updateLanguageRecords($_POST['languages'] ?? []);
    if ($languageResult['success']) {
        $successMessages[] = $languageResult['message'];
        $languageRecords = $profileManager->getLanguageRecords();
    } else {
        $errors[] = $languageResult['message'];
    }

    if (empty($errors)) {
        $success = implode(' ', $successMessages);
    }
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

            <?php if ($success): ?>
                <div class="alert alert-success alert-dismissible fade show">
                    <?= htmlspecialchars($success) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger alert-dismissible fade show">
                    <?= htmlspecialchars(implode(' ', $errors)) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

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
                                                        <?php if ($training->startDate): ?>
                                                            <?= date('M j, Y', strtotime($training->startDate)) ?>
                                                            <?php if ($training->endDate): ?>
                                                                - <?= date('M j, Y', strtotime($training->endDate)) ?>
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
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="mb-0"><i class="fas fa-university"></i> Education & Qualifications</h5>
                            <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#editEducationLanguageModal">
                                <i class="fas fa-pen"></i> Add / Edit
                            </button>
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
                                                            <h6 class="mb-1"><?= htmlspecialchars($education['qualification'] ?? $education['level'] ?? 'Qualification not specified') ?></h6>
                                                            <p class="text-muted mb-1">
                                                                <strong><?= htmlspecialchars($education['institution'] ?? 'Institution not specified') ?></strong>
                                                            </p>
                                                            <?php if (!empty($education['course_id'])): ?><p class="text-muted mb-1"><small>Course ID: <?= htmlspecialchars($education['course_id']) ?></small></p><?php endif; ?>
                                                            <?php if (!empty($education['result']) || !empty($education['grade'])): ?><p class="text-muted mb-1"><small><?= !empty($education['result']) ? 'Result: ' . htmlspecialchars($education['result']) : '' ?><?= !empty($education['result']) && !empty($education['grade']) ? ' | ' : '' ?><?= !empty($education['grade']) ? 'Grade: ' . htmlspecialchars($education['grade']) : '' ?></small></p><?php endif; ?>
                                                            <p class="text-muted mb-0">
                                                                <small>
                                                                    <?php if (!empty($education['year_started']) && !empty($education['year_completed'])): ?>
                                                                        <?= htmlspecialchars($education['year_started']) ?> - <?= htmlspecialchars($education['year_completed']) ?>
                                                                    <?php elseif (!empty($education['year_completed'])): ?>
                                                                        Completed: <?= htmlspecialchars($education['year_completed']) ?>
                                                                    <?php else: ?>
                                                                        Year not specified
                                                                    <?php endif; ?>
                                                                </small>
                                                            </p>
                                                            <?php if (!empty($education['authID'])): ?><p class="text-muted mb-0"><small>Authority: <?= htmlspecialchars($education['authID']) ?></small></p><?php endif; ?>
                                                            <?php if (!empty($education['field_of_study'])): ?>
                                                                <p class="text-muted mb-0">
                                                                    <small><?= htmlspecialchars($education['field_of_study']) ?></small>
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

            <!-- Language Skills -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="mb-0"><i class="fas fa-language"></i> Language Skills</h5>
                            <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#editEducationLanguageModal">
                                <i class="fas fa-pen"></i> Add / Edit
                            </button>
                        </div>
                        <div class="card-body">
                            <?php if (empty($languageRecords)): ?>
                                <div class="text-center py-4">
                                    <i class="fas fa-language fa-3x text-muted mb-3"></i>
                                    <h5 class="text-muted">No Language Records</h5>
                                    <p class="text-muted">Your language skills will appear here when recorded.</p>
                                </div>
                            <?php else: ?>
                                <div class="row">
                                    <?php foreach ($languageRecords as $lang): ?>
                                        <div class="col-md-4 mb-3">
                                            <div class="card border-left-info">
                                                <div class="card-body">
                                                    <h6 class="mb-1"><?= htmlspecialchars($lang['language_name'] ?? 'Language not specified') ?></h6>
                                                    <p class="text-muted mb-1"><small>Proficiency: <?= htmlspecialchars($lang['proficiency_level'] ?? 'Not specified') ?></small></p>
                                                    <p class="text-muted mb-0">
                                                        <small>
                                                            <?php
                                                            $abilities = [];
                                                            if (!empty($lang['can_speak'])) $abilities[] = 'Speak';
                                                            if (!empty($lang['can_read'])) $abilities[] = 'Read';
                                                            if (!empty($lang['can_write'])) $abilities[] = 'Write';
                                                            if (!empty($lang['can_understand'])) $abilities[] = 'Understand';
                                                            echo $abilities ? htmlspecialchars(implode(', ', $abilities)) : 'No abilities specified';
                                                            ?>
                                                        </small>
                                                    </p>
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
                                    $category = $skill->skillCategory ?? 'Other';
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
                                                        <span><?= htmlspecialchars($skill->skillName) ?></span>
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

<!-- Add/Edit Education & Language Modal -->
<div class="modal fade" id="editEducationLanguageModal" tabindex="-1" aria-labelledby="editEducationLanguageModalLabel">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <form method="POST" id="educationLanguageForm">
                <?= csrf_field() ?>
                <input type="hidden" name="update_education_language" value="1">
                <div class="modal-header">
                    <h5 class="modal-title" id="editEducationLanguageModalLabel">Add / Edit Education &amp; Language Records</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <ul class="nav nav-tabs mb-3">
                        <li class="nav-item">
                            <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tab-education" type="button">Education</button>
                        </li>
                        <li class="nav-item">
                            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-language" type="button">Languages</button>
                        </li>
                    </ul>
                    <div class="tab-content">
                        <div class="tab-pane fade show active" id="tab-education">
                            <p class="text-muted small">To remove a record, clear its Institution and Qualification fields, then save.</p>
                            <div id="educationRows">
                                <?php foreach ($educationRecords as $i => $edu): ?>
                                    <div class="row g-2 mb-2 border-bottom pb-2 education-row">
                                        <input type="hidden" name="education[<?= $i ?>][id]" value="<?= htmlspecialchars($edu['id'] ?? '') ?>">
                                        <div class="col-md-6">
                                            <input type="text" class="form-control form-control-sm" name="education[<?= $i ?>][institution]" placeholder="Institution" value="<?= htmlspecialchars($edu['institution'] ?? '') ?>">
                                        </div>
                                        <div class="col-md-6">
                                            <input type="text" class="form-control form-control-sm" name="education[<?= $i ?>][qualification]" placeholder="Qualification" value="<?= htmlspecialchars($edu['qualification'] ?? '') ?>">
                                        </div>
                                        <div class="col-md-3">
                                            <input type="text" class="form-control form-control-sm" name="education[<?= $i ?>][course_id]" placeholder="Course ID" value="<?= htmlspecialchars($edu['course_id'] ?? '') ?>">
                                        </div>
                                        <div class="col-md-3">
                                            <input type="text" class="form-control form-control-sm" name="education[<?= $i ?>][year_started]" placeholder="Year started" value="<?= htmlspecialchars(substr($edu['year_started'] ?? '', 0, 4)) ?>">
                                        </div>
                                        <div class="col-md-3">
                                            <input type="text" class="form-control form-control-sm" name="education[<?= $i ?>][year_completed]" placeholder="Year completed" value="<?= htmlspecialchars(substr($edu['year_completed'] ?? '', 0, 4)) ?>">
                                        </div>
                                        <div class="col-md-3">
                                            <input type="text" class="form-control form-control-sm" name="education[<?= $i ?>][grade_obtained]" placeholder="Grade" value="<?= htmlspecialchars($edu['grade_obtained'] ?? '') ?>">
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <button type="button" class="btn btn-sm btn-outline-secondary" id="addEducationRow">
                                <i class="fas fa-plus"></i> Add another education record
                            </button>
                        </div>
                        <div class="tab-pane fade" id="tab-language">
                            <p class="text-muted small">To remove a record, clear its Language field, then save.</p>
                            <div id="languageRows">
                                <?php foreach ($languageRecords as $i => $lang): ?>
                                    <div class="row g-2 mb-2 border-bottom pb-2 language-row">
                                        <input type="hidden" name="languages[<?= $i ?>][id]" value="<?= htmlspecialchars($lang['id'] ?? '') ?>">
                                        <div class="col-md-6">
                                            <input type="text" class="form-control form-control-sm" name="languages[<?= $i ?>][language_name]" placeholder="Language" value="<?= htmlspecialchars($lang['language_name'] ?? '') ?>">
                                        </div>
                                        <div class="col-md-6">
                                            <select class="form-select form-select-sm" name="languages[<?= $i ?>][proficiency_level]">
                                                <?php foreach (['Basic', 'Intermediate', 'Fluent', 'Native'] as $level): ?>
                                                    <option value="<?= $level ?>" <?= ($lang['proficiency_level'] ?? '') === $level ? 'selected' : '' ?>><?= $level ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="col-md-12">
                                            <div class="form-check form-check-inline">
                                                <input class="form-check-input" type="checkbox" name="languages[<?= $i ?>][can_speak]" <?= !empty($lang['can_speak']) ? 'checked' : '' ?>>
                                                <label class="form-check-label">Speak</label>
                                            </div>
                                            <div class="form-check form-check-inline">
                                                <input class="form-check-input" type="checkbox" name="languages[<?= $i ?>][can_read]" <?= !empty($lang['can_read']) ? 'checked' : '' ?>>
                                                <label class="form-check-label">Read</label>
                                            </div>
                                            <div class="form-check form-check-inline">
                                                <input class="form-check-input" type="checkbox" name="languages[<?= $i ?>][can_write]" <?= !empty($lang['can_write']) ? 'checked' : '' ?>>
                                                <label class="form-check-label">Write</label>
                                            </div>
                                            <div class="form-check form-check-inline">
                                                <input class="form-check-input" type="checkbox" name="languages[<?= $i ?>][can_understand]" <?= !empty($lang['can_understand']) ? 'checked' : '' ?>>
                                                <label class="form-check-label">Understand</label>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <button type="button" class="btn btn-sm btn-outline-secondary" id="addLanguageRow">
                                <i class="fas fa-plus"></i> Add another language
                            </button>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
(function () {
    let eduIndex = <?= count($educationRecords) ?>;
    document.getElementById('addEducationRow').addEventListener('click', function () {
        const row = document.createElement('div');
        row.className = 'row g-2 mb-2 border-bottom pb-2 education-row';
        row.innerHTML = `
            <input type="hidden" name="education[${eduIndex}][id]" value="">
            <div class="col-md-6"><input type="text" class="form-control form-control-sm" name="education[${eduIndex}][institution]" placeholder="Institution"></div>
            <div class="col-md-6"><input type="text" class="form-control form-control-sm" name="education[${eduIndex}][qualification]" placeholder="Qualification"></div>
            <div class="col-md-3"><input type="text" class="form-control form-control-sm" name="education[${eduIndex}][course_id]" placeholder="Course ID"></div>
            <div class="col-md-3"><input type="text" class="form-control form-control-sm" name="education[${eduIndex}][year_started]" placeholder="Year started"></div>
            <div class="col-md-3"><input type="text" class="form-control form-control-sm" name="education[${eduIndex}][year_completed]" placeholder="Year completed"></div>
            <div class="col-md-3"><input type="text" class="form-control form-control-sm" name="education[${eduIndex}][grade_obtained]" placeholder="Grade"></div>
        `;
        document.getElementById('educationRows').appendChild(row);
        eduIndex++;
    });

    let langIndex = <?= count($languageRecords) ?>;
    document.getElementById('addLanguageRow').addEventListener('click', function () {
        const row = document.createElement('div');
        row.className = 'row g-2 mb-2 border-bottom pb-2 language-row';
        row.innerHTML = `
            <input type="hidden" name="languages[${langIndex}][id]" value="">
            <div class="col-md-6"><input type="text" class="form-control form-control-sm" name="languages[${langIndex}][language_name]" placeholder="Language"></div>
            <div class="col-md-6">
                <select class="form-select form-select-sm" name="languages[${langIndex}][proficiency_level]">
                    <option value="Basic">Basic</option>
                    <option value="Intermediate">Intermediate</option>
                    <option value="Fluent">Fluent</option>
                    <option value="Native">Native</option>
                </select>
            </div>
            <div class="col-md-12">
                <div class="form-check form-check-inline"><input class="form-check-input" type="checkbox" name="languages[${langIndex}][can_speak]"><label class="form-check-label">Speak</label></div>
                <div class="form-check form-check-inline"><input class="form-check-input" type="checkbox" name="languages[${langIndex}][can_read]"><label class="form-check-label">Read</label></div>
                <div class="form-check form-check-inline"><input class="form-check-input" type="checkbox" name="languages[${langIndex}][can_write]"><label class="form-check-label">Write</label></div>
                <div class="form-check form-check-inline"><input class="form-check-input" type="checkbox" name="languages[${langIndex}][can_understand]"><label class="form-check-label">Understand</label></div>
            </div>
        `;
        document.getElementById('languageRows').appendChild(row);
        langIndex++;
    });
})();
</script>

<?php include dirname(__DIR__) . '/shared/footer.php'; ?>
