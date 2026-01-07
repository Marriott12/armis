<?php
/**
 * Staff Password Reset Tool
 * Allows administrators to reset passwords for staff members
 * 
 * Usage:
 * 1. Via Browser: http://localhost/Armis2/reset_staff_password.php
 * 2. Via CLI: php reset_staff_password.php --service-number=108458
 */

// Start session
session_start();

// Database connection
require_once __DIR__ . '/shared/database_connection.php';

// Check if running from CLI
$isCLI = php_sapi_name() === 'cli';

if ($isCLI) {
    // === CLI MODE ===
    
    // Parse command line arguments
    $options = getopt('', ['service-number:', 'password::', 'help']);
    
    if (isset($options['help']) || !isset($options['service-number'])) {
        echo "\n";
        echo "╔═══════════════════════════════════════════════════════════╗\n";
        echo "║          ARMIS Staff Password Reset Tool                 ║\n";
        echo "╚═══════════════════════════════════════════════════════════╝\n";
        echo "\n";
        echo "Usage:\n";
        echo "  php reset_staff_password.php --service-number=108458 [--password=CustomPass123]\n";
        echo "\n";
        echo "Options:\n";
        echo "  --service-number    Staff service number (required)\n";
        echo "  --password          Custom password (optional, auto-generated if not provided)\n";
        echo "  --help              Show this help message\n";
        echo "\n";
        echo "Examples:\n";
        echo "  php reset_staff_password.php --service-number=108458\n";
        echo "  php reset_staff_password.php --service-number=108458 --password=TempPass2024\n";
        echo "\n";
        exit(0);
    }
    
    $serviceNumber = $options['service-number'];
    $customPassword = $options['password'] ?? null;
    
    try {
        $pdo = getDbConnection();
        
        // Find staff member
        $stmt = $pdo->prepare("
            SELECT id, svcNo, fName, lName, username, email, rankId
            FROM staff 
            WHERE svcNo = ?
        ");
        $stmt->execute([$serviceNumber]);
        $staff = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$staff) {
            echo "\n❌ ERROR: No staff member found with service number: $serviceNumber\n\n";
            exit(1);
        }
        
        echo "\n";
        echo "═══════════════════════════════════════════════════════════\n";
        echo "Staff Member Found:\n";
        echo "═══════════════════════════════════════════════════════════\n";
        echo "Name:            {$staff['fName']} {$staff['lName']}\n";
        echo "Service Number:  {$staff['svcNo']}\n";
        echo "Username:        " . ($staff['username'] ?: 'Not set') . "\n";
        echo "Email:           " . ($staff['email'] ?: 'Not set') . "\n";
        echo "═══════════════════════════════════════════════════════════\n\n";
        
        // Generate or use custom password
        if ($customPassword) {
            $newPassword = $customPassword;
            echo "Using custom password...\n";
        } else {
            // Generate secure random password
            $newPassword = bin2hex(random_bytes(6)); // 12 character password
            echo "Generated random password...\n";
        }
        
        // Hash password
        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
        
        // Update username if not set
        $username = $staff['username'] ?: $serviceNumber;
        
        // Update database
        $updateStmt = $pdo->prepare("
            UPDATE staff 
            SET password = ?, 
                username = ?, 
                isFirstLogin = 1,
                accStatus = 'Active'
            WHERE id = ?
        ");
        
        if ($updateStmt->execute([$hashedPassword, $username, $staff['id']])) {
            echo "✅ Password reset successful!\n\n";
            echo "═══════════════════════════════════════════════════════════\n";
            echo "📋 NEW LOGIN CREDENTIALS\n";
            echo "═══════════════════════════════════════════════════════════\n";
            echo "Username: $username\n";
            echo "Password: $newPassword\n";
            echo "═══════════════════════════════════════════════════════════\n\n";
            echo "⚠️  IMPORTANT:\n";
            echo "• User will be required to change password on first login\n";
            echo "• Account status set to 'Active'\n";
            echo "• Please communicate these credentials securely\n\n";
        } else {
            echo "❌ ERROR: Failed to update password\n\n";
            exit(1);
        }
        
    } catch (Exception $e) {
        echo "\n❌ ERROR: " . $e->getMessage() . "\n\n";
        exit(1);
    }
    
} else {
    // === WEB MODE ===
    
    // Simple authentication check (must be logged in as admin)
    if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
        http_response_code(403);
        die('Access denied. Admin access required.');
    }
    
    $message = '';
    $error = '';
    $credentials = null;
    
    // Handle form submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['svcNo'])) {
        $serviceNumber = trim($_POST['svcNo']);
        $customPassword = trim($_POST['custom_password'] ?? '');
        
        try {
            $pdo = getDbConnection();
            
            // Find staff member
            $stmt = $pdo->prepare("
                SELECT id, svcNo, fName, lName, username, email
                FROM staff 
                WHERE svcNo = ?
            ");
            $stmt->execute([$serviceNumber]);
            $staff = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$staff) {
                $error = "No staff member found with service number: $serviceNumber";
            } else {
                // Generate or use custom password
                if ($customPassword) {
                    $newPassword = $customPassword;
                } else {
                    $newPassword = bin2hex(random_bytes(6)); // 12 character password
                }
                
                // Hash password
                $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
                
                // Update username if not set
                $username = $staff['username'] ?: $serviceNumber;
                
                // Update database
                $updateStmt = $pdo->prepare("
                    UPDATE staff 
                    SET password = ?, 
                        username = ?, 
                        isFirstLogin = 1,
                        accStatus = 'Active'
                    WHERE id = ?
                ");
                
                if ($updateStmt->execute([$hashedPassword, $username, $staff['id']])) {
                    $message = "Password reset successful!";
                    $credentials = [
                        'name' => $staff['fName'] . ' ' . $staff['lName'],
                        'svcNo' => $staff['svcNo'],
                        'username' => $username,
                        'password' => $newPassword,
                        'email' => $staff['email']
                    ];
                } else {
                    $error = "Failed to update password in database";
                }
            }
            
        } catch (Exception $e) {
            $error = "Error: " . $e->getMessage();
        }
    }
    
    // HTML Output
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Staff Password Reset - ARMIS</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
        <style>
            body { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; }
            .reset-container { max-width: 600px; margin: 50px auto; }
            .card { box-shadow: 0 10px 30px rgba(0,0,0,0.2); border-radius: 15px; }
            .card-header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border-radius: 15px 15px 0 0 !important; }
            .credential-box { background: #f8f9fa; border: 2px solid #dee2e6; border-radius: 10px; padding: 20px; margin: 20px 0; }
            .credential-item { display: flex; justify-content: space-between; align-items: center; margin: 10px 0; }
            .credential-label { font-weight: bold; color: #495057; }
            .credential-value { font-family: 'Courier New', monospace; background: white; padding: 5px 10px; border-radius: 5px; }
            .copy-btn { margin-left: 10px; }
        </style>
    </head>
    <body>
        <div class="container reset-container">
            <div class="card">
                <div class="card-header">
                    <h4 class="mb-0"><i class="fas fa-key"></i> Staff Password Reset</h4>
                </div>
                <div class="card-body">
                    
                    <?php if ($error): ?>
                        <div class="alert alert-danger alert-dismissible fade show">
                            <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($credentials): ?>
                        <div class="alert alert-success">
                            <i class="fas fa-check-circle"></i> <?= htmlspecialchars($message) ?>
                        </div>
                        
                        <div class="credential-box">
                            <h5 class="text-center mb-3"><i class="fas fa-user-shield"></i> New Login Credentials</h5>
                            
                            <div class="credential-item">
                                <span class="credential-label">Name:</span>
                                <span class="credential-value"><?= htmlspecialchars($credentials['name']) ?></span>
                            </div>
                            
                            <div class="credential-item">
                                <span class="credential-label">Service Number:</span>
                                <span class="credential-value"><?= htmlspecialchars($credentials['svcNo']) ?></span>
                            </div>
                            
                            <div class="credential-item">
                                <span class="credential-label">Username:</span>
                                <span class="credential-value" id="username"><?= htmlspecialchars($credentials['username']) ?></span>
                                <button class="btn btn-sm btn-outline-primary copy-btn" onclick="copyToClipboard('username')">
                                    <i class="fas fa-copy"></i> Copy
                                </button>
                            </div>
                            
                            <div class="credential-item">
                                <span class="credential-label">Password:</span>
                                <span class="credential-value" id="password"><?= htmlspecialchars($credentials['password']) ?></span>
                                <button class="btn btn-sm btn-outline-primary copy-btn" onclick="copyToClipboard('password')">
                                    <i class="fas fa-copy"></i> Copy
                                </button>
                            </div>
                            
                            <?php if ($credentials['email']): ?>
                            <div class="credential-item">
                                <span class="credential-label">Email:</span>
                                <span class="credential-value"><?= htmlspecialchars($credentials['email']) ?></span>
                            </div>
                            <?php endif; ?>
                            
                            <hr>
                            
                            <div class="alert alert-warning mb-0">
                                <i class="fas fa-info-circle"></i> <strong>Important:</strong>
                                <ul class="mb-0 mt-2">
                                    <li>User will be required to change password on first login</li>
                                    <li>Account status has been set to 'Active'</li>
                                    <li>Please communicate these credentials securely</li>
                                </ul>
                            </div>
                        </div>
                        
                        <div class="text-center mt-3">
                            <a href="?" class="btn btn-secondary">Reset Another Password</a>
                            <a href="/Armis2/admin_branch/" class="btn btn-primary">Back to Dashboard</a>
                        </div>
                        
                    <?php else: ?>
                        
                        <form method="POST" action="">
                            <div class="mb-3">
                                <label for="svcNo" class="form-label">
                                    <i class="fas fa-id-badge"></i> Staff Service Number <span class="text-danger">*</span>
                                </label>
                                <input type="text" class="form-control" id="svcNo" name="svcNo" 
                                       placeholder="e.g., 108458" required>
                                <small class="form-text text-muted">Enter the service number of the staff member</small>
                            </div>
                            
                            <div class="mb-3">
                                <label for="custom_password" class="form-label">
                                    <i class="fas fa-lock"></i> Custom Password <span class="text-muted">(Optional)</span>
                                </label>
                                <input type="text" class="form-control" id="custom_password" name="custom_password" 
                                       placeholder="Leave empty to auto-generate">
                                <small class="form-text text-muted">If empty, a secure random password will be generated</small>
                            </div>
                            
                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-primary btn-lg">
                                    <i class="fas fa-key"></i> Reset Password
                                </button>
                                <a href="/Armis2/admin_branch/" class="btn btn-outline-secondary">
                                    <i class="fas fa-arrow-left"></i> Cancel
                                </a>
                            </div>
                        </form>
                        
                    <?php endif; ?>
                    
                </div>
            </div>
            
            <div class="text-center mt-3 text-white">
                <small><i class="fas fa-shield-alt"></i> Admin Tool - Restricted Access</small>
            </div>
        </div>
        
    <!-- Core JS (jQuery/Bootstrap) are loaded centrally in shared/footer.php. -->
    <script>
            function copyToClipboard(elementId) {
                const element = document.getElementById(elementId);
                const text = element.textContent;
                
                navigator.clipboard.writeText(text).then(() => {
                    const btn = element.nextElementSibling;
                    const originalHTML = btn.innerHTML;
                    btn.innerHTML = '<i class="fas fa-check"></i> Copied!';
                    btn.classList.remove('btn-outline-primary');
                    btn.classList.add('btn-success');
                    
                    setTimeout(() => {
                        btn.innerHTML = originalHTML;
                        btn.classList.remove('btn-success');
                        btn.classList.add('btn-outline-primary');
                    }, 2000);
                });
            }
        </script>
    </body>
    </html>
    <?php
}
