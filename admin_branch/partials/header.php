<?php
// Configure session settings before starting session
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_samesite', 'Strict');

require_once '../../auth.php';
require_once '../db.php';
requireAdmin();

// Cache control headers
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ARMIS - <?php echo $pageTitle ?? 'Admin Dashboard'; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&family=Open+Sans:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../css/armis_custom.css">
    <style>
        :root {
            --primary: #355E3B;
            --yellow: #f1c40f;
            --primary-dark: #2d4d32;
            --primary-light: #4a7c59;
        }
        
        body {
            font-family: 'Roboto', 'Open Sans', Arial, sans-serif;
            background-color: #f8f9fa;
        }
        
        .main-content {
            padding: 20px;
        }
        
        .dashboard-card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 20px;
            padding: 20px;
        }
        
        .dashboard-card h3 {
            color: var(--primary);
            margin-bottom: 20px;
            font-weight: 600;
        }
        
        .btn-primary {
            background-color: var(--primary);
            border-color: var(--primary);
        }
        
        .btn-primary:hover {
            background-color: var(--primary-dark);
            border-color: var(--primary-dark);
        }
        
        .navbar {
            background-color: var(--primary) !important;
        }
        
        .navbar-brand, .nav-link {
            color: white !important;
        }
        
        .alert {
            border-radius: 8px;
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="../admin_branch.php">
                <i class="fas fa-shield-alt"></i> ARMIS Admin
            </a>
            <div class="navbar-nav ms-auto">
                <a class="nav-link" href="../admin_branch.php">
                    <i class="fas fa-dashboard"></i> Dashboard
                </a>
                <a class="nav-link" href="../../logout.php">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </div>
        </div>
    </nav>
    
    <div class="main-content">
        <div class="container-fluid">
            <?php if (isset($pageTitle)): ?>
            <div class="row">
                <div class="col-12">
                    <h2 class="mb-4" style="color: var(--primary);">
                        <i class="<?php echo $pageIcon ?? 'fas fa-cog'; ?>"></i> 
                        <?php echo $pageTitle; ?>
                    </h2>
                </div>
            </div>
            <?php endif; ?>
            
            <?php
            // Display messages
            if (isset($_SESSION['message'])) {
                echo '<div class="alert alert-' . $_SESSION['message_type'] . ' alert-dismissible fade show" role="alert">';
                echo $_SESSION['message'];
                echo '<button type="button" class="btn-close" data-bs-dismiss="alert"></button>';
                echo '</div>';
                unset($_SESSION['message'], $_SESSION['message_type']);
            }
            ?>