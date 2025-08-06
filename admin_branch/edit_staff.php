<?php
// Define module constants
define('ARMIS_ADMIN_BRANCH', true);
define('ARMIS_DEVELOPMENT', true); // Set to false in production

// Include admin branch authentication and database
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/analytics.php';

// Include RBAC system
require_once dirname(__DIR__) . '/shared/rbac.php';

// Include database connection
require_once dirname(__DIR__) . '/shared/database_connection.php';

// Require authentication and admin privileges
requireAuth();

// Check if user has access to admin_branch module
requireModuleAccess('admin_branch');

// Create user object with data function for compatibility with existing code
class User {
    public function data() {
        $data = new stdClass();
        $data->id = $_SESSION['user_id'] ?? 0;
        $data->username = $_SESSION['username'] ?? 'unknown';
        return $data;
    }
}
$user = new User();

$pageTitle = "Edit Staff - Admin Branch";
$moduleName = "Admin Branch";
$moduleIcon = "user-edit";
$currentPage = "edit";

$sidebarLinks = [
    ['title' => 'Dashboard', 'url' => '/Armis2/admin_branch/index.php', 'icon' => 'tachometer-alt', 'page' => 'dashboard'],
    ['title' => 'Staff Management', 'url' => '/Armis2/admin_branch/edit_staff.php', 'icon' => 'users', 'page' => 'staff'],
    ['title' => 'Create Staff', 'url' => '/Armis2/admin_branch/create_staff.php', 'icon' => 'user-plus', 'page' => 'create'],
    ['title' => 'Promotions', 'url' => '/Armis2/admin_branch/promote_staff.php', 'icon' => 'arrow-up', 'page' => 'promotions'],
    ['title' => 'Medals', 'url' => '/Armis2/admin_branch/assign_medal.php', 'icon' => 'medal', 'page' => 'medals'],
    [
        'title' => 'Reports',
        'icon' => 'chart-bar',
        'page' => 'reports',
        'url' => '#',
        'children' => [
            ['title' => 'Seniority', 'url' => '/Armis2/admin_branch/reports_seniority.php', 'page' => 'reports_seniority'],
            ['title' => 'Appointments', 'url' => '/Armis2/admin_branch/reports_appointments.php', 'page' => 'reports_appointments'],
            ['title' => 'Contract', 'url' => '/Armis2/admin_branch/reports_contract.php', 'page' => 'reports_contract'],
            ['title' => 'Courses', 'url' => '/Armis2/admin_branch/reports_courses.php', 'page' => 'reports_courses'],
            ['title' => 'Courses Filters', 'url' => '/Armis2/admin_branch/reports_courses_filters.php', 'page' => 'reports_courses_filters'],
            ['title' => 'Deceased', 'url' => '/Armis2/admin_branch/reports_deceased.php', 'page' => 'reports_deceased'],
            ['title' => 'Gender', 'url' => '/Armis2/admin_branch/reports_gender.php', 'page' => 'reports_gender'],
            ['title' => 'Marital', 'url' => '/Armis2/admin_branch/reports_marital.php', 'page' => 'reports_marital'],
            ['title' => 'Rank', 'url' => '/Armis2/admin_branch/reports_rank.php', 'page' => 'reports_rank'],
            ['title' => 'Retired', 'url' => '/Armis2/admin_branch/reports_retired.php', 'page' => 'reports_retired'],
            ['title' => 'Trade', 'url' => '/Armis2/admin_branch/reports_trade.php', 'page' => 'reports_trade'],
            ['title' => 'Corps', 'url' => '/Armis2/admin_branch/reports_corps.php', 'page' => 'reports_corps'],
            ['title' => 'Units', 'url' => '/Armis2/admin_branch/reports_units.php', 'page' => 'reports_units'],
        ]
    ],
    ['title' => 'System Settings', 'url' => '/Armis2/admin_branch/system_settings.php', 'icon' => 'cogs', 'page' => 'settings']
];


// --- Constants ---
define('MAX_NAME_LENGTH', 100);
define('MAX_NRC_LENGTH', 50);
define('MIN_NAME_LENGTH', 2);
define('MIN_AGE_YEARS', 18);
define('MAX_AGE_YEARS', 65);
define('VALID_GENDERS', ['Male', 'Female']);
define('VALID_STATUSES', ['Active', 'Inactive', 'Retired', 'Transferred']);
define('MAX_FILE_SIZE', 2097152); // 2MB
define('ALLOWED_IMAGE_TYPES', ['image/jpeg', 'image/png', 'image/gif']);
define('UPLOAD_DIR', '../uploads/staff_photos/');

// Ensure upload directory exists
if (!is_dir(UPLOAD_DIR)) {
    mkdir(UPLOAD_DIR, 0755, true);
}

// --- Validation functions ---

function validateName($name, $field = 'Name') {
    $name = trim($name);
    if (strlen($name) < MIN_NAME_LENGTH) {
        return "$field must be at least " . MIN_NAME_LENGTH . " characters long.";
    }
    if (strlen($name) > MAX_NAME_LENGTH) {
        return "$field must not exceed " . MAX_NAME_LENGTH . " characters.";
    }
    if (!preg_match("/^[a-zA-Z\s\-'\.]+$/", $name)) {
        return "$field contains invalid characters.";
    }
    return null;
}

function validateNRC($nrc) {
    if (empty($nrc)) return null; // NRC is optional
    $nrc = trim($nrc);
    if (strlen($nrc) > MAX_NRC_LENGTH) {
        return "NRC must not exceed " . MAX_NRC_LENGTH . " characters.";
    }
    // Add your country-specific NRC format validation here
    return null;
}

function validateDOB($dob) {
    if (empty($dob)) return null; // DOB is optional
    $birthDate = DateTime::createFromFormat('Y-m-d', $dob);
    if (!$birthDate) {
        return "Invalid date format.";
    }
    $today = new DateTime();
    $age = $today->diff($birthDate)->y;
    if ($birthDate > $today) {
        return "Date of birth cannot be in the future.";
    }
    if ($age < MIN_AGE_YEARS) {
        return "Staff member must be at least " . MIN_AGE_YEARS . " years old.";
    }
    if ($age > MAX_AGE_YEARS) {
        return "Staff member cannot be older than " . MAX_AGE_YEARS . " years.";
    }
    return null;
}

function logStaffChange($svcNo, $oldData, $newData, $userId, $userIP) {
    $changes = [];
    $fields = ['first_name', 'last_name', 'rank_id', 'unit_id', 'NRC', 'DOB', 'gender', 'svcStatus'];
    foreach ($fields as $field) {
        if (isset($oldData->$field) && isset($newData[$field]) && $oldData->$field != $newData[$field]) {
            $changes[] = [
                'field' => $field,
                'old_value' => $oldData->$field,
                'new_value' => $newData[$field]
            ];
        }
    }
    if (!empty($changes)) {
        try {
            $pdo = getDbConnection();
            $stmt = $pdo->prepare("INSERT INTO staff_edit_log (service_number, edited_by, edited_at, user_ip, changes) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([
                $svcNo,
                $userId,
                date('Y-m-d H:i:s'),
                $userIP,
                json_encode($changes)
            ]);
        } catch (Exception $e) {
            error_log("Failed to log staff changes: " . $e->getMessage());
        }
    }
}

// --- CSV Export ---
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=staff_list.csv');
    $output = fopen('php://output', 'w');
    
    // CSV headers
    fputcsv($output, ['Service Number', 'First Name', 'Last Name', 'Rank', 'Unit', 'Status', 'Date of Birth', 'Date of Enlistment', 'Category']);
    
    // Build query for export (similar to AJAX search but with more fields)
    $pdo = getDbConnection();
    $sql = "SELECT s.service_number, s.first_name, s.last_name, r.name as rank_name, u.name as unit_name, 
                   s.svcStatus, s.DOB, s.attestDate, s.category 
            FROM staff s
            LEFT JOIN ranks r ON s.rank_id = r.id
            LEFT JOIN units u ON s.unit_id = u.id
            WHERE 1=1";
    
    $params = [];
    
    // Apply filters if set
    $rankFilter = $_GET['rank'] ?? '';
    $unitFilter = $_GET['unit'] ?? '';
    $statusFilter = $_GET['status'] ?? '';
    $search = trim($_GET['search'] ?? '');
    
    if ($rankFilter !== '') {
        $sql .= " AND s.rank_id = ?";
        $params[] = $rankFilter;
    }
    
    if ($unitFilter !== '') {
        $sql .= " AND s.unit_id = ?";
        $params[] = $unitFilter;
    }
    
    if ($statusFilter !== '') {
        $sql .= " AND s.svcStatus = ?";
        $params[] = $statusFilter;
    }
    
    if ($search !== '') {
        $sql .= " AND (s.service_number LIKE ? OR s.first_name LIKE ? OR s.last_name LIKE ?)";
        $searchParam = '%' . $search . '%';
        $params = array_merge($params, [$searchParam, $searchParam, $searchParam]);
    }
    
    // Exclude retired and deceased staff if requested
    if (isset($_GET['exclude_inactive']) && $_GET['exclude_inactive'] === '1') {
        $sql .= " AND s.svcStatus = 'Active'";
    }
    
    $sql .= " ORDER BY r.level ASC, s.last_name ASC, s.first_name ASC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    
    // Export all rows
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        fputcsv($output, [
            $row['service_number'],
            $row['first_name'],
            $row['last_name'],
            $row['rank_name'],
            $row['unit_name'],
            $row['svcStatus'],
            $row['DOB'],
            $row['attestDate'],
            $row['category']
        ]);
    }
    
    // Log the export
    logActivity('staff_export', json_encode([
        'filters' => [
            'rank' => $rankFilter,
            'unit' => $unitFilter,
            'status' => $statusFilter,
            'search' => $search
        ],
        'count' => $stmt->rowCount()
    ]));
    
    exit;
}

// --- AJAX search endpoint ---
if (isset($_GET['ajax']) && $_GET['ajax'] === '1') {
    header('Content-Type: application/json');
    $pdo = getDbConnection();
    
    // Handle audit logging for search
    if (isset($_GET['log_search']) && $_GET['log_search'] === '1') {
        $requestData = json_decode(file_get_contents('php://input'), true);
        $logData = [
            'user_id' => $user->data()->id ?? 0,
            'action' => 'staff_search',
            'search_terms' => json_encode($requestData),
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '',
            'created_at' => date('Y-m-d H:i:s')
        ];
        try {
            $stmt = $pdo->prepare("INSERT INTO activity_log (user_id, action, details, ip_address, created_at) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$logData['user_id'], $logData['action'], $logData['search_terms'], $logData['ip_address'], $logData['created_at']]);
        } catch (Exception $e) {
            error_log("Failed to log search activity: " . $e->getMessage());
        }
        echo json_encode(['success' => true]);
        exit;
    }
    
    // Handle audit logging for viewing staff
    if (isset($_GET['audit_view']) && $_GET['audit_view'] === '1' && isset($_GET['staff_id'])) {
        $staffId = $_GET['staff_id'];
        $logData = [
            'user_id' => $user->data()->id ?? 0,
            'action' => 'staff_view',
            'details' => json_encode(['staff_id' => $staffId]),
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '',
            'created_at' => date('Y-m-d H:i:s')
        ];
        try {
            $stmt = $pdo->prepare("INSERT INTO activity_log (user_id, action, details, ip_address, created_at) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$logData['user_id'], $logData['action'], $logData['details'], $logData['ip_address'], $logData['created_at']]);
        } catch (Exception $e) {
            error_log("Failed to log view activity: " . $e->getMessage());
        }
        echo json_encode(['success' => true]);
        exit;
    }
    
    // Perform staff search
    $ranks = $pdo->query("SELECT id as rankID, name as rankName FROM ranks")->fetchAll(PDO::FETCH_OBJ);
    $rankMap = [];
    foreach ($ranks as $r) $rankMap[$r->rankID] = $r->rankName;
    $units = $pdo->query("SELECT id as unitID, name as unitName FROM units")->fetchAll(PDO::FETCH_OBJ);
    $unitMap = [];
    foreach ($units as $u) $unitMap[$u->unitID] = $u->unitName;

    $search = trim($_GET['search'] ?? '');
    $rankFilter = $_GET['rank'] ?? '';
    $unitFilter = $_GET['unit'] ?? '';
    $statusFilter = $_GET['status'] ?? '';
    $excludeInactive = isset($_GET['exclude_inactive']) && $_GET['exclude_inactive'] === '1';
    
    $sql = "SELECT id, service_number, first_name, last_name, rank_id, unit_id, svcStatus FROM staff WHERE 1=1";
    $params = [];
    
    // Search condition
    if ($search !== '') {
        $sql .= " AND (service_number LIKE ? OR first_name LIKE ? OR last_name LIKE ?)";
        $searchParam = '%' . $search . '%';
        $params = array_merge($params, [$searchParam, $searchParam, $searchParam]);
    }
    
    // Filter conditions
    if ($rankFilter !== '') {
        $sql .= " AND rank_id = ?";
        $params[] = $rankFilter;
    }
    
    if ($unitFilter !== '') {
        $sql .= " AND unit_id = ?";
        $params[] = $unitFilter;
    }
    
    if ($statusFilter !== '') {
        $sql .= " AND svcStatus = ?";
        $params[] = $statusFilter;
    }
    
    // Exclude retired and deceased staff when requested
    if ($excludeInactive) {
        $sql .= " AND svcStatus = 'Active'";
    }
    
    $sql .= " ORDER BY rank_id ASC, last_name ASC, first_name ASC LIMIT 200";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $staffList = $stmt->fetchAll(PDO::FETCH_OBJ);
    $result = [];
    foreach ($staffList as $s) {
        $result[] = [
            'id' => $s->id,
            'svcNo' => $s->service_number,
            'name' => $s->last_name . ' ' . $s->first_name,
            'rank' => isset($rankMap[$s->rank_id]) ? $rankMap[$s->rank_id] : ('ID:' . $s->rank_id),
            'unit' => isset($unitMap[$s->unit_id]) ? $unitMap[$s->unit_id] : '',
            'status' => $s->svcStatus
        ];
    }
    echo json_encode($result);
    exit;
}

// --- CSRF helper ---
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
function csrf_token() { return $_SESSION['csrf_token']; }

// --- Lookup for ranks, units, corps ---
$pdo = getDbConnection();
$ranks = $pdo->query("SELECT id as rankID, name as rankName FROM ranks")->fetchAll(PDO::FETCH_OBJ);
$rankMap = [];
foreach ($ranks as $r) $rankMap[$r->rankID] = $r->rankName;
$units = $pdo->query("SELECT id as unitID, name as unitName FROM units")->fetchAll(PDO::FETCH_OBJ);
$unitMap = [];
foreach ($units as $u) $unitMap[$u->unitID] = $u->unitName;
$corps = $pdo->query("SELECT id, name FROM corps ORDER BY name ASC")->fetchAll(PDO::FETCH_OBJ);

$errors = [];
$success = false;
$staff = null;

// --- Staff selection ---
if (isset($_GET['svcNo'])) {
    $svcNo = $_GET['svcNo'];
    $stmt = $pdo->prepare("SELECT * FROM staff WHERE service_number = ?");
    $stmt->execute([$svcNo]);
    $staff = $stmt->fetch(PDO::FETCH_OBJ);
}

// --- Handle edit post ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_staff'])) {
    $svcNo = $_POST['svcNo'];
    $csrf = $_POST['csrf_token'] ?? '';
    if (!isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $csrf)) {
        $errors[] = "Invalid CSRF token. Please reload the page and try again.";
    } else {
        $stmt = $pdo->prepare("SELECT * FROM staff WHERE service_number = ?");
        $stmt->execute([$svcNo]);
        $originalStaff = $stmt->fetch(PDO::FETCH_OBJ);
        if (!$originalStaff) {
            $errors[] = "Staff member not found.";
        } else {
            // Get and sanitize form data
            $fname = trim($_POST['fname'] ?? '');
            $lname = trim($_POST['lname'] ?? '');
            $rankID = $_POST['rankID'] ?? '';
            $unitID = $_POST['unitID'] ?? '';
            $corpsID = $_POST['corps'] ?? '';
            $NRC = trim($_POST['NRC'] ?? '');
            $DOB = $_POST['DOB'] ?? '';
            $gender = $_POST['gender'] ?? '';
            $svcStatus = $_POST['svcStatus'] ?? '';
            $tel = $_POST['tel'] ?? '';
            $email = $_POST['email'] ?? '';
            $address = $_POST['address'] ?? '';
            $nok = $_POST['nok'] ?? '';
            $nokTel = $_POST['nokTel'] ?? '';
            $nokNrc = $_POST['nokNrc'] ?? '';
            $nokRelat = $_POST['nokRelat'] ?? '';
            $profession = $_POST['profession'] ?? '';
            $trade = $_POST['trade'] ?? '';
            $specialization = $_POST['specialization'] ?? '';
            $combatSize = $_POST['combatSize'] ?? '';
            $bsize = $_POST['bsize'] ?? '';
            $ssize = $_POST['ssize'] ?? '';
            $hdress = $_POST['hdress'] ?? '';
            $attestDate = $_POST['attestDate'] ?? '';
            $lastPromotion = $_POST['lastPromotion'] ?? '';
            $postingHistory = $_POST['postingHistory'] ?? '';
            $awards = $_POST['awards'] ?? '';
            $disciplinaryRecord = $_POST['disciplinaryRecord'] ?? '';

            // Comprehensive validation
            $validationErrors = [];
            if ($nameError = validateName($fname, 'First name')) $validationErrors['fname'] = $nameError;
            if ($nameError = validateName($lname, 'Last name')) $validationErrors['lname'] = $nameError;
            if ($nrcError = validateNRC($NRC)) $validationErrors['NRC'] = $nrcError;
            if ($dobError = validateDOB($DOB)) $validationErrors['DOB'] = $dobError;
            if (empty($rankID)) $validationErrors['rankID'] = 'Rank is required.';
            if (empty($unitID)) $validationErrors['unitID'] = 'Unit is required.';
            if (empty($gender)) $validationErrors['gender'] = 'Gender is required.';
            elseif (!in_array($gender, VALID_GENDERS)) $validationErrors['gender'] = 'Invalid gender selection.';
            if (empty($svcStatus)) $validationErrors['svcStatus'] = 'Service status is required.';
            elseif (!in_array($svcStatus, VALID_STATUSES)) $validationErrors['svcStatus'] = 'Invalid service status selection.';

            if (!empty($validationErrors)) {
                foreach ($validationErrors as $error) $errors[] = $error;
            } else {
                $updateData = [
                    'first_name' => $fname,
                    'last_name' => $lname,
                    'rank_id' => $rankID,
                    'unit_id' => $unitID,
                    'corps_id' => $corpsID,
                    'NRC' => $NRC,
                    'DOB' => $DOB,
                    'gender' => $gender,
                    'svcStatus' => $svcStatus,
                    'tel' => $tel,
                    'email' => $email,
                    'address' => $address,
                    'nok' => $nok,
                    'nokTel' => $nokTel,
                    'nokNrc' => $nokNrc,
                    'nokRelat' => $nokRelat,
                    'profession' => $profession,
                    'trade' => $trade,
                    'specialization' => $specialization,
                    'combatSize' => $combatSize,
                    'bsize' => $bsize,
                    'ssize' => $ssize,
                    'hdress' => $hdress,
                    'attestDate' => $attestDate,
                    'lastPromotion' => $lastPromotion,
                    'postingHistory' => $postingHistory,
                    'awards' => $awards,
                    'disciplinaryRecord' => $disciplinaryRecord
                ];
                try {
                    $pdo->beginTransaction();
                    
                    // Debug: Log the update operation
                    error_log("Starting staff update for service number: " . $svcNo);
                    
                    logStaffChange($svcNo, $originalStaff, $updateData, $user->data()->id ?? 0, $_SERVER['REMOTE_ADDR'] ?? '');
                    $updateSql = "UPDATE staff SET first_name = ?, last_name = ?, rank_id = ?, unit_id = ?, corps_id = ?, NRC = ?, DOB = ?, gender = ?, svcStatus = ?, tel = ?, email = ?, address = ?, nok = ?, nokTel = ?, nokNrc = ?, nokRelat = ?, profession = ?, trade = ?, specialization = ?, combatSize = ?, bsize = ?, ssize = ?, hdress = ?, attestDate = ?, lastPromotion = ?, postingHistory = ?, awards = ?, disciplinaryRecord = ? WHERE service_number = ?";
                    $updateParams = [$fname, $lname, $rankID, $unitID, $corpsID, $NRC, $DOB, $gender, $svcStatus, $tel, $email, $address, $nok, $nokTel, $nokNrc, $nokRelat, $profession, $trade, $specialization, $combatSize, $bsize, $ssize, $hdress, $attestDate, $lastPromotion, $postingHistory, $awards, $disciplinaryRecord, $svcNo];
                    
                    // Debug: Log the SQL and parameters
                    error_log("Update SQL: " . $updateSql);
                    error_log("Update Params: " . json_encode($updateParams));
                    
                    $stmt = $pdo->prepare($updateSql);
                    $stmt->execute($updateParams);
                    
                    error_log("Rows affected by main update: " . $stmt->rowCount());
                    
                    // Debug dynamic fields
                    $operations = $_POST['operations'] ?? [];
                    $deployments = $_POST['deployments'] ?? [];
                    $education = $_POST['education'] ?? [];
                    $skills = $_POST['skills'] ?? [];
                    
                    error_log("Processing operations data: " . json_encode($operations));
                    error_log("Processing deployments data: " . json_encode($deployments));
                    error_log("Processing education data: " . json_encode($education));
                    error_log("Processing skills data: " . json_encode($skills));

                    // Get staff_id for child tables
                    $stmtStaffId = $pdo->prepare("SELECT id FROM staff WHERE service_number = ?");
                    $stmtStaffId->execute([$svcNo]);
                    $staffId = $stmtStaffId->fetchColumn();

                    // --- Operations ---
                    error_log("Processing operations for staff ID: " . $staffId);
                    $pdo->prepare("DELETE FROM staff_operations WHERE staff_id = ?")->execute([$staffId]);
                    $opStmt = $pdo->prepare("INSERT INTO staff_operations (staff_id, operation_id, role, start_date, end_date, performance_rating, remarks, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
                    foreach ($_POST['operations'] ?? [] as $index => $op) {
                        error_log("Processing operation " . ($index + 1) . ": " . json_encode($op));
                        if (!empty($op['operation_id'])) {
                            $opStmt->execute([
                                $staffId,
                                $op['operation_id'] ?? '',
                                $op['role'] ?? '',
                                $op['start_date'] ?? null,
                                $op['end_date'] ?? null,
                                $op['performance_rating'] ?? null,
                                $op['remarks'] ?? ''
                            ]);
                            error_log("Operation insert result: " . ($opStmt->rowCount() > 0 ? "Success" : "Failed"));
                        } else {
                            error_log("Skipping operation due to missing operation_id");
                        }
                    }

                    // --- Deployments ---
                    error_log("Processing deployments for staff ID: " . $staffId);
                    $pdo->prepare("DELETE FROM staff_deployments WHERE staff_id = ?")->execute([$staffId]);
                    $depStmt = $pdo->prepare("INSERT INTO staff_deployments (staff_id, deployment_name, mission_type, location, country, start_date, end_date, duration_months, deployment_status, rank_during_deployment, role_during_deployment, commanding_officer, deployment_allowance, notes, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())");
                    foreach ($_POST['deployments'] ?? [] as $index => $dep) {
                        error_log("Processing deployment " . ($index + 1) . ": " . json_encode($dep));
                        if (!empty($dep['deployment_name'])) {
                            $depStmt->execute([
                                $staffId,
                                $dep['deployment_name'] ?? '',
                                $dep['mission_type'] ?? '',
                                $dep['location'] ?? '',
                                $dep['country'] ?? '',
                                $dep['start_date'] ?? null,
                                $dep['end_date'] ?? null,
                                $dep['duration_months'] ?? null,
                                $dep['deployment_status'] ?? '',
                                $dep['rank_during_deployment'] ?? '',
                                $dep['role_during_deployment'] ?? '',
                                $dep['commanding_officer'] ?? '',
                                $dep['deployment_allowance'] ?? null,
                                $dep['notes'] ?? ''
                            ]);
                            error_log("Deployment insert result: " . ($depStmt->rowCount() > 0 ? "Success" : "Failed"));
                        } else {
                            error_log("Skipping deployment due to missing deployment_name");
                        }
                    }

                    // --- Education ---
                    error_log("Processing education for staff ID: " . $staffId);
                    $pdo->prepare("DELETE FROM staff_education WHERE staff_id = ?")->execute([$staffId]);
                    $eduStmt = $pdo->prepare("INSERT INTO staff_education (staff_id, institution, qualification, level, field_of_study, year_started, year_completed, grade_obtained, is_highest_qualification, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())");
                    foreach ($_POST['education'] ?? [] as $index => $edu) {
                        error_log("Processing education " . ($index + 1) . ": " . json_encode($edu));
                        if (!empty($edu['institution'])) {
                            $eduStmt->execute([
                                $staffId,
                                $edu['institution'] ?? '',
                                $edu['qualification'] ?? '',
                                $edu['level'] ?? '',
                                $edu['field_of_study'] ?? '',
                                $edu['year_started'] ?? null,
                                $edu['year_completed'] ?? null,
                                $edu['grade_obtained'] ?? '',
                                !empty($edu['is_highest_qualification']) ? 1 : 0
                            ]);
                            error_log("Education insert result: " . ($eduStmt->rowCount() > 0 ? "Success" : "Failed"));
                        } else {
                            error_log("Skipping education due to missing institution");
                        }
                    }
                    
                    // --- Skills ---
                    error_log("Processing skills for staff ID: " . $staffId);
                    if ($pdo->query("SHOW TABLES LIKE 'staff_skills'")->rowCount()) {
                        $pdo->prepare("DELETE FROM staff_skills WHERE staff_id = ?")->execute([$staffId]);
                        $skillStmt = $pdo->prepare("INSERT INTO staff_skills (staff_id, course_name, course_type, institution, start_date, end_date, duration_days, certificate_number, grade_obtained, location, cost, sponsored_by, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())");
                        foreach ($_POST['skills'] ?? [] as $index => $skill) {
                            error_log("Processing skill " . ($index + 1) . ": " . json_encode($skill));
                            if (!empty($skill['course_name'])) {
                                $skillStmt->execute([
                                    $staffId,
                                    $skill['course_name'] ?? '',
                                    $skill['course_type'] ?? '',
                                    $skill['institution'] ?? '',
                                    $skill['start_date'] ?? null,
                                    $skill['end_date'] ?? null,
                                    $skill['duration_days'] ?? null,
                                    $skill['certificate_number'] ?? '',
                                    $skill['grade_obtained'] ?? '',
                                    $skill['location'] ?? '',
                                    $skill['cost'] ?? null,
                                    $skill['sponsored_by'] ?? ''
                                ]);
                                error_log("Skill insert result: " . ($skillStmt->rowCount() > 0 ? "Success" : "Failed"));
                            } else {
                                error_log("Skipping skill due to missing course_name");
                            }
                        }
                    } else {
                        error_log("staff_skills table does not exist, skipping skills processing");
                    }

                    $pdo->commit();
                    $success = true;
                    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
                    $stmt = $pdo->prepare("SELECT * FROM staff WHERE service_number = ?");
                    $stmt->execute([$svcNo]);
                    $staff = $stmt->fetch(PDO::FETCH_OBJ);
                } catch (Exception $e) {
                    $pdo->rollBack();
                    error_log("Edit error by user {$user->data()->id}: " . $e->getMessage());
                    $errors[] = "Error updating staff record. Please try again or contact system administrator.";
                }
            }
        }
    }
}

// --- Staff statistics ---
function getStaffStatistics() {
    $pdo = getDbConnection();
    $stats = [];
    // Total staff count
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM staff");
    $stats['total'] = $stmt->fetchColumn();
    // Active staff count
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM staff WHERE svcStatus = 'Active'");
    $stats['active'] = $stmt->fetchColumn();
    // Staff by gender
    $stmt = $pdo->query("SELECT gender, COUNT(*) as count FROM staff GROUP BY gender");
    $stats['gender'] = [];
    foreach ($stmt->fetchAll(PDO::FETCH_OBJ) as $gender) {
        $stats['gender'][$gender->gender] = $gender->count;
    }
    // Staff by rank
    $stmt = $pdo->query("SELECT r.name as rankName, COUNT(s.service_number) as count FROM ranks r LEFT JOIN staff s ON r.id = s.rank_id GROUP BY r.id, r.name ORDER BY count DESC");
    $stats['ranks'] = $stmt->fetchAll(PDO::FETCH_OBJ);
    // Staff by unit
    $stmt = $pdo->query("SELECT u.name as unitName, COUNT(s.service_number) as count FROM units u LEFT JOIN staff s ON u.id = s.unit_id GROUP BY u.id, u.name ORDER BY count DESC");
    $stats['units'] = $stmt->fetchAll(PDO::FETCH_OBJ);
    return $stats;
}

$staffStats = getStaffStatistics();

// --- Recent Activity Function ---
function getRecentActivity($limit = 10) {
    $pdo = getDbConnection();
    try {
        $stmt = $pdo->prepare("
            SELECT 
                sel.service_number as svcNo,
                s.first_name as fname,
                s.last_name as lname,
                sel.edited_by,
                sel.edited_at,
                sel.changes,
                u.username as edited_by_username
            FROM staff_edit_log sel
            LEFT JOIN staff s ON sel.service_number = s.service_number
            LEFT JOIN users u ON sel.edited_by = u.id
            ORDER BY sel.edited_at DESC
            LIMIT ?
        ");
        $stmt->execute([$limit]);
        return $stmt->fetchAll(PDO::FETCH_OBJ);
    } catch (Exception $e) {
        error_log("Error fetching recent activity: " . $e->getMessage());
        return [];
    }
}

$recentActivity = getRecentActivity(5);

// Log page access with enhanced analytics
logActivity('edit_staff_access', 'Accessed Edit Staff page');

// Ensure shared admin branch CSS is loaded
echo '<link rel="stylesheet" href="/Armis2/assets/css/admin_branch.css">';
include dirname(__DIR__) . '/shared/header.php';
include dirname(__DIR__) . '/shared/sidebar.php';

// --- Load initial data for dynamic sections ---
$staffOperations = [];
$staffDeployments = [];
$staffEducation = [];
$staffSkills = [];

if (!empty($staff)) {
    error_log("Loading dynamic data for staff ID: " . $staff->id);
    
    // Load operations
    $stmt = $pdo->prepare("SELECT * FROM staff_operations WHERE staff_id = ?");
    $stmt->execute([$staff->id]);
    $staffOperations = $stmt->fetchAll(PDO::FETCH_ASSOC);
    error_log("Loaded operations: " . count($staffOperations));
    error_log("Operations data: " . json_encode($staffOperations));

    // Load deployments
    $stmt = $pdo->prepare("SELECT * FROM staff_deployments WHERE staff_id = ?");
    $stmt->execute([$staff->id]);
    $staffDeployments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    error_log("Loaded deployments: " . count($staffDeployments));
    error_log("Deployments data: " . json_encode($staffDeployments));

    // Load education
    $stmt = $pdo->prepare("SELECT * FROM staff_education WHERE staff_id = ?");
    $stmt->execute([$staff->id]);
    $staffEducation = $stmt->fetchAll(PDO::FETCH_ASSOC);
    error_log("Loaded education: " . count($staffEducation));
    error_log("Education data: " . json_encode($staffEducation));

    // Load skills/courses if you have such table, e.g. staff_skills
    if ($pdo->query("SHOW TABLES LIKE 'staff_skills'")->rowCount()) {
        error_log("Staff skills table exists, loading skills");
        $stmt = $pdo->prepare("SELECT * FROM staff_skills WHERE staff_id = ?");
        $stmt->execute([$staff->id]);
        $staffSkills = $stmt->fetchAll(PDO::FETCH_ASSOC);
        error_log("Loaded skills: " . count($staffSkills));
        error_log("Skills data: " . json_encode($staffSkills));
    } else {
        error_log("Staff skills table does not exist");
    }
}

?>

<!-- Dynamically load initial data for dynamic sections -->
<script>
window.operationsOptions = <?php
    // You must provide the operations/codes from your DB if needed
    $ops = $pdo->query("SELECT id, name, code FROM operations ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($ops);
?>;

// Pre-populate dynamic sections if editing
document.addEventListener('DOMContentLoaded', function() {
    console.log('DOM content loaded, initializing dynamic sections');
    
    // Operations
    <?php if (!empty($staffOperations)): ?>
        console.log('Initializing operations: <?=count($staffOperations)?>');
        <?php foreach ($staffOperations as $op): ?>
            console.log('Adding operation: <?=json_encode($op)?>');
            addOperationRow(<?=json_encode($op)?>);
        <?php endforeach; ?>
    <?php else: ?>
        console.log('No operations to initialize');
    <?php endif; ?>

    // Deployments
    <?php if (!empty($staffDeployments)): ?>
        console.log('Initializing deployments: <?=count($staffDeployments)?>');
        <?php foreach ($staffDeployments as $dep): ?>
            console.log('Adding deployment: <?=json_encode($dep)?>');
            addDeploymentRow(<?=json_encode($dep)?>);
        <?php endforeach; ?>
    <?php else: ?>
        console.log('No deployments to initialize');
    <?php endif; ?>

    // Education
    <?php if (!empty($staffEducation)): ?>
        console.log('Initializing education: <?=count($staffEducation)?>');
        <?php foreach ($staffEducation as $edu): ?>
            console.log('Adding education: <?=json_encode($edu)?>');
            addEducationRow(<?=json_encode($edu)?>);
        <?php endforeach; ?>
    <?php else: ?>
        console.log('No education to initialize');
    <?php endif; ?>

    // Skills/Courses
    <?php if (!empty($staffSkills)): ?>
        console.log('Initializing skills: <?=count($staffSkills)?>');
        <?php foreach ($staffSkills as $skill): ?>
            console.log('Adding skill: <?=json_encode($skill)?>');
            addSkillRow(<?=json_encode($skill)?>);
        <?php endforeach; ?>
    <?php else: ?>
        console.log('No skills to initialize');
    <?php endif; ?>
});
</script>

<!-- Load custom CSS and assets -->
<link rel="stylesheet" href="/Armis2/assets/css/admin_branch.css">
<link rel="stylesheet" href="/Armis2/assets/css/custom-icons.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.3/css/bootstrap.min.css">
<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.3/js/bootstrap.bundle.min.js"></script>
<script src="/Armis2/assets/js/edit_staff_support.js"></script>
<div class="content-wrapper with-sidebar">
    <div class="container-fluid">
        <div class="main-content">
            <div class="row">
                <div class="col-12">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h1 class="section-title">
                            <i class="fas fa-user-edit"></i> Edit Staff Member
                        </h1>
                        <div>
                            <a href="/Armis2/admin_branch/edit_staff.php" class="btn btn-outline-secondary">
                                <i class="fas fa-arrow-left"></i> Back to Search
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="col-12">
                    <div class="card shadow-sm">
                        <div class="card-header bg-primary text-white">
                            <h4 class="mb-0"><i class="fa fa-user-edit"></i> Edit Staff Member</h4>
                        </div>
        <div class="card-body">
            <?php if ($success): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fa fa-check-circle"></i> 
                    <strong>Success!</strong> Staff member information has been updated successfully.
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>
            
            <?php if ($errors): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fa fa-exclamation-triangle"></i> 
                    <strong>Please correct the following errors:</strong>
                    <ul class="mb-0 mt-2">
                        <?php foreach ($errors as $err): ?>
                            <li><?=htmlspecialchars($err)?></li>
                        <?php endforeach; ?>
                    </ul>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <?php if (!$staff && isset($_GET['svcNo'])): ?>
                <div class="alert alert-warning alert-dismissible fade show" role="alert">
                    <i class="fa fa-exclamation-triangle"></i> 
                    <strong>Staff member not found.</strong> The requested staff member could not be found.
                    <a href="edit_staff.php" class="btn btn-sm btn-outline-primary ms-2">
                        <i class="fa fa-search"></i> Search Again
                    </a>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <?php if (!$staff): ?>
                <!-- Staff Management Intro -->
                <div class="alert alert-info d-flex align-items-center mb-3" role="alert">
                    <i class="fas fa-info-circle me-2"></i>
                    <span>
                        Search by service number, name, rank, unit, or status. Use filters for more specific results. Click on a staff member to edit their details.
                    </span>
                    <button type="button" class="btn btn-sm btn-outline-info ms-auto" data-bs-toggle="modal" data-bs-target="#helpModal" title="Show Help"><i class="fa fa-info-circle"></i> Help</button>
                </div>
                <div class="modal fade" id="helpModal" tabindex="-1" aria-labelledby="helpModalLabel" aria-hidden="true">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title" id="helpModalLabel">Staff Management Help</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body">
                                <ul>
                                    <li>Use the search box to find staff by service number, name, etc.</li>
                                    <li>Apply filters to narrow down results by rank, unit, or status.</li>
                                    <li>Click on a staff member to edit their details.</li>
                                    <li>All actions are logged for audit purposes.</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
                
<!-- Action Buttons -->
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <a href="?export=csv" class="btn btn-outline-success">
                            <i class="fa fa-download"></i> Export to CSV
                        </a>
                        <button id="print-staff-list" class="btn btn-outline-secondary">
                            <i class="fa fa-print"></i> Print
                        </button>
                        <button id="customize-columns" class="btn btn-outline-primary ms-1" data-bs-toggle="modal" data-bs-target="#columnsModal">
                            <i class="fa fa-columns"></i> Customize Columns
                        </button>
                    </div>
                    <div>
                        <a href="create_staff.php" class="btn btn-success">
                            <i class="fa fa-plus"></i> Add New Staff
                        </a>
                    </div>
                </div>

                <!-- Columns Customization Modal -->
                <div class="modal fade" id="columnsModal" tabindex="-1" aria-labelledby="columnsModalLabel" aria-hidden="true">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title" id="columnsModalLabel">Customize Visible Columns</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body">
                                <div class="form-check mb-2">
                                    <input class="form-check-input toggle-col" type="checkbox" value="" id="col-service-no" data-col="service-no" checked>
                                    <label class="form-check-label" for="col-service-no">
                                        Service Number
                                    </label>
                                </div>
                                <div class="form-check mb-2">
                                    <input class="form-check-input toggle-col" type="checkbox" value="" id="col-name" data-col="name" checked>
                                    <label class="form-check-label" for="col-name">
                                        Name
                                    </label>
                                </div>
                                <div class="form-check mb-2">
                                    <input class="form-check-input toggle-col" type="checkbox" value="" id="col-rank" data-col="rank" checked>
                                    <label class="form-check-label" for="col-rank">
                                        Rank
                                    </label>
                                </div>
                                <div class="form-check mb-2">
                                    <input class="form-check-input toggle-col" type="checkbox" value="" id="col-unit" data-col="unit" checked>
                                    <label class="form-check-label" for="col-unit">
                                        Unit
                                    </label>
                                </div>
                                <div class="form-check mb-2">
                                    <input class="form-check-input toggle-col" type="checkbox" value="" id="col-status" data-col="status" checked>
                                    <label class="form-check-label" for="col-status">
                                        Status
                                    </label>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Live Search Form -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h6 class="mb-0">Search & Filter Staff</h6>
                    </div>
                    <div class="card-body">
                        <form id="searchForm" aria-label="Search Staff" autocomplete="off" onsubmit="return false;">
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <input type="text" name="search" id="searchStaff" class="form-control" 
                                           placeholder="Search by Service No, Name..." autocomplete="off">
                                </div>
                                <div class="col-md-2">
                                    <select class="form-select" id="filterRank" name="filterRank">
                                        <option value="">Rank</option>
                                        <?php foreach ($ranks as $rank): ?>
                                            <option value="<?=htmlspecialchars($rank->rankID)?>">
                                                <?=htmlspecialchars($rank->rankName)?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <select class="form-select" id="filterUnit" name="filterUnit">
                                        <option value="">Unit</option>
                                        <?php foreach ($units as $unit): ?>
                                            <option value="<?=htmlspecialchars($unit->unitID)?>">
                                                <?=htmlspecialchars($unit->unitName)?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <select class="form-select" id="filterStatus" name="filterStatus">
                                        <option value="">Status</option>
                                        <?php foreach (VALID_STATUSES as $status): ?>
                                            <option value="<?=htmlspecialchars($status)?>">
                                                <?=htmlspecialchars($status)?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <button class="btn btn-outline-secondary w-100" type="button" onclick="clearFilters()">
                                        <i class="fa fa-times"></i> Clear
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Recent Activity -->
                <?php if (!empty($recentActivity)): ?>
                <div class="card mb-4">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h6 class="mb-0">Recent Staff Updates</h6>
                        <small class="text-muted">Last 5 activities</small>
                    </div>
                    <div class="card-body">
                        <div class="timeline">
                            <?php foreach ($recentActivity as $activity): ?>
                                <div class="timeline-item">
                                    <div class="timeline-marker"></div>
                                    <div class="timeline-content">
                                        <h6 class="mb-1">
                                            <?=htmlspecialchars($activity->first_name . ' ' . $activity->last_name)?>
                                            <small class="text-muted">(<?=htmlspecialchars($activity->service_number)?>)</small>
                                        </h6>
                                        <p class="mb-1">
                                            Updated by: <?=htmlspecialchars($activity->edited_by_username ?? 'Unknown')?>
                                        </p>
                                        <small class="text-muted">
                                            <?=date('M j, Y g:i A', strtotime($activity->edited_at))?>
                                        </small>
                                        <?php if ($activity->changes): ?>
                                            <div class="mt-2">
                                                <?php 
                                                $changes = json_decode($activity->changes, true);
                                                if ($changes): ?>
                                                    <small class="text-info">
                                                        Changes: <?=count($changes)?> field(s) updated
                                                    </small>
                                                <?php endif; ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- Results Table -->
                <div class="table-responsive print-friendly">
                    <table class="table table-striped table-hover align-middle" id="staffResultsTable">
                        <thead class="table-primary">
                            <tr>
                                <th scope="col" role="button" tabindex="0" aria-label="Sort by Service No">Service No</th>
                                <th scope="col" role="button" tabindex="0" aria-label="Sort by Name">Name</th>
                                <th scope="col" role="button" tabindex="0" aria-label="Sort by Rank">Rank</th>
                                <th scope="col" role="button" tabindex="0" aria-label="Sort by Unit">Unit</th>
                                <th scope="col" role="button" tabindex="0" aria-label="Sort by Status">Status</th>
                                <th scope="col">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr><td colspan="6" class="text-center text-muted">Enter search criteria to find staff members</td></tr>
                        </tbody>
                    </table>
                </div>
                <nav>
                    <ul class="pagination justify-content-end" id="pagination"></ul>
                </nav>
                <script>
                const searchInput = document.getElementById('searchStaff');
                const resultsTable = document.getElementById('staffResultsTable').getElementsByTagName('tbody')[0];
                const filterRank = document.getElementById('filterRank');
                const filterUnit = document.getElementById('filterUnit');
                const filterStatus = document.getElementById('filterStatus');
                
                let typingTimer;
                let lastSearchTerm = '';
                let isSearching = false;
                
                function showSearchLoading() {
                    resultsTable.innerHTML = `
                        <tr>
                            <td colspan="5" class="text-center text-muted">
                                <i class="fa fa-spinner fa-spin"></i> Searching staff...
                            </td>
                        </tr>`;
                }
                
                function buildSearchQuery() {
                    const params = new URLSearchParams();
                    params.append('ajax', '1');
                    params.append('search', searchInput.value.trim());
                    
                    if (filterRank.value) {
                        params.append('rank', filterRank.value);
                        filterRank.classList.add('filter-active');
                    } else {
                        filterRank.classList.remove('filter-active');
                    }
                    
                    if (filterUnit.value) {
                        params.append('unit', filterUnit.value);
                        filterUnit.classList.add('filter-active');
                    } else {
                        filterUnit.classList.remove('filter-active');
                    }
                    
                    if (filterStatus.value) {
                        params.append('status', filterStatus.value);
                        filterStatus.classList.add('filter-active');
                    } else {
                        filterStatus.classList.remove('filter-active');
                    }
                    
                    return params.toString();
                }
                
                function escapeHtml(unsafe) {
                    return unsafe
                        .replace(/&/g, "&amp;")
                        .replace(/</g, "&lt;")
                        .replace(/>/g, "&gt;")
                        .replace(/"/g, "&quot;")
                        .replace(/'/g, "&#039;");
                }
                
                // Current page and items per page
                let currentPage = 1;
                const itemsPerPage = 25;
                let totalItems = 0;
                let allResults = [];
                
                function renderPagination() {
                    const pagination = document.getElementById('pagination');
                    pagination.innerHTML = '';
                    
                    if (totalItems <= itemsPerPage) return;
                    
                    const totalPages = Math.ceil(totalItems / itemsPerPage);
                    
                    // Previous button
                    const prevLi = document.createElement('li');
                    prevLi.className = `page-item ${currentPage === 1 ? 'disabled' : ''}`;
                    const prevLink = document.createElement('a');
                    prevLink.className = 'page-link';
                    prevLink.href = '#';
                    prevLink.innerHTML = '&laquo;';
                    prevLink.setAttribute('aria-label', 'Previous');
                    prevLink.addEventListener('click', (e) => {
                        e.preventDefault();
                        if (currentPage > 1) {
                            currentPage--;
                            renderTable(allResults);
                        }
                    });
                    prevLi.appendChild(prevLink);
                    pagination.appendChild(prevLi);
                    
                    // Page numbers
                    const maxLinks = 5;
                    let startPage = Math.max(1, currentPage - Math.floor(maxLinks / 2));
                    let endPage = Math.min(totalPages, startPage + maxLinks - 1);
                    
                    if (endPage - startPage + 1 < maxLinks) {
                        startPage = Math.max(1, endPage - maxLinks + 1);
                    }
                    
                    for (let i = startPage; i <= endPage; i++) {
                        const pageLi = document.createElement('li');
                        pageLi.className = `page-item ${i === currentPage ? 'active' : ''}`;
                        const pageLink = document.createElement('a');
                        pageLink.className = 'page-link';
                        pageLink.href = '#';
                        pageLink.textContent = i;
                        pageLink.addEventListener('click', (e) => {
                            e.preventDefault();
                            currentPage = i;
                            renderTable(allResults);
                        });
                        pageLi.appendChild(pageLink);
                        pagination.appendChild(pageLi);
                    }
                    
                    // Next button
                    const nextLi = document.createElement('li');
                    nextLi.className = `page-item ${currentPage === totalPages ? 'disabled' : ''}`;
                    const nextLink = document.createElement('a');
                    nextLink.className = 'page-link';
                    nextLink.href = '#';
                    nextLink.innerHTML = '&raquo;';
                    nextLink.setAttribute('aria-label', 'Next');
                    nextLink.addEventListener('click', (e) => {
                        e.preventDefault();
                        if (currentPage < totalPages) {
                            currentPage++;
                            renderTable(allResults);
                        }
                    });
                    nextLi.appendChild(nextLink);
                    pagination.appendChild(nextLi);
                }
                
                function renderTable(data) {
                    resultsTable.innerHTML = '';
                    
                    if (data.length === 0) {
                        resultsTable.innerHTML = `
                            <tr>
                                <td colspan="6" class="text-center text-muted">
                                    <i class="fa fa-search"></i> No staff members found.
                                    ${searchInput.value.trim() ? ' Try different search terms or filters.' : ''}
                                </td>
                            </tr>`;
                        return;
                    }
                    
                    // Calculate pagination
                    const start = (currentPage - 1) * itemsPerPage;
                    const end = Math.min(start + itemsPerPage, data.length);
                    const currentPageData = data.slice(start, end);
                    
                    // Render table rows
                    currentPageData.forEach(function(staff) {
                        const tr = document.createElement('tr');
                        tr.ondblclick = function() {
                            // Log audit view action
                            fetch('?ajax=1&audit_view=1&staff_id=' + encodeURIComponent(staff.id));
                            // Redirect to view page
                            window.location.href = 'view_staff.php?id=' + encodeURIComponent(staff.id);
                        };
                        
                        tr.innerHTML =
                            '<td class="fw-bold">' + escapeHtml(staff.svcNo) + '</td>' +
                            '<td>' + escapeHtml(staff.name) + '</td>' +
                            '<td><span class="badge bg-secondary">' + escapeHtml(staff.rank) + '</span></td>' +
                            '<td>' + escapeHtml(staff.unit) + '</td>' +
                            '<td>' + 
                                '<span class="badge ' + 
                                (staff.status === 'Active' ? 'bg-success' : 
                                 staff.status === 'Inactive' ? 'bg-warning' : 
                                 staff.status === 'Retired' ? 'bg-secondary' : 'bg-danger') + 
                                '">' + escapeHtml(staff.status) + '</span>' +
                            '</td>' +
                            '<td>' +
                                '<a href="?svcNo=' + encodeURIComponent(staff.svcNo) + '" class="btn btn-primary btn-sm me-1" title="Edit ' + escapeHtml(staff.name) + '">' +
                                    '<i class="fa fa-edit"></i> Edit' +
                                '</a>' +
                                '<a href="view_staff.php?id=' + encodeURIComponent(staff.id) + '" class="btn btn-info btn-sm" title="View ' + escapeHtml(staff.name) + '">' +
                                    '<i class="fa fa-eye"></i> View' +
                                '</a>' +
                            '</td>';
                        resultsTable.appendChild(tr);
                    });
                    
                    // Add pagination info row
                    const countRow = document.createElement('tr');
                    countRow.className = 'table-info';
                    countRow.innerHTML = `
                        <td colspan="6" class="text-center">
                            <small><i class="fa fa-info-circle"></i> Showing ${start + 1} to ${end} of ${data.length} staff members</small>
                        </td>`;
                    resultsTable.appendChild(countRow);
                    
                    // Update pagination controls
                    renderPagination();
                }
                
                function fetchResults() {
                    const queryString = buildSearchQuery();
                    
                    // Add parameter to exclude retired and deceased staff
                    const url = new URL(window.location.origin + window.location.pathname + '?' + queryString);
                    url.searchParams.append('exclude_inactive', '1');
                    
                    // Avoid duplicate searches
                    if (url.toString() === lastSearchTerm || isSearching) {
                        return;
                    }
                    
                    lastSearchTerm = url.toString();
                    isSearching = true;
                    showSearchLoading();
                    
                    fetch(url)
                        .then(response => {
                            if (!response.ok) {
                                throw new Error('Network response was not ok');
                            }
                            return response.json();
                        })
                        .then(data => {
                            isSearching = false;
                            
                            if (!Array.isArray(data)) {
                                throw new Error('Invalid response format');
                            }
                            
                            // Log search action for audit trail
                            const searchParams = {
                                search: searchInput.value.trim(),
                                rank: filterRank.value,
                                unit: filterUnit.value,
                                status: filterStatus.value,
                                action: 'staff_search'
                            };
                            
                            fetch('?ajax=1&log_search=1', {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json',
                                },
                                body: JSON.stringify(searchParams)
                            });
                            
                            // Store all results for pagination
                            allResults = data;
                            totalItems = data.length;
                            currentPage = 1; // Reset to first page
                            
                            // Render the table with the current page of results
                            renderTable(data);
                        })
                        .catch(error => {
                            isSearching = false;
                            console.error('Search error:', error);
                            resultsTable.innerHTML = `
                                <tr>
                                    <td colspan="6" class="text-center text-danger">
                                        <i class="fa fa-exclamation-triangle"></i> Error searching staff: ${error.message}
                                        <br><small>Please try again or contact support if the problem persists.</small>
                                    </td>
                                </tr>`;
                        });
                }
                
                function clearFilters() {
                    searchInput.value = '';
                    filterRank.value = '';
                    filterUnit.value = '';
                    filterStatus.value = '';
                    [filterRank, filterUnit, filterStatus].forEach(el => el.classList.remove('filter-active'));
                    fetchResults();
                }
                
                // Initialize search
                fetchResults();
                
                // Event listeners
                searchInput.addEventListener('input', function() {
                    clearTimeout(typingTimer);
                    typingTimer = setTimeout(fetchResults, 300);
                });
                
                filterRank.addEventListener('change', fetchResults);
                filterUnit.addEventListener('change', fetchResults);
                filterStatus.addEventListener('change', fetchResults);
                
                // Search on Enter key
                searchInput.addEventListener('keypress', function(e) {
                    if (e.key === 'Enter') {
                        clearTimeout(typingTimer);
                        fetchResults();
                    }
                });
                
                // Keyboard shortcuts
                document.addEventListener('keydown', function(e) {
                    if (e.ctrlKey && e.key === 'f') {
                        e.preventDefault();
                        searchInput.focus();
                    }
                });
                
                // Print functionality
                document.getElementById('print-staff-list').addEventListener('click', function() {
                    window.print();
                });
                
                // Column visibility toggle
                document.querySelectorAll('.toggle-col').forEach(function(checkbox) {
                    const colName = checkbox.dataset.col;
                    
                    // Check local storage for saved preferences
                    const savedState = localStorage.getItem('staff-col-' + colName);
                    if (savedState !== null) {
                        checkbox.checked = savedState === 'true';
                        toggleColumnVisibility(colName, checkbox.checked);
                    }
                    
                    checkbox.addEventListener('change', function() {
                        toggleColumnVisibility(colName, this.checked);
                        localStorage.setItem('staff-col-' + colName, this.checked);
                    });
                });
                
                function toggleColumnVisibility(colName, isVisible) {
                    const table = document.getElementById('staffResultsTable');
                    const headerCells = table.querySelectorAll('thead th');
                    const dataCells = table.querySelectorAll('tbody td');
                    
                    let columnIndex = -1;
                    for (let i = 0; i < headerCells.length; i++) {
                        if (headerCells[i].textContent.trim().toLowerCase().includes(colName.replace('-', ' '))) {
                            columnIndex = i;
                            break;
                        }
                    }
                    
                    if (columnIndex >= 0) {
                        // Toggle header
                        headerCells[columnIndex].style.display = isVisible ? '' : 'none';
                        
                        // Toggle data cells
                        const rows = table.querySelectorAll('tbody tr');
                        rows.forEach(row => {
                            const cells = row.querySelectorAll('td');
                            if (cells.length > columnIndex) {
                                cells[columnIndex].style.display = isVisible ? '' : 'none';
                            }
                        });
                    }
                }
                </script>
                <style>
                    @media print {
                        body * {
                            visibility: hidden;
                        }
                        .table-responsive, .table-responsive * {
                            visibility: visible;
                        }
                        .table-responsive {
                            position: absolute;
                            left: 0;
                            top: 0;
                            width: 100%;
                        }
                        .pagination, .action-btn, button, .btn {
                            display: none !important;
                        }
                    }
                    /* Timeline styling */
                    .timeline {
                        position: relative;
                        padding-left: 30px;
                    }
                    .timeline-item {
                        position: relative;
                        margin-bottom: 20px;
                    }
                    .timeline-marker {
                        position: absolute;
                        left: -30px;
                        top: 0;
                        width: 15px;
                        height: 15px;
                        border-radius: 50%;
                        background-color: #4e73df;
                        box-shadow: 0 0 0 3px rgba(78, 115, 223, 0.1);
                    }
                    .timeline-content {
                        padding-bottom: 15px;
                        border-bottom: 1px solid #e3e6f0;
                    }
                    
                    /* Filter active indicator */
                    .filter-active {
                        border-color: #4e73df;
                        box-shadow: 0 0 0 0.2rem rgba(78, 115, 223, 0.25);
                    }
                    
                    /* Staff table enhancements */
                    #staffResultsTable tbody tr {
                        cursor: pointer;
                        transition: background-color 0.15s ease;
                    }
                    #staffResultsTable tbody tr:hover {
                        background-color: rgba(78, 115, 223, 0.05);
                    }
                </style>
            <?php else: ?>
                <form method="post" id="editStaffForm" autocomplete="off" aria-label="Edit Staff Member" enctype="multipart/form-data">
                    <input type="hidden" name="edit_staff" value="1">
                    <input type="hidden" name="svcNo" value="<?=htmlspecialchars($staff->service_number ?? '')?>">
                    <input type="hidden" name="csrf_token" value="<?=csrf_token()?>">
                    
                    <?php if (!empty($_SESSION['is_admin']) && $_SESSION['is_admin']): ?>
                    <!-- Debug information for administrators -->
                    <div class="card mb-4 border-danger">
                        <div class="card-header bg-danger text-white">
                            <h5 class="mb-0">
                                <i class="fa fa-bug"></i> Debug Information (Admin Only)
                                <button class="btn btn-sm btn-light float-end" type="button" 
                                        onclick="document.getElementById('debugInfo').classList.toggle('d-none')">
                                    Toggle Debug Info
                                </button>
                            </h5>
                        </div>
                        <div class="card-body d-none" id="debugInfo">
                            <div class="alert alert-info">
                                <p><strong>Form ID:</strong> <?=htmlspecialchars('editStaffForm')?></p>
                                <p><strong>Service Number:</strong> <?=htmlspecialchars($staff->service_number ?? 'Not set')?></p>
                                <p><strong>Staff ID:</strong> <?=htmlspecialchars($staff->id ?? 'Not set')?></p>
                                <p><strong>CSRF Token:</strong> <?=htmlspecialchars(substr(csrf_token(), 0, 10))?>...</p>
                                <p><strong>Dynamic Data:</strong></p>
                                <ul>
                                    <li>Operations: <?=!empty($staffOperations) ? count($staffOperations) : 0?> records</li>
                                    <li>Deployments: <?=!empty($staffDeployments) ? count($staffDeployments) : 0?> records</li>
                                    <li>Education: <?=!empty($staffEducation) ? count($staffEducation) : 0?> records</li>
                                    <li>Skills: <?=!empty($staffSkills) ? count($staffSkills) : 0?> records</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Form Steps Navigation -->
                    <div class="form-stepper mb-4">
                        <div class="stepper-row">
                            <div class="step active" data-step="1">
                                <div class="step-icon"><i class="fa fa-user"></i></div>
                                <div class="step-label">Personal Info</div>
                            </div>
                            <div class="step" data-step="2">
                                <div class="step-icon"><i class="fa fa-shield-alt"></i></div>
                                <div class="step-label">Military Details</div>
                            </div>
                            <div class="step" data-step="3">
                                <div class="step-icon"><i class="fa fa-tasks"></i></div>
                                <div class="step-label">Operations</div>
                            </div>
                            <div class="step" data-step="4">
                                <div class="step-icon"><i class="fa fa-plane"></i></div>
                                <div class="step-label">Deployments</div>
                            </div>
                            <div class="step" data-step="5">
                                <div class="step-icon"><i class="fa fa-graduation-cap"></i></div>
                                <div class="step-label">Education & Skills</div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Alert Container for Validation Messages -->
                    <div id="alertContainer">
                        <?php if (!empty($errors)): ?>
                        <div class="alert alert-danger mb-4">
                            <h5><i class="fa fa-exclamation-triangle"></i> Please fix the following errors:</h5>
                            <ul class="mb-0">
                                <?php foreach ($errors as $error): ?>
                                    <li><?= htmlspecialchars($error) ?></li>
                                <?php endforeach; ?>
                            </ul>
                            <hr>
                            <p class="mb-0">Fields with errors have been highlighted below.</p>
                        </div>
                        <?php endif; ?>
                    </div>

                    <!-- Personal Details - Step 1 -->
                    <div class="form-step active" id="step1">
                        <div class="card mb-4">
                            <div class="card-header bg-primary text-white">
                                <h5 class="mb-0"><i class="fa fa-user"></i> Personal Details</h5>
                            </div>
                            <div class="card-body">
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label for="fname" class="form-label required-field">First Name</label>
                                        <input type="text" class="form-control <?= isset($validationErrors['fname']) ? 'is-invalid-field' : '' ?>" name="fname" id="fname"
                                            value="<?=htmlspecialchars($staff->first_name ?? '')?>" required maxlength="<?=MAX_NAME_LENGTH?>" minlength="<?=MIN_NAME_LENGTH?>"
                                            pattern="[a-zA-Z\s\-'\.]{<?=MIN_NAME_LENGTH?>,<?=MAX_NAME_LENGTH?>}" aria-describedby="fname-help">
                                        <small id="fname-help" class="form-text text-muted"><?=MIN_NAME_LENGTH?>-<?=MAX_NAME_LENGTH?> characters, letters only</small>
                                        <div class="invalid-feedback" id="fname-error"><?= $validationErrors['fname'] ?? '' ?></div>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="lname" class="form-label required-field">Last Name</label>
                                        <input type="text" class="form-control <?= isset($validationErrors['lname']) ? 'is-invalid-field' : '' ?>" name="lname" id="lname"
                                            value="<?=htmlspecialchars($staff->last_name ?? '')?>" required maxlength="<?=MAX_NAME_LENGTH?>" minlength="<?=MIN_NAME_LENGTH?>"
                                            pattern="[a-zA-Z\s\-'\.]{<?=MIN_NAME_LENGTH?>,<?=MAX_NAME_LENGTH?>}" aria-describedby="lname-help">
                                        <small id="lname-help" class="form-text text-muted"><?=MIN_NAME_LENGTH?>-<?=MAX_NAME_LENGTH?> characters, letters only</small>
                                        <div class="invalid-feedback" id="lname-error"><?= $validationErrors['lname'] ?? '' ?></div>
                                    </div>
                                </div>

                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label for="rankID" class="form-label required-field">Rank</label>
                                        <select class="form-select <?= isset($validationErrors['rankID']) ? 'is-invalid-field' : '' ?>" name="rankID" id="rankID" required aria-describedby="rankID-help">
                                            <option value="">Select Rank</option>
                                            <?php foreach ($ranks as $rank): ?>
                                                <option value="<?=htmlspecialchars($rank->rankID)?>" <?=(!empty($staff) && $staff->rank_id == $rank->rankID) ? 'selected' : ''?>>
                                                    <?=htmlspecialchars($rank->rankName)?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <div class="invalid-feedback" id="rankID-error"><?= $validationErrors['rankID'] ?? '' ?></div>
                                    </div>
                                    </select>
                                    <small id="rankID-help" class="form-text text-muted">
                                        Current: <strong><?=htmlspecialchars(!empty($staff) ? ($rankMap[$staff->rank_id] ?? 'Unknown') : 'Unknown')?></strong>
                                    </small>
                                    <div class="invalid-feedback" id="rankID-error"></div>
                                </div>
                                <div class="col-md-6">
                                    <label for="unitID" class="form-label required-field">Unit</label>
                                    <select class="form-select" name="unitID" id="unitID" required aria-describedby="unitID-help">
                                        <option value="">Select Unit</option>
                                        <?php foreach ($units as $unit): ?>
                                            <option value="<?=htmlspecialchars($unit->unitID)?>" <?=(!empty($staff) && $staff->unit_id == $unit->unitID) ? 'selected' : ''?>>
                                                <?=htmlspecialchars($unit->unitName)?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <small id="unitID-help" class="form-text text-muted">
                                        Current: <strong><?=htmlspecialchars(!empty($staff) ? ($unitMap[$staff->unit_id] ?? 'Unknown') : 'Unknown')?></strong>
                                    </small>
                                    <div class="invalid-feedback" id="unitID-error"></div>
                                </div>
                            </div>

                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="corps" class="form-label">Corps <span class="text-muted">(Optional)</span></label>
                                    <select class="form-select" name="corps" id="corps">
                                        <option value="">Select Corps</option>
                                        <?php foreach ($corps as $corpsItem): ?>
                                            <option value="<?=htmlspecialchars($corpsItem->id)?>" <?=(!empty($staff) && isset($staff->corps_id) && $staff->corps_id == $corpsItem->id) ? 'selected' : ''?>>
                                                <?=htmlspecialchars($corpsItem->name)?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label for="NRC" class="form-label">NRC <span class="text-muted">(Optional)</span></label>
                                    <input type="text" class="form-control" name="NRC" id="NRC"
                                        value="<?=htmlspecialchars($staff->NRC ?? '')?>" maxlength="<?=MAX_NRC_LENGTH?>">
                                    <small id="NRC-help" class="form-text text-muted">National Registration Card number</small>
                                    <div class="invalid-feedback" id="NRC-error"></div>
                                </div>
                            </div>

                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="DOB" class="form-label">Date of Birth <span class="text-muted">(Optional)</span></label>
                                    <input type="date" class="form-control" name="DOB" id="DOB"
                                        value="<?=htmlspecialchars($staff->DOB ?? '')?>"
                                        min="<?=date('Y-m-d', strtotime('-' . MAX_AGE_YEARS . ' years'))?>"
                                        max="<?=date('Y-m-d', strtotime('-' . MIN_AGE_YEARS . ' years'))?>">
                                    <small id="DOB-help" class="form-text text-muted">
                                        Age must be between <?=MIN_AGE_YEARS?> and <?=MAX_AGE_YEARS?> years
                                    </small>
                                    <div class="invalid-feedback" id="DOB-error"></div>
                                </div>
                                <div class="col-md-6">
                                    <label for="gender" class="form-label required-field">Gender</label>
                                    <select class="form-select" name="gender" id="gender" required aria-describedby="gender-help">
                                        <option value="">Select Gender</option>
                                        <?php foreach (VALID_GENDERS as $g): ?>
                                            <option value="<?=htmlspecialchars($g)?>" <?=(!empty($staff) && $staff->gender == $g) ? 'selected' : ''?>><?=htmlspecialchars($g)?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <small id="gender-help" class="form-text text-muted">Select gender</small>
                                    <div class="invalid-feedback" id="gender-error"></div>
                                </div>
                            </div>

                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="svcStatus" class="form-label required-field">Service Status</label>
                                    <select class="form-select" name="svcStatus" id="svcStatus" required aria-describedby="svcStatus-help">
                                        <option value="">Select Status</option>
                                        <?php foreach (VALID_STATUSES as $status): ?>
                                            <option value="<?=htmlspecialchars($status)?>" <?=(!empty($staff) && $staff->svcStatus == $status) ? 'selected' : ''?>><?=htmlspecialchars($status)?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <small id="svcStatus-help" class="form-text text-muted">Current service status</small>
                                    <div class="invalid-feedback" id="svcStatus-error"></div>
                                </div>
                                <div class="col-md-6">
                                    <label for="tel" class="form-label">Telephone <span class="text-muted">(Optional)</span></label>
                                    <input type="text" class="form-control" name="tel" id="tel"
                                        value="<?=htmlspecialchars($staff->tel ?? '')?>">
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Note about User Editable Content -->
                    <div class="card mb-4">
                        <div class="card-header bg-info text-white">
                            <h5 class="mb-0"><i class="fa fa-info-circle"></i> User Editable Information Note</h5>
                        </div>
                        <div class="card-body">
                            <div class="alert alert-info">
                                <i class="fa fa-info-circle"></i> The following information is managed by users in their personal profile:
                                <ul>
                                    <li><strong>Contact Information:</strong> Email, Address</li>
                                    <li><strong>Next of Kin Information:</strong> Name, Telephone, NRC, Relationship</li>
                                    <li><strong>Professional Details:</strong> Profession, Trade, Specialization</li>
                                </ul>
                                <p>Users can update these fields in their personal profile at <code>/users/personal.php</code></p>
                            </div>
                        </div>
                    </div>

                    <!-- Military Details -->
                    <div class="card mb-4">
                        <div class="card-header bg-dark text-white">
                            <h5 class="mb-0"><i class="fa fa-shield-alt"></i> Military Details</h5>
                        </div>
                        <div class="card-body">
                            <div class="row mb-3">
                                <div class="col-md-3">
                                    <label for="combatSize" class="form-label">Combat Size <span class="text-muted">(Optional)</span></label>
                                    <input type="text" class="form-control" name="combatSize" id="combatSize"
                                        value="<?=htmlspecialchars($staff->combatSize ?? '')?>">
                                </div>
                                <div class="col-md-3">
                                    <label for="bsize" class="form-label">B Size <span class="text-muted">(Optional)</span></label>
                                    <input type="text" class="form-control" name="bsize" id="bsize"
                                        value="<?=htmlspecialchars($staff->bsize ?? '')?>">
                                </div>
                                <div class="col-md-3">
                                    <label for="ssize" class="form-label">S Size <span class="text-muted">(Optional)</span></label>
                                    <input type="text" class="form-control" name="ssize" id="ssize"
                                        value="<?=htmlspecialchars($staff->ssize ?? '')?>">
                                </div>
                                <div class="col-md-3">
                                    <label for="hdress" class="form-label">Dress Size <span class="text-muted">(Optional)</span></label>
                                    <input type="text" class="form-control" name="hdress" id="hdress"
                                        value="<?=htmlspecialchars($staff->hdress ?? '')?>">
                                </div>
                            </div>

                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="attestDate" class="form-label">Attestation Date <span class="text-muted">(Optional)</span></label>
                                    <input type="date" class="form-control" name="attestDate" id="attestDate"
                                        value="<?=htmlspecialchars($staff->attestDate ?? '')?>">
                                </div>
                                <div class="col-md-6">
                                    <label for="lastPromotion" class="form-label">Last Promotion Date <span class="text-muted">(Optional)</span></label>
                                    <input type="date" class="form-control" name="lastPromotion" id="lastPromotion"
                                        value="<?=htmlspecialchars($staff->lastPromotion ?? '')?>">
                                </div>
                            </div>

                            <div class="row mb-3">
                                <div class="col-md-12">
                                    <label for="postingHistory" class="form-label">Posting History <span class="text-muted">(Optional)</span></label>
                                    <textarea class="form-control" name="postingHistory" id="postingHistory" rows="2"><?=htmlspecialchars($staff->postingHistory ?? '')?></textarea>
                                </div>
                            </div>

                            <div class="row mb-3">
                                <div class="col-md-12">
                                    <label for="awards" class="form-label">Awards & Commendations <span class="text-muted">(Optional)</span></label>
                                    <textarea class="form-control" name="awards" id="awards" rows="2"><?=htmlspecialchars($staff->awards ?? '')?></textarea>
                                </div>
                            </div>

                            <div class="row mb-3">
                                <div class="col-md-12">
                                    <label for="disciplinaryRecord" class="form-label">Disciplinary Record <span class="text-muted">(Optional)</span></label>
                                    <textarea class="form-control" name="disciplinaryRecord" id="disciplinaryRecord" rows="2"><?=htmlspecialchars($staff->disciplinaryRecord ?? '')?></textarea>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Dynamic Operations Section -->
                    <div class="card mb-4">
                        <div class="card-header bg-secondary text-white">
                            <h5 class="mb-0"><i class="fa fa-tasks"></i> Operations</h5>
                        </div>
                        <div class="card-body">
                            <div class="alert alert-info">
                                <i class="fa fa-info-circle"></i> Add operations this staff member has participated in. Leave empty if none.
                            </div>
                            <div id="operationsList">
                                <!-- Existing operations will be rendered here dynamically via JS/PHP if needed -->
                            </div>
                            <button type="button" class="btn btn-outline-primary" id="addOperationBtn">
                                <i class="fa fa-plus"></i> Add Operation
                            </button>
                        </div>
                    </div>
                    <!-- Dynamic Deployments Section -->
                    <div class="card mb-4">
                        <div class="card-header bg-info text-white">
                            <h5 class="mb-0"><i class="fa fa-plane"></i> Deployments</h5>
                        </div>
                        <div class="card-body">
                            <div class="alert alert-info">
                                <i class="fa fa-info-circle"></i> Add deployments this staff member has been assigned to. Leave empty if none.
                            </div>
                            <div id="deploymentsList">
                                <!-- Existing deployments will be rendered here dynamically via JS/PHP if needed -->
                            </div>
                            <button type="button" class="btn btn-outline-primary" id="addDeploymentBtn">
                                <i class="fa fa-plus"></i> Add Deployment
                            </button>
                        </div>
                    </div>
                    <!-- Dynamic Education Section -->
                    <div class="card mb-4">
                        <div class="card-header bg-success text-white">
                            <h5 class="mb-0"><i class="fa fa-graduation-cap"></i> Education</h5>
                        </div>
                        <div class="card-body">
                            <div class="alert alert-info">
                                <i class="fa fa-info-circle"></i> Add education qualifications for this staff member. Leave empty if none.
                            </div>
                            <div id="educationList">
                                <!-- Existing education will be rendered here dynamically via JS/PHP if needed -->
                            </div>
                            <button type="button" class="btn btn-outline-primary" id="addEducationBtn">
                                <i class="fa fa-plus"></i> Add Education
                            </button>
                        </div>
                    </div>
                    <!-- Dynamic Skills/Courses Section (optional, if applicable) -->
                    <div class="card mb-4">
                        <div class="card-header bg-warning text-dark">
                            <h5 class="mb-0"><i class="fa fa-cogs"></i> Skills & Courses</h5>
                        </div>
                        <div class="card-body">
                            <div class="alert alert-info">
                                <i class="fa fa-info-circle"></i> Add skills and courses completed by this staff member. Leave empty if none.
                            </div>
                            <div id="skillsList">
                                <!-- Existing skills/courses will be rendered here dynamically via JS/PHP if needed -->
                            </div>
                            <button type="button" class="btn btn-outline-primary" id="addSkillBtn">
                                <i class="fa fa-plus"></i> Add Skill/Course
                            </button>
                        </div>
                    </div>

                    <div class="d-flex gap-2 justify-content-between mt-4 mb-4">
                        <button type="button" class="btn btn-secondary" id="prevStepBtn" style="display: none;">
                            <i class="fa fa-arrow-left"></i> Previous Step
                        </button>
                        <div>
                            <button type="button" class="btn btn-primary" id="nextStepBtn">
                                Next Step <i class="fa fa-arrow-right"></i>
                            </button>
                            <button type="submit" class="btn btn-success px-4" id="submitFormBtn" style="display: none;">
                                <i class="fa fa-save"></i> Save Staff Member
                            </button>
                            <button type="button" class="btn btn-info" id="validateFormBtn">
                                <i class="fa fa-check-circle"></i> Validate Form
                            </button>
                            <a href="edit_staff.php" class="btn btn-secondary" id="cancelBtn">Cancel</a>
                        </div>
                    </div>
                    
                    <div class="alert alert-info mt-3">
                        <p><i class="fa fa-info-circle"></i> <strong>Form Submission Tip:</strong> If the form isn't saving properly, click the "Validate Form" button to check for issues.</p>
                    </div>
                </form>
            <?php endif; ?>
        </div>
    </div>
</div>
            </div>
        </div>
        </div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.3/js/bootstrap.bundle.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
<script src="/Armis2/assets/js/edit_staff_support.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Include multi-step form functionality
    const script = document.createElement('script');
    script.src = '/Armis2/admin_branch/js/multi-step-form.js';
    document.head.appendChild(script);
    // Setup UI event handlers
    document.getElementById('addOperationBtn')?.addEventListener('click', function() {
        addOperationRow();
    });

    document.getElementById('addDeploymentBtn')?.addEventListener('click', function() {
        addDeploymentRow();
    });

    document.getElementById('addEducationBtn')?.addEventListener('click', function() {
        addEducationRow();
    });

    document.getElementById('addSkillBtn')?.addEventListener('click', function() {
        addSkillRow();
    });

    // Flash alerts for 5 seconds
    const alerts = document.querySelectorAll('.alert-success');
    alerts.forEach(function(alert) {
        setTimeout(function() {
            const closeBtn = alert.querySelector('.btn-close');
            if (closeBtn) closeBtn.click();
        }, 5000);
    });

    // Form validation
    const editForm = document.getElementById('editStaffForm');
    if (editForm) {
        editForm.addEventListener('submit', function(e) {
            if (!validateForm()) {
                e.preventDefault();
                alert('Please fix the validation errors before submitting');
                window.scrollTo(0, 0);
                return false;
            }
            
            if (!confirm('Are you sure you want to update this staff member?')) {
                e.preventDefault();
                return false;
            }
        });
    }
    
    // Validate button handler
    const validateButton = document.getElementById('validateFormBtn');
    if (validateButton) {
        validateButton.addEventListener('click', function() {
            validateForm(true); // Show validation results
        });
    }
    
    // Validate form function
    function validateForm(showResults = false) {
        console.log('Validating form...');
        let isValid = true;
        let validationReport = [];
        
        // Basic validation for required fields
        const requiredFields = document.querySelectorAll('[required]');
        requiredFields.forEach(field => {
            if (!field.value.trim()) {
                isValid = false;
                field.classList.add('is-invalid');
                validationReport.push(`Required field "${field.name}" is empty`);
                const feedbackEl = document.getElementById(`${field.id}-error`);
                if (feedbackEl) feedbackEl.textContent = 'This field is required';
            } else {
                field.classList.remove('is-invalid');
            }
        });
        
        
        // Check operations
        const operationsCount = document.getElementById('operationsList')?.children.length || 0;
        validationReport.push(`Operations: ${operationsCount} items found`);
        
        // Check deployments
        const deploymentsCount = document.getElementById('deploymentsList')?.children.length || 0;
        validationReport.push(`Deployments: ${deploymentsCount} items found`);
        
        // Check education
        const educationCount = document.getElementById('educationList')?.children.length || 0;
        validationReport.push(`Education: ${educationCount} items found`);
        
        // Check skills
        const skillsCount = document.getElementById('skillsList')?.children.length || 0;
        validationReport.push(`Skills: ${skillsCount} items found`);
        
        // Serialized form data check
        const formData = new FormData(editForm);
        const formFieldsCount = Array.from(formData.entries()).length;
        validationReport.push(`Total form fields: ${formFieldsCount}`);
        
        // Check CSRF token
        const csrfToken = formData.get('csrf_token');
        if (!csrfToken) {
            isValid = false;
            validationReport.push('CSRF token is missing');
        } else {
            validationReport.push(`CSRF token present: ${csrfToken.substring(0, 10)}...`);
        }
        
        // Check service number
        const svcNo = formData.get('svcNo');
        if (!svcNo) {
            isValid = false;
            validationReport.push('Service number is missing');
        } else {
            validationReport.push(`Service number: ${svcNo}`);
        }
        
        if (showResults) {
            alert(`Validation Results:\n${validationReport.join('\n')}\n\nForm is ${isValid ? 'valid' : 'invalid'}`);
            console.log('Validation report:', validationReport);
        }
        
        return isValid;
    }
    
    // Auto-dismiss alerts
    document.querySelectorAll('.alert-dismissible').forEach(alert => {
        setTimeout(() => {
            const bsAlert = new bootstrap.Alert(alert);
            bsAlert.close();
        }, 5000);
    });
    
    // CSV Export functionality
    const exportCsvLink = document.querySelector('a[href="?export=csv"]');
    if (exportCsvLink) {
        exportCsvLink.addEventListener('click', function(e) {
            e.preventDefault();
            exportTableToCsv('staff_list.csv');
        });
    }
    
    function exportTableToCsv(filename) {
        // Check if we have staff data
        if (!allResults || allResults.length === 0) {
            alert('No data available to export. Please search for staff first.');
            return;
        }
        
        // Generate CSV content
        const headers = ['Service Number', 'Name', 'Rank', 'Unit', 'Status'];
        
        let csvContent = headers.join(',') + '\n';
        
        allResults.forEach(function(staff) {
            const row = [
                '"' + staff.svcNo.replace(/"/g, '""') + '"',
                '"' + staff.name.replace(/"/g, '""') + '"',
                '"' + staff.rank.replace(/"/g, '""') + '"',
                '"' + staff.unit.replace(/"/g, '""') + '"',
                '"' + staff.status.replace(/"/g, '""') + '"'
            ];
            csvContent += row.join(',') + '\n';
        });
        
        // Create download link
        const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
        const url = URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.setAttribute('href', url);
        link.setAttribute('download', filename);
        link.style.visibility = 'hidden';
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    }
});
</script>
<?php include dirname(__DIR__) . '/shared/footer.php'; ?>