<?php
/**
 * Enhanced Audit Logger for Military-Grade User Profile Module
 * Provides comprehensive audit trail and compliance logging
 */

class AuditLogger {
    
    private $pdo;
    private $userId;
    private $sessionId;
    private $ipAddress;
    private $userAgent;
    
    public function __construct($pdo = null, $userId = null) {
        $this->pdo = $pdo ?: getDbConnection();
        $this->userId = $userId ?: ($_SESSION['user_id'] ?? null);
        $this->sessionId = session_id();
        $this->ipAddress = $this->getClientIP();
        $this->userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    }
    
    /**
     * Log profile change with comprehensive audit trail
     */
    public function logProfileChange($staffId, $tableName, $recordId, $action, $fieldName = null, $oldValue = null, $newValue = null, $reason = null) {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO user_profile_audit (
                    staff_id, user_id, table_name, record_id, action_type,
                    field_name, old_value, new_value, change_reason,
                    ip_address, user_agent, session_id, severity, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            
            $severity = $this->calculateSeverity($tableName, $fieldName, $action);
            
            $stmt->execute([
                $staffId,
                $this->userId,
                $tableName,
                $recordId,
                $action,
                $fieldName,
                $this->sanitizeForAudit($oldValue),
                $this->sanitizeForAudit($newValue),
                $reason,
                $this->ipAddress,
                $this->userAgent,
                $this->sessionId,
                $severity
            ]);
            
            // Log high-severity changes to security audit log as well
            if ($severity === 'HIGH' || $severity === 'CRITICAL') {
                $this->logSecurityEvent("profile_change_$action", $tableName, $recordId, [
                    'field' => $fieldName,
                    'old_value' => $this->sanitizeForAudit($oldValue),
                    'new_value' => $this->sanitizeForAudit($newValue)
                ]);
            }
            
            return true;
            
        } catch (Exception $e) {
            error_log("Audit logging failed: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Log security event
     */
    public function logSecurityEvent($action, $resource = null, $resourceId = null, $additionalData = null) {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO security_audit_log (
                    user_id, action, resource, resource_id, new_values,
                    ip_address, user_agent, session_id, severity, risk_score, additional_data, timestamp
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            
            $severity = $this->calculateSecuritySeverity($action);
            $riskScore = $this->calculateRiskScore($action, $additionalData);
            
            $stmt->execute([
                $this->userId,
                $action,
                $resource,
                $resourceId,
                $additionalData ? json_encode($additionalData) : null,
                $this->ipAddress,
                $this->userAgent,
                $this->sessionId,
                $severity,
                $riskScore,
                $additionalData ? json_encode($additionalData) : null
            ]);
            
            // Alert for high-risk events
            if ($riskScore >= 80) {
                $this->triggerSecurityAlert($action, $riskScore, $additionalData);
            }
            
            return true;
            
        } catch (Exception $e) {
            error_log("Security audit logging failed: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Log data access
     */
    public function logDataAccess($accessedUserId, $dataType, $accessType, $success = true, $reason = null) {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO data_access_log (
                    user_id, accessed_user_id, data_type, access_type,
                    success, access_reason, ip_address, user_agent, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            
            $stmt->execute([
                $this->userId,
                $accessedUserId,
                $dataType,
                $accessType,
                $success,
                $reason,
                $this->ipAddress,
                $this->userAgent
            ]);
            
            // Log security event for sensitive data access
            if (in_array($dataType, ['security', 'medical', 'personal']) && $accessType !== 'view') {
                $this->logSecurityEvent("sensitive_data_access", $dataType, $accessedUserId, [
                    'access_type' => $accessType,
                    'success' => $success
                ]);
            }
            
            return true;
            
        } catch (Exception $e) {
            error_log("Data access logging failed: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Log validation attempt
     */
    public function logValidation($fieldType, $success, $context = 'profile_update') {
        try {
            // Use security audit log for validation events
            $this->logSecurityEvent("field_validation", $fieldType, null, [
                'context' => $context,
                'success' => $success,
                'field_type' => $fieldType
            ]);
            
            return true;
            
        } catch (Exception $e) {
            error_log("Validation logging failed: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Log session activity
     */
    public function logSessionActivity($action, $details = null) {
        try {
            // Update or create session audit record
            if ($action === 'login') {
                $stmt = $this->pdo->prepare("
                    INSERT INTO user_session_audit (
                        user_id, session_id, login_time, ip_address, user_agent,
                        device_fingerprint, location_data
                    ) VALUES (?, ?, NOW(), ?, ?, ?, ?)
                ");
                
                $stmt->execute([
                    $this->userId,
                    $this->sessionId,
                    $this->ipAddress,
                    $this->userAgent,
                    $this->generateDeviceFingerprint(),
                    $this->getLocationData()
                ]);
            } elseif ($action === 'logout') {
                $stmt = $this->pdo->prepare("
                    UPDATE user_session_audit 
                    SET logout_time = NOW(),
                        session_duration = TIMESTAMPDIFF(SECOND, login_time, NOW()),
                        logout_reason = ?
                    WHERE user_id = ? AND session_id = ? AND logout_time IS NULL
                ");
                
                $stmt->execute([
                    $details['reason'] ?? 'manual',
                    $this->userId,
                    $this->sessionId
                ]);
            }
            
            return true;
            
        } catch (Exception $e) {
            error_log("Session audit logging failed: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Update profile completion tracking
     */
    public function updateProfileCompletion($staffId, $sectionName, $completionPercentage, $mandatoryComplete = false, $optionalComplete = false) {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO profile_completion_tracking (
                    staff_id, section_name, completion_percentage,
                    mandatory_fields_complete, optional_fields_complete
                ) VALUES (?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                    completion_percentage = VALUES(completion_percentage),
                    mandatory_fields_complete = VALUES(mandatory_fields_complete),
                    optional_fields_complete = VALUES(optional_fields_complete),
                    last_updated = NOW()
            ");
            
            $stmt->execute([
                $staffId,
                $sectionName,
                $completionPercentage,
                $mandatoryComplete,
                $optionalComplete
            ]);
            
            // Log significant completion milestones
            if ($completionPercentage >= 100) {
                $this->logSecurityEvent("profile_section_completed", $sectionName, $staffId, [
                    'completion_percentage' => $completionPercentage
                ]);
            }
            
            return true;
            
        } catch (Exception $e) {
            error_log("Profile completion tracking failed: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Log compliance monitoring event
     */
    public function logComplianceEvent($staffId, $complianceType, $status, $score = null, $violations = 0) {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO compliance_monitoring (
                    staff_id, compliance_type, compliance_status,
                    last_review_date, next_review_date, compliance_score,
                    violations_count, reviewer_id
                ) VALUES (?, ?, ?, CURDATE(), DATE_ADD(CURDATE(), INTERVAL 1 YEAR), ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                    compliance_status = VALUES(compliance_status),
                    last_review_date = VALUES(last_review_date),
                    compliance_score = VALUES(compliance_score),
                    violations_count = VALUES(violations_count),
                    reviewer_id = VALUES(reviewer_id),
                    updated_at = NOW()
            ");
            
            $stmt->execute([
                $staffId,
                $complianceType,
                $status,
                $score,
                $violations,
                $this->userId
            ]);
            
            // Log non-compliance as security event
            if ($status === 'Non_Compliant') {
                $this->logSecurityEvent("compliance_violation", $complianceType, $staffId, [
                    'violations_count' => $violations,
                    'compliance_score' => $score
                ]);
            }
            
            return true;
            
        } catch (Exception $e) {
            error_log("Compliance monitoring failed: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get audit trail for a specific staff member
     */
    public function getAuditTrail($staffId, $limit = 50, $offset = 0) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT 
                    upa.*, 
                    s.first_name, s.last_name, s.username
                FROM user_profile_audit upa
                LEFT JOIN staff s ON upa.user_id = s.id
                WHERE upa.staff_id = ?
                ORDER BY upa.created_at DESC
                LIMIT ? OFFSET ?
            ");
            
            $stmt->execute([$staffId, $limit, $offset]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (Exception $e) {
            error_log("Error retrieving audit trail: " . $e->getMessage());
            return [];
        }
    }
    
    // Helper methods
    
    private function calculateSeverity($tableName, $fieldName, $action) {
        // Critical operations
        if ($tableName === 'staff_security_clearance' || $fieldName === 'service_number') {
            return 'CRITICAL';
        }
        
        // High severity operations
        if (in_array($tableName, ['staff', 'staff_service_record']) && $action === 'DELETE') {
            return 'HIGH';
        }
        
        // Medium severity operations
        if (in_array($fieldName, ['rank_id', 'unit_id', 'status', 'role'])) {
            return 'HIGH';
        }
        
        // Default severity
        return 'MEDIUM';
    }
    
    private function calculateSecuritySeverity($action) {
        $highRiskActions = ['login_failed', 'account_locked', 'compliance_violation', 'profile_change_DELETE'];
        $mediumRiskActions = ['field_validation', 'sensitive_data_access', 'profile_section_completed'];
        
        if (in_array($action, $highRiskActions)) {
            return 'high';
        } elseif (in_array($action, $mediumRiskActions)) {
            return 'medium';
        }
        
        return 'low';
    }
    
    private function calculateRiskScore($action, $additionalData) {
        $score = 10; // Base score
        
        // High-risk actions
        if (in_array($action, ['login_failed', 'account_locked'])) {
            $score += 50;
        }
        
        // Failed operations
        if (isset($additionalData['success']) && !$additionalData['success']) {
            $score += 20;
        }
        
        // Sensitive data access
        if (strpos($action, 'sensitive_data') !== false) {
            $score += 30;
        }
        
        return min(100, $score);
    }
    
    private function sanitizeForAudit($value) {
        if (is_null($value)) return null;
        
        // Truncate long values
        if (strlen($value) > 500) {
            return substr($value, 0, 497) . '...';
        }
        
        return $value;
    }
    
    private function getClientIP() {
        $ipKeys = ['HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'HTTP_CLIENT_IP', 'REMOTE_ADDR'];
        
        foreach ($ipKeys as $key) {
            if (!empty($_SERVER[$key])) {
                $ip = $_SERVER[$key];
                if (strpos($ip, ',') !== false) {
                    $ip = trim(explode(',', $ip)[0]);
                }
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    }
    
    private function generateDeviceFingerprint() {
        $factors = [
            $_SERVER['HTTP_USER_AGENT'] ?? '',
            $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '',
            $_SERVER['HTTP_ACCEPT_ENCODING'] ?? '',
            $this->ipAddress
        ];
        
        return hash('sha256', implode('|', $factors));
    }
    
    private function getLocationData() {
        // Simplified location data - in production, use IP geolocation service
        return "IP: " . $this->ipAddress;
    }
    
    private function triggerSecurityAlert($action, $riskScore, $additionalData) {
        // Log high-risk event for security team review
        error_log("HIGH RISK SECURITY EVENT: $action (Score: $riskScore) for user {$this->userId}");
        
        // In production, this would trigger real-time alerts to security team
        // Could integrate with SIEM systems, email alerts, etc.
    }
}
?>