<?php
// Define module constants
define('ARMIS_ADMIN_BRANCH', true);
define('ARMIS_DEVELOPMENT', true); // Set to false in production

// Include admin branch authentication and database
require_once __DIR__ . '/includes/auth.php';

// Include RBAC system
require_once dirname(__DIR__) . '/shared/rbac.php';

// Require authentication and admin privileges
requireAuth();

// Check if user has access to admin_branch module
requireModuleAccess('admin_branch');

$pageTitle = "Create Medal";
$moduleName = "Admin Branch";
$moduleIcon = "users-cog";
$currentPage = "create medal";

$sidebarLinks = [
    ['title' => 'Dashboard', 'url' => '/Armis2/admin_branch/index.php', 'icon' => 'tachometer-alt', 'page' => 'dashboard'],
    ['title' => 'Create Staff', 'url' => '/Armis2/admin_branch/create_staff.php', 'icon' => 'user-plus', 'page' => 'create_staff'],
    ['title' => 'Edit Staff', 'url' => '/Armis2/admin_branch/edit_staff.php', 'icon' => 'user-edit', 'page' => 'edit_staff'],
    ['title' => 'Appointments', 'url' => '/Armis2/admin_branch/appointments.php', 'icon' => 'briefcase', 'page' => 'appointments'],
    ['title' => 'Promotions', 'url' => '/Armis2/admin_branch/promote_staff.php', 'icon' => 'arrow-up', 'page' => 'promotions'],
    ['title' => 'Medals', 'url' => '/Armis2/admin_branch/medals.php', 'icon' => 'medal', 'page' => 'medals'],
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
            ['title' => 'Medals', 'url' => '/Armis2/admin_branch/reports_medals.php'],
        ]
    ],
    ['title' => 'System Settings', 'url' => '/Armis2/admin_branch/system_settings.php', 'icon' => 'cogs', 'page' => 'settings']
];

// CSRF Token
if (!isset($_SESSION)) { session_start(); }
if (!function_exists('Token')) {
    class Token {
        public static function generate() {
            if (!isset($_SESSION['csrf_token'])) {
                $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            }
            return $_SESSION['csrf_token'];
        }
        public static function check($token) {
            return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
        }
    }
}
$csrfToken = Token::generate();

$errors = [];
$success = false;

// Use PDO for DB connection
require_once dirname(__DIR__) . '/shared/database_connection.php';
$pdo = getDbConnection();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Token::check($_POST['csrf'] ?? '')) {
        $errors[] = "Invalid CSRF token.";
    } else {
        $medalName = trim($_POST['medal_name'] ?? '');
        $medalDesc = trim($_POST['medal_desc'] ?? '');
        // Image upload handling
        $imagePath = '';
        if (isset($_FILES['medal_image']) && $_FILES['medal_image']['error'] !== UPLOAD_ERR_NO_FILE) {
            $file = $_FILES['medal_image'];
            $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
            $allowedExts = ['jpg', 'jpeg', 'png', 'gif'];
            $maxSize = 5 * 1024 * 1024; // 5MB
            if ($file['error'] !== UPLOAD_ERR_OK) {
                $errors[] = "Error uploading image.";
            } elseif (!in_array(mime_content_type($file['tmp_name']), $allowedTypes)) {
                $errors[] = "Invalid image type. Only JPG, PNG, GIF allowed.";
            } elseif ($file['size'] > $maxSize) {
                $errors[] = "Image size must be under 5MB.";
            } else {
                $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                if (!in_array($ext, $allowedExts)) {
                    $errors[] = "Invalid file extension. Only JPG, PNG, GIF allowed.";
                } else {
                    // Optional: check image dimensions (e.g., max 2000x2000)
                    $imgInfo = @getimagesize($file['tmp_name']);
                    if ($imgInfo && ($imgInfo[0] > 2000 || $imgInfo[1] > 2000)) {
                        $errors[] = "Image dimensions must be under 2000x2000 pixels.";
                    } else {
                        $safeName = 'medal_' . uniqid() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
                        $uploadDir = dirname(__DIR__) . '/assets/medals/';
                        if (!is_dir($uploadDir)) {
                            mkdir($uploadDir, 0755, true);
                        }
                        $dest = $uploadDir . $safeName;
                        if (move_uploaded_file($file['tmp_name'], $dest)) {
                            $imagePath = 'assets/medals/' . $safeName;
                        } else {
                            $errors[] = "Failed to save uploaded image.";
                        }
                    }
                }
            }
        }

        // Validation
        if ($medalName === '') $errors[] = "Medal name is required.";
        if (mb_strlen($medalName) > 100) $errors[] = "Medal name must be under 100 characters.";
        if (mb_strlen($medalDesc) > 500) $errors[] = "Description must be under 500 characters.";
        // Check for duplicate medal name (case-insensitive, trimmed)
        if ($medalName !== '') {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM medals WHERE LOWER(TRIM(name)) = LOWER(TRIM(?))");
            $stmt->execute([$medalName]);
            if ($stmt->fetchColumn() > 0) {
                $errors[] = "A medal with this name already exists.";
            }
        }

        if (empty($errors)) {
            try {
                $stmt = $pdo->prepare("INSERT INTO medals (name, description, image_path, created_at) VALUES (?, ?, ?, ?)");
                $now = date('Y-m-d H:i:s');
                $stmt->execute([
                    $medalName,
                    $medalDesc,
                    $imagePath,
                    $now
                ]);
                // Audit log
                error_log("[AUDIT] Medal '$medalName' created by " . (isset($user) && method_exists($user, 'data') ? ($user->data()->username ?? 'admin') : (isset($_SESSION['username']) ? $_SESSION['username'] : 'admin')) . " on $now");
                $success = "Medal created successfully.";
                // Optionally regenerate CSRF token after success
                $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
                $csrfToken = $_SESSION['csrf_token'];
                $_POST = [];
            } catch (Exception $e) {
                $errors[] = "Error creating medal: " . htmlspecialchars($e->getMessage());
            }
        }
    }
}
?>

<?php include dirname(__DIR__) . '/shared/header.php'; ?>
<?php include dirname(__DIR__) . '/shared/sidebar.php'; ?>
<div class="content-wrapper with-sidebar">
    <div class="container-fluid p-4">
        <div class="mb-3 d-flex flex-wrap gap-2">
            <a href="medals.php" class="btn btn-outline-secondary">
                <i class="fa fa-arrow-left"></i> Back to Medals List
            </a>
            <a href="assign_medal.php" class="btn btn-outline-primary">
                <i class="fa fa-medal"></i> Assign Medal
            </a>
        </div>
        <h2 class="mb-3"><i class="fa fa-medal"></i> Add Medal</h2>
        <div class="card shadow-sm">
            <div class="card-header bg-info text-white">
                <h4 class="mb-0"><i class="fa fa-plus"></i> Create New Medal</h4>
            </div>
            <div class="card-body">
                <?php if ($success): ?>
                    <div class="alert alert-success" role="alert"><?=htmlspecialchars($success)?></div>
                <?php endif; ?>
                <?php if ($errors): ?>
                    <div class="alert alert-danger" role="alert">
                        <ul class="mb-0">
                            <?php foreach ($errors as $err): ?>
                                <li><?=htmlspecialchars($err)?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>
                <form method="post" action="" enctype="multipart/form-data" autocomplete="off" aria-label="Create Medal Form" id="createMedalForm">
                    <input type="hidden" name="csrf" value="<?=htmlspecialchars($csrfToken)?>">
                    <div class="mb-3">
                        <label for="medal_name" class="form-label" aria-label="Medal Name">Medal Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="medal_name" name="medal_name" maxlength="100" required aria-required="true" value="<?=htmlspecialchars($_POST['medal_name'] ?? '')?>">
                    </div>
                    <div class="mb-3">
                        <label for="medal_desc" class="form-label" aria-label="Description">Description</label>
                        <textarea class="form-control" id="medal_desc" name="medal_desc" rows="3" maxlength="500"><?=htmlspecialchars($_POST['medal_desc'] ?? '')?></textarea>
                    </div>
                    <div class="mb-3">
                        <label for="medal_image" class="form-label">Medal Image</label>
                        <input type="file" class="form-control" id="medal_image" name="medal_image" accept="image/*">
                        <small class="text-muted">Optional. JPG, PNG, GIF. Max 5MB. Max 2000x2000 px.</small>
                    </div>
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-success" id="createMedalBtn"><i class="fa fa-save"></i> Create Medal</button>
                        <a href="medals.php" class="btn btn-outline-secondary"><i class="fa fa-list"></i> Medals List</a>
                        <a href="assign_medal.php" class="btn btn-outline-primary"><i class="fa fa-medal"></i> Assign Medal</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<script>
document.getElementById('createMedalForm').addEventListener('submit', function() {
    document.getElementById('createMedalBtn').disabled = true;
    document.getElementById('createMedalBtn').innerText = 'Creating...';
});
</script>
<?php include dirname(__DIR__) . '/shared/footer.php'; ?>