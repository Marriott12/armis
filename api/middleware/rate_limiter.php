<?php
/**
 * ARMIS API Rate Limiter
 * Implements rate limiting to prevent API abuse
 */

class RateLimiter {
    private static $redis = null;
    private static $defaultLimit = 100; // requests per minute
    private static $defaultWindow = 60; // seconds
    
    /**
     * Initialize Redis connection
     */
    private static function initRedis() {
        if (self::$redis === null && class_exists('Redis')) {
            try {
                self::$redis = new Redis();
                self::$redis->connect('localhost', 6379);
                // If Redis has a password, authenticate here
                // self::$redis->auth('password');
            } catch (Exception $e) {
                error_log("Redis connection failed for rate limiter: " . $e->getMessage());
                self::$redis = false; // Disable Redis-based rate limiting
            }
        }
    }
    
    /**
     * Check if request is within rate limit
     */
    public static function checkLimit($clientIP, $limit = null, $window = null) {
        $limit = $limit ?: self::$defaultLimit;
        $window = $window ?: self::$defaultWindow;
        
        self::initRedis();
        
        // If Redis is available, use it for distributed rate limiting
        if (self::$redis && self::$redis !== false) {
            return self::checkRedisLimit($clientIP, $limit, $window);
        }
        
        // Fallback to file-based rate limiting
        return self::checkFileLimit($clientIP, $limit, $window);
    }
    
    /**
     * Redis-based rate limiting
     */
    private static function checkRedisLimit($clientIP, $limit, $window) {
        try {
            $key = "rate_limit:$clientIP";
            $current = self::$redis->incr($key);
            
            if ($current === 1) {
                self::$redis->expire($key, $window);
            }
            
            return $current <= $limit;
            
        } catch (Exception $e) {
            error_log("Redis rate limiting error: " . $e->getMessage());
            return true; // Allow request if Redis fails
        }
    }
    
    /**
     * File-based rate limiting (fallback)
     */
    private static function checkFileLimit($clientIP, $limit, $window) {
        try {
            $logDir = __DIR__ . '/../../shared/logs';
            if (!is_dir($logDir)) {
                mkdir($logDir, 0755, true);
            }
            
            $rateLimitFile = $logDir . '/rate_limits.json';
            $now = time();
            
            // Load existing rate limit data
            $rateLimits = [];
            if (file_exists($rateLimitFile)) {
                $data = file_get_contents($rateLimitFile);
                $rateLimits = json_decode($data, true) ?: [];
            }
            
            // Clean old entries
            foreach ($rateLimits as $ip => $data) {
                if ($data['window_start'] + $window < $now) {
                    unset($rateLimits[$ip]);
                }
            }
            
            // Check current IP
            if (!isset($rateLimits[$clientIP])) {
                $rateLimits[$clientIP] = [
                    'count' => 0,
                    'window_start' => $now
                ];
            }
            
            // Reset window if expired
            if ($rateLimits[$clientIP]['window_start'] + $window < $now) {
                $rateLimits[$clientIP] = [
                    'count' => 0,
                    'window_start' => $now
                ];
            }
            
            // Increment counter
            $rateLimits[$clientIP]['count']++;
            
            // Save updated data
            file_put_contents($rateLimitFile, json_encode($rateLimits));
            
            return $rateLimits[$clientIP]['count'] <= $limit;
            
        } catch (Exception $e) {
            error_log("File-based rate limiting error: " . $e->getMessage());
            return true; // Allow request if file operations fail
        }
    }
    
    /**
     * Get current rate limit status for IP
     */
    public static function getStatus($clientIP, $limit = null, $window = null) {
        $limit = $limit ?: self::$defaultLimit;
        $window = $window ?: self::$defaultWindow;
        
        self::initRedis();
        
        if (self::$redis && self::$redis !== false) {
            return self::getRedisStatus($clientIP, $limit, $window);
        }
        
        return self::getFileStatus($clientIP, $limit, $window);
    }
    
    /**
     * Get Redis-based rate limit status
     */
    private static function getRedisStatus($clientIP, $limit, $window) {
        try {
            $key = "rate_limit:$clientIP";
            $current = self::$redis->get($key) ?: 0;
            $ttl = self::$redis->ttl($key);
            
            return [
                'limit' => $limit,
                'remaining' => max(0, $limit - $current),
                'reset_time' => $ttl > 0 ? time() + $ttl : time() + $window,
                'current_count' => $current
            ];
            
        } catch (Exception $e) {
            error_log("Redis rate limit status error: " . $e->getMessage());
            return [
                'limit' => $limit,
                'remaining' => $limit,
                'reset_time' => time() + $window,
                'current_count' => 0
            ];
        }
    }
    
    /**
     * Get file-based rate limit status
     */
    private static function getFileStatus($clientIP, $limit, $window) {
        try {
            $rateLimitFile = __DIR__ . '/../../shared/logs/rate_limits.json';
            $now = time();
            
            if (!file_exists($rateLimitFile)) {
                return [
                    'limit' => $limit,
                    'remaining' => $limit,
                    'reset_time' => $now + $window,
                    'current_count' => 0
                ];
            }
            
            $data = file_get_contents($rateLimitFile);
            $rateLimits = json_decode($data, true) ?: [];
            
            if (!isset($rateLimits[$clientIP])) {
                return [
                    'limit' => $limit,
                    'remaining' => $limit,
                    'reset_time' => $now + $window,
                    'current_count' => 0
                ];
            }
            
            $current = $rateLimits[$clientIP]['count'];
            $windowStart = $rateLimits[$clientIP]['window_start'];
            
            // Check if window has expired
            if ($windowStart + $window < $now) {
                return [
                    'limit' => $limit,
                    'remaining' => $limit,
                    'reset_time' => $now + $window,
                    'current_count' => 0
                ];
            }
            
            return [
                'limit' => $limit,
                'remaining' => max(0, $limit - $current),
                'reset_time' => $windowStart + $window,
                'current_count' => $current
            ];
            
        } catch (Exception $e) {
            error_log("File rate limit status error: " . $e->getMessage());
            return [
                'limit' => $limit,
                'remaining' => $limit,
                'reset_time' => time() + $window,
                'current_count' => 0
            ];
        }
    }
    
    /**
     * Set custom rate limit for specific IP or endpoint
     */
    public static function setCustomLimit($identifier, $limit, $window = null) {
        $window = $window ?: self::$defaultWindow;
        
        // This could be stored in database or Redis for persistence
        // For now, we'll use a simple file-based approach
        try {
            $customLimitsFile = __DIR__ . '/../../shared/logs/custom_rate_limits.json';
            $customLimits = [];
            
            if (file_exists($customLimitsFile)) {
                $data = file_get_contents($customLimitsFile);
                $customLimits = json_decode($data, true) ?: [];
            }
            
            $customLimits[$identifier] = [
                'limit' => $limit,
                'window' => $window,
                'updated_at' => time()
            ];
            
            file_put_contents($customLimitsFile, json_encode($customLimits));
            return true;
            
        } catch (Exception $e) {
            error_log("Custom rate limit setting error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get custom rate limit for identifier
     */
    public static function getCustomLimit($identifier) {
        try {
            $customLimitsFile = __DIR__ . '/../../shared/logs/custom_rate_limits.json';
            
            if (!file_exists($customLimitsFile)) {
                return null;
            }
            
            $data = file_get_contents($customLimitsFile);
            $customLimits = json_decode($data, true) ?: [];
            
            return $customLimits[$identifier] ?? null;
            
        } catch (Exception $e) {
            error_log("Custom rate limit retrieval error: " . $e->getMessage());
            return null;
        }
    }
}
?>