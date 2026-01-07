<?php
declare(strict_types=1);
// Minimal clean DB helper for ARMIS

if (!defined('DB_HOST')) define('DB_HOST', 'localhost');
if (!defined('DB_NAME')) define('DB_NAME', 'armis1');
if (!defined('DB_USER')) define('DB_USER', 'root');
if (!defined('DB_PASS')) define('DB_PASS', '');
if (!defined('DB_CHARSET')) define('DB_CHARSET', 'utf8mb4');

/**
 * Get (and cache) a PDO database connection.
 *
 * @return PDO
 * @throws PDOException
 */
function getDbConnection()
{
    static $pdo = null;
    if ($pdo === null) {
        $dsn = sprintf('mysql:host=%s;dbname=%s;charset=%s', DB_HOST, DB_NAME, DB_CHARSET);
        $opts = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];
        $pdo = new PDO($dsn, DB_USER, DB_PASS, $opts);
    }
    return $pdo;
}

/**
 * Authenticate a user by username/service number and password.
 *
 * @param string $username
 * @param string $password
 * @return array|false
 */
function authenticateUser($username, $password)
{
    $pdo = getDbConnection();
    // Use camelCase column names consistently - corps.corpsId is the primary key
    $sql = "SELECT s.svcNo AS id, s.username, s.password, s.role, s.accStatus, s.lastLogin, s.svcNo, s.isFirstLogin, 
               s.fName, s.lName, s.email, s.corpsId, r.rankId AS rank_name, u.code AS unit_name, c.abbreviation AS corps_abbr
        FROM staff s
        LEFT JOIN `rank` r ON s.rankId = r.rankId
        LEFT JOIN `unit` u ON s.unitId = u.unitId
        LEFT JOIN corps c ON s.corpsId = c.corpsId
        WHERE (s.username = ? OR s.svcNo = ?)
        LIMIT 1";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$username, $username]);
    $user = $stmt->fetch();
    if (!$user) return false;
    if (!isset($user['password'])) return false;
    return password_verify($password, $user['password']) ? $user : false;
}

/**
 * Update the lastLogin timestamp for a user.
 *
 * @param int $userId
 * @return bool
 */
function updateLastLogin($userId)
{
    $pdo = getDbConnection();
    // The staff table uses svcNo as the identifier. Accept either an id alias (svcNo)
    // or a raw svcNo value here and update the appropriate row.
    $stmt = $pdo->prepare('UPDATE staff SET lastLogin = NOW() WHERE svcNo = ?');
    return $stmt->execute([$userId]);
}

/**
 * Fetch profile data for a user ID.
 *
 * @param int $userId
 * @return array|false
 */
function getUserProfileData($userId)
{
    $pdo = getDbConnection();
    // Use svcNo as the lookup key (aliased as id elsewhere in the code). This keeps
    // callers that pass the session-stored `user_id` (which is svcNo) compatible.
    // Use camelCase column names consistently - corps.corpsId is the primary key
    $sql = "SELECT s.*, s.role, s.isFirstLogin, s.fName, s.lName, s.email, s.corpsId, r.rankId AS rank_abbr, r.rankId AS rank_name, u.code AS unit_code, c.abbreviation AS corps_abbr FROM staff s LEFT JOIN `rank` r ON s.rankId = r.rankId LEFT JOIN unit u ON s.unitId = u.unitId LEFT JOIN corps c ON s.corpsId = c.corpsId WHERE s.svcNo = ? LIMIT 1";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$userId]);
    return $stmt->fetch();
}

// No PHP closing tag to avoid accidental output
