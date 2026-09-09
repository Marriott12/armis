<?php
// Define module constants
define('ARMIS_ADMIN_BRANCH', true);

// Include admin branch authentication and database
require_once __DIR__ . '/includes/auth.php';
require_once dirname(__DIR__) . '/shared/database_connection.php';
require_once dirname(__DIR__) . '/shared/csrf.php';
require_once __DIR__ . '/includes/settings.php';

// Require authentication
requireAuth();
requireModuleAccess('admin_branch');

// System settings previously linked from the sidebar (and from a dead
// NAVIGATION_MENU constant in includes/config.php) but this file never
// existed — a 404 behind an otherwise-valid-looking link. Restricted to
// PERM_SYSTEM_SETTINGS, same tier as Rank Tools / Rollback Promotions.
if (!hasPermission(PERM_SYSTEM_SETTINGS)) {
    http_response_code(403);
    die('Access denied. System settings require system-administrator permission.');
}

$pageTitle = "System Settings - Admin Branch";
$moduleName = "Admin Branch";
$moduleIcon = "users-cog";
$currentPage = "settings";

$sidebarLinks = []; // set by shared nav include below
require_once __DIR__ . '/includes/sidebar_nav.php';

$success = '';
$errors = [];

// These are the only settings with a real effect on the running app —
// ajax_search.php's default page size reads staff_list_page_size (see
// there). Everything else in the old includes/config.php's PAGINATION
// / UPLOAD_SETTINGS constants was dead — nothing consumed them — so
// this page starts small and real rather than reintroducing more
// settings that don't actually do anything.
$fields = [
    'staff_list_page_size' => [
        'label' => 'Staff list default page size',
        'type' => 'number',
        'min' => 5,
        'max' => 200,
        'help' => 'Default number of rows per page in Advanced Search results.',
    ],
    'max_upload_size_mb' => [
        'label' => 'Max document upload size (MB)',
        'type' => 'number',
        'min' => 1,
        'max' => 50,
        'help' => 'Informational only — the actual hard limit is enforced in shared/file_upload_handler.php for all modules, not just admin_branch. Lower this to advise staff of a stricter practical limit; raising it above the shared handler\'s limit has no effect.',
    ],
    'allowed_upload_types' => [
        'label' => 'Allowed upload file extensions',
        'type' => 'text',
        'help' => 'Comma-separated, informational only for the same reason as above.',
    ],
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $validatedValues = [];
    foreach ($fields as $key => $meta) {
        if (!isset($_POST[$key])) {
            continue;
        }
        $value = trim($_POST[$key]);
        if ($meta['type'] === 'number') {
            $value = (int) $value;
            if ($value < $meta['min'] || $value > $meta['max']) {
                $errors[] = "{$meta['label']} must be between {$meta['min']} and {$meta['max']}.";
                continue;
            }
            $value = (string) $value;
        } elseif ($key === 'allowed_upload_types' && !preg_match('/^[a-z0-9]+(?:\s*,\s*[a-z0-9]+)*$/i', $value)) {
            $errors[] = "{$meta['label']} must contain comma-separated file extensions only.";
            continue;
        }
        $validatedValues[$key] = $value;
    }
    if (empty($errors)) {
        $allSaved = true;
        foreach ($validatedValues as $key => $value) {
            if (!setAdminBranchSetting($key, $value, $_SESSION['user_id'] ?? null)) {
                $allSaved = false;
            }
        }
        if ($allSaved) {
            $success = 'Settings updated.';
        } else {
            $errors[] = 'Could not save settings — the admin_branch_settings table may not exist yet. Run database/migrations/2026_08_26_add_admin_branch_settings_table.sql, then try again.';
        }
    }
}

$currentValues = [];
foreach ($fields as $key => $meta) {
    $default = match ($key) {
        'staff_list_page_size' => '25',
        'max_upload_size_mb' => '10',
        'allowed_upload_types' => 'jpg,jpeg,png,pdf,doc,docx,xls,xlsx',
        default => '',
    };
    $currentValues[$key] = getAdminBranchSetting($key, $default);
}

include dirname(__DIR__) . '/shared/header.php';
include dirname(__DIR__) . '/shared/sidebar.php';
?>
<div class="content-wrapper with-sidebar">
    <div class="container-fluid py-4">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
            <div><h1 class="h3 mb-1"><i class="fas fa-cogs me-2"></i>System Settings</h1><p class="text-muted mb-0">Manage defaults used by Admin Branch workflows.</p></div>
            <a class="btn btn-outline-secondary" href="<?= ADMIN_BRANCH_URL ?>/index.php"><i class="fas fa-arrow-left me-1"></i> Admin Dashboard</a>
        </div>

        <?php if ($success): ?>
            <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>
        <?php if ($errors): ?>
            <div class="alert alert-danger">
                <ul class="mb-0">
                    <?php foreach ($errors as $err): ?>
                        <li><?= htmlspecialchars($err) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <div class="card shadow-sm">
            <div class="card-body">
                <form method="POST">
                    <?= csrf_field() ?>
                    <?php foreach ($fields as $key => $meta): ?>
                        <div class="mb-3">
                            <label for="<?= htmlspecialchars($key) ?>" class="form-label"><?= htmlspecialchars($meta['label']) ?></label>
                            <input
                                type="<?= $meta['type'] === 'number' ? 'number' : 'text' ?>"
                                class="form-control"
                                id="<?= htmlspecialchars($key) ?>"
                                name="<?= htmlspecialchars($key) ?>"
                                value="<?= htmlspecialchars($currentValues[$key]) ?>"
                                <?php if ($meta['type'] === 'number'): ?>
                                    min="<?= (int) $meta['min'] ?>" max="<?= (int) $meta['max'] ?>"
                                <?php endif; ?>
                            >
                            <div class="form-text"><?= htmlspecialchars($meta['help']) ?></div>
                        </div>
                    <?php endforeach; ?>
                    <button type="submit" class="btn btn-primary"><i class="fa fa-save me-1"></i> Save Settings</button>
                </form>
            </div>
        </div>
    </div>
</div>
<?php include dirname(__DIR__) . '/shared/footer.php'; ?>
