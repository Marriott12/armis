-- Database setup for file upload and search functionality

CREATE TABLE IF NOT EXISTS document_types (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE,
    allowed_extensions TEXT NOT NULL,
    max_file_size INT DEFAULT 5242880,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS staff_documents (
    id INT AUTO_INCREMENT PRIMARY KEY,
    staff_id INT NOT NULL,
    document_type_id INT NOT NULL,
    original_filename VARCHAR(255) NOT NULL,
    stored_filename VARCHAR(255) NOT NULL,
    file_path VARCHAR(500) NOT NULL,
    file_size INT NOT NULL,
    mime_type VARCHAR(100) NOT NULL,
    upload_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    uploaded_by INT NOT NULL,
    access_level ENUM('public', 'restricted', 'confidential') DEFAULT 'restricted',
    description TEXT,
    is_deleted BOOLEAN DEFAULT FALSE,
    FOREIGN KEY (staff_id) REFERENCES staff(id) ON DELETE CASCADE,
    FOREIGN KEY (document_type_id) REFERENCES document_types(id) ON DELETE RESTRICT,
    FOREIGN KEY (uploaded_by) REFERENCES staff(id) ON DELETE RESTRICT,
    INDEX idx_staff_documents_staff_id (staff_id),
    INDEX idx_staff_documents_type (document_type_id),
    INDEX idx_staff_documents_date (upload_date)
);

CREATE TABLE IF NOT EXISTS file_access_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    document_id INT NOT NULL,
    accessed_by INT NOT NULL,
    access_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    action ENUM('view', 'download', 'delete', 'modify') NOT NULL,
    ip_address VARCHAR(45),
    user_agent TEXT,
    FOREIGN KEY (document_id) REFERENCES staff_documents(id) ON DELETE CASCADE,
    FOREIGN KEY (accessed_by) REFERENCES staff(id) ON DELETE RESTRICT,
    INDEX idx_file_access_log_document (document_id),
    INDEX idx_file_access_log_user (accessed_by),
    INDEX idx_file_access_log_date (access_date)
);

CREATE TABLE IF NOT EXISTS search_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    search_query TEXT NOT NULL,
    search_filters JSON,
    results_count INT DEFAULT 0,
    search_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    execution_time DECIMAL(8,4),
    FOREIGN KEY (user_id) REFERENCES staff(id) ON DELETE CASCADE,
    INDEX idx_search_history_user (user_id),
    INDEX idx_search_history_date (search_date)
);

-- Insert default document types
INSERT IGNORE INTO document_types (name, allowed_extensions, max_file_size, description) VALUES
('Photo/Image', 'jpg,jpeg,png,gif,bmp,webp', 10485760, 'Staff photos and image documents'),
('PDF Document', 'pdf', 20971520, 'PDF documents and forms'),
('Word Document', 'doc,docx', 10485760, 'Microsoft Word documents'),
('Excel Spreadsheet', 'xls,xlsx,csv', 10485760, 'Excel files and spreadsheets'),
('Text Document', 'txt,rtf', 5242880, 'Plain text and rich text documents'),
('Medical Certificate', 'pdf,jpg,jpeg,png', 15728640, 'Medical certificates and health documents'),
('Training Certificate', 'pdf,jpg,jpeg,png', 15728640, 'Training and certification documents'),
('ID Documents', 'pdf,jpg,jpeg,png', 10485760, 'Identity documents and credentials');
