<?php
/**
 * ARMIS API Authentication Middleware
 * Handles JWT token authentication and MFA verification
 */

require_once __DIR__ . '/../../shared/database_connection.php';

class APIAuthentication {
    private static $jwtSecret = null;
    private static $tokenExpiry = 3600; // 1 hour
    private static $refreshTokenExpiry = 86400; // 24 hours
    
    /**
     * Initialize JWT secret
     */
    private static function initJWTSecret() {
        if (self::$jwtSecret === null) {
            // In production, this should be stored securely
            self::$jwtSecret = hash('sha256', 'ARMIS_JWT_SECRET_' . (defined('DB_NAME') ? DB_NAME : 'armis'));
        }
    }
    
    /**
     * Validate API request authentication
     */
    public static function validateRequest($headers) {
        try {
            // Check for Authorization header
            $authHeader = null;
            if (isset($headers['Authorization'])) {
                $authHeader = $headers['Authorization'];
            } elseif (isset($headers['authorization'])) {
                $authHeader = $headers['authorization'];
            }
            
            if (!$authHeader) {
                return ['valid' => false, 'message' => 'Authorization header missing'];
            }
            
            // Extract Bearer token
            if (!preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
                return ['valid' => false, 'message' => 'Invalid authorization header format'];
            }
            
            $token = $matches[1];
            
            // Validate JWT token
            $payload = self::validateJWT($token);
            if (!$payload) {
                return ['valid' => false, 'message' => 'Invalid or expired token'];
            }
            
            // Check if user still exists and is active
            $user = self::validateUser($payload['user_id']);
            if (!$user) {
                return ['valid' => false, 'message' => 'User not found or inactive'];
            }
            
            // Set user in session for backward compatibility
            self::setUserSession($user);
            
            return [
                'valid' => true,
                'user' => $user,
                'payload' => $payload
            ];
            
        } catch (Exception $e) {
            error_log("Authentication validation error: " . $e->getMessage());
            return ['valid' => false, 'message' => 'Authentication error'];
        }
    }
    
    /**
     * Generate JWT token for user
     */
    public static function generateJWT($userId, $username, $role, $additionalClaims = []) {
        self::initJWTSecret();
        
        $header = json_encode(['typ' => 'JWT', 'alg' => 'HS256']);
        
        $payload = array_merge([
            'iss' => 'ARMIS',
            'aud' => 'ARMIS-API',
            'iat' => time(),
            'exp' => time() + self::$tokenExpiry,
            'user_id' => $userId,
            'username' => $username,
            'role' => $role,
            'jti' => uniqid('armis_', true) // Unique token ID
        ], $additionalClaims);
        
        $payloadEncoded = json_encode($payload);
        
        $base64Header = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($header));
        $base64Payload = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($payloadEncoded));
        
        $signature = hash_hmac('sha256', $base64Header . "." . $base64Payload, self::$jwtSecret, true);
        $base64Signature = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($signature));
        
        return $base64Header . "." . $base64Payload . "." . $base64Signature;
    }
    
    /**
     * Validate JWT token
     */
    public static function validateJWT($token) {
        self::initJWTSecret();
        
        try {
            $parts = explode('.', $token);
            if (count($parts) !== 3) {
                return false;
            }
            
            [$header, $payload, $signature] = $parts;
            
            // Verify signature
            $expectedSignature = hash_hmac('sha256', $header . "." . $payload, self::$jwtSecret, true);
            $expectedSignature = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($expectedSignature));
            
            if (!hash_equals($signature, $expectedSignature)) {
                return false;
            }
            
            // Decode payload
            $payloadData = json_decode(base64_decode(str_replace(['-', '_'], ['+', '/'], $payload)), true);
            
            if (!$payloadData) {
                return false;
            }
            
            // Check expiration
            if (isset($payloadData['exp']) && $payloadData['exp'] < time()) {
                return false;
            }
            
            return $payloadData;
            
        } catch (Exception $e) {
            error_log("JWT validation error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Generate refresh token
     */
    public static function generateRefreshToken($userId) {
        $token = bin2hex(random_bytes(32));
        $expires = time() + self::$refreshTokenExpiry;
        
        try {
            $pdo = getDbConnection();
            
            // Store refresh token in database
            $stmt = $pdo->prepare("
                INSERT INTO refresh_tokens (user_id, token, expires_at, created_at) 
                VALUES (?, ?, FROM_UNIXTIME(?), NOW())
                ON DUPLICATE KEY UPDATE 
                token = VALUES(token), 
                expires_at = VALUES(expires_at), 
                updated_at = NOW()
            ");
            $stmt->execute([$userId, $token, $expires]);
            
            return $token;
            
        } catch (Exception $e) {
            error_log("Refresh token generation error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Validate refresh token
     */
    public static function validateRefreshToken($token) {
        try {
            $pdo = getDbConnection();
            
            $stmt = $pdo->prepare("
                SELECT rt.*, s.username, s.role 
                FROM refresh_tokens rt
                JOIN staff s ON rt.user_id = s.id
                WHERE rt.token = ? AND rt.expires_at > NOW() AND s.accStatus = 'Active'
            ");
            $stmt->execute([$token]);
            
            return $stmt->fetch(PDO::FETCH_ASSOC);
            
        } catch (Exception $e) {
            error_log("Refresh token validation error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Validate user exists and is active
     */
    private static function validateUser($userId) {
        try {
            $pdo = getDbConnection();
            
            $stmt = $pdo->prepare("
                SELECT s.*, r.name as rank_name, r.abbreviation as rank_abbr,
                       u.name as unit_name, c.name as corps_name, c.abbreviation as corps_abbr
                FROM staff s
                LEFT JOIN ranks r ON s.rank_id = r.id
                LEFT JOIN units u ON s.unit_id = u.id
                LEFT JOIN corps c ON s.corps = c.name
                WHERE s.id = ? AND s.accStatus = 'Active'
            ");
            $stmt->execute([$userId]);
            
            return $stmt->fetch(PDO::FETCH_ASSOC);
            
        } catch (Exception $e) {
            error_log("User validation error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Set user session for backward compatibility
     */
    private static function setUserSession($user) {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['rank'] = $user['rank_name'] ?? '';
        $_SESSION['rank_abbr'] = $user['rank_abbr'] ?? '';
        $_SESSION['fname'] = $user['first_name'] ?? '';
        $_SESSION['lname'] = $user['last_name'] ?? '';
        $_SESSION['svcNo'] = $user['service_number'] ?? '';
        $_SESSION['unit'] = $user['unit_name'] ?? '';
        $_SESSION['corps'] = $user['corps_name'] ?? '';
        $_SESSION['corps_abbr'] = $user['corps_abbr'] ?? '';
    }
    
    /**
     * Setup MFA for user
     */
    public static function setupMFA($userId) {
        // Generate TOTP secret
        $secret = self::generateTOTPSecret();
        
        try {
            $pdo = getDbConnection();
            
            $stmt = $pdo->prepare("
                INSERT INTO user_mfa (user_id, secret, enabled, created_at)
                VALUES (?, ?, 0, NOW())
                ON DUPLICATE KEY UPDATE secret = VALUES(secret), updated_at = NOW()
            ");
            $stmt->execute([$userId, $secret]);
            
            return [
                'secret' => $secret,
                'qr_code_url' => self::generateQRCodeURL($secret, $userId)
            ];
            
        } catch (Exception $e) {
            error_log("MFA setup error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Generate TOTP secret
     */
    private static function generateTOTPSecret() {
        return base32_encode(random_bytes(20));
    }
    
    /**
     * Generate QR code URL for TOTP
     */
    private static function generateQRCodeURL($secret, $userId) {
        $issuer = 'ARMIS';
        $accountName = "ARMIS:$userId";
        
        $query = http_build_query([
            'secret' => $secret,
            'issuer' => $issuer,
            'algorithm' => 'SHA1',
            'digits' => 6,
            'period' => 30
        ]);
        
        return "otpauth://totp/{$accountName}?{$query}";
    }
    
    /**
     * Verify TOTP code
     */
    public static function verifyTOTP($userId, $code) {
        try {
            $pdo = getDbConnection();
            
            $stmt = $pdo->prepare("SELECT secret FROM user_mfa WHERE user_id = ? AND enabled = 1");
            $stmt->execute([$userId]);
            $mfa = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$mfa) {
                return false;
            }
            
            // Verify TOTP code (simplified implementation)
            return self::verifyTOTPCode($mfa['secret'], $code);
            
        } catch (Exception $e) {
            error_log("TOTP verification error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Simplified TOTP verification
     */
    private static function verifyTOTPCode($secret, $code) {
        // This is a simplified implementation
        // In production, use a proper TOTP library
        $timeStep = floor(time() / 30);
        
        // Allow for time drift (check current and previous time windows)
        for ($i = -1; $i <= 1; $i++) {
            $hash = hash_hmac('sha1', pack('N*', 0) . pack('N*', $timeStep + $i), base32_decode($secret), true);
            $offset = ord($hash[19]) & 0xf;
            $calculatedCode = (
                ((ord($hash[$offset]) & 0x7f) << 24) |
                ((ord($hash[$offset + 1]) & 0xff) << 16) |
                ((ord($hash[$offset + 2]) & 0xff) << 8) |
                (ord($hash[$offset + 3]) & 0xff)
            ) % 1000000;
            
            if (sprintf('%06d', $calculatedCode) === $code) {
                return true;
            }
        }
        
        return false;
    }
}

// Helper function for base32 encoding
if (!function_exists('base32_encode')) {
    function base32_encode($data) {
        $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $encoded = '';
        $buffer = 0;
        $bitsLeft = 0;
        
        for ($i = 0; $i < strlen($data); $i++) {
            $buffer = ($buffer << 8) | ord($data[$i]);
            $bitsLeft += 8;
            
            while ($bitsLeft >= 5) {
                $encoded .= $chars[($buffer >> ($bitsLeft - 5)) & 31];
                $bitsLeft -= 5;
            }
        }
        
        if ($bitsLeft > 0) {
            $encoded .= $chars[($buffer << (5 - $bitsLeft)) & 31];
        }
        
        return $encoded;
    }
}

// Helper function for base32 decoding
if (!function_exists('base32_decode')) {
    function base32_decode($data) {
        $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $charMap = array_flip(str_split($chars));
        
        $decoded = '';
        $buffer = 0;
        $bitsLeft = 0;
        
        for ($i = 0; $i < strlen($data); $i++) {
            if (!isset($charMap[$data[$i]])) continue;
            
            $buffer = ($buffer << 5) | $charMap[$data[$i]];
            $bitsLeft += 5;
            
            if ($bitsLeft >= 8) {
                $decoded .= chr(($buffer >> ($bitsLeft - 8)) & 255);
                $bitsLeft -= 8;
            }
        }
        
        return $decoded;
    }
}
?>