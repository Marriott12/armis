# ARMIS Phase 1 Security Implementation Review — 16 September 2026

## Scope
Authentication, session security, RBAC authority, row-level branch reach, CSRF on high-value state-changing endpoints, and regression-test scaffolding.

## Implemented
- Removed the anonymous/default administrator session from `shared/session_init.php`.
- Added centralized `shared/session_security.php` using strict session mode, HttpOnly, SameSite=Lax, and Secure when HTTPS is active.
- Enforced `staff.accStatus = 'Active'` in database authentication.
- Regenerated the PHP session identifier after successful credential verification.
- Added `canViewRecord()` / `canViewStaffRecord()` row-level read guards.
- Added `requireModuleWriteAccess()` for module mutations.
- Separated system-administrator authority from granular `admin_access`; `requireAdmin()` now uses the actual administrator roles.
- Added `role_permissions` as the centralized granular action-permission store, preserving the current permission matrix through a migration.
- Added CSRF enforcement to the high-value Admin Branch, Operations, Training, User Profile, password-reset, and CV-management mutation paths touched in this pass.
- Protected the previously unauthenticated Admin Branch duplicate-check and edit-form AJAX endpoints.
- Protected the previously unauthenticated legacy Operations resource-management endpoint.
- Added PHPUnit configuration and 20 security regression tests. The current environment does not contain PHPUnit, so these tests are staged for execution after dev dependencies are installed.
- Added a CLI RBAC audit tool and security/RBAC documentation.

## Important deployment step
Run `database/migrations/2026_09_16_add_role_permissions.sql` against the ARMIS database before relying on centralized granular permissions. Permission lookups intentionally fail closed when the table is unavailable.

## Validation
- PHP syntax validation completed successfully for all project PHP files outside `vendor/` after this pass.
- PHPUnit was not executed because PHPUnit is not installed in the supplied repository/runtime.
- The live database was not modified by this build process; migration execution remains a controlled deployment step.

## Remaining Phase 1 work
The static audit still identifies lower-risk/read-oriented endpoints that need individual review rather than blind CSRF insertion. Examples include search/filter APIs, dashboard data endpoints, and AJAX lookups. The next security pass should inspect these endpoints for authorization, data-scope enforcement, and whether POST is actually necessary.

## Recommended acceptance tests in the live/staging environment
1. Anonymous request to a protected module is redirected/401.
2. Inactive account with correct password is rejected.
3. Suspended account with correct password is rejected.
4. Successful login changes the PHP session ID.
5. Branch writer cannot edit another branch's staff record.
6. Branch writer cannot delete another branch's staff record.
7. Branch writer cannot promote another branch's staff record.
8. Read-only AG/DG roles cannot execute module mutations.
9. A role with `admin_access` alone cannot satisfy `requireAdmin()`.
10. Missing/invalid CSRF token produces HTTP 403 on protected mutations.
11. Valid CSRF token succeeds.
12. Anonymous AJAX access to protected staff lookup endpoints is rejected.
13. Central `role_permissions` data matches the intended permission matrix.
14. No anonymous request creates a default administrator session.
15. `tools/phase1_rbac_audit.php` reports no orphan staff roles and no unexpected branchless branch-assignable active accounts.
