<?php
/**
 * ARMIS Training Module Functions
 * Common utility functions for training operations
 */

// Include required files
require_once __DIR__ . '/config.php';
require_once dirname(dirname(__DIR__)) . '/shared/database_connection.php';

/**
 * Training utility functions
 */
class TrainingUtils {
    
    /**
     * Get all available courses
     */
    public static function getCourses($status = 'Active') {
        $sql = "SELECT * FROM courses";
        $params = [];
        
        if ($status) {
            $sql .= " WHERE status = ?";
            $params[] = $status;
        }
        
        $sql .= " ORDER BY course_name ASC";
        return fetchAll($sql, $params) ?: [];
    }
    
    /**
     * Get course by ID
     */
    public static function getCourseById($courseId) {
        $sql = "SELECT * FROM courses WHERE id = ?";
        return fetchOne($sql, [$courseId]);
    }
    
    /**
     * Get training records for a staff member
     */
    public static function getStaffTrainingRecords($staffId) {
        $sql = "SELECT tr.*, c.course_name, c.duration_hours 
                FROM training_records tr
                LEFT JOIN courses c ON tr.course_id = c.id
                WHERE tr.staff_id = ?
                ORDER BY tr.completion_date DESC";
        return fetchAll($sql, [$staffId]) ?: [];
    }
    
    /**
     * Get certifications for a staff member
     */
    public static function getStaffCertifications($staffId) {
        $sql = "SELECT * FROM certifications 
                WHERE staff_id = ? 
                ORDER BY issue_date DESC";
        return fetchAll($sql, [$staffId]) ?: [];
    }
    
    /**
     * Get training statistics
     */
    public static function getTrainingStats() {
        $stats = [];
        
        // Active courses
        $result = fetchOne("SELECT COUNT(*) as total FROM courses WHERE status = 'Active'");
        $stats['active_courses'] = $result ? $result->total : 0;
        
        // Completion rate
        $totalEnrollments = fetchOne("SELECT COUNT(*) as total FROM training_records WHERE status IN ('Enrolled', 'In Progress', 'Completed')")
            ->total ?? 1;
        $completedEnrollments = fetchOne("SELECT COUNT(*) as total FROM training_records WHERE status = 'Completed'")
            ->total ?? 0;
        
        $stats['completion_rate'] = $totalEnrollments > 0 ? round(($completedEnrollments / $totalEnrollments) * 100) : 0;
        
        // Pending certifications
        $result = fetchOne("SELECT COUNT(*) as total FROM certifications WHERE status = 'Pending'");
        $stats['pending_certs'] = $result ? $result->total : 0;
        
        // Instructors
        $result = fetchOne("SELECT COUNT(*) as total FROM instructors WHERE status = 'Active'");
        $stats['instructors'] = $result ? $result->total : 0;
        
        return $stats;
    }
    
    /**
     * Enroll staff in course
     */
    public static function enrollStaffInCourse($staffId, $courseId, $enrolledBy) {
        $sql = "INSERT INTO training_records (staff_id, course_id, status, enrollment_date, enrolled_by) 
                VALUES (?, ?, 'Enrolled', NOW(), ?)";
        
        return executeQuery($sql, [$staffId, $courseId, $enrolledBy]);
    }
    
    /**
     * Update training record status
     */
    public static function updateTrainingStatus($recordId, $status, $completionDate = null) {
        $sql = "UPDATE training_records SET status = ?, updated_at = NOW()";
        $params = [$status];
        
        if ($completionDate && $status === 'Completed') {
            $sql .= ", completion_date = ?";
            $params[] = $completionDate;
        }
        
        $sql .= " WHERE id = ?";
        $params[] = $recordId;
        
        return executeQuery($sql, $params);
    }
    
    /**
     * Create new course
     */
    public static function createCourse($data) {
        $sql = "INSERT INTO courses (course_name, description, duration_hours, difficulty, category, status, created_by) 
                VALUES (?, ?, ?, ?, ?, 'Draft', ?)";
        
        return executeQuery($sql, [
            $data['course_name'],
            $data['description'],
            $data['duration_hours'],
            $data['difficulty'],
            $data['category'],
            $_SESSION['user_id']
        ]);
    }
    
    /**
     * Issue certification
     */
    public static function issueCertification($staffId, $certType, $validUntil) {
        $sql = "INSERT INTO certifications (staff_id, certification_type, issue_date, valid_until, status, issued_by) 
                VALUES (?, ?, NOW(), ?, 'Valid', ?)";
        
        return executeQuery($sql, [$staffId, $certType, $validUntil, $_SESSION['user_id']]);
    }
    
    /**
     * Get upcoming training schedule
     */
    public static function getUpcomingTraining($limit = 10) {
        $sql = "SELECT ts.*, c.course_name, i.instructor_name 
                FROM training_schedule ts
                LEFT JOIN courses c ON ts.course_id = c.id
                LEFT JOIN instructors i ON ts.instructor_id = i.id
                WHERE ts.start_date >= NOW()
                ORDER BY ts.start_date ASC
                LIMIT ?";
        
        return fetchAll($sql, [$limit]) ?: [];
    }
    
    /**
     * Get instructors
     */
    public static function getInstructors($status = 'Active') {
        $sql = "SELECT * FROM instructors";
        $params = [];
        
        if ($status) {
            $sql .= " WHERE status = ?";
            $params[] = $status;
        }
        
        $sql .= " ORDER BY instructor_name ASC";
        return fetchAll($sql, $params) ?: [];
    }
    
    /**
     * Format training duration
     */
    public static function formatDuration($hours) {
        if ($hours < 1) {
            return ($hours * 60) . ' minutes';
        } elseif ($hours < 24) {
            return $hours . ' hours';
        } else {
            $days = floor($hours / 24);
            $remainingHours = $hours % 24;
            return $days . ' days' . ($remainingHours > 0 ? ', ' . $remainingHours . ' hours' : '');
        }
    }
    
    /**
     * Get status badge class
     */
    public static function getStatusBadgeClass($status) {
        $classes = [
            'Draft' => 'bg-secondary',
            'Active' => 'bg-success',
            'Suspended' => 'bg-warning',
            'Completed' => 'bg-primary',
            'Cancelled' => 'bg-danger',
            'Enrolled' => 'bg-info',
            'In Progress' => 'bg-warning',
            'Failed' => 'bg-danger',
            'Withdrawn' => 'bg-secondary',
            'Valid' => 'bg-success',
            'Expired' => 'bg-danger',
            'Pending' => 'bg-warning'
        ];
        
        return $classes[$status] ?? 'bg-secondary';
    }
    
    /**
     * Get difficulty badge class
     */
    public static function getDifficultyBadgeClass($difficulty) {
        $classes = [
            'Beginner' => 'bg-success',
            'Intermediate' => 'bg-info',
            'Advanced' => 'bg-warning',
            'Expert' => 'bg-danger'
        ];
        
        return $classes[$difficulty] ?? 'bg-secondary';
    }
}