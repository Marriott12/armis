<?php
/**
 * ARMIS Centralized Database Connection
 * Single point of database connectivity for the entire system
 * Version: 2.0
 */

// Prevent direct access
if (!defined('ARMIS_DB_CONFIG')) {
    define('ARMIS_DB_CONFIG', true);
}

// Database configuration constants
if (!defined('DB_HOST')) define('DB_HOST', 'localhost');
if (!defined('DB_NAME')) define('DB_NAME', 'armis1');
if (!defined('DB_USER')) define('DB_USER', 'root');
if (!defined('DB_PASS')) define('DB_PASS', '');
if (!defined('DB_CHARSET')) define('DB_CHARSET', 'utf8mb4');

/**
 * Get PDO Database Connection (Preferred method)
 * @return PDO
 */
function getDbConnection() {
    static $pdo = null;
    
    if ($pdo === null) {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::ATTR_PERSISTENT => false
            ];
            
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
            error_log("PDO Database connection established successfully to " . DB_NAME);
        } catch (PDOException $e) {
            error_log("PDO Database connection failed: " . $e->getMessage());
            throw new Exception("Database connection failed: " . $e->getMessage());
        }
    }
    
    return $pdo;
}

/**
 * Get MySQLi Database Connection (For legacy compatibility)
 * @return mysqli
 */
function getMysqliConnection() {
    static $mysqli = null;
    
    if ($mysqli === null) {
        try {
            $mysqli = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
            
            if ($mysqli->connect_error) {
                throw new Exception("MySQLi connection failed: " . $mysqli->connect_error);
            }
            
            $mysqli->set_charset(DB_CHARSET);
            error_log("MySQLi Database connection established successfully to " . DB_NAME);
        } catch (Exception $e) {
            error_log("MySQLi Database connection failed: " . $e->getMessage());
            throw new Exception("Database connection failed: " . $e->getMessage());
        }
    }
    
    return $mysqli;
}

/**
 * Enhanced user authentication function with temporary password support
 */
function authenticateUser($username, $password) {
    try {
        $pdo = getDbConnection();
        
        $stmt = $pdo->prepare("
            SELECT s.id, s.username, s.password, s.role, s.accStatus, 
                   s.last_login, s.service_number,
                   s.first_name, s.last_name, s.email, s.corps,
                   r.name as rank_name, u.name as unit_name,
                   c.name as corps_name, c.abbreviation as corps_abbr
            FROM staff s 
            LEFT JOIN ranks r ON s.rank_id = r.id
            LEFT JOIN units u ON s.unit_id = u.id
            LEFT JOIN corps c ON s.corps = c.name
            WHERE s.username = ? AND (s.accStatus = 'active' OR s.accStatus = 'Active')
        ");
        
        $stmt->execute([$username]);
        $user = $stmt->fetch();
        
        if (!$user) {
            return false;
        }
        
        // Check regular password
        if (password_verify($password, $user['password'])) {
            updateLastLogin($user['id']);
            return $user;
        }
        
        return false;
        
    } catch (Exception $e) {
        error_log("Authentication error: " . $e->getMessage());
        return false;
    }
}

/**
 * Update user's last login timestamp
 */
function updateLastLogin($userId) {
    try {
        $pdo = getDbConnection();
        $stmt = $pdo->prepare("UPDATE staff SET last_login = NOW() WHERE id = ?");
        $stmt->execute([$userId]);
        return true;
    } catch (Exception $e) {
        error_log("Failed to update last login: " . $e->getMessage());
        return false;
    }
}

/**
 * Get detailed user profile data from database
 */
function getUserProfileData($userId) {
    try {
        $pdo = getDbConnection();
        $stmt = $pdo->prepare("
            SELECT 
                s.*,
                r.name as rank_name,
                r.abbreviation as rank_abbr,
                u.name as unit_name,
                u.code as unit_code,
                c.name as corps_name,
                c.abbreviation as corps_abbr
            FROM staff s
            LEFT JOIN ranks r ON s.rank_id = r.id
            LEFT JOIN units u ON s.unit_id = u.id
            LEFT JOIN corps c ON s.corps = c.name
            WHERE s.id = ?
        ");
        $stmt->execute([$userId]);
        return $stmt->fetch();
    } catch (Exception $e) {
        error_log("Failed to get user profile data: " . $e->getMessage());
        return false;
    }
}

/**
 * Check if database tables exist and are properly structured
 */
function verifyDatabaseStructure() {
    try {
        $pdo = getDbConnection();
        
        $tables = ['staff', 'ranks', 'units', 'corps'];
        $results = [];
        
        foreach ($tables as $table) {
            $stmt = $pdo->prepare("SHOW TABLES LIKE ?");
            $stmt->execute([$table]);
            $results[$table] = $stmt->rowCount() > 0;
        }
        
        return $results;
        
    } catch (Exception $e) {
        error_log("Database structure verification failed: " . $e->getMessage());
        return false;
    }
}

/**
 * Get database connection for backward compatibility
 * @deprecated Use getDbConnection() instead
 */
if (!function_exists('getDBConnection')) {
    function getDBConnection() {
        return getDbConnection();
    }
}

/**
 * Fetch all results from a query (Helper function for compatibility)
 */
if (!function_exists('fetchAll')) {
    function fetchAll($sql, $params = []) {
        try {
            $pdo = getDbConnection();
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_OBJ);
        } catch (PDOException $e) {
            error_log("fetchAll query failed: " . $e->getMessage());
            throw new Exception("Database query failed: " . $e->getMessage());
        }
    }
}

/**
 * Fetch single result from a query (Helper function for compatibility)
 */
if (!function_exists('fetchOne')) {
    function fetchOne($sql, $params = []) {
        try {
            $pdo = getDbConnection();
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetch(PDO::FETCH_OBJ);
        } catch (PDOException $e) {
            error_log("fetchOne query failed: " . $e->getMessage());
            throw new Exception("Database query failed: " . $e->getMessage());
        }
    }
}

// Initialize connection test on include
try {
    $test_connection = getDbConnection();
    error_log("ARMIS Database connection initialized successfully");
} catch (Exception $e) {
    error_log("ARMIS Database initialization failed: " . $e->getMessage());
}
?>
