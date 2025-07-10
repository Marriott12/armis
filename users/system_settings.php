<?php
require_once '../auth.php';
requireAdmin();

// Handle settings update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_settings'])) {
    if (CSRFToken::validate($_POST['csrf_token'])) {
        // In a real application, this would update the database
        setMessage('System settings updated successfully!');
        redirect('system_settings.php');
    } else {
        setMessage('Security token validation failed.', 'error');
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ARMIS - System Settings</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link rel="stylesheet" href="css/armis_custom.css">
</head>
<body>
    <?php include 'includes/sidebar.php'; ?>
    
    <div class="main-content">
        <div class="dashboard-header">
            <div class="container-fluid">
                <h1><i class="fas fa-cog"></i> System Settings</h1>
                <p class="mb-0">Configure system-wide settings and preferences</p>
            </div>
        </div>

        <div class="container-fluid">
            <?php displayMessages(); ?>
            
            <div class="row">
                <div class="col-md-8">
                    <div class="dashboard-card">
                        <h3><i class="fas fa-server"></i> General Settings</h3>
                        <form method="POST" action="system_settings.php">
                            <?php echo CSRFToken::getField(); ?>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="system_name" class="form-label">System Name</label>
                                        <input type="text" class="form-control" id="system_name" name="system_name" 
                                               value="ARMIS" placeholder="System Name">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="system_version" class="form-label">System Version</label>
                                        <input type="text" class="form-control" id="system_version" name="system_version" 
                                               value="2.0" placeholder="Version">
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="system_description" class="form-label">System Description</label>
                                <textarea class="form-control" id="system_description" name="system_description" rows="3" 
                                          placeholder="System Description">Army Resource Management Information System</textarea>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="session_timeout" class="form-label">Session Timeout (minutes)</label>
                                        <input type="number" class="form-control" id="session_timeout" name="session_timeout" 
                                               value="30" min="5" max="480">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="max_login_attempts" class="form-label">Max Login Attempts</label>
                                        <input type="number" class="form-control" id="max_login_attempts" name="max_login_attempts" 
                                               value="5" min="1" max="10">
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <button type="submit" name="update_settings" class="btn btn-primary">
                                    <i class="fas fa-save"></i> Save Settings
                                </button>
                            </div>
                        </form>
                    </div>
                    
                    <div class="dashboard-card">
                        <h3><i class="fas fa-database"></i> Database Settings</h3>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Database Status</label>
                                    <p class="form-control-plaintext">
                                        <span class="badge bg-success">Connected</span>
                                    </p>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Last Backup</label>
                                    <p class="form-control-plaintext">Today, 2:00 AM</p>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <button class="btn btn-info btn-sm">
                                    <i class="fas fa-download"></i> Backup Database
                                </button>
                            </div>
                            <div class="col-md-6">
                                <button class="btn btn-warning btn-sm">
                                    <i class="fas fa-upload"></i> Restore Database
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-4">
                    <div class="dashboard-card">
                        <h3><i class="fas fa-shield-alt"></i> Security Settings</h3>
                        <div class="mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="force_https" checked>
                                <label class="form-check-label" for="force_https">
                                    Force HTTPS
                                </label>
                            </div>
                        </div>
                        <div class="mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="csrf_protection" checked>
                                <label class="form-check-label" for="csrf_protection">
                                    CSRF Protection
                                </label>
                            </div>
                        </div>
                        <div class="mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="audit_logging" checked>
                                <label class="form-check-label" for="audit_logging">
                                    Audit Logging
                                </label>
                            </div>
                        </div>
                        <div class="mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="failed_login_tracking" checked>
                                <label class="form-check-label" for="failed_login_tracking">
                                    Failed Login Tracking
                                </label>
                            </div>
                        </div>
                    </div>
                    
                    <div class="dashboard-card">
                        <h3><i class="fas fa-bell"></i> Notification Settings</h3>
                        <div class="mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="email_notifications" checked>
                                <label class="form-check-label" for="email_notifications">
                                    Email Notifications
                                </label>
                            </div>
                        </div>
                        <div class="mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="system_alerts" checked>
                                <label class="form-check-label" for="system_alerts">
                                    System Alerts
                                </label>
                            </div>
                        </div>
                        <div class="mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="backup_notifications" checked>
                                <label class="form-check-label" for="backup_notifications">
                                    Backup Notifications
                                </label>
                            </div>
                        </div>
                    </div>
                    
                    <div class="dashboard-card">
                        <h3><i class="fas fa-info-circle"></i> System Information</h3>
                        <div class="mb-2">
                            <small class="text-muted">PHP Version:</small>
                            <div><?php echo phpversion(); ?></div>
                        </div>
                        <div class="mb-2">
                            <small class="text-muted">Server Software:</small>
                            <div><?php echo $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown'; ?></div>
                        </div>
                        <div class="mb-2">
                            <small class="text-muted">System Load:</small>
                            <div>
                                <div class="progress" style="height: 5px;">
                                    <div class="progress-bar bg-success" role="progressbar" style="width: 25%"></div>
                                </div>
                                <small>Low (25%)</small>
                            </div>
                        </div>
                        <div class="mb-2">
                            <small class="text-muted">Disk Usage:</small>
                            <div>
                                <div class="progress" style="height: 5px;">
                                    <div class="progress-bar bg-primary" role="progressbar" style="width: 60%"></div>
                                </div>
                                <small>60% of 100GB</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>