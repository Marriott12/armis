-- Training Module Database Schema
-- Creates tables for training management functionality

-- Training Courses Table
CREATE TABLE IF NOT EXISTS training_courses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    course_name VARCHAR(255) NOT NULL,
    course_code VARCHAR(50) UNIQUE,
    description TEXT,
    course_type ENUM('basic', 'advanced', 'specialized', 'leadership', 'technical', 'safety', 'compliance') NOT NULL,
    status ENUM('draft', 'active', 'completed', 'cancelled', 'archived') DEFAULT 'draft',
    difficulty_level ENUM('beginner', 'intermediate', 'advanced', 'expert') DEFAULT 'beginner',
    duration_hours INT DEFAULT 0,
    max_participants INT DEFAULT 0,
    prerequisites TEXT, -- JSON array of prerequisite course IDs or requirements
    learning_objectives TEXT,
    start_date DATE,
    end_date DATE,
    created_by INT NOT NULL,
    instructor_id INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES staff(id) ON DELETE RESTRICT,
    FOREIGN KEY (instructor_id) REFERENCES staff(id) ON DELETE SET NULL,
    INDEX idx_courses_status (status),
    INDEX idx_courses_type (course_type),
    INDEX idx_courses_dates (start_date, end_date)
);

-- Training Enrollments Table
CREATE TABLE IF NOT EXISTS training_enrollments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    course_id INT NOT NULL,
    personnel_id INT NOT NULL,
    status ENUM('enrolled', 'in-progress', 'completed', 'failed', 'dropped', 'pending') DEFAULT 'enrolled',
    enrolled_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    started_at TIMESTAMP NULL,
    completion_date TIMESTAMP NULL,
    final_score DECIMAL(5,2),
    passing_score DECIMAL(5,2) DEFAULT 70.00,
    attempts INT DEFAULT 1,
    certificate_issued BOOLEAN DEFAULT FALSE,
    notes TEXT,
    enrolled_by INT,
    FOREIGN KEY (course_id) REFERENCES training_courses(id) ON DELETE CASCADE,
    FOREIGN KEY (personnel_id) REFERENCES staff(id) ON DELETE CASCADE,
    FOREIGN KEY (enrolled_by) REFERENCES staff(id) ON DELETE SET NULL,
    UNIQUE KEY unique_course_personnel (course_id, personnel_id),
    INDEX idx_enrollments_status (status),
    INDEX idx_enrollments_personnel (personnel_id),
    INDEX idx_enrollments_dates (enrolled_at, completion_date)
);

-- Training Sessions Table
CREATE TABLE IF NOT EXISTS training_sessions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    course_id INT NOT NULL,
    session_name VARCHAR(255) NOT NULL,
    session_type ENUM('lecture', 'practical', 'assessment', 'field_exercise', 'simulation', 'workshop') NOT NULL,
    start_date DATETIME NOT NULL,
    end_date DATETIME NOT NULL,
    location VARCHAR(255),
    max_attendees INT DEFAULT 0,
    instructor_id INT,
    assistant_instructor_id INT,
    status ENUM('scheduled', 'in-progress', 'completed', 'cancelled', 'postponed') DEFAULT 'scheduled',
    attendance_mandatory BOOLEAN DEFAULT TRUE,
    materials_required TEXT, -- JSON data for required materials
    equipment_needed TEXT, -- JSON data for equipment requirements  
    session_notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (course_id) REFERENCES training_courses(id) ON DELETE CASCADE,
    FOREIGN KEY (instructor_id) REFERENCES staff(id) ON DELETE SET NULL,
    FOREIGN KEY (assistant_instructor_id) REFERENCES staff(id) ON DELETE SET NULL,
    INDEX idx_sessions_course (course_id),
    INDEX idx_sessions_status (status),
    INDEX idx_sessions_dates (start_date, end_date)
);

-- Training Session Participants Table
CREATE TABLE IF NOT EXISTS training_session_participants (
    id INT AUTO_INCREMENT PRIMARY KEY,
    session_id INT NOT NULL,
    personnel_id INT NOT NULL,
    attendance_status ENUM('registered', 'present', 'absent', 'excused', 'late') DEFAULT 'registered',
    check_in_time TIMESTAMP NULL,
    check_out_time TIMESTAMP NULL,
    participation_score DECIMAL(5,2),
    feedback TEXT,
    recorded_by INT,
    FOREIGN KEY (session_id) REFERENCES training_sessions(id) ON DELETE CASCADE,
    FOREIGN KEY (personnel_id) REFERENCES staff(id) ON DELETE CASCADE,
    FOREIGN KEY (recorded_by) REFERENCES staff(id) ON DELETE SET NULL,
    UNIQUE KEY unique_session_personnel (session_id, personnel_id),
    INDEX idx_participants_attendance (attendance_status),
    INDEX idx_participants_session (session_id)
);

-- Training Instructors Table
CREATE TABLE IF NOT EXISTS training_instructors (
    id INT AUTO_INCREMENT PRIMARY KEY,
    personnel_id INT NOT NULL,
    instructor_code VARCHAR(50) UNIQUE,
    specializations TEXT, -- JSON array of specialization areas
    qualification_level ENUM('basic', 'senior', 'master', 'expert') DEFAULT 'basic',
    certifications TEXT, -- JSON array of instructor certifications
    status ENUM('active', 'inactive', 'on_leave', 'retired') DEFAULT 'active',
    hire_date DATE,
    experience_years INT DEFAULT 0,
    rating DECIMAL(3,2) DEFAULT 0.00, -- Average rating from course evaluations
    total_courses_taught INT DEFAULT 0,
    contact_info TEXT, -- JSON data for contact information
    bio TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (personnel_id) REFERENCES staff(id) ON DELETE CASCADE,
    UNIQUE KEY unique_instructor_personnel (personnel_id),
    INDEX idx_instructors_status (status),
    INDEX idx_instructors_rating (rating)
);

-- Training Certifications Table
CREATE TABLE IF NOT EXISTS training_certifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    personnel_id INT NOT NULL,
    certification_name VARCHAR(255) NOT NULL,
    certification_code VARCHAR(100),
    issuing_authority VARCHAR(255),
    issue_date DATE NOT NULL,
    expiry_date DATE,
    status ENUM('active', 'expired', 'revoked', 'pending', 'suspended') DEFAULT 'active',
    certification_level ENUM('basic', 'intermediate', 'advanced', 'expert', 'master') DEFAULT 'basic',
    renewal_required BOOLEAN DEFAULT TRUE,
    renewal_period_months INT DEFAULT 12,
    course_id INT, -- If certification was earned through a course
    score_achieved DECIMAL(5,2),
    certificate_number VARCHAR(100),
    digital_certificate_path VARCHAR(500),
    notes TEXT,
    verified_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (personnel_id) REFERENCES staff(id) ON DELETE CASCADE,
    FOREIGN KEY (course_id) REFERENCES training_courses(id) ON DELETE SET NULL,
    FOREIGN KEY (verified_by) REFERENCES staff(id) ON DELETE SET NULL,
    INDEX idx_certifications_personnel (personnel_id),
    INDEX idx_certifications_status (status),
    INDEX idx_certifications_expiry (expiry_date)
);

-- Training Assessments Table
CREATE TABLE IF NOT EXISTS training_assessments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    course_id INT NOT NULL,
    session_id INT,
    assessment_name VARCHAR(255) NOT NULL,
    assessment_type ENUM('quiz', 'exam', 'practical', 'project', 'presentation', 'simulation') NOT NULL,
    max_score DECIMAL(5,2) DEFAULT 100.00,
    passing_score DECIMAL(5,2) DEFAULT 70.00,
    time_limit_minutes INT DEFAULT 60,
    attempts_allowed INT DEFAULT 3,
    instructions TEXT,
    questions TEXT, -- JSON data for questions and answers
    status ENUM('draft', 'active', 'archived') DEFAULT 'draft',
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (course_id) REFERENCES training_courses(id) ON DELETE CASCADE,
    FOREIGN KEY (session_id) REFERENCES training_sessions(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES staff(id) ON DELETE RESTRICT,
    INDEX idx_assessments_course (course_id),
    INDEX idx_assessments_status (status)
);

-- Training Assessment Results Table
CREATE TABLE IF NOT EXISTS training_assessment_results (
    id INT AUTO_INCREMENT PRIMARY KEY,
    assessment_id INT NOT NULL,
    personnel_id INT NOT NULL,
    attempt_number INT DEFAULT 1,
    score DECIMAL(5,2) DEFAULT 0.00,
    passed BOOLEAN DEFAULT FALSE,
    start_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    end_time TIMESTAMP NULL,
    time_taken_minutes INT,
    answers TEXT, -- JSON data for submitted answers
    feedback TEXT,
    graded_by INT,
    graded_at TIMESTAMP NULL,
    FOREIGN KEY (assessment_id) REFERENCES training_assessments(id) ON DELETE CASCADE,
    FOREIGN KEY (personnel_id) REFERENCES staff(id) ON DELETE CASCADE,
    FOREIGN KEY (graded_by) REFERENCES staff(id) ON DELETE SET NULL,
    INDEX idx_results_assessment (assessment_id),
    INDEX idx_results_personnel (personnel_id),
    INDEX idx_results_score (score)
);

-- Training Resources Table
CREATE TABLE IF NOT EXISTS training_resources (
    id INT AUTO_INCREMENT PRIMARY KEY,
    course_id INT,
    resource_name VARCHAR(255) NOT NULL,
    resource_type ENUM('document', 'video', 'audio', 'presentation', 'simulation', 'equipment', 'software') NOT NULL,
    file_path VARCHAR(500),
    external_url VARCHAR(500),
    description TEXT,
    file_size BIGINT, -- in bytes
    mime_type VARCHAR(100),
    access_level ENUM('public', 'enrolled', 'instructor', 'admin') DEFAULT 'enrolled',
    download_count INT DEFAULT 0,
    version VARCHAR(20) DEFAULT '1.0',
    status ENUM('active', 'archived', 'restricted') DEFAULT 'active',
    uploaded_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (course_id) REFERENCES training_courses(id) ON DELETE CASCADE,
    FOREIGN KEY (uploaded_by) REFERENCES staff(id) ON DELETE RESTRICT,
    INDEX idx_resources_course (course_id),
    INDEX idx_resources_type (resource_type),
    INDEX idx_resources_access (access_level)
);

-- Training Feedback Table
CREATE TABLE IF NOT EXISTS training_feedback (
    id INT AUTO_INCREMENT PRIMARY KEY,
    course_id INT,
    session_id INT,
    instructor_id INT,
    personnel_id INT NOT NULL,
    feedback_type ENUM('course', 'session', 'instructor', 'general') NOT NULL,
    rating DECIMAL(3,2) DEFAULT 0.00, -- 1.00 to 5.00
    comments TEXT,
    suggestions TEXT,
    anonymous BOOLEAN DEFAULT FALSE,
    submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (course_id) REFERENCES training_courses(id) ON DELETE CASCADE,
    FOREIGN KEY (session_id) REFERENCES training_sessions(id) ON DELETE CASCADE,
    FOREIGN KEY (instructor_id) REFERENCES training_instructors(id) ON DELETE CASCADE,
    FOREIGN KEY (personnel_id) REFERENCES staff(id) ON DELETE CASCADE,
    INDEX idx_feedback_course (course_id),
    INDEX idx_feedback_instructor (instructor_id),
    INDEX idx_feedback_rating (rating)
);

-- Insert sample data for immediate functionality
INSERT INTO training_courses (course_name, course_code, description, course_type, status, difficulty_level, duration_hours, max_participants, start_date, end_date, created_by) VALUES
('Basic Military Training', 'BMT-001', 'Fundamental military skills and discipline training', 'basic', 'active', 'beginner', 120, 30, CURDATE(), DATE_ADD(CURDATE(), INTERVAL 4 WEEK), 1),
('Advanced Combat Tactics', 'ACT-001', 'Advanced tactical training for field operations', 'advanced', 'active', 'advanced', 80, 20, DATE_ADD(CURDATE(), INTERVAL 1 WEEK), DATE_ADD(CURDATE(), INTERVAL 3 WEEK), 1),
('Leadership Development', 'LD-001', 'Leadership skills for military personnel', 'leadership', 'active', 'intermediate', 60, 25, DATE_ADD(CURDATE(), INTERVAL 2 WEEK), DATE_ADD(CURDATE(), INTERVAL 5 WEEK), 1),
('Safety Protocols', 'SP-001', 'Comprehensive safety training for all personnel', 'safety', 'active', 'beginner', 40, 50, CURDATE(), DATE_ADD(CURDATE(), INTERVAL 1 WEEK), 1);

INSERT INTO training_enrollments (course_id, personnel_id, status, enrolled_at, final_score) VALUES
(1, 1, 'completed', NOW() - INTERVAL 2 WEEK, 85.5),
(1, 2, 'in-progress', NOW() - INTERVAL 1 WEEK, NULL),
(2, 1, 'enrolled', NOW() - INTERVAL 3 DAY, NULL),
(3, 2, 'enrolled', NOW() - INTERVAL 1 DAY, NULL),
(4, 1, 'completed', NOW() - INTERVAL 1 MONTH, 92.0),
(4, 2, 'completed', NOW() - INTERVAL 3 WEEK, 78.5);

INSERT INTO training_sessions (course_id, session_name, session_type, start_date, end_date, location, max_attendees, status) VALUES
(1, 'BMT Orientation', 'lecture', NOW() + INTERVAL 1 DAY, NOW() + INTERVAL 1 DAY + INTERVAL 2 HOUR, 'Training Room A', 30, 'scheduled'),
(2, 'Tactical Simulation Exercise', 'simulation', NOW() + INTERVAL 3 DAY, NOW() + INTERVAL 3 DAY + INTERVAL 4 HOUR, 'Training Field B', 20, 'scheduled'),
(3, 'Leadership Workshop', 'workshop', NOW() + INTERVAL 5 DAY, NOW() + INTERVAL 5 DAY + INTERVAL 6 HOUR, 'Conference Room C', 25, 'scheduled');

INSERT INTO training_instructors (personnel_id, instructor_code, specializations, qualification_level, status, experience_years, rating) VALUES
(1, 'INST-001', '["combat_tactics", "leadership", "safety"]', 'senior', 'active', 8, 4.5);

INSERT INTO training_certifications (personnel_id, certification_name, certification_code, issuing_authority, issue_date, expiry_date, status, course_id, score_achieved, verified_by) VALUES
(1, 'Basic Military Training Certificate', 'BMT-CERT-001', 'ARMIS Training Division', CURDATE() - INTERVAL 2 WEEK, DATE_ADD(CURDATE(), INTERVAL 2 YEAR), 'active', 1, 85.5, 1),
(2, 'Safety Protocol Certification', 'SP-CERT-001', 'ARMIS Training Division', CURDATE() - INTERVAL 3 WEEK, DATE_ADD(CURDATE(), INTERVAL 1 YEAR), 'active', 4, 78.5, 1);

-- Create indexes for performance
CREATE INDEX idx_courses_created_at ON training_courses(created_at);
CREATE INDEX idx_enrollments_completion ON training_enrollments(completion_date);
CREATE INDEX idx_sessions_instructor ON training_sessions(instructor_id);
CREATE INDEX idx_certifications_renewal ON training_certifications(expiry_date, renewal_required);