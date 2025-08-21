-- ARMIS Phase 1: Database Schema Standardization
-- Standardize user profile tables with military-grade requirements

-- Enhance existing staff table with military-specific fields
ALTER TABLE staff 
ADD COLUMN DOB DATE COMMENT 'Date of Birth',
ADD COLUMN gender ENUM('M', 'F', 'Other') COMMENT 'Gender',
ADD COLUMN NRC VARCHAR(20) COMMENT 'National Registration Card',
ADD COLUMN tel VARCHAR(20) COMMENT 'Telephone Number',
ADD COLUMN address TEXT COMMENT 'Residential Address',
ADD COLUMN bloodGp VARCHAR(5) COMMENT 'Blood Group',
ADD COLUMN marital ENUM('Single', 'Married', 'Divorced', 'Widowed') COMMENT 'Marital Status',
ADD COLUMN attestDate DATE COMMENT 'Enlistment/Attestation Date',
ADD COLUMN prefix VARCHAR(10) COMMENT 'Title/Prefix (Mr, Mrs, Dr, etc)',
ADD COLUMN middle_name VARCHAR(50) COMMENT 'Middle Name',
ADD COLUMN suffix VARCHAR(10) COMMENT 'Suffix (Jr, Sr, III, etc)',
ADD COLUMN emergency_contact_name VARCHAR(100) COMMENT 'Emergency Contact Name',
ADD COLUMN emergency_contact_phone VARCHAR(20) COMMENT 'Emergency Contact Phone',
ADD COLUMN emergency_contact_relationship VARCHAR(50) COMMENT 'Emergency Contact Relationship',
ADD COLUMN profile_picture VARCHAR(255) COMMENT 'Profile Picture Path',
ADD COLUMN security_clearance_level ENUM('None', 'Confidential', 'Secret', 'Top Secret') DEFAULT 'None',
ADD COLUMN clearance_expiry_date DATE COMMENT 'Security Clearance Expiry Date',
ADD COLUMN medical_status ENUM('Fit', 'Limited', 'Unfit', 'Pending') DEFAULT 'Pending',
ADD COLUMN medical_expiry_date DATE COMMENT 'Medical Fitness Expiry Date',
ADD COLUMN deployment_status ENUM('Available', 'Deployed', 'Training', 'Leave', 'Medical') DEFAULT 'Available',
ADD COLUMN next_promotion_eligible_date DATE COMMENT 'Next Promotion Eligible Date';

-- Add indexes for performance
ALTER TABLE staff 
ADD INDEX IF NOT EXISTS idx_staff_rank_id (rank_id),
ADD INDEX IF NOT EXISTS idx_staff_unit_id (unit_id),
ADD INDEX IF NOT EXISTS idx_staff_corps (corps),
ADD INDEX IF NOT EXISTS idx_staff_security_clearance (security_clearance_level),
ADD INDEX IF NOT EXISTS idx_staff_deployment_status (deployment_status),
ADD INDEX IF NOT EXISTS idx_staff_medical_status (medical_status),
ADD INDEX IF NOT EXISTS idx_staff_service_number (service_number),
ADD INDEX IF NOT EXISTS idx_staff_email (email),
ADD INDEX IF NOT EXISTS idx_staff_accStatus (accStatus);

-- Enhance ranks table
ALTER TABLE ranks 
ADD COLUMN IF NOT EXISTS rank_level INT COMMENT 'Numeric rank level for hierarchy',
ADD COLUMN IF NOT EXISTS rank_category ENUM('Officer', 'NCO', 'Enlisted') COMMENT 'Rank Category',
ADD COLUMN IF NOT EXISTS minimum_service_years INT DEFAULT 0 COMMENT 'Minimum service years for this rank',
ADD COLUMN IF NOT EXISTS base_salary DECIMAL(10,2) COMMENT 'Base salary for this rank',
ADD COLUMN IF NOT EXISTS is_active BOOLEAN DEFAULT TRUE COMMENT 'Whether rank is currently in use',
ADD COLUMN IF NOT EXISTS created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
ADD COLUMN IF NOT EXISTS updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;

-- Enhance units table
ALTER TABLE units 
ADD COLUMN IF NOT EXISTS parent_unit_id INT COMMENT 'Parent unit for hierarchy',
ADD COLUMN IF NOT EXISTS unit_type ENUM('Command', 'Battalion', 'Company', 'Platoon', 'Squad', 'Section') COMMENT 'Unit Type',
ADD COLUMN IF NOT EXISTS commander_id INT COMMENT 'Unit Commander Staff ID',
ADD COLUMN IF NOT EXISTS location VARCHAR(200) COMMENT 'Unit Location/Base',
ADD COLUMN IF NOT EXISTS establishment_date DATE COMMENT 'Unit Establishment Date',
ADD COLUMN IF NOT EXISTS operational_status ENUM('Active', 'Training', 'Standby', 'Disbanded') DEFAULT 'Active',
ADD COLUMN IF NOT EXISTS is_active BOOLEAN DEFAULT TRUE COMMENT 'Whether unit is currently active',
ADD COLUMN IF NOT EXISTS created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
ADD COLUMN IF NOT EXISTS updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
ADD FOREIGN KEY IF NOT EXISTS fk_units_parent (parent_unit_id) REFERENCES units(id) ON DELETE SET NULL,
ADD FOREIGN KEY IF NOT EXISTS fk_units_commander (commander_id) REFERENCES staff(id) ON DELETE SET NULL;

-- Enhance corps table
ALTER TABLE corps 
ADD COLUMN IF NOT EXISTS corps_type ENUM('Combat', 'Combat Support', 'Combat Service Support', 'Special Forces') COMMENT 'Corps Type',
ADD COLUMN IF NOT EXISTS description TEXT COMMENT 'Corps Description',
ADD COLUMN IF NOT EXISTS motto VARCHAR(200) COMMENT 'Corps Motto',
ADD COLUMN IF NOT EXISTS color_code VARCHAR(7) COMMENT 'Corps Color Code for UI',
ADD COLUMN IF NOT EXISTS is_active BOOLEAN DEFAULT TRUE COMMENT 'Whether corps is currently active',
ADD COLUMN IF NOT EXISTS created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
ADD COLUMN IF NOT EXISTS updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;

-- Add foreign key constraints to staff table
ALTER TABLE staff 
ADD CONSTRAINT IF NOT EXISTS fk_staff_rank FOREIGN KEY (rank_id) REFERENCES ranks(id) ON DELETE SET NULL,
ADD CONSTRAINT IF NOT EXISTS fk_staff_unit FOREIGN KEY (unit_id) REFERENCES units(id) ON DELETE SET NULL;

-- Create indexes for better performance
CREATE INDEX IF NOT EXISTS idx_ranks_level ON ranks(rank_level);
CREATE INDEX IF NOT EXISTS idx_ranks_category ON ranks(rank_category);
CREATE INDEX IF NOT EXISTS idx_units_type ON units(unit_type);
CREATE INDEX IF NOT EXISTS idx_units_status ON units(operational_status);
CREATE INDEX IF NOT EXISTS idx_corps_type ON corps(corps_type);

-- ARMIS Database Schema Standardization
-- Migration 001: Standardize User Profile Schema with Military-Grade Standards
-- Ensures consistent snake_case naming and proper relationships

-- Standardize staff table column names and add missing fields
ALTER TABLE staff 
    CHANGE COLUMN svcNo service_number VARCHAR(50),
    CHANGE COLUMN fname first_name VARCHAR(100),
    CHANGE COLUMN lname last_name VARCHAR(100),
    CHANGE COLUMN DOB date_of_birth DATE,
    CHANGE COLUMN NRC national_id VARCHAR(20),
    CHANGE COLUMN attestDate enlistment_date DATE,
    CHANGE COLUMN rankID rank_id INT,
    CHANGE COLUMN unitID unit_id INT,
    CHANGE COLUMN svcStatus service_status VARCHAR(20) DEFAULT 'active',
    CHANGE COLUMN dateCreated created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CHANGE COLUMN tel phone VARCHAR(20),
    CHANGE COLUMN bloodGp blood_group VARCHAR(5),
    CHANGE COLUMN marital marital_status VARCHAR(20);

-- Add missing standardized columns if they don't exist
ALTER TABLE staff 
    ADD COLUMN IF NOT EXISTS updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    ADD COLUMN IF NOT EXISTS middle_name VARCHAR(100) AFTER first_name,
    ADD COLUMN IF NOT EXISTS prefix VARCHAR(20) BEFORE first_name,
    ADD COLUMN IF NOT EXISTS suffix VARCHAR(20) AFTER last_name,
    ADD COLUMN IF NOT EXISTS nationality VARCHAR(50) DEFAULT 'Zambian',
    ADD COLUMN IF NOT EXISTS place_of_birth VARCHAR(100),
    ADD COLUMN IF NOT EXISTS emergency_contact_name VARCHAR(100),
    ADD COLUMN IF NOT EXISTS emergency_contact_phone VARCHAR(20),
    ADD COLUMN IF NOT EXISTS emergency_contact_relationship VARCHAR(50),
    ADD COLUMN IF NOT EXISTS security_clearance_level VARCHAR(50),
    ADD COLUMN IF NOT EXISTS clearance_expiry_date DATE,
    ADD COLUMN IF NOT EXISTS medical_fitness_status VARCHAR(50) DEFAULT 'Fit',
    ADD COLUMN IF NOT EXISTS last_medical_exam DATE,
    ADD COLUMN IF NOT EXISTS next_medical_due DATE,
    ADD COLUMN IF NOT EXISTS profile_completion_percentage INT DEFAULT 0,
    ADD COLUMN IF NOT EXISTS is_active BOOLEAN DEFAULT TRUE,
    ADD COLUMN IF NOT EXISTS notes TEXT;

-- Ensure proper data types and constraints
ALTER TABLE staff 
    MODIFY COLUMN username VARCHAR(50) NOT NULL UNIQUE,
    MODIFY COLUMN email VARCHAR(100) UNIQUE,
    MODIFY COLUMN service_number VARCHAR(50) UNIQUE,
    MODIFY COLUMN role ENUM('admin', 'user', 'manager', 'commander', 'instructor', 'medical_officer') DEFAULT 'user',
    MODIFY COLUMN accStatus ENUM('active', 'inactive', 'suspended', 'retired', 'transferred') DEFAULT 'active',
    MODIFY COLUMN gender ENUM('Male', 'Female', 'Other'),
    MODIFY COLUMN marital_status ENUM('Single', 'Married', 'Divorced', 'Widowed', 'Separated');

-- Ensure ranks table is properly structured
CREATE TABLE IF NOT EXISTS ranks (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    abbreviation VARCHAR(10) NOT NULL,
    rank_order INT NOT NULL,
    category ENUM('Officer', 'NCO', 'Enlisted') NOT NULL,
    pay_grade VARCHAR(10),
    description TEXT,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_rank_name (name),
    UNIQUE KEY unique_rank_abbr (abbreviation),
    INDEX idx_rank_order (rank_order),
    INDEX idx_rank_category (category)
);

-- Ensure units table is properly structured  
CREATE TABLE IF NOT EXISTS units (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(200) NOT NULL,
    code VARCHAR(20) NOT NULL UNIQUE,
    type VARCHAR(50),
    parent_unit_id INT,
    commander_id INT,
    location VARCHAR(200),
    establishment_date DATE,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (parent_unit_id) REFERENCES units(id) ON DELETE SET NULL,
    FOREIGN KEY (commander_id) REFERENCES staff(id) ON DELETE SET NULL,
    INDEX idx_unit_type (type),
    INDEX idx_unit_parent (parent_unit_id),
    INDEX idx_unit_commander (commander_id)
);

-- Ensure corps table is properly structured
CREATE TABLE IF NOT EXISTS corps (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL UNIQUE,
    abbreviation VARCHAR(10) NOT NULL UNIQUE,
    description TEXT,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_corps_abbr (abbreviation)
);

-- Add proper foreign key constraints
ALTER TABLE staff 
    ADD CONSTRAINT fk_staff_rank FOREIGN KEY (rank_id) REFERENCES ranks(id) ON DELETE SET NULL,
    ADD CONSTRAINT fk_staff_unit FOREIGN KEY (unit_id) REFERENCES units(id) ON DELETE SET NULL;

-- Create audit log table for tracking all changes
CREATE TABLE IF NOT EXISTS audit_log (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT,
    table_name VARCHAR(50) NOT NULL,
    record_id INT NOT NULL,
    action ENUM('INSERT', 'UPDATE', 'DELETE') NOT NULL,
    field_name VARCHAR(50),
    old_value TEXT,
    new_value TEXT,
    ip_address VARCHAR(45),
    user_agent TEXT,
    timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES staff(id) ON DELETE SET NULL,
    INDEX idx_audit_user (user_id),
    INDEX idx_audit_table (table_name),
    INDEX idx_audit_record (record_id),
    INDEX idx_audit_timestamp (timestamp)
);

-- Create session management table
CREATE TABLE IF NOT EXISTS user_sessions (
    id VARCHAR(128) PRIMARY KEY,
    user_id INT NOT NULL,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_activity TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    expires_at TIMESTAMP,
    is_active BOOLEAN DEFAULT TRUE,
    FOREIGN KEY (user_id) REFERENCES staff(id) ON DELETE CASCADE,
    INDEX idx_session_user (user_id),
    INDEX idx_session_activity (last_activity),
    INDEX idx_session_expires (expires_at)
);

-- Create CSRF tokens table
CREATE TABLE IF NOT EXISTS csrf_tokens (
    token VARCHAR(64) PRIMARY KEY,
    user_id INT NOT NULL,
    expires_at TIMESTAMP NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES staff(id) ON DELETE CASCADE,
    INDEX idx_csrf_user (user_id),
    INDEX idx_csrf_expires (expires_at)
);

-- Insert sample ranks if table is empty
INSERT IGNORE INTO ranks (name, abbreviation, rank_order, category, pay_grade) VALUES
-- Officers
('General', 'GEN', 100, 'Officer', 'O-10'),
('Lieutenant General', 'LT GEN', 95, 'Officer', 'O-9'),
('Major General', 'MAJ GEN', 90, 'Officer', 'O-8'),
('Brigadier General', 'BRIG GEN', 85, 'Officer', 'O-7'),
('Colonel', 'COL', 80, 'Officer', 'O-6'),
('Lieutenant Colonel', 'LT COL', 75, 'Officer', 'O-5'),
('Major', 'MAJ', 70, 'Officer', 'O-4'),
('Captain', 'CAPT', 65, 'Officer', 'O-3'),
('First Lieutenant', '1LT', 60, 'Officer', 'O-2'),
('Second Lieutenant', '2LT', 55, 'Officer', 'O-1'),
-- NCOs
('Sergeant Major', 'SGM', 50, 'NCO', 'E-9'),
('Master Sergeant', 'MSG', 45, 'NCO', 'E-8'),
('Sergeant First Class', 'SFC', 40, 'NCO', 'E-7'),
('Staff Sergeant', 'SSG', 35, 'NCO', 'E-6'),
('Sergeant', 'SGT', 30, 'NCO', 'E-5'),
('Corporal', 'CPL', 25, 'NCO', 'E-4'),
-- Enlisted
('Specialist', 'SPC', 20, 'Enlisted', 'E-4'),
('Private First Class', 'PFC', 15, 'Enlisted', 'E-3'),
('Private', 'PVT', 10, 'Enlisted', 'E-2'),
('Recruit', 'RCT', 5, 'Enlisted', 'E-1');

-- Insert sample units if table is empty
INSERT IGNORE INTO units (name, code, type, location) VALUES
('Headquarters Battalion', 'HQ-BTN', 'Command', 'Lusaka'),
('1st Infantry Battalion', '1-INF', 'Infantry', 'Kabwe'),
('2nd Infantry Battalion', '2-INF', 'Infantry', 'Ndola'),
('Artillery Regiment', 'ARTY-REG', 'Artillery', 'Livingstone'),
('Engineer Battalion', 'ENG-BTN', 'Engineering', 'Chipata'),
('Signal Battalion', 'SIG-BTN', 'Communications', 'Kitwe'),
('Medical Battalion', 'MED-BTN', 'Medical', 'Lusaka'),
('Military Police Battalion', 'MP-BTN', 'Military Police', 'Lusaka');

-- Insert sample corps if table is empty
INSERT IGNORE INTO corps (name, abbreviation, description) VALUES
('Infantry', 'INF', 'Ground combat forces'),
('Artillery', 'ARTY', 'Heavy weapons and fire support'),
('Armor', 'ARM', 'Tank and armored vehicle operations'),
('Engineers', 'ENG', 'Construction and demolition'),
('Signal', 'SIG', 'Communications and IT'),
('Medical', 'MED', 'Healthcare and medical support'),
('Military Police', 'MP', 'Law enforcement and security'),
('Intelligence', 'INT', 'Information gathering and analysis'),
('Logistics', 'LOG', 'Supply and transportation'),
('Aviation', 'AV', 'Aircraft operations and maintenance');

-- Create optimized indexes for performance
CREATE INDEX IF NOT EXISTS idx_staff_service_number ON staff(service_number);
CREATE INDEX IF NOT EXISTS idx_staff_rank ON staff(rank_id);
CREATE INDEX IF NOT EXISTS idx_staff_unit ON staff(unit_id);
CREATE INDEX IF NOT EXISTS idx_staff_status ON staff(service_status);
CREATE INDEX IF NOT EXISTS idx_staff_name ON staff(first_name, last_name);
CREATE INDEX IF NOT EXISTS idx_staff_email ON staff(email);
CREATE INDEX IF NOT EXISTS idx_staff_last_login ON staff(last_login);
CREATE INDEX IF NOT EXISTS idx_staff_created ON staff(created_at);
CREATE INDEX IF NOT EXISTS idx_staff_active ON staff(is_active);

-- Update profile completion percentage for existing users
UPDATE staff SET profile_completion_percentage = (
    CASE 
        WHEN first_name IS NOT NULL AND first_name != '' THEN 10 ELSE 0 END +
    CASE 
        WHEN last_name IS NOT NULL AND last_name != '' THEN 10 ELSE 0 END +
    CASE 
        WHEN email IS NOT NULL AND email != '' THEN 10 ELSE 0 END +
    CASE 
        WHEN phone IS NOT NULL AND phone != '' THEN 10 ELSE 0 END +
    CASE 
        WHEN date_of_birth IS NOT NULL THEN 10 ELSE 0 END +
    CASE 
        WHEN national_id IS NOT NULL AND national_id != '' THEN 10 ELSE 0 END +
    CASE 
        WHEN rank_id IS NOT NULL THEN 10 ELSE 0 END +
    CASE 
        WHEN unit_id IS NOT NULL THEN 10 ELSE 0 END +
    CASE 
        WHEN blood_group IS NOT NULL AND blood_group != '' THEN 10 ELSE 0 END +
    CASE 
        WHEN marital_status IS NOT NULL AND marital_status != '' THEN 10 ELSE 0 END
) WHERE profile_completion_percentage = 0;

