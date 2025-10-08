<?php
// Define module constants
define('ARMIS_ADMIN_BRANCH', true);
define('ARMIS_DEVELOPMENT', true); // Set to false in production

// Include admin branch authentication and database
require_once __DIR__ . '/includes/auth.php';

// Include RBAC system
require_once dirname(__DIR__) . '/shared/rbac.php';

// Require authentication and admin privileges
requireAuth();

// Check if user has access to admin_branch module
requireModuleAccess('admin_branch');

$pageTitle = "Award Medal";
$moduleName = "Admin Branch";
$moduleIcon = "users-cog";
$currentPage = "medals";

$sidebarLinks = [
    ['title' => 'Dashboard', 'url' => '/Armis2/admin_branch/index.php', 'icon' => 'tachometer-alt', 'page' => 'dashboard'],
    ['title' => 'Create Staff', 'url' => '/Armis2/admin_branch/create_staff.php', 'icon' => 'user-plus', 'page' => 'create_staff'],
    ['title' => 'Edit Staff', 'url' => '/Armis2/admin_branch/edit_staff.php', 'icon' => 'user-edit', 'page' => 'edit_staff'],
    ['title' => 'Appointments', 'url' => '/Armis2/admin_branch/appointments.php', 'icon' => 'briefcase', 'page' => 'appointments'],
    ['title' => 'Medals', 'url' => '/Armis2/admin_branch/medals.php', 'icon' => 'medal', 'page' => 'medals'],
    [
        'title' => 'Reports',
        'icon' => 'chart-bar',
        'page' => 'reports',
        'children' => [
            ['title' => 'Seniority', 'url' => '/Armis2/admin_branch/reports_seniority.php'],
            ['title' => 'Unit List', 'url' => '/Armis2/admin_branch/reports_units.php'],
            ['title' => 'Appointments', 'url' => '/Armis2/admin_branch/reports_appointment.php'],
            ['title' => 'Contracts', 'url' => '/Armis2/admin_branch/reports_contract.php'],
            ['title' => 'Courses', 'url' => '/Armis2/admin_branch/reports_courses.php'],
            ['title' => 'Deceased', 'url' => '/Armis2/admin_branch/reports_deceased.php'],
            ['title' => 'Gender', 'url' => '/Armis2/admin_branch/reports_gender.php'],
            ['title' => 'Marital', 'url' => '/Armis2/admin_branch/reports_marital.php'],
            ['title' => 'Rank', 'url' => '/Armis2/admin_branch/reports_rank.php'],
            ['title' => 'Retired', 'url' => '/Armis2/admin_branch/reports_retired.php'],
            ['title' => 'Trade', 'url' => '/Armis2/admin_branch/reports_trade.php'],
            ['title' => 'Corps', 'url' => '/Armis2/admin_branch/reports_corps.php'],
            ['title' => 'Units', 'url' => '/Armis2/admin_branch/reports_units.php'],
        ]
    ],
    ['title' => 'System Settings', 'url' => '/Armis2/admin_branch/system_settings.php', 'icon' => 'cogs', 'page' => 'settings']
];

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

$errors = [];
$success = false;
$pdo = getDbConnection();
$medals = [];
try {
    // Fetch medals and ensure unique by name (or id if you prefer)
    $stmt = $pdo->query("SELECT id, name, description, image_path FROM medals ORDER BY name ASC");
    $allMedals = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Remove duplicate medals by name (or by id as key)
    $uniqueMedals = [];
    foreach ($allMedals as $medal) {
        // Use name as key (case-insensitive) to avoid duplicates
        $key = strtolower(trim($medal['name']));
        if (!isset($uniqueMedals[$key])) {
            $uniqueMedals[$key] = (object)$medal;
        }
    }
    $medals = array_values($uniqueMedals);
} catch (Exception $e) {
    $errors[] = "Error fetching medals: " . htmlspecialchars($e->getMessage());
}

$BULK_CONFIRMATION_THRESHOLD = 5;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Token::check($_POST['csrf'] ?? '')) {
        $errors[] = "Invalid CSRF token.";
    } else {
        $medalId = trim($_POST['medal_id'] ?? '');
        $awardDate = trim($_POST['award_date'] ?? '');
        $selectedStaff = $_POST['selected_staff'] ?? [];
        $auth = trim($_POST['auth'] ?? '');
        $remark = trim($_POST['remark'] ?? '');
        $gazetteReference = trim($_POST['gazette_reference'] ?? '');
        $barNumber = trim($_POST['bar_number'] ?? '');

        if (!in_array($_SESSION['role'] ?? '', ['admin', 'branch-admin', 'hr'])) {
            $errors[] = "You do not have permission to assign medals.";
        }
        if (!ctype_digit($medalId)) $errors[] = "Invalid medal selected.";
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $awardDate)) $errors[] = "Invalid award date.";
        if (empty($selectedStaff) || !is_array($selectedStaff)) $errors[] = "Please select at least one staff member.";
        foreach ($selectedStaff as $sid) {
            if (!ctype_digit($sid)) $errors[] = "Invalid staff selection.";
        }
        if ($auth === '') $errors[] = "Please enter the authority.";
        if (count($selectedStaff) !== count(array_unique($selectedStaff))) {
            $errors[] = "Duplicate staff selected.";
        }

        $staffInfoList = [];
        foreach ($selectedStaff as $staffId) {
            $stmt = $pdo->prepare("SELECT service_number FROM staff WHERE id = ?");
            $stmt->execute([$staffId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$row || empty($row['service_number'])) {
                $errors[] = "Staff member with ID $staffId not found or missing service number.";
                continue;
            }
            $service_number = $row['service_number'];
            $stmt2 = $pdo->prepare("SELECT COUNT(*) FROM staff_medals WHERE staff_id = ? AND medal_id = ?");
            $stmt2->execute([$staffId, $medalId]);
            $alreadyAwarded = $stmt2->fetchColumn();
            if ($alreadyAwarded > 0) {
                $errors[] = "Staff member {$service_number} has already been awarded this medal.";
            }
            $staffInfoList[] = [
                'staff_id' => $staffId,
                'service_number' => $service_number,
            ];
        }

        if (empty($errors)) {
            try {
                $pdo->beginTransaction();
                $stmt = $pdo->prepare("INSERT INTO staff_medals (staff_id, service_number, medal_id, award_date, citation, gazette_reference, bar_number, created_by, created_at, authority) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $createdBy = $_SESSION['username'] ?? 'admin';
                $now = date('Y-m-d H:i:s');
                foreach ($staffInfoList as $info) {
                    $stmt->execute([
                        $info['staff_id'],
                        $info['service_number'],
                        $medalId,
                        $awardDate,
                        $remark,
                        $gazetteReference,
                        $barNumber,
                        $createdBy,
                        $now,
                        $auth
                    ]);
                }
                $pdo->commit();
                $success = "Medal assigned successfully.";
                $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
                $csrfToken = $_SESSION['csrf_token'];
                $_POST = [];
            } catch (Exception $e) {
                $pdo->rollBack();
                $errors[] = "Error assigning medal: " . htmlspecialchars($e->getMessage());
            }
        }
    }
}
?>

<?php include dirname(__DIR__) . '/shared/header.php'; ?>
<?php include dirname(__DIR__) . '/shared/sidebar.php'; ?>
<div class="content-wrapper with-sidebar">
    <div class="container-fluid p-4">
        <div class="mb-3">
            <a href="medals.php" class="btn btn-outline-secondary"><i class="fa fa-arrow-left"></i> Back to Medals List</a>
        </div>
        <div class="card shadow-sm">
        <div class="card-header bg-info text-white">
            <h4 class="mb-0"><i class="fa fa-medal"></i> Assign Medal to Staff</h4>
        </div>
        <div class="card-body">
            <?php if ($success): ?>
                <div class="alert alert-success"><?=htmlspecialchars($success)?></div>
            <?php endif; ?>
            <?php if ($errors): ?>
                <div class="alert alert-danger">
                    <ul class="mb-0">
                        <?php foreach ($errors as $err): ?>
                            <li><?=htmlspecialchars($err)?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
            <div class="mb-3">
                <ul class="stepper mb-0">
                    <li class="step active">Select Medal</li>
                    <li class="step <?=isset($_POST['medal_id'])?'active':''?>">Select Staff</li>
                    <li class="step <?=isset($_POST['selected_staff'])?'active':''?>">Details</li>
                    <li class="step">Confirm</li>
                    <li class="step <?=($success?'active':'')?>">Success</li>
                </ul>
            </div>
            <form method="post" action="" autocomplete="off" aria-label="Assign Medal Form" id="assignMedalForm">
                <input type="hidden" name="csrf" value="<?=htmlspecialchars($csrfToken)?>">
                <div class="mb-3">
                    <label for="medal_id" class="form-label" aria-label="Medal">Medal <span class="text-danger">*</span></label>
                    <select name="medal_id" id="medal_id" class="form-select" required aria-required="true">
                        <option value="">Select Medal...</option>
                        <?php foreach ($medals as $medal): ?>
                            <option value="<?=htmlspecialchars($medal->id)?>" <?=isset($_POST['medal_id']) && $_POST['medal_id']==$medal->id?'selected':''?>>
                                <?=htmlspecialchars($medal->name)?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (!empty($medals)): ?>
                    <div class="mt-2">
                        <?php foreach ($medals as $medal): ?>
                            <span class="badge bg-secondary mx-1" title="<?=htmlspecialchars($medal->description)?>">
                                <?php if(!empty($medal->image_path)): ?>
                                    <img src="<?=htmlspecialchars($medal->image_path)?>" alt="Medal" height="22" style="vertical-align:middle;">
                                <?php endif; ?>
                                <?=htmlspecialchars($medal->name)?>
                            </span>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
                <div class="mb-3">
                    <label for="award_date" class="form-label" aria-label="Award Date">Award Date <span class="text-danger">*</span></label>
                    <input type="date" class="form-control" id="award_date" name="award_date" required aria-required="true" min="1900-01-01" max="<?=date('Y-m-d')?>" value="<?=htmlspecialchars($_POST['award_date'] ?? date('Y-m-d'))?>">
                </div>
                <div class="mb-3">
                    <label for="auth" class="form-label" aria-label="Authority">Authority <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="auth" name="auth" required aria-required="true" value="<?=htmlspecialchars($_POST['auth'] ?? '')?>">
                </div>
                <div class="mb-3">
                    <label for="selected_staff" class="form-label" aria-label="Select Staff Members">Select Staff Members <span class="text-danger">*</span></label>
                    
                    <!-- Staff Selection Controls -->
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h6 class="mb-0">
                            <i class="fa fa-users"></i> 
                            Select Staff Members for Medal Assignment
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
                    
                    <!-- Hidden container to store selected staff for form submission -->
                    <div id="selectedStaffInputs"></div>
                    
                    <!-- Legacy Select2 hidden input for compatibility -->
                    <select name="selected_staff[]" id="selected_staff" multiple style="display: none;">
                        <!-- Will be populated by JavaScript -->
                    </select>
                </div>
                <div class="mb-3">
                    <label for="remark" class="form-label" aria-label="Citation or Remarks">Citation / Remarks</label>
                    <input type="text" class="form-control" id="remark" name="remark" value="<?=htmlspecialchars($_POST['remark'] ?? '')?>">
                </div>
                <div class="mb-3">
                    <label for="gazette_reference" class="form-label">Gazette Reference</label>
                    <input type="text" class="form-control" id="gazette_reference" name="gazette_reference" value="<?=htmlspecialchars($_POST['gazette_reference'] ?? '')?>">
                </div>
                <div class="mb-3">
                    <label for="bar_number" class="form-label">Bar Number</label>
                    <input type="text" class="form-control" id="bar_number" name="bar_number" value="<?=htmlspecialchars($_POST['bar_number'] ?? '')?>">
                </div>
                <div class="text-end">
                    <button type="button" id="showConfirmModal" class="btn btn-primary px-5 py-2" aria-label="Review and confirm medal assignment" disabled><i class="fa fa-medal"></i> Assign Medal</button>
                </div>
                <div class="modal fade" id="confirmModal" tabindex="-1" aria-labelledby="confirmModalLabel" aria-hidden="true">
                    <div class="modal-dialog modal-lg">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title" id="confirmModalLabel">Confirm Medal Assignment</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body">
                                <div id="confirmSummary"></div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                <button type="button" id="confirmSubmitBtn" class="btn btn-primary">Confirm</button>
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
        <div class="card-header bg-success text-white">
            <h5 class="mb-0"><i class="fa fa-history"></i> Recent Medal Assignments</h5>
        </div>
        <div class="card-body">
            <?php
            // Fetch recent medal assignments
            try {
                $recentMedalsStmt = $pdo->prepare("
                    SELECT 
                        sm.id,
                        sm.award_date,
                        sm.citation,
                        sm.awarded_by,
                        sm.created_at,
                        s.service_number,
                        s.first_name,
                        s.last_name,
                        r.name AS rank_name,
                        r.abbreviation AS rank_abbr,
                        u.name AS unit_name,
                        m.name AS medal_name,
                        m.description AS medal_description
                    FROM staff_medals sm
                    LEFT JOIN staff s ON sm.staff_id = s.id
                    LEFT JOIN ranks r ON s.rank_id = r.id
                    LEFT JOIN units u ON s.unit_id = u.id
                    LEFT JOIN medals m ON sm.medal_id = m.id
                    ORDER BY sm.created_at DESC, sm.award_date DESC
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
                                                    <?= htmlspecialchars($medal->service_number ?? 'N/A') ?>
                                                </strong>
                                                <br>
                                                <small class="text-muted">
                                                    <?php if (!empty($medal->rank_abbr)): ?>
                                                        <?= htmlspecialchars($medal->rank_abbr) ?>
                                                    <?php endif; ?>
                                                    <?= htmlspecialchars(($medal->first_name ?? '') . ' ' . ($medal->last_name ?? '')) ?>
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
                                        <?php if (!empty($medal->awarded_by)): ?>
                                            <span class="badge bg-secondary">
                                                <?= htmlspecialchars($medal->awarded_by) ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="text-muted">N/A</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if (!empty($medal->created_at)): ?>
                                            <small class="text-muted">
                                                <?= date('d M Y H:i', strtotime($medal->created_at)) ?>
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
    </div>
</div>

<!-- DataTables CSS -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/datatables.net-bs5@1.13.6/css/dataTables.bootstrap5.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/datatables.net-responsive-bs5@2.5.0/css/responsive.bootstrap5.min.css">

<style>
.stepper { list-style: none; padding: 0; display: flex; gap: 10px; }
.step { padding: 4px 10px; border-radius: 12px; background: #eee; color: #333; }
.step.active { background: #17a2b8; color: #fff; font-weight: bold; }

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
</style>
<!-- Load jQuery first from allowed CDN -->
<script src="https://cdn.jsdelivr.net/npm/jquery@3.6.0/dist/jquery.min.js"></script>
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
});

function initializeStaffTable() {
    // Load all staff data with proper seniority information
    $.ajax({
        url: 'search_staff.php',
        method: 'GET',
        data: { q: 'all', limit: 1000 },
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
    
    // Convert staff data for DataTables with proper seniority sorting
    const tableData = allStaffData.map(staff => {
        return {
            id: staff.staff_id || staff.id || staff.service_number,
            service_number: staff.service_number || 'N/A',
            first_name: staff.first_name || '',
            last_name: staff.last_name || '',
            rank_name: staff.rank_name || 'N/A',
            rank_level: staff.rank_level || 999, // High number for unknown ranks
            rank_abbr: staff.rank_abbr || '',
            unit_name: staff.unit_name || 'N/A',
            full_name: formatSentenceCase((staff.first_name || '') + ' ' + (staff.last_name || '')).replace(/^\s+/, '').replace(/\s+$/, '') || 'N/A',
            surname: formatSentenceCase(staff.last_name || ''),
            first_names: formatSentenceCase(staff.first_name || ''),
            corps: staff.corps || 'N/A',
            status: staff.status || 'Active',
            attestDate: staff.attestDate || '',
            subWef: staff.subWef || '',
            tempWef: staff.tempWef || '',
            // Add sorting keys for military seniority (same logic as reports_seniority.php)
            sort_key: [
                staff.rank_level || 999,
                staff.subWef || '9999-12-31',
                staff.tempWef || '9999-12-31', 
                staff.attestDate || '9999-12-31',
                staff.service_number || 'ZZZ999'
            ].join('|')
        };
    });
    
    // Sort the data using military seniority logic
    tableData.sort((a, b) => {
        // Primary sort: Rank level (lower numbers = higher ranks)
        if (a.rank_level !== b.rank_level) {
            return a.rank_level - b.rank_level;
        }
        
        // Secondary sort: Sub rank effective date (earlier = senior)
        if (a.subWef !== b.subWef) {
            return (a.subWef || '9999-12-31').localeCompare(b.subWef || '9999-12-31');
        }
        
        // Tertiary sort: Temp rank effective date (earlier = senior)
        if (a.tempWef !== b.tempWef) {
            return (a.tempWef || '9999-12-31').localeCompare(b.tempWef || '9999-12-31');
        }
        
        // Quaternary sort: Attestation date (earlier = senior)
        if (a.attestDate !== b.attestDate) {
            return (a.attestDate || '9999-12-31').localeCompare(b.attestDate || '9999-12-31');
        }
        
        // Final sort: Service number
        return (a.service_number || 'ZZZ999').localeCompare(b.service_number || 'ZZZ999');
    });
    
    console.log('Table data sample (sorted by seniority):', tableData.slice(0, 3));
    
    $('#totalStaffCount').text(tableData.length);
    
    if ($.fn.DataTable.isDataTable('#staffSelectionTable')) {
        $('#staffSelectionTable').DataTable().destroy();
    }
    
    try {
        staffTable = $('#staffSelectionTable').DataTable({
            data: tableData,
            pageLength: 25,
            order: [], // Don't apply additional ordering, data is already sorted by seniority
            responsive: true,
            columns: [
                {
                    data: null,
                    orderable: false,
                    className: 'select-checkbox text-center',
                    render: function(data, type, row) {
                        return `<input type="checkbox" class="staff-checkbox form-check-input" value="${row.service_number}" data-staff-id="${row.id}">`;
                    }
                },
                { 
                    data: 'service_number', 
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
                        
                        const surname = toTitleCase(row.last_name || '');
                        const firstName = toTitleCase(row.first_name || '');
                        
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
        const staffId = checkbox.data('staff-id');
        const row = checkbox.closest('tr');
        
        if (checkbox.is(':checked')) {
            // Add to selection
            if (!selectedStaff.find(s => s.service_number === serviceNumber)) {
                const staffData = allStaffData.find(s => s.service_number === serviceNumber);
                if (staffData) {
                    selectedStaff.push(staffData);
                    row.addClass('selected');
                }
            }
        } else {
            // Remove from selection
            selectedStaff = selectedStaff.filter(s => s.service_number !== serviceNumber);
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
}

function updateLegacySelect() {
    // Update the hidden select element for form compatibility
    const select = $('#selected_staff');
    select.empty();
    
    selectedStaff.forEach(staff => {
        const option = new Option(
            `${staff.service_number} - ${staff.rank_name || ''} ${staff.first_name} ${staff.last_name}`,
            staff.service_number,
            true,
            true
        );
        select.append(option);
    });
}

function calculateAge(dob) {
    if (!dob) return null;
    const birthDate = new Date(dob);
    const today = new Date();
    let age = today.getFullYear() - birthDate.getFullYear();
    const monthDiff = today.getMonth() - birthDate.getMonth();
    if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < birthDate.getDate())) {
        age--;
    }
    return age;
}
function enableAssignButton() {
    let allFilled = $('#medal_id').val() && $('#award_date').val() && $('#auth').val() && selectedStaff.length > 0;
    $('#showConfirmModal').prop('disabled', !allFilled);
}
$('#medal_id, #award_date, #auth').on('input', enableAssignButton);
$('#showConfirmModal').on('click', function() {
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
    let summary = '<ul class="list-group">';
    selected.forEach(function(staff){
        summary += `<li class="list-group-item">${staff.service_number} - ${staff.rank_name ? staff.rank_name + ' ' : ''}${staff.first_name} ${staff.last_name} <br><strong>Medal:</strong> ${$('#medal_id option:selected').text()} <br><strong>Authority:</strong> ${$('#auth').val() || '-'}</li>`;
    });
    summary += '</ul>';
    $('#confirmSummary').html(summary);
}
$('#confirmSubmitBtn').on('click', function() {
    $('#assignMedalForm').submit();
});
$('#assignMedalForm').on('submit', function() {
    $('#assignMedalBtn').prop('disabled', true).text('Assigning...');
});
$(function() {
    enableAssignButton();
});
</script>
<?php include dirname(__DIR__) . '/shared/footer.php'; ?>