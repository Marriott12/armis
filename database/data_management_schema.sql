-- ====================================================================
-- ARMIS Data Management Expansions - Additional Tables
-- Extends system_enhancements_schema.sql with backup and archival tables
-- ====================================================================

-- Backup Management Tables
CREATE TABLE IF NOT EXISTS backup_log (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    backup_type ENUM('full', 'incremental', 'differential', 'system') NOT NULL,
    file_path VARCHAR(500) NOT NULL,
    file_size BIGINT NOT NULL DEFAULT 0,
    status ENUM('started', 'completed', 'failed', 'cancelled') NOT NULL,
    started_at TIMESTAMP NULL,
    completed_at TIMESTAMP NULL,
    last_tested_at TIMESTAMP NULL,
    test_results JSON NULL,
    error_message TEXT NULL,
    created_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_backup_type (backup_type),
    INDEX idx_status (status),
    INDEX idx_started_at (started_at),
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS backup_schedules (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(200) NOT NULL,
    backup_type ENUM('full', 'incremental', 'differential', 'system') NOT NULL,
    schedule_cron VARCHAR(100) NOT NULL,
    retention_days INT DEFAULT 30,
    is_active BOOLEAN DEFAULT TRUE,
    last_run_at TIMESTAMP NULL,
    next_run_at TIMESTAMP NULL,
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_next_run (next_run_at),
    INDEX idx_active (is_active),
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE RESTRICT
);

-- Data Archival Tables
CREATE TABLE IF NOT EXISTS archive_policies (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(200) NOT NULL,
    data_type VARCHAR(100) NOT NULL,
    table_name VARCHAR(100) NOT NULL,
    archive_after_days INT NOT NULL,
    purge_after_years INT NOT NULL,
    compression_method ENUM('gzip', 'lz4', 'zstd', 'xz') DEFAULT 'gzip',
    is_active BOOLEAN DEFAULT TRUE,
    auto_execute BOOLEAN DEFAULT FALSE,
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_data_type (data_type),
    INDEX idx_active (is_active),
    INDEX idx_auto_execute (auto_execute),
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE RESTRICT
);

CREATE TABLE IF NOT EXISTS archive_operations (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    policy_id INT NOT NULL,
    operation_type ENUM('archive', 'purge', 'restore') NOT NULL,
    status ENUM('pending', 'running', 'completed', 'failed') DEFAULT 'pending',
    records_processed INT DEFAULT 0,
    records_success INT DEFAULT 0,
    records_error INT DEFAULT 0,
    data_size_before BIGINT DEFAULT 0,
    data_size_after BIGINT DEFAULT 0,
    started_at TIMESTAMP NULL,
    completed_at TIMESTAMP NULL,
    error_message TEXT NULL,
    executed_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_policy (policy_id),
    INDEX idx_status (status),
    INDEX idx_started_at (started_at),
    FOREIGN KEY (policy_id) REFERENCES archive_policies(id) ON DELETE CASCADE,
    FOREIGN KEY (executed_by) REFERENCES users(id) ON DELETE SET NULL
);

-- Data Migration Tables
CREATE TABLE IF NOT EXISTS migration_jobs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(200) NOT NULL,
    source_system VARCHAR(100) NOT NULL,
    target_system VARCHAR(100) NOT NULL,
    migration_type ENUM('import', 'export', 'sync') NOT NULL,
    data_format ENUM('csv', 'xml', 'json', 'sql', 'excel') NOT NULL,
    file_path VARCHAR(500) NULL,
    mapping_config JSON NULL,
    validation_rules JSON NULL,
    status ENUM('pending', 'running', 'completed', 'failed', 'cancelled') DEFAULT 'pending',
    records_total INT DEFAULT 0,
    records_processed INT DEFAULT 0,
    records_success INT DEFAULT 0,
    records_error INT DEFAULT 0,
    started_at TIMESTAMP NULL,
    completed_at TIMESTAMP NULL,
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_status (status),
    INDEX idx_migration_type (migration_type),
    INDEX idx_started_at (started_at),
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE RESTRICT
);

CREATE TABLE IF NOT EXISTS migration_logs (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    job_id INT NOT NULL,
    record_id VARCHAR(100) NULL,
    log_level ENUM('info', 'warning', 'error') NOT NULL,
    message TEXT NOT NULL,
    details JSON NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_job (job_id),
    INDEX idx_level (log_level),
    INDEX idx_created_at (created_at),
    FOREIGN KEY (job_id) REFERENCES migration_jobs(id) ON DELETE CASCADE
);

-- Data Warehouse Tables
CREATE TABLE IF NOT EXISTS data_marts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(200) NOT NULL,
    description TEXT NULL,
    source_tables JSON NOT NULL,
    target_table VARCHAR(100) NOT NULL,
    refresh_schedule VARCHAR(100) NULL,
    last_refresh_at TIMESTAMP NULL,
    next_refresh_at TIMESTAMP NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_target_table (target_table),
    INDEX idx_next_refresh (next_refresh_at),
    INDEX idx_active (is_active),
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE RESTRICT
);

CREATE TABLE IF NOT EXISTS etl_processes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(200) NOT NULL,
    process_type ENUM('extract', 'transform', 'load', 'full_etl') NOT NULL,
    source_config JSON NOT NULL,
    transformation_rules JSON NULL,
    target_config JSON NOT NULL,
    schedule_cron VARCHAR(100) NULL,
    status ENUM('active', 'inactive', 'error') DEFAULT 'active',
    last_run_at TIMESTAMP NULL,
    next_run_at TIMESTAMP NULL,
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_process_type (process_type),
    INDEX idx_status (status),
    INDEX idx_next_run (next_run_at),
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE RESTRICT
);

CREATE TABLE IF NOT EXISTS etl_execution_log (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    process_id INT NOT NULL,
    execution_status ENUM('started', 'running', 'completed', 'failed') NOT NULL,
    records_extracted INT DEFAULT 0,
    records_transformed INT DEFAULT 0,
    records_loaded INT DEFAULT 0,
    execution_time_seconds INT DEFAULT 0,
    error_message TEXT NULL,
    execution_details JSON NULL,
    started_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    completed_at TIMESTAMP NULL,
    INDEX idx_process (process_id),
    INDEX idx_status (execution_status),
    INDEX idx_started_at (started_at),
    FOREIGN KEY (process_id) REFERENCES etl_processes(id) ON DELETE CASCADE
);

-- Data Quality and Monitoring Tables
CREATE TABLE IF NOT EXISTS data_quality_rules (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(200) NOT NULL,
    table_name VARCHAR(100) NOT NULL,
    column_name VARCHAR(100) NULL,
    rule_type ENUM('not_null', 'unique', 'range', 'format', 'custom') NOT NULL,
    rule_config JSON NOT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_table_name (table_name),
    INDEX idx_rule_type (rule_type),
    INDEX idx_active (is_active),
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE RESTRICT
);

CREATE TABLE IF NOT EXISTS data_quality_checks (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    rule_id INT NOT NULL,
    check_status ENUM('passed', 'failed', 'warning') NOT NULL,
    records_checked INT DEFAULT 0,
    records_failed INT DEFAULT 0,
    failure_rate DECIMAL(5,2) DEFAULT 0.00,
    details JSON NULL,
    checked_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_rule (rule_id),
    INDEX idx_status (check_status),
    INDEX idx_checked_at (checked_at),
    FOREIGN KEY (rule_id) REFERENCES data_quality_rules(id) ON DELETE CASCADE
);

-- Enhanced Report Templates (extending existing table)
-- Note: MySQL doesn't support IF NOT EXISTS in ALTER TABLE ADD COLUMN
-- These columns will be added if they don't exist
SET @sql = 'ALTER TABLE report_templates ADD COLUMN data_source ENUM(''database'', ''warehouse'', ''api'', ''file'') DEFAULT ''database'' AFTER output_format';
SET @sqlstmt = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
     WHERE TABLE_SCHEMA = DATABASE() 
     AND TABLE_NAME = 'report_templates' 
     AND COLUMN_NAME = 'data_source') > 0,
    'SELECT ''Column data_source already exists'' ______;',
    @sql));
PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = 'ALTER TABLE report_templates ADD COLUMN refresh_interval INT DEFAULT 0 AFTER data_source';
SET @sqlstmt = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
     WHERE TABLE_SCHEMA = DATABASE() 
     AND TABLE_NAME = 'report_templates' 
     AND COLUMN_NAME = 'refresh_interval') > 0,
    'SELECT ''Column refresh_interval already exists'' ______;',
    @sql));
PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = 'ALTER TABLE report_templates ADD COLUMN cache_duration INT DEFAULT 300 AFTER refresh_interval';
SET @sqlstmt = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
     WHERE TABLE_SCHEMA = DATABASE() 
     AND TABLE_NAME = 'report_templates' 
     AND COLUMN_NAME = 'cache_duration') > 0,
    'SELECT ''Column cache_duration already exists'' ______;',
    @sql));
PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = 'ALTER TABLE report_templates ADD COLUMN access_permissions JSON NULL AFTER is_public';
SET @sqlstmt = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
     WHERE TABLE_SCHEMA = DATABASE() 
     AND TABLE_NAME = 'report_templates' 
     AND COLUMN_NAME = 'access_permissions') > 0,
    'SELECT ''Column access_permissions already exists'' ______;',
    @sql));
PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Data Catalog Tables
CREATE TABLE IF NOT EXISTS data_catalog (
    id INT AUTO_INCREMENT PRIMARY KEY,
    entity_type ENUM('table', 'view', 'report', 'dashboard', 'api') NOT NULL,
    entity_name VARCHAR(200) NOT NULL,
    display_name VARCHAR(200) NOT NULL,
    description TEXT NULL,
    schema_name VARCHAR(100) NULL,
    data_classification ENUM('public', 'internal', 'confidential', 'restricted') DEFAULT 'internal',
    data_owner INT NULL,
    business_glossary JSON NULL,
    technical_metadata JSON NULL,
    data_lineage JSON NULL,
    quality_score DECIMAL(3,2) DEFAULT 0.00,
    last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_entity (entity_type, entity_name),
    INDEX idx_entity_type (entity_type),
    INDEX idx_classification (data_classification),
    INDEX idx_owner (data_owner),
    FOREIGN KEY (data_owner) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE RESTRICT
);

-- Insert sample data for demonstration
INSERT IGNORE INTO backup_schedules (name, backup_type, schedule_cron, retention_days, created_by) VALUES
('Daily Full Backup', 'full', '0 2 * * *', 30, 1),
('Hourly Incremental', 'incremental', '0 */6 * * *', 7, 1),
('Weekly System Backup', 'system', '0 6 * * 0', 90, 1);

INSERT IGNORE INTO archive_policies (name, data_type, table_name, archive_after_days, purge_after_years, created_by) VALUES
('Audit Logs Policy', 'audit_logs', 'audit_logs', 90, 7, 1),
('Personnel Records Policy', 'staff_records', 'staff', 365, 50, 1),
('Training Records Policy', 'training_data', 'training_records', 180, 10, 1),
('System Logs Policy', 'system_logs', 'system_logs', 30, 2, 1);

INSERT IGNORE INTO data_marts (name, description, source_tables, target_table, refresh_schedule, created_by) VALUES
('Personnel Analytics', 'Personnel statistics and analytics', '["staff", "ranks", "units"]', 'personnel_analytics', '0 1 * * *', 1),
('Operations Summary', 'Operations performance metrics', '["workflow_instances", "missions", "operations"]', 'operations_summary', '0 */6 * * *', 1),
('Financial Reporting', 'Financial data aggregation', '["budget", "expenses", "financial_records"]', 'financial_reporting', '0 3 * * *', 1);

INSERT IGNORE INTO data_catalog (entity_type, entity_name, display_name, description, data_classification, created_by) VALUES
('table', 'staff', 'Personnel Records', 'Core personnel information and records', 'confidential', 1),
('table', 'audit_logs', 'Audit Trail', 'System activity and security audit logs', 'restricted', 1),
('table', 'inventory_items', 'Equipment Inventory', 'Military equipment and supply inventory', 'internal', 1),
('report', 'personnel_summary', 'Personnel Summary Report', 'Summary statistics of personnel status', 'internal', 1);

-- Create indexes for better performance (only if they don't exist)
SET @sql = 'CREATE INDEX idx_audit_logs_created_at ON audit_logs(created_at)';
SET @sqlstmt = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS 
     WHERE TABLE_SCHEMA = DATABASE() 
     AND TABLE_NAME = 'audit_logs' 
     AND INDEX_NAME = 'idx_audit_logs_created_at') > 0,
    'SELECT ''Index idx_audit_logs_created_at already exists'' ______;',
    @sql));
PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = 'CREATE INDEX idx_analytics_metrics_timestamp ON analytics_metrics(timestamp)';
SET @sqlstmt = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS 
     WHERE TABLE_SCHEMA = DATABASE() 
     AND TABLE_NAME = 'analytics_metrics' 
     AND INDEX_NAME = 'idx_analytics_metrics_timestamp') > 0,
    'SELECT ''Index idx_analytics_metrics_timestamp already exists'' ______;',
    @sql));
PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;