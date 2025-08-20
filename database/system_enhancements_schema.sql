-- ====================================================================
-- ARMIS System Enhancements Database Schema
-- Complete implementation for 7 major module enhancements
-- ====================================================================

-- ====================================================================
-- 1. LOGISTICS & SUPPLY CHAIN MANAGEMENT MODULE
-- ====================================================================

-- Inventory Management
CREATE TABLE IF NOT EXISTS inventory_categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    code VARCHAR(20) UNIQUE NOT NULL,
    description TEXT,
    parent_category_id INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_parent (parent_category_id),
    FOREIGN KEY (parent_category_id) REFERENCES inventory_categories(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS inventory_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    category_id INT NOT NULL,
    name VARCHAR(200) NOT NULL,
    part_number VARCHAR(100) UNIQUE,
    description TEXT,
    unit_of_measure VARCHAR(50) NOT NULL DEFAULT 'EACH',
    minimum_stock INT DEFAULT 0,
    maximum_stock INT DEFAULT 1000,
    current_stock INT DEFAULT 0,
    unit_cost DECIMAL(10,2) DEFAULT 0.00,
    location VARCHAR(100),
    status ENUM('ACTIVE', 'INACTIVE', 'DISCONTINUED') DEFAULT 'ACTIVE',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_category (category_id),
    INDEX idx_part_number (part_number),
    INDEX idx_status (status),
    FOREIGN KEY (category_id) REFERENCES inventory_categories(id) ON DELETE RESTRICT
);

-- Stock Movements
CREATE TABLE IF NOT EXISTS stock_movements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    item_id INT NOT NULL,
    movement_type ENUM('IN', 'OUT', 'TRANSFER', 'ADJUSTMENT') NOT NULL,
    quantity INT NOT NULL,
    unit_cost DECIMAL(10,2) DEFAULT 0.00,
    reference_type ENUM('PURCHASE', 'REQUISITION', 'RETURN', 'LOSS', 'ADJUSTMENT') NOT NULL,
    reference_id INT,
    from_location VARCHAR(100),
    to_location VARCHAR(100),
    notes TEXT,
    user_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_item (item_id),
    INDEX idx_type (movement_type),
    INDEX idx_reference (reference_type, reference_id),
    INDEX idx_date (created_at),
    FOREIGN KEY (item_id) REFERENCES inventory_items(id) ON DELETE RESTRICT,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT
);

-- Supply Requisitions
CREATE TABLE IF NOT EXISTS supply_requisitions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    requisition_number VARCHAR(50) UNIQUE NOT NULL,
    requesting_unit_id INT,
    requester_id INT NOT NULL,
    status ENUM('DRAFT', 'SUBMITTED', 'APPROVED', 'REJECTED', 'FULFILLED', 'CANCELLED') DEFAULT 'DRAFT',
    priority ENUM('LOW', 'NORMAL', 'HIGH', 'URGENT') DEFAULT 'NORMAL',
    requested_date DATE NOT NULL,
    required_date DATE NOT NULL,
    justification TEXT,
    approver_id INT NULL,
    approved_at TIMESTAMP NULL,
    approval_notes TEXT,
    total_estimated_cost DECIMAL(12,2) DEFAULT 0.00,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_number (requisition_number),
    INDEX idx_status (status),
    INDEX idx_requester (requester_id),
    INDEX idx_unit (requesting_unit_id),
    INDEX idx_dates (requested_date, required_date),
    FOREIGN KEY (requester_id) REFERENCES users(id) ON DELETE RESTRICT,
    FOREIGN KEY (approver_id) REFERENCES users(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS requisition_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    requisition_id INT NOT NULL,
    item_id INT NOT NULL,
    quantity_requested INT NOT NULL,
    quantity_approved INT DEFAULT 0,
    quantity_fulfilled INT DEFAULT 0,
    unit_cost DECIMAL(10,2) DEFAULT 0.00,
    total_cost DECIMAL(12,2) GENERATED ALWAYS AS (quantity_approved * unit_cost) STORED,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_requisition (requisition_id),
    INDEX idx_item (item_id),
    FOREIGN KEY (requisition_id) REFERENCES supply_requisitions(id) ON DELETE CASCADE,
    FOREIGN KEY (item_id) REFERENCES inventory_items(id) ON DELETE RESTRICT
);

-- Vendors and Procurement
CREATE TABLE IF NOT EXISTS vendors (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(200) NOT NULL,
    contact_person VARCHAR(100),
    email VARCHAR(100),
    phone VARCHAR(20),
    address TEXT,
    tax_id VARCHAR(50),
    status ENUM('ACTIVE', 'INACTIVE', 'BLACKLISTED') DEFAULT 'ACTIVE',
    rating DECIMAL(3,2) DEFAULT 0.00,
    payment_terms VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_name (name),
    INDEX idx_status (status)
);

CREATE TABLE IF NOT EXISTS purchase_orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    po_number VARCHAR(50) UNIQUE NOT NULL,
    vendor_id INT NOT NULL,
    requisition_id INT NULL,
    status ENUM('DRAFT', 'SENT', 'ACKNOWLEDGED', 'DELIVERED', 'CANCELLED') DEFAULT 'DRAFT',
    order_date DATE NOT NULL,
    expected_delivery_date DATE,
    actual_delivery_date DATE NULL,
    subtotal DECIMAL(12,2) DEFAULT 0.00,
    tax_amount DECIMAL(12,2) DEFAULT 0.00,
    total_amount DECIMAL(12,2) DEFAULT 0.00,
    terms TEXT,
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_po_number (po_number),
    INDEX idx_vendor (vendor_id),
    INDEX idx_status (status),
    INDEX idx_dates (order_date, expected_delivery_date),
    FOREIGN KEY (vendor_id) REFERENCES vendors(id) ON DELETE RESTRICT,
    FOREIGN KEY (requisition_id) REFERENCES supply_requisitions(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE RESTRICT
);

-- Equipment Maintenance
CREATE TABLE IF NOT EXISTS equipment_maintenance_schedules (
    id INT AUTO_INCREMENT PRIMARY KEY,
    equipment_id INT NOT NULL,
    maintenance_type ENUM('PREVENTIVE', 'CORRECTIVE', 'PREDICTIVE', 'EMERGENCY') NOT NULL,
    frequency_days INT NOT NULL,
    last_maintenance_date DATE,
    next_maintenance_date DATE NOT NULL,
    status ENUM('SCHEDULED', 'OVERDUE', 'IN_PROGRESS', 'COMPLETED', 'CANCELLED') DEFAULT 'SCHEDULED',
    assigned_technician_id INT,
    estimated_hours DECIMAL(5,2),
    actual_hours DECIMAL(5,2),
    maintenance_notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_equipment (equipment_id),
    INDEX idx_next_date (next_maintenance_date),
    INDEX idx_status (status),
    FOREIGN KEY (assigned_technician_id) REFERENCES users(id) ON DELETE SET NULL
);

-- ====================================================================
-- 2. ADVANCED WORKFLOW MANAGEMENT SYSTEM
-- ====================================================================

-- Workflow Templates
CREATE TABLE IF NOT EXISTS workflow_templates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(200) NOT NULL,
    description TEXT,
    category VARCHAR(100),
    version INT DEFAULT 1,
    is_active BOOLEAN DEFAULT TRUE,
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_name (name),
    INDEX idx_category (category),
    INDEX idx_active (is_active),
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE RESTRICT
);

CREATE TABLE IF NOT EXISTS workflow_steps (
    id INT AUTO_INCREMENT PRIMARY KEY,
    template_id INT NOT NULL,
    step_number INT NOT NULL,
    step_name VARCHAR(200) NOT NULL,
    step_type ENUM('APPROVAL', 'TASK', 'DECISION', 'NOTIFICATION', 'SYSTEM') NOT NULL,
    assignee_type ENUM('USER', 'ROLE', 'UNIT', 'SYSTEM') NOT NULL,
    assignee_value VARCHAR(100) NOT NULL,
    is_required BOOLEAN DEFAULT TRUE,
    auto_approve_hours INT DEFAULT 0,
    escalation_hours INT DEFAULT 72,
    escalation_to VARCHAR(100),
    instructions TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_template (template_id),
    INDEX idx_step_number (template_id, step_number),
    FOREIGN KEY (template_id) REFERENCES workflow_templates(id) ON DELETE CASCADE
);

-- Workflow Instances
CREATE TABLE IF NOT EXISTS workflow_instances (
    id INT AUTO_INCREMENT PRIMARY KEY,
    template_id INT NOT NULL,
    instance_name VARCHAR(200) NOT NULL,
    reference_type VARCHAR(100),
    reference_id INT,
    initiated_by INT NOT NULL,
    current_step INT DEFAULT 1,
    status ENUM('ACTIVE', 'COMPLETED', 'CANCELLED', 'ERROR') DEFAULT 'ACTIVE',
    priority ENUM('LOW', 'NORMAL', 'HIGH', 'URGENT') DEFAULT 'NORMAL',
    data JSON,
    started_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    completed_at TIMESTAMP NULL,
    INDEX idx_template (template_id),
    INDEX idx_status (status),
    INDEX idx_reference (reference_type, reference_id),
    INDEX idx_initiator (initiated_by),
    FOREIGN KEY (template_id) REFERENCES workflow_templates(id) ON DELETE RESTRICT,
    FOREIGN KEY (initiated_by) REFERENCES users(id) ON DELETE RESTRICT
);

CREATE TABLE IF NOT EXISTS workflow_step_instances (
    id INT AUTO_INCREMENT PRIMARY KEY,
    workflow_instance_id INT NOT NULL,
    step_id INT NOT NULL,
    step_number INT NOT NULL,
    assignee_id INT,
    status ENUM('PENDING', 'IN_PROGRESS', 'COMPLETED', 'SKIPPED', 'ESCALATED') DEFAULT 'PENDING',
    assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    started_at TIMESTAMP NULL,
    completed_at TIMESTAMP NULL,
    action_taken ENUM('APPROVED', 'REJECTED', 'DELEGATED', 'ESCALATED', 'COMPLETED') NULL,
    comments TEXT,
    INDEX idx_workflow (workflow_instance_id),
    INDEX idx_step (step_id),
    INDEX idx_assignee (assignee_id),
    INDEX idx_status (status),
    FOREIGN KEY (workflow_instance_id) REFERENCES workflow_instances(id) ON DELETE CASCADE,
    FOREIGN KEY (step_id) REFERENCES workflow_steps(id) ON DELETE RESTRICT,
    FOREIGN KEY (assignee_id) REFERENCES users(id) ON DELETE SET NULL
);

-- ====================================================================
-- 3. ENHANCED COMMUNICATION & MESSAGING
-- ====================================================================

-- Internal Messaging
CREATE TABLE IF NOT EXISTS message_threads (
    id INT AUTO_INCREMENT PRIMARY KEY,
    subject VARCHAR(200) NOT NULL,
    thread_type ENUM('PERSONAL', 'GROUP', 'ANNOUNCEMENT', 'SYSTEM') DEFAULT 'PERSONAL',
    priority ENUM('LOW', 'NORMAL', 'HIGH', 'URGENT') DEFAULT 'NORMAL',
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_type (thread_type),
    INDEX idx_priority (priority),
    INDEX idx_creator (created_by),
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE RESTRICT
);

CREATE TABLE IF NOT EXISTS messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    thread_id INT NOT NULL,
    sender_id INT NOT NULL,
    content TEXT NOT NULL,
    message_type ENUM('TEXT', 'FILE', 'SYSTEM') DEFAULT 'TEXT',
    attachment_path VARCHAR(500),
    is_read BOOLEAN DEFAULT FALSE,
    sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_thread (thread_id),
    INDEX idx_sender (sender_id),
    INDEX idx_sent_at (sent_at),
    FOREIGN KEY (thread_id) REFERENCES message_threads(id) ON DELETE CASCADE,
    FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE RESTRICT
);

CREATE TABLE IF NOT EXISTS message_participants (
    id INT AUTO_INCREMENT PRIMARY KEY,
    thread_id INT NOT NULL,
    user_id INT NOT NULL,
    role ENUM('SENDER', 'RECIPIENT', 'CC', 'BCC') DEFAULT 'RECIPIENT',
    joined_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_read_at TIMESTAMP NULL,
    is_active BOOLEAN DEFAULT TRUE,
    UNIQUE KEY unique_participant (thread_id, user_id),
    INDEX idx_thread (thread_id),
    INDEX idx_user (user_id),
    FOREIGN KEY (thread_id) REFERENCES message_threads(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Notifications Framework
CREATE TABLE IF NOT EXISTS notification_types (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) UNIQUE NOT NULL,
    description TEXT,
    icon VARCHAR(50),
    color VARCHAR(20),
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    type_id INT NOT NULL,
    recipient_id INT NOT NULL,
    title VARCHAR(200) NOT NULL,
    message TEXT NOT NULL,
    data JSON,
    priority ENUM('LOW', 'NORMAL', 'HIGH', 'URGENT') DEFAULT 'NORMAL',
    is_read BOOLEAN DEFAULT FALSE,
    read_at TIMESTAMP NULL,
    expires_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_recipient (recipient_id),
    INDEX idx_type (type_id),
    INDEX idx_read (is_read),
    INDEX idx_created (created_at),
    FOREIGN KEY (type_id) REFERENCES notification_types(id) ON DELETE RESTRICT,
    FOREIGN KEY (recipient_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Document Sharing
CREATE TABLE IF NOT EXISTS shared_documents (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(200) NOT NULL,
    description TEXT,
    file_path VARCHAR(500) NOT NULL,
    file_size BIGINT NOT NULL,
    mime_type VARCHAR(100) NOT NULL,
    category VARCHAR(100),
    access_level ENUM('PUBLIC', 'UNIT', 'ROLE', 'PRIVATE') DEFAULT 'PRIVATE',
    uploaded_by INT NOT NULL,
    version INT DEFAULT 1,
    is_active BOOLEAN DEFAULT TRUE,
    download_count INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_name (name),
    INDEX idx_category (category),
    INDEX idx_access (access_level),
    INDEX idx_uploader (uploaded_by),
    FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE RESTRICT
);

CREATE TABLE IF NOT EXISTS document_permissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    document_id INT NOT NULL,
    permission_type ENUM('USER', 'ROLE', 'UNIT') NOT NULL,
    permission_value VARCHAR(100) NOT NULL,
    access_type ENUM('READ', 'write', 'admin') DEFAULT 'read',
    granted_by INT NOT NULL,
    granted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_permission (document_id, permission_type, permission_value),
    INDEX idx_document (document_id),
    INDEX idx_permission (permission_type, permission_value),
    FOREIGN KEY (document_id) REFERENCES shared_documents(id) ON DELETE CASCADE,
    FOREIGN KEY (granted_by) REFERENCES users(id) ON DELETE RESTRICT
);

-- Announcement Board
CREATE TABLE IF NOT EXISTS announcements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(200) NOT NULL,
    content TEXT NOT NULL,
    category VARCHAR(100),
    priority ENUM('LOW', 'NORMAL', 'HIGH', 'URGENT') DEFAULT 'NORMAL',
    target_audience ENUM('ALL', 'UNIT', 'ROLE', 'SPECIFIC') DEFAULT 'ALL',
    target_value VARCHAR(100),
    is_active BOOLEAN DEFAULT TRUE,
    published_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NULL,
    created_by INT NOT NULL,
    view_count INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_category (category),
    INDEX idx_priority (priority),
    INDEX idx_target (target_audience, target_value),
    INDEX idx_active (is_active),
    INDEX idx_published (published_at),
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE RESTRICT
);

-- ====================================================================
-- 4. ADVANCED SECURITY & COMPLIANCE
-- ====================================================================

-- Audit Logging System
CREATE TABLE IF NOT EXISTS audit_logs (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    session_id VARCHAR(128),
    action VARCHAR(100) NOT NULL,
    entity_type VARCHAR(100),
    entity_id INT,
    old_values JSON,
    new_values JSON,
    ip_address VARCHAR(45),
    user_agent TEXT,
    module VARCHAR(50),
    severity ENUM('LOW', 'MEDIUM', 'HIGH', 'CRITICAL') DEFAULT 'LOW',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user (user_id),
    INDEX idx_action (action),
    INDEX idx_entity (entity_type, entity_id),
    INDEX idx_module (module),
    INDEX idx_severity (severity),
    INDEX idx_created (created_at),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

-- Security Incidents
CREATE TABLE IF NOT EXISTS security_incidents (
    id INT AUTO_INCREMENT PRIMARY KEY,
    incident_type ENUM('UNAUTHORIZED_ACCESS', 'DATA_BREACH', 'MALWARE', 'PHISHING', 'POLICY_VIOLATION', 'OTHER') NOT NULL,
    severity ENUM('LOW', 'MEDIUM', 'HIGH', 'CRITICAL') NOT NULL,
    status ENUM('OPEN', 'INVESTIGATING', 'RESOLVED', 'CLOSED') DEFAULT 'OPEN',
    title VARCHAR(200) NOT NULL,
    description TEXT NOT NULL,
    affected_systems TEXT,
    reported_by INT,
    assigned_to INT,
    detected_at TIMESTAMP NOT NULL,
    resolved_at TIMESTAMP NULL,
    resolution_notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_type (incident_type),
    INDEX idx_severity (severity),
    INDEX idx_status (status),
    INDEX idx_detected (detected_at),
    FOREIGN KEY (reported_by) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (assigned_to) REFERENCES users(id) ON DELETE SET NULL
);

-- Compliance Monitoring
CREATE TABLE IF NOT EXISTS compliance_frameworks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(200) NOT NULL,
    description TEXT,
    version VARCHAR(50),
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS compliance_controls (
    id INT AUTO_INCREMENT PRIMARY KEY,
    framework_id INT NOT NULL,
    control_id VARCHAR(50) NOT NULL,
    title VARCHAR(200) NOT NULL,
    description TEXT NOT NULL,
    category VARCHAR(100),
    implementation_status ENUM('NOT_IMPLEMENTED', 'PARTIAL', 'IMPLEMENTED', 'NOT_APPLICABLE') DEFAULT 'NOT_IMPLEMENTED',
    last_assessed_at TIMESTAMP NULL,
    next_assessment_date DATE,
    responsible_party INT,
    evidence_location TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_control (framework_id, control_id),
    INDEX idx_framework (framework_id),
    INDEX idx_status (implementation_status),
    INDEX idx_assessment (next_assessment_date),
    FOREIGN KEY (framework_id) REFERENCES compliance_frameworks(id) ON DELETE CASCADE,
    FOREIGN KEY (responsible_party) REFERENCES users(id) ON DELETE SET NULL
);

-- ====================================================================
-- 5. INTEGRATION & DATA MANAGEMENT
-- ====================================================================

-- API Management
CREATE TABLE IF NOT EXISTS api_endpoints (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(200) NOT NULL,
    endpoint_url VARCHAR(500) NOT NULL,
    method ENUM('GET', 'POST', 'PUT', 'DELETE', 'PATCH') NOT NULL,
    description TEXT,
    authentication_type ENUM('NONE', 'API_KEY', 'BEARER_TOKEN', 'BASIC_AUTH', 'OAUTH') DEFAULT 'API_KEY',
    is_active BOOLEAN DEFAULT TRUE,
    rate_limit_per_hour INT DEFAULT 1000,
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_name (name),
    INDEX idx_active (is_active),
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE RESTRICT
);

CREATE TABLE IF NOT EXISTS api_access_logs (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    endpoint_id INT NOT NULL,
    user_id INT,
    ip_address VARCHAR(45),
    request_method VARCHAR(10),
    request_path VARCHAR(500),
    request_headers JSON,
    request_body TEXT,
    response_code INT,
    response_headers JSON,
    response_body TEXT,
    response_time_ms INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_endpoint (endpoint_id),
    INDEX idx_user (user_id),
    INDEX idx_response_code (response_code),
    INDEX idx_created (created_at),
    FOREIGN KEY (endpoint_id) REFERENCES api_endpoints(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

-- Data Sync & Integration
CREATE TABLE IF NOT EXISTS data_sync_jobs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(200) NOT NULL,
    source_system VARCHAR(100) NOT NULL,
    target_system VARCHAR(100) NOT NULL,
    sync_type ENUM('EXPORT', 'IMPORT', 'BIDIRECTIONAL') NOT NULL,
    schedule_cron VARCHAR(100),
    last_run_at TIMESTAMP NULL,
    next_run_at TIMESTAMP NULL,
    status ENUM('ACTIVE', 'INACTIVE', 'ERROR') DEFAULT 'ACTIVE',
    configuration JSON,
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_name (name),
    INDEX idx_status (status),
    INDEX idx_next_run (next_run_at),
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE RESTRICT
);

CREATE TABLE IF NOT EXISTS data_sync_logs (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    job_id INT NOT NULL,
    status ENUM('STARTED', 'SUCCESS', 'WARNING', 'ERROR') NOT NULL,
    records_processed INT DEFAULT 0,
    records_success INT DEFAULT 0,
    records_error INT DEFAULT 0,
    start_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    end_time TIMESTAMP NULL,
    error_message TEXT,
    log_details JSON,
    INDEX idx_job (job_id),
    INDEX idx_status (status),
    INDEX idx_start_time (start_time),
    FOREIGN KEY (job_id) REFERENCES data_sync_jobs(id) ON DELETE CASCADE
);

-- ====================================================================
-- 6. ADVANCED REPORTING & ANALYTICS
-- ====================================================================

-- Custom Report Builder
CREATE TABLE IF NOT EXISTS report_templates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(200) NOT NULL,
    description TEXT,
    category VARCHAR(100),
    sql_query TEXT NOT NULL,
    parameters JSON,
    output_format ENUM('TABLE', 'CHART', 'DASHBOARD') DEFAULT 'TABLE',
    chart_config JSON,
    is_public BOOLEAN DEFAULT FALSE,
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_name (name),
    INDEX idx_category (category),
    INDEX idx_public (is_public),
    INDEX idx_creator (created_by),
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE RESTRICT
);

CREATE TABLE IF NOT EXISTS scheduled_reports (
    id INT AUTO_INCREMENT PRIMARY KEY,
    template_id INT NOT NULL,
    name VARCHAR(200) NOT NULL,
    schedule_cron VARCHAR(100) NOT NULL,
    recipients JSON NOT NULL,
    parameters JSON,
    output_format ENUM('PDF', 'EXCEL', 'CSV', 'HTML') DEFAULT 'PDF',
    is_active BOOLEAN DEFAULT TRUE,
    last_run_at TIMESTAMP NULL,
    next_run_at TIMESTAMP NULL,
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_template (template_id),
    INDEX idx_active (is_active),
    INDEX idx_next_run (next_run_at),
    FOREIGN KEY (template_id) REFERENCES report_templates(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE RESTRICT
);

-- Analytics Data
CREATE TABLE IF NOT EXISTS analytics_metrics (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    metric_name VARCHAR(100) NOT NULL,
    metric_value DECIMAL(15,4) NOT NULL,
    dimensions JSON,
    timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_metric (metric_name),
    INDEX idx_timestamp (timestamp)
);

-- ====================================================================
-- 7. SYSTEM ADMINISTRATION TOOLS
-- ====================================================================

-- System Configuration
CREATE TABLE IF NOT EXISTS system_config (
    id INT AUTO_INCREMENT PRIMARY KEY,
    config_key VARCHAR(200) UNIQUE NOT NULL,
    config_value TEXT,
    config_type ENUM('STRING', 'INTEGER', 'BOOLEAN', 'JSON', 'ENCRYPTED') DEFAULT 'STRING',
    category VARCHAR(100),
    description TEXT,
    is_editable BOOLEAN DEFAULT TRUE,
    updated_by INT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_key (config_key),
    INDEX idx_category (category),
    FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
);

-- System Health Monitoring
CREATE TABLE IF NOT EXISTS system_health_checks (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    check_name VARCHAR(100) NOT NULL,
    check_type ENUM('DATABASE', 'FILESYSTEM', 'SERVICE', 'NETWORK', 'CUSTOM') NOT NULL,
    status ENUM('OK', 'WARNING', 'ERROR', 'UNKNOWN') NOT NULL,
    response_time_ms INT,
    message TEXT,
    details JSON,
    checked_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_name (check_name),
    INDEX idx_type (check_type),
    INDEX idx_status (status),
    INDEX idx_checked (checked_at)
);

-- Performance Metrics
CREATE TABLE IF NOT EXISTS performance_metrics (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    metric_type ENUM('CPU', 'MEMORY', 'DISK', 'NETWORK', 'DATABASE', 'APPLICATION') NOT NULL,
    metric_name VARCHAR(100) NOT NULL,
    metric_value DECIMAL(10,4) NOT NULL,
    unit VARCHAR(20),
    server_name VARCHAR(100),
    collected_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_type (metric_type),
    INDEX idx_name (metric_name),
    INDEX idx_server (server_name),
    INDEX idx_collected (collected_at)
);

-- ====================================================================
-- Insert Default Data
-- ====================================================================

-- Default Inventory Categories
INSERT IGNORE INTO inventory_categories (name, code, description) VALUES
('Equipment', 'EQUIP', 'Military equipment and gear'),
('Supplies', 'SUPP', 'General supplies and consumables'),
('Ammunition', 'AMMO', 'Ammunition and ordnance'),
('Vehicles', 'VEH', 'Military vehicles and transport'),
('Medical', 'MED', 'Medical supplies and equipment'),
('Communications', 'COMM', 'Communication equipment'),
('Food & Rations', 'FOOD', 'Food supplies and rations');

-- Default Notification Types
INSERT IGNORE INTO notification_types (name, description, icon, color) VALUES
('system_alert', 'System alerts and warnings', 'exclamation-triangle', 'warning'),
('workflow_approval', 'Workflow approval requests', 'check-circle', 'primary'),
('message_received', 'New message received', 'envelope', 'info'),
('maintenance_due', 'Equipment maintenance due', 'wrench', 'warning'),
('requisition_status', 'Supply requisition status update', 'clipboard-list', 'success'),
('security_incident', 'Security incident notification', 'shield-alt', 'danger');

-- Default Compliance Frameworks
INSERT IGNORE INTO compliance_frameworks (name, description, version) VALUES
('NIST Cybersecurity Framework', 'National Institute of Standards and Technology Cybersecurity Framework', '1.1'),
('ISO 27001', 'Information security management system standard', '2013'),
('DoD STIG', 'Department of Defense Security Technical Implementation Guide', '2023');

-- Default System Configuration
INSERT IGNORE INTO system_config (config_key, config_value, config_type, category, description) VALUES
('system.name', 'ARMIS - Army Resource Management Information System', 'STRING', 'general', 'System name'),
('system.version', '2.0.0', 'STRING', 'general', 'System version'),
('system.timezone', 'UTC', 'STRING', 'general', 'System timezone'),
('security.session_timeout', '3600', 'INTEGER', 'security', 'Session timeout in seconds'),
('security.max_login_attempts', '5', 'INTEGER', 'security', 'Maximum login attempts'),
('notifications.email_enabled', 'true', 'BOOLEAN', 'notifications', 'Enable email notifications'),
('backup.retention_days', '30', 'INTEGER', 'backup', 'Backup retention period in days'),
('performance.cache_enabled', 'true', 'BOOLEAN', 'performance', 'Enable system caching');