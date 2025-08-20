-- Finance Module Database Schema
-- Creates tables for financial management functionality

-- Finance Budgets Table
CREATE TABLE IF NOT EXISTS finance_budgets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    budget_name VARCHAR(255) NOT NULL,
    description TEXT,
    fiscal_year YEAR NOT NULL,
    category VARCHAR(100) NOT NULL,
    allocated_amount DECIMAL(15,2) NOT NULL DEFAULT 0,
    spent_amount DECIMAL(15,2) NOT NULL DEFAULT 0,
    remaining_amount DECIMAL(15,2) GENERATED ALWAYS AS (allocated_amount - spent_amount) STORED,
    status ENUM('draft', 'proposed', 'approved', 'active', 'locked', 'closed') DEFAULT 'draft',
    approval_date DATE,
    start_date DATE,
    end_date DATE,
    created_by INT NOT NULL,
    approved_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES staff(id) ON DELETE RESTRICT,
    FOREIGN KEY (approved_by) REFERENCES staff(id) ON DELETE SET NULL,
    INDEX idx_budgets_fiscal_year (fiscal_year),
    INDEX idx_budgets_category (category),
    INDEX idx_budgets_status (status)
);

-- Finance Transactions Table
CREATE TABLE IF NOT EXISTS finance_transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    transaction_type ENUM('income', 'expense', 'transfer', 'adjustment') NOT NULL,
    reference_number VARCHAR(50) UNIQUE,
    description TEXT NOT NULL,
    amount DECIMAL(15,2) NOT NULL,
    category VARCHAR(100) NOT NULL,
    subcategory VARCHAR(100),
    budget_id INT,
    vendor_supplier VARCHAR(255),
    invoice_number VARCHAR(100),
    receipt_number VARCHAR(100),
    transaction_date DATE NOT NULL,
    due_date DATE,
    payment_date DATE,
    payment_method ENUM('cash', 'check', 'bank_transfer', 'credit_card', 'other') DEFAULT 'bank_transfer',
    status ENUM('pending', 'approved', 'paid', 'cancelled', 'rejected') DEFAULT 'pending',
    approver_id INT,
    approval_date TIMESTAMP NULL,
    notes TEXT,
    attachments TEXT, -- JSON array of file paths
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (budget_id) REFERENCES finance_budgets(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES staff(id) ON DELETE RESTRICT,
    FOREIGN KEY (approver_id) REFERENCES staff(id) ON DELETE SET NULL,
    INDEX idx_transactions_date (transaction_date),
    INDEX idx_transactions_status (status),
    INDEX idx_transactions_type (transaction_type),
    INDEX idx_transactions_category (category)
);

-- Finance Contracts Table
CREATE TABLE IF NOT EXISTS finance_contracts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    contract_name VARCHAR(255) NOT NULL,
    contract_number VARCHAR(100) UNIQUE,
    vendor_name VARCHAR(255) NOT NULL,
    vendor_contact TEXT,
    contract_type ENUM('service', 'supply', 'construction', 'maintenance', 'consulting', 'other') NOT NULL,
    description TEXT,
    contract_value DECIMAL(15,2) NOT NULL,
    currency VARCHAR(3) DEFAULT 'USD',
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    status ENUM('draft', 'pending', 'active', 'completed', 'terminated', 'expired') DEFAULT 'draft',
    payment_terms TEXT,
    renewal_option BOOLEAN DEFAULT FALSE,
    auto_renewal BOOLEAN DEFAULT FALSE,
    notice_period_days INT DEFAULT 30,
    contract_manager_id INT,
    signed_date DATE,
    contract_file_path VARCHAR(500),
    notes TEXT,
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (contract_manager_id) REFERENCES staff(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES staff(id) ON DELETE RESTRICT,
    INDEX idx_contracts_status (status),
    INDEX idx_contracts_dates (start_date, end_date),
    INDEX idx_contracts_vendor (vendor_name)
);

-- Finance Purchase Orders Table
CREATE TABLE IF NOT EXISTS finance_purchase_orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    po_number VARCHAR(50) UNIQUE NOT NULL,
    vendor_name VARCHAR(255) NOT NULL,
    vendor_contact TEXT,
    total_amount DECIMAL(15,2) NOT NULL,
    currency VARCHAR(3) DEFAULT 'USD',
    order_date DATE NOT NULL,
    expected_delivery DATE,
    actual_delivery DATE,
    status ENUM('draft', 'pending', 'approved', 'ordered', 'delivered', 'completed', 'cancelled') DEFAULT 'draft',
    budget_id INT,
    contract_id INT,
    delivery_address TEXT,
    special_instructions TEXT,
    created_by INT NOT NULL,
    approved_by INT,
    approved_date TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (budget_id) REFERENCES finance_budgets(id) ON DELETE SET NULL,
    FOREIGN KEY (contract_id) REFERENCES finance_contracts(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES staff(id) ON DELETE RESTRICT,
    FOREIGN KEY (approved_by) REFERENCES staff(id) ON DELETE SET NULL,
    INDEX idx_po_status (status),
    INDEX idx_po_date (order_date),
    INDEX idx_po_vendor (vendor_name)
);

-- Finance Purchase Order Items Table
CREATE TABLE IF NOT EXISTS finance_po_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    po_id INT NOT NULL,
    item_description TEXT NOT NULL,
    quantity DECIMAL(10,2) NOT NULL,
    unit_price DECIMAL(10,2) NOT NULL,
    total_price DECIMAL(15,2) GENERATED ALWAYS AS (quantity * unit_price) STORED,
    unit_of_measure VARCHAR(50) DEFAULT 'units',
    part_number VARCHAR(100),
    specifications TEXT,
    delivery_status ENUM('pending', 'partial', 'delivered', 'cancelled') DEFAULT 'pending',
    delivered_quantity DECIMAL(10,2) DEFAULT 0,
    delivery_date DATE,
    notes TEXT,
    FOREIGN KEY (po_id) REFERENCES finance_purchase_orders(id) ON DELETE CASCADE,
    INDEX idx_po_items_status (delivery_status)
);

-- Finance Audit Log Table
CREATE TABLE IF NOT EXISTS finance_audit_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    table_name VARCHAR(50) NOT NULL,
    record_id INT NOT NULL,
    action ENUM('INSERT', 'UPDATE', 'DELETE') NOT NULL,
    old_values TEXT, -- JSON of old values
    new_values TEXT, -- JSON of new values
    changed_fields TEXT, -- JSON array of changed field names
    user_id INT NOT NULL,
    ip_address VARCHAR(45),
    user_agent TEXT,
    timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES staff(id) ON DELETE RESTRICT,
    INDEX idx_audit_table (table_name),
    INDEX idx_audit_record (record_id),
    INDEX idx_audit_user (user_id),
    INDEX idx_audit_timestamp (timestamp)
);

-- Finance Reports Table
CREATE TABLE IF NOT EXISTS finance_reports (
    id INT AUTO_INCREMENT PRIMARY KEY,
    report_name VARCHAR(255) NOT NULL,
    report_type ENUM('budget', 'expense', 'income', 'contract', 'procurement', 'audit', 'custom') NOT NULL,
    description TEXT,
    parameters TEXT, -- JSON data for report parameters
    generated_by INT NOT NULL,
    generated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    file_path VARCHAR(500),
    file_format ENUM('pdf', 'excel', 'csv', 'html') DEFAULT 'pdf',
    status ENUM('generating', 'completed', 'failed', 'expired') DEFAULT 'generating',
    expiry_date TIMESTAMP NULL,
    download_count INT DEFAULT 0,
    is_scheduled BOOLEAN DEFAULT FALSE,
    schedule_frequency ENUM('daily', 'weekly', 'monthly', 'quarterly', 'annually') NULL,
    next_generation TIMESTAMP NULL,
    FOREIGN KEY (generated_by) REFERENCES staff(id) ON DELETE RESTRICT,
    INDEX idx_reports_type (report_type),
    INDEX idx_reports_status (status),
    INDEX idx_reports_generated (generated_at)
);

-- Insert sample data for immediate functionality
INSERT INTO finance_budgets (budget_name, description, fiscal_year, category, allocated_amount, status, created_by) VALUES
('Personnel Budget', 'Annual personnel and salary allocations', YEAR(CURDATE()), 'Personnel', 2500000.00, 'approved', 1),
('Equipment Budget', 'Equipment procurement and maintenance', YEAR(CURDATE()), 'Equipment', 1000000.00, 'approved', 1),
('Operations Budget', 'Operational expenses and logistics', YEAR(CURDATE()), 'Operations', 750000.00, 'approved', 1),
('Training Budget', 'Training programs and education', YEAR(CURDATE()), 'Training', 300000.00, 'approved', 1);

INSERT INTO finance_transactions (transaction_type, reference_number, description, amount, category, transaction_date, status, created_by) VALUES
('expense', 'TXN-2024-001', 'Equipment maintenance contract payment', 15000.00, 'Equipment', CURDATE() - INTERVAL 5 DAY, 'approved', 1),
('expense', 'TXN-2024-002', 'Training materials procurement', 3500.00, 'Training', CURDATE() - INTERVAL 3 DAY, 'approved', 1),
('expense', 'TXN-2024-003', 'Fuel and logistics expenses', 8200.00, 'Operations', CURDATE() - INTERVAL 1 DAY, 'pending', 1),
('income', 'TXN-2024-004', 'Budget allocation for Q2', 500000.00, 'Budget Transfer', CURDATE(), 'approved', 1);

INSERT INTO finance_contracts (contract_name, contract_number, vendor_name, contract_type, description, contract_value, start_date, end_date, status, created_by) VALUES
('IT Services Contract', 'CNT-2024-001', 'TechSolutions Inc.', 'service', 'Annual IT support and maintenance services', 120000.00, CURDATE() - INTERVAL 30 DAY, DATE_ADD(CURDATE(), INTERVAL 11 MONTH), 'active', 1),
('Vehicle Maintenance Contract', 'CNT-2024-002', 'AutoCare Services', 'maintenance', 'Fleet maintenance and repair services', 85000.00, CURDATE() - INTERVAL 60 DAY, DATE_ADD(CURDATE(), INTERVAL 10 MONTH), 'active', 1),
('Construction Contract', 'CNT-2024-003', 'BuildRight Construction', 'construction', 'Training facility renovation project', 250000.00, DATE_ADD(CURDATE(), INTERVAL 1 WEEK), DATE_ADD(CURDATE(), INTERVAL 6 MONTH), 'pending', 1);

INSERT INTO finance_purchase_orders (po_number, vendor_name, total_amount, order_date, expected_delivery, status, created_by) VALUES
('PO-2024-001', 'Office Supplies Co.', 5500.00, CURDATE() - INTERVAL 7 DAY, CURDATE() + INTERVAL 3 DAY, 'ordered', 1),
('PO-2024-002', 'Safety Equipment Ltd.', 12000.00, CURDATE() - INTERVAL 2 DAY, CURDATE() + INTERVAL 10 DAY, 'approved', 1),
('PO-2024-003', 'Communications Gear Inc.', 25000.00, CURDATE(), CURDATE() + INTERVAL 14 DAY, 'pending', 1);

-- Create indexes for performance
CREATE INDEX idx_budgets_created_at ON finance_budgets(created_at);
CREATE INDEX idx_transactions_created_at ON finance_transactions(created_at);
CREATE INDEX idx_contracts_created_at ON finance_contracts(created_at);
CREATE INDEX idx_po_created_at ON finance_purchase_orders(created_at);