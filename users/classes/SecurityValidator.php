<?php
/**
 * Enhanced Security Validator for Military-Grade User Profile Module
 * Provides comprehensive input validation and sanitization
 */

class SecurityValidator {
    
    private $auditLogger;
    private $validationRules;
    
    public function __construct($auditLogger = null) {
        $this->auditLogger = $auditLogger;
        $this->loadValidationRules();
    }
    
    /**
     * Load validation rules from configuration
     */
    private function loadValidationRules() {
        $this->validationRules = [
            'name' => [
                'pattern' => '/^[a-zA-Z\s\-\'\.]+$/',
                'min_length' => 2,
                'max_length' => 100,
                'required' => true
            ],
            'email' => [
                'pattern' => '/^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/',
                'max_length' => 254,
                'required' => true
            ],
            'phone' => [
                'pattern' => '/^[\+]?[0-9\-\s\(\)]{7,20}$/',
                'required' => false
            ],
            'service_number' => [
                'pattern' => '/^[A-Z0-9]{6,15}$/',
                'required' => true
            ],
            'nrc' => [
                'pattern' => '/^[0-9]{6}\/[0-9]{2}\/[0-9]$/',
                'required' => true
            ],
            'passport' => [
                'pattern' => '/^[A-Z]{1,2}[0-9]{6,8}$/',
                'required' => false
            ]
        ];
    }
    
    /**
     * Validate and sanitize user input
     */
    public function validateInput($data, $fieldType, $context = 'profile_update') {
        $result = [
            'valid' => false,
            'sanitized_value' => null,
            'errors' => [],
            'warnings' => [],
            'security_score' => 0
        ];
        
        try {
            // Check if field type exists in rules
            if (!isset($this->validationRules[$fieldType])) {
                $result['errors'][] = "Unknown field type: $fieldType";
                return $result;
            }
            
            $rules = $this->validationRules[$fieldType];
            $value = $data;
            
            // Basic sanitization
            $value = $this->sanitizeInput($value, $fieldType);
            
            // Required field check
            if ($rules['required'] && empty($value)) {
                $result['errors'][] = "Field is required";
                return $result;
            }
            
            // Skip validation if field is not required and empty
            if (!$rules['required'] && empty($value)) {
                $result['valid'] = true;
                $result['sanitized_value'] = $value;
                $result['security_score'] = 100;
                return $result;
            }
            
            // Length validation
            if (isset($rules['min_length']) && strlen($value) < $rules['min_length']) {
                $result['errors'][] = "Minimum length is {$rules['min_length']} characters";
            }
            
            if (isset($rules['max_length']) && strlen($value) > $rules['max_length']) {
                $result['errors'][] = "Maximum length is {$rules['max_length']} characters";
            }
            
            // Pattern validation
            if (isset($rules['pattern']) && !preg_match($rules['pattern'], $value)) {
                $result['errors'][] = "Invalid format for $fieldType";
            }
            
            // Additional security checks
            $this->performSecurityChecks($value, $fieldType, $result);
            
            // Calculate security score
            $result['security_score'] = $this->calculateSecurityScore($value, $fieldType, $result);
            
            // Set validity based on errors
            $result['valid'] = empty($result['errors']);
            $result['sanitized_value'] = $value;
            
            // Log validation attempt
            if ($this->auditLogger) {
                $this->auditLogger->logValidation($fieldType, $result['valid'], $context);
            }
            
        } catch (Exception $e) {
            $result['errors'][] = "Validation error: " . $e->getMessage();
            error_log("Security validation error: " . $e->getMessage());
        }
        
        return $result;
    }
    
    /**
     * Sanitize input based on field type
     */
    private function sanitizeInput($value, $fieldType) {
        // Basic HTML entities encoding
        $value = htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
        
        // Remove any null bytes
        $value = str_replace(chr(0), '', $value);
        
        // Trim whitespace
        $value = trim($value);
        
        // Field-specific sanitization
        switch ($fieldType) {
            case 'email':
                $value = filter_var($value, FILTER_SANITIZE_EMAIL);
                break;
                
            case 'phone':
                $value = preg_replace('/[^0-9\+\-\s\(\)]/', '', $value);
                break;
                
            case 'service_number':
            case 'nrc':
            case 'passport':
                $value = strtoupper(preg_replace('/[^A-Z0-9\/]/', '', $value));
                break;
                
            case 'name':
                $value = preg_replace('/[^a-zA-Z\s\-\'\.]/', '', $value);
                break;
        }
        
        return $value;
    }
    
    /**
     * Perform additional security checks
     */
    private function performSecurityChecks($value, $fieldType, &$result) {
        // Check for SQL injection patterns
        $sqlPatterns = [
            '/(\bUNION\b|\bSELECT\b|\bINSERT\b|\bUPDATE\b|\bDELETE\b|\bDROP\b)/i',
            '/(\bOR\b|\bAND\b)\s+(\d+\s*=\s*\d+|\'\w*\'\s*=\s*\'\w*\')/i',
            '/[\'\"]\s*;\s*--/',
            '/\b(exec|execute|sp_|xp_)\b/i'
        ];
        
        foreach ($sqlPatterns as $pattern) {
            if (preg_match($pattern, $value)) {
                $result['errors'][] = "Potential security threat detected";
                $result['security_score'] = 0;
                return;
            }
        }
        
        // Check for XSS patterns
        $xssPatterns = [
            '/<script\b/i',
            '/javascript:/i',
            '/on\w+\s*=/i',
            '/<iframe\b/i',
            '/<object\b/i',
            '/<embed\b/i'
        ];
        
        foreach ($xssPatterns as $pattern) {
            if (preg_match($pattern, $value)) {
                $result['warnings'][] = "Potential XSS pattern detected";
                $result['security_score'] -= 20;
            }
        }
        
        // Check for suspicious characters
        if (preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', $value)) {
            $result['warnings'][] = "Contains suspicious control characters";
            $result['security_score'] -= 10;
        }
    }
    
    /**
     * Calculate security score (0-100)
     */
    private function calculateSecurityScore($value, $fieldType, $result) {
        $score = 100;
        
        // Deduct points for errors
        $score -= count($result['errors']) * 25;
        
        // Deduct points for warnings
        $score -= count($result['warnings']) * 10;
        
        // Bonus points for good practices
        if (strlen($value) > 0 && ctype_alnum(str_replace([' ', '-', '.', '\''], '', $value))) {
            $score += 5;
        }
        
        return max(0, min(100, $score));
    }
    
    /**
     * Validate CSRF token
     */
    public function validateCSRFToken($token) {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
    }
    
    /**
     * Generate secure CSRF token
     */
    public function generateCSRFToken() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        
        return $_SESSION['csrf_token'];
    }
    
    /**
     * Rate limiting check
     */
    public function checkRateLimit($action, $identifier, $maxAttempts = 5, $timeWindow = 300) {
        $key = "rate_limit_{$action}_{$identifier}";
        
        if (!isset($_SESSION[$key])) {
            $_SESSION[$key] = ['count' => 0, 'first_attempt' => time()];
        }
        
        $data = $_SESSION[$key];
        
        // Reset if time window has passed
        if (time() - $data['first_attempt'] > $timeWindow) {
            $_SESSION[$key] = ['count' => 1, 'first_attempt' => time()];
            return true;
        }
        
        // Check if limit exceeded
        if ($data['count'] >= $maxAttempts) {
            return false;
        }
        
        // Increment counter
        $_SESSION[$key]['count']++;
        return true;
    }
    
    /**
     * Validate file upload
     */
    public function validateFileUpload($file, $allowedTypes = ['pdf', 'doc', 'docx'], $maxSize = 10485760) {
        $result = [
            'valid' => false,
            'errors' => [],
            'file_info' => []
        ];
        
        if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            $result['errors'][] = "No valid file uploaded";
            return $result;
        }
        
        // Check file size
        if ($file['size'] > $maxSize) {
            $result['errors'][] = "File size exceeds maximum limit";
        }
        
        // Check file type
        $fileExtension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($fileExtension, $allowedTypes)) {
            $result['errors'][] = "File type not allowed";
        }
        
        // Check MIME type
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        
        $allowedMimes = [
            'pdf' => 'application/pdf',
            'doc' => 'application/msword',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
        ];
        
        if (!in_array($mimeType, array_values($allowedMimes))) {
            $result['errors'][] = "Invalid file type detected";
        }
        
        $result['valid'] = empty($result['errors']);
        $result['file_info'] = [
            'original_name' => $file['name'],
            'size' => $file['size'],
            'type' => $mimeType,
            'extension' => $fileExtension
        ];
        
        return $result;
    }
}
?>