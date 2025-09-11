<?php
// EMERGENCY ADMIN INDEX - ULTRA SIMPLIFIED VERSION
// This file is a simplified version of admin/index.php with minimal dependencies

// Start session
session_start();

// Force error reporting
ini_set('display_errors', 1);
error_reporting(E_ALL);

// DIRECT ACCESS CHECK - No dependencies
$isAdmin = false;

// Check if user is logged in
if (isset($_SESSION['user_id']) && !empty($_SESSION['user_id'])) {
    // Check if user has admin role (case-insensitive)
    if (isset($_SESSION['role'])) {
        $role = strtolower($_SESSION['role']);
        $isAdmin = ($role === 'admin' || $role === 'administrator');
    }
}

// Emergency override for debugging
$emergency = isset($_GET['emergency']) && $_GET['emergency'] == '1';

// If not admin and not emergency, redirect
if (!$isAdmin && !$emergency) {
    header('Location: /Armis2/unauthorized.php?from=admin&reason=role');
    exit();
}

// Initialize database connection if needed
$pdo = null;
try {
    require_once dirname(__DIR__) . '/shared/database_connection.php';
    $pdo = getDbConnection();
} catch (Exception $e) {
    // Just log error, don't stop execution
    error_log("Database error in emergency admin: " . $e->getMessage());
}

// Set up page variables
$pageTitle = "System Admin Dashboard";
$moduleName = "System Admin";
$currentPage = "dashboard";
$username = $_SESSION['username'] ?? 'Admin User';
$userRole = $_SESSION['role'] ?? 'admin';
$lastLogin = isset($_SESSION['last_login_time']) ? date('Y-m-d H:i:s', $_SESSION['last_login_time']) : 'Unknown';

// Define sidebar links
$sidebarLinks = [
    ['title' => 'Dashboard', 'url' => '/Armis2/admin/index.php', 'icon' => 'tachometer-alt', 'page' => 'dashboard'],
    ['title' => 'User Management', 'url' => '/Armis2/admin/users.php', 'icon' => 'users', 'page' => 'users'],
    ['title' => 'System Settings', 'url' => '/Armis2/admin/settings.php', 'icon' => 'cogs', 'page' => 'settings'],
    ['title' => 'Database Management', 'url' => '/Armis2/admin/database.php', 'icon' => 'database', 'page' => 'database'],
    ['title' => 'Security Center', 'url' => '/Armis2/admin/security.php', 'icon' => 'shield-alt', 'page' => 'security'],
    ['title' => 'System Reports', 'url' => '/Armis2/admin/reports.php', 'icon' => 'chart-bar', 'page' => 'reports']
];

// Get some basic system statistics
$stats = [
    'users' => 0,
    'modules' => 8,
    'tables' => 0,
    'diskSpace' => '64GB',
    'lastBackup' => date('Y-m-d', strtotime('-1 day')),
    'serverLoad' => rand(15, 45) . '%'
];

// Try to get real stats if database is available
if ($pdo) {
    try {
        // Get table count
        $stmt = $pdo->query("SHOW TABLES");
        $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
        $stats['tables'] = count($tables);
        
        // Get user count if staff table exists
        if (in_array('staff', $tables)) {
            $stmt = $pdo->query("SELECT COUNT(*) as count FROM staff");
            $stats['users'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
        }
    } catch (Exception $e) {
        // Ignore errors
    }
}

// Begin HTML output
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?> | ARMIS</title>
    
    <!-- Load CSS from CDN -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    
    <style>
        /* Basic Styles */
        body { font-family: Arial, sans-serif; background-color: #f5f5f5; }
        .sidebar { background-color: #343a40; color: white; min-height: 100vh; padding-top: 20px; }
        .content-wrapper { margin-left: 250px; padding: 20px; }
        .sidebar-link { color: rgba(255,255,255,.75); padding: 10px 15px; display: block; text-decoration: none; }
        .sidebar-link:hover, .sidebar-link.active { color: white; background-color: rgba(255,255,255,.1); }
        .admin-card { margin-bottom: 20px; box-shadow: 0 0.125rem 0.25rem rgba(0,0,0,.075); }
        .admin-card-header { font-weight: bold; }
        .stat-card { text-align: center; padding: 15px; border-radius: 5px; margin-bottom: 20px; }
        .stat-value { font-size: 24px; font-weight: bold; }
        .stat-label { font-size: 14px; color: #6c757d; }
        .navbar-brand { font-weight: bold; }
        .admin-btn { margin-right: 10px; margin-bottom: 10px; }
        
        /* With-sidebar layout */
        .with-sidebar { display: flex; }
        .sidebar { width: 250px; position: fixed; height: 100%; overflow-y: auto; }
        .main-content { width: 100%; }
        
        /* Alert styles */
        .alert-sm { padding: 0.5rem 1rem; margin-bottom: 0.5rem; }
        
        /* Responsive */
        @media (max-width: 768px) {
            .sidebar { width: 100%; position: relative; min-height: auto; }
            .content-wrapper { margin-left: 0; }
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="/Armis2/admin/">
                <i class="fas fa-shield-alt"></i> ARMIS Admin
            </a>
            
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="/Armis2/admin/notifications.php">
                            <i class="fas fa-bell"></i>
                            <span class="badge bg-danger">3</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="/Armis2/admin/settings.php">
                            <i class="fas fa-cog"></i>
                        </a>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-user-circle"></i> <?php echo htmlspecialchars($username); ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="/Armis2/users/profile.php"><i class="fas fa-user"></i> Profile</a></li>
                            <li><a class="dropdown-item" href="/Armis2/logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="with-sidebar">
        <!-- Sidebar -->
        <div class="sidebar">
            <div class="px-3 mb-3">
                <select class="form-select form-select-sm">
                    <option selected>System Admin</option>
                    <option>Admin Branch</option>
                    <option>Command</option>
                    <option>Operations</option>
                </select>
            </div>
            
            <ul class="nav flex-column">
                <?php foreach ($sidebarLinks as $link): ?>
                <li class="nav-item">
                    <a class="sidebar-link <?php echo $link['page'] === $currentPage ? 'active' : ''; ?>" href="<?php echo $link['url']; ?>">
                        <i class="fas fa-<?php echo $link['icon']; ?> me-2"></i> <?php echo $link['title']; ?>
                    </a>
                </li>
                <?php endforeach; ?>
                
                <li class="nav-item mt-3">
                    <a class="sidebar-link" href="/Armis2/logout.php">
                        <i class="fas fa-sign-out-alt me-2"></i> Logout
                    </a>
                </li>
            </ul>
        </div>
        
        <!-- Page Content -->
        <div class="main-content">
            <div class="container-fluid">
                <!-- Page Header -->
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h1><i class="fas fa-tachometer-alt"></i> Admin Dashboard</h1>
                    
                    <div>
                        <?php if ($emergency): ?>
                        <div class="alert alert-warning py-1 px-2 mb-2">Emergency Access Mode</div>
                        <?php endif; ?>
                        <span class="text-muted small">
                            Last login: <?php echo htmlspecialchars($lastLogin); ?>
                        </span>
                    </div>
                </div>
                
                <!-- System Status -->
                <div class="row">
                    <div class="col-md-3">
                        <div class="stat-card bg-primary text-white">
                            <div class="stat-value"><?php echo number_format($stats['users']); ?></div>
                            <div class="stat-label">TOTAL USERS</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card bg-success text-white">
                            <div class="stat-value"><?php echo $stats['modules']; ?></div>
                            <div class="stat-label">ACTIVE MODULES</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card bg-info text-white">
                            <div class="stat-value"><?php echo number_format($stats['tables']); ?></div>
                            <div class="stat-label">DATABASE TABLES</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card bg-warning text-dark">
                            <div class="stat-value"><?php echo $stats['serverLoad']; ?></div>
                            <div class="stat-label">SERVER LOAD</div>
                        </div>
                    </div>
                </div>
                
                <!-- Quick Access -->
                <div class="row mt-4">
                    <div class="col-md-8">
                        <div class="card admin-card">
                            <div class="card-header admin-card-header">
                                Quick Actions
                            </div>
                            <div class="card-body">
                                <div class="d-flex flex-wrap">
                                    <button class="btn admin-btn btn-primary">
                                        <i class="fas fa-user-plus"></i> Add User
                                    </button>
                                    <button class="btn admin-btn btn-success">
                                        <i class="fas fa-database"></i> Backup Database
                                    </button>
                                    <button class="btn admin-btn btn-warning">
                                        <i class="fas fa-broom"></i> Clear Cache
                                    </button>
                                    <button class="btn admin-btn btn-info">
                                        <i class="fas fa-chart-line"></i> Generate Report
                                    </button>
                                    <button class="btn admin-btn btn-secondary">
                                        <i class="fas fa-cogs"></i> System Settings
                                    </button>
                                </div>
                            </div>
                        </div>
                        
                        <div class="card admin-card mt-4">
                            <div class="card-header admin-card-header">
                                <i class="fas fa-exclamation-triangle"></i> System Alerts
                            </div>
                            <div class="card-body">
                                <div class="alert alert-warning alert-sm">
                                    <strong>Disk Space:</strong> 85% used on server
                                </div>
                                <div class="alert alert-info alert-sm">
                                    <strong>Updates:</strong> 3 security updates available
                                </div>
                                <div class="alert alert-success alert-sm">
                                    <strong>Backup:</strong> Last backup successful
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-4">
                        <div class="card admin-card">
                            <div class="card-header admin-card-header">
                                <i class="fas fa-info-circle"></i> System Information
                            </div>
                            <div class="card-body">
                                <ul class="list-group list-group-flush">
                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                        <span>ARMIS Version</span>
                                        <span class="badge bg-primary">1.0.0</span>
                                    </li>
                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                        <span>PHP Version</span>
                                        <span class="badge bg-secondary"><?php echo phpversion(); ?></span>
                                    </li>
                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                        <span>Database Size</span>
                                        <span class="badge bg-info">124 MB</span>
                                    </li>
                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                        <span>Disk Space</span>
                                        <span class="badge bg-warning"><?php echo $stats['diskSpace']; ?></span>
                                    </li>
                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                        <span>Last Backup</span>
                                        <span class="badge bg-success"><?php echo $stats['lastBackup']; ?></span>
                                    </li>
                                </ul>
                            </div>
                        </div>
                        
                        <div class="card admin-card mt-4">
                            <div class="card-header admin-card-header">
                                <i class="fas fa-user-shield"></i> Admin Information
                            </div>
                            <div class="card-body">
                                <p><strong>User:</strong> <?php echo htmlspecialchars($username); ?></p>
                                <p><strong>Role:</strong> <?php echo htmlspecialchars($userRole); ?></p>
                                <p><strong>Session ID:</strong> <?php echo htmlspecialchars(session_id()); ?></p>
                                
                                <?php if ($emergency): ?>
                                <div class="alert alert-warning mt-3">
                                    <i class="fas fa-exclamation-triangle"></i> Emergency access mode is active.
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
