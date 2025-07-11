<?php
require_once '../staff_auth_standalone.php';
requireLogin();

$user = getCurrentUser();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ARMIS - Account Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link rel="stylesheet" href="css/armis_custom.css">
</head>
<body>
    <?php include 'includes/sidebar.php'; ?>
    
    <div class="main-content">
        <div class="dashboard-header">
            <div class="container-fluid">
                <h1><i class="fas fa-user"></i> Account Dashboard</h1>
                <p class="mb-0">Welcome back, <?php echo htmlspecialchars($user['fname'] . ' ' . $user['lname']); ?>!</p>
            </div>
        </div>

        <div class="container-fluid">
            <?php displayMessages(); ?>
            
            <!-- Account Information -->
            <div class="row">
                <div class="col-md-8">
                    <div class="card">
                        <div class="card-header">
                            <h5><i class="fas fa-user-circle"></i> Account Information</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <p><strong>Service Number:</strong> <?php echo htmlspecialchars($user['svcNo']); ?></p>
                                    <p><strong>Full Name:</strong> <?php echo htmlspecialchars($user['fname'] . ' ' . $user['lname']); ?></p>
                                    <p><strong>Username:</strong> <?php echo htmlspecialchars($user['username']); ?></p>
                                </div>
                                <div class="col-md-6">
                                    <p><strong>Email:</strong> <?php echo htmlspecialchars($user['email']); ?></p>
                                    <p><strong>Role:</strong> <?php echo htmlspecialchars($user['role']); ?></p>
                                    <p><strong>Login Time:</strong> <?php echo date('Y-m-d H:i:s', $user['login_time']); ?></p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-4">
                    <div class="card">
                        <div class="card-header">
                            <h5><i class="fas fa-cogs"></i> Quick Actions</h5>
                        </div>
                        <div class="card-body">
                            <div class="d-grid gap-2">
                                <a href="change_password.php" class="btn btn-outline-primary">
                                    <i class="fas fa-key"></i> Change Password
                                </a>
                                <?php if (isAdminBranch()): ?>
                                <a href="admin_branch.php" class="btn btn-outline-success">
                                    <i class="fas fa-users-cog"></i> Admin Branch
                                </a>
                                <?php endif; ?>
                                <?php if (isAdmin()): ?>
                                <a href="admin.php" class="btn btn-outline-warning">
                                    <i class="fas fa-shield-alt"></i> System Admin
                                </a>
                                <?php endif; ?>
                                <a href="../logout.php" class="btn btn-outline-danger">
                                    <i class="fas fa-sign-out-alt"></i> Logout
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Recent Activity -->
            <div class="row mt-4">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h5><i class="fas fa-history"></i> Recent Activity</h5>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-striped">
                                    <thead>
                                        <tr>
                                            <th>Date/Time</th>
                                            <th>Action</th>
                                            <th>Details</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php
                                        try {
                                            $db = getDatabase();
                                            $stmt = $db->prepare("SELECT * FROM logs WHERE user_id = ? ORDER BY logdate DESC LIMIT 10");
                                            $stmt->execute([$user['svcNo']]);
                                            $logs = $stmt->fetchAll();
                                            
                                            if (!empty($logs)) {
                                                foreach ($logs as $log) {
                                                    echo '<tr>';
                                                    echo '<td>' . htmlspecialchars($log['logdate']) . '</td>';
                                                    echo '<td>' . htmlspecialchars($log['logtype']) . '</td>';
                                                    echo '<td>' . htmlspecialchars($log['lognote']) . '</td>';
                                                    echo '</tr>';
                                                }
                                            } else {
                                                echo '<tr><td colspan="3" class="text-center text-muted">No recent activity found</td></tr>';
                                            }
                                        } catch (Exception $e) {
                                            echo '<tr><td colspan="3" class="text-center text-danger">Error loading activity log</td></tr>';
                                        }
                                        ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Include footer and scripts -->
    <?php include 'includes/page_footer.php'; ?>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>