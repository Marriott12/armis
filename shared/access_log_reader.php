<?php
/**
 * Reads logs/access.log — the JSON-lines access log written by
 * shared/rbac.php's logAccess(), called from every module (admin,
 * admin_branch, operations, training, command, etc.).
 *
 * This is the comprehensive, app-wide activity source. The separate
 * `activity_log` DATABASE table is NOT this — it's only written to by
 * admin_branch/includes/auth.php's logActivity(), so it only ever
 * reflects admin_branch usage. Querying `activity_log` for "who's
 * active right now" or "login activity" undercounts (or shows zero)
 * because it misses every other module. Use this file's functions
 * instead for anything meant to reflect real, whole-app activity.
 */

if (!function_exists('readAccessLogEntries')) {
    /**
     * @param int $sinceCutoffTs Unix timestamp; only entries at or after
     * this time are returned. 0 means no lower bound.
     * @return array Each entry as decoded from its JSON line, plus a
     * '_time' key with the parsed Unix timestamp for convenience.
     */
    function readAccessLogEntries(int $sinceCutoffTs = 0): array {
        $entries = [];
        $path = dirname(__DIR__) . '/logs/access.log';
        if (!is_readable($path)) {
            return $entries;
        }
        $handle = fopen($path, 'r');
        if (!$handle) {
            return $entries;
        }
        while (($line = fgets($handle)) !== false) {
            $entry = json_decode($line, true);
            if (!$entry) continue;
            $entry['_time'] = strtotime($entry['timestamp'] ?? '');
            if ($sinceCutoffTs > 0 && $entry['_time'] < $sinceCutoffTs) continue;
            $entries[] = $entry;
        }
        fclose($handle);
        return $entries; // chronological order, oldest first
    }
}

if (!function_exists('getActiveUsersFromAccessLog')) {
    /**
     * Distinct users with at least one successful (any module) entry
     * within the last $windowSeconds, most recent first. This is the
     * app's definition of "currently active" everywhere it's used.
     */
    function getActiveUsersFromAccessLog(int $windowSeconds): array {
        $cutoff = time() - $windowSeconds;
        $byUser = [];
        foreach (readAccessLogEntries($cutoff) as $entry) {
            if (empty($entry['success'])) continue;
            $key = $entry['username'] ?? ($entry['user_id'] ?? 'unknown');
            $byUser[$key] = [
                'user_id' => $entry['user_id'] ?? '',
                'username' => $entry['username'] ?? $key,
                'last_seen' => $entry['timestamp'] ?? '',
                'last_ip' => $entry['ip'] ?? '',
            ];
        }
        $rows = array_values($byUser);
        usort($rows, fn($a, $b) => strtotime($b['last_seen']) <=> strtotime($a['last_seen']));
        return $rows;
    }
}
