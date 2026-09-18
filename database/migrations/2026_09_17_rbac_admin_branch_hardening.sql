-- ARMIS 2026-09-17 canonical RBAC model + Admin Branch hardening
-- Run after 2026_08_07_add_branches_and_roles.sql and 2026_09_16_add_role_permissions.sql.
-- Backup armis1 first.

START TRANSACTION;

-- Canonical system administrator. Safe normalization for duplicate administrator names.
UPDATE staff SET role='admin' WHERE LOWER(role) IN ('administrator','superadmin');

-- Canonical roles. Branch identity is staff.branch_id, not a role code.
INSERT INTO roles (code,name,level,scope,access,is_branch_assignable,is_system,status,description) VALUES
('admin','System Administrator',100,'org','write',0,1,'Active','Full unrestricted ARMIS access across all branches'),
('ag','Adjutant General',95,'org','read',0,1,'Active','Whole-Army consolidated reporting through Admin Branch only'),
('dag','Deputy Adjutant General',90,'branch','write',1,1,'Active','Branch-posted personnel management; no staff deletion'),
('dg','Director General',85,'branch','read',1,1,'Active','Branch-posted read-only dashboard and statistics'),
('ddg','Deputy Director General',80,'branch','write',1,1,'Active','Branch-posted personnel management; no staff deletion'),
('cc','Chief Clerk',50,'branch','write',1,1,'Active','Branch-posted personnel management; no staff deletion'),
('soi','Staff Officer I',48,'branch','write',1,1,'Active','Branch-posted personnel management; no staff deletion'),
('soii','Staff Officer II',46,'branch','write',1,1,'Active','Branch-posted personnel management; no staff deletion'),
('soiii','Staff Officer III',44,'branch','write',1,1,'Active','Branch-posted personnel management; no staff deletion'),
('user','Standard User',10,'none','none',0,1,'Active','Basic authenticated user')
ON DUPLICATE KEY UPDATE name=VALUES(name), level=VALUES(level), scope=VALUES(scope), access=VALUES(access), is_branch_assignable=VALUES(is_branch_assignable), is_system=VALUES(is_system), status='Active', description=VALUES(description);

-- Retire branch-as-role and duplicate administrator role codes. Existing staff on
-- these legacy roles are NOT reassigned automatically (except administrator/superadmin above).
UPDATE roles SET status='Inactive', access='none', is_branch_assignable=0
WHERE code IN ('administrator','superadmin','admin_branch','command','training','operations','q_branch','provost','intelligence');

DELETE FROM role_permissions WHERE role_code IN ('admin','ag','dag','dg','ddg','cc','soi','soii','soiii','user');
INSERT INTO role_permissions(role_code,permission_code) VALUES
('admin','view_staff'),('admin','edit_staff'),('admin','create_staff'),('admin','delete_staff'),('admin','promote_staff'),('admin','manage_appointments'),('admin','assign_medals'),('admin','create_medal'),('admin','view_reports'),('admin','admin_access'),('admin','admin_branch_access'),('admin','system_settings'),('admin','manage_postings'),('admin','manage_education'),('admin','view_dashboard'),('admin','view_all_modules'),('admin','manage_roles'),('admin','manage_permissions'),('admin','manage_users'),('admin','manage_system_settings'),
('ag','view_staff'),('ag','view_reports'),('ag','admin_branch_access'),('ag','view_dashboard'),
('dag','view_staff'),('dag','edit_staff'),('dag','create_staff'),('dag','assign_medals'),('dag','create_medal'),('dag','promote_staff'),('dag','view_reports'),('dag','admin_branch_access'),('dag','manage_postings'),('dag','manage_education'),('dag','manage_appointments'),('dag','view_dashboard'),
('dg','view_staff'),('dg','view_reports'),('dg','admin_branch_access'),('dg','view_dashboard'),
('ddg','view_staff'),('ddg','edit_staff'),('ddg','create_staff'),('ddg','assign_medals'),('ddg','create_medal'),('ddg','promote_staff'),('ddg','view_reports'),('ddg','admin_branch_access'),('ddg','manage_postings'),('ddg','manage_education'),('ddg','manage_appointments'),('ddg','view_dashboard'),
('cc','view_staff'),('cc','edit_staff'),('cc','create_staff'),('cc','assign_medals'),('cc','create_medal'),('cc','promote_staff'),('cc','view_reports'),('cc','admin_branch_access'),('cc','manage_postings'),('cc','manage_education'),('cc','manage_appointments'),('cc','view_dashboard'),
('soi','view_staff'),('soi','edit_staff'),('soi','create_staff'),('soi','assign_medals'),('soi','create_medal'),('soi','promote_staff'),('soi','view_reports'),('soi','admin_branch_access'),('soi','manage_postings'),('soi','manage_education'),('soi','manage_appointments'),('soi','view_dashboard'),
('soii','view_staff'),('soii','edit_staff'),('soii','create_staff'),('soii','assign_medals'),('soii','create_medal'),('soii','promote_staff'),('soii','view_reports'),('soii','admin_branch_access'),('soii','manage_postings'),('soii','manage_education'),('soii','manage_appointments'),('soii','view_dashboard'),
('soiii','view_staff'),('soiii','edit_staff'),('soiii','create_staff'),('soiii','assign_medals'),('soiii','create_medal'),('soiii','promote_staff'),('soiii','view_reports'),('soiii','admin_branch_access'),('soiii','manage_postings'),('soiii','manage_education'),('soiii','manage_appointments'),('soiii','view_dashboard'),
('user','view_dashboard');

-- AG can use only Admin Branch/dashboard UI. Other branch UI is deliberately absent.
DELETE FROM role_modules WHERE role_code IN ('admin','ag','dag','dg','ddg','cc','soi','soii','soiii');
INSERT INTO role_modules(role_code,module_code) VALUES
('admin','dashboard'),('admin','admin'),('admin','users'),('admin','branches'),('admin','admin_branch'),('admin','command'),('admin','operations'),('admin','training'),('admin','finance'),('admin','ordinance'),
('ag','dashboard'),('ag','admin_branch'),
('dag','dashboard'),('dag','users'),('dg','dashboard'),('dg','users'),('ddg','dashboard'),('ddg','users'),
('cc','dashboard'),('cc','users'),('soi','dashboard'),('soi','users'),('soii','dashboard'),('soii','users'),('soiii','dashboard'),('soiii','users')
ON DUPLICATE KEY UPDATE module_code=VALUES(module_code);

-- Audit query after migration:
-- 1) SELECT role, COUNT(*) FROM staff GROUP BY role ORDER BY role;
-- 2) SELECT id,code,name,status,is_branch_assignable FROM roles ORDER BY level DESC;
-- 3) SELECT role_code,permission_code FROM role_permissions ORDER BY role_code,permission_code;
-- 4) SELECT svcNo,username,role,branch_id FROM staff WHERE role IN ('dag','dg','ddg','cc','soi','soii','soiii','ag') AND branch_id IS NULL;

COMMIT;
