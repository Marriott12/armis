<?php
/**
 * ARMIS shared notifications
 * --------------------------
 * Backs the real, app-wide notification bell (see
 * shared/js/notifications.js and shared/notifications_api.php),
 * replacing the previously-disabled shared/notifications.js stub and
 * consolidating operations/operations_manager.php's notification
 * methods, which pointed at an `operations_notifications` table that
 * was never actually created.
 *
 * IMPORTANT: staff has no numeric `id` column — its primary key is
 * svcNo (varchar). $_SESSION['user_id'] actually holds svcNo (see
 * shared/database_connection.php's authenticateUser(), which selects
 * `s.svcNo AS id`). Every function here takes/returns svcNo, not a
 * numeric id, despite the "user_id" naming used elsewhere in the app.
 */

require_once __DIR__ . '/database_connection.php';

if (!function_exists('createNotification')) {
    /**
     * @param string $svcNo Recipient's svcNo.
     * @param string $module e.g. 'admin_branch', 'operations'.
     * @param string $type e.g. 'staff_created', 'promotion', 'medal_assigned'.
     * @param string $title Short headline shown in the bell dropdown.
     * @param string|null $message Optional longer body text.
     * @param string|null $link Optional relative URL opened on click.
     */
    function createNotification(
        string $svcNo,
        string $module,
        string $type,
        string $title,
        ?string $message = null,
        ?string $link = null
    ): bool {
        try {
            $pdo = getDbConnection();
            $stmt = $pdo->prepare(
                'INSERT INTO notifications (user_id, module, type, title, message, link)
                 VALUES (?, ?, ?, ?, ?, ?)'
            );
            return $stmt->execute([$svcNo, $module, $type, $title, $message, $link]);
        } catch (PDOException $e) {
            // Falls back to silently skipping the notification (rather
            // than fatal-erroring the actual action, e.g. staff
            // creation, that triggered it) if the migration in
            // database/migrations/2026_08_27_add_notifications_table.sql
            // hasn't been run yet.
            error_log('createNotification failed (has the migration been run?): ' . $e->getMessage());
            return false;
        }
    }
}

if (!function_exists('notifyRoles')) {
    /**
     * Send the same notification to every staff member whose (possibly
     * comma-separated) role includes any of $roles — e.g. every
     * admin/superadmin, for oversight-style events like "staff record
     * deleted".
     *
     * @param string[] $roles
     */
    function notifyRoles(
        array $roles,
        string $module,
        string $type,
        string $title,
        ?string $message = null,
        ?string $link = null,
        ?string $excludeSvcNo = null
    ): void {
        try {
            $pdo = getDbConnection();
            $conditions = [];
            $params = [];
            foreach ($roles as $role) {
                $conditions[] = 'FIND_IN_SET(?, role)';
                $params[] = $role;
            }
            $sql = 'SELECT svcNo FROM staff WHERE (' . implode(' OR ', $conditions) . ") AND svcStatus = 'Active'";
            if ($excludeSvcNo !== null) {
                $sql .= ' AND svcNo != ?';
                $params[] = $excludeSvcNo;
            }
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $recipients = $stmt->fetchAll(PDO::FETCH_COLUMN);
            foreach ($recipients as $svcNo) {
                createNotification($svcNo, $module, $type, $title, $message, $link);
            }
        } catch (PDOException $e) {
            error_log('notifyRoles failed: ' . $e->getMessage());
        }
    }
}

if (!function_exists('getUserNotifications')) {
    function getUserNotifications(string $svcNo, int $limit = 20): array
    {
        try {
            $pdo = getDbConnection();
            $stmt = $pdo->prepare(
                'SELECT id, module, type, title, message, link, status, created_at
                 FROM notifications
                 WHERE user_id = ?
                 ORDER BY created_at DESC
                 LIMIT ?'
            );
            $stmt->bindValue(1, $svcNo, PDO::PARAM_STR);
            $stmt->bindValue(2, $limit, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log('getUserNotifications failed (has the migration been run?): ' . $e->getMessage());
            return [];
        }
    }
}

if (!function_exists('getUnreadNotificationCount')) {
    function getUnreadNotificationCount(string $svcNo): int
    {
        try {
            $pdo = getDbConnection();
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND status = 'unread'");
            $stmt->execute([$svcNo]);
            return (int) $stmt->fetchColumn();
        } catch (PDOException $e) {
            error_log('getUnreadNotificationCount failed: ' . $e->getMessage());
            return 0;
        }
    }
}

if (!function_exists('markNotificationRead')) {
    /**
     * @param int $notificationId
     * @param string $svcNo The requesting user's svcNo — always scoped
     * to their own notifications, so one user can't mark another's as
     * read by guessing an id.
     */
    function markNotificationRead(int $notificationId, string $svcNo): bool
    {
        try {
            $pdo = getDbConnection();
            $stmt = $pdo->prepare(
                "UPDATE notifications SET status = 'read', read_at = NOW()
                 WHERE id = ? AND user_id = ?"
            );
            return $stmt->execute([$notificationId, $svcNo]);
        } catch (PDOException $e) {
            error_log('markNotificationRead failed: ' . $e->getMessage());
            return false;
        }
    }
}

if (!function_exists('markAllNotificationsRead')) {
    function markAllNotificationsRead(string $svcNo): bool
    {
        try {
            $pdo = getDbConnection();
            $stmt = $pdo->prepare(
                "UPDATE notifications SET status = 'read', read_at = NOW()
                 WHERE user_id = ? AND status = 'unread'"
            );
            return $stmt->execute([$svcNo]);
        } catch (PDOException $e) {
            error_log('markAllNotificationsRead failed: ' . $e->getMessage());
            return false;
        }
    }
}
