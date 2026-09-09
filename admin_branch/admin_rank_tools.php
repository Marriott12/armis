<?php
require_once __DIR__ . '/includes/auth.php';
requireAuth();
requireModuleAccess('admin_branch');
if (!hasPermission(PERM_EDIT_STAFF)) {
    http_response_code(403);
    exit('Access denied.');
}

if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['csrf_token'];
$pdo = getDbConnection();
$tmpDir = dirname(__DIR__) . '/tmp';
$message = '';
$messageType = 'info';
$selectedSvcNo = trim((string)($_GET['svcNo'] ?? $_POST['svcNo'] ?? ''));

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!isset($_POST['csrf_token']) || !hash_equals($csrfToken, (string)$_POST['csrf_token'])) {
            throw new RuntimeException('Invalid security token. Please reload the page and try again.');
        }

        $action = $_POST['action'] ?? '';
        if ($action === 'correct_rank') {
            $rankId = trim((string)($_POST['rankId'] ?? ''));
            if ($selectedSvcNo === '' || $rankId === '') {
                throw new InvalidArgumentException('Select both a staff member and a rank.');
            }

            $rankStmt = $pdo->prepare('SELECT rankId FROM `rank` WHERE rankId = ? LIMIT 1');
            $rankStmt->execute([$rankId]);
            if (!$rankStmt->fetchColumn()) {
                throw new InvalidArgumentException('The selected rank does not exist.');
            }

            $update = $pdo->prepare('UPDATE staff SET rankId = ? WHERE svcNo = ?');
            $update->execute([$rankId, $selectedSvcNo]);
            $message = $update->rowCount() > 0
                ? "Rank for {$selectedSvcNo} was updated to {$rankId}."
                : "No change was needed for {$selectedSvcNo}.";
            $messageType = 'success';
        } elseif ($action === 'run_update') {
            $pdo->beginTransaction();
            $find = $pdo->query("SELECT s.svcNo, s.rankId, s.tempRank, s.subRank, COALESCE(r_temp.rankId, r_sub.rankId) AS matchedRankId, r_temp.rankId AS tempMatch, r_sub.rankId AS subMatch
                FROM staff s
                LEFT JOIN `rank` r_temp ON r_temp.rankId = CONCAT('T', s.tempRank)
                LEFT JOIN `rank` r_sub ON r_sub.rankId = s.subRank
                WHERE (r_temp.rankId IS NOT NULL OR r_sub.rankId IS NOT NULL)
                  AND (s.rankId IS NULL OR s.rankId <> COALESCE(r_temp.rankId, r_sub.rankId))");
            $rows = $find->fetchAll(PDO::FETCH_ASSOC);
            if (!$rows) {
                $pdo->commit();
                $message = 'No staff records require rank correction.';
                $messageType = 'info';
            } else {
                if (!is_dir($tmpDir)) {
                    mkdir($tmpDir, 0775, true);
                }
                $timestamp = date('Ymd_His');
                $backupName = "general_rank_update_backup_{$timestamp}.csv";
                $backupPath = $tmpDir . DIRECTORY_SEPARATOR . $backupName;
                $fp = fopen($backupPath, 'wb');
                fputcsv($fp, ['svcNo', 'rankId', 'tempRank', 'subRank', 'tempMatch', 'subMatch', 'matchedRankId']);
                foreach ($rows as $row) {
                    fputcsv($fp, [$row['svcNo'], $row['rankId'], $row['tempRank'], $row['subRank'], $row['tempMatch'], $row['subMatch'], $row['matchedRankId']]);
                }
                fclose($fp);

                $tempUpdate = $pdo->exec("UPDATE staff s JOIN `rank` r ON r.rankId = CONCAT('T', s.tempRank)
                    SET s.rankId = r.rankId WHERE s.tempRank IS NOT NULL AND s.tempRank <> ''
                    AND (s.rankId IS NULL OR s.rankId <> r.rankId)");
                $subUpdate = $pdo->exec("UPDATE staff s JOIN `rank` r ON r.rankId = s.subRank
                    SET s.rankId = r.rankId WHERE s.subRank IS NOT NULL AND s.subRank <> ''
                    AND (s.rankId IS NULL OR s.rankId <> r.rankId)");
                $pdo->commit();
                $message = 'Rank repair completed. ' . (($tempUpdate ?: 0) + ($subUpdate ?: 0)) . " records updated; backup created as {$backupName}.";
                $messageType = 'success';
            }
        } elseif ($action === 'restore') {
            $backupName = basename((string)($_POST['backup_file'] ?? ''));
            $backupPath = $tmpDir . DIRECTORY_SEPARATOR . $backupName;
            if (!preg_match('/^general_rank_update_backup_[0-9_]+\.csv$/', $backupName) || !is_file($backupPath)) {
                throw new InvalidArgumentException('Backup file not found.');
            }
            $fp = fopen($backupPath, 'rb');
            $header = fgetcsv($fp);
            $svcIndex = array_search('svcNo', $header ?: [], true);
            $rankIndex = array_search('rankId', $header ?: [], true);
            if ($svcIndex === false || $rankIndex === false) {
                fclose($fp);
                throw new InvalidArgumentException('Backup file format is invalid.');
            }
            $pdo->beginTransaction();
            $restore = $pdo->prepare('UPDATE staff SET rankId = ? WHERE svcNo = ?');
            $restored = 0;
            while (($row = fgetcsv($fp)) !== false) {
                $svcNo = trim((string)($row[$svcIndex] ?? ''));
                $rankId = trim((string)($row[$rankIndex] ?? ''));
                if ($svcNo !== '') {
                    $restore->execute([$rankId !== '' ? $rankId : null, $svcNo]);
                    $restored += $restore->rowCount();
                }
            }
            fclose($fp);
            $pdo->commit();
            $message = "Restored {$restored} staff rank values from {$backupName}.";
            $messageType = 'success';
        }
    }
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $message = $e->getMessage();
    $messageType = 'danger';
}

$ranks = $pdo->query('SELECT rankId, rankIndex FROM `rank` WHERE rankIndex IS NOT NULL ORDER BY rankIndex, rankId')->fetchAll(PDO::FETCH_ASSOC);
$staff = null;
if ($selectedSvcNo !== '') {
    $staffStmt = $pdo->prepare('SELECT s.svcNo, s.fName, s.lName, s.rankId, s.unitId, s.svcStatus, r.rankIndex FROM staff s LEFT JOIN `rank` r ON s.rankId = r.rankId WHERE s.svcNo = ? LIMIT 1');
    $staffStmt->execute([$selectedSvcNo]);
    $staff = $staffStmt->fetch(PDO::FETCH_ASSOC) ?: null;
}
$backups = glob($tmpDir . '/general_rank_update_backup_*.csv') ?: [];
rsort($backups);

$pageTitle = 'Admin Rank Tools - ARMIS';
$moduleName = 'Admin Branch';
$currentPage = 'rank-tools';
require_once dirname(__DIR__) . '/shared/header.php';
require_once __DIR__ . '/includes/sidebar_nav.php';
require_once dirname(__DIR__) . '/shared/sidebar.php';
?>

<div class="content-wrapper with-sidebar">
    <div class="container-fluid">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
            <div>
                <h1 class="h3 mb-1">Rank Correction Tools</h1>
                <p class="text-muted mb-0">Correct staff rank assignments and repair legacy rank mappings.</p>
            </div>
            <a class="btn btn-outline-secondary" href="<?= ADMIN_BRANCH_URL ?>/index.php"><i class="fas fa-arrow-left me-1"></i> Admin Dashboard</a>
        </div>

        <?php if ($message !== ''): ?>
            <div class="alert alert-<?= htmlspecialchars($messageType) ?>" role="alert"><?= nl2br(htmlspecialchars($message)) ?></div>
        <?php endif; ?>

        <div class="row g-4">
            <div class="col-12 col-xl-7">
                <div class="card h-100">
                    <div class="card-header"><h2 class="h5 mb-0"><i class="fas fa-user-edit me-2"></i>Correct an Individual Rank</h2></div>
                    <div class="card-body">
                        <form method="get" class="row g-3 mb-4">
                            <div class="col-sm-8">
                                <label class="form-label" for="svcNo">Service number</label>
                                <input class="form-control" id="svcNo" name="svcNo" value="<?= htmlspecialchars($selectedSvcNo) ?>" required>
                            </div>
                            <div class="col-sm-4 d-flex align-items-end"><button class="btn btn-primary w-100" type="submit"><i class="fas fa-search me-1"></i> Find Staff</button></div>
                        </form>
                        <?php if ($selectedSvcNo !== '' && !$staff): ?>
                            <div class="alert alert-warning">No staff record was found for that service number.</div>
                        <?php elseif ($staff): ?>
                            <div class="table-responsive mb-3"><table class="table table-sm align-middle mb-0">
                                <tr><th scope="row">Name</th><td><?= htmlspecialchars(trim(($staff['fName'] ?? '') . ' ' . ($staff['lName'] ?? ''))) ?></td></tr>
                                <tr><th scope="row">Unit</th><td><?= htmlspecialchars($staff['unitId'] ?? 'Unassigned') ?></td></tr>
                                <tr><th scope="row">Current rank</th><td><?= htmlspecialchars($staff['rankId'] ?? 'Unassigned') ?></td></tr>
                                <tr><th scope="row">Status</th><td><?= htmlspecialchars($staff['svcStatus'] ?? 'Unknown') ?></td></tr>
                            </table></div>
                            <form method="post" onsubmit="return confirm('Update this staff member\'s rank?');">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                                <input type="hidden" name="action" value="correct_rank">
                                <input type="hidden" name="svcNo" value="<?= htmlspecialchars($staff['svcNo']) ?>">
                                <label class="form-label" for="rankId">New rank</label>
                                <div class="input-group"><select class="form-select" id="rankId" name="rankId" required><option value="">Select rank</option>
                                    <?php foreach ($ranks as $rank): ?><option value="<?= htmlspecialchars($rank['rankId']) ?>" <?= $rank['rankId'] === $staff['rankId'] ? 'selected' : '' ?>><?= htmlspecialchars($rank['rankId']) ?> (<?= htmlspecialchars($rank['rankIndex']) ?>)</option><?php endforeach; ?>
                                </select><button class="btn btn-success" type="submit"><i class="fas fa-save me-1"></i> Save Rank</button></div>
                            </form>
                        <?php else: ?>
                            <p class="text-muted mb-0">Enter a service number to load the staff record and available ranks.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="col-12 col-xl-5">
                <div class="card mb-4">
                    <div class="card-header"><h2 class="h5 mb-0"><i class="fas fa-tools me-2"></i>Repair Legacy Mappings</h2></div>
                    <div class="card-body">
                        <p class="text-muted">Matches <code>tempRank</code> first, then <code>subRank</code>, and updates only records whose current rank differs. A CSV backup is created before changes.</p>
                        <form method="post" onsubmit="return confirm('Run the rank repair and update matching staff records?');">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>"><input type="hidden" name="action" value="run_update">
                            <button class="btn btn-warning w-100" type="submit"><i class="fas fa-wrench me-1"></i> Run Rank Repair</button>
                        </form>
                    </div>
                </div>
                <div class="card">
                    <div class="card-header"><h2 class="h5 mb-0"><i class="fas fa-history me-2"></i>Backups</h2></div>
                    <div class="card-body">
                        <?php if (!$backups): ?><p class="text-muted mb-0">No rank repair backups found.</p><?php else: ?>
                            <div class="list-group list-group-flush"> <?php foreach ($backups as $backup): $name = basename($backup); ?>
                                <div class="list-group-item px-0 d-flex flex-wrap justify-content-between align-items-center gap-2"><a href="/Armis2/tmp/<?= rawurlencode($name) ?>" download><?= htmlspecialchars($name) ?></a><form method="post" onsubmit="return confirm('Restore rank values from this backup?');"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>"><input type="hidden" name="action" value="restore"><input type="hidden" name="backup_file" value="<?= htmlspecialchars($name) ?>"><button class="btn btn-sm btn-outline-danger" type="submit">Restore</button></form></div>
                            <?php endforeach; ?></div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once dirname(__DIR__) . '/shared/footer.php'; ?>
