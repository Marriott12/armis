<?php
/**
 * ARMIS Scalability Configuration
 * Optimized for handling 1M+ users efficiently
 * 
 * This configuration provides database connection pooling,
 * caching strategies, and performance optimizations
 */

// Database Configuration for High Load
class ScalabilityConfig {
    
    // Database connection pooling settings
    const DB_POOL_SIZE = 20;
    const DB_MAX_CONNECTIONS = 100;
    const DB_CONNECTION_TIMEOUT = 30;
    
    // Redis caching configuration
    const REDIS_HOST = 'localhost';
    const REDIS_PORT = 6379;
    const REDIS_PASSWORD = null;
    const CACHE_TTL = 3600; // 1 hour default
    
    // Session management for scale
    const SESSION_HANDLER = 'redis'; // Options: 'files', 'database', 'redis'
    const SESSION_LIFETIME = 28800; // 8 hours
    
    // Performance settings
    const ENABLE_QUERY_CACHE = true;
    const ENABLE_PAGE_CACHE = true;
    const ENABLE_GZIP_COMPRESSION = true;
    const MAX_EXECUTION_TIME = 60;
    const MEMORY_LIMIT = '512M';
    
    // Load balancing and clustering
    const LOAD_BALANCER_ENABLED = false;
    const READ_REPLICA_HOSTS = [
        // 'replica1.example.com',
        // 'replica2.example.com'
    ];
    
    // Security for high-scale deployment
    const RATE_LIMIT_REQUESTS = 100; // per minute per IP
    const ENABLE_DDOS_PROTECTION = true;
    const MAX_LOGIN_ATTEMPTS = 5;
    const LOGIN_LOCKOUT_TIME = 900; // 15 minutes
    
    // File upload limits for CV and documents
    const MAX_UPLOAD_SIZE = '10M';
    const ALLOWED_FILE_TYPES = ['pdf', 'doc', 'docx', 'jpg', 'png'];
    
    // Database optimization settings
    public static function getDatabaseConfig() {
        return [
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'options' => [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::ATTR_PERSISTENT => true, // Connection pooling
                PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => false, // For large result sets
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET sql_mode='STRICT_TRANS_TABLES'"
            ]
        ];
    }
    
    // Caching strategy for frequently accessed data
    public static function getCacheKeys() {
        return [
            'user_profile' => 'user_profile_{user_id}',
            'user_permissions' => 'user_perms_{user_id}',
            'staff_list' => 'staff_list_{page}_{filters}',
            'rank_structure' => 'rank_structure',
            'unit_list' => 'unit_list',
            'training_courses' => 'training_courses',
            'reports_cache' => 'report_{type}_{date}_{filters}'
        ];
    }
    
    // Performance monitoring
    public static function getPerformanceConfig() {
        return [
            'enable_profiling' => false, // Enable only in development
            'log_slow_queries' => true,
            'slow_query_threshold' => 2.0, // seconds
            'enable_memory_monitoring' => true,
            'max_memory_usage' => '256M'
        ];
    }
    
    // Security headers for production
    public static function setSecurityHeaders() {
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: DENY');
        header('X-XSS-Protection: 1; mode=block');
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        header('Content-Security-Policy: default-src \'self\'; script-src \'self\' \'unsafe-inline\' cdn.jsdelivr.net cdnjs.cloudflare.com; style-src \'self\' \'unsafe-inline\' cdn.jsdelivr.net cdnjs.cloudflare.com; img-src \'self\' data:; font-src \'self\' cdnjs.cloudflare.com;');
    }
    
    // Database indices for performance (SQL commands)
    public static function getOptimizationIndices() {
        return [
            "CREATE INDEX idx_staff_svcno ON staff(svcNo)",
            "CREATE INDEX idx_staff_unit ON staff(unitID)",
            "CREATE INDEX idx_staff_rank ON staff(rankID)",
            "CREATE INDEX idx_staff_status ON staff(svcStatus)",
            "CREATE INDEX idx_staff_name ON staff(firstName, lastName)",
            "CREATE INDEX idx_users_email ON users(email)",
            "CREATE INDEX idx_users_last_login ON users(last_login)",
            "CREATE INDEX idx_training_user ON training_records(user_id)",
            "CREATE INDEX idx_training_date ON training_records(completion_date)",
            "CREATE INDEX idx_medals_user ON staff_medals(svcNo)",
            "CREATE INDEX idx_promotions_user ON staff_promotions(svcNo)",
            "CREATE INDEX idx_promotions_date ON staff_promotions(datePromoted)"
        ];
    }
}

// Initialize scalability features
if (class_exists('ScalabilityConfig')) {
    // Set memory and execution limits
    ini_set('memory_limit', ScalabilityConfig::MEMORY_LIMIT);
    ini_set('max_execution_time', ScalabilityConfig::MAX_EXECUTION_TIME);
    
    // Enable compression if supported
    if (ScalabilityConfig::ENABLE_GZIP_COMPRESSION && !ob_get_level()) {
        ob_start('ob_gzhandler');
    }
    
    // Set security headers
    ScalabilityConfig::setSecurityHeaders();
}

// Simple caching class for high-performance operations
class ARMISCache {
    private static $redis = null;
    
    public static function init() {
        if (class_exists('Redis') && self::$redis === null) {
            try {
                self::$redis = new Redis();
                self::$redis->connect(ScalabilityConfig::REDIS_HOST, ScalabilityConfig::REDIS_PORT);
                if (ScalabilityConfig::REDIS_PASSWORD) {
                    self::$redis->auth(ScalabilityConfig::REDIS_PASSWORD);
                }
            } catch (Exception $e) {
                error_log("Redis connection failed: " . $e->getMessage());
                self::$redis = null;
            }
        }
    }
    
    public static function get($key) {
        self::init();
        if (self::$redis) {
            try {
                $data = self::$redis->get($key);
                return $data ? unserialize($data) : false;
            } catch (Exception $e) {
                error_log("Cache get failed: " . $e->getMessage());
                return false;
            }
        }
        return false;
    }
    
    public static function set($key, $value, $ttl = null) {
        self::init();
        if (self::$redis) {
            try {
                $ttl = $ttl ?: ScalabilityConfig::CACHE_TTL;
                return self::$redis->setex($key, $ttl, serialize($value));
            } catch (Exception $e) {
                error_log("Cache set failed: " . $e->getMessage());
                return false;
            }
        }
        return false;
    }
    
    public static function delete($key) {
        self::init();
        if (self::$redis) {
            try {
                return self::$redis->del($key);
            } catch (Exception $e) {
                error_log("Cache delete failed: " . $e->getMessage());
                return false;
            }
        }
        return false;
    }
    
    public static function clear($pattern = '*') {
        self::init();
        if (self::$redis) {
            try {
                $keys = self::$redis->keys($pattern);
                if ($keys) {
                    return self::$redis->del($keys);
                }
                return true;
            } catch (Exception $e) {
                error_log("Cache clear failed: " . $e->getMessage());
                return false;
            }
        }
        return false;
    }
}

// Performance monitoring class
class PerformanceMonitor {
    private static $startTime;
    private static $startMemory;
    
    public static function start() {
        self::$startTime = microtime(true);
        self::$startMemory = memory_get_usage(true);
    }
    
    public static function end($operation = 'unknown') {
        $endTime = microtime(true);
        $endMemory = memory_get_usage(true);
        
        $executionTime = $endTime - self::$startTime;
        $memoryUsed = $endMemory - self::$startMemory;
        
        // Log slow operations
        if ($executionTime > ScalabilityConfig::getPerformanceConfig()['slow_query_threshold']) {
            error_log("Slow operation detected: {$operation} took {$executionTime}s and used " . 
                     number_format($memoryUsed / 1024 / 1024, 2) . "MB");
        }
        
        return [
            'execution_time' => $executionTime,
            'memory_used' => $memoryUsed,
            'peak_memory' => memory_get_peak_usage(true)
        ];
    }
}

// Rate limiting for API endpoints
class RateLimiter {
    public static function checkLimit($identifier, $limit = null, $window = 60) {
        $limit = $limit ?: ScalabilityConfig::RATE_LIMIT_REQUESTS;
        $key = "rate_limit:{$identifier}:" . floor(time() / $window);
        
        $current = ARMISCache::get($key) ?: 0;
        
        if ($current >= $limit) {
            return false;
        }
        
        ARMISCache::set($key, $current + 1, $window);
        return true;
    }
}

// Auto-start performance monitoring for all requests
PerformanceMonitor::start();

// Register shutdown function to log performance data
register_shutdown_function(function() {
    $stats = PerformanceMonitor::end($_SERVER['REQUEST_URI'] ?? 'unknown');
    
    // Log to performance log file for analysis
    $logEntry = date('Y-m-d H:i:s') . " - " . 
                ($_SERVER['REQUEST_URI'] ?? 'unknown') . " - " .
                "Time: " . number_format($stats['execution_time'], 4) . "s - " .
                "Memory: " . number_format($stats['memory_used'] / 1024 / 1024, 2) . "MB" . PHP_EOL;
    
    // Ensure logs directory exists
    $logDir = dirname(__FILE__) . '/logs';
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }
    
    $logFile = $logDir . '/performance.log';
    
    // Only log if we can write to the file
    try {
        file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
    } catch (Exception $e) {
        // Silently fail if logging isn't available
        error_log("ARMIS Performance logging failed: " . $e->getMessage());
    }
});

?>
