<?php
require_once dirname(__DIR__) . '/shared/module_auth.php';
require_once 'training_manager.php';
bootModule(['module' => 'training', 'page' => 'certifications', 'pageTitle' => 'Certifications - ARMIS', 'moduleName' => 'Training', 'moduleIcon' => 'graduation-cap']);

$records = (new TrainingManager())->getAllAssignments();
$completed = array_values(array_filter($records, static fn(array $record): bool => strtolower((string) $record['status']) === 'completed'));
include dirname(__DIR__) . '/shared/header.php';
include dirname(__DIR__) . '/shared/sidebar.php';
?>
<div class="content-wrapper with-sidebar"><div class="container-fluid"><div class="main-content">
  <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4"><div><h1 class="section-title mb-1"><i class="fas fa-award"></i> Certifications</h1><p class="text-muted mb-0">Completed training assignments eligible for certification records.</p></div><a class="btn btn-outline-primary" href="records.php"><i class="fas fa-certificate"></i> Training Records</a></div>
  <div class="card"><div class="card-header d-flex justify-content-between"><strong>Completed Training</strong><span class="text-muted small"><?= count($completed) ?> completed</span></div><div class="card-body p-0"><div class="table-responsive"><table class="table mb-0" data-training-table><thead><tr><th data-sort>Personnel</th><th data-sort>Course</th><th data-sort>Session</th><th>Status</th></tr></thead><tbody><?php foreach ($completed as $record): ?><tr><td><?= htmlspecialchars($record['personnel_name'] ?? $record['personnel_id']) ?></td><td><?= htmlspecialchars($record['course_name'] ?? '') ?></td><td><?= htmlspecialchars($record['session_title'] ?? '') ?></td><td><span class="badge text-bg-success">Completed</span></td></tr><?php endforeach; ?><?php if (!$completed): ?><tr><td colspan="4" class="text-center text-muted py-4">No completed training assignments yet.</td></tr><?php endif; ?></tbody></table></div></div></div>
</div></div></div>
<?php include dirname(__DIR__) . '/shared/footer.php'; ?>