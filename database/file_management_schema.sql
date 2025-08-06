-- ARMIS File Management System Database Schema
-- Add these tables to your existing database

-- Staff Documents Table
CREATE TABLE IF NOT EXISTS `staff_documents` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `staff_id` int(11) NOT NULL,
  `document_type` varchar(50) NOT NULL,
  `original_filename` varchar(255) NOT NULL,
  `secure_filename` varchar(255) NOT NULL,
  `file_path` varchar(500) NOT NULL,
  `file_size` int(11) NOT NULL,
  `file_type` varchar(10) NOT NULL,
  `mime_type` varchar(100) NOT NULL,
  `uploaded_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `uploaded_by` int(11) NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  KEY `idx_staff_id` (`staff_id`),
  KEY `idx_document_type` (`document_type`),
  KEY `idx_uploaded_at` (`uploaded_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- File Access Log Table
CREATE TABLE IF NOT EXISTS `file_access_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `file_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `accessed_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `ip_address` varchar(45) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_file_id` (`file_id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_accessed_at` (`accessed_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Advanced Search History Table
CREATE TABLE IF NOT EXISTS `search_history` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `search_query` text NOT NULL,
  `search_filters` json DEFAULT NULL,
  `results_count` int(11) NOT NULL DEFAULT 0,
  `searched_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_searched_at` (`searched_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Staff Search Index for better performance
ALTER TABLE `staff` ADD FULLTEXT KEY `idx_fulltext_search` (`fname`, `lname`, `email`, `svcNo`);

-- Add indexes for better search performance
ALTER TABLE `staff` ADD INDEX `idx_rank_unit` (`rankID`, `unitID`);
ALTER TABLE `staff` ADD INDEX `idx_status_gender` (`svcStatus`, `gender`);
ALTER TABLE `staff` ADD INDEX `idx_enlistment_date` (`enlistmentDate`);
ALTER TABLE `staff` ADD INDEX `idx_birth_date` (`dateOfBirth`);

-- Document Types Reference Table
CREATE TABLE IF NOT EXISTS `document_types` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `type_code` varchar(20) NOT NULL,
  `type_name` varchar(100) NOT NULL,
  `description` text,
  `is_required` tinyint(1) NOT NULL DEFAULT 0,
  `max_file_size` int(11) NOT NULL DEFAULT 10485760,
  `allowed_extensions` varchar(255) NOT NULL DEFAULT 'pdf,jpg,jpeg,png,doc,docx',
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `type_code` (`type_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insert default document types
INSERT IGNORE INTO `document_types` (`type_code`, `type_name`, `description`, `is_required`, `sort_order`) VALUES
('photo', 'Passport Photo', 'Official passport-style photograph', 1, 1),
('nrc_copy', 'NRC Copy', 'Copy of National Registration Card', 1, 2),
('passport_copy', 'Passport Copy', 'Copy of passport (if available)', 0, 3),
('birth_cert', 'Birth Certificate', 'Official birth certificate', 1, 4),
('education_cert', 'Education Certificate', 'Educational qualifications certificates', 0, 5),
('medical_report', 'Medical Report', 'Medical examination reports', 1, 6),
('next_of_kin', 'Next of Kin Details', 'Next of kin documentation', 0, 7),
('cv_resume', 'CV/Resume', 'Curriculum Vitae or Resume', 0, 8),
('reference_letter', 'Reference Letter', 'Character or employment references', 0, 9),
('other', 'Other Documents', 'Miscellaneous supporting documents', 0, 10);

-- Add foreign key constraints
ALTER TABLE `staff_documents` 
  ADD CONSTRAINT `fk_staff_documents_staff` 
  FOREIGN KEY (`staff_id`) REFERENCES `staff` (`id`) ON DELETE CASCADE;

ALTER TABLE `file_access_log` 
  ADD CONSTRAINT `fk_file_access_log_file` 
  FOREIGN KEY (`file_id`) REFERENCES `staff_documents` (`id`) ON DELETE CASCADE;

-- Performance optimization views
CREATE OR REPLACE VIEW `staff_with_documents` AS
SELECT 
  s.*,
  COUNT(sd.id) as document_count,
  GROUP_CONCAT(DISTINCT sd.document_type) as document_types
FROM staff s
LEFT JOIN staff_documents sd ON s.id = sd.staff_id AND sd.is_active = 1
GROUP BY s.id;

-- Search statistics view
CREATE OR REPLACE VIEW `search_analytics` AS
SELECT 
  DATE(searched_at) as search_date,
  COUNT(*) as total_searches,
  COUNT(DISTINCT user_id) as unique_users,
  AVG(results_count) as avg_results
FROM search_history
GROUP BY DATE(searched_at)
ORDER BY search_date DESC;
