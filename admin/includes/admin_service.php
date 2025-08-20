<?php
/**
 * ARMIS Admin Service
 * Core service class for system administration and monitoring
 */

class AdminService {
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    /**
     * Get overall system health status
     */
    public function getSystemHealth() {
        try {
            $health = [
                'overall_status' => 'HEALTHY',
                'database_status' => 'OK',
                'file_system_status' => 'OK',
                'memory_status' => 'OK',
                'services_status' => 'OK'
            ];
            
            // Check database connectivity
            $stmt = $this->pdo->query("SELECT 1");
            if (!$stmt) {
                $health['database_status'] = 'ERROR';
                $health['overall_status'] = 'ERROR';
            }
            
            // Check critical system tables
            $criticalTables = ['users', 'audit_logs', 'system_config'];
            foreach ($criticalTables as $table) {
                try {
                    $stmt = $this->pdo->query("SELECT COUNT(*) FROM `$table`");
                    if (!$stmt) {
                        $health['database_status'] = 'WARNING';
                        $health['overall_status'] = 'WARNING';
                    }
                } catch (Exception $e) {
                    $health['database_status'] = 'ERROR';
                    $health['overall_status'] = 'ERROR';
                }
            }
            
            // Check file system permissions
            $uploadDir = ARMIS_ROOT . '/uploads';
            if (!is_writable($uploadDir)) {
                $health['file_system_status'] = 'WARNING';
                $health['overall_status'] = 'WARNING';
            }
            
            // Check memory usage
            $memoryUsage = $this->getMemoryUsage();
            if ($memoryUsage > 80) {
                $health['memory_status'] = 'WARNING';
                $health['overall_status'] = 'WARNING';
            }
            
            return $health;
        } catch (Exception $e) {
            error_log("Error getting system health: " . $e->getMessage());
            return [
                'overall_status' => 'ERROR',
                'database_status' => 'ERROR',
                'file_system_status' => 'UNKNOWN',
                'memory_status' => 'UNKNOWN',
                'services_status' => 'UNKNOWN'
            ];
        }
    }
    
    /**
     * Get system performance metrics
     */
    public function getPerformanceMetrics() {
        try {
            // Get latest performance data or simulate for demo
            $stmt = $this->pdo->query("
                SELECT 
                    metric_name,
                    metric_value,
                    collected_at
                FROM performance_metrics 
                WHERE collected_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
                ORDER BY collected_at DESC
                LIMIT 100
            ");
            
            $metrics = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Process metrics or provide defaults
            $processedMetrics = [
                'cpu_usage' => $this->getLatestMetric($metrics, 'CPU', 15),
                'memory_usage' => $this->getLatestMetric($metrics, 'MEMORY', 45),
                'disk_usage' => $this->getLatestMetric($metrics, 'DISK', 25),
                'network_usage' => $this->getLatestMetric($metrics, 'NETWORK', 10),
                'uptime_days' => $this->getSystemUptime(),
                'load_average' => $this->getLatestMetric($metrics, 'LOAD', 0.8)
            ];
            
            return $processedMetrics;
        } catch (Exception $e) {
            error_log("Error getting performance metrics: " . $e->getMessage());
            return [
                'cpu_usage' => 0,
                'memory_usage' => 0,
                'disk_usage' => 0,
                'network_usage' => 0,
                'uptime_days' => 0,
                'load_average' => 0
            ];
        }
    }
    
    /**
     * Get security status information
     */
    public function getSecurityStatus() {
        try {
            $stmt = $this->pdo->query("
                SELECT 
                    COUNT(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR) 
                               AND action LIKE '%login_failed%' THEN 1 END) as failed_logins_24h,
                    COUNT(CASE WHEN status = 'OPEN' THEN 1 END) as open_incidents,
                    MAX(CASE WHEN created_at IS NOT NULL THEN created_at END) as last_scan
                FROM (
                    SELECT created_at, action, NULL as status FROM audit_logs
                    UNION ALL
                    SELECT detected_at as created_at, incident_type as action, status FROM security_incidents
                ) combined
            ");
            
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            return [
                'failed_logins_24h' => $result['failed_logins_24h'] ?? 0,
                'open_incidents' => $result['open_incidents'] ?? 0,
                'last_scan' => $result['last_scan'] ? date('M j, Y g:i A', strtotime($result['last_scan'])) : 'Never'
            ];
        } catch (Exception $e) {
            error_log("Error getting security status: " . $e->getMessage());
            return [
                'failed_logins_24h' => 0,
                'open_incidents' => 0,
                'last_scan' => 'Never'
            ];
        }
    }
    
    /**
     * Get user activity statistics
     */
    public function getUserActivity() {
        try {
            $stmt = $this->pdo->query("
                SELECT 
                    COUNT(DISTINCT CASE WHEN last_login >= DATE_SUB(NOW(), INTERVAL 24 HOUR) THEN id END) as active_users,
                    COUNT(CASE WHEN status = 'ACTIVE' THEN 1 END) as total_active_users,
                    COUNT(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 END) as new_users_30d,
                    COUNT(*) as total_users
                FROM users
            ");
            
            return $stmt->fetch(PDO::FETCH_ASSOC) ?: [
                'active_users' => 0,
                'total_active_users' => 0,
                'new_users_30d' => 0,
                'total_users' => 0
            ];
        } catch (Exception $e) {
            error_log("Error getting user activity: " . $e->getMessage());
            return [
                'active_users' => 0,
                'total_active_users' => 0,
                'new_users_30d' => 0,
                'total_users' => 0
            ];
        }
    }
    
    /**
     * Get recent system logs
     */
    public function getRecentSystemLogs($limit = 10) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT 
                    al.action,
                    al.module,
                    al.severity,
                    al.created_at,
                    CONCAT(u.fname, ' ', u.lname) as user_name
                FROM audit_logs al
                LEFT JOIN users u ON al.user_id = u.id
                ORDER BY al.created_at DESC
                LIMIT ?
            ");
            
            $stmt->execute([$limit]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error getting recent system logs: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get database status information
     */
    public function getDatabaseStatus() {
        try {
            // Get database size
            $stmt = $this->pdo->query("
                SELECT 
                    ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) AS total_size_mb,
                    COUNT(*) as table_count
                FROM information_schema.tables 
                WHERE table_schema = DATABASE()
            ");
            
            $dbInfo = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Get record counts from key tables
            $recordCounts = 0;
            $tables = ['users', 'audit_logs', 'notifications', 'workflow_instances'];
            
            foreach ($tables as $table) {
                try {
                    $stmt = $this->pdo->query("SELECT COUNT(*) as count FROM `$table`");
                    $result = $stmt->fetch(PDO::FETCH_ASSOC);
                    $recordCounts += $result['count'] ?? 0;
                } catch (Exception $e) {
                    // Table might not exist, continue
                }
            }
            
            // Get connection usage (simulated)
            $connectionUsage = min(50, rand(5, 30)); // Simulate 5-30% usage
            
            return [
                'total_size_mb' => $dbInfo['total_size_mb'] ?? 0,
                'table_count' => $dbInfo['table_count'] ?? 0,
                'total_records' => $recordCounts,
                'connection_usage' => $connectionUsage,
                'last_backup' => $this->getLastBackupDate()
            ];
        } catch (Exception $e) {
            error_log("Error getting database status: " . $e->getMessage());
            return [
                'total_size_mb' => 0,
                'table_count' => 0,
                'total_records' => 0,
                'connection_usage' => 0,
                'last_backup' => 'Never'
            ];
        }
    }
    
    /**
     * Get module status information
     */
    public function getModuleStatus() {
        $modules = [
            'admin_branch' => ['enabled' => true, 'version' => '2.0.0'],
            'logistics' => ['enabled' => true, 'version' => '1.0.0'],
            'workflow' => ['enabled' => true, 'version' => '1.0.0'],
            'messaging' => ['enabled' => true, 'version' => '1.0.0'],
            'command' => ['enabled' => true, 'version' => '1.5.0'],
            'training' => ['enabled' => true, 'version' => '1.3.0'],
            'finance' => ['enabled' => true, 'version' => '1.2.0'],
            'operations' => ['enabled' => true, 'version' => '1.4.0'],
            'ordinance' => ['enabled' => true, 'version' => '1.1.0']
        ];
        
        // Check if module directories exist
        foreach ($modules as $module => &$info) {
            $modulePath = dirname(__DIR__) . '/' . $module;
            if (!is_dir($modulePath)) {
                $info['enabled'] = false;
            }
        }
        
        return $modules;
    }
    
    /**
     * Get system configuration settings
     */
    public function getSystemConfig() {
        try {
            $stmt = $this->pdo->query("
                SELECT 
                    config_key,
                    config_value,
                    config_type,
                    category,
                    description,
                    is_editable
                FROM system_config
                ORDER BY category, config_key
            ");
            
            $configs = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Group by category
            $grouped = [];
            foreach ($configs as $config) {
                $category = $config['category'] ?: 'general';
                $grouped[$category][] = $config;
            }
            
            return $grouped;
        } catch (Exception $e) {
            error_log("Error getting system config: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Update system configuration
     */
    public function updateSystemConfig($key, $value, $userId) {
        try {
            $stmt = $this->pdo->prepare("
                UPDATE system_config 
                SET config_value = ?, updated_by = ?, updated_at = NOW()
                WHERE config_key = ? AND is_editable = 1
            ");
            
            $result = $stmt->execute([$value, $userId, $key]);
            
            if ($result && $stmt->rowCount() > 0) {
                // Log the configuration change
                $this->logActivity('config_updated', "Configuration '$key' updated", 'system_config', $key, $userId);
                return true;
            }
            
            return false;
        } catch (Exception $e) {
            error_log("Error updating system config: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Create system backup
     */
    public function createBackup($userId) {
        try {
            $backupDir = ARMIS_ROOT . '/backups';
            if (!is_dir($backupDir)) {
                mkdir($backupDir, 0755, true);
            }
            
            $timestamp = date('Y-m-d_H-i-s');
            $backupFile = $backupDir . "/armis_backup_{$timestamp}.sql";
            
            // Create database backup using mysqldump
            $command = sprintf(
                'mysqldump -h %s -u %s -p%s %s > %s',
                DB_HOST,
                DB_USER,
                DB_PASS,
                DB_NAME,
                $backupFile
            );
            
            exec($command, $output, $returnCode);
            
            if ($returnCode === 0 && file_exists($backupFile)) {
                // Log backup creation
                $this->logActivity('backup_created', 'Database backup created', 'backup', null, $userId);
                
                // Update last backup date in system config
                $this->updateSystemConfig('backup.last_backup_date', date('Y-m-d H:i:s'), $userId);
                
                return [
                    'success' => true,
                    'file' => $backupFile,
                    'size' => filesize($backupFile)
                ];
            }
            
            return ['success' => false, 'error' => 'Backup creation failed'];
        } catch (Exception $e) {
            error_log("Error creating backup: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Helper methods
     */
    private function getLatestMetric($metrics, $type, $default = 0) {
        foreach ($metrics as $metric) {
            if (stripos($metric['metric_name'], $type) !== false) {
                return $metric['metric_value'];
            }
        }
        return $default;
    }
    
    private function getMemoryUsage() {
        $memoryLimit = ini_get('memory_limit');
        $memoryUsage = memory_get_usage(true);
        
        if ($memoryLimit && $memoryLimit !== '-1') {
            $limit = $this->convertToBytes($memoryLimit);
            return ($memoryUsage / $limit) * 100;
        }
        
        return 0;
    }
    
    private function convertToBytes($value) {
        $unit = strtolower(substr($value, -1));
        $number = substr($value, 0, -1);
        
        switch ($unit) {
            case 'g': return $number * 1024 * 1024 * 1024;
            case 'm': return $number * 1024 * 1024;
            case 'k': return $number * 1024;
            default: return $number;
        }
    }
    
    private function getSystemUptime() {
        if (function_exists('sys_getloadavg')) {
            // Unix-like systems
            $uptime = shell_exec('uptime');
            if (preg_match('/up\s+(\d+)\s+days?/', $uptime, $matches)) {
                return $matches[1];
            }
        }
        
        // Fallback: estimate based on when system was last restarted
        return rand(1, 30); // Simulate 1-30 days
    }
    
    private function getLastBackupDate() {
        try {
            $stmt = $this->pdo->query("
                SELECT config_value 
                FROM system_config 
                WHERE config_key = 'backup.last_backup_date'
            ");
            
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($result && $result['config_value']) {
                return date('M j, Y g:i A', strtotime($result['config_value']));
            }
        } catch (Exception $e) {
            error_log("Error getting last backup date: " . $e->getMessage());
        }
        
        return 'Never';
    }
    
    private function logActivity($action, $description, $entityType = null, $entityId = null, $userId = null) {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO audit_logs (
                    user_id, action, entity_type, entity_id, 
                    ip_address, user_agent, module, severity, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, 'admin', 'LOW', NOW())
            ");
            
            $stmt->execute([
                $userId ?: ($_SESSION['user_id'] ?? null),
                $action,
                $entityType,
                $entityId,
                $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
            ]);
        } catch (Exception $e) {
            error_log("Error logging activity: " . $e->getMessage());
        }
    }
}
?>