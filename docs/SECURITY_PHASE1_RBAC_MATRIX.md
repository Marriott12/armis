# ARMIS Phase 1 — Authentication, RBAC, Session and CSRF Baseline

## Policy authorities

ARMIS now has two intentionally separate policy layers:

1. **Module access:** `roles` + `role_modules`, enforced by `shared/rbac.php`.
2. **Action permissions:** `role_permissions`, enforced by `shared/permissions.php`.
3. **Row reach:** `staff.branch_id` + the role/branch scope, enforced by `canViewRecord()` / `canAlterRecord()`.

The old hardcoded action-permission matrix in `shared/permissions.php` has been replaced by the database-backed `role_permissions` migration.

## System administrator definition

System-administrator authority is determined by the role identity (`admin`, `administrator`, `superadmin`) through `isAdmin()`. A granular permission such as `admin_access` is not sufficient to become a system administrator.

## Branch rules

- `admin`, `administrator`, `superadmin`: organization-wide.
- `ag`: organization-wide read-only.
- `dg`: assigned-branch read-only.
- `cc`, `soi`, `soii`, `soiii`: assigned-branch write access.
- Users assigned to an organization-wide branch inherit the organization-wide reach defined by the RBAC model.

## Authentication rules

- Account must exist.
- Password must verify.
- `accStatus` must be `Active`.
- Successful authentication regenerates the PHP session ID.
- First-login accounts are routed to mandatory temporary-password change.
- Anonymous requests never receive a fabricated administrator session.

## CSRF rules

State-changing requests should use `shared/csrf.php` and `require_csrf()`. Existing legacy tokens remain accepted by the shared validator for compatibility while endpoints are migrated.

## Deployment requirement

Before enabling the centralized action-permission checks in an environment, run:

`database/migrations/2026_09_16_add_role_permissions.sql`

The permission lookup fails closed if the migration is missing.
