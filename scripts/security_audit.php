<?php
/**
 * ARMIS Security Audit Script
 * Automated security monitoring and alerting
 */

require_once __DIR__ . '/../shared/database_connection.php';
require_once __DIR__ . '/../shared/security_audit_service.php';

class SecurityAudit {
    private $db;
    private $auditService;
    
    public function __construct() {
        $this->db = getDbConnection();
        $this->auditService = new SecurityAuditService();
    }
    
    public function runSecurityAudit() {
        echo "[" . date('Y-m-d H:i:s') . "] Starting security audit...\n";
        
        $alerts = [];
        
        // Check for suspicious login activity
        $alerts = array_merge($alerts, $this->checkSuspiciousLogins());
        
        // Check for unusual data access patterns
        $alerts = array_merge($alerts, $this->checkUnusualDataAccess());
        
        // Check for failed authentication attempts
        $alerts = array_merge($alerts, $this->checkFailedAuthentications());
        
        // Check for privilege escalations
        $alerts = array_merge($alerts, $this->checkPrivilegeEscalations());
        
        // Check system integrity
        $alerts = array_merge($alerts, $this->checkSystemIntegrity());
        
        // Generate security report
        $this->generateSecurityReport($alerts);
        
        echo "[" . date('Y-m-d H:i:s') . "] Security audit completed. Found " . count($alerts) . " alerts.\n";
        
        return $alerts;
    }
    
    private function checkSuspiciousLogins() {
        $alerts = [];
        
        try {
            // Check for logins from unusual locations/IPs
            $stmt = $this->db->query("
                SELECT ip_address, COUNT(*) as login_count, 
                       COUNT(DISTINCT username) as unique_users
                FROM login_attempts 
                WHERE attempted_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
                AND successful = 1
                GROUP BY ip_address
                HAVING login_count > 50 OR unique_users > 10
            ");
            
            while ($row = $stmt->fetch()) {
                $alerts[] = [
                    'type' => 'suspicious_login_pattern',
                    'severity' => 'high',
                    'message' => "Suspicious login activity from IP {$row['ip_address']}: {$row['login_count']} logins, {$row['unique_users']} different users",
                    'data' => $row
                ];
            }
            
            // Check for off-hours logins
            $stmt = $this->db->query("
                SELECT username, ip_address, attempted_at
                FROM login_attempts 
                WHERE attempted_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
                AND successful = 1
                AND (HOUR(attempted_at) < 6 OR HOUR(attempted_at) > 22)
                ORDER BY attempted_at DESC
                LIMIT 20
            ");
            
            while ($row = $stmt->fetch()) {
                $alerts[] = [
                    'type' => 'off_hours_login',
                    'severity' => 'medium',
                    'message' => "Off-hours login detected: {$row['username']} from {$row['ip_address']} at {$row['attempted_at']}",
                    'data' => $row
                ];
            }
            
        } catch (Exception $e) {
            error_log("Suspicious login check failed: " . $e->getMessage());
        }
        
        return $alerts;
    }
    
    private function checkUnusualDataAccess() {
        $alerts = [];
        
        try {
            // Check for users accessing large amounts of data
            $stmt = $this->db->query("
                SELECT user_id, COUNT(*) as access_count,
                       COUNT(DISTINCT resource) as resource_count
                FROM security_audit_log 
                WHERE timestamp >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
                AND action LIKE 'data_%'
                GROUP BY user_id
                HAVING access_count > 100 OR resource_count > 20
            ");
            
            while ($row = $stmt->fetch()) {
                $userStmt = $this->db->prepare("SELECT username FROM staff WHERE id = ?");
                $userStmt->execute([$row['user_id']]);
                $user = $userStmt->fetch();
                
                $alerts[] = [
                    'type' => 'unusual_data_access',
                    'severity' => 'high',
                    'message' => "Unusual data access pattern: User {$user['username']} accessed {$row['access_count']} records across {$row['resource_count']} resources in the last hour",
                    'data' => array_merge($row, $user ?: [])
                ];
            }
            
        } catch (Exception $e) {
            error_log("Unusual data access check failed: " . $e->getMessage());
        }
        
        return $alerts;
    }
    
    private function checkFailedAuthentications() {
        $alerts = [];
        
        try {
            // Check for brute force attempts
            $stmt = $this->db->query("
                SELECT ip_address, COUNT(*) as failed_count,
                       COUNT(DISTINCT username) as target_users,
                       MIN(attempted_at) as first_attempt,
                       MAX(attempted_at) as last_attempt
                FROM login_attempts 
                WHERE attempted_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
                AND successful = 0
                GROUP BY ip_address
                HAVING failed_count > 20
            ");
            
            while ($row = $stmt->fetch()) {
                $alerts[] = [
                    'type' => 'brute_force_attempt',
                    'severity' => 'critical',
                    'message' => "Potential brute force attack from IP {$row['ip_address']}: {$row['failed_count']} failed attempts targeting {$row['target_users']} users",
                    'data' => $row
                ];
            }
            
            // Check for failed admin logins
            $stmt = $this->db->query("
                SELECT la.*, s.role
                FROM login_attempts la
                JOIN staff s ON la.username = s.username
                WHERE la.attempted_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
                AND la.successful = 0
                AND s.role IN ('administrator', 'admin')
            ");
            
            while ($row = $stmt->fetch()) {
                $alerts[] = [
                    'type' => 'failed_admin_login',
                    'severity' => 'high',
                    'message' => "Failed admin login attempt: {$row['username']} from {$row['ip_address']} at {$row['attempted_at']}",
                    'data' => $row
                ];
            }
            
        } catch (Exception $e) {
            error_log("Failed authentication check failed: " . $e->getMessage());
        }
        
        return $alerts;
    }
    
    private function checkPrivilegeEscalations() {
        $alerts = [];
        
        try {
            // Check for recent privilege changes
            $stmt = $this->db->query("
                SELECT user_id, action, old_values, new_values, timestamp
                FROM security_audit_log 
                WHERE timestamp >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
                AND (action LIKE '%permission%' OR action LIKE '%role%' OR action LIKE '%privilege%')
                ORDER BY timestamp DESC
            ");
            
            while ($row = $stmt->fetch()) {
                $userStmt = $this->db->prepare("SELECT username FROM staff WHERE id = ?");
                $userStmt->execute([$row['user_id']]);
                $user = $userStmt->fetch();
                
                $alerts[] = [
                    'type' => 'privilege_change',
                    'severity' => 'medium',
                    'message' => "Privilege change detected for user {$user['username']}: {$row['action']}",
                    'data' => array_merge($row, $user ?: [])
                ];
            }
            
        } catch (Exception $e) {
            error_log("Privilege escalation check failed: " . $e->getMessage());
        }
        
        return $alerts;
    }
    
    private function checkSystemIntegrity() {
        $alerts = [];
        
        try {
            // Check for unauthorized file modifications
            $criticalFiles = [
                '/var/www/html/config.php',
                '/var/www/html/shared/database_connection.php',
                '/var/www/html/api/gateway.php'
            ];
            
            foreach ($criticalFiles as $file) {
                if (file_exists($file)) {
                    $lastModified = filemtime($file);
                    $age = time() - $lastModified;
                    
                    // Alert if critical files were modified in the last hour
                    if ($age < 3600) {
                        $alerts[] = [
                            'type' => 'critical_file_modification',
                            'severity' => 'critical',
                            'message' => "Critical file modified recently: {$file} (modified " . date('Y-m-d H:i:s', $lastModified) . ")",
                            'data' => ['file' => $file, 'modified_at' => date('Y-m-d H:i:s', $lastModified)]
                        ];
                    }
                }
            }
            
            // Check disk space
            $diskFree = disk_free_space(__DIR__);
            $diskTotal = disk_total_space(__DIR__);
            $diskUsagePercent = (($diskTotal - $diskFree) / $diskTotal) * 100;
            
            if ($diskUsagePercent > 90) {
                $alerts[] = [
                    'type' => 'low_disk_space',
                    'severity' => 'high',
                    'message' => "Low disk space: {$diskUsagePercent}% used",
                    'data' => ['disk_usage_percent' => $diskUsagePercent]
                ];
            }
            
        } catch (Exception $e) {
            error_log("System integrity check failed: " . $e->getMessage());
        }
        
        return $alerts;
    }
    
    private function generateSecurityReport($alerts) {
        try {
            $report = [
                'timestamp' => date('c'),
                'alerts' => $alerts,
                'summary' => [
                    'total_alerts' => count($alerts),
                    'critical' => count(array_filter($alerts, function($a) { return $a['severity'] === 'critical'; })),
                    'high' => count(array_filter($alerts, function($a) { return $a['severity'] === 'high'; })),
                    'medium' => count(array_filter($alerts, function($a) { return $a['severity'] === 'medium'; })),
                    'low' => count(array_filter($alerts, function($a) { return $a['severity'] === 'low'; }))
                ]
            ];
            
            // Save report to file
            $reportFile = __DIR__ . '/../shared/logs/security_report_' . date('Y-m-d_H-i-s') . '.json';
            file_put_contents($reportFile, json_encode($report, JSON_PRETTY_PRINT));
            
            // Log critical alerts immediately
            foreach ($alerts as $alert) {
                if ($alert['severity'] === 'critical') {
                    error_log("CRITICAL SECURITY ALERT: " . $alert['message']);
                    
                    // In production, send immediate notifications here
                    $this->sendSecurityAlert($alert);
                }
            }
            
            echo "[" . date('Y-m-d H:i:s') . "] Security report saved: $reportFile\n";
            
        } catch (Exception $e) {
            error_log("Security report generation failed: " . $e->getMessage());
        }
    }
    
    private function sendSecurityAlert($alert) {
        try {
            // Add alert to notification queue
            $stmt = $this->db->prepare("
                INSERT INTO notification_queue 
                (notification_type, title, message, data, priority, status, created_at)
                VALUES ('security_alert', ?, ?, ?, 'urgent', 'pending', NOW())
            ");
            
            $title = "SECURITY ALERT: " . strtoupper($alert['type']);
            $message = $alert['message'];
            $data = json_encode($alert['data']);
            
            $stmt->execute([$title, $message, $data]);
            
        } catch (Exception $e) {
            error_log("Security alert notification failed: " . $e->getMessage());
        }
    }
}

// Run security audit
try {
    $audit = new SecurityAudit();
    $alerts = $audit->runSecurityAudit();
    
    // Exit with non-zero code if critical alerts found
    $criticalAlerts = array_filter($alerts, function($a) { return $a['severity'] === 'critical'; });
    if (!empty($criticalAlerts)) {
        exit(1);
    }
    
} catch (Exception $e) {
    error_log("Security audit failed: " . $e->getMessage());
    echo "[" . date('Y-m-d H:i:s') . "] ERROR: " . $e->getMessage() . "\n";
    exit(1);
}
?>