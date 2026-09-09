# ARMIS Branch RBAC — Hotfix 2 (2026-08-12)

## What was found

I checked your updated `armis1.sql` export against what the first patch expected:

| Check | Result |
|---|---|
| `branches`, `roles`, `role_modules`, `branch_alteration_log` tables exist | ✅ Yes — all three migrations were applied |
| Admin Branch seeded with `is_org_wide = 1` | ✅ Yes |
| `staff.branch_id` column + `fk_staff_branch` | ✅ Yes |
| `staff.role` converted to VARCHAR | ✅ Yes |
| `fk_staff_role` constraint (staff.role → roles.code) | ❌ **Missing** |
| `staff.branch_id` backfilled for legacy branch roles | ❌ **Not applied** — e.g. svcNo `007414` (role `admin_branch`) still has `branch_id = NULL` |
| Login sets `$_SESSION['branch_id']` | ❌ **Never did, in the original codebase** — this is the root cause of the fatal error you'd hit next, even after the migrations are fully applied |

The third one is the important one: `shared/rbac.php`'s `canAlterRecord()` and `getSnapshotScope()` both read `$_SESSION['branch_id']`, but nothing in `login.php` ever set it — `authenticateUser()` didn't even select the column. Without this fix, every branch-scoped write would be silently rejected regardless of how correctly the database is set up.

## Files in this hotfix

| File | Change |
|---|---|
| `shared/database_connection.php` | `authenticateUser()` now selects `s.branch_id` |
| `login.php` | Sets `$_SESSION['branch_id'] = $user['branch_id']` on successful login |
| `shared/rbac.php` | Adds `assertBranchMigrationsApplied()` — if `branches`/`roles` don't exist, you now get a clear on-page message instead of a raw PDOException stack trace |
| `database/migrations/2026_08_12_repair_backfill_and_role_fk.sql` | Re-runs the `branch_id` backfill (safe — only touches rows still NULL) and adds the missing `fk_staff_role` constraint |

## Apply in this order

1. Copy `shared/database_connection.php`, `login.php`, and `shared/rbac.php` over your existing files.
2. Run `database/migrations/2026_08_12_repair_backfill_and_role_fk.sql` **step by step**, not all at once:
   - Run STEP 1 (backfill) — safe, just do it.
   - Run STEP 2 (diagnostic SELECT) and look at what it returns.
     - If it returns **zero rows**, skip STEP 3 and run STEP 4.
     - If it returns rows, each one is a `staff.role` value with no matching entry in `roles` — decide per-account whether that's a typo to fix directly, or acceptable to reset to `'user'` via the commented-out STEP 3 block, before running STEP 4.
3. **Log out and log back in** — the branch_id session fix only takes effect on a fresh login; your current session, if any, still won't have it.
4. Confirm: after logging in as svcNo `007414` (or any `admin_branch`-role account), `$_SESSION['branch_id']` should now be `1` (Admin Branch), and the roster/dashboard pages should work.

## Quick way to verify it's actually working

Run this against `armis1` after applying everything:
```sql
SELECT svcNo, role, branch_id FROM staff WHERE role NOT IN ('user');
```
Every branch-assignable role (`cc`, `soi`, `soii`, `soiii`, `dg`, `admin_branch`, `command`, `training`, `operations`, `q_branch`, `provost`, `intelligence`) should now have a non-NULL `branch_id`. `admin`, `superadmin`, and `ag` are fine with `branch_id = NULL` — they don't need one.
