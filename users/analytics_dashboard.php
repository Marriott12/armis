<?php
/**
 * Enhanced Analytics Dashboard for ARMIS Users
 * Provides comprehensive personal analytics and insights
 */

session_start();
require_once dirname(__DIR__) . '/shared/database_connection.php';
require_once 'profile_manager.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit();
}

$profileManager = new UserProfileManager($_SESSION['user_id']);
$personalInfo = $profileManager->getUserProfile();
$contactInfo = $profileManager->getContactInfo();
$familyMembers = $profileManager->getFamilyMembers();
$addresses = $profileManager->getAddresses();

// Get user statistics
function getUserStats($pdo, $userId) {
    // Initialize stats with default values
    $stats = [
        'profile_completion' => 0,
        'recent_activities' => 'N/A',
        'training_records' => 'N/A',
        'equipment_issued' => 'N/A'
    ];
    
    try {
        // Profile completion percentage
        $stmt = $pdo->prepare("
            SELECT 
                (CASE WHEN first_name IS NOT NULL AND first_name != '' THEN 10 ELSE 0 END +
                 CASE WHEN last_name IS NOT NULL AND last_name != '' THEN 10 ELSE 0 END +
                 CASE WHEN dob IS NOT NULL THEN 10 ELSE 0 END +
                 CASE WHEN gender IS NOT NULL AND gender != '' THEN 10 ELSE 0 END +
                 CASE WHEN phone IS NOT NULL AND phone != '' THEN 10 ELSE 0 END +
                 CASE WHEN email IS NOT NULL AND email != '' THEN 10 ELSE 0 END +
                 CASE WHEN combatSize IS NOT NULL AND combatSize != '' THEN 5 ELSE 0 END +
                 CASE WHEN bsize IS NOT NULL AND bsize != '' THEN 5 ELSE 0 END +
                 CASE WHEN ssize IS NOT NULL AND ssize != '' THEN 5 ELSE 0 END +
                 CASE WHEN hdress IS NOT NULL AND hdress != '' THEN 5 ELSE 0 END +
                 CASE WHEN EXISTS(SELECT 1 FROM staff_addresses WHERE staff_id = ?) THEN 15 ELSE 0 END +
                 CASE WHEN EXISTS(SELECT 1 FROM staff_contact_info WHERE staff_id = ?) THEN 10 ELSE 0 END +
                 CASE WHEN EXISTS(SELECT 1 FROM staff_family_members WHERE staff_id = ?) THEN 5 ELSE 0 END
                ) as completion_percentage
            FROM staff WHERE id = ?
        ");
        $stmt->execute([$userId, $userId, $userId, $userId]);
        $result = $stmt->fetch(PDO::FETCH_OBJ);
        $stats['profile_completion'] = $result ? $result->completion_percentage : 0;
        
        // Recent activity count (if activity log exists)
        try {
            $stmt = $pdo->prepare("SELECT COUNT(*) as activity_count FROM staff_activity_log WHERE staff_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)");
            $stmt->execute([$userId]);
            $result = $stmt->fetch(PDO::FETCH_OBJ);
            $stats['recent_activities'] = $result ? $result->activity_count : 0;
        } catch (PDOException $e) {
            $stats['recent_activities'] = 'N/A';
        }
        
        // Training records count (if training table exists)
        try {
            $stmt = $pdo->prepare("SELECT COUNT(*) as training_count FROM staff_training WHERE staff_id = ?");
            $stmt->execute([$userId]);
            $result = $stmt->fetch(PDO::FETCH_OBJ);
            $stats['training_records'] = $result ? $result->training_count : 0;
        } catch (PDOException $e) {
            $stats['training_records'] = 'N/A';
        }
        
        // Equipment issued count (if equipment table exists)
        try {
            $stmt = $pdo->prepare("SELECT COUNT(*) as equipment_count FROM staff_equipment WHERE staff_id = ? AND status = 'issued'");
            $stmt->execute([$userId]);
            $result = $stmt->fetch(PDO::FETCH_OBJ);
            $stats['equipment_issued'] = $result ? $result->equipment_count : 0;
        } catch (PDOException $e) {
            $stats['equipment_issued'] = 'N/A';
        }
        
    } catch (PDOException $e) {
        error_log("Error getting user stats: " . $e->getMessage());
    }
    
    return $stats;
}

$pdo = getDbConnection();
$userStats = getUserStats($pdo, $_SESSION['user_id']);

// Make sure the profile_completion value always exists with a default value
if (!isset($userStats['profile_completion'])) {
    $userStats['profile_completion'] = 0;
}

// Set page title for header inclusion
$pageTitle = "Personal Analytics Dashboard";
$moduleName = "User Profile";
$moduleIcon = "chart-line";
$currentPage = "analytics";

// Include shared header
require_once dirname(__DIR__) . '/shared/header.php';

// Sidebar links for this section
$sidebarLinks = [
    ['title' => 'Dashboard', 'url' => '/Armis2/users/index.php', 'icon' => 'tachometer-alt', 'page' => 'dashboard'],
    ['title' => 'My Profile', 'url' => '/Armis2/users/index.php', 'icon' => 'user', 'page' => 'profile'],
    ['title' => 'Analytics', 'url' => '/Armis2/users/analytics_dashboard.php', 'icon' => 'chart-line', 'page' => 'analytics'],
    ['title' => 'Settings', 'url' => '/Armis2/users/settings.php', 'icon' => 'cogs', 'page' => 'settings']
];

// Include shared sidebar
require_once dirname(__DIR__) . '/shared/sidebar.php';
?>

<!-- Custom CSS specific to analytics dashboard -->
<style>
    .stat-card {
        background: white;
        border-radius: 15px;
        padding: 1.5rem;
        margin-bottom: 1.5rem;
        box-shadow: 0 8px 25px rgba(0,0,0,0.1);
        transition: transform 0.3s ease, box-shadow 0.3s ease;
    }
    
    .stat-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 15px 35px rgba(0,0,0,0.15);
    }
    
    .stat-icon {
        font-size: 2.5rem;
        margin-bottom: 1rem;
    }
    
    .progress-ring {
        width: 120px;
        height: 120px;
        margin: 0 auto;
    }
    
    .chart-container {
        background: white;
        border-radius: 15px;
        padding: 2rem;
        margin-bottom: 2rem;
        box-shadow: 0 8px 25px rgba(0,0,0,0.1);
    }
    
    .info-section {
        background: white;
        border-radius: 15px;
        padding: 1.5rem;
        margin-bottom: 1.5rem;
        box-shadow: 0 8px 25px rgba(0,0,0,0.1);
    }
    
    /* Enhanced styling for charts and tabs */
    .chart-wrapper {
        position: relative;
        margin: 0 auto;
        max-width: 100%;
    }
    
    .completion-percentage {
        font-size: 2.5rem;
        font-weight: 700;
        color: #28a745;
        margin-bottom: 0;
    }
    
    .nav-tabs .nav-link {
        color: #495057;
        font-weight: 500;
        padding: 0.75rem 1.25rem;
        border-radius: 0;
        transition: all 0.2s ease;
    }
    
    .nav-tabs .nav-link.active {
        color: #0d6efd;
        background-color: #fff;
        border-color: #dee2e6 #dee2e6 #fff;
        border-bottom: 3px solid #0d6efd;
    }
    
    .nav-tabs .nav-link:hover:not(.active) {
        background-color: #f8f9fa;
        border-color: transparent;
    }
    
    .nav-fill .nav-item .nav-link {
        text-align: center;
    }
    
    /* Vertical tabs styling */
    .vertical-tabs {
        display: flex;
        border: 1px solid #dee2e6;
        border-radius: 0.25rem;
        overflow: hidden;
        box-shadow: 0 5px 15px rgba(0,0,0,0.05);
    }
    
    .vertical-tabs .nav-tabs {
        flex-direction: column;
        border-right: 1px solid #dee2e6;
        border-bottom: 0;
        min-width: 200px;
        background-color: #f8f9fa;
    }
    
    .vertical-tabs .nav-tabs .nav-link {
        border-radius: 0;
        border: none;
        border-bottom: 1px solid #dee2e6;
        text-align: left;
        padding: 1rem 1.5rem;
        transition: all 0.2s ease;
        color: #495057;
        font-weight: 500;
    }
    
    .vertical-tabs .nav-tabs .nav-link:last-child {
        border-bottom: none;
    }
    
    .vertical-tabs .nav-tabs .nav-link.active {
        background-color: #fff;
        color: #0d6efd;
        border-right: none;
        position: relative;
    }
    
    .vertical-tabs .nav-tabs .nav-link.active::after {
        content: '';
        position: absolute;
        left: 0;
        top: 0;
        height: 100%;
        width: 4px;
        background-color: #0d6efd;
    }
    
    .vertical-tabs .nav-tabs .nav-link:hover:not(.active) {
        background-color: #e9ecef;
    }
    
    .vertical-tabs .tab-content {
        flex: 1;
        padding: 1.5rem;
        background-color: #fff;
    }
    
    /* Card view styling */
    .profile-card {
        border-radius: 15px;
        overflow: hidden;
        box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        transition: transform 0.3s ease, box-shadow 0.3s ease;
        height: 100%;
    }
    
    .profile-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 10px 25px rgba(0,0,0,0.15);
    }
    
    .profile-card .card-header {
        background-color: var(--bs-primary);
        color: white;
        padding: 1rem;
    }
    
    .profile-card .card-body {
        padding: 1.25rem;
    }
    
    @media (max-width: 767.98px) {
        .vertical-tabs {
            flex-direction: column;
        }
        
        .vertical-tabs .nav-tabs {
            flex-direction: row;
            border-right: 0;
            border-bottom: 1px solid #dee2e6;
        }
        
        .vertical-tabs .nav-tabs .nav-link {
            border-radius: 0.25rem 0.25rem 0 0;
            border-bottom: none;
            margin-bottom: -1px;
            margin-right: 0;
        }
        
        .vertical-tabs .nav-tabs .nav-link.active {
            border-color: #dee2e6 #dee2e6 transparent #dee2e6;
        }
        
        .vertical-tabs .tab-content {
            padding: 1rem 0;
        }
    }
</style>

<!-- Main Content Wrapper - This div should come right after the sidebar inclusion -->
<div class="content-wrapper" style="margin-left: 260px; transition: margin-left 0.3s ease;">
    <!-- Main Content Container -->
    <div class="container-fluid mt-4">
        <div class="container">
            <div class="row mb-4">
                <div class="col-md-8">
                    <h2><i class="fas fa-chart-line me-2"></i>Personal Analytics Dashboard</h2>
                    <p class="text-muted">Comprehensive overview of your military service data</p>
                </div>
                <div class="col-md-4 text-end">
                    <a href="personal.php" class="btn btn-primary">
                        <i class="fas fa-user-edit me-2"></i>Update Profile
                    </a>
                </div>
            </div>

        <!-- Statistics Overview -->
        <div class="row">
            <div class="col-lg-3 col-md-6">
                <div class="stat-card text-center">
                    <div class="stat-icon text-success">
                        <i class="fas fa-user-check"></i>
                    </div>
                    <h3><?php echo isset($userStats['profile_completion']) ? $userStats['profile_completion'] : 0; ?>%</h3>
                    <p class="text-muted mb-0">Profile Completion</p>
                    <div class="progress mt-2">
                        <div class="progress-bar bg-success" style="width: <?php echo isset($userStats['profile_completion']) ? $userStats['profile_completion'] : 0; ?>%"></div>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-3 col-md-6">
                <div class="stat-card text-center">
                    <div class="stat-icon text-primary">
                        <i class="fas fa-clock"></i>
                    </div>
                    <h3><?php echo isset($userStats['recent_activities']) ? $userStats['recent_activities'] : 'N/A'; ?></h3>
                    <p class="text-muted mb-0">Recent Activities</p>
                    <small class="text-muted">Last 30 days</small>
                </div>
            </div>
            
            <div class="col-lg-3 col-md-6">
                <div class="stat-card text-center">
                    <div class="stat-icon text-warning">
                        <i class="fas fa-graduation-cap"></i>
                    </div>
                    <h3><?php echo isset($userStats['training_records']) ? $userStats['training_records'] : 'N/A'; ?></h3>
                    <p class="text-muted mb-0">Training Records</p>
                    <small class="text-muted">Completed courses</small>
                </div>
            </div>
            
            <div class="col-lg-3 col-md-6">
                <div class="stat-card text-center">
                    <div class="stat-icon text-info">
                        <i class="fas fa-tools"></i>
                    </div>
                    <h3><?php echo isset($userStats['equipment_issued']) ? $userStats['equipment_issued'] : 'N/A'; ?></h3>
                    <p class="text-muted mb-0">Equipment Issued</p>
                    <small class="text-muted">Currently assigned</small>
                </div>
            </div>
        </div>

        <!-- Detailed Information Tabs with View Toggle -->
        <div class="row mt-4">
            <div class="col-12">
                <div class="info-section">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5 class="section-title mb-0">Detailed Profile Information</h5>
                        <div class="btn-group" role="group" aria-label="Tabs View Toggle">
                            <button type="button" class="btn btn-sm btn-outline-primary active" id="tabsHorizontalBtn">
                                <i class="fas fa-grip-horizontal me-1"></i> Horizontal
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-primary" id="tabsVerticalBtn">
                                <i class="fas fa-grip-vertical me-1"></i> Vertical
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-primary" id="cardsViewBtn">
                                <i class="fas fa-th-large me-1"></i> Cards
                            </button>
                        </div>
                    </div>
                    
                    <!-- Horizontal Tabs View (default) -->
                    <div id="horizontalTabsView">
                        <ul class="nav nav-tabs nav-fill" id="analyticsTab" role="tablist">
                            <li class="nav-item" role="presentation">
                                <button class="nav-link active" id="personal-tab" data-bs-toggle="tab" data-bs-target="#personal" type="button">
                                    <i class="fas fa-user me-2"></i>Personal Details
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="contact-tab" data-bs-toggle="tab" data-bs-target="#contact" type="button">
                                    <i class="fas fa-address-book me-2"></i>Contact Information
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="sizing-tab" data-bs-toggle="tab" data-bs-target="#sizing" type="button">
                                    <i class="fas fa-ruler me-2"></i>Military Sizing
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="family-tab" data-bs-toggle="tab" data-bs-target="#family" type="button">
                                    <i class="fas fa-users me-2"></i>Family Members
                                </button>
                            </li>
                        </ul>

                        <div class="tab-content" id="analyticsTabContent">
                            <!-- Personal Details Tab -->
                            <div class="tab-pane fade show active" id="personal" role="tabpanel">
                                <div class="row mt-3">
                                    <div class="col-md-6">
                                        <h5 class="section-title">Basic Information</h5>
                                        <table class="table table-borderless">
                                            <tr>
                                                <td><strong>Full Name:</strong></td>
                                                <td><?php echo htmlspecialchars($personalInfo->first_name ?? '') . ' ' . htmlspecialchars($personalInfo->last_name ?? ''); ?></td>
                                            </tr>
                                            <tr>
                                                <td><strong>Date of Birth:</strong></td>
                                                <td><?php echo isset($personalInfo->dob) && $personalInfo->dob ? date('d M Y', strtotime($personalInfo->dob)) : 'Not specified'; ?></td>
                                            </tr>
                                            <tr>
                                                <td><strong>Gender:</strong></td>
                                                <td><?php echo htmlspecialchars($personalInfo->gender ?? 'Not specified'); ?></td>
                                            </tr>
                                            <tr>
                                                <td><strong>Marital Status:</strong></td>
                                                <td><?php echo htmlspecialchars($personalInfo->marital_status ?? 'Not specified'); ?></td>
                                            </tr>
                                        </table>
                                    </div>
                                    <div class="col-md-6">
                                        <h5 class="section-title">Professional Information</h5>
                                        <table class="table table-borderless">
                                            <tr>
                                                <td><strong>Email:</strong></td>
                                                <td><?php echo htmlspecialchars($personalInfo->email ?? 'Not specified'); ?></td>
                                            </tr>
                                            <tr>
                                                <td><strong>Phone:</strong></td>
                                                <td><?php echo htmlspecialchars($personalInfo->phone ?? 'Not specified'); ?></td>
                                            </tr>
                                            <tr>
                                                <td><strong>Employment Date:</strong></td>
                                                <td><?php echo isset($personalInfo->employment_date) && $personalInfo->employment_date ? date('d M Y', strtotime($personalInfo->employment_date)) : 'Not specified'; ?></td>
                                            </tr>
                                        </table>
                                    </div>
                                </div>
                            </div>

                            <!-- Contact Information Tab -->
                            <div class="tab-pane fade" id="contact" role="tabpanel">
                                <div class="mt-3">
                                    <h5 class="section-title">Contact Details</h5>
                                    <?php if (!empty($contactInfo)): ?>
                                        <div class="row">
                                            <?php foreach ($contactInfo as $contact): ?>
                                                <div class="col-md-6 mb-3">
                                                    <div class="card">
                                                        <div class="card-body">
                                                            <h6 class="card-title">
                                                                <?php echo ucfirst($contact->contact_type); ?>
                                                                <?php if ($contact->is_primary): ?>
                                                                    <span class="badge bg-primary ms-2">Primary</span>
                                                                <?php endif; ?>
                                                            </h6>
                                                            <p class="card-text">
                                                                <strong>Value:</strong> <?php echo htmlspecialchars($contact->contact_value); ?><br>
                                                                <?php if ($contact->contact_name): ?>
                                                                    <strong>Name:</strong> <?php echo htmlspecialchars($contact->contact_name); ?><br>
                                                                <?php endif; ?>
                                                                <?php if ($contact->relationship): ?>
                                                                    <strong>Relationship:</strong> <?php echo htmlspecialchars($contact->relationship); ?>
                                                                <?php endif; ?>
                                                            </p>
                                                        </div>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php else: ?>
                                        <div class="alert alert-info">
                                            <i class="fas fa-info-circle me-2"></i>No contact information available. 
                                            <a href="personal.php">Add contact details</a>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- Military Sizing Tab -->
                            <div class="tab-pane fade" id="sizing" role="tabpanel">
                                <div class="mt-3">
                                    <h5 class="section-title">Military Equipment Sizing</h5>
                                    <div class="row">
                                        <div class="col-md-6">
                                            <table class="table table-striped">
                                                <tr>
                                                    <td><i class="fas fa-shoe-prints me-2 text-primary"></i><strong>Combat Boot Size:</strong></td>
                                                    <td><?php echo htmlspecialchars($personalInfo->combatSize ?? 'Not specified'); ?></td>
                                                </tr>
                                                <tr>
                                                    <td><i class="fas fa-shoe-prints me-2 text-success"></i><strong>Boot Size:</strong></td>
                                                    <td><?php echo htmlspecialchars($personalInfo->bsize ?? 'Not specified'); ?></td>
                                                </tr>
                                                <tr>
                                                    <td><i class="fas fa-shoe-prints me-2 text-warning"></i><strong>Staff Shoe Size:</strong></td>
                                                    <td><?php echo htmlspecialchars($personalInfo->ssize ?? 'Not specified'); ?></td>
                                                </tr>
                                                <tr>
                                                    <td><i class="fas fa-hard-hat me-2 text-info"></i><strong>Head Dress Size:</strong></td>
                                                    <td><?php echo htmlspecialchars($personalInfo->hdress ?? 'Not specified'); ?></td>
                                                </tr>
                                            </table>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="alert alert-success">
                                                <h6><i class="fas fa-check-circle me-2"></i>Sizing Information Complete</h6>
                                                <p class="mb-0">All military sizing information helps ensure proper equipment allocation and inventory management.</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Family Members Tab -->
                            <div class="tab-pane fade" id="family" role="tabpanel">
                                <div class="mt-3">
                                    <h5 class="section-title">Family Information</h5>
                                    <?php if (!empty($familyMembers)): ?>
                                        <div class="row">
                                            <?php foreach ($familyMembers as $member): ?>
                                                <div class="col-md-6 mb-3">
                                                    <div class="card">
                                                        <div class="card-body">
                                                            <h6 class="card-title">
                                                                <i class="fas fa-user me-2"></i>
                                                                <?php echo htmlspecialchars($member->name); ?>
                                                            </h6>
                                                            <p class="card-text">
                                                                <strong>Relationship:</strong> <?php echo htmlspecialchars($member->relationship); ?><br>
                                                                <strong>Date of Birth:</strong> <?php echo isset($member->date_of_birth) && $member->date_of_birth ? date('d M Y', strtotime($member->date_of_birth)) : 'Not specified'; ?>
                                                            </p>
                                                        </div>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php else: ?>
                                        <div class="alert alert-info">
                                            <i class="fas fa-info-circle me-2"></i>No family members recorded. 
                                            <a href="family.php">Add family information</a>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Vertical Tabs View (hidden by default) -->
                    <div id="verticalTabsView" style="display: none;">
                        <div class="vertical-tabs">
                            <ul class="nav nav-tabs flex-column nav-pills" id="verticalAnalyticsTab" role="tablist">
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link active py-3" id="vertical-personal-tab" data-bs-toggle="tab" data-bs-target="#vertical-personal" type="button">
                                        <i class="fas fa-user me-2"></i>Personal Details
                                    </button>
                                </li>
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link py-3" id="vertical-contact-tab" data-bs-toggle="tab" data-bs-target="#vertical-contact" type="button">
                                        <i class="fas fa-address-book me-2"></i>Contact Information
                                    </button>
                                </li>
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link py-3" id="vertical-sizing-tab" data-bs-toggle="tab" data-bs-target="#vertical-sizing" type="button">
                                        <i class="fas fa-ruler me-2"></i>Military Sizing
                                    </button>
                                </li>
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link py-3" id="vertical-family-tab" data-bs-toggle="tab" data-bs-target="#vertical-family" type="button">
                                        <i class="fas fa-users me-2"></i>Family Members
                                    </button>
                                </li>
                            </ul>

                            <div class="tab-content" id="verticalAnalyticsTabContent">
                                <!-- Vertical Personal Details Tab -->
                                <div class="tab-pane fade show active" id="vertical-personal" role="tabpanel">
                                    <div class="row mt-3">
                                        <div class="col-md-6">
                                            <h5 class="section-title">Basic Information</h5>
                                            <table class="table table-borderless">
                                                <tr>
                                                    <td><strong>Full Name:</strong></td>
                                                    <td><?php echo htmlspecialchars($personalInfo->first_name ?? '') . ' ' . htmlspecialchars($personalInfo->last_name ?? ''); ?></td>
                                                </tr>
                                                <tr>
                                                    <td><strong>Date of Birth:</strong></td>
                                                    <td><?php echo isset($personalInfo->dob) && $personalInfo->dob ? date('d M Y', strtotime($personalInfo->dob)) : 'Not specified'; ?></td>
                                                </tr>
                                                <tr>
                                                    <td><strong>Gender:</strong></td>
                                                    <td><?php echo htmlspecialchars($personalInfo->gender ?? 'Not specified'); ?></td>
                                                </tr>
                                                <tr>
                                                    <td><strong>Marital Status:</strong></td>
                                                    <td><?php echo htmlspecialchars($personalInfo->marital ?? 'Not specified'); ?></td>
                                                </tr>
                                            </table>
                                        </div>
                                        <div class="col-md-6">
                                            <h5 class="section-title">Professional Information</h5>
                                            <table class="table table-borderless">
                                                <tr>
                                                    <td><strong>Email:</strong></td>
                                                    <td><?php echo htmlspecialchars($personalInfo->email ?? 'Not specified'); ?></td>
                                                </tr>
                                                <tr>
                                                    <td><strong>Phone:</strong></td>
                                                    <td><?php echo htmlspecialchars($personalInfo->tel ?? 'Not specified'); ?></td>
                                                </tr>
                                                <tr>
                                                    <td><strong>Employment Date:</strong></td>
                                                    <td><?php echo isset($personalInfo->attestDate) && $personalInfo->attestDate ? date('d M Y', strtotime($personalInfo->employment_date)) : 'Not specified'; ?></td>
                                                </tr>
                                            </table>
                                        </div>
                                    </div>
                                </div>

                                <!-- Vertical Contact Information Tab -->
                                <div class="tab-pane fade" id="vertical-contact" role="tabpanel">
                                    <div class="mt-3">
                                        <h5 class="section-title">Contact Details</h5>
                                        <?php if (!empty($contactInfo)): ?>
                                            <div class="row">
                                                <?php foreach ($contactInfo as $contact): ?>
                                                    <div class="col-md-6 mb-3">
                                                        <div class="card">
                                                            <div class="card-body">
                                                                <h6 class="card-title">
                                                                    <?php echo ucfirst($contact->contact_type); ?>
                                                                    <?php if ($contact->is_primary): ?>
                                                                        <span class="badge bg-primary ms-2">Primary</span>
                                                                    <?php endif; ?>
                                                                </h6>
                                                                <p class="card-text">
                                                                    <strong>Value:</strong> <?php echo htmlspecialchars($contact->contact_value); ?><br>
                                                                    <?php if ($contact->contact_name): ?>
                                                                        <strong>Name:</strong> <?php echo htmlspecialchars($contact->contact_name); ?><br>
                                                                    <?php endif; ?>
                                                                    <?php if ($contact->relationship): ?>
                                                                        <strong>Relationship:</strong> <?php echo htmlspecialchars($contact->relationship); ?>
                                                                    <?php endif; ?>
                                                                </p>
                                                            </div>
                                                        </div>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php else: ?>
                                            <div class="alert alert-info">
                                                <i class="fas fa-info-circle me-2"></i>No contact information available. 
                                                <a href="personal.php">Add contact details</a>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <!-- Vertical Military Sizing Tab -->
                                <div class="tab-pane fade" id="vertical-sizing" role="tabpanel">
                                    <div class="mt-3">
                                        <h5 class="section-title">Military Equipment Sizing</h5>
                                        <div class="row">
                                            <div class="col-md-6">
                                                <table class="table table-striped">
                                                    <tr>
                                                        <td><i class="fas fa-shoe-prints me-2 text-primary"></i><strong>Combat Boot Size:</strong></td>
                                                        <td><?php echo htmlspecialchars($personalInfo->combatSize ?? 'Not specified'); ?></td>
                                                    </tr>
                                                    <tr>
                                                        <td><i class="fas fa-shoe-prints me-2 text-success"></i><strong>Boot Size:</strong></td>
                                                        <td><?php echo htmlspecialchars($personalInfo->bsize ?? 'Not specified'); ?></td>
                                                    </tr>
                                                    <tr>
                                                        <td><i class="fas fa-shoe-prints me-2 text-warning"></i><strong>Staff Shoe Size:</strong></td>
                                                        <td><?php echo htmlspecialchars($personalInfo->ssize ?? 'Not specified'); ?></td>
                                                    </tr>
                                                    <tr>
                                                        <td><i class="fas fa-hard-hat me-2 text-info"></i><strong>Head Dress Size:</strong></td>
                                                        <td><?php echo htmlspecialchars($personalInfo->hdress ?? 'Not specified'); ?></td>
                                                    </tr>
                                                </table>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="alert alert-success">
                                                    <h6><i class="fas fa-check-circle me-2"></i>Sizing Information Complete</h6>
                                                    <p class="mb-0">All military sizing information helps ensure proper equipment allocation and inventory management.</p>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Vertical Family Members Tab -->
                                <div class="tab-pane fade" id="vertical-family" role="tabpanel">
                                    <div class="mt-3">
                                        <h5 class="section-title">Family Information</h5>
                                        <?php if (!empty($familyMembers)): ?>
                                            <div class="row">
                                                <?php foreach ($familyMembers as $member): ?>
                                                    <div class="col-md-6 mb-3">
                                                        <div class="card">
                                                            <div class="card-body">
                                                                <h6 class="card-title">
                                                                    <i class="fas fa-user me-2"></i>
                                                                    <?php echo htmlspecialchars($member->name); ?>
                                                                </h6>
                                                                <p class="card-text">
                                                                    <strong>Relationship:</strong> <?php echo htmlspecialchars($member->relationship); ?><br>
                                                                    <strong>Date of Birth:</strong> <?php echo isset($member->date_of_birth) && $member->date_of_birth ? date('d M Y', strtotime($member->date_of_birth)) : 'Not specified'; ?>
                                                                </p>
                                                            </div>
                                                        </div>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php else: ?>
                                            <div class="alert alert-info">
                                                <i class="fas fa-info-circle me-2"></i>No family members recorded. 
                                                <a href="personal.php">Add family information</a>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Cards View (hidden by default) -->
                    <div id="cardsView" style="display: none;">
                        <div class="row mt-3">
                            <!-- Personal Details Card -->
                            <div class="col-lg-6 col-md-6 mb-4">
                                <div class="profile-card">
                                    <div class="card-header bg-primary">
                                        <h5 class="mb-0 text-white"><i class="fas fa-user me-2"></i>Personal Details</h5>
                                    </div>
                                    <div class="card-body">
                                        <div class="row">
                                            <div class="col-sm-6">
                                                <p><strong>Name:</strong> <?php echo htmlspecialchars($personalInfo->first_name ?? '') . ' ' . htmlspecialchars($personalInfo->last_name ?? ''); ?></p>
                                                <p><strong>DOB:</strong> <?php echo isset($personalInfo->dob) && $personalInfo->dob ? date('d M Y', strtotime($personalInfo->dob)) : 'Not specified'; ?></p>
                                            </div>
                                            <div class="col-sm-6">
                                                <p><strong>Gender:</strong> <?php echo htmlspecialchars($personalInfo->gender ?? 'Not specified'); ?></p>
                                                <p><strong>Marital Status:</strong> <?php echo htmlspecialchars($personalInfo->marital_status ?? 'Not specified'); ?></p>
                                            </div>
                                        </div>
                                        <hr>
                                        <div class="row">
                                            <div class="col-sm-6">
                                                <p><strong>Email:</strong> <?php echo htmlspecialchars($personalInfo->email ?? 'Not specified'); ?></p>
                                            </div>
                                            <div class="col-sm-6">
                                                <p><strong>Phone:</strong> <?php echo htmlspecialchars($personalInfo->phone ?? 'Not specified'); ?></p>
                                            </div>
                                        </div>
                                        <p><strong>Employment Date:</strong> <?php echo isset($personalInfo->employment_date) && $personalInfo->employment_date ? date('d M Y', strtotime($personalInfo->employment_date)) : 'Not specified'; ?></p>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Contact Information Card -->
                            <div class="col-lg-6 col-md-6 mb-4">
                                <div class="profile-card">
                                    <div class="card-header bg-info">
                                        <h5 class="mb-0 text-white"><i class="fas fa-address-book me-2"></i>Contact Information</h5>
                                    </div>
                                    <div class="card-body">
                                        <?php if (!empty($contactInfo)): ?>
                                            <div class="row">
                                                <?php 
                                                $count = 0;
                                                foreach ($contactInfo as $contact): 
                                                    if ($count < 4): // Limit to 4 contacts for card view
                                                ?>
                                                    <div class="col-md-6 mb-3">
                                                        <div class="card">
                                                            <div class="card-body p-3">
                                                                <h6 class="card-title">
                                                                    <?php echo ucfirst($contact->contact_type); ?>
                                                                    <?php if ($contact->is_primary): ?>
                                                                        <span class="badge bg-primary ms-2">Primary</span>
                                                                    <?php endif; ?>
                                                                </h6>
                                                                <p class="card-text small">
                                                                    <?php echo htmlspecialchars($contact->contact_value); ?>
                                                                </p>
                                                            </div>
                                                        </div>
                                                    </div>
                                                <?php 
                                                    $count++;
                                                    endif;
                                                endforeach; 
                                                
                                                if (count($contactInfo) > 4):
                                                ?>
                                                <div class="col-12 text-center mt-2">
                                                    <a href="#" class="btn btn-sm btn-outline-info" id="showAllContacts">
                                                        View all <?php echo count($contactInfo); ?> contacts
                                                    </a>
                                                </div>
                                                <?php endif; ?>
                                            </div>
                                        <?php else: ?>
                                            <div class="alert alert-info">
                                                <i class="fas fa-info-circle me-2"></i>No contact information available. 
                                                <a href="personal.php">Add contact details</a>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Military Sizing Card -->
                            <div class="col-lg-6 col-md-6 mb-4">
                                <div class="profile-card">
                                    <div class="card-header bg-success">
                                        <h5 class="mb-0 text-white"><i class="fas fa-ruler me-2"></i>Military Sizing</h5>
                                    </div>
                                    <div class="card-body">
                                        <div class="row">
                                            <div class="col-md-6">
                                                <ul class="list-group list-group-flush">
                                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                                        <span><i class="fas fa-shoe-prints me-2 text-primary"></i>Combat Boot:</span>
                                                        <strong><?php echo htmlspecialchars($personalInfo->combatSize ?? 'Not specified'); ?></strong>
                                                    </li>
                                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                                        <span><i class="fas fa-shoe-prints me-2 text-success"></i>Boot Size:</span>
                                                        <strong><?php echo htmlspecialchars($personalInfo->bsize ?? 'Not specified'); ?></strong>
                                                    </li>
                                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                                        <span><i class="fas fa-shoe-prints me-2 text-warning"></i>Staff Shoe:</span>
                                                        <strong><?php echo htmlspecialchars($personalInfo->ssize ?? 'Not specified'); ?></strong>
                                                    </li>
                                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                                        <span><i class="fas fa-hard-hat me-2 text-info"></i>Head Dress:</span>
                                                        <strong><?php echo htmlspecialchars($personalInfo->hdress ?? 'Not specified'); ?></strong>
                                                    </li>
                                                </ul>
                                            </div>
                                            <div class="col-md-6 d-flex align-items-center">
                                                <div class="text-center w-100">
                                                    <i class="fas fa-check-circle text-success fa-3x mb-3"></i>
                                                    <p class="mb-0">Sizing Information Complete</p>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Family Members Card -->
                            <div class="col-lg-6 col-md-6 mb-4">
                                <div class="profile-card">
                                    <div class="card-header bg-warning">
                                        <h5 class="mb-0 text-dark"><i class="fas fa-users me-2"></i>Family Members</h5>
                                    </div>
                                    <div class="card-body">
                                        <?php if (!empty($familyMembers)): ?>
                                            <div class="row">
                                                <?php 
                                                $count = 0;
                                                foreach ($familyMembers as $member): 
                                                    if ($count < 4): // Limit to 4 family members for card view
                                                ?>
                                                    <div class="col-md-6 mb-3">
                                                        <div class="card">
                                                            <div class="card-body p-3">
                                                                <h6 class="card-title small">
                                                                    <i class="fas fa-user me-2"></i>
                                                                    <?php echo htmlspecialchars($member->name); ?>
                                                                </h6>
                                                                <p class="card-text small">
                                                                    <strong>Relation:</strong> <?php echo htmlspecialchars($member->relationship); ?><br>
                                                                    <strong>DOB:</strong> <?php echo isset($member->date_of_birth) && $member->date_of_birth ? date('d M Y', strtotime($member->date_of_birth)) : 'Not specified'; ?>
                                                                </p>
                                                            </div>
                                                        </div>
                                                    </div>
                                                <?php 
                                                    $count++;
                                                    endif;
                                                endforeach; 
                                                
                                                if (count($familyMembers) > 4):
                                                ?>
                                                <div class="col-12 text-center mt-2">
                                                    <a href="#" class="btn btn-sm btn-outline-warning" id="showAllFamily">
                                                        View all <?php echo count($familyMembers); ?> family members
                                                    </a>
                                                </div>
                                                <?php endif; ?>
                                            </div>
                                        <?php else: ?>
                                            <div class="alert alert-info">
                                                <i class="fas fa-info-circle me-2"></i>No family members recorded. 
                                                <a href="personal.php">Add family information</a>
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

        <!-- Profile Completion Chart -->
        <div class="row mt-4">
            <div class="col-12">
                <div class="chart-container">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5 class="section-title mb-0">Profile Completion Analysis</h5>
                        <div class="btn-group" role="group" aria-label="Chart View Toggle">
                            <button type="button" class="btn btn-sm btn-outline-primary active" id="horizontalViewBtn">
                                <i class="fas fa-columns me-1"></i> Dashboard View
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-primary" id="verticalViewBtn">
                                <i class="fas fa-th me-1"></i> Card View
                            </button>
                        </div>
                    </div>
                    
                    <!-- Horizontal View (default) -->
                    <div id="horizontalView" class="chart-view">
                        <div class="row">
                            <div class="col-md-5 mb-4 mb-md-0">
                                <div class="card h-100">
                                    <div class="card-body text-center">
                                        <div class="chart-wrapper" style="position: relative; height: 220px;">
                                            <canvas id="completionChart"></canvas>
                                        </div>
                                        <div class="position-relative mt-3">
                                            <h3 class="completion-percentage"><?php echo isset($userStats['profile_completion']) ? $userStats['profile_completion'] : 0; ?>%</h3>
                                            <p class="text-muted">Profile Completion</p>
                                            <?php if ($userStats['profile_completion'] < 100): ?>
                                            <a href="profile.php" class="btn btn-sm btn-primary mt-2">
                                                <i class="fas fa-edit me-1"></i> Complete Profile
                                            </a>
                                            <?php else: ?>
                                            <div class="badge bg-success p-2 mt-2">
                                                <i class="fas fa-check-circle me-1"></i> Profile Complete
                                            </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-7">
                                <div class="card h-100">
                                    <div class="card-body">
                                        <h6 class="card-title d-flex justify-content-between align-items-center">
                                            <span>Profile Sections Status</span>
                                            <small class="text-muted"><?php echo isset($userStats['profile_completion']) ? $userStats['profile_completion'] : 0; ?>% Complete</small>
                                        </h6>
                                        <ul class="list-group list-group-flush"
                                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                                Basic Information
                                                <span class="badge bg-success rounded-pill">
                                                    <i class="fas fa-check-circle"></i> Complete
                                                </span>
                                            </li>
                                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                                Military Sizing
                                                <span class="badge bg-success rounded-pill">
                                                    <i class="fas fa-check-circle"></i> Complete
                                                </span>
                                            </li>
                                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                                Contact Information
                                                <span class="badge <?php echo !empty($contactInfo) ? 'bg-success' : 'bg-warning text-dark'; ?> rounded-pill">
                                                    <i class="fas <?php echo !empty($contactInfo) ? 'fa-check-circle' : 'fa-times-circle'; ?>"></i>
                                                    <?php echo !empty($contactInfo) ? 'Complete' : 'Incomplete'; ?>
                                                </span>
                                            </li>
                                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                                Address Information
                                                <span class="badge <?php echo !empty($addresses) ? 'bg-success' : 'bg-warning text-dark'; ?> rounded-pill">
                                                    <i class="fas <?php echo !empty($addresses) ? 'fa-check-circle' : 'fa-times-circle'; ?>"></i>
                                                    <?php echo !empty($addresses) ? 'Complete' : 'Incomplete'; ?>
                                                </span>
                                            </li>
                                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                                Family Information
                                                <span class="badge <?php echo !empty($familyMembers) ? 'bg-success' : 'bg-info'; ?> rounded-pill">
                                                    <i class="fas <?php echo !empty($familyMembers) ? 'fa-check-circle' : 'fa-exclamation-circle'; ?>"></i>
                                                    <?php echo !empty($familyMembers) ? 'Complete' : 'Optional'; ?>
                                                </span>
                                            </li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Vertical View (hidden by default) -->
                    <div id="verticalView" class="chart-view" style="display: none;">
                        <div class="row mb-4">
                            <div class="col-lg-4 col-md-6 mb-4">
                                <div class="card h-100 shadow-sm">
                                    <div class="card-body text-center">
                                        <div class="chart-wrapper" style="position: relative; height: 180px;">
                                            <canvas id="completionChartVertical"></canvas>
                                        </div>
                                        <div class="position-relative mt-3">
                                            <h3 class="completion-percentage"><?php echo isset($userStats['profile_completion']) ? $userStats['profile_completion'] : 0; ?>%</h3>
                                            <p class="text-muted">Overall Completion</p>
                                        </div>
                                        <?php if ($userStats['profile_completion'] < 100): ?>
                                        <div class="mt-3">
                                            <a href="profile.php" class="btn btn-sm btn-primary">
                                                <i class="fas fa-edit me-1"></i> Complete Profile
                                            </a>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-lg-4 col-md-6 mb-4">
                                <div class="card h-100 border-success shadow-sm">
                                    <div class="card-header bg-success text-white">
                                        <h6 class="mb-0"><i class="fas fa-user-circle me-2"></i>Basic Information</h6>
                                    </div>
                                    <div class="card-body text-center">
                                        <i class="fas fa-user-check fa-3x text-success mb-3"></i>
                                        <h6>Personal Details</h6>
                                        <div class="progress mt-3">
                                            <div class="progress-bar bg-success" style="width: 100%"></div>
                                        </div>
                                        <span class="badge bg-success mt-2">Complete</span>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-lg-4 col-md-6 mb-4">
                                <div class="card h-100 border-success shadow-sm">
                                    <div class="card-header bg-success text-white">
                                        <h6 class="mb-0"><i class="fas fa-ruler me-2"></i>Military Sizing</h6>
                                    </div>
                                    <div class="card-body text-center">
                                        <i class="fas fa-ruler fa-3x text-success mb-3"></i>
                                        <h6>Equipment Specifications</h6>
                                        <div class="progress mt-3">
                                            <div class="progress-bar bg-success" style="width: 100%"></div>
                                        </div>
                                        <span class="badge bg-success mt-2">Complete</span>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-lg-4 col-md-6 mb-4">
                                <div class="card h-100 <?php echo !empty($contactInfo) ? 'border-success shadow-sm' : 'border-warning shadow-sm'; ?>">
                                    <div class="card-header <?php echo !empty($contactInfo) ? 'bg-success text-white' : 'bg-warning text-dark'; ?>">
                                        <h6 class="mb-0"><i class="fas fa-address-book me-2"></i>Contact Information</h6>
                                    </div>
                                    <div class="card-body text-center">
                                        <i class="fas fa-address-book fa-3x <?php echo !empty($contactInfo) ? 'text-success' : 'text-warning'; ?> mb-3"></i>
                                        <h6>Communication Details</h6>
                                        <div class="progress mt-3">
                                            <div class="progress-bar <?php echo !empty($contactInfo) ? 'bg-success' : 'bg-warning'; ?>" 
                                                 style="width: <?php echo !empty($contactInfo) ? '100' : '50'; ?>%"></div>
                                        </div>
                                        <span class="badge <?php echo !empty($contactInfo) ? 'bg-success' : 'bg-warning text-dark'; ?> mt-2">
                                            <?php echo !empty($contactInfo) ? 'Complete' : 'Incomplete'; ?>
                                        </span>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-lg-4 col-md-6 mb-4">
                                <div class="card h-100 <?php echo !empty($addresses) ? 'border-success shadow-sm' : 'border-warning shadow-sm'; ?>">
                                    <div class="card-header <?php echo !empty($addresses) ? 'bg-success text-white' : 'bg-warning text-dark'; ?>">
                                        <h6 class="mb-0"><i class="fas fa-home me-2"></i>Address Information</h6>
                                    </div>
                                    <div class="card-body text-center">
                                        <i class="fas fa-home fa-3x <?php echo !empty($addresses) ? 'text-success' : 'text-warning'; ?> mb-3"></i>
                                        <h6>Location Details</h6>
                                        <div class="progress mt-3">
                                            <div class="progress-bar <?php echo !empty($addresses) ? 'bg-success' : 'bg-warning'; ?>" 
                                                 style="width: <?php echo !empty($addresses) ? '100' : '50'; ?>%"></div>
                                        </div>
                                        <span class="badge <?php echo !empty($addresses) ? 'bg-success' : 'bg-warning text-dark'; ?> mt-2">
                                            <?php echo !empty($addresses) ? 'Complete' : 'Incomplete'; ?>
                                        </span>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-lg-4 col-md-6 mb-4">
                                <div class="card h-100 <?php echo !empty($familyMembers) ? 'border-success shadow-sm' : 'border-info shadow-sm'; ?>">
                                    <div class="card-header <?php echo !empty($familyMembers) ? 'bg-success text-white' : 'bg-info text-white'; ?>">
                                        <h6 class="mb-0"><i class="fas fa-users me-2"></i>Family Information</h6>
                                    </div>
                                    <div class="card-body text-center">
                                        <i class="fas fa-users fa-3x <?php echo !empty($familyMembers) ? 'text-success' : 'text-info'; ?> mb-3"></i>
                                        <h6>Dependents & Relations</h6>
                                        <div class="progress mt-3">
                                            <div class="progress-bar <?php echo !empty($familyMembers) ? 'bg-success' : 'bg-info'; ?>" 
                                                 style="width: <?php echo !empty($familyMembers) ? '100' : '75'; ?>%"></div>
                                        </div>
                                        <span class="badge <?php echo !empty($familyMembers) ? 'bg-success' : 'bg-info'; ?> mt-2">
                                            <?php echo !empty($familyMembers) ? 'Complete' : 'Optional'; ?>
                                        </span>
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
<!-- End of content-wrapper -->

<!-- Custom Scripts -->
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Tab View Toggle Functionality
        const tabsHorizontalBtn = document.getElementById('tabsHorizontalBtn');
        const tabsVerticalBtn = document.getElementById('tabsVerticalBtn');
        const cardsViewBtn = document.getElementById('cardsViewBtn');
        const horizontalTabsView = document.getElementById('horizontalTabsView');
        const verticalTabsView = document.getElementById('verticalTabsView');
        const cardsView = document.getElementById('cardsView');
        
        // Tab view toggle handlers
        tabsHorizontalBtn.addEventListener('click', function() {
            horizontalTabsView.style.display = 'block';
            verticalTabsView.style.display = 'none';
            cardsView.style.display = 'none';
            
            tabsHorizontalBtn.classList.add('active');
            tabsVerticalBtn.classList.remove('active');
            cardsViewBtn.classList.remove('active');
        });
        
        tabsVerticalBtn.addEventListener('click', function() {
            horizontalTabsView.style.display = 'none';
            verticalTabsView.style.display = 'block';
            cardsView.style.display = 'none';
            
            tabsHorizontalBtn.classList.remove('active');
            tabsVerticalBtn.classList.add('active');
            cardsViewBtn.classList.remove('active');
        });
        
        cardsViewBtn.addEventListener('click', function() {
            horizontalTabsView.style.display = 'none';
            verticalTabsView.style.display = 'none';
            cardsView.style.display = 'block';
            
            tabsHorizontalBtn.classList.remove('active');
            tabsVerticalBtn.classList.remove('active');
            cardsViewBtn.classList.add('active');
        });
        
        // Show all handlers for card view
        const showAllContacts = document.getElementById('showAllContacts');
        if (showAllContacts) {
            showAllContacts.addEventListener('click', function(e) {
                e.preventDefault();
                tabsHorizontalBtn.click();
                document.getElementById('contact-tab').click();
            });
        }
        
        const showAllFamily = document.getElementById('showAllFamily');
        if (showAllFamily) {
            showAllFamily.addEventListener('click', function(e) {
                e.preventDefault();
                tabsHorizontalBtn.click();
                document.getElementById('family-tab').click();
            });
        }
        
        // Chart View Toggle Functionality
        const horizontalViewBtn = document.getElementById('horizontalViewBtn');
        const verticalViewBtn = document.getElementById('verticalViewBtn');
        const horizontalView = document.getElementById('horizontalView');
        const verticalView = document.getElementById('verticalView');
        
        // Common chart data
        const chartData = {
            labels: ['Completed', 'Remaining'],
            datasets: [{
                data: [
                    <?php echo isset($userStats['profile_completion']) ? $userStats['profile_completion'] : 0; ?>, 
                    <?php echo isset($userStats['profile_completion']) ? (100 - $userStats['profile_completion']) : 100; ?>
                ],
                backgroundColor: ['#28a745', '#e9ecef'],
                borderWidth: 0,
                hoverOffset: 4
            }]
        };
        
        // Common chart options
        const chartOptions = {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: {
                        padding: 20,
                        usePointStyle: true,
                        pointStyle: 'circle'
                    }
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            return `${context.label}: ${context.raw}%`;
                        }
                    },
                    backgroundColor: 'rgba(0,0,0,0.8)',
                    padding: 10,
                    cornerRadius: 6
                }
            },
            cutout: '75%',
            animation: {
                animateScale: true,
                animateRotate: true
            }
        };
        
        // Create horizontal chart
        const ctxHorizontal = document.getElementById('completionChart').getContext('2d');
        const horizontalChart = new Chart(ctxHorizontal, {
            type: 'doughnut',
            data: chartData,
            options: chartOptions
        });
        
        // Create vertical chart
        const ctxVertical = document.getElementById('completionChartVertical').getContext('2d');
        const verticalChart = new Chart(ctxVertical, {
            type: 'doughnut',
            data: chartData,
            options: chartOptions
        });
        
        // Chart view toggle handlers
        horizontalViewBtn.addEventListener('click', function() {
            horizontalView.style.display = 'block';
            verticalView.style.display = 'none';
            horizontalViewBtn.classList.add('active');
            verticalViewBtn.classList.remove('active');
            // Resize charts to fit new container
            horizontalChart.resize();
        });
        
        verticalViewBtn.addEventListener('click', function() {
            horizontalView.style.display = 'none';
            verticalView.style.display = 'block';
            horizontalViewBtn.classList.remove('active');
            verticalViewBtn.classList.add('active');
            // Resize charts to fit new container
            verticalChart.resize();
        });
        
        // Make sure charts are properly sized on page load
        setTimeout(() => {
            horizontalChart.resize();
            verticalChart.resize();
        }, 100);
    });
</script>

<?php
// Include shared footer
require_once dirname(__DIR__) . '/shared/footer.php';
?>
