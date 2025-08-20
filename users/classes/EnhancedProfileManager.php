<?php
/**
 * Enhanced Profile Manager Class
 * Military-grade user profile management with comprehensive security and performance optimizations
 */

require_once dirname(__DIR__, 2) . '/shared/database_connection.php';
require_once dirname(__DIR__, 2) . '/shared/security_audit_service.php';

class EnhancedProfileManager {
    private $pdo;
    private $userId;
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