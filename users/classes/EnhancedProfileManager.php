<?php
/**
 * Enhanced Military-Grade Profile Manager Class
 * Comprehensive user profile management with military-specific features
 * Enhanced Profile Manager Class
 * Military-grade user profile management with comprehensive security and performance optimizations
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
    private $userProfile;
    private $auditService;
    private $cache = [];
    private $cacheExpiry = 300; // 5 minutes
    
    public function __construct($userId) {
        $this->pdo = getDbConnection();
        $this->userId = (int)$userId;
        $this->auditService = new SecurityAuditService();
        $this->loadUserProfile();
    }
    
    /**
     * Load user profile with comprehensive data
     */
    private function loadUserProfile() {
        $cacheKey = "user_profile_{$this->userId}";
        
        // Check cache first
        if (isset($this->cache[$cacheKey]) && 
            time() - $this->cache[$cacheKey]['timestamp'] < $this->cacheExpiry) {
            $this->userProfile = $this->cache[$cacheKey]['data'];
            return;
        }
        
        try {
            $stmt = $this->pdo->prepare("
                SELECT s.*, 
                       r.name as rank_name, r.abbreviation as rank_abbr,
                       u.name as unit_name, u.code as unit_code, u.type as unit_type,
                       c.name as corps_name, c.abbreviation as corps_abbr
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
            $this->userProfile = $stmt->fetch(PDO::FETCH_OBJ);
            
            // Cache the result
            $this->cache[$cacheKey] = [
                'data' => $this->userProfile,
                'timestamp' => time()
            ];
            
            if (!$this->userProfile) {
                throw new Exception("User profile not found or inactive");
            }
            
        } catch (PDOException $e) {
            error_log("Error loading user profile: " . $e->getMessage());
            throw new Exception("Failed to load user profile");
        }
    }
    
    /**
     * Get comprehensive user profile data
     */
    public function getUserProfile() {
        if (!$this->userProfile) {
            $this->loadUserProfile();
        }
        
        // Calculate dynamic fields
        $profile = clone $this->userProfile;
        $profile->age = $this->calculateAge($profile->date_of_birth ?? null);
        $profile->service_years = $this->calculateServiceYears($profile->enlistment_date ?? null);
        $profile->full_name = $this->formatFullName($profile);
        $profile->display_rank = $profile->rank_name ?? $profile->rank_abbr ?? 'N/A';
        $profile->profile_completion = $this->calculateProfileCompleteness();
        
        // Add legacy field mappings for compatibility
        $profile->fname = $profile->first_name;
        $profile->lname = $profile->last_name;
        $profile->svcNo = $profile->service_number;
        $profile->DOB = $profile->date_of_birth;
        $profile->NRC = $profile->national_id;
        $profile->attestDate = $profile->enlistment_date;
        $profile->rankID = $profile->rank_id;
        $profile->unitID = $profile->unit_id;
        $profile->tel = $profile->phone;
        $profile->bloodGp = $profile->blood_group;
        $profile->marital = $profile->marital_status;
        $profile->svcStatus = $profile->service_status;
        
        return $profile;
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
=======
    public function updateProfile($data, $validateOnly = false) {
        try {
            // Comprehensive input validation
            $validationResult = $this->validateProfileData($data);
            if (!$validationResult['valid']) {
                return [
                    'success' => false,
                    'errors' => $validationResult['errors'],
                    'message' => 'Validation failed'
                ];
            }
            
            if ($validateOnly) {
                return ['success' => true, 'message' => 'Validation passed'];
            }
            
            $this->pdo->beginTransaction();
            
            // Get current data for audit log
            $oldData = $this->getUserProfile();
            
            // Prepare update fields
            $updateFields = [];
            $updateValues = [];
            $allowedFields = [
                'first_name', 'middle_name', 'last_name', 'email', 'phone',
                'date_of_birth', 'gender', 'marital_status', 'religion',
                'blood_group', 'height', 'nationality', 'place_of_birth',
                'emergency_contact_name', 'emergency_contact_phone', 
                'emergency_contact_relationship', 'notes'
            ];
            
            foreach ($allowedFields as $field) {
                if (isset($data[$field])) {
                    $updateFields[] = "{$field} = ?";
                    $updateValues[] = $data[$field];
                }
            }
            
            if (empty($updateFields)) {
                $this->pdo->rollBack();
                return ['success' => false, 'message' => 'No valid fields to update'];
            }
            
            // Add updated timestamp
            $updateFields[] = "updated_at = NOW()";
            $updateValues[] = $this->userId;
            
            // Execute update
            $sql = "UPDATE staff SET " . implode(', ', $updateFields) . " WHERE id = ?";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($updateValues);
            
            // Update profile completion percentage
            $this->updateProfileCompleteness();
            
            // Audit logging
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
                (array)$oldData,
                $data
            );
            
            $this->pdo->commit();
            
            // Clear cache
            $this->clearCache();
            
            return [
                'success' => true,
                'message' => 'Profile updated successfully',
                'completion' => $this->calculateProfileCompleteness()
            ];
            
        } catch (Exception $e) {
            $this->pdo->rollBack();
            error_log("Profile update error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Update failed: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Comprehensive input validation
     */
    private function validateProfileData($data) {
        $errors = [];
        
        // Email validation
        if (isset($data['email']) && !empty($data['email'])) {
            if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
                $errors['email'] = 'Invalid email format';
            } else {
                // Check for duplicate email
                $stmt = $this->pdo->prepare("SELECT id FROM staff WHERE email = ? AND id != ?");
                $stmt->execute([$data['email'], $this->userId]);
                if ($stmt->fetch()) {
                    $errors['email'] = 'Email already exists';
                }
            }
        }
        
        // Phone validation
        if (isset($data['phone']) && !empty($data['phone'])) {
            if (!preg_match('/^\+?[0-9\-\(\)\s]{8,20}$/', $data['phone'])) {
                $errors['phone'] = 'Invalid phone number format';
            }
        }
        
        // Date of birth validation
        if (isset($data['date_of_birth']) && !empty($data['date_of_birth'])) {
            $dob = DateTime::createFromFormat('Y-m-d', $data['date_of_birth']);
            if (!$dob || $dob->format('Y-m-d') !== $data['date_of_birth']) {
                $errors['date_of_birth'] = 'Invalid date format';
            } else {
                $age = $this->calculateAge($data['date_of_birth']);
                if ($age < 16 || $age > 70) {
                    $errors['date_of_birth'] = 'Age must be between 16 and 70';
                }
            }
        }
        
        // Height validation
        if (isset($data['height']) && !empty($data['height'])) {
            $height = (float)$data['height'];
            if ($height < 100 || $height > 250) {
                $errors['height'] = 'Height must be between 100 and 250 cm';
            }
        }
        
        // National ID validation
        if (isset($data['national_id']) && !empty($data['national_id'])) {
            if (!preg_match('/^[0-9]{6}\/[0-9]{2}\/[0-9]$/', $data['national_id'])) {
                $errors['national_id'] = 'Invalid National ID format (should be 123456/78/9)';
            }
        }
        
        // Gender validation
        if (isset($data['gender']) && !empty($data['gender'])) {
            if (!in_array($data['gender'], ['Male', 'Female', 'Other'])) {
                $errors['gender'] = 'Invalid gender selection';
            }
        }
        
        // Blood group validation
        if (isset($data['blood_group']) && !empty($data['blood_group'])) {
            if (!in_array($data['blood_group'], ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'])) {
                $errors['blood_group'] = 'Invalid blood group';
            }
        }
        
        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }
    
    /**
     * Military-specific rank progression tracking
     */
    public function getRankProgression() {
        try {
            $currentRank = $this->userProfile->rank_id;
            if (!$currentRank) {
                return null;
            }
            
            // Get current rank details
            $stmt = $this->pdo->prepare("SELECT * FROM ranks WHERE id = ?");
            $stmt->execute([$currentRank]);
            $current = $stmt->fetch(PDO::FETCH_OBJ);
            
            if (!$current) {
                return null;
            }
            
            // Try to get next rank in progression (if rank_order exists)
            $next = null;
            try {
                $stmt = $this->pdo->prepare("
                    SELECT * FROM ranks 
                    WHERE rank_order > ? AND is_active = TRUE
                    ORDER BY rank_order ASC LIMIT 1
                ");
                $stmt->execute([$current->rank_order ?? 0]);
                $next = $stmt->fetch(PDO::FETCH_OBJ);
            } catch (Exception $e) {
                // rank_order column may not exist yet
                error_log("Rank progression query failed (rank_order column missing): " . $e->getMessage());
            }
            
            // Get rank history
            $history = [];
            try {
                $stmt = $this->pdo->prepare("
                    SELECT r.name, r.abbreviation, a.timestamp
                    FROM audit_log a
                    JOIN ranks r ON r.id = a.new_value
                    WHERE a.user_id = ? AND a.table_name = 'staff' AND a.field_name = 'rank_id'
                    ORDER BY a.timestamp DESC
                    LIMIT 10
                ");
                $stmt->execute([$this->userId]);
                $history = $stmt->fetchAll(PDO::FETCH_OBJ);
            } catch (Exception $e) {
                // audit_log may not have rank history yet
                error_log("Rank history query failed: " . $e->getMessage());
            }
            
            return [
                'current' => $current,
                'next' => $next,
                'history' => $history,
                'eligible_for_promotion' => $this->checkPromotionEligibility()
            ];
            
        } catch (Exception $e) {
            error_log("Rank progression error: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Security clearance management
     */
    public function getSecurityClearance() {
        $profile = $this->getUserProfile();
        
        return [
            'level' => $profile->security_clearance_level ?? 'None',
            'expiry_date' => $profile->clearance_expiry_date,
            'days_until_expiry' => $this->calculateDaysUntilExpiry($profile->clearance_expiry_date),
            'status' => $this->getClearanceStatus($profile->clearance_expiry_date),
            'renewal_required' => $this->isClearanceRenewalRequired($profile->clearance_expiry_date)
        ];
    }
    
    /**
     * Medical readiness tracking
     */
    public function getMedicalReadiness() {
        $profile = $this->getUserProfile();
        
        return [
            'fitness_status' => $profile->medical_fitness_status ?? 'Unknown',
            'last_exam' => $profile->last_medical_exam,
            'next_due' => $profile->next_medical_due,
            'days_until_due' => $this->calculateDaysUntilExpiry($profile->next_medical_due),
            'status' => $this->getMedicalStatus($profile->next_medical_due),
            'exam_required' => $this->isMedicalExamRequired($profile->next_medical_due)
        ];
    }
    
    /**
     * Get family members with enhanced data
     */
    public function getFamilyMembers() {
        try {
            $stmt = $this->pdo->prepare("
                SELECT * FROM staff_family_members 
                WHERE staff_id = ? 
                ORDER BY is_emergency_contact DESC, relationship ASC, name ASC
            ");
            $stmt->execute([$this->userId]);
            return $stmt->fetchAll(PDO::FETCH_OBJ);
        } catch (Exception $e) {
            error_log("Error fetching family members: " . $e->getMessage());
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
     * Get contact information
     */
    public function getContactInfo() {
        try {
            $stmt = $this->pdo->prepare("
                SELECT * FROM staff_contact_info 
                WHERE staff_id = ? 
                ORDER BY is_primary DESC, contact_type ASC
            ");
            $stmt->execute([$this->userId]);
            return $stmt->fetchAll(PDO::FETCH_OBJ);
        } catch (Exception $e) {
            error_log("Error fetching contact info: " . $e->getMessage());
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
     * Get deployment history
     */
    public function getDeploymentHistory() {
        try {
            $stmt = $this->pdo->prepare("
                SELECT * FROM staff_deployments 
                WHERE staff_id = ? 
                ORDER BY start_date DESC
            ");
            $stmt->execute([$this->userId]);
            return $stmt->fetchAll(PDO::FETCH_OBJ);
        } catch (Exception $e) {
            error_log("Error fetching deployment history: " . $e->getMessage());
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
     * Get training records
     */
    public function getTrainingRecords() {
        try {
            $stmt = $this->pdo->prepare("
                SELECT * FROM training_records 
                WHERE staff_id = ? 
                ORDER BY completion_date DESC
            ");
            $stmt->execute([$this->userId]);
            return $stmt->fetchAll(PDO::FETCH_OBJ);
        } catch (Exception $e) {
            // Table may not exist yet, return empty array
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
     * Calculate profile completeness percentage
     */
    public function calculateProfileCompleteness() {
        $profile = $this->userProfile;
        
        $requiredFields = [
            'first_name', 'last_name', 'email', 'phone', 'date_of_birth',
            'gender', 'national_id', 'rank_id', 'unit_id', 'blood_group',
            'marital_status', 'emergency_contact_name', 'emergency_contact_phone'
        ];
        
        $completedFields = 0;
        foreach ($requiredFields as $field) {
            if (!empty($profile->$field)) {
                $completedFields++;
            }
        }
        
        $basePercentage = ($completedFields / count($requiredFields)) * 80;
        
        // Bonus points for additional data
        $bonusPoints = 0;
        if (count($this->getFamilyMembers()) > 0) $bonusPoints += 5;
        if (count($this->getContactInfo()) > 0) $bonusPoints += 5;
        if (count($this->getDeploymentHistory()) > 0) $bonusPoints += 5;
        if (count($this->getTrainingRecords()) > 0) $bonusPoints += 5;
        
        return min(100, round($basePercentage + $bonusPoints));
    }
    
    /**
     * Update profile completion percentage in database
     */
    private function updateProfileCompleteness() {
        $percentage = $this->calculateProfileCompleteness();
        $stmt = $this->pdo->prepare("UPDATE staff SET profile_completion_percentage = ? WHERE id = ?");
        $stmt->execute([$percentage, $this->userId]);
    }
    
    /**
     * Calculate age from date of birth
     */
    private function calculateAge($dateOfBirth) {
        if (!$dateOfBirth) return 'N/A';
        
        try {
            $dob = new DateTime($dateOfBirth);
            $now = new DateTime();
            return $now->diff($dob)->y;
        } catch (Exception $e) {
            return 'N/A';
        }
    }
    
    /**
     * Calculate years of service
     */
    private function calculateServiceYears($enlistmentDate) {
        if (!$enlistmentDate) return 'N/A';
        
        try {
            $enlisted = new DateTime($enlistmentDate);
            $now = new DateTime();
            $diff = $now->diff($enlisted);
            
            if ($diff->y > 0) {
                return $diff->y . ' years' . ($diff->m > 0 ? ", {$diff->m} months" : '');
            } else {
                return $diff->m . ' months';
            }
        } catch (Exception $e) {
            return 'N/A';
        }
    }
    
    /**
     * Format full name with proper military protocol
     */
    private function formatFullName($profile) {
        $parts = array_filter([
            $profile->prefix ?? '',
            $profile->first_name ?? '',
            $profile->middle_name ?? '',
            $profile->last_name ?? '',
            $profile->suffix ?? ''
        ]);
        
        return implode(' ', $parts);
    }
    
    /**
     * Check promotion eligibility
     */
    private function checkPromotionEligibility() {
        // Simplified eligibility check - can be enhanced with military rules
        $serviceYears = $this->calculateAge($this->userProfile->enlistment_date ?? null);
        $hasTraining = count($this->getTrainingRecords()) > 0;
        $hasGoodRecord = true; // Can check disciplinary records
        
        return [
            'eligible' => ($serviceYears >= 2 && $hasTraining && $hasGoodRecord),
            'reasons' => [],
            'requirements' => [
                'minimum_service' => '2 years',
                'training_required' => 'Leadership course',
                'performance_rating' => 'Meets expectations or above'
            ]
        ];
    }
    
    /**
     * Calculate days until expiry
     */
    private function calculateDaysUntilExpiry($expiryDate) {
        if (!$expiryDate) return null;
        
        try {
            $expiry = new DateTime($expiryDate);
            $now = new DateTime();
            $diff = $now->diff($expiry);
            
            return $expiry > $now ? $diff->days : -$diff->days;
        } catch (Exception $e) {
            return null;
        }
    }
    
    /**
     * Get clearance status
     */
    private function getClearanceStatus($expiryDate) {
        $days = $this->calculateDaysUntilExpiry($expiryDate);
        
        if ($days === null) return 'Unknown';
        if ($days < 0) return 'Expired';
        if ($days <= 30) return 'Expiring Soon';
        if ($days <= 90) return 'Due for Renewal';
        
        return 'Active';
    }
    
    /**
     * Check if clearance renewal is required
     */
    private function isClearanceRenewalRequired($expiryDate) {
        $days = $this->calculateDaysUntilExpiry($expiryDate);
        return $days !== null && $days <= 90;
    }
    
    /**
     * Get medical status
     */
    private function getMedicalStatus($dueDate) {
        $days = $this->calculateDaysUntilExpiry($dueDate);
        
        if ($days === null) return 'Unknown';
        if ($days < 0) return 'Overdue';
        if ($days <= 30) return 'Due Soon';
        if ($days <= 90) return 'Upcoming';
        
        return 'Current';
    }
    
    /**
     * Check if medical exam is required
     */
    private function isMedicalExamRequired($dueDate) {
        $days = $this->calculateDaysUntilExpiry($dueDate);
        return $days !== null && $days <= 30;
    }
    
    /**
     * Clear cache
     */
    private function clearCache() {
        $this->cache = [];
        $this->userProfile = null;
    }
    
    /**
     * Get profile photo URL with fallback
     */
    public function getProfilePhotoURL() {
        $profile = $this->getUserProfile();
        
        if (!empty($profile->profile_photo)) {
            $photoPath = '/users/uploads/profile_photos/' . $profile->profile_photo;
            if (file_exists(dirname(__DIR__) . $photoPath)) {
                return $photoPath;
            }
        }
        
        // Try service number based photo
        if (!empty($profile->service_number)) {
            $uploadDir = dirname(__DIR__) . '/uploads/profile_photos/';
            $extensions = ['jpg', 'jpeg', 'png', 'gif'];
            
            foreach ($extensions as $ext) {
                $filename = $profile->service_number . '.' . $ext;
                $filepath = $uploadDir . $filename;
                
                if (file_exists($filepath)) {
                    return '/users/uploads/profile_photos/' . $filename;
                }
            }
        }
        
        // Return default avatar with initials
        $initials = substr($profile->first_name ?? '', 0, 1) . substr($profile->last_name ?? '', 0, 1);
        return '/shared/default_avatar.php?name=' . urlencode($initials);
    }
    
    /**
     * Bulk profile update with validation
     */
    public function bulkUpdateProfile($sections) {
        $results = [];
        $hasErrors = false;
        
        foreach ($sections as $section => $data) {
            $result = $this->updateProfile($data, false);
            $results[$section] = $result;
            
            if (!$result['success']) {
                $hasErrors = true;
            }
        }
        
        return [
            'success' => !$hasErrors,
            'results' => $results,
            'completion' => $this->calculateProfileCompleteness()
        ];
    }
    
    /**
     * Export profile data for backup or transfer
     */
    public function exportProfileData() {
        return [
            'profile' => $this->getUserProfile(),
            'family' => $this->getFamilyMembers(),
            'contact' => $this->getContactInfo(),
            'deployments' => $this->getDeploymentHistory(),
            'training' => $this->getTrainingRecords(),
            'rank_progression' => $this->getRankProgression(),
            'security_clearance' => $this->getSecurityClearance(),
            'medical_readiness' => $this->getMedicalReadiness(),
            'export_date' => date('Y-m-d H:i:s'),
            'export_by' => $this->userId
        ];
    }
}
