<?php
/**
 * ARMIS Security and Audit Service
 * Handles security logging, audit trails, and compliance monitoring
 */

class SecurityAuditService {
    private $db;
    
    public function __construct() {
        $this->db = getDbConnection();
    }
    
    /**
     * Log security event
     */
    public function logSecurityEvent($userId, $action, $resource = null, $resourceId = null, $oldValues = null, $newValues = null, $severity = 'medium') {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO security_audit_log 
                (user_id, action, resource, resource_id, old_values, new_values, ip_address, user_agent, session_id, timestamp, severity)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?)
            ");
            
            $stmt->execute([
                $userId,
                $action,
                $resource,
                $resourceId,
                $oldValues ? json_encode($oldValues) : null,
                $newValues ? json_encode($newValues) : null,
                $this->getClientIP(),
                $_SERVER['HTTP_USER_AGENT'] ?? '',
                session_id(),
                $severity
            ]);
            
            return true;
            
        } catch (Exception $e) {
            error_log("Security audit logging failed: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Log user authentication event
     */
    public function logAuthEvent($username, $userId, $action, $successful = true, $details = null) {
        $this->logSecurityEvent(
            $userId,
            "auth_{$action}",
            'authentication',
            $username,
            null,
            [
                'successful' => $successful,
                'details' => $details,
                'timestamp' => date('c')
            ],
            $successful ? 'low' : 'high'
        );
    }
    
    /**
     * Log data access event
     */
    public function logDataAccess($userId, $dataType, $dataId, $action = 'read') {
        $this->logSecurityEvent(
            $userId,
            "data_{$action}",
            $dataType,
            $dataId,
            null,
            ['action' => $action, 'timestamp' => date('c')],
            'low'
        );
    }
    
    /**
     * Log data modification event
     */
    public function logDataModification($userId, $dataType, $dataId, $oldData, $newData) {
        $this->logSecurityEvent(
            $userId,
            'data_modify',
            $dataType,
            $dataId,
            $oldData,
            $newData,
            'medium'
        );
    }
    
    /**
     * Log administrative action
     */
    public function logAdminAction($userId, $action, $target = null, $details = null) {
        $this->logSecurityEvent(
            $userId,
            "admin_{$action}",
            'administration',
            $target,
            null,
            $details,
            'high'
        );
    }
    
    /**
     * Get security audit logs with filtering
     */
    public function getAuditLogs($filters = [], $page = 1, $limit = 50) {
        try {
            $where = ['1=1'];
            $params = [];
            
            // Apply filters
            if (!empty($filters['user_id'])) {
                $where[] = 'user_id = ?';
                $params[] = $filters['user_id'];
            }
            
            if (!empty($filters['action'])) {
                $where[] = 'action LIKE ?';
                $params[] = '%' . $filters['action'] . '%';
            }
            
            if (!empty($filters['resource'])) {
                $where[] = 'resource = ?';
                $params[] = $filters['resource'];
            }
            
            if (!empty($filters['severity'])) {
                $where[] = 'severity = ?';
                $params[] = $filters['severity'];
            }
            
            if (!empty($filters['date_from'])) {
                $where[] = 'timestamp >= ?';
                $params[] = $filters['date_from'];
            }
            
            if (!empty($filters['date_to'])) {
                $where[] = 'timestamp <= ?';
                $params[] = $filters['date_to'];
            }
            
            $offset = ($page - 1) * $limit;
            
            // Get total count
            $countQuery = "
                SELECT COUNT(*) as total 
                FROM security_audit_log sal
                LEFT JOIN staff s ON sal.user_id = s.id
                WHERE " . implode(' AND ', $where);
            
            $stmt = $this->db->prepare($countQuery);
            $stmt->execute($params);
            $total = $stmt->fetch()['total'];
            
            // Get records
            $query = "
                SELECT sal.*, s.username, s.first_name, s.last_name
                FROM security_audit_log sal
                LEFT JOIN staff s ON sal.user_id = s.id
                WHERE " . implode(' AND ', $where) . "
                ORDER BY sal.timestamp DESC
                LIMIT ? OFFSET ?
            ";
            
            $params[] = $limit;
            $params[] = $offset;
            
            $stmt = $this->db->prepare($query);
            $stmt->execute($params);
            $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            return [
                'logs' => $logs,
                'total' => $total,
                'page' => $page,
                'limit' => $limit,
                'total_pages' => ceil($total / $limit)
            ];
            
        } catch (Exception $e) {
            error_log("Audit logs retrieval failed: " . $e->getMessage());
            return [
                'logs' => [],
                'total' => 0,
                'page' => $page,
                'limit' => $limit,
                'total_pages' => 0
            ];
        }
    }
    
    /**
     * Generate security compliance report
     */
    public function generateComplianceReport($dateFrom, $dateTo) {
        try {
            $report = [
                'period' => [
                    'from' => $dateFrom,
                    'to' => $dateTo
                ],
                'summary' => [],
                'metrics' => [],
                'violations' => [],
                'recommendations' => []
            ];
            
            // Authentication metrics
            $authMetrics = $this->getAuthMetrics($dateFrom, $dateTo);
            $report['metrics']['authentication'] = $authMetrics;
            
            // Data access metrics
            $accessMetrics = $this->getDataAccessMetrics($dateFrom, $dateTo);
            $report['metrics']['data_access'] = $accessMetrics;
            
            // Security violations
            $violations = $this->getSecurityViolations($dateFrom, $dateTo);
            $report['violations'] = $violations;
            
            // Generate recommendations
            $report['recommendations'] = $this->generateSecurityRecommendations($authMetrics, $accessMetrics, $violations);
            
            // Summary
            $report['summary'] = [
                'total_events' => array_sum(array_column($authMetrics, 'count')) + array_sum(array_column($accessMetrics, 'count')),
                'failed_logins' => $authMetrics['failed_logins'] ?? 0,
                'successful_logins' => $authMetrics['successful_logins'] ?? 0,
                'data_modifications' => $accessMetrics['modifications'] ?? 0,
                'security_violations' => count($violations),
                'compliance_score' => $this->calculateComplianceScore($authMetrics, $violations)
            ];
            
            return $report;
            
        } catch (Exception $e) {
            error_log("Compliance report generation failed: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Get authentication metrics
     */
    private function getAuthMetrics($dateFrom, $dateTo) {
        $stmt = $this->db->prepare("
            SELECT 
                action,
                JSON_EXTRACT(new_values, '$.successful') as successful,
                COUNT(*) as count
            FROM security_audit_log
            WHERE action LIKE 'auth_%' 
            AND timestamp BETWEEN ? AND ?
            GROUP BY action, JSON_EXTRACT(new_values, '$.successful')
        ");
        $stmt->execute([$dateFrom, $dateTo]);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $metrics = [
            'successful_logins' => 0,
            'failed_logins' => 0,
            'mfa_setups' => 0,
            'mfa_verifications' => 0
        ];
        
        foreach ($results as $result) {
            if ($result['action'] === 'auth_login') {
                if ($result['successful'] == 1) {
                    $metrics['successful_logins'] = $result['count'];
                } else {
                    $metrics['failed_logins'] = $result['count'];
                }
            } elseif ($result['action'] === 'auth_mfa_setup') {
                $metrics['mfa_setups'] = $result['count'];
            } elseif ($result['action'] === 'auth_mfa_verify') {
                $metrics['mfa_verifications'] = $result['count'];
            }
        }
        
        return $metrics;
    }
    
    /**
     * Get data access metrics
     */
    private function getDataAccessMetrics($dateFrom, $dateTo) {
        $stmt = $this->db->prepare("
            SELECT 
                action,
                resource,
                COUNT(*) as count
            FROM security_audit_log
            WHERE action LIKE 'data_%' 
            AND timestamp BETWEEN ? AND ?
            GROUP BY action, resource
        ");
        $stmt->execute([$dateFrom, $dateTo]);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $metrics = [
            'reads' => 0,
            'modifications' => 0,
            'deletions' => 0,
            'by_resource' => []
        ];
        
        foreach ($results as $result) {
            $resource = $result['resource'];
            if (!isset($metrics['by_resource'][$resource])) {
                $metrics['by_resource'][$resource] = 0;
            }
            $metrics['by_resource'][$resource] += $result['count'];
            
            if ($result['action'] === 'data_read') {
                $metrics['reads'] += $result['count'];
            } elseif ($result['action'] === 'data_modify') {
                $metrics['modifications'] += $result['count'];
            } elseif ($result['action'] === 'data_delete') {
                $metrics['deletions'] += $result['count'];
            }
        }
        
        return $metrics;
    }
    
    /**
     * Get security violations
     */
    private function getSecurityViolations($dateFrom, $dateTo) {
        $stmt = $this->db->prepare("
            SELECT *
            FROM security_audit_log
            WHERE severity IN ('high', 'critical')
            AND timestamp BETWEEN ? AND ?
            ORDER BY timestamp DESC
        ");
        $stmt->execute([$dateFrom, $dateTo]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Generate security recommendations
     */
    private function generateSecurityRecommendations($authMetrics, $accessMetrics, $violations) {
        $recommendations = [];
        
        // High failed login ratio
        $totalLogins = $authMetrics['successful_logins'] + $authMetrics['failed_logins'];
        if ($totalLogins > 0 && ($authMetrics['failed_logins'] / $totalLogins) > 0.2) {
            $recommendations[] = [
                'type' => 'authentication',
                'priority' => 'high',
                'message' => 'High failed login ratio detected. Consider implementing account lockout policies.',
                'metric' => round(($authMetrics['failed_logins'] / $totalLogins) * 100, 2) . '% failed logins'
            ];
        }
        
        // Low MFA adoption
        if ($authMetrics['successful_logins'] > 0 && $authMetrics['mfa_verifications'] < ($authMetrics['successful_logins'] * 0.5)) {
            $recommendations[] = [
                'type' => 'mfa',
                'priority' => 'medium',
                'message' => 'Low MFA adoption rate. Consider enforcing MFA for all users.',
                'metric' => round(($authMetrics['mfa_verifications'] / $authMetrics['successful_logins']) * 100, 2) . '% MFA usage'
            ];
        }
        
        // High number of violations
        if (count($violations) > 10) {
            $recommendations[] = [
                'type' => 'security',
                'priority' => 'critical',
                'message' => 'High number of security violations detected. Immediate review required.',
                'metric' => count($violations) . ' violations'
            ];
        }
        
        // High data modification rate
        if ($accessMetrics['modifications'] > 1000) {
            $recommendations[] = [
                'type' => 'data_protection',
                'priority' => 'medium',
                'message' => 'High data modification rate. Consider implementing additional approval workflows.',
                'metric' => $accessMetrics['modifications'] . ' modifications'
            ];
        }
        
        return $recommendations;
    }
    
    /**
     * Calculate compliance score
     */
    private function calculateComplianceScore($authMetrics, $violations) {
        $score = 100;
        
        // Deduct points for failed logins
        $totalLogins = $authMetrics['successful_logins'] + $authMetrics['failed_logins'];
        if ($totalLogins > 0) {
            $failureRate = ($authMetrics['failed_logins'] / $totalLogins);
            $score -= ($failureRate * 30); // Max 30 points deduction
        }
        
        // Deduct points for violations
        $score -= (count($violations) * 5); // 5 points per violation
        
        // Ensure score doesn't go below 0
        return max(0, round($score, 2));
    }
    
    /**
     * Get client IP address
     */
    private function getClientIP() {
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            return $_SERVER['HTTP_CLIENT_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            return $_SERVER['HTTP_X_FORWARDED_FOR'];
        } else {
            return $_SERVER['REMOTE_ADDR'] ?? '';
        }
    }
    
    /**
     * Encrypt sensitive data
     */
    public function encryptSensitiveData($data, $classificationLevel = 'CONFIDENTIAL') {
        try {
            // Generate encryption key
            $key = random_bytes(32); // 256-bit key
            $iv = random_bytes(16);  // 128-bit IV
            
            // Encrypt data
            $encryptedData = openssl_encrypt($data, 'AES-256-CBC', $key, 0, $iv);
            
            if ($encryptedData === false) {
                throw new Exception('Encryption failed');
            }
            
            // Store encryption metadata
            $keyId = $this->storeEncryptionKey($key, $classificationLevel);
            
            return [
                'data' => $encryptedData,
                'iv' => base64_encode($iv),
                'key_id' => $keyId,
                'classification' => $classificationLevel
            ];
            
        } catch (Exception $e) {
            error_log("Data encryption failed: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Decrypt sensitive data
     */
    public function decryptSensitiveData($encryptedData, $iv, $keyId) {
        try {
            // Retrieve encryption key
            $key = $this->retrieveEncryptionKey($keyId);
            if (!$key) {
                throw new Exception('Encryption key not found');
            }
            
            // Decrypt data
            $decryptedData = openssl_decrypt($encryptedData, 'AES-256-CBC', $key, 0, base64_decode($iv));
            
            if ($decryptedData === false) {
                throw new Exception('Decryption failed');
            }
            
            return $decryptedData;
            
        } catch (Exception $e) {
            error_log("Data decryption failed: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Store encryption key securely
     */
    private function storeEncryptionKey($key, $classification) {
        // In production, this should use a proper key management system
        $keyId = bin2hex(random_bytes(16));
        $encryptedKey = base64_encode($key); // This should be encrypted with a master key
        
        // Store in secure location (database, key vault, etc.)
        // For demo purposes, we'll use a simple file storage
        $keyFile = __DIR__ . '/../../shared/keys/' . $keyId . '.key';
        $keyDir = dirname($keyFile);
        
        if (!is_dir($keyDir)) {
            mkdir($keyDir, 0700, true);
        }
        
        file_put_contents($keyFile, $encryptedKey);
        
        return $keyId;
    }
    
    /**
     * Retrieve encryption key
     */
    private function retrieveEncryptionKey($keyId) {
        $keyFile = __DIR__ . '/../../shared/keys/' . $keyId . '.key';
        
        if (!file_exists($keyFile)) {
            return false;
        }
        
        $encryptedKey = file_get_contents($keyFile);
        return base64_decode($encryptedKey); // This should be decrypted with a master key
    }
}
?>