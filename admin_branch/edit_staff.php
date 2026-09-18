<?php
// Define module constants
define('ARMIS_ADMIN_BRANCH', true);

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
            // Unit is intentionally not editable here; posting changes use appointments.php.
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
                        'staff_edit_log', 'staff_appointment',
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
                        // Core personnel fields only. Posting/unit changes are handled by
                        // Personnel Posting & Appointments; branch-owned education/operations
                        // records are deliberately not writable from this form.
                        'fName' => $fname,
                        'prefix' => $prefix,
                        'lName' => $lname,
                        'rankId' => $rankID,
                        'NRC' => $NRC,
                        'DOB' => $DOB,
                        'gender' => $gender,
                        'svcStatus' => $svcStatus,
                        'telNo' => $tel,
                        'officialEmail' => $email,
                        'corps' => $corpsID,
                        'attestDate' => $attestDate,
                        'trade' => $trade,
                        'bloodGp' => $_POST['bloodGroup'] ?? ($originalStaff->bloodGp ?? null)
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

                    // Operations, deployments and education are maintained by their responsible modules.
                    // They are intentionally not accepted from this Admin Branch staff-edit form.

                    // Skills/courses are maintained by the Training module.

                    // Posting history is maintained transactionally by appointments.php.

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
$staffAwards = [];
$staffDisciplinary = [];

if (!empty($staff)) {


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
    'awards' => $staffAwards,
    'disciplinary' => $staffDisciplinary
], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT)?>;
</script>

<!-- Load custom CSS and assets -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.3/css/bootstrap.min.css">
<link rel="stylesheet" href="/Armis2/admin_branch/css/form-step-styles.css">
<style>.edit-staff-flat-form .form-step{display:block!important;visibility:visible!important;position:relative!important}.edit-staff-flat-form .form-step:not(:first-child){margin-top:1.5rem}</style>
<!-- Core JS (jQuery + Bootstrap) are loaded centrally in shared/footer.php -->
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
                <form method="post" id="editStaffForm" class="edit-staff-flat-form" autocomplete="off" aria-label="Edit Staff Member">
    <input type="hidden" name="edit_staff" value="1">
    <input type="hidden" name="svcNo" value="<?=htmlspecialchars($staff->svcNo ?? '')?>">
    <input type="hidden" name="newSvcNo" value="<?=htmlspecialchars($staff->svcNo ?? '')?>">
    <input type="hidden" name="csrf_token" value="<?=csrf_token()?>">
    <input type="hidden" name="unitID" value="<?=htmlspecialchars($staff->unitId ?? '')?>">

    <style>
      .edit-core-shell{max-width:1180px;margin:0 auto}
      .edit-core-card{background:#fff;border:1px solid #e3e8ef;border-radius:14px;box-shadow:0 3px 14px rgba(24,39,75,.05);overflow:hidden;margin-bottom:18px}
      .edit-core-head{padding:16px 20px;border-bottom:1px solid #e9edf2;display:flex;align-items:center;justify-content:space-between;gap:12px}
      .edit-core-head h5{margin:0;font-size:1rem;font-weight:700}
      .edit-core-body{padding:20px}
      .edit-core-label{font-weight:600;font-size:.88rem;margin-bottom:6px}
      .edit-core-required{color:#dc3545}
      .edit-core-help{font-size:.76rem;color:#6c757d;margin-top:4px}
      .edit-person-banner{background:#f8fafc;border:1px solid #e7ecf2;border-radius:12px;padding:15px 17px;margin-bottom:20px}
      .edit-current-posting{background:#f3f8ff;border:1px solid #d7e8ff;border-radius:10px;padding:13px 15px}
      .edit-actions{position:sticky;bottom:0;z-index:30;background:rgba(255,255,255,.97);border-top:1px solid #dfe5eb;padding:13px 0;backdrop-filter:blur(7px)}
      .edit-actions-inner{max-width:1180px;margin:auto;display:flex;justify-content:space-between;align-items:center;gap:12px}
      @media(max-width:767.98px){.edit-core-body{padding:15px}.edit-actions-inner{flex-direction:column;align-items:stretch}.edit-actions-inner .btn{width:100%}}
    </style>

    <div class="edit-core-shell">
      <?php if (!empty($errors)): ?>
        <div class="alert alert-danger mb-4"><h6 class="mb-2"><i class="fa fa-exclamation-triangle"></i> Please fix the following:</h6><ul class="mb-0"><?php foreach($errors as $error): ?><li><?=htmlspecialchars($error)?></li><?php endforeach; ?></ul></div>
      <?php endif; ?>

      <div class="edit-person-banner">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
          <div><div class="small text-muted text-uppercase fw-semibold">Personnel Record</div><h4 class="mb-1"><?=htmlspecialchars(trim(($staff->fName ?? '').' '.($staff->lName ?? '')))?></h4><div class="text-muted"><strong>Service No:</strong> <?=htmlspecialchars($staff->svcNo ?? '')?> &nbsp;•&nbsp; <strong>Rank:</strong> <?=htmlspecialchars($rankMap[$staff->rankId] ?? ($staff->rankId ?? 'Not set'))?></div></div>
          <div class="text-end"><span class="badge bg-light text-dark border">Core Personnel Details</span><div class="small text-muted mt-1">Extended records are maintained by their responsible modules.</div></div>
        </div>
      </div>

      <div class="edit-core-card">
        <div class="edit-core-head"><h5><i class="fa fa-user text-primary me-2"></i>Identity &amp; Personal Details</h5><span class="small text-muted">Core record</span></div>
        <div class="edit-core-body">
          <div class="row g-3">
            <div class="col-md-2"><label class="edit-core-label" for="prefix">Prefix</label><input class="form-control" name="prefix" id="prefix" value="<?=htmlspecialchars($staff->prefix ?? '')?>" maxlength="20"></div>
            <div class="col-md-4"><label class="edit-core-label" for="fname">Forname(s) <span class="edit-core-required">*</span></label><input class="form-control" name="fname" id="fname" required maxlength="100" value="<?=htmlspecialchars($staff->fName ?? '')?>"></div>
            <div class="col-md-6"><label class="edit-core-label" for="lname">Surname <span class="edit-core-required">*</span></label><input class="form-control" name="lname" id="lname" required maxlength="100" value="<?=htmlspecialchars($staff->lName ?? '')?>"></div>
            <div class="col-md-4"><label class="edit-core-label" for="NRC">NRC <span class="text-muted">(Optional)</span></label><input class="form-control" name="NRC" id="NRC" value="<?=htmlspecialchars($staff->NRC ?? '')?>" maxlength="50" placeholder="123456/12/1"></div>
            <div class="col-md-4"><label class="edit-core-label" for="DOB">Date of Birth</label><input type="date" class="form-control" name="DOB" id="DOB" value="<?=htmlspecialchars($staff->DOB ?? '')?>"></div>
            <div class="col-md-2"><label class="edit-core-label" for="gender">Gender <span class="edit-core-required">*</span></label><select class="form-select" name="gender" id="gender" required><option value="">Select</option><?php foreach(VALID_GENDERS as $g): ?><option value="<?=htmlspecialchars($g)?>" <?=($staff->gender ?? '')===$g?'selected':''?>><?=htmlspecialchars($g)?></option><?php endforeach; ?></select></div>
            <div class="col-md-2"><label class="edit-core-label" for="bloodGroup">Blood Group</label><select class="form-select" name="bloodGroup" id="bloodGroup"><option value="">Select</option><?php foreach(['A+','A-','B+','B-','AB+','AB-','O+','O-'] as $bg): ?><option value="<?=$bg?>" <?=($staff->bloodGp ?? '')===$bg?'selected':''?>><?=$bg?></option><?php endforeach; ?></select></div>
          </div>
        </div>
      </div>

      <div class="edit-core-card">
        <div class="edit-core-head"><h5><i class="fa fa-shield-alt text-primary me-2"></i>Service Details</h5><span class="small text-muted">Current classification</span></div>
        <div class="edit-core-body">
          <div class="row g-3">
            <div class="col-md-4"><label class="edit-core-label" for="rankID">Rank <span class="edit-core-required">*</span></label><select class="form-select" name="rankID" id="rankID" required><option value="">Select Rank</option><?php foreach($ranks as $rank): ?><option value="<?=htmlspecialchars($rank->rankID)?>" <?=($staff->rankId ?? '')==$rank->rankID?'selected':''?>><?=htmlspecialchars($rank->rankName)?></option><?php endforeach; ?></select></div>
            <div class="col-md-4"><label class="edit-core-label">Current Unit</label><div class="edit-current-posting"><strong><?=htmlspecialchars($unitMap[$staff->unitId] ?? ($staff->unitId ?? 'Not assigned'))?></strong><div class="small text-muted mt-1">Unit changes are controlled through Personnel Posting &amp; Appointments.</div></div></div>
            <div class="col-md-4"><label class="edit-core-label" for="corps">Corps</label><select class="form-select" name="corps" id="corps"><option value="">Select Corps</option><?php foreach($corps as $corp): $cv=$corp->corpsName??$corp->corps??''; ?><option value="<?=htmlspecialchars($cv)?>" <?=($staff->corps ?? '')===$cv?'selected':''?>><?=htmlspecialchars($cv)?></option><?php endforeach; ?></select></div>
            <div class="col-md-4"><label class="edit-core-label" for="svcStatus">Service Status <span class="edit-core-required">*</span></label><select class="form-select" name="svcStatus" id="svcStatus" required><?php foreach(VALID_STATUSES as $status): ?><option value="<?=htmlspecialchars($status)?>" <?=($staff->svcStatus ?? '')===$status?'selected':''?>><?=htmlspecialchars($status)?></option><?php endforeach; ?></select></div>
            <div class="col-md-4"><label class="edit-core-label" for="attestDate">Date of Enlistment / Attestation</label><input type="date" class="form-control" name="attestDate" id="attestDate" value="<?=htmlspecialchars($staff->attestDate ?? '')?>"></div>
            <div class="col-md-4"><label class="edit-core-label" for="trade">Trade / Specialisation</label><input class="form-control" name="trade" id="trade" maxlength="100" value="<?=htmlspecialchars($staff->trade ?? '')?>"></div>
          </div>
        </div>
      </div>

      <div class="edit-core-card">
        <div class="edit-core-head"><h5><i class="fa fa-address-card text-primary me-2"></i>Contact Details</h5><span class="small text-muted">Core contact information</span></div>
        <div class="edit-core-body"><div class="row g-3">
          <div class="col-md-6"><label class="edit-core-label" for="email">Official Email</label><input type="email" class="form-control" name="email" id="email" maxlength="100" value="<?=htmlspecialchars($staff->officialEmail ?? '')?>"></div>
          <div class="col-md-6"><label class="edit-core-label" for="tel">Phone Number</label><input type="tel" class="form-control" name="tel" id="tel" maxlength="20" value="<?=htmlspecialchars($staff->telNo ?? '')?>" placeholder="+260 97X XXX XXX"></div>
        </div></div>
      </div>

      <div class="alert alert-info border-0 shadow-sm"><i class="fa fa-route me-1"></i><strong>Posting:</strong> To change this person's unit or appointment, use <a href="appointments.php" class="alert-link">Personnel Posting &amp; Appointments</a>. Education is maintained in Training; operations and deployments are maintained in Operations.</div>
    </div>

    <div class="edit-actions"><div class="edit-actions-inner"><div class="small text-muted"><i class="fa fa-info-circle"></i> Only core personnel details are edited here.</div><div class="d-flex gap-2"><a href="view_staff.php?svcNo=<?=urlencode($staff->svcNo ?? '')?>" class="btn btn-outline-secondary">Cancel</a><button type="submit" name="save_staff" value="1" class="btn btn-armis-primary"><i class="fa fa-save"></i> Save Changes</button></div></div></div>
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

    // Setup UI event handlers
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
            if (!formValidationResult) {
                e.preventDefault();
                alert('Please fix the validation errors before submitting.');
                window.scrollTo(0, 0);
                return false;
            }

            const summary = `You are about to update the core personnel record, awards and disciplinary information.

Posting, operations, deployments and education are maintained by their responsible modules.

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
    (initialData.awards || []).forEach(addAwardRow);
    (initialData.disciplinary || []).forEach(addDisciplinaryRow);

    updateRetirementInfo();
});
</script>
<?php include dirname(__DIR__) . '/shared/footer.php'; ?>