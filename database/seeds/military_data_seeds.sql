-- ARMIS Military Data Seeds
-- Sample data for ranks, units, corps, and other military-specific tables

-- Insert military ranks with proper hierarchy
INSERT IGNORE INTO ranks (id, name, abbreviation, rank_level, rank_category, minimum_service_years, base_salary) VALUES
-- Enlisted Ranks
(1, 'Private', 'Pte', 1, 'Enlisted', 0, 25000.00),
(2, 'Lance Corporal', 'LCpl', 2, 'Enlisted', 1, 27000.00),
(3, 'Corporal', 'Cpl', 3, 'Enlisted', 2, 30000.00),
(4, 'Sergeant', 'Sgt', 4, 'NCO', 4, 35000.00),
(5, 'Staff Sergeant', 'SSgt', 5, 'NCO', 6, 40000.00),
(6, 'Warrant Officer Class 2', 'WO2', 6, 'NCO', 8, 45000.00),
(7, 'Warrant Officer Class 1', 'WO1', 7, 'NCO', 12, 50000.00),
-- Officer Ranks
(8, 'Second Lieutenant', '2Lt', 10, 'Officer', 0, 45000.00),
(9, 'Lieutenant', 'Lt', 11, 'Officer', 2, 50000.00),
(10, 'Captain', 'Capt', 12, 'Officer', 4, 60000.00),
(11, 'Major', 'Maj', 13, 'Officer', 8, 75000.00),
(12, 'Lieutenant Colonel', 'Lt Col', 14, 'Officer', 12, 90000.00),
(13, 'Colonel', 'Col', 15, 'Officer', 16, 110000.00),
(14, 'Brigadier', 'Brig', 16, 'Officer', 20, 130000.00),
(15, 'Major General', 'Maj Gen', 17, 'Officer', 24, 150000.00),
(16, 'Lieutenant General', 'Lt Gen', 18, 'Officer', 28, 180000.00),
(17, 'General', 'Gen', 19, 'Officer', 32, 220000.00);

-- Insert military corps
INSERT IGNORE INTO corps (id, name, abbreviation, corps_type, description, motto, color_code) VALUES
(1, 'Infantry', 'INF', 'Combat', 'Foot soldiers and close combat specialists', 'Follow Me', '#8B4513'),
(2, 'Artillery', 'ARTY', 'Combat', 'Heavy weapons and fire support', 'King of Battle', '#FF0000'),
(3, 'Engineers', 'ENG', 'Combat Support', 'Military engineering and construction', 'Essayons', '#0000FF'),
(4, 'Signals', 'SIG', 'Combat Support', 'Communications and information systems', 'Certa Cito', '#00FF00'),
(5, 'Medical', 'MED', 'Combat Service Support', 'Healthcare and medical services', 'To Preserve Life', '#FF69B4'),
(6, 'Logistics', 'LOG', 'Combat Service Support', 'Supply chain and transportation', 'We Support', '#800080'),
(7, 'Intelligence', 'INT', 'Combat Support', 'Military intelligence and reconnaissance', 'Knowledge is Power', '#000000'),
(8, 'Military Police', 'MP', 'Combat Support', 'Law enforcement and security', 'Assist Protect Defend', '#FFD700'),
(9, 'Armored', 'ARM', 'Combat', 'Tank and armored vehicle operations', 'Through Mobility We Conquer', '#A0522D'),
(10, 'Special Forces', 'SF', 'Special Forces', 'Elite special operations', 'De Oppresso Liber', '#228B22');

-- Insert military units with hierarchy
INSERT IGNORE INTO units (id, name, code, unit_type, location, operational_status, parent_unit_id) VALUES
-- Command Level
(1, 'Army Headquarters', 'AHQ', 'Command', 'Capital City', 'Active', NULL),
(2, '1st Infantry Division', '1ID', 'Command', 'Northern Base', 'Active', 1),
(3, '2nd Armored Division', '2AD', 'Command', 'Central Base', 'Active', 1),
(4, 'Special Operations Command', 'SOCOM', 'Command', 'Special Base', 'Active', 1),
-- Battalions
(5, '1st Infantry Battalion', '1INF', 'Battalion', 'Northern Base', 'Active', 2),
(6, '2nd Infantry Battalion', '2INF', 'Battalion', 'Northern Base', 'Active', 2),
(7, '1st Artillery Battalion', '1ART', 'Battalion', 'Northern Base', 'Active', 2),
(8, '1st Armored Battalion', '1ARM', 'Battalion', 'Central Base', 'Active', 3),
(9, '2nd Armored Battalion', '2ARM', 'Battalion', 'Central Base', 'Active', 3),
(10, '1st Special Forces Group', '1SFG', 'Battalion', 'Special Base', 'Active', 4),
-- Companies
(11, 'Alpha Company', 'A-CO', 'Company', 'Northern Base', 'Active', 5),
(12, 'Bravo Company', 'B-CO', 'Company', 'Northern Base', 'Active', 5),
(13, 'Charlie Company', 'C-CO', 'Company', 'Northern Base', 'Active', 5),
(14, 'Headquarters Company', 'HQ-CO', 'Company', 'Northern Base', 'Active', 5),
-- Support Units
(15, 'Medical Battalion', 'MED-BN', 'Battalion', 'Capital City', 'Active', 1),
(16, 'Signal Battalion', 'SIG-BN', 'Battalion', 'Capital City', 'Active', 1),
(17, 'Engineer Battalion', 'ENG-BN', 'Battalion', 'Central Base', 'Active', 1),
(18, 'Logistics Battalion', 'LOG-BN', 'Battalion', 'Southern Base', 'Active', 1);

-- Insert sample training compliance requirements
INSERT IGNORE INTO training_compliance (staff_id, training_type, training_name, requirement_frequency_months, compliance_status, next_due_date) VALUES
(1, 'Mandatory', 'Basic Military Training Refresher', 12, 'Current', DATE_ADD(CURDATE(), INTERVAL 6 MONTH)),
(1, 'Safety', 'Weapon Safety Training', 6, 'Current', DATE_ADD(CURDATE(), INTERVAL 3 MONTH)),
(1, 'Security', 'Information Security Awareness', 12, 'Current', DATE_ADD(CURDATE(), INTERVAL 8 MONTH)),
(1, 'Professional', 'Leadership Development Course', 24, 'Due Soon', DATE_ADD(CURDATE(), INTERVAL 1 MONTH));

-- Insert sample medical records for admin user
INSERT IGNORE INTO medical_records (staff_id, medical_exam_date, fitness_category, overall_status, height_cm, weight_kg, vision_category, hearing_category, blood_pressure, next_exam_due, examining_physician, medical_facility, is_deployment_ready) VALUES
(1, DATE_SUB(CURDATE(), INTERVAL 6 MONTH), 'A1', 'Fit', 175, 75.5, '20/20', 'H1', '120/80', DATE_ADD(CURDATE(), INTERVAL 6 MONTH), 'Dr. Military Physician', 'Base Medical Center', TRUE);

-- Insert sample family readiness record
INSERT IGNORE INTO family_readiness (staff_id, family_care_plan_status, emergency_contacts_updated, children_count, family_readiness_score, last_assessment_date) VALUES
(1, 'Not Required', TRUE, 0, 'High', CURDATE());

-- Insert profile completion tracking for admin user
INSERT IGNORE INTO profile_completion (staff_id, section, completion_percentage, required_fields_total, completed_fields) VALUES
(1, 'personal_info', 85.00, 20, 17),
(1, 'service_record', 90.00, 10, 9),
(1, 'training_history', 75.00, 8, 6),
(1, 'family_info', 60.00, 15, 9),
(1, 'medical_info', 95.00, 12, 11),
(1, 'security_clearance', 100.00, 5, 5);

-- Update admin user with enhanced profile data
UPDATE staff SET 
    DOB = '1985-01-15',
    gender = 'M',
    NRC = 'ID123456789',
    tel = '+1-555-0123',
    address = '123 Military Base, Headquarters',
    bloodGp = 'O+',
    marital = 'Single',
    attestDate = '2005-06-01',
    prefix = 'Mr',
    emergency_contact_name = 'John Emergency',
    emergency_contact_phone = '+1-555-9999',
    emergency_contact_relationship = 'Brother',
    security_clearance_level = 'Secret',
    clearance_expiry_date = DATE_ADD(CURDATE(), INTERVAL 2 YEAR),
    medical_status = 'Fit',
    medical_expiry_date = DATE_ADD(CURDATE(), INTERVAL 1 YEAR),
    deployment_status = 'Available',
    rank_id = 10,
    unit_id = 1,
    corps = 'INF'
WHERE username = 'admin';

-- Insert security clearance record for admin
INSERT IGNORE INTO security_clearances (staff_id, clearance_level, granted_date, expiry_date, granting_authority, investigation_type, is_active) VALUES
(1, 'Secret', DATE_SUB(CURDATE(), INTERVAL 1 YEAR), DATE_ADD(CURDATE(), INTERVAL 2 YEAR), 'Defense Security Agency', 'Secret Background Investigation', TRUE);

-- Insert sample service record for admin
INSERT IGNORE INTO service_records (staff_id, record_type, record_date, title, description, to_rank_id, to_unit_id, approving_officer_id, reference_number) VALUES
(1, 'Enlistment', '2005-06-01', 'Initial Enlistment', 'Enlisted as Private in Infantry Corps', 1, 5, 1, 'ENL-2005-001'),
(1, 'Promotion', '2007-06-01', 'Promotion to Captain', 'Promoted to Captain based on performance and completion of Officer Training', 10, 1, 1, 'PROM-2007-045'),
(1, 'Transfer', '2010-03-15', 'Transfer to Headquarters', 'Transferred to Army Headquarters for administrative duties', 10, 1, 1, 'TRANS-2010-012');

-- Create default CSRF protection and audit settings
CREATE TABLE IF NOT EXISTS system_settings (
    id INT PRIMARY KEY AUTO_INCREMENT,
    setting_key VARCHAR(100) NOT NULL UNIQUE,
    setting_value TEXT NOT NULL,
    description TEXT,
    updated_by_staff_id INT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (updated_by_staff_id) REFERENCES staff(id) ON DELETE SET NULL
);

INSERT IGNORE INTO system_settings (setting_key, setting_value, description, updated_by_staff_id) VALUES
('csrf_protection_enabled', 'true', 'Enable CSRF protection for all forms', 1),
('profile_audit_enabled', 'true', 'Enable comprehensive audit logging for profile changes', 1),
('security_clearance_alerts', 'true', 'Enable alerts for expiring security clearances', 1),
('medical_fitness_alerts', 'true', 'Enable alerts for medical fitness expiry', 1),
('training_compliance_alerts', 'true', 'Enable alerts for training compliance deadlines', 1),
('auto_calculate_service_years', 'true', 'Automatically calculate service years from attestation date', 1),
('profile_completion_threshold', '80', 'Minimum profile completion percentage for deployment readiness', 1),
('password_complexity_enabled', 'true', 'Enable password complexity requirements', 1),
('session_timeout_minutes', '60', 'Session timeout in minutes', 1),
('max_file_upload_size_mb', '10', 'Maximum file upload size in megabytes', 1);