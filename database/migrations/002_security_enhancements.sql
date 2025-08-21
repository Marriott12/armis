-- ARMIS Security and Audit Enhancement
-- Add military-grade security and audit logging capabilities

-- Add missing military-grade columns to staff table (ignoring errors if columns exist)
SET sql_mode = '';

ALTER TABLE staff ADD COLUMN middle_name VARCHAR(100) AFTER first_name;
ALTER TABLE staff ADD COLUMN prefix VARCHAR(20) FIRST;
ALTER TABLE staff ADD COLUMN suffix VARCHAR(20) AFTER last_name;
ALTER TABLE staff ADD COLUMN nationality VARCHAR(50) DEFAULT 'Zambian';
ALTER TABLE staff ADD COLUMN place_of_birth VARCHAR(100);
ALTER TABLE staff ADD COLUMN emergency_contact_name VARCHAR(100);
ALTER TABLE staff ADD COLUMN emergency_contact_phone VARCHAR(20);
ALTER TABLE staff ADD COLUMN emergency_contact_relationship VARCHAR(50);
ALTER TABLE staff ADD COLUMN security_clearance_level VARCHAR(50);
ALTER TABLE staff ADD COLUMN clearance_expiry_date DATE;
ALTER TABLE staff ADD COLUMN medical_fitness_status VARCHAR(50) DEFAULT 'Fit';
ALTER TABLE staff ADD COLUMN last_medical_exam DATE;
ALTER TABLE staff ADD COLUMN next_medical_due DATE;
ALTER TABLE staff ADD COLUMN profile_completion_percentage INT DEFAULT 0;
ALTER TABLE staff ADD COLUMN is_active BOOLEAN DEFAULT TRUE;
ALTER TABLE staff ADD COLUMN notes TEXT;

-- Update role enum to include military roles
ALTER TABLE staff 
    MODIFY COLUMN role ENUM('admin', 'user', 'manager', 'commander', 'instructor', 'medical_officer') DEFAULT 'user',
    MODIFY COLUMN accStatus ENUM('active', 'inactive', 'suspended', 'retired', 'transferred') DEFAULT 'active';

-- Standardize column names with aliases (keeping both for compatibility)
ALTER TABLE staff 
    CHANGE COLUMN nrc national_id VARCHAR(20),
    CHANGE COLUMN dob date_of_birth DATE,
    CHANGE COLUMN attestDate enlistment_date DATE,
    CHANGE COLUMN tel phone VARCHAR(20),
    CHANGE COLUMN bloodGp blood_group VARCHAR(5),
    CHANGE COLUMN svcStatus service_status VARCHAR(20) DEFAULT 'active';

-- Add proper constraints and indexes
ALTER TABLE staff 
    MODIFY COLUMN email VARCHAR(100) UNIQUE,
    MODIFY COLUMN service_number VARCHAR(50) UNIQUE,
    MODIFY COLUMN gender ENUM('Male', 'Female', 'Other'),
    MODIFY COLUMN marital_status ENUM('Single', 'Married', 'Divorced', 'Widowed', 'Separated');

-- Ensure ranks table has proper military structure (ignoring errors if columns exist)
ALTER TABLE ranks ADD COLUMN rank_order INT NOT NULL DEFAULT 0;
ALTER TABLE ranks ADD COLUMN category ENUM('Officer', 'NCO', 'Enlisted') NOT NULL DEFAULT 'Enlisted';
ALTER TABLE ranks ADD COLUMN pay_grade VARCHAR(10);
ALTER TABLE ranks ADD COLUMN description TEXT;
ALTER TABLE ranks ADD COLUMN is_active BOOLEAN DEFAULT TRUE;
ALTER TABLE ranks ADD COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP;
ALTER TABLE ranks ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;

-- Ensure units table has proper structure
ALTER TABLE units ADD COLUMN parent_unit_id INT;
ALTER TABLE units ADD COLUMN commander_id INT;
ALTER TABLE units ADD COLUMN location VARCHAR(200);
ALTER TABLE units ADD COLUMN establishment_date DATE;
ALTER TABLE units ADD COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP;
ALTER TABLE units ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;

-- Ensure corps table has proper structure
ALTER TABLE corps ADD COLUMN description TEXT;
ALTER TABLE corps ADD COLUMN is_active BOOLEAN DEFAULT TRUE;
ALTER TABLE corps ADD COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP;
ALTER TABLE corps ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;

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

-- Add proper foreign key constraints (ignoring errors if they exist)
-- ALTER TABLE staff ADD CONSTRAINT fk_staff_rank FOREIGN KEY (rank_id) REFERENCES ranks(id) ON DELETE SET NULL;
-- ALTER TABLE staff ADD CONSTRAINT fk_staff_unit FOREIGN KEY (unit_id) REFERENCES units(id) ON DELETE SET NULL;

-- Add proper foreign keys to units table
-- ALTER TABLE units ADD CONSTRAINT fk_unit_parent FOREIGN KEY (parent_unit_id) REFERENCES units(id) ON DELETE SET NULL;
-- ALTER TABLE units ADD CONSTRAINT fk_unit_commander FOREIGN KEY (commander_id) REFERENCES staff(id) ON DELETE SET NULL;

-- Insert comprehensive military ranks if table is empty
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

-- Insert sample military units
INSERT IGNORE INTO units (name, code, type, location) VALUES
('Headquarters Battalion', 'HQ-BTN', 'Command', 'Lusaka'),
('1st Infantry Battalion', '1-INF', 'Infantry', 'Kabwe'),
('2nd Infantry Battalion', '2-INF', 'Infantry', 'Ndola'),
('Artillery Regiment', 'ARTY-REG', 'Artillery', 'Livingstone'),
('Engineer Battalion', 'ENG-BTN', 'Engineering', 'Chipata'),
('Signal Battalion', 'SIG-BTN', 'Communications', 'Kitwe'),
('Medical Battalion', 'MED-BTN', 'Medical', 'Lusaka'),
('Military Police Battalion', 'MP-BTN', 'Military Police', 'Lusaka');

-- Insert military corps
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