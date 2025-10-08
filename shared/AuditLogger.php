<?php
/**
 * Audit Trail Logger
 * 
 * Provides comprehensive logging for all staff promotion system actions
 * Including: promotions, reversions, updates, approvals, rollbacks
 * 
 * @author Admin Branch System
 * @created 2025-10-07
 */

class AuditLogger {
    private $pdo;
    private $userId;
    private $userName;
    private $ipAddress;
    private $userAgent;
    
    /**
     * Constructor
     * 
     * @param PDO $pdo Database connection
     * @param int|null $userId Current user ID (from session)
     * @param string|null $userName Current username (from session)
     */
    public function __construct($pdo, $userId = null, $userName = null) {
        $this->pdo = $pdo;
        $this->userId = $userId ?? ($_SESSION['user_id'] ?? null);
        $this->userName = $userName ?? ($_SESSION['username'] ?? 'System');
        $this->ipAddress = $this->getUserIP();
        $this->userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
    }
    
    /**
     * Log a promotion action
     * 
     * @param int $staffId Staff member ID
     * @param int $promotionId Promotion record ID
     * @param array $beforeData Data before change
     * @param array $afterData Data after change
     * @param string $description Human-readable description
     * @return bool Success status
     */
    public function logPromotion($staffId, $promotionId, $beforeData, $afterData, $description) {
        return $this->log(
            'promotion',
            'staff_promotions',
            $promotionId,
            $staffId,
            $beforeData,
            $afterData,
            $description
        );
    }
    
    /**
     * Log a reversion/demotion action
     * 
     * @param int $staffId Staff member ID
     * @param int $promotionId Promotion record ID
     * @param array $beforeData Data before change
     * @param array $afterData Data after change
     * @param string $description Human-readable description
     * @return bool Success status
     */
    public function logReversion($staffId, $promotionId, $beforeData, $afterData, $description) {
        return $this->log(
            'reversion',
            'staff_promotions',
            $promotionId,
            $staffId,
            $beforeData,
            $afterData,
            $description
        );
    }
    
    /**
     * Log a rollback action
     * 
     * @param int $staffId Staff member ID
     * @param int $promotionId Promotion record ID
     * @param array $beforeData Data before rollback
     * @param array $afterData Data after rollback
     * @param string $description Human-readable description
     * @return bool Success status
     */
    public function logRollback($staffId, $promotionId, $beforeData, $afterData, $description) {
        return $this->log(
            'rollback',
            'staff_promotions',
            $promotionId,
            $staffId,
            $beforeData,
            $afterData,
            $description
        );
    }
    
    /**
     * Log an approval action
     * 
     * @param int $staffId Staff member ID
     * @param int $promotionId Promotion record ID
     * @param string $description Human-readable description
     * @return bool Success status
     */
    public function logApproval($staffId, $promotionId, $description) {
        return $this->log(
            'approval',
            'staff_promotions',
            $promotionId,
            $staffId,
            null,
            ['status' => 'approved'],
            $description
        );
    }
    
    /**
     * Log a rejection action
     * 
     * @param int $staffId Staff member ID
     * @param int $promotionId Promotion record ID
     * @param string $reason Rejection reason
     * @return bool Success status
     */
    public function logRejection($staffId, $promotionId, $reason) {
        return $this->log(
            'rejection',
            'staff_promotions',
            $promotionId,
            $staffId,
            null,
            ['status' => 'rejected', 'reason' => $reason],
            "Promotion rejected: $reason"
        );
    }
    
    /**
     * Core logging method
     * 
     * @param string $actionType Type of action
     * @param string $tableName Table affected
     * @param int $recordId Record ID
     * @param int|null $staffId Staff ID (if applicable)
     * @param mixed $beforeValue Value before change
     * @param mixed $afterValue Value after change
     * @param string $description Human-readable description
     * @return bool Success status
     */
    private function log($actionType, $tableName, $recordId, $staffId = null, $beforeValue = null, $afterValue = null, $description = '') {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO audit_trail (
                    action_type, table_name, record_id, staff_id,
                    user_id, user_name, ip_address, user_agent,
                    before_value, after_value, description
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            return $stmt->execute([
                $actionType,
                $tableName,
                $recordId,
                $staffId,
                $this->userId,
                $this->userName,
                $this->ipAddress,
                $this->userAgent,
                $beforeValue ? json_encode($beforeValue) : null,
                $afterValue ? json_encode($afterValue) : null,
                $description
            ]);
        } catch (Exception $e) {
            // Log error but don't fail the main operation
            error_log("Audit logging failed: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Save a complete snapshot to promotion history
     * 
     * @param int $promotionId Promotion ID
     * @param int $staffId Staff ID
     * @param string $action Action type
     * @param array $snapshot Complete data snapshot
     * @return bool Success status
     */
    public function saveSnapshot($promotionId, $staffId, $action, $snapshot) {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO staff_promotion_history (
                    promotion_id, staff_id, action, snapshot, user_id
                ) VALUES (?, ?, ?, ?, ?)
            ");
            
            return $stmt->execute([
                $promotionId,
                $staffId,
                $action,
                json_encode($snapshot),
                $this->userId
            ]);
        } catch (Exception $e) {
            error_log("Snapshot save failed: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get audit trail for a specific staff member
     * 
     * @param int $staffId Staff ID
     * @param int $limit Number of records to return
     * @return array Audit trail entries
     */
    public function getStaffAuditTrail($staffId, $limit = 50) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT * FROM audit_trail 
                WHERE staff_id = ? 
                ORDER BY created_at DESC 
                LIMIT ?
            ");
            $stmt->execute([$staffId, $limit]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Failed to fetch audit trail: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get promotion history for a specific staff member
     * 
     * @param int $staffId Staff ID
     * @return array Promotion history entries
     */
    public function getPromotionHistory($staffId) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT * FROM staff_promotion_history 
                WHERE staff_id = ? 
                ORDER BY timestamp DESC
            ");
            $stmt->execute([$staffId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Failed to fetch promotion history: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get recent promotions that can be rolled back
     * 
     * @param int $hours Hours window for rollback (default 24)
     * @return array Rollbackable promotions
     */
    public function getRollbackablePromotions($hours = 24) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT sp.*, s.service_number, s.first_name, s.last_name,
                       r_from.name as from_rank_name, r_to.name as to_rank_name,
                       TIMESTAMPDIFF(MINUTE, sp.created_at, NOW()) as minutes_since_creation
                FROM staff_promotions sp
                JOIN staff s ON sp.staff_id = s.id
                LEFT JOIN ranks r_from ON sp.rank_from = r_from.id
                LEFT JOIN ranks r_to ON sp.rank_to = r_to.id
                WHERE sp.can_rollback = 1 
                AND sp.created_at >= DATE_SUB(NOW(), INTERVAL ? HOUR)
                AND sp.rolled_back_at IS NULL
                ORDER BY sp.created_at DESC
            ");
            $stmt->execute([$hours]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Failed to fetch rollbackable promotions: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get user's IP address
     * 
     * @return string IP address
     */
    private function getUserIP() {
        // Check for various proxy headers
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
        } else {
            $ip = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
        }
        
        // Return first IP if multiple IPs are present
        $ips = explode(',', $ip);
        return trim($ips[0]);
    }
    
    /**
     * Generate audit report
     * 
     * @param array $filters Filter criteria (date_from, date_to, action_type, user_id)
     * @return array Audit trail entries matching filters
     */
    public function generateAuditReport($filters = []) {
        try {
            $where = ['1=1'];
            $params = [];
            
            if (!empty($filters['date_from'])) {
                $where[] = 'created_at >= ?';
                $params[] = $filters['date_from'];
            }
            
            if (!empty($filters['date_to'])) {
                $where[] = 'created_at <= ?';
                $params[] = $filters['date_to'];
            }
            
            if (!empty($filters['action_type'])) {
                $where[] = 'action_type = ?';
                $params[] = $filters['action_type'];
            }
            
            if (!empty($filters['user_id'])) {
                $where[] = 'user_id = ?';
                $params[] = $filters['user_id'];
            }
            
            if (!empty($filters['staff_id'])) {
                $where[] = 'staff_id = ?';
                $params[] = $filters['staff_id'];
            }
            
            $sql = "
                SELECT at.*, s.service_number, s.first_name, s.last_name
                FROM audit_trail at
                LEFT JOIN staff s ON at.staff_id = s.id
                WHERE " . implode(' AND ', $where) . "
                ORDER BY at.created_at DESC
            ";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Failed to generate audit report: " . $e->getMessage());
            return [];
        }
    }
}
