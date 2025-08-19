<?php
/**
 * ARMIS Authentication Controller
 * Handles login, logout, token refresh, and MFA operations
 */

require_once __DIR__ . '/../middleware/authentication.php';

class AuthController {
    private $db;
    
    public function __construct() {
        $this->db = getDbConnection();
    }
    
    /**
     * User login with JWT token generation
     */
    public function login($params, $data) {
        try {
            $username = $data['username'] ?? '';
            $password = $data['password'] ?? '';
            $mfaCode = $data['mfa_code'] ?? '';
            
            if (empty($username) || empty($password)) {
                return ['error' => 'Username and password are required', 'code' => 400];
            }
            
            // Authenticate user
            $user = $this->authenticateUser($username, $password);
            if (!$user) {
                // Log failed login attempt
                $this->logLoginAttempt($username, false, $_SERVER['REMOTE_ADDR']);
                return ['error' => 'Invalid credentials', 'code' => 401];
            }
            
            // Check if MFA is enabled
            $mfaEnabled = $this->checkMFAEnabled($user['id']);
            if ($mfaEnabled && empty($mfaCode)) {
                return [
                    'error' => 'MFA code required',
                    'code' => 403,
                    'mfa_required' => true
                ];
            }
            
            // Verify MFA if provided
            if ($mfaEnabled && !empty($mfaCode)) {
                if (!APIAuthentication::verifyTOTP($user['id'], $mfaCode)) {
                    $this->logLoginAttempt($username, false, $_SERVER['REMOTE_ADDR'], 'Invalid MFA code');
                    return ['error' => 'Invalid MFA code', 'code' => 401];
                }
            }
            
            // Generate tokens
            $accessToken = APIAuthentication::generateJWT(
                $user['id'],
                $user['username'],
                $user['role'],
                [
                    'rank' => $user['rank_name'] ?? '',
                    'unit' => $user['unit_name'] ?? '',
                    'corps' => $user['corps_name'] ?? ''
                ]
            );
            
            $refreshToken = APIAuthentication::generateRefreshToken($user['id']);
            
            // Update last login
            $this->updateLastLogin($user['id']);
            
            // Log successful login
            $this->logLoginAttempt($username, true, $_SERVER['REMOTE_ADDR']);
            
            return [
                'message' => 'Login successful',
                'access_token' => $accessToken,
                'refresh_token' => $refreshToken,
                'token_type' => 'Bearer',
                'expires_in' => 3600,
                'user' => [
                    'id' => $user['id'],
                    'username' => $user['username'],
                    'role' => $user['role'],
                    'name' => trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')),
                    'rank' => $user['rank_name'] ?? '',
                    'unit' => $user['unit_name'] ?? '',
                    'corps' => $user['corps_name'] ?? ''
                ]
            ];
            
        } catch (Exception $e) {
            error_log("Login error: " . $e->getMessage());
            return ['error' => 'Login failed', 'code' => 500];
        }
    }
    
    /**
     * User logout (invalidate tokens)
     */
    public function logout($params, $data) {
        try {
            // Get current user from session or token
            $userId = $_SESSION['user_id'] ?? null;
            
            if ($userId) {
                // Invalidate refresh tokens
                $stmt = $this->db->prepare("DELETE FROM refresh_tokens WHERE user_id = ?");
                $stmt->execute([$userId]);
                
                // Clear session
                session_destroy();
                
                return ['message' => 'Logout successful'];
            }
            
            return ['error' => 'Not authenticated', 'code' => 401];
            
        } catch (Exception $e) {
            error_log("Logout error: " . $e->getMessage());
            return ['error' => 'Logout failed', 'code' => 500];
        }
    }
    
    /**
     * Refresh access token using refresh token
     */
    public function refresh($params, $data) {
        try {
            $refreshToken = $data['refresh_token'] ?? '';
            
            if (empty($refreshToken)) {
                return ['error' => 'Refresh token required', 'code' => 400];
            }
            
            // Validate refresh token
            $tokenData = APIAuthentication::validateRefreshToken($refreshToken);
            if (!$tokenData) {
                return ['error' => 'Invalid or expired refresh token', 'code' => 401];
            }
            
            // Generate new access token
            $accessToken = APIAuthentication::generateJWT(
                $tokenData['user_id'],
                $tokenData['username'],
                $tokenData['role']
            );
            
            // Optionally generate new refresh token for enhanced security
            $newRefreshToken = APIAuthentication::generateRefreshToken($tokenData['user_id']);
            
            return [
                'message' => 'Token refreshed successfully',
                'access_token' => $accessToken,
                'refresh_token' => $newRefreshToken,
                'token_type' => 'Bearer',
                'expires_in' => 3600
            ];
            
        } catch (Exception $e) {
            error_log("Token refresh error: " . $e->getMessage());
            return ['error' => 'Token refresh failed', 'code' => 500];
        }
    }
    
    /**
     * Setup MFA for user
     */
    public function setupMFA($params, $data) {
        try {
            $userId = $_SESSION['user_id'] ?? null;
            
            if (!$userId) {
                return ['error' => 'Authentication required', 'code' => 401];
            }
            
            $mfaData = APIAuthentication::setupMFA($userId);
            if (!$mfaData) {
                return ['error' => 'MFA setup failed', 'code' => 500];
            }
            
            return [
                'message' => 'MFA setup initiated',
                'secret' => $mfaData['secret'],
                'qr_code_url' => $mfaData['qr_code_url'],
                'backup_codes' => $this->generateBackupCodes($userId)
            ];
            
        } catch (Exception $e) {
            error_log("MFA setup error: " . $e->getMessage());
            return ['error' => 'MFA setup failed', 'code' => 500];
        }
    }
    
    /**
     * Verify and enable MFA
     */
    public function verifyMFA($params, $data) {
        try {
            $userId = $_SESSION['user_id'] ?? null;
            $code = $data['code'] ?? '';
            
            if (!$userId || empty($code)) {
                return ['error' => 'User ID and MFA code required', 'code' => 400];
            }
            
            if (APIAuthentication::verifyTOTP($userId, $code)) {
                // Enable MFA for user
                $stmt = $this->db->prepare("UPDATE user_mfa SET enabled = 1 WHERE user_id = ?");
                $stmt->execute([$userId]);
                
                return ['message' => 'MFA enabled successfully'];
            } else {
                return ['error' => 'Invalid MFA code', 'code' => 400];
            }
            
        } catch (Exception $e) {
            error_log("MFA verification error: " . $e->getMessage());
            return ['error' => 'MFA verification failed', 'code' => 500];
        }
    }
    
    /**
     * Authenticate user credentials
     */
    private function authenticateUser($username, $password) {
        try {
            $stmt = $this->db->prepare("
                SELECT s.*, r.name as rank_name, r.abbreviation as rank_abbr,
                       u.name as unit_name, c.name as corps_name, c.abbreviation as corps_abbr
                FROM staff s
                LEFT JOIN ranks r ON s.rank_id = r.id
                LEFT JOIN units u ON s.unit_id = u.id
                LEFT JOIN corps c ON s.corps = c.name
                WHERE s.username = ? AND s.accStatus = 'Active'
            ");
            $stmt->execute([$username]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$user) {
                return false;
            }
            
            // Verify password
            if (password_verify($password, $user['password'])) {
                return $user;
            }
            
            return false;
            
        } catch (Exception $e) {
            error_log("User authentication error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Check if MFA is enabled for user
     */
    private function checkMFAEnabled($userId) {
        try {
            $stmt = $this->db->prepare("SELECT enabled FROM user_mfa WHERE user_id = ?");
            $stmt->execute([$userId]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            return $result && $result['enabled'] == 1;
            
        } catch (Exception $e) {
            error_log("MFA check error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Update user's last login timestamp
     */
    private function updateLastLogin($userId) {
        try {
            $stmt = $this->db->prepare("UPDATE staff SET last_login = NOW() WHERE id = ?");
            $stmt->execute([$userId]);
        } catch (Exception $e) {
            error_log("Last login update error: " . $e->getMessage());
        }
    }
    
    /**
     * Log login attempt for security auditing
     */
    private function logLoginAttempt($username, $successful, $ipAddress, $reason = null) {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO login_attempts (username, successful, ip_address, reason, attempted_at)
                VALUES (?, ?, ?, ?, NOW())
            ");
            $stmt->execute([$username, $successful ? 1 : 0, $ipAddress, $reason]);
        } catch (Exception $e) {
            error_log("Login attempt logging error: " . $e->getMessage());
        }
    }
    
    /**
     * Generate backup codes for MFA
     */
    private function generateBackupCodes($userId) {
        $codes = [];
        for ($i = 0; $i < 10; $i++) {
            $codes[] = sprintf('%04d-%04d', mt_rand(0, 9999), mt_rand(0, 9999));
        }
        
        try {
            // Store backup codes in database (hashed)
            foreach ($codes as $code) {
                $hashedCode = password_hash($code, PASSWORD_DEFAULT);
                $stmt = $this->db->prepare("
                    INSERT INTO mfa_backup_codes (user_id, code_hash, created_at)
                    VALUES (?, ?, NOW())
                ");
                $stmt->execute([$userId, $hashedCode]);
            }
        } catch (Exception $e) {
            error_log("Backup codes generation error: " . $e->getMessage());
        }
        
        return $codes;
    }
}
?>