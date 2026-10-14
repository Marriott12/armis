<?php
// Include scalability configuration
require_once dirname(__DIR__) . '/config/scalability.php';

// Include session initialization
require_once dirname(__DIR__) . '/shared/session_init.php';

// Include military formatting functions
require_once dirname(__DIR__) . '/shared/military_formatting.php';

// Include RBAC compatibility layer
require_once dirname(__DIR__) . '/shared/rbac_compat.php';
require_once dirname(__DIR__) . '/shared/permissions.php';

// Include database functions for user profile data
require_once dirname(__DIR__) . '/shared/database_connection.php';

// Check if user is logged in
$isLoggedIn = isset($_SESSION['user_id']);
$userName = $_SESSION['name'] ?? '';
$userRank = $_SESSION['rank'] ?? '';
$userRankAbbr = $_SESSION['rank_abbr'] ?? '';
$userFirstName = $_SESSION['fName'] ?? ($_SESSION['fname'] ?? '');
$userMiddleName = $_SESSION['mName'] ?? ($_SESSION['mname'] ?? '');
$userLastName = $_SESSION['lName'] ?? ($_SESSION['lname'] ?? '');
$userRole = $_SESSION['role'] ?? '';
$userCategory = $_SESSION['category'] ?? '';

// Get user's accessible modules for dynamic navigation
$userModules = $isLoggedIn ? (function_exists('getUserModules') ? getUserModules($userRole) : []) : [];
$isSystemAdmin = $isLoggedIn && strtolower((string)$userRole) === 'admin';
if ($isSystemAdmin && !in_array('admin', $userModules, true)) { $userModules[] = 'admin'; }

// Get detailed user profile data from database if logged in
$userProfileData = null;
if ($isLoggedIn && isset($_SESSION['user_id']) && function_exists('getUserProfileData')) {
    $userProfileData = getUserProfileData($_SESSION['user_id']);
    if ($userProfileData && is_array($userProfileData)) {
        // Update session with fresh database data
        $_SESSION['rank'] = $userProfileData['rank_name'] ?? $userRank;
        $_SESSION['rank_abbr'] = $userProfileData['rank_abbr'] ?? $userRankAbbr;
        $_SESSION['category'] = $userProfileData['category'] ?? $userCategory;
        $_SESSION['unit'] = $userProfileData['unit_name'] ?? ($_SESSION['unit'] ?? 'Unknown');
        $_SESSION['svcNo'] = $userProfileData['svcNo'] ?? ($_SESSION['svcNo'] ?? '');
        $_SESSION['email'] = $userProfileData['email'] ?? ($_SESSION['email'] ?? '');

        // Update display variables
        $userRank = $_SESSION['rank'];
        $userRankAbbr = $_SESSION['rank_abbr'];
        $userFirstName = $userProfileData['fName'] ?? $userFirstName;
        $userMiddleName = $userProfileData['mName'] ?? $userMiddleName;
        $userLastName = $userProfileData['lName'] ?? $userLastName;
        $_SESSION['mName'] = $userMiddleName;
    }
}

// Header identity: include the middle-name initial when a middle name exists.
$formattedUserName = '';
if ($isLoggedIn) {
    $formattedUserName = formatMilitaryName(
        $userRank,
        $userRankAbbr,
        $userFirstName,
        $userLastName,
        $userCategory,
        $userMiddleName
    );
}

if (empty($moduleStylesheet) && ($moduleName ?? '') === 'Operations') {
    $moduleStylesheet = '/Armis2/operations/module.css';
}

// Set performance headers with fallback for REQUEST_TIME_FLOAT
$startTime = $_SERVER['REQUEST_TIME_FLOAT'] ?? microtime(true);
header('X-Powered-By: Army Resource Management Information System');
header('X-Response-Time: ' . (microtime(true) - $startTime));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Army Resource Management Information System - Comprehensive military resource management platform">
    <meta name="keywords" content="Army, Resource Management, Military, Administration">
    <meta name="author" content="Army Resource Management Information System Development Team">
    <meta name="robots" content="noindex, nofollow">

    <!-- Performance optimizations -->
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <?php
    // Global CSRF token, available to every page that includes this
    // header. shared/js/csrf-fetch.js reads this meta tag and
    // automatically attaches it to every fetch()/AJAX POST as an
    // X-CSRF-Token header, so individual AJAX call sites don't each
    // need to remember to include it by hand.
    require_once dirname(__DIR__) . '/shared/csrf.php';
    ?>
    <meta name="csrf-token" content="<?= htmlspecialchars(csrf_token(), ENT_QUOTES) ?>">
    <link rel="dns-prefetch" href="//cdn.jsdelivr.net">
    <link rel="dns-prefetch" href="//cdnjs.cloudflare.com">
    <link rel="preconnect" href="https://cdn.jsdelivr.net">
    <link rel="preconnect" href="https://cdnjs.cloudflare.com">

    <title><?php echo isset($pageTitle) ? htmlspecialchars($pageTitle) . ' | Army Resource Management Information System' : 'Army Resource Management Information System'; ?></title>

    <!-- Critical CSS - inline for performance -->
    <style>
        :root {
            --armis-primary: #2c5530;
            --armis-secondary: #8b4513;
            --armis-gold: #ffd700;
            --armis-dark: #1a1a1a;
        }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            /* The padding-top must match the navbar height to avoid content being hidden behind the navbar */
            padding-top: 76px;
            background-color: #f8f9fa;
        }
        .navbar-custom {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            z-index: 1050;
            min-height: 60px;
            background: linear-gradient(135deg, var(--armis-primary) 0%, #3a6b3f 100%);
            box-shadow: 0 2px 10px rgba(0,0,0,0.2);
            padding: 0.5rem 1rem;
        }
        .navbar-brand {
            max-width: 350px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            display: flex;
            align-items: center;
        }
        .system-title, .system-title-short {
            color: var(--armis-gold);
            font-weight: 700;
            font-size: 1.05rem;
            text-shadow: 1px 1px 2px rgba(0,0,0,0.3);
            letter-spacing: 0.5px;
            vertical-align: middle;
            display: inline;
        }
        .system-title-short {
            display: none;
        }
        @media (max-width: 1200px) {
            .navbar-brand {
                max-width: 180px;
            }
            .system-title {
                display: none;
            }
            .system-title-short {
                display: inline !important;
            }
        }
        .dropdown-menu { max-height: 400px; overflow-y: auto; }
        .navbar-custom .nav-link { white-space: nowrap; }
        .navbar-custom .badge { font-size: .72rem; letter-spacing: .2px; }
        @media (max-width: 991.98px) {
            .navbar-custom { padding: .45rem .75rem; }
            .navbar-custom .navbar-collapse { padding-top: .5rem; }
            .navbar-custom .navbar-nav { gap: .15rem; }
        }
        .page-loader {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(255,255,255,0.9);
            z-index: 9999;
            display: flex;
            justify-content: center;
            align-items: center;
        }
        .page-loader.hidden {
            display: none;
        }
    </style>

    <!-- Non-critical CSS loaded asynchronously -->
    <link rel="preload" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" as="style" onload="this.onload=null;this.rel='stylesheet'">
    <link rel="preload" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" as="style" onload="this.onload=null;this.rel='stylesheet'">
    <link rel="preload" href="<?= htmlspecialchars(ScalabilityConfig::assetUrl('/shared/armis-styles.css'), ENT_QUOTES) ?>" as="style" onload="this.onload=null;this.rel='stylesheet'">
    <noscript>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
        <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
        <link href="<?= htmlspecialchars(ScalabilityConfig::assetUrl('/shared/armis-styles.css'), ENT_QUOTES) ?>" rel="stylesheet">
    </noscript>
    <?php if (!empty($moduleStylesheet)): ?>
    <link href="<?= htmlspecialchars($moduleStylesheet, ENT_QUOTES) ?>" rel="stylesheet">
    <?php endif; ?>
    <link rel="icon" type="image/x-icon" href="<?= htmlspecialchars(ScalabilityConfig::assetUrl('/favicon.ico'), ENT_QUOTES) ?>">
    <link rel="apple-touch-icon" href="<?= htmlspecialchars(ScalabilityConfig::assetUrl('/logo.png'), ENT_QUOTES) ?>">

    <!-- Performance monitoring and loader -->
    <script>
        window.performance.mark('armis-page-start');
        window.addEventListener('load', function() {
            setTimeout(function() {
                const loader = document.querySelector('.page-loader');
                if (loader) loader.classList.add('hidden');
                window.performance.mark('armis-page-loaded');
            }, 100);
        });
        // Sidebar toggle function for mobile
        function toggleSidebar() {
            var sidebar = document.querySelector('.sidebar');
            if (sidebar) {
                sidebar.classList.toggle('active');
            }
        }
    </script>
    <script src="<?= htmlspecialchars(ScalabilityConfig::assetUrl('/shared/js/csrf-fetch.js'), ENT_QUOTES) ?>"></script>
    <script src="<?= htmlspecialchars(ScalabilityConfig::assetUrl('/shared/js/notification-bell.js'), ENT_QUOTES) ?>" defer></script>
</head>
<body>
    <!-- Page Loader -->
    <div class="page-loader">
        <div class="text-center">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Loading...</span>
            </div>
        </div>
    </div>

    <!-- Navigation Header: fully session- and permission-driven. Anonymous users see only Login. -->
    <nav class="navbar navbar-expand-lg navbar-dark navbar-custom fixed-top" aria-label="Main navigation">
        <div class="container-fluid">
            <a class="navbar-brand d-flex align-items-center" href="<?= $isLoggedIn ? htmlspecialchars(getRoleDashboardUrl(), ENT_QUOTES) : '/Armis2/login.php' ?>">
                <img src="/Armis2/logo.png" alt="Army Resource Management Information System" height="30" class="me-2" onerror="this.style.display='none'">
                <span class="system-title">Army Resource Management Information System</span>
            </a>

            <?php if ($isLoggedIn): ?>
                <?php
                $currentBranch = function_exists('getUserBranchInfo') ? getUserBranchInfo() : null;
                $roleLabel = function_exists('getRoleDisplayLabel') ? getRoleDisplayLabel($userRole) : strtoupper((string)$userRole);
                $landingUrl = function_exists('getRoleDashboardUrl') ? getRoleDashboardUrl($userRole) : '/Armis2/users/index.php';
                $canViewReports = function_exists('hasPermission') && hasPermission(PERM_VIEW_REPORTS);
                $hasModuleLinks = !empty($userModules);
                $reportLinks = [];
                if ($canViewReports) {
                    if (function_exists('hasModuleAccess') && hasModuleAccess('admin_branch')) {
                        $reportLinks = [
                            ['title'=>'Seniority Roll','url'=>'/Armis2/admin_branch/reports_seniority.php','icon'=>'list-ol'],
                            ['title'=>'By Rank','url'=>'/Armis2/admin_branch/reports_rank.php','icon'=>'medal'],
                            ['title'=>'Unit Reports','url'=>'/Armis2/admin_branch/reports_units.php','icon'=>'building'],
                            ['title'=>'Corps Reports','url'=>'/Armis2/admin_branch/reports_corps.php','icon'=>'shield-alt'],
                        ];
                    } elseif ($currentBranch) {
                        $branchCode = strtolower((string)$currentBranch['code']);
                        $branchReportMap = [
                            'command' => '/Armis2/command/reports.php',
                            'operations' => '/Armis2/operations/reports.php',
                            'training' => '/Armis2/training/index.php',
                            'finance' => '/Armis2/finance/reports.php',
                        ];
                        if (isset($branchReportMap[$branchCode])) {
                            $reportLinks[] = ['title'=>'Branch Reports','url'=>$branchReportMap[$branchCode],'icon'=>'chart-line'];
                        }
                    }
                }
                ?>
                <button class="btn btn-outline-light d-lg-none me-2" type="button" onclick="toggleSidebar()" aria-label="Toggle sidebar">
                    <i class="fas fa-bars"></i>
                </button>
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                    <span class="navbar-toggler-icon"></span>
                </button>

                <div class="collapse navbar-collapse" id="navbarNav">
                    <ul class="navbar-nav align-items-lg-center ms-auto justify-content-end">
                        <li class="nav-item">
                            <a class="nav-link" href="<?= htmlspecialchars($landingUrl, ENT_QUOTES) ?>" title="Main dashboard">
                                <i class="fas fa-home"></i><span class="d-none d-xl-inline ms-1">Dashboard</span>
                            </a>
                        </li>
                        <?php if ($hasModuleLinks): ?>
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="fas fa-th-large"></i> <span class="d-none d-md-inline">Modules</span>
                            </a>
                            <ul class="dropdown-menu">
                                <?php
                                $moduleConfigs = [
                                    'admin'=>['title'=>'System Admin','icon'=>'user-shield','url'=>'/Armis2/admin/index.php','category'=>'System Management'],
                                    'admin_branch'=>['title'=>'Admin Branch','icon'=>'users-cog','url'=>'/Armis2/admin_branch/index.php','category'=>'System Management'],
                                    'command'=>['title'=>'Command','icon'=>'chess-king','url'=>'/Armis2/command/index.php','category'=>'Branches'],
                                    'operations'=>['title'=>'Operations','icon'=>'map-marked-alt','url'=>'/Armis2/operations/index.php','category'=>'Branches'],
                                    'training'=>['title'=>'Training','icon'=>'graduation-cap','url'=>'/Armis2/training/index.php','category'=>'Branches'],
                                    'finance'=>['title'=>'Finance','icon'=>'dollar-sign','url'=>'/Armis2/finance/index.php','category'=>'Branches'],
                                    'ordinance'=>['title'=>'Ordinance','icon'=>'tools','url'=>'/Armis2/ordinance/index.php','category'=>'Branches'],
                                    'users'=>['title'=>'User Account','icon'=>'user-cog','url'=>'/Armis2/users/index.php','category'=>'Account'],
                                ];
                                $groupedModules=[];
                                foreach ($userModules as $module) {
                                    if (!isset($moduleConfigs[$module])) continue;
                                    $cfg=$moduleConfigs[$module];
                                    $groupedModules[$cfg['category']][] = array_merge($cfg,['module'=>$module]);
                                }
                                foreach ($groupedModules as $category=>$modules): ?>
                                    <li><h6 class="dropdown-header"><?= htmlspecialchars($category) ?></h6></li>
                                    <?php foreach ($modules as $module): ?>
                                        <li><a class="dropdown-item" href="<?= htmlspecialchars($module['url'], ENT_QUOTES) ?>"><i class="fas fa-<?= htmlspecialchars($module['icon']) ?> me-2"></i><?= htmlspecialchars($module['title']) ?></a></li>
                                    <?php endforeach; ?>
                                <?php endforeach; ?>
                            </ul>
                        </li>
                        <?php endif; ?>
                        <?php if (!empty($reportLinks)): ?>
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="fas fa-chart-line"></i> <span class="d-none d-md-inline">Reports</span>
                            </a>
                            <ul class="dropdown-menu">
                                <?php foreach ($reportLinks as $report): ?>
                                    <li><a class="dropdown-item" href="<?= htmlspecialchars($report['url'], ENT_QUOTES) ?>"><i class="fas fa-<?= htmlspecialchars($report['icon']) ?> me-2"></i><?= htmlspecialchars($report['title']) ?></a></li>
                                <?php endforeach; ?>
                                <?php if ($userRole === 'admin'): ?>
                                    <li><hr class="dropdown-divider"></li>
                                    <li><a class="dropdown-item" href="/Armis2/admin/health.php"><i class="fas fa-heartbeat me-2"></i>System Health</a></li>
                                <?php endif; ?>
                            </ul>
                        </li>
                        <?php endif; ?>
                    </ul>

                    <ul class="navbar-nav align-items-lg-center">
                        <?php if ($currentBranch): ?>
                        <li class="nav-item d-none d-xl-block me-2">
                            <span class="badge rounded-pill bg-light text-dark border" title="Posted branch">
                                <i class="fas fa-sitemap me-1"></i><?= htmlspecialchars($currentBranch['name']) ?>
                            </span>
                        </li>
                        <?php endif; ?>
                        <li class="nav-item d-none d-md-block me-1">
                            <span class="badge rounded-pill bg-warning text-dark" title="Role">
                                <?= htmlspecialchars($roleLabel) ?>
                            </span>
                        </li>
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle position-relative" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false" id="notificationBellToggle" title="Notifications">
                                <i class="fas fa-bell"></i>
                                <span id="notificationBadge" class="badge rounded-pill bg-danger position-absolute" style="top:0;right:0;font-size:.6rem;display:none;">0</span>
                            </a>
                            <div class="dropdown-menu dropdown-menu-end p-0" id="notificationDropdown" style="width:340px;max-height:420px;overflow-y:auto;">
                                <div class="d-flex justify-content-between align-items-center px-3 py-2 border-bottom"><strong>Notifications</strong><button type="button" class="btn btn-link btn-sm p-0" id="markAllReadBtn">Mark all read</button></div>
                                <div id="notificationList"><div class="text-center text-muted py-3">Loading...</div></div>
                            </div>
                        </li>
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="fas fa-user-circle"></i>
                                <span class="d-none d-md-inline"><?= htmlspecialchars(trim($formattedUserName) ?: $roleLabel) ?></span>
                                <span class="d-md-none"><?= htmlspecialchars($roleLabel ?: 'Account') ?></span>
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end">
                                <li><h6 class="dropdown-header"><i class="fas fa-id-badge me-1"></i><?= htmlspecialchars(trim($formattedUserName) ?: $roleLabel) ?><br><small class="text-muted"><?= htmlspecialchars($roleLabel) ?><?php if (!empty($_SESSION['svcNo'])): ?> | SVC: <?= htmlspecialchars($_SESSION['svcNo']) ?><?php endif; ?></small></h6></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item" href="/Armis2/users/index.php"><i class="fas fa-user-edit me-2"></i>My Profile</a></li>
                                <li><a class="dropdown-item" href="/Armis2/users/settings.php"><i class="fas fa-cog me-2"></i>Settings & Preferences</a></li>
                                <li><a class="dropdown-item" href="/Armis2/users/cv_download.php"><i class="fas fa-download me-2"></i>Download CV</a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item text-danger" href="/Armis2/logout.php"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
                            </ul>
                        </li>
                    </ul>
                </div>
            <?php else: ?>
                <div>
                    <a class="btn btn-outline-light btn-sm" href="/Armis2/login.php"><i class="fas fa-sign-in-alt me-1"></i>Login</a>
                </div>
            <?php endif; ?>
        </div>
    </nav>
    <!-- This padding ensures content is never hidden by the fixed navbar. Remove any duplicate padding. -->
    <div style="padding-top: 0"></div>