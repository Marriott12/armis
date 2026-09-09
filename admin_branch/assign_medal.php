<?php
// Define module constants
define('ARMIS_ADMIN_BRANCH', true);
define('ARMIS_DEVELOPMENT', true); // Set to false in production

// Include admin branch authentication and database
require_once __DIR__ . '/includes/auth.php';

// Include RBAC system
require_once dirname(__DIR__) . '/shared/rbac.php';

// Include permissions
require_once dirname(__DIR__) . '/shared/permissions.php';

// Require authentication and admin privileges
requireAuth();

// Check if user has access to admin_branch module
requireModuleAccess('admin_branch');

$pageTitle = "Award Medal";
$moduleName = "Admin Branch";
$moduleIcon = "users-cog";
$currentPage = "medals";

$sidebarLinks = []; // set by shared nav include below
require_once __DIR__ . '/includes/sidebar_nav.php';

// CSRF Token
if (!isset($_SESSION)) { session_start(); }
if (!function_exists('Token')) {
    class Token {
        public static function generate() {
            if (!isset($_SESSION['csrf_token'])) {
                $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            }
            return $_SESSION['csrf_token'];
        }
        public static function check($token) {
            return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
        }
    }
}
$csrfToken = Token::generate();

// honorId is a fixed-width varchar(2) primary key in the `honors` table.
function isValidHonorId($id) {
    return is_string($id) && preg_match('/^[A-Za-z0-9]{1,2}$/', $id);
}

$errors = [];
$success = false;
$warnings = []; // Non-critical issues like duplicate assignments
$pdo = getDbConnection();
$medals = [];
try {
    // honorId is already unique (it's the primary key), so no de-duplication is needed.
    $stmt = $pdo->query("SELECT honorId, honorDesc, honorAuth FROM honors ORDER BY honorDesc ASC");
    $medals = $stmt->fetchAll(PDO::FETCH_OBJ);
} catch (Exception $e) {
    $errors[] = "Error fetching honors: " . htmlspecialchars($e->getMessage());
}

$BULK_CONFIRMATION_THRESHOLD = 5;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Token::check($_POST['csrf'] ?? '')) {
        $errors[] = "Invalid CSRF token.";
    } else {
        $honorId = trim($_POST['medal_id'] ?? '');
        $awardDate = trim($_POST['award_date'] ?? '');
        $selectedStaff = $_POST['selected_staff'] ?? [];
        $remark = trim($_POST['remark'] ?? '');
        $gazetteReference = trim($_POST['gazette_reference'] ?? '');
        $barNumberRaw = trim($_POST['bar_number'] ?? '');
        $barNumber = ($barNumberRaw !== '' && ctype_digit($barNumberRaw)) ? (int)$barNumberRaw : null;

        // Permission check - allow admin_branch access
        if (!hasPermission(PERM_ASSIGN_MEDALS)) {
            $errors[] = "You do not have permission to assign medals.";
        }

        if (!isValidHonorId($honorId)) $errors[] = "Invalid medal selected.";
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $awardDate)) $errors[] = "Invalid award date.";
        if (empty($selectedStaff) || !is_array($selectedStaff)) $errors[] = "Please select at least one staff member.";
        if (count($selectedStaff) !== count(array_unique($selectedStaff))) {
            $errors[] = "Duplicate staff selected.";
        }

        // Look up the honor's display name up front - needed for the INSERT
        // (award_name) and for the success message.
        $honorDesc = null;
        if (isValidHonorId($honorId)) {
            $hStmt = $pdo->prepare("SELECT honorDesc FROM honors WHERE honorId = ?");
            $hStmt->execute([$honorId]);
            $honorDesc = $hStmt->fetchColumn();
            if ($honorDesc === false) {
                $errors[] = "Selected medal no longer exists.";
                $honorDesc = null;
            }
        }

        // Track duplicates (already-awarded staff) for later reporting
        $duplicateStaff = [];
        $staffInfoList = [];

        foreach ($selectedStaff as $svcNo) {
            // staff.svcNo is the actual primary key - there is no separate
            // integer `id` column on this table.
            $svcNo = trim($svcNo);
            if ($svcNo === '') continue;

            $stmt = $pdo->prepare("SELECT svcNo, branch_id, CONCAT(fName, ' ', lName) as full_name FROM staff WHERE svcNo = ?");
            $stmt->execute([$svcNo]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            // Fallback: allow for leading zeros being stripped client-side
            // (e.g. "007414" submitted as "7414").
            if (!$row) {
                $numericValue = ltrim($svcNo, '0');
                if ($numericValue !== $svcNo && $numericValue !== '') {
                    $stmt = $pdo->prepare("SELECT svcNo, branch_id, CONCAT(fName, ' ', lName) as full_name FROM staff WHERE svcNo = ?");
                    $stmt->execute([$numericValue]);
                    $row = $stmt->fetch(PDO::FETCH_ASSOC);
                }
            }

            if (!$row || empty($row['svcNo'])) {
                error_log("Medal Assignment: Staff lookup failed for identifier: {$svcNo}");
                $errors[] = "Staff member with identifier '".htmlspecialchars($svcNo)."' not found. Please verify the staff exists in the system.";
                continue;
            }

            if (function_exists('canAlterRecord') && !canAlterRecord($row['branch_id'] ?? null)) {
                $errors[] = "Staff member '".htmlspecialchars($row['full_name'])."' is outside your branch - skipped.";
                continue;
            }

            $resolvedSvcNo = $row['svcNo'];
            $full_name = $row['full_name'];

            // Check for duplicate honor assignment
            if (isValidHonorId($honorId)) {
                $stmt2 = $pdo->prepare("SELECT COUNT(*) FROM staff_awards WHERE svcNo = ? AND honorId = ?");
                $stmt2->execute([$resolvedSvcNo, $honorId]);
                $alreadyAwarded = $stmt2->fetchColumn();
                if ($alreadyAwarded > 0) {
                    $duplicateStaff[] = "{$full_name} ({$resolvedSvcNo})";
                    continue;
                }
            }

            $staffInfoList[] = [
                'svcNo' => $resolvedSvcNo,
                'full_name' => $full_name
            ];
        }

        // Process assignments if there are valid staff members and no errors
        if (empty($errors) && !empty($staffInfoList) && $honorDesc !== null) {
            try {
                $pdo->beginTransaction();
                $stmt = $pdo->prepare("INSERT INTO staff_awards
                    (svcNo, honorId, award_type, award_name, award_date, citation, gazette_reference, bar_number, createdBy, createdAt)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $createdBy = $_SESSION['username'] ?? 'admin';
                $now = date('Y-m-d H:i:s');

                $successCount = 0;

                foreach ($staffInfoList as $info) {
                    try {
                        $stmt->execute([
                            $info['svcNo'],
                            $honorId,
                            'Honor',
                            $honorDesc,
                            $awardDate,
                            $remark,
                            $gazetteReference,
                            $barNumber,
                            $createdBy,
                            $now
                        ]);
                        $successCount++;
                    } catch (PDOException $e) {
                        // Race-condition safety net: the uniq_staff_honor
                        // constraint on staff_awards(svcNo, honorId) catches
                        // simultaneous submissions the pre-check above missed.
                        if ($e->getCode() == 23000) {
                            $duplicateStaff[] = $info['full_name'] . " (" . $info['svcNo'] . ")";
                        } else {
                            throw $e;
                        }
                    }
                }

                $pdo->commit();

                if ($successCount > 0) {
                    $success = "Successfully assigned <strong>".htmlspecialchars($honorDesc)."</strong> to {$successCount} staff member" . ($successCount > 1 ? 's' : '') . ".";

                    // Notify each honored staff member directly, plus a
                    // single oversight notification to admins.
                    require_once dirname(__DIR__) . '/shared/notifications_helper.php';
                    foreach ($staffInfoList as $info) {
                        createNotification(
                            $info['svcNo'],
                            'admin_branch',
                            'medal_assigned',
                            'Honor/Award received',
                            "You were awarded: $honorDesc" . ($remark ? " ($remark)" : ''),
                            '/Armis2/admin_branch/view_staff.php?svcNo=' . urlencode($info['svcNo'])
                        );
                    }
                    notifyRoles(
                        ['admin', 'superadmin'],
                        'admin_branch',
                        'medal_assigned',
                        'Honor/Award assigned',
                        "$honorDesc was assigned to $successCount staff member" . ($successCount > 1 ? 's' : '') . '.',
                        null,
                        $_SESSION['user_id'] ?? null
                    );
                }

                $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
                $csrfToken = $_SESSION['csrf_token'];
                $_POST = [];
            } catch (Exception $e) {
                $pdo->rollBack();
                $errors[] = "Error assigning medal: " . htmlspecialchars($e->getMessage());
            }
        }

        // Report all duplicates (found in pre-check or during database insert)
        if (!empty($duplicateStaff)) {
            $duplicateList = implode(', ', array_map('htmlspecialchars', $duplicateStaff));
            $warnings[] = "The following staff members have already been awarded this medal: <strong>{$duplicateList}</strong>";
        }
    }
}
?>

<?php include dirname(__DIR__) . '/shared/header.php'; ?>
<?php include dirname(__DIR__) . '/shared/sidebar.php'; ?>
<div class="content-wrapper with-sidebar">
    <div class="container-fluid p-4">
        <div class="mb-3 d-flex justify-content-between align-items-center">
            <a href="medals.php" class="btn btn-outline-secondary"><i class="fa fa-arrow-left"></i> Back to Medals List</a>
            <a href="create_medal.php" class="btn btn-success"><i class="fa fa-plus"></i> Add New Medal</a>
        </div>
        <div class="card shadow-sm">
        <div class="card-header bg-info text-white">
            <h4 class="mb-0"><i class="fa fa-medal"></i> Assign Medal to Staff</h4>
        </div>
        <div class="card-body">
            <!-- Enhanced Alert Messages -->
            <?php if ($success): ?>
                <div class="alert alert-success alert-dismissible fade show shadow-sm border-0" role="alert">
                    <div class="d-flex align-items-center">
                        <div class="flex-shrink-0">
                            <i class="fa fa-check-circle fa-2x me-3 text-success"></i>
                        </div>
                        <div class="flex-grow-1">
                            <h5 class="alert-heading mb-1"><i class="fa fa-trophy"></i> Success!</h5>
                            <p class="mb-0"><?=$success?></p>
                        </div>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($warnings): ?>
                <div class="alert alert-warning alert-dismissible fade show shadow-sm border-0" role="alert">
                    <div class="d-flex align-items-start">
                        <div class="flex-shrink-0">
                            <i class="fa fa-info-circle fa-2x me-3 text-warning"></i>
                        </div>
                        <div class="flex-grow-1">
                            <h5 class="alert-heading mb-2"><i class="fa fa-exclamation-triangle"></i> Note:</h5>
                            <ul class="mb-0">
                                <?php foreach ($warnings as $warning): ?>
                                    <li><?=$warning?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($errors): ?>
                <div class="alert alert-danger alert-dismissible fade show shadow-sm border-0" role="alert">
                    <div class="d-flex align-items-start">
                        <div class="flex-shrink-0">
                            <i class="fa fa-exclamation-triangle fa-2x me-3 text-danger"></i>
                        </div>
                        <div class="flex-grow-1">
                            <h5 class="alert-heading mb-2"><i class="fa fa-exclamation-circle"></i> Please fix the following issues:</h5>
                            <ul class="mb-0">
                                <?php foreach ($errors as $err): ?>
                                    <li><?=$err?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Dynamic Progress Bar -->
            <div class="mb-4">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <h6 class="mb-0"><i class="fa fa-tasks"></i> Assignment Progress</h6>
                    <span class="badge bg-primary" id="progressPercentage">0%</span>
                </div>
                <div class="progress" style="height: 10px;">
                    <div class="progress-bar progress-bar-striped progress-bar-animated bg-success"
                         id="progressBar"
                         role="progressbar"
                         style="width: 0%"
                         aria-valuenow="0"
                         aria-valuemin="0"
                         aria-valuemax="100">
                    </div>
                </div>

                <!-- Stepper -->
                <ul class="stepper mt-3 mb-0">
                    <li class="step" id="step1"><i class="fa fa-medal"></i> Select Medal</li>
                    <li class="step" id="step2"><i class="fa fa-users"></i> Select Staff</li>
                    <li class="step" id="step3"><i class="fa fa-edit"></i> Add Details</li>
                    <li class="step" id="step4"><i class="fa fa-check"></i> Confirm</li>
                </ul>
            </div>

            <form method="post" action="" autocomplete="off" aria-label="Assign Medal Form" id="assignMedalForm">
                <input type="hidden" name="csrf" value="<?=htmlspecialchars($csrfToken)?>">

                <!-- Two-Column Layout -->
                <div class="row">
                    <!-- Left Column - Primary Fields -->
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="medal_id" class="form-label" aria-label="Medal">
                                <i class="fa fa-medal text-warning"></i> Medal <span class="text-danger">*</span>
                            </label>
                            <select name="medal_id" id="medal_id" class="form-select" required aria-required="true">
                                <option value="">Select Medal...</option>
                                <?php foreach ($medals as $medal): ?>
                                    <option value="<?=htmlspecialchars($medal->honorId)?>"
                                            data-description="<?=htmlspecialchars($medal->honorAuth ?? 'No authority on file')?>"
                                            <?=isset($_POST['medal_id']) && $_POST['medal_id']==$medal->honorId?'selected':''?>>
                                        <?=htmlspecialchars($medal->honorDesc)?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Medal Preview Card -->
                        <div id="medalPreviewCard" class="card bg-light p-3 mb-3" style="display: none;">
                            <div class="row align-items-center">
                                <div class="col-12">
                                    <h6 id="previewMedalName" class="mb-1 text-primary"></h6>
                                    <p class="text-muted mb-0 small"><i class="fa fa-landmark"></i> Awarding Authority: <span id="previewMedalDescription"></span></p>
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="award_date" class="form-label" aria-label="Award Date">
                                <i class="fa fa-calendar text-primary"></i> Award Date <span class="text-danger">*</span>
                            </label>
                            <input type="date" class="form-control" id="award_date" name="award_date" required aria-required="true" min="1900-01-01" max="<?=date('Y-m-d')?>" value="<?=htmlspecialchars($_POST['award_date'] ?? date('Y-m-d'))?>">
                        </div>
                    </div>

                    <!-- Right Column - Optional Fields -->
                    <div class="col-md-6">
                        <div class="card bg-light h-100 p-3">
                            <h6 class="text-muted mb-3">
                                <i class="fa fa-info-circle"></i> Additional Information <small class="text-muted">(Optional)</small>
                            </h6>

                            <div class="mb-3">
                                <label for="remark" class="form-label" aria-label="Citation or Remarks">
                                    <i class="fa fa-quote-left text-info"></i> Citation / Remarks
                                </label>
                                <textarea class="form-control" id="remark" name="remark" rows="3" placeholder="Enter citation or remarks..."><?=htmlspecialchars($_POST['remark'] ?? '')?></textarea>
                            </div>

                            <div class="mb-3">
                                <label for="gazette_reference" class="form-label">
                                    <i class="fa fa-file-alt text-secondary"></i> Gazette Reference
                                </label>
                                <input type="text" class="form-control" id="gazette_reference" name="gazette_reference" value="<?=htmlspecialchars($_POST['gazette_reference'] ?? '')?>" placeholder="e.g., GRZ No. 123/2025">
                            </div>

                            <div class="mb-3">
                                <label for="bar_number" class="form-label">
                                    <i class="fa fa-bars text-secondary"></i> Bar Number
                                </label>
                                <input type="number" class="form-control" id="bar_number" name="bar_number" value="<?=htmlspecialchars($_POST['bar_number'] ?? '')?>" min="0" placeholder="0">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Staff Selection Section -->
                <div class="mb-3 mt-4">
                    <label for="selected_staff" class="form-label" aria-label="Select Staff Members">
                        <i class="fa fa-users text-success"></i> Select Staff Members <span class="text-danger">*</span>
                    </label>

                    <!-- Quick Filter Buttons -->
                    <div class="btn-toolbar mb-3" role="toolbar" aria-label="Staff filter toolbar">
                        <div class="btn-group btn-group-sm me-2" role="group" aria-label="Staff category filter">
                            <button type="button" class="btn btn-outline-primary active filter-category" data-filter="all">
                                <i class="fa fa-users"></i> All Staff
                            </button>
                            <button type="button" class="btn btn-outline-success filter-category" data-filter="officers">
                                <i class="fa fa-star"></i> Officers Only
                            </button>
                            <button type="button" class="btn btn-outline-info filter-category" data-filter="ncos">
                                <i class="fa fa-user"></i> NCOs Only
                            </button>
                            <button type="button" class="btn btn-outline-warning filter-category" data-filter="ce">
                                <i class="fa fa-user-tie"></i> Civilian Employees Only
                            </button>
                        </div>

                        <div class="btn-group btn-group-sm me-2" role="group" aria-label="Staff status filter">
                            <button type="button" class="btn btn-outline-secondary filter-status active" data-filter="all">
                                <i class="fa fa-list"></i> All
                            </button>
                            <button type="button" class="btn btn-outline-success filter-status" data-filter="active">
                                <i class="fa fa-check-circle"></i> Active
                            </button>
                            <button type="button" class="btn btn-outline-warning filter-status" data-filter="retired">
                                <i class="fa fa-user-clock"></i> Retired
                            </button>
                        </div>

                        <div class="input-group input-group-sm flex-grow-1" style="max-width: 300px;">
                            <span class="input-group-text"><i class="fa fa-search"></i></span>
                            <input type="text" class="form-control" id="quickSearch" placeholder="Quick search by name or service number...">
                        </div>
                    </div>

                    <!-- Staff Selection Controls -->
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h6 class="mb-0">
                            <i class="fa fa-list"></i>
                            Staff List <small class="text-muted">(Ordered by Seniority)</small>
                        </h6>
                        <div>
                            <button type="button" class="btn btn-sm btn-success" id="selectAllBtn">
                                <i class="fa fa-check-double"></i> Select All
                            </button>
                            <button type="button" class="btn btn-sm btn-warning" id="deselectAllBtn">
                                <i class="fa fa-times"></i> Deselect All
                            </button>
                        </div>
                    </div>

                    <div class="alert alert-info d-flex justify-content-between align-items-center">
                        <div>
                            <i class="fa fa-info-circle"></i>
                            <strong>Total Staff:</strong> <span id="totalStaffCount">0</span> |
                            <strong>Selected:</strong> <span id="selectionCount">0</span> staff member(s)
                        </div>
                    </div>

                    <!-- Staff Selection Table -->
                    <div class="table-responsive" id="staffTableContainer">
                        <table class="table table-hover table-sm" id="staffSelectionTable" style="width:100%">
                            <thead class="table-light">
                                <tr>
                                    <th style="width: 40px;">
                                        <input type="checkbox" id="masterCheckbox" class="form-check-input" title="Select/Deselect All">
                                    </th>
                                    <th>Service No.</th>
                                    <th>Rank</th>
                                    <th>Name</th>
                                    <th>Unit</th>
                                    <th>Corps</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <!-- DataTables will populate this -->
                            </tbody>
                        </table>
                    </div>

                    <!-- Selected Staff Profile Cards (Matching appointments.php style) -->
                    <div id="selectedStaffCards" class="mt-4" style="display: none;">
                        <h6 class="border-bottom pb-2 mb-3">
                            <i class="fas fa-check-circle text-success"></i> Selected Staff Members
                            <span class="badge bg-primary" id="selectedCardCount">0</span>
                        </h6>
                        <div id="selectedStaffList" class="row g-3">
                            <!-- Profile cards will be populated here -->
                        </div>
                    </div>

                    <!-- Hidden container to store selected staff for form submission -->
                    <div id="selectedStaffInputs"></div>

                    <!-- Legacy Select2 hidden input for compatibility -->
                    <select name="selected_staff[]" id="selected_staff" multiple style="display: none;">
                        <!-- Will be populated by JavaScript -->
                    </select>
                </div>
                <div class="text-end">
                    <!-- Split Button with Keyboard Shortcut -->
                    <div class="btn-group" role="group" aria-label="Medal assignment actions">
                        <button type="button" id="showConfirmModal" class="btn btn-primary px-4 py-2" aria-label="Review and confirm medal assignment" disabled>
                            <i class="fa fa-medal"></i> Assign Medal <small class="opacity-75">(Ctrl+Enter)</small>
                        </button>
                        <button type="button" class="btn btn-primary dropdown-toggle dropdown-toggle-split px-3 py-2" data-bs-toggle="dropdown" aria-expanded="false" disabled id="splitDropdown">
                            <span class="visually-hidden">Toggle Dropdown</span>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end shadow">
                            <li><a class="dropdown-item" href="#" id="assignAndNew"><i class="fa fa-plus-circle me-2"></i>Assign &amp; Create New</a></li>
                            <li><a class="dropdown-item" href="#" id="assignAndView"><i class="fa fa-user me-2"></i>Assign &amp; View Profile</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="#" id="resetForm"><i class="fa fa-redo me-2"></i>Reset Form</a></li>
                        </ul>
                    </div>
                </div>

                <!-- Enhanced Confirmation Modal -->
                <div class="modal fade" id="confirmModal" tabindex="-1" aria-labelledby="confirmModalLabel" aria-hidden="true">
                    <div class="modal-dialog modal-lg">
                        <div class="modal-content">
                            <div class="modal-header bg-primary text-white">
                                <h5 class="modal-title" id="confirmModalLabel">
                                    <i class="fa fa-check-circle me-2"></i>Confirm Medal Assignment
                                </h5>
                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body">
                                <!-- Assignment Statistics -->
                                <div class="card mb-3 border-0 bg-light">
                                    <div class="card-body">
                                        <div class="row text-center">
                                            <div class="col-md-4">
                                                <div class="h2 text-primary mb-0" id="modalSelectedCount">0</div>
                                                <small class="text-muted">Staff Members</small>
                                            </div>
                                            <div class="col-md-4">
                                                <div class="h2 text-success mb-0">1</div>
                                                <small class="text-muted">Medal Selected</small>
                                            </div>
                                            <div class="col-md-4">
                                                <div class="h2 text-info mb-0" id="totalAssignments">0</div>
                                                <small class="text-muted">Total Assignments</small>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Assignment Details -->
                                <div id="confirmSummary"></div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                                    <i class="fa fa-times me-2"></i>Cancel
                                </button>
                                <button type="button" id="confirmSubmitBtn" class="btn btn-primary px-4">
                                    <i class="fa fa-check me-2"></i>Confirm Assignment</button>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal fade" id="bulkConfirmModal" tabindex="-1">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title">Bulk Medal Assignment Confirmation</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body">
                                <p>You are about to assign medals to <span id="bulkCount"></span> staff. Are you sure?</p>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                <button type="button" id="bulkConfirmSubmitBtn" class="btn btn-primary">Yes, Confirm</button>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Recent Medal Assignments Table -->
<div class="container-fluid p-4">
    <div class="card shadow-sm mt-4">
        <div class="card-header bg-success text-white d-flex justify-content-between align-items-center">
            <h5 class="mb-0"><i class="fa fa-history"></i> Recent Medal Assignments</h5>
            <button class="btn btn-sm btn-light" type="button" data-bs-toggle="collapse" data-bs-target="#recentMedalsSection" aria-expanded="true" aria-controls="recentMedalsSection">
                <i class="fa fa-chevron-up" id="collapseIcon"></i>
            </button>
        </div>
        <div class="collapse show" id="recentMedalsSection">
        <div class="card-body">
            <?php
            // Fetch recent medal assignments
            try {
                $recentMedalsStmt = $pdo->prepare("
                    SELECT
                        sa.id,
                        sa.award_date,
                        sa.citation,
                        sa.createdBy,
                        sa.createdAt,
                        s.svcNo,
                        s.fName,
                        s.lName,
                        r.rankId AS rank_abbr,
                        COALESCE(u.unitLoc, u.mainUnit, 'N/A') AS unit_name,
                        COALESCE(h.honorDesc, sa.award_name) AS medal_name,
                        h.honorAuth AS medal_description
                    FROM staff_awards sa
                    LEFT JOIN staff s ON sa.svcNo = s.svcNo
                    LEFT JOIN `rank` r ON s.rankId = r.rankId
                    LEFT JOIN unit u ON s.unitId = u.unitId
                    LEFT JOIN honors h ON sa.honorId = h.honorId
                    ORDER BY sa.createdAt DESC, sa.award_date DESC
                    LIMIT 20
                ");
                $recentMedalsStmt->execute();
                $recentMedals = $recentMedalsStmt->fetchAll(PDO::FETCH_OBJ);
            } catch (PDOException $e) {
                $recentMedals = [];
                error_log("Error fetching recent medals: " . $e->getMessage());
            }
            ?>

            <?php if (!empty($recentMedals)): ?>
                <div class="table-responsive">
                    <table class="table table-striped table-hover" id="recentMedalsTable">
                        <thead class="table-dark">
                            <tr>
                                <th><i class="fa fa-hashtag"></i> #</th>
                                <th><i class="fa fa-calendar"></i> Award Date</th>
                                <th><i class="fa fa-user"></i> Staff Member</th>
                                <th><i class="fa fa-medal"></i> Medal</th>
                                <th><i class="fa fa-quote-left"></i> Citation</th>
                                <th><i class="fa fa-user-shield"></i> Awarded By</th>
                                <th><i class="fa fa-clock"></i> Assigned On</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentMedals as $index => $medal): ?>
                                <tr>
                                    <td><?= $index + 1 ?></td>
                                    <td>
                                        <?php if (!empty($medal->award_date)): ?>
                                            <span class="badge bg-primary">
                                                <?= date('d M Y', strtotime($medal->award_date)) ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="text-muted">N/A</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div>
                                                <strong>
                                                    <?= htmlspecialchars($medal->svcNo ?? 'N/A') ?>
                                                </strong>
                                                <br>
                                                <small class="text-muted">
                                                    <?php if (!empty($medal->rank_abbr)): ?>
                                                        <?= htmlspecialchars($medal->rank_abbr) ?>
                                                    <?php endif; ?>
                                                    <?= htmlspecialchars(($medal->fName ?? '') . ' ' . ($medal->lName ?? '')) ?>
                                                </small>
                                                <?php if (!empty($medal->unit_name)): ?>
                                                    <br>
                                                    <small class="text-info">
                                                        <i class="fa fa-building"></i> <?= htmlspecialchars($medal->unit_name) ?>
                                                    </small>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge bg-warning text-dark">
                                            <i class="fa fa-medal"></i> <?= htmlspecialchars($medal->medal_name ?? 'Unknown Medal') ?>
                                        </span>
                                        <?php if (!empty($medal->medal_description)): ?>
                                            <br>
                                            <small class="text-muted">
                                                <?= htmlspecialchars(substr($medal->medal_description, 0, 50)) ?>
                                                <?= strlen($medal->medal_description ?? '') > 50 ? '...' : '' ?>
                                            </small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if (!empty($medal->citation)): ?>
                                            <span class="text-muted">
                                                <?= htmlspecialchars(substr($medal->citation, 0, 60)) ?>
                                                <?= strlen($medal->citation) > 60 ? '...' : '' ?>
                                            </span>
                                        <?php else: ?>
                                            <em class="text-muted">No citation</em>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if (!empty($medal->createdBy)): ?>
                                            <span class="badge bg-secondary">
                                                <?= htmlspecialchars($medal->createdBy) ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="text-muted">N/A</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if (!empty($medal->createdAt)): ?>
                                            <small class="text-muted">
                                                <?= date('d M Y H:i', strtotime($medal->createdAt)) ?>
                                            </small>
                                        <?php else: ?>
                                            <span class="text-muted">N/A</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Table Footer with Summary -->
                <div class="row mt-3">
                    <div class="col-md-6">
                        <small class="text-muted">
                            <i class="fa fa-info-circle"></i>
                            Showing the latest <?= count($recentMedals) ?> medal assignments
                        </small>
                    </div>
                    <div class="col-md-6 text-end">
                        <a href="view_staff.php" class="btn btn-outline-primary btn-sm">
                            <i class="fa fa-eye"></i> View All Staff Records
                        </a>
                    </div>
                </div>

            <?php else: ?>
                <div class="alert alert-info">
                    <i class="fa fa-info-circle"></i>
                    <strong>No Medal Assignments Found</strong>
                    <p class="mb-0">No recent medal assignments to display. Use the form above to assign medals to staff members.</p>
                </div>
            <?php endif; ?>
        </div>
        </div><!-- End collapse -->
    </div>
</div>

<!-- DataTables CSS -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/datatables.net-bs5@1.13.6/css/dataTables.bootstrap5.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/datatables.net-responsive-bs5@2.5.0/css/responsive.bootstrap5.min.css">

<style>
.stepper { list-style: none; padding: 0; display: flex; gap: 10px; }
.step { padding: 8px 16px; border-radius: 12px; background: #e9ecef; color: #6c757d; font-size: 0.9rem; transition: all 0.3s ease; }
.step.active { background: #28a745; color: #fff; font-weight: bold; box-shadow: 0 2px 8px rgba(40, 167, 69, 0.3); }
.step.completed { background: #17a2b8; color: #fff; }
.step i { margin-right: 5px; }

/* DataTables Custom Styling */
#staffSelectionTable {
    font-size: 0.9rem;
}

#staffSelectionTable thead th {
    background-color: #f8f9fa;
    font-weight: 600;
    border-bottom: 2px solid #dee2e6;
    padding: 12px 8px;
}

#staffSelectionTable tbody tr {
    transition: background-color 0.2s ease;
}

#staffSelectionTable tbody tr:hover {
    background-color: #f1f3f5;
    cursor: pointer;
}

/* Selected row styling */
#staffSelectionTable tbody tr.selected {
    background-color: #cfe2ff !important;
    border-left: 4px solid #0d6efd !important;
}

#staffSelectionTable tbody tr.selected:hover {
    background-color: #b6d4fe !important;
}

.staff-checkbox {
    cursor: pointer;
    width: 18px;
    height: 18px;
}

#masterCheckbox {
    cursor: pointer;
    width: 18px;
    height: 18px;
}

/* Loading state */
#staffTableContainer.loading {
    position: relative;
    opacity: 0.6;
    pointer-events: none;
}

#staffTableContainer.loading::after {
    content: 'Loading staff data...';
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    background: rgba(255, 255, 255, 0.95);
    padding: 20px 40px;
    border-radius: 8px;
    box-shadow: 0 4px 6px rgba(0,0,0,0.1);
    font-weight: 600;
    color: #0d6efd;
}

/* Mobile responsive */
@media (max-width: 768px) {
    #staffSelectionTable {
        font-size: 0.8rem;
    }

    #staffSelectionTable thead th,
    #staffSelectionTable tbody td {
        padding: 0.5rem 0.25rem;
    }

    /* Hide less important columns on mobile */
    #staffSelectionTable th:nth-child(6),
    #staffSelectionTable td:nth-child(6),
    #staffSelectionTable th:nth-child(7),
    #staffSelectionTable td:nth-child(7),
    #staffSelectionTable th:nth-child(8),
    #staffSelectionTable td:nth-child(8) {
        display: none;
    }

    .d-flex.justify-content-between {
        flex-direction: column;
        gap: 10px;
    }
}

/* Staff Profile Cards (Matching appointments.php) */
.staff-profile-card {
    transition: all 0.2s ease;
    border: 1px solid #dee2e6;
}

.staff-profile-card:hover {
    box-shadow: 0 0.25rem 0.75rem rgba(0, 0, 0, 0.1);
    transform: translateY(-2px);
}

.staff-profile-card .card-body {
    padding: 1rem;
}

.staff-avatar-circle {
    width: 48px;
    height: 48px;
    background-color: #0d6efd;
    color: white;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.staff-profile-card .staff-name {
    font-weight: 600;
    font-size: 1rem;
    color: #212529;
    margin-bottom: 0.25rem;
}

.staff-profile-card .staff-info {
    font-size: 0.875rem;
    color: #6c757d;
    margin-bottom: 0.25rem;
}

.staff-profile-card .staff-info i {
    width: 16px;
    text-align: center;
    margin-right: 4px;
}

.staff-profile-card .btn-remove {
    transition: all 0.2s ease;
}

.staff-profile-card .btn-remove:hover {
    transform: scale(1.1);
}

.select2-container--bootstrap-5 .select2-selection--multiple .select2-selection__choice {
    background-color: #0d6efd;
    border-color: #0d6efd;
    color: #fff;
    font-size: 1em;
}
.select2-container--bootstrap-5 .select2-selection--multiple .select2-selection__choice__remove {
    color: #fff;
    margin-right: 6px;
    font-weight: bold;
}

/* Mobile Responsive Enhancements */
@media (max-width: 576px) {
    /* Stack two-column layout on mobile */
    .row.g-4 > .col-md-8,
    .row.g-4 > .col-md-4 {
        width: 100%;
        margin-bottom: 1rem;
    }

    /* Reduce padding on mobile */
    .container-fluid {
        padding-left: 0.5rem !important;
        padding-right: 0.5rem !important;
    }

    .card {
        margin-bottom: 1rem;
    }

    .card-body {
        padding: 1rem;
    }

    /* Adjust progress bar on mobile */
    .progress {
        height: 1.5rem;
    }

    .progress-bar {
        font-size: 0.75rem;
    }

    /* Stepper adjustments */
    .stepper {
        flex-wrap: wrap;
        gap: 5px;
    }

    .step {
        padding: 3px 8px;
        font-size: 0.8rem;
    }

    /* Filter buttons stack on mobile */
    .btn-group {
        display: flex;
        flex-wrap: wrap;
        gap: 0.25rem;
    }

    .btn-group .btn {
        font-size: 0.8rem;
        padding: 0.375rem 0.5rem;
    }

    /* Split button adjustments */
    .dropdown-menu {
        min-width: 200px;
    }

    /* Modal adjustments */
    .modal-dialog {
        margin: 0.5rem;
    }

    /* Staff profile cards - full width on mobile */
    .staff-profile-card .col-md-6,
    .staff-profile-card .col-lg-4 {
        width: 100%;
    }

    /* Quick search input */
    #quickSearch {
        width: 100%;
        margin-top: 0.5rem;
    }

    /* Alert messages */
    .alert {
        font-size: 0.9rem;
        padding: 0.75rem;
    }

    .alert .btn-close {
        padding: 0.5rem;
    }
}

@media (min-width: 577px) and (max-width: 768px) {
    /* Tablet adjustments */
    .row.g-4 > .col-md-8 {
        width: 60%;
    }

    .row.g-4 > .col-md-4 {
        width: 40%;
    }
}

/* Print styles */
@media print {
    .btn,
    .btn-group,
    .dropdown,
    .card-header button,
    .staff-profile-card .btn-remove,
    #quickSearch,
    .filter-category,
    .filter-status {
        display: none !important;
    }

    .card {
        break-inside: avoid;
    }

    #recentMedalsSection {
        display: block !important;
    }
}
</style>
<!-- Load jQuery first from allowed CDN -->
<!-- Core JS (jQuery/Bootstrap) are loaded centrally in shared/footer.php. Keep DataTables scripts below. -->
<!-- DataTables JavaScript -->
<script src="https://cdn.jsdelivr.net/npm/datatables.net@1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/datatables.net-bs5@1.13.6/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/datatables.net-responsive@2.5.0/js/dataTables.responsive.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/datatables.net-responsive-bs5@2.5.0/js/responsive.bootstrap5.min.js"></script>
<script>
let staffTable;
let allStaffData = [];
let selectedStaff = [];

// Wait for jQuery to be available (either from app or CDN)
function waitForJQuery(callback) {
    if (typeof jQuery !== 'undefined' && typeof $ !== 'undefined') {
        callback();
    } else {
        console.log('Waiting for jQuery to load...');
        setTimeout(() => waitForJQuery(callback), 100);
    }
}

// Initialize when jQuery is ready
waitForJQuery(function() {
    $(document).ready(function() {
        console.log('Assign Medal page loaded, initializing staff table...');

        // Add loading indicator
        $('#staffTableContainer').addClass('loading');

        initializeStaffTable();
        bindEventHandlers();

        // Initial button state check
        enableAssignButton();
    });

    // Medal Preview Card (moved inside waitForJQuery so $ is guaranteed defined)
    $('#medal_id').on('change', function() {
        const selectedOption = $(this).find('option:selected');
        const medalName = selectedOption.text();
        const description = selectedOption.data('description') || 'No description available';
        const medalId = $(this).val();

        if (medalId) {
            $('#medalPreviewCard').show();
            $('#previewMedalName').text(medalName);
            $('#previewMedalDescription').text(description);

            // Reload staff table excluding those who already have this medal
            console.log('Medal selected, reloading staff excluding those with medal ID:', medalId);
            initializeStaffTable(medalId);
        } else {
            $('#medalPreviewCard').hide();
            // Reload all staff when medal is cleared
            console.log('Medal cleared, reloading all staff');
            initializeStaffTable(null);
        }

        enableAssignButton();
    });

    $('#medal_id, #award_date').on('input', enableAssignButton);
});

function initializeStaffTable(excludeMedalId = null) {
    // Show loading state
    $('#staffTableContainer').addClass('loading');

    // Load all staff data with proper seniority information
    const ajaxData = { q: 'all', limit: 1000 };

    // Exclude staff who already have the selected medal
    if (excludeMedalId) {
        ajaxData.exclude_medal_id = excludeMedalId;
    }

    $.ajax({
        url: 'search_staff.php',
        method: 'GET',
        data: ajaxData,
        dataType: 'json',
        success: function(response) {
            console.log('Staff data loaded:', response);

            if (Array.isArray(response)) {
                allStaffData = response;
                initDataTable();
            } else if (response && response.results && Array.isArray(response.results)) {
                allStaffData = response.results;
                initDataTable();
            } else {
                console.error('Invalid response format:', response);
                allStaffData = [];
                initDataTable();
            }
        },
        error: function(xhr, status, error) {
            console.error('Error loading staff data:', error);
            console.error('Response:', xhr.responseText);
            // Initialize empty table on error
            allStaffData = [];
            initDataTable();
        }
    });
}

function initDataTable() {
    console.log('Initializing DataTable with', allStaffData.length, 'staff members');

    // Remove loading indicator
    $('#staffTableContainer').removeClass('loading');

    // Helper function to format names in sentence case (same as reports_seniority.php)
    function formatSentenceCase(name) {
        if (!name) return '';
        return name.split(' ').map(word =>
            word.charAt(0).toUpperCase() + word.slice(1).toLowerCase()
        ).join(' ');
    }

    // Convert staff data for DataTables with proper seniority sorting.
    // staff.svcNo is the one true identifier - there is no separate `id`.
    const tableData = allStaffData.map(staff => {
        const serviceNumber = staff.svcNo;

        return {
            svcNo: serviceNumber,
            fName: staff.fName || '',
            lName: staff.lName || '',
            rank_name: staff.rank_name || 'N/A',
            rank_level: staff.rank_level || 999, // High number for unknown ranks
            rank_abbr: staff.rank_abbr || '',
            rank_category: staff.rank_category || '',
            unit_name: staff.unit_name || 'N/A',
            full_name: formatSentenceCase((staff.fName || '') + ' ' + (staff.lName || '')).replace(/^\s+/, '').replace(/\s+$/, '') || 'N/A',
            surname: formatSentenceCase(staff.lName || ''),
            first_names: formatSentenceCase(staff.fName || ''),
            corps: staff.corps || 'N/A',
            status: staff.status || 'Active',
            attestDate: staff.attestDate || '',
            subWef: staff.subWef || '',
            tempWef: staff.tempWef || ''
        };
    });

    // Data is already sorted by seniority from search_staff.php ORDER BY clause.
    // No need to re-sort here - preserve the database order.

    $('#totalStaffCount').text(tableData.length);

    if ($.fn.DataTable.isDataTable('#staffSelectionTable')) {
        $('#staffSelectionTable').DataTable().destroy();
    }

    try {
        staffTable = $('#staffSelectionTable').DataTable({
            data: tableData,
            pageLength: 25,
            order: [], // Don't apply additional ordering - data is already sorted by database seniority order
            ordering: false, // Disable column sorting to preserve database seniority order
            responsive: true,
            columns: [
                {
                    data: null,
                    orderable: false,
                    className: 'select-checkbox text-center',
                    render: function(data, type, row) {
                        return `<input type="checkbox" class="staff-checkbox form-check-input" value="${row.svcNo}">`;
                    }
                },
                {
                    data: 'svcNo',
                    title: 'Service No.',
                    orderable: false // Maintain seniority order
                },
                {
                    data: null,
                    title: 'Rank',
                    orderable: false,
                    render: function(data, type, row) {
                        return `<span class="badge bg-primary">${row.rank_abbr || row.rank_name}</span>`;
                    }
                },
                {
                    data: null,
                    title: 'Name',
                    orderable: false,
                    render: function(data, type, row) {
                        // Helper function for proper title case
                        function toTitleCase(str) {
                            if (!str) return '';
                            return str.trim().split(/\s+/).map(word => {
                                if (word.length === 0) return '';
                                // Handle hyphenated names and apostrophes
                                return word.split('-').map(part =>
                                    part.split("'").map(subpart =>
                                        subpart.charAt(0).toUpperCase() + subpart.slice(1).toLowerCase()
                                    ).join("'")
                                ).join('-');
                            }).join(' ');
                        }

                        const surname = toTitleCase(row.lName || '');
                        const firstName = toTitleCase(row.fName || '');

                        // Properly format name without comma
                        let fullName = '';
                        if (surname && firstName) {
                            fullName = `${firstName} ${surname}`;
                        } else if (surname) {
                            fullName = surname;
                        } else if (firstName) {
                            fullName = firstName;
                        } else {
                            fullName = 'N/A';
                        }

                        return `<div class="fw-bold">${fullName}</div>`;
                    }
                },
                {
                    data: 'unit_name',
                    title: 'Unit',
                    orderable: false
                },
                {
                    data: 'corps',
                    title: 'Corps',
                    orderable: false
                },
                {
                    data: 'status',
                    title: 'Status',
                    orderable: false,
                    render: function(data, type, row) {
                        const statusClass = row.status === 'Active' ? 'bg-success' : 'bg-secondary';
                        return `<span class="badge ${statusClass}">${row.status}</span>`;
                    }
                }
            ],
            language: {
                emptyTable: "No staff members found",
                info: "Showing _START_ to _END_ of _TOTAL_ staff members (ordered by military seniority)",
                infoEmpty: "No staff members available",
                search: "Search staff:"
            },
            dom: '<"row"<"col-sm-12 col-md-6"l><"col-sm-12 col-md-6"f>>rtip'
        });

        console.log('DataTable initialized successfully with military seniority sorting');
    } catch (error) {
        console.error('DataTable initialization failed:', error);
        $('#staffTableContainer').removeClass('loading');

        // Show fallback message
        $('#staffSelectionTable tbody').html(
            '<tr><td colspan="8" class="text-center text-danger">' +
            'Failed to load staff data. Please refresh the page.' +
            '</td></tr>'
        );
    }
}

function bindEventHandlers() {
    // Master checkbox handler
    $(document).on('change', '#masterCheckbox', function() {
        const isChecked = $(this).is(':checked');
        $('.staff-checkbox:visible').prop('checked', isChecked).trigger('change');
    });

    // Individual checkbox handler
    $(document).on('change', '.staff-checkbox', function() {
        const checkbox = $(this);
        const serviceNumber = checkbox.val();
        const row = checkbox.closest('tr');

        if (checkbox.is(':checked')) {
            // Add to selection
            if (!selectedStaff.find(s => s.svcNo === serviceNumber)) {
                const staffData = allStaffData.find(s => s.svcNo === serviceNumber);
                if (staffData) {
                    selectedStaff.push(staffData);
                    row.addClass('selected');
                } else {
                    console.error('Staff data not found for service number:', serviceNumber);
                }
            }
        } else {
            // Remove from selection
            selectedStaff = selectedStaff.filter(s => s.svcNo !== serviceNumber);
            row.removeClass('selected');
        }

        updateSelectionDisplay();
        updateLegacySelect();
        enableAssignButton();
    });

    // Select All button
    $('#selectAllBtn').on('click', function() {
        $('#masterCheckbox').prop('checked', true).trigger('change');
    });

    // Deselect All button
    $('#deselectAllBtn').on('click', function() {
        $('#masterCheckbox').prop('checked', false).trigger('change');
    });
}

function updateSelectionDisplay() {
    $('#selectionCount').text(selectedStaff.length);
    $('#selectedCardCount').text(selectedStaff.length);

    // Update master checkbox state
    const visibleCheckboxes = $('.staff-checkbox:visible');
    const checkedBoxes = $('.staff-checkbox:visible:checked');

    if (checkedBoxes.length === 0) {
        $('#masterCheckbox').prop('indeterminate', false).prop('checked', false);
    } else if (checkedBoxes.length === visibleCheckboxes.length) {
        $('#masterCheckbox').prop('indeterminate', false).prop('checked', true);
    } else {
        $('#masterCheckbox').prop('indeterminate', true);
    }

    // Render staff profile cards (matching appointments.php style)
    renderStaffProfileCards();
}

// Render staff profile cards (matching appointments.php)
function renderStaffProfileCards() {
    const container = $('#selectedStaffList');
    const cardsSection = $('#selectedStaffCards');

    if (selectedStaff.length === 0) {
        cardsSection.hide();
        container.empty();

        // Clear hidden inputs
        $('#selectedStaffInputs').empty();
        return;
    }

    cardsSection.show();
    container.empty();

    // Helper function for proper title case
    function toTitleCase(str) {
        if (!str) return '';
        return str.trim().split(/\s+/).map(word => {
            if (word.length === 0) return '';
            return word.split('-').map(part =>
                part.split("'").map(subpart =>
                    subpart.charAt(0).toUpperCase() + subpart.slice(1).toLowerCase()
                ).join("'")
            ).join('-');
        }).join(' ');
    }

    selectedStaff.forEach((staff, index) => {
        const firstName = toTitleCase(staff.fName || '');
        const lastName = toTitleCase(staff.lName || '');
        const fullName = firstName && lastName ? `${firstName} ${lastName}` : (firstName || lastName || 'N/A');

        const card = `
            <div class="col-md-6 col-lg-4">
                <div class="card staff-profile-card border-primary" data-service-number="${staff.svcNo}">
                    <div class="card-body">
                        <div class="d-flex align-items-start">
                            <div class="staff-avatar-circle me-3">
                                <i class="fas fa-user fa-lg"></i>
                            </div>
                            <div class="flex-grow-1">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <h6 class="staff-name mb-0">${fullName}</h6>
                                        <div class="staff-info">
                                            <i class="fas fa-id-card"></i>
                                            ${staff.svcNo || 'N/A'}
                                        </div>
                                        <div class="staff-info">
                                            <i class="fas fa-star"></i>
                                            ${staff.rank_abbr || staff.rank_name || 'N/A'} |
                                            <i class="fas fa-building"></i>
                                            ${staff.unit_name || 'N/A'}
                                        </div>
                                        ${staff.corps ? `<div class="staff-info"><i class="fas fa-shield-alt"></i> ${staff.corps}</div>` : ''}
                                    </div>
                                    <button type="button" class="btn btn-sm btn-outline-danger btn-remove remove-staff-btn"
                                            data-service-number="${staff.svcNo}"
                                            title="Remove from selection">
                                        <i class="fas fa-times"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        `;

        container.append(card);
    });

    // Update hidden inputs for form submission
    updateHiddenInputs();

    // Bind remove button handlers
    $('.remove-staff-btn').off('click').on('click', function() {
        const serviceNumber = $(this).data('service-number');

        // Uncheck the checkbox in the table
        $(`.staff-checkbox[value="${serviceNumber}"]`).prop('checked', false).trigger('change');
    });
}

// Update hidden inputs for form submission
function updateHiddenInputs() {
    const inputsContainer = $('#selectedStaffInputs');
    inputsContainer.empty();

    selectedStaff.forEach(staff => {
        inputsContainer.append(`<input type="hidden" name="selected_staff[]" value="${staff.svcNo}">`);
    });
}

function updateLegacySelect() {
    // Update the hidden select element for form compatibility
    const select = $('#selected_staff');
    select.empty();

    selectedStaff.forEach(staff => {
        const option = new Option(
            `${staff.svcNo} - ${staff.rank_name || ''} ${staff.fName} ${staff.lName}`,
            staff.svcNo,
            true,
            true
        );
        select.append(option);
    });
}

function enableAssignButton() {
    let allFilled = $('#medal_id').val() && $('#award_date').val() && selectedStaff.length > 0;
    $('#showConfirmModal').prop('disabled', !allFilled);
    $('#splitDropdown').prop('disabled', !allFilled);

    // Update progress bar
    updateProgressBar();
}

// Progress Bar Update Function
function updateProgressBar() {
    let progress = 0;

    // Step 1: Medal selected (25%)
    if ($('#medal_id').val()) {
        progress += 25;
        $('#step1').addClass('completed').removeClass('active');
    } else {
        $('#step1').addClass('active').removeClass('completed');
        $('#step2, #step3, #step4').removeClass('active completed');
    }

    // Step 2: Staff selected (25%)
    if (selectedStaff.length > 0 && $('#medal_id').val()) {
        progress += 25;
        $('#step2').addClass('completed').removeClass('active');
    } else if ($('#medal_id').val()) {
        $('#step2').addClass('active').removeClass('completed');
        $('#step3, #step4').removeClass('active completed');
    }

    // Step 3: Award date filled (25%)
    if ($('#award_date').val() && selectedStaff.length > 0 && $('#medal_id').val()) {
        progress += 25;
        $('#step3').addClass('completed').removeClass('active');
    } else if (selectedStaff.length > 0 && $('#medal_id').val()) {
        $('#step3').addClass('active').removeClass('completed');
        $('#step4').removeClass('active completed');
    }

    // Step 4: Ready to confirm (25%)
    if ($('#medal_id').val() && $('#award_date').val() && selectedStaff.length > 0) {
        progress += 25;
        $('#step4').addClass('active').removeClass('completed');
    }

    // Update progress bar
    $('#progressBar').css('width', progress + '%').attr('aria-valuenow', progress);
    $('#progressPercentage').text(progress + '%');
}

// Quick Filter Buttons
$(document).on('click', '.filter-category', function() {
    $('.filter-category').removeClass('active');
    $(this).addClass('active');

    const filter = $(this).data('filter');

    if (staffTable) {
        // Use custom filter function for category filtering
        $.fn.dataTable.ext.search.pop(); // Remove previous custom filter if exists

        if (filter === 'all') {
            staffTable.draw();
        } else {
            $.fn.dataTable.ext.search.push(
                function(settings, data, dataIndex) {
                    const rowData = staffTable.row(dataIndex).data();
                    if (!rowData) return true;

                    const rankCategory = rowData.rank_category || '';
                    const rankLevel = rowData.rank_level || 999;
                    const rankName = (rowData.rank_name || '').toLowerCase();
                    const rankAbbr = (rowData.rank_abbr || '').toLowerCase();

                    let matches = false;

                    if (filter === 'officers') {
                        if (rankCategory === 'Officer') {
                            matches = true;
                        } else if (rankLevel >= 1 && rankLevel <= 13) {
                            matches = true;
                        } else if (rankName.match(/officer|captain|lieutenant|major|colonel|general|brigadier|commander/i) ||
                               rankAbbr.match(/^(2lt|lt|capt|maj|lt col|col|brig|maj gen|lt gen|gen|cmdr|cdr|o\/cdt)$/i)) {
                            matches = true;
                        }
                    } else if (filter === 'ncos') {
                        if (rankCategory === 'NCO') {
                            matches = true;
                        } else if (rankLevel >= 15 && rankLevel <= 26) {
                            matches = true;
                        } else if (rankName.match(/private|lance|corporal|sergeant|warrant/i) ||
                               rankAbbr.match(/^(pvt|pte|rct|lcpl|l\/cpl|l cpl|cpl|sgt|ssgt|s sgt|wo1|wo2|woi|woii)$/i)) {
                            matches = true;
                        }
                    } else if (filter === 'ce') {
                        // FIX: "Civilian Employees Only" was previously
                        // missing entirely — only Officers/NCOs/All
                        // existed, even though CE is the third category
                        // used everywhere else in this module (see
                        // reports_seniority.php).
                        if (rankCategory === 'Civilian Employee' || rankCategory === 'CE') {
                            matches = true;
                        } else if (rankLevel === 28) {
                            matches = true;
                        } else if (rankName.match(/civilian/i)) {
                            matches = true;
                        }
                    }

                    return matches;
                }
            );

            staffTable.draw();
        }
    }
});

$(document).on('click', '.filter-status', function() {
    $('.filter-status').removeClass('active');
    $(this).addClass('active');

    const status = $(this).data('filter');

    if (staffTable) {
        if (status === 'all') {
            staffTable.column(6).search('').draw(); // Clear status filter
        } else if (status === 'active') {
            staffTable.column(6).search('^Active$', true, false).draw(); // Status column
        } else if (status === 'retired') {
            staffTable.column(6).search('^Retired$', true, false).draw(); // Status column
        }
    }
});

$(document).on('keyup', '#quickSearch', function() {
    const searchValue = $(this).val();
    if (staffTable) {
        staffTable.search(searchValue).draw();
    }
});

// Split Button Actions
$(document).on('click', '#assignAndNew', function(e) {
    e.preventDefault();
    sessionStorage.setItem('assignAction', 'new');
    $('#showConfirmModal').click();
});

$(document).on('click', '#assignAndView', function(e) {
    e.preventDefault();
    sessionStorage.setItem('assignAction', 'view');
    $('#showConfirmModal').click();
});

$(document).on('click', '#resetForm', function(e) {
    e.preventDefault();
    if (confirm('Are you sure you want to reset the form? All current selections will be lost.')) {
        location.reload();
    }
});

// Keyboard Shortcut (Ctrl+Enter)
$(document).on('keydown', function(e) {
    if (e.ctrlKey && e.key === 'Enter') {
        e.preventDefault();
        if (!$('#showConfirmModal').prop('disabled')) {
            $('#showConfirmModal').click();
        }
    }
});

// Collapse Icon Toggle
$(document).on('show.bs.collapse', '#recentMedalsSection', function() {
    $('#collapseIcon').removeClass('fa-chevron-down').addClass('fa-chevron-up');
});

$(document).on('hide.bs.collapse', '#recentMedalsSection', function() {
    $('#collapseIcon').removeClass('fa-chevron-up').addClass('fa-chevron-down');
});

// Loading Overlay Functions
function showLoadingOverlay(message = 'Processing...') {
    if ($('#loadingOverlay').length === 0) {
        $('body').append(`
            <div id="loadingOverlay" style="
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background: rgba(0, 0, 0, 0.7);
                display: flex;
                align-items: center;
                justify-content: center;
                z-index: 9999;
            ">
                <div style="
                    background: white;
                    padding: 30px;
                    border-radius: 10px;
                    text-align: center;
                    box-shadow: 0 4px 20px rgba(0,0,0,0.3);
                ">
                    <div class="spinner-border text-primary mb-3" role="status" style="width: 3rem; height: 3rem;">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <h5 class="mb-0">${message}</h5>
                </div>
            </div>
        `);
    }
}

function hideLoadingOverlay() {
    $('#loadingOverlay').fadeOut(300, function() {
        $(this).remove();
    });
}

$(document).on('click', '#showConfirmModal', function() {
    if (selectedStaff.length >= <?=json_encode($BULK_CONFIRMATION_THRESHOLD)?>) {
        $('#bulkCount').text(selectedStaff.length);
        var modal = new bootstrap.Modal(document.getElementById('bulkConfirmModal'));
        modal.show();
        $('#bulkConfirmSubmitBtn').off('click').on('click', function() {
            renderConfirmSummary(selectedStaff);
            var confirmModal = new bootstrap.Modal(document.getElementById('confirmModal'));
            confirmModal.show();
            modal.hide();
        });
        return;
    }
    renderConfirmSummary(selectedStaff);
    var modal = new bootstrap.Modal(document.getElementById('confirmModal'));
    modal.show();
});
function renderConfirmSummary(selected) {
    // Update statistics
    $('#modalSelectedCount').text(selected.length);
    $('#totalAssignments').text(selected.length);

    // Render staff list
    let summary = '<ul class="list-group">';
    selected.forEach(function(staff){
        summary += `<li class="list-group-item d-flex justify-content-between align-items-start">
            <div>
                <div class="fw-bold">${staff.svcNo} - ${staff.rank_name ? staff.rank_name + ' ' : ''}${staff.fName} ${staff.lName}</div>
                <small class="text-muted">${staff.unit_name || 'N/A'}</small>
            </div>
            <span class="badge bg-primary rounded-pill">${$('#medal_id option:selected').text()}</span>
        </li>`;
    });
    summary += '</ul>';
    $('#confirmSummary').html(summary);
}
$(document).on('click', '#confirmSubmitBtn', function() {
    showLoadingOverlay('Assigning medals to selected staff...');
    $('#assignMedalForm').submit();
});
$(document).on('submit', '#assignMedalForm', function() {
    $('#showConfirmModal').prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Assigning...');
});

// Handle post-assignment actions
$(function() {
    const assignAction = sessionStorage.getItem('assignAction');
    if (assignAction) {
        sessionStorage.removeItem('assignAction');

        if (assignAction === 'new') {
            console.log('Ready for new assignment');
        } else if (assignAction === 'view' && selectedStaff.length === 1) {
            // Redirect to profile view (you'll need to implement this URL)
            // window.location.href = 'view_staff.php?id=' + selectedStaff[0].svcNo;
        }
    }

    enableAssignButton();
});
</script>
<?php include dirname(__DIR__) . '/shared/footer.php'; ?>