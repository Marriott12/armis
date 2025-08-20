<?php
/**
 * ARMIS Performance Metrics Collection Script
 * Collects and stores performance metrics for monitoring
 */

require_once __DIR__ . '/../shared/database_connection.php';

class PerformanceMetrics {
    private $db;
    
    public function __construct() {
        $this->db = getDbConnection();
    }
    
    public function collectMetrics() {
        echo "[" . date('Y-m-d H:i:s') . "] Starting performance metrics collection\n";
        
        // Collect system metrics
        $this->collectSystemMetrics();
        
        // Collect database metrics
        $this->collectDatabaseMetrics();
        
        // Collect application metrics
        $this->collectApplicationMetrics();
        
        // Collect API metrics
        $this->collectAPIMetrics();
        
        echo "[" . date('Y-m-d H:i:s') . "] Performance metrics collection completed\n";
    }
    
    private function collectSystemMetrics() {
        // Memory usage
        $memoryUsage = memory_get_usage(true);
        $memoryPeak = memory_get_peak_usage(true);
        
        $this->storeMetric('system_memory_usage', $memoryUsage / 1024 / 1024, 'MB');
        $this->storeMetric('system_memory_peak', $memoryPeak / 1024 / 1024, 'MB');
        
        // CPU load average (if available)
        if (function_exists('sys_getloadavg')) {
            $load = sys_getloadavg();
            $this->storeMetric('system_load_1min', $load[0], 'ratio');
            $this->storeMetric('system_load_5min', $load[1], 'ratio');
            $this->storeMetric('system_load_15min', $load[2], 'ratio');
        }
        
        // Disk usage
        $diskTotal = disk_total_space(__DIR__);
        $diskFree = disk_free_space(__DIR__);
        $diskUsed = $diskTotal - $diskFree;
        
        $this->storeMetric('disk_usage_percent', ($diskUsed / $diskTotal) * 100, 'percent');
        $this->storeMetric('disk_free_space', $diskFree / 1024 / 1024 / 1024, 'GB');
    }
    
    private function collectDatabaseMetrics() {
        try {
            // Connection count
            $stmt = $this->db->query("SHOW STATUS LIKE 'Threads_connected'");
            $result = $stmt->fetch();
            if ($result) {
                $this->storeMetric('db_connections', $result['Value'], 'count');
            }
            
            // Query cache hit rate
            $stmt = $this->db->query("SHOW STATUS LIKE 'Qcache_hits'");
            $hits = $stmt->fetch()['Value'] ?? 0;
            
            $stmt = $this->db->query("SHOW STATUS LIKE 'Qcache_inserts'");
            $inserts = $stmt->fetch()['Value'] ?? 0;
            
            if ($hits + $inserts > 0) {
                $hitRate = ($hits / ($hits + $inserts)) * 100;
                $this->storeMetric('db_query_cache_hit_rate', $hitRate, 'percent');
            }
            
            // Slow queries
            $stmt = $this->db->query("SHOW STATUS LIKE 'Slow_queries'");
            $result = $stmt->fetch();
            if ($result) {
                $this->storeMetric('db_slow_queries', $result['Value'], 'count');
            }
            
            // Database size
            $stmt = $this->db->prepare("
                SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) AS size_mb
                FROM information_schema.tables 
                WHERE table_schema = ?
            ");
            $stmt->execute([DB_NAME]);
            $result = $stmt->fetch();
            if ($result) {
                $this->storeMetric('db_size', $result['size_mb'], 'MB');
            }
            
        } catch (Exception $e) {
            error_log("Database metrics collection failed: " . $e->getMessage());
        }
    }
    
    private function collectApplicationMetrics() {
        try {
            // Active sessions count
            $activeUsers = $this->getActiveUsersCount();
            $this->storeMetric('active_users', $activeUsers, 'count');
            
            // Recent API requests
            $apiRequests = $this->getRecentAPIRequests();
            $this->storeMetric('api_requests_last_hour', $apiRequests, 'count');
            
            // Failed login attempts
            $failedLogins = $this->getRecentFailedLogins();
            $this->storeMetric('failed_logins_last_hour', $failedLogins, 'count');
            
            // File upload count and size
            $uploads = $this->getRecentUploads();
            $this->storeMetric('file_uploads_last_hour', $uploads['count'], 'count');
            $this->storeMetric('file_uploads_size_mb', $uploads['size'] / 1024 / 1024, 'MB');
            
        } catch (Exception $e) {
            error_log("Application metrics collection failed: " . $e->getMessage());
        }
    }
    
    private function collectAPIMetrics() {
        // Test API endpoints response times
        $endpoints = [
            '/api/v1/health',
            '/api/v1/status'
        ];
        
        foreach ($endpoints as $endpoint) {
            $startTime = microtime(true);
            
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, "http://localhost" . $endpoint);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 5);
            curl_setopt($ch, CURLOPT_HEADER, false);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $responseTime = (microtime(true) - $startTime) * 1000; // Convert to milliseconds
            
            curl_close($ch);
            
            $endpointName = str_replace(['/', '-'], ['_', '_'], trim($endpoint, '/'));
            $this->storeMetric("api_response_time_$endpointName", $responseTime, 'ms', $endpoint);
            
            if ($httpCode !== 200) {
                $this->storeMetric("api_error_$endpointName", 1, 'count', $endpoint);
            }
        }
    }
    
    private function storeMetric($name, $value, $unit, $endpoint = null) {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO performance_metrics 
                (metric_name, metric_value, metric_unit, endpoint, timestamp)
                VALUES (?, ?, ?, ?, NOW())
            ");
            $stmt->execute([$name, $value, $unit, $endpoint]);
            
        } catch (Exception $e) {
            error_log("Failed to store metric $name: " . $e->getMessage());
        }
    }
    
    private function getActiveUsersCount() {
        $stmt = $this->db->query("
            SELECT COUNT(*) as count 
            FROM staff 
            WHERE last_login >= DATE_SUB(NOW(), INTERVAL 1 HOUR) 
            AND accStatus = 'Active'
        ");
        return $stmt->fetch()['count'] ?? 0;
    }
    
    private function getRecentAPIRequests() {
        // This would typically come from access logs or a request tracking table
        // For demo purposes, we'll estimate based on login attempts
        $stmt = $this->db->query("
            SELECT COUNT(*) as count 
            FROM login_attempts 
            WHERE attempted_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
        ");
        return ($stmt->fetch()['count'] ?? 0) * 5; // Estimate 5 API calls per login
    }
    
    private function getRecentFailedLogins() {
        $stmt = $this->db->query("
            SELECT COUNT(*) as count 
            FROM login_attempts 
            WHERE attempted_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
            AND successful = 0
        ");
        return $stmt->fetch()['count'] ?? 0;
    }
    
    private function getRecentUploads() {
        // This would typically track file uploads in a dedicated table
        // For demo purposes, we'll return sample data
        return ['count' => 0, 'size' => 0];
    }
}

// Run metrics collection
try {
    $metrics = new PerformanceMetrics();
    $metrics->collectMetrics();
} catch (Exception $e) {
    error_log("Performance metrics collection failed: " . $e->getMessage());
    echo "[" . date('Y-m-d H:i:s') . "] ERROR: " . $e->getMessage() . "\n";
}
?>