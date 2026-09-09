<?php
require_once dirname(__DIR__) . '/shared/module_auth.php';
require_once dirname(__DIR__) . '/shared/csrf.php';

bootModule([
    'module' => 'training',
    'page' => 'setup',
    'pageTitle' => 'Training Database Setup - ARMIS',
    'moduleName' => 'Training',
    'moduleIcon' => 'graduation-cap',
]);

$message = '';
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    if (!in_array(strtolower($_SESSION['role'] ?? ''), ['admin', 'administrator', 'superadmin'], true)) {
        http_response_code(403);
        $error = 'Only system administrators can apply database migrations.';
    } else {
        try {
            $sql = file_get_contents(dirname(__DIR__) . '/database/migrations/2026_09_02_create_training_tables.sql');
            if ($sql === false) throw new RuntimeException('Training schema migration could not be read.');
            $pdo = getDbConnection();
            foreach (array_filter(array_map('trim', explode(';', $sql))) as $statement) $pdo->exec($statement);
            $message = 'Training database tables are ready.';
        } catch (Throwable $exception) {
            $error = 'Setup failed: ' . $exception->getMessage();
        }
    }
}

include dirname(__DIR__) . '/shared/header.php';
include dirname(__DIR__) . '/shared/sidebar.php';
?>
<div class="content-wrapper with-sidebar"><div class="container-fluid"><div class="main-content">
  <h1 class="section-title"><i class="fas fa-database"></i> Training Database Setup</h1>
  <?php if ($message): ?><div class="alert alert-success" role="status"><?= htmlspecialchars($message) ?></div><?php endif; ?>
  <?php if ($error): ?><div class="alert alert-danger" role="alert"><?= htmlspecialchars($error) ?></div><?php endif; ?>
  <p class="text-muted">Creates the course catalog, training-session, and personnel-assignment tables. Existing records are retained.</p>
  <?php if (in_array(strtolower($_SESSION['role'] ?? ''), ['admin', 'administrator', 'superadmin'], true)): ?><form method="post"><?= csrf_field() ?><button class="btn btn-primary" type="submit"><i class="fas fa-database"></i> Set Up Training Database</button></form><?php else: ?><div class="alert alert-info">Ask a system administrator to run the database setup.</div><?php endif; ?>
</div></div></div>
<?php include dirname(__DIR__) . '/shared/footer.php'; ?>