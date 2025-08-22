<?php
/**
 * ARMIS Profile Management API Router
 * Complete CRUD operations for all profile data with military-grade security
 */

// Set JSON content type
header('Content-Type: application/json');

// Security headers
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include dependencies
require_once dirname(__DIR__, 2) . '/config.php';
require_once dirname(__DIR__, 2) . '/shared/database_connection.php';
require_once dirname(__DIR__) . '/classes/EnhancedProfileManager.php';
require_once dirname(__DIR__) . '/classes/ServiceRecordManager.php';
require_once dirname(__DIR__) . '/classes/CVProcessor.php';

/**
 * ARMIS Profile API Router Class
 */
class ProfileAPIRouter {
    private $userId;
    private $profileManager;
    private $serviceManager;
    private $cvProcessor;
    private $allowedMethods = ['GET', 'POST', 'PUT', 'DELETE'];
    private $rateLimitWindow = 3600; // 1 hour
    private $rateLimitRequests = 100; // 100 requests per hour
    
    public function __construct() {
        $this->authenticate();
        $this->checkRateLimit();
        $this->initializeManagers();
    }
    
    /**
     * Authenticate user
     */
    private function authenticate() {
        if (!isset($_SESSION['user_id'])) {
            $this->sendError(401, 'Authentication required');
        }
        
        $this->userId = $_SESSION['user_id'];
    }
    
    /**
     * Basic rate limiting
     */
    private function checkRateLimit() {
        $clientIP = $this->getClientIP();
        $cacheKey = "rate_limit:{$clientIP}";
        
        // Simple in-memory rate limiting (in production, use Redis/Memcached)
        if (!isset($_SESSION['rate_limit'])) {
            $_SESSION['rate_limit'] = [];
        }
        
        $now = time();
        $windowStart = $now - $this->rateLimitWindow;
        
        // Clean old entries
        $_SESSION['rate_limit'] = array_filter($_SESSION['rate_limit'], function($timestamp) use ($windowStart) {
            return $timestamp > $windowStart;
        });
        
        // Check current count
        if (count($_SESSION['rate_limit']) >= $this->rateLimitRequests) {
            $this->sendError(429, 'Rate limit exceeded');
        }
        
        // Add current request
        $_SESSION['rate_limit'][] = $now;
    }
    
    /**
     * Get client IP address
     */
    private function getClientIP() {
        $headers = ['HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'HTTP_CLIENT_IP', 'REMOTE_ADDR'];
        foreach ($headers as $header) {
            if (!empty($_SERVER[$header])) {
                return $_SERVER[$header];
            }
        }
        return '127.0.0.1';
    }
    
    /**
     * Initialize manager classes
     */
    private function initializeManagers() {
        $this->profileManager = new EnhancedProfileManager($this->userId);
        $this->serviceManager = new ServiceRecordManager($this->userId);
        $this->cvProcessor = new CVProcessor($this->userId);
    }
    
    /**
     * Route the request
     */
    public function route() {
        $method = $_SERVER['REQUEST_METHOD'];
        $path = $this->getRequestPath();
        
        if (!in_array($method, $this->allowedMethods)) {
            $this->sendError(405, 'Method not allowed');
        }
        
        try {
            switch ($path) {
                // Profile endpoints
                case 'profile':
                    $this->handleProfile($method);
                    break;
                    
                case 'profile/personal':
                    $this->handlePersonalInfo($method);
                    break;
                    
                case 'profile/contact':
                    $this->handleContactInfo($method);
                    break;
                    
                case 'profile/military':
                    $this->handleMilitaryInfo($method);
                    break;
                    
                case 'profile/family':
                    $this->handleFamilyInfo($method);
                    break;
                    
                case 'profile/emergency':
                    $this->handleEmergencyContacts($method);
                    break;
                    
                // Service record endpoints
                case 'service/deployments':
                    $this->handleDeployments($method);
                    break;
                    
                case 'service/clearance':
                    $this->handleSecurityClearance($method);
                    break;
                    
                case 'service/medical':
                    $this->handleMedicalReadiness($method);
                    break;
                    
                case 'service/training':
                    $this->handleTrainingCompliance($method);
                    break;
                    
                case 'service/rank':
                    $this->handleRankProgression($method);
                    break;
                    
                case 'service/eligibility':
                    $this->handlePromotionEligibility($method);
                    break;
                    
                // CV management endpoints
                case 'cv/upload':
                    $this->handleCVUpload($method);
                    break;
                    
                case 'cv/generate':
                    $this->handleCVGeneration($method);
                    break;
                    
                case 'cv/templates':
                    $this->handleCVTemplates($method);
                    break;
                    
                // Analytics endpoints
                case 'analytics/summary':
                    $this->handleAnalyticsSummary($method);
                    break;
                    
                case 'analytics/compliance':
                    $this->handleComplianceAnalytics($method);
                    break;
                    
                // Notification endpoints
                case 'notifications/expiring':
                    $this->handleExpiringNotifications($method);
                    break;
                    
                default:
                    $this->sendError(404, 'Endpoint not found');
            }
        } catch (Exception $e) {
            error_log("API Error: " . $e->getMessage());
            $this->sendError(500, 'Internal server error');
        }
    }
    
    /**
     * Get request path
     */
    private function getRequestPath() {
        $path = $_GET['endpoint'] ?? '';
        return trim($path, '/');
    }
    
    /**
     * Get request data
     */
    private function getRequestData() {
        $method = $_SERVER['REQUEST_METHOD'];
        
        switch ($method) {
            case 'GET':
                return $_GET;
                
            case 'POST':
                return array_merge($_POST, $_FILES);
                
            case 'PUT':
            case 'DELETE':
                parse_str(file_get_contents('php://input'), $data);
                return $data;
                
            default:
                return [];
        }
    }
    
    /**
     * Handle profile operations
     */
    private function handleProfile($method) {
        switch ($method) {
            case 'GET':
                $profile = $this->profileManager->getCompleteProfile();
                $this->sendResponse(['profile' => $profile]);
                break;
                
            case 'PUT':
                $data = $this->getRequestData();
                $result = $this->profileManager->updateProfile($data);
                $this->sendResponse($result);
                break;
                
            default:
                $this->sendError(405, 'Method not allowed for this endpoint');
        }
    }
    
    /**
     * Handle personal information
     */
    private function handlePersonalInfo($method) {
        switch ($method) {
            case 'GET':
                $info = $this->profileManager->getPersonalInfo();
                $this->sendResponse(['personal_info' => $info]);
                break;
                
            case 'PUT':
                $data = $this->getRequestData();
                $result = $this->profileManager->updatePersonalInfo($data);
                $this->sendResponse($result);
                break;
                
            default:
                $this->sendError(405, 'Method not allowed for this endpoint');
        }
    }
    
    /**
     * Handle contact information
     */
    private function handleContactInfo($method) {
        switch ($method) {
            case 'GET':
                $info = $this->profileManager->getContactInfo();
                $this->sendResponse(['contact_info' => $info]);
                break;
                
            case 'PUT':
                $data = $this->getRequestData();
                $result = $this->profileManager->updateContactInfo($data);
                $this->sendResponse($result);
                break;
                
            default:
                $this->sendError(405, 'Method not allowed for this endpoint');
        }
    }
    
    /**
     * Handle military information
     */
    private function handleMilitaryInfo($method) {
        switch ($method) {
            case 'GET':
                $info = $this->profileManager->getMilitaryInfo();
                $this->sendResponse(['military_info' => $info]);
                break;
                
            case 'PUT':
                $data = $this->getRequestData();
                $result = $this->profileManager->updateMilitaryInfo($data);
                $this->sendResponse($result);
                break;
                
            default:
                $this->sendError(405, 'Method not allowed for this endpoint');
        }
    }
    
    /**
     * Handle family information
     */
    private function handleFamilyInfo($method) {
        switch ($method) {
            case 'GET':
                $info = $this->profileManager->getFamilyInfo();
                $this->sendResponse(['family_info' => $info]);
                break;
                
            case 'POST':
                $data = $this->getRequestData();
                $result = $this->profileManager->addFamilyMember($data);
                $this->sendResponse($result);
                break;
                
            case 'PUT':
                $data = $this->getRequestData();
                $result = $this->profileManager->updateFamilyMember($data);
                $this->sendResponse($result);
                break;
                
            case 'DELETE':
                $data = $this->getRequestData();
                $result = $this->profileManager->removeFamilyMember($data['family_id']);
                $this->sendResponse($result);
                break;
                
            default:
                $this->sendError(405, 'Method not allowed for this endpoint');
        }
    }
    
    /**
     * Handle emergency contacts
     */
    private function handleEmergencyContacts($method) {
        switch ($method) {
            case 'GET':
                $contacts = $this->profileManager->getEmergencyContacts();
                $this->sendResponse(['emergency_contacts' => $contacts]);
                break;
                
            case 'POST':
                $data = $this->getRequestData();
                $result = $this->profileManager->addEmergencyContact($data);
                $this->sendResponse($result);
                break;
                
            case 'PUT':
                $data = $this->getRequestData();
                $result = $this->profileManager->updateEmergencyContact($data);
                $this->sendResponse($result);
                break;
                
            case 'DELETE':
                $data = $this->getRequestData();
                $result = $this->profileManager->removeEmergencyContact($data['contact_id']);
                $this->sendResponse($result);
                break;
                
            default:
                $this->sendError(405, 'Method not allowed for this endpoint');
        }
    }
    
    /**
     * Handle deployments
     */
    private function handleDeployments($method) {
        switch ($method) {
            case 'GET':
                $deployments = $this->serviceManager->getDeploymentHistory();
                $this->sendResponse(['deployments' => $deployments]);
                break;
                
            case 'POST':
                $data = $this->getRequestData();
                $result = $this->serviceManager->addDeployment($data);
                $this->sendResponse($result);
                break;
                
            case 'PUT':
                $data = $this->getRequestData();
                $result = $this->serviceManager->updateDeploymentStatus(
                    $data['deployment_id'],
                    $data['status'],
                    $data['actual_end_date'] ?? null
                );
                $this->sendResponse(['success' => $result]);
                break;
                
            default:
                $this->sendError(405, 'Method not allowed for this endpoint');
        }
    }
    
    /**
     * Handle security clearance
     */
    private function handleSecurityClearance($method) {
        switch ($method) {
            case 'GET':
                $clearance = $this->serviceManager->getSecurityClearanceStatus();
                $this->sendResponse(['security_clearance' => $clearance]);
                break;
                
            case 'POST':
                $data = $this->getRequestData();
                $result = $this->serviceManager->addSecurityClearance($data);
                $this->sendResponse($result);
                break;
                
            default:
                $this->sendError(405, 'Method not allowed for this endpoint');
        }
    }
    
    /**
     * Handle medical readiness
     */
    private function handleMedicalReadiness($method) {
        switch ($method) {
            case 'GET':
                $medical = $this->serviceManager->getMedicalReadiness();
                $this->sendResponse(['medical_readiness' => $medical]);
                break;
                
            case 'PUT':
                $data = $this->getRequestData();
                $result = $this->serviceManager->updateMedicalReadiness($data);
                $this->sendResponse($result);
                break;
                
            default:
                $this->sendError(405, 'Method not allowed for this endpoint');
        }
    }
    
    /**
     * Handle training compliance
     */
    private function handleTrainingCompliance($method) {
        switch ($method) {
            case 'GET':
                $training = $this->serviceManager->getTrainingCompliance();
                $this->sendResponse(['training_compliance' => $training]);
                break;
                
            case 'POST':
                $data = $this->getRequestData();
                $result = $this->serviceManager->addTrainingCompliance($data);
                $this->sendResponse($result);
                break;
                
            default:
                $this->sendError(405, 'Method not allowed for this endpoint');
        }
    }
    
    /**
     * Handle rank progression
     */
    private function handleRankProgression($method) {
        switch ($method) {
            case 'GET':
                $progression = $this->serviceManager->getRankProgressionHistory();
                $this->sendResponse(['rank_progression' => $progression]);
                break;
                
            case 'POST':
                $data = $this->getRequestData();
                $result = $this->serviceManager->addRankProgression($data);
                $this->sendResponse($result);
                break;
                
            default:
                $this->sendError(405, 'Method not allowed for this endpoint');
        }
    }
    
    /**
     * Handle promotion eligibility
     */
    private function handlePromotionEligibility($method) {
        if ($method !== 'GET') {
            $this->sendError(405, 'Method not allowed for this endpoint');
        }
        
        $eligibility = $this->serviceManager->checkPromotionEligibility();
        $this->sendResponse(['promotion_eligibility' => $eligibility]);
    }
    
    /**
     * Handle CV upload
     */
    private function handleCVUpload($method) {
        if ($method !== 'POST') {
            $this->sendError(405, 'Method not allowed for this endpoint');
        }
        
        $data = $this->getRequestData();
        if (!isset($data['cv_file'])) {
            $this->sendError(400, 'CV file required');
        }
        
        $templateType = $data['template_type'] ?? null;
        $result = $this->cvProcessor->processCV($data['cv_file'], $templateType);
        $this->sendResponse($result);
    }
    
    /**
     * Handle CV generation
     */
    private function handleCVGeneration($method) {
        if ($method !== 'POST') {
            $this->sendError(405, 'Method not allowed for this endpoint');
        }
        
        $data = $this->getRequestData();
        if (!isset($data['cv_id'])) {
            $this->sendError(400, 'CV ID required');
        }
        
        $result = $this->cvProcessor->generateMilitaryCV(
            $data['cv_id'],
            $data['template_type'] ?? CVProcessor::TEMPLATE_CIVILIAN,
            $data['format'] ?? 'html'
        );
        $this->sendResponse($result);
    }
    
    /**
     * Handle CV templates
     */
    private function handleCVTemplates($method) {
        if ($method !== 'GET') {
            $this->sendError(405, 'Method not allowed for this endpoint');
        }
        
        $templates = $this->cvProcessor->getAvailableTemplates();
        $this->sendResponse(['templates' => $templates]);
    }
    
    /**
     * Handle analytics summary
     */
    private function handleAnalyticsSummary($method) {
        if ($method !== 'GET') {
            $this->sendError(405, 'Method not allowed for this endpoint');
        }
        
        $summary = [
            'profile_completion' => $this->profileManager->getProfileCompletionStatus(),
            'deployments' => count($this->serviceManager->getDeploymentHistory()),
            'security_clearance' => $this->serviceManager->getSecurityClearanceStatus(),
            'medical_readiness' => $this->serviceManager->getMedicalReadiness(),
            'training_compliance' => $this->getTrainingComplianceStats(),
            'promotion_eligibility' => $this->serviceManager->checkPromotionEligibility()
        ];
        
        $this->sendResponse(['analytics' => $summary]);
    }
    
    /**
     * Handle compliance analytics
     */
    private function handleComplianceAnalytics($method) {
        if ($method !== 'GET') {
            $this->sendError(405, 'Method not allowed for this endpoint');
        }
        
        $compliance = [
            'training' => $this->getTrainingComplianceStats(),
            'medical' => $this->getMedicalComplianceStats(),
            'clearance' => $this->getClearanceComplianceStats(),
            'overall_score' => $this->calculateOverallComplianceScore()
        ];
        
        $this->sendResponse(['compliance' => $compliance]);
    }
    
    /**
     * Handle expiring notifications
     */
    private function handleExpiringNotifications($method) {
        if ($method !== 'GET') {
            $this->sendError(405, 'Method not allowed for this endpoint');
        }
        
        $daysAhead = $_GET['days'] ?? 90;
        $expiring = $this->serviceManager->getExpiringItems($daysAhead);
        $this->sendResponse(['expiring_items' => $expiring]);
    }
    
    /**
     * Get training compliance statistics
     */
    private function getTrainingComplianceStats() {
        $training = $this->serviceManager->getTrainingCompliance();
        $total = count($training);
        $completed = count(array_filter($training, function($t) { return $t['status'] === 'completed'; }));
        $expired = count(array_filter($training, function($t) { return $t['status'] === 'expired'; }));
        
        return [
            'total' => $total,
            'completed' => $completed,
            'expired' => $expired,
            'compliance_rate' => $total > 0 ? round(($completed / $total) * 100, 1) : 0
        ];
    }
    
    /**
     * Get medical compliance statistics
     */
    private function getMedicalComplianceStats() {
        $medical = $this->serviceManager->getMedicalReadiness();
        
        return [
            'fitness_category' => $medical['fitness_category'] ?? 'unknown',
            'deployment_eligible' => $medical['deployment_eligibility'] ?? false,
            'exams_current' => $this->checkMedicalExamsCurrent($medical)
        ];
    }
    
    /**
     * Check if medical exams are current
     */
    private function checkMedicalExamsCurrent($medical) {
        $now = date('Y-m-d');
        $current = [];
        
        if ($medical) {
            $current['physical'] = isset($medical['physical_exam_expiry']) && $medical['physical_exam_expiry'] > $now;
            $current['dental'] = isset($medical['dental_exam_expiry']) && $medical['dental_exam_expiry'] > $now;
            $current['vision'] = isset($medical['vision_exam_expiry']) && $medical['vision_exam_expiry'] > $now;
            $current['hearing'] = isset($medical['hearing_exam_expiry']) && $medical['hearing_exam_expiry'] > $now;
        }
        
        return $current;
    }
    
    /**
     * Get clearance compliance statistics
     */
    private function getClearanceComplianceStats() {
        $clearance = $this->serviceManager->getSecurityClearanceStatus();
        
        if (!$clearance) {
            return ['level' => 'none', 'status' => 'none', 'days_to_expiry' => null];
        }
        
        $now = new DateTime();
        $expiry = new DateTime($clearance['expiry_date']);
        $daysToExpiry = $now->diff($expiry)->days;
        
        return [
            'level' => $clearance['clearance_level'],
            'status' => $clearance['status'],
            'days_to_expiry' => $expiry > $now ? $daysToExpiry : -$daysToExpiry
        ];
    }
    
    /**
     * Calculate overall compliance score
     */
    private function calculateOverallComplianceScore() {
        $scores = [];
        
        // Training compliance (30%)
        $trainingStats = $this->getTrainingComplianceStats();
        $scores[] = $trainingStats['compliance_rate'] * 0.3;
        
        // Medical compliance (25%)
        $medicalStats = $this->getMedicalComplianceStats();
        $medicalScore = $medicalStats['deployment_eligible'] ? 100 : 50;
        $scores[] = $medicalScore * 0.25;
        
        // Clearance compliance (25%)
        $clearanceStats = $this->getClearanceComplianceStats();
        $clearanceScore = ($clearanceStats['status'] === 'active') ? 100 : 0;
        $scores[] = $clearanceScore * 0.25;
        
        // Profile completion (20%)
        $profileCompletion = $this->profileManager->getProfileCompletionStatus();
        $scores[] = $profileCompletion['percentage'] * 0.2;
        
        return round(array_sum($scores), 1);
    }
    
    /**
     * Send JSON response
     */
    private function sendResponse($data, $statusCode = 200) {
        http_response_code($statusCode);
        echo json_encode([
            'success' => true,
            'data' => $data,
            'timestamp' => date('c'),
            'user_id' => $this->userId
        ]);
        exit;
    }
    
    /**
     * Send error response
     */
    private function sendError($statusCode, $message) {
        http_response_code($statusCode);
        echo json_encode([
            'success' => false,
            'error' => $message,
            'timestamp' => date('c'),
            'status_code' => $statusCode
        ]);
        exit;
    }
}

// Handle the request
if (isset($_GET['endpoint'])) {
    $router = new ProfileAPIRouter();
    $router->route();
} else {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'Endpoint parameter required',
        'usage' => 'Use ?endpoint=profile or ?endpoint=service/deployments, etc.'
    ]);
}