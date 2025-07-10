<?php
require_once 'auth.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ARMIS - Army Resource Management Information System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="users/css/armis_custom.css">
    <style>
        :root {
            --primary: #355E3B;
            --yellow: #f1c40f;
        }
        body {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            min-height: 100vh;
        }
        .jumbotron {
            background: white;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            padding: 3rem;
            margin: 2rem 0;
        }
        .btn-primary {
            background: var(--primary);
            border-color: var(--primary);
        }
        .btn-primary:hover {
            background: #2d4d32;
            border-color: #2d4d32;
        }
        .btn-warning {
            background: var(--yellow);
            border-color: var(--yellow);
            color: #000;
        }
        .btn-warning:hover {
            background: #d4ac0d;
            border-color: #d4ac0d;
            color: #000;
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg armis-navbar">
        <div class="container">
            <a class="navbar-brand armis-brand" href="/">ARMIS</a>
        </div>
    </nav>

    <div class="container">
        <div class="jumbotron">
            <h1 class="text-center">ARMIS</h1>
            <p class="text-center text-muted">Welcome to the Army Resource Management Information System</p>
            <p class="text-center">
                <?php if (isLoggedIn()): ?>
                    <a class="btn btn-primary" href="users/admin.php" role="button">Admin Dashboard &raquo;</a>
                    <a class="btn btn-secondary" href="logout.php" role="button">Logout</a>
                <?php else: ?>
                    <a class="btn btn-warning" href="login.php" role="button">Login &raquo;</a>
                <?php endif; ?>
            </p>
            <br>
            <p class="text-center">Make sure you have your correct login details to use this system.</p>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
