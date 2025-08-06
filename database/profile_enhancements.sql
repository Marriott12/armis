-- Profile Enhancement Tables
-- Run this to add new tables for profile photos, CV processing, and additional profile data

-- Add profile_photo column to staff table if it doesn't exist
ALTER TABLE staff 
ADD COLUMN profile_photo VARCHAR(255) NULL AFTER email,
ADD COLUMN last_profile_update TIMESTAMP NULL;

-- Staff CVs table for uploaded CV files
CREATE TABLE IF NOT EXISTS staff_cvs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    staff_id INT NOT NULL,
    filename VARCHAR(255) NOT NULL,
    original_name VARCHAR(255) NOT NULL,
    file_type VARCHAR(100) NOT NULL,
    file_size INT NOT NULL,
    extracted_data TEXT NULL,
    upload_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    is_verified BOOLEAN DEFAULT FALSE,
    applied_date TIMESTAMP NULL,
    FOREIGN KEY (staff_id) REFERENCES staff(id) ON DELETE CASCADE
);

-- Staff Skills table
CREATE TABLE IF NOT EXISTS staff_skills (
    id INT PRIMARY KEY AUTO_INCREMENT,
    staff_id INT NOT NULL,
    skill_name VARCHAR(100) NOT NULL,
    skill_level ENUM('Beginner', 'Intermediate', 'Advanced', 'Expert') DEFAULT 'Intermediate',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (staff_id) REFERENCES staff(id) ON DELETE CASCADE
);

-- Staff Certifications table
CREATE TABLE IF NOT EXISTS staff_certifications (
    id INT PRIMARY KEY AUTO_INCREMENT,
    staff_id INT NOT NULL,
    certification_name VARCHAR(150) NOT NULL,
    issuer VARCHAR(100) NULL,
    issue_date DATE NULL,
    expiry_date DATE NULL,
    is_verified BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (staff_id) REFERENCES staff(id) ON DELETE CASCADE
);

-- Enhanced staff_contact_info table (if not exists)
CREATE TABLE IF NOT EXISTS staff_contact_info (
    id INT PRIMARY KEY AUTO_INCREMENT,
    staff_id INT NOT NULL,
    contact_type ENUM('Mobile', 'Home', 'Work', 'Email', 'Emergency') NOT NULL,
    contact_value VARCHAR(150) NOT NULL,
    is_primary BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (staff_id) REFERENCES staff(id) ON DELETE CASCADE
);

-- Enhanced staff_addresses table (if not exists)
CREATE TABLE IF NOT EXISTS staff_addresses (
    id INT PRIMARY KEY AUTO_INCREMENT,
    staff_id INT NOT NULL,
    address_type ENUM('Home', 'Work', 'Permanent', 'Temporary') NOT NULL,
    street_address TEXT NULL,
    city VARCHAR(100) NULL,
    province VARCHAR(100) NULL,
    postal_code VARCHAR(20) NULL,
    country VARCHAR(100) DEFAULT 'Zambia',
    is_primary BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (staff_id) REFERENCES staff(id) ON DELETE CASCADE
);

-- Enhanced staff_education table (if not exists)
CREATE TABLE IF NOT EXISTS staff_education (
    id INT PRIMARY KEY AUTO_INCREMENT,
    staff_id INT NOT NULL,
    qualification VARCHAR(150) NOT NULL,
    institution VARCHAR(150) NULL,
    level ENUM('Primary', 'Secondary', 'Certificate', 'Diploma', 'Bachelor', 'Master', 'PhD', 'Other') DEFAULT 'Other',
    field_of_study VARCHAR(100) NULL,
    year_started YEAR NULL,
    year_completed YEAR NULL,
    grade_gpa VARCHAR(20) NULL,
    is_verified BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (staff_id) REFERENCES staff(id) ON DELETE CASCADE
);

-- Enhanced staff_family_members table (if not exists)
CREATE TABLE IF NOT EXISTS staff_family_members (
    id INT PRIMARY KEY AUTO_INCREMENT,
    staff_id INT NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    relationship ENUM('Spouse', 'Child', 'Parent', 'Sibling', 'Other') NOT NULL,
    date_of_birth DATE NULL,
    phone VARCHAR(20) NULL,
    email VARCHAR(100) NULL,
    address TEXT NULL,
    is_emergency_contact BOOLEAN DEFAULT FALSE,
    is_dependent BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (staff_id) REFERENCES staff(id) ON DELETE CASCADE
);

-- Activity log table for profile updates
CREATE TABLE IF NOT EXISTS staff_activity_log (
    id INT PRIMARY KEY AUTO_INCREMENT,
    staff_id INT NOT NULL,
    activity_type ENUM('profile_update', 'photo_upload', 'cv_upload', 'contact_update', 'education_update') NOT NULL,
    description TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (staff_id) REFERENCES staff(id) ON DELETE CASCADE
);

-- Add indexes for performance
CREATE INDEX idx_staff_cvs_staff_id ON staff_cvs(staff_id);
CREATE INDEX idx_staff_skills_staff_id ON staff_skills(staff_id);
CREATE INDEX idx_staff_certifications_staff_id ON staff_certifications(staff_id);
CREATE INDEX idx_staff_contact_staff_id ON staff_contact_info(staff_id);
CREATE INDEX idx_staff_addresses_staff_id ON staff_addresses(staff_id);
CREATE INDEX idx_staff_education_staff_id ON staff_education(staff_id);
CREATE INDEX idx_staff_family_staff_id ON staff_family_members(staff_id);
CREATE INDEX idx_staff_activity_staff_id ON staff_activity_log(staff_id);
