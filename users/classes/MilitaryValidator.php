<?php
/**
 * Military Validator for ARMIS User Profile Module
 * Handles military-specific validations and data integrity
 */

class MilitaryValidator {
    
    private $pdo;
    private $militaryRanks;
    private $securityClearanceLevels;
    private $militaryUnits;
    
    public function __construct($pdo = null) {
        $this->pdo = $pdo ?: getDbConnection();
        $this->loadMilitaryData();
    }
    
    /**
     * Load military reference data
     */
    private function loadMilitaryData() {
        try {
            // Load ranks
            $stmt = $this->pdo->query("SELECT id, name, abbreviation FROM ranks ORDER BY id");
            $this->militaryRanks = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Load units
            $stmt = $this->pdo->query("SELECT id, name, code FROM units ORDER BY name");
            $this->militaryUnits = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Define security clearance levels
            $this->securityClearanceLevels = [
                'Confidential' => ['level' => 1, 'authority' => ['Military Intelligence', 'Command']],
                'Secret' => ['level' => 2, 'authority' => ['Military Intelligence', 'Command', 'Defense Ministry']],
                'Top Secret' => ['level' => 3, 'authority' => ['Defense Ministry', 'National Security']],
                'SCI' => ['level' => 4, 'authority' => ['National Security', 'Intelligence Services']],
                'Cosmic Top Secret' => ['level' => 5, 'authority' => ['National Security', 'Intelligence Services', 'NATO']]
            ];
            
        } catch (Exception $e) {
            error_log("Error loading military data: " . $e->getMessage());
            $this->militaryRanks = [];
            $this->militaryUnits = [];
        }
    }
    
    /**
     * Validate service number format and uniqueness
     */
    public function validateServiceNumber($serviceNumber, $excludeStaffId = null) {
        $result = [
            'valid' => false,
            'errors' => [],
            'formatted_number' => null,
            'suggestions' => []
        ];
        
        try {
            // Basic format validation
            $serviceNumber = strtoupper(trim($serviceNumber));
            
            // Expected format: 2 letters + 6-8 digits
            if (!preg_match('/^[A-Z]{2}[0-9]{6,8}$/', $serviceNumber)) {
                $result['errors'][] = "Service number must be 2 letters followed by 6-8 digits (e.g., ZA123456)";
                
                // Suggest corrections
                if (strlen($serviceNumber) < 8) {
                    $result['suggestions'][] = "Service number appears too short";
                } elseif (strlen($serviceNumber) > 10) {
                    $result['suggestions'][] = "Service number appears too long";
                }
                
                return $result;
            }
            
            // Check uniqueness in database
            $sql = "SELECT id, first_name, last_name FROM staff WHERE service_number = ?";
            $params = [$serviceNumber];
            
            if ($excludeStaffId) {
                $sql .= " AND id != ?";
                $params[] = $excludeStaffId;
            }
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            $existing = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($existing) {
                $result['errors'][] = "Service number already exists for {$existing['first_name']} {$existing['last_name']}";
                return $result;
            }
            
            // Validate service number format based on military standards
            $prefix = substr($serviceNumber, 0, 2);
            $validPrefixes = ['ZA', 'ZM', 'MF', 'AF', 'NV', 'AR']; // Zambian Armed Forces prefixes
            
            if (!in_array($prefix, $validPrefixes)) {
                $result['suggestions'][] = "Consider using standard prefixes: " . implode(', ', $validPrefixes);
            }
            
            $result['valid'] = true;
            $result['formatted_number'] = $serviceNumber;
            
        } catch (Exception $e) {
            $result['errors'][] = "Error validating service number: " . $e->getMessage();
            error_log("Service number validation error: " . $e->getMessage());
        }
        
        return $result;
    }
    
    /**
     * Validate rank progression and eligibility
     */
    public function validateRankProgression($currentRankId, $newRankId, $staffId = null) {
        $result = [
            'valid' => false,
            'errors' => [],
            'warnings' => [],
            'promotion_eligible' => false,
            'requirements' => []
        ];
        
        try {
            // Get rank details
            $currentRank = $this->getRankById($currentRankId);
            $newRank = $this->getRankById($newRankId);
            
            if (!$currentRank || !$newRank) {
                $result['errors'][] = "Invalid rank specified";
                return $result;
            }
            
            // Check if promotion is logical (simplified logic)
            $rankOrder = $this->getRankOrder();
            $currentOrder = array_search($currentRankId, $rankOrder);
            $newOrder = array_search($newRankId, $rankOrder);
            
            if ($currentOrder === false || $newOrder === false) {
                $result['errors'][] = "Rank not found in progression order";
                return $result;
            }
            
            // Check if jumping too many ranks
            if ($newOrder > $currentOrder + 2) {
                $result['warnings'][] = "Promotion skips multiple ranks - requires justification";
            }
            
            // Check demotion
            if ($newOrder < $currentOrder) {
                $result['warnings'][] = "This is a demotion - requires administrative approval";
            }
            
            // Check service requirements (if staff ID provided)
            if ($staffId) {
                $serviceYears = $this->calculateServiceYears($staffId);
                $requirements = $this->getPromotionRequirements($newRankId);
                
                if ($serviceYears < $requirements['min_service_years']) {
                    $result['warnings'][] = "Insufficient service years for this rank";
                    $result['requirements'][] = "Minimum service: {$requirements['min_service_years']} years";
                }
            }
            
            $result['valid'] = empty($result['errors']);
            $result['promotion_eligible'] = empty($result['errors']) && empty($result['warnings']);
            
        } catch (Exception $e) {
            $result['errors'][] = "Error validating rank progression: " . $e->getMessage();
            error_log("Rank validation error: " . $e->getMessage());
        }
        
        return $result;
    }
    
    /**
     * Validate security clearance eligibility
     */
    public function validateSecurityClearance($clearanceLevel, $staffId, $issuingAuthority) {
        $result = [
            'valid' => false,
            'errors' => [],
            'warnings' => [],
            'requirements' => []
        ];
        
        try {
            // Check if clearance level exists
            if (!isset($this->securityClearanceLevels[$clearanceLevel])) {
                $result['errors'][] = "Invalid security clearance level";
                return $result;
            }
            
            $clearanceInfo = $this->securityClearanceLevels[$clearanceLevel];
            
            // Check issuing authority
            if (!in_array($issuingAuthority, $clearanceInfo['authority'])) {
                $result['errors'][] = "Invalid issuing authority for this clearance level";
                $result['requirements'][] = "Valid authorities: " . implode(', ', $clearanceInfo['authority']);
            }
            
            // Check staff eligibility
            $staffInfo = $this->getStaffSecurityProfile($staffId);
            
            // Check current clearance level
            if ($staffInfo['current_clearance_level'] && 
                $this->securityClearanceLevels[$staffInfo['current_clearance_level']]['level'] > $clearanceInfo['level']) {
                $result['warnings'][] = "Downgrading from higher clearance level";
            }
            
            // Check minimum rank requirements
            $minRankForClearance = $this->getMinimumRankForClearance($clearanceLevel);
            if ($staffInfo['rank_id'] < $minRankForClearance) {
                $result['warnings'][] = "Rank may be insufficient for this clearance level";
            }
            
            // Check service years
            if ($staffInfo['service_years'] < $this->getMinServiceYearsForClearance($clearanceLevel)) {
                $result['warnings'][] = "Insufficient service years for this clearance level";
            }
            
            $result['valid'] = empty($result['errors']);
            
        } catch (Exception $e) {
            $result['errors'][] = "Error validating security clearance: " . $e->getMessage();
            error_log("Security clearance validation error: " . $e->getMessage());
        }
        
        return $result;
    }
    
    /**
     * Validate medical fitness category
     */
    public function validateMedicalFitness($fitnessCategory, $staffId) {
        $result = [
            'valid' => false,
            'errors' => [],
            'warnings' => [],
            'deployment_status' => 'unknown'
        ];
        
        $validCategories = ['A1', 'A2', 'B1', 'B2', 'C1', 'C2', 'D'];
        
        if (!in_array($fitnessCategory, $validCategories)) {
            $result['errors'][] = "Invalid medical fitness category";
            return $result;
        }
        
        // Determine deployment eligibility
        switch ($fitnessCategory) {
            case 'A1':
            case 'A2':
                $result['deployment_status'] = 'fully_deployable';
                break;
            case 'B1':
            case 'B2':
                $result['deployment_status'] = 'limited_deployment';
                $result['warnings'][] = "Limited deployment capability";
                break;
            case 'C1':
            case 'C2':
                $result['deployment_status'] = 'home_duties_only';
                $result['warnings'][] = "Home duties only";
                break;
            case 'D':
                $result['deployment_status'] = 'unfit_for_duty';
                $result['warnings'][] = "Unfit for military duties";
                break;
        }
        
        $result['valid'] = true;
        return $result;
    }
    
    /**
     * Validate training compliance
     */
    public function validateTrainingCompliance($staffId) {
        $result = [
            'compliant' => false,
            'expired_training' => [],
            'due_training' => [],
            'compliance_score' => 0
        ];
        
        try {
            // Check training compliance (if table exists)
            $stmt = $this->pdo->prepare("
                SELECT training_category, course_name, completion_date, expiry_date, compliance_status
                FROM staff_training_compliance 
                WHERE staff_id = ?
                ORDER BY expiry_date ASC
            ");
            $stmt->execute([$staffId]);
            $training = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $total = count($training);
            $compliant = 0;
            
            foreach ($training as $course) {
                if ($course['compliance_status'] === 'Current') {
                    $compliant++;
                } elseif ($course['compliance_status'] === 'Expired') {
                    $result['expired_training'][] = $course;
                } elseif ($course['compliance_status'] === 'Due') {
                    $result['due_training'][] = $course;
                }
            }
            
            $result['compliance_score'] = $total > 0 ? round(($compliant / $total) * 100, 2) : 0;
            $result['compliant'] = $result['compliance_score'] >= 80; // 80% compliance threshold
            
        } catch (Exception $e) {
            error_log("Training compliance validation error: " . $e->getMessage());
        }
        
        return $result;
    }
    
    // Helper methods
    
    private function getRankById($rankId) {
        foreach ($this->militaryRanks as $rank) {
            if ($rank['id'] == $rankId) {
                return $rank;
            }
        }
        return null;
    }
    
    private function getRankOrder() {
        // Simplified rank order - should be configured based on military structure
        return [1, 2, 3, 4, 5, 6, 7, 8, 9, 10]; // rank IDs in progression order
    }
    
    private function calculateServiceYears($staffId) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT DATEDIFF(CURDATE(), created_at) / 365.25 as service_years 
                FROM staff WHERE id = ?
            ");
            $stmt->execute([$staffId]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result ? $result['service_years'] : 0;
        } catch (Exception $e) {
            return 0;
        }
    }
    
    private function getPromotionRequirements($rankId) {
        // Simplified requirements - should be configured
        $requirements = [
            1 => ['min_service_years' => 0],
            2 => ['min_service_years' => 1],
            3 => ['min_service_years' => 3],
            4 => ['min_service_years' => 5],
            5 => ['min_service_years' => 8],
        ];
        
        return $requirements[$rankId] ?? ['min_service_years' => 0];
    }
    
    private function getStaffSecurityProfile($staffId) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT s.rank_id, 
                       DATEDIFF(CURDATE(), s.created_at) / 365.25 as service_years,
                       sc.clearance_level as current_clearance_level
                FROM staff s
                LEFT JOIN staff_security_clearance sc ON s.id = sc.staff_id AND sc.status = 'Active'
                WHERE s.id = ?
            ");
            $stmt->execute([$staffId]);
            return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        } catch (Exception $e) {
            return [];
        }
    }
    
    private function getMinimumRankForClearance($clearanceLevel) {
        $minimumRanks = [
            'Confidential' => 1,
            'Secret' => 2,
            'Top Secret' => 3,
            'SCI' => 4,
            'Cosmic Top Secret' => 5
        ];
        
        return $minimumRanks[$clearanceLevel] ?? 1;
    }
    
    private function getMinServiceYearsForClearance($clearanceLevel) {
        $minimumYears = [
            'Confidential' => 1,
            'Secret' => 2,
            'Top Secret' => 5,
            'SCI' => 8,
            'Cosmic Top Secret' => 10
        ];
        
        return $minimumYears[$clearanceLevel] ?? 0;
    }
}
?>