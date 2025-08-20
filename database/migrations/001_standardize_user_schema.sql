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