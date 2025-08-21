-- ARMIS Phase 1: Database Schema Standardization (Safe Migration)
-- Standardize user profile tables with military-grade requirements

-- First, check and add staff table columns that don't exist
-- Use individual ALTER statements to avoid IF NOT EXISTS syntax issues

-- Basic profile fields
SELECT 'Adding staff table enhancements...' as status;

-- Check if columns exist before adding (will error if column exists, but that's ok)
-- Personal Information
ALTER TABLE staff ADD COLUMN DOB DATE COMMENT 'Date of Birth';
ALTER TABLE staff ADD COLUMN gender ENUM('M', 'F', 'Other') COMMENT 'Gender';
ALTER TABLE staff ADD COLUMN NRC VARCHAR(20) COMMENT 'National Registration Card';
ALTER TABLE staff ADD COLUMN tel VARCHAR(20) COMMENT 'Telephone Number';
ALTER TABLE staff ADD COLUMN address TEXT COMMENT 'Residential Address';
ALTER TABLE staff ADD COLUMN bloodGp VARCHAR(5) COMMENT 'Blood Group';
ALTER TABLE staff ADD COLUMN marital ENUM('Single', 'Married', 'Divorced', 'Widowed') COMMENT 'Marital Status';
ALTER TABLE staff ADD COLUMN attestDate DATE COMMENT 'Enlistment/Attestation Date';
ALTER TABLE staff ADD COLUMN prefix VARCHAR(10) COMMENT 'Title/Prefix (Mr, Mrs, Dr, etc)';
ALTER TABLE staff ADD COLUMN middle_name VARCHAR(50) COMMENT 'Middle Name';
ALTER TABLE staff ADD COLUMN suffix VARCHAR(10) COMMENT 'Suffix (Jr, Sr, III, etc)';

-- Emergency Contact Information
ALTER TABLE staff ADD COLUMN emergency_contact_name VARCHAR(100) COMMENT 'Emergency Contact Name';
ALTER TABLE staff ADD COLUMN emergency_contact_phone VARCHAR(20) COMMENT 'Emergency Contact Phone';
ALTER TABLE staff ADD COLUMN emergency_contact_relationship VARCHAR(50) COMMENT 'Emergency Contact Relationship';

-- Military-Specific Fields
ALTER TABLE staff ADD COLUMN profile_picture VARCHAR(255) COMMENT 'Profile Picture Path';
ALTER TABLE staff ADD COLUMN security_clearance_level ENUM('None', 'Confidential', 'Secret', 'Top Secret') DEFAULT 'None';
ALTER TABLE staff ADD COLUMN clearance_expiry_date DATE COMMENT 'Security Clearance Expiry Date';
ALTER TABLE staff ADD COLUMN medical_status ENUM('Fit', 'Limited', 'Unfit', 'Pending') DEFAULT 'Pending';
ALTER TABLE staff ADD COLUMN medical_expiry_date DATE COMMENT 'Medical Fitness Expiry Date';
ALTER TABLE staff ADD COLUMN deployment_status ENUM('Available', 'Deployed', 'Training', 'Leave', 'Medical') DEFAULT 'Available';
ALTER TABLE staff ADD COLUMN next_promotion_eligible_date DATE COMMENT 'Next Promotion Eligible Date';

-- Add indexes for performance
ALTER TABLE staff ADD INDEX idx_staff_rank_id (rank_id);
ALTER TABLE staff ADD INDEX idx_staff_unit_id (unit_id);
ALTER TABLE staff ADD INDEX idx_staff_corps (corps);
ALTER TABLE staff ADD INDEX idx_staff_security_clearance (security_clearance_level);
ALTER TABLE staff ADD INDEX idx_staff_deployment_status (deployment_status);
ALTER TABLE staff ADD INDEX idx_staff_medical_status (medical_status);
ALTER TABLE staff ADD INDEX idx_staff_service_number (service_number);
ALTER TABLE staff ADD INDEX idx_staff_email (email);
ALTER TABLE staff ADD INDEX idx_staff_accStatus (accStatus);

-- Enhance ranks table
ALTER TABLE ranks ADD COLUMN rank_level INT COMMENT 'Numeric rank level for hierarchy';
ALTER TABLE ranks ADD COLUMN rank_category ENUM('Officer', 'NCO', 'Enlisted') COMMENT 'Rank Category';
ALTER TABLE ranks ADD COLUMN minimum_service_years INT DEFAULT 0 COMMENT 'Minimum service years for this rank';
ALTER TABLE ranks ADD COLUMN base_salary DECIMAL(10,2) COMMENT 'Base salary for this rank';
ALTER TABLE ranks ADD COLUMN is_active BOOLEAN DEFAULT TRUE COMMENT 'Whether rank is currently in use';
ALTER TABLE ranks ADD COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP;
ALTER TABLE ranks ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;

-- Enhance units table
ALTER TABLE units ADD COLUMN parent_unit_id INT COMMENT 'Parent unit for hierarchy';
ALTER TABLE units ADD COLUMN unit_type ENUM('Command', 'Battalion', 'Company', 'Platoon', 'Squad', 'Section') COMMENT 'Unit Type';
ALTER TABLE units ADD COLUMN commander_id INT COMMENT 'Unit Commander Staff ID';
ALTER TABLE units ADD COLUMN location VARCHAR(200) COMMENT 'Unit Location/Base';
ALTER TABLE units ADD COLUMN establishment_date DATE COMMENT 'Unit Establishment Date';
ALTER TABLE units ADD COLUMN operational_status ENUM('Active', 'Training', 'Standby', 'Disbanded') DEFAULT 'Active';
ALTER TABLE units ADD COLUMN is_active BOOLEAN DEFAULT TRUE COMMENT 'Whether unit is currently active';
ALTER TABLE units ADD COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP;
ALTER TABLE units ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;

-- Enhance corps table
ALTER TABLE corps ADD COLUMN corps_type ENUM('Combat', 'Combat Support', 'Combat Service Support', 'Special Forces') COMMENT 'Corps Type';
ALTER TABLE corps ADD COLUMN description TEXT COMMENT 'Corps Description';
ALTER TABLE corps ADD COLUMN motto VARCHAR(200) COMMENT 'Corps Motto';
ALTER TABLE corps ADD COLUMN color_code VARCHAR(7) COMMENT 'Corps Color Code for UI';
ALTER TABLE corps ADD COLUMN is_active BOOLEAN DEFAULT TRUE COMMENT 'Whether corps is currently active';
ALTER TABLE corps ADD COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP;
ALTER TABLE corps ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;

-- Create indexes for better performance
CREATE INDEX idx_ranks_level ON ranks(rank_level);
CREATE INDEX idx_ranks_category ON ranks(rank_category);
CREATE INDEX idx_units_type ON units(unit_type);
CREATE INDEX idx_units_status ON units(operational_status);
CREATE INDEX idx_corps_type ON corps(corps_type);

SELECT 'Staff table enhancements completed (errors for existing columns are normal)' as status;