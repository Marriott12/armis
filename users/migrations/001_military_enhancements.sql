-- Military-Grade User Profile Enhancements
-- Additional tables for comprehensive military personnel management

-- Military Security Clearances Table
CREATE TABLE IF NOT EXISTS staff_security_clearance (
    id INT AUTO_INCREMENT PRIMARY KEY,
    staff_id INT NOT NULL,
    clearance_level ENUM('Confidential', 'Secret', 'Top Secret', 'SCI', 'Cosmic Top Secret') NOT NULL,
    clearance_authority VARCHAR(100) NOT NULL,
    issue_date DATE NOT NULL,
    expiry_date DATE NOT NULL,
    status ENUM('Active', 'Suspended', 'Expired', 'Revoked') DEFAULT 'Active',
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (staff_id) REFERENCES staff(id) ON DELETE CASCADE,
    INDEX idx_staff_clearance_staff_id (staff_id),
    INDEX idx_staff_clearance_level (clearance_level),
    INDEX idx_staff_clearance_status (status)
);

-- Military Awards and Decorations Table
CREATE TABLE IF NOT EXISTS staff_awards (
    id INT AUTO_INCREMENT PRIMARY KEY,
    staff_id INT NOT NULL,
    award_name VARCHAR(200) NOT NULL,
    award_type ENUM('Medal', 'Ribbon', 'Badge', 'Citation', 'Commendation', 'Certificate') NOT NULL,
    award_category ENUM('Combat', 'Service', 'Training', 'Achievement', 'Unit', 'Campaign') NOT NULL,
    awarding_authority VARCHAR(150) NOT NULL,
    award_date DATE NOT NULL,
    certificate_number VARCHAR(100),
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (staff_id) REFERENCES staff(id) ON DELETE CASCADE,
    INDEX idx_staff_awards_staff_id (staff_id),
    INDEX idx_staff_awards_type (award_type),
    INDEX idx_staff_awards_category (award_category)
);

-- Medical Fitness Records Table
CREATE TABLE IF NOT EXISTS staff_medical_fitness (
    id INT AUTO_INCREMENT PRIMARY KEY,
    staff_id INT NOT NULL,
    examination_date DATE NOT NULL,
    fitness_category ENUM('A1', 'A2', 'B1', 'B2', 'C1', 'C2', 'D') NOT NULL,
    medical_officer VARCHAR(150) NOT NULL,
    height_cm DECIMAL(5,2),
    weight_kg DECIMAL(5,2),
    blood_pressure VARCHAR(20),
    vision_left VARCHAR(20),
    vision_right VARCHAR(20),
    hearing_status ENUM('Normal', 'Impaired', 'Requires Aid') DEFAULT 'Normal',
    deployment_restrictions TEXT,
    next_examination_date DATE,
    medical_certificate_number VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (staff_id) REFERENCES staff(id) ON DELETE CASCADE,
    INDEX idx_staff_medical_staff_id (staff_id),
    INDEX idx_staff_medical_category (fitness_category),
    INDEX idx_staff_medical_next_exam (next_examination_date)
);

-- Training Compliance Tracking Table
CREATE TABLE IF NOT EXISTS staff_training_compliance (
    id INT AUTO_INCREMENT PRIMARY KEY,
    staff_id INT NOT NULL,
    training_category ENUM('Basic Military', 'Combat', 'Leadership', 'Technical', 'Safety', 'Specialized') NOT NULL,
    course_name VARCHAR(200) NOT NULL,
    completion_date DATE NOT NULL,
    expiry_date DATE,
    instructor VARCHAR(150),
    score DECIMAL(5,2),
    certification_number VARCHAR(100),
    compliance_status ENUM('Current', 'Expired', 'Due', 'Overdue') DEFAULT 'Current',
    renewal_required BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (staff_id) REFERENCES staff(id) ON DELETE CASCADE,
    INDEX idx_staff_training_staff_id (staff_id),
    INDEX idx_staff_training_category (training_category),
    INDEX idx_staff_training_status (compliance_status),
    INDEX idx_staff_training_expiry (expiry_date)
);

-- Service Record Enhancements Table
CREATE TABLE IF NOT EXISTS staff_service_record (
    id INT AUTO_INCREMENT PRIMARY KEY,
    staff_id INT NOT NULL,
    enlistment_date DATE NOT NULL,
    commission_date DATE,
    retirement_date DATE,
    service_type ENUM('Regular', 'Reserve', 'National Service', 'Volunteer') NOT NULL,
    current_status ENUM('Active', 'Reserve', 'Retired', 'Discharged', 'AWOL', 'Medical Leave') DEFAULT 'Active',
    total_service_years DECIMAL(4,2) DEFAULT 0,
    overseas_service_years DECIMAL(4,2) DEFAULT 0,
    deployment_count INT DEFAULT 0,
    promotion_eligibility_date DATE,
    next_posting_date DATE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (staff_id) REFERENCES staff(id) ON DELETE CASCADE,
    INDEX idx_staff_service_staff_id (staff_id),
    INDEX idx_staff_service_status (current_status),
    INDEX idx_staff_service_years (total_service_years)
);

-- Enhanced CV Management Table
CREATE TABLE IF NOT EXISTS staff_cv_versions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    staff_id INT NOT NULL,
    version_number INT NOT NULL,
    filename VARCHAR(255) NOT NULL,
    file_path VARCHAR(500) NOT NULL,
    file_type ENUM('PDF', 'DOC', 'DOCX') NOT NULL,
    file_size INT NOT NULL,
    template_used VARCHAR(100),
    auto_generated BOOLEAN DEFAULT FALSE,
    extracted_data JSON,
    digital_signature_hash VARCHAR(255),
    is_current_version BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (staff_id) REFERENCES staff(id) ON DELETE CASCADE,
    INDEX idx_staff_cv_staff_id (staff_id),
    INDEX idx_staff_cv_current (is_current_version),
    INDEX idx_staff_cv_version (version_number)
);