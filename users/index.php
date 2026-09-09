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

// Load shared navigation (replaces 9-item $sidebarLinks array)
require_once dirname(__DIR__) . '/shared/user_navigation.php';
$sidebarLinks = $userNavigationItems;

require_once dirname(__DIR__) . '/shared/database_connection.php';
require_once __DIR__ . '/profile_manager.php';

require_once dirname(__DIR__) . '/shared/components/stat_card.php';
require_once dirname(__DIR__) . '/shared/components/info_card.php';
require_once dirname(__DIR__) . '/shared/components/empty_state.php';

/**
 * Turns one staff_edit_log row's JSON `changes` array into short, readable
 * bullet lines, e.g. "Rank changed: Capt -> Maj". Falls back gracefully if
 * the JSON is malformed or a field is unrecognized.
 */
function describeEditLogChanges($changesJson) {
    $changes = json_decode($changesJson, true);
    if (!is_array($changes)) return [];

    $fieldLabels = [
        'fName' => 'First name', 'lName' => 'Last name', 'rankId' => 'Rank',
        'unitId' => 'Unit', 'corps' => 'Corps', 'NRC' => 'NRC', 'DOB' => 'Date of birth',
        'gender' => 'Gender', 'svcStatus' => 'Service status',
    ];

    $lines = [];
    foreach ($changes as $change) {
        if (!isset($change['field'])) continue;
        $label = $fieldLabels[$change['field']] ?? $change['field'];
        $old = $change['old_value'] ?? 'empty';
        $new = $change['new_value'] ?? 'empty';
        $lines[] = "$label changed: " . ($old !== '' ? $old : 'empty') . ' &rarr; ' . ($new !== '' ? $new : 'empty');
    }
    return $lines;
}

try {
    $pdo = getDbConnection();

    // NOTE: `staff` has no `id` column - its primary key is `svcNo` (varchar),
    // and login credentials (username/password/role) live directly on the
    // staff table itself (there is no separate `users` table in this schema).
    // The auth flow therefore stores the person's svcNo in $_SESSION['user_id'].
    $svcNoRaw = $_SESSION['user_id'];

    $profileManager = new UserProfileManager($svcNoRaw);

    $stmt = $pdo->prepare("SELECT s.* FROM staff s WHERE s.svcNo = ?");
    $stmt->execute([$svcNoRaw]);
    $userData = $stmt->fetch(PDO::FETCH_OBJ);

    if ($userData) {
        $userData->age = (isset($userData->DOB) && $userData->DOB) ? floor((time() - strtotime($userData->DOB)) / (365.25 * 24 * 3600)) : 'N/A';
        $userData->serviceYears = (isset($userData->attestDate) && $userData->attestDate) ? floor((time() - strtotime($userData->attestDate)) / (365.25 * 24 * 3600)) : 'N/A';
        $userData->fullName = trim(($userData->fName ?? '') . ' ' . ($userData->mName ?? ''). ' ' . ($userData->lName ?? ''));

        $userData->fname = $userData->fName ?? null;
        $userData->lname = $userData->lName ?? null;
        // Combine prefix with service number for display (space-separated -
        // a bare concatenation like "MrJDoe" reads as a typo when prefix is set)
        $userData->svcNo = trim((!empty($userData->prefix) ? $userData->prefix . ' ' : '') . ($userData->svcNo ?? ''));
        $userData->rankID = $userData->rankId ?? null;
        $userData->unitID = $userData->unitId ?? null;

        // `staff` stores email/phone as officialEmail / emailPvt / telNo / tel2,
        // not `email` / `tel`. Normalize into the names the view uses below,
        // preferring the official values and falling back to personal ones.
        $userData->email = $userData->officialEmail ?: ($userData->emailPvt ?: null);
        $userData->tel = $userData->telNo ?: ($userData->tel2 ?: null);

        // Get rank information if rankId exists.
        // `rank` only has rankId / rankIndex / rankType - there is no separate
        // rank "name" column, so rankId itself is used as the display value.
        if (isset($userData->rankId) && $userData->rankId) {
            try {
                $rankStmt = $pdo->prepare("SELECT rankId, rankIndex FROM `rank` WHERE rankId = ?");
                $rankStmt->execute([$userData->rankId]);
                $rankData = $rankStmt->fetch(PDO::FETCH_OBJ);
                $userData->displayRank = $rankData ? $rankData->rankId : 'N/A';
            } catch (Exception $e) {
                $userData->displayRank = 'N/A';
                error_log("Rank query error: " . $e->getMessage());
            }
        } else {
            $userData->displayRank = 'N/A';
        }

        // Get unit information if unitId exists.
        // `unit` only has unitId / unitLoc - there is no `code` or `name`
        // column, so unitId is used as the code/display name and unitLoc
        // as the location.
        if (isset($userData->unitId) && $userData->unitId) {
            try {
                $unitStmt = $pdo->prepare("SELECT unitId, unitLoc FROM `unit` WHERE unitId = ?");
                $unitStmt->execute([$userData->unitId]);
                $unitData = $unitStmt->fetch(PDO::FETCH_OBJ);
                $userData->unit_name = $unitData ? ($unitData->unitId . ($unitData->unitLoc ? ' - ' . $unitData->unitLoc : '')) : 'No Unit Assigned';
            } catch (Exception $e) {
                $userData->unit_name = 'No Unit Assigned';
                error_log("Unit query error: " . $e->getMessage());
            }
        } else {
            $userData->unit_name = 'No Unit Assigned';
        }
    }

    // ==================== COURSES & TRAINING (real: staff_course + staff_skills) ====================
    // Replaces a query against `training_records`, which does not exist anywhere in the schema.
    $courseCount = 0;
    $skillCount = 0;
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM staff_course WHERE svcNo = ?");
        $stmt->execute([$svcNoRaw]);
        $courseCount = (int)($stmt->fetch(PDO::FETCH_OBJ)->count ?? 0);

        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM staff_skills WHERE svcNo = ?");
        $stmt->execute([$svcNoRaw]);
        $skillCount = (int)($stmt->fetch(PDO::FETCH_OBJ)->count ?? 0);
    } catch (Exception $e) {
        error_log("Courses/skills count query error: " . $e->getMessage());
    }
    $trainingCount = $courseCount + $skillCount;

    // ==================== NEXT OF KIN (real: staff.nok*/altNok* columns) ====================
    // Replaces a query against `staff_family_members`, which does not exist anywhere in the schema.
    $familyCount = 0;
    if ($userData) {
        if (!empty($userData->nok)) $familyCount++;
        if (!empty($userData->altNok)) $familyCount++;
    }

    // ==================== CONTACT METHODS (real: staff contact columns actually filled) ====================
    // Replaces a query against `staff_contact_info`, which does not exist anywhere in the schema.
    $contactCount = 0;
    if ($userData) {
        foreach (['telNo', 'tel2', 'officialEmail', 'emailPvt', 'address'] as $field) {
            if (!empty($userData->$field)) $contactCount++;
        }
    }

    // ==================== EDUCATION / COURSE HISTORY (real: staff_course) ====================
    // Replaces profile_manager's getEducationRecords(), which reads from a `staff_education`
    // table that does not exist anywhere in the schema.
    $educationRecords = [];
    try {
        $eduStmt = $pdo->prepare("
            SELECT sc.*, i.instLoc AS institutionLocation
            FROM staff_course sc
            LEFT JOIN institution i ON sc.instId = i.instId
            WHERE sc.svcNo = ?
            ORDER BY sc.cseEnd DESC, sc.cseStart DESC
        ");
        $eduStmt->execute([$svcNoRaw]);
        $educationRecords = $eduStmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $educationRecords = [];
        error_log("Education records query error: " . $e->getMessage());
    }

    // ==================== LANGUAGE SKILLS ====================
    // No `staff_languages` table (or equivalent) exists anywhere in the schema, so there is
    // no real data source for this section yet. Deliberately left empty rather than reading
    // from a nonexistent table and always showing a fake "0 languages" result.
    $languageRecords = [];
    $languagesTracked = false;

    // ==================== PROFILE COMPLETENESS (real-time, computed from the live row) ====================
    $completenessFields = [
        'fName', 'lName', 'DOB', 'attestDate', 'officialEmail', 'telNo',
        'NRC', 'gender', 'marital', 'unitId', 'rankId', 'corps', 'trade',
        'address', 'profilePhoto', 'nok',
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

    // ==================== RECENT ACTIVITY (real: staff_edit_log audit trail) ====================
    // Replaces a hardcoded mock array with the actual edit history for this person's record.
    $recentActivity = [];
    $lastUpdated = null;
    try {
        $stmt = $pdo->prepare("
            SELECT sel.edited_at, sel.changes, u.username AS edited_by_username
            FROM staff_edit_log sel
            LEFT JOIN staff u ON sel.edited_by = u.svcNo
            WHERE sel.svcNo = ?
            ORDER BY sel.edited_at DESC
            LIMIT 8
        ");
        $stmt->execute([$svcNoRaw]);
        $editLogRows = $stmt->fetchAll(PDO::FETCH_OBJ);

        foreach ($editLogRows as $row) {
            $changeLines = describeEditLogChanges($row->changes);
            $recentActivity[] = [
                'date' => $row->edited_at,
                'type' => 'Profile Update',
                'description' => !empty($changeLines) ? implode('; ', array_map('strip_tags', $changeLines)) : 'Record updated',
                'description_html' => !empty($changeLines) ? implode('<br>', $changeLines) : 'Record updated',
                'status' => 'Completed',
                'icon' => 'user-edit',
                'color' => 'success',
            ];
        }
        if (!empty($editLogRows)) {
            $lastUpdated = $editLogRows[0]->edited_at;
        }
    } catch (Exception $e) {
        $recentActivity = [];
        error_log("Recent activity query error: " . $e->getMessage());
    }

    // Handle case where user profile doesn't exist
    if (!$userData) {
        $userData = (object)[
            'svcNo' => 'Not Available', 'fullName' => 'User Profile', 'displayRank' => 'N/A',
            'unit_name' => 'N/A', 'email' => 'No email on file', 'tel' => 'No phone on file',
            'DOB' => null, 'attestDate' => null, 'age' => 'N/A', 'serviceYears' => 'N/A',
            'svcStatus' => 'Unknown', 'fName' => 'Unknown', 'lName' => 'User',
            'fname' => 'Unknown', 'lname' => 'User', 'NRC' => null, 'gender' => null,
            'marital' => null, 'unitId' => null, 'rankId' => null, 'prefix' => null,
            'rank_name' => null, 'rank_abbr' => null, 'unit_code' => null,
            'corps' => null, 'trade' => null, 'titles' => null,
        ];
        $profileCompleteness = 0;
        $familyCount = 0; $trainingCount = 0; $contactCount = 0;
        $educationRecords = []; $languageRecords = []; $recentActivity = []; $lastUpdated = null;
    }

} catch (Exception $e) {
    error_log("Profile loading error: " . $e->getMessage());
    $userData = (object)[
        'svcNo' => 'Error', 'fullName' => 'Error Loading Profile', 'displayRank' => 'N/A',
        'unit_name' => 'N/A', 'email' => 'Error', 'tel' => 'Error', 'age' => 'N/A',
        'serviceYears' => 'N/A', 'svcStatus' => 'Unknown', 'DOB' => null, 'attestDate' => null,
        'fName' => 'Error', 'lName' => 'Loading', 'fname' => 'Error', 'lname' => 'Loading',
        'NRC' => null, 'gender' => null, 'marital' => null, 'unitId' => null, 'rankId' => null,
        'prefix' => null, 'rank_name' => null, 'rank_abbr' => null, 'unit_code' => null,
        'corps' => null, 'trade' => null, 'titles' => null,
    ];
    $recentActivity = []; $profileCompleteness = 0; $familyCount = 0; $trainingCount = 0;
    $contactCount = 0; $educationRecords = []; $languageRecords = []; $lastUpdated = null;
    $languagesTracked = false;
}

include dirname(__DIR__) . '/shared/header.php';
include dirname(__DIR__) . '/shared/sidebar.php';
?>

<link rel="stylesheet" href="/Armis2/assets/css/users-module-standard.css">

<style>
    .profile-avatar-wrap {
        position: relative;
        display: inline-block;
    }
    .profile-status-dot {
        position: absolute;
        bottom: 6px;
        right: 6px;
        width: 18px;
        height: 18px;
        border-radius: 50%;
        border: 3px solid #fff;
    }
    .completeness-hint {
        font-size: 0.78rem;
    }
    .activity-item {
        border-left: 3px solid #e9ecef;
        padding-left: 0.9rem;
        margin-bottom: 1rem;
        position: relative;
    }
    .activity-item::before {
        content: '';
        position: absolute;
        left: -6px;
        top: 3px;
        width: 9px;
        height: 9px;
        border-radius: 50%;
        background: #0d6efd;
    }
    .activity-item:last-child { margin-bottom: 0; }
    .last-updated-note {
        font-size: 0.8rem;
        color: #6c757d;
    }
    .language-coming-soon {
        border: 1px dashed #dee2e6;
        border-radius: 0.5rem;
        padding: 2rem 1rem;
        text-align: center;
        color: #8a94a6;
    }
</style>

<div class="content-wrapper with-sidebar">
    <div class="container-fluid">
        <div class="main-content">
            <!-- Header Section -->
            <div class="row">
                <div class="col-12">
                    <div class="d-flex justify-content-between align-items-center mb-1 flex-wrap gap-2">
                        <h1 class="page-title mb-0">
                            <i class="fas fa-user me-3"></i>My Profile
                        </h1>
                        <div>
                            <span class="badge bg-<?= (isset($userData->svcStatus) && $userData->svcStatus === 'Active') ? 'success' : 'secondary' ?> me-2">
                                <?= htmlspecialchars($userData->svcStatus ?? 'Unknown') ?>
                            </span>
                            <a href="/Armis2/users/cv_download.php" class="btn btn-primary">
                                <i class="fas fa-download"></i> Download CV
                            </a>
                        </div>
                    </div>
                    <?php if (!empty($lastUpdated)): ?>
                        <p class="last-updated-note mb-4">
                            <i class="fas fa-history"></i> Record last updated <?= date('M j, Y \a\t g:ia', strtotime($lastUpdated)) ?>
                        </p>
                    <?php else: ?>
                        <p class="last-updated-note mb-4">&nbsp;</p>
                    <?php endif; ?>
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
                                <span class="profile-avatar-wrap">
                                    <img src="<?= htmlspecialchars($profileManager->getProfilePhotoURL()) ?>"
                                         alt="Profile Picture"
                                         class="rounded-circle"
                                         style="width: 120px; height: 120px; object-fit: cover; border: 3px solid #dee2e6;"
                                         onerror="this.onerror=null; this.src='/Armis2/shared/default_avatar.php'">
                                    <span class="profile-status-dot bg-<?= (isset($userData->svcStatus) && $userData->svcStatus === 'Active') ? 'success' : 'secondary' ?>"
                                          title="<?= htmlspecialchars($userData->svcStatus ?? 'Unknown') ?>"></span>
                                </span>
                            </div>
                            <h4 class="mb-0">
                                <?= htmlspecialchars($userData->fullName ?? 'Unknown User') ?>
                                <?php if (!empty($userData->titles)): ?>
                                    <span class="badge bg-light text-dark align-middle" style="font-size:0.55em;"><?= htmlspecialchars($userData->titles) ?></span>
                                <?php endif; ?>
                            </h4>
                            <p class="text-muted mb-1"><?= htmlspecialchars($userData->svcNo ?? 'N/A') ?></p>
                            <p class="text-muted">
                                <strong><?= htmlspecialchars($userData->displayRank ?? 'N/A') ?></strong><br>
                                <?= htmlspecialchars($userData->unit_name ?? 'No Unit Assigned') ?>
                                <?php if (!empty($userData->corps)): ?>
                                    <br><small><?= htmlspecialchars($userData->corps) ?></small>
                                <?php endif; ?>
                            </p>

                            <!-- Profile Completeness Progress -->
                            <div class="mt-3">
                                <small class="text-muted">Profile Completeness <span class="text-muted">(live)</span></small>
                                <div class="progress mb-2" style="height: 6px;">
                                    <div class="progress-bar bg-<?= $profileCompleteness >= 80 ? 'success' : ($profileCompleteness >= 60 ? 'warning' : 'danger') ?>"
                                         style="width: <?= $profileCompleteness ?>%"></div>
                                </div>
                                <small class="text-muted"><?= $profileCompleteness ?>% Complete</small>
                                <?php if ($profileCompleteness < 100): ?>
                                    <div class="completeness-hint text-muted mt-1">
                                        <?php
                                        $missing = [];
                                        foreach ($completenessFields as $field) {
                                            if (empty($userData->$field ?? null)) $missing[] = $field;
                                        }
                                        if (!empty($missing)) {
                                            echo 'Missing: ' . htmlspecialchars(implode(', ', array_slice($missing, 0, 4))) . (count($missing) > 4 ? ' +' . (count($missing) - 4) . ' more' : '');
                                        }
                                        ?>
                                    </div>
                                <?php endif; ?>
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
                                    <label class="form-label text-muted small">Corps</label>
                                    <p class="fw-bold"><?= htmlspecialchars($userData->corps ?? 'Not set') ?></p>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label text-muted small">Trade</label>
                                    <p class="fw-bold"><?= htmlspecialchars($userData->trade ?? 'Not set') ?></p>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label text-muted small">Official Email</label>
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
                                <div class="col-md-6 mb-3">
                                    <label class="form-label text-muted small">Marital Status</label>
                                    <p class="fw-bold"><?= htmlspecialchars($userData->marital ?? 'Not set') ?></p>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label text-muted small">Retirement Age</label>
                                    <p class="fw-bold">60 years</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Statistics Cards (all computed live from real tables/columns above) -->
            <?php
            $statsData = [
                ['icon' => 'graduation-cap', 'value' => $trainingCount, 'label' => 'Courses & Training', 'color' => 'primary'],
                ['icon' => 'users', 'value' => $familyCount, 'label' => 'Next of Kin', 'color' => 'success'],
                ['icon' => 'address-book', 'value' => $contactCount, 'label' => 'Contact Methods', 'color' => 'warning'],
                ['icon' => 'chart-pie', 'value' => $profileCompleteness . '%', 'label' => 'Profile Complete', 'color' => 'info', 'showProgress' => true, 'progressValue' => $profileCompleteness]
            ];
            renderStatCardRow($statsData, 4);
            ?>

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

            <!-- Education & Courses (real data from staff_course) -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="card dashboard-card">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="fas fa-graduation-cap"></i> Education &amp; Course History</h5>
                        </div>
                        <div class="card-body">
                            <?php if (empty($educationRecords)): ?>
                                <?php renderEmptyState(
                                    'graduation-cap',
                                    'No education or course records found',
                                    '/Armis2/users/personal.php',
                                    'plus',
                                    'Add Education Records',
                                    'primary'
                                ); ?>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-hover align-middle">
                                        <thead class="table-light">
                                            <tr>
                                                <th><i class="fas fa-university"></i> Institution</th>
                                                <th><i class="fas fa-award"></i> Qualification</th>
                                                <th><i class="fas fa-calendar"></i> Period</th>
                                                <th><i class="fas fa-star"></i> Grade</th>
                                                <th><i class="fas fa-check-circle"></i> Result</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($educationRecords as $edu): ?>
                                            <tr>
                                                <td>
                                                    <strong><?= htmlspecialchars($edu['instId'] ?? 'N/A') ?></strong>
                                                    <?php if (!empty($edu['institutionLocation'])): ?>
                                                        <br><small class="text-muted"><?= htmlspecialchars($edu['institutionLocation']) ?></small>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?= htmlspecialchars($edu['qualification'] ?? 'N/A') ?>
                                                    <?php if (!empty($edu['isHighest'])): ?>
                                                        <span class="badge bg-warning text-dark ms-1"><i class="fas fa-star"></i> Highest</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if (!empty($edu['cseStart'])): ?>
                                                        <?= date('Y', strtotime($edu['cseStart'])) ?>
                                                        <?= !empty($edu['cseEnd']) ? ' - ' . date('Y', strtotime($edu['cseEnd'])) : ' - Present' ?>
                                                    <?php else: ?>
                                                        N/A
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if (!empty($edu['grade'])): ?>
                                                        <span class="badge bg-success"><?= htmlspecialchars($edu['grade']) ?></span>
                                                    <?php else: ?>
                                                        <span class="text-muted">N/A</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?= htmlspecialchars($edu['result'] ?? 'N/A') ?></td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                                <div class="text-end mt-3">
                                    <a href="/Armis2/users/personal.php" class="btn btn-sm btn-outline-primary">
                                        <i class="fas fa-edit"></i> Manage Education Records
                                    </a>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Language Skills -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="card dashboard-card">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="fas fa-language"></i> Language Skills</h5>
                        </div>
                        <div class="card-body">
                            <div class="language-coming-soon">
                                <i class="fas fa-language fa-2x mb-2"></i>
                                <p class="mb-0">Language skills aren't tracked in the system yet.</p>
                                <small>This section will populate automatically once language records are added to the database.</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent Activity (real: staff_edit_log audit trail for this record) -->
            <div class="row">
                <div class="col-12">
                    <div class="card dashboard-card">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="fas fa-clock"></i> Recent Activity</h5>
                        </div>
                        <div class="card-body">
                            <?php if (empty($recentActivity)): ?>
                                <?php renderEmptyState(
                                    'history',
                                    'No recent activity - Updates to your record will appear here',
                                    null,
                                    null,
                                    'Browse Features',
                                    'secondary',
                                    'md'
                                ); ?>
                            <?php else: ?>
                                <?php foreach ($recentActivity as $activity): ?>
                                    <div class="activity-item">
                                        <div class="d-flex justify-content-between align-items-start">
                                            <div>
                                                <i class="fas fa-<?= htmlspecialchars($activity['icon']) ?> text-<?= htmlspecialchars($activity['color']) ?> me-2"></i>
                                                <strong><?= htmlspecialchars($activity['type']) ?></strong>
                                                <div class="small text-muted mt-1"><?= $activity['description_html'] ?? htmlspecialchars($activity['description']) ?></div>
                                            </div>
                                            <div class="text-end">
                                                <span class="badge bg-<?= htmlspecialchars($activity['color']) ?>"><?= htmlspecialchars($activity['status']) ?></span>
                                                <div class="small text-muted mt-1"><?= date('M j, Y g:ia', strtotime($activity['date'])) ?></div>
                                            </div>
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