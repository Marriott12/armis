-- ============================================================================
-- ARMIS RBAC Role Model — 2026-09-16
--
-- Target model:
--   admin  = the ONLY System Administrator role
--   ag     = Adjutant General; posted to Admin Branch; read-only consolidated
--            Admin Branch dashboard/reporting access
--   dag    = Deputy Adjutant General; branch-posted; same common personnel
--            permissions as CC/SOI/SOII/SOIII, subject to branch scope
--   cc/soi/soii/soiii = common personnel-management roles, branch-posted
--   user   = basic user
--
-- DG/DDG are removed from the active role model.
-- Legacy branch-as-role codes are retained as INACTIVE compatibility records
-- until all existing staff accounts have been reassigned safely.
--
-- IMPORTANT:
--   This migration does NOT guess how a legacy command/training/operations/
--   q_branch/provost/intelligence account should be reclassified. Those
--   accounts are reported at the end for deliberate reassignment.
--
-- Run against armis1 AFTER backing up the database.
-- ============================================================================

-- 1. Normalize duplicate system-administrator accounts.
UPDATE staff
SET role = 'admin'
WHERE LOWER(TRIM(role)) IN ('superadmin', 'administrator');

-- 2. Keep one canonical administrator role and deactivate duplicate/retired
--    role definitions. We retain the rows for audit/compatibility rather than
--    deleting them while historical accounts may still reference their codes.
UPDATE roles
SET status = 'Inactive', is_system = 0,
    description = 'Retired: consolidated into the canonical admin role.'
WHERE code IN ('superadmin', 'administrator', 'dg', 'ddg');

-- Legacy branch-as-role values are no longer active roles. Branch identity is
-- represented by staff.branch_id + branches.code.
UPDATE roles
SET status = 'Inactive', is_system = 0,
    description = 'Retired legacy branch role. Use cc/soi/soii/soiii + staff.branch_id.'
WHERE code IN ('admin_branch','command','training','operations','q_branch','provost','intelligence');

-- 3. Ensure the canonical role rows exist with the intended semantics.
INSERT INTO roles
    (code, name, level, scope, access, is_branch_assignable, is_system, status, description)
VALUES
    ('admin', 'System Administrator', 100, 'org', 'write', 0, 1, 'Active',
     'Single system administrator role with unrestricted ARMIS access.'),
    ('ag', 'Adjutant General', 95, 'org', 'read', 0, 1, 'Active',
     'Posted to Admin Branch; consolidated Army-wide dashboards and reports; read-only.'),
    ('dag', 'Deputy Adjutant General', 90, 'branch', 'write', 1, 1, 'Active',
     'Posted to a branch; same common personnel-management permissions as CC/SO, subject to branch scope.'),
    ('cc', 'Chief Clerk', 50, 'branch', 'write', 1, 1, 'Active',
     'Posted to a branch; common personnel-management permissions within ARMIS rules.'),
    ('soi', 'Staff Officer I', 48, 'branch', 'write', 1, 1, 'Active',
     'Posted to a branch; common personnel-management permissions within ARMIS rules.'),
    ('soii', 'Staff Officer II', 46, 'branch', 'write', 1, 1, 'Active',
     'Posted to a branch; common personnel-management permissions within ARMIS rules.'),
    ('soiii', 'Staff Officer III', 44, 'branch', 'write', 1, 1, 'Active',
     'Posted to a branch; common personnel-management permissions within ARMIS rules.'),
    ('user', 'Standard User', 10, 'none', 'none', 0, 1, 'Active',
     'Basic authenticated user access.')
ON DUPLICATE KEY UPDATE
    name = VALUES(name),
    level = VALUES(level),
    scope = VALUES(scope),
    access = VALUES(access),
    is_branch_assignable = VALUES(is_branch_assignable),
    is_system = VALUES(is_system),
    status = VALUES(status),
    description = VALUES(description);

-- 4. Recreate the permission table so a previously failed/partial attempt
--    cannot leave an incompatible collation behind. BACK UP armis1 first.
DROP TABLE IF EXISTS role_permissions;

-- The current ARMIS schema uses utf8mb4_0900_ai_ci for roles.code.
CREATE TABLE role_permissions (
    role_code VARCHAR(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
    permission_code VARCHAR(60) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
    PRIMARY KEY (role_code, permission_code),
    CONSTRAINT fk_role_permissions_role
        FOREIGN KEY (role_code) REFERENCES roles(code)
        ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_0900_ai_ci;

-- 5. Replace permission assignments with the approved matrix.
DELETE FROM role_permissions;

-- System Administrator: unrestricted access is also enforced in PHP, but the
-- explicit rows make the permission model inspectable and auditable.
INSERT INTO role_permissions (role_code, permission_code) VALUES
('admin','view_staff'),
('admin','edit_staff'),
('admin','create_staff'),
('admin','delete_staff'),
('admin','promote_staff'),
('admin','assign_medals'),
('admin','create_medal'),
('admin','view_reports'),
('admin','admin_access'),
('admin','admin_branch_access'),
('admin','system_settings'),
('admin','manage_postings'),
('admin','manage_education');

-- AG: read-only Admin Branch consolidated reporting.
INSERT INTO role_permissions (role_code, permission_code) VALUES
('ag','view_staff'),
('ag','view_reports'),
('ag','admin_branch_access');

-- DAG / CC / SOs: common personnel-management permissions. Branch-specific
-- operations are additionally gated by the PHP RBAC branch check.
INSERT INTO role_permissions (role_code, permission_code) VALUES
('dag','view_staff'),('dag','edit_staff'),('dag','create_staff'),
('dag','promote_staff'),('dag','assign_medals'),('dag','create_medal'),
('dag','view_reports'),('dag','admin_branch_access'),
('dag','manage_postings'),('dag','manage_education'),
('cc','view_staff'),('cc','edit_staff'),('cc','create_staff'),
('cc','promote_staff'),('cc','assign_medals'),('cc','create_medal'),
('cc','view_reports'),('cc','admin_branch_access'),
('cc','manage_postings'),('cc','manage_education'),
('soi','view_staff'),('soi','edit_staff'),('soi','create_staff'),
('soi','promote_staff'),('soi','assign_medals'),('soi','create_medal'),
('soi','view_reports'),('soi','admin_branch_access'),
('soi','manage_postings'),('soi','manage_education'),
('soii','view_staff'),('soii','edit_staff'),('soii','create_staff'),
('soii','promote_staff'),('soii','assign_medals'),('soii','create_medal'),
('soii','view_reports'),('soii','admin_branch_access'),
('soii','manage_postings'),('soii','manage_education'),
('soiii','view_staff'),('soiii','edit_staff'),('soiii','create_staff'),
('soiii','promote_staff'),('soiii','assign_medals'),('soiii','create_medal'),
('soiii','view_reports'),('soiii','admin_branch_access'),
('soiii','manage_postings'),('soiii','manage_education');

INSERT INTO role_permissions (role_code, permission_code) VALUES
('user','view_staff');

-- 6. Rebuild role_modules only for active canonical roles. Branch modules are
--    additionally derived from staff.branch_id by shared/rbac.php.
DELETE FROM role_modules
WHERE role_code IN (
    'admin','superadmin','ag','dg','ddg','dag','cc','soi','soii','soiii',
    'admin_branch','command','training','operations','q_branch','provost','intelligence','user'
);

INSERT INTO role_modules (role_code, module_code) VALUES
('admin','admin'),('admin','admin_branch'),('admin','branches'),('admin','command'),
('admin','dashboard'),('admin','finance'),('admin','operations'),('admin','ordinance'),
('admin','training'),('admin','users'),
('ag','admin_branch'),('ag','dashboard'),
('dag','dashboard'),('dag','users'),
('cc','dashboard'),('cc','users'),
('soi','dashboard'),('soi','users'),
('soii','dashboard'),('soii','users'),
('soiii','dashboard'),('soiii','users'),
('user','dashboard'),('user','users');

-- 7. Ensure AG is structurally posted to Admin Branch. Only rows currently
--    identifiable as AG are changed; other roles are untouched.
UPDATE staff s
JOIN branches b ON b.code = 'admin_branch'
SET s.branch_id = b.id
WHERE LOWER(TRIM(s.role)) = 'ag';

-- 8. Diagnostic output: accounts still carrying retired legacy role codes.
SELECT role, COUNT(*) AS account_count
FROM staff
WHERE LOWER(TRIM(role)) IN (
    'dg','ddg','admin_branch','command','training','operations',
    'q_branch','provost','intelligence','superadmin','administrator'
)
GROUP BY role
ORDER BY role;

-- 9. Diagnostic output: branch-posted DAG/CC/SO accounts missing branch_id.
SELECT role, COUNT(*) AS missing_branch_count
FROM staff
WHERE LOWER(TRIM(role)) IN ('dag','cc','soi','soii','soiii')
  AND branch_id IS NULL
GROUP BY role
ORDER BY role;
