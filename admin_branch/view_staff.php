<?php
define('ARMIS_ADMIN_BRANCH', true);
define('ARMIS_DEVELOPMENT', true);

require_once dirname(__DIR__) . '/shared/database_connection.php';
require_once dirname(__DIR__) . '/shared/rank_levels.php';
// Reuse the app's single, established photo-resolution logic (also used by
// users/index.php) instead of maintaining a second, separate implementation
// here that can drift out of sync with it.
require_once dirname(__DIR__) . '/users/profile_manager.php';

$pdo = getDbConnection();

$pageTitle = "Staff Profile - Admin Branch";
$moduleName = "Admin Branch";
$moduleIcon = "users-cog";
$currentPage = "profile";

$additionalStyles = [
    '/Armis2/admin_branch/css/advanced-profile.css'
];

$additionalScripts = [
    '/Armis2/admin_branch/js/advanced-profile.js',
];

$sidebarLinks = []; // set by shared nav include below
require_once __DIR__ . '/includes/sidebar_nav.php';

$svcNo = isset($_GET['svcNo']) ? trim($_GET['svcNo']) : '';
if (empty($svcNo)) {
    die('<div class="alert alert-danger">Invalid service number.</div>');
}

// ==================== FETCH MAIN STAFF RECORD ====================
// staff.unitId references unit.unitId; unit only has unitLoc (no separate "name" column),
// so unitId itself doubles as the display code and unitLoc gives the location.
$sql = "SELECT s.*, r.rankId AS rankName, r.rankId AS rankAbbr, r.rankIndex AS rankIndex,
            " . getRankCategoryCaseSQL('r') . " AS rankCategory,
            u.unitId AS unitName, u.unitLoc AS unitLocation,
            COALESCE(NULLIF(s.corps, ''), 'N/A') AS corpsName,
            TIMESTAMPDIFF(YEAR, s.attestDate, CURDATE()) as years_of_service,
            TIMESTAMPDIFF(YEAR, s.DOB, CURDATE()) as age
        FROM staff s
        LEFT JOIN `rank` r ON s.rankId = r.rankId
        LEFT JOIN unit u ON s.unitId = u.unitId
        WHERE s.svcNo = ?
        LIMIT 1";

$stmt = $pdo->prepare($sql);
$stmt->execute([$svcNo]);
$staff = $stmt->fetch(PDO::FETCH_OBJ);

if (!$staff) {
    die('<div class="alert alert-danger">Staff member not found.</div>');
}

// Reused across the module for photo resolution (see PROFILE HERO below) -
// UserProfileManager is generically keyed by whatever svcNo is passed to
// its constructor, so it works equally well here (viewing an arbitrary
// staff member's record) as it does in users/index.php (viewing your own).
$profileManager = new UserProfileManager($svcNo);

// ==================== CURRENT APPOINTMENT (most recent open/latest posting) ====================
$currentAppointment = null;
try {
    $curApptStmt = $pdo->prepare("
        SELECT sa.*, u.unitLoc AS unit_location
        FROM staff_appointment sa
        LEFT JOIN unit u ON sa.unitId = u.unitId
        WHERE sa.svcNo = ?
        ORDER BY (sa.endDate IS NULL) DESC, sa.apptWef DESC
        LIMIT 1
    ");
    $curApptStmt->execute([$svcNo]);
    $currentAppointment = $curApptStmt->fetch(PDO::FETCH_OBJ);
} catch (Exception $e) {
    error_log("Error fetching current appointment: " . $e->getMessage());
}

// ==================== COURSES & EDUCATION (staff_course) ====================
// Real columns: instId, cseId, qualification, cseStart, cseEnd, grade, result, isHighest, authID
$courses = [];
try {
    $courseStmt = $pdo->prepare("
        SELECT
            sc.*,
            i.instLoc AS institutionLocation,
            i.instType AS institutionType,
            c.cseType AS courseType,
            c.cseLevel AS courseLevel
        FROM staff_course sc
        LEFT JOIN institution i ON sc.instId = i.instId
        LEFT JOIN course c ON sc.cseId = c.cseId
        WHERE sc.svcNo = ?
        ORDER BY sc.cseEnd DESC, sc.cseStart DESC
    ");
    $courseStmt->execute([$svcNo]);
    $courses = $courseStmt->fetchAll(PDO::FETCH_OBJ);
} catch (Exception $e) {
    error_log("Error fetching courses: " . $e->getMessage());
}

// ==================== SKILLS & TRAINING (staff_skills) ====================
$skills = [];
try {
    $skillStmt = $pdo->prepare("
        SELECT * FROM staff_skills
        WHERE svcNo = ?
        ORDER BY startDate DESC, course_name ASC
    ");
    $skillStmt->execute([$svcNo]);
    $skills = $skillStmt->fetchAll(PDO::FETCH_OBJ);
} catch (Exception $e) {
    error_log("Error fetching skills: " . $e->getMessage());
}

// ==================== OPERATIONS (staff_operation, singular) ====================
// Real columns: opId, opStart, opEnd, remarks, authID - joined against the `operation`
// lookup table (opId, opType) for a human-readable operation type.
$operations = [];
try {
    $opStmt = $pdo->prepare("
        SELECT so.*, o.opType AS operationType, a.description AS authorityDescription
        FROM staff_operation so
        LEFT JOIN operation o ON so.opId = o.opId
        LEFT JOIN authority a ON so.authID = a.authID
        WHERE so.svcNo = ?
        ORDER BY so.opStart DESC
    ");
    $opStmt->execute([$svcNo]);
    $operations = $opStmt->fetchAll(PDO::FETCH_OBJ);
} catch (Exception $e) {
    error_log("Error fetching operations: " . $e->getMessage());
}

// ==================== DEPLOYMENTS (staff_deployments - schema already matches) ====================
$deployments = [];
try {
    $deploymentStmt = $pdo->prepare("
        SELECT * FROM staff_deployments
        WHERE svcNo = ?
        ORDER BY startDate DESC
    ");
    $deploymentStmt->execute([$svcNo]);
    $deployments = $deploymentStmt->fetchAll(PDO::FETCH_OBJ);
} catch (Exception $e) {
    error_log("Error fetching deployments: " . $e->getMessage());
}

// ==================== PROMOTIONS & REVERSIONS (staff_promotion) ====================
// Real columns: currentRank, wefDate, dateTo, type, newRank, authID, remark
$promotions = [];
try {
    $promStmt = $pdo->prepare("
        SELECT
            p.*,
            r.rankId AS newRankName,
            IFNULL(pr.rankId, 'N/A') AS previousRankName,
            a.description AS authorityDescription,
            DATEDIFF(IFNULL(p.dateTo, CURDATE()), p.wefDate) as days_in_rank
        FROM staff_promotion p
        LEFT JOIN `rank` r ON p.newRank = r.rankId
        LEFT JOIN `rank` pr ON p.currentRank = pr.rankId
        LEFT JOIN authority a ON p.authID = a.authID
        WHERE p.svcNo = ?
        ORDER BY p.wefDate DESC
    ");
    $promStmt->execute([$svcNo]);
    $promotions = $promStmt->fetchAll(PDO::FETCH_OBJ);
} catch (Exception $e) {
    error_log("Error fetching promotions: " . $e->getMessage());
}

// ==================== POSTINGS & APPOINTMENTS (staff_appointment) ====================
// Real columns: apptId, apptType, unitId, apptWef, powers, endDate, durationMonths, authorityId, remarks
// (no rankId, no startDate, no posting_order_reference - those don't exist on this table)
$postings = [];
try {
    $postingStmt = $pdo->prepare("
        SELECT sa.*, u.unitLoc AS unit_location, a.description AS authorityDescription
        FROM staff_appointment sa
        LEFT JOIN unit u ON sa.unitId = u.unitId
        LEFT JOIN authority a ON sa.authorityId = a.authID
        WHERE sa.svcNo = ?
        ORDER BY sa.apptWef DESC
    ");
    $postingStmt->execute([$svcNo]);
    $postings = $postingStmt->fetchAll(PDO::FETCH_OBJ);
} catch (Exception $e) {
    error_log("Error fetching postings: " . $e->getMessage());
}

// ==================== HONOURS & AWARDS (staff_awards - the only real award/medal table) ====================
// Note: there is no `staff_medals`/`medal` table in the schema, so this is the single
// source of truth for recognitions of any kind (commendations, medals, certificates, etc.)
$awards = [];
try {
    $awardStmt = $pdo->prepare("
        SELECT * FROM staff_awards
        WHERE svcNo = ?
        ORDER BY award_date DESC
    ");
    $awardStmt->execute([$svcNo]);
    $awards = $awardStmt->fetchAll(PDO::FETCH_OBJ);
} catch (Exception $e) {
    error_log("Error fetching awards: " . $e->getMessage());
}

// ==================== DISCIPLINARY RECORDS (staff_disciplinary - schema already matches) ====================
$disciplinary = [];
try {
    $disciplinaryStmt = $pdo->prepare("
        SELECT * FROM staff_disciplinary
        WHERE svcNo = ?
        ORDER BY incident_date DESC
    ");
    $disciplinaryStmt->execute([$svcNo]);
    $disciplinary = $disciplinaryStmt->fetchAll(PDO::FETCH_OBJ);
} catch (Exception $e) {
    error_log("Error fetching disciplinary records: " . $e->getMessage());
}

// ==================== STATISTICS ====================
$yearsOfService = !empty($staff->attestDate) ? floor((time() - strtotime($staff->attestDate)) / (365.25 * 24 * 60 * 60)) : 0;
$promotionCount = count($promotions);
$courseCount = count($courses);
$skillCount = count($skills);
$deploymentCount = count($deployments);
$operationCount = count($operations);
$postingCount = count($postings);
$awardCount = count($awards);
$disciplinaryCount = count($disciplinary);

// ==================== RETIREMENT DATES ====================
function calculateRetirementInfo($dateOfBirth, $dateOfEnlistment) {
    $result = ['runout' => null, 'earlyRetirement' => null];

    // Expected Runout Date (Age 60)
    if (!empty($dateOfBirth)) {
        try {
            $dob = new DateTime($dateOfBirth);
            $runoutDate = clone $dob;
            $runoutDate->modify('+60 years');
            $now = new DateTime();

            $result['runout'] = [
                'date' => $runoutDate,
                'formatted' => $runoutDate->format('d M Y'),
                'isPast' => $runoutDate < $now,
            ];

            if ($runoutDate >= $now) {
                $interval = $now->diff($runoutDate);
                $result['runout']['remaining'] = "{$interval->y} years, {$interval->m} months";
                $result['runout']['yearsRemaining'] = $interval->y;
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
                $result['earlyRetirement']['remaining'] = "{$interval->y} years, {$interval->m} months";
                $result['earlyRetirement']['yearsRemaining'] = $interval->y;
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

function getRetirementUrgencyClass($yearsRemaining) {
    if ($yearsRemaining < 0) return 'text-danger';
    if ($yearsRemaining < 1) return 'text-danger';
    if ($yearsRemaining <= 5) return 'text-warning';
    if ($yearsRemaining <= 10) return 'text-info';
    return 'text-success';
}

/**
 * Renders a value, but greys out and italicizes it when empty/N/A so the eye
 * is drawn to fields that actually carry data.
 */
function displayValue($value, $suffix = '') {
    if ($value === null || $value === '' || $value === 'N/A') {
        return '<span class="text-muted fst-italic">N/A</span>';
    }
    return htmlspecialchars($value) . htmlspecialchars($suffix);
}

/**
 * Masks a sensitive value (NRC, email, phone) for initial display, with a
 * data-full attribute the "reveal" toggle in JS swaps in on click.
 */
function maskedField($value, $type = 'text') {
    if (empty($value)) {
        return '<span class="text-muted fst-italic">N/A</span>';
    }
    $full = htmlspecialchars($value);
    switch ($type) {
        case 'email':
            $parts = explode('@', $value);
            $masked = isset($parts[1]) ? substr($parts[0], 0, 2) . str_repeat('•', max(3, strlen($parts[0]) - 2)) . '@' . $parts[1] : str_repeat('•', 8);
            break;
        case 'phone':
            $masked = strlen($value) > 4 ? str_repeat('•', strlen($value) - 4) . substr($value, -4) : str_repeat('•', strlen($value));
            break;
        default:
            $masked = strlen($value) > 3 ? substr($value, 0, 2) . str_repeat('•', max(3, strlen($value) - 2)) : str_repeat('•', strlen($value));
    }
    $masked = htmlspecialchars($masked);
    return "<span class=\"sensitive-field\" data-full=\"{$full}\" data-masked=\"{$masked}\" data-state=\"masked\">{$masked}</span>"
        . ' <button type="button" class="btn-reveal" title="Show/hide" aria-label="Show or hide this sensitive value"><i class="fa fa-eye"></i></button>';
}

function statusBadgeClass($status) {
    switch (strtolower($status ?? '')) {
        case 'active': return 'badge-cmd-green';
        case 'retired': return 'badge-cmd-slate';
        case 'deceased': return 'bg-dark';
        case 'awol': return 'badge-cmd-crimson';
        case 'discharged': return 'badge-cmd-slate';
        case 'on contract': return 'badge-cmd-amber';
        default: return 'badge-cmd-slate';
    }
}

$retirementInfo = calculateRetirementInfo($staff->DOB ?? null, $staff->attestDate ?? null);

// ==================== DEFAULT ACTIVE TAB ====================
// Land on the first tab that actually has records rather than always
// defaulting to Promotions, which is frequently empty for junior staff.
$tabRecordCounts = [
    'promotions'   => $promotionCount,
    'postings'     => $postingCount,
    'courses'      => $courseCount,
    'skills'       => $skillCount,
    'operations'   => $operationCount,
    'deployments'  => $deploymentCount,
    'awards'       => $awardCount,
    'disciplinary' => $disciplinaryCount,
];
$defaultActiveTab = 'promotions';
foreach ($tabRecordCounts as $tabKey => $tabCount) {
    if ($tabCount > 0) {
        $defaultActiveTab = $tabKey;
        break;
    }
}
function accordionButtonClass($tabKey, $defaultActiveTab) {
    return 'accordion-button' . ($tabKey === $defaultActiveTab ? '' : ' collapsed');
}
function accordionCollapseClass($tabKey, $defaultActiveTab) {
    return 'accordion-collapse collapse' . ($tabKey === $defaultActiveTab ? ' show' : '');
}
function accordionExpandedAttr($tabKey, $defaultActiveTab) {
    return $tabKey === $defaultActiveTab ? 'true' : 'false';
}
function tabCountBadgeClass($count) {
    // Ghost/outline treatment for empty sections so the eye is drawn to the
    // ones that actually carry records, instead of a wall of identical badges.
    return $count > 0 ? 'badge-cmd-navy' : 'badge-cmd-ghost';
}

$bodyClass = (defined('ARMIS_ADMIN_BRANCH') && ARMIS_ADMIN_BRANCH) ? 'admin-access' : '';

include dirname(__DIR__) . '/shared/header.php';
include dirname(__DIR__) . '/shared/sidebar.php';
?>

<style>
    :root {
        /* Command-aesthetic palette shared with ARMIS/IncidentOps: navy, crimson, amber, cipher green */
        --profile-accent: #1a3a6b;
        --profile-accent-light: #eef2f8;
        --cmd-navy: #1a3a6b;
        --cmd-navy-light: #4a6da3;
        --cmd-crimson: #8c1d2b;
        --cmd-crimson-light: #c0394a;
        --cmd-amber: #b5790a;
        --cmd-amber-light: #f0a83c;
        --cmd-green: #1e6b4f;
        --cmd-green-light: #2f9c73;
        --cmd-slate: #5a6472;
    }

    /* ---------- Breadcrumb ---------- */
    .profile-breadcrumb {
        font-size: 0.85rem;
        margin-bottom: 0.85rem;
    }
    .profile-breadcrumb a { color: var(--cmd-navy); text-decoration: none; }
    .profile-breadcrumb a:hover { text-decoration: underline; }
    .profile-breadcrumb .breadcrumb-item + .breadcrumb-item::before { content: "›"; color: #adb5bd; }
    .profile-breadcrumb .breadcrumb-item.active { color: #6c757d; }

    /* ---------- Rank chip ---------- */
    .rank-chip {
        display: inline-flex;
        align-items: center;
        gap: 0.4rem;
        background: rgba(255,255,255,0.16);
        border: 1px solid rgba(255,255,255,0.35);
        color: #fff;
        font-weight: 700;
        font-size: 0.78rem;
        letter-spacing: 0.04em;
        padding: 0.2rem 0.6rem;
        border-radius: 1rem;
        text-transform: uppercase;
        vertical-align: middle;
    }
    .rank-chip i { font-size: 0.85em; }

    .svc-copy-btn {
        background: none;
        border: none;
        color: rgba(255,255,255,0.85);
        padding: 0 0.15rem;
        cursor: pointer;
        font-size: 0.8rem;
    }
    .svc-copy-btn:hover { color: #fff; }
    .svc-copy-btn.copied::after {
        content: "Copied!";
        margin-left: 0.35rem;
        font-size: 0.75rem;
        font-weight: 600;
    }

    .profile-photo {
        width: 160px;
        height: 160px;
        object-fit: cover;
        border: 4px solid #fff;
        border-radius: 0.75rem;
        box-shadow: 0 2px 10px rgba(0,0,0,0.12);
    }
    .profile-hero {
        background: linear-gradient(135deg, var(--profile-accent) 0%, #4a6da3 100%);
        color: #fff;
        border-radius: 0.75rem 0.75rem 0 0;
        padding: 1.75rem 1.5rem;
    }
    .profile-hero .svc-badge {
        font-size: 0.85rem;
        letter-spacing: 0.03em;
        opacity: 0.85;
    }
    .profile-hero h2 {
        margin: 0.15rem 0 0.35rem;
        font-weight: 600;
    }
    .profile-hero .subline {
        opacity: 0.9;
        font-size: 0.95rem;
    }
    .quick-stats-panel {
        border-radius: 0.6rem;
        box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        margin: -1.75rem 1.5rem 0.35rem;
        overflow: hidden;
        /* The grid lines below are drawn using the gap trick (background color
           showing through 1px gaps), so the panel itself provides that color. */
        background: #eef0f3;
    }
    .quick-stats-grid {
        display: grid;
        /* auto-fit sizes columns off the panel's own rendered width, so this
           reliably forms multiple columns whenever there's room - unlike a
           viewport-based breakpoint, which ignores the sidebar eating into
           the actual available space. Items fill left-to-right, then wrap
           to the next row (grid's default row-major auto-flow). */
        grid-template-columns: repeat(auto-fit, minmax(230px, 1fr));
        gap: 1px;
    }
    .quick-stat-row {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 0.75rem;
        width: 100%;
        padding: 0.65rem 1.1rem;
        border: none;
        background: #fff;
        text-align: left;
        font-family: inherit;
    }
    .quick-stat-row--link {
        cursor: pointer;
        transition: background-color 0.15s ease;
    }
    .quick-stat-row--link:hover,
    .quick-stat-row--link:focus-visible {
        background-color: var(--profile-accent-light);
        outline: none;
    }
    .quick-stat-row--link:focus-visible {
        box-shadow: inset 0 0 0 2px rgba(44,74,124,0.35);
    }
    .quick-stat-row .qs-label {
        display: flex;
        align-items: center;
        gap: 0.55rem;
        font-size: 0.87rem;
        color: #495057;
    }
    .quick-stat-row .qs-label i {
        color: var(--profile-accent);
        width: 1.1em;
        text-align: center;
        flex-shrink: 0;
    }
    .quick-stat-row .qs-count {
        font-weight: 700;
        font-size: 0.95rem;
        color: var(--profile-accent);
        flex-shrink: 0;
    }
    .quick-stat-row--warning .qs-count { color: var(--cmd-crimson); }
    .titles-badge {
        font-size: 0.65em;
        vertical-align: middle;
        font-weight: 500;
        display: inline-block;
        margin-top: 0.25rem;
    }
    @media (max-width: 576px) {
        .titles-badge { display: block; width: fit-content; margin-top: 0.35rem; }
    }

    /* ---------- Sensitive field reveal ---------- */
    .sensitive-field {
        font-variant-numeric: tabular-nums;
        letter-spacing: 0.02em;
    }
    .btn-reveal {
        background: none;
        border: none;
        color: var(--cmd-navy);
        opacity: 0.55;
        padding: 0 0.25rem;
        cursor: pointer;
        font-size: 0.85rem;
    }
    .btn-reveal:hover, .btn-reveal:focus-visible { opacity: 1; outline: none; }

    /* ---------- Show more / less toggle for long text ---------- */
    .truncated-text .full-text { display: none; }
    .truncated-text.expanded .full-text { display: inline; }
    .truncated-text.expanded .short-text { display: none; }
    .show-more-btn {
        background: none;
        border: none;
        color: var(--cmd-navy);
        padding: 0;
        margin-left: 0.25rem;
        font-size: 0.78rem;
        font-weight: 600;
        cursor: pointer;
        text-decoration: underline;
    }

    /* ---------- Per-tab search + sortable columns ---------- */
    .tab-toolbar {
        display: flex;
        justify-content: flex-end;
        margin-bottom: 0.6rem;
    }
    .tab-toolbar .table-filter-input {
        max-width: 260px;
        font-size: 0.85rem;
    }
    th.sortable { cursor: pointer; user-select: none; white-space: nowrap; }
    th.sortable:hover { background-color: #eef2f8; }
    th.sortable .sort-arrow { opacity: 0.35; font-size: 0.75em; margin-left: 0.3em; }
    th.sortable.sort-asc .sort-arrow, th.sortable.sort-desc .sort-arrow { opacity: 1; }

    .section-header {
        background: var(--profile-accent-light);
        color: var(--profile-accent);
        padding: 0.85rem 1.25rem;
        border-radius: 0.5rem 0.5rem 0 0;
        border-bottom: 1px solid rgba(44,74,124,0.15);
    }
    .section-header h4 { margin: 0; font-size: 1.05rem; font-weight: 600; }
    .info-table th {
        width: 38%;
        background-color: #f8f9fa;
        font-weight: 600;
        font-size: 0.88rem;
        color: #495057;
    }
    .info-table td { font-size: 0.9rem; }

    /* ---------- Lighter definition-list layout for contact/NOK details ---------- */
    .def-list dt {
        font-weight: 600;
        font-size: 0.85rem;
        color: #495057;
        padding: 0.4rem 0;
        border-bottom: 1px solid #f1f3f5;
    }
    .def-list dd {
        font-size: 0.9rem;
        padding: 0.4rem 0;
        border-bottom: 1px solid #f1f3f5;
        margin-bottom: 0;
    }
    /* ---------- Records accordion ---------- */
    .accordion-item { border-color: #e9ecef; }
    .accordion-button {
        font-size: 0.95rem;
        font-weight: 600;
        color: #343a40;
        background: #fff;
    }
    .accordion-button:not(.collapsed) {
        color: var(--profile-accent);
        background: var(--profile-accent-light);
        box-shadow: none;
    }
    .accordion-button:focus { box-shadow: 0 0 0 2px rgba(26,58,107,0.25); }
    .accordion-button::after { margin-left: 0.6rem; }
    .accordion-button .badge { font-size: 0.75rem; }
    .accordion-body { padding-top: 1rem; }
    .empty-state {
        text-align: center;
        padding: 2.5rem 1rem;
        color: #8a94a6;
    }
    .empty-state i { font-size: 2rem; margin-bottom: 0.5rem; display: block; opacity: 0.6; }
    .remarks-cell { max-width: 260px; }
    .table-responsive table { font-size: 0.88rem; }
    /* ---------- Command-palette badges (replaces default Bootstrap bg-* for suite consistency) ---------- */
    .badge-cmd-navy   { background-color: var(--cmd-navy);   color: #fff; }
    .badge-cmd-crimson{ background-color: var(--cmd-crimson);color: #fff; }
    .badge-cmd-amber  { background-color: var(--cmd-amber);  color: #fff; }
    .badge-cmd-green  { background-color: var(--cmd-green);  color: #fff; }
    .badge-cmd-slate  { background-color: var(--cmd-slate);  color: #fff; }
    .badge-cmd-ghost {
        background-color: transparent;
        color: #adb5bd;
        border: 1px solid #dee2e6;
        font-weight: 500;
    }
    /* De-emphasize sections with no records so the eye lands on the ones
       that actually carry data (e.g. Courses) instead of a wall of
       identical accordion headers. */
    .accordion-button.collapsed:has(.badge-cmd-ghost),
    .accordion-button.section-empty.collapsed {
        color: #8a94a6;
        font-weight: 500;
    }
    .quick-stat-row--empty .qs-label,
    .quick-stat-row--empty .qs-count {
        color: #adb5bd;
    }
    .last-updated-meta {
        font-size: 0.78rem;
        color: #8a94a6;
        text-align: right;
        margin-top: -0.5rem;
        margin-bottom: 1rem;
    }
    .print-section-title {
        display: none;
    }
    /* ---------- Print header (only visible when printing) ---------- */
    .print-header {
        display: none;
    }

    @media print {
        /* ===== A4 page box with real margins (portrait by default) ===== */
        @page {
            size: A4 portrait;
            margin: 16mm 14mm;
        }

        html, body { background: #fff !important; }
        .no-print { display: none !important; }

        /* The screen layout reserves space for a fixed sidebar and adds its
           own page padding; both waste the @page margin we just set and can
           push content off the printable area, so they're zeroed here. */
        .content-wrapper.with-sidebar { margin-left: 0 !important; width: 100% !important; }
        .container-fluid.p-4 { padding: 0 !important; }

        /* ===== Print header (repeats visually as the top of the printed
           document; browsers don't support true repeating page headers via
           CSS alone - see recommendation notes) ===== */
        .print-header {
            display: flex !important;
            justify-content: space-between;
            align-items: baseline;
            border-bottom: 2px solid #1a3a6b;
            padding-bottom: 0.4rem;
            margin-bottom: 0.9rem;
            font-size: 0.8rem;
            color: #1a3a6b;
        }
        .print-header strong { font-size: 0.95rem; }

        /* ===== Ink-safe hero: gradients/white-on-color depend on the
           browser's "background graphics" print option, which is off by
           default - so print gets a plain bordered header instead ===== */
        .profile-hero {
            background: #fff !important;
            color: #1a3a6b !important;
            border: 1px solid #1a3a6b;
            border-radius: 0.4rem 0.4rem 0 0;
            padding: 0.9rem 1rem !important;
        }
        .profile-hero .svc-badge, .profile-hero .subline { opacity: 1 !important; color: #495057 !important; }
        .rank-chip { background: #eef2f8 !important; border: 1px solid #1a3a6b; color: #1a3a6b !important; }
        .svc-copy-btn { display: none !important; }

        /* ===== Ink-safe badges: outline instead of solid fill so status
           still reads clearly without relying on background-graphics ===== */
        .badge {
            background: none !important;
            border: 1px solid #495057 !important;
            color: #212529 !important;
            font-weight: 600;
        }

        .card { break-inside: avoid; box-shadow: none !important; border: 1px solid #dee2e6 !important; }
        .quick-stats-panel {
            margin: 0.6rem 0 0.4rem 0 !important;
            box-shadow: none;
            border: 1px solid #dee2e6;
        }
        /* Accordion headers are interactive chrome (toggle chevron, expand
           state) that means nothing on paper - the .print-section-title
           inside each body already labels the section, so the header
           button is hidden and every section is forced open regardless
           of what was expanded/collapsed on screen. */
        .accordion-header { display: none !important; }
        .accordion-collapse { display: block !important; height: auto !important; visibility: visible !important; }
        .accordion-item { border: none !important; break-inside: avoid; }
        .accordion-item:not(:last-child) {
            margin-bottom: 1.1rem;
            padding-bottom: 0.85rem;
            border-bottom: 1px dashed #ccc !important;
        }
        .accordion-body { padding: 0 !important; }
        .print-section-title {
            display: block !important;
            font-weight: 700;
            font-size: 1rem;
            color: #1a3a6b !important;
            border-bottom: 2px solid #1a3a6b;
            padding-bottom: 0.25rem;
            margin-bottom: 0.65rem;
        }

        /* ===== Tables: fit the A4 printable width instead of scrolling ===== */
        .table-responsive { overflow: visible !important; }
        .table-responsive table { font-size: 0.72rem; width: 100% !important; }
        table thead { display: table-header-group; }  /* repeat column headers on each printed page */
        tr, .empty-state { break-inside: avoid; }
        .remarks-cell { max-width: none; }
        .tab-toolbar, .show-more-btn, .btn-reveal { display: none !important; }
        .truncated-text .full-text { display: inline !important; }  /* print full text, never the truncated form */
        .truncated-text .short-text { display: none !important; }
        .sensitive-field::before { content: attr(data-full); }      /* print full values, not the masked placeholder */
        .sensitive-field { font-size: 0; }
        .sensitive-field::before { font-size: 0.72rem; }

        .def-list dt, .def-list dd { border-bottom: 1px solid #eee; font-size: 0.82rem; }
    }
</style>

<div class="content-wrapper with-sidebar">
    <div class="container-fluid p-4">

        <div class="print-header">
            <span><strong>ARMIS &middot; Personnel Profile</strong> &mdash; Admin Branch</span>
            <span>
                <?=htmlspecialchars(trim(($staff->rankAbbr ?? '') . ' ' . $staff->fName . ' ' . $staff->lName))?>
                &middot; Svc No. <?=htmlspecialchars($staff->svcNo ?? 'N/A')?>
                &middot; Printed <?=date('d M Y')?>
            </span>
        </div>

        <nav class="profile-breadcrumb no-print" aria-label="breadcrumb">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a href="/Armis2/admin_branch/index.php">Admin Branch</a></li>
                <li class="breadcrumb-item"><a href="edit_staff.php">Staff Management</a></li>
                <li class="breadcrumb-item active" aria-current="page">
                    <?=htmlspecialchars(trim(($staff->rankAbbr ?? '') . ' ' . $staff->fName . ' ' . $staff->lName))?>
                </li>
            </ol>
        </nav>

        <div class="d-flex justify-content-between align-items-center mb-4 no-print">
            <h1 class="mb-0"><i class="fa fa-user"></i> Personnel Profile</h1>
            <div>
                <div class="btn-group me-2" role="group" aria-label="Print options">
                    <button onclick="printProfile('portrait')" class="btn btn-outline-secondary">
                        <i class="fa fa-print"></i> Print
                    </button>
                    <button onclick="printProfile('landscape')" class="btn btn-outline-secondary" title="Best for wide record tables like Courses or Postings">
                        <i class="fa fa-arrows-alt-h"></i> Print Landscape
                    </button>
                </div>
                <?php if (defined('ARMIS_ADMIN_BRANCH') && ARMIS_ADMIN_BRANCH): ?>
                    <a href="edit_staff.php?svcNo=<?=urlencode($staff->svcNo)?>" class="btn btn-warning">
                        <i class="fa fa-edit"></i> Edit Profile
                    </a>
                <?php endif; ?>
            </div>
        </div>

        <?php if (isset($_GET['msg'])): ?>
            <div class="alert alert-success alert-dismissible fade show no-print">
                <?=htmlspecialchars($_GET['msg'])?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if (isset($_GET['error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show no-print">
                <?=htmlspecialchars($_GET['error'])?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php
            // Only renders if these audit columns exist on the staff record - safe no-op otherwise.
            $lastUpdatedAt = $staff->updated_at ?? $staff->lastModified ?? $staff->modifiedDate ?? null;
            $lastUpdatedBy = $staff->updated_by ?? $staff->modifiedBy ?? null;
        ?>
        <?php if (!empty($lastUpdatedAt)): ?>
            <div class="last-updated-meta no-print">
                <i class="fa fa-history"></i> Last updated <?=date('d M Y, H:i', strtotime($lastUpdatedAt))?><?=!empty($lastUpdatedBy) ? ' by ' . htmlspecialchars($lastUpdatedBy) : ''?>
            </div>
        <?php endif; ?>

        <!-- ==================== PROFILE HERO ==================== -->
        <div class="card mb-4 shadow-sm border-0">
            <div class="profile-hero">
                <div class="row align-items-center">
                    <div class="col-auto text-center">
                        <?php
                            $staffFullName = trim($staff->fName . ' ' . (!empty($staff->mName) ? $staff->mName . ' ' : '') . $staff->lName);
                            // Single source of truth for photo resolution, shared with users/index.php -
                            // checks uploads/profile_photos/{svcNo}.{ext} on disk first (the real,
                            // established convention), falls back to the staff.profilePhoto DB column,
                            // then to the server-generated default_avatar.php if nothing is found.
                            $photoSrc = $profileManager->getProfilePhotoURL();
                            // Cache-bust with the last profile update time so a freshly replaced
                            // photo doesn't keep showing a browser-cached older image. Appended with
                            // '&' when the resolved URL already has a query string (default_avatar.php
                            // uses ?name=...), '?' otherwise.
                            $photoCacheBust = $staff->lastProfileUpdate ?? $staff->updatedAt ?? null;
                            if (!empty($photoCacheBust)) {
                                $photoSrc .= (strpos($photoSrc, '?') !== false ? '&' : '?') . 'v=' . urlencode(strtotime($photoCacheBust));
                            }
                        ?>
                        <img src="<?=htmlspecialchars($photoSrc)?>"
                             class="profile-photo" alt="Profile photo of <?=htmlspecialchars($staffFullName)?>"
                             onerror="handleImageError(this, '/Armis2/shared/default_avatar.php?name=<?=urlencode($staff->svcNo ?? '')?>')">
                    </div>
                    <div class="col">
                        <div class="svc-badge">
                            <i class="fa fa-id-badge"></i> SERVICE NO. <?=htmlspecialchars($staff->svcNo ?? 'N/A')?>
                            <button type="button" class="svc-copy-btn" id="copySvcNoBtn" data-svcno="<?=htmlspecialchars($staff->svcNo ?? '')?>" title="Copy service number" aria-label="Copy service number to clipboard">
                                <i class="fa fa-copy"></i>
                            </button>
                        </div>
                        <h2>
                            <?php if (!empty($staff->rankAbbr)): ?>
                                <span class="rank-chip"><i class="fa fa-shield-alt"></i> <?=htmlspecialchars($staff->rankAbbr)?></span>
                            <?php endif; ?>
                            <?=htmlspecialchars($staffFullName)?>
                            <?php if (!empty($staff->titles)): ?>
                                <span class="badge bg-light text-dark align-middle titles-badge"><?=htmlspecialchars($staff->titles)?></span>
                            <?php endif; ?>
                        </h2>
                        <div class="subline">
                            <?=htmlspecialchars($currentAppointment->apptType ?? $staff->corpsName ?? 'N/A')?>
                            &middot; <?=htmlspecialchars($staff->unitName ?? 'Unassigned')?><?=!empty($staff->unitLocation) ? ' (' . htmlspecialchars($staff->unitLocation) . ')' : ''?>
                        </div>
                        <div class="mt-2">
                            <span class="badge <?=statusBadgeClass($staff->svcStatus)?>"><?=htmlspecialchars($staff->svcStatus ?? 'N/A')?></span>
                            <?php if (!empty($staff->trade)): ?>
                                <span class="badge bg-light text-dark ms-1"><?=htmlspecialchars($staff->trade)?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            <div class="quick-stats-panel">
                <div class="quick-stats-grid">
                    <div class="quick-stat-row" title="Total years of service">
                        <span class="qs-label"><i class="fa fa-hourglass-half"></i> Years of Service</span>
                        <span class="qs-count"><?=$yearsOfService?></span>
                    </div>
                    <button type="button" class="quick-stat-row quick-stat-row--link" data-target-tab="collapse-promotions" aria-label="View promotions, <?=$promotionCount?> record<?=$promotionCount === 1 ? '' : 's'?>">
                        <span class="qs-label"><i class="fa fa-arrow-up"></i> Promotions</span>
                        <span class="qs-count"><?=$promotionCount?></span>
                    </button>
                    <button type="button" class="quick-stat-row quick-stat-row--link" data-target-tab="collapse-postings" aria-label="View postings, <?=$postingCount?> record<?=$postingCount === 1 ? '' : 's'?>">
                        <span class="qs-label"><i class="fa fa-map-marker-alt"></i> Postings</span>
                        <span class="qs-count"><?=$postingCount?></span>
                    </button>
                    <button type="button" class="quick-stat-row quick-stat-row--link" data-target-tab="collapse-courses" aria-label="View courses, <?=$courseCount?> record<?=$courseCount === 1 ? '' : 's'?>">
                        <span class="qs-label"><i class="fa fa-graduation-cap"></i> Courses</span>
                        <span class="qs-count"><?=$courseCount?></span>
                    </button>
                    <button type="button" class="quick-stat-row quick-stat-row--link" data-target-tab="collapse-skills" aria-label="View skills and training, <?=$skillCount?> record<?=$skillCount === 1 ? '' : 's'?>">
                        <span class="qs-label"><i class="fa fa-cogs"></i> Skills &amp; Training</span>
                        <span class="qs-count"><?=$skillCount?></span>
                    </button>
                    <button type="button" class="quick-stat-row quick-stat-row--link" data-target-tab="collapse-operations" aria-label="View operations, <?=$operationCount?> record<?=$operationCount === 1 ? '' : 's'?>">
                        <span class="qs-label"><i class="fa fa-crosshairs"></i> Operations</span>
                        <span class="qs-count"><?=$operationCount?></span>
                    </button>
                    <button type="button" class="quick-stat-row quick-stat-row--link" data-target-tab="collapse-deployments" aria-label="View deployments, <?=$deploymentCount?> record<?=$deploymentCount === 1 ? '' : 's'?>">
                        <span class="qs-label"><i class="fa fa-globe"></i> Deployments</span>
                        <span class="qs-count"><?=$deploymentCount?></span>
                    </button>
                    <button type="button" class="quick-stat-row quick-stat-row--link" data-target-tab="collapse-awards" aria-label="View honours and awards, <?=$awardCount?> record<?=$awardCount === 1 ? '' : 's'?>">
                        <span class="qs-label"><i class="fa fa-medal"></i> Honours &amp; Awards</span>
                        <span class="qs-count"><?=$awardCount?></span>
                    </button>
                    <button type="button" class="quick-stat-row quick-stat-row--link<?=$disciplinaryCount > 0 ? ' quick-stat-row--warning' : ''?>" data-target-tab="collapse-disciplinary" aria-label="View disciplinary records, <?=$disciplinaryCount?> record<?=$disciplinaryCount === 1 ? '' : 's'?>">
                        <span class="qs-label"><i class="fa fa-exclamation-triangle"></i> Disciplinary</span>
                        <span class="qs-count"><?=$disciplinaryCount?></span>
                    </button>
                </div>
            </div>
            <div class="card-body pt-2">
                <div class="row">
                    <div class="col-lg-6">
                        <table class="table table-bordered table-sm info-table mb-lg-0">
                            <tbody>
                                <tr>
                                    <th scope="row"><i class="fa fa-shield-alt text-primary"></i> Corps</th>
                                    <td><?=displayValue($staff->corpsName ?? null)?></td>
                                </tr>
                                <tr>
                                    <th scope="row"><i class="fa fa-id-card text-primary"></i> NRC</th>
                                    <td><?=maskedField($staff->NRC ?? null)?></td>
                                </tr>
                                <tr>
                                    <th scope="row"><i class="fa fa-tools text-primary"></i> Trade</th>
                                    <td><?=displayValue($staff->trade ?? null)?></td>
                                </tr>
                                <tr>
                                    <th scope="row"><i class="fa fa-briefcase text-primary"></i> Profession</th>
                                    <td><?=displayValue($staff->profession ?? null)?></td>
                                </tr>
                                <tr>
                                    <th scope="row"><i class="fa fa-venus-mars text-primary"></i> Gender</th>
                                    <td><?=displayValue($staff->gender ?? null)?></td>
                                </tr>
                                <tr>
                                    <th scope="row"><i class="fa fa-ring text-primary"></i> Marital Status</th>
                                    <td><?=displayValue($staff->marital ?? null)?></td>
                                </tr>
                                <tr>
                                    <th scope="row"><i class="fa fa-pray text-primary"></i> Religion</th>
                                    <td><?=displayValue($staff->religion ?? null)?></td>
                                </tr>
                                <tr>
                                    <th scope="row"><i class="fa fa-tint text-primary"></i> Blood Group</th>
                                    <td><?=displayValue($staff->bloodGp ?? null)?></td>
                                </tr>
                                <tr>
                                    <th scope="row"><i class="fa fa-ruler-vertical text-primary"></i> Height</th>
                                    <td><?=displayValue($staff->height ?? null, !empty($staff->height) ? ' cm' : '')?></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="col-lg-6">
                        <table class="table table-bordered table-sm info-table mb-0">
                            <tbody>
                                <tr>
                                    <th scope="row"><i class="fa fa-calendar text-primary"></i> Date of Birth</th>
                                    <td>
                                        <?=!empty($staff->DOB) ? date('d M Y', strtotime($staff->DOB)) : '<span class="text-muted fst-italic">N/A</span>'?>
                                        <?php if (!empty($staff->age)): ?>
                                            <small class="text-muted">(<?=$staff->age?> years old)</small>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><i class="fa fa-calendar-check text-primary"></i> Date of Enlistment</th>
                                    <td><?=!empty($staff->attestDate) ? date('d M Y', strtotime($staff->attestDate)) : '<span class="text-muted fst-italic">N/A</span>'?></td>
                                </tr>
                                <tr>
                                    <th scope="row"><i class="fa fa-hourglass-end text-primary"></i> Expected Runout (Age 60)</th>
                                    <td>
                                        <?php if ($retirementInfo['runout']): ?>
                                            <?php $runout = $retirementInfo['runout']; $urgencyClass = getRetirementUrgencyClass($runout['yearsRemaining']); ?>
                                            <strong><?=$runout['formatted']?></strong>
                                            <br><small class="<?=$urgencyClass?>"><i class="fa fa-clock"></i> <?=$runout['remaining']?> remaining</small>
                                        <?php else: ?>
                                            <span class="text-muted">N/A (DOB not recorded)</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><i class="fa fa-calendar-alt text-primary"></i> Early Retirement (20 Yrs)</th>
                                    <td>
                                        <?php if ($retirementInfo['earlyRetirement']): ?>
                                            <?php $earlyRet = $retirementInfo['earlyRetirement']; $urgencyClass = getRetirementUrgencyClass($earlyRet['yearsRemaining']); ?>
                                            <strong><?=$earlyRet['formatted']?></strong>
                                            <br><small class="<?=$urgencyClass?>"><i class="fa fa-clock"></i> <?=$earlyRet['remaining']?> <?=$earlyRet['isPast'] ? '' : 'remaining'?></small>
                                        <?php else: ?>
                                            <span class="text-muted">N/A (enlistment date not recorded)</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><i class="fa fa-user-tie text-primary"></i> Current Appointment</th>
                                    <td>
                                        <?php if ($currentAppointment): ?>
                                            <?=htmlspecialchars($currentAppointment->apptType ?? 'N/A')?>
                                            <?php if (empty($currentAppointment->endDate)): ?>
                                                <span class="badge badge-cmd-green ms-1">Current</span>
                                            <?php endif; ?>
                                            <br><small class="text-muted">since <?=!empty($currentAppointment->apptWef) ? date('d M Y', strtotime($currentAppointment->apptWef)) : 'N/A'?></small>
                                        <?php else: ?>
                                            <span class="text-muted">No posting on record</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- ==================== CONTACT INFORMATION CARD ==================== -->
        <div class="card mb-4 shadow-sm">
            <div class="section-header">
                <h4><i class="fa fa-address-book"></i> Contact Information</h4>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <dl class="row def-list mb-0">
                            <dt class="col-5 col-lg-4"><i class="fa fa-phone text-success"></i> Telephone</dt>
                            <dd class="col-7 col-lg-8"><?=maskedField($staff->telNo ?? null, 'phone')?></dd>

                            <dt class="col-5 col-lg-4"><i class="fa fa-envelope text-success"></i> Official Email</dt>
                            <dd class="col-7 col-lg-8"><?=displayValue($staff->officialEmail ?? null)?></dd>

                            <dt class="col-5 col-lg-4"><i class="fa fa-envelope-open text-success"></i> Personal Email</dt>
                            <dd class="col-7 col-lg-8"><?=maskedField($staff->emailPvt ?? null, 'email')?></dd>

                            <dt class="col-5 col-lg-4"><i class="fa fa-map-marker-alt text-success"></i> Province</dt>
                            <dd class="col-7 col-lg-8"><?=displayValue($staff->province ?? null)?></dd>

                            <dt class="col-5 col-lg-4"><i class="fa fa-map-marked text-success"></i> District</dt>
                            <dd class="col-7 col-lg-8"><?=displayValue($staff->district ?? null)?></dd>

                            <dt class="col-5 col-lg-4"><i class="fa fa-home text-success"></i> Village</dt>
                            <dd class="col-7 col-lg-8"><?=displayValue($staff->village ?? null)?></dd>

                            <dt class="col-5 col-lg-4"><i class="fa fa-map text-success"></i> Address</dt>
                            <dd class="col-7 col-lg-8"><?=displayValue($staff->address ?? null)?></dd>
                        </dl>
                    </div>
                    <div class="col-md-6">
                        <h5 class="text-danger"><i class="fa fa-user-shield"></i> Next of Kin (Primary)</h5>
                        <dl class="row def-list mb-0">
                            <dt class="col-5 col-lg-4"><i class="fa fa-user text-danger"></i> Name</dt>
                            <dd class="col-7 col-lg-8"><?=displayValue($staff->nok ?? null)?></dd>

                            <dt class="col-5 col-lg-4"><i class="fa fa-id-card text-danger"></i> NRC</dt>
                            <dd class="col-7 col-lg-8"><?=maskedField($staff->nokNrc ?? null)?></dd>

                            <dt class="col-5 col-lg-4"><i class="fa fa-phone text-danger"></i> Telephone</dt>
                            <dd class="col-7 col-lg-8"><?=maskedField($staff->nokTel ?? null, 'phone')?></dd>

                            <dt class="col-5 col-lg-4"><i class="fa fa-users text-danger"></i> Relationship</dt>
                            <dd class="col-7 col-lg-8"><?=displayValue($staff->nokRelat ?? null)?></dd>
                        </dl>

                        <?php if (!empty($staff->altNok)): ?>
                            <h5 class="text-warning mt-3"><i class="fa fa-user-shield"></i> Next of Kin (Alternative)</h5>
                            <dl class="row def-list mb-0">
                                <dt class="col-5 col-lg-4"><i class="fa fa-user text-warning"></i> Name</dt>
                                <dd class="col-7 col-lg-8"><?=displayValue($staff->altNok ?? null)?></dd>

                                <dt class="col-5 col-lg-4"><i class="fa fa-phone text-warning"></i> Telephone</dt>
                                <dd class="col-7 col-lg-8"><?=maskedField($staff->altNokTel ?? null, 'phone')?></dd>

                                <dt class="col-5 col-lg-4"><i class="fa fa-users text-warning"></i> Relationship</dt>
                                <dd class="col-7 col-lg-8"><?=displayValue($staff->altNokRelat ?? null)?></dd>
                            </dl>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- ==================== RECORDS ACCORDION ==================== -->
        <div class="card shadow-sm" id="profileTabsCard">
            <div class="card-body p-0">
                <div class="accordion accordion-flush" id="profileAccordion">
                    <div class="accordion-item">
                        <h2 class="accordion-header" id="heading-promotions">
                            <button class="<?=accordionButtonClass('promotions', $defaultActiveTab)?>" type="button" data-bs-toggle="collapse" data-bs-target="#collapse-promotions" aria-expanded="<?=accordionExpandedAttr('promotions', $defaultActiveTab)?>" aria-controls="collapse-promotions">
                                <i class="fa fa-arrow-up me-2"></i> Promotions
                                <span class="badge <?=tabCountBadgeClass($promotionCount)?> ms-2"><?=$promotionCount?></span>
                            </button>
                        </h2>
                        <div id="collapse-promotions" class="<?=accordionCollapseClass('promotions', $defaultActiveTab)?>" aria-labelledby="heading-promotions">
                            <div class="accordion-body">
                        <h5 class="print-section-title">Promotions &amp; Reversions</h5>
                        <?php if (!empty($promotions)): ?>
                            <div class="tab-toolbar no-print">
                                <input type="search" class="form-control form-control-sm table-filter-input" data-filter-target="#promotionsTable" placeholder="Search promotions..." aria-label="Search promotions">
                            </div>
                            <div class="table-responsive">
                                <table class="table table-hover table-bordered align-middle" id="promotionsTable">
                                    <thead class="table-light">
                                        <tr>
                                            <th>#</th><th class="sortable" data-sort-type="text">From Rank<i class="fa fa-sort sort-arrow"></i></th><th class="sortable" data-sort-type="text">To Rank<i class="fa fa-sort sort-arrow"></i></th><th class="sortable" data-sort-type="text">Type<i class="fa fa-sort sort-arrow"></i></th>
                                            <th class="sortable" data-sort-type="date">Effective Date<i class="fa fa-sort sort-arrow"></i></th><th class="sortable" data-sort-type="number">Days in Rank<i class="fa fa-sort sort-arrow"></i></th><th>Authority</th><th>Remark</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($promotions as $idx => $prom): ?>
                                            <tr>
                                                <td><?=$idx + 1?></td>
                                                <td><?=htmlspecialchars($prom->previousRankName ?? 'N/A')?></td>
                                                <td><strong><?=htmlspecialchars($prom->newRankName ?? 'N/A')?></strong></td>
                                                <td>
                                                    <span class="badge <?=strtolower($prom->type ?? '') === 'demotion' ? 'badge-cmd-crimson' : 'badge-cmd-green'?>">
                                                        <?=htmlspecialchars(ucfirst($prom->type ?? 'N/A'))?>
                                                    </span>
                                                </td>
                                                <td><?=!empty($prom->wefDate) ? date('d M Y', strtotime($prom->wefDate)) : 'N/A'?></td>
                                                <td><?=number_format($prom->days_in_rank ?? 0)?> days</td>
                                                <td><small><?=htmlspecialchars($prom->authorityDescription ?? $prom->authID ?? 'N/A')?></small></td>
                                                <td class="remarks-cell"><small><?=htmlspecialchars($prom->remark ?? '')?></small></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="empty-state"><i class="fa fa-arrow-up"></i>No promotion records found.</div>
                        <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <div class="accordion-item">
                        <h2 class="accordion-header" id="heading-postings">
                            <button class="<?=accordionButtonClass('postings', $defaultActiveTab)?>" type="button" data-bs-toggle="collapse" data-bs-target="#collapse-postings" aria-expanded="<?=accordionExpandedAttr('postings', $defaultActiveTab)?>" aria-controls="collapse-postings">
                                <i class="fa fa-map-marker-alt me-2"></i> Postings
                                <span class="badge <?=tabCountBadgeClass($postingCount)?> ms-2"><?=$postingCount?></span>
                            </button>
                        </h2>
                        <div id="collapse-postings" class="<?=accordionCollapseClass('postings', $defaultActiveTab)?>" aria-labelledby="heading-postings">
                            <div class="accordion-body">
                        <h5 class="print-section-title">Postings &amp; Appointments</h5>
                        <?php if (!empty($postings)): ?>
                            <div class="tab-toolbar no-print">
                                <input type="search" class="form-control form-control-sm table-filter-input" data-filter-target="#postingsTable" placeholder="Search postings..." aria-label="Search postings">
                            </div>
                            <div class="table-responsive">
                                <table class="table table-hover table-bordered align-middle" id="postingsTable">
                                    <thead class="table-light">
                                        <tr>
                                            <th>#</th><th class="sortable" data-sort-type="text">Unit<i class="fa fa-sort sort-arrow"></i></th><th class="sortable" data-sort-type="text">Appointment Type<i class="fa fa-sort sort-arrow"></i></th><th>Powers</th>
                                            <th class="sortable" data-sort-type="date">Start Date<i class="fa fa-sort sort-arrow"></i></th><th class="sortable" data-sort-type="date">End Date<i class="fa fa-sort sort-arrow"></i></th><th>Duration</th><th>Authority</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($postings as $idx => $post): ?>
                                            <?php
                                            $duration = $post->durationMonths ?? null;
                                            if ($duration === null && !empty($post->apptWef)) {
                                                $start = strtotime($post->apptWef);
                                                $end = !empty($post->endDate) ? strtotime($post->endDate) : time();
                                                $duration = floor(($end - $start) / (60 * 60 * 24 * 30.44));
                                            }
                                            ?>
                                            <tr data-group="post-<?=$idx?>">
                                                <td><?=$idx + 1?></td>
                                                <td>
                                                    <strong><?=htmlspecialchars($post->unitId ?? 'N/A')?></strong>
                                                    <?php if (!empty($post->unit_location)): ?>
                                                        <br><small class="text-muted"><?=htmlspecialchars($post->unit_location)?></small>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?=htmlspecialchars($post->apptType ?? 'N/A')?></td>
                                                <td><?=htmlspecialchars($post->powers ?? '-')?></td>
                                                <td><?=!empty($post->apptWef) ? date('d M Y', strtotime($post->apptWef)) : 'N/A'?></td>
                                                <td>
                                                    <?php if (!empty($post->endDate)): ?>
                                                        <?=date('d M Y', strtotime($post->endDate))?>
                                                    <?php else: ?>
                                                        <span class="badge badge-cmd-green">Current</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?=$duration !== null ? $duration . ' mo' : 'N/A'?></td>
                                                <td><small><?=htmlspecialchars($post->authorityDescription ?? $post->authorityId ?? '-')?></small></td>
                                            </tr>
                                            <?php if (!empty($post->remarks)): ?>
                                                <tr class="table-light" data-group="post-<?=$idx?>">
                                                    <td></td>
                                                    <td colspan="7"><small><strong>Remarks:</strong> <?=htmlspecialchars($post->remarks)?></small></td>
                                                </tr>
                                            <?php endif; ?>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="empty-state"><i class="fa fa-map-marker-alt"></i>No posting history found.</div>
                        <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <div class="accordion-item">
                        <h2 class="accordion-header" id="heading-courses">
                            <button class="<?=accordionButtonClass('courses', $defaultActiveTab)?>" type="button" data-bs-toggle="collapse" data-bs-target="#collapse-courses" aria-expanded="<?=accordionExpandedAttr('courses', $defaultActiveTab)?>" aria-controls="collapse-courses">
                                <i class="fa fa-graduation-cap me-2"></i> Courses
                                <span class="badge <?=tabCountBadgeClass($courseCount)?> ms-2"><?=$courseCount?></span>
                            </button>
                        </h2>
                        <div id="collapse-courses" class="<?=accordionCollapseClass('courses', $defaultActiveTab)?>" aria-labelledby="heading-courses">
                            <div class="accordion-body">
                        <h5 class="print-section-title">Courses &amp; Education</h5>
                        <?php if (!empty($courses)): ?>
                            <div class="tab-toolbar no-print">
                                <input type="search" class="form-control form-control-sm table-filter-input" data-filter-target="#coursesTable" placeholder="Search courses..." aria-label="Search courses">
                            </div>
                            <div class="table-responsive">
                                <table class="table table-hover table-bordered align-middle" id="coursesTable">
                                    <thead class="table-light">
                                        <tr>
                                            <th>#</th><th class="sortable" data-sort-type="text">Institution / Course<i class="fa fa-sort sort-arrow"></i></th><th class="sortable" data-sort-type="text">Qualification<i class="fa fa-sort sort-arrow"></i></th>
                                            <th>Type &amp; Level</th><th class="sortable" data-sort-type="date">Start<i class="fa fa-sort sort-arrow"></i></th><th class="sortable" data-sort-type="date">Completed<i class="fa fa-sort sort-arrow"></i></th>
                                            <th>Grade</th><th>Result</th><th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($courses as $idx => $course): ?>
                                            <tr>
                                                <td><?=$idx + 1?></td>
                                                <td>
                                                    <strong><?=htmlspecialchars($course->instId ?? 'N/A')?></strong>
                                                    <?php if (!empty($course->institutionLocation)): ?>
                                                        <small class="text-muted d-block"><?=htmlspecialchars($course->institutionLocation)?></small>
                                                    <?php endif; ?>
                                                    <?php if (!empty($course->cseId)): ?>
                                                        <small class="text-primary d-block"><i class="fa fa-book"></i> <?=htmlspecialchars($course->cseId)?></small>
                                                    <?php endif; ?>
                                                </td>
                                                <td><strong><?=htmlspecialchars($course->qualification ?? 'N/A')?></strong></td>
                                                <td>
                                                    <?php if (!empty($course->courseType)): ?>
                                                        <span class="badge <?=$course->courseType == 'Military' ? 'badge-cmd-navy' : 'badge-cmd-amber'?>"><?=htmlspecialchars($course->courseType)?></span>
                                                    <?php endif; ?>
                                                    <?php if (!empty($course->courseLevel)): ?>
                                                        <br><small class="text-muted"><?=htmlspecialchars($course->courseLevel)?></small>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?=!empty($course->cseStart) ? date('M Y', strtotime($course->cseStart)) : 'N/A'?></td>
                                                <td>
                                                    <?=!empty($course->cseEnd) ? date('M Y', strtotime($course->cseEnd)) : 'N/A'?>
                                                    <?php if ($course->isHighest == 1): ?>
                                                        <br><span class="badge badge-cmd-amber"><i class="fa fa-star"></i> Highest</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?=htmlspecialchars($course->grade ?? 'N/A')?></td>
                                                <td>
                                                    <?php
                                                        $resultText = $course->result ?? 'N/A';
                                                        $resultClass = 'badge-cmd-slate';
                                                        if (stripos($resultText, 'pass') !== false || stripos($resultText, 'distinction') !== false) {
                                                            $resultClass = 'badge-cmd-green';
                                                        } elseif (stripos($resultText, 'fail') !== false) {
                                                            $resultClass = 'badge-cmd-crimson';
                                                        }
                                                    ?>
                                                    <span class="badge <?=$resultClass?>"><?=htmlspecialchars($resultText)?></span>
                                                </td>
                                                <td>
                                                    <?php if (!empty($course->cseEnd)): ?>
                                                        <span class="badge badge-cmd-green">Completed</span>
                                                    <?php else: ?>
                                                        <span class="badge badge-cmd-amber">In Progress</span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="empty-state"><i class="fa fa-graduation-cap"></i>No courses or education records found.</div>
                        <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <div class="accordion-item">
                        <h2 class="accordion-header" id="heading-skills">
                            <button class="<?=accordionButtonClass('skills', $defaultActiveTab)?>" type="button" data-bs-toggle="collapse" data-bs-target="#collapse-skills" aria-expanded="<?=accordionExpandedAttr('skills', $defaultActiveTab)?>" aria-controls="collapse-skills">
                                <i class="fa fa-cogs me-2"></i> Skills &amp; Training
                                <span class="badge <?=tabCountBadgeClass($skillCount)?> ms-2"><?=$skillCount?></span>
                            </button>
                        </h2>
                        <div id="collapse-skills" class="<?=accordionCollapseClass('skills', $defaultActiveTab)?>" aria-labelledby="heading-skills">
                            <div class="accordion-body">
                        <h5 class="print-section-title">Skills &amp; Training</h5>
                        <?php if (!empty($skills)): ?>
                            <div class="tab-toolbar no-print">
                                <input type="search" class="form-control form-control-sm table-filter-input" data-filter-target="#skillsTable" placeholder="Search skills..." aria-label="Search skills">
                            </div>
                            <div class="table-responsive">
                                <table class="table table-hover table-bordered align-middle" id="skillsTable">
                                    <thead class="table-light">
                                        <tr>
                                            <th>#</th><th class="sortable" data-sort-type="text">Course / Skill<i class="fa fa-sort sort-arrow"></i></th><th class="sortable" data-sort-type="text">Type<i class="fa fa-sort sort-arrow"></i></th><th>Institution</th>
                                            <th>Dates</th><th>Grade</th><th>Certificate #</th><th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($skills as $idx => $skill): ?>
                                            <tr>
                                                <td><?=$idx + 1?></td>
                                                <td><strong><?=htmlspecialchars($skill->course_name ?? 'N/A')?></strong></td>
                                                <td><?=htmlspecialchars($skill->course_type ?? 'N/A')?></td>
                                                <td>
                                                    <?=htmlspecialchars($skill->institution ?? 'N/A')?>
                                                    <?php if (!empty($skill->location)): ?>
                                                        <small class="text-muted d-block"><?=htmlspecialchars($skill->location)?></small>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?=!empty($skill->startDate) ? date('d M Y', strtotime($skill->startDate)) : 'N/A'?>
                                                    <?php if (!empty($skill->endDate)): ?>
                                                        &ndash; <?=date('d M Y', strtotime($skill->endDate))?>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?=htmlspecialchars($skill->grade_obtained ?? 'N/A')?></td>
                                                <td><?=htmlspecialchars($skill->certificateNumber ?? '-')?></td>
                                                <td>
                                                    <?php
                                                        $certStatus = $skill->certification_status ?? 'N/A';
                                                        $certClass = 'badge-cmd-slate';
                                                        if (strtolower($certStatus) === 'certified') $certClass = 'badge-cmd-green';
                                                        elseif (strtolower($certStatus) === 'in progress') $certClass = 'badge-cmd-amber';
                                                        elseif (strtolower($certStatus) === 'expired') $certClass = 'badge-cmd-crimson';
                                                    ?>
                                                    <span class="badge <?=$certClass?>"><?=htmlspecialchars($certStatus)?></span>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="empty-state"><i class="fa fa-cogs"></i>No skills or training records found.</div>
                        <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <div class="accordion-item">
                        <h2 class="accordion-header" id="heading-operations">
                            <button class="<?=accordionButtonClass('operations', $defaultActiveTab)?>" type="button" data-bs-toggle="collapse" data-bs-target="#collapse-operations" aria-expanded="<?=accordionExpandedAttr('operations', $defaultActiveTab)?>" aria-controls="collapse-operations">
                                <i class="fa fa-crosshairs me-2"></i> Operations
                                <span class="badge <?=tabCountBadgeClass($operationCount)?> ms-2"><?=$operationCount?></span>
                            </button>
                        </h2>
                        <div id="collapse-operations" class="<?=accordionCollapseClass('operations', $defaultActiveTab)?>" aria-labelledby="heading-operations">
                            <div class="accordion-body">
                        <h5 class="print-section-title">Operations</h5>
                        <?php if (!empty($operations)): ?>
                            <div class="tab-toolbar no-print">
                                <input type="search" class="form-control form-control-sm table-filter-input" data-filter-target="#operationsTable" placeholder="Search operations..." aria-label="Search operations">
                            </div>
                            <div class="table-responsive">
                                <table class="table table-hover table-bordered align-middle" id="operationsTable">
                                    <thead class="table-light">
                                        <tr>
                                            <th>#</th><th class="sortable" data-sort-type="text">Operation<i class="fa fa-sort sort-arrow"></i></th><th class="sortable" data-sort-type="text">Type<i class="fa fa-sort sort-arrow"></i></th>
                                            <th class="sortable" data-sort-type="date">Start Date<i class="fa fa-sort sort-arrow"></i></th><th class="sortable" data-sort-type="date">End Date<i class="fa fa-sort sort-arrow"></i></th><th>Authority</th><th>Remarks</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($operations as $idx => $op): ?>
                                            <tr>
                                                <td><?=$idx + 1?></td>
                                                <td><strong><?=htmlspecialchars($op->opId ?? 'N/A')?></strong></td>
                                                <td>
                                                    <?php if (!empty($op->operationType)): ?>
                                                        <span class="badge badge-cmd-navy"><?=htmlspecialchars($op->operationType)?></span>
                                                    <?php else: ?>
                                                        <span class="text-muted fst-italic">N/A</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?=!empty($op->opStart) ? date('d M Y', strtotime($op->opStart)) : 'N/A'?></td>
                                                <td><?=!empty($op->opEnd) ? date('d M Y', strtotime($op->opEnd)) : 'Ongoing'?></td>
                                                <td><small><?=htmlspecialchars($op->authorityDescription ?? $op->authID ?? 'N/A')?></small></td>
                                                <td class="remarks-cell"><small><?=htmlspecialchars($op->remarks ?? '')?></small></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="empty-state"><i class="fa fa-crosshairs"></i>No operations participation recorded.</div>
                        <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <div class="accordion-item">
                        <h2 class="accordion-header" id="heading-deployments">
                            <button class="<?=accordionButtonClass('deployments', $defaultActiveTab)?>" type="button" data-bs-toggle="collapse" data-bs-target="#collapse-deployments" aria-expanded="<?=accordionExpandedAttr('deployments', $defaultActiveTab)?>" aria-controls="collapse-deployments">
                                <i class="fa fa-globe me-2"></i> Deployments
                                <span class="badge <?=tabCountBadgeClass($deploymentCount)?> ms-2"><?=$deploymentCount?></span>
                            </button>
                        </h2>
                        <div id="collapse-deployments" class="<?=accordionCollapseClass('deployments', $defaultActiveTab)?>" aria-labelledby="heading-deployments">
                            <div class="accordion-body">
                        <h5 class="print-section-title">Deployments</h5>
                        <?php if (!empty($deployments)): ?>
                            <div class="tab-toolbar no-print">
                                <input type="search" class="form-control form-control-sm table-filter-input" data-filter-target="#deploymentsTable" placeholder="Search deployments..." aria-label="Search deployments">
                            </div>
                            <div class="table-responsive">
                                <table class="table table-hover table-bordered align-middle" id="deploymentsTable">
                                    <thead class="table-light">
                                        <tr>
                                            <th>#</th><th class="sortable" data-sort-type="text">Deployment<i class="fa fa-sort sort-arrow"></i></th><th class="sortable" data-sort-type="text">Type<i class="fa fa-sort sort-arrow"></i></th><th>Location</th>
                                            <th class="sortable" data-sort-type="date">Start<i class="fa fa-sort sort-arrow"></i></th><th class="sortable" data-sort-type="date">End<i class="fa fa-sort sort-arrow"></i></th><th>Duration</th><th>Role</th><th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($deployments as $idx => $dep): ?>
                                            <?php
                                            $duration = $dep->durationMonths ?? null;
                                            if ($duration === null && !empty($dep->startDate)) {
                                                $start = strtotime($dep->startDate);
                                                $end = !empty($dep->endDate) ? strtotime($dep->endDate) : time();
                                                $duration = floor(($end - $start) / (60 * 60 * 24 * 30.44));
                                            }
                                            $status = $dep->deployment_status ?? '';
                                            switch (strtolower($status)) {
                                                case 'completed': $statusClass = 'badge-cmd-green'; break;
                                                case 'active': $statusClass = 'badge-cmd-navy'; break;
                                                case 'upcoming': $statusClass = 'badge-cmd-amber'; break;
                                                default: $statusClass = 'badge-cmd-slate';
                                            }
                                            ?>
                                            <tr>
                                                <td><?=$idx + 1?></td>
                                                <td><strong><?=htmlspecialchars($dep->deployment_name ?? 'N/A')?></strong></td>
                                                <td><?=htmlspecialchars($dep->mission_type ?? 'N/A')?></td>
                                                <td>
                                                    <?=htmlspecialchars($dep->location ?? 'N/A')?>
                                                    <?php if (!empty($dep->country)): ?>
                                                        <small class="text-muted">(<?=htmlspecialchars($dep->country)?>)</small>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?=!empty($dep->startDate) ? date('d M Y', strtotime($dep->startDate)) : 'N/A'?></td>
                                                <td><?=!empty($dep->endDate) ? date('d M Y', strtotime($dep->endDate)) : 'Ongoing'?></td>
                                                <td><?=$duration !== null ? $duration . ' mo' : 'N/A'?></td>
                                                <td><?=htmlspecialchars($dep->role_during_deployment ?? 'N/A')?></td>
                                                <td><span class="badge <?=$statusClass?>"><?=htmlspecialchars($status ?: 'N/A')?></span></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="empty-state"><i class="fa fa-globe"></i>No deployment records found.</div>
                        <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <div class="accordion-item">
                        <h2 class="accordion-header" id="heading-awards">
                            <button class="<?=accordionButtonClass('awards', $defaultActiveTab)?>" type="button" data-bs-toggle="collapse" data-bs-target="#collapse-awards" aria-expanded="<?=accordionExpandedAttr('awards', $defaultActiveTab)?>" aria-controls="collapse-awards">
                                <i class="fa fa-medal me-2"></i> Honours &amp; Awards
                                <span class="badge <?=tabCountBadgeClass($awardCount)?> ms-2"><?=$awardCount?></span>
                            </button>
                        </h2>
                        <div id="collapse-awards" class="<?=accordionCollapseClass('awards', $defaultActiveTab)?>" aria-labelledby="heading-awards">
                            <div class="accordion-body">
                        <h5 class="print-section-title">Honours &amp; Awards</h5>
                        <?php if (!empty($awards)): ?>
                            <div class="tab-toolbar no-print">
                                <input type="search" class="form-control form-control-sm table-filter-input" data-filter-target="#awardsTable" placeholder="Search awards..." aria-label="Search awards">
                            </div>
                            <div class="table-responsive">
                                <table class="table table-hover table-bordered align-middle" id="awardsTable">
                                    <thead class="table-light">
                                        <tr>
                                            <th>#</th><th class="sortable" data-sort-type="text">Type<i class="fa fa-sort sort-arrow"></i></th><th class="sortable" data-sort-type="text">Name<i class="fa fa-sort sort-arrow"></i></th><th>Awarded By</th>
                                            <th class="sortable" data-sort-type="date">Date<i class="fa fa-sort sort-arrow"></i></th><th>Citation</th><th>Certificate #</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($awards as $idx => $award): ?>
                                            <?php
                                            switch (strtolower($award->award_type ?? '')) {
                                                case 'commendation': $typeClass = 'badge-cmd-green'; break;
                                                case 'letter of appreciation': $typeClass = 'badge-cmd-navy'; break;
                                                case 'certificate': $typeClass = 'badge-cmd-navy'; break;
                                                case 'meritorious service': $typeClass = 'badge-cmd-amber'; break;
                                                default: $typeClass = 'badge-cmd-slate';
                                            }
                                            ?>
                                            <tr>
                                                <td><?=$idx + 1?></td>
                                                <td><span class="badge <?=$typeClass?>"><?=htmlspecialchars($award->award_type ?? 'N/A')?></span></td>
                                                <td><strong><?=htmlspecialchars($award->award_name ?? 'N/A')?></strong></td>
                                                <td><?=htmlspecialchars($award->awarded_by ?? 'N/A')?></td>
                                                <td><?=!empty($award->award_date) ? date('d M Y', strtotime($award->award_date)) : 'N/A'?></td>
                                                <td class="remarks-cell">
                                                    <?php if (!empty($award->citation)):
                                                        $citation = $award->citation;
                                                        $isLong = strlen($citation) > 100;
                                                    ?>
                                                        <small class="truncated-text">
                                                            <?php if ($isLong): ?>
                                                                <span class="short-text"><?=htmlspecialchars(substr($citation, 0, 100))?>&hellip;</span>
                                                                <span class="full-text"><?=htmlspecialchars($citation)?></span>
                                                                <button type="button" class="show-more-btn">Show more</button>
                                                            <?php else: ?>
                                                                <?=htmlspecialchars($citation)?>
                                                            <?php endif; ?>
                                                        </small>
                                                    <?php else: ?><span class="text-muted fst-italic">&mdash;</span><?php endif; ?>
                                                </td>
                                                <td><?=htmlspecialchars($award->certificateNumber ?? '-')?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="empty-state"><i class="fa fa-medal"></i>No honours or awards recorded.</div>
                        <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <div class="accordion-item">
                        <h2 class="accordion-header" id="heading-disciplinary">
                            <button class="<?=accordionButtonClass('disciplinary', $defaultActiveTab)?>" type="button" data-bs-toggle="collapse" data-bs-target="#collapse-disciplinary" aria-expanded="<?=accordionExpandedAttr('disciplinary', $defaultActiveTab)?>" aria-controls="collapse-disciplinary">
                                <i class="fa fa-exclamation-triangle me-2"></i> Disciplinary
                                <span class="badge <?=$disciplinaryCount > 0 ? 'badge-cmd-amber' : 'badge-cmd-ghost'?> ms-2"><?=$disciplinaryCount?></span>
                            </button>
                        </h2>
                        <div id="collapse-disciplinary" class="<?=accordionCollapseClass('disciplinary', $defaultActiveTab)?>" aria-labelledby="heading-disciplinary">
                            <div class="accordion-body">
                        <h5 class="print-section-title">Disciplinary Records</h5>
                        <?php if (defined('ARMIS_ADMIN_BRANCH') && ARMIS_ADMIN_BRANCH): ?>
                            <?php if (!empty($disciplinary)): ?>
                                <div class="alert alert-warning">
                                    <i class="fa fa-lock"></i> <strong>Confidential:</strong> Handle with appropriate discretion.
                                </div>
                                <div class="tab-toolbar no-print">
                                    <input type="search" class="form-control form-control-sm table-filter-input" data-filter-target="#disciplinaryTable" placeholder="Search disciplinary records..." aria-label="Search disciplinary records">
                                </div>
                                <div class="table-responsive">
                                    <table class="table table-hover table-bordered align-middle" id="disciplinaryTable">
                                        <thead class="table-light">
                                            <tr>
                                                <th>#</th><th class="sortable" data-sort-type="date">Date<i class="fa fa-sort sort-arrow"></i></th><th class="sortable" data-sort-type="text">Type<i class="fa fa-sort sort-arrow"></i></th><th>Description</th>
                                                <th>Action Taken</th><th>Outcome</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($disciplinary as $idx => $disc): ?>
                                                <?php
                                                switch (strtolower($disc->outcome ?? '')) {
                                                    case 'resolved': $outcomeClass = 'badge-cmd-green'; break;
                                                    case 'under review': $outcomeClass = 'badge-cmd-navy'; break;
                                                    case 'appealed': $outcomeClass = 'badge-cmd-amber'; break;
                                                    case 'dismissed': $outcomeClass = 'badge-cmd-slate'; break;
                                                    default: $outcomeClass = 'badge-cmd-slate';
                                                }
                                                ?>
                                                <tr>
                                                    <td><?=$idx + 1?></td>
                                                    <td><?=!empty($disc->incident_date) ? date('d M Y', strtotime($disc->incident_date)) : 'N/A'?></td>
                                                    <td><?=htmlspecialchars($disc->incident_type ?? 'N/A')?></td>
                                                    <td class="remarks-cell">
                                                        <?php
                                                            $descText = $disc->description ?? '';
                                                            $descLong = strlen($descText) > 100;
                                                        ?>
                                                        <?php if ($descText !== ''): ?>
                                                            <small class="truncated-text">
                                                                <?php if ($descLong): ?>
                                                                    <span class="short-text"><?=htmlspecialchars(substr($descText, 0, 100))?>&hellip;</span>
                                                                    <span class="full-text"><?=htmlspecialchars($descText)?></span>
                                                                    <button type="button" class="show-more-btn">Show more</button>
                                                                <?php else: ?>
                                                                    <?=htmlspecialchars($descText)?>
                                                                <?php endif; ?>
                                                            </small>
                                                        <?php else: ?><span class="text-muted fst-italic">&mdash;</span><?php endif; ?>
                                                    </td>
                                                    <td><small><?=htmlspecialchars($disc->action_taken ?? 'N/A')?></small></td>
                                                    <td><span class="badge <?=$outcomeClass?>"><?=htmlspecialchars($disc->outcome ?? 'N/A')?></span></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <div class="alert alert-success mb-0">
                                    <i class="fa fa-check-circle"></i> No disciplinary records found. Clean record.
                                </div>
                            <?php endif; ?>
                        <?php else: ?>
                            <div class="alert alert-danger mb-0">
                                <i class="fa fa-lock"></i> <strong>Access Denied:</strong> You do not have permission to view disciplinary records.
                            </div>
                        <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <?php if (!empty($staff->remarks)): ?>
            <div class="card mt-4 shadow-sm">
                <div class="section-header"><h4><i class="fa fa-comment"></i> Remarks</h4></div>
                <div class="card-body">
                    <p class="mb-0"><?=nl2br(htmlspecialchars($staff->remarks))?></p>
                </div>
            </div>
        <?php endif; ?>

        <div class="mt-4 no-print">
            <a href="edit_staff.php" class="btn btn-secondary">
                <i class="fa fa-arrow-left"></i> Back to Staff Management
            </a>
        </div>

    </div>
</div>

<script>
function handleImageError(img, fallbackSrc) {
    img.onerror = null; // prevent loop if the fallback itself fails to load
    img.src = fallbackSrc || '/Armis2/logo.png';
}

/**
 * Prints the profile. @page rules apply to the whole print job and can't be
 * scoped with a CSS class selector, so for landscape we inject a temporary
 * stylesheet that wins on specificity/order, print, then remove it - the
 * portrait @page rule defined in the main stylesheet is the permanent default.
 */
function printProfile(orientation) {
    var styleId = 'print-orientation-override';
    var existing = document.getElementById(styleId);
    if (existing) existing.remove();

    if (orientation === 'landscape') {
        var style = document.createElement('style');
        style.id = styleId;
        style.media = 'print';
        style.textContent = '@page { size: A4 landscape; margin: 14mm 16mm; }';
        document.head.appendChild(style);
    }

    var cleanup = function() {
        var s = document.getElementById(styleId);
        if (s) s.remove();
        window.removeEventListener('afterprint', cleanup);
    };
    window.addEventListener('afterprint', cleanup);

    window.print();
}

document.addEventListener('DOMContentLoaded', function() {
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.forEach(function (el) { new bootstrap.Tooltip(el); });

    // Quick-stat cards expand the matching accordion section and scroll to it
    document.querySelectorAll('.quick-stat-row[data-target-tab]').forEach(function(card) {
        card.addEventListener('click', function() {
            var collapseEl = document.getElementById(this.getAttribute('data-target-tab'));
            if (!collapseEl) return;
            var collapse = bootstrap.Collapse.getOrCreateInstance(collapseEl, { toggle: false });
            collapse.show();
            collapseEl.scrollIntoView({ behavior: 'smooth', block: 'start' });
        });
    });

    // ---------- Mute empty sections / quick-stat rows (CSS :has() fallback) ----------
    document.querySelectorAll('.accordion-button').forEach(function(link) {
        var countEl = link.querySelector('.qs-count, .badge');
        if (countEl && countEl.textContent.trim() === '0') {
            link.classList.add('section-empty');
        }
    });
    document.querySelectorAll('.quick-stat-row').forEach(function(row) {
        var countEl = row.querySelector('.qs-count');
        if (countEl && countEl.textContent.trim() === '0') {
            row.classList.add('quick-stat-row--empty');
        }
    });

    // ---------- Copy service number ----------
    var copyBtn = document.getElementById('copySvcNoBtn');
    if (copyBtn) {
        copyBtn.addEventListener('click', function() {
            var svcNo = this.getAttribute('data-svcno') || '';
            var done = function() {
                copyBtn.classList.add('copied');
                setTimeout(function() { copyBtn.classList.remove('copied'); }, 1500);
            };
            if (navigator.clipboard && window.isSecureContext) {
                navigator.clipboard.writeText(svcNo).then(done).catch(function() {});
            } else {
                var tmp = document.createElement('textarea');
                tmp.value = svcNo;
                tmp.style.position = 'fixed';
                tmp.style.opacity = '0';
                document.body.appendChild(tmp);
                tmp.select();
                try { document.execCommand('copy'); done(); } catch (e) {}
                document.body.removeChild(tmp);
            }
        });
    }

    // ---------- Sensitive field reveal/hide toggle ----------
    document.querySelectorAll('.btn-reveal').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var field = this.previousElementSibling;
            if (!field || !field.classList.contains('sensitive-field')) return;
            var showing = field.dataset.state === 'full';
            field.textContent = showing ? field.dataset.masked : field.dataset.full;
            field.dataset.state = showing ? 'masked' : 'full';
            this.querySelector('i').className = showing ? 'fa fa-eye' : 'fa fa-eye-slash';
        });
    });

    // ---------- Show more / less toggle for truncated text ----------
    document.querySelectorAll('.show-more-btn').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var wrap = this.closest('.truncated-text');
            if (!wrap) return;
            var expanded = wrap.classList.toggle('expanded');
            this.textContent = expanded ? 'Show less' : 'Show more';
        });
    });

    // ---------- Per-tab table search/filter (group-aware: rows sharing data-group move together) ----------
    document.querySelectorAll('.table-filter-input').forEach(function(input) {
        input.addEventListener('input', function() {
            var table = document.querySelector(this.getAttribute('data-filter-target'));
            if (!table) return;
            var term = this.value.trim().toLowerCase();
            var rows = Array.prototype.slice.call(table.tBodies[0].rows);

            // Build groups: consecutive rows sharing the same data-group (or each row its own group)
            var groups = [];
            var seen = {};
            rows.forEach(function(row) {
                var key = row.getAttribute('data-group') || ('__row' + groups.length);
                if (!seen[key]) {
                    seen[key] = { rows: [] };
                    groups.push(seen[key]);
                }
                seen[key].rows.push(row);
            });

            groups.forEach(function(group) {
                var text = group.rows.map(function(r) { return r.textContent.toLowerCase(); }).join(' ');
                var match = term === '' || text.indexOf(term) !== -1;
                group.rows.forEach(function(r) { r.style.display = match ? '' : 'none'; });
            });
        });
    });

    // ---------- Sortable table columns (group-aware) ----------
    document.querySelectorAll('th.sortable').forEach(function(th) {
        th.addEventListener('click', function() {
            var table = th.closest('table');
            var tbody = table.tBodies[0];
            var headerRow = th.parentElement;
            var colIndex = Array.prototype.indexOf.call(headerRow.children, th);
            var sortType = th.getAttribute('data-sort-type') || 'text';
            var asc = !th.classList.contains('sort-asc');

            // Clear sort state on sibling headers
            headerRow.querySelectorAll('th.sortable').forEach(function(h) {
                h.classList.remove('sort-asc', 'sort-desc');
                var icon = h.querySelector('.sort-arrow');
                if (icon) icon.className = 'fa fa-sort sort-arrow';
            });
            th.classList.add(asc ? 'sort-asc' : 'sort-desc');
            var thisIcon = th.querySelector('.sort-arrow');
            if (thisIcon) thisIcon.className = asc ? 'fa fa-sort-up sort-arrow' : 'fa fa-sort-down sort-arrow';

            var rows = Array.prototype.slice.call(tbody.rows);
            var groups = [];
            var seen = {};
            rows.forEach(function(row) {
                var key = row.getAttribute('data-group') || ('__row' + groups.length + Math.random());
                if (!seen[key]) {
                    seen[key] = { rows: [], key: key };
                    groups.push(seen[key]);
                }
                seen[key].rows.push(row);
            });

            var getValue = function(group) {
                var leadRow = group.rows[0];
                var cell = leadRow.children[colIndex];
                var raw = cell ? cell.textContent.trim() : '';
                if (sortType === 'number') {
                    var num = parseFloat(raw.replace(/[^0-9.\-]/g, ''));
                    return isNaN(num) ? -Infinity : num;
                }
                if (sortType === 'date') {
                    var d = Date.parse(raw);
                    return isNaN(d) ? -Infinity : d;
                }
                return raw.toLowerCase();
            };

            groups.sort(function(a, b) {
                var va = getValue(a), vb = getValue(b);
                if (va < vb) return asc ? -1 : 1;
                if (va > vb) return asc ? 1 : -1;
                return 0;
            });

            var frag = document.createDocumentFragment();
            groups.forEach(function(group) {
                group.rows.forEach(function(row) { frag.appendChild(row); });
            });
            tbody.appendChild(frag);
        });
    });
});
</script>

<?php include dirname(__DIR__) . '/shared/footer.php'; ?>