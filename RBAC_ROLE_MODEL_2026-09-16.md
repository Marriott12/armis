# ARMIS RBAC Role Model — 2026-09-16

## Approved role model

### System Administrator
- Canonical role code: `admin`
- Single system administrator role.
- Full system access across every branch and module.
- Only role permitted to delete staff.
- `superadmin` and `administrator` are consolidated into `admin`.

### Adjutant General (AG)
- Role code: `ag`
- Posted to **Admin Branch** only.
- Landing page: `/Armis2/admin_branch/index.php`.
- Admin Branch dashboard provides consolidated Army-wide reporting.
- Read-only oversight: dashboards/reports and permitted staff viewing.
- AG is not assigned to multiple branches.

### Deputy Adjutant General (DAG)
- Role code: `dag`
- Branch-posted.
- Landing page follows `staff.branch_id`.
- Receives the same common personnel-management permissions as CC/SOI/SOII/SOIII.
- Specialized Operations/Training functions remain branch-gated.

### CC / SOI / SOII / SOIII
- Branch-posted roles.
- Each user lands on the dashboard for their posted branch.
- Common personnel-management permissions:
  - view staff
  - edit staff details
  - create/add staff
  - assign medals
  - create/add medals
  - manage promotions
  - view reports
- No staff deletion permission.
- Branch-specific permissions are additionally constrained by `staff.branch_id`.

## Branch-specific rules

- **Operations:** posting creation/editing is restricted to authorized users posted to Operations.
- **Training:** education-detail entry/editing is restricted to authorized users posted to Training.
- **Admin Branch:** personnel administration is Army-wide because Admin Branch is marked `is_org_wide=1`.

## Retired role codes

The migration marks these role codes inactive rather than deleting them immediately, to avoid silently orphaning existing accounts:

- `superadmin`
- `dg`
- `ddg`
- `administrator` (if present)
- `admin_branch`
- `command`
- `training`
- `operations`
- `q_branch`
- `provost`
- `intelligence`

Existing `superadmin`/`administrator` staff accounts are safely normalized to `admin`. Other legacy-role accounts are reported by the migration for deliberate reassignment; the migration does not guess their new role.

## Permission table

`role_permissions.role_code` uses `utf8mb4_0900_ai_ci`, matching the current `roles.code` definition. The migration recreates `role_permissions` to eliminate the collation incompatibility that caused MySQL error #3780.

## Deployment

1. Back up `armis1`.
2. Deploy the repaired RBAC package.
3. Run `database/migrations/2026_09_16_rbac_role_model.sql` in phpMyAdmin against `armis1`.
4. Review the two diagnostic result sets at the end of the migration.
5. Reassign any remaining legacy-role accounts deliberately.
6. Test authentication and landing-page routing.
7. Continue with the Admin Branch module audit before changing other branches.
