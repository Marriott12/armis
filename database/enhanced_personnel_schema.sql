-- Enhanced ARMIS Database Schema for Comprehensive Personnel Management
-- Additional tables to support complete personnel data collection

-- Staff Family Members Table
CREATE TABLE IF NOT EXISTS staff_family_members (
    id INT PRIMARY KEY AUTO_INCREMENT,
    staff_id INT NOT NULL,
    name VARCHAR(100) NOT NULL,
    relationship VARCHAR(50) NOT NULL,
    date_of_birth DATE,
    gender ENUM('M', 'F', 'Other'),
    nrc VARCHAR(20),
    contact_number VARCHAR(20),
    occupation VARCHAR(100),
    is_dependent BOOLEAN DEFAULT FALSE,
    is_emergency_contact BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (staff_id) REFERENCES staff(id) ON DELETE CASCADE,
    INDEX idx_staff_family_staff_id (staff_id),
    INDEX idx_staff_family_relationship (relationship)
);

-- Staff Education Table
CREATE TABLE IF NOT EXISTS staff_education (
    id INT PRIMARY KEY AUTO_INCREMENT,
    staff_id INT NOT NULL,
    institution VARCHAR(200) NOT NULL,
    qualification VARCHAR(200),
    level ENUM('Primary', 'Secondary', 'Certificate', 'Diploma', 'Degree', 'Masters', 'PhD', 'Professional') NOT NULL,
    field_of_study VARCHAR(150),
    year_started YEAR,
    year_completed YEAR,
    grade_obtained VARCHAR(20),
    is_military_education BOOLEAN DEFAULT FALSE,
    certificate_number VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (staff_id) REFERENCES staff(id) ON DELETE CASCADE,
    INDEX idx_staff_education_staff_id (staff_id),
    INDEX idx_staff_education_level (level)
);

-- Staff Languages Table
CREATE TABLE IF NOT EXISTS staff_languages (
    id INT PRIMARY KEY AUTO_INCREMENT,
    staff_id INT NOT NULL,
    language VARCHAR(50) NOT NULL,
    proficiency_level ENUM('Beginner', 'Intermediate', 'Advanced', 'Native') NOT NULL,
    can_read BOOLEAN DEFAULT TRUE,
    can_write BOOLEAN DEFAULT TRUE,
    can_speak BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (staff_id) REFERENCES staff(id) ON DELETE CASCADE,
    UNIQUE KEY unique_staff_language (staff_id, language),
    INDEX idx_staff_languages_staff_id (staff_id)
);

-- Staff Medical Records Table
CREATE TABLE IF NOT EXISTS staff_medical_records (
    id INT PRIMARY KEY AUTO_INCREMENT,
    staff_id INT NOT NULL,
    medical_category ENUM('A1', 'A2', 'A3', 'B1', 'B2', 'B3', 'C1', 'C2', 'C3', 'Unfit') DEFAULT 'A1',
    blood_group VARCHAR(5) NOT NULL,
    height DECIMAL(5,2),
    weight DECIMAL(5,2),
    bmi DECIMAL(4,2) GENERATED ALWAYS AS (weight / ((height/100) * (height/100))) STORED,
    vision_left VARCHAR(10),
    vision_right VARCHAR(10),
    allergies TEXT,
    medical_conditions TEXT,
    medications TEXT,
    last_medical_exam DATE,
    next_medical_due DATE,
    medical_officer VARCHAR(100),
    fitness_status ENUM('Fit', 'Limited Duties', 'Medical Board', 'Unfit') DEFAULT 'Fit',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (staff_id) REFERENCES staff(id) ON DELETE CASCADE,
    INDEX idx_staff_medical_staff_id (staff_id),
    INDEX idx_staff_medical_category (medical_category)
);

-- Staff Contact Information Table
CREATE TABLE IF NOT EXISTS staff_contact_info (
    id INT PRIMARY KEY AUTO_INCREMENT,
    staff_id INT NOT NULL,
    contact_type ENUM('Home', 'Work', 'Mobile', 'Emergency', 'Email') NOT NULL,
    contact_value VARCHAR(100) NOT NULL,
    is_primary BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (staff_id) REFERENCES staff(id) ON DELETE CASCADE,
    INDEX idx_staff_contact_staff_id (staff_id),
    INDEX idx_staff_contact_type (contact_type)
);

-- Staff Addresses Table
CREATE TABLE IF NOT EXISTS staff_addresses (
    id INT PRIMARY KEY AUTO_INCREMENT,
    staff_id INT NOT NULL,
    address_type ENUM('Permanent', 'Current', 'Emergency', 'Work') NOT NULL,
    house_number VARCHAR(20),
    street_name VARCHAR(100),
    township VARCHAR(100),
    district VARCHAR(100),
    province VARCHAR(100),
    postal_code VARCHAR(20),
    country VARCHAR(100) DEFAULT 'Zambia',
    is_primary BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (staff_id) REFERENCES staff(id) ON DELETE CASCADE,
    INDEX idx_staff_addresses_staff_id (staff_id),
    INDEX idx_staff_addresses_type (address_type)
);

-- Staff Skills and Competencies Table
CREATE TABLE IF NOT EXISTS staff_skills (
    id INT PRIMARY KEY AUTO_INCREMENT,
    staff_id INT NOT NULL,
    skill_category ENUM('Technical', 'Leadership', 'Communication', 'Military', 'Professional', 'Other') NOT NULL,
    skill_name VARCHAR(100) NOT NULL,
    proficiency_level ENUM('Beginner', 'Intermediate', 'Advanced', 'Expert') NOT NULL,
    years_experience INT,
    certification VARCHAR(200),
    validated_by VARCHAR(100),
    validation_date DATE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (staff_id) REFERENCES staff(id) ON DELETE CASCADE,
    INDEX idx_staff_skills_staff_id (staff_id),
    INDEX idx_staff_skills_category (skill_category)
);

-- Staff Deployment History Table
CREATE TABLE IF NOT EXISTS staff_deployments (
    id INT PRIMARY KEY AUTO_INCREMENT,
    staff_id INT NOT NULL,
    deployment_name VARCHAR(200) NOT NULL,
    location VARCHAR(200) NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE,
    deployment_type ENUM('Operational', 'Training', 'Exercise', 'Peacekeeping', 'Humanitarian', 'Other') NOT NULL,
    role VARCHAR(100),
    unit_deployed_with VARCHAR(200),
    status ENUM('Deployed', 'Returned', 'Extended', 'Terminated') DEFAULT 'Deployed',
    remarks TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (staff_id) REFERENCES staff(id) ON DELETE CASCADE,
    INDEX idx_staff_deployments_staff_id (staff_id),
    INDEX idx_staff_deployments_status (status)
);

-- Staff Awards and Decorations Table
CREATE TABLE IF NOT EXISTS staff_awards (
    id INT PRIMARY KEY AUTO_INCREMENT,
    staff_id INT NOT NULL,
    award_name VARCHAR(200) NOT NULL,
    award_type ENUM('Medal', 'Badge', 'Certificate', 'Commendation', 'Other') NOT NULL,
    date_awarded DATE NOT NULL,
    awarding_authority VARCHAR(200),
    citation TEXT,
    award_number VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (staff_id) REFERENCES staff(id) ON DELETE CASCADE,
    INDEX idx_staff_awards_staff_id (staff_id),
    INDEX idx_staff_awards_type (award_type)
);

-- Staff Performance Reviews Table
CREATE TABLE IF NOT EXISTS staff_performance_reviews (
    id INT PRIMARY KEY AUTO_INCREMENT,
    staff_id INT NOT NULL,
    review_period_start DATE NOT NULL,
    review_period_end DATE NOT NULL,
    overall_rating ENUM('Outstanding', 'Exceeds Expectations', 'Meets Expectations', 'Below Expectations', 'Unsatisfactory') NOT NULL,
    leadership_rating INT CHECK (leadership_rating BETWEEN 1 AND 5),
    technical_rating INT CHECK (technical_rating BETWEEN 1 AND 5),
    communication_rating INT CHECK (communication_rating BETWEEN 1 AND 5),
    teamwork_rating INT CHECK (teamwork_rating BETWEEN 1 AND 5),
    achievements TEXT,
    areas_for_improvement TEXT,
    goals_next_period TEXT,
    reviewer_id INT,
    reviewer_comments TEXT,
    review_date DATE NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (staff_id) REFERENCES staff(id) ON DELETE CASCADE,
    FOREIGN KEY (reviewer_id) REFERENCES staff(id) ON DELETE SET NULL,
    INDEX idx_staff_performance_staff_id (staff_id),
    INDEX idx_staff_performance_date (review_date)
);

-- Staff Equipment Assignment Table
CREATE TABLE IF NOT EXISTS staff_equipment (
    id INT PRIMARY KEY AUTO_INCREMENT,
    staff_id INT NOT NULL,
    equipment_type VARCHAR(100) NOT NULL,
    equipment_name VARCHAR(200) NOT NULL,
    serial_number VARCHAR(100),
    date_issued DATE NOT NULL,
    date_returned DATE,
    condition_issued ENUM('New', 'Good', 'Fair', 'Poor') DEFAULT 'Good',
    condition_returned ENUM('New', 'Good', 'Fair', 'Poor', 'Damaged', 'Lost'),
    issued_by VARCHAR(100),
    returned_to VARCHAR(100),
    remarks TEXT,
    status ENUM('Issued', 'Returned', 'Lost', 'Damaged') DEFAULT 'Issued',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (staff_id) REFERENCES staff(id) ON DELETE CASCADE,
    INDEX idx_staff_equipment_staff_id (staff_id),
    INDEX idx_staff_equipment_status (status)
);

-- Staff Leave Records Table
CREATE TABLE IF NOT EXISTS staff_leave_records (
    id INT PRIMARY KEY AUTO_INCREMENT,
    staff_id INT NOT NULL,
    leave_type ENUM('Annual', 'Sick', 'Maternity', 'Paternity', 'Compassionate', 'Study', 'Special', 'Other') NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    days_requested INT NOT NULL,
    days_approved INT,
    status ENUM('Pending', 'Approved', 'Rejected', 'Cancelled') DEFAULT 'Pending',
    reason TEXT,
    approved_by INT,
    approval_date DATE,
    approval_comments TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (staff_id) REFERENCES staff(id) ON DELETE CASCADE,
    FOREIGN KEY (approved_by) REFERENCES staff(id) ON DELETE SET NULL,
    INDEX idx_staff_leave_staff_id (staff_id),
    INDEX idx_staff_leave_status (status),
    INDEX idx_staff_leave_dates (start_date, end_date)
);

-- Create indexes for improved performance
CREATE INDEX idx_staff_svcNo ON staff(svcNo);
CREATE INDEX idx_staff_rankID ON staff(rankID);
CREATE INDEX idx_staff_unitID ON staff(unitID);
CREATE INDEX idx_staff_svcStatus ON staff(svcStatus);
CREATE INDEX idx_staff_category ON staff(category);
CREATE INDEX idx_staff_corps ON staff(corps);
CREATE INDEX idx_staff_gender ON staff(gender);
CREATE INDEX idx_staff_province ON staff(province);
CREATE INDEX idx_staff_marital ON staff(marital);
CREATE INDEX idx_staff_created ON staff(dateCreated);

-- Create full-text search indexes for better search performance
ALTER TABLE staff ADD FULLTEXT(lname, fname, email);
ALTER TABLE staff_family_members ADD FULLTEXT(name);
ALTER TABLE staff_education ADD FULLTEXT(institution, qualification);
