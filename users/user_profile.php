<?php
require_once '../auth.php';
requireAdmin();

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    if (CSRFToken::validate($_POST['csrf_token'])) {
        // In a real application, this would update the database
        setMessage('Profile updated successfully!');
        redirect('user_profile.php');
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
    <title>ARMIS - User Profile</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link rel="stylesheet" href="css/armis_custom.css">
</head>
<body>
    <?php include 'includes/sidebar.php'; ?>
    
    <div class="main-content">
        <div class="dashboard-header">
            <div class="container-fluid">
                <h1><i class="fas fa-user"></i> User Profile</h1>
                <p class="mb-0">Manage your account settings and preferences</p>
            </div>
        </div>

        <div class="container-fluid">
            <?php displayMessages(); ?>
            
            <div class="row">
                <div class="col-md-8">
                    <div class="dashboard-card">
                        <h3><i class="fas fa-user-edit"></i> Profile Information</h3>
                        <form method="POST" action="user_profile.php">
                            <?php echo CSRFToken::getField(); ?>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="username" class="form-label">Username</label>
                                        <input type="text" class="form-control" id="username" name="username" 
                                               value="<?php echo htmlspecialchars(getCurrentUser()['username']); ?>" readonly>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="email" class="form-label">Email</label>
                                        <input type="email" class="form-control" id="email" name="email" 
                                               value="admin@armis.mil" placeholder="admin@armis.mil">
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="first_name" class="form-label">First Name</label>
                                        <input type="text" class="form-control" id="first_name" name="first_name" 
                                               value="System" placeholder="First Name">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="last_name" class="form-label">Last Name</label>
                                        <input type="text" class="form-control" id="last_name" name="last_name" 
                                               value="Administrator" placeholder="Last Name">
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="department" class="form-label">Department</label>
                                <input type="text" class="form-control" id="department" name="department" 
                                       value="IT Administration" placeholder="Department">
                            </div>
                            
                            <div class="mb-3">
                                <button type="submit" name="update_profile" class="btn btn-primary">
                                    <i class="fas fa-save"></i> Update Profile
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
                
                <div class="col-md-4">
                    <div class="dashboard-card">
                        <h3><i class="fas fa-shield-alt"></i> Security</h3>
                        <div class="mb-3">
                            <label class="form-label">Account Type</label>
                            <p class="form-control-plaintext">Administrator</p>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Last Login</label>
                            <p class="form-control-plaintext">Today, <?php echo date('g:i A'); ?></p>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Account Status</label>
                            <p class="form-control-plaintext">
                                <span class="badge bg-success">Active</span>
                            </p>
                        </div>
                        <div class="mb-3">
                            <button class="btn btn-warning btn-sm w-100">
                                <i class="fas fa-key"></i> Change Password
                            </button>
                        </div>
                    </div>
                    
                    <div class="dashboard-card">
                        <h3><i class="fas fa-cog"></i> Preferences</h3>
                        <div class="mb-3">
                            <label class="form-label">Language</label>
                            <select class="form-select">
                                <option value="en" selected>English</option>
                                <option value="es">Spanish</option>
                                <option value="fr">French</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Timezone</label>
                            <select class="form-select">
                                <option value="UTC" selected>UTC</option>
                                <option value="EST">Eastern Time</option>
                                <option value="PST">Pacific Time</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="notifications" checked>
                                <label class="form-check-label" for="notifications">
                                    Email Notifications
                                </label>
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