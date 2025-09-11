-- Add mission history table
CREATE TABLE IF NOT EXISTS operations_mission_history (
    history_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    mission_id INT NOT NULL,
    changed_by INT NOT NULL,
    change_type VARCHAR(50) NOT NULL,
    change_details TEXT,
    changed_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (mission_id) REFERENCES operations_missions(mission_id) ON DELETE CASCADE,
    FOREIGN KEY (changed_by) REFERENCES staff(id) ON DELETE SET NULL
);

-- Add activity log table
CREATE TABLE IF NOT EXISTS operations_activity_log (
    log_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    action VARCHAR(100) NOT NULL,
    details TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES staff(id) ON DELETE SET NULL
);

-- Add notifications table
CREATE TABLE IF NOT EXISTS operations_notifications (
    notification_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    message TEXT NOT NULL,
    type VARCHAR(50) NOT NULL,
    is_read TINYINT(1) DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES staff(id) ON DELETE SET NULL
);

-- Add resource maintenance tracking
ALTER TABLE operations_resources ADD COLUMN maintenance_status ENUM('ok','needs_service','in_service') DEFAULT 'ok';

-- Add deployment status tracking
ALTER TABLE operations_deployments ADD COLUMN status ENUM('planned','active','completed','cancelled') DEFAULT 'planned';

-- Add audit fields to status reports
ALTER TABLE operations_status_reports ADD COLUMN reviewed_by INT DEFAULT NULL, ADD COLUMN reviewed_at DATETIME DEFAULT NULL;

-- Add indexes for search/filtering
CREATE INDEX idx_mission_status ON operations_missions(status);
CREATE INDEX idx_deployment_status ON operations_deployments(status);
CREATE INDEX idx_resource_type ON operations_resources(resource_type_id);
CREATE INDEX idx_report_date ON operations_status_reports(report_date);
