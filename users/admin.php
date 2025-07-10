<?php
require_once '../auth.php';
requireAdmin();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ARMIS - Admin Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link rel="stylesheet" href="css/armis_custom.css">
    <style>
        :root {
            --primary: #355E3B;
            --yellow: #f1c40f;
        }
        .admin-header {
            background: var(--primary);
            color: white;
            padding: 1rem 0;
            margin-bottom: 2rem;
        }
        .admin-card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            padding: 2rem;
            margin-bottom: 2rem;
        }
        .btn-custom {
            background: var(--primary);
            border-color: var(--primary);
            color: white;
        }
        .btn-custom:hover {
            background: #2d4d32;
            border-color: #2d4d32;
            color: white;
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg armis-navbar">
        <div class="container">
            <a class="navbar-brand armis-brand" href="../">ARMIS</a>
            <div class="navbar-nav ms-auto">
                <a class="nav-link" href="../logout.php">Logout</a>
            </div>
        </div>
    </nav>

    <div class="admin-header">
        <div class="container">
            <h1><i class="fa fa-dashboard"></i> Admin Dashboard</h1>
            <p class="mb-0">Welcome, <?php echo htmlspecialchars(getCurrentUser()['username']); ?>!</p>
        </div>
    </div>

    <div class="container">
        <div class="admin-card">
            <h3>Quick Actions</h3>
            <div class="row">
                <div class="col-md-3 mb-3">
                    <a href="employees.php" class="btn btn-custom w-100 py-3">
                        <i class="fa fa-users"></i><br>Manage Employees
                    </a>
                </div>
                <div class="col-md-3 mb-3">
                    <a href="admin_branch.php" class="btn btn-custom w-100 py-3">
                        <i class="fa fa-cogs"></i><br>Admin Branch
                    </a>
                </div>
                <div class="col-md-3 mb-3">
                    <a href="command_reports.php" class="btn btn-custom w-100 py-3">
                        <i class="fa fa-chart-bar"></i><br>Command Reports
                    </a>
                </div>
                <div class="col-md-3 mb-3">
                    <a href="training/courses.php" class="btn btn-custom w-100 py-3">
                        <i class="fa fa-graduation-cap"></i><br>Training Courses
                    </a>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

