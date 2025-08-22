<?php
/**
 * Military Service Record Manager
 * Handles deployment tracking, security clearance management, and rank progression
 */

require_once dirname(__DIR__, 2) . '/shared/database_connection.php';
require_once dirname(__DIR__, 2) . '/shared/security_audit_service.php';

class ServiceRecordManager {
    private $pdo;
    private $auditService;
    private $userId;
    
    // Deployment statuses
    const DEPLOYMENT_UPCOMING = 'upcoming';
    const DEPLOYMENT_ACTIVE = 'active';
    const DEPLOYMENT_COMPLETED = 'completed';
    const DEPLOYMENT_CANCELLED = 'cancelled';
    
    // Security clearance levels
    const CLEARANCE_NONE = 'none';
    const CLEARANCE_CONFIDENTIAL = 'confidential';
    const CLEARANCE_SECRET = 'secret';
    const CLEARANCE_TOP_SECRET = 'top_secret';
    const CLEARANCE_SCI = 'sci';
    const CLEARANCE_COSMIC_TOP_SECRET = 'cosmic_top_secret';
    
    // Medical readiness statuses
    const MEDICAL_FIT = 'fit';
    const MEDICAL_LIMITED = 'limited';
    const MEDICAL_UNFIT = 'unfit';
    const MEDICAL_PENDING = 'pending_review';
    
    public function __construct($userId) {
        $this->pdo = getDbConnection();
        $this->auditService = new SecurityAuditService();
        $this->userId = $userId;
        $this->ensureTables();
    }
    
    /**
     * Ensure required tables exist
     */
    private function ensureTables() {
        $this->createDeploymentTable();
        $this->createSecurityClearanceTable();
        $this->createMedicalReadinessTable();
        $this->createTrainingComplianceTable();
        $this->createRankProgressionTable();
    }
    
    /**
     * Create deployment tracking table
     */
    private function createDeploymentTable() {
        $sql = "
            CREATE TABLE IF NOT EXISTS staff_deployments (
                id INT PRIMARY KEY AUTO_INCREMENT,
                staff_id INT NOT NULL,
                deployment_name VARCHAR(255) NOT NULL,
                location VARCHAR(255) NOT NULL,
                country VARCHAR(100) NOT NULL,
                start_date DATE NOT NULL,
                end_date DATE,
                actual_end_date DATE,
                status ENUM('upcoming', 'active', 'completed', 'cancelled') DEFAULT 'upcoming',
                deployment_type ENUM('combat', 'peacekeeping', 'training', 'humanitarian', 'other') DEFAULT 'other',
                unit_deployed VARCHAR(255),
                role VARCHAR(255),
                commander VARCHAR(255),
                hazard_pay DECIMAL(10,2) DEFAULT 0.00,
                family_separation_allowance DECIMAL(10,2) DEFAULT 0.00,
                notes TEXT,
                created_by INT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (staff_id) REFERENCES staff(id) ON DELETE CASCADE,
                FOREIGN KEY (created_by) REFERENCES staff(id) ON DELETE SET NULL,
                INDEX idx_staff_id (staff_id),
                INDEX idx_status (status),
                INDEX idx_dates (start_date, end_date)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ";
        
        try {
            $this->pdo->exec($sql);
        } catch (PDOException $e) {
            error_log("Error creating deployments table: " . $e->getMessage());
        }
    }
    
    /**
     * Create security clearance table
     */
    private function createSecurityClearanceTable() {
        $sql = "
            CREATE TABLE IF NOT EXISTS staff_security_clearance (
                id INT PRIMARY KEY AUTO_INCREMENT,
                staff_id INT NOT NULL,
                clearance_level ENUM('none', 'confidential', 'secret', 'top_secret', 'sci', 'cosmic_top_secret') NOT NULL,
                issue_date DATE NOT NULL,
                expiry_date DATE NOT NULL,
                issuing_authority VARCHAR(255) NOT NULL,
                investigation_type VARCHAR(100),
                polygraph_required BOOLEAN DEFAULT FALSE,
                polygraph_date DATE,
                polygraph_expiry DATE,
                adjudication_date DATE,
                status ENUM('active', 'suspended', 'revoked', 'expired', 'pending') DEFAULT 'active',
                restrictions TEXT,
                renewal_status ENUM('not_required', 'in_progress', 'completed', 'denied') DEFAULT 'not_required',
                last_review_date DATE,
                next_review_date DATE,
                created_by INT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (staff_id) REFERENCES staff(id) ON DELETE CASCADE,
                FOREIGN KEY (created_by) REFERENCES staff(id) ON DELETE SET NULL,
                INDEX idx_staff_id (staff_id),
                INDEX idx_clearance_level (clearance_level),
                INDEX idx_expiry_date (expiry_date),
                INDEX idx_status (status)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ";
        
        try {
            $this->pdo->exec($sql);
        } catch (PDOException $e) {
            error_log("Error creating security clearance table: " . $e->getMessage());
        }
    }
    
    /**
     * Create medical readiness table
     */
    private function createMedicalReadinessTable() {
        $sql = "
            CREATE TABLE IF NOT EXISTS staff_medical_readiness (
                id INT PRIMARY KEY AUTO_INCREMENT,
                staff_id INT NOT NULL,
                fitness_category ENUM('fit', 'limited', 'unfit', 'pending_review') NOT NULL,
                physical_exam_date DATE,
                physical_exam_expiry DATE,
                dental_exam_date DATE,
                dental_exam_expiry DATE,
                vision_exam_date DATE,
                vision_exam_expiry DATE,
                hearing_exam_date DATE,
                hearing_exam_expiry DATE,
                immunizations_current BOOLEAN DEFAULT TRUE,
                immunization_date DATE,
                blood_type VARCHAR(10),
                medical_conditions TEXT,
                medications TEXT,
                allergies TEXT,
                deployment_eligibility BOOLEAN DEFAULT TRUE,
                fitness_test_score DECIMAL(5,2),
                fitness_test_date DATE,
                fitness_test_expiry DATE,
                medical_officer VARCHAR(255),
                notes TEXT,
                created_by INT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (staff_id) REFERENCES staff(id) ON DELETE CASCADE,
                FOREIGN KEY (created_by) REFERENCES staff(id) ON DELETE SET NULL,
                INDEX idx_staff_id (staff_id),
                INDEX idx_fitness_category (fitness_category),
                INDEX idx_deployment_eligibility (deployment_eligibility)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ";
        
        try {
            $this->pdo->exec($sql);
        } catch (PDOException $e) {
            error_log("Error creating medical readiness table: " . $e->getMessage());
        }
    }
    
    /**
     * Create training compliance table
     */
    private function createTrainingComplianceTable() {
        $sql = "
            CREATE TABLE IF NOT EXISTS staff_training_compliance (
                id INT PRIMARY KEY AUTO_INCREMENT,
                staff_id INT NOT NULL,
                training_name VARCHAR(255) NOT NULL,
                training_type ENUM('mandatory', 'professional', 'specialized', 'leadership') NOT NULL,
                completion_date DATE,
                expiry_date DATE,
                certificate_number VARCHAR(100),
                training_provider VARCHAR(255),
                hours_completed DECIMAL(5,2),
                score DECIMAL(5,2),
                passing_score DECIMAL(5,2),
                status ENUM('not_started', 'in_progress', 'completed', 'expired', 'failed') DEFAULT 'not_started',
                required_for_deployment BOOLEAN DEFAULT FALSE,
                required_for_promotion BOOLEAN DEFAULT FALSE,
                reminder_sent BOOLEAN DEFAULT FALSE,
                notes TEXT,
                created_by INT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (staff_id) REFERENCES staff(id) ON DELETE CASCADE,
                FOREIGN KEY (created_by) REFERENCES staff(id) ON DELETE SET NULL,
                INDEX idx_staff_id (staff_id),
                INDEX idx_training_type (training_type),
                INDEX idx_status (status),
                INDEX idx_expiry_date (expiry_date)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ";
        
        try {
            $this->pdo->exec($sql);
        } catch (PDOException $e) {
            error_log("Error creating training compliance table: " . $e->getMessage());
        }
    }
    
    /**
     * Create rank progression table
     */
    private function createRankProgressionTable() {
        $sql = "
            CREATE TABLE IF NOT EXISTS staff_rank_progression (
                id INT PRIMARY KEY AUTO_INCREMENT,
                staff_id INT NOT NULL,
                from_rank_id INT,
                to_rank_id INT NOT NULL,
                promotion_date DATE NOT NULL,
                effective_date DATE,
                promotion_type ENUM('regular', 'battlefield', 'temporary', 'acting') DEFAULT 'regular',
                promotion_board_date DATE,
                promotion_order VARCHAR(100),
                time_in_grade_months INT,
                time_in_service_months INT,
                eligible_for_next BOOLEAN DEFAULT FALSE,
                next_eligible_date DATE,
                promotion_points INT,
                requirements_met BOOLEAN DEFAULT FALSE,
                requirements_notes TEXT,
                approved_by INT,
                created_by INT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (staff_id) REFERENCES staff(id) ON DELETE CASCADE,
                FOREIGN KEY (from_rank_id) REFERENCES ranks(id) ON DELETE SET NULL,
                FOREIGN KEY (to_rank_id) REFERENCES ranks(id) ON DELETE CASCADE,
                FOREIGN KEY (approved_by) REFERENCES staff(id) ON DELETE SET NULL,
                FOREIGN KEY (created_by) REFERENCES staff(id) ON DELETE SET NULL,
                INDEX idx_staff_id (staff_id),
                INDEX idx_promotion_date (promotion_date),
                INDEX idx_eligible_for_next (eligible_for_next)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ";
        
        try {
            $this->pdo->exec($sql);
        } catch (PDOException $e) {
            error_log("Error creating rank progression table: " . $e->getMessage());
        }
    }
    
    /**
     * Add deployment record
     */
    public function addDeployment($data) {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO staff_deployments (
                    staff_id, deployment_name, location, country, start_date, end_date,
                    deployment_type, unit_deployed, role, commander, notes, created_by
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $result = $stmt->execute([
                $this->userId,
                $data['deployment_name'],
                $data['location'],
                $data['country'],
                $data['start_date'],
                $data['end_date'] ?? null,
                $data['deployment_type'] ?? 'other',
                $data['unit_deployed'] ?? null,
                $data['role'] ?? null,
                $data['commander'] ?? null,
                $data['notes'] ?? null,
                $_SESSION['user_id'] ?? $this->userId
            ]);
            
            if ($result) {
                $deploymentId = $this->pdo->lastInsertId();
                $this->auditService->logActivity(
                    $this->userId,
                    'deployment_added',
                    'Deployment record added',
                    'deployment',
                    $deploymentId,
                    $data
                );
                return ['success' => true, 'deployment_id' => $deploymentId];
            }
            
            return ['success' => false, 'message' => 'Failed to add deployment'];
            
        } catch (PDOException $e) {
            error_log("Error adding deployment: " . $e->getMessage());
            return ['success' => false, 'message' => 'Database error'];
        }
    }
    
    /**
     * Update deployment status
     */
    public function updateDeploymentStatus($deploymentId, $status, $actualEndDate = null) {
        try {
            $sql = "UPDATE staff_deployments SET status = ?";
            $params = [$status];
            
            if ($actualEndDate && $status === self::DEPLOYMENT_COMPLETED) {
                $sql .= ", actual_end_date = ?";
                $params[] = $actualEndDate;
            }
            
            $sql .= " WHERE id = ? AND staff_id = ?";
            $params[] = $deploymentId;
            $params[] = $this->userId;
            
            $stmt = $this->pdo->prepare($sql);
            $result = $stmt->execute($params);
            
            if ($result) {
                $this->auditService->logActivity(
                    $this->userId,
                    'deployment_status_updated',
                    "Deployment status changed to {$status}",
                    'deployment',
                    $deploymentId
                );
            }
            
            return $result;
            
        } catch (PDOException $e) {
            error_log("Error updating deployment status: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Add security clearance record
     */
    public function addSecurityClearance($data) {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO staff_security_clearance (
                    staff_id, clearance_level, issue_date, expiry_date, issuing_authority,
                    investigation_type, polygraph_required, adjudication_date, created_by
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $result = $stmt->execute([
                $this->userId,
                $data['clearance_level'],
                $data['issue_date'],
                $data['expiry_date'],
                $data['issuing_authority'],
                $data['investigation_type'] ?? null,
                $data['polygraph_required'] ?? false,
                $data['adjudication_date'] ?? null,
                $_SESSION['user_id'] ?? $this->userId
            ]);
            
            if ($result) {
                $clearanceId = $this->pdo->lastInsertId();
                $this->auditService->logActivity(
                    $this->userId,
                    'security_clearance_added',
                    'Security clearance record added',
                    'security_clearance',
                    $clearanceId,
                    $data
                );
                return ['success' => true, 'clearance_id' => $clearanceId];
            }
            
            return ['success' => false, 'message' => 'Failed to add security clearance'];
            
        } catch (PDOException $e) {
            error_log("Error adding security clearance: " . $e->getMessage());
            return ['success' => false, 'message' => 'Database error'];
        }
    }
    
    /**
     * Update medical readiness
     */
    public function updateMedicalReadiness($data) {
        try {
            // Check if record exists
            $stmt = $this->pdo->prepare("SELECT id FROM staff_medical_readiness WHERE staff_id = ?");
            $stmt->execute([$this->userId]);
            $exists = $stmt->fetch();
            
            if ($exists) {
                // Update existing record
                $stmt = $this->pdo->prepare("
                    UPDATE staff_medical_readiness SET
                        fitness_category = ?, physical_exam_date = ?, physical_exam_expiry = ?,
                        dental_exam_date = ?, dental_exam_expiry = ?, immunizations_current = ?,
                        deployment_eligibility = ?, fitness_test_score = ?, fitness_test_date = ?,
                        blood_type = ?, medical_conditions = ?, medications = ?, allergies = ?,
                        updated_at = CURRENT_TIMESTAMP
                    WHERE staff_id = ?
                ");
                
                $result = $stmt->execute([
                    $data['fitness_category'],
                    $data['physical_exam_date'] ?? null,
                    $data['physical_exam_expiry'] ?? null,
                    $data['dental_exam_date'] ?? null,
                    $data['dental_exam_expiry'] ?? null,
                    $data['immunizations_current'] ?? true,
                    $data['deployment_eligibility'] ?? true,
                    $data['fitness_test_score'] ?? null,
                    $data['fitness_test_date'] ?? null,
                    $data['blood_type'] ?? null,
                    $data['medical_conditions'] ?? null,
                    $data['medications'] ?? null,
                    $data['allergies'] ?? null,
                    $this->userId
                ]);
                
                $recordId = $exists['id'];
            } else {
                // Create new record
                $stmt = $this->pdo->prepare("
                    INSERT INTO staff_medical_readiness (
                        staff_id, fitness_category, physical_exam_date, physical_exam_expiry,
                        dental_exam_date, dental_exam_expiry, immunizations_current,
                        deployment_eligibility, fitness_test_score, fitness_test_date,
                        blood_type, medical_conditions, medications, allergies, created_by
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                
                $result = $stmt->execute([
                    $this->userId,
                    $data['fitness_category'],
                    $data['physical_exam_date'] ?? null,
                    $data['physical_exam_expiry'] ?? null,
                    $data['dental_exam_date'] ?? null,
                    $data['dental_exam_expiry'] ?? null,
                    $data['immunizations_current'] ?? true,
                    $data['deployment_eligibility'] ?? true,
                    $data['fitness_test_score'] ?? null,
                    $data['fitness_test_date'] ?? null,
                    $data['blood_type'] ?? null,
                    $data['medical_conditions'] ?? null,
                    $data['medications'] ?? null,
                    $data['allergies'] ?? null,
                    $_SESSION['user_id'] ?? $this->userId
                ]);
                
                $recordId = $this->pdo->lastInsertId();
            }
            
            if ($result) {
                $this->auditService->logActivity(
                    $this->userId,
                    'medical_readiness_updated',
                    'Medical readiness record updated',
                    'medical_readiness',
                    $recordId,
                    $data
                );
                return ['success' => true, 'record_id' => $recordId];
            }
            
            return ['success' => false, 'message' => 'Failed to update medical readiness'];
            
        } catch (PDOException $e) {
            error_log("Error updating medical readiness: " . $e->getMessage());
            return ['success' => false, 'message' => 'Database error'];
        }
    }
    
    /**
     * Add training compliance record
     */
    public function addTrainingCompliance($data) {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO staff_training_compliance (
                    staff_id, training_name, training_type, completion_date, expiry_date,
                    certificate_number, training_provider, hours_completed, score,
                    status, required_for_deployment, required_for_promotion, created_by
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $result = $stmt->execute([
                $this->userId,
                $data['training_name'],
                $data['training_type'],
                $data['completion_date'] ?? null,
                $data['expiry_date'] ?? null,
                $data['certificate_number'] ?? null,
                $data['training_provider'] ?? null,
                $data['hours_completed'] ?? null,
                $data['score'] ?? null,
                $data['status'] ?? 'not_started',
                $data['required_for_deployment'] ?? false,
                $data['required_for_promotion'] ?? false,
                $_SESSION['user_id'] ?? $this->userId
            ]);
            
            if ($result) {
                $trainingId = $this->pdo->lastInsertId();
                $this->auditService->logActivity(
                    $this->userId,
                    'training_compliance_added',
                    'Training compliance record added',
                    'training_compliance',
                    $trainingId,
                    $data
                );
                return ['success' => true, 'training_id' => $trainingId];
            }
            
            return ['success' => false, 'message' => 'Failed to add training compliance'];
            
        } catch (PDOException $e) {
            error_log("Error adding training compliance: " . $e->getMessage());
            return ['success' => false, 'message' => 'Database error'];
        }
    }
    
    /**
     * Add rank progression record
     */
    public function addRankProgression($data) {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO staff_rank_progression (
                    staff_id, from_rank_id, to_rank_id, promotion_date, effective_date,
                    promotion_type, promotion_order, time_in_grade_months, 
                    time_in_service_months, promotion_points, approved_by, created_by
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $result = $stmt->execute([
                $this->userId,
                $data['from_rank_id'] ?? null,
                $data['to_rank_id'],
                $data['promotion_date'],
                $data['effective_date'] ?? $data['promotion_date'],
                $data['promotion_type'] ?? 'regular',
                $data['promotion_order'] ?? null,
                $data['time_in_grade_months'] ?? null,
                $data['time_in_service_months'] ?? null,
                $data['promotion_points'] ?? null,
                $data['approved_by'] ?? null,
                $_SESSION['user_id'] ?? $this->userId
            ]);
            
            if ($result) {
                $progressionId = $this->pdo->lastInsertId();
                
                // Update staff rank
                $this->updateStaffRank($data['to_rank_id']);
                
                $this->auditService->logActivity(
                    $this->userId,
                    'rank_progression_added',
                    'Rank progression record added',
                    'rank_progression',
                    $progressionId,
                    $data
                );
                return ['success' => true, 'progression_id' => $progressionId];
            }
            
            return ['success' => false, 'message' => 'Failed to add rank progression'];
            
        } catch (PDOException $e) {
            error_log("Error adding rank progression: " . $e->getMessage());
            return ['success' => false, 'message' => 'Database error'];
        }
    }
    
    /**
     * Update staff rank
     */
    private function updateStaffRank($rankId) {
        try {
            $stmt = $this->pdo->prepare("UPDATE staff SET rank_id = ? WHERE id = ?");
            $stmt->execute([$rankId, $this->userId]);
        } catch (PDOException $e) {
            error_log("Error updating staff rank: " . $e->getMessage());
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
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error getting deployment history: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get security clearance status
     */
    public function getSecurityClearanceStatus() {
        try {
            $stmt = $this->pdo->prepare("
                SELECT * FROM staff_security_clearance 
                WHERE staff_id = ? 
                ORDER BY issue_date DESC 
                LIMIT 1
            ");
            $stmt->execute([$this->userId]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error getting security clearance: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Get medical readiness status
     */
    public function getMedicalReadiness() {
        try {
            $stmt = $this->pdo->prepare("
                SELECT * FROM staff_medical_readiness 
                WHERE staff_id = ?
            ");
            $stmt->execute([$this->userId]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error getting medical readiness: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Get training compliance records
     */
    public function getTrainingCompliance() {
        try {
            $stmt = $this->pdo->prepare("
                SELECT * FROM staff_training_compliance 
                WHERE staff_id = ? 
                ORDER BY expiry_date ASC
            ");
            $stmt->execute([$this->userId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error getting training compliance: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get rank progression history
     */
    public function getRankProgressionHistory() {
        try {
            $stmt = $this->pdo->prepare("
                SELECT rp.*, r1.name as from_rank_name, r2.name as to_rank_name
                FROM staff_rank_progression rp
                LEFT JOIN ranks r1 ON rp.from_rank_id = r1.id
                JOIN ranks r2 ON rp.to_rank_id = r2.id
                WHERE rp.staff_id = ? 
                ORDER BY rp.promotion_date DESC
            ");
            $stmt->execute([$this->userId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error getting rank progression: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Check promotion eligibility
     */
    public function checkPromotionEligibility() {
        try {
            // Get current rank and service record
            $stmt = $this->pdo->prepare("
                SELECT s.rank_id, r.name as current_rank,
                       DATEDIFF(NOW(), s.created_at) / 30 as months_in_service
                FROM staff s
                JOIN ranks r ON s.rank_id = r.id
                WHERE s.id = ?
            ");
            $stmt->execute([$this->userId]);
            $serviceRecord = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$serviceRecord) {
                return ['eligible' => false, 'reason' => 'Service record not found'];
            }
            
            // Get latest promotion
            $stmt = $this->pdo->prepare("
                SELECT promotion_date, DATEDIFF(NOW(), promotion_date) / 30 as months_in_grade
                FROM staff_rank_progression 
                WHERE staff_id = ? 
                ORDER BY promotion_date DESC 
                LIMIT 1
            ");
            $stmt->execute([$this->userId]);
            $lastPromotion = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $monthsInGrade = $lastPromotion ? $lastPromotion['months_in_grade'] : $serviceRecord['months_in_service'];
            
            // Simple eligibility rules (can be made more complex)
            $minMonthsInGrade = 18; // Minimum months in current grade
            $eligible = $monthsInGrade >= $minMonthsInGrade;
            
            return [
                'eligible' => $eligible,
                'months_in_grade' => round($monthsInGrade, 1),
                'min_required' => $minMonthsInGrade,
                'reason' => $eligible ? 'Eligible for promotion' : "Need {$minMonthsInGrade} months in grade"
            ];
            
        } catch (PDOException $e) {
            error_log("Error checking promotion eligibility: " . $e->getMessage());
            return ['eligible' => false, 'reason' => 'Database error'];
        }
    }
    
    /**
     * Get expiring items that need attention
     */
    public function getExpiringItems($daysAhead = 90) {
        $expiring = [];
        $alertDate = date('Y-m-d', strtotime("+{$daysAhead} days"));
        
        try {
            // Security clearance expiry
            $stmt = $this->pdo->prepare("
                SELECT 'Security Clearance' as type, clearance_level as item, expiry_date
                FROM staff_security_clearance 
                WHERE staff_id = ? AND expiry_date <= ? AND status = 'active'
                ORDER BY expiry_date ASC
            ");
            $stmt->execute([$this->userId, $alertDate]);
            $expiring = array_merge($expiring, $stmt->fetchAll(PDO::FETCH_ASSOC));
            
            // Medical exams
            $stmt = $this->pdo->prepare("
                SELECT 'Physical Exam' as type, 'Physical Examination' as item, physical_exam_expiry as expiry_date
                FROM staff_medical_readiness 
                WHERE staff_id = ? AND physical_exam_expiry <= ?
                UNION
                SELECT 'Dental Exam' as type, 'Dental Examination' as item, dental_exam_expiry as expiry_date
                FROM staff_medical_readiness 
                WHERE staff_id = ? AND dental_exam_expiry <= ?
                ORDER BY expiry_date ASC
            ");
            $stmt->execute([$this->userId, $alertDate, $this->userId, $alertDate]);
            $expiring = array_merge($expiring, $stmt->fetchAll(PDO::FETCH_ASSOC));
            
            // Training compliance
            $stmt = $this->pdo->prepare("
                SELECT 'Training' as type, training_name as item, expiry_date
                FROM staff_training_compliance 
                WHERE staff_id = ? AND expiry_date <= ? AND status = 'completed'
                ORDER BY expiry_date ASC
            ");
            $stmt->execute([$this->userId, $alertDate]);
            $expiring = array_merge($expiring, $stmt->fetchAll(PDO::FETCH_ASSOC));
            
        } catch (PDOException $e) {
            error_log("Error getting expiring items: " . $e->getMessage());
        }
        
        return $expiring;
    }
}