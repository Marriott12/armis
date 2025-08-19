<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include RBAC system
require_once dirname(__DIR__) . '/shared/rbac.php';
require_once dirname(__DIR__) . '/config.php';

$pageTitle = "System Settings";
$moduleName = "System Admin";
$moduleIcon = "cogs";
$currentPage = "settings";

$sidebarLinks = [
    ['title' => 'Dashboard', 'url' => '/Armis2/admin/index.php', 'icon' => 'tachometer-alt', 'page' => 'dashboard'],
    ['title' => 'User Management', 'url' => '/Armis2/admin/users.php', 'icon' => 'users', 'page' => 'users'],
    ['title' => 'System Settings', 'url' => '/Armis2/admin/settings.php', 'icon' => 'cogs', 'page' => 'settings'],
    ['title' => 'Database Management', 'url' => '/Armis2/admin/database.php', 'icon' => 'database', 'page' => 'database'],
    ['title' => 'Security Center', 'url' => '/Armis2/admin/security.php', 'icon' => 'shield-alt', 'page' => 'security'],
    ['title' => 'System Reports', 'url' => '/Armis2/admin/reports.php', 'icon' => 'chart-bar', 'page' => 'reports']
];

// Check if user is logged in and has admin privileges
if (!isset($_SESSION['user_id'])) {
    header('Location: ' . dirname($_SERVER['PHP_SELF']) . '/../login.php');
    exit();
}

// Check if user has access to admin module
requireModuleAccess('admin');

// Log access
logAccess('admin', 'settings_view', true);

// Handle settings updates
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && $_POST['action'] === 'update_settings') {
        // For demo purposes, we'll show what would be updated
        // In a real system, these would write to a config file or database
        $message = "Settings would be updated in a production system. This is a demo interface.";
        $messageType = "info";
        logAccess('admin', 'settings_update_attempt', true);
    }
}

// System configuration data
$systemConfig = [
    'general' => [
        'system_name' => get_config('ARMIS_NAME', 'Army Resource Management Information System'),
        'system_version' => get_config('ARMIS_VERSION', '1.0.0'),
        'timezone' => get_config('ARMIS_TIMEZONE', 'UTC'),
        'language' => get_config('ARMIS_LANG', 'en'),
        'theme' => get_config('ARMIS_THEME', 'military')
    ],
    'security' => [
        'session_timeout' => get_config('SESSION_TIMEOUT', 3600),
        'max_login_attempts' => get_config('MAX_LOGIN_ATTEMPTS', 5),
        'lockout_time' => get_config('LOGIN_LOCKOUT_TIME', 900),
        'csrf_token_expiry' => get_config('CSRF_TOKEN_EXPIRY', 1800)
    ],
    'modules' => [
        'admin' => get_config('ENABLE_ADMIN_MODULE', true),
        'command' => get_config('ENABLE_COMMAND_MODULE', true),
        'training' => get_config('ENABLE_TRAINING_MODULE', true),
        'operations' => get_config('ENABLE_OPERATIONS_MODULE', true)
    ],
    'uploads' => [
        'max_size' => get_config('MAX_UPLOAD_SIZE', 10485760), // 10MB
        'allowed_types' => get_config('ALLOWED_FILE_TYPES', 'jpg,jpeg,png,pdf,doc,docx,xls,xlsx')
    ]
];

include dirname(__DIR__) . '/shared/header.php';
include dirname(__DIR__) . '/shared/sidebar.php';
?>

<!-- Main Content -->
<div class="content-wrapper with-sidebar">
    <div class="container-fluid">
        <div class="main-content">
            <!-- Header Section -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h1 class="admin-section-title">
                                <i class="fas fa-cogs text-primary"></i> System Settings
                            </h1>
                            <p class="text-muted mb-0">Configure system parameters and modules</p>
                        </div>
                        <div>
                            <button class="btn btn-outline-warning btn-sm" onclick="exportSettings()">
                                <i class="fas fa-download"></i> Export Config
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Alert Messages -->
            <?php if ($message): ?>
            <div class="row mb-3">
                <div class="col-12">
                    <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show" role="alert">
                        <?php echo htmlspecialchars($message); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Settings Form -->
            <form method="POST" action="">
                <input type="hidden" name="action" value="update_settings">
                
                <!-- General Settings -->
                <div class="row mb-4">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0"><i class="fas fa-info-circle"></i> General Settings</h5>
                            </div>
                            <div class="card-body">
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label for="system_name" class="form-label">System Name</label>
                                        <input type="text" class="form-control" id="system_name" name="system_name" 
                                               value="<?php echo htmlspecialchars($systemConfig['general']['system_name']); ?>" readonly>
                                        <div class="form-text">The display name for the system</div>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="system_version" class="form-label">System Version</label>
                                        <input type="text" class="form-control" id="system_version" name="system_version" 
                                               value="<?php echo htmlspecialchars($systemConfig['general']['system_version']); ?>" readonly>
                                        <div class="form-text">Current system version</div>
                                    </div>
                                    <div class="col-md-4">
                                        <label for="timezone" class="form-label">Timezone</label>
                                        <select class="form-select" id="timezone" name="timezone" disabled>
                                            <option value="UTC" <?php echo ($systemConfig['general']['timezone'] === 'UTC') ? 'selected' : ''; ?>>UTC</option>
                                            <option value="America/New_York" <?php echo ($systemConfig['general']['timezone'] === 'America/New_York') ? 'selected' : ''; ?>>Eastern Time</option>
                                            <option value="America/Chicago" <?php echo ($systemConfig['general']['timezone'] === 'America/Chicago') ? 'selected' : ''; ?>>Central Time</option>
                                            <option value="America/Denver" <?php echo ($systemConfig['general']['timezone'] === 'America/Denver') ? 'selected' : ''; ?>>Mountain Time</option>
                                            <option value="America/Los_Angeles" <?php echo ($systemConfig['general']['timezone'] === 'America/Los_Angeles') ? 'selected' : ''; ?>>Pacific Time</option>
                                        </select>
                                    </div>
                                    <div class="col-md-4">
                                        <label for="language" class="form-label">Language</label>
                                        <select class="form-select" id="language" name="language" disabled>
                                            <option value="en" <?php echo ($systemConfig['general']['language'] === 'en') ? 'selected' : ''; ?>>English</option>
                                            <option value="es" <?php echo ($systemConfig['general']['language'] === 'es') ? 'selected' : ''; ?>>Spanish</option>
                                        </select>
                                    </div>
                                    <div class="col-md-4">
                                        <label for="theme" class="form-label">Theme</label>
                                        <select class="form-select" id="theme" name="theme" disabled>
                                            <option value="military" <?php echo ($systemConfig['general']['theme'] === 'military') ? 'selected' : ''; ?>>Military</option>
                                            <option value="admin" <?php echo ($systemConfig['general']['theme'] === 'admin') ? 'selected' : ''; ?>>Admin</option>
                                            <option value="dark" <?php echo ($systemConfig['general']['theme'] === 'dark') ? 'selected' : ''; ?>>Dark</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Security Settings -->
                <div class="row mb-4">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0"><i class="fas fa-shield-alt"></i> Security Settings</h5>
                            </div>
                            <div class="card-body">
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label for="session_timeout" class="form-label">Session Timeout (seconds)</label>
                                        <input type="number" class="form-control" id="session_timeout" name="session_timeout" 
                                               value="<?php echo $systemConfig['security']['session_timeout']; ?>" readonly>
                                        <div class="form-text">How long users stay logged in when idle</div>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="max_login_attempts" class="form-label">Max Login Attempts</label>
                                        <input type="number" class="form-control" id="max_login_attempts" name="max_login_attempts" 
                                               value="<?php echo $systemConfig['security']['max_login_attempts']; ?>" readonly>
                                        <div class="form-text">Failed attempts before account lockout</div>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="lockout_time" class="form-label">Lockout Time (seconds)</label>
                                        <input type="number" class="form-control" id="lockout_time" name="lockout_time" 
                                               value="<?php echo $systemConfig['security']['lockout_time']; ?>" readonly>
                                        <div class="form-text">How long accounts remain locked</div>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="csrf_token_expiry" class="form-label">CSRF Token Expiry (seconds)</label>
                                        <input type="number" class="form-control" id="csrf_token_expiry" name="csrf_token_expiry" 
                                               value="<?php echo $systemConfig['security']['csrf_token_expiry']; ?>" readonly>
                                        <div class="form-text">Security token lifetime</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Module Settings -->
                <div class="row mb-4">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0"><i class="fas fa-puzzle-piece"></i> Module Settings</h5>
                            </div>
                            <div class="card-body">
                                <div class="row g-3">
                                    <?php foreach ($systemConfig['modules'] as $module => $enabled): ?>
                                    <div class="col-md-3">
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox" role="switch" 
                                                   id="module_<?php echo $module; ?>" name="modules[<?php echo $module; ?>]" 
                                                   <?php echo $enabled ? 'checked' : ''; ?> disabled>
                                            <label class="form-check-label" for="module_<?php echo $module; ?>">
                                                <?php echo ucfirst($module); ?> Module
                                            </label>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                                <div class="alert alert-info mt-3">
                                    <i class="fas fa-info-circle"></i> Module settings are configured in the main config file and require system restart to take effect.
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- File Upload Settings -->
                <div class="row mb-4">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0"><i class="fas fa-upload"></i> File Upload Settings</h5>
                            </div>
                            <div class="card-body">
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label for="max_upload_size" class="form-label">Maximum Upload Size (bytes)</label>
                                        <input type="number" class="form-control" id="max_upload_size" name="max_upload_size" 
                                               value="<?php echo $systemConfig['uploads']['max_size']; ?>" readonly>
                                        <div class="form-text">Current: <?php echo round($systemConfig['uploads']['max_size'] / 1024 / 1024, 1); ?>MB</div>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="allowed_types" class="form-label">Allowed File Types</label>
                                        <input type="text" class="form-control" id="allowed_types" name="allowed_types" 
                                               value="<?php echo htmlspecialchars($systemConfig['uploads']['allowed_types']); ?>" readonly>
                                        <div class="form-text">Comma-separated list of allowed extensions</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- System Information -->
                <div class="row mb-4">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0"><i class="fas fa-server"></i> System Information</h5>
                            </div>
                            <div class="card-body">
                                <div class="row g-3">
                                    <div class="col-md-3">
                                        <div class="text-center">
                                            <div class="text-muted small">PHP Version</div>
                                            <div class="h5"><?php echo PHP_VERSION; ?></div>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="text-center">
                                            <div class="text-muted small">Memory Limit</div>
                                            <div class="h5"><?php echo ini_get('memory_limit'); ?></div>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="text-center">
                                            <div class="text-muted small">Max Execution Time</div>
                                            <div class="h5"><?php echo ini_get('max_execution_time'); ?>s</div>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="text-center">
                                            <div class="text-muted small">Upload Max Size</div>
                                            <div class="h5"><?php echo ini_get('upload_max_filesize'); ?></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Action Buttons -->
                <div class="row">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-body text-center">
                                <div class="alert alert-warning">
                                    <i class="fas fa-exclamation-triangle"></i> 
                                    <strong>Note:</strong> This is a demonstration interface. In a production system, 
                                    settings would be saved to configuration files or database tables.
                                </div>
                                <button type="submit" class="btn btn-primary me-2" disabled>
                                    <i class="fas fa-save"></i> Save Settings
                                </button>
                                <button type="button" class="btn btn-outline-secondary me-2" onclick="location.reload()">
                                    <i class="fas fa-undo"></i> Reset
                                </button>
                                <a href="/Armis2/admin/health.php" class="btn btn-outline-info">
                                    <i class="fas fa-heartbeat"></i> System Health
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function exportSettings() {
    const settings = {
        general: {
            system_name: document.getElementById('system_name').value,
            system_version: document.getElementById('system_version').value,
            timezone: document.getElementById('timezone').value,
            language: document.getElementById('language').value,
            theme: document.getElementById('theme').value
        },
        security: {
            session_timeout: document.getElementById('session_timeout').value,
            max_login_attempts: document.getElementById('max_login_attempts').value,
            lockout_time: document.getElementById('lockout_time').value,
            csrf_token_expiry: document.getElementById('csrf_token_expiry').value
        },
        uploads: {
            max_upload_size: document.getElementById('max_upload_size').value,
            allowed_types: document.getElementById('allowed_types').value
        }
    };
    
    const dataStr = "data:text/json;charset=utf-8," + encodeURIComponent(JSON.stringify(settings, null, 2));
    const downloadAnchorNode = document.createElement('a');
    downloadAnchorNode.setAttribute("href", dataStr);
    downloadAnchorNode.setAttribute("download", "armis_settings_" + new Date().toISOString().split('T')[0] + ".json");
    document.body.appendChild(downloadAnchorNode);
    downloadAnchorNode.click();
    downloadAnchorNode.remove();
}
</script>

<?php include dirname(__DIR__) . '/shared/footer.php'; ?>