-- ARMIS Security Enhancement Schema
-- Tables for JWT tokens, MFA, audit logging, and enhanced security

-- Refresh Tokens Table
CREATE TABLE IF NOT EXISTS refresh_tokens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    token VARCHAR(255) NOT NULL UNIQUE,
    expires_at DATETIME NOT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_user_id (user_id),
    INDEX idx_token (token),
    INDEX idx_expires (expires_at),
    FOREIGN KEY (user_id) REFERENCES staff(id) ON DELETE CASCADE
);

-- Multi-Factor Authentication Table
CREATE TABLE IF NOT EXISTS user_mfa (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL UNIQUE,
    secret VARCHAR(255) NOT NULL,
    enabled TINYINT(1) DEFAULT 0,
    backup_codes_used INT DEFAULT 0,
    created_at DATETIME NOT NULL,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_user_id (user_id),
    FOREIGN KEY (user_id) REFERENCES staff(id) ON DELETE CASCADE
);

-- MFA Backup Codes Table
CREATE TABLE IF NOT EXISTS mfa_backup_codes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    code_hash VARCHAR(255) NOT NULL,
    used TINYINT(1) DEFAULT 0,
    used_at DATETIME NULL,
    created_at DATETIME NOT NULL,
    INDEX idx_user_id (user_id),
    INDEX idx_used (used),
    FOREIGN KEY (user_id) REFERENCES staff(id) ON DELETE CASCADE
);

-- Login Attempts Table for Security Auditing
CREATE TABLE IF NOT EXISTS login_attempts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(100) NOT NULL,
    successful TINYINT(1) NOT NULL,
    ip_address VARCHAR(45) NOT NULL,
    user_agent TEXT,
    reason VARCHAR(255),
    attempted_at DATETIME NOT NULL,
    INDEX idx_username (username),
    INDEX idx_ip_address (ip_address),
    INDEX idx_attempted_at (attempted_at),
    INDEX idx_successful (successful)
);

-- Security Audit Log Table
CREATE TABLE IF NOT EXISTS security_audit_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    action VARCHAR(100) NOT NULL,
    resource VARCHAR(100),
    resource_id VARCHAR(100),
    old_values JSON,
    new_values JSON,
    ip_address VARCHAR(45),
    user_agent TEXT,
    session_id VARCHAR(255),
    timestamp DATETIME NOT NULL,
    severity ENUM('low', 'medium', 'high', 'critical') DEFAULT 'medium',
    INDEX idx_user_id (user_id),
    INDEX idx_action (action),
    INDEX idx_timestamp (timestamp),
    INDEX idx_severity (severity),
    FOREIGN KEY (user_id) REFERENCES staff(id) ON DELETE SET NULL
);

-- Data Classification Table
CREATE TABLE IF NOT EXISTS data_classifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL UNIQUE,
    level INT NOT NULL,
    description TEXT,
    color_code VARCHAR(7),
    created_at DATETIME NOT NULL,
    INDEX idx_level (level)
);

-- Insert default data classifications
INSERT IGNORE INTO data_classifications (name, level, description, color_code, created_at) VALUES
('PUBLIC', 1, 'Information that can be freely shared', '#28a745', NOW()),
('INTERNAL', 2, 'Information for internal use only', '#ffc107', NOW()),
('CONFIDENTIAL', 3, 'Sensitive information requiring protection', '#fd7e14', NOW()),
('SECRET', 4, 'Highly sensitive information', '#dc3545', NOW()),
('TOP_SECRET', 5, 'Most sensitive information requiring highest protection', '#6f42c1', NOW());

-- File Encryption Table
CREATE TABLE IF NOT EXISTS file_encryption (
    id INT AUTO_INCREMENT PRIMARY KEY,
    file_path VARCHAR(500) NOT NULL,
    original_name VARCHAR(255) NOT NULL,
    encryption_key_id VARCHAR(100) NOT NULL,
    classification_id INT NOT NULL,
    encrypted_at DATETIME NOT NULL,
    encrypted_by INT NOT NULL,
    file_size BIGINT NOT NULL,
    checksum VARCHAR(64) NOT NULL,
    INDEX idx_file_path (file_path),
    INDEX idx_classification (classification_id),
    INDEX idx_encrypted_by (encrypted_by),
    FOREIGN KEY (classification_id) REFERENCES data_classifications(id),
    FOREIGN KEY (encrypted_by) REFERENCES staff(id)
);

-- API Rate Limiting Table
CREATE TABLE IF NOT EXISTS api_rate_limits (
    id INT AUTO_INCREMENT PRIMARY KEY,
    identifier VARCHAR(100) NOT NULL,
    endpoint VARCHAR(200),
    requests_count INT DEFAULT 0,
    window_start DATETIME NOT NULL,
    window_duration INT NOT NULL,
    max_requests INT NOT NULL,
    INDEX idx_identifier (identifier),
    INDEX idx_window_start (window_start),
    UNIQUE KEY unique_identifier_endpoint (identifier, endpoint)
);

-- Performance Metrics Table
CREATE TABLE IF NOT EXISTS performance_metrics (
    id INT AUTO_INCREMENT PRIMARY KEY,
    metric_name VARCHAR(100) NOT NULL,
    metric_value DECIMAL(10,4) NOT NULL,
    metric_unit VARCHAR(20),
    endpoint VARCHAR(200),
    execution_time DECIMAL(8,4),
    memory_usage BIGINT,
    query_count INT,
    timestamp DATETIME NOT NULL,
    INDEX idx_metric_name (metric_name),
    INDEX idx_timestamp (timestamp),
    INDEX idx_endpoint (endpoint)
);

-- Enhanced User Permissions Table
CREATE TABLE IF NOT EXISTS user_permissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    module VARCHAR(50) NOT NULL,
    permission VARCHAR(50) NOT NULL,
    granted_by INT,
    granted_at DATETIME NOT NULL,
    expires_at DATETIME,
    INDEX idx_user_id (user_id),
    INDEX idx_module (module),
    INDEX idx_permission (permission),
    UNIQUE KEY unique_user_module_permission (user_id, module, permission),
    FOREIGN KEY (user_id) REFERENCES staff(id) ON DELETE CASCADE,
    FOREIGN KEY (granted_by) REFERENCES staff(id) ON DELETE SET NULL
);

-- System Configuration Table
CREATE TABLE IF NOT EXISTS system_config (
    id INT AUTO_INCREMENT PRIMARY KEY,
    config_key VARCHAR(100) NOT NULL UNIQUE,
    config_value TEXT,
    config_type ENUM('string', 'integer', 'boolean', 'json') DEFAULT 'string',
    description TEXT,
    is_sensitive TINYINT(1) DEFAULT 0,
    updated_by INT,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_config_key (config_key),
    FOREIGN KEY (updated_by) REFERENCES staff(id) ON DELETE SET NULL
);

-- Insert default system configurations
INSERT IGNORE INTO system_config (config_key, config_value, config_type, description, is_sensitive) VALUES
('jwt_secret_rotation_days', '30', 'integer', 'Days between JWT secret rotation', 1),
('mfa_required_for_admin', 'true', 'boolean', 'Require MFA for admin users', 0),
('session_timeout_minutes', '60', 'integer', 'Session timeout in minutes', 0),
('max_login_attempts', '5', 'integer', 'Maximum login attempts before lockout', 0),
('lockout_duration_minutes', '15', 'integer', 'Account lockout duration in minutes', 0),
('password_min_length', '8', 'integer', 'Minimum password length', 0),
('password_require_special', 'true', 'boolean', 'Require special characters in passwords', 0),
('api_rate_limit_per_minute', '100', 'integer', 'API requests per minute per IP', 0),
('backup_retention_days', '30', 'integer', 'Backup retention period in days', 0),
('audit_log_retention_days', '90', 'integer', 'Audit log retention period in days', 0);

-- Automated Backup Log Table
CREATE TABLE IF NOT EXISTS backup_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    backup_type ENUM('full', 'incremental', 'differential') NOT NULL,
    status ENUM('started', 'completed', 'failed') NOT NULL,
    file_path VARCHAR(500),
    file_size BIGINT,
    started_at DATETIME NOT NULL,
    completed_at DATETIME,
    error_message TEXT,
    created_by VARCHAR(100) DEFAULT 'system',
    INDEX idx_status (status),
    INDEX idx_started_at (started_at),
    INDEX idx_backup_type (backup_type)
);

-- Notification Queue Table
CREATE TABLE IF NOT EXISTS notification_queue (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    notification_type VARCHAR(50) NOT NULL,
    title VARCHAR(200) NOT NULL,
    message TEXT NOT NULL,
    data JSON,
    priority ENUM('low', 'medium', 'high', 'urgent') DEFAULT 'medium',
    status ENUM('pending', 'sent', 'failed', 'read') DEFAULT 'pending',
    scheduled_for DATETIME,
    sent_at DATETIME,
    read_at DATETIME,
    created_at DATETIME NOT NULL,
    INDEX idx_user_id (user_id),
    INDEX idx_status (status),
    INDEX idx_priority (priority),
    INDEX idx_scheduled_for (scheduled_for),
    FOREIGN KEY (user_id) REFERENCES staff(id) ON DELETE CASCADE
);