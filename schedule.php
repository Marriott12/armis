<?php
require_once dirname(__DIR__) . '/shared/module_auth.php';
require_once 'training_manager.php';
bootModule(['module' => 'training', 'page' => 'schedule', 'pageTitle' => 'Training Schedule - ARMIS', 'moduleName' => 'Training', 'moduleIcon' => 'graduation-cap']);

$sessions = (new TrainingManager())->getAllSessions();
usort($sessions, static fn(array $left, array $right): int => strcmp($left['date'] ?? '', $right['date'] ?? ''));
include dirname(__DIR__) . '/shared/header.php';
include dirname(__DIR__) . '/shared/sidebar.php';
?>
<div class="content-wrapper with-sidebar"><div class="container-fluid"><div class="main-content">
  <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4"><div><h1 class="section-title mb-1"><i class="fas fa-calendar"></i> Training Schedule</h1><p class="text-muted mb-0">Upcoming and completed course sessions.</p></div><a class="btn btn-outline-primary" href="sessions.php"><i class="fas fa-calendar-alt"></i> Manage Sessions</a></div>
  <div class="card"><div class="card-header d-flex justify-content-between"><strong>Sessions</strong><span class="text-muted small"><?= count($sessions) ?> scheduled</span></div><div class="card-body p-0"><div class="table-responsive"><table class="table mb-0" data-training-table><thead><tr><th data-sort>Date</th><th data-sort>Session</th><th data-sort>Course</th><th data-sort>Assigned</th></tr></thead><tbody><?php foreach ($sessions as $session): ?><tr><td><?= htmlspecialchars($session['date'] ?? 'Unscheduled') ?></td><td><?= htmlspecialchars($session['title']) ?></td><td><?= htmlspecialchars($session['course_name'] ?? 'Unassigned course') ?></td><td><?= number_format($session['assigned_count'] ?? 0) ?></td></tr><?php endforeach; ?><?php if (!$sessions): ?><tr><td colspan="4" class="text-center text-muted py-4">No sessions scheduled. Create a session from Training Sessions.</td></tr><?php endif; ?></tbody></table></div></div></div>
</div></div></div>
<?php include dirname(__DIR__) . '/shared/footer.php'; ?>