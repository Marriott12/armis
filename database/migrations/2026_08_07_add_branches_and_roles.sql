-- ============================================================================
-- ARMIS Migration: 2026_08_07_add_branches_and_roles.sql
-- Adds dynamic branches + dynamic roles so a new branch (e.g. "Legal") can be
-- created from the Admin UI with zero code changes.
-- Run BEFORE 2026_08_07_add_staff_branch_scoping.sql
-- Non-destructive. Backup first.
-- ============================================================================

START TRANSACTION;

-- ----------------------------------------------------------------------------
-- branches: one row per ARMIS department. `is_org_wide` is what makes Admin
-- Branch different from Training/Finance/Operations/Ordinance/Command — its
-- CC/SO/DG reach ALL personnel (personnel administration is army-wide by
-- nature), everyone else's reach is limited to their own branch.
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `branches` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `code`        VARCHAR(30)  NOT NULL COMMENT 'stable slug used in module checks, matches folder name',
  `name`        VARCHAR(100) NOT NULL,
  `description` VARCHAR(255) DEFAULT NULL,
  `icon`        VARCHAR(50)  DEFAULT 'folder' COMMENT 'Font Awesome icon name, no fa- prefix',
  `color`       VARCHAR(20)  DEFAULT '#8a6d2f',
  `url_path`    VARCHAR(100) DEFAULT NULL,
  `is_org_wide` TINYINT(1)   NOT NULL DEFAULT 0 COMMENT '1 = Admin Branch only: CC/SO/DG here reach the whole Army',
  `status`      ENUM('Active','Inactive') NOT NULL DEFAULT 'Active',
  `created_by`  VARCHAR(10)  DEFAULT NULL COMMENT 'svcNo of admin who created it',
  `createdAt`   TIMESTAMP    NULL DEFAULT CURRENT_TIMESTAMP,
  `updatedAt`   TIMESTAMP    NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_branches_code` (`code`),
  KEY `idx_branches_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- ----------------------------------------------------------------------------
-- roles: `scope` only matters for the AG role (org-wide regardless of branch).
-- Everyone else's real-world reach = their assigned branch's is_org_wide flag,
-- resolved at runtime in shared/rbac.php (canAlterRecord / getSnapshotScope).
--   access = 'write' -> may alter records (cc/soi/soii/soiii, admin)
--          = 'read'  -> snapshot only (ag, dg)
--          = 'none'  -> no personnel-management access (user)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `roles` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `code`       VARCHAR(30)  NOT NULL COMMENT 'matches staff.role',
  `name`       VARCHAR(100) NOT NULL,
  `level`      INT          NOT NULL DEFAULT 10,
  `scope`      ENUM('org','branch','none') NOT NULL DEFAULT 'none',
  `access`     ENUM('read','write','none') NOT NULL DEFAULT 'none',
  `is_branch_assignable` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1 = requires staff.branch_id (cc/soi/soii/soiii/dg)',
  `is_system`  TINYINT(1)   NOT NULL DEFAULT 0 COMMENT '1 = protected from deletion in admin UI',
  `status`     ENUM('Active','Inactive') NOT NULL DEFAULT 'Active',
  `description` VARCHAR(255) DEFAULT NULL,
  `createdAt`  TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `updatedAt`  TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_roles_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- ----------------------------------------------------------------------------
-- role_modules: global (non-branch) module grants. Branch modules resolved
-- at runtime from staff.branch_id, not stored here.
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `role_modules` (
  `role_code`   VARCHAR(30) NOT NULL,
  `module_code` VARCHAR(30) NOT NULL,
  PRIMARY KEY (`role_code`, `module_code`),
  CONSTRAINT `fk_role_modules_role` FOREIGN KEY (`role_code`) REFERENCES `roles` (`code`)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- ----------------------------------------------------------------------------
-- Seed branches — one per existing module folder, so nothing breaks.
-- Admin Branch is the only one flagged org-wide.
-- ----------------------------------------------------------------------------
INSERT INTO `branches` (`code`, `name`, `description`, `icon`, `color`, `url_path`, `is_org_wide`, `status`) VALUES
('admin_branch', 'Admin Branch', 'Personnel administration for the entire Army',      'users-cog',      '#8a6d2f', '/Armis2/admin_branch/', 1, 'Active'),
('command',      'Command',      'Command and operational oversight',                 'chess-king',     '#2c3e50', '/Armis2/command/',      0, 'Active'),
('operations',   'Operations',   'Operations, missions and deployments',              'map-marked-alt', '#3d5a80', '/Armis2/operations/',   0, 'Active'),
('training',     'Training',     'Training courses, sessions and records',            'graduation-cap', '#556b2f', '/Armis2/training/',     0, 'Active'),
('finance',      'Finance',      'Financial management',                              'calculator',     '#7b5e2a', '/Armis2/finance/',      0, 'Active'),
('ordinance',    'Ordinance',    'Equipment and ordinance management',                'shield-alt',     '#5c3d2e', '/Armis2/ordinance/',    0, 'Active')
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`);

-- ----------------------------------------------------------------------------
-- Seed roles — new roles requested PLUS every value already live in
-- staff.role's ENUM today, so the FK added in migration 2 doesn't break a
-- single existing account.
-- ----------------------------------------------------------------------------
INSERT INTO `roles` (`code`, `name`, `level`, `scope`, `access`, `is_branch_assignable`, `is_system`, `status`, `description`) VALUES
('admin',        'System Administrator', 100, 'org',    'write', 0, 1, 'Active', 'Full system access, all branches'),
('superadmin',   'Super Administrator',  100, 'org',    'write', 0, 1, 'Active', 'Legacy super-admin, treated as admin'),

('ag',           'Adjutant General',      95, 'org',    'read',  0, 1, 'Active', 'Whole-Army snapshot: Officers, NCOs and Civilian Employees. Read-only.'),
('dg',           'Director General',      85, 'branch', 'read',  1, 1, 'Active', 'Snapshot of own branch only. Read-only.'),

('cc',           'Chief Clerk',           50, 'branch', 'write', 1, 1, 'Active', 'Alters records for their assigned branch'),
('soi',          'Staff Officer I',       48, 'branch', 'write', 1, 1, 'Active', 'Alters records for their assigned branch'),
('soii',         'Staff Officer II',      46, 'branch', 'write', 1, 1, 'Active', 'Alters records for their assigned branch'),
('soiii',        'Staff Officer III',     44, 'branch', 'write', 1, 1, 'Active', 'Alters records for their assigned branch'),

-- legacy values already present in staff.role — kept so existing accounts keep working
('admin_branch', 'Admin Branch Staff (legacy)', 70, 'branch', 'write', 1, 0, 'Active', 'Superseded by cc/soi/soii/soiii on the admin_branch branch'),
('command',      'Command Officer (legacy)',     80, 'branch', 'write', 1, 0, 'Active', 'Legacy role'),
('training',     'Training Officer (legacy)',    60, 'branch', 'write', 1, 0, 'Active', 'Legacy role'),
('operations',   'Operations Officer (legacy)',  60, 'branch', 'write', 1, 0, 'Active', 'Legacy role'),
('q_branch',     'Q Branch (legacy)',            60, 'branch', 'write', 1, 0, 'Active', 'Legacy role'),
('provost',      'Provost (legacy)',             60, 'branch', 'write', 1, 0, 'Active', 'Legacy role'),
('intelligence', 'Intelligence (legacy)',        60, 'branch', 'write', 1, 0, 'Active', 'Legacy role'),

('user',         'Standard User', 10, 'none', 'none', 0, 1, 'Active', 'Basic profile access only')
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`);

-- ----------------------------------------------------------------------------
-- Seed role_modules (global modules only — dashboard/users/admin/branches)
-- ----------------------------------------------------------------------------
INSERT INTO `role_modules` (`role_code`, `module_code`) VALUES
('admin','dashboard'), ('admin','admin'), ('admin','users'), ('admin','branches'), ('admin','admin_branch'), ('admin','command'), ('admin','operations'), ('admin','training'), ('admin','finance'), ('admin','ordinance'),
('superadmin','dashboard'), ('superadmin','admin'), ('superadmin','users'), ('superadmin','branches'), ('superadmin','admin_branch'), ('superadmin','command'), ('superadmin','operations'), ('superadmin','training'), ('superadmin','finance'), ('superadmin','ordinance'),
('ag','dashboard'), ('ag','users'), ('ag','admin_branch'),
('dg','dashboard'), ('dg','users'),
('cc','dashboard'), ('cc','users'),
('soi','dashboard'), ('soi','users'),
('soii','dashboard'), ('soii','users'),
('soiii','dashboard'), ('soiii','users'),
('admin_branch','dashboard'), ('admin_branch','users'), ('admin_branch','admin_branch'),
('command','dashboard'), ('command','users'), ('command','command'),
('training','dashboard'), ('training','users'), ('training','training'),
('operations','dashboard'), ('operations','users'), ('operations','operations'),
('q_branch','dashboard'), ('q_branch','users'),
('provost','dashboard'), ('provost','users'),
('intelligence','dashboard'), ('intelligence','users'),
('user','dashboard'), ('user','users')
ON DUPLICATE KEY UPDATE `role_code` = VALUES(`role_code`);

COMMIT;
