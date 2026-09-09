-- ============================================================================
-- ARMIS Migration: 2026_08_12_repair_backfill_and_role_fk.sql
--
-- Repairs two things found after the first deploy:
--
-- 1. staff.branch_id backfill didn't take for at least some legacy-role
--    accounts (e.g. svcNo 007414, role='admin_branch', still had
--    branch_id = NULL). Likely cause: migration 2 was run before the
--    `branches` table had rows, so the original backfill UPDATE's JOIN
--    matched nothing. This re-runs it - safe to run again even if some
--    rows were already correct, it only touches rows that still need it.
--
-- 2. `fk_staff_role` (staff.role -> roles.code) never got created. Adding
--    an FK fails outright if even one existing row's value isn't in the
--    target table. Run the diagnostic SELECT below FIRST - if it returns
--    any rows, decide how to handle each one (usually: correct a typo, or
--    set it to 'user') before running the ALTER TABLE further down.
-- ============================================================================

-- ----------------------------------------------------------------------------
-- STEP 1 — re-run the backfill (idempotent, safe to re-run)
-- ----------------------------------------------------------------------------
UPDATE `staff` s
JOIN `branches` b ON b.`code` = s.`role`
SET s.`branch_id` = b.`id`
WHERE s.`role` IN ('admin_branch', 'command', 'training', 'operations')
  AND s.`branch_id` IS NULL;

-- ----------------------------------------------------------------------------
-- STEP 2 — DIAGNOSTIC: run this SELECT first. It lists any staff.role value
-- that does NOT exist in the roles table - these are what's blocking the FK.
-- If this returns zero rows, skip straight to STEP 4.
-- ----------------------------------------------------------------------------
SELECT DISTINCT s.svcNo, s.role
FROM staff s
LEFT JOIN roles r ON r.code = s.role
WHERE r.code IS NULL;

-- ----------------------------------------------------------------------------
-- STEP 3 — if STEP 2 returned rows, this is the safe catch-all: any
-- orphaned role value gets reset to 'user' (the safest possible default -
-- no permissions beyond viewing their own profile). Uncomment and run only
-- after you've reviewed STEP 2's output and are comfortable with this.
-- If you'd rather fix specific accounts individually, do that instead and
-- skip this block.
-- ----------------------------------------------------------------------------
-- UPDATE staff s
-- LEFT JOIN roles r ON r.code = s.role
-- SET s.role = 'user'
-- WHERE r.code IS NULL;

-- ----------------------------------------------------------------------------
-- STEP 4 — add the missing FK. Only run this once STEP 2 returns zero rows.
-- ----------------------------------------------------------------------------
ALTER TABLE `staff`
  ADD CONSTRAINT `fk_staff_role` FOREIGN KEY (`role`) REFERENCES `roles` (`code`)
  ON DELETE RESTRICT ON UPDATE CASCADE;
