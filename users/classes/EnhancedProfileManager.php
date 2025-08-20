<?php
/**
 * Enhanced Military-Grade Profile Manager Class
 * Comprehensive user profile management with military-specific features
 */

require_once dirname(__DIR__, 2) . '/shared/database_connection.php';
require_once dirname(__DIR__, 2) . '/shared/security_audit_service.php';

class EnhancedProfileManager {
    private $pdo;
    private $userId;
    private $userSvcNo;
    private $auditService;
    private $errors = [];
    private $validationRules = [];
    
    public function __construct($userId) {
        $this->pdo = getDbConnection();
        $this->userId = $userId;
        $this->auditService = new SecurityAuditService();
        $this->loadUserInfo();
        $this->initializeValidationRules();
    }
    
    /**
     * Initialize validation rules for military data
     */
    private function initializeValidationRules() {
        $this->validationRules = [
            'email' => [
                'required' => false,
                'pattern' => '/^[^\s@]+@[^\s@]+\.[^\s@]+$/',
                'message' => 'Invalid email format'
            ],
            'tel' => [
                'required' => false,
                'pattern' => '/^[\+]?[0-9\-\s\(\)]+$/',
                'message' => 'Invalid phone number format'
            ],
            'NRC' => [
                'required' => false,
                'pattern' => '/^[A-Z0-9]{6,20}$/',
                'message' => 'NRC must be 6-20 alphanumeric characters'
            ],
            'service_number' => [
                'required' => true,
                'pattern' => '/^[A-Z0-9]{4,15}$/',
                'message' => 'Service number must be 4-15 alphanumeric characters'
            ]
        ];
    }
    
    /**
     * Load basic user information
     */
    private function loadUserInfo() {
        try {
            $stmt = $this->pdo->prepare("SELECT service_number FROM staff WHERE id = ?");
            $stmt->execute([$this->userId]);
            $user = $stmt->fetch(PDO::FETCH_OBJ);
            $this->userSvcNo = $user->service_number ?? 'USER_' . $this->userId;
        } catch (PDOException $e) {
            error_log("Error loading user info: " . $e->getMessage());
            $this->userSvcNo = 'USER_' . $this->userId;
        }
    }
    
    /**
     * Get comprehensive user profile with military data
     */
    public function getUserProfile() {
        try {
            $stmt = $this->pdo->prepare("
                SELECT s.*,
                       r.name as rankName, r.abbreviation as rankAbbr, r.rank_level, r.rank_category,
                       u.name as unitName, u.code as unitCode, u.unit_type, u.location as unitLocation,
                       c.name as corpsName, c.abbreviation as corpsAbbr, c.corps_type, c.color_code as corpsColor
                FROM staff s 
                LEFT JOIN ranks r ON s.rank_id = r.id
                LEFT JOIN units u ON s.unit_id = u.id
                LEFT JOIN corps c ON s.corps = c.abbreviation
                WHERE s.id = ?
            ");
            $stmt->execute([$this->userId]);
            $profile = $stmt->fetch(PDO::FETCH_OBJ);
            
            if (!$profile) {
                return null;
            }
            
            // Add calculated fields
            $profile->age = $this->calculateAge($profile->DOB);
            $profile->serviceYears = $this->calculateServiceYears($profile->attestDate);
            $profile->fullName = trim(($profile->prefix ?? '') . ' ' . $profile->first_name . ' ' . $profile->last_name);
            $profile->displayRank = $profile->rankName ?? $profile->rankAbbr ?? 'N/A';
            
            // Add legacy field mappings for compatibility
            $profile->fname = $profile->first_name;
            $profile->lname = $profile->last_name;
            $profile->rankID = $profile->rank_id;
            $profile->unitID = $profile->unit_id;
            
            return $profile;
        } catch (PDOException $e) {
            error_log("Error fetching user profile: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Update user profile with comprehensive validation and audit logging
     */
    public function updateUserProfile($data) {
        try {
            $this->pdo->beginTransaction();
            
            // Get current profile for audit logging
            $currentProfile = $this->getUserProfile();
            
            // Validate input data
            $validatedData = $this->validateProfileData($data);
            if (!empty($this->errors)) {
                $this->pdo->rollBack();
                return ['success' => false, 'errors' => $this->errors];
            }
            
            // Build dynamic update query
            $updateFields = [];
            $updateValues = [];
            
            $allowedFields = [
                'first_name', 'last_name', 'middle_name', 'prefix', 'suffix',
                'email', 'tel', 'address', 'DOB', 'gender', 'NRC', 'bloodGp', 'marital',
                'emergency_contact_name', 'emergency_contact_phone', 'emergency_contact_relationship',
                'rank_id', 'unit_id', 'corps'
            ];
            
            foreach ($allowedFields as $field) {
                if (isset($validatedData[$field])) {
                    $updateFields[] = "$field = ?";
                    $updateValues[] = $validatedData[$field];
                }
            }
            
            if (!empty($updateFields)) {
                $updateValues[] = $this->userId;
                
                $sql = "UPDATE staff SET " . implode(', ', $updateFields) . ", updated_at = NOW() WHERE id = ?";
                $stmt = $this->pdo->prepare($sql);
                $stmt->execute($updateValues);
                
                // Log changes for audit
                $this->logProfileChanges($currentProfile, $validatedData);
                
                // Update profile completion
                $this->updateProfileCompletion();
            }
            
            $this->pdo->commit();
            
            // Log successful update
            $this->auditService->logDataModification(
                $this->userId,
                'staff',
                $this->userId,
                (array)$currentProfile,
                $validatedData
            );
            
            return ['success' => true, 'message' => 'Profile updated successfully'];
            
        } catch (PDOException $e) {
            $this->pdo->rollBack();
            error_log("Error updating profile: " . $e->getMessage());
            return ['success' => false, 'message' => 'Database error occurred'];
        }
    }
    
    /**
     * Validate profile data with military-specific rules
     */
    private function validateProfileData($data) {
        $validated = [];
        $this->errors = [];
        
        foreach ($data as $field => $value) {
            $value = trim($value);
            
            // Skip empty values for non-required fields
            if (empty($value) && !($this->validationRules[$field]['required'] ?? false)) {
                continue;
            }
            
            // Apply validation rules
            if (isset($this->validationRules[$field])) {
                $rule = $this->validationRules[$field];
                
                if ($rule['required'] && empty($value)) {
                    $this->errors[$field] = ucfirst($field) . ' is required';
                    continue;
                }
                
                if (!empty($value) && isset($rule['pattern']) && !preg_match($rule['pattern'], $value)) {
                    $this->errors[$field] = $rule['message'];
                    continue;
                }
            }
            
            // Sanitize and validate specific fields
            switch ($field) {
                case 'email':
                    if (!empty($value) && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                        $this->errors[$field] = 'Invalid email format';
                        continue 2;
                    }
                    break;
                    
                case 'DOB':
                    if (!empty($value)) {
                        $date = DateTime::createFromFormat('Y-m-d', $value);
                        if (!$date || $date->format('Y-m-d') !== $value) {
                            $this->errors[$field] = 'Invalid date format (YYYY-MM-DD required)';
                            continue 2;
                        }
                        if ($date > new DateTime()) {
                            $this->errors[$field] = 'Birth date cannot be in the future';
                            continue 2;
                        }
                    }
                    break;
                    
                case 'rank_id':
                case 'unit_id':
                    if (!empty($value) && !is_numeric($value)) {
                        $this->errors[$field] = 'Invalid ' . str_replace('_', ' ', $field);
                        continue 2;
                    }
                    break;
            }
            
            $validated[$field] = htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
        }
        
        return $validated;
    }
    
    /**
     * Get security clearance information
     */
    public function getSecurityClearances() {
        try {
            $stmt = $this->pdo->prepare("
                SELECT sc.*, 
                       DATEDIFF(sc.expiry_date, CURDATE()) as days_until_expiry,
                       CASE 
                           WHEN sc.expiry_date < CURDATE() THEN 'Expired'
                           WHEN DATEDIFF(sc.expiry_date, CURDATE()) <= 30 THEN 'Expiring Soon'
                           ELSE 'Current'
                       END as status
                FROM security_clearances sc
                WHERE sc.staff_id = ? AND sc.is_active = 1
                ORDER BY sc.clearance_level DESC, sc.granted_date DESC
            ");
            $stmt->execute([$this->userId]);
            return $stmt->fetchAll(PDO::FETCH_OBJ);
        } catch (PDOException $e) {
            error_log("Error fetching security clearances: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get service records with comprehensive details
     */
    public function getServiceRecords($limit = 50) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT sr.*,
                       fr.name as from_rank_name, fr.abbreviation as from_rank_abbr,
                       tr.name as to_rank_name, tr.abbreviation as to_rank_abbr,
                       fu.name as from_unit_name, fu.code as from_unit_code,
                       tu.name as to_unit_name, tu.code as to_unit_code,
                       ao.first_name as approving_officer_fname, ao.last_name as approving_officer_lname
                FROM service_records sr
                LEFT JOIN ranks fr ON sr.from_rank_id = fr.id
                LEFT JOIN ranks tr ON sr.to_rank_id = tr.id
                LEFT JOIN units fu ON sr.from_unit_id = fu.id
                LEFT JOIN units tu ON sr.to_unit_id = tu.id
                LEFT JOIN staff ao ON sr.approving_officer_id = ao.id
                WHERE sr.staff_id = ?
                ORDER BY sr.record_date DESC, sr.created_at DESC
                LIMIT ?
            ");
            $stmt->execute([$this->userId, $limit]);
            return $stmt->fetchAll(PDO::FETCH_OBJ);
        } catch (PDOException $e) {
            error_log("Error fetching service records: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get medical records with fitness tracking
     */
    public function getMedicalRecords() {
        try {
            $stmt = $this->pdo->prepare("
                SELECT mr.*,
                       DATEDIFF(mr.next_exam_due, CURDATE()) as days_until_exam,
                       CASE 
                           WHEN mr.next_exam_due < CURDATE() THEN 'Overdue'
                           WHEN DATEDIFF(mr.next_exam_due, CURDATE()) <= 30 THEN 'Due Soon'
                           ELSE 'Current'
                       END as exam_status
                FROM medical_records mr
                WHERE mr.staff_id = ?
                ORDER BY mr.medical_exam_date DESC
                LIMIT 10
            ");
            $stmt->execute([$this->userId]);
            return $stmt->fetchAll(PDO::FETCH_OBJ);
        } catch (PDOException $e) {
            error_log("Error fetching medical records: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get training compliance status
     */
    public function getTrainingCompliance() {
        try {
            $stmt = $this->pdo->prepare("
                SELECT tc.*,
                       DATEDIFF(tc.next_due_date, CURDATE()) as days_until_due,
                       CASE tc.compliance_status
                           WHEN 'Current' THEN 'success'
                           WHEN 'Due Soon' THEN 'warning'
                           WHEN 'Overdue' THEN 'danger'
                           ELSE 'secondary'
                       END as status_class
                FROM training_compliance tc
                WHERE tc.staff_id = ?
                ORDER BY tc.next_due_date ASC, tc.training_type
            ");
            $stmt->execute([$this->userId]);
            return $stmt->fetchAll(PDO::FETCH_OBJ);
        } catch (PDOException $e) {
            error_log("Error fetching training compliance: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get family readiness information
     */
    public function getFamilyReadiness() {
        try {
            $stmt = $this->pdo->prepare("
                SELECT fr.*
                FROM family_readiness fr
                WHERE fr.staff_id = ?
            ");
            $stmt->execute([$this->userId]);
            return $stmt->fetch(PDO::FETCH_OBJ);
        } catch (PDOException $e) {
            error_log("Error fetching family readiness: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Get profile completion percentage with detailed breakdown
     */
    public function getProfileCompletion() {
        try {
            $stmt = $this->pdo->prepare("
                SELECT pc.section, pc.completion_percentage, 
                       pc.required_fields_total, pc.completed_fields,
                       pc.last_updated_date
                FROM profile_completion pc
                WHERE pc.staff_id = ?
                ORDER BY pc.section
            ");
            $stmt->execute([$this->userId]);
            $sections = $stmt->fetchAll(PDO::FETCH_OBJ);
            
            // Calculate overall completion
            $totalPercentage = 0;
            $sectionCount = count($sections);
            
            foreach ($sections as $section) {
                $totalPercentage += $section->completion_percentage;
            }
            
            $overallCompletion = $sectionCount > 0 ? $totalPercentage / $sectionCount : 0;
            
            return [
                'overall_percentage' => round($overallCompletion, 2),
                'sections' => $sections,
                'deployment_ready' => $overallCompletion >= 80
            ];
        } catch (PDOException $e) {
            error_log("Error calculating profile completion: " . $e->getMessage());
            return ['overall_percentage' => 0, 'sections' => [], 'deployment_ready' => false];
        }
    }
    
    /**
     * Update profile completion tracking
     */
    private function updateProfileCompletion() {
        try {
            $profile = $this->getUserProfile();
            
            // Define sections and their required fields
            $sections = [
                'personal_info' => ['first_name', 'last_name', 'DOB', 'gender', 'email', 'tel', 'address', 'NRC', 'bloodGp', 'marital'],
                'service_record' => ['rank_id', 'unit_id', 'corps', 'attestDate', 'service_number'],
                'emergency_contact' => ['emergency_contact_name', 'emergency_contact_phone', 'emergency_contact_relationship'],
                'security_clearance' => ['security_clearance_level', 'clearance_expiry_date'],
                'medical_info' => ['medical_status', 'medical_expiry_date']
            ];
            
            foreach ($sections as $section => $fields) {
                $completedFields = 0;
                $totalFields = count($fields);
                
                foreach ($fields as $field) {
                    if (!empty($profile->$field)) {
                        $completedFields++;
                    }
                }
                
                $percentage = ($completedFields / $totalFields) * 100;
                
                // Update or insert completion record
                $stmt = $this->pdo->prepare("
                    INSERT INTO profile_completion 
                    (staff_id, section, completion_percentage, required_fields_total, completed_fields)
                    VALUES (?, ?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE
                    completion_percentage = VALUES(completion_percentage),
                    completed_fields = VALUES(completed_fields),
                    last_updated_date = CURRENT_TIMESTAMP
                ");
                $stmt->execute([$this->userId, $section, $percentage, $totalFields, $completedFields]);
            }
        } catch (PDOException $e) {
            error_log("Error updating profile completion: " . $e->getMessage());
        }
    }
    
    /**
     * Log profile changes for audit trail
     */
    private function logProfileChanges($oldProfile, $newData) {
        try {
            foreach ($newData as $field => $newValue) {
                $oldValue = $oldProfile->$field ?? null;
                
                if ($oldValue != $newValue) {
                    $stmt = $this->pdo->prepare("
                        INSERT INTO profile_audit_log 
                        (staff_id, changed_by_staff_id, table_name, record_id, field_name, old_value, new_value, change_type, ip_address, user_agent, session_id)
                        VALUES (?, ?, 'staff', ?, ?, ?, ?, 'UPDATE', ?, ?, ?)
                    ");
                    $stmt->execute([
                        $this->userId,
                        $_SESSION['user_id'] ?? $this->userId,
                        $this->userId,
                        $field,
                        $oldValue,
                        $newValue,
                        $_SERVER['REMOTE_ADDR'] ?? '',
                        $_SERVER['HTTP_USER_AGENT'] ?? '',
                        session_id()
                    ]);
                }
            }
        } catch (PDOException $e) {
            error_log("Error logging profile changes: " . $e->getMessage());
        }
    }
    
    /**
     * Calculate age from birth date
     */
    private function calculateAge($dob) {
        if (!$dob) return null;
        
        $birthDate = new DateTime($dob);
        $today = new DateTime();
        $age = $today->diff($birthDate);
        
        return $age->y;
    }
    
    /**
     * Calculate service years from attestation date
     */
    private function calculateServiceYears($attestDate) {
        if (!$attestDate) return 'N/A';
        
        $attestDateTime = new DateTime($attestDate);
        $now = new DateTime();
        $service = $now->diff($attestDateTime);
        
        $years = $service->y;
        $months = $service->m;
        
        if ($years > 0) {
            return $years . ' years' . ($months > 0 ? ", {$months} months" : '');
        } else {
            return $months . ' months';
        }
    }
    
    /**
     * Generate CSRF token for forms
     */
    public function generateCSRFToken() {
        $token = bin2hex(random_bytes(32));
        
        try {
            // Clean up old tokens
            $stmt = $this->pdo->prepare("DELETE FROM csrf_tokens WHERE expires_at < NOW() OR used = 1");
            $stmt->execute();
            
            // Insert new token
            $stmt = $this->pdo->prepare("
                INSERT INTO csrf_tokens (token, staff_id, expires_at)
                VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 30 MINUTE))
            ");
            $stmt->execute([$token, $this->userId]);
            
            return $token;
        } catch (PDOException $e) {
            error_log("Error generating CSRF token: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Validate CSRF token
     */
    public function validateCSRFToken($token) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT id FROM csrf_tokens 
                WHERE token = ? AND staff_id = ? AND expires_at > NOW() AND used = 0
            ");
            $stmt->execute([$token, $this->userId]);
            $result = $stmt->fetch();
            
            if ($result) {
                // Mark token as used
                $stmt = $this->pdo->prepare("UPDATE csrf_tokens SET used = 1 WHERE id = ?");
                $stmt->execute([$result['id']]);
                return true;
            }
            
            return false;
        } catch (PDOException $e) {
            error_log("Error validating CSRF token: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get validation errors
     */
    public function getErrors() {
        return $this->errors;
    }
}
?>