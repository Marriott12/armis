-- =====================================================
-- ARMIS (Army Resource Management Information System)
-- CLEANED AND NORMALIZED DATABASE SCHEMA
-- Version: 2.0 (Production Ready)
-- Date: Generated on schema cleanup
-- =====================================================

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+02:00";

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

-- =====================================================
-- DATABASE CREATION AND CLEANUP
-- =====================================================

-- Create database if not exists
CREATE DATABASE IF NOT EXISTS `armis` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `armis`;

-- =====================================================
-- UTILITY STORED PROCEDURES FOR SAFE MIGRATIONS
-- =====================================================

DELIMITER $$

DROP PROCEDURE IF EXISTS `SafeAddColumn`$$
CREATE DEFINER=`root`@`localhost` PROCEDURE `SafeAddColumn` (
    IN `table_name` VARCHAR(64), 
    IN `column_name` VARCHAR(64), 
    IN `column_definition` TEXT
)
BEGIN
    DECLARE column_exists INT DEFAULT 0;
    DECLARE CONTINUE HANDLER FOR SQLEXCEPTION BEGIN END;
    
    SELECT COUNT(*) INTO column_exists 
    FROM information_schema.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = table_name 
    AND COLUMN_NAME = column_name;
    
    IF column_exists = 0 THEN
        SET @sql = CONCAT('ALTER TABLE ', table_name, ' ADD COLUMN ', column_name, ' ', column_definition);
        PREPARE stmt FROM @sql;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;
    END IF;
END$$

DROP PROCEDURE IF EXISTS `SafeAddConstraint`$$
CREATE DEFINER=`root`@`localhost` PROCEDURE `SafeAddConstraint` (
    IN `table_name` VARCHAR(64), 
    IN `constraint_name` VARCHAR(64), 
    IN `constraint_definition` TEXT
)
BEGIN
    DECLARE constraint_exists INT DEFAULT 0;
    DECLARE CONTINUE HANDLER FOR SQLEXCEPTION BEGIN END;
    
    SELECT COUNT(*) INTO constraint_exists 
    FROM information_schema.KEY_COLUMN_USAGE 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = table_name 
    AND CONSTRAINT_NAME = constraint_name;
    
    IF constraint_exists = 0 THEN
        SET @sql = CONCAT('ALTER TABLE ', table_name, ' ADD CONSTRAINT ', constraint_name, ' ', constraint_definition);
        PREPARE stmt FROM @sql;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;
    END IF;
END$$

DROP PROCEDURE IF EXISTS `SafeAddIndex`$$
CREATE DEFINER=`root`@`localhost` PROCEDURE `SafeAddIndex` (
    IN `table_name` VARCHAR(64), 
    IN `index_name` VARCHAR(64), 
    IN `index_definition` TEXT
)
BEGIN
    DECLARE index_exists INT DEFAULT 0;
    DECLARE CONTINUE HANDLER FOR SQLEXCEPTION BEGIN END;
    
    SELECT COUNT(*) INTO index_exists 
    FROM information_schema.STATISTICS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = table_name 
    AND INDEX_NAME = index_name;
    
    IF index_exists = 0 THEN
        SET @sql = CONCAT('CREATE INDEX ', index_name, ' ON ', table_name, ' ', index_definition);
        PREPARE stmt FROM @sql;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;
    END IF;
END$$

DELIMITER ;

-- =====================================================
-- CORE MILITARY STRUCTURE TABLES
-- =====================================================

-- -----------------------------------------------------
-- Table: corps
-- Purpose: Military corps/branches classification
-- -----------------------------------------------------
DROP TABLE IF EXISTS `corps`;
CREATE TABLE `corps` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(100) NOT NULL,
    `abbreviation` VARCHAR(10) NOT NULL,
    `description` TEXT,
    `is_active` BOOLEAN DEFAULT TRUE,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_corps_abbreviation` (`abbreviation`),
    INDEX `idx_corps_active` (`is_active`),
    INDEX `idx_corps_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------
-- Table: ranks
-- Purpose: Military rank structure with hierarchy
-- -----------------------------------------------------
DROP TABLE IF EXISTS `ranks`;
CREATE TABLE `ranks` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(100) NOT NULL,
    `abbreviation` VARCHAR(10) NOT NULL,
    `rank_level` INT NOT NULL DEFAULT 1,
    `category` ENUM('Enlisted', 'NCO', 'Warrant Officer', 'Officer') NOT NULL DEFAULT 'Enlisted',
    `pay_grade` VARCHAR(10),
    `nato_code` VARCHAR(10),
    `is_active` BOOLEAN DEFAULT TRUE,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_ranks_abbreviation` (`abbreviation`),
    UNIQUE KEY `uk_ranks_level_category` (`rank_level`, `category`),
    INDEX `idx_ranks_category` (`category`),
    INDEX `idx_ranks_level` (`rank_level`),
    INDEX `idx_ranks_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------
-- Table: units
-- Purpose: Military unit structure with hierarchy
-- -----------------------------------------------------
DROP TABLE IF EXISTS `units`;
CREATE TABLE `units` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(200) NOT NULL,
    `code` VARCHAR(20) NOT NULL,
    `type` VARCHAR(50),
    `parent_unit_id` INT DEFAULT NULL,
    `commander_id` INT DEFAULT NULL,
    `location` VARCHAR(100),
    `strength_authorized` INT DEFAULT 0,
    `strength_current` INT DEFAULT 0,
    `is_active` BOOLEAN DEFAULT TRUE,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_units_code` (`code`),
    INDEX `idx_units_parent` (`parent_unit_id`),
    INDEX `idx_units_commander` (`commander_id`),
    INDEX `idx_units_type` (`type`),
    INDEX `idx_units_location` (`location`),
    INDEX `idx_units_active` (`is_active`),
    CONSTRAINT `fk_units_parent` FOREIGN KEY (`parent_unit_id`) REFERENCES `units` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_units_commander` FOREIGN KEY (`commander_id`) REFERENCES `staff` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- PERSONNEL MANAGEMENT TABLES
-- =====================================================

-- -----------------------------------------------------
-- Table: staff
-- Purpose: Core staff/personnel records (normalized)
-- -----------------------------------------------------
DROP TABLE IF EXISTS `staff`;
CREATE TABLE `staff` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `service_number` VARCHAR(50) NOT NULL,
    `username` VARCHAR(50) UNIQUE,
    `password` VARCHAR(255),
    `first_name` VARCHAR(100) NOT NULL,
    `last_name` VARCHAR(100) NOT NULL,
    `email` VARCHAR(100) UNIQUE,
    `rank_id` INT NOT NULL,
    `unit_id` INT NOT NULL,
    `corps_id` INT,
    `gender` ENUM('M', 'F') NOT NULL,
    `date_of_birth` DATE,
    `date_of_enlistment` DATE,
    `nrc_number` VARCHAR(20),
    `passport_number` VARCHAR(20),
    `passport_expiry` DATE,
    `blood_type` ENUM('A+','A-','B+','B-','AB+','AB-','O+','O-'),
    `height` DECIMAL(5,2),
    `marital_status` ENUM('Single', 'Married', 'Divorced', 'Widowed') DEFAULT 'Single',
    `province` VARCHAR(50),
    `phone` VARCHAR(20),
    `emergency_contact_name` VARCHAR(100),
    `emergency_contact_phone` VARCHAR(20),
    `emergency_contact_relationship` VARCHAR(50),
    `role` ENUM('admin', 'user', 'manager', 'officer') DEFAULT 'user',
    `service_status` ENUM('Active', 'Inactive', 'Retired', 'Discharged') DEFAULT 'Active',
    `account_status` ENUM('active', 'inactive', 'suspended') DEFAULT 'active',
    `last_login` TIMESTAMP NULL,
    `password_changed_at` TIMESTAMP NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_staff_service_number` (`service_number`),
    UNIQUE KEY `uk_staff_username` (`username`),
    UNIQUE KEY `uk_staff_email` (`email`),
    INDEX `idx_staff_rank` (`rank_id`),
    INDEX `idx_staff_unit` (`unit_id`),
    INDEX `idx_staff_corps` (`corps_id`),
    INDEX `idx_staff_status` (`service_status`),
    INDEX `idx_staff_name` (`last_name`, `first_name`),
    INDEX `idx_staff_login` (`username`, `account_status`),
    CONSTRAINT `fk_staff_rank` FOREIGN KEY (`rank_id`) REFERENCES `ranks` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_staff_unit` FOREIGN KEY (`unit_id`) REFERENCES `units` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_staff_corps` FOREIGN KEY (`corps_id`) REFERENCES `corps` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------
-- Table: users
-- Purpose: Unified authentication table linked to staff
-- -----------------------------------------------------
DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `staff_id` INT NOT NULL,
    `username` VARCHAR(50) NOT NULL,
    `email` VARCHAR(100) NOT NULL,
    `password` VARCHAR(255) NOT NULL,
    `role` ENUM('admin','admin_branch','command','training','operations','finance','ordinance') DEFAULT 'command',
    `is_active` BOOLEAN DEFAULT TRUE,
    `last_login` TIMESTAMP NULL,
    `password_changed_at` TIMESTAMP NULL,
    `mfa_enabled` BOOLEAN DEFAULT FALSE,
    `mfa_secret` VARCHAR(255),
    `failed_login_attempts` INT DEFAULT 0,
    `locked_until` TIMESTAMP NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_users_staff` (`staff_id`),
    UNIQUE KEY `uk_users_username` (`username`),
    UNIQUE KEY `uk_users_email` (`email`),
    INDEX `idx_users_role` (`role`),
    INDEX `idx_users_active` (`is_active`),
    INDEX `idx_users_login` (`username`, `is_active`),
    CONSTRAINT `fk_users_staff` FOREIGN KEY (`staff_id`) REFERENCES `staff` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- TRAINING AND EDUCATION TABLES
-- =====================================================

-- -----------------------------------------------------
-- Table: courses
-- Purpose: Training courses and educational programs
-- -----------------------------------------------------
DROP TABLE IF EXISTS `courses`;
CREATE TABLE `courses` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `code` VARCHAR(20) NOT NULL,
    `name` VARCHAR(200) NOT NULL,
    `description` TEXT,
    `course_type` ENUM('Military', 'Technical', 'Leadership', 'Academic', 'Professional') NOT NULL,
    `duration_days` INT,
    `maximum_participants` INT,
    `prerequisites` TEXT,
    `provider_institution` VARCHAR(200),
    `is_active` BOOLEAN DEFAULT TRUE,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_courses_code` (`code`),
    INDEX `idx_courses_type` (`course_type`),
    INDEX `idx_courses_active` (`is_active`),
    INDEX `idx_courses_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------
-- Table: staff_courses
-- Purpose: Staff course enrollment and completion tracking
-- -----------------------------------------------------
DROP TABLE IF EXISTS `staff_courses`;
CREATE TABLE `staff_courses` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `staff_id` INT NOT NULL,
    `course_id` INT NOT NULL,
    `enrollment_date` DATE NOT NULL,
    `start_date` DATE,
    `completion_date` DATE,
    `status` ENUM('Enrolled', 'In Progress', 'Completed', 'Failed', 'Withdrawn') DEFAULT 'Enrolled',
    `grade` VARCHAR(10),
    `certificate_number` VARCHAR(100),
    `cost` DECIMAL(10,2),
    `funded_by` VARCHAR(100),
    `location` VARCHAR(100),
    `instructor` VARCHAR(100),
    `notes` TEXT,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_staff_course_enrollment` (`staff_id`, `course_id`, `enrollment_date`),
    INDEX `idx_staff_courses_staff` (`staff_id`),
    INDEX `idx_staff_courses_course` (`course_id`),
    INDEX `idx_staff_courses_status` (`status`),
    INDEX `idx_staff_courses_dates` (`start_date`, `completion_date`),
    CONSTRAINT `fk_staff_courses_staff` FOREIGN KEY (`staff_id`) REFERENCES `staff` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_staff_courses_course` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- DOCUMENT MANAGEMENT TABLES
-- =====================================================

-- -----------------------------------------------------
-- Table: document_types
-- Purpose: Classification of document types
-- -----------------------------------------------------
DROP TABLE IF EXISTS `document_types`;
CREATE TABLE `document_types` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(100) NOT NULL,
    `description` TEXT,
    `category` VARCHAR(50),
    `retention_period_years` INT,
    `security_classification` ENUM('Public', 'Internal', 'Confidential', 'Secret') DEFAULT 'Internal',
    `is_active` BOOLEAN DEFAULT TRUE,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_document_types_name` (`name`),
    INDEX `idx_document_types_category` (`category`),
    INDEX `idx_document_types_classification` (`security_classification`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------
-- Table: staff_documents
-- Purpose: Staff document management and file tracking
-- -----------------------------------------------------
DROP TABLE IF EXISTS `staff_documents`;
CREATE TABLE `staff_documents` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `staff_id` INT NOT NULL,
    `document_type_id` INT NOT NULL,
    `title` VARCHAR(200) NOT NULL,
    `file_name` VARCHAR(255) NOT NULL,
    `file_path` VARCHAR(500) NOT NULL,
    `file_size` BIGINT,
    `mime_type` VARCHAR(100),
    `document_number` VARCHAR(100),
    `issue_date` DATE,
    `expiry_date` DATE,
    `issuing_authority` VARCHAR(200),
    `security_classification` ENUM('Public', 'Internal', 'Confidential', 'Secret') DEFAULT 'Internal',
    `access_level` ENUM('Public', 'Unit', 'Personal', 'Restricted') DEFAULT 'Personal',
    `version_number` INT DEFAULT 1,
    `checksum` VARCHAR(64),
    `uploaded_by` INT NOT NULL,
    `approved_by` INT,
    `approval_date` TIMESTAMP NULL,
    `status` ENUM('Draft', 'Pending', 'Approved', 'Rejected', 'Archived') DEFAULT 'Draft',
    `notes` TEXT,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_staff_documents_staff` (`staff_id`),
    INDEX `idx_staff_documents_type` (`document_type_id`),
    INDEX `idx_staff_documents_status` (`status`),
    INDEX `idx_staff_documents_expiry` (`expiry_date`),
    INDEX `idx_staff_documents_classification` (`security_classification`),
    INDEX `idx_staff_documents_uploaded` (`uploaded_by`),
    CONSTRAINT `fk_staff_documents_staff` FOREIGN KEY (`staff_id`) REFERENCES `staff` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_staff_documents_type` FOREIGN KEY (`document_type_id`) REFERENCES `document_types` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_staff_documents_uploader` FOREIGN KEY (`uploaded_by`) REFERENCES `staff` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_staff_documents_approver` FOREIGN KEY (`approved_by`) REFERENCES `staff` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- AUDIT AND ACTIVITY TRACKING
-- =====================================================

-- -----------------------------------------------------
-- Table: activity_log
-- Purpose: Unified activity logging for audit trail
-- Note: Removed redundant activity_logs table
-- -----------------------------------------------------
DROP TABLE IF EXISTS `activity_log`;
CREATE TABLE `activity_log` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `user_id` INT,
    `staff_id` INT,
    `username` VARCHAR(50),
    `action` VARCHAR(100) NOT NULL,
    `entity_type` VARCHAR(50),
    `entity_id` INT,
    `description` TEXT,
    `ip_address` VARCHAR(45),
    `user_agent` TEXT,
    `module` VARCHAR(50),
    `severity` ENUM('LOW', 'MEDIUM', 'HIGH', 'CRITICAL') DEFAULT 'LOW',
    `success` BOOLEAN DEFAULT TRUE,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_activity_user` (`user_id`),
    INDEX `idx_activity_staff` (`staff_id`),
    INDEX `idx_activity_action` (`action`),
    INDEX `idx_activity_entity` (`entity_type`, `entity_id`),
    INDEX `idx_activity_module` (`module`),
    INDEX `idx_activity_severity` (`severity`),
    INDEX `idx_activity_created` (`created_at`),
    CONSTRAINT `fk_activity_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_activity_staff` FOREIGN KEY (`staff_id`) REFERENCES `staff` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- ADDITIONAL PERSONNEL TABLES
-- =====================================================

-- -----------------------------------------------------
-- Table: staff_deployments
-- Purpose: Track deployments and assignments
-- -----------------------------------------------------
DROP TABLE IF EXISTS `staff_deployments`;
CREATE TABLE `staff_deployments` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `staff_id` INT NOT NULL,
    `deployment_name` VARCHAR(200) NOT NULL,
    `location` VARCHAR(200) NOT NULL,
    `start_date` DATE NOT NULL,
    `end_date` DATE,
    `status` ENUM('Upcoming', 'Active', 'Completed', 'Cancelled') DEFAULT 'Upcoming',
    `deployment_type` ENUM('Combat', 'Peacekeeping', 'Training', 'Humanitarian', 'Other') DEFAULT 'Other',
    `unit_deployed` VARCHAR(255),
    `role` VARCHAR(255),
    `commander` VARCHAR(255),
    `hazard_pay` DECIMAL(10,2) DEFAULT 0.00,
    `family_separation_allowance` DECIMAL(10,2) DEFAULT 0.00,
    `notes` TEXT,
    `created_by` INT,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_deployments_staff` (`staff_id`),
    INDEX `idx_deployments_status` (`status`),
    INDEX `idx_deployments_dates` (`start_date`, `end_date`),
    INDEX `idx_deployments_type` (`deployment_type`),
    CONSTRAINT `fk_deployments_staff` FOREIGN KEY (`staff_id`) REFERENCES `staff` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_deployments_creator` FOREIGN KEY (`created_by`) REFERENCES `staff` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------
-- Table: staff_rank_progression
-- Purpose: Track rank advancement history
-- -----------------------------------------------------
DROP TABLE IF EXISTS `staff_rank_progression`;
CREATE TABLE `staff_rank_progression` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `staff_id` INT NOT NULL,
    `from_rank_id` INT,
    `to_rank_id` INT NOT NULL,
    `promotion_date` DATE NOT NULL,
    `effective_date` DATE,
    `promotion_type` ENUM('Regular', 'Battlefield', 'Temporary', 'Acting') DEFAULT 'Regular',
    `promotion_board_date` DATE,
    `promotion_order` VARCHAR(100),
    `time_in_grade_months` INT,
    `time_in_service_months` INT,
    `eligible_for_next` BOOLEAN DEFAULT FALSE,
    `next_eligible_date` DATE,
    `promotion_points` INT,
    `requirements_met` BOOLEAN DEFAULT FALSE,
    `requirements_notes` TEXT,
    `approved_by` INT,
    `created_by` INT,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_rank_progression_staff` (`staff_id`),
    INDEX `idx_rank_progression_dates` (`promotion_date`, `effective_date`),
    INDEX `idx_rank_progression_type` (`promotion_type`),
    CONSTRAINT `fk_rank_progression_staff` FOREIGN KEY (`staff_id`) REFERENCES `staff` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_rank_progression_from_rank` FOREIGN KEY (`from_rank_id`) REFERENCES `ranks` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_rank_progression_to_rank` FOREIGN KEY (`to_rank_id`) REFERENCES `ranks` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_rank_progression_approver` FOREIGN KEY (`approved_by`) REFERENCES `staff` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_rank_progression_creator` FOREIGN KEY (`created_by`) REFERENCES `staff` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- PERFORMANCE VIEWS FOR OPTIMIZATION
-- =====================================================

-- -----------------------------------------------------
-- View: staff_full_details
-- Purpose: Optimized view for complete staff information
-- -----------------------------------------------------
DROP VIEW IF EXISTS `staff_full_details`;
CREATE VIEW `staff_full_details` AS
SELECT 
    s.id,
    s.service_number,
    s.first_name,
    s.last_name,
    CONCAT(s.first_name, ' ', s.last_name) as full_name,
    s.email,
    s.phone,
    r.name as rank_name,
    r.abbreviation as rank_abbr,
    r.category as rank_category,
    u.name as unit_name,
    u.code as unit_code,
    u.location as unit_location,
    c.name as corps_name,
    c.abbreviation as corps_abbr,
    s.service_status,
    s.account_status,
    s.last_login,
    s.created_at,
    s.updated_at
FROM staff s
LEFT JOIN ranks r ON s.rank_id = r.id
LEFT JOIN units u ON s.unit_id = u.id  
LEFT JOIN corps c ON s.corps_id = c.id
WHERE s.service_status = 'Active';

-- -----------------------------------------------------
-- View: unit_strength_summary
-- Purpose: Real-time unit strength reporting
-- -----------------------------------------------------
DROP VIEW IF EXISTS `unit_strength_summary`;
CREATE VIEW `unit_strength_summary` AS
SELECT 
    u.id,
    u.name,
    u.code,
    u.type,
    u.location,
    u.strength_authorized,
    COUNT(s.id) as strength_current,
    (u.strength_authorized - COUNT(s.id)) as strength_deficit,
    ROUND((COUNT(s.id) / u.strength_authorized * 100), 2) as strength_percentage,
    commander.full_name as commander_name
FROM units u
LEFT JOIN staff s ON u.id = s.unit_id AND s.service_status = 'Active'
LEFT JOIN staff_full_details commander ON u.commander_id = commander.id
WHERE u.is_active = TRUE
GROUP BY u.id, u.name, u.code, u.type, u.location, u.strength_authorized, commander.full_name;

-- =====================================================
-- INITIAL DATA POPULATION
-- =====================================================

-- Insert default corps
INSERT INTO `corps` (`name`, `abbreviation`, `description`) VALUES
('Infantry', 'INF', 'Infantry Corps'),
('Artillery', 'ARTY', 'Artillery Corps'),
('Engineers', 'ENG', 'Engineering Corps'),
('Signals', 'SIG', 'Signals/Communications Corps'),
('Medical', 'MED', 'Medical Corps'),
('Intelligence', 'INT', 'Intelligence Corps'),
('Logistics', 'LOG', 'Logistics Corps'),
('Military Police', 'MP', 'Military Police Corps');

-- Insert military ranks structure
INSERT INTO `ranks` (`name`, `abbreviation`, `rank_level`, `category`, `pay_grade`) VALUES
-- Enlisted
('Private', 'Pte', 1, 'Enlisted', 'E-1'),
('Private First Class', 'PFC', 2, 'Enlisted', 'E-2'),
('Corporal', 'Cpl', 3, 'Enlisted', 'E-3'),
('Lance Corporal', 'LCpl', 4, 'Enlisted', 'E-4'),
-- NCO
('Sergeant', 'Sgt', 5, 'NCO', 'E-5'),
('Staff Sergeant', 'SSgt', 6, 'NCO', 'E-6'),
('Sergeant First Class', 'SFC', 7, 'NCO', 'E-7'),
('Master Sergeant', 'MSgt', 8, 'NCO', 'E-8'),
('Sergeant Major', 'SGM', 9, 'NCO', 'E-9'),
-- Warrant Officers
('Warrant Officer', 'WO', 10, 'Warrant Officer', 'W-1'),
('Chief Warrant Officer', 'CWO', 11, 'Warrant Officer', 'W-2'),
-- Officers
('Second Lieutenant', '2Lt', 12, 'Officer', 'O-1'),
('Lieutenant', 'Lt', 13, 'Officer', 'O-2'),
('Captain', 'Capt', 14, 'Officer', 'O-3'),
('Major', 'Maj', 15, 'Officer', 'O-4'),
('Lieutenant Colonel', 'LtCol', 16, 'Officer', 'O-5'),
('Colonel', 'Col', 17, 'Officer', 'O-6'),
('Brigadier General', 'BGen', 18, 'Officer', 'O-7'),
('Major General', 'MGen', 19, 'Officer', 'O-8'),
('Lieutenant General', 'LtGen', 20, 'Officer', 'O-9'),
('General', 'Gen', 21, 'Officer', 'O-10');

-- Insert basic military units structure (avoiding duplicates)
INSERT INTO `units` (`name`, `code`, `type`, `parent_unit_id`, `location`, `strength_authorized`) VALUES
('Army Headquarters', 'AHQ', 'Headquarters', NULL, 'Lusaka', 500),
('1st Infantry Brigade', '1INF-BDE', 'Brigade', 1, 'Lusaka', 3000),
('2nd Infantry Brigade', '2INF-BDE', 'Brigade', 1, 'Ndola', 3000),
('1st Armoured Regiment', '1ARM-REG', 'Regiment', 2, 'Lusaka', 800),
('1st Infantry Battalion', '1INF-BN', 'Battalion', 2, 'Lusaka', 600),
('2nd Infantry Battalion', '2INF-BN', 'Battalion', 3, 'Ndola', 600),
('Engineering Regiment', 'ENG-REG', 'Regiment', 1, 'Kabwe', 400),
('Artillery Regiment', 'ARTY-REG', 'Regiment', 1, 'Mumbwa', 500),
('Medical Battalion', 'MED-BN', 'Battalion', 1, 'Lusaka', 300),
('Military Police Company', 'MP-COY', 'Company', 1, 'Lusaka', 150);

-- Insert document types
INSERT INTO `document_types` (`name`, `description`, `category`, `retention_period_years`, `security_classification`) VALUES
('Personnel File', 'Complete personnel record', 'Personnel', 30, 'Confidential'),
('Training Certificate', 'Course completion certificates', 'Training', 10, 'Internal'),
('Medical Record', 'Medical examination records', 'Medical', 25, 'Confidential'),
('Performance Review', 'Annual performance evaluations', 'Personnel', 7, 'Internal'),
('Disciplinary Action', 'Disciplinary records', 'Personnel', 15, 'Confidential'),
('Security Clearance', 'Security clearance documentation', 'Security', 20, 'Secret'),
('Leave Application', 'Leave and absence requests', 'Administrative', 3, 'Internal'),
('Assignment Order', 'Assignment and posting orders', 'Administrative', 10, 'Internal');

-- Insert default courses
INSERT INTO `courses` (`code`, `name`, `description`, `course_type`, `duration_days`, `maximum_participants`) VALUES
('BTC-001', 'Basic Training Course', 'Initial military training for recruits', 'Military', 90, 50),
('OCS-001', 'Officer Candidate School', 'Officer commissioning program', 'Military', 180, 25),
('JNCO-001', 'Junior NCO Course', 'Leadership training for junior NCOs', 'Leadership', 30, 30),
('SNCO-001', 'Senior NCO Course', 'Advanced leadership for senior NCOs', 'Leadership', 60, 20),
('CTT-001', 'Combat Training', 'Advanced combat skills training', 'Military', 45, 40),
('ENG-001', 'Engineering Operations', 'Military engineering training', 'Technical', 60, 25),
('MED-001', 'Combat Medic Training', 'Medical training for combat medics', 'Technical', 120, 15),
('SIG-001', 'Communications Training', 'Signals and communications course', 'Technical', 45, 30);

-- Create default admin user
INSERT INTO `staff` (
    `service_number`, `username`, `password`, `first_name`, `last_name`, `email`, 
    `rank_id`, `unit_id`, `gender`, `role`, `service_status`, `account_status`
) VALUES (
    'ADM001', 'admin', '$2y$10$.T/BwYsngu9zCjwIUj3R6uO1ga6yRHCcxNFaf7zIpqw4hAeIcGeHC', 
    'System', 'Administrator', 'admin@armis.mil', 
    17, 1, 'M', 'admin', 'Active', 'active'
);

-- Create corresponding user record
INSERT INTO `users` (`staff_id`, `username`, `email`, `password`, `role`, `is_active`) 
SELECT `id`, `username`, `email`, `password`, 'admin', TRUE 
FROM `staff` WHERE `username` = 'admin';

-- =====================================================
-- PERFORMANCE INDEXES
-- =====================================================

-- Additional composite indexes for common queries
CREATE INDEX `idx_staff_unit_rank` ON `staff` (`unit_id`, `rank_id`);
CREATE INDEX `idx_staff_status_date` ON `staff` (`service_status`, `created_at`);
CREATE INDEX `idx_activity_user_date` ON `activity_log` (`user_id`, `created_at`);
CREATE INDEX `idx_documents_staff_type` ON `staff_documents` (`staff_id`, `document_type_id`);
CREATE INDEX `idx_courses_staff_status` ON `staff_courses` (`staff_id`, `status`);

-- =====================================================
-- DATABASE INTEGRITY AND FINAL SETUP
-- =====================================================

-- Enable foreign key checks
SET FOREIGN_KEY_CHECKS = 1;

-- Update table statistics for optimization
ANALYZE TABLE `staff`, `users`, `ranks`, `units`, `corps`, `activity_log`;

COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

-- =====================================================
-- END OF CLEANED AND NORMALIZED ARMIS SCHEMA
-- =====================================================
