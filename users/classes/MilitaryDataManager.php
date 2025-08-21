<?php
/**
 * Military Data Manager Class
 * Handles military-specific data operations (ranks, units, corps, etc.)
 */

require_once dirname(__DIR__, 2) . '/shared/database_connection.php';

class MilitaryDataManager {
    private $pdo;
    private $cache = [];
    private $cacheExpiry = 3600; // 1 hour
    
    public function __construct() {
        $this->pdo = getDbConnection();
    }
    
    /**
     * Get all active ranks with hierarchy
     */
    public function getRanks($category = null) {
        $cacheKey = 'ranks_' . ($category ?? 'all');
        
        if (isset($this->cache[$cacheKey])) {
            return $this->cache[$cacheKey];
        }
        
        try {
            $sql = "
                SELECT r.*, 
                       COUNT(s.id) as personnel_count
                FROM ranks r
                LEFT JOIN staff s ON r.id = s.rank_id AND s.accStatus = 'active'
                WHERE r.is_active = 1
            ";
            
            $params = [];
            if ($category) {
                $sql .= " AND r.rank_category = ?";
                $params[] = $category;
            }
            
            $sql .= " GROUP BY r.id ORDER BY r.rank_level ASC";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            $ranks = $stmt->fetchAll(PDO::FETCH_OBJ);
            
            $this->cache[$cacheKey] = $ranks;
            return $ranks;
            
        } catch (PDOException $e) {
            error_log("Error fetching ranks: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get rank by ID with details
     */
    public function getRankById($rankId) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT r.*,
                       COUNT(s.id) as personnel_count,
                       AVG(TIMESTAMPDIFF(YEAR, s.attestDate, CURDATE())) as avg_service_years
                FROM ranks r
                LEFT JOIN staff s ON r.id = s.rank_id AND s.accStatus = 'active'
                WHERE r.id = ? AND r.is_active = 1
                GROUP BY r.id
            ");
            $stmt->execute([$rankId]);
            return $stmt->fetch(PDO::FETCH_OBJ);
        } catch (PDOException $e) {
            error_log("Error fetching rank: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Get rank progression path for a specific rank
     */
    public function getRankProgression($currentRankId) {
        try {
            $currentRank = $this->getRankById($currentRankId);
            if (!$currentRank) return [];
            
            // Get next possible ranks in the same category
            $stmt = $this->pdo->prepare("
                SELECT * FROM ranks 
                WHERE rank_category = ? 
                AND rank_level > ? 
                AND is_active = 1
                ORDER BY rank_level ASC
                LIMIT 3
            ");
            $stmt->execute([$currentRank->rank_category, $currentRank->rank_level]);
            return $stmt->fetchAll(PDO::FETCH_OBJ);
            
        } catch (PDOException $e) {
            error_log("Error fetching rank progression: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get all active units with hierarchy
     */
    public function getUnits($parentUnitId = null) {
        $cacheKey = 'units_' . ($parentUnitId ?? 'all');
        
        if (isset($this->cache[$cacheKey])) {
            return $this->cache[$cacheKey];
        }
        
        try {
            $sql = "
                SELECT u.*, 
                       pu.name as parent_unit_name,
                       c.first_name as commander_fname, c.last_name as commander_lname,
                       c.rank_id as commander_rank_id, r.abbreviation as commander_rank,
                       COUNT(s.id) as personnel_count
                FROM units u
                LEFT JOIN units pu ON u.parent_unit_id = pu.id
                LEFT JOIN staff c ON u.commander_id = c.id
                LEFT JOIN ranks r ON c.rank_id = r.id
                LEFT JOIN staff s ON u.id = s.unit_id AND s.accStatus = 'active'
                WHERE u.is_active = 1
            ";
            
            $params = [];
            if ($parentUnitId !== null) {
                $sql .= " AND u.parent_unit_id = ?";
                $params[] = $parentUnitId;
            }
            
            $sql .= " GROUP BY u.id ORDER BY u.unit_type, u.name";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            $units = $stmt->fetchAll(PDO::FETCH_OBJ);
            
            $this->cache[$cacheKey] = $units;
            return $units;
            
        } catch (PDOException $e) {
            error_log("Error fetching units: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get unit hierarchy tree
     */
    public function getUnitHierarchy() {
        try {
            $units = $this->getUnits();
            return $this->buildUnitTree($units);
        } catch (Exception $e) {
            error_log("Error building unit hierarchy: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Build hierarchical unit tree
     */
    private function buildUnitTree($units, $parentId = null) {
        $tree = [];
        
        foreach ($units as $unit) {
            if ($unit->parent_unit_id == $parentId) {
                $unit->children = $this->buildUnitTree($units, $unit->id);
                $tree[] = $unit;
            }
        }
        
        return $tree;
    }
    
    /**
     * Get all active corps
     */
    public function getCorps() {
        if (isset($this->cache['corps'])) {
            return $this->cache['corps'];
        }
        
        try {
            $stmt = $this->pdo->prepare("
                SELECT c.*, 
                       COUNT(s.id) as personnel_count
                FROM corps c
                LEFT JOIN staff s ON c.abbreviation = s.corps AND s.accStatus = 'active'
                WHERE c.is_active = 1
                GROUP BY c.id
                ORDER BY c.corps_type, c.name
            ");
            $stmt->execute();
            $corps = $stmt->fetchAll(PDO::FETCH_OBJ);
            
            $this->cache['corps'] = $corps;
            return $corps;
            
        } catch (PDOException $e) {
            error_log("Error fetching corps: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get corps by abbreviation
     */
    public function getCorpsByAbbreviation($abbreviation) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT c.*,
                       COUNT(s.id) as personnel_count
                FROM corps c
                LEFT JOIN staff s ON c.abbreviation = s.corps AND s.accStatus = 'active'
                WHERE c.abbreviation = ? AND c.is_active = 1
                GROUP BY c.id
            ");
            $stmt->execute([$abbreviation]);
            return $stmt->fetch(PDO::FETCH_OBJ);
        } catch (PDOException $e) {
            error_log("Error fetching corps: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Get military deployment statistics
     */
    public function getDeploymentStatistics() {
        try {
            $stmt = $this->pdo->prepare("
                SELECT deployment_status, COUNT(*) as count
                FROM staff 
                WHERE accStatus = 'active'
                GROUP BY deployment_status
            ");
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_OBJ);
        } catch (PDOException $e) {
            error_log("Error fetching deployment statistics: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get medical readiness statistics
     */
    public function getMedicalReadinessStats() {
        try {
            $stmt = $this->pdo->prepare("
                SELECT medical_status, COUNT(*) as count
                FROM staff 
                WHERE accStatus = 'active'
                GROUP BY medical_status
            ");
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_OBJ);
        } catch (PDOException $e) {
            error_log("Error fetching medical readiness stats: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get security clearance distribution
     */
    public function getSecurityClearanceStats() {
        try {
            $stmt = $this->pdo->prepare("
                SELECT security_clearance_level, COUNT(*) as count
                FROM staff 
                WHERE accStatus = 'active'
                GROUP BY security_clearance_level
            ");
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_OBJ);
        } catch (PDOException $e) {
            error_log("Error fetching security clearance stats: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get personnel with expiring clearances
     */
    public function getExpiringClearances($days = 90) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT s.id, s.first_name, s.last_name, s.service_number,
                       s.security_clearance_level, s.clearance_expiry_date,
                       DATEDIFF(s.clearance_expiry_date, CURDATE()) as days_until_expiry,
                       u.name as unit_name, r.abbreviation as rank
                FROM staff s
                LEFT JOIN units u ON s.unit_id = u.id
                LEFT JOIN ranks r ON s.rank_id = r.id
                WHERE s.accStatus = 'active' 
                AND s.security_clearance_level != 'None'
                AND s.clearance_expiry_date IS NOT NULL
                AND DATEDIFF(s.clearance_expiry_date, CURDATE()) <= ?
                AND DATEDIFF(s.clearance_expiry_date, CURDATE()) >= 0
                ORDER BY s.clearance_expiry_date ASC
            ");
            $stmt->execute([$days]);
            return $stmt->fetchAll(PDO::FETCH_OBJ);
        } catch (PDOException $e) {
            error_log("Error fetching expiring clearances: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get personnel with upcoming medical exams
     */
    public function getUpcomingMedicalExams($days = 30) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT s.id, s.first_name, s.last_name, s.service_number,
                       s.medical_status, s.medical_expiry_date,
                       DATEDIFF(s.medical_expiry_date, CURDATE()) as days_until_expiry,
                       u.name as unit_name, r.abbreviation as rank
                FROM staff s
                LEFT JOIN units u ON s.unit_id = u.id
                LEFT JOIN ranks r ON s.rank_id = r.id
                WHERE s.accStatus = 'active' 
                AND s.medical_expiry_date IS NOT NULL
                AND DATEDIFF(s.medical_expiry_date, CURDATE()) <= ?
                AND DATEDIFF(s.medical_expiry_date, CURDATE()) >= 0
                ORDER BY s.medical_expiry_date ASC
            ");
            $stmt->execute([$days]);
            return $stmt->fetchAll(PDO::FETCH_OBJ);
        } catch (PDOException $e) {
            error_log("Error fetching upcoming medical exams: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get promotion eligible personnel
     */
    public function getPromotionEligible() {
        try {
            $stmt = $this->pdo->prepare("
                SELECT s.id, s.first_name, s.last_name, s.service_number,
                       s.attestDate, s.next_promotion_eligible_date,
                       r.name as current_rank, r.abbreviation as rank_abbr, r.rank_level,
                       nr.name as next_rank, nr.abbreviation as next_rank_abbr,
                       u.name as unit_name,
                       TIMESTAMPDIFF(YEAR, s.attestDate, CURDATE()) as service_years
                FROM staff s
                JOIN ranks r ON s.rank_id = r.id
                LEFT JOIN ranks nr ON nr.rank_category = r.rank_category 
                    AND nr.rank_level = r.rank_level + 1 
                    AND nr.is_active = 1
                LEFT JOIN units u ON s.unit_id = u.id
                WHERE s.accStatus = 'active'
                AND (s.next_promotion_eligible_date <= CURDATE() 
                     OR (s.next_promotion_eligible_date IS NULL 
                         AND TIMESTAMPDIFF(YEAR, s.attestDate, CURDATE()) >= r.minimum_service_years + 2))
                AND nr.id IS NOT NULL
                ORDER BY s.next_promotion_eligible_date ASC, s.attestDate ASC
            ");
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_OBJ);
        } catch (PDOException $e) {
            error_log("Error fetching promotion eligible personnel: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Validate military data consistency
     */
    public function validateMilitaryData($staffId) {
        $issues = [];
        
        try {
            $stmt = $this->pdo->prepare("
                SELECT s.*, r.rank_category, r.minimum_service_years,
                       TIMESTAMPDIFF(YEAR, s.attestDate, CURDATE()) as actual_service_years
                FROM staff s
                LEFT JOIN ranks r ON s.rank_id = r.id
                WHERE s.id = ?
            ");
            $stmt->execute([$staffId]);
            $staff = $stmt->fetch(PDO::FETCH_OBJ);
            
            if (!$staff) {
                return ['error' => 'Staff member not found'];
            }
            
            // Check service years vs rank requirements
            if ($staff->actual_service_years < $staff->minimum_service_years) {
                $issues[] = "Insufficient service years for current rank (has {$staff->actual_service_years}, needs {$staff->minimum_service_years})";
            }
            
            // Check security clearance expiry
            if ($staff->security_clearance_level !== 'None' && !$staff->clearance_expiry_date) {
                $issues[] = "Security clearance expiry date missing";
            }
            
            if ($staff->clearance_expiry_date && $staff->clearance_expiry_date < date('Y-m-d')) {
                $issues[] = "Security clearance has expired";
            }
            
            // Check medical status
            if ($staff->medical_expiry_date && $staff->medical_expiry_date < date('Y-m-d')) {
                $issues[] = "Medical fitness certification has expired";
            }
            
            return ['valid' => empty($issues), 'issues' => $issues];
            
        } catch (PDOException $e) {
            error_log("Error validating military data: " . $e->getMessage());
            return ['error' => 'Validation failed'];
        }
    }
    
    /**
     * Clear cache
     */
    public function clearCache() {
        $this->cache = [];
    }
    
    /**
     * Get system-wide military readiness summary
     */
    public function getReadinessSummary() {
        try {
            $summary = [];
            
            // Overall personnel count
            $stmt = $this->pdo->prepare("SELECT COUNT(*) as total FROM staff WHERE accStatus = 'active'");
            $stmt->execute();
            $summary['total_personnel'] = $stmt->fetch(PDO::FETCH_OBJ)->total;
            
            // Deployment status breakdown
            $summary['deployment'] = $this->getDeploymentStatistics();
            
            // Medical readiness
            $summary['medical'] = $this->getMedicalReadinessStats();
            
            // Security clearances
            $summary['clearances'] = $this->getSecurityClearanceStats();
            
            // Alerts
            $summary['alerts'] = [
                'expiring_clearances' => count($this->getExpiringClearances(30)),
                'upcoming_medical' => count($this->getUpcomingMedicalExams(30)),
                'promotion_eligible' => count($this->getPromotionEligible())
            ];
            
            return $summary;
            
        } catch (PDOException $e) {
            error_log("Error generating readiness summary: " . $e->getMessage());
            return [];
        }
    }
}
?>