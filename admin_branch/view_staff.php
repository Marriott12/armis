<?php
define('ARMIS_ADMIN_BRANCH', true);
define('ARMIS_DEVELOPMENT', true);

require_once dirname(__DIR__) . '/shared/database_connection.php';
require_once dirname(__DIR__) . '/shared/rank_levels.php';

$pdo = getDbConnection();

$pageTitle = "Staff Profile - Admin Branch";
$moduleName = "Admin Branch";
$moduleIcon = "users-cog";
$currentPage = "profile";

// Load required scripts and styles for the advanced profile
$additionalStyles = [
    '/Armis2/admin_branch/css/advanced-profile.css'
];

$additionalScripts = [
    'https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js',
    '/Armis2/admin_branch/js/advanced-profile.js',
    '/Armis2/admin_branch/js/profile-visualizations.js'
];

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
];

$svcNo = isset($_GET['svcNo']) ? trim($_GET['svcNo']) : '';
if (empty($svcNo)) {
    die('<div class="alert alert-danger">Invalid service number.</div>');
}

// ==================== FETCH COMPREHENSIVE STAFF DATA ====================

// Main staff data with calculated fields
// helper: check if a table exists and return its columns
function tableColumns(PDO $pdo, string $tableName): array {
    try {
        // Some environments don't allow parameterized SHOW TABLES; use direct query
        $checkSql = "SHOW TABLES LIKE '" . addslashes($tableName) . "'";
        $stmt = $pdo->query($checkSql);
        if ($stmt === false || $stmt->rowCount() === 0) return [];

        $cols = [];
        $desc = $pdo->query("DESCRIBE `" . $tableName . "`");
        foreach ($desc->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $cols[] = $row['Field'];
        }
        return $cols;
    } catch (Exception $e) {
        return [];
    }
}

// Determine unit table and useful columns
$unitTable = '';
$unitCols = [];
foreach (['unit', 'units'] as $t) {
    $cols = tableColumns($pdo, $t);
    if (!empty($cols)) {
        $unitTable = $t;
        $unitCols = $cols;
        break;
    }
}

// pick a sensible column for display name and id
$unitNameCol = null;
$unitIdCol = null;
if (!empty($unitCols)) {
    // possible name columns
    foreach (['name', 'unit_name', 'unitName', 'unitName', 'unitName'] as $cand) {
        if (in_array($cand, $unitCols)) { $unitNameCol = $cand; break; }
    }
    // fallback to any sensible column
    if ($unitNameCol === null) {
        foreach ($unitCols as $c) {
            if (stripos($c, 'name') !== false || stripos($c, 'title') !== false) { $unitNameCol = $c; break; }
        }
    }
    // id column
    if (in_array('unitId', $unitCols)) $unitIdCol = 'unitId';
    elseif (in_array('id', $unitCols)) $unitIdCol = 'id';
    elseif (in_array('unitID', $unitCols)) $unitIdCol = 'unitID';
}

// Build the staff query dynamically so we only reference existing columns/tables
$selectExtras = "c.corpsId AS corpsName,\n        TIMESTAMPDIFF(YEAR, s.attestDate, CURDATE()) as years_of_service,\n        TIMESTAMPDIFF(YEAR, s.DOB, CURDATE()) as age";
$joinUnit = '';
$unitSelect = "'' AS unitName";
if ($unitTable !== '') {
    // alias u
    $joinUnit = " LEFT JOIN `" . $unitTable . "` u ON ";
    if ($unitIdCol !== null) {
        $joinUnit .= "s.unitId = u.`" . $unitIdCol . "`";
    } else {
        // best-effort join; leave join condition to match s.unitId = u.id if present
        $joinUnit .= "s.unitId = u.id";
    }

    if ($unitNameCol !== null) {
        $unitSelect = "u.`" . $unitNameCol . "` AS unitName";
    } else {
        // fall back to id when no name column is present
        if ($unitIdCol !== null) $unitSelect = "u.`" . $unitIdCol . "` AS unitName";
    }
}

$sql = "SELECT s.*, r.rankId AS rankName, r.rankId AS rankAbbr, " . getRankCategoryCaseSQL('r') . " AS rankCategory, " . $unitSelect . ", " . $selectExtras . "\n    FROM staff s\n    LEFT JOIN `rank` r ON s.rankId = r.rankId\n    " . $joinUnit . "\n    LEFT JOIN corps c ON s.corpsId = c.corpsId\n    WHERE s.svcNo = ?\n    LIMIT 1";

$stmt = $pdo->prepare($sql);
$stmt->execute([$svcNo]);
$staff = $stmt->fetch(PDO::FETCH_OBJ);

if (!$staff) {
    die('<div class="alert alert-danger">Staff member not found.</div>');
}

// ==================== FETCH EDUCATION DATA ====================
$education = [];
try {
    $eduStmt = $pdo->prepare("
        SELECT * FROM staff_course 
        WHERE svcNo = ? 
        ORDER BY yearCompleted DESC, yearStarted DESC
    ");
    $eduStmt->execute([$svcNo]);
    $education = $eduStmt->fetchAll(PDO::FETCH_OBJ);
} catch (PDOException $e) {
    // Table may not exist yet, ignore
}

// ==================== FETCH SKILLS DATA ====================
$skills = [];
try {
    $skillStmt = $pdo->prepare("
        SELECT * FROM staff_skills 
        WHERE svcNo = ? 
        ORDER BY skillLevel DESC, skillName ASC
    ");
    $skillStmt->execute([$svcNo]);
    $skills = $skillStmt->fetchAll(PDO::FETCH_OBJ);
} catch (PDOException $e) {
    // Table may not exist yet, ignore
}

// ==================== FETCH OPERATIONS DATA ====================
$operations = [];
try {
    $opStmt = $pdo->prepare("
        SELECT * FROM staff_operations 
        WHERE svcNo = ? 
        ORDER BY startDate DESC
    ");
    $opStmt->execute([$svcNo]);
    $operations = $opStmt->fetchAll(PDO::FETCH_OBJ);
} catch (PDOException $e) {
    // Table may not exist yet, ignore
}

// ==================== FETCH DEPLOYMENTS DATA ====================
$deployments = [];
try {
    $deploymentStmt = $pdo->prepare("
        SELECT 
            d.*,
            d.startDate AS dateFrom,
            d.endDate AS dateTo,
            d.deployment_status AS status,
            d.role_during_deployment AS role
        FROM staff_deployments d
        WHERE d.svcNo = ?
        ORDER BY d.startDate DESC
    ");
    $deploymentStmt->execute([$svcNo]);
    $deployments = $deploymentStmt->fetchAll(PDO::FETCH_OBJ);
} catch (PDOException $e) {
    // Table may not exist yet, ignore
}

// ==================== FETCH PROMOTIONS DATA ====================
$promotions = [];
try {
    $promStmt = $pdo->prepare("
        SELECT 
            p.*, 
            r.rankId AS newRankName,
            r.rankId AS newRankAbbr,
            IFNULL(pr.rankId, 'N/A') AS previousRankName,
            IFNULL(pr.rankId, 'N/A') AS previousRankAbbr,
            DATEDIFF(IFNULL(p.dateTo, CURDATE()), p.dateFrom) as days_in_rank
        FROM staff_promotion p 
        LEFT JOIN `rank` r ON p.newRank = r.rankId
        LEFT JOIN `rank` pr ON p.currentRank = pr.rankId
        WHERE p.svcNo = ? 
        ORDER BY p.dateFrom DESC
    ");
    $promStmt->execute([$svcNo]);
    $promotions = $promStmt->fetchAll(PDO::FETCH_OBJ);
} catch (PDOException $e) {
    // Table may not exist or schema mismatch, ignore
}

// ==================== FETCH MEDALS DATA ====================
$medals = [];
try {
    $medalStmt = $pdo->prepare("
        SELECT 
            m.*, 
            mm.name AS medalName,
            mm.description AS medalDescription
        FROM staff_medals m 
        LEFT JOIN medal mm ON m.medal_id = mm.id 
        WHERE m.svcNo = ? 
        ORDER BY m.award_date DESC
    ");
    $medalStmt->execute([$svcNo]);
    $medals = $medalStmt->fetchAll(PDO::FETCH_OBJ);
} catch (PDOException $e) {
    // Table may not exist or schema mismatch, ignore
}

// ==================== FETCH COURSES DATA ====================
$courses = [];
try {
    $courseStmt = $pdo->prepare("
        SELECT 
            sc.id,
            sc.svcNo,
            sc.instId,
            sc.cseId,
            sc.qualification,
            sc.yearStarted,
            sc.yearCompleted,
            sc.grade,
            sc.result,
            sc.isHighest,
            sc.createdAt,
            sc.updatedAt,
            i.instLoc AS institutionLocation,
            i.instType AS institutionType,
            c.cseType AS courseType,
            c.cseLevel AS courseLevel
        FROM staff_course sc
        LEFT JOIN institution i ON sc.instId = i.instId
        LEFT JOIN course c ON sc.cseId = c.cseId
        WHERE sc.svcNo = ?
        ORDER BY sc.yearCompleted DESC, sc.yearStarted DESC
    ");
    $courseStmt->execute([$svcNo]);
    $courses = $courseStmt->fetchAll(PDO::FETCH_OBJ);
} catch (Exception $e) {
    error_log("Error fetching courses: " . $e->getMessage());
    $courses = [];
}

// ==================== FETCH POSTING HISTORY (from staff_appointment) ====================
$postings = [];
try {
    // Use previously detected $unitTable and $unitCols if available; otherwise try 'units'
    if (empty($unitTable)) {
        try {
            $chk = $pdo->query("SHOW TABLES LIKE 'units'");
            if ($chk && $chk->rowCount() > 0) {
                $unitTable = 'units';
                $unitCols = tableColumns($pdo, 'units');
            }
        } catch (Exception $e) { }
    }

    $unitNameSelect = "'' AS unit_name";
    $unitLocationSelect = "'' AS unit_location";
    $unitJoin = '';
    if (!empty($unitTable) && !empty($unitCols)) {
        $joinCol = in_array('unitId', $unitCols) ? 'unitId' : (in_array('id', $unitCols) ? 'id' : $unitCols[0]);
        $unitJoin = "LEFT JOIN `" . $unitTable . "` u ON sa.unitId = u.`" . $joinCol . "`";
        if (in_array('name', $unitCols)) $unitNameSelect = 'u.name AS unit_name';
        elseif (in_array('unit_name', $unitCols)) $unitNameSelect = 'u.unit_name AS unit_name';
        elseif (in_array('unitName', $unitCols)) $unitNameSelect = 'u.unitName AS unit_name';

        if (in_array('location', $unitCols)) $unitLocationSelect = 'u.location AS unit_location';
        elseif (in_array('unit_location', $unitCols)) $unitLocationSelect = 'u.unit_location AS unit_location';
    }

    $postingSql = "\n        SELECT \n            sa.*,\n            " . $unitNameSelect . ",\n            " . $unitLocationSelect . ",\n            r.rankId AS rank_name,\n            r.rankId AS rank_abbr\n        FROM staff_appointment sa\n        " . $unitJoin . "\n        LEFT JOIN `rank` r ON sa.rankId = r.rankId\n        WHERE sa.svcNo = ?\n        ORDER BY sa.startDate DESC\n    ";
    $postingStmt = $pdo->prepare($postingSql);
    $postingStmt->execute([$svcNo]);
    $postings = $postingStmt->fetchAll(PDO::FETCH_OBJ);
} catch (Exception $e) {
    error_log("Error fetching postings from staff_appointment: " . $e->getMessage());
}

// ==================== FETCH AWARDS & COMMENDATIONS ====================
$awards = [];
try {
    $awardStmt = $pdo->prepare("
        SELECT * FROM staff_awards 
        WHERE svcNo = ? 
        ORDER BY createdAt DESC
    ");
    $awardStmt->execute([$svcNo]);
    $awards = $awardStmt->fetchAll(PDO::FETCH_OBJ);
} catch (Exception $e) {
    error_log("Error fetching awards: " . $e->getMessage());
}

// ==================== FETCH DISCIPLINARY RECORDS ====================
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

// ==================== CALCULATE STATISTICS ====================
$yearsOfService = !empty($staff->attestDate) ? floor((time() - strtotime($staff->attestDate)) / (365.25 * 24 * 60 * 60)) : 0;
$medalCount = count($medals);
$promotionCount = count($promotions);
$courseCount = count($courses);
$deploymentCount = count($deployments);
$operationCount = count($operations);
$educationCount = count($education);
$skillCount = count($skills);

$postingCount = count($postings);
$awardCount = count($awards);
$disciplinaryCount = count($disciplinary);

// ==================== CALCULATE RETIREMENT DATES ====================
/**
 * Calculate retirement dates and remaining time
 */
function calculateRetirementInfo($dateOfBirth, $dateOfEnlistment) {
    $result = [
        'runout' => null,
        'earlyRetirement' => null
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

$retirementInfo = calculateRetirementInfo($staff->DOB ?? null, $staff->attestDate ?? null);

// Add body class for admin access
$bodyClass = '';
if (defined('ARMIS_ADMIN_BRANCH') && ARMIS_ADMIN_BRANCH) {
    $bodyClass = 'admin-access';
}

include dirname(__DIR__) . '/shared/header.php';
include dirname(__DIR__) . '/shared/sidebar.php'; 
?>

<style>
    .profile-photo {
        max-height: 250px;
        object-fit: cover;
        border: 3px solid #fff;
        box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    }
    .stat-card {
        border-left: 4px solid #007bff;
        transition: transform 0.2s;
    }
    .stat-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    }
    .stat-number {
        font-size: 2rem;
        font-weight: bold;
        color: #007bff;
    }
    .stat-label {
        color: #6c757d;
        font-size: 0.875rem;
        text-transform: uppercase;
    }
    .section-header {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        padding: 1rem;
        border-radius: 0.5rem 0.5rem 0 0;
        margin-top: 1.5rem;
    }
    .info-table th {
        width: 35%;
        background-color: #f8f9fa;
        font-weight: 600;
    }
    .badge-skill-level {
        font-size: 0.75rem;
        padding: 0.25rem 0.5rem;
    }
    .nav-tabs {
        display: flex;
        flex-direction: row;
        flex-wrap: nowrap;
        overflow-x: auto;
        overflow-y: hidden;
        white-space: nowrap;
        -webkit-overflow-scrolling: touch;
    }
    .nav-tabs .nav-item {
        flex: 0 0 auto;
    }
    .nav-tabs .nav-link {
        color: #495057;
        border: none;
        border-bottom: 3px solid transparent;
        white-space: nowrap;
    }
    .nav-tabs .nav-link.active {
        color: #007bff;
        border-bottom: 3px solid #007bff;
        background: none;
    }
    @media print {
        .no-print {
            display: none !important;
        }
        .card {
            break-inside: avoid;
        }
    }
</style>

<div class="content-wrapper with-sidebar">
    <div class="container-fluid p-4">
        
        <!-- Page Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="mb-0"><i class="fa fa-user"></i> Staff Profile</h1>
            <div class="no-print">
                <button onclick="window.print()" class="btn btn-secondary me-2">
                    <i class="fa fa-print"></i> Print
                </button>
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

        <!-- ==================== PROFILE HEADER CARD ==================== -->
        <div class="card mb-4 shadow-sm">
            <div class="card-header bg-primary text-white">
                <h3 class="mb-0">
                    <i class="fa fa-user"></i> 
                    <?=htmlspecialchars(($staff->rankAbbr ?? '') . ' ' . $staff->fName . ' ' . $staff->lName)?>
                    <span class="badge bg-light text-dark ms-2"><?=htmlspecialchars($staff->titles ?? 'N/A')?></span>
                </h3>
            </div>
            <div class="card-body row">
                <!-- Photo Column -->
                <div class="col-md-3 text-center">
                    <?php if (!empty($staff->profilePhoto) && file_exists(dirname(__DIR__) . '/' . $staff->profilePhoto)): ?>
                        <img src="/Armis2/<?=htmlspecialchars($staff->profilePhoto)?>" 
                             class="img-fluid rounded mb-3 profile-photo" 
                             alt="Profile Photo">
                    <?php else: ?>
                        <img src="/Armis2/logo.png" 
                             class="img-fluid rounded mb-3 profile-photo" 
                             alt="No Photo">
                    <?php endif; ?>
                    
                    <div class="mb-2">
                        <span class="badge bg-<?=strcasecmp($staff->svcStatus,'Active')===0?'success':(strcasecmp($staff->svcStatus,'Retired')===0?'secondary':(strcasecmp($staff->svcStatus,'Deceased')===0?'danger':'warning'))?>">
                            <?=htmlspecialchars($staff->svcStatus ?? 'N/A')?>
                        </span>
                    </div>
                    
                    <?php if (!empty($staff->age)): ?>
                        <small class="text-muted d-block">Age: <?=$staff->age?> years</small>
                    <?php endif; ?>
                </div>
                
                <!-- Personal Information Column -->
                <div class="col-md-9">
                    <table class="table table-bordered table-sm info-table">
                        <tbody>
                            <tr>
                                <th><i class="fa fa-id-card text-primary"></i> Service Number</th>
                                <td><strong><?=htmlspecialchars($staff->svcNo ?? 'N/A')?></strong></td>
                            </tr>
                            <tr>
                                <th><i class="fa fa-id-badge text-primary"></i> NRC</th>
                                <td><?=htmlspecialchars($staff->NRC ?? 'N/A')?></td>
                            </tr>
                            <tr>
                                <th><i class="fa fa-star text-primary"></i> Rank</th>
                                <td><?=htmlspecialchars($staff->rankName ?? 'N/A')?></td>
                            </tr>
                            <tr>
                                <th><i class="fa fa-building text-primary"></i> Unit</th>
                                <td><?=htmlspecialchars($staff->unitName ?? 'N/A')?></td>
                            </tr>
                            <tr>
                                <th><i class="fa fa-user-cog text-primary"></i> Appointment</th>
                                <td><?=htmlspecialchars($staff->appt ?? 'N/A')?></td>
                            </tr>
                            <tr>
                                <th><i class="fa fa-shield-alt text-primary"></i> Corps</th>
                                <td><?=htmlspecialchars($staff->corpsName ?? 'N/A')?></td>
                            </tr>
                            <tr>
                                <th><i class="fa fa-tools text-primary"></i> Trade</th>
                                <td><?=htmlspecialchars($staff->trade ?? 'N/A')?></td>
                            </tr>
                            <tr>
                                <th><i class="fa fa-briefcase text-primary"></i> Profession</th>
                                <td><?=htmlspecialchars($staff->profession ?? 'N/A')?></td>
                            </tr>
                            <tr>
                                <th><i class="fa fa-tag text-primary"></i> Category</th>
                                <td><?=htmlspecialchars($staff->rankCategory ?? $staff->category ?? 'N/A')?></td>
                            </tr>
                            <tr>
                                <th><i class="fa fa-calendar text-primary"></i> Date of Birth</th>
                                <td>
                                    <?=!empty($staff->DOB) ? date('d M Y', strtotime($staff->DOB)) : 'N/A'?>
                                    <?php if (!empty($staff->age)): ?>
                                        <small class="text-muted">(<?=$staff->age?> years old)</small>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <tr>
                                <th><i class="fa fa-calendar-check text-primary"></i> Date of Enlistment</th>
                                <td>
                                    <?=!empty($staff->attestDate) ? date('d M Y', strtotime($staff->attestDate)) : 'N/A'?>
                                    <?php if ($yearsOfService > 0): ?>
                                        <small class="text-muted">(<?=$yearsOfService?> years of service)</small>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            
                            <!-- Expected Runout Date (Age 65) -->
                            <tr>
                                <th><i class="fa fa-hourglass-end text-primary"></i> Expected Runout Date (Age 65)</th>
                                <td>
                                    <?php if ($retirementInfo['runout']): ?>
                                        <?php 
                                            $runout = $retirementInfo['runout'];
                                            $urgencyClass = getRetirementUrgencyClass($runout['yearsRemaining']);
                                        ?>
                                        <strong><?=$runout['formatted']?></strong>
                                        <br>
                                        <small class="<?=$urgencyClass?>">
                                            <i class="fa fa-clock"></i> <?=$runout['remaining']?> remaining
                                        </small>
                                    <?php else: ?>
                                        <span class="text-muted">N/A (Date of birth not recorded)</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            
                            <!-- Expected Early Retirement (20 Years Service) -->
                            <tr>
                                <th><i class="fa fa-calendar-alt text-primary"></i> Expected Early Retirement (20 Years Service)</th>
                                <td>
                                    <?php if ($retirementInfo['earlyRetirement']): ?>
                                        <?php 
                                            $earlyRet = $retirementInfo['earlyRetirement'];
                                            $urgencyClass = getRetirementUrgencyClass($earlyRet['yearsRemaining']);
                                        ?>
                                        <strong><?=$earlyRet['formatted']?></strong>
                                        <br>
                                        <small class="<?=$urgencyClass?>">
                                            <i class="fa fa-clock"></i> <?=$earlyRet['remaining']?> 
                                            <?=$earlyRet['isPast'] ? '' : 'remaining'?>
                                        </small>
                                    <?php else: ?>
                                        <span class="text-muted">N/A (Enlistment date not recorded)</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <tr>
                                <th><i class="fa fa-venus-mars text-primary"></i> Gender</th>
                                <td><?=htmlspecialchars($staff->gender ?? 'N/A')?></td>
                            </tr>
                            <tr>
                                <th><i class="fa fa-ring text-primary"></i> Marital Status</th>
                                <td><?=htmlspecialchars($staff->marital ?? 'N/A')?></td>
                            </tr>
                            <tr>
                                <th><i class="fa fa-pray text-primary"></i> Religion</th>
                                <td><?=htmlspecialchars($staff->religion ?? 'N/A')?></td>
                            </tr>
                            <tr>
                                <th><i class="fa fa-tint text-primary"></i> Blood Group</th>
                                <td><?=htmlspecialchars($staff->bloodGp ?? 'N/A')?></td>
                            </tr>
                            <tr>
                                <th><i class="fa fa-ruler-vertical text-primary"></i> Height</th>
                                <td><?=htmlspecialchars($staff->height ?? 'N/A')?></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- ==================== CONTACT INFORMATION CARD ==================== -->
        <div class="card mb-4 shadow-sm">
            <div class="section-header">
                <h4 class="mb-0"><i class="fa fa-address-book"></i> Contact Information</h4>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <table class="table table-bordered table-sm info-table">
                            <tbody>
                                <tr>
                                    <th><i class="fa fa-phone text-success"></i> Telephone</th>
                                    <td><?=htmlspecialchars($staff->tel ?? 'N/A')?></td>
                                </tr>
                                <tr>
                                    <th><i class="fa fa-envelope text-success"></i> Email</th>
                                    <td><?=htmlspecialchars($staff->email ?? 'N/A')?></td>
                                </tr>
                                <tr>
                                    <th><i class="fa fa-map-marker-alt text-success"></i> Province</th>
                                    <td><?=htmlspecialchars($staff->province ?? 'N/A')?></td>
                                </tr>
                                <tr>
                                    <th><i class="fa fa-map-marked text-success"></i> District</th>
                                    <td><?=htmlspecialchars($staff->district ?? 'N/A')?></td>
                                </tr>
                                <tr>
                                    <th><i class="fa fa-home text-success"></i> Village</th>
                                    <td><?=htmlspecialchars($staff->village ?? 'N/A')?></td>
                                </tr>
                                <tr>
                                    <th><i class="fa fa-map text-success"></i> Address</th>
                                    <td><?=htmlspecialchars($staff->address ?? 'N/A')?></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <h5 class="text-danger"><i class="fa fa-user-shield"></i> Next of Kin (Primary)</h5>
                        <table class="table table-bordered table-sm info-table">
                            <tbody>
                                <tr>
                                    <th><i class="fa fa-user text-danger"></i> Name</th>
                                    <td><?=htmlspecialchars($staff->nok ?? 'N/A')?></td>
                                </tr>
                                <tr>
                                    <th><i class="fa fa-id-card text-danger"></i> NRC</th>
                                    <td><?=htmlspecialchars($staff->nokNrc ?? 'N/A')?></td>
                                </tr>
                                <tr>
                                    <th><i class="fa fa-phone text-danger"></i> Telephone</th>
                                    <td><?=htmlspecialchars($staff->nokTel ?? 'N/A')?></td>
                                </tr>
                                <tr>
                                    <th><i class="fa fa-users text-danger"></i> Relationship</th>
                                    <td><?=htmlspecialchars($staff->nokRelat ?? 'N/A')?></td>
                                </tr>
                            </tbody>
                        </table>
                        
                        <?php if (!empty($staff->altNok)): ?>
                            <h5 class="text-warning mt-3"><i class="fa fa-user-shield"></i> Next of Kin (Alternative)</h5>
                            <table class="table table-bordered table-sm info-table">
                                <tbody>
                                    <tr>
                                        <th><i class="fa fa-user text-warning"></i> Name</th>
                                        <td><?=htmlspecialchars($staff->altNok ?? 'N/A')?></td>
                                    </tr>
                                    <tr>
                                        <th><i class="fa fa-id-card text-warning"></i> NRC</th>
                                        <td><?=htmlspecialchars($staff->altNokNrc ?? 'N/A')?></td>
                                    </tr>
                                    <tr>
                                        <th><i class="fa fa-phone text-warning"></i> Telephone</th>
                                        <td><?=htmlspecialchars($staff->altNokTel ?? 'N/A')?></td>
                                    </tr>
                                    <tr>
                                        <th><i class="fa fa-users text-warning"></i> Relationship</th>
                                        <td><?=htmlspecialchars($staff->altNokRelat ?? 'N/A')?></td>
                                    </tr>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- ==================== TABBED SECTIONS ==================== -->
        <div class="card shadow-sm">
            <div class="card-header bg-white">
                <ul class="nav nav-tabs card-header-tabs" id="profileTabs" role="tablist">
                    <!-- Career Progress Group -->
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="promotions-tab" data-bs-toggle="tab" data-bs-target="#promotions" type="button">
                            <i class="fa fa-arrow-up me-1"></i> Promotions 
                            <span class="badge <?=$promotionCount > 0 ? 'bg-primary' : 'bg-secondary'?> ms-1"><?=$promotionCount?></span>
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="postings-tab" data-bs-toggle="tab" data-bs-target="#postings" type="button">
                            <i class="fa fa-map-marker-alt me-1"></i>Appointments 
                            <span class="badge <?=$postingCount > 0 ? 'bg-primary' : 'bg-secondary'?> ms-1"><?=$postingCount?></span>
                        </button>
                    </li>
                    
                    <!-- Recognition Group -->
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="medals-tab" data-bs-toggle="tab" data-bs-target="#medals" type="button">
                            <i class="fa fa-medal me-1"></i> Medals 
                            <span class="badge <?=$medalCount > 0 ? 'bg-primary' : 'bg-secondary'?> ms-1"><?=$medalCount?></span>
                        </button>
                    </li>
                    <!--<li class="nav-item" role="presentation">
                        <button class="nav-link" id="awards-tab" data-bs-toggle="tab" data-bs-target="#awards" type="button">
                            <i class="fa fa-trophy me-1"></i> Awards 
                            <span class="badge <?=$awardCount > 0 ? 'bg-primary' : 'bg-secondary'?> ms-1"><?=$awardCount?></span>
                        </button>
                    </li>-->
                    
                    <!-- Development Group -->
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="courses-tab" data-bs-toggle="tab" data-bs-target="#courses" type="button">
                            <i class="fa fa-graduation-cap me-1"></i> Courses 
                            <span class="badge <?=($courseCount + $educationCount) > 0 ? 'bg-primary' : 'bg-secondary'?> ms-1"><?=($courseCount + $educationCount)?></span>
                        </button>
                    </li>
                    
                    <!-- Service Group -->
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="operations-tab" data-bs-toggle="tab" data-bs-target="#operations" type="button">
                            <i class="fa fa-crosshairs me-1"></i> Operations 
                            <span class="badge <?=$operationCount > 0 ? 'bg-primary' : 'bg-secondary'?> ms-1"><?=$operationCount?></span>
                        </button>
                    </li>
                    <!--<li class="nav-item" role="presentation">
                        <button class="nav-link" id="deployments-tab" data-bs-toggle="tab" data-bs-target="#deployments" type="button">
                            <i class="fa fa-globe me-1"></i> Deployments 
                            <span class="badge <?=$deploymentCount > 0 ? 'bg-primary' : 'bg-secondary'?> ms-1"><?=$deploymentCount?></span>
                        </button>
                    </li>-->
                    
                    <!-- Records Group -->
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="disciplinary-tab" data-bs-toggle="tab" data-bs-target="#disciplinary" type="button">
                            <i class="fa fa-exclamation-triangle me-1"></i> Disciplinary 
                            <span class="badge <?=$disciplinaryCount > 0 ? 'bg-warning text-dark' : 'bg-secondary'?> ms-1"><?=$disciplinaryCount?></span>
                        </button>
                    </li>
                </ul>
            </div>
            <div class="card-body">
                <div class="tab-content" id="profileTabsContent">
                    <!-- ==================== OPERATIONS TAB ==================== -->
                    <div class="tab-pane fade" id="operations" role="tabpanel">
                        <?php if (!empty($operations)): ?>
                            <div class="table-responsive">
                                <table class="table table-hover table-bordered">
                                    <thead class="table-light">
                                        <tr>
                                            <th>#</th>
                                            <th>Operation Name</th>
                                            <th>Type</th>
                                            <th>Location</th>
                                            <th>Start Date</th>
                                            <th>End Date</th>
                                            <th>Role</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($operations as $idx => $op): ?>
                                            <tr>
                                                <td><?=$idx + 1?></td>
                                                <td><strong><?=htmlspecialchars($op->operationName ?? 'N/A')?></strong></td>
                                                <td><?=htmlspecialchars($op->operationType ?? 'N/A')?></td>
                                                <td><?=htmlspecialchars($op->operationLocation ?? 'N/A')?></td>
                                                <td><?=!empty($op->startDate) ? date('d M Y', strtotime($op->startDate)) : 'N/A'?></td>
                                                <td><?=!empty($op->endDate) ? date('d M Y', strtotime($op->endDate)) : 'Ongoing'?></td>
                                                <td><?=htmlspecialchars($op->role ?? 'N/A')?></td>
                                                <td>
                                                    <?php
                                                    $status = $op->status ?? '';
                                                    // Use switch for compatibility with older PHP versions
                                                    switch (strtolower($status)) {
                                                        case 'completed':
                                                            $statusClass = 'bg-success';
                                                            break;
                                                        case 'active':
                                                            $statusClass = 'bg-primary';
                                                            break;
                                                        case 'cancelled':
                                                            $statusClass = 'bg-danger';
                                                            break;
                                                        default:
                                                            $statusClass = 'bg-secondary';
                                                            break;
                                                    }
                                                    ?>
                                                    <span class="badge <?=$statusClass?>">
                                                        <?=htmlspecialchars($status ?: 'N/A')?>
                                                    </span>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-info">
                                <i class="fa fa-info-circle"></i> No operations participation recorded.
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- ==================== PROMOTIONS TAB ==================== -->
                    <div class="tab-pane fade show active" id="promotions" role="tabpanel">
                        <?php if (!empty($promotions) && count($promotions) > 0): ?>
                            <div class="table-responsive">
                                <table class="table table-hover table-bordered">
                                    <thead class="table-light">
                                        <tr>
                                            <th>#</th>
                                            <th>From Rank</th>
                                            <th>To Rank</th>
                                            <th>Promotion Date</th>
                                            <th>Days in Rank</th>
                                            <th>Remarks</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($promotions as $idx => $prom): ?>
                                            <tr>
                                                <td><?=$idx + 1?></td>
                                                <td><?=htmlspecialchars($prom->previousRankName ?? 'N/A')?></td>
                                                <td><strong><?=htmlspecialchars($prom->newRankName ?? 'N/A')?></strong></td>
                                                <td><?=!empty($prom->dateFrom) ? date('d M Y', strtotime($prom->dateFrom)) : 'N/A'?></td>
                                                <td><?=number_format($prom->days_in_rank ?? 0)?> days</td>
                                                <td><?=htmlspecialchars($prom->remarks ?? '')?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-info">
                                <i class="fa fa-info-circle"></i> No promotion records found.
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- ==================== MEDALS TAB ==================== -->
                    <div class="tab-pane fade" id="medals" role="tabpanel">
                        <?php if (!empty($medals)): ?>
                            <div class="table-responsive">
                                <table class="table table-hover table-bordered">
                                    <thead class="table-light">
                                        <tr>
                                            <th>#</th>
                                            <th>Medal Name</th>
                                            <th>Award Date</th>
                                            <th>Citation</th>
                                            <th>Awarded By</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($medals as $idx => $medal): ?>
                                            <tr>
                                                <td><?=$idx + 1?></td>
                                                <td><strong><?=htmlspecialchars($medal->medalName ?? 'N/A')?></strong></td>
                                                <td><?=!empty($medal->award_date) ? date('d M Y', strtotime($medal->award_date)) : 'N/A'?></td>
                                                <td><?=htmlspecialchars($medal->citation ?? '')?></td>
                                                <td><?=htmlspecialchars($medal->awarded_by ?? '')?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-info">
                                <i class="fa fa-info-circle"></i> No medals awarded.
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- ==================== COURSES TAB (COMBINED) ==================== -->
                    <div class="tab-pane fade" id="courses" role="tabpanel">
                        <?php if (!empty($courses) || !empty($education)): ?>
                            <div class="table-responsive">
                                <table class="table table-hover table-bordered">
                                    <thead class="table-light">
                                        <tr>
                                            <th>#</th>
                                            <th>Institution/Course</th>
                                            <th>Qualification</th>
                                            <th>Type & Level</th>
                                            <th>Year Started</th>
                                            <th>Year Completed</th>
                                            <th>Grade</th>
                                            <th>Result</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php $idx = 1; ?>
                                        <?php foreach ($courses as $course): ?>
                                            <tr>
                                                <td><?=$idx++?></td>
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
                                                        <span class="badge bg-<?=$course->courseType == 'Military' ? 'success' : 'info'?>"><?=htmlspecialchars($course->courseType)?></span>
                                                    <?php endif; ?>
                                                    <?php if (!empty($course->courseLevel)): ?>
                                                        <br><small class="text-muted"><?=htmlspecialchars($course->courseLevel)?></small>
                                                    <?php endif; ?>
                                                    <?php if (!empty($course->institutionType)): ?>
                                                        <br><small class="badge bg-secondary"><?=htmlspecialchars($course->institutionType)?></small>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?=htmlspecialchars($course->yearStarted ?? 'N/A')?></td>
                                                <td>
                                                    <?=htmlspecialchars($course->yearCompleted ?? 'N/A')?>
                                                    <?php if ($course->isHighest == 1): ?>
                                                        <br><span class="badge bg-warning text-dark"><i class="fa fa-star"></i> Highest</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?=htmlspecialchars($course->grade ?? 'N/A')?></td>
                                                <td>
                                                    <?php 
                                                        $resultClass = 'secondary';
                                                        $resultText = $course->result ?? 'N/A';
                                                        if (stripos($resultText, 'pass') !== false || stripos($resultText, 'distinction') !== false) {
                                                            $resultClass = 'success';
                                                        } elseif (stripos($resultText, 'fail') !== false) {
                                                            $resultClass = 'danger';
                                                        }
                                                    ?>
                                                    <span class="badge bg-<?=$resultClass?>"><?=htmlspecialchars($resultText)?></span>
                                                </td>
                                                <td>
                                                    <?php if (!empty($course->yearCompleted)): ?>
                                                        <span class="badge bg-success">Completed</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-warning">In Progress</span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                        <?php foreach ($education as $edu): ?>
                                            <tr>
                                                <td><?=$idx++?></td>
                                                <td><?=htmlspecialchars($edu->institution ?? 'N/A')?></td>
                                                <td><strong><?=htmlspecialchars($edu->qualification ?? 'N/A')?></strong></td>
                                                <td><span class="badge bg-info">Education</span></td>
                                                <td><?=htmlspecialchars($edu->year_started ?? 'N/A')?></td>
                                                <td><?=htmlspecialchars($edu->year_completed ?? 'N/A')?></td>
                                                <td><?=htmlspecialchars($edu->grade_obtained ?? 'N/A')?></td>
                                                <td>-</td>
                                                <td><span class="badge bg-info">Education Record</span></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-info">
                                <i class="fa fa-info-circle"></i> No courses or education records found.
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- ==================== DEPLOYMENTS TAB ==================== -->
                    <div class="tab-pane fade" id="deployments" role="tabpanel">
                        <?php if (!empty($deployments)): ?>
                            <div class="table-responsive">
                                <table class="table table-hover table-bordered">
                                    <thead class="table-light">
                                        <tr>
                                            <th>#</th>
                                            <th>Mission/Operation</th>
                                            <th>Type</th>
                                            <th>Location</th>
                                            <th>Start Date</th>
                                            <th>End Date</th>
                                            <th>Duration</th>
                                            <th>Role</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($deployments as $idx => $dep): ?>
                                            <?php
                                            $duration = '';
                                            if (!empty($dep->dateFrom)) {
                                                $start = strtotime($dep->dateFrom);
                                                $end = !empty($dep->dateTo) ? strtotime($dep->dateTo) : time();
                                                $days = floor(($end - $start) / (60 * 60 * 24));
                                                $months = floor($days / 30);
                                                $duration = $months > 0 ? $months . ' months' : $days . ' days';
                                            }
                                            ?>
                                            <tr>
                                                <td><?=$idx + 1?></td>
                                                <td><strong><?=htmlspecialchars($dep->operationName ?? $dep->deployment_name ?? 'N/A')?></strong></td>
                                                <td><?=htmlspecialchars($dep->mission_type ?? 'N/A')?></td>
                                                <td>
                                                    <?=htmlspecialchars($dep->operationLocation ?? $dep->location ?? 'N/A')?>
                                                    <?php if (!empty($dep->country)): ?>
                                                        <small class="text-muted">(<?=htmlspecialchars($dep->country)?>)</small>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?=!empty($dep->dateFrom) ? date('d M Y', strtotime($dep->dateFrom)) : 'N/A'?></td>
                                                <td><?=!empty($dep->dateTo) ? date('d M Y', strtotime($dep->dateTo)) : 'Ongoing'?></td>
                                                <td><?=$duration?></td>
                                                <td><?=htmlspecialchars($dep->role ?? 'N/A')?></td>
                                                <td>
                                                    <?php
                                                    $status = $dep->status ?? '';
                                                    switch (strtolower($status)) {
                                                        case 'completed':
                                                            $statusClass = 'bg-success';
                                                            break;
                                                        case 'active':
                                                        case 'ongoing':
                                                            $statusClass = 'bg-primary';
                                                            break;
                                                        case 'cancelled':
                                                            $statusClass = 'bg-danger';
                                                            break;
                                                        default:
                                                            $statusClass = 'bg-secondary';
                                                            break;
                                                    }
                                                    ?>
                                                    <span class="badge <?=$statusClass?>">
                                                        <?=htmlspecialchars($status ?: 'N/A')?>
                                                    </span>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-info">
                                <i class="fa fa-info-circle"></i> No deployment records found.
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- ==================== POSTINGS TAB (from staff_appointment) ==================== -->
                    <div class="tab-pane fade" id="postings" role="tabpanel">
                        <?php if (!empty($postings)): ?>
                            <div class="table-responsive">
                                <table class="table table-hover table-bordered">
                                    <thead class="table-light">
                                        <tr>
                                            <th>#</th>
                                            <th>Unit</th>
                                            <th>Location</th>
                                            <th>Rank</th>
                                            <th>Appointment/Role</th>
                                            <th>Start Date</th>
                                            <th>End Date</th>
                                            <th>Duration</th>
                                            <th>Order Ref</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($postings as $idx => $post): ?>
                                            <?php
                                            // Calculate duration
                                            $duration = '';
                                            if (!empty($post->startDate)) {
                                                $start = strtotime($post->startDate);
                                                $end = !empty($post->endDate) ? strtotime($post->endDate) : time();
                                                $months = $post->durationMonths ?? floor(($end - $start) / (60 * 60 * 24 * 30.44));
                                                $duration = $months . ' months';
                                            }
                                            ?>
                                            <tr>
                                                <td><?=$idx + 1?></td>
                                                <td>
                                                    <strong><?=htmlspecialchars($post->unit_name ?? 'N/A')?></strong>
                                                </td>
                                                <td><?=htmlspecialchars($post->location ?? $post->unit_location ?? 'N/A')?></td>
                                                <td>
                                                    <?php if (!empty($post->rank_abbr)): ?>
                                                        <span class="badge bg-info">
                                                            <?=htmlspecialchars($post->rank_abbr)?>
                                                        </span>
                                                    <?php else: ?>
                                                        N/A
                                                    <?php endif; ?>
                                                </td>
                                                <td><?=htmlspecialchars($post->appointment ?? 'N/A')?></td>
                                                <td><?=!empty($post->startDate) ? date('d M Y', strtotime($post->startDate)) : 'N/A'?></td>
                                                <td>
                                                    <?php if (!empty($post->endDate)): ?>
                                                        <?=date('d M Y', strtotime($post->endDate))?>
                                                    <?php else: ?>
                                                        <span class="badge bg-success">Current</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?=$duration ?: 'N/A'?></td>
                                                <td><?=htmlspecialchars($post->posting_order_reference ?? '-')?></td>
                                            </tr>
                                            <?php if (!empty($post->remarks)): ?>
                                                <tr class="table-light">
                                                    <td colspan="9">
                                                        <small><strong>Remarks:</strong> <?=htmlspecialchars($post->remarks)?></small>
                                                    </td>
                                                </tr>
                                            <?php endif; ?>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-info">
                                <i class="fa fa-info-circle"></i> No posting history found.
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- ==================== AWARDS TAB ==================== -->
                    <div class="tab-pane fade" id="awards" role="tabpanel">
                        <?php if (!empty($awards)): ?>
                            <div class="table-responsive">
                                <table class="table table-hover table-bordered">
                                    <thead class="table-light">
                                        <tr>
                                            <th>#</th>
                                            <th>Award Type</th>
                                            <th>Award Name</th>
                                            <th>Awarded By</th>
                                            <th>Date</th>
                                            <th>Citation</th>
                                            <th>Certificate #</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($awards as $idx => $award): ?>
                                            <tr>
                                                <td><?=$idx + 1?></td>
                                                <td>
                                                    <?php
                                                    $awardType = strtolower($award->award_type ?? '');
                                                    switch ($awardType) {
                                                        case 'commendation':
                                                            $typeClass = 'bg-success';
                                                            break;
                                                        case 'letter of appreciation':
                                                            $typeClass = 'bg-info';
                                                            break;
                                                        case 'certificate':
                                                            $typeClass = 'bg-primary';
                                                            break;
                                                        case 'plaque':
                                                            $typeClass = 'bg-warning text-dark';
                                                            break;
                                                        default:
                                                            $typeClass = 'bg-secondary';
                                                            break;
                                                    }
                                                    ?>
                                                    <span class="badge <?=$typeClass?>">
                                                        <?=htmlspecialchars($award->award_type ?? 'N/A')?>
                                                    </span>
                                                </td>
                                                <td><strong><?=htmlspecialchars($award->award_name ?? 'N/A')?></strong></td>
                                                <td><?=htmlspecialchars($award->awarded_by ?? 'N/A')?></td>
                                                <td><?=!empty($award->createdAt) ? date('d M Y', strtotime($award->createdAt)) : 'N/A'?></td>
                                                <td>
                                                    <?php if (!empty($award->citation)): ?>
                                                        <small><?=htmlspecialchars(substr($award->citation, 0, 100))?><?=strlen($award->citation) > 100 ? '...' : ''?></small>
                                                    <?php else: ?>
                                                        -
                                                    <?php endif; ?>
                                                </td>
                                                <td><?=htmlspecialchars($award->certificateNumber ?? '-')?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-info">
                                <i class="fa fa-info-circle"></i> No awards or commendations found.
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- ==================== DISCIPLINARY TAB ==================== -->
                    <div class="tab-pane fade" id="disciplinary" role="tabpanel">
                        <?php if (defined('ARMIS_ADMIN_BRANCH') && ARMIS_ADMIN_BRANCH): ?>
                            <?php if (!empty($disciplinary)): ?>
                                <div class="alert alert-warning">
                                    <i class="fa fa-lock"></i> <strong>Confidential Information:</strong> This section contains sensitive disciplinary records. Handle with appropriate discretion.
                                </div>
                                <div class="table-responsive">
                                    <table class="table table-hover table-bordered">
                                        <thead class="table-light">
                                            <tr>
                                                <th>#</th>
                                                <th>Incident Date</th>
                                                <th>Incident Type</th>
                                                <th>Severity</th>
                                                <th>Description</th>
                                                <th>Action Taken</th>
                                                <th>Status</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($disciplinary as $idx => $disc): ?>
                                                <tr>
                                                    <td><?=$idx + 1?></td>
                                                    <td><?=!empty($disc->incident_date) ? date('d M Y', strtotime($disc->incident_date)) : 'N/A'?></td>
                                                    <td><?=htmlspecialchars($disc->incident_type ?? 'N/A')?></td>
                                                    <td>
                                                        <?php
                                                        $sev = strtolower($disc->severity ?? '');
                                                        switch ($sev) {
                                                            case 'minor':
                                                                $severityClass = 'bg-warning text-dark';
                                                                break;
                                                            case 'major':
                                                                $severityClass = 'bg-danger';
                                                                break;
                                                            case 'severe':
                                                                $severityClass = 'bg-dark';
                                                                break;
                                                            default:
                                                                $severityClass = 'bg-secondary';
                                                                break;
                                                        }
                                                        ?>
                                                        <span class="badge <?=$severityClass?>">
                                                            <?=htmlspecialchars($disc->severity ?? 'N/A')?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <small><?=htmlspecialchars(substr($disc->description ?? '', 0, 80))?><?=strlen($disc->description ?? '') > 80 ? '...' : ''?></small>
                                                    </td>
                                                    <td>
                                                        <small><?=htmlspecialchars(substr($disc->action_taken ?? '', 0, 60))?><?=strlen($disc->action_taken ?? '') > 60 ? '...' : ''?></small>
                                                    </td>
                                                    <td>
                                                        <?php
                                                        $discStatus = strtolower($disc->status ?? '');
                                                        switch ($discStatus) {
                                                            case 'resolved':
                                                                $statusClass = 'bg-success';
                                                                break;
                                                            case 'pending':
                                                                $statusClass = 'bg-warning text-dark';
                                                                break;
                                                            case 'under investigation':
                                                                $statusClass = 'bg-info';
                                                                break;
                                                            default:
                                                                $statusClass = 'bg-secondary';
                                                                break;
                                                        }
                                                        ?>
                                                        <span class="badge <?=$statusClass?>">
                                                            <?=htmlspecialchars($disc->status ?? 'N/A')?>
                                                        </span>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <div class="alert alert-success">
                                    <i class="fa fa-check-circle"></i> No disciplinary records found. Clean record.
                                </div>
                            <?php endif; ?>
                        <?php else: ?>
                            <div class="alert alert-danger">
                                <i class="fa fa-lock"></i> <strong>Access Denied:</strong> You do not have permission to view disciplinary records.
                            </div>
                        <?php endif; ?>
                    </div>

                </div>
            </div>
        </div>

        <!-- ==================== REMARKS SECTION ==================== -->
        <?php if (!empty($staff->remarks)): ?>
            <div class="card mt-4 shadow-sm">
                <div class="card-header bg-info text-white">
                    <h5 class="mb-0"><i class="fa fa-comment"></i> Remarks</h5>
                </div>
                <div class="card-body">
                    <p class="mb-0"><?=nl2br(htmlspecialchars($staff->remarks))?></p>
                </div>
            </div>
        <?php endif; ?>

        <!-- Back Button -->
        <div class="mt-4 no-print">
            <a href="edit_staff.php" class="btn btn-secondary">
                <i class="fa fa-arrow-left"></i> Back to Staff Management
            </a>
        </div>

    </div>
</div>

<script>
// Handle image loading errors
function handleImageError(img) {
    img.onerror = null; // Prevent infinite loop
    img.src = '/Armis2/logo.png';
}

// Initialize Bootstrap tooltips if available
document.addEventListener('DOMContentLoaded', function() {
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
});
</script>

<?php include dirname(__DIR__) . '/shared/footer.php'; ?>
