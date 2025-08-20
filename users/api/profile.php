<?php
/**
 * Enhanced User Profile API
 * Provides RESTful endpoints for military-grade user profile operations
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once dirname(dirname(__DIR__)) . '/shared/database_connection.php';
require_once dirname(__DIR__) . '/classes/SecurityValidator.php';
require_once dirname(__DIR__) . '/classes/MilitaryValidator.php';
require_once dirname(__DIR__) . '/classes/AuditLogger.php';
require_once dirname(__DIR__) . '/profile_manager.php';

// Start session for authentication
session_start();

// Check authentication
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Authentication required']);
    exit();
}

try {
    $pdo = getDbConnection();
    $auditLogger = new AuditLogger($pdo, $_SESSION['user_id']);
    $securityValidator = new SecurityValidator($auditLogger);
    $militaryValidator = new MilitaryValidator($pdo);
    $profileManager = new UserProfileManager($_SESSION['user_id']);
    
    $method = $_SERVER['REQUEST_METHOD'];
    $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    $pathParts = explode('/', trim($path, '/'));
    
    // Extract endpoint and parameters
    $endpoint = end($pathParts);
    $input = json_decode(file_get_contents('php://input'), true);
    
    // CSRF protection for state-changing operations
    if (in_array($method, ['POST', 'PUT', 'DELETE'])) {
        $csrfToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $input['csrf_token'] ?? null;
        if (!$securityValidator->validateCSRFToken($csrfToken)) {
            http_response_code(403);
            echo json_encode(['error' => 'Invalid CSRF token']);
            exit();
        }
    }
    
    // Route requests
    switch ($method) {
        case 'GET':
            handleGetRequest($endpoint, $pdo, $auditLogger, $profileManager);
            break;
            
        case 'POST':
            handlePostRequest($endpoint, $input, $pdo, $auditLogger, $securityValidator, $militaryValidator, $profileManager);
            break;
            
        case 'PUT':
            handlePutRequest($endpoint, $input, $pdo, $auditLogger, $securityValidator, $militaryValidator, $profileManager);
            break;
            
        case 'DELETE':
            handleDeleteRequest($endpoint, $input, $pdo, $auditLogger, $profileManager);
            break;
            
        default:
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
            break;
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error', 'message' => $e->getMessage()]);
    error_log("Profile API error: " . $e->getMessage());
}

/**
 * Handle GET requests
 */
function handleGetRequest($endpoint, $pdo, $auditLogger, $profileManager) {
    switch ($endpoint) {
        case 'profile':
            $profile = $profileManager->getCompleteProfile();
            $auditLogger->logDataAccess($_SESSION['user_id'], 'profile', 'view');
            echo json_encode(['success' => true, 'data' => $profile]);
            break;
            
        case 'completion':
            $completion = getProfileCompletion($_SESSION['user_id'], $pdo);
            echo json_encode(['success' => true, 'data' => $completion]);
            break;
            
        case 'security-clearance':
            $clearance = getSecurityClearance($_SESSION['user_id'], $pdo);
            $auditLogger->logDataAccess($_SESSION['user_id'], 'security', 'view');
            echo json_encode(['success' => true, 'data' => $clearance]);
            break;
            
        case 'training-compliance':
            $compliance = getTrainingCompliance($_SESSION['user_id'], $pdo);
            echo json_encode(['success' => true, 'data' => $compliance]);
            break;
            
        case 'audit-trail':
            $trail = $auditLogger->getAuditTrail($_SESSION['user_id']);
            echo json_encode(['success' => true, 'data' => $trail]);
            break;
            
        case 'csrf-token':
            $securityValidator = new SecurityValidator();
            $token = $securityValidator->generateCSRFToken();
            echo json_encode(['success' => true, 'csrf_token' => $token]);
            break;
            
        default:
            http_response_code(404);
            echo json_encode(['error' => 'Endpoint not found']);
            break;
    }
}

/**
 * Handle POST requests
 */
function handlePostRequest($endpoint, $input, $pdo, $auditLogger, $securityValidator, $militaryValidator, $profileManager) {
    switch ($endpoint) {
        case 'validate-field':
            $fieldType = $input['field_type'] ?? '';
            $value = $input['value'] ?? '';
            $context = $input['context'] ?? 'profile_update';
            
            $result = $securityValidator->validateInput($value, $fieldType, $context);
            
            // Additional military validation for specific fields
            if ($result['valid'] && $fieldType === 'service_number') {
                $militaryResult = $militaryValidator->validateServiceNumber($value, $_SESSION['user_id']);
                if (!$militaryResult['valid']) {
                    $result['valid'] = false;
                    $result['errors'] = array_merge($result['errors'], $militaryResult['errors']);
                }
            }
            
            echo json_encode(['success' => true, 'validation' => $result]);
            break;
            
        case 'security-clearance':
            $clearanceLevel = $input['clearance_level'] ?? '';
            $authority = $input['authority'] ?? '';
            
            $validation = $militaryValidator->validateSecurityClearance($clearanceLevel, $_SESSION['user_id'], $authority);
            
            if ($validation['valid']) {
                $result = createSecurityClearance($_SESSION['user_id'], $input, $pdo);
                $auditLogger->logProfileChange($_SESSION['user_id'], 'staff_security_clearance', $result['id'], 'INSERT', null, null, json_encode($input));
                echo json_encode(['success' => true, 'data' => $result]);
            } else {
                echo json_encode(['success' => false, 'errors' => $validation['errors'], 'warnings' => $validation['warnings']]);
            }
            break;
            
        case 'medical-fitness':
            $fitnessCategory = $input['fitness_category'] ?? '';
            $validation = $militaryValidator->validateMedicalFitness($fitnessCategory, $_SESSION['user_id']);
            
            if ($validation['valid']) {
                $result = createMedicalFitness($_SESSION['user_id'], $input, $pdo);
                $auditLogger->logProfileChange($_SESSION['user_id'], 'staff_medical_fitness', $result['id'], 'INSERT');
                echo json_encode(['success' => true, 'data' => $result, 'deployment_status' => $validation['deployment_status']]);
            } else {
                echo json_encode(['success' => false, 'errors' => $validation['errors']]);
            }
            break;
            
        default:
            http_response_code(404);
            echo json_encode(['error' => 'Endpoint not found']);
            break;
    }
}

/**
 * Handle PUT requests
 */
function handlePutRequest($endpoint, $input, $pdo, $auditLogger, $securityValidator, $militaryValidator, $profileManager) {
    switch ($endpoint) {
        case 'basic-info':
            $validationErrors = [];
            $validatedData = [];
            
            // Validate each field
            foreach ($input as $field => $value) {
                $validation = $securityValidator->validateInput($value, $field);
                if (!$validation['valid']) {
                    $validationErrors[$field] = $validation['errors'];
                } else {
                    $validatedData[$field] = $validation['sanitized_value'];
                }
            }
            
            if (empty($validationErrors)) {
                $oldProfile = $profileManager->getCompleteProfile();
                $result = $profileManager->updateBasicInfo($validatedData);
                
                // Log changes
                foreach ($validatedData as $field => $newValue) {
                    $oldValue = $oldProfile->$field ?? null;
                    if ($oldValue !== $newValue) {
                        $auditLogger->logProfileChange($_SESSION['user_id'], 'staff', $_SESSION['user_id'], 'UPDATE', $field, $oldValue, $newValue);
                    }
                }
                
                $auditLogger->logDataAccess($_SESSION['user_id'], 'profile', 'edit', true, 'Basic info update');
                echo json_encode(['success' => true, 'data' => $result]);
            } else {
                echo json_encode(['success' => false, 'validation_errors' => $validationErrors]);
            }
            break;
            
        case 'rank':
            $currentRankId = $input['current_rank_id'] ?? null;
            $newRankId = $input['new_rank_id'] ?? null;
            
            $validation = $militaryValidator->validateRankProgression($currentRankId, $newRankId, $_SESSION['user_id']);
            
            if ($validation['valid']) {
                $result = updateStaffRank($_SESSION['user_id'], $newRankId, $pdo);
                $auditLogger->logProfileChange($_SESSION['user_id'], 'staff', $_SESSION['user_id'], 'UPDATE', 'rank_id', $currentRankId, $newRankId, 'Rank progression');
                echo json_encode(['success' => true, 'data' => $result, 'promotion_eligible' => $validation['promotion_eligible']]);
            } else {
                echo json_encode(['success' => false, 'errors' => $validation['errors'], 'warnings' => $validation['warnings']]);
            }
            break;
            
        default:
            http_response_code(404);
            echo json_encode(['error' => 'Endpoint not found']);
            break;
    }
}

/**
 * Handle DELETE requests
 */
function handleDeleteRequest($endpoint, $input, $pdo, $auditLogger, $profileManager) {
    // Implementation for delete operations (if needed)
    http_response_code(404);
    echo json_encode(['error' => 'Delete endpoint not implemented']);
}

// Helper functions

function getProfileCompletion($staffId, $pdo) {
    try {
        $stmt = $pdo->prepare("
            SELECT section_name, completion_percentage, mandatory_fields_complete, 
                   optional_fields_complete, verification_status
            FROM profile_completion_tracking 
            WHERE staff_id = ?
        ");
        $stmt->execute([$staffId]);
        $sections = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $totalCompletion = 0;
        $sectionCount = count($sections);
        
        foreach ($sections as $section) {
            $totalCompletion += $section['completion_percentage'];
        }
        
        return [
            'overall_completion' => $sectionCount > 0 ? round($totalCompletion / $sectionCount, 2) : 0,
            'sections' => $sections
        ];
        
    } catch (Exception $e) {
        return ['overall_completion' => 0, 'sections' => []];
    }
}

function getSecurityClearance($staffId, $pdo) {
    try {
        $stmt = $pdo->prepare("
            SELECT * FROM staff_security_clearance 
            WHERE staff_id = ? AND status = 'Active'
            ORDER BY issue_date DESC
            LIMIT 1
        ");
        $stmt->execute([$staffId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        
    } catch (Exception $e) {
        return null;
    }
}

function getTrainingCompliance($staffId, $pdo) {
    try {
        $stmt = $pdo->prepare("
            SELECT training_category, course_name, completion_date, expiry_date, 
                   compliance_status, renewal_required
            FROM staff_training_compliance 
            WHERE staff_id = ?
            ORDER BY expiry_date ASC
        ");
        $stmt->execute([$staffId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
        
    } catch (Exception $e) {
        return [];
    }
}

function createSecurityClearance($staffId, $data, $pdo) {
    $stmt = $pdo->prepare("
        INSERT INTO staff_security_clearance (
            staff_id, clearance_level, clearance_authority, issue_date, 
            expiry_date, status, notes
        ) VALUES (?, ?, ?, ?, ?, 'Active', ?)
    ");
    
    $issueDate = $data['issue_date'] ?? date('Y-m-d');
    $expiryDate = $data['expiry_date'] ?? date('Y-m-d', strtotime('+5 years'));
    
    $stmt->execute([
        $staffId,
        $data['clearance_level'],
        $data['clearance_authority'],
        $issueDate,
        $expiryDate,
        $data['notes'] ?? null
    ]);
    
    return ['id' => $pdo->lastInsertId(), 'message' => 'Security clearance created successfully'];
}

function createMedicalFitness($staffId, $data, $pdo) {
    $stmt = $pdo->prepare("
        INSERT INTO staff_medical_fitness (
            staff_id, examination_date, fitness_category, medical_officer,
            height_cm, weight_kg, blood_pressure, next_examination_date
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ");
    
    $examDate = $data['examination_date'] ?? date('Y-m-d');
    $nextExamDate = date('Y-m-d', strtotime('+1 year', strtotime($examDate)));
    
    $stmt->execute([
        $staffId,
        $examDate,
        $data['fitness_category'],
        $data['medical_officer'],
        $data['height_cm'] ?? null,
        $data['weight_kg'] ?? null,
        $data['blood_pressure'] ?? null,
        $nextExamDate
    ]);
    
    return ['id' => $pdo->lastInsertId(), 'message' => 'Medical fitness record created successfully'];
}

function updateStaffRank($staffId, $newRankId, $pdo) {
    $stmt = $pdo->prepare("UPDATE staff SET rank_id = ? WHERE id = ?");
    $stmt->execute([$newRankId, $staffId]);
    
    return ['message' => 'Rank updated successfully'];
}
?>