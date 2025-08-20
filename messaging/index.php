<?php
/**
 * ARMIS Messaging Module - Main Dashboard
 * Enhanced Communication & Messaging System
 */

// Module constants
define('ARMIS_MESSAGING', true);

// Include core files
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/shared/session_init.php';
require_once dirname(__DIR__) . '/shared/database_connection.php';
require_once dirname(__DIR__) . '/shared/rbac.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/messaging_service.php';

// Authentication and authorization
requireAuth();
requireModuleAccess('messaging');

// Initialize services
$pdo = getDbConnection();
$messagingService = new MessagingService($pdo);

// Get dashboard data
$dashboardData = [
    'messaging_summary' => $messagingService->getMessagingSummary($_SESSION['user_id']),
    'recent_messages' => $messagingService->getRecentMessages($_SESSION['user_id']),
    'unread_notifications' => $messagingService->getUnreadNotifications($_SESSION['user_id']),
    'announcements' => $messagingService->getActiveAnnouncements(),
    'shared_documents' => $messagingService->getRecentSharedDocuments()
];

// Page title
$pageTitle = 'Messaging & Communication Dashboard';
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
    <!-- Custom CSS -->
    <link href="css/messaging.css" rel="stylesheet">
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
                <div class="content-area messaging-dashboard">
                    <!-- Page header -->
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <div>
                            <h1 class="h3 mb-0">
                                <i class="fas fa-comments text-primary"></i>
                                Communication Center
                            </h1>
                            <p class="text-muted mb-0">Internal Messaging & Communication Hub</p>
                        </div>
                        <div class="btn-group" role="group">
                            <a href="compose.php" class="btn btn-primary">
                                <i class="fas fa-pen"></i> Compose Message
                            </a>
                            <a href="announcements.php" class="btn btn-outline-primary">
                                <i class="fas fa-bullhorn"></i> Announcements
                            </a>
                            <a href="documents.php" class="btn btn-outline-info">
                                <i class="fas fa-file-alt"></i> Documents
                            </a>
                            <button class="btn btn-outline-secondary" onclick="refreshDashboard()">
                                <i class="fas fa-sync-alt"></i> Refresh
                            </button>
                        </div>
                    </div>

                    <!-- Communication Stats -->
                    <div class="row mb-4">
                        <div class="col-lg-3 col-md-6 mb-3">
                            <div class="card border-0 shadow-sm">
                                <div class="card-body">
                                    <div class="d-flex align-items-center">
                                        <div class="me-3">
                                            <div class="bg-primary bg-gradient rounded-circle p-3">
                                                <i class="fas fa-envelope text-white"></i>
                                            </div>
                                        </div>
                                        <div>
                                            <h5 class="card-title mb-0"><?= $dashboardData['messaging_summary']['unread_messages'] ?? 0 ?></h5>
                                            <p class="card-text text-muted">Unread Messages</p>
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
                                                <i class="fas fa-bell text-white"></i>
                                            </div>
                                        </div>
                                        <div>
                                            <h5 class="card-title mb-0"><?= count($dashboardData['unread_notifications']) ?></h5>
                                            <p class="card-text text-muted">Notifications</p>
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
                                                <i class="fas fa-bullhorn text-white"></i>
                                            </div>
                                        </div>
                                        <div>
                                            <h5 class="card-title mb-0"><?= count($dashboardData['announcements']) ?></h5>
                                            <p class="card-text text-muted">Active Announcements</p>
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
                                                <i class="fas fa-share-alt text-white"></i>
                                            </div>
                                        </div>
                                        <div>
                                            <h5 class="card-title mb-0"><?= count($dashboardData['shared_documents']) ?></h5>
                                            <p class="card-text text-muted">Shared Documents</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Main Dashboard Content -->
                    <div class="row">
                        <div class="col-lg-8">
                            <!-- Recent Messages -->
                            <div class="card border-0 shadow-sm mb-4">
                                <div class="card-header bg-primary bg-gradient text-white d-flex justify-content-between align-items-center">
                                    <h6 class="mb-0">
                                        <i class="fas fa-inbox"></i>
                                        Recent Messages
                                    </h6>
                                    <a href="messages.php" class="btn btn-sm btn-light">
                                        View All
                                    </a>
                                </div>
                                <div class="card-body p-0">
                                    <?php if (empty($dashboardData['recent_messages'])): ?>
                                        <div class="text-center text-muted py-4">
                                            <i class="fas fa-inbox fa-3x mb-3"></i>
                                            <p class="mb-0">No recent messages</p>
                                        </div>
                                    <?php else: ?>
                                        <div class="list-group list-group-flush">
                                            <?php foreach ($dashboardData['recent_messages'] as $message): ?>
                                            <div class="list-group-item list-group-item-action message-item <?= !$message['is_read'] ? 'unread' : '' ?>">
                                                <div class="d-flex w-100 justify-content-between">
                                                    <div class="d-flex align-items-start">
                                                        <div class="avatar me-3">
                                                            <div class="bg-secondary rounded-circle d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                                                                <span class="text-white fw-bold">
                                                                    <?= strtoupper(substr($message['sender_name'], 0, 1)) ?>
                                                                </span>
                                                            </div>
                                                        </div>
                                                        <div class="flex-grow-1">
                                                            <h6 class="mb-1"><?= htmlspecialchars($message['subject']) ?></h6>
                                                            <p class="mb-1 text-muted">From: <?= htmlspecialchars($message['sender_name']) ?></p>
                                                            <small class="text-muted"><?= htmlspecialchars(substr($message['content'], 0, 100)) ?>...</small>
                                                        </div>
                                                    </div>
                                                    <div class="text-end">
                                                        <small class="text-muted"><?= date('M j, g:i A', strtotime($message['sent_at'])) ?></small>
                                                        <?php if (!$message['is_read']): ?>
                                                            <br><span class="badge bg-primary">New</span>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- Active Announcements -->
                            <div class="card border-0 shadow-sm">
                                <div class="card-header bg-warning bg-gradient text-white d-flex justify-content-between align-items-center">
                                    <h6 class="mb-0">
                                        <i class="fas fa-bullhorn"></i>
                                        Active Announcements
                                    </h6>
                                    <a href="announcements.php" class="btn btn-sm btn-light">
                                        View All
                                    </a>
                                </div>
                                <div class="card-body">
                                    <?php if (empty($dashboardData['announcements'])): ?>
                                        <p class="text-muted text-center mb-0">No active announcements</p>
                                    <?php else: ?>
                                        <?php foreach ($dashboardData['announcements'] as $announcement): ?>
                                        <div class="announcement-item mb-3 p-3 border rounded">
                                            <div class="d-flex justify-content-between align-items-start">
                                                <div class="flex-grow-1">
                                                    <h6 class="mb-1">
                                                        <span class="badge bg-<?= $announcement['priority'] === 'HIGH' ? 'danger' : ($announcement['priority'] === 'NORMAL' ? 'primary' : 'secondary') ?>">
                                                            <?= $announcement['priority'] ?>
                                                        </span>
                                                        <?= htmlspecialchars($announcement['title']) ?>
                                                    </h6>
                                                    <p class="mb-2"><?= htmlspecialchars(substr($announcement['content'], 0, 200)) ?>...</p>
                                                    <small class="text-muted">
                                                        <i class="fas fa-user"></i> <?= htmlspecialchars($announcement['creator_name']) ?>
                                                        | <i class="fas fa-calendar"></i> <?= date('M j, Y g:i A', strtotime($announcement['published_at'])) ?>
                                                        <?php if ($announcement['category']): ?>
                                                        | <i class="fas fa-tag"></i> <?= htmlspecialchars($announcement['category']) ?>
                                                        <?php endif; ?>
                                                    </small>
                                                </div>
                                                <button class="btn btn-sm btn-outline-primary ms-2" onclick="viewAnnouncement(<?= $announcement['id'] ?>)">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                            </div>
                                        </div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <div class="col-lg-4">
                            <!-- Quick Actions -->
                            <div class="card border-0 shadow-sm mb-4">
                                <div class="card-header bg-success bg-gradient text-white">
                                    <h6 class="mb-0">
                                        <i class="fas fa-bolt"></i>
                                        Quick Actions
                                    </h6>
                                </div>
                                <div class="card-body">
                                    <div class="d-grid gap-2">
                                        <a href="compose.php" class="btn btn-outline-primary">
                                            <i class="fas fa-pen"></i> Compose Message
                                        </a>
                                        <a href="create_announcement.php" class="btn btn-outline-warning">
                                            <i class="fas fa-bullhorn"></i> Create Announcement
                                        </a>
                                        <a href="upload_document.php" class="btn btn-outline-info">
                                            <i class="fas fa-upload"></i> Share Document
                                        </a>
                                        <a href="group_messages.php" class="btn btn-outline-secondary">
                                            <i class="fas fa-users"></i> Group Messages
                                        </a>
                                        <a href="settings.php" class="btn btn-outline-dark">
                                            <i class="fas fa-cog"></i> Notification Settings
                                        </a>
                                    </div>
                                </div>
                            </div>

                            <!-- Notifications -->
                            <div class="card border-0 shadow-sm mb-4">
                                <div class="card-header bg-info bg-gradient text-white">
                                    <h6 class="mb-0">
                                        <i class="fas fa-bell"></i>
                                        Recent Notifications
                                    </h6>
                                </div>
                                <div class="card-body">
                                    <?php if (empty($dashboardData['unread_notifications'])): ?>
                                        <p class="text-muted text-center mb-0">No new notifications</p>
                                    <?php else: ?>
                                        <div class="notification-list">
                                            <?php foreach (array_slice($dashboardData['unread_notifications'], 0, 5) as $notification): ?>
                                            <div class="notification-item p-2 mb-2 border rounded">
                                                <div class="d-flex align-items-start">
                                                    <i class="fas fa-<?= $notification['icon'] ?? 'bell' ?> text-<?= $notification['color'] ?? 'primary' ?> me-2 mt-1"></i>
                                                    <div class="flex-grow-1">
                                                        <h6 class="mb-1 fs-6"><?= htmlspecialchars($notification['title']) ?></h6>
                                                        <p class="mb-1 small"><?= htmlspecialchars($notification['message']) ?></p>
                                                        <small class="text-muted"><?= date('M j, g:i A', strtotime($notification['created_at'])) ?></small>
                                                    </div>
                                                    <button class="btn btn-sm btn-outline-secondary" onclick="markNotificationRead(<?= $notification['id'] ?>)">
                                                        <i class="fas fa-check"></i>
                                                    </button>
                                                </div>
                                            </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- Recent Shared Documents -->
                            <div class="card border-0 shadow-sm">
                                <div class="card-header bg-secondary bg-gradient text-white d-flex justify-content-between align-items-center">
                                    <h6 class="mb-0">
                                        <i class="fas fa-file-alt"></i>
                                        Recent Documents
                                    </h6>
                                    <a href="documents.php" class="btn btn-sm btn-light">
                                        View All
                                    </a>
                                </div>
                                <div class="card-body">
                                    <?php if (empty($dashboardData['shared_documents'])): ?>
                                        <p class="text-muted text-center mb-0">No recent documents</p>
                                    <?php else: ?>
                                        <div class="list-group list-group-flush">
                                            <?php foreach ($dashboardData['shared_documents'] as $document): ?>
                                            <div class="list-group-item px-0 py-2">
                                                <div class="d-flex justify-content-between align-items-center">
                                                    <div class="d-flex align-items-center">
                                                        <i class="fas fa-file-<?= $this->getFileIcon($document['mime_type']) ?> text-primary me-2"></i>
                                                        <div>
                                                            <h6 class="mb-0 fs-6"><?= htmlspecialchars($document['name']) ?></h6>
                                                            <small class="text-muted">
                                                                by <?= htmlspecialchars($document['uploader_name']) ?>
                                                                | <?= date('M j', strtotime($document['created_at'])) ?>
                                                            </small>
                                                        </div>
                                                    </div>
                                                    <div class="btn-group btn-group-sm">
                                                        <button class="btn btn-outline-primary" onclick="viewDocument(<?= $document['id'] ?>)">
                                                            <i class="fas fa-eye"></i>
                                                        </button>
                                                        <button class="btn btn-outline-success" onclick="downloadDocument(<?= $document['id'] ?>)">
                                                            <i class="fas fa-download"></i>
                                                        </button>
                                                    </div>
                                                </div>
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
    <script src="js/messaging.js"></script>

    <script>
        // Initialize dashboard on page load
        document.addEventListener('DOMContentLoaded', function() {
            console.log('Messaging dashboard loaded');
            ARMIS_MESSAGING.init();
        });

        function refreshDashboard() {
            location.reload();
        }

        function viewAnnouncement(id) {
            window.open(`announcement_details.php?id=${id}`, '_blank');
        }

        function markNotificationRead(id) {
            ARMIS_MESSAGING.notifications.markAsRead(id);
        }

        function viewDocument(id) {
            window.open(`document_viewer.php?id=${id}`, '_blank');
        }

        function downloadDocument(id) {
            window.open(`download_document.php?id=${id}`, '_blank');
        }
    </script>
</body>
</html>

<?php
// Helper function for file icons
function getFileIcon($mimeType) {
    if (strpos($mimeType, 'pdf') !== false) return 'pdf';
    if (strpos($mimeType, 'word') !== false || strpos($mimeType, 'document') !== false) return 'word';
    if (strpos($mimeType, 'excel') !== false || strpos($mimeType, 'spreadsheet') !== false) return 'excel';
    if (strpos($mimeType, 'image') !== false) return 'image';
    if (strpos($mimeType, 'video') !== false) return 'video';
    return 'alt';
}
?>