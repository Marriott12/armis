-- ARMIS Phase 1 RBAC: centralize granular action permissions.
-- The existing roles/role_modules tables remain the authority for module access;
-- this table is the single authority for action-level permissions.
CREATE TABLE IF NOT EXISTS role_permissions (
    role_code VARCHAR(30) NOT NULL,
    permission_code VARCHAR(60) NOT NULL,
    PRIMARY KEY (role_code, permission_code),
    CONSTRAINT fk_role_permissions_role
        FOREIGN KEY (role_code) REFERENCES roles(code)
        ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO role_permissions (role_code, permission_code) VALUES
('admin','view_staff'),('admin','edit_staff'),('admin','create_staff'),('admin','delete_staff'),('admin','promote_staff'),('admin','manage_appointments'),('admin','assign_medals'),('admin','view_reports'),('admin','admin_access'),('admin','admin_branch_access'),('admin','system_settings'),
('superadmin','view_staff'),('superadmin','edit_staff'),('superadmin','create_staff'),('superadmin','delete_staff'),('superadmin','promote_staff'),('superadmin','manage_appointments'),('superadmin','assign_medals'),('superadmin','view_reports'),('superadmin','admin_access'),('superadmin','admin_branch_access'),('superadmin','system_settings'),
('administrator','view_staff'),('administrator','edit_staff'),('administrator','create_staff'),('administrator','delete_staff'),('administrator','promote_staff'),('administrator','manage_appointments'),('administrator','assign_medals'),('administrator','view_reports'),('administrator','admin_access'),('administrator','admin_branch_access'),('administrator','system_settings'),
('admin_branch','view_staff'),('admin_branch','edit_staff'),('admin_branch','create_staff'),('admin_branch','delete_staff'),('admin_branch','promote_staff'),('admin_branch','manage_appointments'),('admin_branch','assign_medals'),('admin_branch','view_reports'),('admin_branch','admin_access'),('admin_branch','admin_branch_access'),('admin_branch','system_settings'),
('hr_officer','view_staff'),('hr_officer','edit_staff'),('hr_officer','create_staff'),('hr_officer','promote_staff'),('hr_officer','manage_appointments'),('hr_officer','view_reports'),('hr_officer','admin_branch_access'),
('records_officer','view_staff'),('records_officer','edit_staff'),('records_officer','view_reports'),('records_officer','admin_branch_access'),
('commander','view_staff'),('commander','view_reports'),('commander','assign_medals'),
('command','view_staff'),('command','view_reports'),('command','assign_medals'),('command','admin_branch_access'),
('training','view_staff'),('training','edit_staff'),('training','view_reports'),('training','admin_branch_access'),
('operations','view_staff'),('operations','edit_staff'),('operations','view_reports'),('operations','admin_branch_access'),
('staff_officer','view_staff'),('staff_officer','view_reports'),
('cc','view_staff'),('cc','edit_staff'),('cc','create_staff'),('cc','delete_staff'),('cc','promote_staff'),('cc','manage_appointments'),('cc','assign_medals'),('cc','view_reports'),
('soi','view_staff'),('soi','edit_staff'),('soi','promote_staff'),('soi','manage_appointments'),('soi','assign_medals'),('soi','view_reports'),
('soii','view_staff'),('soii','edit_staff'),('soii','manage_appointments'),('soii','view_reports'),
('soiii','view_staff'),('soiii','edit_staff'),('soiii','view_reports'),
('dg','view_staff'),('dg','view_reports'),
('ag','view_staff'),('ag','view_reports'),
('user','view_staff');
