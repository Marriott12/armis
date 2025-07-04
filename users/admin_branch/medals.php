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

// Handle delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_medal'])) {
    if (!Token::check($_POST['csrf'] ?? '')) {
        $errors[] = "Invalid CSRF token.";
    } else {
        $medID = intval($_POST['medID']);
        try {
            $db->delete('medals', ['medID', '=', $medID]);
            $success = "Medal deleted successfully.";
        } catch (Exception $e) {
            $errors[] = "Error deleting medal: " . htmlspecialchars($e->getMessage());
        }
    }
}

// Fetch medals
$medals = $db->query("SELECT * FROM medals ORDER BY dateCreated DESC")->results();
?>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet" />

<div class="container my-5">
    <div class="mb-3 d-flex justify-content-between align-items-center">
        <a href="../admin_branch.php" class="btn btn-outline-secondary" aria-label="Back to Admin Branch">
            <i class="fa fa-arrow-left"></i> Back to Admin Branch
        </a>
        <a href="create_medal.php" class="btn btn-info"><i class="fa fa-plus"></i> Add New Medal</a>
    </div>
    <div class="card shadow-sm">
        <div class="card-header bg-info text-white">
            <h4 class="mb-0"><i class="fa fa-medal"></i> Medals List</h4>
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
            <?php if (count($medals) === 0): ?>
                <div class="alert alert-warning">No medals found.</div>
            <?php else: ?>
            <div class="table-responsive">
                <table class="table table-bordered align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>#</th>
                            <th>Name</th>
                            <th>Description</th>
                            <th>Category</th>
                            <th>Date Established</th>
                            <th>Created By</th>
                            <th>Date Created</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($medals as $i => $medal): ?>
                        <tr>
                            <td><?=($i+1)?></td>
                            <td><?=htmlspecialchars($medal->medName)?></td>
                            <td><?=htmlspecialchars($medal->medDesc)?></td>
                            <td><?=htmlspecialchars($medal->category)?></td>
                            <td><?=htmlspecialchars($medal->dateEstablished)?></td>
                            <td><?=htmlspecialchars($medal->createdBy)?></td>
                            <td><?=htmlspecialchars($medal->dateCreated)?></td>
                            <td>
                                <a href="edit_medal.php?medID=<?=$medal->medID?>" class="btn btn-sm btn-warning"><i class="fa fa-edit"></i> Edit</a>
                                <form method="post" action="" class="d-inline" onsubmit="return confirm('Delete this medal?');">
                                    <input type="hidden" name="csrf" value="<?=htmlspecialchars($csrfToken)?>">
                                    <input type="hidden" name="medID" value="<?=intval($medal->medID)?>">
                                    <button type="submit" name="delete_medal" class="btn btn-sm btn-danger"><i class="fa fa-trash"></i> Delete</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
            <div class="mt-3">
                <a href="assign_medal.php" class="btn btn-success"><i class="fa fa-user-plus"></i> Assign Medal to Staff</a>
            </div>
        </div>
    </div>
</div>
<?php require_once $abs_us_root . $us_url_root . 'users/includes/html_footer.php'; ?>