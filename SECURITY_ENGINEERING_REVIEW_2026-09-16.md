# ARMIS Security, Architecture & Engineering Review
Date: 2026-09-16

## Scope
Reviewed the supplied ARMIS repository archive: 269 application PHP files excluding vendor/.git for code-pattern analysis, plus the supplied `armis1.sql`, RBAC/auth/session/CSRF implementations, Composer configuration and README.

## Correct classification
ARMIS is a traditional server-rendered PHP/MySQL monolith for personnel/HR administration. The supplied evidence does **not** support treating it as a data-engineering pipeline platform. The automated data-engineering score should therefore be interpreted as a rubric mismatch rather than evidence that ARMIS needs orchestration, lineage, or ETL tooling.

The real engineering priorities are:
1. authentication and authorization correctness;
2. session and CSRF security;
3. auditability/observability;
4. regression testing and CI;
5. incremental modularization;
6. database migration/backup discipline;
7. production documentation.

## Findings

### Critical / high priority
1. **Development fallback session exists in production-facing code.**
   `admin_branch/includes/auth.php` calls `initializeDefaultSession()` whenever `ARMIS_DEVELOPMENT` is true. Multiple admin_branch entry points explicitly defined that constant as true. This can bypass normal authentication. The supplied patch changes those explicit development flags to false.

2. **Account status was not enforced by `authenticateUser()`.**
   Correct password verification could return an account regardless of `accStatus`. The patch now permits authentication only when `accStatus = 'Active'`.

3. **Session fixation protection was missing at successful login.**
   The patch regenerates the session ID after credentials are verified and before authenticated session state is established.

4. **Session cookie security was not centralized.**
   A new `shared/session_security.php` establishes HttpOnly, SameSite=Lax and HTTPS-sensitive Secure settings for migrated entry points. Existing pages still need incremental migration from direct `session_start()` calls.

5. **CSRF implementation exists, but adoption is incomplete.**
   `shared/csrf.php` is sounder than many local copies, but code analysis found state-changing endpoints without a direct shared-CSRF call. These need endpoint-by-endpoint verification because some have custom CSRF conventions and some are AJAX handlers.

### Medium priority
6. **RBAC has multiple policy layers.**
   `shared/rbac.php` is the canonical module/branch reach implementation, while `shared/permissions.php` still contains a separate role-to-action permission matrix. These should be reconciled into one authoritative policy model.

7. **`admin_branch` remains an active legacy role while its description says it is superseded.**
   The database seeds `admin_branch` as a branch-scoped write role, while `shared/permissions.php` grants it the full legacy permission set. This deserves an explicit administrative decision and regression tests rather than silently changing its privileges.

8. **No structured application logging.**
   There are at least 65 PHP files containing `error_log()` calls. ARMIS already has database activity logging, but application errors lack a consistent correlation ID, level, context and centralized sink.

9. **No automated tests/CI in the supplied repository.**
   Test infrastructure was absent. A PHPUnit/PHPCS/Composer-audit CI scaffold is included in the accompanying engineering package.

10. **Large mixed-responsibility files remain.**
   The supplied assessment reports 36 files >500 LOC and 14 >1000 LOC. The largest files should be split incrementally, with tests accompanying each extraction. Do not attempt a wholesale rewrite.

## CSRF audit inventory
The code-pattern scan found 50 PHP files containing POST input but no direct reference to the shared CSRF functions. This is an inventory, not a claim that all 50 are vulnerable: several endpoints implement local/custom token checks or are partial handlers. They require manual endpoint verification.

Highest-risk candidates to review first:
- admin_branch/delete_staff.php
- admin_branch/promote_staff.php
- admin_branch/ajax_promote_staff.php
- admin_branch/rollback_promotions.php
- admin_branch/create_medal.php
- admin_branch/assign_medal.php
- admin_branch/ajax_file_upload.php
- admin_branch/appointments.php
- admin/users.php
- admin/bulk_assign_credentials.php
- users/settings.php
- users/personal.php
- users/family.php
- operations/mission_edit.php
- operations/resources.php
- operations/deployments.php
- training/assignments.php
- training/sessions.php
- training/records.php
- training/courses.php

## Architecture recommendation
Do not introduce orchestration/data-pipeline tooling merely to satisfy an automated data-engineering rubric.

Use a gradual modular monolith target:

HTTP entry point
→ Authentication/session guard
→ Authorization/RBAC
→ Request validation
→ Application/service layer
→ Repository/database layer
→ View/rendering

Keep the existing URL structure and database initially. Extract one responsibility at a time.

## Testing strategy
Start with security-critical behavior, not 100% coverage:
- authentication/account-status tests;
- return-URL validation;
- first-login/password-change flow;
- branch scope / canAlterRecord;
- getSnapshotScope;
- permission matrix;
- CSRF validation;
- staff validation;
- destructive operation authorization.

The initial package contains 5 pure security-policy tests and CI scaffolding. The next security pass should expand this to the requested 20+ meaningful tests against extracted policy functions and a disposable test database.

## Database/migrations recommendation
Treat `armis1.sql` as a deployment snapshot, not the long-term migration system.

Near-term:
- retain the snapshot for recovery/reference;
- create a clean `database/schema.sql`;
- create ordered migrations for future changes;
- document which migrations have been applied;
- never silently modify production schema from request-time PHP.

## Documentation recommendation
Replace the current hotfix README with:
- Overview
- System architecture
- Security model
- RBAC/branch-scoping
- Database setup
- Environment variables
- Development setup
- Testing
- Deployment
- Backup/restore
- Troubleshooting
- Contributing/change discipline
- Changelog

Move the existing hotfix narrative into CHANGELOG.md.

## Recommended implementation sequence
### Phase 1 — Security gate
- Disable development fallback (patched).
- Enforce account status (patched).
- Regenerate session ID after authentication (patched).
- Standardize session cookies (scaffolded).
- Complete CSRF endpoint audit.
- Reconcile permissions.php with rbac.php.
- Add regression tests for branch scope.

### Phase 2 — Verification gate
- PHPUnit 20+ security/regression tests.
- PHPCS baseline.
- CI on push/PR.
- Composer audit.
- Disposable test database.

### Phase 3 — Observability
- Structured logger.
- Correlation IDs.
- Central exception handler.
- Real health endpoint.
- Security/audit event taxonomy.

### Phase 4 — Maintainability
- Extract StaffValidator.
- Split dashboard service.
- Extract repositories/services from the largest files.
- Keep each refactor behavior-preserving and independently tested.

### Phase 5 — Operations
- Clean schema + migrations.
- Reproducible local environment.
- Backup/restore verification.
- Production deployment documentation.

## Important distinction
The goal is **not** to turn ARMIS into a data-engineering platform. The goal is to make its existing personnel/HR application secure, testable, observable, maintainable and reproducibly deployable.
