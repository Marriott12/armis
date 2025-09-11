-- Create deletion_log table for tracking staff deletions
-- This provides more specific auditing for deletion operations

CREATE TABLE IF NOT EXISTS `deletion_log` (
  `id` int NOT NULL AUTO_INCREMENT,
  `svcNo` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `staff_name` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `rank_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `unit_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `deleted_by` int NOT NULL,
  `deleted_by_username` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `user_ip` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `reason` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `staff_data_backup` json DEFAULT NULL COMMENT 'Backup of staff record before deletion',
  PRIMARY KEY (`id`),
  KEY `idx_svcNo` (`svcNo`),
  KEY `idx_deleted_by` (`deleted_by`),
  KEY `idx_deleted_at` (`deleted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Audit log for staff deletions';
