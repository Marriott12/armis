<?php
/**
 * Enhanced Military-Grade Profile Manager Class
 * Simplified version for testing
 */

require_once dirname(__DIR__, 2) . '/shared/database_connection.php';

class EnhancedProfileManager {
    private $pdo;
    private $userId;
    private $userProfile;
    
    public function __construct($userId) {
        $this->pdo = getDbConnection();
        $this->userId = (int)$userId;
        $this->loadUserProfile();
    }
    
    /**
     * Load user profile
     */
    private function loadUserProfile() {
        try {
            $stmt = $this->pdo->prepare("
                SELECT s.*, 
                       r.name as rank_name, r.abbreviation as rank_abbr,
                       u.name as unit_name, u.code as unit_code
                FROM staff s 
                LEFT JOIN ranks r ON s.rank_id = r.id
                LEFT JOIN units u ON s.unit_id = u.id
                WHERE s.id = ?
            ");
            $stmt->execute([$this->userId]);
            $this->userProfile = $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error loading user profile: " . $e->getMessage());
            $this->userProfile = null;
        }
    }
    
    /**
     * Get complete profile data
     */
    public function getCompleteProfile() {
        return $this->userProfile;
    }
    
    /**
     * Get personal information
     */
    public function getPersonalInfo() {
        if (!$this->userProfile) return null;
        
        return [
            'first_name' => $this->userProfile['first_name'] ?? '',
            'last_name' => $this->userProfile['last_name'] ?? '',
            'email' => $this->userProfile['email'] ?? '',
            'service_number' => $this->userProfile['service_number'] ?? ''
        ];
    }
    
    /**
     * Get contact information
     */
    public function getContactInfo() {
        if (!$this->userProfile) return null;
        
        return [
            'email' => $this->userProfile['email'] ?? '',
            'phone' => $this->userProfile['phone'] ?? '',
            'address' => $this->userProfile['address'] ?? ''
        ];
    }
    
    /**
     * Get military information
     */
    public function getMilitaryInfo() {
        if (!$this->userProfile) return null;
        
        return [
            'service_number' => $this->userProfile['service_number'] ?? '',
            'rank_name' => $this->userProfile['rank_name'] ?? '',
            'rank_abbr' => $this->userProfile['rank_abbr'] ?? '',
            'unit_name' => $this->userProfile['unit_name'] ?? '',
            'unit_code' => $this->userProfile['unit_code'] ?? '',
            'corps' => $this->userProfile['corps'] ?? ''
        ];
    }
    
    /**
     * Get family information (placeholder)
     */
    public function getFamilyInfo() {
        return [];
    }
    
    /**
     * Get emergency contacts (placeholder)
     */
    public function getEmergencyContacts() {
        return [];
    }
    
    /**
     * Update profile information
     */
    public function updateProfile($data) {
        try {
            // Basic validation
            if (empty($data['first_name']) || empty($data['last_name'])) {
                return ['success' => false, 'message' => 'First name and last name are required'];
            }
            
            $stmt = $this->pdo->prepare("
                UPDATE staff 
                SET first_name = ?, last_name = ?, email = ?, updated_at = CURRENT_TIMESTAMP
                WHERE id = ?
            ");
            
            $result = $stmt->execute([
                $data['first_name'],
                $data['last_name'],
                $data['email'] ?? null,
                $this->userId
            ]);
            
            if ($result) {
                $this->loadUserProfile(); // Reload profile data
                return ['success' => true, 'message' => 'Profile updated successfully'];
            }
            
            return ['success' => false, 'message' => 'Failed to update profile'];
            
        } catch (PDOException $e) {
            error_log("Error updating profile: " . $e->getMessage());
            return ['success' => false, 'message' => 'Database error'];
        }
    }
    
    /**
     * Update personal information
     */
    public function updatePersonalInfo($data) {
        return $this->updateProfile($data);
    }
    
    /**
     * Update contact information
     */
    public function updateContactInfo($data) {
        return $this->updateProfile($data);
    }
    
    /**
     * Update military information
     */
    public function updateMilitaryInfo($data) {
        try {
            $stmt = $this->pdo->prepare("
                UPDATE staff 
                SET rank_id = ?, unit_id = ?, corps = ?, updated_at = CURRENT_TIMESTAMP
                WHERE id = ?
            ");
            
            $result = $stmt->execute([
                $data['rank_id'] ?? null,
                $data['unit_id'] ?? null,
                $data['corps'] ?? null,
                $this->userId
            ]);
            
            if ($result) {
                $this->loadUserProfile();
                return ['success' => true, 'message' => 'Military information updated successfully'];
            }
            
            return ['success' => false, 'message' => 'Failed to update military information'];
            
        } catch (PDOException $e) {
            error_log("Error updating military info: " . $e->getMessage());
            return ['success' => false, 'message' => 'Database error'];
        }
    }
    
    /**
     * Add family member (placeholder)
     */
    public function addFamilyMember($data) {
        return ['success' => true, 'message' => 'Family member functionality not implemented'];
    }
    
    /**
     * Update family member (placeholder)
     */
    public function updateFamilyMember($data) {
        return ['success' => true, 'message' => 'Family member functionality not implemented'];
    }
    
    /**
     * Remove family member (placeholder)
     */
    public function removeFamilyMember($familyId) {
        return ['success' => true, 'message' => 'Family member functionality not implemented'];
    }
    
    /**
     * Add emergency contact (placeholder)
     */
    public function addEmergencyContact($data) {
        return ['success' => true, 'message' => 'Emergency contact functionality not implemented'];
    }
    
    /**
     * Update emergency contact (placeholder)
     */
    public function updateEmergencyContact($data) {
        return ['success' => true, 'message' => 'Emergency contact functionality not implemented'];
    }
    
    /**
     * Remove emergency contact (placeholder)
     */
    public function removeEmergencyContact($contactId) {
        return ['success' => true, 'message' => 'Emergency contact functionality not implemented'];
    }
    
    /**
     * Get profile completion status
     */
    public function getProfileCompletionStatus() {
        if (!$this->userProfile) {
            return ['percentage' => 0, 'missing_fields' => ['All profile data']];
        }
        
        $requiredFields = ['first_name', 'last_name', 'email', 'service_number', 'rank_id', 'unit_id'];
        $completedFields = 0;
        $missingFields = [];
        
        foreach ($requiredFields as $field) {
            if (!empty($this->userProfile[$field])) {
                $completedFields++;
            } else {
                $missingFields[] = $field;
            }
        }
        
        $percentage = round(($completedFields / count($requiredFields)) * 100);
        
        return [
            'percentage' => $percentage,
            'completed_fields' => $completedFields,
            'total_fields' => count($requiredFields),
            'missing_fields' => $missingFields
        ];
    }
}