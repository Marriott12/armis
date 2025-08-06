<?php
// Include scalability configuration
require_once dirname(__DIR__) . '/config/scalability.php';

// Include session initialization
require_once dirname(__DIR__) . '/shared/session_init.php';

// Include military formatting functions
require_once dirname(__DIR__) . '/shared/military_formatting.php';

// Include RBAC system for role-based navigation
require_once dirname(__DIR__) . '/shared/rbac.php';

// Include database functions for user profile data
require_once dirname(__DIR__) . '/shared/database_connection.php';

// Check if user is logged in
$isLoggedIn = isset($_SESSION['user_id']);
$userName = $_SESSION['name'] ?? 'User';
$userRank = $_SESSION['rank'] ?? '';
$userRankAbbr = $_SESSION['rank_abbr'] ?? '';
$userFirstName = $_SESSION['first_name'] ?? ($_SESSION['fname'] ?? '');
$userLastName = $_SESSION['last_name'] ?? ($_SESSION['lname'] ?? '');
$userRole = $_SESSION['role'] ?? '';
$userCategory = $_SESSION['category'] ?? '';

// Get user's accessible modules for dynamic navigation
$userModules = $isLoggedIn ? (function_exists('getUserModules') ? getUserModules($userRole) : []) : [];

// Get detailed user profile data from database if logged in
$userProfileData = null;
if ($isLoggedIn && isset($_SESSION['user_id']) && function_exists('getUserProfileData')) {
    $userProfileData = getUserProfileData($_SESSION['user_id']);
    if ($userProfileData && is_array($userProfileData)) {
        // Update session with fresh database data
        $_SESSION['rank'] = $userProfileData['rank_name'] ?? $userRank;
        $_SESSION['rank_abbr'] = $userProfileData['rank_abbr'] ?? $userRankAbbr;
        $_SESSION['unit'] = $userProfileData['unit_name'] ?? ($_SESSION['unit'] ?? 'Unknown');
        $_SESSION['service_number'] = $userProfileData['service_number'] ?? ($_SESSION['service_number'] ?? '');
        $_SESSION['email'] = $userProfileData['email'] ?? ($_SESSION['email'] ?? '');

        // Update display variables
        $userRank = $_SESSION['rank'];
        $userRankAbbr = $_SESSION['rank_abbr'];
        $userFirstName = $userProfileData['first_name'] ?? $userFirstName;
        $userLastName = $userProfileData['last_name'] ?? $userLastName;
    }
}

$formattedUserName = function_exists('formatMilitaryName')
    ? formatMilitaryName($userRank, $userRankAbbr, $userFirstName, $userLastName, $userCategory)
    : htmlspecialchars(trim($userRank . ' ' . $userFirstName . ' ' . $userLastName));

// Set performance headers with fallback for REQUEST_TIME_FLOAT
$startTime = $_SERVER['REQUEST_TIME_FLOAT'] ?? microtime(true);
header('X-Powered-By: ARMIS v1.0');
header('X-Response-Time: ' . (microtime(true) - $startTime));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Army Resource Management Information System - Comprehensive military resource management platform">
    <meta name="keywords" content="ARMIS, Army, Resource Management, Military, Administration">
    <meta name="author" content="ARMIS Development Team">
    <meta name="robots" content="noindex, nofollow">

    <!-- Performance optimizations -->
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <link rel="dns-prefetch" href="//cdn.jsdelivr.net">
    <link rel="dns-prefetch" href="//cdnjs.cloudflare.com">
    <link rel="preconnect" href="https://cdn.jsdelivr.net">
    <link rel="preconnect" href="https://cdnjs.cloudflare.com">

    <title><?php echo isset($pageTitle) ? htmlspecialchars($pageTitle) . ' | ARMIS' : 'ARMIS - Army Resource Management Information System'; ?></title>

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
        .dropdown-menu {
            max-height: 400px;
            overflow-y: auto;
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
    <link rel="preload" href="/Armis2/shared/armis-styles.css" as="style" onload="this.onload=null;this.rel='stylesheet'">
    <noscript>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
        <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
        <link href="/Armis2/shared/armis-styles.css" rel="stylesheet">
    </noscript>
    <link rel="icon" type="image/x-icon" href="/Armis2/favicon.ico">
    <link rel="apple-touch-icon" href="/Armis2/logo.png">

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
</head>
<body>
    <!-- Page Loader -->
    <div class="page-loader">
        <div class="text-center">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Loading ARMIS...</span>
            </div>
            <div class="mt-2 text-muted">Loading ARMIS...</div>
        </div>
    </div>

    <!-- Navigation Header -->
    <nav class="navbar navbar-expand-lg navbar-dark navbar-custom fixed-top">
        <div class="container-fluid">
            <a class="navbar-brand d-flex align-items-center" href="/Armis2/">
                <img src="/Armis2/logo.png" alt="ARMIS Logo" height="30" class="me-2" onerror="this.style.display='none'">
                <span class="system-title">Army Resource Management Information System</span>
                <!--<span class="system-title-short">ARMIS</span>-->
            </a>
            <?php if ($isLoggedIn): ?>
            <button class="btn btn-outline-light d-md-none me-2" type="button" onclick="toggleSidebar()">
                <i class="fas fa-bars"></i>
            </button>
            <?php endif; ?>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <?php if ($isLoggedIn): ?>
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-th-large"></i> Modules
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <?php
                            // Module configs
                            $moduleConfigs = [
                                'admin' => [
                                    'title' => 'System Admin',
                                    'icon' => 'user-shield',
                                    'color' => 'primary',
                                    'url' => '/Armis2/admin/index.php',
                                    'category' => 'System Management'
                                ],
                                'admin_branch' => [
                                    'title' => 'Admin Branch',
                                    'icon' => 'users-cog',
                                    'color' => 'success',
                                    'url' => '/Armis2/admin_branch/index.php',
                                    'category' => 'System Management'
                                ],
                                'command' => [
                                    'title' => 'Command',
                                    'icon' => 'chess-king',
                                    'color' => 'warning',
                                    'url' => '/Armis2/command/index.php',
                                    'category' => 'Operations'
                                ],
                                'operations' => [
                                    'title' => 'Operations',
                                    'icon' => 'map-marked-alt',
                                    'color' => 'danger',
                                    'url' => '/Armis2/operations/index.php',
                                    'category' => 'Operations'
                                ],
                                'training' => [
                                    'title' => 'Training',
                                    'icon' => 'graduation-cap',
                                    'color' => 'info',
                                    'url' => '/Armis2/training/index.php',
                                    'category' => 'Resources'
                                ],
                                'finance' => [
                                    'title' => 'Finance',
                                    'icon' => 'dollar-sign',
                                    'color' => 'secondary',
                                    'url' => '/Armis2/finance/index.php',
                                    'category' => 'Resources'
                                ],
                                'ordinance' => [
                                    'title' => 'Ordinance',
                                    'icon' => 'tools',
                                    'color' => 'dark',
                                    'url' => '/Armis2/ordinance/index.php',
                                    'category' => 'Resources'
                                ]
                            ];
                            // Group modules by category
                            $groupedModules = [];
                            foreach ($userModules as $module) {
                                if (isset($moduleConfigs[$module])) {
                                    $config = $moduleConfigs[$module];
                                    $groupedModules[$config['category']][] = array_merge($config, ['module' => $module]);
                                }
                            }
                            // Display grouped modules or fallback message
                            if (!empty($groupedModules)) {
                                foreach ($groupedModules as $category => $modules): ?>
                                    <li><h6 class="dropdown-header">
                                        <i class="fas fa-<?php echo $category === 'System Management' ? 'cogs' : ($category === 'Operations' ? 'chess-king' : 'tools'); ?>"></i>
                                        <?php echo htmlspecialchars($category); ?>
                                    </h6></li>
                                    <?php foreach ($modules as $module): ?>
                                        <li><a class="dropdown-item" href="<?php echo htmlspecialchars($module['url']); ?>">
                                            <i class="fas fa-<?php echo htmlspecialchars($module['icon']); ?> text-<?php echo htmlspecialchars($module['color']); ?>"></i>
                                            <?php echo htmlspecialchars($module['title']); ?>
                                        </a></li>
                                    <?php endforeach; ?>
                                    <?php if ($category !== array_key_last($groupedModules)): ?>
                                        <li><hr class="dropdown-divider"></li>
                                    <?php endif; ?>
                                <?php endforeach;
                            } else { ?>
                                <li>
                                    <span class="dropdown-item text-muted">
                                        <i class="fas fa-info-circle"></i> No modules assigned to your account.
                                    </span>
                                </li>
                            <?php } ?>
                        </ul>
                    </li>
                    <?php if (function_exists('hasModuleAccess') && (hasModuleAccess('admin_branch') || hasModuleAccess('admin'))): ?>
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                                <i class="fas fa-chart-line"></i> Reports
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end">
                                <li><a class="dropdown-item" href="/Armis2/admin_branch/reports_seniority.php">
                                    <i class="fas fa-list-ol"></i> Seniority Roll
                                </a></li>
                                <li><a class="dropdown-item" href="/Armis2/admin_branch/reports_rank.php">
                                    <i class="fas fa-medal"></i> By Rank
                                </a></li>
                                <li><a class="dropdown-item" href="/Armis2/admin_branch/reports_units.php">
                                    <i class="fas fa-building"></i> Unit Reports
                                </a></li>
                                <li><a class="dropdown-item" href="/Armis2/admin_branch/reports_corps.php">
                                    <i class="fas fa-shield-alt"></i> Corps Reports
                                </a></li>
                                <?php if (hasModuleAccess('admin')): ?>
                                    <li><hr class="dropdown-divider"></li>
                                    <li><a class="dropdown-item" href="/Armis2/admin/health.php">
                                        <i class="fas fa-heartbeat"></i> System Health
                                    </a></li>
                                <?php endif; ?>
                            </ul>
                        </li>
                    <?php endif; ?>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-user-circle"></i>
                            <span class="d-none d-md-inline"><?php echo htmlspecialchars($formattedUserName); ?></span>
                            <span class="d-md-none">Account</span>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><h6 class="dropdown-header">
                                <i class="fas fa-id-badge"></i> <?php echo htmlspecialchars($formattedUserName); ?><br>
                                <small class="text-muted">
                                    <?php echo htmlspecialchars(ucfirst($userRole)); ?>
                                    <?php if (!empty($_SESSION['service_number'])): ?>
                                        | SVC: <?php echo htmlspecialchars($_SESSION['service_number']); ?>
                                    <?php endif; ?>
                                </small>
                                <?php if ($userProfileData): ?>
                                    <br><small class="text-muted">
                                        <?php if (!empty($_SESSION['unit'])): ?>
                                            📍 <?php echo htmlspecialchars($_SESSION['unit']); ?>
                                        <?php endif; ?>
                                        <?php if (!empty($_SESSION['corps']) && $_SESSION['corps'] !== $_SESSION['unit']): ?>
                                            | <?php echo htmlspecialchars($_SESSION['corps']); ?>
                                        <?php endif; ?>
                                    </small>
                                    <?php if (!empty($userProfileData['last_login']) && $userProfileData['last_login'] !== '0000-00-00 00:00:00'): ?>
                                        <br><small class="text-muted">
                                            🕒 Last Login: <?php echo date('M j, Y g:i A', strtotime($userProfileData['last_login'])); ?>
                                        </small>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </h6></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="/Armis2/users/index.php">
                                <i class="fas fa-user-edit"></i> My Profile
                                <?php if ($userProfileData && !empty($userProfileData['email'])): ?>
                                    <small class="d-block text-muted">✉️ <?php echo htmlspecialchars($userProfileData['email']); ?></small>
                                <?php endif; ?>
                            </a></li>
                            <?php if ($userProfileData && !empty($userProfileData['tel'])): ?>
                                <li><a class="dropdown-item" href="tel:<?php echo htmlspecialchars($userProfileData['tel']); ?>">
                                    <i class="fas fa-phone"></i> Contact: <?php echo htmlspecialchars($userProfileData['tel']); ?>
                                </a></li>
                            <?php endif; ?>
                            <li><a class="dropdown-item" href="/Armis2/users/index.php">
                                <i class="fas fa-id-card"></i> Account Details
                                <?php if ($userProfileData && !empty($userProfileData['attestDate'])): ?>
                                    <small class="d-block text-muted">📅 Enlisted: <?php echo date('M j, Y', strtotime($userProfileData['attestDate'])); ?></small>
                                <?php endif; ?>
                            </a></li>
                            <li><a class="dropdown-item" href="/Armis2/users/settings.php">
                                <i class="fas fa-cog"></i> Settings & Preferences
                            </a></li>
                            <?php if ($userProfileData && !empty($userProfileData['nok'])): ?>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item text-warning" href="tel:<?php echo htmlspecialchars($userProfileData['nokTel'] ?? ''); ?>">
                                    <i class="fas fa-exclamation-triangle"></i> Emergency Contact
                                    <small class="d-block text-muted"><?php echo htmlspecialchars($userProfileData['nok']); ?>
                                    <?php if (!empty($userProfileData['nokTel'])): ?>
                                        - <?php echo htmlspecialchars($userProfileData['nokTel']); ?>
                                    <?php endif; ?>
                                    </small>
                                </a></li>
                            <?php endif; ?>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item text-danger" href="/Armis2/logout.php">
                                <i class="fas fa-sign-out-alt"></i> Logout
                            </a></li>
                        </ul>
                    </li>
                </ul>
                <?php else: ?>
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="/Armis2/login.php">
                            <i class="fas fa-sign-in-alt"></i> Login
                        </a>
                    </li>
                </ul>
                <?php endif; ?>
            </div>
        </div>
    </nav>
    <!-- This padding ensures content is never hidden by the fixed navbar. Remove any duplicate padding. -->
    <div style="padding-top: 0"></div>