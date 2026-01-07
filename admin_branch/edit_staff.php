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
require_once dirname(__DIR__) . '/shared/rank_levels.php';
require_once dirname(__DIR__) . '/shared/rank_levels.php';

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
    // Zambian NRC format: XXXXXX/XX/X
    if (!preg_match('/^[0-9]{6}\/[0-9]{2}\/[0-9]$/', $nrc)) {
        return "NRC format should be XXXXXX/XX/X (e.g., 123456/12/1)";
    }
    return null;
}

function validatePhone($phone) {
    if (empty($phone)) return null; // Phone is optional
    $phone = trim($phone);
    // Remove common formatting characters
    $cleanPhone = preg_replace('/[\s\-\(\)]/', '', $phone);
    // Zambian phone format: +260 followed by 9 digits OR just 09/07 followed by 8 digits
    if (!preg_match('/^(\+260|0)(9|7)[0-9]{8}$/', $cleanPhone)) {
        return "Invalid phone number format. Use +260 XXX XXX XXX or 09XX XXX XXX";
    }
    return null;
}

function validateEmail($email) {
    if (empty($email)) return null; // Email is optional
    $email = trim($email);
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return "Invalid email address format";
    }
    // Additional check for common typos
    if (!preg_match('/^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/', $email)) {
        return "Email address format is invalid";
    }
    return null;
}

function validateHeight($height) {
    if (empty($height)) return null; // Height is optional
    $height = floatval($height);
    if ($height < 120 || $height > 250) {
        return "Height must be between 120cm and 250cm";
    }
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

function logStaffChange($svcNo, $oldData, $newData, $userId, $userIP, $pdo = null) {
    $changes = [];
    $fields = ['fName', 'lName', 'rankId', 'unitId', 'corpsId', 'NRC', 'DOB', 'gender', 'svcStatus'];
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
            // Use provided PDO connection or get a new one
            if ($pdo === null) {
                $pdo = getDbConnection();
            }
            
            // Insert log record (table should already exist - created before transaction)
            $stmt = $pdo->prepare("INSERT INTO staff_edit_log (svcNo, edited_by, edited_at, user_ip, changes) VALUES (?, ?, ?, ?, ?)");
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
    $sql = "SELECT s.svcNo, s.fName, s.lName, r.rankId as rank_name, u.code as unit_name, 
             s.svcStatus, s.DOB, s.attestDate, s.category 
         FROM staff s
         LEFT JOIN `rank` r ON s.rankId = r.rankId
         LEFT JOIN unit u ON s.unitId = u.unitId
         WHERE 1=1";
    
    $params = [];
    
    // Apply filters if set
    $rankFilter = $_GET['rank'] ?? '';
    $unitFilter = $_GET['unit'] ?? '';
    $statusFilter = $_GET['status'] ?? '';
    $search = trim($_GET['search'] ?? '');
    
    if ($rankFilter !== '') {
        $sql .= " AND s.rankId = ?";
        $params[] = $rankFilter;
    }
    
    if ($unitFilter !== '') {
        $sql .= " AND s.unitId = ?";
        $params[] = $unitFilter;
    }
    
    if ($statusFilter !== '') {
        $sql .= " AND s.svcStatus = ?";
        $params[] = $statusFilter;
    }
    
    if ($search !== '') {
        $sql .= " AND (s.svcNo LIKE ? OR s.fName LIKE ? OR s.lName LIKE ?)";
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
        s.svcNo ASC,
        s.DOB ASC
    ";

    // Log missing/inconsistent data for audit
    $auditStmt = $pdo->query("SELECT svcNo, subWef, tempWef, attestDate FROM staff WHERE (subWef IS NULL OR subWef = '' OR subWef < '1900-01-01') OR (temWef IS NULL OR temWef = '' OR temWef < '1900-01-01') OR (attestDate IS NULL OR attestDate = '' OR attestDate < '1900-01-01')");
    $auditRows = $auditStmt->fetchAll(PDO::FETCH_ASSOC);
    if (!empty($auditRows)) {
        error_log('Staff seniority audit: Missing/inconsistent date fields: ' . json_encode($auditRows));
    }
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    
    // Export all rows
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        fputcsv($output, [
            $row['svcNo'],
            $row['fName'],
            $row['lName'],
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
    $debug = [];
    $debug[] = 'AJAX handler entered';
    $pdo = getDbConnection();
    try {
        $pdo = getDbConnection();
        // Handle audit logging for search
        if (isset($_GET['log_search']) && $_GET['log_search'] === '1') {
            $debug[] = 'log_search branch entered';
            $requestData = json_decode(file_get_contents('php://input'), true);
            $logData = [
                'user_id' => $user->data()->id ?? 0,
                'action' => 'staff_search',
                'search_terms' => json_encode($requestData),
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '',
                'createdAt' => date('Y-m-d H:i:s')
            ];
            try {
                $stmt = $pdo->prepare("INSERT INTO activity_log (user_id, action, details, ip_address, createdAt) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$logData['user_id'], $logData['action'], $logData['search_terms'], $logData['ip_address'], $logData['createdAt']]);
            } catch (Exception $e) {
                error_log("Failed to log search activity: " . $e->getMessage());
            }
            echo json_encode(['success' => true, 'debug' => $debug, 'branch' => 'log_search exit']);
            exit;
        }
        // Handle audit logging for viewing staff
        if (isset($_GET['audit_view']) && $_GET['audit_view'] === '1' && isset($_GET['svcNo'])) {
            $debug[] = 'audit_view branch entered';
            $staffId = $_GET['svcNo'];
            $logData = [
                'user_id' => $user->data()->id ?? 0,
                'action' => 'staff_view',
                'details' => json_encode(['svcNo' => $staffId]),
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '',
                'createdAt' => date('Y-m-d H:i:s')
            ];
            try {
                $stmt = $pdo->prepare("INSERT INTO activity_log (user_id, action, details, ip_address, createdAt) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$logData['user_id'], $logData['action'], $logData['details'], $logData['ip_address'], $logData['createdAt']]);
            } catch (Exception $e) {
                error_log("Failed to log view activity: " . $e->getMessage());
            }
            echo json_encode(['success' => true, 'debug' => $debug, 'branch' => 'audit_view exit']);
            exit;
        }
        // Perform staff search with pagination and sorting like reports_seniority.php
        $debug[] = 'staff search branch entered';
    $ranks = $pdo->query("SELECT rankId as rankID, rankId as rankName, level FROM `rank` ORDER BY level ASC")->fetchAll(PDO::FETCH_OBJ);
    $rankMap = [];
    foreach ($ranks as $r) $rankMap[$r->rankID] = $r->rankName;
        $units = $pdo->query("SELECT unitId as unitID, code as unitName FROM unit ORDER BY code ASC")->fetchAll(PDO::FETCH_OBJ);
        $unitMap = [];
        foreach ($units as $u) $unitMap[$u->unitID] = $u->unitName;
        $search = trim($_GET['search'] ?? '');
        $rankFilter = $_GET['rank'] ?? '';
        $unitFilter = $_GET['unit'] ?? '';
        $statusFilter = $_GET['status'] ?? '';
        $excludeInactive = isset($_GET['exclude_inactive']) && $_GET['exclude_inactive'] === '1';
        // Pagination parameters (matching reports_seniority.php)
        $per_page = intval($_GET['per_page'] ?? 25);
        $page = max(1, intval($_GET['page'] ?? 1));
        $offset = ($page - 1) * $per_page;
        // Sorting parameters (matching reports_seniority.php)
        $sortable_columns = [
            'svcNo' => 's.svcNo',
            'rank' => 'r.level',
            'surname' => 's.lName',
            'fName' => 's.fName',
            'unit' => 'u.code',
            'category' => 's.category',
            'DOB' => 's.DOB',
            'attestDate' => 's.attestDate',
            'subWef' => 's.subWef',
            'tempWef' => 's.tempWef',
            'svcStatus' => 's.svcStatus'
        ];
        $sort_col = $_GET['sort_col'] ?? '';
        $sort_dir = strtolower($_GET['sort_dir'] ?? 'asc') === 'desc' ? 'DESC' : 'ASC';
        // Main query with JOINs
    $sql = "SELECT DISTINCT s.svcNo, s.fName, s.lName, s.rankId, s.unitId, s.svcStatus, 
               r.rankId as rankName, r.rankId as rankAbbr, r.level as rankLevel, 
               u.code as unitName, u.code as unitCode, 
               s.subWef, s.tempWef, s.attestDate 
        FROM staff s 
        LEFT JOIN `rank` r ON s.rankId = r.rankId 
        LEFT JOIN unit u ON s.unitId = u.unitId 
        WHERE 1=1";
        // Count query for pagination
    $count_sql = "SELECT COUNT(DISTINCT s.svcNo) FROM staff s\n                      LEFT JOIN `rank` r ON s.rankId = r.rankId\n                      LEFT JOIN unit u ON s.unitId = u.unitId\n                      WHERE 1=1";
        $params = [];
        $count_params = [];
        // Search condition
        if ($search !== '') {
            $searchCondition = " AND (s.svcNo LIKE ? OR s.fName LIKE ? OR s.lName LIKE ? OR r.rankId LIKE ? OR r.rankId LIKE ? OR u.code LIKE ?)";
            $sql .= $searchCondition;
            $count_sql .= $searchCondition;
            $searchParam = '%' . $search . '%';
            for ($i = 0; $i < 6; $i++) {
                $params[] = $searchParam;
                $count_params[] = $searchParam;
            }
        }
        // Filter conditions
        if ($rankFilter !== '') {
            $sql .= " AND s.rankId = ?";
            $count_sql .= " AND s.rankId = ?";
            $params[] = $rankFilter;
            $count_params[] = $rankFilter;
        }
        if ($unitFilter !== '') {
            $sql .= " AND s.unitId = ?";
            $count_sql .= " AND s.unitId = ?";
            $params[] = $unitFilter;
            $count_params[] = $unitFilter;
        }
        if ($statusFilter !== '') {
            $sql .= " AND s.svcStatus = ?";
            $count_sql .= " AND s.svcStatus = ?";
            $params[] = $statusFilter;
            $count_params[] = $statusFilter;
        }
        // Exclude retired and deceased staff when requested
        if ($excludeInactive) {
            $sql .= " AND s.svcStatus = 'Active'";
            $count_sql .= " AND s.svcStatus = 'Active'";
        }
        // Get total count for pagination
        $stmt_count = $pdo->prepare($count_sql);
        $stmt_count->execute($count_params);
        $total_staff = $stmt_count->fetchColumn();
        $total_pages = ceil($total_staff / $per_page);
        // Apply sorting (matching reports_seniority.php logic)
        if ($sort_col && array_key_exists($sort_col, $sortable_columns)) {
            $sql .= " ORDER BY " . $sortable_columns[$sort_col] . " $sort_dir";
        } else {
            // Default seniority sorting: rank level, then subWef, then tempWef, then attestDate, then service number
            // Personnel without ranks (NULL rankId) are listed last
            $sql .= " ORDER BY 
                CASE WHEN s.rankId IS NULL THEN 1 ELSE 0 END,
                r.level ASC,
                s.subWef ASC,
                s.tempWef ASC,
                s.attestDate ASC,
                s.svcNo ASC";
        }
        // Apply pagination
        $sql .= " LIMIT $per_page OFFSET $offset";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $staffList = $stmt->fetchAll(PDO::FETCH_OBJ);
        $result = [];
        foreach ($staffList as $s) {
            // Some installations do not have a numeric `id` column on staff and use svcNo as the primary identifier.
            // Return `id` as svcNo for backward compatibility with UI code that expects an `id` field.
            $result[] = [
                'id' => $s->svcNo,
                'svcNo' => $s->svcNo,
                'rank' => $s->rankAbbr ?? $s->rankName ?? 'N/A',
                'name' => $s->lName . ' ' . $s->fName,
                'unit' => $s->unitCode ?? $s->unitName ?? '',
                'status' => $s->svcStatus
            ];
        }
        // Return data with pagination info and debug trace
        echo json_encode([
            'data' => $result,
            'pagination' => [
                'current_page' => $page,
                'per_page' => $per_page,
                'total_items' => $total_staff,
                'total_pages' => $total_pages
            ],
            'debug' => $debug
        ]);
        exit;
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Server error: ' . $e->getMessage()]);
        exit;
    }
    
    // Unreachable code removed after exit;
}

// --- CSRF helper ---
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
function csrf_token() { return $_SESSION['csrf_token']; }

$pdo = getDbConnection();
// Lookup ranks using the `rank` table and its rankId identifier
$ranks = $pdo->query("SELECT rankId as rankID, rankId as rankName FROM `rank`")->fetchAll(PDO::FETCH_OBJ);
$rankMap = [];
foreach ($ranks as $r) $rankMap[$r->rankID] = $r->rankName;
$units = $pdo->query("SELECT unitId as unitID, code as unitName FROM unit")->fetchAll(PDO::FETCH_OBJ);
$unitMap = [];
foreach ($units as $u) $unitMap[$u->unitID] = $u->unitName;
$corps = $pdo->query("SELECT corpsId as id, abbreviation as name FROM corps ORDER BY abbreviation ASC")->fetchAll(PDO::FETCH_OBJ);

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

/**
 * Calculate retirement dates and remaining time
 */
function calculateRetirementInfo($dateOfBirth, $dateOfEnlistment) {

        $result = [
            'runout' => [
                'date' => null,
                'formatted' => null,
                'isPast' => null,
                'remaining' => null,
                'yearsRemaining' => null
            ],
            'earlyRetirement' => [
                'date' => null,
                'formatted' => null,
                'isPast' => null,
                'remaining' => null,
                'yearsRemaining' => null
            ]
        ];
    
    // Expected Runout Date (Age 65)
    if (!empty($dateOfBirth)) {
        try {
            $dob = new DateTime($dateOfBirth);
            $runoutDate = clone $dob;
            $runoutDate->modify('+65 years');
            $now = new DateTime();
            
            $result['runout'] = [
                'date' => $runoutDate,
                'formatted' => $runoutDate->format('d M Y'),
                'isPast' => $runoutDate < $now,
            ];
            
            if ($runoutDate >= $now) {
                $interval = $now->diff($runoutDate);
                $years = $interval->y;
                $months = $interval->m;
                $result['runout']['remaining'] = "$years years, $months months";
                $result['runout']['yearsRemaining'] = $years;
            } else {
                $result['runout']['remaining'] = 'Past retirement age';
                $result['runout']['yearsRemaining'] = -1;
            }
        } catch (Exception $e) {
            // Invalid date
        }
    }
    
    // Expected Early Retirement (20 Years Service)
    if (!empty($dateOfEnlistment)) {
        try {
            $enlistment = new DateTime($dateOfEnlistment);
            $earlyRetireDate = clone $enlistment;
            $earlyRetireDate->modify('+20 years');
            $now = new DateTime();
            
            $result['earlyRetirement'] = [
                'date' => $earlyRetireDate,
                'formatted' => $earlyRetireDate->format('d M Y'),
                'isPast' => $earlyRetireDate < $now,
            ];
            
            if ($earlyRetireDate >= $now) {
                $interval = $now->diff($earlyRetireDate);
                $years = $interval->y;
                $months = $interval->m;
                $result['earlyRetirement']['remaining'] = "$years years, $months months";
                $result['earlyRetirement']['yearsRemaining'] = $years;
            } else {
                $result['earlyRetirement']['remaining'] = 'Eligible now';
                $result['earlyRetirement']['yearsRemaining'] = -1;
            }
        } catch (Exception $e) {
            // Invalid date
        }
    }
    
    return $result;
}

/**
 * Get CSS class for retirement urgency color coding
 */
function getRetirementUrgencyClass($yearsRemaining) {
    if ($yearsRemaining < 0) return 'text-danger'; // Past or eligible
    if ($yearsRemaining < 1) return 'text-danger';
    if ($yearsRemaining <= 5) return 'text-warning';
    if ($yearsRemaining <= 10) return 'text-info';
    return 'text-success';
}


// --- Staff selection ---
if (isset($_GET['svcNo'])) {
    $svcNo = $_GET['svcNo'];
    $normalizedSvcNo = normalizeServiceNumber($svcNo);
    
    // Try exact match first
    $stmt = $pdo->prepare("SELECT * FROM staff WHERE svcNo = ?");
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
        error_log("Found staff member: " . $staff->fName . " " . $staff->lName . " with service number: " . $staff->svcNo);
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
        $stmt = $pdo->prepare("SELECT * FROM staff WHERE svcNo = ?");
        $stmt->execute([$svcNo]);
        $originalStaff = $stmt->fetch(PDO::FETCH_OBJ);
        if (!$originalStaff) {
            $errors[] = "Staff member not found with service number: " . $svcNo . ". Please check the service number and try again.";
            error_log("Staff member not found with service number: " . $svcNo);
        } else {
            error_log("Found staff member: " . $originalStaff->fName . " " . $originalStaff->lName . " (SvcNo: " . $originalStaff->svcNo . ")");
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
            // Legacy fields - now managed in dynamic sections (Steps 6-8)
            $postingHistory = ''; // Moved to staff_postings table
            $awards = ''; // Moved to staff_awards table
            $disciplinaryRecord = ''; // Moved to staff_disciplinary table

            // Comprehensive validation
            $validationErrors = [];
            if ($nameError = validateName($fname, 'First name')) $validationErrors['fname'] = $nameError;
            if ($nameError = validateName($lname, 'Last name')) $validationErrors['lname'] = $nameError;
            if ($nrcError = validateNRC($NRC)) $validationErrors['NRC'] = $nrcError;
            if ($dobError = validateDOB($DOB)) $validationErrors['DOB'] = $dobError;
            if ($phoneError = validatePhone($tel)) $validationErrors['tel'] = $phoneError;
            if ($emailError = validateEmail($email)) $validationErrors['email'] = $emailError;
            if ($heightError = validateHeight($_POST['height'] ?? '')) $validationErrors['height'] = $heightError;
            if ($nokPhoneError = validatePhone($nokTel)) $validationErrors['nokTel'] = $nokPhoneError;
            if ($nokNrcError = validateNRC($nokNrc)) $validationErrors['nokNrc'] = $nokNrcError;
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
                    'svcNo' => $newSvcNo,
                    'fName' => $fname,
                    'lName' => $lname,
                    'rankId' => $rankID,
                    'unitId' => $unitID,
                    'corpsId' => $corpsID,
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
                    // --- Advanced rank logic: update tempWef/subWef based on rank type ---
                    // Get rank details to determine if temporal or substantive
                    $rankStmt = $pdo->prepare("SELECT rankId as name FROM `rank` WHERE rankId = ? LIMIT 1");
                    $rankStmt->execute([$rankID]);
                    $rankDetails = $rankStmt->fetch(PDO::FETCH_ASSOC);
                    if ($rankDetails) {
                        $rankName = $rankDetails['name'] ?? '';
                        $lastPromotionDate = $lastPromotion;
                        // Check if rank name contains 'Temporal' or starts with 'T' (case-insensitive)
                        $isTemporal = stripos($rankName, 'Temporal') !== false || (strlen($rankName) > 1 && $rankName[0] === 'T' && ctype_upper($rankName[1]));
                        if ($isTemporal) {
                            $updateData['tempWef'] = $lastPromotionDate;
                            $updateData['subWef'] = null;
                        } else {
                            $updateData['subWef'] = $lastPromotionDate;
                            $updateData['tempWef'] = null;
                        }
                    }
                try {
                    // IMPORTANT: Ensure all tables exist BEFORE starting transaction
                    // (CREATE TABLE causes implicit commit in MySQL, which would break our transaction)
                    
                    // Create staff_edit_log table if needed
                    try {
                        $tableExists = $pdo->query("SHOW TABLES LIKE 'staff_edit_log'")->rowCount() > 0;
                        if (!$tableExists) {
                            $createSql = "
                                CREATE TABLE staff_edit_log (
                                    id INT AUTO_INCREMENT PRIMARY KEY,
                                    svcNo VARCHAR(50) NOT NULL,
                                    edited_by INT NOT NULL,
                                    edited_at DATETIME NOT NULL,
                                    user_ip VARCHAR(45),
                                    changes JSON,
                                    createdAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                                )
                            ";
                            $pdo->exec($createSql);
                            error_log("Created staff_edit_log table before transaction");
                        }
                    } catch (Exception $tableCheckE) {
                        error_log("Error checking/creating log table: " . $tableCheckE->getMessage());
                    }
                    
                    // Create staff_operation table if needed
                    try {
                        $tableExists = $pdo->query("SHOW TABLES LIKE 'staff_operation'")->rowCount() > 0;
                        if (!$tableExists) {
                            $createSql = "
                                CREATE TABLE staff_operation (
                                    id INT AUTO_INCREMENT PRIMARY KEY,
                                    svcNo VARCHAR(10) NOT NULL,
                                    opId VARCHAR(100) NOT NULL,
                                    apptId VARCHAR(100),
                                    startDate DATE,
                                    endDate DATE,
                                    reasonLeft VARCHAR(50),
                                    remarks TEXT,
                                    authorityId VARCHAR(10) NOT NULL,
                                    createdAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                                    INDEX idx_svcNo (svcNo),
                                    INDEX idx_opId (opId)
                                )
                            ";
                            $pdo->exec($createSql);
                            error_log("Created staff_operation table before transaction");
                        }
                    } catch (Exception $tableCheckE) {
                        error_log("Error checking/creating staff_operation table: " . $tableCheckE->getMessage());
                    }
                    
                    // Add missing columns to staff table if they don't exist
                    try {
                        $columns = $pdo->query('DESCRIBE staff')->fetchAll(PDO::FETCH_ASSOC);
                        $existingCols = array_column($columns, 'Field');
                        
                        // Add specialization column if it doesn't exist
                        if (!in_array('specialization', $existingCols)) {
                            $pdo->exec("ALTER TABLE staff ADD COLUMN specialization VARCHAR(100) NULL AFTER trade");
                            error_log("Added specialization column to staff table");
                        }
                    } catch (Exception $alterE) {
                        error_log("Error adding specialization column to staff table: " . $alterE->getMessage());
                    }
                    
                    // Create staff_deployments table if needed
                    try {
                        $tableExists = $pdo->query("SHOW TABLES LIKE 'staff_deployments'")->rowCount() > 0;
                        if (!$tableExists) {
                            $createSql = "
                                CREATE TABLE staff_deployments (
                                    id INT AUTO_INCREMENT PRIMARY KEY,
                                    svcNo VARCHAR(20) NOT NULL,
                                    deployment_name VARCHAR(200),
                                    mission_type VARCHAR(100),
                                    location VARCHAR(100),
                                    country VARCHAR(100),
                                    startDate DATE,
                                    endDate DATE,
                                    durationMonths INT,
                                    deployment_status VARCHAR(50),
                                    rank_during_deployment VARCHAR(50),
                                    role_during_deployment VARCHAR(100),
                                    commanding_officer VARCHAR(100),
                                    deployment_allowance DECIMAL(10,2),
                                    notes TEXT,
                                    createdAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                                    updatedAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                                    INDEX idx_svcNo (svcNo)
                                )
                            ";
                            $pdo->exec($createSql);
                            error_log("Created staff_deployments table before transaction");
                        }
                    } catch (Exception $tableCheckE) {
                        error_log("Error checking/creating staff_deployments table: " . $tableCheckE->getMessage());
                    }
                    
                    // Create staff_course table if needed (for education/courses)
                    try {
                        $tableExists = $pdo->query("SHOW TABLES LIKE 'staff_course'")->rowCount() > 0;
                        if (!$tableExists) {
                            $createSql = "
                                CREATE TABLE staff_course (
                                    id INT AUTO_INCREMENT PRIMARY KEY,
                                    svcNo VARCHAR(20) NOT NULL,
                                    instId INT,
                                    cseId INT,
                                    qualification VARCHAR(200),
                                    yearStarted INT,
                                    yearCompleted INT,
                                    grade VARCHAR(20),
                                    result VARCHAR(100),
                                    certificate VARCHAR(200),
                                    isHighest TINYINT(1) DEFAULT 0,
                                    authorityId INT,
                                    createdAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                                    updatedAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                                    INDEX idx_svcNo (svcNo)
                                )
                            ";
                            $pdo->exec($createSql);
                            error_log("Created staff_course table before transaction");
                        }
                    } catch (Exception $tableCheckE) {
                        error_log("Error checking/creating staff_course table: " . $tableCheckE->getMessage());
                    }
                    
                    // Create staff_skills table if needed
                    try {
                        $tableExists = $pdo->query("SHOW TABLES LIKE 'staff_skills'")->rowCount() > 0;
                        if (!$tableExists) {
                            $createSql = "
                                CREATE TABLE staff_skills (
                                    id INT AUTO_INCREMENT PRIMARY KEY,
                                    svcNo VARCHAR(20) NOT NULL,
                                    course_name VARCHAR(200),
                                    course_type VARCHAR(100),
                                    institution VARCHAR(200),
                                    startDate DATE,
                                    endDate DATE,
                                    duration_days INT,
                                    certificateNumber VARCHAR(100),
                                    grade_obtained VARCHAR(20),
                                    location VARCHAR(100),
                                    cost DECIMAL(10,2),
                                    sponsored_by VARCHAR(100),
                                    certification_status VARCHAR(50),
                                    createdAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                                    updatedAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                                    INDEX idx_svcNo (svcNo)
                                )
                            ";
                            $pdo->exec($createSql);
                            error_log("Created staff_skills table before transaction");
                        }
                    } catch (Exception $tableCheckE) {
                        error_log("Error checking/creating staff_skills table: " . $tableCheckE->getMessage());
                    }
                    
                    // NOW start the transaction (after ALL DDL operations)
                    $pdo->beginTransaction();
                    
                    // Debug: Log the update operation
                    error_log("Starting staff update for service number: " . $svcNo);
                    
                    // Log staff changes (pass PDO connection to avoid transaction issues)
                    logStaffChange($svcNo, $originalStaff, $updateData, $user->data()->id ?? 0, $_SERVER['REMOTE_ADDR'] ?? '', $pdo);
                    
                    // Get current staff table structure to build dynamic update query
                    $columns = $pdo->query('DESCRIBE staff')->fetchAll(PDO::FETCH_ASSOC);
                    $existingColumns = array_column($columns, 'Field');
                    
                    // Map form fields to database columns
                    $fieldMap = [
                        'fName' => $fname,
                        'lName' => $lname,
                        'rankId' => $rankID,
                        'unitId' => $unitID,
                        'corpsId' => $corpsID,
                        'category' => $category,
                        'svcNo' => $newSvcNo,
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
                        'bootSize' => $bsize, 
                        'shoeSize' => $ssize, 
                        'hDress' => $hdress, 
                        'attestDate' => $attestDate,
                        'lastPromotion' => $lastPromotion,
                        'postingHistory' => $postingHistory,
                        'awards' => $awards,
                        'disciplinaryRecord' => $disciplinaryRecord,
                        'marital' => $_POST['maritalStatus'] ?? null,
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
                    
                    error_log("Specialization value from POST: '" . ($specialization ?? 'NULL') . "'");
                    error_log("Existing staff table columns: " . implode(", ", $existingColumns));
                    
                    foreach ($fieldMap as $column => $value) {
                        if (in_array($column, $existingColumns)) {
                            // Only allow svcNo update if changed
                            if ($column === 'svcNo' && $newSvcNo !== $svcNo) {
                                // Check for duplicate service number
                                $dupStmt = $pdo->prepare("SELECT COUNT(*) FROM staff WHERE svcNo = ?");
                                $dupStmt->execute([$newSvcNo]);
                                if ($dupStmt->fetchColumn() > 0) {
                                    throw new Exception("Service number already exists. Please choose a unique service number.");
                                }
                            }
                            $updateFields[] = "$column = ?";
                            $updateParams[] = $value;
                            if ($column === 'specialization') {
                                error_log("Specialization WILL BE UPDATED to: '" . $value . "'");
                            }
                        } else {
                            error_log("Skipping missing column: $column");
                        }
                    }
                    // Add svcNo for WHERE clause
                    $updateParams[] = $svcNo;
                    $updateSql = "UPDATE staff SET " . implode(', ', $updateFields) . " WHERE svcNo = ?";
                    
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
                        $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM staff WHERE svcNo = ?");
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
                    
                    // Use svcNo directly for child tables (staff table uses svcNo as primary key, not id)
                    $staffSvcNo = $newSvcNo;
                    
                    error_log("Using staff svcNo: " . $staffSvcNo . " for processing dynamic fields");

                    // --- Operations (with error handling) ---
                    try {
                        error_log("Processing operations for staff svcNo: " . $staffSvcNo);
                        error_log("POST operations data: " . json_encode($_POST['operations'] ?? []));
                        error_log("Number of operations in POST: " . count($_POST['operations'] ?? []));
                        
                        // Table already ensured to exist before transaction
                        // Only process if operations data was submitted
                        if (isset($_POST['operations']) && is_array($_POST['operations'])) {
                            // Delete existing operations for this staff member
                            $deleteStmt = $pdo->prepare("DELETE FROM staff_operation WHERE svcNo = ?");
                            $deleteStmt->execute([$staffSvcNo]);
                            error_log("Deleted existing operations for svcNo: $staffSvcNo");
                            
                            // Insert new operations
                            $opStmt = $pdo->prepare("INSERT INTO staff_operation (svcNo, opId, apptId, startDate, endDate, reasonLeft, remarks, authorityId) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                            $insertCount = 0;
                            foreach ($_POST['operations'] as $index => $op) {
                            error_log("Processing operation #$index: " . json_encode($op));
                            // Check if there's meaningful data (either opId or apptId should be present)
                            if (!empty($op['opID']) || !empty($op['opId']) || !empty($op['apptID']) || !empty($op['apptId']) || !empty($op['operation_id'])) {
                                try {
                                    $opStmt->execute([
                                        $staffSvcNo,
                                        !empty($op['opId']) ? $op['opId'] : (!empty($op['opID']) ? $op['opID'] : (!empty($op['operation_id']) ? $op['operation_id'] : '')),
                                        !empty($op['apptId']) ? $op['apptId'] : (!empty($op['apptID']) ? $op['apptID'] : ''),
                                        !empty($op['startDate']) ? $op['startDate'] : null,
                                        !empty($op['endDate']) ? $op['endDate'] : null,
                                        $op['reasonLeft'] ?? $op['reason_left'] ?? '',
                                        $op['remarks'] ?? '',
                                        $op['authorityId'] ?? $op['authority_id'] ?? ''
                                    ]);
                                    $insertCount++;
                                    error_log("Successfully inserted operation #$index");
                                } catch (Exception $insertEx) {
                                    error_log("Failed to insert operation #$index: " . $insertEx->getMessage());
                                }
                            } else {
                                error_log("Skipping empty operation #$index");
                            }
                        }
                        error_log("Successfully inserted $insertCount operations for svcNo: $staffSvcNo");
                        } else {
                            error_log("No operations data submitted - preserving existing operations for svcNo: $staffSvcNo");
                        }
                    } catch (Exception $e) {
                        error_log("Error processing operations: " . $e->getMessage());
                        error_log("Operations data: " . json_encode($_POST['operations'] ?? []));
                        error_log("Stack trace: " . $e->getTraceAsString());
                        // Continue processing - don't fail the entire update for child table issues
                    }

                    // --- Deployments (with error handling) ---
                    try {
                        error_log("Processing deployments for staff svcNo: " . $staffSvcNo);
                        error_log("POST deployments data: " . json_encode($_POST['deployments'] ?? []));
                        error_log("Number of deployments in POST: " . count($_POST['deployments'] ?? []));
                        
                        // Table already ensured to exist before transaction
                        // Only process if deployments data was submitted
                        if (isset($_POST['deployments']) && is_array($_POST['deployments'])) {
                            // Delete existing deployments for this staff member
                            $deleteStmt = $pdo->prepare("DELETE FROM staff_deployments WHERE svcNo = ?");
                            $deleteStmt->execute([$staffSvcNo]);
                            error_log("Deleted existing deployments for svcNo: $staffSvcNo");
                            
                            // Insert new deployments
                            $depStmt = $pdo->prepare("INSERT INTO staff_deployments (svcNo, deployment_name, mission_type, location, country, startDate, endDate, durationMonths, deployment_status, rank_during_deployment, role_during_deployment, commanding_officer, deployment_allowance, notes, createdAt, updatedAt) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())");
                            $insertCount = 0;
                            foreach ($_POST['deployments'] as $index => $dep) {
                            error_log("Processing deployment #$index: " . json_encode($dep));
                            if (!empty($dep['deployment_name'])) {
                                try {
                                    $depStmt->execute([
                                        $staffSvcNo,
                                        $dep['deployment_name'] ?? '',
                                        $dep['mission_type'] ?? '',
                                        $dep['location'] ?? '',
                                        $dep['country'] ?? '',
                                        !empty($dep['startDate']) ? $dep['startDate'] : null,
                                        !empty($dep['endDate']) ? $dep['endDate'] : null,
                                        !empty($dep['durationMonths']) ? intval($dep['durationMonths']) : null,
                                        $dep['deployment_status'] ?? $dep['status'] ?? '', // Use the correct field name
                                        $dep['rank_during_deployment'] ?? '',
                                        $dep['role_during_deployment'] ?? $dep['role'] ?? '', // Use the correct field name
                                        $dep['commanding_officer'] ?? '',
                                        !empty($dep['deployment_allowance']) ? floatval($dep['deployment_allowance']) : null,
                                        $dep['notes'] ?? ''
                                    ]);
                                    $insertCount++;
                                    error_log("Successfully inserted deployment #$index");
                                } catch (Exception $insertEx) {
                                    error_log("Failed to insert deployment #$index: " . $insertEx->getMessage());
                                }
                            } else {
                                error_log("Skipping empty deployment #$index");
                            }
                        }
                        error_log("Successfully inserted $insertCount deployments for svcNo: $staffSvcNo");
                        } else {
                            error_log("No deployments data submitted - preserving existing deployments for svcNo: $staffSvcNo");
                        }
                    } catch (Exception $e) {
                        error_log("Error processing deployments: " . $e->getMessage());
                        error_log("Deployments data: " . json_encode($_POST['deployments'] ?? []));
                        error_log("Stack trace: " . $e->getTraceAsString());
                        // Continue processing
                    }

                    // --- Education/Courses (with error handling) ---
                    try {
                        error_log("Processing education/courses for staff svcNo: " . $staffSvcNo);
                        error_log("POST education data: " . json_encode($_POST['education'] ?? []));
                        error_log("Number of education records in POST: " . count($_POST['education'] ?? []));
                        
                        // Table already ensured to exist before transaction (staff_course)
                        // Only process if education data was submitted
                        if (isset($_POST['education']) && is_array($_POST['education'])) {
                            // Delete existing education/course records
                            $deleteStmt = $pdo->prepare("DELETE FROM staff_course WHERE svcNo = ?");
                            $deleteStmt->execute([$staffSvcNo]);
                            error_log("Deleted existing courses for svcNo: $staffSvcNo");
                            
                            // Insert new education/course records
                            $eduStmt = $pdo->prepare("INSERT INTO staff_course (svcNo, instId, cseId, qualification, yearStarted, yearCompleted, grade, result, certificate, isHighest, authorityId) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                            $insertCount = 0;
                            foreach ($_POST['education'] as $index => $edu) {
                            error_log("Processing education #$index: " . json_encode($edu));
                            // Check if there's meaningful data (instId or cseId should be present)
                            if (!empty($edu['instId']) || !empty($edu['cseId']) || !empty($edu['institution'])) {
                                try {
                                    $eduStmt->execute([
                                        $staffSvcNo,
                                        !empty($edu['instId']) ? $edu['instId'] : (!empty($edu['institution']) ? $edu['institution'] : ''),
                                        !empty($edu['cseId']) ? $edu['cseId'] : '',
                                        $edu['qualification'] ?? '',
                                        !empty($edu['yearStarted']) ? intval($edu['yearStarted']) : (!empty($edu['year_started']) ? intval($edu['year_started']) : null),
                                        !empty($edu['yearCompleted']) ? intval($edu['yearCompleted']) : (!empty($edu['year_completed']) ? intval($edu['year_completed']) : null),
                                        $edu['grade'] ?? $edu['grade_obtained'] ?? '',
                                        $edu['result'] ?? '',
                                        $edu['certificate'] ?? '',
                                        !empty($edu['isHighest']) ? 1 : (!empty($edu['is_highest_qualification']) ? 1 : 0),
                                        $edu['authorityId'] ?? $edu['authority_id'] ?? ''
                                    ]);
                                    $insertCount++;
                                    error_log("Successfully inserted education #$index");
                                } catch (Exception $insertEx) {
                                    error_log("Failed to insert education #$index: " . $insertEx->getMessage());
                                }
                            } else {
                                error_log("Skipping empty education #$index");
                            }
                        }
                        error_log("Successfully inserted $insertCount course records for svcNo: $staffSvcNo");
                        } else {
                            error_log("No education data submitted - preserving existing courses for svcNo: $staffSvcNo");
                        }
                    } catch (Exception $e) {
                        error_log("Error processing education: " . $e->getMessage());
                        error_log("Education data: " . json_encode($_POST['education'] ?? []));
                        error_log("Stack trace: " . $e->getTraceAsString());
                        // Continue processing
                    }
                    
                    // --- Skills (with error handling) ---
                    try {
                        error_log("Processing skills for staff svcNo: " . $staffSvcNo);
                        error_log("POST skills data: " . json_encode($_POST['skills'] ?? []));
                        error_log("Number of skills in POST: " . count($_POST['skills'] ?? []));
                        
                        // Table already ensured to exist before transaction
                        // Only process if skills data was submitted
                        if (isset($_POST['skills']) && is_array($_POST['skills'])) {
                            // Delete existing skills
                            $deleteStmt = $pdo->prepare("DELETE FROM staff_skills WHERE svcNo = ?");
                            $deleteStmt->execute([$staffSvcNo]);
                            error_log("Deleted existing skills for svcNo: $staffSvcNo");
                            
                            // Insert new skills
                            $skillStmt = $pdo->prepare("INSERT INTO staff_skills (svcNo, skillName, skillLevel, skillCategory, yearsOfExperience, certified, certificationBody, certificationDate, certificationExpiry) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                            $insertCount = 0;
                            foreach ($_POST['skills'] as $index => $skill) {
                            error_log("Processing skill #$index: " . json_encode($skill));
                            if (!empty($skill['skillName']) || !empty($skill['skill_name'])) {
                                try {
                                    $skillStmt->execute([
                                        $staffSvcNo,
                                        $skill['skillName'] ?? $skill['skill_name'] ?? '',
                                        $skill['skillLevel'] ?? $skill['skill_level'] ?? null,
                                        $skill['skillCategory'] ?? $skill['skill_category'] ?? null,
                                        !empty($skill['yearsOfExperience']) ? intval($skill['yearsOfExperience']) : (!empty($skill['years_of_experience']) ? intval($skill['years_of_experience']) : null),
                                        !empty($skill['certified']) ? 1 : 0,
                                        $skill['certificationBody'] ?? $skill['certification_body'] ?? null,
                                        !empty($skill['certificationDate']) ? $skill['certificationDate'] : (!empty($skill['certification_date']) ? $skill['certification_date'] : null),
                                        !empty($skill['certificationExpiry']) ? $skill['certificationExpiry'] : (!empty($skill['certification_expiry']) ? $skill['certification_expiry'] : null)
                                    ]);
                                    $insertCount++;
                                    error_log("Successfully inserted skill #$index");
                                } catch (Exception $insertEx) {
                                    error_log("Failed to insert skill #$index: " . $insertEx->getMessage());
                                }
                            } else {
                                error_log("Skipping empty skill #$index");
                            }
                        }
                        error_log("Successfully inserted $insertCount skills for svcNo: $staffSvcNo");
                        } else {
                            error_log("No skills data submitted - preserving existing skills for svcNo: $staffSvcNo");
                        }
                    } catch (Exception $e) {
                        error_log("Error processing skills: " . $e->getMessage());
                        error_log("Skills data: " . json_encode($_POST['skills'] ?? []));
                        error_log("Stack trace: " . $e->getTraceAsString());
                        // Continue processing
                    }

                    // ==================== PROCESS POSTING HISTORY (staff_appointment table) ====================
                    try {
                        // Check if table exists
                        $tableCheck = $pdo->query("SHOW TABLES LIKE 'staff_appointment'");
                        if ($tableCheck->rowCount() > 0) {
                            
                        if (isset($_POST['postings']) && is_array($_POST['postings'])) {
                            error_log("Processing postings for svcNo: $staffSvcNo");
                            
                            // Delete existing postings for this staff member from staff_appointment
                            $deletePostings = $pdo->prepare("DELETE FROM staff_appointment WHERE svcNo = ?");
                            $deletePostings->execute([$staffSvcNo]);
                            error_log("Deleted existing postings from staff_appointment for svcNo: $staffSvcNo");
                            
                            // Insert new postings into staff_appointment table
                            $insertPosting = $pdo->prepare("
                                INSERT INTO staff_appointment 
                                (svcNo, apptId, apptType, unitId, apptWef, powers, endDate, authorityId, remarks, createdBy)
                                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                            ");
                            
                            $insertCount = 0;
                            foreach ($_POST['postings'] as $index => $posting) {
                                // Skip if missing required fields
                                if (empty($posting['apptId']) && empty($posting['appointment_id'])) {
                                    error_log("Skipping posting #$index - missing apptId");
                                    continue;
                                }
                                
                                $insertPosting->execute([
                                    $staffSvcNo,
                                    $posting['apptId'] ?? $posting['appointment_id'] ?? '',
                                    $posting['appointment_type'] ?? $posting['apptType'] ?? null,
                                    $posting['unitId'] ?? null,
                                    $posting['startDate'] ?? $posting['apptWef'] ?? null,
                                    $posting['powers'] ?? '',
                                    !empty($posting['endDate']) ? $posting['endDate'] : null,
                                    $posting['authorityId'] ?? $posting['authority_id'] ?? null,
                                    $posting['remarks'] ?? null,
                                    $_SESSION['username'] ?? 'system'
                                ]);
                                $insertCount++;
                            }
                            error_log("Successfully inserted $insertCount postings into staff_appointment for svcNo: $staffSvcNo");
                        } else {
                            error_log("No postings data submitted - preserving existing postings for svcNo: $staffSvcNo");
                        }
                        
                        }
                    } catch (Exception $e) {
                        error_log("Error processing postings: " . $e->getMessage());
                        error_log("Postings data: " . json_encode($_POST['postings'] ?? []));
                        error_log("Stack trace: " . $e->getTraceAsString());
                        // Continue processing
                    }

                    // ==================== PROCESS AWARDS & COMMENDATIONS ====================
                    try {
                        // Check if table exists
                        $tableCheck = $pdo->query("SHOW TABLES LIKE 'staff_awards'");
                        if ($tableCheck->rowCount() > 0) {
                            
                        if (isset($_POST['awards']) && is_array($_POST['awards'])) {
                            error_log("Processing awards for svcNo: $staffSvcNo");
                            
                            // Delete existing awards for this staff member
                            $deleteAwards = $pdo->prepare("DELETE FROM staff_awards WHERE svcNo = ?");
                            $deleteAwards->execute([$staffSvcNo]);
                            error_log("Deleted existing awards for svcNo: $staffSvcNo");
                            
                            // Insert new awards
                            $insertAward = $pdo->prepare("
                                INSERT INTO staff_awards 
                                (svcNo, award_type, award_name, award_date, awarded_by, 
                                 citation, certificateNumber, gazette_reference, remarks)
                                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                            ");
                            
                            $insertCount = 0;
                            foreach ($_POST['awards'] as $index => $award) {
                                // Skip if missing required fields
                                if (empty($award['award_type']) || empty($award['award_name']) || empty($award['award_date'])) {
                                    error_log("Skipping empty award #$index");
                                    continue;
                                }
                                
                                $insertAward->execute([
                                    $staffSvcNo,
                                    $award['award_type'],
                                    $award['award_name'],
                                    $award['award_date'],
                                    $award['awarded_by'] ?? null,
                                    $award['citation'] ?? null,
                                    $award['certificateNumber'] ?? null,
                                    $award['gazette_reference'] ?? null,
                                    $award['remarks'] ?? null
                                ]);
                                $insertCount++;
                            }
                            error_log("Successfully inserted $insertCount awards for svcNo: $staffSvcNo");
                        } else {
                            error_log("No awards data submitted - preserving existing awards for svcNo: $staffSvcNo");
                        }
                        
                        }
                    } catch (Exception $e) {
                        error_log("Error processing awards: " . $e->getMessage());
                        error_log("Awards data: " . json_encode($_POST['awards'] ?? []));
                        error_log("Stack trace: " . $e->getTraceAsString());
                        // Continue processing
                    }

                    // ==================== PROCESS DISCIPLINARY RECORDS ====================
                    try {
                        // Check if table exists
                        $tableCheck = $pdo->query("SHOW TABLES LIKE 'staff_disciplinary'");
                        if ($tableCheck->rowCount() > 0) {
                            
                        if (isset($_POST['disciplinary']) && is_array($_POST['disciplinary'])) {
                            error_log("Processing disciplinary records for svcNo: $staffSvcNo");
                            
                            // Delete existing disciplinary records for this staff member
                            $deleteDisciplinary = $pdo->prepare("DELETE FROM staff_disciplinary WHERE svcNo = ?");
                            $deleteDisciplinary->execute([$staffSvcNo]);
                            error_log("Deleted existing disciplinary records for svcNo: $staffSvcNo");
                            
                            // Insert new disciplinary records
                            $insertDisciplinary = $pdo->prepare("
                                INSERT INTO staff_disciplinary 
                                (svcNo, incident_type, incident_date, description, action_taken, 
                                 disciplinary_authority, startDate, endDate, case_reference, 
                                 outcome, appeal_details, clearance_date, confidential, remarks)
                                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                            ");
                            
                            $insertCount = 0;
                            foreach ($_POST['disciplinary'] as $index => $disc) {
                                // Skip if missing required fields
                                if (empty($disc['incident_type']) || empty($disc['incident_date']) || empty($disc['description'])) {
                                    error_log("Skipping empty disciplinary record #$index");
                                    continue;
                                }
                                
                                $insertDisciplinary->execute([
                                    $staffSvcNo,
                                    $disc['incident_type'],
                                    $disc['incident_date'],
                                    $disc['description'],
                                    $disc['action_taken'] ?? null,
                                    $disc['disciplinary_authority'] ?? null,
                                    !empty($disc['startDate']) ? $disc['startDate'] : null,
                                    !empty($disc['endDate']) ? $disc['endDate'] : null,
                                    $disc['case_reference'] ?? null,
                                    $disc['outcome'] ?? null,
                                    $disc['appeal_details'] ?? null,
                                    !empty($disc['clearance_date']) ? $disc['clearance_date'] : null,
                                    isset($disc['confidential']) ? (int)$disc['confidential'] : 1,
                                    $disc['remarks'] ?? null
                                ]);
                                $insertCount++;
                            }
                            error_log("Successfully inserted $insertCount disciplinary records for svcNo: $staffSvcNo");
                        } else {
                            error_log("No disciplinary data submitted - preserving existing records for svcNo: $staffSvcNo");
                        }
                        
                        }
                    } catch (Exception $e) {
                        error_log("Error processing disciplinary records: " . $e->getMessage());
                        error_log("Disciplinary data: " . json_encode($_POST['disciplinary'] ?? []));
                        error_log("Stack trace: " . $e->getTraceAsString());
                        // Continue processing
                    }

                    $pdo->commit();
                    $success = true;
                    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
                    $stmt = $pdo->prepare("SELECT * FROM staff WHERE svcNo = ?");
                    $stmt->execute([$newSvcNo]);
                    $staff = $stmt->fetch(PDO::FETCH_OBJ);
                } catch (Exception $e) {
                    // Only roll back if a transaction is active to avoid "There is no active transaction" errors
                    if ($pdo->inTransaction()) {
                        try {
                            $pdo->rollBack();
                        } catch (Exception $rbE) {
                            error_log('rollBack failed: ' . $rbE->getMessage());
                        }
                    }
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
    $stmt = $pdo->query("SELECT r.rankId as rankName, COUNT(s.svcNo) as count FROM `rank` r LEFT JOIN staff s ON r.rankId = s.rankId GROUP BY r.rankId ORDER BY count DESC");
    $stats['ranks'] = $stmt->fetchAll(PDO::FETCH_OBJ);
    // Staff by unit
    $stmt = $pdo->query("SELECT u.code as unitName, COUNT(s.svcNo) as count FROM unit u LEFT JOIN staff s ON u.unitId = s.unitId GROUP BY u.unitId, u.code ORDER BY count DESC");
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
                sel.svcNo,
                s.fName,
                s.lName,
                sel.edited_by,
                sel.edited_at,
                sel.changes,
                u.username as edited_by_username
            FROM staff_edit_log sel
            LEFT JOIN staff s ON sel.svcNo = s.svcNo
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
// Add unified design system CSS
echo '<link rel="stylesheet" href="css/armis-unified.css">';
echo '<link rel="stylesheet" href="css/form-step-styles.css">';

include dirname(__DIR__) . '/shared/header.php';
include dirname(__DIR__) . '/shared/sidebar.php';

// --- Load initial data for dynamic sections ---
$staffOperations = [];
$staffDeployments = [];
$staffEducation = [];
$staffSkills = [];
$staffPostings = [];
$staffAwards = [];
$staffDisciplinary = [];

if (!empty($staff)) {
    error_log("Loading dynamic data for staff svcNo: " . $staff->svcNo);
    
    // Load operations (correct table name is staff_operation, singular)
    if ($pdo->query("SHOW TABLES LIKE 'staff_operation'")->rowCount()) {
        error_log("Staff operation table exists, loading operations");
        $stmt = $pdo->prepare("SELECT * FROM staff_operation WHERE svcNo = ?");
        $stmt->execute([$staff->svcNo]);
        $staffOperations = $stmt->fetchAll(PDO::FETCH_ASSOC);
        error_log("Loaded operations: " . count($staffOperations));
        error_log("Operations data: " . json_encode($staffOperations));
    } else {
        error_log("Staff operation table does not exist");
    }

    // Load deployments
    if ($pdo->query("SHOW TABLES LIKE 'staff_deployments'")->rowCount()) {
        error_log("Staff deployments table exists, loading deployments");
        $stmt = $pdo->prepare("SELECT * FROM staff_deployments WHERE svcNo = ?");
        $stmt->execute([$staff->svcNo]);
        $staffDeployments = $stmt->fetchAll(PDO::FETCH_ASSOC);
        error_log("Loaded deployments: " . count($staffDeployments));
        error_log("Deployments data: " . json_encode($staffDeployments));
    } else {
        error_log("Staff deployments table does not exist");
    }

    // Load education/courses
    if ($pdo->query("SHOW TABLES LIKE 'staff_course'")->rowCount()) {
        error_log("Staff course table exists, loading courses");
        $stmt = $pdo->prepare("SELECT * FROM staff_course WHERE svcNo = ?");
        $stmt->execute([$staff->svcNo]);
        $staffEducation = $stmt->fetchAll(PDO::FETCH_ASSOC);
        error_log("Loaded courses: " . count($staffEducation));
        error_log("Courses data: " . json_encode($staffEducation));
    } else {
        error_log("Staff course table does not exist");
    }

    // Load skills/courses if you have such table, e.g. staff_skills
    if ($pdo->query("SHOW TABLES LIKE 'staff_skills'")->rowCount()) {
        error_log("Staff skills table exists, loading skills");
        $stmt = $pdo->prepare("SELECT * FROM staff_skills WHERE svcNo = ?");
        $stmt->execute([$staff->svcNo]);
        $staffSkills = $stmt->fetchAll(PDO::FETCH_ASSOC);
        error_log("Loaded skills: " . count($staffSkills));
        error_log("Skills data: " . json_encode($staffSkills));
    } else {
        error_log("Staff skills table does not exist");
    }

    // Load posting history from staff_appointment table
    try {
        if ($pdo->query("SHOW TABLES LIKE 'staff_appointment'")->rowCount()) {
            error_log("Staff appointment table exists, loading postings");
            // Load from staff_appointment - this table does NOT have rankId column
            $stmt = $pdo->prepare("
                SELECT sa.*, COALESCE(u.code, '') as unit_name
                FROM staff_appointment sa
                LEFT JOIN unit u ON sa.unitId = u.unitId
                WHERE sa.svcNo = ? 
                ORDER BY sa.apptWef DESC
            ");
            $stmt->execute([$staff->svcNo]);
            $staffPostings = $stmt->fetchAll(PDO::FETCH_ASSOC);
            error_log("Loaded postings from staff_appointment: " . count($staffPostings));
            error_log("Postings data: " . json_encode($staffPostings));
        } else {
            error_log("Staff appointment table does not exist");
        }
    } catch (Exception $e) {
        error_log("Error loading postings: " . $e->getMessage());
        $staffPostings = [];
    }

    // Load awards & commendations
    try {
        if ($pdo->query("SHOW TABLES LIKE 'staff_awards'")->rowCount()) {
            error_log("Staff awards table exists, loading awards");
            
            // Check if award_date column exists
            $columnCheck = $pdo->query("SHOW COLUMNS FROM staff_awards LIKE 'award_date'")->rowCount();
            $orderBy = $columnCheck ? "award_date DESC" : "id DESC";
            
            $stmt = $pdo->prepare("SELECT * FROM staff_awards WHERE svcNo = ? ORDER BY $orderBy");
            $stmt->execute([$staff->svcNo]);
            $staffAwards = $stmt->fetchAll(PDO::FETCH_ASSOC);
            error_log("Loaded awards: " . count($staffAwards));
            error_log("Awards data: " . json_encode($staffAwards));
        } else {
            error_log("Staff awards table does not exist");
        }
    } catch (Exception $e) {
        error_log("Error loading awards: " . $e->getMessage());
        $staffAwards = [];
    }

    // Load disciplinary records
    try {
        if ($pdo->query("SHOW TABLES LIKE 'staff_disciplinary'")->rowCount()) {
            error_log("Staff disciplinary table exists, loading disciplinary records");
            $stmt = $pdo->prepare("SELECT * FROM staff_disciplinary WHERE svcNo = ? ORDER BY incident_date DESC");
            $stmt->execute([$staff->svcNo]);
            $staffDisciplinary = $stmt->fetchAll(PDO::FETCH_ASSOC);
            error_log("Loaded disciplinary records: " . count($staffDisciplinary));
            error_log("Disciplinary data: " . json_encode($staffDisciplinary));
        } else {
            error_log("Staff disciplinary table does not exist");
        }
    } catch (Exception $e) {
        error_log("Error loading disciplinary records: " . $e->getMessage());
        $staffDisciplinary = [];
    }
}

?>

<!-- Dynamically load initial data for dynamic sections -->
<script>
window.operationsOptions = <?php
    // Defensive: operations table may not exist in all environments. Try/catch and return empty array if missing.
    try {
        $ops = [];
        $res = $pdo->query("SHOW TABLES LIKE 'operations'");
        if ($res && $res->rowCount()) {
            $ops = $pdo->query("SELECT id, name, code FROM operations ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
        }
    } catch (Exception $e) {
        error_log("Could not load operations for edit_staff: " . $e->getMessage());
        $ops = [];
    }
    echo json_encode($ops);
?>;

// Fetch distinct units (case-insensitive) for JavaScript dropdowns
window.unitsOptions = <?php
    try {
        $unitsForDropdown = [];
        $res = $pdo->query("SHOW TABLES LIKE 'unit'");
        if ($res && $res->rowCount()) {
            $unitsForDropdown = $pdo->query("SELECT DISTINCT unitId, code, location FROM unit ORDER BY code ASC")->fetchAll(PDO::FETCH_ASSOC);
        }
    } catch (Exception $e) {
        error_log("Could not load units for edit_staff: " . $e->getMessage());
        $unitsForDropdown = [];
    }
    echo json_encode($unitsForDropdown);
?>;

// Fetch ranks for the staff member's category for JavaScript dropdowns
window.ranksOptions = <?php
    try {
        $rankCategory = '';
        if (isset($staff) && !empty($staff->rank)) {
            // Get the rank category for this staff member from `rank` table
            $stmtRank = $pdo->prepare("SELECT category FROM `rank` WHERE rankId = ? LIMIT 1");
            $stmtRank->execute([$staff->rank]);
            $rankCat = $stmtRank->fetch(PDO::FETCH_OBJ);
            if ($rankCat) {
                $rankCategory = $rankCat->category;
            }
        }
        
        // Get all ranks in the same category, or all if category not found
        if (!empty($rankCategory)) {
            $ranks = $pdo->prepare("SELECT rankId as id, rankId as name, rankId as abbreviation, level FROM `rank` WHERE category = ? ORDER BY level DESC");
            $ranks->execute([$rankCategory]);
            $ranksData = $ranks->fetchAll(PDO::FETCH_ASSOC);
        } else {
            $ranksData = $pdo->query("SELECT rankId as id, rankId as name, rankId as abbreviation, level FROM `rank` ORDER BY level DESC")->fetchAll(PDO::FETCH_ASSOC);
        }
    } catch (Exception $e) {
        error_log("Could not load ranks for edit_staff: " . $e->getMessage());
        $ranksData = [];
    }
    echo json_encode($ranksData);
?>;

window.staffRankCategory = <?php echo json_encode($rankCategory ?? 'Unknown'); ?>;

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

    // Posting History
    <?php if (!empty($staffPostings)): ?>
        console.log('Initializing postings: <?=count($staffPostings)?>');
        <?php foreach ($staffPostings as $posting): ?>
            console.log('Adding posting: <?=json_encode($posting)?>');
            addPostingRow(<?=json_encode($posting)?>);
        <?php endforeach; ?>
    <?php else: ?>
        console.log('No postings to initialize');
    <?php endif; ?>

    // Awards & Commendations
    <?php if (!empty($staffAwards)): ?>
        console.log('Initializing awards: <?=count($staffAwards)?>');
        <?php foreach ($staffAwards as $award): ?>
            console.log('Adding award: <?=json_encode($award)?>');
            addAwardRow(<?=json_encode($award)?>);
        <?php endforeach; ?>
    <?php else: ?>
        console.log('No awards to initialize');
    <?php endif; ?>

    // Disciplinary Records
    <?php if (!empty($staffDisciplinary)): ?>
        console.log('Initializing disciplinary records: <?=count($staffDisciplinary)?>');
        <?php foreach ($staffDisciplinary as $disc): ?>
            console.log('Adding disciplinary record: <?=json_encode($disc)?>');
            addDisciplinaryRow(<?=json_encode($disc)?>);
        <?php endforeach; ?>
    <?php else: ?>
        console.log('No disciplinary records to initialize');
    <?php endif; ?>
});
</script>

<!-- Load custom CSS and assets -->
<link rel="stylesheet" href="/Armis2/assets/css/admin_branch.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.3/css/bootstrap.min.css">
<link rel="stylesheet" href="/Armis2/admin_branch/css/form-step-styles.css">
<!-- Core JS (jQuery + Bootstrap) are loaded centrally in shared/footer.php -->
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
                    <div class="mt-2">
                        <a href="view_staff.php?svcNo=<?= urlencode($staff->svcNo ?? '') ?>" class="btn btn-sm btn-primary">
                            <i class="fa fa-user"></i> View Profile
                        </a>
                        <a href="edit_staff.php" class="btn btn-sm btn-secondary">
                            <i class="fa fa-search"></i> Edit Another Staff
                        </a>
                    </div>
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
                    <a href="edit_staff.php" class="btn btn-sm btn-armis-primary ms-2">
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
                                <div class="col-md-3">
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
                                    <select class="form-select" id="perPageSelect" name="per_page" title="Records per page">
                                        <?php foreach ([10, 25, 50, 100, 200] as $pp): ?>
                                            <option value="<?=$pp?>" <?=$pp === 25 ? 'selected' : ''?>><?=$pp?> per page</option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-1">
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
                                <th scope="col" role="button" tabindex="0" aria-label="Sort by Rank">Rank</th>
                                <th scope="col" role="button" tabindex="0" aria-label="Sort by Name">Name</th>
                                <th scope="col" role="button" tabindex="0" aria-label="Sort by Unit">Unit</th>
                                <th scope="col">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr><td colspan="5" class="text-center text-muted">Enter search criteria to find staff members</td></tr>
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
                                            <?=htmlspecialchars(($activity->fName ?? 'Unknown') . ' ' . ($activity->lName ?? 'Staff'))?>
                                            <small class="text-muted">(<?=htmlspecialchars($activity->svcNo ?? 'N/A')?>)</small>
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
                const perPageSelect = document.getElementById('perPageSelect');
                
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
                
                // Server-side pagination parameters (matching reports_seniority.php)
                let currentPage = 1;
                let itemsPerPage = 25;
                let totalItems = 0;
                let totalPages = 0;
                let currentData = [];
                
                function renderPagination() {
                    const pagination = document.getElementById('pagination');
                    pagination.innerHTML = '';
                    
                    if (totalPages <= 1) return;
                    
                    // Previous button
                    const prevLi = document.createElement('li');
                    prevLi.className = `page-item ${currentPage === 1 ? 'disabled' : ''}`;
                    const prevLink = document.createElement('a');
                    prevLink.className = 'page-link';
                    prevLink.href = '#';
                    prevLink.innerHTML = '&laquo; Prev';
                    prevLink.setAttribute('aria-label', 'Previous');
                    prevLink.addEventListener('click', (e) => {
                        e.preventDefault();
                        if (currentPage > 1) {
                            currentPage--;
                            fetchResults();
                        }
                    });
                    prevLi.appendChild(prevLink);
                    pagination.appendChild(prevLi);
                    
                    // Page numbers (matching reports_seniority.php - 7 links)
                    const maxLinks = 7;
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
                            if (i !== currentPage) {
                                currentPage = i;
                                fetchResults();
                            }
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
                    nextLink.innerHTML = 'Next &raquo;';
                    nextLink.setAttribute('aria-label', 'Next');
                    nextLink.addEventListener('click', (e) => {
                        e.preventDefault();
                        if (currentPage < totalPages) {
                            currentPage++;
                            fetchResults();
                        }
                    });
                    nextLi.appendChild(nextLink);
                    pagination.appendChild(nextLi);
                }
                
                function renderTable(data) {
                // Convert string to Title Case (basic capitalization)
                function titleCase(str) {
                    return str.replace(/\w\S*/g, function(txt){
                        return txt.charAt(0).toUpperCase() + txt.substr(1).toLowerCase();
                    });
                }
                    resultsTable.innerHTML = '';
                    
                    if (data.length === 0) {
                        resultsTable.innerHTML = `
                            <tr>
                                <td colspan="5" class="text-center text-muted">
                                    <i class="fa fa-search"></i> No staff members found.
                                    ${searchInput.value.trim() ? ' Try different search terms or filters.' : ''}
                                </td>
                            </tr>`;
                        return;
                    }
                    
                    // Server-side pagination - no client-side slicing needed
                    // Render table rows directly from server data
                    data.forEach(function(staff) {
                        const tr = document.createElement('tr');
                        tr.ondblclick = function() {
                            // Log audit view action
                            fetch('?ajax=1&audit_view=1&svcNo=' + encodeURIComponent(staff.svcNo));
                            // Redirect to view page using svcNo
                            window.location.href = 'view_staff.php?svcNo=' + encodeURIComponent(staff.svcNo);
                        };
                        
                        tr.innerHTML =
                            '<td class="fw-bold">' + escapeHtml(staff.svcNo) + '</td>' +
                            '<td><span class="badge bg-secondary">' + escapeHtml(staff.rank) + '</span></td>' +
                            '<td>' + escapeHtml(titleCase(staff.name)) + '</td>' +
                            '<td>' + escapeHtml(staff.unit) + '</td>' +
                            '<td>' +
                                '<a href="?svcNo=' + encodeURIComponent(staff.svcNo) + '" class="btn btn-primary btn-sm me-1" title="Edit ' + escapeHtml(titleCase(staff.name)) + '">' +
                                    '<i class="fa fa-edit"></i> Edit' +
                                '</a>' +
                                '<a href="view_staff.php?svcNo=' + encodeURIComponent(staff.svcNo) + '" class="btn btn-info btn-sm" title="View ' + escapeHtml(titleCase(staff.name)) + '">' +
                                    '<i class="fa fa-eye"></i> View' +
                                '</a>' +
                            '</td>';
                        resultsTable.appendChild(tr);
                    });
                    
                    // Add pagination info row showing server-side pagination info
                    const start = (currentPage - 1) * itemsPerPage + 1;
                    const end = Math.min(currentPage * itemsPerPage, totalItems);
                    const countRow = document.createElement('tr');
                    countRow.className = 'table-info';
                    countRow.innerHTML = `
                        <td colspan="6" class="text-center">
                            <small><i class="fa fa-info-circle"></i> Showing ${start} to ${end} of ${totalItems} staff members (Page ${currentPage} of ${totalPages})</small>
                        </td>`;
                    resultsTable.appendChild(countRow);
                    
                    // Update pagination controls
                    renderPagination();
                }
                
                function fetchResults() {
                    const queryString = buildSearchQuery();
                    
                    // Add server-side pagination parameters
                    const url = new URL(window.location.origin + window.location.pathname + '?' + queryString);
                    url.searchParams.append('exclude_inactive', '1');
                    // Ensure server-side AJAX handler is invoked
                    url.searchParams.append('ajax', '1');
                    url.searchParams.append('page', currentPage);
                    url.searchParams.append('per_page', itemsPerPage);
                    
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
                            
                            // Handle new server-side pagination response format
                            if (data && data.data && data.pagination) {
                                // Server returned pagination data
                                currentData = data.data;
                                totalItems = data.pagination.total_items;
                                totalPages = data.pagination.total_pages;
                                currentPage = data.pagination.current_page;
                                itemsPerPage = data.pagination.per_page;
                                
                                // Render the table with server data
                                renderTable(currentData);
                            } else if (Array.isArray(data)) {
                                // Fallback: old format without pagination
                                currentData = data;
                                totalItems = data.length;
                                totalPages = 1;
                                
                                // Render the table
                                renderTable(currentData);
                            } else {
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
                    currentPage = 1; // Reset to first page
                    fetchResults();
                }
                
                // Initialize search
                fetchResults();
                
                // Event listeners
                searchInput.addEventListener('input', function() {
                    clearTimeout(typingTimer);
                    currentPage = 1; // Reset to first page on new search
                    typingTimer = setTimeout(fetchResults, 300);
                });
                
                filterRank.addEventListener('change', function() {
                    currentPage = 1; // Reset to first page on filter change
                    fetchResults();
                });
                filterUnit.addEventListener('change', function() {
                    currentPage = 1; // Reset to first page on filter change
                    fetchResults();
                });
                filterStatus.addEventListener('change', function() {
                    currentPage = 1; // Reset to first page on filter change
                    fetchResults();
                });
                
                // Per-page selector change event
                perPageSelect.addEventListener('change', function() {
                    itemsPerPage = parseInt(this.value);
                    currentPage = 1; // Reset to first page
                    fetchResults();
                });
                
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
    <input type="hidden" name="svcNo" value="<?= htmlspecialchars($staff->svcNo ?? '') ?>">
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
                <p><strong>Service Number:</strong> <?= htmlspecialchars($staff->svcNo ?? 'Not set') ?></p>
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
            <div class="step" data-step="6">
                <div class="step-icon"><i class="fa fa-map-marker-alt"></i></div>
                <div class="step-label">Posting History</div>
            </div>
            <div class="step" data-step="7">
                <div class="step-icon"><i class="fa fa-trophy"></i></div>
                <div class="step-label">Awards</div>
            </div>
            <div class="step" data-step="8">
                <div class="step-icon"><i class="fa fa-gavel"></i></div>
                <div class="step-label">Disciplinary</div>
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
                               value="<?= htmlspecialchars($staff->svcNo ?? '') ?>"
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
                               value="<?= htmlspecialchars($staff->fName ?? '') ?>"
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
                               value="<?= htmlspecialchars($staff->lName ?? '') ?>"
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
                                        <?= (!empty($staff) && $staff->rankId == $rank->rankID) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($rank->rankName) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small id="rankID-help" class="form-text text-muted">
                            Current: <strong><?= htmlspecialchars(!empty($staff) ? ($rankMap[$staff->rankId] ?? 'Unknown') : 'Unknown') ?></strong>
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
                                        <?= (!empty($staff) && $staff->unitId == $unit->unitID) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($unit->unitName) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small id="unitID-help" class="form-text text-muted">
                            Current: <strong><?= htmlspecialchars(!empty($staff) ? ($unitMap[$staff->unitId] ?? 'Unknown') : 'Unknown') ?></strong>
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
                                        <?= (!empty($staff) && isset($staff->corpsId) && $staff->corpsId == $corpsItem->id) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($corpsItem->name) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <!-- NRC -->
                    <div class="col-md-6">
                        <label for="NRC" class="form-label">NRC <span class="text-muted">(Optional)</span></label>
                        <input type="text" class="form-control <?= isset($validationErrors['NRC']) ? 'is-invalid' : '' ?>" name="NRC" id="NRC"
                               value="<?= htmlspecialchars($staff->NRC ?? '') ?>" maxlength="<?= MAX_NRC_LENGTH ?>"
                               placeholder="123456/12/1">
                        <small id="NRC-help" class="form-text text-muted">Format: XXXXXX/XX/X (e.g., 123456/12/1)</small>
                        <div class="invalid-feedback" id="NRC-error"><?= $validationErrors['NRC'] ?? '' ?></div>
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
                                        <?= (!empty($staff) && ($staff->marital ?? $staff->maritalStatus ?? $staff->marital_status ?? '') === $status) ? 'selected' : '' ?>>
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
                        <input type="tel" class="form-control <?= isset($validationErrors['tel']) ? 'is-invalid' : '' ?>" name="tel" id="tel"
                               value="<?= htmlspecialchars($staff->tel ?? '') ?>"
                               placeholder="+260 XXX XXX XXX" maxlength="20">
                        <small class="form-text text-muted">Contact telephone number (e.g., +260 977 123 456)</small>
                        <div class="invalid-feedback" id="tel-error"><?= $validationErrors['tel'] ?? '' ?></div>
                    </div>
                </div>

                <div class="row mb-3">
                    <!-- Email -->
                    <div class="col-md-6">
                        <label for="email" class="form-label">Email Address <span class="text-muted">(Optional)</span></label>
                        <input type="email" class="form-control <?= isset($validationErrors['email']) ? 'is-invalid' : '' ?>" name="email" id="email"
                               value="<?= htmlspecialchars($staff->email ?? '') ?>"
                               placeholder="example@mail.com" maxlength="100">
                        <small class="form-text text-muted">Official email address (leave empty if not available)</small>
                        <div class="invalid-feedback" id="email-error"><?= $validationErrors['email'] ?? '' ?></div>
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
                            $religions = ['Christian', 'Islam', 'Hinduism', 'Buddhism', 'Judaism', 'Traditional', 'Other', 'None'];
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
                        <input type="number" class="form-control <?= isset($validationErrors['height']) ? 'is-invalid' : '' ?>" name="height" id="height"
                               value="<?= htmlspecialchars($staff->height ?? '') ?>"
                               placeholder="170" min="120" max="250" step="0.1">
                        <small class="form-text text-muted">Height in centimeters (120-250cm)</small>
                        <div class="invalid-feedback" id="height-error"><?= $validationErrors['height'] ?? '' ?></div>
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
                            <input type="tel" class="form-control <?= isset($validationErrors['nokTel']) ? 'is-invalid' : '' ?>" name="nokTel" id="nokTel"
                                   value="<?= htmlspecialchars($staff->nokTel ?? '') ?>"
                                   placeholder="+260 XXX XXX XXX" maxlength="20">
                            <div class="invalid-feedback" id="nokTel-error"><?= $validationErrors['nokTel'] ?? '' ?></div>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="nokNrc" class="form-label">Next of Kin NRC <span class="text-muted">(Optional)</span></label>
                            <input type="text" class="form-control <?= isset($validationErrors['nokNrc']) ? 'is-invalid' : '' ?>" name="nokNrc" id="nokNrc"
                                   value="<?= htmlspecialchars($staff->nokNrc ?? '') ?>"
                                   placeholder="123456/12/1" maxlength="<?= MAX_NRC_LENGTH ?>">
                            <small class="form-text text-muted">Format: XXXXXX/XX/X (e.g., 123456/12/1)</small>
                            <div class="invalid-feedback" id="nokNrc-error"><?= $validationErrors['nokNrc'] ?? '' ?></div>
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
                                                    <?= (!empty($staff) && ($staff->bootSize ?? $staff->bsize ?? '') == $i) ? 'selected' : '' ?>>
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
                                                    <?= (!empty($staff) && ($staff->shoeSize ?? $staff->ssize ?? '') == $i) ? 'selected' : '' ?>>
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
                                                    <?= (!empty($staff) && ($staff->hDress ?? $staff->hdress ?? '') == $i) ? 'selected' : '' ?>>
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

                            <?php if (isset($staff) && $staff): ?>
                                <?php 
                                    $retirementInfo = calculateRetirementInfo($staff->DOB ?? null, $staff->attestDate ?? null);
                                ?>
                                
                                <!-- Retirement Information Display -->
                                <div class="row mb-3">
                                    <div class="col-12">
                                        <div class="card bg-light">
                                            <div class="card-body">
                                                <h6 class="card-title text-primary mb-3">
                                                    <i class="fa fa-calendar-times"></i> Retirement Information
                                                </h6>
                                                
                                                <div class="row">
                                                    <!-- Expected Runout Date (Age 65) -->
                                                    <div class="col-md-6 mb-2">
                                                        <strong><i class="fa fa-hourglass-end text-primary"></i> Expected Runout Date (Age 65):</strong>
                                                        <div class="mt-1" data-retirement="runout">
                                                            <?php if ($retirementInfo['runout']): ?>
                                                                <?php 
                                                                    $runout = $retirementInfo['runout'];
                                                                    $urgencyClass = getRetirementUrgencyClass($runout['yearsRemaining']);
                                                                ?>
                                                                <div><?=$runout['formatted']?></div>
                                                                <small class="<?=$urgencyClass?>">
                                                                    <i class="fa fa-clock"></i> <?=$runout['remaining']?> remaining
                                                                </small>
                                                            <?php else: ?>
                                                                <span class="text-muted">N/A (Date of birth not recorded)</span>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                    
                                                    <!-- Expected Early Retirement (20 Years Service) -->
                                                    <div class="col-md-6 mb-2">
                                                        <strong><i class="fa fa-calendar-alt text-primary"></i> Expected Early Retirement (20 Years Service):</strong>
                                                        <div class="mt-1" data-retirement="earlyRetirement">
                                                            <?php if ($retirementInfo['earlyRetirement']): ?>
                                                                <?php 
                                                                    $earlyRet = $retirementInfo['earlyRetirement'];
                                                                    $urgencyClass = getRetirementUrgencyClass($earlyRet['yearsRemaining']);
                                                                ?>
                                                                <div><?=$earlyRet['formatted']?></div>
                                                                <small class="<?=$urgencyClass?>">
                                                                    <i class="fa fa-clock"></i> <?=$earlyRet['remaining']?> 
                                                                    <?=$earlyRet['isPast'] ? '' : 'remaining'?>
                                                                </small>
                                                            <?php else: ?>
                                                                <span class="text-muted">N/A (Enlistment date not recorded)</span>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                </div>
                                                
                                                <small class="text-muted d-block mt-2">
                                                    <i class="fa fa-info-circle"></i> These dates are automatically calculated and update when DOB or Attestation Date changes.
                                                </small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <div class="alert alert-info mt-3">
                                <i class="fa fa-info-circle"></i> <strong>Note:</strong> Posting History, Awards & Commendations, and Disciplinary Records are now managed in dedicated sections (Steps 6-8) for better organization and tracking.
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

                    <!-- Step Navigation Buttons -->
                    <div class="d-flex justify-content-between mt-4">
                        <button type="button" class="btn btn-secondary" onclick="previousStep(4)">Previous</button>
                        <button type="button" class="btn btn-primary" onclick="nextStep(6)">Next Step</button>
                    </div>
                </div>

                <!-- Posting History - Step 6 -->
                <div class="form-step" id="step6">
                    <div class="card mb-4">
                        <div class="card-header bg-primary text-white">
                            <h5 class="mb-0"><i class="fa fa-map-marker-alt"></i> Posting History</h5>
                        </div>
                        <div class="card-body">
                            <div class="alert alert-info">
                                <i class="fa fa-info-circle"></i> Track this staff member's posting history including unit assignments, transfers, and duty stations.
                            </div>
                            <div class="mb-3">
                                <small class="text-muted">
                                    <strong>Expected fields:</strong> Unit, Location, Role/Appointment, Start/End Dates, Posting Order Reference
                                </small>
                            </div>
                            <div id="postingsList">
                                <!-- Dynamic postings will be added here -->
                            </div>
                            <button type="button" class="btn btn-outline-primary" id="addPostingBtn">
                                <i class="fa fa-plus"></i> Add Posting
                            </button>
                        </div>
                    </div>
                    
                    <!-- Step Navigation Buttons -->
                    <div class="d-flex justify-content-between mt-4">
                        <button type="button" class="btn btn-secondary" onclick="previousStep(5)">Previous</button>
                        <button type="button" class="btn btn-primary" onclick="nextStep(7)">Next Step</button>
                    </div>
                </div>

                <!-- Awards & Commendations - Step 7 -->
                <div class="form-step" id="step7">
                    <div class="card mb-4">
                        <div class="card-header bg-warning text-dark">
                            <h5 class="mb-0"><i class="fa fa-trophy"></i> Awards & Commendations</h5>
                        </div>
                        <div class="card-body">
                            <div class="alert alert-info">
                                <i class="fa fa-info-circle"></i> Record awards, commendations, letters of appreciation, and other recognitions (separate from medals).
                            </div>
                            <div class="mb-3">
                                <small class="text-muted">
                                    <strong>Expected fields:</strong> Award Type, Award Name, Award Date, Awarded By, Citation, Certificate Number
                                </small>
                            </div>
                            <div id="awardsList">
                                <!-- Dynamic awards will be added here -->
                            </div>
                            <button type="button" class="btn btn-outline-primary" id="addAwardBtn">
                                <i class="fa fa-plus"></i> Add Award
                            </button>
                        </div>
                    </div>
                    
                    <!-- Step Navigation Buttons -->
                    <div class="d-flex justify-content-between mt-4">
                        <button type="button" class="btn btn-secondary" onclick="previousStep(6)">Previous</button>
                        <button type="button" class="btn btn-primary" onclick="nextStep(8)">Next Step</button>
                    </div>
                </div>

                <!-- Disciplinary Record - Step 8 -->
                <div class="form-step" id="step8">
                    <div class="card mb-4">
                        <div class="card-header bg-danger text-white">
                            <h5 class="mb-0"><i class="fa fa-exclamation-triangle"></i> Disciplinary Records</h5>
                        </div>
                        <div class="card-body">
                            <div class="alert alert-warning">
                                <i class="fa fa-lock"></i> <strong>Confidential:</strong> This information is restricted and should be handled with appropriate discretion.
                            </div>
                            <div class="alert alert-info">
                                <i class="fa fa-info-circle"></i> Document disciplinary actions, incidents, and their outcomes for personnel record keeping.
                            </div>
                            <div class="mb-3">
                                <small class="text-muted">
                                    <strong>Expected fields:</strong> Incident Type, Date, Description, Action Taken, Case Reference, Outcome
                                </small>
                            </div>
                            <div id="disciplinaryList">
                                <!-- Dynamic disciplinary records will be added here -->
                            </div>
                            <button type="button" class="btn btn-outline-primary" id="addDisciplinaryBtn">
                                <i class="fa fa-plus"></i> Add Disciplinary Record
                            </button>
                        </div>
                    </div>

                    <!-- Final Step Navigation Buttons -->
                    <div class="d-flex justify-content-between mt-4">
                        <button type="button" class="btn btn-secondary" onclick="previousStep(7)">Previous</button>
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

<!-- Bootstrap JS and jQuery already loaded above -->
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
    let postingCounter = 0;
    let awardCounter = 0;
    let disciplinaryCounter = 0;

    // Dynamic field functions
    function addOperationRow(data = {}) {
        const container = document.getElementById('operationsList');
        const index = operationCounter++;
        const html = `
            <div class="card mb-3 operation-item" data-index="${index}">
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Operation ID</label>
                            <input type="number" name="operations[${index}][opID]" class="form-control" 
                                   placeholder="Operation ID" value="${data.opID || data.operation_id || ''}" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Appointment ID</label>
                            <input type="number" name="operations[${index}][apptID]" class="form-control" 
                                   placeholder="Appointment ID" value="${data.apptID || ''}">
                        </div>
                    </div>
                    <div class="row g-3 mt-2">
                        <div class="col-md-4">
                            <label class="form-label">Start Date</label>
                            <input type="date" name="operations[${index}][startDate]" class="form-control" 
                                   value="${data.startDate || ''}">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">End Date</label>
                            <input type="date" name="operations[${index}][endDate]" class="form-control" 
                                   value="${data.endDate || ''}">
                        </div>
                        <div class="col-md-4 d-flex align-items-end">
                            <button type="button" class="btn btn-danger w-100" onclick="removeOperationRow(${index})">
                                <i class="fa fa-trash"></i> Remove
                            </button>
                        </div>
                    </div>
                    <div class="row g-3 mt-2">
                        <div class="col-md-12">
                            <label class="form-label">Reason Left</label>
                            <textarea name="operations[${index}][reasonLeft]" class="form-control" rows="2" 
                                      placeholder="Reason for leaving this operation">${data.reasonLeft || data.reason_left || ''}</textarea>
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
                            <input type="date" name="deployments[${index}][startDate]" class="form-control" 
                                   value="${data.startDate || ''}">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">End Date</label>
                            <input type="date" name="deployments[${index}][endDate]" class="form-control" 
                                   value="${data.endDate || ''}">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Duration (Months) <small class="text-muted">Auto-calculated</small></label>
                            <input type="number" name="deployments[${index}][durationMonths]" class="form-control" 
                                   placeholder="Auto-calculated" value="${data.durationMonths || ''}" min="0" readonly>
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
                            <select name="deployments[${index}][rank_during_deployment]" class="form-select">
                                ${buildRanksDropdown(data.rank_during_deployment)}
                            </select>
                            <small class="text-muted">Rank held (${window.staffRankCategory || 'All'})</small>
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
                        <div class="col-md-12">
                            <label class="form-label">Commanding Officer</label>
                            <input type="text" name="deployments[${index}][commanding_officer]" class="form-control" 
                                   placeholder="Commanding officer" value="${data.commanding_officer || ''}">
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
        
        // Add event listeners for auto-calculating duration
        const deploymentCard = container.lastElementChild;
        const startDateInput = deploymentCard.querySelector(`input[name="deployments[${index}][startDate]"]`);
        const endDateInput = deploymentCard.querySelector(`input[name="deployments[${index}][endDate]"]`);
        const durationInput = deploymentCard.querySelector(`input[name="deployments[${index}][durationMonths]"]`);
        
        function calculateDeploymentDuration() {
            if (startDateInput.value && endDateInput.value) {
                const start = new Date(startDateInput.value);
                const end = new Date(endDateInput.value);
                if (end >= start) {
                    const diffTime = Math.abs(end - start);
                    const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
                    const months = Math.round(diffDays / 30.44); // Average days per month
                    durationInput.value = months;
                } else {
                    durationInput.value = '';
                }
            }
        }
        
        startDateInput.addEventListener('change', calculateDeploymentDuration);
        endDateInput.addEventListener('change', calculateDeploymentDuration);
        
        console.log('✈️ Added deployment row #' + index);
    }

    function addEducationRow(data = {}) {
        const container = document.getElementById('educationList');
        const index = educationCounter++;
        const html = `
            <div class="card mb-3 education-item" data-index="${index}">
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-3">
                            <label class="form-label">Institution ID</label>
                            <input type="number" name="education[${index}][instId]" class="form-control" 
                                   placeholder="Institution ID" value="${data.instId || data.institution || ''}" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Course ID</label>
                            <input type="number" name="education[${index}][cseId]" class="form-control" 
                                   placeholder="Course ID" value="${data.cseId || ''}">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Qualification</label>
                            <input type="text" name="education[${index}][qualification]" class="form-control" 
                                   placeholder="e.g., Bachelor's Degree" value="${data.qualification || ''}">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Authority ID</label>
                            <input type="number" name="education[${index}][authorityId]" class="form-control" 
                                   placeholder="Authority ID" value="${data.authorityId || ''}">
                        </div>
                    </div>
                    <div class="row g-3 mt-2">
                        <div class="col-md-2">
                            <label class="form-label">Start Year</label>
                            <input type="number" name="education[${index}][yearStarted]" class="form-control" 
                                   placeholder="2020" value="${data.yearStarted || data.year_started || ''}" min="1900" max="2030">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">End Year</label>
                            <input type="number" name="education[${index}][yearCompleted]" class="form-control" 
                                   placeholder="2024" value="${data.yearCompleted || data.year_completed || ''}" min="1900" max="2030">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Grade</label>
                            <input type="text" name="education[${index}][grade]" class="form-control" 
                                   placeholder="A, B+, etc." value="${data.grade || data.grade_obtained || ''}">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Result</label>
                            <input type="text" name="education[${index}][result]" class="form-control" 
                                   placeholder="Pass, Distinction, etc." value="${data.result || ''}">
                        </div>
                        <div class="col-md-3 d-flex align-items-end">
                            <button type="button" class="btn btn-danger w-100" onclick="removeEducationRow(${index})">
                                <i class="fa fa-trash"></i> Remove
                            </button>
                        </div>
                    </div>
                    <div class="row g-3 mt-2">
                        <div class="col-md-8">
                            <label class="form-label">Certificate Number</label>
                            <input type="text" name="education[${index}][certificate]" class="form-control" 
                                   placeholder="Certificate number or reference" value="${data.certificate || ''}">
                        </div>
                        <div class="col-md-4">
                            <div class="form-check mt-4">
                                <input class="form-check-input" type="checkbox" name="education[${index}][isHighest]" 
                                       value="1" id="highest_${index}" ${data.isHighest || data.is_highest_qualification ? 'checked' : ''}>
                                <label class="form-check-label" for="highest_${index}">
                                    Highest qualification
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
                            <label class="form-label">Duration (Days) <small class="text-muted">Auto-calculated</small></label>
                            <input type="number" name="skills[${index}][duration_days]" class="form-control" 
                                   placeholder="Auto-calculated" value="${data.duration_days || ''}" min="0" readonly>
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
                            <input type="date" name="skills[${index}][startDate]" class="form-control" 
                                   value="${data.startDate || ''}">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">End Date</label>
                            <input type="date" name="skills[${index}][endDate]" class="form-control" 
                                   value="${data.endDate || ''}">
                        </div>
                    </div>
                    <div class="row g-3 mt-2">
                        <div class="col-md-3">
                            <label class="form-label">Certificate Number</label>
                            <input type="text" name="skills[${index}][certificateNumber]" class="form-control" 
                                   placeholder="Certificate #" value="${data.certificateNumber || ''}">
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
        
        // Add event listeners for auto-calculating duration
        const skillCard = container.lastElementChild;
        const startDateInput = skillCard.querySelector(`input[name="skills[${index}][startDate]"]`);
        const endDateInput = skillCard.querySelector(`input[name="skills[${index}][endDate]"]`);
        const durationInput = skillCard.querySelector(`input[name="skills[${index}][duration_days]"]`);
        
        function calculateSkillDuration() {
            if (startDateInput.value && endDateInput.value) {
                const start = new Date(startDateInput.value);
                const end = new Date(endDateInput.value);
                if (end >= start) {
                    const diffTime = Math.abs(end - start);
                    const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24)) + 1; // +1 to include both start and end days
                    durationInput.value = diffDays;
                } else {
                    durationInput.value = '';
                }
            }
        }
        
        startDateInput.addEventListener('change', calculateSkillDuration);
        endDateInput.addEventListener('change', calculateSkillDuration);
        
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
    window.removeOperationRow = removeOperationRow;

    function removeDeploymentRow(index) {
        const item = document.querySelector(`.deployment-item[data-index="${index}"]`);
        if (item && confirm('Are you sure you want to remove this deployment?')) {
            item.remove();
            console.log('🗑️ Removed deployment row #' + index);
        }
    }
    window.removeDeploymentRow = removeDeploymentRow;

    function removeEducationRow(index) {
        const item = document.querySelector(`.education-item[data-index="${index}"]`);
        if (item && confirm('Are you sure you want to remove this education record?')) {
            item.remove();
            console.log('🗑️ Removed education row #' + index);
        }
    }
    // Make function globally accessible
    window.removeEducationRow = removeEducationRow;

    function removeSkillRow(index) {
        const item = document.querySelector(`.skill-item[data-index="${index}"]`);
        if (item && confirm('Are you sure you want to remove this skill/course?')) {
            item.remove();
            console.log('🗑️ Removed skill row #' + index);
        }
    }
    window.removeSkillRow = removeSkillRow;

    // Helper function to build ranks dropdown
    function buildRanksDropdown(selectedValue = '') {
        let html = '<option value="">Select Rank</option>';
        if (window.ranksOptions && window.ranksOptions.length > 0) {
            window.ranksOptions.forEach(rank => {
                const selected = (selectedValue && selectedValue == rank.id) ? 'selected' : 
                                (selectedValue && (selectedValue == rank.abbreviation || selectedValue == rank.name)) ? 'selected' : '';
                html += `<option value="${rank.id}" ${selected}>${rank.abbreviation} - ${rank.name}</option>`;
            });
        }
        return html;
    }

    // ==================== POSTING HISTORY FUNCTIONS ====================
    function addPostingRow(data = {}) {
        const container = document.getElementById('postingsList');
        const index = postingCounter++;
        
        // Build units dropdown options
        let unitsOptionsHTML = '<option value="">Select Unit</option>';
        if (window.unitsOptions && window.unitsOptions.length > 0) {
            window.unitsOptions.forEach(unit => {
                const selected = (data.unitId && data.unitId == unit.id) ? 'selected' : '';
                unitsOptionsHTML += `<option value="${unit.id}" ${selected}>${unit.name}${unit.location ? ' - ' + unit.location : ''}</option>`;
            });
        }
        
        // Build ranks dropdown options (from staff member's category)
        let ranksOptionsHTML = '<option value="">Select Rank</option>';
        if (window.ranksOptions && window.ranksOptions.length > 0) {
            window.ranksOptions.forEach(rank => {
                const selected = (data.rankId && data.rankId == rank.id) ? 'selected' : '';
                ranksOptionsHTML += `<option value="${rank.id}" ${selected}>${rank.abbreviation} - ${rank.name}</option>`;
            });
        }
        
        const html = `
            <div class="card mb-3 posting-item" data-index="${index}">
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Unit <span class="text-danger">*</span></label>
                            <select name="postings[${index}][unitId]" class="form-select" required>
                                ${unitsOptionsHTML}
                            </select>
                            <small class="text-muted">Assigned unit/formation</small>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Location</label>
                            <input type="text" name="postings[${index}][location]" class="form-control" 
                                   placeholder="Physical location/base" value="${data.location || ''}">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Appointment/Role</label>
                            <input type="text" name="postings[${index}][appointment_role]" class="form-control" 
                                   placeholder="e.g., Company Commander" value="${data.appointment_id || data.appointment_role || ''}">
                        </div>
                    </div>
                    <div class="row g-3 mt-2">
                        <div class="col-md-3">
                            <label class="form-label">Rank at Posting</label>
                            <select name="postings[${index}][rankId]" class="form-select">
                                ${ranksOptionsHTML}
                            </select>
                            <small class="text-muted">Rank held (${window.staffRankCategory || 'All'})</small>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Start Date <span class="text-danger">*</span></label>
                            <input type="date" name="postings[${index}][startDate]" class="form-control posting-start-date" 
                                   value="${data.startDate || ''}" required onchange="calculatePostingDuration(${index})">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">End Date</label>
                            <input type="date" name="postings[${index}][endDate]" class="form-control posting-end-date" 
                                   value="${data.endDate || ''}" onchange="calculatePostingDuration(${index})">
                            <small class="text-muted">Leave blank if current</small>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Duration (Months)</label>
                            <input type="number" name="postings[${index}][durationMonths]" class="form-control posting-duration" 
                                   value="${data.durationMonths || ''}" readonly>
                            <small class="text-muted">Auto-calculated</small>
                        </div>
                    </div>
                    <div class="row g-3 mt-2">
                        <div class="col-md-6">
                            <label class="form-label">Posting Order Reference</label>
                            <input type="text" name="postings[${index}][posting_order_reference]" class="form-control" 
                                   placeholder="Official order number" value="${data.posting_order_reference || ''}">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Remarks</label>
                            <textarea name="postings[${index}][remarks]" class="form-control" rows="1" 
                                      placeholder="Additional notes">${data.remarks || ''}</textarea>
                        </div>
                    </div>
                    <div class="row g-3 mt-2">
                        <div class="col-md-12">
                            <button type="button" class="btn btn-danger btn-sm" onclick="removePostingRow(${index})">
                                <i class="fa fa-trash"></i> Remove Posting
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        `;
        container.insertAdjacentHTML('beforeend', html);
        
        // Calculate duration if dates exist
        if (data.startDate) {
            setTimeout(() => calculatePostingDuration(index), 100);
        }
        
        console.log('➕ Added posting row #' + index);
    }

    function removePostingRow(index) {
        const item = document.querySelector(`.posting-item[data-index="${index}"]`);
        if (item && confirm('Are you sure you want to remove this posting?')) {
            item.remove();
            console.log('🗑️ Removed posting row #' + index);
        }
    }
    window.removePostingRow = removePostingRow;

    function calculatePostingDuration(index) {
        const posting = document.querySelector(`.posting-item[data-index="${index}"]`);
        if (!posting) return;

        const startDate = posting.querySelector('.posting-start-date').value;
        const endDate = posting.querySelector('.posting-end-date').value || new Date().toISOString().split('T')[0];
        
        if (startDate && endDate) {
            const start = new Date(startDate);
            const end = new Date(endDate);
            const months = Math.round((end - start) / (1000 * 60 * 60 * 24 * 30.44));
            posting.querySelector('.posting-duration').value = Math.max(0, months);
        }
    }

    // ==================== AWARDS & COMMENDATIONS FUNCTIONS ====================
    function addAwardRow(data = {}) {
        const container = document.getElementById('awardsList');
        const index = awardCounter++;
        const html = `
            <div class="card mb-3 award-item" data-index="${index}">
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Award Type <span class="text-danger">*</span></label>
                            <select name="awards[${index}][award_type]" class="form-select" required>
                                <option value="">Select Type</option>
                                <option value="Commendation" ${data.award_type === 'Commendation' ? 'selected' : ''}>Commendation</option>
                                <option value="Certificate" ${data.award_type === 'Certificate' ? 'selected' : ''}>Certificate</option>
                                <option value="Letter of Appreciation" ${data.award_type === 'Letter of Appreciation' ? 'selected' : ''}>Letter of Appreciation</option>
                                <option value="Meritorious Service" ${data.award_type === 'Meritorious Service' ? 'selected' : ''}>Meritorious Service</option>
                                <option value="Good Conduct" ${data.award_type === 'Good Conduct' ? 'selected' : ''}>Good Conduct</option>
                                <option value="Other" ${data.award_type === 'Other' ? 'selected' : ''}>Other</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Award Name <span class="text-danger">*</span></label>
                            <input type="text" name="awards[${index}][award_name]" class="form-control" 
                                   placeholder="Award name" value="${data.award_name || ''}" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Award Date <span class="text-danger">*</span></label>
                            <input type="date" name="awards[${index}][award_date]" class="form-control" 
                                   value="${data.award_date || ''}" required>
                        </div>
                    </div>
                    <div class="row g-3 mt-2">
                        <div class="col-md-4">
                            <label class="form-label">Awarded By</label>
                            <input type="text" name="awards[${index}][awarded_by]" class="form-control" 
                                   placeholder="Awarding authority" value="${data.awarded_by || ''}">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Certificate Number</label>
                            <input type="text" name="awards[${index}][certificateNumber]" class="form-control" 
                                   placeholder="Certificate/document number" value="${data.certificateNumber || ''}">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Gazette Reference</label>
                            <input type="text" name="awards[${index}][gazette_reference]" class="form-control" 
                                   placeholder="Gazette reference" value="${data.gazette_reference || ''}">
                        </div>
                    </div>
                    <div class="row g-3 mt-2">
                        <div class="col-md-12">
                            <label class="form-label">Citation</label>
                            <textarea name="awards[${index}][citation]" class="form-control" rows="2" 
                                      placeholder="Reason for award or citation text">${data.citation || ''}</textarea>
                        </div>
                    </div>
                    <div class="row g-3 mt-2">
                        <div class="col-md-12">
                            <label class="form-label">Remarks</label>
                            <textarea name="awards[${index}][remarks]" class="form-control" rows="1" 
                                      placeholder="Additional notes">${data.remarks || ''}</textarea>
                        </div>
                    </div>
                    <div class="row g-3 mt-2">
                        <div class="col-md-12">
                            <button type="button" class="btn btn-danger btn-sm" onclick="removeAwardRow(${index})">
                                <i class="fa fa-trash"></i> Remove Award
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        `;
        container.insertAdjacentHTML('beforeend', html);
        console.log('➕ Added award row #' + index);
    }

    function removeAwardRow(index) {
        const item = document.querySelector(`.award-item[data-index="${index}"]`);
        if (item && confirm('Are you sure you want to remove this award?')) {
            item.remove();
            console.log('🗑️ Removed award row #' + index);
        }
    }
    window.removeAwardRow = removeAwardRow;

    // ==================== DISCIPLINARY RECORD FUNCTIONS ====================
    function addDisciplinaryRow(data = {}) {
        const container = document.getElementById('disciplinaryList');
        const index = disciplinaryCounter++;
        const html = `
            <div class="card mb-3 disciplinary-item border-warning" data-index="${index}">
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Incident Type <span class="text-danger">*</span></label>
                            <select name="disciplinary[${index}][incident_type]" class="form-select" required>
                                <option value="">Select Type</option>
                                <option value="Misconduct" ${data.incident_type === 'Misconduct' ? 'selected' : ''}>Misconduct</option>
                                <option value="AWOL/Absence" ${data.incident_type === 'AWOL/Absence' ? 'selected' : ''}>AWOL/Absence</option>
                                <option value="Insubordination" ${data.incident_type === 'Insubordination' ? 'selected' : ''}>Insubordination</option>
                                <option value="Neglect of Duty" ${data.incident_type === 'Neglect of Duty' ? 'selected' : ''}>Neglect of Duty</option>
                                <option value="Violation of Regulations" ${data.incident_type === 'Violation of Regulations' ? 'selected' : ''}>Violation of Regulations</option>
                                <option value="Other" ${data.incident_type === 'Other' ? 'selected' : ''}>Other</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Incident Date <span class="text-danger">*</span></label>
                            <input type="date" name="disciplinary[${index}][incident_date]" class="form-control" 
                                   value="${data.incident_date || ''}" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Case Reference</label>
                            <input type="text" name="disciplinary[${index}][case_reference]" class="form-control" 
                                   placeholder="Case/file reference" value="${data.case_reference || ''}">
                        </div>
                    </div>
                    <div class="row g-3 mt-2">
                        <div class="col-md-12">
                            <label class="form-label">Description <span class="text-danger">*</span></label>
                            <textarea name="disciplinary[${index}][description]" class="form-control" rows="3" 
                                      placeholder="Detailed description of the incident" required>${data.description || ''}</textarea>
                        </div>
                    </div>
                    <div class="row g-3 mt-2">
                        <div class="col-md-6">
                            <label class="form-label">Action Taken</label>
                            <select name="disciplinary[${index}][action_taken]" class="form-select">
                                <option value="">Select Action</option>
                                <option value="Verbal Warning" ${data.action_taken === 'Verbal Warning' ? 'selected' : ''}>Verbal Warning</option>
                                <option value="Written Warning" ${data.action_taken === 'Written Warning' ? 'selected' : ''}>Written Warning</option>
                                <option value="Reprimand" ${data.action_taken === 'Reprimand' ? 'selected' : ''}>Reprimand</option>
                                <option value="Confinement" ${data.action_taken === 'Confinement' ? 'selected' : ''}>Confinement</option>
                                <option value="Demotion" ${data.action_taken === 'Demotion' ? 'selected' : ''}>Demotion</option>
                                <option value="Fine" ${data.action_taken === 'Fine' ? 'selected' : ''}>Fine</option>
                                <option value="Other" ${data.action_taken === 'Other' ? 'selected' : ''}>Other</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Disciplinary Authority</label>
                            <input type="text" name="disciplinary[${index}][disciplinary_authority]" class="form-control" 
                                   placeholder="Authority who issued action" value="${data.disciplinary_authority || ''}">
                        </div>
                    </div>
                    <div class="row g-3 mt-2">
                        <div class="col-md-3">
                            <label class="form-label">Start Date</label>
                            <input type="date" name="disciplinary[${index}][startDate]" class="form-control disciplinary-start-date" 
                                   value="${data.startDate || ''}" onchange="calculateDisciplinaryDuration(${index})">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">End Date</label>
                            <input type="date" name="disciplinary[${index}][endDate]" class="form-control disciplinary-end-date" 
                                   value="${data.endDate || ''}" onchange="calculateDisciplinaryDuration(${index})">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Duration (Days)</label>
                            <input type="number" name="disciplinary[${index}][duration_days]" class="form-control disciplinary-duration" 
                                   value="${data.duration_days || ''}" readonly>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Outcome</label>
                            <select name="disciplinary[${index}][outcome]" class="form-select">
                                <option value="">Select Outcome</option>
                                <option value="Resolved" ${data.outcome === 'Resolved' ? 'selected' : ''}>Resolved</option>
                                <option value="Under Review" ${data.outcome === 'Under Review' ? 'selected' : ''}>Under Review</option>
                                <option value="Appealed" ${data.outcome === 'Appealed' ? 'selected' : ''}>Appealed</option>
                                <option value="Dismissed" ${data.outcome === 'Dismissed' ? 'selected' : ''}>Dismissed</option>
                            </select>
                        </div>
                    </div>
                    <div class="row g-3 mt-2">
                        <div class="col-md-6">
                            <label class="form-label">Appeal Details</label>
                            <textarea name="disciplinary[${index}][appeal_details]" class="form-control" rows="2" 
                                      placeholder="Details of any appeal filed">${data.appeal_details || ''}</textarea>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Remarks</label>
                            <textarea name="disciplinary[${index}][remarks]" class="form-control" rows="2" 
                                      placeholder="Additional notes">${data.remarks || ''}</textarea>
                        </div>
                    </div>
                    <div class="row g-3 mt-2">
                        <div class="col-md-4">
                            <label class="form-label">Clearance Date</label>
                            <input type="date" name="disciplinary[${index}][clearance_date]" class="form-control" 
                                   value="${data.clearance_date || ''}">
                            <small class="text-muted">When record was cleared/expunged</small>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Confidential</label>
                            <select name="disciplinary[${index}][confidential]" class="form-select">
                                <option value="1" ${data.confidential !== '0' ? 'selected' : ''}>Yes (Restricted)</option>
                                <option value="0" ${data.confidential === '0' ? 'selected' : ''}>No (Visible)</option>
                            </select>
                        </div>
                        <div class="col-md-4 d-flex align-items-end">
                            <button type="button" class="btn btn-danger btn-sm w-100" onclick="removeDisciplinaryRow(${index})">
                                <i class="fa fa-trash"></i> Remove Record
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        `;
        container.insertAdjacentHTML('beforeend', html);
        console.log('➕ Added disciplinary row #' + index);
    }

    function removeDisciplinaryRow(index) {
        const item = document.querySelector(`.disciplinary-item[data-index="${index}"]`);
        if (item && confirm('Are you sure you want to remove this disciplinary record?')) {
            item.remove();
            console.log('🗑️ Removed disciplinary row #' + index);
        }
    }
    window.removeDisciplinaryRow = removeDisciplinaryRow;

    function calculateDisciplinaryDuration(index) {
        const disciplinary = document.querySelector(`.disciplinary-item[data-index="${index}"]`);
        if (!disciplinary) return;

        const startDate = disciplinary.querySelector('.disciplinary-start-date').value;
        const endDate = disciplinary.querySelector('.disciplinary-end-date').value;
        
        if (startDate && endDate) {
            const start = new Date(startDate);
            const end = new Date(endDate);
            const days = Math.round((end - start) / (1000 * 60 * 60 * 24));
            disciplinary.querySelector('.disciplinary-duration').value = Math.max(0, days);
        }
    }

    // Function to load existing data when editing a staff member
    function loadExistingData() {
        const svcNo = '<?= htmlspecialchars($staff->svcNo ?? '') ?>';
        if (svcNo) {
            // Load existing operations, deployments, education, and skills
            console.log('🔄 Loading existing data for service number:', svcNo);
            
            // For now, we'll add sample data - in production this would come from the database
            // This can be populated from PHP when the page loads
        }
    }

    // Function to validate dynamic fields before form submission
    function validateDynamicFields() {
        console.log('🔍 Validating dynamic fields...');
        let isValid = true;
        const errors = [];

        // Validate operations
        const operations = document.querySelectorAll('#operationsList .operation-item');
        console.log(`📋 Validating ${operations.length} operations...`);
        operations.forEach((op, index) => {
            const opIDField = op.querySelector('input[name*="[opID]"]');
            if (opIDField && !opIDField.value.trim()) {
                opIDField.classList.add('is-invalid');
                errors.push(`Operation #${index + 1}: Operation ID is required`);
                isValid = false;
            } else if (opIDField) {
                opIDField.classList.remove('is-invalid');
            }
        });

        // Validate deployments
        const deployments = document.querySelectorAll('#deploymentsList .deployment-item');
        console.log(`🌍 Validating ${deployments.length} deployments...`);
        deployments.forEach((dep, index) => {
            const nameField = dep.querySelector('input[name*="[deployment_name]"]');
            if (nameField && !nameField.value.trim()) {
                nameField.classList.add('is-invalid');
                errors.push(`Deployment #${index + 1}: Name is required`);
                isValid = false;
            } else if (nameField) {
                nameField.classList.remove('is-invalid');
            }
        });

        // Validate education
        const education = document.querySelectorAll('#educationList .education-item');
        console.log(`🎓 Validating ${education.length} education records...`);
        education.forEach((edu, index) => {
            const instIdField = edu.querySelector('input[name*="[instId]"]');
            const cseIdField = edu.querySelector('input[name*="[cseId]"]');
            // Require either instId or cseId (at least one identifier)
            if ((!instIdField || !instIdField.value.trim()) && (!cseIdField || !cseIdField.value.trim())) {
                if (instIdField) instIdField.classList.add('is-invalid');
                if (cseIdField) cseIdField.classList.add('is-invalid');
                errors.push(`Education #${index + 1}: Institution ID or Course ID is required`);
                isValid = false;
            } else {
                if (instIdField) instIdField.classList.remove('is-invalid');
                if (cseIdField) cseIdField.classList.remove('is-invalid');
            }
        });

        // Validate skills
        const skills = document.querySelectorAll('#skillsList .skill-item');
        console.log(`🎯 Validating ${skills.length} skills/courses...`);
        skills.forEach((skill, index) => {
            const nameField = skill.querySelector('input[name*="[course_name]"]');
            if (nameField && !nameField.value.trim()) {
                nameField.classList.add('is-invalid');
                errors.push(`Skill/Course #${index + 1}: Name is required`);
                isValid = false;
            } else if (nameField) {
                nameField.classList.remove('is-invalid');
            }
        });

        if (!isValid) {
            console.error('❌ Dynamic field validation errors:', errors);
            alert('Please fix the following errors in the dynamic fields:\n\n' + errors.join('\n'));
        } else {
            console.log('✅ All dynamic fields are valid');
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

    document.getElementById('addPostingBtn')?.addEventListener('click', function() {
        addPostingRow();
    });

    document.getElementById('addAwardBtn')?.addEventListener('click', function() {
        addAwardRow();
    });

    document.getElementById('addDisciplinaryBtn')?.addEventListener('click', function() {
        addDisciplinaryRow();
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

    // Handle optional contact fields - clear if invalid
    // NRC auto-formatting
    function autoFormatNRC(nrc) {
        nrc = nrc.replace(/[^\d]/g, '');
        if (nrc.length >= 6) {
            nrc = nrc.slice(0, 6) + '/' + nrc.slice(6);
        }
        if (nrc.length >= 9) {
            nrc = nrc.slice(0, 9) + '/' + nrc.slice(9, 10);
        }
        return nrc.slice(0, 12);
    }

    ['NRC', 'nokNrc'].forEach(function(fieldId) {
        const field = document.getElementById(fieldId);
        if (field) {
            field.addEventListener('input', function() {
                let formatted = autoFormatNRC(this.value);
                if (this.value !== formatted) {
                    this.value = formatted;
                }
            });
        }
    });

    // Phone auto-formatting
    function autoFormatPhone(phone) {
        phone = phone.replace(/[^\d+]/g, '');
        if (phone.startsWith('0')) {
            phone = '+260' + phone.slice(1);
        } else if (!phone.startsWith('+260') && phone.length === 9) {
            phone = '+260' + phone;
        }
        return phone;
    }

    ['tel', 'nokTel'].forEach(function(fieldId) {
        const field = document.getElementById(fieldId);
        if (field) {
            field.addEventListener('blur', function() {
                let formatted = autoFormatPhone(this.value);
                if (this.value !== formatted) {
                    this.value = formatted;
                }
            });
        }
    });

    // Handle optional contact fields - clear if invalid
    const contactFields = ['email', 'tel', 'nokTel'];
    contactFields.forEach(fieldId => {
        const field = document.getElementById(fieldId);
        if (field) {
            field.addEventListener('blur', function() {
                // If field is filled but invalid, show warning
                if (this.value.trim() && !this.checkValidity()) {
                    const fieldType = this.type === 'email' ? 'email address' : 'phone number';
                    console.warn(`⚠️ Invalid ${fieldType} format detected:`, this.value);
                    const confirmClear = confirm(`The ${fieldType} appears to be incomplete or invalid.\n\nCurrent value: ${this.value}\n\nWould you like to clear it? (Click Cancel to edit it)`);
                    if (confirmClear) {
                        this.value = '';
                        this.classList.remove('is-invalid');
                        console.log(`✅ ${fieldType} field cleared`);
                    }
                }
            });
        }
    });

    // Form validation
    const editForm = document.getElementById('editStaffForm');
    if (editForm) {
        editForm.addEventListener('submit', function(e) {
            console.log('🔍 Form submission initiated...');
            
            // Remove HTML5 validation constraints for optional fields to prevent blocking
            // Let PHP handle validation and data cleaning
            const optionalContactFields = ['email', 'tel', 'nokTel'];
            optionalContactFields.forEach(fieldId => {
                const field = document.getElementById(fieldId);
                if (field && field.value.trim() && !field.checkValidity()) {
                    const fieldType = field.type === 'email' ? 'email' : 'phone';
                    console.warn(`⚠️ Invalid ${fieldType} format detected (${fieldId}), removing validation constraint to allow submission`);
                    // Remove the type constraint temporarily to bypass HTML5 validation
                    field.setAttribute('data-original-type', field.type);
                    field.type = 'text';
                }
            });
            
            // Check if we have a valid staff member selected
            const staffId = document.querySelector('input[name="svcNo"]');
            if (!staffId || !staffId.value) {
                e.preventDefault();
                console.error('❌ No staff member selected');
                alert('Error: No staff member selected. Please select a staff member first.');
                return false;
            }
            console.log('✅ Staff ID found:', staffId.value);
            
            // Validate basic form fields
            const formValidationResult = validateForm();
            console.log('📝 Basic form validation:', formValidationResult ? '✅ Passed' : '❌ Failed');
            
            // Validate dynamic fields
            const dynamicValidationResult = validateDynamicFields();
            console.log('📋 Dynamic fields validation:', dynamicValidationResult ? '✅ Passed' : '❌ Failed');
            
            if (!formValidationResult || !dynamicValidationResult) {
                e.preventDefault();
                console.error('❌ Validation failed, form submission prevented');
                alert('Please fix the validation errors before submitting. Check the console for details.');
                window.scrollTo(0, 0);
                return false;
            }
            
            // Show confirmation with counts
            const opCount = document.querySelectorAll('#operationsList .operation-item').length;
            const depCount = document.querySelectorAll('#deploymentsList .deployment-item').length;
            const eduCount = document.querySelectorAll('#educationList .education-item').length;
            const skillCount = document.querySelectorAll('#skillsList .skill-item').length;
            
            console.log('📊 Item counts:', { operations: opCount, deployments: depCount, education: eduCount, skills: skillCount });
            
            const summary = `You are about to update this staff member with:
• ${opCount} operation(s)
• ${depCount} deployment(s)  
• ${eduCount} education record(s)
• ${skillCount} skill/course(s)

Are you sure you want to proceed?`;
            
            if (!confirm(summary)) {
                e.preventDefault();
                console.log('⚠️ User canceled submission');
                return false;
            }
            
            console.log('✅ All validations passed, submitting form...');
            // Form will submit naturally
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
        console.log('🔍 Validating form...');
        let isValid = true;
        let validationReport = [];
        let emptyFields = [];
        
        // Basic validation for required fields
        const requiredFields = document.querySelectorAll('[required]');
        console.log(`📝 Found ${requiredFields.length} required fields to validate`);
        
        requiredFields.forEach(field => {
            if (!field.value.trim()) {
                isValid = false;
                field.classList.add('is-invalid');
                const fieldName = field.name || field.id || 'Unknown field';
                emptyFields.push(fieldName);
                validationReport.push(`Required field "${fieldName}" is empty`);
                const feedbackEl = document.getElementById(`${field.id}-error`);
                if (feedbackEl) feedbackEl.textContent = 'This field is required';
            } else {
                field.classList.remove('is-invalid');
            }
        });
        
        if (emptyFields.length > 0) {
            console.error('❌ Empty required fields:', emptyFields);
        } else {
            console.log('✅ All required fields are filled');
        }
        
        
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

    // ==================== RETIREMENT DATE CALCULATOR ====================
    /**
     * Calculate and display retirement dates dynamically when DOB or attestDate changes
     */
    function updateRetirementInfo() {
        const dobField = document.getElementById('DOB');
        const attestDateField = document.getElementById('attestDate');
        
        if (!dobField || !attestDateField) return;
        
        const dob = dobField.value;
        const attestDate = attestDateField.value;
        
        // Calculate runout date (age 65)
        let runoutHtml = '<span class="text-muted">N/A (Date of birth not recorded)</span>';
        if (dob) {
            try {
                const dobDate = new Date(dob);
                const runoutDate = new Date(dobDate);
                runoutDate.setFullYear(dobDate.getFullYear() + 65);
                
                const now = new Date();
                const isPast = runoutDate < now;
                
                if (!isPast) {
                    const diff = runoutDate - now;
                    const years = Math.floor(diff / (1000 * 60 * 60 * 24 * 365.25));
                    const months = Math.floor((diff % (1000 * 60 * 60 * 24 * 365.25)) / (1000 * 60 * 60 * 24 * 30.44));
                    
                    const urgencyClass = years < 1 ? 'text-danger' : 
                                       years <= 5 ? 'text-warning' : 
                                       years <= 10 ? 'text-info' : 'text-success';
                    
                    runoutHtml = `
                        <div>${runoutDate.toLocaleDateString('en-GB', {day: 'numeric', month: 'short', year: 'numeric'})}</div>
                        <small class="${urgencyClass}">
                            <i class="fa fa-clock"></i> ${years} years, ${months} months remaining
                        </small>
                    `;
                } else {
                    runoutHtml = `
                        <div>${runoutDate.toLocaleDateString('en-GB', {day: 'numeric', month: 'short', year: 'numeric'})}</div>
                        <small class="text-danger">
                            <i class="fa fa-clock"></i> Past retirement age
                        </small>
                    `;
                }
            } catch (e) {
                console.error('Error calculating runout date:', e);
            }
        }
        
        // Calculate early retirement date (20 years service)
        let earlyRetHtml = '<span class="text-muted">N/A (Enlistment date not recorded)</span>';
        if (attestDate) {
            try {
                const enlistDate = new Date(attestDate);
                const earlyRetDate = new Date(enlistDate);
                earlyRetDate.setFullYear(enlistDate.getFullYear() + 20);
                
                const now = new Date();
                const isPast = earlyRetDate < now;
                
                if (!isPast) {
                    const diff = earlyRetDate - now;
                    const years = Math.floor(diff / (1000 * 60 * 60 * 24 * 365.25));
                    const months = Math.floor((diff % (1000 * 60 * 60 * 24 * 365.25)) / (1000 * 60 * 60 * 24 * 30.44));
                    
                    const urgencyClass = years < 1 ? 'text-danger' : 
                                       years <= 5 ? 'text-warning' : 
                                       years <= 10 ? 'text-info' : 'text-success';
                    
                    earlyRetHtml = `
                        <div>${earlyRetDate.toLocaleDateString('en-GB', {day: 'numeric', month: 'short', year: 'numeric'})}</div>
                        <small class="${urgencyClass}">
                            <i class="fa fa-clock"></i> ${years} years, ${months} months remaining
                        </small>
                    `;
                } else {
                    earlyRetHtml = `
                        <div>${earlyRetDate.toLocaleDateString('en-GB', {day: 'numeric', month: 'short', year: 'numeric'})}</div>
                        <small class="text-danger">
                            <i class="fa fa-clock"></i> Eligible now
                        </small>
                    `;
                }
            } catch (e) {
                console.error('Error calculating early retirement date:', e);
            }
        }
        
        // Update the display (find the retirement info divs and update them)
        const runoutDisplay = document.querySelector('[data-retirement="runout"]');
        const earlyRetDisplay = document.querySelector('[data-retirement="earlyRetirement"]');
        
        if (runoutDisplay) runoutDisplay.innerHTML = runoutHtml;
        if (earlyRetDisplay) earlyRetDisplay.innerHTML = earlyRetHtml;
    }
    
    // Add event listeners to update retirement info when dates change
    const dobInput = document.getElementById('DOB');
    const attestDateInput = document.getElementById('attestDate');
    
    if (dobInput) {
        dobInput.addEventListener('change', updateRetirementInfo);
    }
    
    if (attestDateInput) {
        attestDateInput.addEventListener('change', updateRetirementInfo);
    }
    
    // Initial calculation on page load
    updateRetirementInfo();
});
</script>
<?php include dirname(__DIR__) . '/shared/footer.php'; ?>
