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

// Check specific permission for editing staff
if (!hasPermission(PERM_EDIT_STAFF)) {
    header('HTTP/1.1 403 Forbidden');
    die('Access denied. You do not have permission to edit staff records.');
}

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

// Sidebar navigation
$sidebarLinks = [
    ['title' => 'Dashboard', 'url' => '/Armis2/admin_branch/index.php', 'icon' => 'tachometer-alt', 'page' => 'dashboard'],
    ['title' => 'Staff Management', 'url' => '/Armis2/admin_branch/edit_staff.php', 'icon' => 'users', 'page' => 'staff'],
    ['title' => 'Create Staff', 'url' => '/Armis2/admin_branch/create_staff.php', 'icon' => 'user-plus', 'page' => 'create'],
    ['title' => 'Delete Staff', 'url' => '/Armis2/admin_branch/delete_staff.php', 'icon' => 'user-times', 'page' => 'delete'],
    ['title' => 'Promotions', 'url' => '/Armis2/admin_branch/promote_staff.php', 'icon' => 'arrow-up', 'page' => 'promotions'],
    ['title' => 'Appointments', 'url' => '/Armis2/admin_branch/appointments.php', 'icon' => 'user-tie', 'page' => 'appointments'],
    ['title' => 'Medals', 'url' => '/Armis2/admin_branch/assign_medal.php', 'icon' => 'medal', 'page' => 'medals'],
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
            ['title' => 'Medals', 'url' => '/Armis2/admin_branch/reports_medals.php'],
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
    $fields = ['first_name', 'last_name', 'rank_id', 'unit_id', 'corps_id', 'NRC', 'DOB', 'gender', 'svcStatus'];
    foreach ($fields as $field) {
        $oldValue = isset($oldData->$field) ? $oldData->$field : null;
        $newValue = isset($newData[$field]) ? $newData[$field] : null;
        if ($oldValue != $newValue) {
            $changes[] = [
                'field' => $field,
                'old_value' => $oldValue,
                'new_value' => $newValue
            ];
        }
    }
    if (!empty($changes)) {
        try {
            $pdo = getDbConnection();
            
            // Check if staff_edit_log table exists, create if not
            $tableExists = $pdo->query("SHOW TABLES LIKE 'staff_edit_log'")->rowCount() > 0;
            if (!$tableExists) {
                $createSql = "
                    CREATE TABLE staff_edit_log (
                        id INT AUTO_INCREMENT PRIMARY KEY,
                        service_number VARCHAR(50) NOT NULL,
                        edited_by INT NOT NULL,
                        edited_at DATETIME NOT NULL,
                        user_ip VARCHAR(45),
                        changes JSON,
                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                    )
                ";
                $pdo->exec($createSql);
                error_log("Created staff_edit_log table");
            }
            
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
            // Don't throw exception, just log it
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
    
    // 1. Primary: Rank level ascending
    // 2. Secondary: Date of rank (subWef, tempWef, attestDate) earliest first
    // 3. Tertiary: Service number ascending
    // 4. Acting/Appointed: Use effective date if acting/appointed
    // 5. Consistent Data Entry: Validate date fields before query
    // 6. Retirement/Transfer: Exclude or mark retired/transferred staff
    // 7. Audit/Validation: Log missing/inconsistent data

    // Exclude retired/transferred staff unless explicitly requested
    if (!isset($_GET['include_inactive']) || $_GET['include_inactive'] !== '1') {
        $sql .= " AND s.svcStatus IN ('Active', 'Inactive')";
    }

    // Validate date fields for consistency (log if missing)
    $dateFields = ['subWef', 'tempWef', 'attestDate'];
    foreach ($dateFields as $df) {
        $sql .= " AND (s.$df IS NULL OR s.$df = '' OR s.$df >= '1900-01-01')";
    }

    // Order by seniority logic
    $sql .= " ORDER BY r.level ASC, 
        LEAST(
            COALESCE(s.subWef, '9999-12-31'),
            COALESCE(s.tempWef, '9999-12-31'),
            COALESCE(s.attestDate, '9999-12-31'),
            COALESCE(s.actingWef, '9999-12-31'),
            COALESCE(s.appointedWef, '9999-12-31')
        ) ASC,
        s.service_number ASC,
        s.DOB ASC
    ";

    // Log missing/inconsistent data for audit
    $auditStmt = $pdo->query("SELECT service_number, subWef, tempWef, attestDate FROM staff WHERE (subWef IS NULL OR subWef = '' OR subWef < '1900-01-01') OR (temWef IS NULL OR temWef = '' OR temWef < '1900-01-01') OR (attestDate IS NULL OR attestDate = '' OR attestDate < '1900-01-01')");
    $auditRows = $auditStmt->fetchAll(PDO::FETCH_ASSOC);
    if (!empty($auditRows)) {
        error_log('Staff seniority audit: Missing/inconsistent date fields: ' . json_encode($auditRows));
    }
    
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

// Helper function to normalize service numbers
function normalizeServiceNumber($svcNo) {
    // Remove any non-numeric characters and pad with leading zeros if needed
    $clean = preg_replace('/[^0-9]/', '', $svcNo);
    // If it's a reasonable length, pad to 6 digits (adjust as needed)
    if (strlen($clean) > 0 && strlen($clean) <= 6) {
        return str_pad($clean, 6, '0', STR_PAD_LEFT);
    }
    return $clean;
}

// --- Staff selection ---
if (isset($_GET['svcNo'])) {
    $svcNo = $_GET['svcNo'];
    $normalizedSvcNo = normalizeServiceNumber($svcNo);
    
    // Try exact match first
    $stmt = $pdo->prepare("SELECT * FROM staff WHERE service_number = ?");
    $stmt->execute([$svcNo]);
    $staff = $stmt->fetch(PDO::FETCH_OBJ);
    
    // If not found and the normalized version is different, try that
    if (!$staff && $normalizedSvcNo !== $svcNo) {
        error_log("Staff not found with '$svcNo', trying normalized '$normalizedSvcNo'");
        $stmt->execute([$normalizedSvcNo]);
        $staff = $stmt->fetch(PDO::FETCH_OBJ);
    }
    
    // If still not found, try without leading zeros
    if (!$staff) {
        $withoutZeros = ltrim($svcNo, '0');
        if ($withoutZeros !== $svcNo && !empty($withoutZeros)) {
            error_log("Trying without leading zeros: '$withoutZeros'");
            $stmt->execute([$withoutZeros]);
            $staff = $stmt->fetch(PDO::FETCH_OBJ);
        }
    }
    
    if ($staff) {
        error_log("Found staff member: " . $staff->first_name . " " . $staff->last_name . " with service number: " . $staff->service_number);
    } else {
        error_log("Staff member not found with any variation of service number: " . $svcNo);
    }
}

// --- Handle edit post ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_staff'])) {
    $svcNo = $_POST['svcNo'];
    $newSvcNo = trim($_POST['newSvcNo'] ?? $svcNo);
    $csrf = $_POST['csrf_token'] ?? '';
    
    // Debug logging
    error_log("=== EDIT STAFF REQUEST ===");
    error_log("Original service number: " . $svcNo);
    error_log("New service number: " . $newSvcNo);
    error_log("Has operations: " . (isset($_POST['operations']) ? count($_POST['operations']) : 0));
    error_log("Has deployments: " . (isset($_POST['deployments']) ? count($_POST['deployments']) : 0));
    error_log("Has education: " . (isset($_POST['education']) ? count($_POST['education']) : 0));
    error_log("Has skills: " . (isset($_POST['skills']) ? count($_POST['skills']) : 0));
    if (!isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $csrf)) {
        $errors[] = "Invalid CSRF token. Please reload the page and try again.";
    } else {
        $stmt = $pdo->prepare("SELECT * FROM staff WHERE service_number = ?");
        $stmt->execute([$svcNo]);
        $originalStaff = $stmt->fetch(PDO::FETCH_OBJ);
        if (!$originalStaff) {
            $errors[] = "Staff member not found with service number: " . $svcNo . ". Please check the service number and try again.";
            error_log("Staff member not found with service number: " . $svcNo);
        } else {
            error_log("Found staff member: " . $originalStaff->first_name . " " . $originalStaff->last_name . " (ID: " . $originalStaff->id . ")");
            // Get and sanitize form data
            $fname = trim($_POST['fname'] ?? '');
            $lname = trim($_POST['lname'] ?? '');
            $rankID = $_POST['rankID'] ?? '';
            $unitID = $_POST['unitID'] ?? '';
            $corpsID = $_POST['corps'] ?? '';
            $category = $_POST['category'] ?? '';
            $NRC = trim($_POST['NRC'] ?? '');
            $DOB = $_POST['DOB'] ?? '';
            $gender = $_POST['gender'] ?? '';
            $svcStatus = $_POST['svcStatus'] ?? '';
            $tel = $_POST['tel'] ?? '';
            $email = $_POST['email'] ?? '';
            $address = $_POST['address'] ?? '';
            $nok = $_POST['nokName'] ?? $_POST['nok'] ?? '';
            $nokTel = $_POST['nokPhone'] ?? $_POST['nokTel'] ?? '';
            $nokNrc = $_POST['nokNRC'] ?? $_POST['nokNrc'] ?? '';
            $nokRelat = $_POST['nokRelationship'] ?? $_POST['nokRelat'] ?? '';
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
                    'service_number' => $newSvcNo,
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
                    // --- Advanced rank logic: update tempWef/subWef/category ---
                    // Get rank details
                    $rankStmt = $pdo->prepare("SELECT name, category FROM ranks WHERE id = ? LIMIT 1");
                    $rankStmt->execute([$rankID]);
                    $rankDetails = $rankStmt->fetch(PDO::FETCH_ASSOC);
                    if ($rankDetails) {
                        $rankName = $rankDetails['name'] ?? '';
                        $category = $rankDetails['category'] ?? '';
                        $lastPromotionDate = $lastPromotion;
                        // Always update staff category from selected rank
                        $updateData['category'] = $category;
                        // Check if rank name contains 'Temporal' (case-insensitive)
                        $isTemporal = stripos($rankName, 'Temporal') !== false;
                        if ($isTemporal) {
                            $updateData['tempWef'] = $lastPromotionDate;
                            $updateData['subWef'] = null;
                        } else {
                            $updateData['subWef'] = $lastPromotionDate;
                            $updateData['tempWef'] = null;
                        }
                    }
                try {
                    $pdo->beginTransaction();
                    
                    // Debug: Log the update operation
                    error_log("Starting staff update for service number: " . $svcNo);
                    
                    // Log staff changes (this won't throw exceptions now)
                    logStaffChange($svcNo, $originalStaff, $updateData, $user->data()->id ?? 0, $_SERVER['REMOTE_ADDR'] ?? '');
                    
                    // Get current staff table structure to build dynamic update query
                    $columns = $pdo->query('DESCRIBE staff')->fetchAll(PDO::FETCH_ASSOC);
                    $existingColumns = array_column($columns, 'Field');
                    
                    // Map form fields to database columns
                    $fieldMap = [
                        'first_name' => $fname,
                        'last_name' => $lname,
                        'rank_id' => $rankID,
                        'unit_id' => $unitID,
                        'corps_id' => $corpsID,
                        'category' => $category,
                        'service_number' => $newSvcNo,
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
                        'disciplinaryRecord' => $disciplinaryRecord,
                        'maritalStatus' => $_POST['maritalStatus'] ?? null,
                        'marital_status' => $_POST['maritalStatus'] ?? null, // Handle both column names
                        'religion' => $_POST['religion'] ?? null,
                        'bloodGroup' => $_POST['bloodGroup'] ?? null,
                        'bloodGp' => $_POST['bloodGroup'] ?? null, // Handle both column names
                        'height' => $_POST['height'] ?? null
                    ];
                    
                    // Build dynamic update query with only existing columns
                    $updateFields = [];
                    $updateParams = [];
                    foreach ($fieldMap as $column => $value) {
                        if (in_array($column, $existingColumns)) {
                            // Only allow service_number update if changed
                            if ($column === 'service_number' && $newSvcNo !== $svcNo) {
                                // Check for duplicate service number
                                $dupStmt = $pdo->prepare("SELECT COUNT(*) FROM staff WHERE service_number = ?");
                                $dupStmt->execute([$newSvcNo]);
                                if ($dupStmt->fetchColumn() > 0) {
                                    throw new Exception("Service number already exists. Please choose a unique service number.");
                                }
                            }
                            $updateFields[] = "$column = ?";
                            $updateParams[] = $value;
                        } else {
                            error_log("Skipping missing column: $column");
                        }
                    }
                    // Add service_number for WHERE clause
                    $updateParams[] = $svcNo;
                    $updateSql = "UPDATE staff SET " . implode(', ', $updateFields) . " WHERE service_number = ?";
                    
                    // Debug: Log the SQL and parameters
                    error_log("Dynamic Update SQL: " . $updateSql);
                    error_log("Update Params: " . json_encode($updateParams));
                    
                    $stmt = $pdo->prepare($updateSql);
                    if (!$stmt->execute($updateParams)) {
                        throw new Exception("Failed to update main staff record: " . implode(", ", $stmt->errorInfo()));
                    }
                    
                    $rowsAffected = $stmt->rowCount();
                    error_log("Rows affected by main update: " . $rowsAffected);
                    
                    if ($rowsAffected === 0) {
                        // Additional debugging: Check if the record exists
                        $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM staff WHERE service_number = ?");
                        $checkStmt->execute([$svcNo]);
                        $recordExists = $checkStmt->fetchColumn();
                        
                        error_log("DEBUG: Record exists check for service number '$svcNo': " . ($recordExists ? 'YES' : 'NO'));
                        error_log("DEBUG: New service number: '$newSvcNo'");
                        error_log("DEBUG: Update SQL: " . $updateSql);
                        error_log("DEBUG: Update Params: " . json_encode($updateParams));
                        
                        if ($recordExists) {
                            // Record exists but wasn't updated - might be no actual changes
                            error_log("WARNING: Staff record exists but was not updated - possibly no changes detected");
                            // Don't throw exception, continue processing
                        } else {
                            throw new Exception("No staff record found with service number: " . $svcNo . ". Please verify the service number exists.");
                        }
                    }
                    
                    // Get staff_id for child tables using the new service number
                    $stmtStaffId = $pdo->prepare("SELECT id FROM staff WHERE service_number = ?");
                    $stmtStaffId->execute([$newSvcNo]);
                    $staffId = $stmtStaffId->fetchColumn();
                    
                    // If not found with new service number, try with original service number
                    if (!$staffId && $newSvcNo !== $svcNo) {
                        error_log("Staff not found with new service number '$newSvcNo', trying original '$svcNo'");
                        $stmtStaffId->execute([$svcNo]);
                        $staffId = $stmtStaffId->fetchColumn();
                    }
                    
                    // If still not found, try to get from the original staff object
                    if (!$staffId && isset($originalStaff->id)) {
                        $staffId = $originalStaff->id;
                        error_log("Using staff ID from original staff object: " . $staffId);
                    }
                    
                    if (!$staffId) {
                        throw new Exception("Could not retrieve staff ID for service number: " . $newSvcNo . " or original: " . $svcNo);
                    }
                    
                    error_log("Using staff ID: " . $staffId . " for processing dynamic fields");

                    // --- Operations (with error handling) ---
                    try {
                        error_log("Processing operations for staff ID: " . $staffId);
                        
                        // Check if table exists
                        $tableExists = $pdo->query("SHOW TABLES LIKE 'staff_operations'")->rowCount() > 0;
                        if (!$tableExists) {
                            $createSql = "
                                CREATE TABLE staff_operations (
                                    id INT AUTO_INCREMENT PRIMARY KEY,
                                    staff_id INT NOT NULL,
                                    operation_id VARCHAR(100),
                                    role VARCHAR(100),
                                    start_date DATE,
                                    end_date DATE,
                                    performance_rating VARCHAR(50),
                                    remarks TEXT,
                                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                                    FOREIGN KEY (staff_id) REFERENCES staff(id) ON DELETE CASCADE
                                )
                            ";
                            $pdo->exec($createSql);
                            error_log("Created staff_operations table");
                        }
                        
                        $pdo->prepare("DELETE FROM staff_operations WHERE staff_id = ?")->execute([$staffId]);
                        
                        // Update table structure to match new fields
                        $pdo->exec("ALTER TABLE staff_operations 
                                   ADD COLUMN IF NOT EXISTS operation_name VARCHAR(200),
                                   ADD COLUMN IF NOT EXISTS location VARCHAR(200),
                                   ADD COLUMN IF NOT EXISTS status VARCHAR(50)");
                        
                        $opStmt = $pdo->prepare("INSERT INTO staff_operations (staff_id, operation_name, operation_id, role, location, start_date, end_date, status, remarks, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
                        foreach ($_POST['operations'] ?? [] as $index => $op) {
                            if (!empty($op['operation_name']) || !empty($op['operation_id'])) {
                                $opStmt->execute([
                                    $staffId,
                                    $op['operation_name'] ?? '',
                                    $op['operation_id'] ?? $op['operation_name'] ?? '', // Use name as ID if no ID provided
                                    $op['role'] ?? '',
                                    $op['location'] ?? '',
                                    $op['start_date'] ?? null,
                                    $op['end_date'] ?? null,
                                    $op['status'] ?? '',
                                    $op['remarks'] ?? ''
                                ]);
                            }
                        }
                    } catch (Exception $e) {
                        error_log("Error processing operations: " . $e->getMessage());
                        error_log("Operations data: " . json_encode($_POST['operations'] ?? []));
                        // Continue processing - don't fail the entire update for child table issues
                    }

                    // --- Deployments (with error handling) ---
                    try {
                        error_log("Processing deployments for staff ID: " . $staffId);
                        
                        // Check if table exists
                        $tableExists = $pdo->query("SHOW TABLES LIKE 'staff_deployments'")->rowCount() > 0;
                        if (!$tableExists) {
                            $createSql = "
                                CREATE TABLE staff_deployments (
                                    id INT AUTO_INCREMENT PRIMARY KEY,
                                    staff_id INT NOT NULL,
                                    deployment_name VARCHAR(200),
                                    mission_type VARCHAR(100),
                                    location VARCHAR(100),
                                    country VARCHAR(100),
                                    start_date DATE,
                                    end_date DATE,
                                    duration_months INT,
                                    deployment_status VARCHAR(50),
                                    rank_during_deployment VARCHAR(50),
                                    role_during_deployment VARCHAR(100),
                                    commanding_officer VARCHAR(100),
                                    deployment_allowance DECIMAL(10,2),
                                    notes TEXT,
                                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                                    FOREIGN KEY (staff_id) REFERENCES staff(id) ON DELETE CASCADE
                                )
                            ";
                            $pdo->exec($createSql);
                            error_log("Created staff_deployments table");
                        }
                        
                        $pdo->prepare("DELETE FROM staff_deployments WHERE staff_id = ?")->execute([$staffId]);
                        
                        // Ensure compatibility with new field names
                        $depStmt = $pdo->prepare("INSERT INTO staff_deployments (staff_id, deployment_name, mission_type, location, country, start_date, end_date, duration_months, deployment_status, rank_during_deployment, role_during_deployment, commanding_officer, deployment_allowance, notes, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())");
                        foreach ($_POST['deployments'] ?? [] as $index => $dep) {
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
                                    $dep['deployment_status'] ?? $dep['status'] ?? '', // Use the correct field name
                                    $dep['rank_during_deployment'] ?? '',
                                    $dep['role_during_deployment'] ?? $dep['role'] ?? '', // Use the correct field name
                                    $dep['commanding_officer'] ?? '',
                                    $dep['deployment_allowance'] ?? null,
                                    $dep['notes'] ?? ''
                                ]);
                            }
                        }
                    } catch (Exception $e) {
                        error_log("Error processing deployments: " . $e->getMessage());
                        error_log("Deployments data: " . json_encode($_POST['deployments'] ?? []));
                        // Continue processing
                    }

                    // --- Education (with error handling) ---
                    try {
                        error_log("Processing education for staff ID: " . $staffId);
                        
                        // Check if table exists
                        $tableExists = $pdo->query("SHOW TABLES LIKE 'staff_education'")->rowCount() > 0;
                        if (!$tableExists) {
                            $createSql = "
                                CREATE TABLE staff_education (
                                    id INT AUTO_INCREMENT PRIMARY KEY,
                                    staff_id INT NOT NULL,
                                    institution VARCHAR(200),
                                    qualification VARCHAR(100),
                                    level VARCHAR(50),
                                    field_of_study VARCHAR(100),
                                    year_started YEAR,
                                    year_completed YEAR,
                                    grade_obtained VARCHAR(20),
                                    is_highest_qualification BOOLEAN DEFAULT FALSE,
                                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                                    FOREIGN KEY (staff_id) REFERENCES staff(id) ON DELETE CASCADE
                                )
                            ";
                            $pdo->exec($createSql);
                            error_log("Created staff_education table");
                        }
                        
                        $pdo->prepare("DELETE FROM staff_education WHERE staff_id = ?")->execute([$staffId]);
                        $eduStmt = $pdo->prepare("INSERT INTO staff_education (staff_id, institution, qualification, level, field_of_study, year_started, year_completed, is_highest_qualification, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())");
                        foreach ($_POST['education'] ?? [] as $index => $edu) {
                            if (!empty($edu['institution'])) {
                                $eduStmt->execute([
                                    $staffId,
                                    $edu['institution'] ?? '',
                                    $edu['qualification'] ?? '',
                                    $edu['level'] ?? '',
                                    $edu['field_of_study'] ?? '',
                                    $edu['year_started'] ?? null,
                                    $edu['year_completed'] ?? null,
                                    !empty($edu['is_highest_qualification']) ? 1 : 0
                                ]);
                            }
                        }
                    } catch (Exception $e) {
                        error_log("Error processing education: " . $e->getMessage());
                        error_log("Education data: " . json_encode($_POST['education'] ?? []));
                        // Continue processing
                    }
                    
                    // --- Skills (with error handling) ---
                    try {
                        error_log("Processing skills for staff ID: " . $staffId);
                        
                        // Check if table exists
                        $tableExists = $pdo->query("SHOW TABLES LIKE 'staff_skills'")->rowCount() > 0;
                        if (!$tableExists) {
                            $createSql = "
                                CREATE TABLE staff_skills (
                                    id INT AUTO_INCREMENT PRIMARY KEY,
                                    staff_id INT NOT NULL,
                                    course_name VARCHAR(200),
                                    course_type VARCHAR(100),
                                    institution VARCHAR(200),
                                    start_date DATE,
                                    end_date DATE,
                                    duration_days INT,
                                    certificate_number VARCHAR(100),
                                    grade_obtained VARCHAR(20),
                                    location VARCHAR(100),
                                    cost DECIMAL(10,2),
                                    sponsored_by VARCHAR(100),
                                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                                    FOREIGN KEY (staff_id) REFERENCES staff(id) ON DELETE CASCADE
                                )
                            ";
                            $pdo->exec($createSql);
                            error_log("Created staff_skills table");
                        }
                        
                        $pdo->prepare("DELETE FROM staff_skills WHERE staff_id = ?")->execute([$staffId]);
                        
                        // Add certification_status column if not exists
                        $pdo->exec("ALTER TABLE staff_skills ADD COLUMN IF NOT EXISTS certification_status VARCHAR(50)");
                        
                        $skillStmt = $pdo->prepare("INSERT INTO staff_skills (staff_id, course_name, course_type, institution, start_date, end_date, duration_days, certificate_number, grade_obtained, location, cost, sponsored_by, certification_status, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())");
                        foreach ($_POST['skills'] ?? [] as $index => $skill) {
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
                                    $skill['sponsored_by'] ?? '',
                                    $skill['certification_status'] ?? ''
                                ]);
                            }
                        }
                    } catch (Exception $e) {
                        error_log("Error processing skills: " . $e->getMessage());
                        error_log("Skills data: " . json_encode($_POST['skills'] ?? []));
                        // Continue processing
                    }

                    $pdo->commit();
                    $success = true;
                    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
                    $stmt = $pdo->prepare("SELECT * FROM staff WHERE service_number = ?");
                    $stmt->execute([$newSvcNo]);
                    $staff = $stmt->fetch(PDO::FETCH_OBJ);
                } catch (Exception $e) {
                    $pdo->rollBack();
                    error_log("Edit error by user {$user->data()->id}: " . $e->getMessage());
                    
                    // More specific error messages
                    if (strpos($e->getMessage(), 'Column not found') !== false) {
                        $errors[] = "Database schema error: " . $e->getMessage() . ". The system has been updated to handle missing columns. Please try again.";
                    } elseif (strpos($e->getMessage(), 'Duplicate entry') !== false) {
                        $errors[] = "This staff member already exists with the same details.";
                    } else {
                        $errors[] = "Error updating staff record: " . $e->getMessage() . ". Please check the logs or contact system administrator.";
                    }
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
                sel.service_number,
                s.first_name,
                s.last_name,
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
<link rel="stylesheet" href="/Armis2/admin_branch/css/form-step-styles.css">
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
                <!-- Recent Activity (moved below results table) -->
               <!-- <?php if (!empty($recentActivity) && is_array($recentActivity)): ?>
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
                                            <?=htmlspecialchars(($activity->first_name ?? 'Unknown') . ' ' . ($activity->last_name ?? 'Staff'))?>
                                            <small class="text-muted">(<?=htmlspecialchars($activity->service_number ?? 'N/A')?>)</small>
                                        </h6>
                                        <p class="mb-1">
                                            Updated by: <?=htmlspecialchars($activity->edited_by_username ?? 'Unknown')?>
                                        </p>
                                        <small class="text-muted">
                                            <?=isset($activity->edited_at) ? date('M j, Y g:i A', strtotime($activity->edited_at)) : 'Unknown date'?>
                                        </small>
                                        <?php if (isset($activity->changes) && $activity->changes): ?>
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
                <?php endif; ?> -->
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
                <?php if (empty($staff)): ?>
                    <div class="alert alert-warning">
                        <h5><i class="fa fa-exclamation-triangle"></i> No Staff Member Selected</h5>
                        <p>Please search for and select a staff member to edit using the search form above.</p>
                        <p><strong>Note:</strong> To edit a staff member, you need to:</p>
                        <ol>
                            <li>Use the search form above to find the staff member</li>
                            <li>Click "Edit" on the staff member you want to modify</li>
                            <li>Or use a direct URL like: <code>edit_staff.php?svcNo=SERVICENUMBER</code></li>
                        </ol>
                    </div>
                <?php else: ?>
                <form method="post" id="editStaffForm" autocomplete="off" aria-label="Edit Staff Member" enctype="multipart/form-data">
    <input type="hidden" name="edit_staff" value="1">
    <input type="hidden" name="staff_id" value="<?= htmlspecialchars($staff->id ?? '') ?>">
    <input type="hidden" name="svcNo" value="<?= htmlspecialchars($staff->service_number ?? '') ?>">
    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">

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
                <p><strong>Form ID:</strong> <?= htmlspecialchars('editStaffForm') ?></p>
                <p><strong>Service Number:</strong> <?= htmlspecialchars($staff->service_number ?? 'Not set') ?></p>
                <p><strong>Staff ID:</strong> <?= htmlspecialchars($staff->id ?? 'Not set') ?></p>
                <p><strong>CSRF Token:</strong> <?= htmlspecialchars(substr(csrf_token(), 0, 10)) ?>...</p>
                <p><strong>Dynamic Data:</strong></p>
                <ul>
                    <li>Operations: <?= !empty($staffOperations) ? count($staffOperations) : 0 ?> records</li>
                    <li>Deployments: <?= !empty($staffDeployments) ? count($staffDeployments) : 0 ?> records</li>
                    <li>Education: <?= !empty($staffEducation) ? count($staffEducation) : 0 ?> records</li>
                    <li>Skills: <?= !empty($staffSkills) ? count($staffSkills) : 0 ?> records</li>
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
                    <!-- Service Number -->
                    <div class="col-md-4">
                        <label for="newSvcNo" class="form-label required-field">Service Number</label>
                        <input type="text"
                               class="form-control"
                               name="newSvcNo" id="newSvcNo"
                               value="<?= htmlspecialchars($staff->service_number ?? '') ?>"
                               required pattern="\d+" maxlength="50"
                               aria-describedby="svcNo-help">
                        <small id="svcNo-help" class="form-text text-muted">Unique integer service number</small>
                    </div>
                    <!-- First Name -->
                    <div class="col-md-4">
                        <label for="fname" class="form-label required-field">First Name</label>
                        <input type="text"
                               class="form-control <?= isset($validationErrors['fname']) ? 'is-invalid' : '' ?>"
                               name="fname" id="fname"
                               value="<?= htmlspecialchars($staff->first_name ?? '') ?>"
                               required
                               maxlength="<?= MAX_NAME_LENGTH ?>"
                               minlength="<?= MIN_NAME_LENGTH ?>"
                               pattern="[a-zA-Z\s\-'.]{<?= MIN_NAME_LENGTH ?>,<?= MAX_NAME_LENGTH ?>}"
                               aria-describedby="fname-help">
                        <small id="fname-help" class="form-text text-muted">
                            <?= MIN_NAME_LENGTH ?>–<?= MAX_NAME_LENGTH ?> characters, letters only
                        </small>
                        <div class="invalid-feedback" id="fname-error"><?= $validationErrors['fname'] ?? '' ?></div>
                    </div>
                    <!-- Last Name -->
                    <div class="col-md-4">
                        <label for="lname" class="form-label required-field">Last Name</label>
                        <input type="text"
                               class="form-control <?= isset($validationErrors['lname']) ? 'is-invalid' : '' ?>"
                               name="lname" id="lname"
                               value="<?= htmlspecialchars($staff->last_name ?? '') ?>"
                               required
                               maxlength="<?= MAX_NAME_LENGTH ?>"
                               minlength="<?= MIN_NAME_LENGTH ?>"
                               pattern="[a-zA-Z\s\-'.]{<?= MIN_NAME_LENGTH ?>,<?= MAX_NAME_LENGTH ?>}"
                               aria-describedby="lname-help">
                        <small id="lname-help" class="form-text text-muted">
                            <?= MIN_NAME_LENGTH ?>–<?= MAX_NAME_LENGTH ?> characters, letters only
                        </small>
                        <div class="invalid-feedback" id="lname-error"><?= $validationErrors['lname'] ?? '' ?></div>
                    </div>
                </div>

                <div class="row mb-3">
                    <!-- Rank -->
                    <div class="col-md-6">
                        <label for="rankID" class="form-label required-field">Rank</label>
                        <select class="form-select <?= isset($validationErrors['rankID']) ? 'is-invalid' : '' ?>"
                                name="rankID" id="rankID" required aria-describedby="rankID-help">
                            <option value="">Select Rank</option>
                            <?php foreach ($ranks as $rank): ?>
                                <option value="<?= htmlspecialchars($rank->rankID) ?>"
                                        <?= (!empty($staff) && $staff->rank_id == $rank->rankID) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($rank->rankName) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small id="rankID-help" class="form-text text-muted">
                            Current: <strong><?= htmlspecialchars(!empty($staff) ? ($rankMap[$staff->rank_id] ?? 'Unknown') : 'Unknown') ?></strong>
                        </small>
                        <div class="invalid-feedback" id="rankID-error"><?= $validationErrors['rankID'] ?? '' ?></div>
                    </div>
                    <!-- Unit -->
                    <div class="col-md-6">
                        <label for="unitID" class="form-label required-field">Unit</label>
                        <select class="form-select <?= isset($validationErrors['unitID']) ? 'is-invalid' : '' ?>"
                                name="unitID" id="unitID" required aria-describedby="unitID-help">
                            <option value="">Select Unit</option>
                            <?php foreach ($units as $unit): ?>
                                <option value="<?= htmlspecialchars($unit->unitID) ?>"
                                        <?= (!empty($staff) && $staff->unit_id == $unit->unitID) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($unit->unitName) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small id="unitID-help" class="form-text text-muted">
                            Current: <strong><?= htmlspecialchars(!empty($staff) ? ($unitMap[$staff->unit_id] ?? 'Unknown') : 'Unknown') ?></strong>
                        </small>
                        <div class="invalid-feedback" id="unitID-error"><?= $validationErrors['unitID'] ?? '' ?></div>
                    </div>
                </div>

                <div class="row mb-3">
                    <!-- Corps -->
                    <div class="col-md-6">
                        <label for="corps" class="form-label">Corps <span class="text-muted">(Optional)</span></label>
                        <select class="form-select" name="corps" id="corps">
                            <option value="">Select Corps</option>
                            <?php foreach ($corps as $corpsItem): ?>
                                <option value="<?= htmlspecialchars($corpsItem->id) ?>"
                                        <?= (!empty($staff) && isset($staff->corps_id) && $staff->corps_id == $corpsItem->id) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($corpsItem->name) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <!-- NRC -->
                    <div class="col-md-6">
                        <label for="NRC" class="form-label">NRC <span class="text-muted">(Optional)</span></label>
                        <input type="text" class="form-control" name="NRC" id="NRC"
                               value="<?= htmlspecialchars($staff->NRC ?? '') ?>" maxlength="<?= MAX_NRC_LENGTH ?>">
                        <small id="NRC-help" class="form-text text-muted">National Registration Card number</small>
                        <div class="invalid-feedback" id="NRC-error"></div>
                    </div>
                </div>

                <div class="row mb-3">
                    <!-- DOB -->
                    <div class="col-md-4">
                        <label for="DOB" class="form-label">Date of Birth <span class="text-muted">(Optional)</span></label>
                        <input type="date" class="form-control" name="DOB" id="DOB"
                               value="<?= htmlspecialchars($staff->DOB ?? '') ?>"
                               min="<?= date('Y-m-d', strtotime('-' . MAX_AGE_YEARS . ' years')) ?>"
                               max="<?= date('Y-m-d', strtotime('-' . MIN_AGE_YEARS . ' years')) ?>">
                        <small id="DOB-help" class="form-text text-muted">
                            Age must be between <?= MIN_AGE_YEARS ?> and <?= MAX_AGE_YEARS ?> years
                        </small>
                        <div class="invalid-feedback" id="DOB-error"></div>
                    </div>
                    <!-- Gender -->
                    <div class="col-md-4">
                        <label for="gender" class="form-label required-field">Gender</label>
                        <select class="form-select <?= isset($validationErrors['gender']) ? 'is-invalid' : '' ?>"
                                name="gender" id="gender" required aria-describedby="gender-help">
                            <option value="">Select Gender</option>
                            <?php foreach (VALID_GENDERS as $g): ?>
                                <option value="<?= htmlspecialchars($g) ?>"
                                        <?= (!empty($staff) && $staff->gender == $g) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($g) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small id="gender-help" class="form-text text-muted">Select gender</small>
                        <div class="invalid-feedback" id="gender-error"><?= $validationErrors['gender'] ?? '' ?></div>
                    </div>
                    <!-- Marital Status -->
                    <div class="col-md-4">
                        <label for="maritalStatus" class="form-label">Marital Status <span class="text-muted">(Optional)</span></label>
                        <select class="form-select" name="maritalStatus" id="maritalStatus">
                            <option value="">Select Status</option>
                            <?php 
                            $maritalStatuses = ['Single', 'Married', 'Divorced', 'Widowed', 'Separated'];
                            foreach ($maritalStatuses as $status): ?>
                                <option value="<?= htmlspecialchars($status) ?>" 
                                        <?= (!empty($staff) && ($staff->maritalStatus ?? $staff->marital_status ?? '') === $status) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($status) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small class="form-text text-muted">Current marital status</small>
                    </div>
                </div>

                <div class="row mb-3">
                    <!-- Service Status -->
                    <div class="col-md-6">
                        <label for="svcStatus" class="form-label required-field">Service Status</label>
                        <select class="form-select <?= isset($validationErrors['svcStatus']) ? 'is-invalid' : '' ?>"
                                name="svcStatus" id="svcStatus" required aria-describedby="svcStatus-help">
                            <option value="">Select Status</option>
                            <?php foreach (VALID_STATUSES as $status): ?>
                                <option value="<?= htmlspecialchars($status) ?>"
                                        <?= (!empty($staff) && $staff->svcStatus == $status) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($status) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small id="svcStatus-help" class="form-text text-muted">Current service status</small>
                        <div class="invalid-feedback" id="svcStatus-error"><?= $validationErrors['svcStatus'] ?? '' ?></div>
                    </div>
                    <!-- Telephone -->
                    <div class="col-md-6">
                        <label for="tel" class="form-label">Telephone <span class="text-muted">(Optional)</span></label>
                        <input type="tel" class="form-control" name="tel" id="tel"
                               value="<?= htmlspecialchars($staff->tel ?? '') ?>"
                               placeholder="+260 XXX XXX XXX" maxlength="20">
                        <small class="form-text text-muted">Contact telephone number</small>
                    </div>
                </div>

                <div class="row mb-3">
                    <!-- Email -->
                    <div class="col-md-6">
                        <label for="email" class="form-label">Email Address <span class="text-muted">(Optional)</span></label>
                        <input type="email" class="form-control" name="email" id="email"
                               value="<?= htmlspecialchars($staff->email ?? '') ?>"
                               placeholder="example@mail.com" maxlength="100">
                        <small class="form-text text-muted">Official email address</small>
                    </div>
                    <!-- Address -->
                    <div class="col-md-6">
                        <label for="address" class="form-label">Address <span class="text-muted">(Optional)</span></label>
                        <textarea class="form-control" name="address" id="address" rows="2"
                                  placeholder="Current residential address" maxlength="500"><?= htmlspecialchars($staff->address ?? '') ?></textarea>
                        <small class="form-text text-muted">Current residential address</small>
                    </div>
                </div>

                <div class="row mb-3">
                    <!-- Religion -->
                    <div class="col-md-4">
                        <label for="religion" class="form-label">Religion <span class="text-muted">(Optional)</span></label>
                        <select class="form-select" name="religion" id="religion">
                            <option value="">Select Religion</option>
                            <?php 
                            $religions = ['Christianity', 'Islam', 'Hinduism', 'Buddhism', 'Judaism', 'Traditional', 'Other', 'None'];
                            foreach ($religions as $religion): ?>
                                <option value="<?= htmlspecialchars($religion) ?>" 
                                        <?= (!empty($staff) && ($staff->religion ?? '') === $religion) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($religion) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small class="form-text text-muted">Religious affiliation</small>
                    </div>
                    <!-- Blood Group -->
                    <div class="col-md-4">
                        <label for="bloodGroup" class="form-label">Blood Group <span class="text-muted">(Optional)</span></label>
                        <select class="form-select" name="bloodGroup" id="bloodGroup">
                            <option value="">Select Blood Group</option>
                            <?php 
                            $bloodGroups = ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'];
                            foreach ($bloodGroups as $bg): ?>
                                <option value="<?= htmlspecialchars($bg) ?>" 
                                        <?= (!empty($staff) && ($staff->bloodGroup ?? $staff->bloodGp ?? '') === $bg) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($bg) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small class="form-text text-muted">Medical blood type</small>
                    </div>
                    <!-- Height -->
                    <div class="col-md-4">
                        <label for="height" class="form-label">Height (cm) <span class="text-muted">(Optional)</span></label>
                        <input type="number" class="form-control" name="height" id="height"
                               value="<?= htmlspecialchars($staff->height ?? '') ?>"
                               placeholder="170" min="120" max="250" step="0.1">
                        <small class="form-text text-muted">Height in centimeters</small>
                    </div>
                </div>



                <!-- Next of Kin Information -->
                <div class="alert alert-secondary">
                    <h6 class="alert-heading"><i class="fa fa-users"></i> Next of Kin Information</h6>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="nok" class="form-label">Next of Kin Name <span class="text-muted">(Optional)</span></label>
                            <input type="text" class="form-control" name="nok" id="nok"
                                   value="<?= htmlspecialchars($staff->nok ?? '') ?>"
                                   placeholder="Full name" maxlength="100">
                        </div>
                        <div class="col-md-6">
                            <label for="nokTel" class="form-label">Next of Kin Phone <span class="text-muted">(Optional)</span></label>
                            <input type="tel" class="form-control" name="nokTel" id="nokTel"
                                   value="<?= htmlspecialchars($staff->nokTel ?? '') ?>"
                                   placeholder="+260 XXX XXX XXX" maxlength="20">
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="nokNrc" class="form-label">Next of Kin NRC <span class="text-muted">(Optional)</span></label>
                            <input type="text" class="form-control" name="nokNrc" id="nokNrc"
                                   value="<?= htmlspecialchars($staff->nokNrc ?? '') ?>"
                                   placeholder="XX/XXXXXX/XX" maxlength="20">
                        </div>
                        <div class="col-md-6">
                            <label for="nokRelat" class="form-label">Relationship <span class="text-muted">(Optional)</span></label>
                            <select class="form-select" name="nokRelat" id="nokRelat">
                                <option value="">Select Relationship</option>
                                <?php 
                                $relationships = ['Parent', 'Spouse', 'Child', 'Sibling', 'Grandparent', 'Uncle', 'Aunt', 'Cousin', 'Friend', 'Other'];
                                foreach ($relationships as $rel): ?>
                                    <option value="<?= htmlspecialchars($rel) ?>" 
                                            <?= (!empty($staff) && ($staff->nokRelat ?? '') === $rel) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($rel) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Professional Information -->
                <div class="alert alert-info">
                    <h6 class="alert-heading"><i class="fa fa-briefcase"></i> Professional Information</h6>
                    <div class="row mb-3">
                        <div class="col-md-4">
                            <label for="profession" class="form-label">Profession <span class="text-muted">(Optional)</span></label>
                            <select class="form-select" name="profession" id="profession">
                                <option value="">Select Profession</option>
                                <?php 
                                $professions = [
                                    'Engineer', 'Doctor', 'Lawyer', 'Teacher', 'Accountant', 'Nurse', 'Pilot',
                                    'Mechanic', 'Electrician', 'Technician', 'Administrator', 'Manager',
                                    'Consultant', 'Analyst', 'Programmer', 'Designer', 'Architect',
                                    'Surveyor', 'Pharmacist', 'Veterinarian', 'Journalist', 'Translator',
                                    'Chef', 'Driver', 'Security Officer', 'Clerk', 'Supervisor'
                                ];
                                foreach ($professions as $profession): ?>
                                    <option value="<?= htmlspecialchars($profession) ?>" 
                                            <?= (!empty($staff) && ($staff->profession ?? '') === $profession) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($profession) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label for="trade" class="form-label">Trade <span class="text-muted">(Optional)</span></label>
                            <select class="form-select" name="trade" id="trade">
                                <option value="">Select Trade</option>
                                <?php 
                                $trades = [
                                    'Automotive Mechanic', 'Heavy Equipment Mechanic', 'Aircraft Mechanic',
                                    'Electrician', 'Electronics Technician', 'Telecommunications Technician',
                                    'Plumber', 'Welder', 'Machinist', 'Carpenter', 'Mason', 'Painter',
                                    'HVAC Technician', 'Refrigeration Technician', 'Generator Technician',
                                    'Radio Technician', 'Computer Technician', 'Network Technician',
                                    'Medical Technician', 'Laboratory Technician', 'X-Ray Technician',
                                    'Dental Technician', 'Pharmacy Technician', 'Cook', 'Baker',
                                    'Tailor', 'Barber', 'Armorer', 'Logistics Specialist'
                                ];
                                foreach ($trades as $trade): ?>
                                    <option value="<?= htmlspecialchars($trade) ?>" 
                                            <?= (!empty($staff) && ($staff->trade ?? '') === $trade) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($trade) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label for="specialization" class="form-label">Specialization <span class="text-muted">(Optional)</span></label>
                            <select class="form-select" name="specialization" id="specialization">
                                <option value="">Select Specialization</option>
                                <?php 
                                $specializations = [
                                    // Medical Specializations
                                    'General Medicine', 'Surgery', 'Pediatrics', 'Psychiatry', 'Cardiology',
                                    'Orthopedics', 'Emergency Medicine', 'Anesthesiology', 'Radiology',
                                    
                                    // Engineering Specializations
                                    'Civil Engineering', 'Mechanical Engineering', 'Electrical Engineering',
                                    'Electronics Engineering', 'Computer Engineering', 'Aerospace Engineering',
                                    'Communications Engineering', 'Environmental Engineering',
                                    
                                    // Military Specializations
                                    'Infantry Operations', 'Artillery Operations', 'Armor Operations',
                                    'Aviation Operations', 'Naval Operations', 'Special Forces',
                                    'Intelligence Analysis', 'Cyber Operations', 'Logistics Management',
                                    'Military Police', 'Combat Engineering', 'Signal Operations',
                                    
                                    // Technical Specializations
                                    'Information Technology', 'Cybersecurity', 'Database Management',
                                    'Network Administration', 'Software Development', 'Systems Analysis',
                                    'Quality Assurance', 'Project Management', 'Training & Development',
                                    
                                    // Other Specializations
                                    'Human Resources', 'Finance & Accounting', 'Legal Affairs',
                                    'Public Relations', 'Administration', 'Procurement', 'Security Management'
                                ];
                                foreach ($specializations as $specialization): ?>
                                    <option value="<?= htmlspecialchars($specialization) ?>" 
                                            <?= (!empty($staff) && ($staff->specialization ?? '') === $specialization) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($specialization) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Step Navigation Buttons -->
        <div class="d-flex justify-content-between mt-4">
            <button type="button" class="btn btn-secondary" disabled>Previous</button>
            <button type="button" class="btn btn-primary" onclick="nextStep(2)">Next Step</button>
        </div>
    </div>

    <!-- Military Details - Step 2 -->
    <div class="form-step" id="step2">
        <div class="card mb-4">
            <div class="card-header bg-dark text-white">
                <h5 class="mb-0"><i class="fa fa-shield-alt"></i> Military Details</h5>
            </div>
            <div class="card-body">
                            <div class="alert alert-info mb-3">
                                <i class="fa fa-info-circle"></i> 
                                <strong>Uniform & Equipment Sizing:</strong> These sizes are used for uniform and equipment allocation.
                            </div>
                            <div class="row mb-3">
                                <div class="col-md-3">
                                    <label for="combatSize" class="form-label">Combat Size <span class="text-muted">(Optional)</span></label>
                                    <select class="form-select" name="combatSize" id="combatSize" 
                                            title="Combat uniform size for protective gear and field uniforms">
                                        <option value="">Select Size</option>
                                        <?php 
                                        $combatSizes = [
                                            'XS' => 'Extra Small (XS)',
                                            'S' => 'Small (S)', 
                                            'M' => 'Medium (M)',
                                            'L' => 'Large (L)',
                                            'XL' => 'Extra Large (XL)',
                                            'XXL' => '2X Large (XXL)',
                                            '3XL' => '3X Large (3XL)',
                                            '4XL' => '4X Large (4XL)'
                                        ];
                                        foreach ($combatSizes as $value => $label): ?>
                                            <option value="<?= htmlspecialchars($value) ?>" 
                                                    <?= (!empty($staff) && ($staff->combatSize ?? '') === $value) ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($label) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label for="bsize" class="form-label">Boot Size <span class="text-muted">(Optional)</span></label>
                                    <select class="form-select" name="bsize" id="bsize" 
                                            title="Military boot size for protective and combat footwear">
                                        <option value="">Select Size</option>
                                        <?php 
                                        for ($i = 4; $i <= 15; $i++): ?>
                                            <option value="<?= $i ?>" 
                                                    <?= (!empty($staff) && ($staff->bsize ?? '') == $i) ? 'selected' : '' ?>>
                                                <?= $i ?>
                                            </option>
                                        <?php endfor; ?>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label for="ssize" class="form-label">Staff Shoe Size <span class="text-muted">(Optional)</span></label>
                                    <select class="form-select" name="ssize" id="ssize" 
                                            title="Dress shoe size for formal and ceremonial footwear">
                                        <option value="">Select Size</option>
                                        <?php 
                                        for ($i = 4; $i <= 15; $i++): ?>
                                            <option value="<?= $i ?>" 
                                                    <?= (!empty($staff) && ($staff->ssize ?? '') == $i) ? 'selected' : '' ?>>
                                                <?= $i ?>
                                            </option>
                                        <?php endfor; ?>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label for="hdress" class="form-label">Headdress Size <span class="text-muted">(Optional)</span></label>
                                    <select class="form-select" name="hdress" id="hdress" 
                                            title="Head dress size for berets, caps, and ceremonial headwear">
                                        <option value="">Select Size</option>
                                        <?php 
                                        for ($i = 52; $i <= 65; $i++): ?>
                                            <option value="<?= $i ?>" 
                                                    <?= (!empty($staff) && ($staff->hdress ?? '') == $i) ? 'selected' : '' ?>>
                                                <?= $i ?>
                                            </option>
                                        <?php endfor; ?>
                                    </select>
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
                    
                    <!-- Step Navigation Buttons -->
                    <div class="d-flex justify-content-between mt-4">
                        <button type="button" class="btn btn-secondary" onclick="previousStep(1)">Previous</button>
                        <button type="button" class="btn btn-primary" onclick="nextStep(3)">Next Step</button>
                    </div>
                </div>

                <!-- Operations - Step 3 -->
                <div class="form-step" id="step3">
                    <div class="card mb-4">
                        <div class="card-header bg-secondary text-white">
                            <h5 class="mb-0"><i class="fa fa-tasks"></i> Operations</h5>
                        </div>
                        <div class="card-body">
                            <div class="alert alert-info">
                                <i class="fa fa-info-circle"></i> Add military operations this staff member has participated in. This includes combat operations, peacekeeping missions, training exercises, etc.
                            </div>
                            <div class="mb-3">
                                <small class="text-muted">
                                    <strong>Expected fields:</strong> Operation Name, Role, Location, Start/End Dates, Status, and Remarks
                                </small>
                            </div>
                            <div id="operationsList">
                                <!-- Dynamic operations will be added here -->
                            </div>
                            <button type="button" class="btn btn-outline-primary" id="addOperationBtn">
                                <i class="fa fa-plus"></i> Add Operation
                            </button>
                        </div>
                    </div>
                    
                    <!-- Step Navigation Buttons -->
                    <div class="d-flex justify-content-between mt-4">
                        <button type="button" class="btn btn-secondary" onclick="previousStep(2)">Previous</button>
                        <button type="button" class="btn btn-primary" onclick="nextStep(4)">Next Step</button>
                    </div>
                </div>

                <!-- Deployments - Step 4 -->
                <div class="form-step" id="step4">
                    <div class="card mb-4">
                        <div class="card-header bg-info text-white">
                            <h5 class="mb-0"><i class="fa fa-plane"></i> Deployments</h5>
                        </div>
                        <div class="card-body">
                            <div class="alert alert-info">
                                <i class="fa fa-info-circle"></i> Add deployments and overseas assignments this staff member has been assigned to. Include peacekeeping missions, joint operations, and extended postings.
                            </div>
                            <div class="mb-3">
                                <small class="text-muted">
                                    <strong>Expected fields:</strong> Deployment Name, Location, Country, Duration, Role, Status, and Notes
                                </small>
                            </div>
                            <div id="deploymentsList">
                                <!-- Dynamic deployments will be added here -->
                            </div>
                            <button type="button" class="btn btn-outline-primary" id="addDeploymentBtn">
                                <i class="fa fa-plus"></i> Add Deployment
                            </button>
                        </div>
                    </div>
                    
                    <!-- Step Navigation Buttons -->
                    <div class="d-flex justify-content-between mt-4">
                        <button type="button" class="btn btn-secondary" onclick="previousStep(3)">Previous</button>
                        <button type="button" class="btn btn-primary" onclick="nextStep(5)">Next Step</button>
                    </div>
                </div>

                <!-- Education & Skills - Step 5 -->
                <div class="form-step" id="step5">
                    <div class="card mb-4">
                        <div class="card-header bg-success text-white">
                            <h5 class="mb-0"><i class="fa fa-graduation-cap"></i> Education</h5>
                        </div>
                        <div class="card-body">
                            <div class="alert alert-info">
                                <i class="fa fa-info-circle"></i> Add formal education qualifications including degrees, diplomas, certificates, and military academy training.
                            </div>
                            <div class="mb-3">
                                <small class="text-muted">
                                    <strong>Expected fields:</strong> Institution, Qualification, Field of Study, Years, Grade, and Highest Qualification flag
                                </small>
                            </div>
                            <div id="educationList">
                                <!-- Dynamic education records will be added here -->
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
                                <i class="fa fa-info-circle"></i> Add professional skills, training courses, certifications, and specialized military training completed by this staff member.
                            </div>
                            <div class="mb-3">
                                <small class="text-muted">
                                    <strong>Expected fields:</strong> Course/Skill Name, Type, Duration, Dates, and Certification Status
                                </small>
                            </div>
                            <div id="skillsList">
                                <!-- Dynamic skills/courses will be added here -->
                            </div>
                            <button type="button" class="btn btn-outline-primary" id="addSkillBtn">
                                <i class="fa fa-plus"></i> Add Skill/Course
                            </button>
                        </div>
                    </div>

                    <!-- Final Step Navigation Buttons -->
                    <div class="d-flex justify-content-between mt-4">
                        <button type="button" class="btn btn-secondary" onclick="previousStep(4)">Previous</button>
                        <button type="submit" class="btn btn-success">
                            <i class="fa fa-save"></i> Update Staff Record
                        </button>
                    </div>
                </div>
                    
                    <div class="alert alert-info mt-3">
                        <p><i class="fa fa-info-circle"></i> <strong>Form Submission Tip:</strong> If the form isn't saving properly, navigate through all steps to ensure all required fields are completed.</p>
                    </div>
                </form>
                <?php endif; ?>
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
    // Global counters for dynamic fields
    let operationCounter = 0;
    let deploymentCounter = 0; 
    let educationCounter = 0;
    let skillCounter = 0;

    // Dynamic field functions
    function addOperationRow(data = {}) {
        const container = document.getElementById('operationsList');
        const index = operationCounter++;
        const html = `
            <div class="card mb-3 operation-item" data-index="${index}">
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Operation Name</label>
                            <input type="text" name="operations[${index}][operation_name]" class="form-control" 
                                   placeholder="Operation name" value="${data.operation_name || ''}" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Role</label>
                            <input type="text" name="operations[${index}][role]" class="form-control" 
                                   placeholder="Your role" value="${data.role || ''}">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Location</label>
                            <input type="text" name="operations[${index}][location]" class="form-control" 
                                   placeholder="Operation location" value="${data.location || ''}">
                        </div>
                    </div>
                    <div class="row g-3 mt-2">
                        <div class="col-md-3">
                            <label class="form-label">Start Date</label>
                            <input type="date" name="operations[${index}][start_date]" class="form-control" 
                                   value="${data.start_date || ''}">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">End Date</label>
                            <input type="date" name="operations[${index}][end_date]" class="form-control" 
                                   value="${data.end_date || ''}">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Status</label>
                            <select name="operations[${index}][status]" class="form-select">
                                <option value="">Select Status</option>
                                <option value="Active" ${data.status === 'Active' ? 'selected' : ''}>Active</option>
                                <option value="Completed" ${data.status === 'Completed' ? 'selected' : ''}>Completed</option>
                                <option value="Pending" ${data.status === 'Pending' ? 'selected' : ''}>Pending</option>
                            </select>
                        </div>
                        <div class="col-md-3 d-flex align-items-end">
                            <button type="button" class="btn btn-danger w-100" onclick="removeOperationRow(${index})">
                                <i class="fa fa-trash"></i> Remove
                            </button>
                        </div>
                    </div>
                    <div class="row g-3 mt-2">
                        <div class="col-md-12">
                            <label class="form-label">Remarks</label>
                            <textarea name="operations[${index}][remarks]" class="form-control" rows="2" 
                                      placeholder="Additional notes or remarks">${data.remarks || ''}</textarea>
                        </div>
                    </div>
                </div>
            </div>
        `;
        container.insertAdjacentHTML('beforeend', html);
        console.log('🎯 Added operation row #' + index);
    }

    function addDeploymentRow(data = {}) {
        const container = document.getElementById('deploymentsList');
        const index = deploymentCounter++;
        const html = `
            <div class="card mb-3 deployment-item" data-index="${index}">
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-3">
                            <label class="form-label">Deployment Name</label>
                            <input type="text" name="deployments[${index}][deployment_name]" class="form-control" 
                                   placeholder="Deployment name" value="${data.deployment_name || ''}" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Mission Type</label>
                            <select name="deployments[${index}][mission_type]" class="form-select">
                                <option value="">Select Mission Type</option>
                                <option value="Peacekeeping" ${data.mission_type === 'Peacekeeping' ? 'selected' : ''}>Peacekeeping</option>
                                <option value="Combat" ${data.mission_type === 'Combat' ? 'selected' : ''}>Combat</option>
                                <option value="Training" ${data.mission_type === 'Training' ? 'selected' : ''}>Training</option>
                                <option value="Humanitarian" ${data.mission_type === 'Humanitarian' ? 'selected' : ''}>Humanitarian</option>
                                <option value="Support" ${data.mission_type === 'Support' ? 'selected' : ''}>Support</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Location</label>
                            <input type="text" name="deployments[${index}][location]" class="form-control" 
                                   placeholder="Deployment location" value="${data.location || ''}">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Country</label>
                            <input type="text" name="deployments[${index}][country]" class="form-control" 
                                   placeholder="Country" value="${data.country || ''}">
                        </div>
                    </div>
                    <div class="row g-3 mt-2">
                        <div class="col-md-3">
                            <label class="form-label">Start Date</label>
                            <input type="date" name="deployments[${index}][start_date]" class="form-control" 
                                   value="${data.start_date || ''}">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">End Date</label>
                            <input type="date" name="deployments[${index}][end_date]" class="form-control" 
                                   value="${data.end_date || ''}">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Duration (Months)</label>
                            <input type="number" name="deployments[${index}][duration_months]" class="form-control" 
                                   placeholder="Duration" value="${data.duration_months || ''}" min="0">
                        </div>
                        <div class="col-md-3 d-flex align-items-end">
                            <button type="button" class="btn btn-danger w-100" onclick="removeDeploymentRow(${index})">
                                <i class="fa fa-trash"></i> Remove
                            </button>
                        </div>
                    </div>
                    <div class="row g-3 mt-2">
                        <div class="col-md-4">
                            <label class="form-label">Role During Deployment</label>
                            <input type="text" name="deployments[${index}][role_during_deployment]" class="form-control" 
                                   placeholder="Role during deployment" value="${data.role_during_deployment || data.role || ''}">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Rank During Deployment</label>
                            <input type="text" name="deployments[${index}][rank_during_deployment]" class="form-control" 
                                   placeholder="Rank during deployment" value="${data.rank_during_deployment || ''}">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Status</label>
                            <select name="deployments[${index}][deployment_status]" class="form-select">
                                <option value="">Select Status</option>
                                <option value="Active" ${data.deployment_status === 'Active' ? 'selected' : ''}>Active</option>
                                <option value="Completed" ${data.deployment_status === 'Completed' ? 'selected' : ''}>Completed</option>
                                <option value="Upcoming" ${data.deployment_status === 'Upcoming' ? 'selected' : ''}>Upcoming</option>
                            </select>
                        </div>
                    </div>
                    <div class="row g-3 mt-2">
                        <div class="col-md-6">
                            <label class="form-label">Commanding Officer</label>
                            <input type="text" name="deployments[${index}][commanding_officer]" class="form-control" 
                                   placeholder="Commanding officer" value="${data.commanding_officer || ''}">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Deployment Allowance</label>
                            <input type="number" name="deployments[${index}][deployment_allowance]" class="form-control" 
                                   placeholder="Allowance amount" value="${data.deployment_allowance || ''}" step="0.01">
                        </div>
                    </div>
                    <div class="row g-3 mt-2">
                        <div class="col-md-12">
                            <label class="form-label">Notes</label>
                            <textarea name="deployments[${index}][notes]" class="form-control" rows="2" 
                                      placeholder="Additional deployment notes">${data.notes || ''}</textarea>
                        </div>
                    </div>
                </div>
            </div>
        `;
        container.insertAdjacentHTML('beforeend', html);
        console.log('✈️ Added deployment row #' + index);
    }

    function addEducationRow(data = {}) {
        const container = document.getElementById('educationList');
        const index = educationCounter++;
        const html = `
            <div class="card mb-3 education-item" data-index="${index}">
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Institution</label>
                            <input type="text" name="education[${index}][institution]" class="form-control" 
                                   placeholder="Educational institution" value="${data.institution || ''}" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Qualification</label>
                            <select name="education[${index}][qualification]" class="form-select">
                                <option value="">Select Qualification</option>
                                <option value="Certificate" ${data.qualification === 'Certificate' ? 'selected' : ''}>Certificate</option>
                                <option value="Diploma" ${data.qualification === 'Diploma' ? 'selected' : ''}>Diploma</option>
                                <option value="Associate Degree" ${data.qualification === 'Associate Degree' ? 'selected' : ''}>Associate Degree</option>
                                <option value="Bachelor's Degree" ${data.qualification === 'Bachelor\'s Degree' ? 'selected' : ''}>Bachelor's Degree</option>
                                <option value="Master's Degree" ${data.qualification === 'Master\'s Degree' ? 'selected' : ''}>Master's Degree</option>
                                <option value="Doctorate/PhD" ${data.qualification === 'Doctorate/PhD' ? 'selected' : ''}>Doctorate/PhD</option>
                                <option value="Professional Certification" ${data.qualification === 'Professional Certification' ? 'selected' : ''}>Professional Certification</option>
                                <option value="Military Training" ${data.qualification === 'Military Training' ? 'selected' : ''}>Military Training</option>
                                <option value="Other" ${data.qualification === 'Other' ? 'selected' : ''}>Other</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Field of Study</label>
                            <select name="education[${index}][field_of_study]" class="form-select">
                                <option value="">Select Field of Study</option>
                                <option value="Engineering" ${data.field_of_study === 'Engineering' ? 'selected' : ''}>Engineering</option>
                                <option value="Computer Science" ${data.field_of_study === 'Computer Science' ? 'selected' : ''}>Computer Science</option>
                                <option value="Information Technology" ${data.field_of_study === 'Information Technology' ? 'selected' : ''}>Information Technology</option>
                                <option value="Business Administration" ${data.field_of_study === 'Business Administration' ? 'selected' : ''}>Business Administration</option>
                                <option value="Management" ${data.field_of_study === 'Management' ? 'selected' : ''}>Management</option>
                                <option value="Finance" ${data.field_of_study === 'Finance' ? 'selected' : ''}>Finance</option>
                                <option value="Accounting" ${data.field_of_study === 'Accounting' ? 'selected' : ''}>Accounting</option>
                                <option value="Law" ${data.field_of_study === 'Law' ? 'selected' : ''}>Law</option>
                                <option value="Medicine" ${data.field_of_study === 'Medicine' ? 'selected' : ''}>Medicine</option>
                                <option value="Nursing" ${data.field_of_study === 'Nursing' ? 'selected' : ''}>Nursing</option>
                                <option value="Education" ${data.field_of_study === 'Education' ? 'selected' : ''}>Education</option>
                                <option value="Psychology" ${data.field_of_study === 'Psychology' ? 'selected' : ''}>Psychology</option>
                                <option value="Military Science" ${data.field_of_study === 'Military Science' ? 'selected' : ''}>Military Science</option>
                                <option value="International Relations" ${data.field_of_study === 'International Relations' ? 'selected' : ''}>International Relations</option>
                                <option value="Security Studies" ${data.field_of_study === 'Security Studies' ? 'selected' : ''}>Security Studies</option>
                                <option value="Communications" ${data.field_of_study === 'Communications' ? 'selected' : ''}>Communications</option>
                                <option value="Languages" ${data.field_of_study === 'Languages' ? 'selected' : ''}>Languages</option>
                                <option value="Mathematics" ${data.field_of_study === 'Mathematics' ? 'selected' : ''}>Mathematics</option>
                                <option value="Physics" ${data.field_of_study === 'Physics' ? 'selected' : ''}>Physics</option>
                                <option value="Chemistry" ${data.field_of_study === 'Chemistry' ? 'selected' : ''}>Chemistry</option>
                                <option value="Biology" ${data.field_of_study === 'Biology' ? 'selected' : ''}>Biology</option>
                                <option value="Social Sciences" ${data.field_of_study === 'Social Sciences' ? 'selected' : ''}>Social Sciences</option>
                                <option value="Other" ${data.field_of_study === 'Other' ? 'selected' : ''}>Other</option>
                            </select>
                        </div>
                    </div>
                    <div class="row g-3 mt-2">
                        <div class="col-md-4">
                            <label class="form-label">Start Year</label>
                            <input type="number" name="education[${index}][year_started]" class="form-control" 
                                   placeholder="2020" value="${data.year_started || ''}" min="1900" max="2030">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">End Year</label>
                            <input type="number" name="education[${index}][year_completed]" class="form-control" 
                                   placeholder="2024" value="${data.year_completed || ''}" min="1900" max="2030">
                        </div>
                        <div class="col-md-4 d-flex align-items-end">
                            <button type="button" class="btn btn-danger w-100" onclick="removeEducationRow(${index})">
                                <i class="fa fa-trash"></i> Remove
                            </button>
                        </div>
                    </div>
                    <div class="row g-3 mt-2">
                        <div class="col-md-10">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="education[${index}][is_highest_qualification]" 
                                       value="1" id="highest_${index}" ${data.is_highest_qualification ? 'checked' : ''}>
                                <label class="form-check-label" for="highest_${index}">
                                    This is my highest qualification
                                </label>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        `;
        container.insertAdjacentHTML('beforeend', html);
        console.log('🎓 Added education row #' + index);
    }

    function addSkillRow(data = {}) {
        const container = document.getElementById('skillsList');
        const index = skillCounter++;
        const html = `
            <div class="card mb-3 skill-item" data-index="${index}">
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Course/Skill Name</label>
                            <input type="text" name="skills[${index}][course_name]" class="form-control" 
                                   placeholder="Course or skill name" value="${data.course_name || ''}" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Course Type</label>
                            <select name="skills[${index}][course_type]" class="form-select">
                                <option value="">Select Type</option>
                                <option value="Military Training" ${data.course_type === 'Military Training' ? 'selected' : ''}>Military Training</option>
                                <option value="Technical Skills" ${data.course_type === 'Technical Skills' ? 'selected' : ''}>Technical Skills</option>
                                <option value="Leadership" ${data.course_type === 'Leadership' ? 'selected' : ''}>Leadership</option>
                                <option value="Certification" ${data.course_type === 'Certification' ? 'selected' : ''}>Certification</option>
                                <option value="Other" ${data.course_type === 'Other' ? 'selected' : ''}>Other</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Duration (Days)</label>
                            <input type="number" name="skills[${index}][duration_days]" class="form-control" 
                                   placeholder="Duration" value="${data.duration_days || ''}" min="0">
                        </div>
                    </div>
                    <div class="row g-3 mt-2">
                        <div class="col-md-4">
                            <label class="form-label">Institution</label>
                            <input type="text" name="skills[${index}][institution]" class="form-control" 
                                   placeholder="Training institution" value="${data.institution || ''}">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Start Date</label>
                            <input type="date" name="skills[${index}][start_date]" class="form-control" 
                                   value="${data.start_date || ''}">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">End Date</label>
                            <input type="date" name="skills[${index}][end_date]" class="form-control" 
                                   value="${data.end_date || ''}">
                        </div>
                    </div>
                    <div class="row g-3 mt-2">
                        <div class="col-md-3">
                            <label class="form-label">Certificate Number</label>
                            <input type="text" name="skills[${index}][certificate_number]" class="form-control" 
                                   placeholder="Certificate #" value="${data.certificate_number || ''}">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Grade Obtained</label>
                            <input type="text" name="skills[${index}][grade_obtained]" class="form-control" 
                                   placeholder="Grade/Score" value="${data.grade_obtained || ''}">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Location</label>
                            <input type="text" name="skills[${index}][location]" class="form-control" 
                                   placeholder="Training location" value="${data.location || ''}">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Certification Status</label>
                            <select name="skills[${index}][certification_status]" class="form-select">
                                <option value="">Select Status</option>
                                <option value="Certified" ${data.certification_status === 'Certified' ? 'selected' : ''}>Certified</option>
                                <option value="In Progress" ${data.certification_status === 'In Progress' ? 'selected' : ''}>In Progress</option>
                                <option value="Expired" ${data.certification_status === 'Expired' ? 'selected' : ''}>Expired</option>
                            </select>
                        </div>
                    </div>
                    <div class="row g-3 mt-2">
                        <div class="col-md-4">
                            <label class="form-label">Cost</label>
                            <input type="number" name="skills[${index}][cost]" class="form-control" 
                                   placeholder="Training cost" value="${data.cost || ''}" step="0.01">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Sponsored By</label>
                            <input type="text" name="skills[${index}][sponsored_by]" class="form-control" 
                                   placeholder="Sponsoring organization" value="${data.sponsored_by || ''}">
                        </div>
                        <div class="col-md-4 d-flex align-items-end">
                            <button type="button" class="btn btn-danger w-100" onclick="removeSkillRow(${index})">
                                <i class="fa fa-trash"></i> Remove
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        `;
        container.insertAdjacentHTML('beforeend', html);
        console.log('🛠️ Added skill row #' + index);
    }

    // Remove functions
    function removeOperationRow(index) {
        const item = document.querySelector(`.operation-item[data-index="${index}"]`);
        if (item && confirm('Are you sure you want to remove this operation?')) {
            item.remove();
            console.log('🗑️ Removed operation row #' + index);
        }
    }

    function removeDeploymentRow(index) {
        const item = document.querySelector(`.deployment-item[data-index="${index}"]`);
        if (item && confirm('Are you sure you want to remove this deployment?')) {
            item.remove();
            console.log('🗑️ Removed deployment row #' + index);
        }
    }

    function removeEducationRow(index) {
        const item = document.querySelector(`.education-item[data-index="${index}"]`);
        if (item && confirm('Are you sure you want to remove this education record?')) {
            item.remove();
            console.log('🗑️ Removed education row #' + index);
        }
    }

    function removeSkillRow(index) {
        const item = document.querySelector(`.skill-item[data-index="${index}"]`);
        if (item && confirm('Are you sure you want to remove this skill/course?')) {
            item.remove();
            console.log('🗑️ Removed skill row #' + index);
        }
    }

    // Function to load existing data when editing a staff member
    function loadExistingData() {
        const svcNo = '<?= htmlspecialchars($staff->service_number ?? '') ?>';
        if (svcNo) {
            // Load existing operations, deployments, education, and skills
            console.log('🔄 Loading existing data for service number:', svcNo);
            
            // For now, we'll add sample data - in production this would come from the database
            // This can be populated from PHP when the page loads
        }
    }

    // Function to validate dynamic fields before form submission
    function validateDynamicFields() {
        let isValid = true;
        const errors = [];

        // Validate operations
        const operations = document.querySelectorAll('#operationsList .operation-item');
        operations.forEach((op, index) => {
            const nameField = op.querySelector('input[name*="[operation_name]"]');
            if (nameField && !nameField.value.trim()) {
                nameField.classList.add('is-invalid');
                errors.push(`Operation #${index + 1}: Name is required`);
                isValid = false;
            }
        });

        // Validate deployments
        const deployments = document.querySelectorAll('#deploymentsList .deployment-item');
        deployments.forEach((dep, index) => {
            const nameField = dep.querySelector('input[name*="[deployment_name]"]');
            if (nameField && !nameField.value.trim()) {
                nameField.classList.add('is-invalid');
                errors.push(`Deployment #${index + 1}: Name is required`);
                isValid = false;
            }
        });

        // Validate education
        const education = document.querySelectorAll('#educationList .education-item');
        education.forEach((edu, index) => {
            const institutionField = edu.querySelector('input[name*="[institution]"]');
            if (institutionField && !institutionField.value.trim()) {
                institutionField.classList.add('is-invalid');
                errors.push(`Education #${index + 1}: Institution is required`);
                isValid = false;
            }
        });

        // Validate skills
        const skills = document.querySelectorAll('#skillsList .skill-item');
        skills.forEach((skill, index) => {
            const nameField = skill.querySelector('input[name*="[course_name]"]');
            if (nameField && !nameField.value.trim()) {
                nameField.classList.add('is-invalid');
                errors.push(`Skill/Course #${index + 1}: Name is required`);
                isValid = false;
            }
        });

        if (!isValid) {
            console.error('❌ Dynamic field validation errors:', errors);
            alert('Please fix the following errors in the dynamic fields:\n\n' + errors.join('\n'));
        }

        return isValid;
    }

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

    // Load existing data when page loads
    loadExistingData();

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
            // Check if we have a valid staff member selected
            const staffId = document.querySelector('input[name="staff_id"]');
            if (!staffId || !staffId.value) {
                e.preventDefault();
                alert('Error: No staff member selected. Please select a staff member first.');
                return false;
            }
            
            if (!validateForm() || !validateDynamicFields()) {
                e.preventDefault();
                alert('Please fix the validation errors before submitting');
                window.scrollTo(0, 0);
                return false;
            }
            
            // Show confirmation with counts
            const opCount = document.querySelectorAll('#operationsList .operation-item').length;
            const depCount = document.querySelectorAll('#deploymentsList .deployment-item').length;
            const eduCount = document.querySelectorAll('#educationList .education-item').length;
            const skillCount = document.querySelectorAll('#skillsList .skill-item').length;
            
            const summary = `You are about to update this staff member with:
• ${opCount} operation(s)
• ${depCount} deployment(s)  
• ${eduCount} education record(s)
• ${skillCount} skill/course(s)

Are you sure you want to proceed?`;
            
            if (!confirm(summary)) {
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

    // Size field validation and user experience improvements
    const sizeFields = {
        combatSize: document.getElementById('combatSize'),
        bsize: document.getElementById('bsize'),
        ssize: document.getElementById('ssize'),
        hdress: document.getElementById('hdress')
    };

    // Add change event listeners for size fields
    Object.keys(sizeFields).forEach(fieldName => {
        const field = sizeFields[fieldName];
        if (field) {
            field.addEventListener('change', function() {
                // Add visual feedback for selection
                if (this.value) {
                    this.classList.add('border-success');
                    this.classList.remove('border-warning');
                } else {
                    this.classList.remove('border-success');
                    this.classList.add('border-warning');
                }
                
                // Log size selection for audit purposes
                console.log(`📏 Size updated: ${fieldName} = ${this.value}`);
            });

            // Initialize visual state
            if (field.value) {
                field.classList.add('border-success');
            }
        }
    });

    // Validate size consistency (optional validation)
    function validateSizeConsistency() {
        const bootSize = parseInt(sizeFields.bsize?.value || '0');
        const shoeSize = parseInt(sizeFields.ssize?.value || '0');
        
        if (bootSize > 0 && shoeSize > 0) {
            const sizeDifference = Math.abs(bootSize - shoeSize);
            if (sizeDifference > 2) {
                console.warn('⚠️ Boot size and shoe size differ significantly. Please verify.');
            }
        }
    }

    // Add size consistency check when both sizes are selected
    ['bsize', 'ssize'].forEach(fieldName => {
        const field = sizeFields[fieldName];
        if (field) {
            field.addEventListener('change', validateSizeConsistency);
        }
    });
});
</script>
<?php include dirname(__DIR__) . '/shared/footer.php'; ?>
