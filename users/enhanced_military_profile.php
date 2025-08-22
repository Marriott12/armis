<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ' . dirname($_SERVER['PHP_SELF']) . '/../login.php');
    exit();
}

$pageTitle = "Enhanced Military Profile";
$moduleName = "Enhanced Military Profile";
$moduleIcon = "shield-alt";
$currentPage = "enhanced_military";

// Include required classes
require_once dirname(__DIR__) . '/shared/database_connection.php';
require_once 'classes/EnhancedProfileManager.php';
require_once 'classes/ServiceRecordManager.php';
require_once 'classes/CVProcessor.php';

// Initialize managers
try {
    $profileManager = new EnhancedProfileManager($_SESSION['user_id']);
    $serviceManager = new ServiceRecordManager($_SESSION['user_id']);
    $cvProcessor = new CVProcessor($_SESSION['user_id']);
    
    // Get profile completion status
    $completionStatus = $profileManager->getProfileCompletionStatus();
    
    // Get service summary
    $deployments = $serviceManager->getDeploymentHistory();
    $securityClearance = $serviceManager->getSecurityClearanceStatus();
    $medicalReadiness = $serviceManager->getMedicalReadiness();
    $trainingCompliance = $serviceManager->getTrainingCompliance();
    $promotionEligibility = $serviceManager->checkPromotionEligibility();
    $expiringItems = $serviceManager->getExpiringItems(30);
    
    // Get available CV templates
    $cvTemplates = $cvProcessor->getAvailableTemplates();
    
} catch (Exception $e) {
    error_log("Error initializing profile page: " . $e->getMessage());
    $completionStatus = ['percentage' => 0];
    $deployments = [];
    $securityClearance = null;
    $medicalReadiness = null;
    $trainingCompliance = [];
    $promotionEligibility = ['eligible' => false];
    $expiringItems = [];
    $cvTemplates = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle); ?> - ARMIS</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <!-- Custom CSS -->
    <link href="../assets/css/common.css" rel="stylesheet">
    
    <style>
        .military-header {
            background: linear-gradient(135deg, #2c5530, #3d6b3f);
            color: white;
            padding: 2rem;
            border-radius: 8px;
            margin-bottom: 2rem;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        
        .completion-circle {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            background: conic-gradient(#28a745 var(--percentage), #e9ecef var(--percentage));
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto;
            position: relative;
        }
        
        .completion-circle::before {
            content: '';
            width: 90px;
            height: 90px;
            border-radius: 50%;
            background: white;
            position: absolute;
        }
        
        .completion-text {
            position: relative;
            z-index: 1;
            font-weight: bold;
            font-size: 1.2rem;
        }
        
        .status-card {
            border-left: 4px solid #28a745;
            transition: all 0.3s ease;
        }
        
        .status-card:hover {
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            transform: translateY(-2px);
        }
        
        .status-card.warning {
            border-left-color: #ffc107;
        }
        
        .status-card.danger {
            border-left-color: #dc3545;
        }
        
        .deployment-badge {
            font-size: 0.8rem;
            padding: 0.25rem 0.5rem;
        }
        
        .military-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1.5rem;
            margin-top: 2rem;
        }
        
        .feature-card {
            background: white;
            border-radius: 8px;
            padding: 1.5rem;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            border: 1px solid #dee2e6;
        }
        
        .feature-card h5 {
            color: #2c5530;
            margin-bottom: 1rem;
        }
        
        .api-demo {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 1rem;
            margin-top: 1rem;
        }
        
        .loading-spinner {
            display: none;
            text-align: center;
            padding: 1rem;
        }
        
        .alert-expiring {
            background-color: #fff3cd;
            border-color: #ffeaa7;
            color: #856404;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <!-- Enhanced Military Header -->
        <div class="military-header">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h1><i class="fas fa-shield-alt me-2"></i>Enhanced Military Profile System</h1>
                    <p class="mb-0">Military-grade personnel management with advanced features and real-time analytics</p>
                </div>
                <div class="col-md-4 text-center">
                    <div class="completion-circle" style="--percentage: <?php echo $completionStatus['percentage']; ?>%;">
                        <div class="completion-text"><?php echo $completionStatus['percentage']; ?>%</div>
                    </div>
                    <small class="text-light">Profile Completion</small>
                </div>
            </div>
        </div>

        <!-- Alert for Expiring Items -->
        <?php if (!empty($expiringItems)): ?>
        <div class="alert alert-expiring alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-triangle me-2"></i>
            <strong>Attention Required:</strong> You have <?php echo count($expiringItems); ?> item(s) expiring within 30 days.
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <!-- Military Features Grid -->
        <div class="military-grid">
            <!-- Service Record Card -->
            <div class="feature-card">
                <h5><i class="fas fa-medal me-2"></i>Service Record Management</h5>
                <div class="row">
                    <div class="col-6">
                        <div class="text-center">
                            <h3 class="text-primary"><?php echo count($deployments); ?></h3>
                            <small>Deployments</small>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="text-center">
                            <h3 class="text-success"><?php echo $promotionEligibility['eligible'] ? 'Yes' : 'No'; ?></h3>
                            <small>Promotion Eligible</small>
                        </div>
                    </div>
                </div>
                <div class="api-demo">
                    <button class="btn btn-outline-primary btn-sm" onclick="loadServiceRecord()">
                        <i class="fas fa-sync me-1"></i>Load via API
                    </button>
                    <button class="btn btn-outline-success btn-sm" onclick="addDeployment()">
                        <i class="fas fa-plus me-1"></i>Add Deployment
                    </button>
                </div>
            </div>

            <!-- Security Clearance Card -->
            <div class="feature-card">
                <h5><i class="fas fa-lock me-2"></i>Security Clearance</h5>
                <?php if ($securityClearance): ?>
                <div class="status-card p-3 mb-2 <?php echo $securityClearance['status'] === 'active' ? '' : 'warning'; ?>">
                    <div class="d-flex justify-content-between">
                        <span><strong><?php echo ucwords(str_replace('_', ' ', $securityClearance['clearance_level'])); ?></strong></span>
                        <span class="badge bg-<?php echo $securityClearance['status'] === 'active' ? 'success' : 'warning'; ?>">
                            <?php echo ucfirst($securityClearance['status']); ?>
                        </span>
                    </div>
                    <small class="text-muted">Expires: <?php echo date('M j, Y', strtotime($securityClearance['expiry_date'])); ?></small>
                </div>
                <?php else: ?>
                <p class="text-muted">No security clearance on file</p>
                <?php endif; ?>
                <div class="api-demo">
                    <button class="btn btn-outline-primary btn-sm" onclick="loadSecurityClearance()">
                        <i class="fas fa-sync me-1"></i>Load via API
                    </button>
                    <button class="btn btn-outline-success btn-sm" onclick="addSecurityClearance()">
                        <i class="fas fa-plus me-1"></i>Add Clearance
                    </button>
                </div>
            </div>

            <!-- Medical Readiness Card -->
            <div class="feature-card">
                <h5><i class="fas fa-heartbeat me-2"></i>Medical Readiness</h5>
                <?php if ($medicalReadiness): ?>
                <div class="status-card p-3 mb-2 <?php echo $medicalReadiness['deployment_eligibility'] ? '' : 'danger'; ?>">
                    <div class="d-flex justify-content-between">
                        <span><strong><?php echo ucfirst($medicalReadiness['fitness_category']); ?></strong></span>
                        <span class="badge bg-<?php echo $medicalReadiness['deployment_eligibility'] ? 'success' : 'danger'; ?>">
                            <?php echo $medicalReadiness['deployment_eligibility'] ? 'Deploy Ready' : 'Not Ready'; ?>
                        </span>
                    </div>
                    <?php if ($medicalReadiness['physical_exam_expiry']): ?>
                    <small class="text-muted">Physical Exam: <?php echo date('M j, Y', strtotime($medicalReadiness['physical_exam_expiry'])); ?></small>
                    <?php endif; ?>
                </div>
                <?php else: ?>
                <p class="text-muted">No medical readiness data on file</p>
                <?php endif; ?>
                <div class="api-demo">
                    <button class="btn btn-outline-primary btn-sm" onclick="loadMedicalReadiness()">
                        <i class="fas fa-sync me-1"></i>Load via API
                    </button>
                    <button class="btn btn-outline-success btn-sm" onclick="updateMedicalReadiness()">
                        <i class="fas fa-edit me-1"></i>Update Status
                    </button>
                </div>
            </div>

            <!-- Training Compliance Card -->
            <div class="feature-card">
                <h5><i class="fas fa-graduation-cap me-2"></i>Training Compliance</h5>
                <?php 
                $totalTraining = count($trainingCompliance);
                $completedTraining = count(array_filter($trainingCompliance, function($t) { return $t['status'] === 'completed'; }));
                $complianceRate = $totalTraining > 0 ? round(($completedTraining / $totalTraining) * 100) : 0;
                ?>
                <div class="row text-center">
                    <div class="col-4">
                        <h4 class="text-primary"><?php echo $totalTraining; ?></h4>
                        <small>Total</small>
                    </div>
                    <div class="col-4">
                        <h4 class="text-success"><?php echo $completedTraining; ?></h4>
                        <small>Completed</small>
                    </div>
                    <div class="col-4">
                        <h4 class="text-<?php echo $complianceRate >= 80 ? 'success' : 'warning'; ?>"><?php echo $complianceRate; ?>%</h4>
                        <small>Compliance</small>
                    </div>
                </div>
                <div class="api-demo">
                    <button class="btn btn-outline-primary btn-sm" onclick="loadTrainingCompliance()">
                        <i class="fas fa-sync me-1"></i>Load via API
                    </button>
                    <button class="btn btn-outline-success btn-sm" onclick="addTraining()">
                        <i class="fas fa-plus me-1"></i>Add Training
                    </button>
                </div>
            </div>

            <!-- Enhanced CV Management Card -->
            <div class="feature-card">
                <h5><i class="fas fa-file-alt me-2"></i>Enhanced CV Management</h5>
                <p class="small text-muted">Multiple military templates with advanced processing</p>
                <div class="mb-2">
                    <select class="form-select form-select-sm" id="cvTemplateSelect">
                        <option value="">Select Template</option>
                        <?php foreach ($cvTemplates as $key => $template): ?>
                        <option value="<?php echo $key; ?>"><?php echo $template['name']; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="api-demo">
                    <input type="file" class="form-control form-control-sm mb-2" id="cvFileInput" accept=".pdf,.doc,.docx,.txt">
                    <button class="btn btn-outline-primary btn-sm" onclick="uploadCV()">
                        <i class="fas fa-upload me-1"></i>Process CV
                    </button>
                    <button class="btn btn-outline-success btn-sm" onclick="generateCV()">
                        <i class="fas fa-file-pdf me-1"></i>Generate PDF
                    </button>
                </div>
            </div>

            <!-- Analytics Dashboard Card -->
            <div class="feature-card">
                <h5><i class="fas fa-chart-line me-2"></i>Real-time Analytics</h5>
                <div id="analyticsContent">
                    <div class="text-center">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                    </div>
                </div>
                <div class="api-demo">
                    <button class="btn btn-outline-primary btn-sm" onclick="loadAnalytics()">
                        <i class="fas fa-sync me-1"></i>Refresh Analytics
                    </button>
                    <button class="btn btn-outline-info btn-sm" onclick="loadCompliance()">
                        <i class="fas fa-clipboard-check me-1"></i>Compliance Report
                    </button>
                </div>
            </div>
        </div>

        <!-- API Response Display -->
        <div class="mt-4">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="fas fa-terminal me-2"></i>API Response Monitor</h5>
                    <button class="btn btn-outline-secondary btn-sm" onclick="clearApiResponse()">
                        <i class="fas fa-trash me-1"></i>Clear
                    </button>
                </div>
                <div class="card-body">
                    <pre id="apiResponse" class="mb-0" style="max-height: 300px; overflow-y: auto; background: #f8f9fa; padding: 1rem; border-radius: 4px;">
API responses will appear here...
                    </pre>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // API base URL
        const API_BASE = 'api/profile_api.php';
        
        // Utility function to make API calls
        async function apiCall(endpoint, method = 'GET', data = null) {
            const loadingSpinner = document.querySelector('.loading-spinner');
            if (loadingSpinner) loadingSpinner.style.display = 'block';
            
            try {
                const options = {
                    method: method,
                    headers: {
                        'Content-Type': 'application/json',
                    }
                };
                
                if (data && method !== 'GET') {
                    if (data instanceof FormData) {
                        delete options.headers['Content-Type'];
                        options.body = data;
                    } else {
                        options.body = JSON.stringify(data);
                    }
                }
                
                let url = `${API_BASE}?endpoint=${endpoint}`;
                if (method === 'GET' && data) {
                    const params = new URLSearchParams(data);
                    url += `&${params}`;
                }
                
                const response = await fetch(url, options);
                const result = await response.json();
                
                // Display in response monitor
                displayApiResponse(endpoint, method, result);
                
                if (loadingSpinner) loadingSpinner.style.display = 'none';
                
                return result;
            } catch (error) {
                console.error('API call failed:', error);
                displayApiResponse(endpoint, method, { error: error.message });
                if (loadingSpinner) loadingSpinner.style.display = 'none';
                return { success: false, error: error.message };
            }
        }
        
        // Display API response in monitor
        function displayApiResponse(endpoint, method, response) {
            const responseElement = document.getElementById('apiResponse');
            const timestamp = new Date().toLocaleTimeString();
            const entry = `[${timestamp}] ${method} ${endpoint}\n${JSON.stringify(response, null, 2)}\n\n`;
            responseElement.textContent = entry + responseElement.textContent;
        }
        
        // Clear API response monitor
        function clearApiResponse() {
            document.getElementById('apiResponse').textContent = 'API responses will appear here...';
        }
        
        // Load service record
        async function loadServiceRecord() {
            const result = await apiCall('service/deployments');
            if (result.success) {
                console.log('Service record loaded:', result.data);
            }
        }
        
        // Add deployment
        async function addDeployment() {
            const deploymentData = {
                deployment_name: 'Operation Demo',
                location: 'Forward Operating Base Alpha',
                country: 'Demonstration Country',
                start_date: '2024-01-01',
                end_date: '2024-06-30',
                deployment_type: 'training'
            };
            
            const result = await apiCall('service/deployments', 'POST', deploymentData);
            if (result.success) {
                alert('Deployment added successfully!');
            }
        }
        
        // Load security clearance
        async function loadSecurityClearance() {
            const result = await apiCall('service/clearance');
            if (result.success) {
                console.log('Security clearance loaded:', result.data);
            }
        }
        
        // Add security clearance
        async function addSecurityClearance() {
            const clearanceData = {
                clearance_level: 'secret',
                issue_date: '2024-01-01',
                expiry_date: '2029-01-01',
                issuing_authority: 'Defense Security Service',
                investigation_type: 'NACLC'
            };
            
            const result = await apiCall('service/clearance', 'POST', clearanceData);
            if (result.success) {
                alert('Security clearance added successfully!');
            }
        }
        
        // Load medical readiness
        async function loadMedicalReadiness() {
            const result = await apiCall('service/medical');
            if (result.success) {
                console.log('Medical readiness loaded:', result.data);
            }
        }
        
        // Update medical readiness
        async function updateMedicalReadiness() {
            const medicalData = {
                fitness_category: 'fit',
                physical_exam_date: '2024-01-15',
                physical_exam_expiry: '2025-01-15',
                deployment_eligibility: true,
                fitness_test_score: 85.5
            };
            
            const result = await apiCall('service/medical', 'PUT', medicalData);
            if (result.success) {
                alert('Medical readiness updated successfully!');
            }
        }
        
        // Load training compliance
        async function loadTrainingCompliance() {
            const result = await apiCall('service/training');
            if (result.success) {
                console.log('Training compliance loaded:', result.data);
            }
        }
        
        // Add training
        async function addTraining() {
            const trainingData = {
                training_name: 'Combat Lifesaver Course',
                training_type: 'mandatory',
                completion_date: '2024-02-15',
                expiry_date: '2026-02-15',
                status: 'completed',
                required_for_deployment: true
            };
            
            const result = await apiCall('service/training', 'POST', trainingData);
            if (result.success) {
                alert('Training record added successfully!');
            }
        }
        
        // Upload CV
        async function uploadCV() {
            const fileInput = document.getElementById('cvFileInput');
            const templateSelect = document.getElementById('cvTemplateSelect');
            
            if (!fileInput.files[0]) {
                alert('Please select a CV file first');
                return;
            }
            
            const formData = new FormData();
            formData.append('cv_file', fileInput.files[0]);
            formData.append('template_type', templateSelect.value);
            formData.append('action', 'upload');
            
            try {
                const response = await fetch('api/cv_processor.php', {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();
                displayApiResponse('cv/upload', 'POST', result);
                
                if (result.success) {
                    alert('CV uploaded and processed successfully!');
                    fileInput.value = '';
                }
            } catch (error) {
                console.error('CV upload failed:', error);
            }
        }
        
        // Generate CV
        async function generateCV() {
            alert('CV generation feature will create a professional military-formatted document');
        }
        
        // Load analytics
        async function loadAnalytics() {
            const result = await apiCall('analytics/summary');
            if (result.success) {
                updateAnalyticsDisplay(result.data.analytics);
            }
        }
        
        // Update analytics display
        function updateAnalyticsDisplay(analytics) {
            const content = document.getElementById('analyticsContent');
            content.innerHTML = `
                <div class="row text-center">
                    <div class="col-6">
                        <h4 class="text-primary">${analytics.profile_completion.percentage}%</h4>
                        <small>Profile Complete</small>
                    </div>
                    <div class="col-6">
                        <h4 class="text-success">${analytics.deployments}</h4>
                        <small>Deployments</small>
                    </div>
                </div>
                <div class="mt-2">
                    <small class="text-muted">
                        Promotion Eligible: ${analytics.promotion_eligibility.eligible ? 'Yes' : 'No'}
                    </small>
                </div>
            `;
        }
        
        // Load compliance report
        async function loadCompliance() {
            const result = await apiCall('analytics/compliance');
            if (result.success) {
                console.log('Compliance report loaded:', result.data);
            }
        }
        
        // Initialize page
        document.addEventListener('DOMContentLoaded', function() {
            // Load initial analytics
            loadAnalytics();
            
            // Set up auto-refresh for analytics (every 30 seconds)
            setInterval(loadAnalytics, 30000);
        });
    </script>
</body>
</html>