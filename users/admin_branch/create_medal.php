<?php
require_once '../init.php';
require_once $abs_us_root . $us_url_root . 'users/includes/template/prep.php';

if (!securePage($_SERVER['PHP_SELF'])) { die(); }

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

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Token::check($_POST['csrf'] ?? '')) {
        $errors[] = "Invalid CSRF token.";
    } else {
        $medName = trim($_POST['medName'] ?? '');
        $medDesc = trim($_POST['medDesc'] ?? '');

        if ($medName === '') $errors[] = "Medal name is required.";

        if (empty($errors)) {
            try {
                $db->insert('medals', [
                    'medName' => $medName,
                    'medDesc' => $medDesc,
                    'createdBy' => $user->data()->username ?? 'admin',
                    'dateCreated' => date('Y-m-d H:i:s')
                ]);
                $success = "Medal created successfully.";
            } catch (Exception $e) {
                $errors[] = "Error creating medal: " . htmlspecialchars($e->getMessage());
            }
        }
    }
}
?>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet" />

<div class="container my-5">
    <div class="mb-3">
        <a href="medals.php" class="btn btn-outline-secondary"><i class="fa fa-arrow-left"></i> Back to Medals List</a>
    </div>
    <div class="card shadow-sm">
        <div class="card-header bg-info text-white">
            <h4 class="mb-0"><i class="fa fa-plus"></i> Create New Medal</h4>
        </div>
        <div class="card-body">
            <?php if ($success): ?>
                <div class="alert alert-success"><?=htmlspecialchars($success)?></div>
            <?php endif; ?>
            <?php if ($errors): ?>
                <div class="alert alert-danger">
                    <ul class="mb-0">
                        <?php foreach ($errors as $err): ?>
                            <li><?=htmlspecialchars($err)?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
            <form method="post" action="" autocomplete="off">
                <input type="hidden" name="csrf" value="<?=htmlspecialchars($csrfToken)?>">
                <div class="mb-3">
                    <label for="medName" class="form-label">Medal Name <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="medName" name="medName" required value="<?=htmlspecialchars($_POST['medName'] ?? '')?>">
                </div>
                <div class="mb-3">
                    <label for="medDesc" class="form-label">Description</label>
                    <textarea class="form-control" id="medDesc" name="medDesc" rows="3"><?=htmlspecialchars($_POST['medDesc'] ?? '')?></textarea>
                </div>
                <button type="submit" class="btn btn-success"><i class="fa fa-save"></i> Create Medal</button>
            </form>
        </div>
    </div>
</div>
<?php require_once $abs_us_root . $us_url_root . 'users/includes/html_footer.php'; ?>