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

// Initialize global database connection
try {
    $pdo = getDbConnection();
} catch (Exception $e) {
    error_log("Database connection failed in admin/reports.php: " . $e->getMessage());
    die("Database connection failed. Please check your configuration.");
}

$pageTitle = "System Reports";
$moduleName = "System Reports";
$moduleIcon = "chart-bar";
$currentPage = "reports";

require_once __DIR__ . '/includes/sidebar_nav.php';

// Check if user is logged in and has admin privileges
if (!isset($_SESSION['user_id'])) {
    header('Location: ' . dirname($_SERVER['PHP_SELF']) . '/../login.php');
    exit();
}

// Check if user has access to admin module
requireModuleAccess('admin');
require_once dirname(__DIR__) . '/shared/csrf.php';

// --- Report export (GET, triggers a file download) ---
// Real CSV generation from live data — previously every "Generate"/
// "View"/"Download" action in this page was a JS alert() with no
// backend at all.
if (isset($_GET['export'])) {
    $reportType = $_GET['export'];
    $format = $_GET['format'] ?? 'csv';
    $range = $_GET['range'] ?? '30days';

    $data = generateReportData($pdo, $reportType, $range);
    if ($data === null) {
        http_response_code(400);
        die('Unknown or unavailable report type: ' . htmlspecialchars($reportType));
    }

    try {
        $stmt = $pdo->prepare('INSERT INTO generated_reports (report_type, format, date_range, generated_by) VALUES (?, ?, ?, ?)');
        $stmt->execute([$reportType, $format, $range, $_SESSION['user_id'] ?? null]);
    } catch (PDOException $e) {
        error_log('generated_reports write failed (has the migration been run?): ' . $e->getMessage());
    }

    $filename = 'armis_' . $reportType . '_' . date('Y-m-d') . '.csv';
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    $out = fopen('php://output', 'w');
    fputcsv($out, $data['headers']);
    foreach ($data['rows'] as $row) {
        fputcsv($out, $row);
    }
    fclose($out);
    logAccess('admin', 'report_export', true);
    exit;
}

// --- Delete a report-history entry (POST) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_report_entry') {
    require_csrf();
    $reportId = (int) ($_POST['report_id'] ?? 0);
    if ($reportId > 0) {
        $stmt = $pdo->prepare('DELETE FROM generated_reports WHERE id = ?');
        $stmt->execute([$reportId]);
    }
    header('Location: /Armis2/admin/reports.php');
    exit;
}

/**
 * Real report data for each of the sub-report types linked from this
 * page. Returns ['headers' => [...], 'rows' => [[...], ...]] or null
 * for an unknown type. A couple of the original sub-types
 * (response_times) have no real data source anywhere in this app (no
 * APM/performance-timing table exists) — rather than fake it, those
 * are omitted from the switch and return null, which the export
 * handler turns into a clear "unavailable" response instead of a
 * silent fake success.
 */
function generateReportData(PDO $pdo, string $type, string $range): ?array {
    $days = match ($range) {
        '7days' => 7,
        '90days' => 90,
        '1year' => 365,
        default => 30,
    };

    switch ($type) {
        case 'security':
        case 'login_activity':
        case 'access_log':
            // FIX: previously queried activity_log, which only
            // reflects admin_branch usage (see
            // shared/access_log_reader.php), not app-wide login/access
            // activity. access.log is the comprehensive, correct
            // source — same fix as admin/security.php's "Active
            // Sessions" needed.
            require_once dirname(__DIR__) . '/shared/access_log_reader.php';
            $entries = array_reverse(readAccessLogEntries(time() - ($days * 86400))); // most recent first
            $rows = array_map(fn($e) => [
                $e['timestamp'] ?? '',
                $e['username'] ?? '',
                $e['module'] ?? '',
                $e['action'] ?? '',
                !empty($e['success']) ? 'Success' : 'Failed',
                $e['ip'] ?? '',
            ], $entries);
            return [
                'headers' => ['Date/Time', 'Username', 'Module', 'Action', 'Result', 'IP Address'],
                'rows' => $rows,
            ];

        case 'failed_attempts':
            // Real, but from a different source: login failures are
            // recorded in logs/access.log (JSON lines), not the
            // activity_log DB table, which only logs actions taken
            // after a successful login.
            $rows = [];
            $accessLogPath = dirname(__DIR__) . '/logs/access.log';
            if (is_readable($accessLogPath)) {
                $cutoff = time() - ($days * 86400);
                $handle = fopen($accessLogPath, 'r');
                while ($handle && ($line = fgets($handle)) !== false) {
                    $entry = json_decode($line, true);
                    if (!$entry || !empty($entry['success'])) continue;
                    if (strtotime($entry['timestamp'] ?? '') < $cutoff) continue;
                    $rows[] = [$entry['timestamp'] ?? '', $entry['username'] ?? '', $entry['module'] ?? '', $entry['action'] ?? '', $entry['ip'] ?? ''];
                }
                if ($handle) fclose($handle);
            }
            return ['headers' => ['Date/Time', 'Username', 'Module', 'Action', 'IP Address'], 'rows' => $rows];

        case 'user_permissions':
            $stmt = $pdo->query("SELECT svcNo, username, role, accStatus FROM staff WHERE username IS NOT NULL AND username != '' ORDER BY username");
            return ['headers' => ['Service No', 'Username', 'Role', 'Account Status'], 'rows' => $stmt->fetchAll(PDO::FETCH_NUM)];

        case 'password_audit':
            // Real, meaningful signal already tracked in the DB:
            // accounts still on their original temp password
            // (isFirstLogin=1) never completed the forced first-login
            // password change.
            $stmt = $pdo->query("
                SELECT svcNo, username, role, accStatus, dateCreated
                FROM staff WHERE isFirstLogin = 1 AND username IS NOT NULL AND username != ''
                ORDER BY dateCreated ASC
            ");
            return ['headers' => ['Service No', 'Username', 'Role', 'Account Status', 'Created'], 'rows' => $stmt->fetchAll(PDO::FETCH_NUM)];

        case 'performance':
        case 'database_performance':
            $stmt = $pdo->query("
                SELECT table_name AS t, table_rows AS r, ROUND((data_length + index_length) / 1024 / 1024, 2) AS mb
                FROM information_schema.TABLES WHERE table_schema = DATABASE() ORDER BY mb DESC
            ");
            return ['headers' => ['Table', 'Rows', 'Size (MB)'], 'rows' => $stmt->fetchAll(PDO::FETCH_NUM)];

        case 'system_usage':
            // FIX: same activity_log undercounting issue — access.log
            // is the real, app-wide source.
            require_once dirname(__DIR__) . '/shared/access_log_reader.php';
            $entries = readAccessLogEntries(time() - ($days * 86400));
            $byDay = [];
            foreach ($entries as $e) {
                if (empty($e['success'])) continue;
                $day = date('Y-m-d', $e['_time']);
                $byDay[$day]['users'][$e['username'] ?? $e['user_id'] ?? 'unknown'] = true;
                $byDay[$day]['actions'] = ($byDay[$day]['actions'] ?? 0) + 1;
            }
            krsort($byDay);
            $rows = array_map(fn($day, $data) => [$day, count($data['users']), $data['actions']], array_keys($byDay), $byDay);
            return ['headers' => ['Date', 'Active Users', 'Actions'], 'rows' => $rows];

        case 'resource_utilization':
            $diskFree = disk_free_space(dirname(__DIR__));
            $diskTotal = disk_total_space(dirname(__DIR__));
            return [
                'headers' => ['Metric', 'Value'],
                'rows' => [
                    ['PHP Memory Usage (MB)', number_format(memory_get_usage(true) / 1024 / 1024, 1)],
                    ['Disk Free (GB)', $diskFree ? number_format($diskFree / 1024 / 1024 / 1024, 1) : 'N/A'],
                    ['Disk Total (GB)', $diskTotal ? number_format($diskTotal / 1024 / 1024 / 1024, 1) : 'N/A'],
                    ['Server Load (1min)', function_exists('sys_getloadavg') ? (sys_getloadavg()[0] ?? 'N/A') : 'N/A'],
                ],
            ];

        case 'personnel':
        case 'staff_summary':
            $stmt = $pdo->query("SELECT svcStatus, COUNT(*) as c FROM staff GROUP BY svcStatus");
            return ['headers' => ['Status', 'Count'], 'rows' => $stmt->fetchAll(PDO::FETCH_NUM)];

        case 'rank_distribution':
            $stmt = $pdo->query("
                SELECT s.rankId, COUNT(*) as c FROM staff s WHERE s.svcStatus = 'Active'
                GROUP BY s.rankId ORDER BY c DESC
            ");
            return ['headers' => ['Rank', 'Count'], 'rows' => $stmt->fetchAll(PDO::FETCH_NUM)];

        case 'unit_strength':
            $stmt = $pdo->query("
                SELECT u.unitId, COUNT(s.svcNo) as c FROM unit u LEFT JOIN staff s ON s.unitId = u.unitId AND s.svcStatus = 'Active'
                GROUP BY u.unitId ORDER BY c DESC
            ");
            return ['headers' => ['Unit', 'Active Staff'], 'rows' => $stmt->fetchAll(PDO::FETCH_NUM)];

        case 'training_status':
            $stmt = $pdo->query("SELECT status, COUNT(*) as c FROM training_records GROUP BY status");
            return ['headers' => ['Status', 'Count'], 'rows' => $stmt->fetchAll(PDO::FETCH_NUM)];

        default:
            return null; // includes 'response_times' — no real data source exists for this anywhere in the app
    }
}

// Log access
logAccess('admin', 'reports_view', true);

// FIX: previously all hardcoded ("Sample report data (would come from
// actual queries in production)") — genuine queries now.
require_once dirname(__DIR__) . '/shared/access_log_reader.php';

$systemStats = [
    'total_users' => (int) $pdo->query("SELECT COUNT(*) FROM staff")->fetchColumn(),
    // FIX: previously queried the activity_log DB table, which only
    // reflects admin_branch usage (see shared/access_log_reader.php) —
    // access.log is the real, app-wide source, same definition of
    // "active" the app uses elsewhere (e.g. admin/security.php).
    'active_sessions' => count(getActiveUsersFromAccessLog((int) SESSION_TIMEOUT)),
    'total_staff' => (int) $pdo->query("SELECT COUNT(*) FROM staff WHERE svcStatus = 'Active'")->fetchColumn(),
    'pending_approvals' => (int) $pdo->query("SELECT COUNT(*) FROM staff WHERE accStatus = 'Pending'")->fetchColumn(),
];

// Last 6 months of real activity, from access.log (see above for why
// not activity_log).
$monthlyStats = [];
$sixMonthsAgo = strtotime('-6 months');
$monthlyByYm = [];
foreach (readAccessLogEntries($sixMonthsAgo) as $entry) {
    if (empty($entry['success'])) continue;
    $ym = date('Y-m', $entry['_time']);
    $monthlyByYm[$ym]['month'] = date('M', $entry['_time']);
    $monthlyByYm[$ym]['users'][$entry['username'] ?? $entry['user_id'] ?? 'unknown'] = true;
    $monthlyByYm[$ym]['actions'] = ($monthlyByYm[$ym]['actions'] ?? 0) + 1;
}
ksort($monthlyByYm);
$monthlyRows = [];
foreach ($monthlyByYm as $ym => $data) {
    $monthlyRows[] = ['ym' => $ym, 'month' => $data['month'], 'users' => count($data['users']), 'actions' => $data['actions']];
}
// Real PHP error count per month, parsed from the actual error log
// (logs/php_errors.log), keyed the same way as the activity rows above.
$errorCounts = [];
$errorLogPath = dirname(__DIR__) . '/logs/php_errors.log';
if (is_readable($errorLogPath)) {
    $handle = fopen($errorLogPath, 'r');
    if ($handle) {
        while (($line = fgets($handle)) !== false) {
            if (preg_match('/^\[(\d{1,2})-(\w{3})-(\d{4})/', $line, $m)) {
                $ym = date('Y-m', strtotime("{$m[1]} {$m[2]} {$m[3]}"));
                $errorCounts[$ym] = ($errorCounts[$ym] ?? 0) + 1;
            }
        }
        fclose($handle);
    }
}
foreach ($monthlyRows as $row) {
    $monthlyStats[] = [
        'month' => $row['month'],
        'users' => (int) $row['users'],
        'logins' => (int) $row['actions'], // activity_log doesn't distinguish "login" specifically — total logged actions
        'errors' => $errorCounts[$row['ym']] ?? 0,
    ];
}

// Real report-generation history — see generated_reports table
// (database/migrations/2026_08_28_add_generated_reports_table.sql).
// Reports are generated on demand (no files persisted to disk); this
// is an audit trail of what was generated, when, and by whom.
$recentReports = [];
try {
    $reportsStmt = $pdo->query("
        SELECT gr.id, gr.report_type, gr.format, gr.date_range, gr.created_at, s.fName, s.lName
        FROM generated_reports gr
        LEFT JOIN staff s ON gr.generated_by = s.svcNo
        ORDER BY gr.created_at DESC
        LIMIT 20
    ");
    $recentReports = $reportsStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Migration not run yet — show an empty list rather than fatal-erroring the page.
    error_log('generated_reports read failed (has the migration been run?): ' . $e->getMessage());
}

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
                                <i class="fas fa-chart-bar text-primary"></i> System Reports
                            </h1>
                            <p class="text-muted mb-0">Generate and view comprehensive system reports</p>
                        </div>
                        <div>
                            <div class="btn-group" role="group">
                                <button class="btn btn-success" onclick="generateReport()">
                                    <i class="fas fa-plus"></i> Generate Report
                                </button>
                                <button class="btn btn-primary" onclick="scheduleReport()">
                                    <i class="fas fa-clock"></i> Schedule Report
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Quick Stats -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="card bg-primary text-white">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h6 class="card-title">Total Users</h6>
                                    <h4 class="mb-0"><?= number_format($systemStats['total_users']) ?></h4>
                                </div>
                                <div class="align-self-center">
                                    <i class="fas fa-users fa-2x opacity-75"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-success text-white">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h6 class="card-title">Active Sessions</h6>
                                    <h4 class="mb-0"><?= number_format($systemStats['active_sessions']) ?></h4>
                                </div>
                                <div class="align-self-center">
                                    <i class="fas fa-circle fa-2x opacity-75"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-info text-white">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h6 class="card-title">Total Staff</h6>
                                    <h4 class="mb-0"><?= number_format($systemStats['total_staff']) ?></h4>
                                </div>
                                <div class="align-self-center">
                                    <i class="fas fa-user-tie fa-2x opacity-75"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-warning text-white">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h6 class="card-title">Pending Approvals</h6>
                                    <h4 class="mb-0"><?= number_format($systemStats['pending_approvals']) ?></h4>
                                </div>
                                <div class="align-self-center">
                                    <i class="fas fa-clock fa-2x opacity-75"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Report Categories -->
            <div class="row mb-4">
                <div class="col-md-4">
                    <div class="card h-100">
                        <div class="card-header bg-primary text-white">
                            <h5 class="card-title mb-0">
                                <i class="fas fa-shield-alt"></i> Security Reports
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="list-group list-group-flush">
                                <button class="list-group-item list-group-item-action" onclick="generateSecurityReport('login_activity')">
                                    <i class="fas fa-sign-in-alt"></i> Login Activity Report
                                </button>
                                <button class="list-group-item list-group-item-action" onclick="generateSecurityReport('failed_attempts')">
                                    <i class="fas fa-exclamation-triangle"></i> Failed Login Attempts
                                </button>
                                <button class="list-group-item list-group-item-action" onclick="generateSecurityReport('user_permissions')">
                                    <i class="fas fa-user-shield"></i> User Permissions Audit
                                </button>
                                <button class="list-group-item list-group-item-action" onclick="generateSecurityReport('access_log')">
                                    <i class="fas fa-list-alt"></i> System Access Log
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card h-100">
                        <div class="card-header bg-success text-white">
                            <h5 class="card-title mb-0">
                                <i class="fas fa-chart-line"></i> Performance Reports
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="list-group list-group-flush">
                                <button class="list-group-item list-group-item-action" onclick="generatePerformanceReport('system_usage')">
                                    <i class="fas fa-tachometer-alt"></i> System Usage Statistics
                                </button>
                                <button class="list-group-item list-group-item-action" onclick="generatePerformanceReport('database_performance')">
                                    <i class="fas fa-database"></i> Database Performance
                                </button>
                                <button class="list-group-item list-group-item-action" onclick="generatePerformanceReport('response_times')">
                                    <i class="fas fa-stopwatch"></i> Response Time Analysis
                                </button>
                                <button class="list-group-item list-group-item-action" onclick="generatePerformanceReport('resource_utilization')">
                                    <i class="fas fa-server"></i> Resource Utilization
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card h-100">
                        <div class="card-header bg-info text-white">
                            <h5 class="card-title mb-0">
                                <i class="fas fa-users"></i> Personnel Reports
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="list-group list-group-flush">
                                <button class="list-group-item list-group-item-action" onclick="generatePersonnelReport('staff_summary')">
                                    <i class="fas fa-clipboard-list"></i> Staff Summary Report
                                </button>
                                <button class="list-group-item list-group-item-action" onclick="generatePersonnelReport('rank_distribution')">
                                    <i class="fas fa-medal"></i> Rank Distribution
                                </button>
                                <button class="list-group-item list-group-item-action" onclick="generatePersonnelReport('unit_strength')">
                                    <i class="fas fa-flag"></i> Unit Strength Report
                                </button>
                                <button class="list-group-item list-group-item-action" onclick="generatePersonnelReport('training_status')">
                                    <i class="fas fa-graduation-cap"></i> Training Status Report
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Monthly Statistics Chart -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-chart-area"></i> Monthly Statistics
                    </h5>
                </div>
                <div class="card-body">
                    <canvas id="monthlyStatsChart" width="400" height="200"></canvas>
                </div>
            </div>

            <!-- Recent Reports -->
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-history"></i> Recent Reports
                    </h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead>
                                <tr>
                                    <th>Report Type</th>
                                    <th>Generated</th>
                                    <th>Generated By</th>
                                    <th>Format</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($recentReports)): ?>
                                <tr><td colspan="5" class="text-center text-muted">No reports generated yet.</td></tr>
                                <?php endif; ?>
                                <?php foreach ($recentReports as $report): ?>
                                <tr>
                                    <td>
                                        <?php
                                        $typeClass = match ($report['report_type']) {
                                            'security' => 'danger',
                                            'performance' => 'success',
                                            'personnel' => 'info',
                                            default => 'secondary',
                                        };
                                        ?>
                                        <span class="badge bg-<?= $typeClass ?>"><?= htmlspecialchars(ucfirst($report['report_type'])) ?></span>
                                    </td>
                                    <td><?= date('M j, Y H:i', strtotime($report['created_at'])) ?></td>
                                    <td><?= htmlspecialchars(trim(($report['fName'] ?? '') . ' ' . ($report['lName'] ?? '')) ?: 'Unknown') ?></td>
                                    <td><?= htmlspecialchars(strtoupper($report['format'])) ?></td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <a class="btn btn-outline-success" href="/Armis2/admin/reports.php?export=<?= urlencode($report['report_type']) ?>&format=<?= urlencode($report['format']) ?>&range=<?= urlencode($report['date_range']) ?>" title="Download (regenerates fresh data)">
                                                <i class="fas fa-download"></i>
                                            </a>
                                            <form method="POST" style="display:inline;" onsubmit="return confirm('Remove this entry from report history?');">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="action" value="delete_report_entry">
                                                <input type="hidden" name="report_id" value="<?= (int) $report['id'] ?>">
                                                <button type="submit" class="btn btn-outline-danger" title="Remove from history">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Report Generation Modal -->
<div class="modal fade" id="reportModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Generate Custom Report</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="reportForm">
                    <div class="mb-3">
                        <label for="reportType" class="form-label">Report Type</label>
                        <select class="form-select" id="reportType" required>
                            <option value="">Select report type...</option>
                            <option value="security">Security Report</option>
                            <option value="performance">Performance Report</option>
                            <option value="personnel">Personnel Report</option>
                            <option value="maintenance">Maintenance Report</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="dateRange" class="form-label">Date Range</label>
                        <select class="form-select" id="dateRange" required>
                            <option value="today">Today</option>
                            <option value="week">Last 7 days</option>
                            <option value="month" selected>Last 30 days</option>
                            <option value="quarter">Last 3 months</option>
                            <option value="year">Last year</option>
                            <option value="custom">Custom range</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="format" class="form-label">Output Format</label>
                        <select class="form-select" id="format" required>
                            <option value="csv">CSV</option>
                        </select>
                        <small class="form-text text-muted">PDF/Excel/HTML export aren't built yet — CSV is the only genuinely implemented format right now (mpdf and phpoffice/phpspreadsheet are already available as dependencies if these are wanted later).</small>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="submitReportGeneration()">Generate Report</button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// Initialize monthly statistics chart
const ctx = document.getElementById('monthlyStatsChart').getContext('2d');
const monthlyStatsChart = new Chart(ctx, {
    type: 'line',
    data: {
        labels: <?= json_encode(array_column($monthlyStats, 'month')) ?>,
        datasets: [{
            label: 'User Registrations',
            data: <?= json_encode(array_column($monthlyStats, 'users')) ?>,
            borderColor: 'rgb(75, 192, 192)',
            backgroundColor: 'rgba(75, 192, 192, 0.1)',
            tension: 0.1
        }, {
            label: 'Logins',
            data: <?= json_encode(array_column($monthlyStats, 'logins')) ?>,
            borderColor: 'rgb(255, 99, 132)',
            backgroundColor: 'rgba(255, 99, 132, 0.1)',
            tension: 0.1
        }, {
            label: 'Errors',
            data: <?= json_encode(array_column($monthlyStats, 'errors')) ?>,
            borderColor: 'rgb(255, 205, 86)',
            backgroundColor: 'rgba(255, 205, 86, 0.1)',
            tension: 0.1
        }]
    },
    options: {
        responsive: true,
        plugins: {
            title: {
                display: true,
                text: 'System Activity Trends'
            }
        },
        scales: {
            y: {
                beginAtZero: true
            }
        }
    }
});

function generateReport() {
    const modal = new bootstrap.Modal(document.getElementById('reportModal'));
    modal.show();
}

function scheduleReport() {
    // FIX: previously alert('...would be displayed'). Report
    // scheduling (recurring, emailed reports) is a real feature but a
    // much larger one (needs a scheduler/cron integration) — being
    // honest that it isn't built yet rather than faking a working UI.
    armisNotifications.info('Not yet available', 'Scheduled/recurring reports are planned but not built yet. Use "Generate Report" for on-demand exports.');
}

function exportReport(type, range = '30days', format = 'csv') {
    window.location.href = `/Armis2/admin/reports.php?export=${encodeURIComponent(type)}&format=${encodeURIComponent(format)}&range=${encodeURIComponent(range)}`;
}

function generateSecurityReport(type) {
    exportReport(type);
}

function generatePerformanceReport(type) {
    exportReport(type);
}

function generatePersonnelReport(type) {
    exportReport(type);
}

function submitReportGeneration() {
    const form = document.getElementById('reportForm');
    if (form.checkValidity()) {
        const reportType = document.getElementById('reportType').value;
        const dateRange = document.getElementById('dateRange').value;
        const format = document.getElementById('format').value;

        exportReport(reportType, dateRange, format);

        const modal = bootstrap.Modal.getInstance(document.getElementById('reportModal'));
        modal.hide();
    } else {
        form.reportValidity();
    }
}
</script>

<?php include dirname(__DIR__) . '/shared/footer.php'; ?>
