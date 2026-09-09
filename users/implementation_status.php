<?php
/**
 * Implementation Status Dashboard
 * Shows the progress and status of all ARMIS enhancements
 */

session_start();
require_once dirname(__DIR__) . '/shared/database_connection.php';

// Check if user is logged in and has appropriate access
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit();
}

$pdo = getDbConnection();

// Check implementation status of various features
function checkImplementationStatus($pdo) {
    $status = [];

    // Check if personal sizing fields exist.
    // FIX: the real staff columns are combatSize / bootSize / sSize / hDress -
    // the previous check looked for bsize/ssize/hdress (which never existed),
    // so this always reported "Pending" even though the feature was live.
    try {
        $stmt = $pdo->query("DESCRIBE staff");
        $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
        $requiredSizingColumns = ['combatSize', 'bootSize', 'sSize', 'hDress'];
        $hasAllSizingColumns = count(array_intersect($requiredSizingColumns, $columns)) === count($requiredSizingColumns);
        $status['personal_sizing'] = [
            'implemented' => $hasAllSizingColumns,
            'description' => 'Military sizing fields (Combat size, Boot size, Staff shoe size, Head dress)',
            'priority' => 'High',
            'module' => 'Personal Information Form'
        ];
    } catch (PDOException $e) {
        $status['personal_sizing'] = ['implemented' => false, 'description' => 'Personal sizing fields', 'priority' => 'High', 'module' => 'Personal Information'];
    }

    // Check enhanced contact information.
    // FIX: staff_contact_info didn't exist before; the schema migration now
    // creates it with contact_name + relationship columns as checked here.
    try {
        $stmt = $pdo->query("DESCRIBE staff_contact_info");
        $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
        $status['enhanced_contact'] = [
            'implemented' => in_array('contact_name', $columns, true) && in_array('relationship', $columns, true),
            'description' => 'Enhanced contact information with name and relationship fields',
            'priority' => 'High',
            'module' => 'Contact Management'
        ];
    } catch (PDOException $e) {
        $status['enhanced_contact'] = ['implemented' => false, 'description' => 'Enhanced contact information', 'priority' => 'High', 'module' => 'Contact Management'];
    }

    // Education and course records are stored in staff_course and staff_skills.
    try {
        $stmt = $pdo->query("SHOW TABLES LIKE 'staff_course'");
        $courseTableExists = $stmt->rowCount() > 0;
        $skillsStmt = $pdo->query("SHOW TABLES LIKE 'staff_skills'");
        $status['training_management'] = [
            'implemented' => $courseTableExists && $skillsStmt->rowCount() > 0,
            'description' => 'Comprehensive training records and management system',
            'priority' => 'Medium',
            'module' => 'Training Management'
        ];
    } catch (PDOException $e) {
        $status['training_management'] = ['implemented' => false, 'description' => 'Training management system', 'priority' => 'Medium', 'module' => 'Training'];
    }

    // Check family members management (feeds users/family.php).
    try {
        $stmt = $pdo->query("DESCRIBE staff_family_members");
        $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
        $requiredFamilyColumns = ['name', 'relationship', 'is_next_of_kin', 'nok_type', 'is_emergency_contact'];
        $status['family_management'] = [
            'implemented' => count(array_intersect($requiredFamilyColumns, $columns)) === count($requiredFamilyColumns),
            'description' => 'Family member records with Next of Kin and emergency contact designation',
            'priority' => 'High',
            'module' => 'Family Management'
        ];
    } catch (PDOException $e) {
        $stmt = $pdo->query("DESCRIBE staff");
        $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
        $hasLegacyNok = count(array_intersect(['nok', 'nokRelat', 'altNok', 'altNokRelat'], $columns)) === 4;
        $status['family_management'] = ['implemented' => $hasLegacyNok, 'description' => 'Family member records with Next of Kin and emergency contact designation', 'priority' => 'High', 'module' => 'Family Management'];
    }

    // Check equipment tracking
    try {
        $stmt = $pdo->query("SHOW TABLES LIKE 'staff_equipment'");
        $status['equipment_tracking'] = [
            'implemented' => $stmt->rowCount() > 0,
            'description' => 'Equipment allocation and tracking system',
            'priority' => 'Medium',
            'module' => 'Equipment Management'
        ];
    } catch (PDOException $e) {
        $status['equipment_tracking'] = ['implemented' => false, 'description' => 'Equipment tracking system', 'priority' => 'Medium', 'module' => 'Equipment'];
    }

    // Check analytics dashboard
    $status['analytics_dashboard'] = [
        'implemented' => file_exists(__DIR__ . '/analytics_dashboard.php'),
        'description' => 'Personal analytics and insights dashboard',
        'priority' => 'Medium',
        'module' => 'Analytics'
    ];

    // Check mobile optimization
    $status['mobile_optimization'] = [
        'implemented' => file_exists(__DIR__ . '/mobile_personal.php'),
        'description' => 'Mobile-optimized personal information form',
        'priority' => 'High',
        'module' => 'Mobile UX'
    ];

    // Check notification system
    try {
        $stmt = $pdo->query("SHOW TABLES LIKE 'staff_notifications'");
        $status['notification_system'] = [
            'implemented' => $stmt->rowCount() > 0,
            'description' => 'Real-time notification and alert system',
            'priority' => 'Medium',
            'module' => 'Notifications'
        ];
    } catch (PDOException $e) {
        $status['notification_system'] = ['implemented' => false, 'description' => 'Notification system', 'priority' => 'Medium', 'module' => 'Notifications'];
    }

    // Check activity logging.
    // NOTE: this app already has a general-purpose `activity_log` table
    // (used by logActivity() across admin_branch) plus a staff-edit-specific
    // `staff_edit_log`. `staff_activity_log` specifically has never existed;
    // kept as its own check since it would represent a distinct per-user
    // audit trail rather than the existing admin-wide log.
    try {
        $stmt = $pdo->query("SHOW TABLES LIKE 'staff_activity_log'");
        $status['activity_logging'] = [
            'implemented' => $stmt->rowCount() > 0,
            'description' => 'Comprehensive user activity logging and audit trail',
            'priority' => 'Low',
            'module' => 'Audit & Security'
        ];
    } catch (PDOException $e) {
        $status['activity_logging'] = ['implemented' => false, 'description' => 'Activity logging system', 'priority' => 'Low', 'module' => 'Security'];
    }

    // Check performance analytics
    try {
        $stmt = $pdo->query("SHOW TABLES LIKE 'system_analytics'");
        $status['performance_analytics'] = [
            'implemented' => $stmt->rowCount() > 0,
            'description' => 'System performance monitoring and analytics',
            'priority' => 'Low',
            'module' => 'System Analytics'
        ];
    } catch (PDOException $e) {
        $status['performance_analytics'] = ['implemented' => false, 'description' => 'Performance analytics', 'priority' => 'Low', 'module' => 'Analytics'];
    }

    return $status;
}

$implementationStatus = checkImplementationStatus($pdo);
// Kept as $status too since several blocks below reference it by that name
$status = $implementationStatus;

// Calculate overall completion percentage - this was always computed
// correctly from $implementationStatus; the bug was inaccurate inputs
// (wrong column/table names above), not this math.
$totalFeatures = count($implementationStatus);
$implementedFeatures = count(array_filter($implementationStatus, function($item) { return $item['implemented']; }));
$completionPercentage = $totalFeatures > 0 ? round(($implementedFeatures / $totalFeatures) * 100) : 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Implementation Status - ARMIS Enhancement Project</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">

    <style>
        :root {
            --military-green: #4a5d23;
            --military-tan: #c19b5b;
            --military-brown: #8b4513;
            --military-gray: #6c7b7f;
            --primary-shadow: 0 8px 25px rgba(0,0,0,0.1);
            --hover-shadow: 0 15px 35px rgba(0,0,0,0.15);
        }

        * {
            box-sizing: border-box;
        }

        html, body {
            height: 100%;
        }

        body {
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            font-size: 16px;
            line-height: 1.6;
            color: #333;
        }

        .container {
            max-width: 1200px;
            padding: 0 15px;
        }

        .military-header {
            background: linear-gradient(135deg, var(--military-green), var(--military-tan));
            color: white;
            padding: 2rem 0;
            margin-bottom: 2rem;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }

        .military-header h1 {
            font-size: clamp(1.5rem, 4vw, 2.5rem);
            margin-bottom: 0.5rem;
            font-weight: 700;
        }

        .military-header p {
            font-size: clamp(0.875rem, 2vw, 1rem);
            opacity: 0.95;
        }

        .status-card {
            background: white;
            border-radius: 12px;
            padding: 1.25rem;
            margin-bottom: 1.5rem;
            box-shadow: var(--primary-shadow);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            border-left: 5px solid var(--military-gray);
            overflow: hidden;
        }

        .status-card.implemented {
            border-left-color: #28a745;
        }

        .status-card.not-implemented {
            border-left-color: #dc3545;
        }

        .status-card:hover {
            transform: translateY(-2px);
            box-shadow: var(--hover-shadow);
        }

        .status-icon {
            font-size: 2rem;
            margin-bottom: 0.5rem;
            display: inline-block;
        }

        .status-icon.implemented {
            color: #28a745;
        }

        .status-icon.not-implemented {
            color: #dc3545;
        }

        .status-card h5 {
            font-size: 1.1rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
        }

        .status-card p {
            margin-bottom: 0.5rem;
            font-size: 0.95rem;
        }

        .status-card small {
            font-size: 0.85rem;
        }

        .priority-badge {
            font-size: 0.7rem;
            padding: 0.35rem 0.6rem;
            font-weight: 500;
            white-space: nowrap;
        }

        .priority-high {
            background-color: #dc3545;
            color: white;
        }

        .priority-medium {
            background-color: #ffc107;
            color: #000;
        }

        .priority-low {
            background-color: #28a745;
            color: white;
        }

        .completion-circle {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            background: conic-gradient(
                var(--military-green) 0deg,
                var(--military-green) <?php echo $completionPercentage * 3.6; ?>deg,
                #e9ecef <?php echo $completionPercentage * 3.6; ?>deg,
                #e9ecef 360deg
            );
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            margin: 0 auto;
        }

        .completion-circle::after {
            content: '';
            width: 80px;
            height: 80px;
            background: white;
            border-radius: 50%;
            position: absolute;
        }

        .completion-text {
            position: relative;
            z-index: 1;
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--military-green);
        }

        .timeline {
            position: relative;
            padding-left: 2rem;
        }

        .timeline::before {
            content: '';
            position: absolute;
            left: 1rem;
            top: 0;
            bottom: 0;
            width: 2px;
            background: var(--military-tan);
        }

        .timeline-item {
            position: relative;
            margin-bottom: 1.5rem;
        }

        .timeline-item::before {
            content: '';
            position: absolute;
            left: -1.5rem;
            top: 0.5rem;
            width: 1rem;
            height: 1rem;
            border-radius: 50%;
            background: var(--military-green);
            border: 3px solid white;
            box-shadow: 0 0 0 2px var(--military-green);
        }

        .timeline-item.not-implemented::before {
            background: #dc3545;
            box-shadow: 0 0 0 2px #dc3545;
        }

        .timeline-item .card {
            border: none;
            box-shadow: var(--primary-shadow);
            border-radius: 10px;
        }

        .enhancement-summary {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 2rem;
            box-shadow: var(--primary-shadow);
        }

        .enhancement-summary h2,
        .enhancement-summary h3 {
            font-size: clamp(1.25rem, 3vw, 1.75rem);
            font-weight: 700;
            color: var(--military-green);
        }

        .enhancement-summary h2 {
            margin-bottom: 1.5rem;
        }

        .enhancement-summary .h3 {
            font-size: clamp(1.5rem, 4vw, 2rem);
            font-weight: 700;
            color: #333;
        }

        .summary-stat {
            padding: 1rem 0.5rem;
        }

        .summary-stat .h3 {
            margin-bottom: 0.5rem;
        }

        .summary-stat p {
            margin-bottom: 0;
            font-size: 0.95rem;
        }

        .card {
            border: none;
            box-shadow: var(--primary-shadow);
            border-radius: 12px;
            margin-bottom: 1.5rem;
        }

        .card-header {
            border-radius: 12px 12px 0 0 !important;
            border: none;
            padding: 1rem 1.25rem;
            font-weight: 600;
        }

        .card-body {
            padding: 1.25rem;
        }

        .card-title {
            font-size: 1rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
        }

        .card-text {
            margin-bottom: 0.5rem;
        }

        .list-group-item {
            border: none;
            border-bottom: 1px solid #e9ecef;
            padding: 0.75rem 0;
        }

        .list-group-item:last-child {
            border-bottom: none;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .military-header {
                padding: 1.5rem 0;
                margin-bottom: 1.5rem;
            }

            .military-header .row {
                text-align: center;
            }

            .military-header .text-end {
                text-align: center !important;
            }

            .military-header h1 {
                font-size: 1.5rem;
                margin-bottom: 0.5rem;
            }

            .military-header p {
                font-size: 0.875rem;
            }

            .completion-circle {
                width: 100px;
                height: 100px;
                margin: 1rem auto 0;
            }

            .completion-circle::after {
                width: 66px;
                height: 66px;
            }

            .completion-text {
                font-size: 1.25rem;
            }

            .status-card {
                padding: 1rem;
                margin-bottom: 1rem;
            }

            .status-card .row {
                flex-direction: column;
            }

            .status-card .col-md-1,
            .status-card .col-md-2,
            .status-card .col-md-7 {
                width: 100%;
                margin-bottom: 0.75rem;
            }

            .status-card .col-md-2.text-end {
                text-align: left !important;
            }

            .status-icon {
                font-size: 1.75rem;
                margin-bottom: 0.5rem;
            }

            .status-card h5 {
                font-size: 1rem;
            }

            .enhancement-summary {
                padding: 1rem;
                margin-bottom: 1.5rem;
            }

            .enhancement-summary h2,
            .enhancement-summary h3 {
                font-size: 1.25rem;
                margin-bottom: 1rem;
            }

            .summary-stat {
                padding: 1rem 0.25rem;
                border-right: 1px solid #e9ecef;
            }

            .summary-stat:last-child {
                border-right: none;
            }

            .summary-stat .h3 {
                font-size: 1.5rem;
            }

            .card {
                margin-bottom: 1rem;
            }

            .priority-badge {
                display: block;
                margin-bottom: 0.5rem;
                width: 100%;
            }

            .timeline {
                padding-left: 1.5rem;
            }

            .timeline::before {
                left: 0.5rem;
            }

            .timeline-item::before {
                left: -1rem;
            }

            .d-grid {
                gap: 0.5rem !important;
            }

            .d-grid .btn {
                padding: 0.6rem 1rem;
                font-size: 0.9rem;
            }
        }

        @media (max-width: 576px) {
            body {
                font-size: 14px;
            }

            .container {
                padding: 0 10px;
            }

            .military-header {
                padding: 1rem 0;
                margin-bottom: 1rem;
            }

            .military-header h1 {
                font-size: 1.25rem;
                margin-bottom: 0.25rem;
            }

            .military-header p {
                font-size: 0.8rem;
            }

            .completion-circle {
                width: 90px;
                height: 90px;
            }

            .completion-circle::after {
                width: 60px;
                height: 60px;
            }

            .completion-text {
                font-size: 1.1rem;
            }

            .status-card {
                padding: 0.875rem;
                margin-bottom: 0.875rem;
                border-radius: 8px;
            }

            .status-icon {
                font-size: 1.5rem;
                margin-bottom: 0.5rem;
            }

            .status-card h5 {
                font-size: 0.95rem;
            }

            .status-card p {
                font-size: 0.85rem;
            }

            .status-card small {
                font-size: 0.8rem;
            }

            .priority-badge {
                font-size: 0.65rem;
                padding: 0.3rem 0.5rem;
            }

            .enhancement-summary {
                padding: 0.875rem;
                margin-bottom: 1rem;
                border-radius: 8px;
            }

            .enhancement-summary h2 {
                font-size: 1.1rem;
                margin-bottom: 0.875rem;
            }

            .enhancement-summary h3 {
                font-size: 1rem;
                margin-bottom: 0.875rem;
            }

            .summary-stat {
                padding: 0.75rem 0.25rem;
            }

            .summary-stat .h3 {
                font-size: 1.25rem;
            }

            .card-header {
                padding: 0.75rem 1rem;
            }

            .card-body {
                padding: 1rem;
            }

            .list-group-item {
                padding: 0.5rem 0;
                font-size: 0.9rem;
            }

            .btn {
                padding: 0.5rem 0.875rem;
                font-size: 0.85rem;
            }

            .timeline {
                padding-left: 1rem;
            }

            .timeline::before {
                left: 0.3rem;
            }

            .timeline-item {
                margin-bottom: 1rem;
            }

            .timeline-item::before {
                left: -0.85rem;
                width: 0.85rem;
                height: 0.85rem;
                top: 0.35rem;
            }
        }

        @media (min-width: 769px) {
            .status-card .row {
                align-items: center;
            }
        }

        /* Print Styles */
        @media print {
            body {
                background: white;
            }

            .military-header {
                box-shadow: none;
                page-break-after: avoid;
            }

            .status-card,
            .card,
            .enhancement-summary {
                box-shadow: none;
                border: 1px solid #ddd;
                page-break-inside: avoid;
            }
        }
    </style>
</head>
<body>
    <!-- Header -->
    <div class="military-header">
        <div class="container">
            <div class="row align-items-center g-3 g-lg-0">
                <div class="col-12 col-lg-8">
                    <h1><i class="fas fa-tasks me-2 me-md-3"></i>ARMIS Enhancement Implementation Status</h1>
                    <p class="mb-0">Comprehensive overview of system improvements and new features</p>
                </div>
                <div class="col-12 col-lg-4 text-center text-lg-end">
                    <div class="completion-circle">
                        <div class="completion-text"><?php echo $completionPercentage; ?>%</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="container">
        <!-- Enhancement Summary -->
        <div class="enhancement-summary">
            <h2 class="text-center mb-4">Project Enhancement Summary</h2>
            <div class="row text-center g-3">
                <div class="col-6 col-md-3">
                    <div class="summary-stat">
                        <div class="h3 text-success"><?php echo $implementedFeatures; ?></div>
                        <p class="text-muted">Features Implemented</p>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="summary-stat">
                        <div class="h3 text-warning"><?php echo $totalFeatures - $implementedFeatures; ?></div>
                        <p class="text-muted">Pending Features</p>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="summary-stat">
                        <div class="h3 text-info"><?php echo $totalFeatures; ?></div>
                        <p class="text-muted">Total Features</p>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="summary-stat">
                        <div class="h3 text-primary"><?php echo $completionPercentage; ?>%</div>
                        <p class="text-muted">Overall Progress</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Feature Status Cards -->
        <div class="row g-4 mb-4">
            <div class="col-12 col-lg-8">
                <h3 class="mb-4">Feature Implementation Status</h3>

                <?php foreach ($implementationStatus as $key => $feature): ?>
                    <div class="status-card <?php echo $feature['implemented'] ? 'implemented' : 'not-implemented'; ?>">
                        <div class="row g-2 align-items-center">
                            <div class="col-auto">
                                <div class="status-icon <?php echo $feature['implemented'] ? 'implemented' : 'not-implemented'; ?>">
                                    <i class="fas <?php echo $feature['implemented'] ? 'fa-check-circle' : 'fa-times-circle'; ?>"></i>
                                </div>
                            </div>
                            <div class="col-12 col-md-auto flex-grow-1">
                                <h5 class="mb-1"><?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $key))); ?></h5>
                                <p class="mb-1 text-muted"><?php echo htmlspecialchars($feature['description']); ?></p>
                                <small class="text-muted">Module: <?php echo htmlspecialchars($feature['module']); ?></small>
                            </div>
                            <div class="col-6 col-md-auto">
                                <span class="badge priority-<?php echo strtolower($feature['priority']); ?> priority-badge">
                                    <?php echo htmlspecialchars($feature['priority']); ?> Priority
                                </span>
                            </div>
                            <div class="col-6 col-md-auto text-end text-md-start">
                                <span class="badge <?php echo $feature['implemented'] ? 'bg-success' : 'bg-danger'; ?>">
                                    <?php echo $feature['implemented'] ? 'Completed' : 'Pending'; ?>
                                </span>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="col-12 col-lg-4">
                <h3 class="mb-4">Implementation Timeline</h3>

                <div class="timeline">
                    <div class="timeline-item implemented">
                        <div class="card">
                            <div class="card-body">
                                <h6 class="card-title">Personal Form Enhancements</h6>
                                <p class="card-text small">Added military sizing fields and enhanced contact information</p>
                                <small class="text-muted">Completed</small>
                            </div>
                        </div>
                    </div>

                    <div class="timeline-item implemented">
                        <div class="card">
                            <div class="card-body">
                                <h6 class="card-title">Analytics Dashboard</h6>
                                <p class="card-text small">Created comprehensive personal analytics and insights</p>
                                <small class="text-muted">Completed</small>
                            </div>
                        </div>
                    </div>

                    <div class="timeline-item implemented">
                        <div class="card">
                            <div class="card-body">
                                <h6 class="card-title">Mobile Optimization</h6>
                                <p class="card-text small">Developed mobile-friendly personal information interface</p>
                                <small class="text-muted">Completed</small>
                            </div>
                        </div>
                    </div>

                    <div class="timeline-item <?php echo ($status['equipment_tracking']['implemented'] ?? false) && ($status['notification_system']['implemented'] ?? false) ? 'implemented' : 'not-implemented'; ?>">
                        <div class="card">
                            <div class="card-body">
                                <h6 class="card-title">Database Enhancements</h6>
                                <p class="card-text small">Advanced training, equipment, and notification systems</p>
                                <small class="text-muted">In Progress</small>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Quick Access Links -->
                <div class="card mt-4">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0"><i class="fas fa-link me-2"></i>Quick Access</h5>
                    </div>
                    <div class="card-body">
                        <div class="d-grid gap-2">
                            <a href="personal.php" class="btn btn-outline-primary btn-sm">
                                <i class="fas fa-edit me-2"></i>Personal Information
                            </a>
                            <a href="analytics_dashboard.php" class="btn btn-outline-success btn-sm">
                                <i class="fas fa-chart-line me-2"></i>Analytics Dashboard
                            </a>
                            <a href="mobile_personal.php" class="btn btn-outline-info btn-sm">
                                <i class="fas fa-mobile-alt me-2"></i>Mobile Interface
                            </a>
                            <a href="../database_enhancements.php" class="btn btn-outline-warning btn-sm">
                                <i class="fas fa-database me-2"></i>Database Enhancements
                            </a>
                            <a href="index.php" class="btn btn-outline-secondary btn-sm">
                                <i class="fas fa-home me-2"></i>User Dashboard
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Technical Documentation -->
        <div class="enhancement-summary">
            <h3 class="mb-4"><i class="fas fa-code me-2"></i>Technical Implementation Details</h3>

            <div class="row g-4">
                <div class="col-12 col-md-6">
                    <h5>Core Enhancements Completed:</h5>
                    <ul class="list-group list-group-flush">
                        <?php foreach ($implementationStatus as $key => $feature): if (!$feature['implemented']) continue; ?>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <span><?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $key))); ?></span>
                            <span class="badge bg-success rounded-pill">✓</span>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                </div>

                <div class="col-12 col-md-6">
                    <h5>Still Pending:</h5>
                    <ul class="list-group list-group-flush">
                        <?php
                        $anyPending = false;
                        foreach ($implementationStatus as $key => $feature):
                            if ($feature['implemented']) continue;
                            $anyPending = true;
                        ?>
                        <li class="list-group-item">
                            <strong><?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $key))); ?>:</strong><br>
                            <small><?php echo htmlspecialchars($feature['description']); ?></small>
                        </li>
                        <?php endforeach; ?>
                        <?php if (!$anyPending): ?>
                        <li class="list-group-item text-success"><strong>Everything checked here is implemented.</strong></li>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>
        </div>

        <!-- Next Steps -->
        <div class="enhancement-summary">
            <h3 class="mb-4"><i class="fas fa-road me-2"></i>Next Implementation Steps</h3>

            <div class="row g-3">
                <div class="col-12 col-md-6 col-lg-4">
                    <div class="card border-warning">
                        <div class="card-header bg-warning text-dark">
                            <h5 class="mb-0">Phase 2: Database Extensions</h5>
                        </div>
                        <div class="card-body">
                            <ul class="list-unstyled">
                                <li><i class="fas fa-circle <?php echo ($status['training_management']['implemented'] ?? false) ? 'text-success' : 'text-warning'; ?> me-2"></i>Training Management System</li>
                                <li><i class="fas fa-circle <?php echo ($status['equipment_tracking']['implemented'] ?? false) ? 'text-success' : 'text-warning'; ?> me-2"></i>Equipment Tracking</li>
                                <li><i class="fas fa-circle <?php echo ($status['notification_system']['implemented'] ?? false) ? 'text-success' : 'text-warning'; ?> me-2"></i>Notification System</li>
                                <li><i class="fas fa-circle <?php echo ($status['activity_logging']['implemented'] ?? false) ? 'text-success' : 'text-warning'; ?> me-2"></i>Activity Logging</li>
                            </ul>
                        </div>
                    </div>
                </div>

                <div class="col-12 col-md-6 col-lg-4">
                    <div class="card border-info">
                        <div class="card-header bg-info text-white">
                            <h5 class="mb-0">Phase 3: Advanced Features</h5>
                        </div>
                        <div class="card-body">
                            <ul class="list-unstyled">
                                <li><i class="fas fa-circle <?php echo ($status['performance_analytics']['implemented'] ?? false) ? 'text-success' : 'text-info'; ?> me-2"></i>Performance Analytics</li>
                                <li><i class="fas fa-circle text-info me-2"></i>AI/ML Integration</li>
                                <li><i class="fas fa-circle text-info me-2"></i>API Development</li>
                                <li><i class="fas fa-circle text-info me-2"></i>Security Enhancements</li>
                            </ul>
                        </div>
                    </div>
                </div>

                <div class="col-12 col-md-6 col-md-offset-3 col-lg-4 col-lg-offset-0">
                    <div class="card border-success">
                        <div class="card-header bg-success text-white">
                            <h5 class="mb-0">Phase 4: Optimization</h5>
                        </div>
                        <div class="card-body">
                            <ul class="list-unstyled">
                                <li><i class="fas fa-circle text-success me-2"></i>Performance Tuning</li>
                                <li><i class="fas fa-circle text-success me-2"></i>User Experience Polish</li>
                                <li><i class="fas fa-circle text-success me-2"></i>Documentation</li>
                                <li><i class="fas fa-circle text-success me-2"></i>Testing & QA</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Core JS (jQuery/Bootstrap) are loaded centrally in shared/footer.php. -->
</body>
</html>