-- Comprehensive Audit Trail Tables for User Profile Management
-- All user profile changes will be tracked with full audit capabilities

-- Profile Change Audit Table
CREATE TABLE IF NOT EXISTS user_profile_audit (
    id INT AUTO_INCREMENT PRIMARY KEY,
    staff_id INT NOT NULL,
    user_id INT,
    table_name VARCHAR(100) NOT NULL,
    record_id INT,
    action_type ENUM('INSERT', 'UPDATE', 'DELETE') NOT NULL,
    field_name VARCHAR(100),
    old_value TEXT,
    new_value TEXT,
    change_reason VARCHAR(500),
    ip_address VARCHAR(45),
    user_agent TEXT,
    session_id VARCHAR(255),
    module VARCHAR(50) DEFAULT 'users',
    severity ENUM('LOW', 'MEDIUM', 'HIGH', 'CRITICAL') DEFAULT 'MEDIUM',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (staff_id) REFERENCES staff(id) ON DELETE CASCADE,
    INDEX idx_profile_audit_staff_id (staff_id),
    INDEX idx_profile_audit_table (table_name),
    INDEX idx_profile_audit_action (action_type),
    INDEX idx_profile_audit_timestamp (created_at),
    INDEX idx_profile_audit_severity (severity)
);

-- Security Events Audit Table
CREATE TABLE IF NOT EXISTS security_audit_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    action VARCHAR(100) NOT NULL,
    resource VARCHAR(100),
    resource_id INT,
    old_values JSON,
    new_values JSON,
    ip_address VARCHAR(45) NOT NULL,
    user_agent TEXT,
    session_id VARCHAR(255),
    timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    severity ENUM('low', 'medium', 'high', 'critical') DEFAULT 'medium',
    risk_score INT DEFAULT 0,
    additional_data JSON,
    INDEX idx_security_audit_user_id (user_id),
    INDEX idx_security_audit_action (action),
    INDEX idx_security_audit_timestamp (timestamp),
    INDEX idx_security_audit_severity (severity),
    INDEX idx_security_audit_risk (risk_score)
);

-- Data Access Log Table
CREATE TABLE IF NOT EXISTS data_access_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    accessed_user_id INT,
    data_type ENUM('profile', 'personal', 'service', 'medical', 'security', 'cv', 'family') NOT NULL,
    access_type ENUM('view', 'edit', 'export', 'print', 'delete') NOT NULL,
    success BOOLEAN DEFAULT TRUE,
    access_reason VARCHAR(200),
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES staff(id) ON DELETE CASCADE,
    FOREIGN KEY (accessed_user_id) REFERENCES staff(id) ON DELETE CASCADE,
    INDEX idx_data_access_user_id (user_id),
    INDEX idx_data_access_accessed_user (accessed_user_id),
    INDEX idx_data_access_type (data_type),
    INDEX idx_data_access_timestamp (created_at)
);

-- Profile Completion Tracking Table
CREATE TABLE IF NOT EXISTS profile_completion_tracking (
    id INT AUTO_INCREMENT PRIMARY KEY,
    staff_id INT NOT NULL,
    section_name VARCHAR(100) NOT NULL,
    completion_percentage DECIMAL(5,2) DEFAULT 0.00,
    last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    mandatory_fields_complete BOOLEAN DEFAULT FALSE,
    optional_fields_complete BOOLEAN DEFAULT FALSE,
    verification_status ENUM('Not Verified', 'Pending', 'Verified', 'Rejected') DEFAULT 'Not Verified',
    verified_by INT,
    verified_at TIMESTAMP NULL,
    FOREIGN KEY (staff_id) REFERENCES staff(id) ON DELETE CASCADE,
    FOREIGN KEY (verified_by) REFERENCES staff(id) ON DELETE SET NULL,
    UNIQUE KEY unique_staff_section (staff_id, section_name),
    INDEX idx_completion_staff_id (staff_id),
    INDEX idx_completion_section (section_name),
    INDEX idx_completion_percentage (completion_percentage)
);

-- User Session Audit Table
CREATE TABLE IF NOT EXISTS user_session_audit (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    session_id VARCHAR(255) NOT NULL,
    login_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    logout_time TIMESTAMP NULL,
    ip_address VARCHAR(45),
    user_agent TEXT,
    device_fingerprint VARCHAR(255),
    location_data VARCHAR(200),
    session_duration INT, -- seconds
    pages_accessed INT DEFAULT 0,
    actions_performed INT DEFAULT 0,
    forced_logout BOOLEAN DEFAULT FALSE,
    logout_reason ENUM('manual', 'timeout', 'admin', 'security', 'concurrent') DEFAULT 'manual',
    FOREIGN KEY (user_id) REFERENCES staff(id) ON DELETE CASCADE,
    INDEX idx_session_audit_user_id (user_id),
    INDEX idx_session_audit_session (session_id),
    INDEX idx_session_audit_login_time (login_time)
);

-- Compliance Monitoring Table
CREATE TABLE IF NOT EXISTS compliance_monitoring (
    id INT AUTO_INCREMENT PRIMARY KEY,
    staff_id INT NOT NULL,
    compliance_type ENUM('GDPR', 'Military_Standards', 'Security_Clearance', 'Training', 'Medical') NOT NULL,
    compliance_status ENUM('Compliant', 'Non_Compliant', 'Pending_Review', 'Grace_Period') DEFAULT 'Pending_Review',
    last_review_date DATE,
    next_review_date DATE NOT NULL,
    compliance_score DECIMAL(5,2) DEFAULT 0.00,
    violations_count INT DEFAULT 0,
    remediation_actions TEXT,
    reviewer_id INT,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (staff_id) REFERENCES staff(id) ON DELETE CASCADE,
    FOREIGN KEY (reviewer_id) REFERENCES staff(id) ON DELETE SET NULL,
    INDEX idx_compliance_staff_id (staff_id),
    INDEX idx_compliance_type (compliance_type),
    INDEX idx_compliance_status (compliance_status),
    INDEX idx_compliance_next_review (next_review_date)
);