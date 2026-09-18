# ARMIS Admin Branch — Comprehensive RBAC Hardening

Date: 2026-09-17

This package contains the complete ARMIS source archive with the Phase 1 authentication/RBAC/session/CSRF repairs overlaid and the Admin Branch application-layer hardening applied.

## Role model
- `admin`: System Administrator; only unrestricted role; only role allowed to delete staff.
- `ag`: Adjutant General; Admin Branch consolidated whole-Army reporting; read-only; no direct branch-module access.
- `dag`: Deputy Adjutant General; branch-posted; common CC/SO personnel-management permissions; no staff deletion.
- `dg`: Director General; branch-posted; read-only branch dashboard/statistics.
- `ddg`: Deputy Director General; branch-posted; common CC/SO personnel-management permissions; no staff deletion.
- `cc`, `soi`, `soii`, `soiii`: branch-posted; common personnel-management permissions; no staff deletion.
- `user`: basic authenticated access.

Branch identity is stored in `staff.branch_id`; do not encode branch names into role codes.

## Important migration
Run:
`database/migrations/2026_09_17_rbac_admin_branch_hardening.sql`

Do not auto-assign branch roles to the 1,569 currently unassigned personnel. Branch posting is intentionally deferred for manual assignment.

## Admin Branch hardening
- No synthetic/default admin session is created in development mode.
- `isAdmin()` recognizes only `admin`.
- Admin Branch endpoints enforce authentication, module access and granular action permissions.
- AJAX/report APIs require authentication and appropriate permissions.
- Appointment writes require CSRF validation and `manage_appointments`.
- Honors/medal management requires `create_medal` and write access.
- Cache clearing requires `system_settings` and POST+CSRF.
- Staff deletion is server-side restricted to `admin`.
- Dashboard does not display fabricated fallback statistics when the database fails.
- Specialized Operations/Training permissions are branch-gated through the centralized permission layer.

## Deployment
1. Back up the current ARMIS directory and `armis1` database.
2. Extract this package to a staging copy first.
3. Run the RBAC migration against `armis1`.
4. Verify role/permission queries in the migration comments.
5. Copy the package into the WAMP Apache document root only after staging validation.
6. Test login, logout, Admin Branch dashboard, view staff, create/edit staff, medals, promotions, appointments and reports with representative roles.

## Branch assignments
Do not assign branch IDs automatically from units/provinces. Existing data does not provide a reliable one-to-one mapping. Assign `staff.branch_id` deliberately later.
