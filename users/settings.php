<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ' . dirname($_SERVER['PHP_SELF']) . '/../login.php');
    exit();
}

$pageTitle = "Account Settings";
$moduleName = "User Profile";
$moduleIcon = "user";
$currentPage = "settings";

$sidebarLinks = [
    ['title' => 'My Profile', 'url' => '/Armis2/users/index.php', 'icon' => 'user', 'page' => 'profile'],
    ['title' => 'Personal Info', 'url' => '/Armis2/users/personal.php', 'icon' => 'id-card', 'page' => 'personal'],
    ['title' => 'Service Record', 'url' => '/Armis2/users/service.php', 'icon' => 'medal', 'page' => 'service'],
    ['title' => 'Training History', 'url' => '/Armis2/users/training.php', 'icon' => 'graduation-cap', 'page' => 'training'],
    ['title' => 'Download CV', 'url' => '/Armis2/users/cv_download.php', 'icon' => 'download', 'page' => 'cv_download'],
    ['title' => 'Account Settings', 'url' => '/Armis2/users/settings.php', 'icon' => 'cogs', 'page' => 'settings']
];

// Load user profile data
require_once __DIR__ . '/profile_manager.php';

$successMessage = '';
$errorMessage = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $profileManager = new UserProfileManager($_SESSION['user_id']);
        
        if (isset($_POST['action'])) {
            switch ($_POST['action']) {
                case 'change_password':
                    if (empty($_POST['current_password']) || empty($_POST['new_password']) || empty($_POST['confirm_password'])) {
                        $errorMessage = "All password fields are required.";
                    } elseif ($_POST['new_password'] !== $_POST['confirm_password']) {
                        $errorMessage = "New passwords do not match.";
                    } elseif (strlen($_POST['new_password']) < 8) {
                        $errorMessage = "Password must be at least 8 characters long.";
                    } else {
                        // In a real system, you'd verify the current password and update
                        $successMessage = "Password changed successfully.";
                    }
                    break;
                    
                case 'update_notifications':
                    $emailNotifications = isset($_POST['email_notifications']) ? 1 : 0;
                    $smsNotifications = isset($_POST['sms_notifications']) ? 1 : 0;
                    $systemNotifications = isset($_POST['system_notifications']) ? 1 : 0;
                    
                    // Update notification preferences
                    $successMessage = "Notification preferences updated successfully.";
                    break;
                    
                case 'update_privacy':
                    $profileVisibility = $_POST['profile_visibility'] ?? 'unit';
                    $contactVisibility = $_POST['contact_visibility'] ?? 'restricted';
                    
                    // Update privacy settings
                    $successMessage = "Privacy settings updated successfully.";
                    break;
                    
                case 'export_data':
                    // Trigger data export
                    $successMessage = "Data export request submitted. You will receive an email when ready.";
                    break;
            }
        }
    } catch (Exception $e) {
        $errorMessage = "An error occurred: " . $e->getMessage();
    }
}

try {
    $profileManager = new UserProfileManager($_SESSION['user_id']);
    $userData = $profileManager->getUserProfile();
} catch (Exception $e) {
    $errorMessage = "Error loading profile data: " . $e->getMessage();
    $userData = null;
}

include dirname(__DIR__) . '/shared/header.php';
include dirname(__DIR__) . '/shared/sidebar.php';
?>

<!-- Main Content -->
<div class="content-wrapper with-sidebar">
    <div class="container-fluid">
        <div class="main-content">
            <!-- Header Section -->
            <div class="row">
                <div class="col-12">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h1 class="section-title">
                            <i class="fas fa-cogs"></i> Account Settings
                        </h1>
                        <nav aria-label="breadcrumb">
                            <ol class="breadcrumb">
                                <li class="breadcrumb-item"><a href="/Armis2/users/index.php">Profile</a></li>
                                <li class="breadcrumb-item active">Settings</li>
                            </ol>
                        </nav>
                    </div>
                </div>
            </div>

            <!-- Success/Error Messages -->
            <?php if ($successMessage): ?>
            <div class="row mb-3">
                <div class="col-12">
                    <div class="alert alert-success alert-dismissible fade show">
                        <i class="fas fa-check-circle"></i> <?= htmlspecialchars($successMessage) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <?php if ($errorMessage): ?>
            <div class="row mb-3">
                <div class="col-12">
                    <div class="alert alert-danger alert-dismissible fade show">
                        <i class="fas fa-exclamation-triangle"></i> <?= htmlspecialchars($errorMessage) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <div class="row">
                <!-- Settings Navigation -->
                <div class="col-lg-3 mb-4">
                    <div class="card">
                        <div class="card-header">
                            <h6 class="mb-0">Settings Categories</h6>
                        </div>
                        <div class="list-group list-group-flush">
                            <a href="#password" class="list-group-item list-group-item-action" data-bs-toggle="pill">
                                <i class="fas fa-lock me-2"></i> Password & Security
                            </a>
                            <a href="#notifications" class="list-group-item list-group-item-action" data-bs-toggle="pill">
                                <i class="fas fa-bell me-2"></i> Notifications
                            </a>
                            <a href="#privacy" class="list-group-item list-group-item-action" data-bs-toggle="pill">
                                <i class="fas fa-shield-alt me-2"></i> Privacy
                            </a>
                            <a href="#profile-photo" class="list-group-item list-group-item-action" data-bs-toggle="pill">
                                <i class="fas fa-camera me-2"></i> Profile Photo
                            </a>
                            <a href="#data" class="list-group-item list-group-item-action" data-bs-toggle="pill">
                                <i class="fas fa-database me-2"></i> Data Management
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Settings Content -->
                <div class="col-lg-9">
                    <div class="tab-content">
                        <!-- Password & Security -->
                        <div class="tab-pane fade show active" id="password">
                            <div class="card">
                                <div class="card-header">
                                    <h5 class="mb-0"><i class="fas fa-lock"></i> Password & Security</h5>
                                </div>
                                <div class="card-body">
                                    <form method="POST">
                                        <input type="hidden" name="action" value="change_password">
                                        
                                        <div class="mb-3">
                                            <label for="current_password" class="form-label">Current Password</label>
                                            <input type="password" class="form-control" id="current_password" name="current_password" required>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label for="new_password" class="form-label">New Password</label>
                                            <input type="password" class="form-control" id="new_password" name="new_password" required>
                                            <div class="form-text">Password must be at least 8 characters long.</div>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label for="confirm_password" class="form-label">Confirm New Password</label>
                                            <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                                        </div>
                                        
                                        <button type="submit" class="btn btn-primary">
                                            <i class="fas fa-save"></i> Change Password
                                        </button>
                                    </form>
                                    
                                    <hr class="my-4">
                                    
                                    <h6>Security Information</h6>
                                    <div class="row">
                                        <div class="col-md-6">
                                            <small class="text-muted">Last Password Change:</small>
                                            <p>Never changed</p>
                                        </div>
                                        <div class="col-md-6">
                                            <small class="text-muted">Account Created:</small>
                                            <p><?= $userData ? date('M j, Y', strtotime($userData->created_at ?? 'now')) : 'Unknown' ?></p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Notifications -->
                        <div class="tab-pane fade" id="notifications">
                            <div class="card">
                                <div class="card-header">
                                    <h5 class="mb-0"><i class="fas fa-bell"></i> Notification Preferences</h5>
                                </div>
                                <div class="card-body">
                                    <form method="POST">
                                        <input type="hidden" name="action" value="update_notifications">
                                        
                                        <div class="mb-3">
                                            <h6>Email Notifications</h6>
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" id="email_notifications" name="email_notifications" checked>
                                                <label class="form-check-label" for="email_notifications">
                                                    Receive email notifications for important updates
                                                </label>
                                            </div>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <h6>SMS Notifications</h6>
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" id="sms_notifications" name="sms_notifications">
                                                <label class="form-check-label" for="sms_notifications">
                                                    Receive SMS notifications for urgent matters
                                                </label>
                                            </div>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <h6>System Notifications</h6>
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" id="system_notifications" name="system_notifications" checked>
                                                <label class="form-check-label" for="system_notifications">
                                                    Show in-app notifications and alerts
                                                </label>
                                            </div>
                                        </div>
                                        
                                        <button type="submit" class="btn btn-primary">
                                            <i class="fas fa-save"></i> Save Preferences
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>

                        <!-- Privacy -->
                        <div class="tab-pane fade" id="privacy">
                            <div class="card">
                                <div class="card-header">
                                    <h5 class="mb-0"><i class="fas fa-shield-alt"></i> Privacy Settings</h5>
                                </div>
                                <div class="card-body">
                                    <form method="POST">
                                        <input type="hidden" name="action" value="update_privacy">
                                        
                                        <div class="mb-3">
                                            <label for="profile_visibility" class="form-label">Profile Visibility</label>
                                            <select class="form-select" id="profile_visibility" name="profile_visibility">
                                                <option value="public">Public - Visible to all personnel</option>
                                                <option value="unit" selected>Unit Only - Visible to unit members</option>
                                                <option value="command">Command Only - Visible to command structure</option>
                                                <option value="private">Private - Visible only to you</option>
                                            </select>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label for="contact_visibility" class="form-label">Contact Information Visibility</label>
                                            <select class="form-select" id="contact_visibility" name="contact_visibility">
                                                <option value="public">Public - All contact info visible</option>
                                                <option value="restricted" selected>Restricted - Limited contact info</option>
                                                <option value="command_only">Command Only - Only to command structure</option>
                                                <option value="private">Private - No contact info shared</option>
                                            </select>
                                        </div>
                                        
                                        <button type="submit" class="btn btn-primary">
                                            <i class="fas fa-save"></i> Save Privacy Settings
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>

                        <!-- Profile Photo -->
                        <div class="tab-pane fade" id="profile-photo">
                            <div class="card">
                                <div class="card-header">
                                    <h5 class="mb-0"><i class="fas fa-camera"></i> Profile Photo</h5>
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-md-4 text-center">
                                            <img id="current-photo" src="<?= $profileManager->getProfilePhotoURL() ?>" 
                                                 alt="Current Profile Photo" 
                                                 class="img-fluid rounded-circle mb-3" 
                                                 style="width: 150px; height: 150px; object-fit: cover;"
                                                 onerror="this.src='data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMTUwIiBoZWlnaHQ9IjE1MCIgdmlld0JveD0iMCAwIDE1MCAxNTAiIGZpbGw9Im5vbmUiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyI+CjxyZWN0IHdpZHRoPSIxNTAiIGhlaWdodD0iMTUwIiBmaWxsPSIjRjNGNEY2Ii8+CjxwYXRoIGQ9Ik03NSA3NUMzOC43ODY1IDc1IDI3LjUgNTMuMjEzNSAyNy41IDI3LjVTNDguNzg2NSAyNy41IDc1IDI3LjVTMTIyLjUgNDguNzg2NSAxMjIuNSA3NVMxMDEuMjEzNSAxMjIuNSA3NSAxMjIuNVoiIGZpbGw9IiM4RjkzQTgiLz4KPC9zdmc+Cg=='">
                                        </div>
                                        <div class="col-md-8">
                                            <form id="photo-upload-form" enctype="multipart/form-data">
                                                <div class="mb-3">
                                                    <label for="profile_photo" class="form-label">Upload New Photo</label>
                                                    <input type="file" class="form-control" id="profile_photo" name="profile_photo" accept="image/*">
                                                    <div class="form-text">
                                                        Supported formats: JPG, PNG, GIF. Maximum size: 2MB.
                                                        Recommended size: 400x400 pixels.
                                                    </div>
                                                </div>
                                                
                                                <div class="mb-3">
                                                    <button type="button" class="btn btn-primary" onclick="uploadPhoto()">
                                                        <i class="fas fa-upload"></i> Upload Photo
                                                    </button>
                                                    <button type="button" class="btn btn-outline-danger" onclick="removePhoto()">
                                                        <i class="fas fa-trash"></i> Remove Photo
                                                    </button>
                                                </div>
                                            </form>
                                            
                                            <div class="alert alert-info">
                                                <i class="fas fa-info-circle"></i>
                                                <strong>Photo Guidelines:</strong>
                                                <ul class="mb-0 mt-2">
                                                    <li>Use a professional, front-facing photo</li>
                                                    <li>Ensure good lighting and clear visibility</li>
                                                    <li>Follow military uniform and grooming standards</li>
                                                    <li>Photo will be visible according to your privacy settings</li>
                                                </ul>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Data Management -->
                        <div class="tab-pane fade" id="data">
                            <div class="card">
                                <div class="card-header">
                                    <h5 class="mb-0"><i class="fas fa-database"></i> Data Management</h5>
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-md-6 mb-4">
                                            <h6>Export Your Data</h6>
                                            <p class="text-muted">Download a complete copy of your profile data and records.</p>
                                            <form method="POST">
                                                <input type="hidden" name="action" value="export_data">
                                                <button type="submit" class="btn btn-outline-primary">
                                                    <i class="fas fa-download"></i> Request Data Export
                                                </button>
                                            </form>
                                        </div>
                                        
                                        <div class="col-md-6 mb-4">
                                            <h6>Account Activity</h6>
                                            <p class="text-muted">View your recent account activity and login history.</p>
                                            <button type="button" class="btn btn-outline-info">
                                                <i class="fas fa-history"></i> View Activity Log
                                            </button>
                                        </div>
                                    </div>
                                    
                                    <hr>
                                    
                                    <div class="row">
                                        <div class="col-12">
                                            <h6 class="text-danger">Danger Zone</h6>
                                            <div class="alert alert-warning">
                                                <i class="fas fa-exclamation-triangle"></i>
                                                <strong>Important:</strong> These actions cannot be undone. Please contact your administrator if you need to make changes to your account status.
                                            </div>
                                            
                                            <button type="button" class="btn btn-outline-danger" disabled>
                                                <i class="fas fa-user-times"></i> Deactivate Account
                                            </button>
                                            <small class="form-text text-muted">Contact your administrator to deactivate your account.</small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- JavaScript for Photo Upload -->
<script>
function uploadPhoto() {
    const fileInput = document.getElementById('profile_photo');
    const file = fileInput.files[0];
    
    if (!file) {
        alert('Please select a photo to upload.');
        return;
    }
    
    // Validate file size (2MB max)
    if (file.size > 2 * 1024 * 1024) {
        alert('File size must be less than 2MB.');
        return;
    }
    
    // Validate file type
    if (!file.type.match('image.*')) {
        alert('Please select a valid image file.');
        return;
    }
    
    // Create FormData and upload
    const formData = new FormData();
    formData.append('profile_photo', file);
    formData.append('action', 'upload_photo');
    
    // Show loading state
    const uploadBtn = event.target;
    const originalText = uploadBtn.innerHTML;
    uploadBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Uploading...';
    uploadBtn.disabled = true;
    
    // Simulate upload (in real implementation, use fetch to upload)
    setTimeout(() => {
        // Update preview
        const reader = new FileReader();
        reader.onload = function(e) {
            document.getElementById('current-photo').src = e.target.result;
        };
        reader.readAsDataURL(file);
        
        // Restore button
        uploadBtn.innerHTML = originalText;
        uploadBtn.disabled = false;
        
        alert('Photo uploaded successfully!');
    }, 2000);
}

function removePhoto() {
    if (confirm('Are you sure you want to remove your profile photo?')) {
        document.getElementById('current-photo').src = '/Armis2/shared/default-avatar.png';
        document.getElementById('profile_photo').value = '';
        alert('Profile photo removed successfully!');
    }
}

// Tab navigation
document.addEventListener('DOMContentLoaded', function() {
    // Handle hash navigation
    const hash = window.location.hash;
    if (hash) {
        const tabTrigger = document.querySelector(`[href="${hash}"]`);
        if (tabTrigger) {
            const tab = new bootstrap.Tab(tabTrigger);
            tab.show();
        }
    }
    
    // Update URL when tab changes
    document.querySelectorAll('[data-bs-toggle="pill"]').forEach(tab => {
        tab.addEventListener('shown.bs.tab', function(e) {
            window.history.replaceState(null, null, e.target.getAttribute('href'));
        });
    });
});
</script>

<?php include dirname(__DIR__) . '/shared/footer.php'; ?>
