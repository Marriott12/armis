-- ============================================================================
-- ARMIS Migration: 2026_08_07_add_staff_branch_scoping.sql
-- Run AFTER 2026_08_07_add_branches_and_roles.sql
-- Backup first — this changes staff.role from ENUM to VARCHAR+FK.
-- ============================================================================

START TRANSACTION;

-- ----------------------------------------------------------------------------
-- staff.branch_id: which ARMIS department this staffer is POSTED TO for
-- admin/RBAC purposes. Deliberately separate from staff.unitId (their
-- military unit posting, e.g. "1 Inf Bde") — different concept entirely.
-- Most rank-and-file soldiers will have branch_id NULL; it's only populated
-- for staff working within a branch (CC/SO/DG/admin roles) or, going
-- forward, any staff whose records a branch needs to track as "theirs".
-- ----------------------------------------------------------------------------
ALTER TABLE `staff`
  ADD COLUMN `branch_id` INT UNSIGNED DEFAULT NULL
    COMMENT 'FK branches.id — admin department this staffer is assigned to'
    AFTER `unitId`;

ALTER TABLE `staff`
  ADD CONSTRAINT `fk_staff_branch` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`)
  ON DELETE SET NULL ON UPDATE CASCADE;

ALTER TABLE `staff` ADD KEY `idx_staff_branch_id` (`branch_id`);

-- ----------------------------------------------------------------------------
-- staff.role: ENUM('user','superadmin','admin_branch','command','training',
-- 'q_branch','provost','intelligence','operations') -> VARCHAR(30) + FK.
-- Every one of those values is already seeded into `roles` in migration 1,
-- so this is a safe, non-destructive type change — no existing row can end
-- up pointing at a role that doesn't exist.
-- ----------------------------------------------------------------------------
ALTER TABLE `staff`
  MODIFY COLUMN `role` VARCHAR(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL DEFAULT 'user';

ALTER TABLE `staff`
  ADD CONSTRAINT `fk_staff_role` FOREIGN KEY (`role`) REFERENCES `roles` (`code`)
  ON DELETE RESTRICT ON UPDATE CASCADE;

-- ----------------------------------------------------------------------------
-- Best-effort backfill: staff already carrying a branch-shaped legacy role
-- get branch_id set to the matching branch so they don't lose access.
-- Review afterwards — this is a starting point, not a final assignment.
-- ----------------------------------------------------------------------------
UPDATE `staff` s
JOIN `branches` b ON b.`code` = s.`role`
SET s.`branch_id` = b.`id`
WHERE s.`role` IN ('admin_branch', 'command', 'training', 'operations');

COMMIT;

-- ============================================================================
-- ROLLBACK (manual, keep for reference):
-- ALTER TABLE `staff` DROP FOREIGN KEY `fk_staff_role`;
-- ALTER TABLE `staff` MODIFY COLUMN `role` ENUM('user','superadmin','admin_branch','command','training','q_branch','provost','intelligence','operations') DEFAULT NULL;
-- ALTER TABLE `staff` DROP FOREIGN KEY `fk_staff_branch`, DROP KEY `idx_staff_branch_id`, DROP COLUMN `branch_id`;
-- ============================================================================
