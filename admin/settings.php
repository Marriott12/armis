<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include configuration and database
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/shared/database_connection.php';

// Include RBAC system
require_once dirname(__DIR__) . '/shared/rbac.php';
require_once dirname(__DIR__) . '/shared/csrf.php';

// Initialize global database connection
try {
    $pdo = getDbConnection();
} catch (Exception $e) {
    error_log("Database connection failed in admin/settings.php: " . $e->getMessage());
    die("Database connection failed. Please check your configuration.");
}

$pageTitle = "System Settings";
$moduleName = "System Admin";
$moduleIcon = "cogs";
$currentPage = "settings";

require_once __DIR__ . '/includes/sidebar_nav.php';

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
$formErrors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();

    if (isset($_POST['action']) && $_POST['action'] === 'update_settings') {
        // FIX: previously a no-op demo ("Settings would be updated in
        // a production system"). Real validation + persistence via
        // set_config() now, matching the min/max ranges already shown
        // in this page's own HTML inputs.
        $numericFields = [
            'SESSION_TIMEOUT' => ['post' => 'session_timeout', 'min' => 300, 'max' => 86400, 'label' => 'Session Timeout'],
            'MAX_LOGIN_ATTEMPTS' => ['post' => 'max_login_attempts', 'min' => 3, 'max' => 10, 'label' => 'Max Login Attempts'],
            'LOGIN_LOCKOUT_TIME' => ['post' => 'lockout_time', 'min' => 300, 'max' => 3600, 'label' => 'Lockout Time'],
            'CSRF_TOKEN_EXPIRY' => ['post' => 'csrf_token_expiry', 'min' => 600, 'max' => 7200, 'label' => 'CSRF Token Expiry'],
            'MAX_UPLOAD_SIZE' => ['post' => 'max_upload_size', 'min' => 1048576, 'max' => 104857600, 'label' => 'Max Upload Size'],
        ];
        $textFields = [
            'ARMIS_NAME' => 'system_name',
            'ARMIS_VERSION' => 'system_version',
            'ARMIS_TIMEZONE' => 'timezone',
            'ARMIS_LANG' => 'language',
            'ARMIS_THEME' => 'theme',
        ];
        $validTimezones = ['UTC', 'America/New_York', 'America/Chicago', 'America/Denver', 'America/Los_Angeles', 'Europe/London', 'Africa/Lusaka'];
        $validLanguages = ['en', 'fr', 'pt'];
        $validThemes = ['military', 'corporate', 'modern'];

        $toSave = [];

        foreach ($numericFields as $constant => $meta) {
            $value = (int) ($_POST[$meta['post']] ?? 0);
            if ($value < $meta['min'] || $value > $meta['max']) {
                $formErrors[] = "{$meta['label']} must be between {$meta['min']} and {$meta['max']}.";
                continue;
            }
            $toSave[$constant] = $value;
        }

        $toSave['ARMIS_NAME'] = trim($_POST['system_name'] ?? '') !== ''
            ? htmlspecialchars(trim($_POST['system_name']), ENT_QUOTES)
            : null;
        if ($toSave['ARMIS_NAME'] === null) {
            $formErrors[] = 'System Name cannot be empty.';
            unset($toSave['ARMIS_NAME']);
        }
        $toSave['ARMIS_VERSION'] = htmlspecialchars(trim($_POST['system_version'] ?? ''), ENT_QUOTES);

        $timezone = $_POST['timezone'] ?? '';
        if (!in_array($timezone, $validTimezones, true)) {
            $formErrors[] = 'Invalid timezone selected.';
        } else {
            $toSave['ARMIS_TIMEZONE'] = $timezone;
        }
        $language = $_POST['language'] ?? '';
        if (!in_array($language, $validLanguages, true)) {
            $formErrors[] = 'Invalid language selected.';
        } else {
            $toSave['ARMIS_LANG'] = $language;
        }
        $theme = $_POST['theme'] ?? '';
        if (!in_array($theme, $validThemes, true)) {
            $formErrors[] = 'Invalid theme selected.';
        } else {
            $toSave['ARMIS_THEME'] = $theme;
        }

        // Module toggles (see is_module_enabled()'s doc comment in
        // config.php — persists correctly but doesn't gate access
        // anywhere yet, that's a separate, larger change).
        foreach (['admin', 'command', 'training', 'operations'] as $mod) {
            $toSave['ENABLE_' . strtoupper($mod) . '_MODULE'] = isset($_POST['modules'][$mod]);
        }

        // Allowed upload file extensions
        $allowedTypes = trim($_POST['allowed_types'] ?? '');
        if ($allowedTypes !== '' && !preg_match('/^[a-z0-9]+(?:\s*,\s*[a-z0-9]+)*$/i', $allowedTypes)) {
            $formErrors[] = 'Allowed File Types must be comma-separated file extensions only.';
        } else {
            $toSave['ALLOWED_FILE_TYPES'] = $allowedTypes;
        }

        if (empty($formErrors)) {
            $allSaved = true;
            foreach ($toSave as $key => $value) {
                if (!set_config($key, $value, $_SESSION['user_id'] ?? null)) {
                    $allSaved = false;
                }
            }
            if ($allSaved) {
                $message = 'Settings updated successfully.';
                $messageType = 'success';
            } else {
                $message = 'Could not save settings — the system_settings table may not exist yet. Run database/migrations/2026_08_28_add_system_settings_table.sql, then try again.';
                $messageType = 'danger';
            }
        } else {
            $message = implode(' ', $formErrors);
            $messageType = 'danger';
        }
        logAccess('admin', 'settings_update_attempt', empty($formErrors));
    }
}

// Helper function to get config value with default (using config.php function)

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
                            <p class="text-muted mb-0">Configure system-wide settings and preferences</p>
                        </div>
                        <div>
                            <button class="btn btn-success" onclick="exportSettings()">
                                <i class="fas fa-download"></i> Export Settings
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Alert Messages -->
            <?php if ($message): ?>
            <div class="alert alert-<?= $messageType ?> alert-dismissible fade show" role="alert">
                <?= htmlspecialchars($message) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>

            <form method="POST">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="update_settings">
                
                <!-- General Settings -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-globe"></i> General Settings
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="system_name" class="form-label">System Name</label>
                                <input type="text" class="form-control" id="system_name" name="system_name" 
                                       value="<?= htmlspecialchars($systemConfig['general']['system_name']) ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="system_version" class="form-label">System Version</label>
                                <input type="text" class="form-control" id="system_version" name="system_version" 
                                       value="<?= htmlspecialchars($systemConfig['general']['system_version']) ?>" readonly>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label for="timezone" class="form-label">Timezone</label>
                                <select class="form-select" id="timezone" name="timezone">
                                    <option value="UTC" <?= $systemConfig['general']['timezone'] === 'UTC' ? 'selected' : '' ?>>UTC</option>
                                    <option value="America/New_York" <?= $systemConfig['general']['timezone'] === 'America/New_York' ? 'selected' : '' ?>>Eastern Time</option>
                                    <option value="America/Chicago" <?= $systemConfig['general']['timezone'] === 'America/Chicago' ? 'selected' : '' ?>>Central Time</option>
                                    <option value="America/Denver" <?= $systemConfig['general']['timezone'] === 'America/Denver' ? 'selected' : '' ?>>Mountain Time</option>
                                    <option value="America/Los_Angeles" <?= $systemConfig['general']['timezone'] === 'America/Los_Angeles' ? 'selected' : '' ?>>Pacific Time</option>
                                    <option value="Europe/London" <?= $systemConfig['general']['timezone'] === 'Europe/London' ? 'selected' : '' ?>>London</option>
                                    <option value="Africa/Lusaka" <?= $systemConfig['general']['timezone'] === 'Africa/Lusaka' ? 'selected' : '' ?>>Lusaka</option>
                                </select>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="language" class="form-label">Language</label>
                                <select class="form-select" id="language" name="language">
                                    <option value="en" <?= $systemConfig['general']['language'] === 'en' ? 'selected' : '' ?>>English</option>
                                    <option value="fr" <?= $systemConfig['general']['language'] === 'fr' ? 'selected' : '' ?>>French</option>
                                    <option value="pt" <?= $systemConfig['general']['language'] === 'pt' ? 'selected' : '' ?>>Portuguese</option>
                                </select>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="theme" class="form-label">Theme</label>
                                <select class="form-select" id="theme" name="theme">
                                    <option value="military" <?= $systemConfig['general']['theme'] === 'military' ? 'selected' : '' ?>>Military</option>
                                    <option value="corporate" <?= $systemConfig['general']['theme'] === 'corporate' ? 'selected' : '' ?>>Corporate</option>
                                    <option value="modern" <?= $systemConfig['general']['theme'] === 'modern' ? 'selected' : '' ?>>Modern</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Security Settings -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-shield-alt"></i> Security Settings
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="session_timeout" class="form-label">Session Timeout (seconds)</label>
                                <input type="number" class="form-control" id="session_timeout" name="session_timeout" 
                                       value="<?= $systemConfig['security']['session_timeout'] ?>" min="300" max="86400">
                                <small class="form-text text-muted">Time before inactive users are logged out</small>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="max_login_attempts" class="form-label">Max Login Attempts</label>
                                <input type="number" class="form-control" id="max_login_attempts" name="max_login_attempts" 
                                       value="<?= $systemConfig['security']['max_login_attempts'] ?>" min="3" max="10">
                                <small class="form-text text-muted">Failed attempts before account lockout</small>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="lockout_time" class="form-label">Lockout Time (seconds)</label>
                                <input type="number" class="form-control" id="lockout_time" name="lockout_time" 
                                       value="<?= $systemConfig['security']['lockout_time'] ?>" min="300" max="3600">
                                <small class="form-text text-muted">Duration of account lockout</small>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="csrf_token_expiry" class="form-label">CSRF Token Expiry (seconds)</label>
                                <input type="number" class="form-control" id="csrf_token_expiry" name="csrf_token_expiry" 
                                       value="<?= $systemConfig['security']['csrf_token_expiry'] ?>" min="600" max="7200">
                                <small class="form-text text-muted">Security token validity period</small>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Module Settings -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-puzzle-piece"></i> Module Configuration
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-3 mb-3">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="enable_admin" name="modules[admin]" 
                                           <?= $systemConfig['modules']['admin'] ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="enable_admin">Admin Module</label>
                                </div>
                            </div>
                            <div class="col-md-3 mb-3">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="enable_command" name="modules[command]" 
                                           <?= $systemConfig['modules']['command'] ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="enable_command">Command Module</label>
                                </div>
                            </div>
                            <div class="col-md-3 mb-3">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="enable_training" name="modules[training]" 
                                           <?= $systemConfig['modules']['training'] ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="enable_training">Training Module</label>
                                </div>
                            </div>
                            <div class="col-md-3 mb-3">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="enable_operations" name="modules[operations]" 
                                           <?= $systemConfig['modules']['operations'] ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="enable_operations">Operations Module</label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Upload Settings -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-upload"></i> Upload Configuration
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="max_upload_size" class="form-label">Maximum Upload Size (bytes)</label>
                                <input type="number" class="form-control" id="max_upload_size" name="max_upload_size" 
                                       value="<?= $systemConfig['uploads']['max_size'] ?>">
                                <small class="form-text text-muted">Current: <?= number_format($systemConfig['uploads']['max_size'] / 1048576, 1) ?>MB</small>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="allowed_types" class="form-label">Allowed File Types</label>
                                <input type="text" class="form-control" id="allowed_types" name="allowed_types" 
                                       value="<?= htmlspecialchars($systemConfig['uploads']['allowed_types']) ?>">
                                <small class="form-text text-muted">Comma-separated file extensions</small>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Action Buttons -->
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <button type="submit" class="btn btn-primary me-2">
                                    <i class="fas fa-save"></i> Save Settings
                                </button>
                                <button type="button" class="btn btn-secondary" onclick="resetForm()">
                                    <i class="fas fa-undo"></i> Reset
                                </button>
                            </div>
                            <div>
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
    
    const dataStr = JSON.stringify(settings, null, 2);
    const dataBlob = new Blob([dataStr], {type: 'application/json'});
    const url = URL.createObjectURL(dataBlob);
    const link = document.createElement('a');
    link.href = url;
    link.download = 'armis_settings_' + new Date().toISOString().split('T')[0] + '.json';
    link.click();
    URL.revokeObjectURL(url);
}

function resetForm() {
    if (confirm('Are you sure you want to reset all settings to their current values?')) {
        location.reload();
    }
}
</script>

<?php include dirname(__DIR__) . '/shared/footer.php'; ?>
