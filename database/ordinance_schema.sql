-- Ordinance Module Database Schema
-- Creates tables for ordinance and equipment management functionality

-- Ordinance Inventory Table
CREATE TABLE IF NOT EXISTS ordinance_inventory (
    id INT AUTO_INCREMENT PRIMARY KEY,
    item_name VARCHAR(255) NOT NULL,
    item_code VARCHAR(50) UNIQUE NOT NULL,
    category ENUM('weapon', 'ammunition', 'explosive', 'equipment', 'vehicle', 'accessory', 'maintenance') NOT NULL,
    subcategory VARCHAR(100),
    description TEXT,
    manufacturer VARCHAR(255),
    model VARCHAR(100),
    serial_number VARCHAR(100),
    stock_quantity INT NOT NULL DEFAULT 0,
    minimum_stock_level INT DEFAULT 0,
    maximum_stock_level INT,
    unit_of_measure VARCHAR(50) DEFAULT 'units',
    unit_cost DECIMAL(10,2),
    total_value DECIMAL(15,2) GENERATED ALWAYS AS (stock_quantity * unit_cost) STORED,
    location VARCHAR(255),
    storage_requirements TEXT,
    condition_status ENUM('excellent', 'good', 'fair', 'poor', 'damaged', 'condemned') DEFAULT 'good',
    security_classification ENUM('unclassified', 'restricted', 'confidential', 'secret', 'top_secret') DEFAULT 'unclassified',
    acquisition_date DATE,
    warranty_expiry DATE,
    last_inspection DATE,
    next_inspection DATE,
    status ENUM('active', 'inactive', 'maintenance', 'disposal', 'transferred') DEFAULT 'active',
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES staff(id) ON DELETE RESTRICT,
    INDEX idx_inventory_category (category),
    INDEX idx_inventory_status (status),
    INDEX idx_inventory_condition (condition_status),
    INDEX idx_inventory_location (location),
    INDEX idx_inventory_classification (security_classification)
);

-- Weapons Registry Table
CREATE TABLE IF NOT EXISTS weapons_registry (
    id INT AUTO_INCREMENT PRIMARY KEY,
    weapon_name VARCHAR(255) NOT NULL,
    weapon_type ENUM('rifle', 'pistol', 'machine_gun', 'grenade_launcher', 'sniper_rifle', 'shotgun', 'other') NOT NULL,
    caliber VARCHAR(50),
    serial_number VARCHAR(100) UNIQUE NOT NULL,
    manufacturer VARCHAR(255),
    model VARCHAR(100),
    year_manufactured YEAR,
    condition_status ENUM('excellent', 'good', 'fair', 'poor', 'non_operational') DEFAULT 'good',
    operational_status ENUM('operational', 'maintenance', 'repair', 'condemned', 'storage') DEFAULT 'operational',
    assigned_to_personnel INT,
    assigned_to_unit INT,
    assignment_date DATE,
    last_inspection DATE,
    next_inspection DATE,
    inspection_notes TEXT,
    maintenance_schedule ENUM('daily', 'weekly', 'monthly', 'quarterly', 'annual') DEFAULT 'monthly',
    last_maintenance DATE,
    next_maintenance DATE,
    round_count INT DEFAULT 0,
    max_round_limit INT,
    security_level ENUM('standard', 'high', 'maximum') DEFAULT 'standard',
    storage_location VARCHAR(255),
    accessories TEXT, -- JSON array of accessories
    modification_history TEXT, -- JSON array of modifications
    notes TEXT,
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (assigned_to_personnel) REFERENCES staff(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES staff(id) ON DELETE RESTRICT,
    INDEX idx_weapons_type (weapon_type),
    INDEX idx_weapons_status (operational_status),
    INDEX idx_weapons_assigned (assigned_to_personnel, assigned_to_unit),
    INDEX idx_weapons_serial (serial_number)
);

-- Ammunition Inventory Table
CREATE TABLE IF NOT EXISTS ammunition_inventory (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ammunition_type VARCHAR(255) NOT NULL,
    caliber VARCHAR(50) NOT NULL,
    ammunition_code VARCHAR(50) UNIQUE,
    manufacturer VARCHAR(255),
    lot_number VARCHAR(100),
    quantity INT NOT NULL DEFAULT 0,
    unit_of_measure VARCHAR(50) DEFAULT 'rounds',
    unit_cost DECIMAL(8,2),
    total_value DECIMAL(15,2) GENERATED ALWAYS AS (quantity * unit_cost) STORED,
    condition_status ENUM('excellent', 'good', 'fair', 'poor', 'expired', 'condemned') DEFAULT 'good',
    manufacture_date DATE,
    expiry_date DATE,
    storage_location VARCHAR(255),
    storage_temperature_min DECIMAL(5,2),
    storage_temperature_max DECIMAL(5,2),
    security_classification ENUM('unclassified', 'restricted', 'confidential', 'secret') DEFAULT 'unclassified',
    last_inspection DATE,
    next_inspection DATE,
    inspection_notes TEXT,
    issued_quantity INT DEFAULT 0,
    remaining_quantity INT GENERATED ALWAYS AS (quantity - issued_quantity) STORED,
    minimum_stock_level INT DEFAULT 0,
    status ENUM('active', 'expired', 'disposed', 'transferred', 'quarantined') DEFAULT 'active',
    notes TEXT,
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES staff(id) ON DELETE RESTRICT,
    INDEX idx_ammo_caliber (caliber),
    INDEX idx_ammo_status (status),
    INDEX idx_ammo_condition (condition_status),
    INDEX idx_ammo_expiry (expiry_date),
    INDEX idx_ammo_location (storage_location)
);

-- Maintenance Records Table
CREATE TABLE IF NOT EXISTS maintenance_records (
    id INT AUTO_INCREMENT PRIMARY KEY,
    item_type ENUM('weapon', 'vehicle', 'equipment', 'facility') NOT NULL,
    item_id INT NOT NULL,
    maintenance_type ENUM('preventive', 'corrective', 'emergency', 'overhaul', 'inspection') NOT NULL,
    maintenance_description TEXT NOT NULL,
    priority ENUM('low', 'medium', 'high', 'critical', 'emergency') DEFAULT 'medium',
    scheduled_date DATE,
    actual_start_date DATETIME,
    completion_date DATETIME,
    status ENUM('scheduled', 'in_progress', 'completed', 'cancelled', 'delayed') DEFAULT 'scheduled',
    performed_by INT,
    supervisor_id INT,
    labor_hours DECIMAL(5,2) DEFAULT 0,
    parts_cost DECIMAL(10,2) DEFAULT 0,
    labor_cost DECIMAL(10,2) DEFAULT 0,
    total_cost DECIMAL(10,2) GENERATED ALWAYS AS (parts_cost + labor_cost) STORED,
    parts_used TEXT, -- JSON array of parts used
    procedures_followed TEXT,
    findings TEXT,
    recommendations TEXT,
    next_maintenance_date DATE,
    warranty_covered BOOLEAN DEFAULT FALSE,
    vendor_performed BOOLEAN DEFAULT FALSE,
    vendor_name VARCHAR(255),
    work_order_number VARCHAR(100),
    attachments TEXT, -- JSON array of file paths
    approval_required BOOLEAN DEFAULT FALSE,
    approved_by INT,
    approval_date TIMESTAMP NULL,
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (performed_by) REFERENCES staff(id) ON DELETE SET NULL,
    FOREIGN KEY (supervisor_id) REFERENCES staff(id) ON DELETE SET NULL,
    FOREIGN KEY (approved_by) REFERENCES staff(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES staff(id) ON DELETE RESTRICT,
    INDEX idx_maintenance_type (maintenance_type),
    INDEX idx_maintenance_status (status),
    INDEX idx_maintenance_priority (priority),
    INDEX idx_maintenance_dates (scheduled_date, completion_date),
    INDEX idx_maintenance_item (item_type, item_id)
);

-- Ordinance Transactions Table
CREATE TABLE IF NOT EXISTS ordinance_transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    transaction_type ENUM('issue', 'return', 'transfer', 'dispose', 'inventory', 'loss', 'found') NOT NULL,
    transaction_number VARCHAR(50) UNIQUE,
    item_type ENUM('weapon', 'ammunition', 'equipment', 'vehicle') NOT NULL,
    item_id INT NOT NULL,
    quantity INT NOT NULL,
    unit_of_measure VARCHAR(50) DEFAULT 'units',
    from_personnel INT,
    to_personnel INT,
    from_unit INT,
    to_unit INT,
    from_location VARCHAR(255),
    to_location VARCHAR(255),
    transaction_date DATETIME DEFAULT CURRENT_TIMESTAMP,
    purpose VARCHAR(255),
    authorization_number VARCHAR(100),
    authorized_by INT,
    received_by INT,
    condition_on_issue ENUM('excellent', 'good', 'fair', 'poor') DEFAULT 'good',
    condition_on_return ENUM('excellent', 'good', 'fair', 'poor', 'damaged', 'lost'),
    return_date DATETIME,
    notes TEXT,
    digital_signature TEXT,
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (from_personnel) REFERENCES staff(id) ON DELETE SET NULL,
    FOREIGN KEY (to_personnel) REFERENCES staff(id) ON DELETE SET NULL,
    FOREIGN KEY (authorized_by) REFERENCES staff(id) ON DELETE SET NULL,
    FOREIGN KEY (received_by) REFERENCES staff(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES staff(id) ON DELETE RESTRICT,
    INDEX idx_transactions_type (transaction_type),
    INDEX idx_transactions_date (transaction_date),
    INDEX idx_transactions_item (item_type, item_id),
    INDEX idx_transactions_personnel (from_personnel, to_personnel)
);

-- Ordinance Security Logs Table
CREATE TABLE IF NOT EXISTS ordinance_security_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    event_type ENUM('access', 'transaction', 'alarm', 'inspection', 'security_breach', 'system_event') NOT NULL,
    severity ENUM('info', 'warning', 'critical', 'emergency') DEFAULT 'info',
    description TEXT NOT NULL,
    location VARCHAR(255),
    user_id INT,
    ip_address VARCHAR(45),
    user_agent TEXT,
    item_type ENUM('weapon', 'ammunition', 'equipment', 'facility', 'system'),
    item_id INT,
    transaction_id INT,
    additional_data TEXT, -- JSON data for extra information
    response_action TEXT,
    resolved BOOLEAN DEFAULT FALSE,
    resolved_by INT,
    resolved_at TIMESTAMP NULL,
    resolution_notes TEXT,
    timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES staff(id) ON DELETE SET NULL,
    FOREIGN KEY (transaction_id) REFERENCES ordinance_transactions(id) ON DELETE SET NULL,
    FOREIGN KEY (resolved_by) REFERENCES staff(id) ON DELETE SET NULL,
    INDEX idx_security_event_type (event_type),
    INDEX idx_security_severity (severity),
    INDEX idx_security_timestamp (timestamp),
    INDEX idx_security_user (user_id),
    INDEX idx_security_resolved (resolved)
);

-- Equipment Assignments Table
CREATE TABLE IF NOT EXISTS equipment_assignments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    equipment_type ENUM('weapon', 'vehicle', 'radio', 'protective_gear', 'tool', 'other') NOT NULL,
    equipment_id INT NOT NULL,
    assigned_to_type ENUM('personnel', 'unit', 'location') NOT NULL,
    assigned_to_id INT NOT NULL,
    assignment_date DATE NOT NULL,
    expected_return_date DATE,
    actual_return_date DATE,
    assignment_purpose VARCHAR(255),
    authorization_ref VARCHAR(100),
    condition_on_assignment ENUM('excellent', 'good', 'fair', 'poor') DEFAULT 'good',
    condition_on_return ENUM('excellent', 'good', 'fair', 'poor', 'damaged', 'lost'),
    status ENUM('active', 'returned', 'overdue', 'lost', 'damaged') DEFAULT 'active',
    notes TEXT,
    assigned_by INT NOT NULL,
    returned_to INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (assigned_by) REFERENCES staff(id) ON DELETE RESTRICT,
    FOREIGN KEY (returned_to) REFERENCES staff(id) ON DELETE SET NULL,
    INDEX idx_assignments_equipment (equipment_type, equipment_id),
    INDEX idx_assignments_assigned_to (assigned_to_type, assigned_to_id),
    INDEX idx_assignments_status (status),
    INDEX idx_assignments_dates (assignment_date, expected_return_date)
);

-- Insert sample data for immediate functionality
INSERT INTO ordinance_inventory (item_name, item_code, category, description, manufacturer, stock_quantity, minimum_stock_level, location, created_by) VALUES
('M4 Carbine Rifle', 'WPN-M4-001', 'weapon', 'Standard issue assault rifle', 'Colt Manufacturing', 150, 50, 'Armory Building A', 1),
('Body Armor Vest', 'EQP-BAV-001', 'equipment', 'Level IIIA ballistic protection vest', 'Point Blank Enterprises', 200, 75, 'Equipment Storage B', 1),
('Military Vehicle HMMWV', 'VEH-HMMWV-001', 'vehicle', 'High Mobility Multipurpose Wheeled Vehicle', 'AM General', 25, 10, 'Motor Pool C', 1),
('Radio Communications Set', 'EQP-RADIO-001', 'equipment', 'Tactical radio communication equipment', 'Harris Corporation', 100, 30, 'Communications Depot', 1);

INSERT INTO weapons_registry (weapon_name, weapon_type, caliber, serial_number, manufacturer, model, condition_status, storage_location, created_by) VALUES
('M4 Carbine #001', 'rifle', '5.56mm', 'M4-001-2024', 'Colt Manufacturing', 'M4A1', 'excellent', 'Armory Section A-1', 1),
('M9 Pistol #001', 'pistol', '9mm', 'M9-001-2024', 'Beretta', 'M9', 'good', 'Armory Section A-2', 1),
('M240 Machine Gun #001', 'machine_gun', '7.62mm', 'M240-001-2024', 'FN Herstal', 'M240B', 'excellent', 'Armory Section B-1', 1);

INSERT INTO ammunition_inventory (ammunition_type, caliber, ammunition_code, manufacturer, quantity, unit_cost, storage_location, created_by) VALUES
('Ball Ammunition', '5.56mm', 'AMMO-556-001', 'Federal Premium', 10000, 0.75, 'Ammunition Storage Bunker 1', 1),
('Ball Ammunition', '9mm', 'AMMO-9MM-001', 'Winchester', 5000, 0.45, 'Ammunition Storage Bunker 2', 1),
('Ball Ammunition', '7.62mm', 'AMMO-762-001', 'Lake City Army', 8000, 1.20, 'Ammunition Storage Bunker 1', 1);

INSERT INTO maintenance_records (item_type, item_id, maintenance_type, maintenance_description, priority, scheduled_date, status, created_by) VALUES
('weapon', 1, 'preventive', 'Monthly weapon inspection and cleaning', 'medium', DATE_ADD(CURDATE(), INTERVAL 7 DAY), 'scheduled', 1),
('vehicle', 1, 'preventive', 'Quarterly vehicle maintenance check', 'high', DATE_ADD(CURDATE(), INTERVAL 14 DAY), 'scheduled', 1),
('equipment', 1, 'inspection', 'Annual equipment safety inspection', 'medium', DATE_ADD(CURDATE(), INTERVAL 30 DAY), 'scheduled', 1);

INSERT INTO ordinance_transactions (transaction_type, transaction_number, item_type, item_id, quantity, to_personnel, transaction_date, purpose, created_by) VALUES
('issue', 'TXN-ORD-001', 'weapon', 1, 1, 1, NOW() - INTERVAL 2 HOUR, 'Training Exercise Alpha', 1),
('issue', 'TXN-ORD-002', 'ammunition', 1, 120, 1, NOW() - INTERVAL 1 HOUR, 'Range Training Session', 1);

-- Create indexes for performance
CREATE INDEX idx_inventory_created_at ON ordinance_inventory(created_at);
CREATE INDEX idx_weapons_created_at ON weapons_registry(created_at);
CREATE INDEX idx_ammo_created_at ON ammunition_inventory(created_at);
CREATE INDEX idx_maintenance_created_at ON maintenance_records(created_at);
CREATE INDEX idx_transactions_created_at ON ordinance_transactions(created_at);
CREATE INDEX idx_security_logs_created_at ON ordinance_security_logs(timestamp);