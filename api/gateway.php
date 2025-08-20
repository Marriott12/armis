<?php
/**
 * ARMIS Unified API Gateway
 * Central routing and management for all API requests
 * Implements rate limiting, authentication, and consistent response formatting
 */

// Define API constants
define('ARMIS_API_VERSION', '1.0.0');
define('ARMIS_API_BASE', true);

// Include core dependencies
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../shared/database_connection.php';
require_once __DIR__ . '/middleware/authentication.php';
require_once __DIR__ . '/middleware/rate_limiter.php';
require_once __DIR__ . '/middleware/cors_handler.php';
require_once __DIR__ . '/core/response_formatter.php';
require_once __DIR__ . '/core/router.php';

// Start session for authentication
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * ARMIS API Gateway Class
 * Handles all incoming API requests with proper middleware
 */
class ARMISApiGateway {
    private $router;
    private $responseFormatter;
    
    public function __construct() {
        $this->router = new APIRouter();
        $this->responseFormatter = new ResponseFormatter();
        
        // Set headers for API responses
        $this->setHeaders();
    }
    
    /**
     * Set standard API headers
     */
    private function setHeaders() {
        header('Content-Type: application/json; charset=utf-8');
        header('X-API-Version: ' . ARMIS_API_VERSION);
        header('X-Powered-By: ARMIS-API');
        
        // Security headers
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: DENY');
        header('X-XSS-Protection: 1; mode=block');
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    }
    
    /**
     * Process incoming API request
     */
    public function handleRequest() {
        try {
            // Handle CORS preflight requests
            if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
                CORSHandler::handlePreflight();
                return;
            }
            
            // Apply CORS headers
            CORSHandler::setHeaders();
            
            // Get request details
            $method = $_SERVER['REQUEST_METHOD'];
            $path = $this->getRequestPath();
            $headers = getallheaders();
            
            // Apply rate limiting
            if (!RateLimiter::checkLimit($this->getClientIP())) {
                $this->sendResponse(['error' => 'Rate limit exceeded'], 429);
                return;
            }
            
            // Authenticate request (unless it's a public endpoint)
            if (!$this->isPublicEndpoint($path)) {
                $authResult = APIAuthentication::validateRequest($headers);
                if (!$authResult['valid']) {
                    $this->sendResponse(['error' => $authResult['message']], 401);
                    return;
                }
            }
            
            // Route the request
            $result = $this->router->route($method, $path, $this->getRequestData());
            
            // Send formatted response
            $this->sendResponse($result['data'], $result['status']);
            
        } catch (Exception $e) {
            error_log("API Gateway Error: " . $e->getMessage());
            $this->sendResponse(['error' => 'Internal server error'], 500);
        }
    }
    
    /**
     * Get the request path
     */
    private function getRequestPath() {
        $requestUri = $_SERVER['REQUEST_URI'];
        $scriptName = $_SERVER['SCRIPT_NAME'];
        
        // Remove script name and query string
        $path = str_replace(dirname($scriptName), '', $requestUri);
        $path = strtok($path, '?');
        
        return trim($path, '/');
    }
    
    /**
     * Get request data based on method
     */
    private function getRequestData() {
        $method = $_SERVER['REQUEST_METHOD'];
        
        switch ($method) {
            case 'GET':
                return $_GET;
            case 'POST':
            case 'PUT':
            case 'PATCH':
                $input = file_get_contents('php://input');
                $data = json_decode($input, true);
                return $data ?: $_POST;
            case 'DELETE':
                return $_GET;
            default:
                return [];
        }
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
            return $_SERVER['REMOTE_ADDR'];
        }
    }
    
    /**
     * Check if endpoint is public (doesn't require authentication)
     */
    private function isPublicEndpoint($path) {
        $publicEndpoints = [
            'api/v1/auth/login',
            'api/v1/auth/refresh',
            'api/v1/health',
            'api/v1/status'
        ];
        
        return in_array($path, $publicEndpoints);
    }
    
    /**
     * Send formatted API response
     */
    private function sendResponse($data, $statusCode = 200) {
        http_response_code($statusCode);
        echo $this->responseFormatter->format($data, $statusCode);
        exit;
    }
}

// Initialize and handle the request
try {
    $gateway = new ARMISApiGateway();
    $gateway->handleRequest();
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'API Gateway initialization failed',
        'timestamp' => date('c')
    ]);
}
?>