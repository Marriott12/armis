<?php
/**
 * ARMIS System Controller
 * Handles system health, status, and version endpoints
 */

class SystemController {
    private $db;
    
    public function __construct() {
        try {
            $this->db = getDbConnection();
        } catch (Exception $e) {
            $this->db = null;
        }
    }
    
    /**
     * System health check endpoint
     */
    public function health($params, $data) {
        $health = [
            'status' => 'healthy',
            'timestamp' => date('c'),
            'version' => ARMIS_API_VERSION,
            'checks' => []
        ];
        
        // Database connectivity check
        $dbHealth = $this->checkDatabase();
        $health['checks']['database'] = $dbHealth;
        
        // Redis connectivity check
        $redisHealth = $this->checkRedis();
        $health['checks']['redis'] = $redisHealth;
        
        // File system check
        $fsHealth = $this->checkFileSystem();
        $health['checks']['filesystem'] = $fsHealth;
        
        // Memory usage check
        $memoryHealth = $this->checkMemoryUsage();
        $health['checks']['memory'] = $memoryHealth;
        
        // Determine overall health
        $allHealthy = true;
        foreach ($health['checks'] as $check) {
            if ($check['status'] !== 'healthy') {
                $allHealthy = false;
                break;
            }
        }
        
        if (!$allHealthy) {
            $health['status'] = 'unhealthy';
        }
        
        return $health;
    }
    
    /**
     * System status endpoint
     */
    public function status($params, $data) {
        return [
            'status' => 'online',
            'environment' => defined('ARMIS_ENV') ? ARMIS_ENV : 'development',
            'api_version' => ARMIS_API_VERSION,
            'system_version' => defined('ARMIS_VERSION') ? ARMIS_VERSION : '1.0.0',
            'php_version' => PHP_VERSION,
            'timezone' => date_default_timezone_get(),
            'uptime' => $this->getSystemUptime(),
            'load_average' => $this->getLoadAverage(),
            'active_users' => $this->getActiveUsersCount()
        ];
    }
    
    /**
     * API version information
     */
    public function version($params, $data) {
        return [
            'api_version' => ARMIS_API_VERSION,
            'system_version' => defined('ARMIS_VERSION') ? ARMIS_VERSION : '1.0.0',
            'php_version' => PHP_VERSION,
            'supported_features' => [
                'jwt_authentication',
                'mfa_support',
                'rate_limiting',
                'cors_handling',
                'api_versioning',
                'audit_logging',
                'real_time_updates',
                'file_encryption',
                'automated_backups'
            ],
            'endpoints' => $this->getAvailableEndpoints()
        ];
    }
    
    /**
     * Check database health
     */
    private function checkDatabase() {
        try {
            if (!$this->db) {
                return [
                    'status' => 'unhealthy',
                    'message' => 'Database connection not available'
                ];
            }
            
            // Test query
            $stmt = $this->db->query("SELECT 1");
            $result = $stmt->fetch();
            
            if ($result) {
                return [
                    'status' => 'healthy',
                    'message' => 'Database connection active'
                ];
            } else {
                return [
                    'status' => 'unhealthy',
                    'message' => 'Database query failed'
                ];
            }
            
        } catch (Exception $e) {
            return [
                'status' => 'unhealthy',
                'message' => 'Database error: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Check Redis health
     */
    private function checkRedis() {
        try {
            if (!class_exists('Redis')) {
                return [
                    'status' => 'unavailable',
                    'message' => 'Redis extension not installed'
                ];
            }
            
            $redis = new Redis();
            $connected = $redis->connect('localhost', 6379);
            
            if ($connected && $redis->ping()) {
                $redis->close();
                return [
                    'status' => 'healthy',
                    'message' => 'Redis connection active'
                ];
            } else {
                return [
                    'status' => 'unhealthy',
                    'message' => 'Redis connection failed'
                ];
            }
            
        } catch (Exception $e) {
            return [
                'status' => 'unhealthy',
                'message' => 'Redis error: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Check file system health
     */
    private function checkFileSystem() {
        try {
            $checks = [];
            
            // Check uploads directory
            $uploadsDir = __DIR__ . '/../../uploads';
            $checks['uploads'] = is_writable($uploadsDir) ? 'writable' : 'not writable';
            
            // Check logs directory
            $logsDir = __DIR__ . '/../../shared/logs';
            $checks['logs'] = is_writable($logsDir) ? 'writable' : 'not writable';
            
            // Check cache directory
            $cacheDir = __DIR__ . '/../../cache';
            if (is_dir($cacheDir)) {
                $checks['cache'] = is_writable($cacheDir) ? 'writable' : 'not writable';
            }
            
            // Determine overall status
            $allWritable = true;
            foreach ($checks as $status) {
                if ($status !== 'writable') {
                    $allWritable = false;
                    break;
                }
            }
            
            return [
                'status' => $allWritable ? 'healthy' : 'unhealthy',
                'details' => $checks
            ];
            
        } catch (Exception $e) {
            return [
                'status' => 'unhealthy',
                'message' => 'File system error: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Check memory usage
     */
    private function checkMemoryUsage() {
        try {
            $memoryUsage = memory_get_usage(true);
            $memoryLimit = ini_get('memory_limit');
            
            // Convert memory limit to bytes
            $limitBytes = $this->convertToBytes($memoryLimit);
            $usagePercent = ($memoryUsage / $limitBytes) * 100;
            
            $status = 'healthy';
            if ($usagePercent > 90) {
                $status = 'critical';
            } elseif ($usagePercent > 75) {
                $status = 'warning';
            }
            
            return [
                'status' => $status,
                'usage_bytes' => $memoryUsage,
                'usage_mb' => round($memoryUsage / 1024 / 1024, 2),
                'limit' => $memoryLimit,
                'usage_percent' => round($usagePercent, 2)
            ];
            
        } catch (Exception $e) {
            return [
                'status' => 'unhealthy',
                'message' => 'Memory check error: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Convert memory limit string to bytes
     */
    private function convertToBytes($value) {
        $unit = strtolower($value[strlen($value) - 1]);
        $value = (int)$value;
        
        switch ($unit) {
            case 'g':
                $value *= 1024;
            case 'm':
                $value *= 1024;
            case 'k':
                $value *= 1024;
        }
        
        return $value;
    }
    
    /**
     * Get system uptime
     */
    private function getSystemUptime() {
        if (function_exists('sys_getloadavg') && file_exists('/proc/uptime')) {
            $uptime = file_get_contents('/proc/uptime');
            $uptime = floatval(explode(' ', $uptime)[0]);
            return round($uptime);
        }
        
        return null;
    }
    
    /**
     * Get system load average
     */
    private function getLoadAverage() {
        if (function_exists('sys_getloadavg')) {
            return sys_getloadavg();
        }
        
        return null;
    }
    
    /**
     * Get count of active users
     */
    private function getActiveUsersCount() {
        try {
            if (!$this->db) {
                return null;
            }
            
            // Count users who have been active in the last 24 hours
            $stmt = $this->db->query("
                SELECT COUNT(*) as count 
                FROM staff 
                WHERE last_login >= DATE_SUB(NOW(), INTERVAL 24 HOUR) 
                AND accStatus = 'Active'
            ");
            $result = $stmt->fetch();
            
            return (int)($result['count'] ?? 0);
            
        } catch (Exception $e) {
            error_log("Active users count error: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Get available API endpoints
     */
    private function getAvailableEndpoints() {
        return [
            'authentication' => [
                'POST /api/v1/auth/login',
                'POST /api/v1/auth/logout',
                'POST /api/v1/auth/refresh',
                'POST /api/v1/auth/mfa/setup',
                'POST /api/v1/auth/mfa/verify'
            ],
            'users' => [
                'GET /api/v1/users',
                'GET /api/v1/users/{id}',
                'POST /api/v1/users',
                'PUT /api/v1/users/{id}',
                'DELETE /api/v1/users/{id}'
            ],
            'personnel' => [
                'GET /api/v1/personnel',
                'GET /api/v1/personnel/{id}',
                'POST /api/v1/personnel',
                'PUT /api/v1/personnel/{id}',
                'DELETE /api/v1/personnel/{id}'
            ],
            'dashboard' => [
                'GET /api/v1/dashboard/stats',
                'GET /api/v1/dashboard/kpi',
                'GET /api/v1/dashboard/activities'
            ],
            'system' => [
                'GET /api/v1/health',
                'GET /api/v1/status',
                'GET /api/v1/version'
            ]
        ];
    }
}
?>