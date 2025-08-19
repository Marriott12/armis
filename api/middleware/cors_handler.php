<?php
/**
 * ARMIS API CORS Handler
 * Manages Cross-Origin Resource Sharing for API requests
 */

class CORSHandler {
    
    /**
     * Set CORS headers for API responses
     */
    public static function setHeaders() {
        $allowedOrigins = self::getAllowedOrigins();
        $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
        
        if (in_array($origin, $allowedOrigins) || self::isOriginAllowed($origin)) {
            header("Access-Control-Allow-Origin: $origin");
        } else {
            header("Access-Control-Allow-Origin: " . $allowedOrigins[0]);
        }
        
        header('Access-Control-Allow-Credentials: true');
        header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS, PATCH');
        header('Access-Control-Allow-Headers: Origin, Content-Type, Accept, Authorization, X-Requested-With, X-API-Key, X-CSRF-Token');
        header('Access-Control-Expose-Headers: X-API-Version, X-Rate-Limit-Limit, X-Rate-Limit-Remaining, X-Rate-Limit-Reset');
        header('Access-Control-Max-Age: 86400'); // 24 hours
    }
    
    /**
     * Handle CORS preflight requests
     */
    public static function handlePreflight() {
        self::setHeaders();
        http_response_code(200);
        exit;
    }
    
    /**
     * Get allowed origins for CORS
     */
    private static function getAllowedOrigins() {
        return [
            'http://localhost',
            'http://localhost:3000',
            'http://localhost:8080',
            'https://localhost',
            'https://localhost:3000',
            'https://localhost:8080',
            // Add production domains here
            defined('ARMIS_BASE_URL') ? ARMIS_BASE_URL : 'http://localhost/Armis2'
        ];
    }
    
    /**
     * Check if origin is allowed based on patterns
     */
    private static function isOriginAllowed($origin) {
        if (empty($origin)) {
            return false;
        }
        
        // Allow localhost on any port for development
        if (preg_match('/^https?:\/\/localhost(:\d+)?$/', $origin)) {
            return true;
        }
        
        // Allow 127.0.0.1 on any port for development
        if (preg_match('/^https?:\/\/127\.0\.0\.1(:\d+)?$/', $origin)) {
            return true;
        }
        
        // In production, you might want to allow specific domain patterns
        // if (preg_match('/^https:\/\/.*\.yourdomain\.mil$/', $origin)) {
        //     return true;
        // }
        
        return false;
    }
    
    /**
     * Validate CORS request
     */
    public static function validateRequest() {
        $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
        
        if (empty($origin)) {
            return true; // Allow same-origin requests
        }
        
        $allowedOrigins = self::getAllowedOrigins();
        
        return in_array($origin, $allowedOrigins) || self::isOriginAllowed($origin);
    }
}
?>