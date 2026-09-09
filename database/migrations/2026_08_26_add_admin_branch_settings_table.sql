-- Admin Branch settings storage.
--
-- system_settings.php was linked from admin_branch's navigation (and
-- from a dead, unused NAVIGATION_MENU constant in includes/config.php)
-- but the file never existed. Separately, the only other "settings"
-- page in the app (admin/settings.php) is an admitted demo — its POST
-- handler literally comments "Settings would be updated in a
-- production system. This is a demo interface" and never writes
-- anywhere. Rather than build a second fake settings screen, this
-- table gives admin_branch's version somewhere real to persist to.
--
-- Simple key-value store, not a wide settings table, so new settings
-- can be added later without another migration.

CREATE TABLE IF NOT EXISTS `admin_branch_settings` (
    `setting_key` VARCHAR(100) NOT NULL PRIMARY KEY,
    `setting_value` VARCHAR(255) NOT NULL,
    `updated_by` VARCHAR(50) DEFAULT NULL COMMENT 'svcNo of the user who last changed this',
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Seed with the current hardcoded defaults from includes/config.php,
-- so behavior is identical until an administrator actually changes
-- something.
INSERT INTO `admin_branch_settings` (`setting_key`, `setting_value`) VALUES
    ('staff_list_page_size', '25'),
    ('max_upload_size_mb', '10'),
    ('allowed_upload_types', 'jpg,jpeg,png,pdf,doc,docx,xls,xlsx')
ON DUPLICATE KEY UPDATE `setting_key` = `setting_key`;
