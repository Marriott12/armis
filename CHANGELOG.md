# ARMIS Branch RBAC Upgrade — CHANGELOG

Applied against the ARMIS.zip codebase as uploaded. Every file below is a
drop-in replacement/addition at the same path — copy this patch tree over
your project root and it lands in the right place.

## Deploy order

1. **Backup your database.**
2. Run, in order:
   - `database/migrations/2026_08_07_add_branches_and_roles.sql`
   - `database/migrations/2026_08_07_add_staff_branch_scoping.sql`
   - `database/migrations/2026_08_07_add_branch_alteration_log.sql`
3. Copy every PHP file in this patch tree over the matching path in your project.
4. Assign real branches/roles to real staff via **Admin → Role & Branch Assignment** (`admin/users.php`) or **Admin Branch → Staff Management → Edit** (admin-only Role/Branch fields).

## The core design decision

**Reach follows the branch, not the role.** `roles.access` (read vs write) is
fixed per role code. But *how much data* a write applies to comes from
`branches.is_org_wide`: Admin Branch is the only branch flagged this way,
because personnel administration is inherently Army-wide — its existing
dashboard (`admin_branch/index.php`) already proves this, since it's never
been filtered by branch. A Chief Clerk posted to Admin Branch can alter any
staff record; a Chief Clerk posted to Training can only alter Training's own
people. Same role, different reach, resolved at runtime from `staff.branch_id`
→ `branches.is_org_wide` (see `canAlterRecord()`/`getSnapshotScope()` in
`shared/rbac.php`).

AG is the one exception: it's fixed at org-scope by the role itself
(`roles.scope = 'org'`), since the Adjutant General isn't posted to a branch
at all — they oversee all of them.

Uniformed vs Non-uniformed (Officers + NCOs vs Civilian Employees) reuses
the classification **already built and working** in
`admin_branch/includes/dashboard_service.php` (`DashboardService`, driven by
`rank.rankIndex`) rather than adding a redundant column.

## New files

| File | Purpose |
|---|---|
| `database/migrations/2026_08_07_add_branches_and_roles.sql` | `branches`, `roles`, `role_modules` tables + seed data (new roles + every legacy `staff.role` value, so the FK conversion in the next migration can't orphan an existing account) |
| `database/migrations/2026_08_07_add_staff_branch_scoping.sql` | `staff.branch_id` + FK; converts `staff.role` from ENUM to VARCHAR+FK; best-effort backfill of `branch_id` for staff already on a branch-shaped legacy role |
| `database/migrations/2026_08_07_add_branch_alteration_log.sql` | New `branch_alteration_log` table for auditing record alterations (see "Known issue" below on why this isn't `audit_trail`) |
| `admin/branches.php` + `admin/branches_ajax.php` | Dynamic branch management UI — create a new branch with zero code changes |
| `shared/branch_roster.php` | Reusable roster component: DG gets read-only, CC/SOI/SOII/SOIII get an editable roster with a status-change modal |
| `shared/branch_roster_save.php` | AJAX save endpoint backing that modal, enforces `canAlterRecord()` server-side |
| `command/roster.php`, `operations/roster.php`, `training/roster.php`, `finance/roster.php`, `ordinance/roster.php` | Five-line wrappers that set the branch code and delegate to `shared/branch_roster.php`. Finance and Ordinance previously had no personnel-facing screen at all — this is their first one. |

## Amended files

| File | What changed and why |
|---|---|
| `shared/rbac.php` | Full rewrite. Same public function names as before (`hasModuleAccess`, `requireModuleAccess`, `getUserModules`, `hasMinimumLevel`, `getFilteredSidebarNavigation`, `getRoleDashboardUrl`, `redirectToRoleDashboard`, `getRoleInfo`, `logAccess`) so no other file needed to change its calling convention. Adds `getUserBranch()`, `canAlterRecord()`, `canAlterStaffRecord()`, `getSnapshotScope()`, `getAllBranches()`, `getAllRoles()`, `createBranch()`/`updateBranch()`/`setBranchStatus()`, `requireBranchAdmin()`. Still defines the `ARMIS_ROLES` constant (now built from the DB) for backward compatibility with anything reading it directly. |
| `shared/rbac_compat.php` | **Bug fix.** Used to define its own, separately-maintained copy of `ARMIS_ROLES` and the same functions as `rbac.php`. Since `shared/header.php` includes this file directly, any page that reached `header.php` without first requiring `rbac.php` was silently running on a stale, out-of-sync permission model. Reduced to a single `require_once rbac.php` — there is now exactly one implementation. |
| `shared/permissions.php` | **Bug fix (the important one).** Used to define its own `hasModuleAccess()`/`requireModuleAccess()`, unconditionally, with no `function_exists()` guard. Because `admin_branch/includes/auth.php` requires this file *before* `rbac.php`, these copies were winning silently across **every file in `admin_branch/`** — meaning admin_branch never actually consulted `rbac.php`'s module-access logic at all, before this fix. Removed both duplicate functions; this file now `require_once`s `rbac.php` so its canonical versions are guaranteed available regardless of include order elsewhere. Extended the `hasPermission()` role-permission map with `cc`/`soi`/`soii`/`soiii`/`dg`/`ag`. |
| `admin_branch/includes/auth.php` | **Bug fix.** `isAdmin()` checked for the literal role string `'administrator'`, which has never been a value in `staff.role` (real values are `admin`, `superadmin`, `admin_branch`, etc.) — so it was always `false` in production. Fixed to check the roles actually seeded as system administrators. Also fixed the dev-mode `initializeDefaultSession()` to use `role = 'admin'` instead of the same non-existent `'administrator'`. |
| `admin_branch/partials/edit_staff_step2_service.php` | Role was a free-text `<input>` — any typo became a real value in `staff.role`. Replaced with a Role dropdown (from the `roles` table) and a new Branch dropdown, both **admin-only** (disabled/read-only for CC/SO/DG editing a colleague, since role/branch reassignment is privilege-relevant). |
| `admin_branch/edit_staff.php` | Added the branch-scoping write guard (`canAlterRecord($originalStaff->branch_id)`) right before any update proceeds. Added `role`/`branch_id` to the persisted field map, but only when `isAdmin()` is true **server-side** — the disabled form fields are a UX nicety, not the actual boundary, since a disabled `<input>` can still be forged in a raw POST. |
| `admin_branch/delete_staff.php` | Same write guard before a delete proceeds. |
| `admin_branch/promote_staff.php`, `admin_branch/ajax_promote_staff.php` | Same write guard, applied per-record inside the batch-promotion loop (a mixed-branch selection now gets partially applied with a clear error per skipped record, rather than either allowing or blocking the whole batch opaquely). |
| `admin_branch/assign_medal.php` | Same write guard, applied per-record in the staff-resolution loop before a medal can be queued for award. |
| `admin_branch/index.php` | Added `$__armisReadOnly` (true for AG/DG) and used it to hide write-action sidebar links (Create/Delete/Promote/Postings/Medals) for read-only viewers. This mirrors, not replaces, the real enforcement — those write endpoints already reject the action server-side regardless of what the sidebar shows. |
| `admin/users.php` | **Bug fix + repurposed.** This file queried a `users` table that does not exist anywhere in the ARMIS schema (`SELECT ... FROM users`, `UPDATE users SET ...`) — login credentials, role, and account status all live on `staff`. Every query here would have thrown a fatal PDO exception. Rewritten end-to-end against `staff`, and now doubles as the canonical Role & Branch assignment screen. |
| `admin/index.php`, `admin/settings.php`, `admin/database.php`, `admin/security.php`, `admin/reports.php` | Added a "Manage Branches" sidebar entry. |

## Known issues found but intentionally *not* fixed here (out of scope for this feature)

- **`shared/AuditLogger.php` / `audit_trail` table column mismatch.** `AuditLogger::log()` inserts into a column named `svcNo`, but the `audit_trail` table (per `database/migrations/add_audit_trail_system.sql`) only defines `staff_id INT`. This looks like a pre-existing bug unrelated to branch scoping. Rather than build new alteration-logging on a table with a known mismatch, this upgrade uses a new, correctly-typed `branch_alteration_log` table instead. Worth fixing `audit_trail` separately.
- **`admin_branch/create_staff.php`** doesn't currently collect a role or branch at creation time (new accounts default to `role = 'user'`, `branch_id = NULL`, both safe defaults). Assign a role/branch afterward via `admin/users.php` or `admin_branch/edit_staff.php`.
- **`admin/index.php`** has AJAX handlers (`assign_role`, `get_user_list`) that call `handleRoleAssignment()`/`getUserList()` — functions that don't exist anywhere in the codebase (would fatal if ever invoked). Separately, three of its dashboard-stat queries reference the same phantom `users` table as the old `admin/users.php`, but those are already defensively wrapped in `if (in_array('users', $tables))` checks against `SHOW TABLES`, so they no-op safely rather than fatal. Worth a follow-up pass, but unrelated to branch scoping.

## Recommendations for further enhancement

1. **Fix `audit_trail`/`AuditLogger`** (see above) so all record-level auditing — promotions, medals, edits, branch alterations — lives in one table instead of two.
2. **Give Create Staff a Role/Branch step**, gated to `isAdmin()`, matching the pattern now used in `edit_staff_step2_service.php`.
3. **Extend `getFilteredSidebarNavigation()` into actual use.** It's fully rewritten and DB-driven now, but no page currently calls it — every module still hand-builds its own `$sidebarLinks` array. Migrating to the shared function would mean a new branch appears in every relevant sidebar automatically, with no per-module edit.
4. **Repair `admin/index.php`'s dead AJAX actions** (`assign_role`, `get_user_list`) — they've likely never worked in production.
5. **Consider a lightweight approval flow for cross-branch reassignment** — right now a System Administrator moves a CC/SO between branches instantly. For a real deployment, an audit trail entry (which `branch_alteration_log` doesn't currently cover, since it's staff-record-scoped, not role-assignment-scoped) or a two-person sign-off might be warranted given how much authority a branch reassignment grants.
