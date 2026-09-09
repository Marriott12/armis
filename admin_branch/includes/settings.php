<?php
/**
 * Admin Branch settings — thin read/write layer over the
 * admin_branch_settings table (see the migration in
 * database/migrations/2026_08_26_add_admin_branch_settings_table.sql).
 */

if (!function_exists('getAdminBranchSetting')) {
    function getAdminBranchSetting(string $key, ?string $default = null): ?string
    {
        try {
            $pdo = getDbConnection();
            $stmt = $pdo->prepare('SELECT setting_value FROM admin_branch_settings WHERE setting_key = ?');
            $stmt->execute([$key]);
            $value = $stmt->fetchColumn();
            return $value !== false ? $value : $default;
        } catch (PDOException $e) {
            // Falls back to the constant default if the migration in
            // database/migrations/2026_08_26_add_admin_branch_settings_table.sql
            // hasn't been run yet, rather than fatal-erroring every page
            // that touches a setting (e.g. ajax_search.php's page size).
            error_log('admin_branch_settings read failed (has the migration been run?): ' . $e->getMessage());
            return $default;
        }
    }
}

if (!function_exists('setAdminBranchSetting')) {
    function setAdminBranchSetting(string $key, string $value, ?string $updatedBySvcNo = null): bool
    {
        try {
            $pdo = getDbConnection();
            $stmt = $pdo->prepare(
                'INSERT INTO admin_branch_settings (setting_key, setting_value, updated_by)
                 VALUES (?, ?, ?)
                 ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_by = VALUES(updated_by)'
            );
            $stmt->execute([$key, $value, $updatedBySvcNo]);
            return true;
        } catch (PDOException $e) {
            error_log('admin_branch_settings write failed (has the migration been run?): ' . $e->getMessage());
            return false;
        }
    }
}
