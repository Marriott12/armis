<?php
/**
 * ARMIS Database Enhancement Script
 * Implements immediate database improvements for enhanced functionality
 */

require_once 'config.php';

class DatabaseEnhancer {
    private $pdo;
    private $enhancements = [];
    
    public function __construct() {
        $this->pdo = getDbConnection();
    }
    
    public function runAllEnhancements() {
        echo "<h2>ARMIS Database Enhancement Script</h2>";
        echo "<p>Implementing immediate database improvements...</p>";
        
        try {
            $this->pdo->beginTransaction();
            
            // Core table enhancements
            $this->enhanceStaffTable();
            $this->createTrainingTables();
            $this->createNotificationSystem();
            $this->createEquipmentTables();
            $this->createAnalyticsTables();
            $this->createAuditTables();
            $this->createPerformanceTables();
            $this->addIndexesForPerformance();
            
            $this->pdo->commit();
            
            echo "<div class='alert alert-success mt-3'>";
            echo "<h4>✅ Database Enhancement Complete!</h4>";
            echo "<p>All database improvements have been successfully implemented.</p>";
            echo "<ul>";
            foreach ($this->enhancements as $enhancement) {
                echo "<li>✓ {$enhancement}</li>";
            }
            echo "</ul>";
            echo "</div>";
            
        } catch (Exception $e) {
            $this->pdo->rollBack();
            echo "<div class='alert alert-danger mt-3'>";
            echo "<h4>❌ Enhancement Failed</h4>";
            echo "<p>Error: " . $e->getMessage() . "</p>";
            echo "</div>";
        }
    }
    
    private function enhanceStaffTable() {
        echo "<h4>Enhancing Staff Table...</h4>";
        
        // Add missing columns for enhanced functionality
        $newColumns = [
            'security_clearance' => "VARCHAR(50) DEFAULT NULL COMMENT 'Security clearance level'",
            'clearance_expiry' => "DATE DEFAULT NULL COMMENT 'Security clearance expiry date'",
            'emergency_contact_verified' => "BOOLEAN DEFAULT FALSE COMMENT 'Emergency contact verification status'",
            'last_medical_exam' => "DATE DEFAULT NULL COMMENT 'Last medical examination date'",
            'fitness_category' => "ENUM('A', 'B', 'C', 'D', 'Medical') DEFAULT NULL COMMENT 'Physical fitness category'",
            'languages_spoken' => "JSON DEFAULT NULL COMMENT 'Languages and proficiency levels'",
            'next_promotion_eligible' => "DATE DEFAULT NULL COMMENT 'Next promotion eligibility date'",
            'deployment_availability' => "ENUM('Available', 'Restricted', 'Not Available') DEFAULT 'Available'",
            'skills_tags' => "JSON DEFAULT NULL COMMENT 'Searchable skills and specializations'",
            'performance_rating' => "DECIMAL(3,2) DEFAULT NULL COMMENT 'Latest performance rating (1-5 scale)'",
            'retention_risk_score' => "DECIMAL(3,2) DEFAULT NULL COMMENT 'AI-calculated retention risk (0-1 scale)'",
            'last_training_date' => "DATE DEFAULT NULL COMMENT 'Last completed training date'",
            'photo_url' => "VARCHAR(500) DEFAULT NULL COMMENT 'Profile photo URL'",
            'photo_thumbnails' => "JSON DEFAULT NULL COMMENT 'Generated thumbnail URLs'"
        ];
        
        foreach ($newColumns as $column => $definition) {
            try {
                $this->pdo->exec("ALTER TABLE staff ADD COLUMN {$column} {$definition}");
                echo "<p class='text-success'>✓ Added column: {$column}</p>";
            } catch (PDOException $e) {
                if (strpos($e->getMessage(), 'Duplicate column name') === false) {
                    echo "<p class='text-warning'>⚠ Column {$column}: " . $e->getMessage() . "</p>";
                } else {
                    echo "<p class='text-info'>- Column {$column} already exists</p>";
                }
            }
        }
        
        $this->enhancements[] = "Enhanced staff table with additional fields";
    }
    
    private function createTrainingTables() {
        echo "<h4>Creating Training Management Tables...</h4>";
        
        // Training Programs
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS training_programs (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(255) NOT NULL,
                description TEXT,
                category VARCHAR(100) NOT NULL,
                duration_hours INT DEFAULT 0,
                cost_per_person DECIMAL(10,2) DEFAULT 0.00,
                prerequisites JSON DEFAULT NULL,
                learning_objectives JSON DEFAULT NULL,
                certification_provided BOOLEAN DEFAULT FALSE,
                is_mandatory BOOLEAN DEFAULT FALSE,
                max_participants INT DEFAULT 20,
                instructor_requirements TEXT,
                materials_required TEXT,
                location_requirements VARCHAR(255),
                is_active BOOLEAN DEFAULT TRUE,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_category (category),
                INDEX idx_mandatory (is_mandatory),
                INDEX idx_active (is_active)
            ) ENGINE=InnoDB COMMENT='Training programs and courses'
        ");
        
        // Training Enrollments
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS training_enrollments (
                id INT AUTO_INCREMENT PRIMARY KEY,
                staff_id INT NOT NULL,
                training_program_id INT NOT NULL,
                enrollment_date DATE NOT NULL,
                start_date DATE,
                completion_date DATE NULL,
                status ENUM('enrolled', 'in_progress', 'completed', 'failed', 'withdrawn', 'no_show') DEFAULT 'enrolled',
                score DECIMAL(5,2) NULL,
                instructor_id INT NULL,
                location VARCHAR(255),
                notes TEXT,
                certificate_issued BOOLEAN DEFAULT FALSE,
                certificate_number VARCHAR(100) NULL,
                expiry_date DATE NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (staff_id) REFERENCES staff(id) ON DELETE CASCADE,
                FOREIGN KEY (training_program_id) REFERENCES training_programs(id) ON DELETE CASCADE,
                INDEX idx_staff_status (staff_id, status),
                INDEX idx_completion_date (completion_date),
                INDEX idx_enrollment_date (enrollment_date)
            ) ENGINE=InnoDB COMMENT='Individual training enrollments and progress'
        ");
        
        // Training Schedules
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS training_schedules (
                id INT AUTO_INCREMENT PRIMARY KEY,
                training_program_id INT NOT NULL,
                instructor_id INT NOT NULL,
                start_datetime DATETIME NOT NULL,
                end_datetime DATETIME NOT NULL,
                location VARCHAR(255) NOT NULL,
                max_participants INT DEFAULT 20,
                current_participants INT DEFAULT 0,
                status ENUM('scheduled', 'in_progress', 'completed', 'cancelled', 'postponed') DEFAULT 'scheduled',
                equipment_needed TEXT,
                special_requirements TEXT,
                cost_total DECIMAL(10,2) DEFAULT 0.00,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (training_program_id) REFERENCES training_programs(id) ON DELETE CASCADE,
                INDEX idx_schedule_date (start_datetime),
                INDEX idx_location (location),
                INDEX idx_status (status)
            ) ENGINE=InnoDB COMMENT='Training session schedules'
        ");
        
        echo "<p class='text-success'>✓ Training management tables created</p>";
        $this->enhancements[] = "Comprehensive training management system";
    }
    
    private function createNotificationSystem() {
        echo "<h4>Creating Notification System...</h4>";
        
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS notifications (
                id INT AUTO_INCREMENT PRIMARY KEY,
                recipient_id INT NOT NULL,
                sender_id INT NULL,
                title VARCHAR(255) NOT NULL,
                message TEXT NOT NULL,
                type ENUM('info', 'warning', 'success', 'danger', 'urgent') DEFAULT 'info',
                priority ENUM('low', 'normal', 'high', 'urgent') DEFAULT 'normal',
                category VARCHAR(100) DEFAULT 'general',
                action_required BOOLEAN DEFAULT FALSE,
                action_url VARCHAR(500) NULL,
                channels JSON DEFAULT NULL COMMENT 'Delivery channels: email, sms, push, in_app',
                delivery_status JSON DEFAULT NULL COMMENT 'Delivery status per channel',
                read_at TIMESTAMP NULL,
                expires_at TIMESTAMP NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (recipient_id) REFERENCES staff(id) ON DELETE CASCADE,
                INDEX idx_recipient_read (recipient_id, read_at),
                INDEX idx_type_priority (type, priority),
                INDEX idx_created_at (created_at),
                INDEX idx_expires_at (expires_at)
            ) ENGINE=InnoDB COMMENT='System notifications and messages'
        ");
        
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS notification_preferences (
                id INT AUTO_INCREMENT PRIMARY KEY,
                staff_id INT NOT NULL,
                category VARCHAR(100) NOT NULL,
                email_enabled BOOLEAN DEFAULT TRUE,
                sms_enabled BOOLEAN DEFAULT FALSE,
                push_enabled BOOLEAN DEFAULT TRUE,
                in_app_enabled BOOLEAN DEFAULT TRUE,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (staff_id) REFERENCES staff(id) ON DELETE CASCADE,
                UNIQUE KEY unique_staff_category (staff_id, category)
            ) ENGINE=InnoDB COMMENT='User notification preferences'
        ");
        
        echo "<p class='text-success'>✓ Notification system tables created</p>";
        $this->enhancements[] = "Advanced notification and messaging system";
    }
    
    private function createEquipmentTables() {
        echo "<h4>Creating Equipment Management Tables...</h4>";
        
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS equipment_categories (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(255) NOT NULL,
                description TEXT,
                requires_sizing BOOLEAN DEFAULT FALSE,
                size_type ENUM('clothing', 'footwear', 'headwear', 'gear', 'none') DEFAULT 'none',
                maintenance_required BOOLEAN DEFAULT FALSE,
                is_active BOOLEAN DEFAULT TRUE,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB COMMENT='Equipment categories and types'
        ");
        
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS equipment_inventory (
                id INT AUTO_INCREMENT PRIMARY KEY,
                category_id INT NOT NULL,
                item_name VARCHAR(255) NOT NULL,
                item_code VARCHAR(100) NOT NULL UNIQUE,
                size_value VARCHAR(20) NULL,
                color VARCHAR(50) NULL,
                condition_status ENUM('new', 'good', 'fair', 'poor', 'damaged') DEFAULT 'new',
                location VARCHAR(255) NOT NULL,
                assigned_to INT NULL,
                assigned_date DATE NULL,
                return_due_date DATE NULL,
                purchase_date DATE NULL,
                purchase_cost DECIMAL(10,2) NULL,
                warranty_expiry DATE NULL,
                last_maintenance DATE NULL,
                next_maintenance_due DATE NULL,
                notes TEXT,
                is_active BOOLEAN DEFAULT TRUE,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (category_id) REFERENCES equipment_categories(id),
                FOREIGN KEY (assigned_to) REFERENCES staff(id) ON DELETE SET NULL,
                INDEX idx_item_code (item_code),
                INDEX idx_assigned_to (assigned_to),
                INDEX idx_condition (condition_status),
                INDEX idx_location (location)
            ) ENGINE=InnoDB COMMENT='Equipment inventory and tracking'
        ");
        
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS equipment_assignments (
                id INT AUTO_INCREMENT PRIMARY KEY,
                equipment_id INT NOT NULL,
                staff_id INT NOT NULL,
                assigned_by INT NOT NULL,
                assigned_date DATE NOT NULL,
                return_date DATE NULL,
                expected_return_date DATE NULL,
                condition_out ENUM('new', 'good', 'fair', 'poor') NOT NULL,
                condition_in ENUM('new', 'good', 'fair', 'poor', 'damaged', 'lost') NULL,
                purpose VARCHAR(255),
                notes TEXT,
                status ENUM('active', 'returned', 'overdue', 'lost', 'damaged') DEFAULT 'active',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (equipment_id) REFERENCES equipment_inventory(id),
                FOREIGN KEY (staff_id) REFERENCES staff(id),
                FOREIGN KEY (assigned_by) REFERENCES staff(id),
                INDEX idx_staff_status (staff_id, status),
                INDEX idx_assigned_date (assigned_date),
                INDEX idx_expected_return (expected_return_date)
            ) ENGINE=InnoDB COMMENT='Equipment assignment history'
        ");
        
        echo "<p class='text-success'>✓ Equipment management tables created</p>";
        $this->enhancements[] = "Complete equipment tracking and assignment system";
    }
    
    private function createAnalyticsTables() {
        echo "<h4>Creating Analytics and Metrics Tables...</h4>";
        
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS personnel_metrics (
                id INT AUTO_INCREMENT PRIMARY KEY,
                staff_id INT NOT NULL,
                metric_date DATE NOT NULL,
                performance_score DECIMAL(3,2) NULL,
                training_hours_ytd INT DEFAULT 0,
                training_completion_rate DECIMAL(5,2) DEFAULT 0.00,
                deployment_days_ytd INT DEFAULT 0,
                leave_days_used INT DEFAULT 0,
                equipment_items_assigned INT DEFAULT 0,
                readiness_status ENUM('ready', 'training', 'medical', 'leave', 'deployed') DEFAULT 'ready',
                fitness_score DECIMAL(3,2) NULL,
                last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (staff_id) REFERENCES staff(id) ON DELETE CASCADE,
                UNIQUE KEY unique_staff_date (staff_id, metric_date),
                INDEX idx_metric_date (metric_date),
                INDEX idx_readiness (readiness_status)
            ) ENGINE=InnoDB COMMENT='Daily personnel metrics and KPIs'
        ");
        
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS unit_metrics (
                id INT AUTO_INCREMENT PRIMARY KEY,
                unit_id INT NOT NULL,
                metric_date DATE NOT NULL,
                total_personnel INT NOT NULL,
                ready_personnel INT NOT NULL,
                training_personnel INT NOT NULL,
                deployed_personnel INT NOT NULL,
                on_leave_personnel INT NOT NULL,
                readiness_percentage DECIMAL(5,2) NOT NULL,
                average_performance DECIMAL(3,2) NULL,
                training_completion_rate DECIMAL(5,2) NULL,
                equipment_readiness DECIMAL(5,2) NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (unit_id) REFERENCES units(id),
                UNIQUE KEY unique_unit_date (unit_id, metric_date),
                INDEX idx_metric_date (metric_date),
                INDEX idx_readiness_percentage (readiness_percentage)
            ) ENGINE=InnoDB COMMENT='Daily unit readiness metrics'
        ");
        
        echo "<p class='text-success'>✓ Analytics and metrics tables created</p>";
        $this->enhancements[] = "Comprehensive analytics and reporting system";
    }
    
    private function createAuditTables() {
        echo "<h4>Creating Audit and Logging Tables...</h4>";
        
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS system_audit_log (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NULL,
                action VARCHAR(100) NOT NULL,
                table_name VARCHAR(100) NOT NULL,
                record_id INT NOT NULL,
                old_values JSON NULL,
                new_values JSON NULL,
                ip_address VARCHAR(45) NOT NULL,
                user_agent TEXT NULL,
                session_id VARCHAR(128) NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES staff(id) ON DELETE SET NULL,
                INDEX idx_user_action (user_id, action),
                INDEX idx_table_record (table_name, record_id),
                INDEX idx_created_at (created_at)
            ) ENGINE=InnoDB COMMENT='Comprehensive audit trail'
        ");
        
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS login_attempts (
                id INT AUTO_INCREMENT PRIMARY KEY,
                username VARCHAR(255) NOT NULL,
                ip_address VARCHAR(45) NOT NULL,
                user_agent TEXT NULL,
                success BOOLEAN NOT NULL,
                failure_reason VARCHAR(255) NULL,
                session_id VARCHAR(128) NULL,
                attempted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_username_ip (username, ip_address),
                INDEX idx_attempted_at (attempted_at),
                INDEX idx_success (success)
            ) ENGINE=InnoDB COMMENT='Login attempt tracking'
        ");
        
        echo "<p class='text-success'>✓ Audit and logging tables created</p>";
        $this->enhancements[] = "Complete audit trail and security logging";
    }
    
    private function createPerformanceTables() {
        echo "<h4>Creating Performance Management Tables...</h4>";
        
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS performance_evaluations (
                id INT AUTO_INCREMENT PRIMARY KEY,
                staff_id INT NOT NULL,
                evaluator_id INT NOT NULL,
                evaluation_period_start DATE NOT NULL,
                evaluation_period_end DATE NOT NULL,
                overall_rating DECIMAL(3,2) NOT NULL,
                leadership_rating DECIMAL(3,2) NULL,
                teamwork_rating DECIMAL(3,2) NULL,
                technical_skills_rating DECIMAL(3,2) NULL,
                communication_rating DECIMAL(3,2) NULL,
                goals_achievement DECIMAL(3,2) NULL,
                strengths TEXT,
                areas_for_improvement TEXT,
                development_goals TEXT,
                comments TEXT,
                status ENUM('draft', 'submitted', 'reviewed', 'approved', 'final') DEFAULT 'draft',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (staff_id) REFERENCES staff(id) ON DELETE CASCADE,
                FOREIGN KEY (evaluator_id) REFERENCES staff(id),
                INDEX idx_staff_period (staff_id, evaluation_period_end),
                INDEX idx_overall_rating (overall_rating),
                INDEX idx_status (status)
            ) ENGINE=InnoDB COMMENT='Performance evaluation records'
        ");
        
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS goal_tracking (
                id INT AUTO_INCREMENT PRIMARY KEY,
                staff_id INT NOT NULL,
                evaluator_id INT NOT NULL,
                goal_title VARCHAR(255) NOT NULL,
                goal_description TEXT,
                target_date DATE NOT NULL,
                priority ENUM('low', 'medium', 'high', 'critical') DEFAULT 'medium',
                category VARCHAR(100) NOT NULL,
                progress_percentage DECIMAL(5,2) DEFAULT 0.00,
                status ENUM('not_started', 'in_progress', 'completed', 'overdue', 'cancelled') DEFAULT 'not_started',
                completion_date DATE NULL,
                notes TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (staff_id) REFERENCES staff(id) ON DELETE CASCADE,
                FOREIGN KEY (evaluator_id) REFERENCES staff(id),
                INDEX idx_staff_status (staff_id, status),
                INDEX idx_target_date (target_date),
                INDEX idx_priority (priority)
            ) ENGINE=InnoDB COMMENT='Individual goal tracking'
        ");
        
        echo "<p class='text-success'>✓ Performance management tables created</p>";
        $this->enhancements[] = "Goal tracking and performance evaluation system";
    }
    
    private function addIndexesForPerformance() {
        echo "<h4>Adding Performance Indexes...</h4>";
        
        $indexes = [
            "CREATE INDEX idx_staff_status ON staff(svcStatus)" => "Staff status index",
            "CREATE INDEX idx_staff_unit_rank ON staff(unit_id, rank_id)" => "Staff unit-rank composite index",
            "CREATE INDEX idx_staff_updated ON staff(updated_at)" => "Staff last updated index",
            "CREATE INDEX idx_staff_names ON staff(last_name, first_name)" => "Staff names index",
            "CREATE INDEX idx_contact_staff ON staff_contact_info(staff_id, contact_type)" => "Contact info index",
            "CREATE INDEX idx_education_staff ON staff_education(staff_id, is_highest_qualification)" => "Education index"
        ];
        
        foreach ($indexes as $sql => $description) {
            try {
                $this->pdo->exec($sql);
                echo "<p class='text-success'>✓ Added: {$description}</p>";
            } catch (PDOException $e) {
                if (strpos($e->getMessage(), 'Duplicate key name') === false) {
                    echo "<p class='text-warning'>⚠ {$description}: " . $e->getMessage() . "</p>";
                } else {
                    echo "<p class='text-info'>- Index already exists: {$description}</p>";
                }
            }
        }
        
        $this->enhancements[] = "Performance optimization indexes";
    }
}

// Auto-run if accessed directly
if (basename($_SERVER['PHP_SELF']) === 'database_enhancements.php') {
    echo "<!DOCTYPE html>
    <html>
    <head>
        <title>ARMIS Database Enhancements</title>
        <link href='https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css' rel='stylesheet'>
    </head>
    <body>
    <div class='container mt-4'>";
    
    $enhancer = new DatabaseEnhancer();
    $enhancer->runAllEnhancements();
    
    echo "
        <div class='mt-4'>
            <a href='/Armis2/admin_branch/' class='btn btn-primary'>Return to Admin Dashboard</a>
            <a href='/Armis2/users/' class='btn btn-secondary'>Go to User Portal</a>
        </div>
    </div>
    </body>
    </html>";
}
?>
