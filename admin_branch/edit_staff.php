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
$sidebarLinks = []; // set by shared nav include below
require_once __DIR__ . '/includes/sidebar_nav.php';


// --- Constants ---
define('MAX_NAME_LENGTH', 100);
define('MAX_NRC_LENGTH', 50);
define('MIN_NAME_LENGTH', 2);
define('MIN_AGE_YEARS', 18);
define('MAX_AGE_YEARS', 65);
define('VALID_GENDERS', ['Male', 'Female']);
// staff.svcStatus is a real DB enum('Active','Retired','Deceased','AWOL','Discharged','On Contract').
// The enum stores 'AWOL' in UPPERCASE and also includes 'On Contract', which the previous
// version of this file omitted entirely (making such staff impossible to edit/save without
// forcing them onto a different status). No display->DB casing conversion is needed since the
// enum's own labels already match what we want to show in the UI.
define('VALID_STATUSES', ['Active', 'Retired', 'Deceased', 'AWOL', 'Discharged', 'On Contract']);
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
    $fields = ['fName', 'lName', 'rankId', 'unitId', 'corps', 'NRC', 'DOB', 'gender', 'svcStatus'];
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
    $sql = "SELECT s.svcNo, s.fName, s.lName, r.rankId as rank_name, COALESCE(u.unitId, '') as unit_name,
             s.svcStatus, s.DOB, s.attestDate, " . getRankCategoryCaseSQL('r') . " as category
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

    // Exclude retired/discharged staff unless explicitly requested.
    // Real "not active" values are Retired/Deceased/AWOL/Discharged (DB stores AWOL uppercase).
    if (!isset($_GET['include_inactive']) || $_GET['include_inactive'] !== '1') {
        $sql .= " AND s.svcStatus IN ('Active', 'AWOL')";
    }

    // Validate date fields for consistency (log if missing)
    $dateFields = ['subWef', 'tempWef', 'attestDate'];
    foreach ($dateFields as $df) {
        $sql .= " AND (s.$df IS NULL OR s.$df = '' OR s.$df >= '1900-01-01')";
    }

    // Order by seniority logic
    $sql .= " ORDER BY r.rankIndex ASC,
        LEAST(
            COALESCE(s.subWef, '9999-12-31'),
            COALESCE(s.tempWef, '9999-12-31'),
            COALESCE(s.attestDate, '9999-12-31')
        ) ASC,
        s.svcNo ASC,
        s.DOB ASC
    ";

    // Log missing/inconsistent data for audit
    $auditStmt = $pdo->query("SELECT svcNo, subWef, tempWef, attestDate FROM staff WHERE (subWef IS NULL OR subWef = '' OR subWef < '1900-01-01') OR (tempWef IS NULL OR tempWef = '' OR tempWef < '1900-01-01') OR (attestDate IS NULL OR attestDate = '' OR attestDate < '1900-01-01')");
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
            'rank' => 'r.rankIndex',
            'surname' => 's.lName',
            'fName' => 's.fName',
            'unit' => 'u.unitId',
            'category' => 'r.rankIndex',
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
               r.rankId as rankName, r.rankId as rankAbbr, r.rankIndex as rankLevel,
               COALESCE(u.unitId, '') as unitName, COALESCE(u.unitId, '') as unitCode,
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
            $searchCondition = " AND (s.svcNo LIKE ? OR s.fName LIKE ? OR s.lName LIKE ? OR r.rankId LIKE ? OR r.rankId LIKE ? OR CONCAT_WS(' ', COALESCE(u.unitId, ''), COALESCE(u.unitLoc, '')) LIKE ?)";
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
            // VALID_STATUSES labels already match the DB enum's own casing - no mapping needed
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
                r.rankIndex ASC,
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
                'status' => $s->svcStatus,
                // Roster group (Officers/NCOs/Civilian Employees) for
                // client-side section headers — the default sort order
                // above (rankIndex ASC) is already contiguous by group,
                // since shared/rank_levels.php partitions levels that
                // way, so grouping consecutive rows is safe.
                'rosterGroup' => getRosterGroupFromLevel($s->rankLevel ?? null),
            ];
        }
        // Return data with pagination info and debug trace
        echo json_encode([
            'data' => $result,
            // Roster grouping (Officers/NCOs/CEs) only makes sense to
            // display when rows are actually in the default,
            // group-contiguous seniority order — not when the user has
            // sorted by name/unit/etc, which interleaves groups.
            'groupingSafe' => empty($sort_col),
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
}

// --- CSRF helper ---
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
function csrf_token() { return $_SESSION['csrf_token']; }

$pdo = getDbConnection();
// Lookup ranks using the `rank` table and its rankId identifier, ordered by
// seniority (rankIndex) so the current selection is easy to find in the list.
$ranks = $pdo->query("SELECT rankId as rankID, rankId as rankName FROM `rank` ORDER BY rankIndex ASC")->fetchAll(PDO::FETCH_OBJ);
$rankMap = [];
foreach ($ranks as $r) $rankMap[$r->rankID] = $r->rankName;
$units = $pdo->query("SELECT unitId as unitID, unitId as unitName FROM unit ORDER BY unitId ASC")->fetchAll(PDO::FETCH_OBJ);
$unitMap = [];
foreach ($units as $u) $unitMap[$u->unitID] = $u->unitName;
// Corps is a free-text column on staff (no separate corps lookup table) - the dropdown
// is populated from distinct values already in use.
$corps = $pdo->query("SELECT DISTINCT corps as id, corps as name FROM staff WHERE corps IS NOT NULL AND corps != '' ORDER BY corps ASC")->fetchAll(PDO::FETCH_OBJ);

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
        error_log("Found staff member with service number: " . $staff->svcNo);
    } else {
        error_log("Staff member not found with any variation of service number: " . $svcNo);
    }
}

// --- Handle edit post ---
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['edit_staff'])) {
    $svcNo = $_POST['svcNo'];
    $newSvcNo = trim($_POST['newSvcNo'] ?? $svcNo);
    $csrf = $_POST['csrf_token'] ?? '';

    if (!isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $csrf)) {
        $errors[] = "Invalid CSRF token. Please reload the page and try again.";
    } else {
        $stmt = $pdo->prepare("SELECT * FROM staff WHERE svcNo = ?");
        $stmt->execute([$svcNo]);
        $originalStaff = $stmt->fetch(PDO::FETCH_OBJ);
        if (!$originalStaff) {
            $errors[] = "Staff member not found with service number: " . $svcNo . ". Please check the service number and try again.";
            error_log("Staff member not found with service number: " . $svcNo);
        } elseif (function_exists('canAlterRecord') && !canAlterRecord($originalStaff->branch_id ?? null)) {
            // Branch-scoping guard: a Chief Clerk/Staff Officer may only alter
            // records belonging to their own assigned branch (Admin Branch is
            // the one exception, since it's org-wide by nature). DG/AG never
            // reach here since PERM_EDIT_STAFF is read-only for those roles.
            $errors[] = "You are not permitted to alter records for this branch.";
            error_log("Branch RBAC denied: role '" . ($_SESSION['role'] ?? 'unknown') . "' (branch " . (getUserBranch() ?? 'none') . ") attempted to alter svcNo $svcNo (branch " . ($originalStaff->branch_id ?? 'none') . ")");
        } else {
            // Get and sanitize form data
            $fname = trim($_POST['fname'] ?? '');
            $mname = trim($_POST['mname'] ?? '');
            $prefix = trim($_POST['prefix'] ?? '');
            $initials = trim($_POST['initials'] ?? '');
            $lname = trim($_POST['lname'] ?? '');
            $rankID = $_POST['rankID'] ?? '';
            $unitID = $_POST['unitID'] ?? '';
            $corpsID = $_POST['corps'] ?? '';
            $category = $_POST['category'] ?? '';
            $NRC = trim($_POST['NRC'] ?? '');
            $DOB = $_POST['DOB'] ?? '';
            $gender = $_POST['gender'] ?? '';
            // VALID_STATUSES already matches the DB enum's casing exactly - no display->DB mapping needed
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
            $subRank = trim($_POST['subRank'] ?? '');
            $tempRank = trim($_POST['tempRank'] ?? '');
            $localRank = trim($_POST['localRank'] ?? '');
            // Legacy fields - now managed in dynamic sections (Steps 6-8)
            $postingHistory = ''; // Moved to staff_appointment table
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
                // NOTE: this $updateData array is only used to feed logStaffChange() (the
                // audit trail) below - the actual UPDATE statement is built later from
                // $fieldMap, filtered against the real staff table columns.
                $updateData = [
                    'svcNo' => $newSvcNo,
                    'fName' => $fname,
                    'mName' => $mname,
                    'prefix' => $prefix,
                    'initials' => $initials,
                    'lName' => $lname,
                    'rankId' => $rankID,
                    'unitId' => $unitID,
                    'corps' => $corpsID,
                    'NRC' => $NRC,
                    'DOB' => $DOB,
                    'gender' => $gender,
                    'svcStatus' => $svcStatus,
                    'telNo' => $tel,
                    'officialEmail' => $email,
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
                    'hDress' => $hdress,
                    'attestDate' => $attestDate,
                    'postingHistory' => $postingHistory,
                    'awards' => $awards,
                    'disciplinaryRecord' => $disciplinaryRecord
                ];
                // Rank seniority: update subWef/tempWef based on the rank's real rankType
                // column (Temporal vs Substantive/Local). These feed into $fieldMap below
                // so they actually persist - subWef/tempWef also drive seniority ordering
                // elsewhere (dashboard rank charts, reports_seniority.php).
                $subWefValue = null;
                $tempWefValue = null;
                $rankStmt = $pdo->prepare("SELECT rankType FROM `rank` WHERE rankId = ? LIMIT 1");
                $rankStmt->execute([$rankID]);
                $rankDetails = $rankStmt->fetch(PDO::FETCH_ASSOC);
                if ($rankDetails && !empty($lastPromotion)) {
                    if (($rankDetails['rankType'] ?? '') === 'Temporal') {
                        $tempWefValue = $lastPromotion;
                    } else {
                        // Substantive, Local, or unset rankType - treat as substantive seniority
                        $subWefValue = $lastPromotion;
                    }
                }
                $updateData['subWef'] = $subWefValue;
                $updateData['tempWef'] = $tempWefValue;

                // Track whether any child-table save failed, so we can tell the user
                // instead of silently reporting success while data is actually lost.
                $childTableErrors = [];

                try {
                    // IMPORTANT: table/column existence is verified here, not created here.
                    // Schema migrations (CREATE TABLE / ALTER TABLE) do NOT belong in a
                    // per-request code path - see the deployment note at the bottom of this
                    // file. Every INSERT below targets the REAL column names as defined in
                    // armis1.sql, not a guessed/duplicate schema.

                    $requiredTables = [
                        'staff_edit_log', 'staff_operation', 'staff_deployments',
                        'staff_course', 'staff_skills', 'staff_appointment',
                        'staff_awards', 'staff_disciplinary'
                    ];
                    $missingTables = [];
                    foreach ($requiredTables as $t) {
                        if ($pdo->query("SHOW TABLES LIKE " . $pdo->quote($t))->rowCount() === 0) {
                            $missingTables[] = $t;
                        }
                    }
                    if (!empty($missingTables)) {
                        // Fail loudly and specifically rather than silently creating a
                        // differently-shaped table at request time and corrupting saves.
                        throw new Exception("Missing required table(s): " . implode(', ', $missingTables) . ". Run database migrations before using this form.");
                    }

                    // NOW start the transaction
                    $pdo->beginTransaction();

                    // Log staff changes (pass PDO connection to avoid transaction issues)
                    logStaffChange($svcNo, $originalStaff, $updateData, $user->data()->id ?? 0, $_SERVER['REMOTE_ADDR'] ?? '', $pdo);

                    // Get current staff table structure to build dynamic update query
                    $columns = $pdo->query('DESCRIBE staff')->fetchAll(PDO::FETCH_ASSOC);
                    $existingColumns = array_column($columns, 'Field');

                    // Map form fields to database columns (real `staff` schema).
                    // Fields with NO backing column (shoe size beyond sSize's constraints,
                    // etc.) are left out of this map on purpose.
                    $fieldMap = [
                        'fName' => $fname,
                        'mName' => $mname,
                        'prefix' => $prefix,
                        'initials' => $initials,
                        'lName' => $lname,
                        'rankId' => $rankID,
                        'unitId' => $unitID,
                        'corps' => $corpsID,
                        'category' => $category,
                        'svcNo' => $newSvcNo,
                        'NRC' => $NRC,
                        'DOB' => $DOB,
                        'gender' => $gender,
                        'svcStatus' => $svcStatus,
                        'telNo' => $tel,
                        'officialEmail' => $email,
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
                        'sSize' => $ssize,
                        'hDress' => $hdress,
                        'attestDate' => $attestDate,
                        'subRank' => $subRank,
                        'tempRank' => $tempRank,
                        'localRank' => $localRank,
                        'subWef' => $subWefValue,
                        'tempWef' => $tempWefValue,
                        'localWef' => $_POST['localWef'] !== '' ? ($_POST['localWef'] ?? null) : ($originalStaff->localWef ?? null),
                        'passPort' => $_POST['passPort'] ?? null,
                        'passExp' => $_POST['passExp'] !== '' ? ($_POST['passExp'] ?? null) : ($originalStaff->passExp ?? null),
                        'unitAtt' => $_POST['unitAtt'] ?? null,
                        'dateSeparated' => $_POST['dateSeparated'] ?? null,
                        'contFrom' => $_POST['contFrom'] !== '' ? ($_POST['contFrom'] ?? null) : ($originalStaff->contFrom ?? null),
                        'contDuration' => $_POST['contDuration'] !== '' ? ($_POST['contDuration'] ?? null) : ($originalStaff->contDuration ?? null),
                        'contEnd' => $_POST['contEnd'] !== '' ? ($_POST['contEnd'] ?? null) : ($originalStaff->contEnd ?? null),
                        'province' => $_POST['province'] ?? null,
                        'district' => $_POST['district'] ?? null,
                        'intake' => $_POST['intake'] ?? null,
                        'village' => $_POST['village'] ?? null,
                        'titles' => $_POST['titles'] ?? null,
                        'digitalId' => $_POST['digitalId'] ?? null,
                        'tel2' => $_POST['tel2'] ?? null,
                        'altNok' => $_POST['altNok'] ?? null,
                        'altNokTel' => $_POST['altNokTel'] ?? null,
                        'altNokRelat' => $_POST['altNokRelat'] ?? null,
                        'marital' => $_POST['maritalStatus'] ?? null,
                        'religion' => $_POST['religion'] ?? null,
                        'bloodGp' => $_POST['bloodGroup'] ?? null,
                        'height' => $_POST['height'] ?? null
                    ];

                    // Role/Branch: the edit_staff_step2_service.php partial disables these
                    // inputs client-side for non-admins, but that's a UI convenience only -
                    // re-check isAdmin() here too, server-side, since a forged POST could
                    // otherwise reassign someone's role/branch through a disabled field.
                    if (function_exists('isAdmin') && isAdmin()) {
                        $fieldMap['role'] = $_POST['role'] ?? $originalStaff->role;
                        $fieldMap['branch_id'] = (isset($_POST['branch_id']) && $_POST['branch_id'] !== '')
                            ? (int)$_POST['branch_id']
                            : null;
                    }

                    // Build dynamic update query with only existing columns
                    $updateFields = [];
                    $updateParams = [];

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
                        }
                    }
                    // Add svcNo for WHERE clause
                    $updateParams[] = $svcNo;
                    $updateSql = "UPDATE staff SET " . implode(', ', $updateFields) . " WHERE svcNo = ?";

                    $stmt = $pdo->prepare($updateSql);
                    if (!$stmt->execute($updateParams)) {
                        throw new Exception("Failed to update main staff record: " . implode(", ", $stmt->errorInfo()));
                    }

                    $rowsAffected = $stmt->rowCount();

                    if ($rowsAffected === 0) {
                        $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM staff WHERE svcNo = ?");
                        $checkStmt->execute([$svcNo]);
                        $recordExists = $checkStmt->fetchColumn();

                        if (!$recordExists) {
                            throw new Exception("No staff record found with service number: " . $svcNo . ". Please verify the service number exists.");
                        }
                        // Record exists but wasn't updated - possibly no actual changes. Continue processing.
                    }

                    // Use svcNo directly for child tables (staff table uses svcNo as primary key, not id)
                    $staffSvcNo = $newSvcNo;

                    // --- Operations (staff_operation: id, svcNo, opId, opStart, opEnd, remarks, authID, createdAt) ---
                    try {
                        if (isset($_POST['operations']) && is_array($_POST['operations'])) {
                            $deleteStmt = $pdo->prepare("DELETE FROM staff_operation WHERE svcNo = ?");
                            $deleteStmt->execute([$staffSvcNo]);

                            $opStmt = $pdo->prepare("INSERT INTO staff_operation (svcNo, opId, opStart, opEnd, remarks, authID) VALUES (?, ?, ?, ?, ?, ?)");
                            $insertCount = 0;
                            foreach ($_POST['operations'] as $index => $op) {
                                $opId = !empty($op['opId']) ? $op['opId'] : (!empty($op['opID']) ? $op['opID'] : (!empty($op['operation_id']) ? $op['operation_id'] : ''));
                                if (!empty($opId)) {
                                    $opStmt->execute([
                                        $staffSvcNo,
                                        $opId,
                                        !empty($op['startDate']) ? $op['startDate'] : null,
                                        !empty($op['endDate']) ? $op['endDate'] : null,
                                        $op['remarks'] ?? '',
                                        $op['authorityId'] ?? $op['authority_id'] ?? $op['authID'] ?? ''
                                    ]);
                                    $insertCount++;
                                }
                            }
                        }
                    } catch (Exception $e) {
                        $childTableErrors[] = 'Operations';
                        error_log("Error processing operations: " . $e->getMessage());
                    }

                    // --- Deployments (matches real schema as-is) ---
                    try {
                        if (isset($_POST['deployments']) && is_array($_POST['deployments'])) {
                            $deleteStmt = $pdo->prepare("DELETE FROM staff_deployments WHERE svcNo = ?");
                            $deleteStmt->execute([$staffSvcNo]);

                            $depStmt = $pdo->prepare("INSERT INTO staff_deployments (svcNo, deployment_name, mission_type, location, country, startDate, endDate, durationMonths, deployment_status, rank_during_deployment, role_during_deployment, commanding_officer, deployment_allowance, notes, createdAt, updatedAt) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())");
                            foreach ($_POST['deployments'] as $index => $dep) {
                                if (!empty($dep['deployment_name'])) {
                                    $depStmt->execute([
                                        $staffSvcNo,
                                        $dep['deployment_name'] ?? '',
                                        $dep['mission_type'] ?? '',
                                        $dep['location'] ?? '',
                                        $dep['country'] ?? '',
                                        !empty($dep['startDate']) ? $dep['startDate'] : null,
                                        !empty($dep['endDate']) ? $dep['endDate'] : null,
                                        !empty($dep['durationMonths']) ? intval($dep['durationMonths']) : null,
                                        $dep['deployment_status'] ?? $dep['status'] ?? '',
                                        $dep['rank_during_deployment'] ?? '',
                                        $dep['role_during_deployment'] ?? $dep['role'] ?? '',
                                        $dep['commanding_officer'] ?? '',
                                        !empty($dep['deployment_allowance']) ? floatval($dep['deployment_allowance']) : null,
                                        $dep['notes'] ?? ''
                                    ]);
                                }
                            }
                        }
                    } catch (Exception $e) {
                        $childTableErrors[] = 'Deployments';
                        error_log("Error processing deployments: " . $e->getMessage());
                    }

                    // --- Education/Courses (staff_course: instId, cseId, qualification, cseStart, cseEnd, grade, result, isHighest, authID) ---
                    try {
                        if (isset($_POST['education']) && is_array($_POST['education'])) {
                            $deleteStmt = $pdo->prepare("DELETE FROM staff_course WHERE svcNo = ?");
                            $deleteStmt->execute([$staffSvcNo]);

                            $eduStmt = $pdo->prepare("INSERT INTO staff_course (svcNo, instId, cseId, qualification, cseStart, cseEnd, grade, result, isHighest, authID) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                            foreach ($_POST['education'] as $index => $edu) {
                                $instId = !empty($edu['instId']) ? $edu['instId'] : (!empty($edu['institution']) ? $edu['institution'] : '');
                                if (!empty($instId) || !empty($edu['cseId'])) {
                                    $yearStarted = !empty($edu['yearStarted']) ? $edu['yearStarted'] : ($edu['year_started'] ?? null);
                                    $yearCompleted = !empty($edu['yearCompleted']) ? $edu['yearCompleted'] : ($edu['year_completed'] ?? null);
                                    $eduStmt->execute([
                                        $staffSvcNo,
                                        $instId,
                                        !empty($edu['cseId']) ? $edu['cseId'] : '',
                                        $edu['qualification'] ?? '',
                                        // cseStart/cseEnd are DATE columns - a bare year still works as Y-01-01
                                        $yearStarted ? (strlen((string)$yearStarted) === 4 ? $yearStarted . '-01-01' : $yearStarted) : null,
                                        $yearCompleted ? (strlen((string)$yearCompleted) === 4 ? $yearCompleted . '-12-31' : $yearCompleted) : null,
                                        $edu['grade'] ?? $edu['grade_obtained'] ?? '',
                                        $edu['result'] ?? '',
                                        !empty($edu['isHighest']) ? 1 : (!empty($edu['is_highest_qualification']) ? 1 : 0),
                                        $edu['authorityId'] ?? $edu['authority_id'] ?? $edu['authID'] ?? ''
                                    ]);
                                }
                            }
                        }
                    } catch (Exception $e) {
                        $childTableErrors[] = 'Education/Courses';
                        error_log("Error processing education: " . $e->getMessage());
                    }

                    // --- Skills (matches real schema as-is) ---
                    try {
                        if (isset($_POST['skills']) && is_array($_POST['skills'])) {
                            $deleteStmt = $pdo->prepare("DELETE FROM staff_skills WHERE svcNo = ?");
                            $deleteStmt->execute([$staffSvcNo]);

                            $skillStmt = $pdo->prepare("INSERT INTO staff_skills (svcNo, course_name, course_type, institution, startDate, endDate, duration_days, certificateNumber, grade_obtained, location, cost, sponsored_by, certification_status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                            foreach ($_POST['skills'] as $index => $skill) {
                                if (!empty($skill['course_name'])) {
                                    $skillStmt->execute([
                                        $staffSvcNo,
                                        $skill['course_name'] ?? $skill['skill_name'] ?? '',
                                        $skill['course_type'] ?? '',
                                        $skill['institution'] ?? '',
                                        !empty($skill['startDate']) ? $skill['startDate'] : null,
                                        !empty($skill['endDate']) ? $skill['endDate'] : null,
                                        !empty($skill['duration_days']) ? intval($skill['duration_days']) : null,
                                        $skill['certificateNumber'] ?? '',
                                        $skill['grade_obtained'] ?? '',
                                        $skill['location'] ?? '',
                                        !empty($skill['cost']) ? floatval($skill['cost']) : null,
                                        $skill['sponsored_by'] ?? '',
                                        $skill['certification_status'] ?? ''
                                    ]);
                                }
                            }
                        }
                    } catch (Exception $e) {
                        $childTableErrors[] = 'Skills';
                        error_log("Error processing skills: " . $e->getMessage());
                    }

                    // --- Posting History (staff_appointment: no createdBy/createdAt/updatedAt columns) ---
                    try {
                        if (isset($_POST['postings']) && is_array($_POST['postings'])) {
                            $deletePostings = $pdo->prepare("DELETE FROM staff_appointment WHERE svcNo = ?");
                            $deletePostings->execute([$staffSvcNo]);

                            $insertPosting = $pdo->prepare("
                                INSERT INTO staff_appointment
                                (svcNo, apptId, apptType, unitId, apptWef, powers, endDate, durationMonths, authorityId, remarks)
                                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                            ");
                            foreach ($_POST['postings'] as $index => $posting) {
                                $apptId = $posting['apptId'] ?? $posting['appointment_id'] ?? '';
                                if (empty($apptId)) {
                                    continue;
                                }
                                $startDate = $posting['startDate'] ?? $posting['apptWef'] ?? null;
                                $endDate = !empty($posting['endDate']) ? $posting['endDate'] : null;
                                $durationMonths = null;
                                if ($startDate && $endDate) {
                                    try {
                                        $durationMonths = (new DateTime($startDate))->diff(new DateTime($endDate))->m
                                            + ((new DateTime($startDate))->diff(new DateTime($endDate))->y * 12);
                                    } catch (Exception $ignored) {}
                                }
                                $insertPosting->execute([
                                    $staffSvcNo,
                                    $apptId,
                                    $posting['appointment_type'] ?? $posting['apptType'] ?? null,
                                    $posting['unitId'] ?? null,
                                    $startDate,
                                    $posting['powers'] ?? '',
                                    $endDate,
                                    $durationMonths,
                                    $posting['authorityId'] ?? $posting['authority_id'] ?? null,
                                    $posting['remarks'] ?? null
                                ]);
                            }
                        }
                    } catch (Exception $e) {
                        $childTableErrors[] = 'Posting History';
                        error_log("Error processing postings: " . $e->getMessage());
                    }

                    // --- Awards & Commendations (matches real schema as-is) ---
                    try {
                        if (isset($_POST['awards']) && is_array($_POST['awards'])) {
                            $deleteAwards = $pdo->prepare("DELETE FROM staff_awards WHERE svcNo = ?");
                            $deleteAwards->execute([$staffSvcNo]);

                            $insertAward = $pdo->prepare("
                                INSERT INTO staff_awards
                                (svcNo, award_type, award_name, award_date, awarded_by,
                                 citation, certificateNumber, gazette_reference, remarks)
                                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                            ");
                            foreach ($_POST['awards'] as $index => $award) {
                                if (empty($award['award_type']) || empty($award['award_name']) || empty($award['award_date'])) {
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
                            }
                        }
                    } catch (Exception $e) {
                        $childTableErrors[] = 'Awards';
                        error_log("Error processing awards: " . $e->getMessage());
                    }

                    // --- Disciplinary Records (matches real schema as-is) ---
                    try {
                        if (isset($_POST['disciplinary']) && is_array($_POST['disciplinary'])) {
                            $deleteDisciplinary = $pdo->prepare("DELETE FROM staff_disciplinary WHERE svcNo = ?");
                            $deleteDisciplinary->execute([$staffSvcNo]);

                            $insertDisciplinary = $pdo->prepare("
                                INSERT INTO staff_disciplinary
                                (svcNo, incident_type, incident_date, description, action_taken,
                                 disciplinary_authority, startDate, endDate, case_reference,
                                 outcome, appeal_details, clearance_date, confidential, remarks)
                                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                            ");
                            foreach ($_POST['disciplinary'] as $index => $disc) {
                                if (empty($disc['incident_type']) || empty($disc['incident_date']) || empty($disc['description'])) {
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
                            }
                        }
                    } catch (Exception $e) {
                        $childTableErrors[] = 'Disciplinary Records';
                        error_log("Error processing disciplinary records: " . $e->getMessage());
                    }

                    $pdo->commit();
                    $success = true;
                    if (!empty($childTableErrors)) {
                        // Tell the user the truth instead of a blanket "Success!" - the main
                        // record saved, but one or more sections did not.
                        $errors[] = 'Staff record was updated, but the following section(s) failed to save and need attention: ' . implode(', ', $childTableErrors) . '. Check server logs for details.';
                    }
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
                        $errors[] = "Database schema error: " . $e->getMessage() . ". Please contact a system administrator.";
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
function getStaffStatistics($pdo) {
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
    $stmt = $pdo->query("SELECT COALESCE(CONCAT_WS(' - ', u.unitId, NULLIF(u.unitLoc, '')), u.unitId) as unitName, COUNT(s.svcNo) as count FROM unit u LEFT JOIN staff s ON u.unitId = s.unitId GROUP BY u.unitId, u.unitLoc ORDER BY count DESC");
    $stats['units'] = $stmt->fetchAll(PDO::FETCH_OBJ);
    return $stats;
}

$staffStats = getStaffStatistics($pdo);

// --- Recent Activity Function ---
function getRecentActivity($pdo, $limit = 10) {
    try {
        // FIX: this query previously joined a `users` table that doesn't
        // exist anywhere in this app - staff accounts live in `staff`
        // itself. This silently failed on every page load (caught,
        // logged, empty result), so "recent activity" here never showed
        // anything.
        // FIX: staff has no `id` column at all - its primary key is
        // svcNo (varchar). edited_by is populated from
        // $_SESSION['user_id'], which login.php sets from `s.svcNo AS id`
        // (an alias, not a real numeric id column), stored into
        // edited_by's `int` column - MySQL coerces the numeric-looking
        // svcNo string to an integer on insert. Joined back through
        // svcNo (letting the same int/varchar coercion apply in reverse)
        // instead of a column that doesn't exist.
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
            LEFT JOIN staff u ON sel.edited_by = u.svcNo
            ORDER BY sel.edited_at DESC
            LIMIT ?
        ");
        $stmt->bindValue(1, $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_OBJ);
    } catch (Exception $e) {
        error_log("Error fetching recent activity: " . $e->getMessage());
        return [];
    }
}

$recentActivity = getRecentActivity($pdo, 5);

// Log page access with enhanced analytics
logActivity('edit_staff_access', 'Accessed Edit Staff page');

// Add unified design system CSS (admin_branch.css is already loaded by shared/header.php below)
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
    // Load operations
    $stmt = $pdo->prepare("SELECT * FROM staff_operation WHERE svcNo = ?");
    $stmt->execute([$staff->svcNo]);
    $staffOperations = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Load deployments
    $stmt = $pdo->prepare("SELECT * FROM staff_deployments WHERE svcNo = ?");
    $stmt->execute([$staff->svcNo]);
    $staffDeployments = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Load education/courses
    $stmt = $pdo->prepare("SELECT * FROM staff_course WHERE svcNo = ?");
    $stmt->execute([$staff->svcNo]);
    $staffEducation = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Load skills
    $stmt = $pdo->prepare("SELECT * FROM staff_skills WHERE svcNo = ?");
    $stmt->execute([$staff->svcNo]);
    $staffSkills = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Load posting history
    try {
        $stmt = $pdo->prepare("
            SELECT sa.*, COALESCE(CONCAT_WS(' - ', u.unitId, NULLIF(u.unitLoc, '')), u.unitId, '') as unit_name
            FROM staff_appointment sa
            LEFT JOIN unit u ON sa.unitId = u.unitId
            WHERE sa.svcNo = ?
            ORDER BY sa.apptWef DESC
        ");
        $stmt->execute([$staff->svcNo]);
        $staffPostings = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Error loading postings: " . $e->getMessage());
        $staffPostings = [];
    }

    // Load awards & commendations
    try {
        $stmt = $pdo->prepare("SELECT * FROM staff_awards WHERE svcNo = ? ORDER BY award_date DESC");
        $stmt->execute([$staff->svcNo]);
        $staffAwards = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Error loading awards: " . $e->getMessage());
        $staffAwards = [];
    }

    // Load disciplinary records
    try {
        $stmt = $pdo->prepare("SELECT * FROM staff_disciplinary WHERE svcNo = ? ORDER BY incident_date DESC");
        $stmt->execute([$staff->svcNo]);
        $staffDisciplinary = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Error loading disciplinary records: " . $e->getMessage());
        $staffDisciplinary = [];
    }
}

?>

<!-- Dynamically load initial data for dynamic sections -->
<script>
window.operationsOptions = <?php
    try {
        $ops = [];
        if ($pdo->query("SHOW TABLES LIKE 'operation'")->rowCount()) {
            $ops = $pdo->query("SELECT opId as id, opId as name, opType as code FROM operation ORDER BY opId ASC")->fetchAll(PDO::FETCH_ASSOC);
        }
    } catch (Exception $e) {
        error_log("Could not load operations for edit_staff: " . $e->getMessage());
        $ops = [];
    }
    echo json_encode($ops);
?>;

// Fetch distinct units for JavaScript dropdowns
window.unitsOptions = <?php
    try {
        $unitsForDropdown = $pdo->query("SELECT DISTINCT unitId AS id, unitId AS name, unitLoc AS location FROM unit ORDER BY unitId ASC")->fetchAll(PDO::FETCH_ASSOC);
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
        $staffRankId = $staff->rankId ?? null;
        if (!empty($staffRankId)) {
            $stmtRank = $pdo->prepare("SELECT rankIndex FROM `rank` WHERE rankId = ? LIMIT 1");
            $stmtRank->execute([$staffRankId]);
            $rankCat = $stmtRank->fetch(PDO::FETCH_OBJ);
            if ($rankCat && $rankCat->rankIndex !== null) {
                $rankCategory = getRankCategory((int)$rankCat->rankIndex);
            }
        }

        if (!empty($rankCategory) && $rankCategory !== 'Unknown') {
            $categoryWhere = getRankCategorySQL($rankCategory, 'r');
            $ranksData = $pdo->query("SELECT r.rankId as id, r.rankId as name, r.rankId as abbreviation, r.rankIndex FROM `rank` r WHERE {$categoryWhere} ORDER BY r.rankIndex DESC")->fetchAll(PDO::FETCH_ASSOC);
        } else {
            $ranksData = $pdo->query("SELECT rankId as id, rankId as name, rankId as abbreviation, rankIndex FROM `rank` ORDER BY rankIndex DESC")->fetchAll(PDO::FETCH_ASSOC);
        }
    } catch (Exception $e) {
        error_log("Could not load ranks for edit_staff: " . $e->getMessage());
        $ranksData = [];
    }
    echo json_encode($ranksData);
?>;

window.staffRankCategory = <?php echo json_encode($rankCategory ?? 'Unknown'); ?>;
window.initialEditStaffData = <?=json_encode([
    'operations' => $staffOperations,
    'deployments' => $staffDeployments,
    'education' => $staffEducation,
    'skills' => $staffSkills,
    'postings' => $staffPostings,
    'awards' => $staffAwards,
    'disciplinary' => $staffDisciplinary
], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT)?>;
</script>

<!-- Load custom CSS and assets -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.3/css/bootstrap.min.css">
<link rel="stylesheet" href="/Armis2/admin_branch/css/form-step-styles.css">
<!-- Core JS (jQuery + Bootstrap) are loaded centrally in shared/footer.php -->
<script src="/Armis2/admin_branch/js/multi-step-form.js"></script>
<div class="content-wrapper with-sidebar">
    <div class="container-fluid">
        <nav aria-label="breadcrumb" class="mb-3">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a href="/Armis2/admin_branch/index.php">Admin Branch</a></li>
                <?php if ($staff): ?>
                    <li class="breadcrumb-item"><a href="/Armis2/admin_branch/edit_staff.php">Staff Management</a></li>
                    <li class="breadcrumb-item active" aria-current="page">
                        Editing: <?=htmlspecialchars(trim(($staff->fName ?? '') . ' ' . ($staff->lName ?? '')))?>
                    </li>
                <?php else: ?>
                    <li class="breadcrumb-item active" aria-current="page">Staff Management</li>
                <?php endif; ?>
            </ol>
        </nav>
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
                    <strong><?= $success ? 'Please note:' : 'Please correct the following errors:' ?></strong>
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
                                    <label for="searchStaff" class="visually-hidden">Search by service number or name</label>
                                    <input type="text" name="search" id="searchStaff" class="form-control"
                                           placeholder="Search by Service No, Name..." autocomplete="off">
                                </div>
                                <div class="col-md-2">
                                    <label for="filterRank" class="visually-hidden">Filter by rank</label>
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
                                    <label for="filterUnit" class="visually-hidden">Filter by unit</label>
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
                                    <label for="filterStatus" class="visually-hidden">Filter by status</label>
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
                                    <label for="perPageSelect" class="visually-hidden">Records per page</label>
                                    <select class="form-select" id="perPageSelect" name="per_page" title="Records per page">
                                        <?php foreach ([10, 25, 50, 100, 200] as $pp): ?>
                                            <option value="<?=$pp?>" <?=$pp === 25 ? 'selected' : ''?>><?=$pp?> per page</option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-1">
                                    <button class="btn btn-outline-secondary w-100" type="button" onclick="clearFilters()" aria-label="Clear all search filters">
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
                            <tr><td colspan="5" class="text-center text-muted py-4"><i class="fa fa-search me-2"></i>Enter search criteria to find staff members</td></tr>
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
                let currentGroupingSafe = false;

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
                function titleCase(str) {
                    return str.replace(/\w\S*/g, function(txt){
                        return txt.charAt(0).toUpperCase() + txt.substr(1).toLowerCase();
                    });
                }
                    resultsTable.innerHTML = '';
                    let lastRenderedGroup = null;

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

                    data.forEach(function(staff) {
                        // Roster group section header (Officers / NCOs /
                        // Civilian Employees), only when rows are in
                        // default seniority order so the groups are
                        // actually contiguous.
                        if (currentGroupingSafe && staff.rosterGroup && staff.rosterGroup !== lastRenderedGroup) {
                            const groupTr = document.createElement('tr');
                            groupTr.className = 'table-secondary';
                            const td = document.createElement('td');
                            td.colSpan = 5;
                            td.innerHTML = '<strong>' + escapeHtml(staff.rosterGroup) + '</strong>';
                            groupTr.appendChild(td);
                            resultsTable.appendChild(groupTr);
                            lastRenderedGroup = staff.rosterGroup;
                        }

                        const tr = document.createElement('tr');
                        tr.ondblclick = function() {
                            fetch('?ajax=1&audit_view=1&svcNo=' + encodeURIComponent(staff.svcNo));
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

                    const start = (currentPage - 1) * itemsPerPage + 1;
                    const end = Math.min(currentPage * itemsPerPage, totalItems);
                    const countRow = document.createElement('tr');
                    countRow.className = 'table-info';
                    countRow.innerHTML = `
                        <td colspan="6" class="text-center">
                            <small><i class="fa fa-info-circle"></i> Showing ${start} to ${end} of ${totalItems} staff members (Page ${currentPage} of ${totalPages})</small>
                        </td>`;
                    resultsTable.appendChild(countRow);

                    renderPagination();
                }

                function fetchResults() {
                    const queryString = buildSearchQuery();

                    const url = new URL(window.location.origin + window.location.pathname + '?' + queryString);
                    url.searchParams.append('exclude_inactive', '1');
                    url.searchParams.append('ajax', '1');
                    url.searchParams.append('page', currentPage);
                    url.searchParams.append('per_page', itemsPerPage);

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

                            if (data && data.data && data.pagination) {
                                currentData = data.data;
                                totalItems = data.pagination.total_items;
                                totalPages = data.pagination.total_pages;
                                currentPage = data.pagination.current_page;
                                itemsPerPage = data.pagination.per_page;
                                currentGroupingSafe = !!data.groupingSafe;

                                renderTable(currentData);
                            } else if (Array.isArray(data)) {
                                currentData = data;
                                totalItems = data.length;
                                totalPages = 1;
                                currentGroupingSafe = false;

                                renderTable(currentData);
                            } else {
                                throw new Error('Invalid response format');
                            }

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
                    currentPage = 1;
                    fetchResults();
                }

                fetchResults();

                searchInput.addEventListener('input', function() {
                    clearTimeout(typingTimer);
                    currentPage = 1;
                    typingTimer = setTimeout(fetchResults, 300);
                });

                filterRank.addEventListener('change', function() {
                    currentPage = 1;
                    fetchResults();
                });
                filterUnit.addEventListener('change', function() {
                    currentPage = 1;
                    fetchResults();
                });
                filterStatus.addEventListener('change', function() {
                    currentPage = 1;
                    fetchResults();
                });

                perPageSelect.addEventListener('change', function() {
                    itemsPerPage = parseInt(this.value);
                    currentPage = 1;
                    fetchResults();
                });

                searchInput.addEventListener('keypress', function(e) {
                    if (e.key === 'Enter') {
                        clearTimeout(typingTimer);
                        fetchResults();
                    }
                });

                document.addEventListener('keydown', function(e) {
                    if (e.ctrlKey && e.key === 'f') {
                        e.preventDefault();
                        searchInput.focus();
                    }
                });

                document.getElementById('print-staff-list').addEventListener('click', function() {
                    window.print();
                });

                document.querySelectorAll('.toggle-col').forEach(function(checkbox) {
                    const colName = checkbox.dataset.col;

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
                        headerCells[columnIndex].style.display = isVisible ? '' : 'none';

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

                    .filter-active {
                        border-color: #4e73df;
                        box-shadow: 0 0 0 0.2rem rgba(78, 115, 223, 0.25);
                    }

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
                <div class="step-icon"><i class="fa fa-person-rifle"></i></div>
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
                                        <?= (!empty($staff) && isset($staff->corps) && $staff->corps == $corpsItem->id) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($corpsItem->name) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small class="form-text text-muted">
                            Current: <strong><?= htmlspecialchars(!empty($staff) ? ($staff->corps ?? 'Not set') : 'Not set') ?></strong>
                        </small>
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
                            // staff.marital is enum('Single','Married','Divorced','Widowed') -
                            // 'Separated' is not a valid value in the DB and would fail to save.
                            $maritalStatuses = ['Single', 'Married', 'Divorced', 'Widowed'];
                            foreach ($maritalStatuses as $status): ?>
                                <option value="<?= htmlspecialchars($status) ?>"
                                        <?= (!empty($staff) && ($staff->marital ?? '') === $status) ? 'selected' : '' ?>>
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
                            <?php
                            // VALID_STATUSES labels match the DB enum's own casing exactly - no mapping needed
                            foreach (VALID_STATUSES as $status):
                            ?>
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
                               value="<?= htmlspecialchars($staff->telNo ?? '') ?>"
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
                               value="<?= htmlspecialchars($staff->officialEmail ?? '') ?>"
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
                                        <?= (!empty($staff) && ($staff->bloodGp ?? '') === $bg) ? 'selected' : '' ?>>
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
                            <small class="form-text text-muted">
                                Format: XXXXXX/XX/X (e.g., 123456/12/1). This is saved to the
                                <code>nokNrc</code> column on the staff table.
                            </small>
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
                                    'General Medicine', 'Surgery', 'Pediatrics', 'Psychiatry', 'Cardiology',
                                    'Orthopedics', 'Emergency Medicine', 'Anesthesiology', 'Radiology',
                                    'Civil Engineering', 'Mechanical Engineering', 'Electrical Engineering',
                                    'Electronics Engineering', 'Computer Engineering', 'Aerospace Engineering',
                                    'Communications Engineering', 'Environmental Engineering',
                                    'Infantry Operations', 'Artillery Operations', 'Armor Operations',
                                    'Aviation Operations', 'Naval Operations', 'Special Forces',
                                    'Intelligence Analysis', 'Cyber Operations', 'Logistics Management',
                                    'Military Police', 'Combat Engineering', 'Signal Operations',
                                    'Information Technology', 'Cybersecurity', 'Database Management',
                                    'Network Administration', 'Software Development', 'Systems Analysis',
                                    'Quality Assurance', 'Project Management', 'Training & Development',
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
                                                    <?= (!empty($staff) && ($staff->bootSize ?? '') == $i) ? 'selected' : '' ?>>
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
                                                    <?= (!empty($staff) && ($staff->sSize ?? '') == $i) ? 'selected' : '' ?>>
                                                <?= $i ?>
                                            </option>
                                        <?php endfor; ?>
                                    </select>
                                    <small class="form-text text-muted">
                                        Saved to the <code>sSize</code> column on staff.
                                    </small>
                                </div>
                                <div class="col-md-3">
                                    <label for="hdress" class="form-label">Headdress Size <span class="text-muted">(Optional)</span></label>
                                    <select class="form-select" name="hdress" id="hdress"
                                            title="Head dress size for berets, caps, and ceremonial headwear">
                                        <option value="">Select Size</option>
                                        <?php
                                        for ($i = 52; $i <= 65; $i++): ?>
                                            <option value="<?= $i ?>"
                                                    <?= (!empty($staff) && ($staff->hDress ?? '') == $i) ? 'selected' : '' ?>>
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
                                        value="<?=htmlspecialchars($staff->subWef ?? $staff->tempWef ?? '')?>">
                                    <small class="form-text text-muted">Saved as substantive (subWef) or temporal (tempWef) seniority date, based on the selected rank's type</small>
                                </div>
                            </div>

                            <div class="card border-secondary mb-3">
                                <div class="card-header"><h6 class="mb-0"><i class="fa fa-id-card me-1"></i> Additional Staff Details</h6></div>
                                <div class="card-body">
                                    <div class="row g-3">
                                        <div class="col-md-2"><label class="form-label" for="prefix">Prefix</label><input class="form-control" id="prefix" name="prefix" value="<?=htmlspecialchars($staff->prefix ?? '')?>" maxlength="3"></div>
                                        <div class="col-md-5"><label class="form-label" for="mname">Middle Name(s)</label><input class="form-control" id="mname" name="mname" value="<?=htmlspecialchars($staff->mName ?? '')?>" maxlength="50"></div>
                                        <div class="col-md-5"><label class="form-label" for="initials">Initials</label><input class="form-control" id="initials" name="initials" value="<?=htmlspecialchars($staff->initials ?? '')?>" maxlength="4"></div>
                                        <div class="col-md-4"><label class="form-label" for="subRank">Substantive Rank Reference</label><input class="form-control" id="subRank" name="subRank" value="<?=htmlspecialchars($staff->subRank ?? '')?>" maxlength="10"></div>
                                        <div class="col-md-4"><label class="form-label" for="tempRank">Temporary Rank Reference</label><input class="form-control" id="tempRank" name="tempRank" value="<?=htmlspecialchars($staff->tempRank ?? '')?>" maxlength="10"></div>
                                        <div class="col-md-4"><label class="form-label" for="localRank">Local Rank</label><input class="form-control" id="localRank" name="localRank" value="<?=htmlspecialchars($staff->localRank ?? '')?>" maxlength="150"></div>
                                        <div class="col-md-4"><label class="form-label" for="localWef">Local Rank Effective Date</label><input type="date" class="form-control" id="localWef" name="localWef" value="<?=htmlspecialchars($staff->localWef ?? '')?>"></div>
                                        <div class="col-md-4"><label class="form-label" for="unitAtt">Unit Attached</label><input class="form-control" id="unitAtt" name="unitAtt" value="<?=htmlspecialchars($staff->unitAtt ?? '')?>" maxlength="25"></div>
                                        <div class="col-md-4"><label class="form-label" for="intake">Intake</label><input class="form-control" id="intake" name="intake" value="<?=htmlspecialchars($staff->intake ?? '')?>" maxlength="15"></div>
                                        <div class="col-md-4"><label class="form-label" for="province">Province</label><input class="form-control" id="province" name="province" value="<?=htmlspecialchars($staff->province ?? '')?>" maxlength="12"></div>
                                        <div class="col-md-4"><label class="form-label" for="district">District</label><input class="form-control" id="district" name="district" value="<?=htmlspecialchars($staff->district ?? '')?>" maxlength="100"></div>
                                        <div class="col-md-4"><label class="form-label" for="village">Village</label><input class="form-control" id="village" name="village" value="<?=htmlspecialchars($staff->village ?? '')?>" maxlength="100"></div>
                                        <div class="col-md-4"><label class="form-label" for="titles">Titles</label><input class="form-control" id="titles" name="titles" value="<?=htmlspecialchars($staff->titles ?? '')?>" maxlength="80"></div>
                                        <div class="col-md-4"><label class="form-label" for="digitalId">Digital ID</label><input class="form-control" id="digitalId" name="digitalId" value="<?=htmlspecialchars($staff->digitalId ?? '')?>" maxlength="10"></div>
                                        <div class="col-md-4"><label class="form-label" for="tel2">Secondary Phone</label><input type="tel" class="form-control" id="tel2" name="tel2" value="<?=htmlspecialchars($staff->tel2 ?? '')?>" maxlength="20"></div>
                                        <div class="col-md-4"><label class="form-label" for="passPort">Passport Number</label><input class="form-control" id="passPort" name="passPort" value="<?=htmlspecialchars($staff->passPort ?? '')?>" maxlength="15"></div>
                                        <div class="col-md-4"><label class="form-label" for="passExp">Passport Expiry</label><input type="date" class="form-control" id="passExp" name="passExp" value="<?=htmlspecialchars($staff->passExp ?? '')?>"></div>
                                    </div>
                                    <hr>
                                    <h6 class="text-secondary">Contract and Separation</h6>
                                    <div class="row g-3">
                                        <div class="col-md-4"><label class="form-label" for="contFrom">Contract Start</label><input type="date" class="form-control" id="contFrom" name="contFrom" value="<?=htmlspecialchars($staff->contFrom ?? '')?>"></div>
                                        <div class="col-md-4"><label class="form-label" for="contDuration">Contract Duration</label><input class="form-control" id="contDuration" name="contDuration" value="<?=htmlspecialchars($staff->contDuration ?? '')?>" maxlength="11"></div>
                                        <div class="col-md-4"><label class="form-label" for="contEnd">Contract End</label><input type="date" class="form-control" id="contEnd" name="contEnd" value="<?=htmlspecialchars($staff->contEnd ?? '')?>"></div>
                                        <div class="col-md-4"><label class="form-label" for="dateSeparated">Date Separated</label><input type="date" class="form-control" id="dateSeparated" name="dateSeparated" value="<?=htmlspecialchars($staff->dateSeparated ?? '')?>"></div>
                                    </div>
                                    <hr>
                                    <h6 class="text-secondary">Alternate Next of Kin</h6>
                                    <div class="row g-3">
                                        <div class="col-md-4"><label class="form-label" for="altNok">Name</label><input class="form-control" id="altNok" name="altNok" value="<?=htmlspecialchars($staff->altNok ?? '')?>" maxlength="50"></div>
                                        <div class="col-md-4"><label class="form-label" for="altNokTel">Phone</label><input type="tel" class="form-control" id="altNokTel" name="altNokTel" value="<?=htmlspecialchars($staff->altNokTel ?? '')?>" maxlength="20"></div>
                                        <div class="col-md-4"><label class="form-label" for="altNokRelat">Relationship</label><input class="form-control" id="altNokRelat" name="altNokRelat" value="<?=htmlspecialchars($staff->altNokRelat ?? '')?>" maxlength="30"></div>
                                    </div>
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
                                    <strong>Expected fields:</strong> Operation Name, Location, Start/End Dates, and Remarks
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
                            <h5 class="mb-0"><i class="fa fa-person-rifle"></i> Deployments</h5>
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
                    <!-- Dynamic Skills/Courses Section -->
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
                                    <strong>Expected fields:</strong> Appointment ID, Unit, Role/Appointment Type, Start/End Dates, Authority Reference
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
                            <label class="form-label">Operation</label>
                            <select name="operations[${index}][opId]" class="form-select" required>
                                ${buildOperationsDropdown(data.opId)}
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Authority Reference</label>
                            <input type="text" name="operations[${index}][authorityId]" class="form-control"
                                   placeholder="Authority reference" value="${data.authID || data.authorityId || ''}">
                        </div>
                    </div>
                    <div class="row g-3 mt-2">
                        <div class="col-md-4">
                            <label class="form-label">Start Date</label>
                            <input type="date" name="operations[${index}][startDate]" class="form-control"
                                   value="${data.opStart || data.startDate || ''}">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">End Date</label>
                            <input type="date" name="operations[${index}][endDate]" class="form-control"
                                   value="${data.opEnd || data.endDate || ''}">
                        </div>
                        <div class="col-md-4 d-flex align-items-end">
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
    }

    function buildOperationsDropdown(selectedValue = '') {
        let html = '<option value="">Select Operation</option>';
        if (window.operationsOptions && window.operationsOptions.length > 0) {
            window.operationsOptions.forEach(op => {
                const selected = (selectedValue && selectedValue == op.id) ? 'selected' : '';
                html += `<option value="${op.id}" ${selected}>${op.name}${op.code ? ' (' + op.code + ')' : ''}</option>`;
            });
        }
        return html;
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
                    const months = Math.round(diffDays / 30.44);
                    durationInput.value = months;
                } else {
                    durationInput.value = '';
                }
            }
        }

        startDateInput.addEventListener('change', calculateDeploymentDuration);
        endDateInput.addEventListener('change', calculateDeploymentDuration);
    }

    function addEducationRow(data = {}) {
        const container = document.getElementById('educationList');
        const index = educationCounter++;
        const html = `
            <div class="card mb-3 education-item" data-index="${index}">
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-3">
                            <label class="form-label">Institution</label>
                            <input type="text" name="education[${index}][instId]" class="form-control"
                                   placeholder="Institution" value="${data.instId || data.institution || ''}" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Course ID</label>
                            <input type="text" name="education[${index}][cseId]" class="form-control"
                                   placeholder="Course ID" value="${data.cseId || ''}">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Qualification</label>
                            <input type="text" name="education[${index}][qualification]" class="form-control"
                                   placeholder="e.g., Bachelor's Degree" value="${data.qualification || ''}">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Authority Reference</label>
                            <input type="text" name="education[${index}][authorityId]" class="form-control"
                                   placeholder="Authority reference" value="${data.authID || data.authorityId || ''}">
                        </div>
                    </div>
                    <div class="row g-3 mt-2">
                        <div class="col-md-3">
                            <label class="form-label">Start Year</label>
                            <input type="number" name="education[${index}][yearStarted]" class="form-control"
                                   placeholder="2020" value="${(data.cseStart || '').toString().slice(0,4) || data.yearStarted || ''}" min="1900" max="2030">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">End Year</label>
                            <input type="number" name="education[${index}][yearCompleted]" class="form-control"
                                   placeholder="2024" value="${(data.cseEnd || '').toString().slice(0,4) || data.yearCompleted || ''}" min="1900" max="2030">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Grade</label>
                            <input type="text" name="education[${index}][grade]" class="form-control"
                                   placeholder="A, B+, etc." value="${data.grade || ''}">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Result</label>
                            <input type="text" name="education[${index}][result]" class="form-control"
                                   placeholder="Pass, Distinction, etc." value="${data.result || ''}">
                        </div>
                    </div>
                    <div class="row g-3 mt-2">
                        <div class="col-md-8">
                            <div class="form-check mt-2">
                                <input class="form-check-input" type="checkbox" name="education[${index}][isHighest]"
                                       value="1" id="highest_${index}" ${data.isHighest == 1 ? 'checked' : ''}>
                                <label class="form-check-label" for="highest_${index}">
                                    Highest qualification
                                </label>
                            </div>
                        </div>
                        <div class="col-md-4 d-flex align-items-end">
                            <button type="button" class="btn btn-danger w-100" onclick="removeEducationRow(${index})">
                                <i class="fa fa-trash"></i> Remove
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        `;
        container.insertAdjacentHTML('beforeend', html);
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
                    const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24)) + 1;
                    durationInput.value = diffDays;
                } else {
                    durationInput.value = '';
                }
            }
        }

        startDateInput.addEventListener('change', calculateSkillDuration);
        endDateInput.addEventListener('change', calculateSkillDuration);
    }

    // Remove functions
    function removeOperationRow(index) {
        const item = document.querySelector(`.operation-item[data-index="${index}"]`);
        if (item && confirm('Are you sure you want to remove this operation?')) {
            item.remove();
        }
    }
    window.removeOperationRow = removeOperationRow;

    function removeDeploymentRow(index) {
        const item = document.querySelector(`.deployment-item[data-index="${index}"]`);
        if (item && confirm('Are you sure you want to remove this deployment?')) {
            item.remove();
        }
    }
    window.removeDeploymentRow = removeDeploymentRow;

    function removeEducationRow(index) {
        const item = document.querySelector(`.education-item[data-index="${index}"]`);
        if (item && confirm('Are you sure you want to remove this education record?')) {
            item.remove();
        }
    }
    window.removeEducationRow = removeEducationRow;

    function removeSkillRow(index) {
        const item = document.querySelector(`.skill-item[data-index="${index}"]`);
        if (item && confirm('Are you sure you want to remove this skill/course?')) {
            item.remove();
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

        const html = `
            <div class="card mb-3 posting-item" data-index="${index}">
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Appointment ID <span class="text-danger">*</span></label>
                            <input type="text" name="postings[${index}][apptId]" class="form-control"
                                   placeholder="Appointment reference" value="${data.apptId || ''}" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Unit</label>
                            <select name="postings[${index}][unitId]" class="form-select">
                                ${unitsOptionsHTML}
                            </select>
                            <small class="text-muted">Assigned unit/formation</small>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Appointment Type</label>
                            <input type="text" name="postings[${index}][appointment_type]" class="form-control"
                                   placeholder="e.g., Company Commander" value="${data.apptType || ''}">
                        </div>
                    </div>
                    <div class="row g-3 mt-2">
                        <div class="col-md-3">
                            <label class="form-label">Start Date <span class="text-danger">*</span></label>
                            <input type="date" name="postings[${index}][startDate]" class="form-control posting-start-date"
                                   value="${data.apptWef || data.startDate || ''}" required onchange="calculatePostingDuration(${index})">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">End Date</label>
                            <input type="date" name="postings[${index}][endDate]" class="form-control posting-end-date"
                                   value="${data.endDate || ''}" onchange="calculatePostingDuration(${index})">
                            <small class="text-muted">Leave blank if current</small>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Powers</label>
                            <input type="text" name="postings[${index}][powers]" class="form-control"
                                   value="${data.powers || ''}">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Authority Reference</label>
                            <input type="text" name="postings[${index}][authorityId]" class="form-control"
                                   value="${data.authorityId || ''}">
                        </div>
                    </div>
                    <div class="row g-3 mt-2">
                        <div class="col-md-12">
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
    }

    function removePostingRow(index) {
        const item = document.querySelector(`.posting-item[data-index="${index}"]`);
        if (item && confirm('Are you sure you want to remove this posting?')) {
            item.remove();
        }
    }
    window.removePostingRow = removePostingRow;

    function calculatePostingDuration(index) {
        // Duration is computed server-side on save; nothing to render client-side
        // since staff_appointment.durationMonths isn't shown as its own input.
    }
    window.calculatePostingDuration = calculatePostingDuration;

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
    }

    function removeAwardRow(index) {
        const item = document.querySelector(`.award-item[data-index="${index}"]`);
        if (item && confirm('Are you sure you want to remove this award?')) {
            item.remove();
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
                                <option value="1" ${data.confidential != '0' ? 'selected' : ''}>Yes (Restricted)</option>
                                <option value="0" ${data.confidential == '0' ? 'selected' : ''}>No (Visible)</option>
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
    }

    function removeDisciplinaryRow(index) {
        const item = document.querySelector(`.disciplinary-item[data-index="${index}"]`);
        if (item && confirm('Are you sure you want to remove this disciplinary record?')) {
            item.remove();
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
    window.calculateDisciplinaryDuration = calculateDisciplinaryDuration;

    // Function to validate dynamic fields before form submission
    function validateDynamicFields() {
        let isValid = true;
        const errors = [];

        const operations = document.querySelectorAll('#operationsList .operation-item');
        operations.forEach((op, index) => {
            const opIdField = op.querySelector('select[name*="[opId]"]');
            if (opIdField && !opIdField.value.trim()) {
                opIdField.classList.add('is-invalid');
                errors.push(`Operation #${index + 1}: an Operation must be selected`);
                isValid = false;
            } else if (opIdField) {
                opIdField.classList.remove('is-invalid');
            }
        });

        const deployments = document.querySelectorAll('#deploymentsList .deployment-item');
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

        const education = document.querySelectorAll('#educationList .education-item');
        education.forEach((edu, index) => {
            const instIdField = edu.querySelector('input[name*="[instId]"]');
            const cseIdField = edu.querySelector('input[name*="[cseId]"]');
            if ((!instIdField || !instIdField.value.trim()) && (!cseIdField || !cseIdField.value.trim())) {
                if (instIdField) instIdField.classList.add('is-invalid');
                if (cseIdField) cseIdField.classList.add('is-invalid');
                errors.push(`Education #${index + 1}: Institution or Course ID is required`);
                isValid = false;
            } else {
                if (instIdField) instIdField.classList.remove('is-invalid');
                if (cseIdField) cseIdField.classList.remove('is-invalid');
            }
        });

        const skills = document.querySelectorAll('#skillsList .skill-item');
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

        const postings = document.querySelectorAll('#postingsList .posting-item');
        postings.forEach((posting, index) => {
            const apptIdField = posting.querySelector('input[name*="[apptId]"]');
            if (apptIdField && !apptIdField.value.trim()) {
                apptIdField.classList.add('is-invalid');
                errors.push(`Posting #${index + 1}: Appointment ID is required`);
                isValid = false;
            } else if (apptIdField) {
                apptIdField.classList.remove('is-invalid');
            }
        });

        if (!isValid) {
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

    document.getElementById('addPostingBtn')?.addEventListener('click', function() {
        addPostingRow();
    });

    document.getElementById('addAwardBtn')?.addEventListener('click', function() {
        addAwardRow();
    });

    document.getElementById('addDisciplinaryBtn')?.addEventListener('click', function() {
        addDisciplinaryRow();
    });

    // Flash alerts for 5 seconds
    const alerts = document.querySelectorAll('.alert-success');
    alerts.forEach(function(alert) {
        setTimeout(function() {
            const closeBtn = alert.querySelector('.btn-close');
            if (closeBtn) closeBtn.click();
        }, 5000);
    });

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
                if (this.value.trim() && !this.checkValidity()) {
                    const fieldType = this.type === 'email' ? 'email address' : 'phone number';
                    const confirmClear = confirm(`The ${fieldType} appears to be incomplete or invalid.\n\nCurrent value: ${this.value}\n\nWould you like to clear it? (Click Cancel to edit it)`);
                    if (confirmClear) {
                        this.value = '';
                        this.classList.remove('is-invalid');
                    }
                }
            });
        }
    });

    // Form validation
    const editForm = document.getElementById('editStaffForm');
    if (editForm) {
        editForm.addEventListener('submit', function(e) {
            // Remove HTML5 validation constraints for optional fields to prevent blocking
            const optionalContactFields = ['email', 'tel', 'nokTel'];
            optionalContactFields.forEach(fieldId => {
                const field = document.getElementById(fieldId);
                if (field && field.value.trim() && !field.checkValidity()) {
                    field.setAttribute('data-original-type', field.type);
                    field.type = 'text';
                }
            });

            const staffId = document.querySelector('input[name="svcNo"]');
            if (!staffId || !staffId.value) {
                e.preventDefault();
                alert('Error: No staff member selected. Please select a staff member first.');
                return false;
            }

            const formValidationResult = validateForm();
            const dynamicValidationResult = validateDynamicFields();

            if (!formValidationResult || !dynamicValidationResult) {
                e.preventDefault();
                alert('Please fix the validation errors before submitting.');
                window.scrollTo(0, 0);
                return false;
            }

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
            validateForm(true);
        });
    }

    // Validate form function
    function validateForm(showResults = false) {
        let isValid = true;
        let validationReport = [];

        const requiredFields = document.querySelectorAll('[required]');
        requiredFields.forEach(field => {
            if (!field.value.trim()) {
                isValid = false;
                field.classList.add('is-invalid');
                const feedbackEl = document.getElementById(`${field.id}-error`);
                if (feedbackEl) feedbackEl.textContent = 'This field is required';
            } else {
                field.classList.remove('is-invalid');
            }
        });

        const formData = new FormData(editForm);
        const csrfToken = formData.get('csrf_token');
        if (!csrfToken) {
            isValid = false;
            validationReport.push('CSRF token is missing');
        }

        const svcNo = formData.get('svcNo');
        if (!svcNo) {
            isValid = false;
            validationReport.push('Service number is missing');
        }

        if (showResults) {
            alert(`Validation Results:\n${validationReport.join('\n')}\n\nForm is ${isValid ? 'valid' : 'invalid'}`);
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

    // CSV Export functionality (server-side export already available via ?export=csv link)

    // Size field validation and user experience improvements
    const sizeFields = {
        combatSize: document.getElementById('combatSize'),
        bsize: document.getElementById('bsize'),
        ssize: document.getElementById('ssize'),
        hdress: document.getElementById('hdress')
    };

    Object.keys(sizeFields).forEach(fieldName => {
        const field = sizeFields[fieldName];
        if (field) {
            field.addEventListener('change', function() {
                if (this.value) {
                    this.classList.add('border-success');
                    this.classList.remove('border-warning');
                } else {
                    this.classList.remove('border-success');
                    this.classList.add('border-warning');
                }
            });

            if (field.value) {
                field.classList.add('border-success');
            }
        }
    });

    // ==================== RETIREMENT DATE CALCULATOR ====================
    function updateRetirementInfo() {
        const dobField = document.getElementById('DOB');
        const attestDateField = document.getElementById('attestDate');

        if (!dobField || !attestDateField) return;

        const dob = dobField.value;
        const attestDate = attestDateField.value;

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

        const runoutDisplay = document.querySelector('[data-retirement="runout"]');
        const earlyRetDisplay = document.querySelector('[data-retirement="earlyRetirement"]');

        if (runoutDisplay) runoutDisplay.innerHTML = runoutHtml;
        if (earlyRetDisplay) earlyRetDisplay.innerHTML = earlyRetHtml;
    }

    const dobInput = document.getElementById('DOB');
    const attestDateInput = document.getElementById('attestDate');

    if (dobInput) {
        dobInput.addEventListener('change', updateRetirementInfo);
    }

    if (attestDateInput) {
        attestDateInput.addEventListener('change', updateRetirementInfo);
    }

    const initialData = window.initialEditStaffData || {};
    (initialData.operations || []).forEach(addOperationRow);
    (initialData.deployments || []).forEach(addDeploymentRow);
    (initialData.education || []).forEach(addEducationRow);
    (initialData.skills || []).forEach(addSkillRow);
    (initialData.postings || []).forEach(addPostingRow);
    (initialData.awards || []).forEach(addAwardRow);
    (initialData.disciplinary || []).forEach(addDisciplinaryRow);

    updateRetirementInfo();
});
</script>
<?php include dirname(__DIR__) . '/shared/footer.php'; ?>