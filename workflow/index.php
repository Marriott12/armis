<?php
/**
 * ARMIS Workflow Management Module - Main Dashboard
 * Advanced Workflow Management System
 */

// Module constants
define('ARMIS_WORKFLOW', true);

// Include core files
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/shared/session_init.php';
require_once dirname(__DIR__) . '/shared/database_connection.php';
require_once dirname(__DIR__) . '/shared/rbac.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/workflow_service.php';

// Authentication and authorization
requireAuth();
requireModuleAccess('workflow');

// Initialize services
$pdo = getDbConnection();
$workflowService = new WorkflowService($pdo);

// Get dashboard data
$dashboardData = [
    'workflow_summary' => $workflowService->getWorkflowSummary(),
    'active_workflows' => $workflowService->getActiveWorkflows(),
    'pending_tasks' => $workflowService->getPendingTasks($_SESSION['user_id']),
    'workflow_templates' => $workflowService->getWorkflowTemplates(),
    'recent_activities' => $workflowService->getRecentActivities(10)
];

// Page title
$pageTitle = 'Workflow Management Dashboard';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?> - ARMIS</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <!-- Custom CSS -->
    <link href="css/workflow.css" rel="stylesheet">
</head>
<body>
    <!-- Include header -->
    <?php include dirname(__DIR__) . '/shared/header.php'; ?>

    <div class="container-fluid">
        <div class="row">
            <!-- Include sidebar -->
            <div class="col-md-2 px-0">
                <?php include dirname(__DIR__) . '/shared/sidebar.php'; ?>
            </div>

            <!-- Main content -->
            <div class="col-md-10">
                <div class="content-area workflow-dashboard">
                    <!-- Page header -->
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <div>
                            <h1 class="h3 mb-0">
                                <i class="fas fa-sitemap text-primary"></i>
                                Workflow Management
                            </h1>
                            <p class="text-muted mb-0">Advanced Workflow & Task Management System</p>
                        </div>
                        <div class="btn-group" role="group">
                            <a href="create_workflow.php" class="btn btn-primary">
                                <i class="fas fa-plus"></i> New Workflow
                            </a>
                            <a href="templates.php" class="btn btn-outline-primary">
                                <i class="fas fa-clipboard-list"></i> Templates
                            </a>
                            <button class="btn btn-outline-secondary" onclick="refreshDashboard()">
                                <i class="fas fa-sync-alt"></i> Refresh
                            </button>
                        </div>
                    </div>

                    <!-- KPI Cards -->
                    <div class="row mb-4">
                        <div class="col-lg-3 col-md-6 mb-3">
                            <div class="card border-0 shadow-sm">
                                <div class="card-body">
                                    <div class="d-flex align-items-center">
                                        <div class="me-3">
                                            <div class="bg-primary bg-gradient rounded-circle p-3">
                                                <i class="fas fa-play text-white"></i>
                                            </div>
                                        </div>
                                        <div>
                                            <h5 class="card-title mb-0"><?= $dashboardData['workflow_summary']['active_workflows'] ?? 0 ?></h5>
                                            <p class="card-text text-muted">Active Workflows</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-lg-3 col-md-6 mb-3">
                            <div class="card border-0 shadow-sm">
                                <div class="card-body">
                                    <div class="d-flex align-items-center">
                                        <div class="me-3">
                                            <div class="bg-warning bg-gradient rounded-circle p-3">
                                                <i class="fas fa-clock text-white"></i>
                                            </div>
                                        </div>
                                        <div>
                                            <h5 class="card-title mb-0"><?= count($dashboardData['pending_tasks']) ?></h5>
                                            <p class="card-text text-muted">Pending Tasks</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-lg-3 col-md-6 mb-3">
                            <div class="card border-0 shadow-sm">
                                <div class="card-body">
                                    <div class="d-flex align-items-center">
                                        <div class="me-3">
                                            <div class="bg-success bg-gradient rounded-circle p-3">
                                                <i class="fas fa-check-circle text-white"></i>
                                            </div>
                                        </div>
                                        <div>
                                            <h5 class="card-title mb-0"><?= $dashboardData['workflow_summary']['completed_today'] ?? 0 ?></h5>
                                            <p class="card-text text-muted">Completed Today</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-lg-3 col-md-6 mb-3">
                            <div class="card border-0 shadow-sm">
                                <div class="card-body">
                                    <div class="d-flex align-items-center">
                                        <div class="me-3">
                                            <div class="bg-info bg-gradient rounded-circle p-3">
                                                <i class="fas fa-layer-group text-white"></i>
                                            </div>
                                        </div>
                                        <div>
                                            <h5 class="card-title mb-0"><?= count($dashboardData['workflow_templates']) ?></h5>
                                            <p class="card-text text-muted">Templates</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Main Dashboard Content -->
                    <div class="row mb-4">
                        <div class="col-lg-8">
                            <!-- My Pending Tasks -->
                            <div class="card border-0 shadow-sm mb-3">
                                <div class="card-header bg-warning bg-gradient text-white">
                                    <h6 class="mb-0">
                                        <i class="fas fa-tasks"></i>
                                        My Pending Tasks
                                    </h6>
                                </div>
                                <div class="card-body">
                                    <?php if (empty($dashboardData['pending_tasks'])): ?>
                                        <div class="text-center text-muted py-4">
                                            <i class="fas fa-check-circle fa-3x mb-3 text-success"></i>
                                            <p class="mb-0">No pending tasks</p>
                                        </div>
                                    <?php else: ?>
                                        <div class="table-responsive">
                                            <table class="table table-sm">
                                                <thead>
                                                    <tr>
                                                        <th>Workflow</th>
                                                        <th>Step</th>
                                                        <th>Priority</th>
                                                        <th>Due Date</th>
                                                        <th>Action</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($dashboardData['pending_tasks'] as $task): ?>
                                                    <tr>
                                                        <td>
                                                            <a href="workflow_details.php?id=<?= $task['workflow_id'] ?>">
                                                                <?= htmlspecialchars($task['workflow_name']) ?>
                                                            </a>
                                                        </td>
                                                        <td><?= htmlspecialchars($task['step_name']) ?></td>
                                                        <td>
                                                            <span class="badge bg-<?= $task['priority'] === 'HIGH' ? 'danger' : ($task['priority'] === 'NORMAL' ? 'primary' : 'secondary') ?>">
                                                                <?= $task['priority'] ?>
                                                            </span>
                                                        </td>
                                                        <td>
                                                            <?php 
                                                            $dueDate = strtotime($task['assigned_at'] . ' +' . ($task['escalation_hours'] ?? 72) . ' hours');
                                                            echo date('M j, Y g:i A', $dueDate);
                                                            ?>
                                                        </td>
                                                        <td>
                                                            <div class="btn-group btn-group-sm">
                                                                <a href="task_details.php?id=<?= $task['id'] ?>" 
                                                                   class="btn btn-outline-primary">
                                                                    <i class="fas fa-eye"></i>
                                                                </a>
                                                                <button class="btn btn-outline-success" 
                                                                        onclick="approveTask(<?= $task['id'] ?>)">
                                                                    <i class="fas fa-check"></i>
                                                                </button>
                                                                <button class="btn btn-outline-danger" 
                                                                        onclick="rejectTask(<?= $task['id'] ?>)">
                                                                    <i class="fas fa-times"></i>
                                                                </button>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- Active Workflows -->
                            <div class="card border-0 shadow-sm">
                                <div class="card-header bg-primary bg-gradient text-white">
                                    <h6 class="mb-0">
                                        <i class="fas fa-stream"></i>
                                        Active Workflows
                                    </h6>
                                </div>
                                <div class="card-body">
                                    <?php if (empty($dashboardData['active_workflows'])): ?>
                                        <p class="text-muted text-center mb-0">No active workflows</p>
                                    <?php else: ?>
                                        <div class="table-responsive">
                                            <table class="table table-sm">
                                                <thead>
                                                    <tr>
                                                        <th>Workflow</th>
                                                        <th>Initiated By</th>
                                                        <th>Current Step</th>
                                                        <th>Priority</th>
                                                        <th>Started</th>
                                                        <th>Action</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($dashboardData['active_workflows'] as $workflow): ?>
                                                    <tr>
                                                        <td>
                                                            <a href="workflow_details.php?id=<?= $workflow['id'] ?>">
                                                                <?= htmlspecialchars($workflow['instance_name']) ?>
                                                            </a>
                                                        </td>
                                                        <td><?= htmlspecialchars($workflow['initiator_name']) ?></td>
                                                        <td>
                                                            <span class="badge bg-info">
                                                                Step <?= $workflow['current_step'] ?>
                                                            </span>
                                                        </td>
                                                        <td>
                                                            <span class="badge bg-<?= $workflow['priority'] === 'HIGH' ? 'danger' : ($workflow['priority'] === 'NORMAL' ? 'primary' : 'secondary') ?>">
                                                                <?= $workflow['priority'] ?>
                                                            </span>
                                                        </td>
                                                        <td><?= date('M j, Y', strtotime($workflow['started_at'])) ?></td>
                                                        <td>
                                                            <a href="workflow_details.php?id=<?= $workflow['id'] ?>" 
                                                               class="btn btn-sm btn-outline-primary">
                                                                <i class="fas fa-eye"></i>
                                                            </a>
                                                        </td>
                                                    </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <div class="col-lg-4">
                            <!-- Quick Actions -->
                            <div class="card border-0 shadow-sm mb-3">
                                <div class="card-header bg-success bg-gradient text-white">
                                    <h6 class="mb-0">
                                        <i class="fas fa-bolt"></i>
                                        Quick Actions
                                    </h6>
                                </div>
                                <div class="card-body">
                                    <div class="d-grid gap-2">
                                        <a href="create_workflow.php" class="btn btn-outline-primary">
                                            <i class="fas fa-plus"></i> Start New Workflow
                                        </a>
                                        <a href="templates.php" class="btn btn-outline-info">
                                            <i class="fas fa-layer-group"></i> Manage Templates
                                        </a>
                                        <a href="my_tasks.php" class="btn btn-outline-warning">
                                            <i class="fas fa-tasks"></i> View My Tasks
                                        </a>
                                        <a href="reports.php" class="btn btn-outline-success">
                                            <i class="fas fa-chart-bar"></i> Workflow Reports
                                        </a>
                                        <a href="escalations.php" class="btn btn-outline-danger">
                                            <i class="fas fa-exclamation-triangle"></i> Escalations
                                        </a>
                                    </div>
                                </div>
                            </div>

                            <!-- Workflow Templates -->
                            <div class="card border-0 shadow-sm mb-3">
                                <div class="card-header bg-info bg-gradient text-white">
                                    <h6 class="mb-0">
                                        <i class="fas fa-layer-group"></i>
                                        Quick Start Templates
                                    </h6>
                                </div>
                                <div class="card-body">
                                    <?php if (empty($dashboardData['workflow_templates'])): ?>
                                        <p class="text-muted text-center mb-0">No templates available</p>
                                    <?php else: ?>
                                        <div class="list-group list-group-flush">
                                            <?php foreach (array_slice($dashboardData['workflow_templates'], 0, 5) as $template): ?>
                                            <div class="list-group-item px-0 py-2">
                                                <div class="d-flex justify-content-between align-items-center">
                                                    <div>
                                                        <h6 class="mb-1"><?= htmlspecialchars($template['name']) ?></h6>
                                                        <small class="text-muted"><?= htmlspecialchars($template['category']) ?></small>
                                                    </div>
                                                    <button class="btn btn-sm btn-outline-primary" 
                                                            onclick="startWorkflow(<?= $template['id'] ?>)">
                                                        <i class="fas fa-play"></i>
                                                    </button>
                                                </div>
                                            </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- Recent Activities -->
                            <div class="card border-0 shadow-sm">
                                <div class="card-header bg-secondary bg-gradient text-white">
                                    <h6 class="mb-0">
                                        <i class="fas fa-history"></i>
                                        Recent Activities
                                    </h6>
                                </div>
                                <div class="card-body">
                                    <?php if (empty($dashboardData['recent_activities'])): ?>
                                        <p class="text-muted text-center mb-0">No recent activities</p>
                                    <?php else: ?>
                                        <div class="timeline">
                                            <?php foreach ($dashboardData['recent_activities'] as $activity): ?>
                                            <div class="timeline-item">
                                                <small class="text-muted"><?= date('M j, g:i A', strtotime($activity['created_at'])) ?></small>
                                                <p class="mb-0"><?= htmlspecialchars($activity['description']) ?></p>
                                            </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Include footer -->
    <?php include dirname(__DIR__) . '/shared/footer.php'; ?>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Custom JS -->
    <script src="js/workflow.js"></script>

    <script>
        // Initialize dashboard on page load
        document.addEventListener('DOMContentLoaded', function() {
            console.log('Workflow dashboard loaded');
        });

        function refreshDashboard() {
            location.reload();
        }

        function approveTask(taskId) {
            if (confirm('Are you sure you want to approve this task?')) {
                ARMIS_WORKFLOW.tasks.approve(taskId);
            }
        }

        function rejectTask(taskId) {
            if (confirm('Are you sure you want to reject this task?')) {
                ARMIS_WORKFLOW.tasks.reject(taskId);
            }
        }

        function startWorkflow(templateId) {
            window.location.href = `create_workflow.php?template_id=${templateId}`;
        }
    </script>
</body>
</html>