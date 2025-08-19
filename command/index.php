<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include RBAC system
require_once dirname(__DIR__) . '/shared/rbac.php';
require_once __DIR__ . '/includes/CommandConfigService.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ' . dirname($_SERVER['PHP_SELF']) . '/../login.php');
    exit();
}

// Check if user has access to command module
requireModuleAccess('command');

// Log access
logAccess('command', 'dashboard_view', true);

$pageTitle = "Command";
$moduleName = "Command";
$moduleIcon = "chess-king";
$currentPage = "dashboard";

// Load dynamic navigation from configuration
try {
    $configService = new CommandConfigService();
    $sidebarLinks = $configService->getNavigation();
} catch (Exception $e) {
    error_log("Failed to load command navigation: " . $e->getMessage());
    // Fallback to hardcoded navigation
    $sidebarLinks = [
        ['title' => 'Dashboard', 'url' => '/Armis2/command/index.php', 'icon' => 'tachometer-alt', 'page' => 'dashboard'],
        ['title' => 'Operational Reports', 'url' => '/Armis2/command/operations.php', 'icon' => 'file-alt', 'page' => 'operations'],
        ['title' => 'Staff Profiles', 'url' => '/Armis2/command/profiles.php', 'icon' => 'id-card', 'page' => 'profiles'],
        ['title' => 'Command Reports', 'url' => '/Armis2/command/reports.php', 'icon' => 'chart-line', 'page' => 'reports']
    ];
}

include dirname(__DIR__) . '/shared/header.php';
include dirname(__DIR__) . '/shared/sidebar.php';
?>

<!-- Main Content -->
<div class="content-wrapper with-sidebar">
    <div class="container-fluid">
        <div class="main-content">
            <div class="row">
                <div class="col-12">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h1 class="section-title">
                            <i class="fas fa-chess-king"></i> Command Dashboard
                        </h1>
                        <span class="badge status-badge">Command Center</span>
                    </div>
                </div>
            </div>
            
            <div class="row g-4" id="dynamic-dashboard-modules">
                <!-- Dashboard modules will be loaded dynamically -->
                <div class="col-12 text-center py-4">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading dashboard modules...</span>
                    </div>
                    <p class="mt-2 text-muted">Loading dashboard modules...</p>
                </div>
            </div>
            
            <!-- Command Overview -->
            <div class="row mt-5" id="dynamic-overview-stats">
                <div class="col-12">
                    <h3 class="section-title mb-4">Command Overview</h3>
                </div>
                <!-- Overview statistics will be loaded dynamically -->
                <div class="col-12 text-center py-4">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading overview statistics...</span>
                    </div>
                    <p class="mt-2 text-muted">Loading overview statistics...</p>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Dynamic Loading Script -->
<script src="js/dynamic-loader.js"></script>

<?php include dirname(__DIR__) . '/shared/footer.php'; ?>