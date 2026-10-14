<?php
/**
 * ARMIS authentication session bootstrap.
 *
 * SECURITY: this file MUST NOT create a fake/default authenticated session.
 * It only starts an anonymous session and, when an authenticated account is
 * already present, refreshes non-authoritative display data from staff.
 */

require_once __DIR__ . '/session_security.php';
armisStartSecureSession();

require_once __DIR__ . '/database_connection.php';

if (!function_exists('initializeUserSession')) {
    function initializeUserSession($userId = null): bool
    {
        $svcNo = (string)($userId ?? ($_SESSION['svcNo'] ?? ''));
        if ($svcNo === '') {
            return false;
        }

        try {
            $pdo = getDbConnection();
            $stmt = $pdo->prepare(
                "SELECT s.svcNo, s.username, s.role, s.branch_id, s.accStatus,
                        s.fName, s.lName, s.officialEmail, s.corps,
                        r.rankId AS rank_name, u.unitId AS unit_name
                 FROM staff s
                 LEFT JOIN `rank` r ON s.rankId = r.rankId
                 LEFT JOIN unit u ON s.unitId = u.unitId
                 WHERE s.svcNo = ?
                 LIMIT 1"
            );
            $stmt->execute([$svcNo]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$user || ($user['accStatus'] ?? '') !== 'Active') {
                return false;
            }

            // Only refresh identity data from the canonical staff record.
            $_SESSION['user_id'] = $user['svcNo'];
            $_SESSION['userID'] = $user['svcNo'];
            $_SESSION['svcNo'] = $user['svcNo'];
            $_SESSION['username'] = $user['username'] ?? '';
            $_SESSION['role'] = $user['role'] ?? 'user';
            $_SESSION['branch_id'] = $user['branch_id'] ?? null;
            $_SESSION['rank'] = $user['rank_name'] ?? 'Unknown';
            $_SESSION['name'] = trim(($user['fName'] ?? '') . ' ' . ($user['lName'] ?? ''));
            $_SESSION['fName'] = $user['fName'] ?? '';
            $_SESSION['lName'] = $user['lName'] ?? '';
            $_SESSION['email'] = $user['officialEmail'] ?? '';
            $_SESSION['unit'] = $user['unit_name'] ?? 'Unknown';
            $_SESSION['corps'] = $user['corps'] ?? 'Unknown';
            return true;
        } catch (Throwable $e) {
            error_log('ARMIS session profile refresh failed: ' . $e->getMessage());
            return false;
        }
    }
}

// Existing authenticated sessions may be enriched; anonymous sessions remain anonymous.
if (isset($_SESSION['user_id']) && $_SESSION['user_id'] !== '') {
    initializeUserSession($_SESSION['user_id']);
}
