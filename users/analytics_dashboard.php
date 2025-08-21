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
$personalInfo = $profileManager->getPersonalInfo();
$contactInfo = $profileManager->getContactInfo();
$familyMembers = $profileManager->getFamilyMembers();
$addresses = $profileManager->getAddresses();

// Get user statistics
function getUserStats($pdo, $userId) {
    $stats = [];
    
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
                 CASE WHEN EXISTS(SELECT 1 FROM staff_family WHERE staff_id = ?) THEN 5 ELSE 0 END
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
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Personal Analytics Dashboard - ARMIS</title>
    
    <!-- Enhanced styling -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <style>
        :root {
            --military-green: #4a5d23;
            --military-tan: #c19b5b;
            --military-brown: #8b4513;
            --military-gray: #6c7b7f;
        }
        
        body {
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .military-header {
            background: linear-gradient(135deg, var(--military-green), var(--military-tan));
            color: white;
            padding: 2rem 0;
            margin-bottom: 2rem;
        }
        
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
        
        .section-title {
            color: var(--military-green);
            font-weight: bold;
            margin-bottom: 1rem;
            padding-bottom: 0.5rem;
            border-bottom: 2px solid var(--military-tan);
        }
        
        .nav-tabs .nav-link {
            color: var(--military-green);
            border: none;
            font-weight: 500;
        }
        
        .nav-tabs .nav-link.active {
            background-color: var(--military-green);
            color: white;
        }
    </style>
</head>
<body>
    <!-- Header -->
    <div class="military-header">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h1><i class="fas fa-chart-line me-3"></i>Personal Analytics Dashboard</h1>
                    <p class="mb-0">Comprehensive overview of your military service data</p>
                </div>
                <div class="col-md-4 text-end">
                    <a href="index.php" class="btn btn-light">
                        <i class="fas fa-arrow-left me-2"></i>Back to Profile
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="container">
        <!-- Statistics Overview -->
        <div class="row">
            <div class="col-lg-3 col-md-6">
                <div class="stat-card text-center">
                    <div class="stat-icon text-success">
                        <i class="fas fa-user-check"></i>
                    </div>
                    <h3><?php echo $userStats['profile_completion']; ?>%</h3>
                    <p class="text-muted mb-0">Profile Completion</p>
                    <div class="progress mt-2">
                        <div class="progress-bar bg-success" style="width: <?php echo $userStats['profile_completion']; ?>%"></div>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-3 col-md-6">
                <div class="stat-card text-center">
                    <div class="stat-icon text-primary">
                        <i class="fas fa-clock"></i>
                    </div>
                    <h3><?php echo $userStats['recent_activities']; ?></h3>
                    <p class="text-muted mb-0">Recent Activities</p>
                    <small class="text-muted">Last 30 days</small>
                </div>
            </div>
            
            <div class="col-lg-3 col-md-6">
                <div class="stat-card text-center">
                    <div class="stat-icon text-warning">
                        <i class="fas fa-graduation-cap"></i>
                    </div>
                    <h3><?php echo $userStats['training_records']; ?></h3>
                    <p class="text-muted mb-0">Training Records</p>
                    <small class="text-muted">Completed courses</small>
                </div>
            </div>
            
            <div class="col-lg-3 col-md-6">
                <div class="stat-card text-center">
                    <div class="stat-icon text-info">
                        <i class="fas fa-tools"></i>
                    </div>
                    <h3><?php echo $userStats['equipment_issued']; ?></h3>
                    <p class="text-muted mb-0">Equipment Issued</p>
                    <small class="text-muted">Currently assigned</small>
                </div>
            </div>
        </div>

        <!-- Detailed Information Tabs -->
        <div class="info-section">
            <ul class="nav nav-tabs" id="analyticsTab" role="tablist">
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
                                    <td><?php echo $personalInfo->dob ? date('d M Y', strtotime($personalInfo->dob)) : 'Not specified'; ?></td>
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
                                    <td><?php echo $personalInfo->employment_date ? date('d M Y', strtotime($personalInfo->employment_date)) : 'Not specified'; ?></td>
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
                                                    <strong>Date of Birth:</strong> <?php echo $member->dob ? date('d M Y', strtotime($member->dob)) : 'Not specified'; ?>
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

        <!-- Profile Completion Chart -->
        <div class="chart-container">
            <h5 class="section-title">Profile Completion Analysis</h5>
            <div class="row">
                <div class="col-md-6">
                    <canvas id="completionChart" width="400" height="400"></canvas>
                </div>
                <div class="col-md-6">
                    <h6>Profile Sections Status:</h6>
                    <ul class="list-unstyled">
                        <li class="mb-2">
                            <i class="fas fa-check-circle text-success me-2"></i>
                            Basic Information: Complete
                        </li>
                        <li class="mb-2">
                            <i class="fas fa-check-circle text-success me-2"></i>
                            Military Sizing: Complete
                        </li>
                        <li class="mb-2">
                            <i class="<?php echo !empty($contactInfo) ? 'fas fa-check-circle text-success' : 'fas fa-times-circle text-warning'; ?> me-2"></i>
                            Contact Information: <?php echo !empty($contactInfo) ? 'Complete' : 'Incomplete'; ?>
                        </li>
                        <li class="mb-2">
                            <i class="<?php echo !empty($addresses) ? 'fas fa-check-circle text-success' : 'fas fa-times-circle text-warning'; ?> me-2"></i>
                            Address Information: <?php echo !empty($addresses) ? 'Complete' : 'Incomplete'; ?>
                        </li>
                        <li class="mb-2">
                            <i class="<?php echo !empty($familyMembers) ? 'fas fa-check-circle text-success' : 'fas fa-exclamation-circle text-info'; ?> me-2"></i>
                            Family Information: <?php echo !empty($familyMembers) ? 'Complete' : 'Optional'; ?>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Profile Completion Chart
        const ctx = document.getElementById('completionChart').getContext('2d');
        const completionChart = new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: ['Completed', 'Remaining'],
                datasets: [{
                    data: [<?php echo $userStats['profile_completion']; ?>, <?php echo 100 - $userStats['profile_completion']; ?>],
                    backgroundColor: ['#28a745', '#e9ecef'],
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });
    </script>
</body>
</html>
