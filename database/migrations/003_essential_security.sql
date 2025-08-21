-- ARMIS Security Enhancement - Essential Tables Only
-- Create critical security and audit tables

-- Create audit log table for tracking all changes
CREATE TABLE IF NOT EXISTS audit_log (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT,
    table_name VARCHAR(50) NOT NULL,
    record_id INT NOT NULL,
    action ENUM('INSERT', 'UPDATE', 'DELETE') NOT NULL,
    field_name VARCHAR(50),
    old_value TEXT,
    new_value TEXT,
    ip_address VARCHAR(45),
    user_agent TEXT,
    timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_audit_user (user_id),
    INDEX idx_audit_table (table_name),
    INDEX idx_audit_record (record_id),
    INDEX idx_audit_timestamp (timestamp)
);

-- Create session management table
CREATE TABLE IF NOT EXISTS user_sessions (
    id VARCHAR(128) PRIMARY KEY,
    user_id INT NOT NULL,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_activity TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    expires_at TIMESTAMP,
    is_active BOOLEAN DEFAULT TRUE,
    INDEX idx_session_user (user_id),
    INDEX idx_session_activity (last_activity),
    INDEX idx_session_expires (expires_at)
);

-- Create CSRF tokens table
CREATE TABLE IF NOT EXISTS csrf_tokens (
    token VARCHAR(64) PRIMARY KEY,
    user_id INT NOT NULL,
    expires_at TIMESTAMP NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_csrf_user (user_id),
    INDEX idx_csrf_expires (expires_at)
);

-- Insert comprehensive military ranks if table is empty
INSERT IGNORE INTO ranks (name, abbreviation) VALUES
-- Officers
('General', 'GEN'),
('Lieutenant General', 'LT GEN'),
('Major General', 'MAJ GEN'),
('Brigadier General', 'BRIG GEN'),
('Colonel', 'COL'),
('Lieutenant Colonel', 'LT COL'),
('Major', 'MAJ'),
('Captain', 'CAPT'),
('First Lieutenant', '1LT'),
('Second Lieutenant', '2LT'),
-- NCOs
('Sergeant Major', 'SGM'),
('Master Sergeant', 'MSG'),
('Sergeant First Class', 'SFC'),
('Staff Sergeant', 'SSG'),
('Sergeant', 'SGT'),
('Corporal', 'CPL'),
-- Enlisted
('Specialist', 'SPC'),
('Private First Class', 'PFC'),
('Private', 'PVT'),
('Recruit', 'RCT');

-- Insert sample military units
INSERT IGNORE INTO units (name, code, type) VALUES
('Headquarters Battalion', 'HQ-BTN', 'Command'),
('1st Infantry Battalion', '1-INF', 'Infantry'),
('2nd Infantry Battalion', '2-INF', 'Infantry'),
('Artillery Regiment', 'ARTY-REG', 'Artillery'),
('Engineer Battalion', 'ENG-BTN', 'Engineering'),
('Signal Battalion', 'SIG-BTN', 'Communications'),
('Medical Battalion', 'MED-BTN', 'Medical'),
('Military Police Battalion', 'MP-BTN', 'Military Police');

-- Insert military corps
INSERT IGNORE INTO corps (name, abbreviation) VALUES
('Infantry', 'INF'),
('Artillery', 'ARTY'),
('Armor', 'ARM'),
('Engineers', 'ENG'),
('Signal', 'SIG'),
('Medical', 'MED'),
('Military Police', 'MP'),
('Intelligence', 'INT'),
('Logistics', 'LOG'),
('Aviation', 'AV');

-- Create optimized indexes for performance
CREATE INDEX idx_staff_service_number ON staff(service_number);
CREATE INDEX idx_staff_rank ON staff(rank_id);
CREATE INDEX idx_staff_unit ON staff(unit_id);
CREATE INDEX idx_staff_name ON staff(first_name, last_name);
CREATE INDEX idx_staff_email ON staff(email);
CREATE INDEX idx_staff_last_login ON staff(last_login);
CREATE INDEX idx_staff_created ON staff(created_at);