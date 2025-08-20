<?php
/**
 * ARMIS Training Service
 * Business logic layer for training operations
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';
require_once dirname(dirname(__DIR__)) . '/shared/database_connection.php';

class TrainingService {
    private $pdo;
    
    public function __construct($pdo = null) {
        $this->pdo = $pdo ?: getDbConnection();
    }
    
    /**
     * Get dashboard data
     */
    public function getDashboardData() {
        return [
            'stats' => TrainingUtils::getTrainingStats(),
            'upcoming_training' => TrainingUtils::getUpcomingTraining(5),
            'active_courses' => TrainingUtils::getCourses('Active'),
            'recent_completions' => $this->getRecentCompletions(10)
        ];
    }
    
    /**
     * Create course with validation
     */
    public function createCourse($data) {
        // Validate required fields
        $required = ['course_name', 'description', 'duration_hours', 'difficulty', 'category'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                throw new Exception("Field {$field} is required");
            }
        }
        
        // Validate duration
        if ($data['duration_hours'] <= 0 || $data['duration_hours'] > 720) {
            throw new Exception("Duration must be between 1 and 720 hours");
        }
        
        // Create course
        $courseId = TrainingUtils::createCourse($data);
        
        if ($courseId) {
            logTrainingActivity('course_created', "Course '{$data['course_name']}' created", [
                'course_id' => $courseId
            ]);
        }
        
        return $courseId;
    }
    
    /**
     * Enroll staff in course with validation
     */
    public function enrollStaff($staffId, $courseId) {
        // Check if already enrolled
        $existing = fetchOne("SELECT id FROM training_records WHERE staff_id = ? AND course_id = ? AND status IN ('Enrolled', 'In Progress')", 
                            [$staffId, $courseId]);
        
        if ($existing) {
            throw new Exception("Staff member is already enrolled in this course");
        }
        
        // Check course capacity
        $course = TrainingUtils::getCourseById($courseId);
        if (!$course || $course->status !== 'Active') {
            throw new Exception("Course is not available for enrollment");
        }
        
        // Enroll staff
        $result = TrainingUtils::enrollStaffInCourse($staffId, $courseId, $_SESSION['user_id']);
        
        if ($result) {
            logTrainingActivity('staff_enrolled', "Staff enrolled in course", [
                'staff_id' => $staffId,
                'course_id' => $courseId
            ]);
        }
        
        return $result;
    }
    
    /**
     * Complete training record
     */
    public function completeTraining($recordId, $score = null) {
        $record = fetchOne("SELECT * FROM training_records WHERE id = ?", [$recordId]);
        if (!$record) {
            throw new Exception("Training record not found");
        }
        
        if ($record->status === 'Completed') {
            throw new Exception("Training is already completed");
        }
        
        $result = TrainingUtils::updateTrainingStatus($recordId, 'Completed', date('Y-m-d H:i:s'));
        
        if ($result && $score !== null) {
            executeQuery("UPDATE training_records SET score = ? WHERE id = ?", [$score, $recordId]);
        }
        
        if ($result) {
            logTrainingActivity('training_completed', "Training completed", [
                'record_id' => $recordId,
                'score' => $score
            ]);
            
            // Check if certification should be issued
            $this->checkForCertification($record->staff_id, $record->course_id);
        }
        
        return $result;
    }
    
    /**
     * Issue certification
     */
    public function issueCertification($staffId, $certType, $validityMonths = 24) {
        $validUntil = date('Y-m-d', strtotime("+{$validityMonths} months"));
        
        $certId = TrainingUtils::issueCertification($staffId, $certType, $validUntil);
        
        if ($certId) {
            logTrainingActivity('certification_issued', "Certification issued", [
                'staff_id' => $staffId,
                'certification_type' => $certType,
                'valid_until' => $validUntil
            ]);
        }
        
        return $certId;
    }
    
    /**
     * Search courses
     */
    public function searchCourses($params = []) {
        $sql = "SELECT * FROM courses WHERE 1=1";
        $bindings = [];
        
        if (!empty($params['status'])) {
            $sql .= " AND status = ?";
            $bindings[] = $params['status'];
        }
        
        if (!empty($params['category'])) {
            $sql .= " AND category = ?";
            $bindings[] = $params['category'];
        }
        
        if (!empty($params['difficulty'])) {
            $sql .= " AND difficulty = ?";
            $bindings[] = $params['difficulty'];
        }
        
        if (!empty($params['search'])) {
            $sql .= " AND (course_name LIKE ? OR description LIKE ?)";
            $searchTerm = '%' . $params['search'] . '%';
            $bindings[] = $searchTerm;
            $bindings[] = $searchTerm;
        }
        
        $sql .= " ORDER BY course_name ASC";
        
        if (!empty($params['limit'])) {
            $sql .= " LIMIT " . intval($params['limit']);
        }
        
        return fetchAll($sql, $bindings) ?: [];
    }
    
    /**
     * Generate training report
     */
    public function generateTrainingReport($type, $params = []) {
        switch ($type) {
            case 'course_summary':
                return $this->generateCourseSummaryReport($params);
            case 'completion_rates':
                return $this->generateCompletionRatesReport($params);
            case 'certification_status':
                return $this->generateCertificationStatusReport($params);
            case 'instructor_workload':
                return $this->generateInstructorWorkloadReport($params);
            default:
                throw new Exception("Unknown report type: {$type}");
        }
    }
    
    private function generateCourseSummaryReport($params) {
        $sql = "SELECT 
                    COUNT(*) as total_courses,
                    SUM(CASE WHEN status = 'Active' THEN 1 ELSE 0 END) as active_courses,
                    SUM(CASE WHEN status = 'Draft' THEN 1 ELSE 0 END) as draft_courses,
                    AVG(duration_hours) as avg_duration
                FROM courses";
        
        return fetchOne($sql);
    }
    
    private function generateCompletionRatesReport($params) {
        $sql = "SELECT 
                    c.course_name,
                    COUNT(tr.id) as total_enrollments,
                    SUM(CASE WHEN tr.status = 'Completed' THEN 1 ELSE 0 END) as completions,
                    ROUND((SUM(CASE WHEN tr.status = 'Completed' THEN 1 ELSE 0 END) / COUNT(tr.id)) * 100, 2) as completion_rate
                FROM courses c
                LEFT JOIN training_records tr ON c.id = tr.course_id
                GROUP BY c.id, c.course_name
                HAVING total_enrollments > 0
                ORDER BY completion_rate DESC";
        
        return fetchAll($sql) ?: [];
    }
    
    private function generateCertificationStatusReport($params) {
        $sql = "SELECT 
                    certification_type,
                    COUNT(*) as total_certs,
                    SUM(CASE WHEN status = 'Valid' THEN 1 ELSE 0 END) as valid_certs,
                    SUM(CASE WHEN status = 'Expired' THEN 1 ELSE 0 END) as expired_certs,
                    SUM(CASE WHEN valid_until < NOW() THEN 1 ELSE 0 END) as expiring_soon
                FROM certifications
                GROUP BY certification_type
                ORDER BY certification_type";
        
        return fetchAll($sql) ?: [];
    }
    
    private function generateInstructorWorkloadReport($params) {
        $sql = "SELECT 
                    i.instructor_name,
                    COUNT(ts.id) as scheduled_sessions,
                    SUM(c.duration_hours) as total_hours
                FROM instructors i
                LEFT JOIN training_schedule ts ON i.id = ts.instructor_id
                LEFT JOIN courses c ON ts.course_id = c.id
                WHERE i.status = 'Active'
                GROUP BY i.id, i.instructor_name
                ORDER BY total_hours DESC";
        
        return fetchAll($sql) ?: [];
    }
    
    private function getRecentCompletions($limit) {
        $sql = "SELECT tr.*, c.course_name, s.fname, s.lname, s.svcno 
                FROM training_records tr
                LEFT JOIN courses c ON tr.course_id = c.id
                LEFT JOIN staff s ON tr.staff_id = s.staffID
                WHERE tr.status = 'Completed'
                ORDER BY tr.completion_date DESC
                LIMIT ?";
        
        return fetchAll($sql, [$limit]) ?: [];
    }
    
    private function checkForCertification($staffId, $courseId) {
        // Check if course awards certification
        $course = fetchOne("SELECT * FROM courses WHERE id = ? AND awards_certification = 1", [$courseId]);
        
        if ($course && !empty($course->certification_type)) {
            $this->issueCertification($staffId, $course->certification_type);
        }
    }
}