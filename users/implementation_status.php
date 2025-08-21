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
    
    // Check if personal sizing fields exist
    try {
        $stmt = $pdo->query("DESCRIBE staff");
        $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
        $status['personal_sizing'] = [
            'implemented' => in_array('combatSize', $columns) && in_array('bsize', $columns) && in_array('ssize', $columns) && in_array('hdress', $columns),
            'description' => 'Military sizing fields (Combat boot, Boot size, Staff shoe, Head dress)',
            'priority' => 'High',
            'module' => 'Personal Information Form'
        ];
    } catch (PDOException $e) {
        $status['personal_sizing'] = ['implemented' => false, 'description' => 'Personal sizing fields', 'priority' => 'High', 'module' => 'Personal Information'];
    }
    
    // Check enhanced contact information
    try {
        $stmt = $pdo->query("DESCRIBE staff_contact_info");
        $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
        $status['enhanced_contact'] = [
            'implemented' => in_array('contact_name', $columns) && in_array('relationship', $columns),
            'description' => 'Enhanced contact information with name and relationship fields',
            'priority' => 'High',
            'module' => 'Contact Management'
        ];
    } catch (PDOException $e) {
        $status['enhanced_contact'] = ['implemented' => false, 'description' => 'Enhanced contact information', 'priority' => 'High', 'module' => 'Contact Management'];
    }
    
    // Check training management table
    try {
        $stmt = $pdo->query("SHOW TABLES LIKE 'staff_training'");
        $status['training_management'] = [
            'implemented' => $stmt->rowCount() > 0,
            'description' => 'Comprehensive training records and management system',
            'priority' => 'Medium',
            'module' => 'Training Management'
        ];
    } catch (PDOException $e) {
        $status['training_management'] = ['implemented' => false, 'description' => 'Training management system', 'priority' => 'Medium', 'module' => 'Training'];
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
    
    // Check activity logging
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

// Calculate overall completion percentage
$totalFeatures = count($implementationStatus);
$implementedFeatures = count(array_filter($implementationStatus, function($item) { return $item['implemented']; }));
$completionPercentage = round(($implementedFeatures / $totalFeatures) * 100);
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
        }
        
        body {
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .military-header {
            background: linear-gradient(135deg, var(--military-green), var(--military-tan));
            color: white;
            padding: 3rem 0;
            margin-bottom: 2rem;
        }
        
        .status-card {
            background: white;
            border-radius: 15px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            border-left: 5px solid var(--military-gray);
        }
        
        .status-card.implemented {
            border-left-color: #28a745;
        }
        
        .status-card.not-implemented {
            border-left-color: #dc3545;
        }
        
        .status-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 15px 35px rgba(0,0,0,0.15);
        }
        
        .status-icon {
            font-size: 2rem;
            margin-bottom: 1rem;
        }
        
        .status-icon.implemented {
            color: #28a745;
        }
        
        .status-icon.not-implemented {
            color: #dc3545;
        }
        
        .priority-badge {
            font-size: 0.75rem;
            padding: 0.25rem 0.5rem;
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
            width: 150px;
            height: 150px;
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
        }
        
        .completion-circle::after {
            content: '';
            width: 100px;
            height: 100px;
            background: white;
            border-radius: 50%;
            position: absolute;
        }
        
        .completion-text {
            position: relative;
            z-index: 1;
            font-size: 1.5rem;
            font-weight: bold;
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
            margin-bottom: 2rem;
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
        
        .enhancement-summary {
            background: white;
            border-radius: 15px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
        }
    </style>
</head>
<body>
    <!-- Header -->
    <div class="military-header">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h1><i class="fas fa-tasks me-3"></i>ARMIS Enhancement Implementation Status</h1>
                    <p class="mb-0">Comprehensive overview of system improvements and new features</p>
                </div>
                <div class="col-md-4 text-end">
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
            <div class="row text-center">
                <div class="col-md-3">
                    <div class="h3 text-success"><?php echo $implementedFeatures; ?></div>
                    <p class="text-muted">Features Implemented</p>
                </div>
                <div class="col-md-3">
                    <div class="h3 text-warning"><?php echo $totalFeatures - $implementedFeatures; ?></div>
                    <p class="text-muted">Pending Features</p>
                </div>
                <div class="col-md-3">
                    <div class="h3 text-info"><?php echo $totalFeatures; ?></div>
                    <p class="text-muted">Total Features</p>
                </div>
                <div class="col-md-3">
                    <div class="h3 text-primary"><?php echo $completionPercentage; ?>%</div>
                    <p class="text-muted">Overall Progress</p>
                </div>
            </div>
        </div>

        <!-- Feature Status Cards -->
        <div class="row">
            <div class="col-lg-8">
                <h3 class="mb-4">Feature Implementation Status</h3>
                
                <?php foreach ($implementationStatus as $key => $feature): ?>
                    <div class="status-card <?php echo $feature['implemented'] ? 'implemented' : 'not-implemented'; ?>">
                        <div class="row align-items-center">
                            <div class="col-md-1">
                                <div class="status-icon <?php echo $feature['implemented'] ? 'implemented' : 'not-implemented'; ?>">
                                    <i class="fas <?php echo $feature['implemented'] ? 'fa-check-circle' : 'fa-times-circle'; ?>"></i>
                                </div>
                            </div>
                            <div class="col-md-7">
                                <h5 class="mb-1"><?php echo ucwords(str_replace('_', ' ', $key)); ?></h5>
                                <p class="mb-1 text-muted"><?php echo $feature['description']; ?></p>
                                <small class="text-muted">Module: <?php echo $feature['module']; ?></small>
                            </div>
                            <div class="col-md-2">
                                <span class="badge priority-<?php echo strtolower($feature['priority']); ?> priority-badge">
                                    <?php echo $feature['priority']; ?> Priority
                                </span>
                            </div>
                            <div class="col-md-2 text-end">
                                <span class="badge <?php echo $feature['implemented'] ? 'bg-success' : 'bg-danger'; ?>">
                                    <?php echo $feature['implemented'] ? 'Completed' : 'Pending'; ?>
                                </span>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <div class="col-lg-4">
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
                    
                    <div class="timeline-item not-implemented">
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
                            <a href="personal.php" class="btn btn-outline-primary">
                                <i class="fas fa-edit me-2"></i>Personal Information Form
                            </a>
                            <a href="analytics_dashboard.php" class="btn btn-outline-success">
                                <i class="fas fa-chart-line me-2"></i>Analytics Dashboard
                            </a>
                            <a href="mobile_personal.php" class="btn btn-outline-info">
                                <i class="fas fa-mobile-alt me-2"></i>Mobile Interface
                            </a>
                            <a href="../database_enhancements.php" class="btn btn-outline-warning">
                                <i class="fas fa-database me-2"></i>Database Enhancements
                            </a>
                            <a href="index.php" class="btn btn-outline-secondary">
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
            
            <div class="row">
                <div class="col-md-6">
                    <h5>Core Enhancements Completed:</h5>
                    <ul class="list-group list-group-flush">
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            Military Sizing Fields
                            <span class="badge bg-success rounded-pill">✓</span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            Enhanced Contact Information
                            <span class="badge bg-success rounded-pill">✓</span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            Personal Analytics Dashboard
                            <span class="badge bg-success rounded-pill">✓</span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            Mobile-Optimized Interface
                            <span class="badge bg-success rounded-pill">✓</span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            Implementation Status Dashboard
                            <span class="badge bg-success rounded-pill">✓</span>
                        </li>
                    </ul>
                </div>
                
                <div class="col-md-6">
                    <h5>Database Schema Enhancements:</h5>
                    <ul class="list-group list-group-flush">
                        <li class="list-group-item">
                            <strong>staff table:</strong> Added combatSize, bsize, ssize, hdress columns
                        </li>
                        <li class="list-group-item">
                            <strong>staff_contact_info:</strong> Added contact_name, relationship columns
                        </li>
                        <li class="list-group-item">
                            <strong>Profile Manager:</strong> Enhanced with new field handling
                        </li>
                        <li class="list-group-item">
                            <strong>UI Components:</strong> Bootstrap 5.3.3 integration
                        </li>
                        <li class="list-group-item">
                            <strong>Mobile UX:</strong> Touch-optimized interface design
                        </li>
                    </ul>
                </div>
            </div>
        </div>

        <!-- Next Steps -->
        <div class="enhancement-summary">
            <h3 class="mb-4"><i class="fas fa-road me-2"></i>Next Implementation Steps</h3>
            
            <div class="row">
                <div class="col-md-4">
                    <div class="card border-warning">
                        <div class="card-header bg-warning text-dark">
                            <h5 class="mb-0">Phase 2: Database Extensions</h5>
                        </div>
                        <div class="card-body">
                            <ul class="list-unstyled">
                                <li><i class="fas fa-circle text-warning me-2"></i>Training Management System</li>
                                <li><i class="fas fa-circle text-warning me-2"></i>Equipment Tracking</li>
                                <li><i class="fas fa-circle text-warning me-2"></i>Notification System</li>
                                <li><i class="fas fa-circle text-warning me-2"></i>Activity Logging</li>
                            </ul>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-4">
                    <div class="card border-info">
                        <div class="card-header bg-info text-white">
                            <h5 class="mb-0">Phase 3: Advanced Features</h5>
                        </div>
                        <div class="card-body">
                            <ul class="list-unstyled">
                                <li><i class="fas fa-circle text-info me-2"></i>Performance Analytics</li>
                                <li><i class="fas fa-circle text-info me-2"></i>AI/ML Integration</li>
                                <li><i class="fas fa-circle text-info me-2"></i>API Development</li>
                                <li><i class="fas fa-circle text-info me-2"></i>Security Enhancements</li>
                            </ul>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-4">
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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
