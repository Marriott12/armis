-- Operations Module Database Schema
-- Creates tables for operations management functionality

-- Operations Missions Table
CREATE TABLE IF NOT EXISTS operations_missions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    mission_name VARCHAR(255) NOT NULL,
    description TEXT,
    mission_type ENUM('reconnaissance', 'combat', 'logistics', 'training', 'humanitarian', 'peacekeeping') NOT NULL,
    status ENUM('planning', 'active', 'completed', 'cancelled', 'on-hold') DEFAULT 'planning',
    priority ENUM('low', 'medium', 'high', 'critical') DEFAULT 'medium',
    start_date DATETIME,
    end_date DATETIME,
    estimated_duration INT, -- in hours
    location VARCHAR(500),
    coordinates VARCHAR(100), -- GPS coordinates
    commander_id INT,
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (commander_id) REFERENCES staff(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES staff(id) ON DELETE RESTRICT,
    INDEX idx_missions_status (status),
    INDEX idx_missions_priority (priority),
    INDEX idx_missions_dates (start_date, end_date)
);

-- Operations Deployments Table
CREATE TABLE IF NOT EXISTS operations_deployments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    deployment_name VARCHAR(255) NOT NULL,
    mission_id INT,
    unit_id INT,
    status ENUM('planning', 'deployed', 'active', 'returning', 'completed') DEFAULT 'planning',
    deployment_type ENUM('combat', 'peacekeeping', 'training', 'logistics', 'support') NOT NULL,
    start_date DATETIME,
    end_date DATETIME,
    location VARCHAR(500),
    personnel_count INT DEFAULT 0,
    equipment_allocated TEXT, -- JSON data for equipment
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (mission_id) REFERENCES operations_missions(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES staff(id) ON DELETE RESTRICT,
    INDEX idx_deployments_status (status),
    INDEX idx_deployments_mission (mission_id),
    INDEX idx_deployments_unit (unit_id)
);

-- Deployment Personnel Table
CREATE TABLE IF NOT EXISTS deployment_personnel (
    id INT AUTO_INCREMENT PRIMARY KEY,
    deployment_id INT NOT NULL,
    personnel_id INT NOT NULL,
    role VARCHAR(100),
    assignment_date DATETIME DEFAULT CURRENT_TIMESTAMP,
    return_date DATETIME,
    status ENUM('assigned', 'deployed', 'returned', 'casualties') DEFAULT 'assigned',
    notes TEXT,
    FOREIGN KEY (deployment_id) REFERENCES operations_deployments(id) ON DELETE CASCADE,
    FOREIGN KEY (personnel_id) REFERENCES staff(id) ON DELETE CASCADE,
    UNIQUE KEY unique_deployment_personnel (deployment_id, personnel_id),
    INDEX idx_deployment_personnel_status (status)
);

-- Operations Resources Table
CREATE TABLE IF NOT EXISTS operations_resources (
    id INT AUTO_INCREMENT PRIMARY KEY,
    resource_name VARCHAR(255) NOT NULL,
    resource_type ENUM('personnel', 'vehicle', 'weapon', 'equipment', 'supply', 'ammunition') NOT NULL,
    resource_code VARCHAR(50) UNIQUE,
    available_quantity INT DEFAULT 0,
    allocated_quantity INT DEFAULT 0,
    unit_of_measure VARCHAR(50) DEFAULT 'units',
    location VARCHAR(255),
    condition_status ENUM('excellent', 'good', 'fair', 'poor', 'non-operational') DEFAULT 'good',
    last_maintenance DATE,
    next_maintenance DATE,
    cost_per_unit DECIMAL(10,2),
    supplier VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_resources_type (resource_type),
    INDEX idx_resources_status (condition_status),
    INDEX idx_resources_location (location)
);

-- Operations Equipment Table
CREATE TABLE IF NOT EXISTS operations_equipment (
    id INT AUTO_INCREMENT PRIMARY KEY,
    equipment_name VARCHAR(255) NOT NULL,
    equipment_type VARCHAR(100) NOT NULL,
    serial_number VARCHAR(100) UNIQUE,
    status ENUM('operational', 'maintenance', 'repair', 'decommissioned') DEFAULT 'operational',
    assigned_to_unit INT,
    assigned_to_personnel INT,
    current_location VARCHAR(255),
    acquisition_date DATE,
    last_service_date DATE,
    next_service_date DATE,
    specifications TEXT, -- JSON data for technical specs
    maintenance_log TEXT, -- JSON data for maintenance history
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (assigned_to_personnel) REFERENCES staff(id) ON DELETE SET NULL,
    INDEX idx_equipment_status (status),
    INDEX idx_equipment_type (equipment_type),
    INDEX idx_equipment_assigned (assigned_to_unit, assigned_to_personnel)
);

-- Operations Alerts Table
CREATE TABLE IF NOT EXISTS operations_alerts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    alert_title VARCHAR(255) NOT NULL,
    alert_message TEXT NOT NULL,
    alert_type ENUM('security', 'equipment', 'personnel', 'mission', 'logistics', 'intelligence') NOT NULL,
    priority ENUM('low', 'medium', 'high', 'critical') DEFAULT 'medium',
    status ENUM('active', 'acknowledged', 'resolved', 'dismissed') DEFAULT 'active',
    source VARCHAR(100),
    affected_area VARCHAR(255),
    created_by INT,
    assigned_to INT,
    resolution_notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    acknowledged_at TIMESTAMP NULL,
    resolved_at TIMESTAMP NULL,
    FOREIGN KEY (created_by) REFERENCES staff(id) ON DELETE SET NULL,
    FOREIGN KEY (assigned_to) REFERENCES staff(id) ON DELETE SET NULL,
    INDEX idx_alerts_status (status),
    INDEX idx_alerts_priority (priority),
    INDEX idx_alerts_type (alert_type)
);

-- Operations Personnel Readiness Table
CREATE TABLE IF NOT EXISTS operations_personnel_readiness (
    id INT AUTO_INCREMENT PRIMARY KEY,
    personnel_id INT NOT NULL,
    readiness_status ENUM('ready', 'training', 'unavailable', 'medical', 'leave') DEFAULT 'ready',
    last_training_date DATE,
    certification_status ENUM('current', 'expired', 'pending') DEFAULT 'current',
    medical_clearance DATE,
    equipment_assigned TEXT, -- JSON data for assigned equipment
    specializations TEXT, -- JSON data for special skills
    availability_start DATE,
    availability_end DATE,
    notes TEXT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (personnel_id) REFERENCES staff(id) ON DELETE CASCADE,
    UNIQUE KEY unique_personnel_readiness (personnel_id),
    INDEX idx_readiness_status (readiness_status),
    INDEX idx_readiness_cert (certification_status)
);

-- Mission Objectives Table
CREATE TABLE IF NOT EXISTS mission_objectives (
    id INT AUTO_INCREMENT PRIMARY KEY,
    mission_id INT NOT NULL,
    objective_title VARCHAR(255) NOT NULL,
    objective_description TEXT,
    priority_order INT DEFAULT 1,
    status ENUM('pending', 'in-progress', 'completed', 'failed', 'cancelled') DEFAULT 'pending',
    assigned_to INT,
    target_completion DATETIME,
    actual_completion DATETIME,
    success_criteria TEXT,
    completion_notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (mission_id) REFERENCES operations_missions(id) ON DELETE CASCADE,
    FOREIGN KEY (assigned_to) REFERENCES staff(id) ON DELETE SET NULL,
    INDEX idx_objectives_mission (mission_id),
    INDEX idx_objectives_status (status)
);

-- Resource Allocation Log Table
CREATE TABLE IF NOT EXISTS resource_allocation_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    resource_id INT NOT NULL,
    allocation_type ENUM('mission', 'deployment', 'training', 'maintenance') NOT NULL,
    reference_id INT, -- mission_id, deployment_id, etc.
    quantity_allocated INT NOT NULL,
    allocation_date DATETIME DEFAULT CURRENT_TIMESTAMP,
    return_date DATETIME,
    actual_return_date DATETIME,
    condition_on_return ENUM('excellent', 'good', 'fair', 'poor', 'damaged', 'lost') DEFAULT 'good',
    allocated_by INT NOT NULL,
    returned_by INT,
    notes TEXT,
    FOREIGN KEY (resource_id) REFERENCES operations_resources(id) ON DELETE CASCADE,
    FOREIGN KEY (allocated_by) REFERENCES staff(id) ON DELETE RESTRICT,
    FOREIGN KEY (returned_by) REFERENCES staff(id) ON DELETE SET NULL,
    INDEX idx_allocation_resource (resource_id),
    INDEX idx_allocation_type (allocation_type),
    INDEX idx_allocation_dates (allocation_date, return_date)
);

-- Insert sample data for immediate functionality
INSERT INTO operations_missions (mission_name, description, mission_type, status, priority, start_date, location, created_by) VALUES
('Operation Phoenix', 'Strategic reconnaissance mission in Sector 7', 'reconnaissance', 'active', 'high', NOW() - INTERVAL 6 HOUR, 'Sector 7, Northern Region', 1),
('Operation Shield', 'Defensive perimeter establishment', 'combat', 'completed', 'medium', NOW() - INTERVAL 2 DAY, 'Forward Base Alpha', 1),
('Operation Thunder', 'Equipment deployment and testing', 'logistics', 'planning', 'medium', NOW() + INTERVAL 1 WEEK, 'Training Facility Bravo', 1);

INSERT INTO operations_deployments (deployment_name, mission_id, status, deployment_type, start_date, location, personnel_count, created_by) VALUES
('Alpha Squadron Deploy', 1, 'deployed', 'combat', NOW() - INTERVAL 6 HOUR, 'Sector 7, Northern Region', 15, 1),
('Logistics Support Team', 3, 'planning', 'logistics', NOW() + INTERVAL 1 WEEK, 'Training Facility Bravo', 8, 1);

INSERT INTO operations_resources (resource_name, resource_type, available_quantity, allocated_quantity, unit_of_measure, location, condition_status) VALUES
('Combat Vehicles', 'vehicle', 25, 15, 'units', 'Motor Pool A', 'good'),
('Communications Equipment', 'equipment', 50, 35, 'units', 'Supply Depot B', 'excellent'),
('Field Rations', 'supply', 1000, 750, 'cases', 'Supply Depot C', 'good'),
('Medical Supplies', 'supply', 200, 150, 'kits', 'Medical Facility', 'excellent');

INSERT INTO operations_alerts (alert_title, alert_message, alert_type, priority, status, source, affected_area, created_by) VALUES
('Equipment Maintenance Due', 'Vehicle fleet requires scheduled maintenance', 'equipment', 'medium', 'active', 'Maintenance System', 'Motor Pool A', 1),
('Security Alert - Perimeter', 'Unusual activity detected on eastern perimeter', 'security', 'high', 'active', 'Security System', 'Eastern Sector', 1);

INSERT INTO operations_equipment (equipment_name, equipment_type, serial_number, status, current_location) VALUES
('Combat Vehicle CV-001', 'Vehicle', 'CV-001-2024', 'operational', 'Motor Pool A'),
('Radio Set RS-100', 'Communications', 'RS-100-001', 'operational', 'Communications Center'),
('Medical Kit MK-50', 'Medical', 'MK-50-001', 'operational', 'Medical Facility');

-- Create indexes for performance
CREATE INDEX idx_missions_created_at ON operations_missions(created_at);
CREATE INDEX idx_deployments_dates ON operations_deployments(start_date, end_date);
CREATE INDEX idx_alerts_created_at ON operations_alerts(created_at);
CREATE INDEX idx_equipment_service_dates ON operations_equipment(next_service_date);