-- ARMIS Phase 1: Military-Specific Tables
-- Create comprehensive military data tables for enhanced user profiles

-- Security Clearances Table
CREATE TABLE IF NOT EXISTS security_clearances (
    id INT PRIMARY KEY AUTO_INCREMENT,
    staff_id INT NOT NULL,
    clearance_level ENUM('Confidential', 'Secret', 'Top Secret', 'SCI') NOT NULL,
    granted_date DATE NOT NULL,
    expiry_date DATE NOT NULL,
    granting_authority VARCHAR(200) NOT NULL,
    investigation_type VARCHAR(100),
    access_categories TEXT COMMENT 'JSON array of specific access categories',
    restrictions TEXT COMMENT 'Any specific restrictions',
    is_active BOOLEAN DEFAULT TRUE,
    revoked_date DATE NULL,
    revocation_reason TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (staff_id) REFERENCES staff(id) ON DELETE CASCADE,
    INDEX idx_clearances_staff_id (staff_id),
    INDEX idx_clearances_level (clearance_level),
    INDEX idx_clearances_expiry (expiry_date),
    INDEX idx_clearances_active (is_active)
);

-- Service Records Table
CREATE TABLE IF NOT EXISTS service_records (
    id INT PRIMARY KEY AUTO_INCREMENT,
    staff_id INT NOT NULL,
    record_type ENUM('Enlistment', 'Promotion', 'Transfer', 'Deployment', 'Award', 'Discipline', 'Training', 'Medical', 'Leave') NOT NULL,
    record_date DATE NOT NULL,
    title VARCHAR(200) NOT NULL,
    description TEXT,
    location VARCHAR(200),
    from_rank_id INT NULL,
    to_rank_id INT NULL,
    from_unit_id INT NULL,
    to_unit_id INT NULL,
    deployment_country VARCHAR(100),
    deployment_duration_months INT,
    award_type VARCHAR(100),
    disciplinary_action VARCHAR(200),
    training_course VARCHAR(200),
    training_institution VARCHAR(200),
    medical_category VARCHAR(100),
    leave_type VARCHAR(50),
    leave_days INT,
    approving_officer_id INT,
    reference_number VARCHAR(100),
    documentation_path VARCHAR(255),
    is_confidential BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (staff_id) REFERENCES staff(id) ON DELETE CASCADE,
    FOREIGN KEY (from_rank_id) REFERENCES ranks(id) ON DELETE SET NULL,
    FOREIGN KEY (to_rank_id) REFERENCES ranks(id) ON DELETE SET NULL,
    FOREIGN KEY (from_unit_id) REFERENCES units(id) ON DELETE SET NULL,
    FOREIGN KEY (to_unit_id) REFERENCES units(id) ON DELETE SET NULL,
    FOREIGN KEY (approving_officer_id) REFERENCES staff(id) ON DELETE SET NULL,
    INDEX idx_service_records_staff_id (staff_id),
    INDEX idx_service_records_type (record_type),
    INDEX idx_service_records_date (record_date),
    INDEX idx_service_records_confidential (is_confidential)
);

-- Medical Records Table
CREATE TABLE IF NOT EXISTS medical_records (
    id INT PRIMARY KEY AUTO_INCREMENT,
    staff_id INT NOT NULL,
    medical_exam_date DATE NOT NULL,
    fitness_category ENUM('A1', 'A2', 'B1', 'B2', 'C1', 'C2', 'D', 'E') NOT NULL COMMENT 'Military fitness categories',
    overall_status ENUM('Fit', 'Limited', 'Unfit', 'Pending Review') NOT NULL,
    height_cm INT,
    weight_kg DECIMAL(5,2),
    vision_category VARCHAR(10),
    hearing_category VARCHAR(10),
    blood_pressure VARCHAR(20),
    medical_restrictions TEXT,
    medications TEXT,
    allergies TEXT,
    immunizations TEXT COMMENT 'JSON array of immunizations with dates',
    next_exam_due DATE,
    examining_physician VARCHAR(200),
    medical_facility VARCHAR(200),
    is_deployment_ready BOOLEAN DEFAULT TRUE,
    confidential_notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (staff_id) REFERENCES staff(id) ON DELETE CASCADE,
    INDEX idx_medical_records_staff_id (staff_id),
    INDEX idx_medical_records_status (overall_status),
    INDEX idx_medical_records_fitness (fitness_category),
    INDEX idx_medical_records_next_exam (next_exam_due),
    INDEX idx_medical_records_deployment_ready (is_deployment_ready)
);

-- Training Compliance Table
CREATE TABLE IF NOT EXISTS training_compliance (
    id INT PRIMARY KEY AUTO_INCREMENT,
    staff_id INT NOT NULL,
    training_type ENUM('Mandatory', 'Professional', 'Specialized', 'Leadership', 'Technical', 'Safety', 'Security') NOT NULL,
    training_name VARCHAR(200) NOT NULL,
    requirement_frequency_months INT NOT NULL COMMENT 'How often training is required',
    last_completed_date DATE,
    completion_score DECIMAL(5,2),
    next_due_date DATE,
    compliance_status ENUM('Current', 'Due Soon', 'Overdue', 'Exempt') NOT NULL,
    certifying_authority VARCHAR(200),
    certificate_number VARCHAR(100),
    certificate_expiry_date DATE,
    training_hours DECIMAL(5,2),
    instructor_name VARCHAR(200),
    training_location VARCHAR(200),
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (staff_id) REFERENCES staff(id) ON DELETE CASCADE,
    INDEX idx_training_compliance_staff_id (staff_id),
    INDEX idx_training_compliance_type (training_type),
    INDEX idx_training_compliance_status (compliance_status),
    INDEX idx_training_compliance_due_date (next_due_date),
    UNIQUE KEY unique_staff_training (staff_id, training_name)
);

-- Family Readiness Table (Enhanced)
CREATE TABLE IF NOT EXISTS family_readiness (
    id INT PRIMARY KEY AUTO_INCREMENT,
    staff_id INT NOT NULL,
    family_care_plan_status ENUM('Not Required', 'Required', 'Submitted', 'Approved', 'Needs Update') DEFAULT 'Not Required',
    family_care_plan_date DATE,
    emergency_contacts_updated BOOLEAN DEFAULT FALSE,
    spouse_employment_status VARCHAR(100),
    children_count INT DEFAULT 0,
    dependent_special_needs TEXT,
    family_support_group_member BOOLEAN DEFAULT FALSE,
    deployment_family_briefing_date DATE,
    relocation_assistance_needed BOOLEAN DEFAULT FALSE,
    family_readiness_score ENUM('High', 'Medium', 'Low', 'Unknown') DEFAULT 'Unknown',
    last_assessment_date DATE,
    assessment_notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (staff_id) REFERENCES staff(id) ON DELETE CASCADE,
    INDEX idx_family_readiness_staff_id (staff_id),
    INDEX idx_family_readiness_plan_status (family_care_plan_status),
    INDEX idx_family_readiness_score (family_readiness_score),
    UNIQUE KEY unique_staff_family_readiness (staff_id)
);

-- Profile Completion Tracking
CREATE TABLE IF NOT EXISTS profile_completion (
    id INT PRIMARY KEY AUTO_INCREMENT,
    staff_id INT NOT NULL,
    section VARCHAR(50) NOT NULL,
    completion_percentage DECIMAL(5,2) NOT NULL,
    required_fields_total INT NOT NULL,
    completed_fields INT NOT NULL,
    last_updated_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (staff_id) REFERENCES staff(id) ON DELETE CASCADE,
    INDEX idx_profile_completion_staff_id (staff_id),
    INDEX idx_profile_completion_section (section),
    UNIQUE KEY unique_staff_section (staff_id, section)
);

-- Enhanced Audit Log for Profile Changes
CREATE TABLE IF NOT EXISTS profile_audit_log (
    id INT PRIMARY KEY AUTO_INCREMENT,
    staff_id INT NOT NULL,
    changed_by_staff_id INT NOT NULL,
    table_name VARCHAR(100) NOT NULL,
    record_id INT,
    field_name VARCHAR(100),
    old_value TEXT,
    new_value TEXT,
    change_type ENUM('INSERT', 'UPDATE', 'DELETE') NOT NULL,
    change_reason VARCHAR(500),
    ip_address VARCHAR(45),
    user_agent TEXT,
    session_id VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (staff_id) REFERENCES staff(id) ON DELETE CASCADE,
    FOREIGN KEY (changed_by_staff_id) REFERENCES staff(id) ON DELETE CASCADE,
    INDEX idx_profile_audit_staff_id (staff_id),
    INDEX idx_profile_audit_changed_by (changed_by_staff_id),
    INDEX idx_profile_audit_table (table_name),
    INDEX idx_profile_audit_created (created_at),
    INDEX idx_profile_audit_change_type (change_type)
);

-- CSRF Tokens Table
CREATE TABLE IF NOT EXISTS csrf_tokens (
    id INT PRIMARY KEY AUTO_INCREMENT,
    token VARCHAR(255) NOT NULL UNIQUE,
    staff_id INT NOT NULL,
    expires_at TIMESTAMP NOT NULL,
    used BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (staff_id) REFERENCES staff(id) ON DELETE CASCADE,
    INDEX idx_csrf_tokens_token (token),
    INDEX idx_csrf_tokens_staff_id (staff_id),
    INDEX idx_csrf_tokens_expires (expires_at)
);

-- Document Management for Profile Documents
CREATE TABLE IF NOT EXISTS profile_documents (
    id INT PRIMARY KEY AUTO_INCREMENT,
    staff_id INT NOT NULL,
    document_type ENUM('Photo', 'CV', 'Certificate', 'Medical', 'Security', 'Training', 'Other') NOT NULL,
    document_name VARCHAR(255) NOT NULL,
    original_filename VARCHAR(255) NOT NULL,
    file_path VARCHAR(500) NOT NULL,
    file_size INT NOT NULL,
    mime_type VARCHAR(100) NOT NULL,
    security_classification ENUM('Unclassified', 'Restricted', 'Confidential', 'Secret') DEFAULT 'Unclassified',
    upload_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    uploaded_by_staff_id INT NOT NULL,
    approval_status ENUM('Pending', 'Approved', 'Rejected') DEFAULT 'Pending',
    approved_by_staff_id INT NULL,
    approval_date TIMESTAMP NULL,
    expiry_date DATE NULL,
    access_level ENUM('Self', 'Unit', 'Command', 'System') DEFAULT 'Self',
    virus_scan_status ENUM('Pending', 'Clean', 'Infected', 'Error') DEFAULT 'Pending',
    virus_scan_date TIMESTAMP NULL,
    is_active BOOLEAN DEFAULT TRUE,
    FOREIGN KEY (staff_id) REFERENCES staff(id) ON DELETE CASCADE,
    FOREIGN KEY (uploaded_by_staff_id) REFERENCES staff(id) ON DELETE CASCADE,
    FOREIGN KEY (approved_by_staff_id) REFERENCES staff(id) ON DELETE SET NULL,
    INDEX idx_profile_documents_staff_id (staff_id),
    INDEX idx_profile_documents_type (document_type),
    INDEX idx_profile_documents_classification (security_classification),
    INDEX idx_profile_documents_approval_status (approval_status),
    INDEX idx_profile_documents_access_level (access_level)
);