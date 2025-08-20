-- Performance Optimization Indexes for Enhanced User Profile Module
-- These indexes will improve query performance for military-specific operations

-- Staff table optimizations
CREATE INDEX idx_staff_service_number ON staff(service_number);
CREATE INDEX idx_staff_rank_unit ON staff(rank_id, unit_id);
CREATE INDEX idx_staff_status_role ON staff(accStatus, role);
CREATE INDEX idx_staff_last_login ON staff(last_login);
CREATE INDEX idx_staff_created_at ON staff(created_at);
CREATE INDEX idx_staff_full_name ON staff(first_name, last_name);

-- Ranks table optimizations
CREATE INDEX idx_ranks_name ON ranks(name);
CREATE INDEX idx_ranks_abbreviation ON ranks(abbreviation);

-- Units table optimizations  
CREATE INDEX idx_units_name ON units(name);
CREATE INDEX idx_units_code ON units(code);

-- Corps table optimizations
CREATE INDEX idx_corps_name ON corps(name);
CREATE INDEX idx_corps_abbreviation ON corps(abbreviation);

-- Enhanced personnel schema indexes (if tables exist)
CREATE INDEX IF NOT EXISTS idx_staff_family_relationship_type ON staff_family_members(staff_id, relationship);
CREATE INDEX IF NOT EXISTS idx_staff_education_level_year ON staff_education(staff_id, level, year_completed);
CREATE INDEX IF NOT EXISTS idx_staff_languages_proficiency ON staff_languages(staff_id, proficiency_level);
CREATE INDEX IF NOT EXISTS idx_staff_addresses_primary ON staff_addresses(staff_id, is_primary);
CREATE INDEX IF NOT EXISTS idx_staff_skills_category_level ON staff_skills(staff_id, skill_category, proficiency_level);
CREATE INDEX IF NOT EXISTS idx_staff_deployments_status_date ON staff_deployments(staff_id, status, start_date);

-- Composite indexes for common queries
CREATE INDEX IF NOT EXISTS idx_staff_search_composite ON staff(accStatus, role, rank_id, unit_id);
CREATE INDEX IF NOT EXISTS idx_staff_profile_completion ON staff(id, first_name, last_name, email, service_number);

-- Full-text indexes for search functionality
CREATE FULLTEXT INDEX IF NOT EXISTS ft_staff_names ON staff(first_name, last_name);
CREATE FULLTEXT INDEX IF NOT EXISTS ft_ranks_search ON ranks(name, abbreviation);
CREATE FULLTEXT INDEX IF NOT EXISTS ft_units_search ON units(name, code);

-- Performance indexes for audit tables
CREATE INDEX IF NOT EXISTS idx_audit_staff_date ON user_profile_audit(staff_id, created_at);
CREATE INDEX IF NOT EXISTS idx_audit_table_action ON user_profile_audit(table_name, action_type);
CREATE INDEX IF NOT EXISTS idx_security_audit_user_date ON security_audit_log(user_id, timestamp);
CREATE INDEX IF NOT EXISTS idx_data_access_user_date ON data_access_log(user_id, created_at);
CREATE INDEX IF NOT EXISTS idx_session_audit_date_range ON user_session_audit(login_time, logout_time);

-- Indexes for military-specific tables
CREATE INDEX IF NOT EXISTS idx_clearance_level_status ON staff_security_clearance(clearance_level, status);
CREATE INDEX IF NOT EXISTS idx_awards_category_date ON staff_awards(award_category, award_date);
CREATE INDEX IF NOT EXISTS idx_medical_category_exam_date ON staff_medical_fitness(fitness_category, examination_date);
CREATE INDEX IF NOT EXISTS idx_training_compliance_status ON staff_training_compliance(compliance_status, expiry_date);
CREATE INDEX IF NOT EXISTS idx_service_record_status ON staff_service_record(current_status, total_service_years);

-- Optimization for CV management
CREATE INDEX IF NOT EXISTS idx_cv_versions_current ON staff_cv_versions(staff_id, is_current_version);
CREATE INDEX IF NOT EXISTS idx_cv_versions_date ON staff_cv_versions(staff_id, created_at);

-- Profile completion optimization
CREATE INDEX IF NOT EXISTS idx_completion_percentage ON profile_completion_tracking(completion_percentage, verification_status);

-- Compliance monitoring optimization
CREATE INDEX IF NOT EXISTS idx_compliance_next_review ON compliance_monitoring(next_review_date, compliance_status);

-- Cleanup old indexes that might be redundant
-- DROP INDEX IF EXISTS old_index_name;