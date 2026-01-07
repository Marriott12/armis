<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/analytics.php';
requireAuth();
requireModuleAccess('admin_branch');
if (!hasPermission(PERM_CREATE_STAFF)) {
    header('HTTP/1.1 403 Forbidden'); die('Access denied.');
}

$tmpDir = dirname(__DIR__) . '/tmp';
$backups = glob($tmpDir . '/general_rank_update_backup_*.csv');
$svcs = glob($tmpDir . '/general_rank_update_svcnos_*.csv');

// Run general update action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    if ($action === 'run_update') {
        // Confirm nonce / CSRF
        $out = shell_exec('php "' . realpath(__DIR__ . '/../tools/general_update_rankid.php') . '" 2>&1');
        $message = "Update executed. Output:\n" . $out;
    } elseif ($action === 'restore' && !empty($_POST['backup_file'])) {
        $backup = basename($_POST['backup_file']);
        $restorePath = dirname(__DIR__) . '/tmp/' . $backup;
        if (file_exists($restorePath)) {
            $out = shell_exec('php "' . realpath(__DIR__ . '/../tools/restore_rank_backup.php') . '" ' . escapeshellarg($restorePath) . ' 2>&1');
            $message = "Restore executed. Output:\n" . $out;
        } else {
            $message = "Backup file not found.";
        }
    }
}

?><!doctype html>
<html><head><meta charset="utf-8"><title>Admin Rank Tools</title></head><body>
<h1>Rank Update Tools</h1>
<?php if (!empty($message)): ?><pre><?php echo htmlspecialchars($message); ?></pre><?php endif; ?>
<h2>Run General Rank Update</h2>
<form method="post" onsubmit="return confirm('Are you sure you want to run the general rank update? This will modify staff.rankId.');">
    <input type="hidden" name="action" value="run_update">
    <button type="submit">Run General Update Now</button>
</form>

<h2>Backups</h2>
<?php if (empty($backups)): ?>
    <p>No backup files found in `tmp/`.</p>
<?php else: ?>
    <ul>
    <?php foreach ($backups as $b): $bn = basename($b); ?>
        <li>
            <a href="/Armis2/tmp/<?php echo urlencode($bn); ?>" download><?php echo htmlspecialchars($bn); ?></a>
            <form method="post" style="display:inline;margin-left:10px;" onsubmit="return confirm('Restore rankId values from <?php echo htmlspecialchars($bn); ?>?');">
                <input type="hidden" name="action" value="restore"><input type="hidden" name="backup_file" value="<?php echo htmlspecialchars($bn); ?>">
                <button type="submit">Restore From Backup</button>
            </form>
        </li>
    <?php endforeach; ?>
    </ul>
<?php endif; ?>

<h2>SvcNo CSVs</h2>
<?php if (empty($svcs)): ?>
    <p>No svcNo summary files found.</p>
<?php else: ?>
    <ul>
    <?php foreach ($svcs as $s): $sn = basename($s); ?>
        <li><a href="/Armis2/tmp/<?php echo urlencode($sn); ?>" download><?php echo htmlspecialchars($sn); ?></a></li>
    <?php endforeach; ?>
    </ul>
<?php endif; ?>

<p><a href="/Armis2/admin_branch/index.php">Back to Admin Dashboard</a></p>
</body></html>
