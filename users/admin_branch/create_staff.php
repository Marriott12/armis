<?php
require_once '../../staff_auth.php';
require_once '../db.php';
requireAdminBranch();

$user = getCurrentUser();

// Handle form submission
if ($_POST && isset($_POST['create_staff'])) {
    if (isset($_POST['csrf_token']) && CSRFToken::validate($_POST['csrf_token'])) {
        try {
            // Validate required fields
            $required_fields = ['svcNo', 'fname', 'lname', 'username', 'email', 'role'];
            $missing_fields = [];
            
            foreach ($required_fields as $field) {
                if (empty($_POST[$field])) {
                    $missing_fields[] = $field;
                }
            }
            
            if (!empty($missing_fields)) {
                setMessage('Missing required fields: ' . implode(', ', $missing_fields), 'error');
            } else {
                // Check if username or service number already exists
                $db = getDatabase();
                $stmt = $db->prepare("SELECT svcNo FROM staff WHERE svcNo = ? OR username = ?");
                $stmt->execute([$_POST['svcNo'], $_POST['username']]);
                
                if ($stmt->fetch()) {
                    setMessage('Service number or username already exists.', 'error');
                } else {
                    // Prepare staff data
                    $staffData = [
                        'svcNo' => $_POST['svcNo'],
                        'fname' => $_POST['fname'],
                        'lname' => $_POST['lname'],
                        'username' => $_POST['username'],
                        'email' => $_POST['email'],
                        'role' => $_POST['role'],
                        'rankID' => $_POST['rankID'] ?? null,
                        'gender' => $_POST['gender'] ?? null,
                        'DOB' => $_POST['DOB'] ?? null,
                        'province' => $_POST['province'] ?? null,
                        'tel' => $_POST['tel'] ?? null,
                        'category' => $_POST['category'] ?? null,
                        'unitID' => $_POST['unitID'] ?? null,
                        'svcStatus' => 'Serving'
                    ];
                    
                    // Create staff member
                    $result = createStaff($staffData, $user['svcNo']);
                    
                    if ($result['success']) {
                        setMessage("Staff member created successfully! Temporary password: {$result['tempPassword']} (User must reset on first login)", 'success');
                        // Clear form data
                        $_POST = [];
                    } else {
                        setMessage('Error creating staff member: ' . $result['error'], 'error');
                    }
                }
            }
        } catch (Exception $e) {
            error_log("Create staff error: " . $e->getMessage());
            setMessage('An unexpected error occurred. Please try again.', 'error');
        }
    } else {
        setMessage('Security token validation failed. Please try again.', 'error');
    }
}

// Check if config and handler files exist
if (file_exists('partials/create_staff_config.php')) {
    require_once 'partials/create_staff_config.php';
}
?>
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ARMIS - Create Staff Member</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../css/armis_custom.css">
    <style>
        :root {
            --primary: #355E3B;
            --yellow: #f1c40f;
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg armis-navbar">
        <div class="container">
            <a class="navbar-brand armis-brand" href="../../">ARMIS</a>
            <div class="navbar-nav ms-auto">
                <a class="nav-link" href="../admin.php">Dashboard</a>
                <a class="nav-link" href="../../logout.php">Logout</a>
            </div>
        </div>
    </nav>

<div class="container my-5" style="background: #f8f9fa; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.05); padding: 30px;">
    <div class="mb-3">
        <a href="../admin_branch.php" class="btn btn-outline-secondary" aria-label="Back to Admin Branch">
            <i class="fa fa-arrow-left"></i> Back to Admin Branch
        </a>
    </div>
    <div class="card shadow-sm">
        <div class="card-header text-white" style="background: var(--primary);">
            <h4 class="mb-0"><i class="fa fa-user-plus"></i> Register New Staff Member</h4>
        </div>
        <div class="card-body">
            <?php displayMessages(); ?>
            <?php require 'partials/create_staff_tabs.php'; ?>
            <form id="createStaffForm" method="post" action="<?=htmlspecialchars($_SERVER["PHP_SELF"]);?>" autocomplete="off">
                <?php echo CSRFToken::getField(); ?>
                <input type="hidden" name="create_staff" value="1">
                <div class="tab-content" id="staffTabContent">
                    <?php require 'partials/tab_personal.php'; ?>
                    <?php require 'partials/tab_service.php'; ?>
                    <?php require 'partials/tab_family.php'; ?>
                    <?php require 'partials/tab_academic.php'; ?>
                    <!--<?php //require 'partials/tab_honours.php'; ?>-->
                    <?php require 'partials/tab_id.php'; ?>
                    <?php require 'partials/tab_residence.php'; ?>
                    <?php require 'partials/tab_language.php'; ?>
                </div>
                <div class="text-end mt-4">
                    <button type="submit" name="create_staff" class="btn btn-success px-5 py-2"><i class="fa fa-save"></i> Create Staff Member</button>
                </div>
            </form>
        </div>
    </div>
</div>
<button onclick="scrollToTop()" id="scrollBtn" class="btn btn-secondary" style="display:none; position:fixed; bottom:20px; right:20px; z-index:999;">Top</button>
<?php require_once $abs_us_root . $us_url_root . 'users/includes/html_footer.php'; ?>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<?php require 'partials/create_staff_js.php'; ?>