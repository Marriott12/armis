<?php
define('ARMIS_ADMIN_BRANCH', true);
define('ARMIS_DEVELOPMENT', true);

require_once dirname(__DIR__) . '/shared/database_connection.php';

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

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    die('<div class="alert alert-danger">Invalid staff ID.</div>');
}

// ==================== FETCH COMPREHENSIVE STAFF DATA ====================

// Main staff data with calculated fields
$stmt = $pdo->prepare("
    SELECT 
        s.*, 
        r.name AS rankName,
        r.abbreviation AS rankAbbr,
        u.name AS unitName,
        c.name AS corpsName,
        TIMESTAMPDIFF(YEAR, s.attestDate, CURDATE()) as years_of_service,
        TIMESTAMPDIFF(YEAR, s.DOB, CURDATE()) as age
    FROM staff s 
    LEFT JOIN ranks r ON s.rank_id = r.id 
    LEFT JOIN units u ON s.unit_id = u.id 
    LEFT JOIN corps c ON s.corps_id = c.id
    WHERE s.id = ? 
    LIMIT 1
");
$stmt->execute([$id]);
$staff = $stmt->fetch(PDO::FETCH_OBJ);

if (!$staff) {
    die('<div class="alert alert-danger">Staff member not found.</div>');
}

// ==================== FETCH EDUCATION DATA ====================
$education = [];
try {
    $eduStmt = $pdo->prepare("
        SELECT * FROM staff_education 
        WHERE staff_id = ? 
        ORDER BY year_completed DESC, year_started DESC
    ");
    $eduStmt->execute([$id]);
    $education = $eduStmt->fetchAll(PDO::FETCH_OBJ);
} catch (PDOException $e) {
    // Table may not exist yet, ignore
}

// ==================== FETCH SKILLS DATA ====================
$skills = [];
try {
    $skillStmt = $pdo->prepare("
        SELECT * FROM staff_skills 
        WHERE staff_id = ? 
        ORDER BY skill_level DESC, skill_name ASC
    ");
    $skillStmt->execute([$id]);
    $skills = $skillStmt->fetchAll(PDO::FETCH_OBJ);
} catch (PDOException $e) {
    // Table may not exist yet, ignore
}

// ==================== FETCH OPERATIONS DATA ====================
$operations = [];
try {
    $opStmt = $pdo->prepare("
        SELECT * FROM staff_operations 
        WHERE staff_id = ? 
        ORDER BY start_date DESC
    ");
    $opStmt->execute([$id]);
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
            d.start_date AS date_from,
            d.end_date AS date_to,
            d.deployment_status AS status,
            d.role_during_deployment AS role
        FROM staff_deployments d
        WHERE d.staff_id = ?
        ORDER BY d.start_date DESC
    ");
    $deploymentStmt->execute([$id]);
    $deployments = $deploymentStmt->fetchAll(PDO::FETCH_OBJ);
} catch (PDOException $e) {
    // Table may not exist yet, ignore
}

// ==================== FETCH PROMOTIONS DATA ====================
$promotions = [];
$promStmt = $pdo->prepare("
    SELECT 
        p.*, 
        r.name AS newRankName,
        r.abbreviation AS newRankAbbr,
        IFNULL(pr.name, 'N/A') AS previousRankName,
        IFNULL(pr.abbreviation, 'N/A') AS previousRankAbbr,
        DATEDIFF(IFNULL(p.date_to, CURDATE()), p.date_from) as days_in_rank
    FROM staff_promotions p 
    LEFT JOIN ranks r ON p.new_rank = r.id
    LEFT JOIN ranks pr ON p.current_rank = pr.id
    WHERE p.staff_id = ? 
    ORDER BY p.date_from DESC
");
$promStmt->execute([$id]);
$promotions = $promStmt->fetchAll(PDO::FETCH_OBJ);

// ==================== FETCH MEDALS DATA ====================
$medals = [];
$medalStmt = $pdo->prepare("
    SELECT 
        m.*, 
        mm.name AS medalName,
        mm.description AS medalDescription
    FROM staff_medals m 
    LEFT JOIN medals mm ON m.medal_id = mm.id 
    WHERE m.staff_id = ? 
    ORDER BY m.award_date DESC
");
$medalStmt->execute([$id]);
$medals = $medalStmt->fetchAll(PDO::FETCH_OBJ);

// ==================== FETCH COURSES DATA ====================
$courses = [];
$courseStmt = $pdo->prepare("
    SELECT 
        c.*, 
        cc.name AS courseName
    FROM staff_courses c 
    LEFT JOIN courses cc ON c.course_id = cc.id 
    WHERE c.staff_id = ? 
    ORDER BY c.end_date DESC
");
$courseStmt->execute([$id]);
$courses = $courseStmt->fetchAll(PDO::FETCH_OBJ);

// ==================== FETCH POSTING HISTORY (from staff_appointment) ====================
$postings = [];
try {
    $postingStmt = $pdo->prepare("
        SELECT 
            sa.*,
            u.name AS unit_name,
            u.location AS unit_location,
            r.name AS rank_name,
            r.abbreviation AS rank_abbr
        FROM staff_appointment sa
        LEFT JOIN units u ON sa.unit_id = u.id
        LEFT JOIN ranks r ON sa.rank_id = r.id
        WHERE sa.staff_id = ?
        ORDER BY sa.start_date DESC
    ");
    $postingStmt->execute([$id]);
    $postings = $postingStmt->fetchAll(PDO::FETCH_OBJ);
} catch (Exception $e) {
    error_log("Error fetching postings from staff_appointment: " . $e->getMessage());
}

// ==================== FETCH AWARDS & COMMENDATIONS ====================
$awards = [];
try {
    $awardStmt = $pdo->prepare("
        SELECT * FROM staff_awards 
        WHERE staff_id = ? 
        ORDER BY created_at DESC
    ");
    $awardStmt->execute([$id]);
    $awards = $awardStmt->fetchAll(PDO::FETCH_OBJ);
} catch (Exception $e) {
    error_log("Error fetching awards: " . $e->getMessage());
}

// ==================== FETCH DISCIPLINARY RECORDS ====================
$disciplinary = [];
try {
    $disciplinaryStmt = $pdo->prepare("
        SELECT * FROM staff_disciplinary 
        WHERE staff_id = ? 
        ORDER BY incident_date DESC
    ");
    $disciplinaryStmt->execute([$id]);
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
                    <a href="edit_staff.php?svcNo=<?=urlencode($staff->service_number)?>" class="btn btn-warning">
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
                    <?=htmlspecialchars(($staff->rankAbbr ?? '') . ' ' . $staff->first_name . ' ' . $staff->last_name)?>
                    <span class="badge bg-light text-dark ms-2"><?=htmlspecialchars($staff->titles ?? 'N/A')?></span>
                </h3>
            </div>
            <div class="card-body row">
                <!-- Photo Column -->
                <div class="col-md-3 text-center">
                    <?php if (!empty($staff->profile_photo) && file_exists(dirname(__DIR__) . '/' . $staff->profile_photo)): ?>
                        <img src="/Armis2/<?=htmlspecialchars($staff->profile_photo)?>" 
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
                                <td><strong><?=htmlspecialchars($staff->service_number ?? 'N/A')?></strong></td>
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
                                <td><?=htmlspecialchars($staff->category ?? 'N/A')?></td>
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
                            <span class="badge <?=$courseCount > 0 ? 'bg-primary' : 'bg-secondary'?> ms-1"><?=$courseCount?></span>
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="education-tab" data-bs-toggle="tab" data-bs-target="#education" type="button">
                            <i class="fa fa-book me-1"></i> Education 
                            <span class="badge <?=$educationCount > 0 ? 'bg-primary' : 'bg-secondary'?> ms-1"><?=$educationCount?></span>
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="skills-tab" data-bs-toggle="tab" data-bs-target="#skills" type="button">
                            <i class="fa fa-cogs me-1"></i> Skills 
                            <span class="badge <?=$skillCount > 0 ? 'bg-primary' : 'bg-secondary'?> ms-1"><?=$skillCount?></span>
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
                    
                    <!-- ==================== PROMOTIONS TAB ==================== -->
                    <div class="tab-pane fade show active" id="promotions" role="tabpanel">
                        <?php if (!empty($promotions)): ?>
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
                                                <td><?=!empty($prom->date_from) ? date('d M Y', strtotime($prom->date_from)) : 'N/A'?></td>
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

                    <!-- ==================== COURSES TAB ==================== -->
                    <div class="tab-pane fade" id="courses" role="tabpanel">
                        <?php if (!empty($courses)): ?>
                            <div class="table-responsive">
                                <table class="table table-hover table-bordered">
                                    <thead class="table-light">
                                        <tr>
                                            <th>#</th>
                                            <th>Course Name</th>
                                            <th>Start Date</th>
                                            <th>End Date</th>
                                            <th>Duration</th>
                                            <th>Grade</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($courses as $idx => $course): ?>
                                            <tr>
                                                <td><?=$idx + 1?></td>
                                                <td><strong><?=htmlspecialchars($course->courseName ?? 'N/A')?></strong></td>
                                                <td><?=!empty($course->start_date) ? date('d M Y', strtotime($course->start_date)) : 'N/A'?></td>
                                                <td><?=!empty($course->end_date) ? date('d M Y', strtotime($course->end_date)) : 'N/A'?></td>
                                                <td><?=htmlspecialchars($course->courseDuration ?? 'N/A')?></td>
                                                <td><?=htmlspecialchars($course->grade ?? 'N/A')?></td>
                                                <td>
                                                    <span class="badge bg-<?=strcasecmp($course->status ?? '','Completed')===0?'success':'warning'?>">
                                                        <?=htmlspecialchars($course->status ?? 'N/A')?>
                                                    </span>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-info">
                                <i class="fa fa-info-circle"></i> No courses completed.
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- ==================== EDUCATION TAB ==================== -->
                    <div class="tab-pane fade" id="education" role="tabpanel">
                        <?php if (!empty($education)): ?>
                            <div class="table-responsive">
                                <table class="table table-hover table-bordered">
                                    <thead class="table-light">
                                        <tr>
                                            <th>#</th>
                                            <th>Institution</th>
                                            <th>Qualification</th>
                                            <th>Year Started</th>
                                            <th>Year Completed</th>
                                            <th>Grade</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($education as $idx => $edu): ?>
                                            <tr>
                                                <td><?=$idx + 1?></td>
                                                <td><?=htmlspecialchars($edu->institution ?? 'N/A')?></td>
                                                <td><strong><?=htmlspecialchars($edu->qualification ?? 'N/A')?></strong></td>
                                                <td><?=htmlspecialchars($edu->year_started ?? 'N/A')?></td>
                                                <td><?=htmlspecialchars($edu->year_completed ?? 'N/A')?></td>
                                                <td><?=htmlspecialchars($edu->grade_obtained ?? 'N/A')?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-info">
                                <i class="fa fa-info-circle"></i> No education records found.
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- ==================== SKILLS TAB ==================== -->
                    <div class="tab-pane fade" id="skills" role="tabpanel">
                        <?php if (!empty($skills)): ?>
                            <div class="table-responsive">
                                <table class="table table-hover table-bordered">
                                    <thead class="table-light">
                                        <tr>
                                            <th>#</th>
                                            <th>Skill Name</th>
                                            <th>Level</th>
                                            <th>Category</th>
                                            <th>Certification</th>
                                            <th>Valid Until</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($skills as $idx => $skill): ?>
                                            <tr>
                                                <td><?=$idx + 1?></td>
                                                <td><strong><?=htmlspecialchars($skill->skill_name ?? 'N/A')?></strong></td>
                                                <td>
                                                    <?php
                                                    $level = $skill->skill_level ?? '';
                                                    $badgeClass = match(strtolower($level)) {
                                                        'expert' => 'bg-danger',
                                                        'advanced' => 'bg-success',
                                                        'intermediate' => 'bg-primary',
                                                        'beginner' => 'bg-info',
                                                        default => 'bg-secondary'
                                                    };
                                                    ?>
                                                    <span class="badge <?=$badgeClass?> badge-skill-level">
                                                        <?=htmlspecialchars($level)?>
                                                    </span>
                                                </td>
                                                <td><?=htmlspecialchars($skill->skill_category ?? 'N/A')?></td>
                                                <td><?=htmlspecialchars($skill->certification ?? 'N/A')?></td>
                                                <td>
                                                    <?php if (!empty($skill->certification_expiry)): ?>
                                                        <?=date('d M Y', strtotime($skill->certification_expiry))?>
                                                        <?php
                                                        $expiryTime = strtotime($skill->certification_expiry);
                                                        $now = time();
                                                        if ($expiryTime < $now): ?>
                                                            <span class="badge bg-danger ms-1">Expired</span>
                                                        <?php elseif ($expiryTime < strtotime('+3 months')): ?>
                                                            <span class="badge bg-warning ms-1">Expiring Soon</span>
                                                        <?php endif; ?>
                                                    <?php else: ?>
                                                        N/A
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-info">
                                <i class="fa fa-info-circle"></i> No skills registered.
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- ==================== OPERATIONS TAB ==================== -->
                    <div class="tab-pane fade" id="operations" role="tabpanel">
                        <?php if (!empty($operations)): ?>
                            <div class="table-responsive">
                                <table class="table table-hover table-bordered">
                                    <thead class="table-light">
                                        <tr>
                                            <th>#</th>
                                            <th>Operation Name</th>
                                            <th>Role</th>
                                            <th>Start Date</th>
                                            <th>End Date</th>
                                            <th>Duration</th>
                                            <th>Performance</th>
                                            <th>Remarks</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($operations as $idx => $op): ?>
                                            <?php
                                            $duration = '';
                                            if (!empty($op->start_date)) {
                                                $start = strtotime($op->start_date);
                                                $end = !empty($op->end_date) ? strtotime($op->end_date) : time();
                                                $days = floor(($end - $start) / (60 * 60 * 24));
                                                $duration = $days . ' days';
                                            }
                                            ?>
                                            <tr>
                                                <td><?=$idx + 1?></td>
                                                <td><strong><?=htmlspecialchars($op->operation_name ?? 'N/A')?></strong></td>
                                                <td><?=htmlspecialchars($op->role ?? 'N/A')?></td>
                                                <td><?=!empty($op->start_date) ? date('d M Y', strtotime($op->start_date)) : 'N/A'?></td>
                                                <td><?=!empty($op->end_date) ? date('d M Y', strtotime($op->end_date)) : 'Ongoing'?></td>
                                                <td><?=$duration?></td>
                                                <td>
                                                    <?php if (!empty($op->performance_rating)): ?>
                                                        <span class="badge bg-<?=$op->performance_rating >= 4 ? 'success' : ($op->performance_rating >= 3 ? 'primary' : 'warning')?>">
                                                            <?=$op->performance_rating?>/5
                                                        </span>
                                                    <?php else: ?>
                                                        N/A
                                                    <?php endif; ?>
                                                </td>
                                                <td><?=htmlspecialchars($op->remarks ?? '')?></td>
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
                                            if (!empty($dep->date_from)) {
                                                $start = strtotime($dep->date_from);
                                                $end = !empty($dep->date_to) ? strtotime($dep->date_to) : time();
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
                                                <td><?=!empty($dep->date_from) ? date('d M Y', strtotime($dep->date_from)) : 'N/A'?></td>
                                                <td><?=!empty($dep->date_to) ? date('d M Y', strtotime($dep->date_to)) : 'Ongoing'?></td>
                                                <td><?=$duration?></td>
                                                <td><?=htmlspecialchars($dep->role ?? 'N/A')?></td>
                                                <td>
                                                    <?php
                                                    $status = $dep->status ?? '';
                                                    $statusClass = match(strtolower($status)) {
                                                        'completed' => 'bg-success',
                                                        'active', 'ongoing' => 'bg-primary',
                                                        'cancelled' => 'bg-danger',
                                                        default => 'bg-secondary'
                                                    };
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
                                            if (!empty($post->start_date)) {
                                                $start = strtotime($post->start_date);
                                                $end = !empty($post->end_date) ? strtotime($post->end_date) : time();
                                                $months = $post->duration_months ?? floor(($end - $start) / (60 * 60 * 24 * 30.44));
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
                                                <td><?=!empty($post->start_date) ? date('d M Y', strtotime($post->start_date)) : 'N/A'?></td>
                                                <td>
                                                    <?php if (!empty($post->end_date)): ?>
                                                        <?=date('d M Y', strtotime($post->end_date))?>
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
                                                    $typeClass = match(strtolower($award->award_type ?? '')) {
                                                        'commendation' => 'bg-success',
                                                        'letter of appreciation' => 'bg-info',
                                                        'certificate' => 'bg-primary',
                                                        'plaque' => 'bg-warning text-dark',
                                                        default => 'bg-secondary'
                                                    };
                                                    ?>
                                                    <span class="badge <?=$typeClass?>">
                                                        <?=htmlspecialchars($award->award_type ?? 'N/A')?>
                                                    </span>
                                                </td>
                                                <td><strong><?=htmlspecialchars($award->award_name ?? 'N/A')?></strong></td>
                                                <td><?=htmlspecialchars($award->awarded_by ?? 'N/A')?></td>
                                                <td><?=!empty($award->created_at) ? date('d M Y', strtotime($award->created_at)) : 'N/A'?></td>
                                                <td>
                                                    <?php if (!empty($award->citation)): ?>
                                                        <small><?=htmlspecialchars(substr($award->citation, 0, 100))?><?=strlen($award->citation) > 100 ? '...' : ''?></small>
                                                    <?php else: ?>
                                                        -
                                                    <?php endif; ?>
                                                </td>
                                                <td><?=htmlspecialchars($award->certificate_number ?? '-')?></td>
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
                                                        $severityClass = match(strtolower($disc->severity ?? '')) {
                                                            'minor' => 'bg-warning text-dark',
                                                            'major' => 'bg-danger',
                                                            'severe' => 'bg-dark',
                                                            default => 'bg-secondary'
                                                        };
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
                                                        $statusClass = match(strtolower($disc->status ?? '')) {
                                                            'resolved' => 'bg-success',
                                                            'pending' => 'bg-warning text-dark',
                                                            'under investigation' => 'bg-info',
                                                            default => 'bg-secondary'
                                                        };
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
