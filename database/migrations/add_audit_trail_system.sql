-- ============================================================================
-- Audit Trail System for Staff Promotions
-- Created: October 7, 2025
-- Purpose: Enhanced audit logging and rollback capability
-- ============================================================================

-- Add audit columns to staff_promotions table
ALTER TABLE `staff_promotions` 
ADD COLUMN `created_by` INT(11) NULL DEFAULT NULL COMMENT 'User ID who created the promotion' AFTER `remarks`,
ADD COLUMN `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'When the promotion was created' AFTER `created_by`,
ADD COLUMN `updated_by` INT(11) NULL DEFAULT NULL COMMENT 'User ID who last updated' AFTER `created_at`,
ADD COLUMN `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'When last updated' AFTER `updated_by`,
ADD COLUMN `approved_by` INT(11) NULL DEFAULT NULL COMMENT 'User ID who approved (if applicable)' AFTER `updated_at`,
ADD COLUMN `approved_at` TIMESTAMP NULL DEFAULT NULL COMMENT 'When approved' AFTER `approved_by`,
ADD COLUMN `rejected_by` INT(11) NULL DEFAULT NULL COMMENT 'User ID who rejected (if applicable)' AFTER `approved_at`,
ADD COLUMN `rejected_at` TIMESTAMP NULL DEFAULT NULL COMMENT 'When rejected' AFTER `rejected_by`,
ADD COLUMN `rejection_reason` TEXT NULL DEFAULT NULL COMMENT 'Reason for rejection' AFTER `rejected_at`,
ADD COLUMN `status` ENUM('pending', 'approved', 'rejected', 'completed', 'cancelled') DEFAULT 'completed' COMMENT 'Promotion status' AFTER `rejection_reason`,
ADD COLUMN `can_rollback` TINYINT(1) DEFAULT 1 COMMENT 'Whether this promotion can be rolled back' AFTER `status`,
ADD COLUMN `rolled_back_by` INT(11) NULL DEFAULT NULL COMMENT 'User ID who rolled back' AFTER `can_rollback`,
ADD COLUMN `rolled_back_at` TIMESTAMP NULL DEFAULT NULL COMMENT 'When rolled back' AFTER `rolled_back_by`,
ADD INDEX `idx_created_by` (`created_by`),
ADD INDEX `idx_created_at` (`created_at`),
ADD INDEX `idx_status` (`status`),
ADD INDEX `idx_approved_by` (`approved_by`);

-- Create audit trail table for complete action history
CREATE TABLE IF NOT EXISTS `audit_trail` (
  `id` INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `action_type` VARCHAR(50) NOT NULL COMMENT 'Type of action (promotion, reversion, rollback, update)',
  `table_name` VARCHAR(100) NOT NULL COMMENT 'Table affected (staff, staff_promotions, etc.)',
  `record_id` INT(11) NOT NULL COMMENT 'ID of the record affected',
  `staff_id` INT(11) NULL DEFAULT NULL COMMENT 'Staff member affected',
  `user_id` INT(11) NOT NULL COMMENT 'User who performed the action',
  `user_name` VARCHAR(255) NULL DEFAULT NULL COMMENT 'Username at time of action',
  `ip_address` VARCHAR(45) NULL DEFAULT NULL COMMENT 'IP address of user',
  `user_agent` TEXT NULL DEFAULT NULL COMMENT 'Browser/device information',
  `before_value` TEXT NULL DEFAULT NULL COMMENT 'Value before change (JSON)',
  `after_value` TEXT NULL DEFAULT NULL COMMENT 'Value after change (JSON)',
  `description` TEXT NULL DEFAULT NULL COMMENT 'Human-readable description',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'When action occurred',
  INDEX `idx_action_type` (`action_type`),
  INDEX `idx_table_name` (`table_name`),
  INDEX `idx_record_id` (`record_id`),
  INDEX `idx_staff_id` (`staff_id`),
  INDEX `idx_user_id` (`user_id`),
  INDEX `idx_created_at` (`created_at`),
  INDEX `idx_composite_staff_action` (`staff_id`, `action_type`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Complete audit trail for all staff promotion actions';

-- Create promotion history table for storing complete snapshots
CREATE TABLE IF NOT EXISTS `staff_promotion_history` (
  `id` INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `promotion_id` INT(11) NOT NULL COMMENT 'Reference to staff_promotions.id',
  `staff_id` INT(11) NOT NULL COMMENT 'Staff member ID',
  `action` VARCHAR(50) NOT NULL COMMENT 'Action type (created, updated, approved, rejected, rolled_back)',
  `snapshot` TEXT NOT NULL COMMENT 'Complete promotion record snapshot (JSON)',
  `user_id` INT(11) NOT NULL COMMENT 'User who performed the action',
  `timestamp` TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'When action occurred',
  INDEX `idx_promotion_id` (`promotion_id`),
  INDEX `idx_staff_id` (`staff_id`),
  INDEX `idx_action` (`action`),
  INDEX `idx_timestamp` (`timestamp`),
  FOREIGN KEY (`promotion_id`) REFERENCES `staff_promotions`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Historical snapshots of all promotion changes';

-- Create notification preferences table
CREATE TABLE IF NOT EXISTS `notification_preferences` (
  `id` INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT(11) NOT NULL COMMENT 'User ID',
  `staff_id` INT(11) NULL DEFAULT NULL COMMENT 'Staff ID (if staff member has preferences)',
  `email_promotions` TINYINT(1) DEFAULT 1 COMMENT 'Email on promotions',
  `email_reversions` TINYINT(1) DEFAULT 1 COMMENT 'Email on reversions',
  `email_approvals` TINYINT(1) DEFAULT 1 COMMENT 'Email on approvals needed',
  `email_frequency` ENUM('immediate', 'daily', 'weekly', 'never') DEFAULT 'immediate',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY `unique_user` (`user_id`),
  INDEX `idx_staff_id` (`staff_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='User notification preferences';

-- Create notification queue table
CREATE TABLE IF NOT EXISTS `notification_queue` (
  `id` INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `type` VARCHAR(50) NOT NULL COMMENT 'Notification type (promotion, reversion, approval, etc.)',
  `recipient_email` VARCHAR(255) NOT NULL COMMENT 'Email address',
  `recipient_name` VARCHAR(255) NULL DEFAULT NULL COMMENT 'Recipient name',
  `subject` VARCHAR(255) NOT NULL COMMENT 'Email subject',
  `message` TEXT NOT NULL COMMENT 'Email body (HTML)',
  `reference_id` INT(11) NULL DEFAULT NULL COMMENT 'Related record ID',
  `reference_type` VARCHAR(50) NULL DEFAULT NULL COMMENT 'Related record type',
  `status` ENUM('pending', 'sent', 'failed') DEFAULT 'pending',
  `attempts` INT(11) DEFAULT 0 COMMENT 'Number of send attempts',
  `last_attempt` TIMESTAMP NULL DEFAULT NULL,
  `sent_at` TIMESTAMP NULL DEFAULT NULL,
  `error_message` TEXT NULL DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_status` (`status`),
  INDEX `idx_type` (`type`),
  INDEX `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Email notification queue';

-- Add database indexes for better performance (matching recommendations)
CREATE INDEX `idx_staff_rank` ON `staff`(`rank_id`) COMMENT 'Speed up rank-based queries';
CREATE INDEX `idx_staff_service_number` ON `staff`(`service_number`) COMMENT 'Speed up service number lookups';
CREATE INDEX `idx_staff_unit` ON `staff`(`unit_id`) COMMENT 'Speed up unit-based queries';
CREATE INDEX `idx_staff_dates` ON `staff`(`subWef`, `tempWef`) COMMENT 'Speed up date calculations';
CREATE INDEX `idx_staff_composite` ON `staff`(`rank_id`, `svcStatus`) COMMENT 'Speed up active staff by rank queries';

CREATE INDEX `idx_promotions_staff` ON `staff_promotions`(`staff_id`) COMMENT 'Speed up staff promotion history lookups';
CREATE INDEX `idx_promotions_date_to` ON `staff_promotions`(`date_to`) COMMENT 'Speed up last promotion queries';
CREATE INDEX `idx_promotions_ranks` ON `staff_promotions`(`rank_from`, `rank_to`) COMMENT 'Speed up rank transition queries';
CREATE INDEX `idx_promotions_type` ON `staff_promotions`(`type`) COMMENT 'Speed up promotion type filtering';
CREATE INDEX `idx_promotions_composite` ON `staff_promotions`(`staff_id`, `date_to`, `type`) COMMENT 'Optimize complex promotion queries';

CREATE INDEX `idx_ranks_category` ON `ranks`(`category`) COMMENT 'Speed up category-based rank queries';
CREATE INDEX `idx_ranks_level` ON `ranks`(`level`) COMMENT 'Speed up level-based rank queries';

-- ============================================================================
-- Rollback Scripts (if needed to revert changes)
-- ============================================================================

-- To remove audit columns from staff_promotions:
/*
ALTER TABLE `staff_promotions`
DROP COLUMN `created_by`,
DROP COLUMN `created_at`,
DROP COLUMN `updated_by`,
DROP COLUMN `updated_at`,
DROP COLUMN `approved_by`,
DROP COLUMN `approved_at`,
DROP COLUMN `rejected_by`,
DROP COLUMN `rejected_at`,
DROP COLUMN `rejection_reason`,
DROP COLUMN `status`,
DROP COLUMN `can_rollback`,
DROP COLUMN `rolled_back_by`,
DROP COLUMN `rolled_back_at`,
DROP INDEX `idx_created_by`,
DROP INDEX `idx_created_at`,
DROP INDEX `idx_status`,
DROP INDEX `idx_approved_by`;
*/

-- To remove new tables:
/*
DROP TABLE IF EXISTS `notification_queue`;
DROP TABLE IF EXISTS `notification_preferences`;
DROP TABLE IF EXISTS `staff_promotion_history`;
DROP TABLE IF EXISTS `audit_trail`;
*/

-- ============================================================================
-- Sample Queries for Testing
-- ============================================================================

-- View recent audit trail entries
-- SELECT * FROM audit_trail ORDER BY created_at DESC LIMIT 20;

-- View promotions that can be rolled back (within 24 hours)
-- SELECT sp.*, s.service_number, s.first_name, s.last_name
-- FROM staff_promotions sp
-- JOIN staff s ON sp.staff_id = s.id
-- WHERE sp.can_rollback = 1 
-- AND sp.created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
-- ORDER BY sp.created_at DESC;

-- View promotion history for a specific staff member
-- SELECT * FROM staff_promotion_history 
-- WHERE staff_id = ? 
-- ORDER BY timestamp DESC;

-- ============================================================================
-- End of Migration
-- ============================================================================
