-- ============================================================================
-- ARMIS Migration: 2026_08_07_add_branch_alteration_log.sql
-- Run AFTER the previous two migrations.
--
-- NOTE: the existing `audit_trail` table (see
-- database/migrations/add_audit_trail_system.sql) is written to by
-- shared/AuditLogger.php using an INSERT that references a column named
-- `svcNo`, but the table as created only has `staff_id INT`. That mismatch
-- pre-dates this change and is worth fixing separately (see recommendations
-- in the README) — rather than build new functionality on a table with a
-- known column mismatch, this migration adds a small, purpose-built log
-- table for branch-scoped record alterations, with svcNo correctly typed
-- as VARCHAR to match `staff`.`svcNo`.
-- ============================================================================

START TRANSACTION;

CREATE TABLE IF NOT EXISTS `branch_alteration_log` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `svcNo`       VARCHAR(10)  NOT NULL COMMENT 'the staff record that was altered',
  `branch_id`   INT UNSIGNED DEFAULT NULL COMMENT 'branch context the edit happened under',
  `module`      VARCHAR(30)  DEFAULT NULL COMMENT 'e.g. admin_branch, training, finance',
  `edited_by`   VARCHAR(10)  NOT NULL COMMENT 'svcNo of the user who made the change',
  `changes`     TEXT         DEFAULT NULL COMMENT 'JSON: {field: {from, to}}',
  `ip_address`  VARCHAR(45)  DEFAULT NULL,
  `createdAt`   TIMESTAMP    NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_bal_svcNo` (`svcNo`),
  KEY `idx_bal_branch` (`branch_id`),
  KEY `idx_bal_created` (`createdAt`),
  CONSTRAINT `fk_bal_branch` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

COMMIT;
