<?php
/**
 * Training Dashboard Service
 * Provides dynamic data for training dashboard and functionality
 */

if (!defined('ARMIS_TRAINING')) {
    die('Direct access not permitted');
}

class TrainingService {
    private $db;
    
    public function __construct($database_connection) {
        $this->db = $database_connection;
    }
    
    /**
     * Get training KPI data
     */
    public function getKPIData() {
        try {
            $kpis = [];
            
            // Active Courses
            $stmt = $this->db->prepare("SELECT COUNT(*) as total FROM training_courses WHERE status = 'active'");
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $kpis['active_courses'] = (int)($result['total'] ?? 0);
            
            // Total Enrolled Personnel
            $stmt = $this->db->prepare("
                SELECT COUNT(DISTINCT te.personnel_id) as total 
                FROM training_enrollments te 
                JOIN training_courses tc ON te.course_id = tc.id 
                WHERE te.status IN ('enrolled', 'in-progress') AND tc.status = 'active'
            ");
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $kpis['enrolled_personnel'] = (int)($result['total'] ?? 0);
            
            // Completion Rate (last 30 days)
            $stmt = $this->db->prepare("
                SELECT 
                    COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed,
                    COUNT(*) as total
                FROM training_enrollments 
                WHERE updated_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
            ");
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $total = (int)$result['total'];
            $completed = (int)$result['completed'];
            $kpis['completion_rate'] = $total > 0 ? round(($completed / $total) * 100, 1) : 0;
            
            // Upcoming Training Events
            $stmt = $this->db->prepare("
                SELECT COUNT(*) as total 
                FROM training_sessions 
                WHERE start_date >= CURDATE() AND start_date <= DATE_ADD(CURDATE(), INTERVAL 7 DAY)
                AND status = 'scheduled'
            ");
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $kpis['upcoming_sessions'] = (int)($result['total'] ?? 0);
            
            // Pending Certifications
            $stmt = $this->db->prepare("
                SELECT COUNT(*) as total 
                FROM training_certifications 
                WHERE status = 'pending' OR expiry_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)
            ");
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $kpis['pending_certifications'] = (int)($result['total'] ?? 0);
            
            error_log("Training KPI data retrieved: " . json_encode($kpis));
            return $kpis;
            
        } catch (Exception $e) {
            error_log("Training KPI error: " . $e->getMessage());
            return [
                'active_courses' => 0,
                'enrolled_personnel' => 0,
                'completion_rate' => 0,
                'upcoming_sessions' => 0,
                'pending_certifications' => 0
            ];
        }
    }
    
    /**
     * Get recent training activities
     */
    public function getRecentActivities($limit = 10) {
        try {
            $stmt = $this->db->prepare("
                SELECT 
                    'enrollment' as type,
                    tc.course_name as title,
                    CONCAT('New enrollment by ', s.fname, ' ', s.lname) as description,
                    te.status,
                    te.enrolled_at as timestamp
                FROM training_enrollments te
                JOIN training_courses tc ON te.course_id = tc.id
                JOIN staff s ON te.personnel_id = s.id
                WHERE te.enrolled_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                
                UNION ALL
                
                SELECT 
                    'completion' as type,
                    tc.course_name as title,
                    CONCAT('Course completed by ', s.fname, ' ', s.lname) as description,
                    'completed' as status,
                    te.completion_date as timestamp
                FROM training_enrollments te
                JOIN training_courses tc ON te.course_id = tc.id
                JOIN staff s ON te.personnel_id = s.id
                WHERE te.status = 'completed' AND te.completion_date >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                
                UNION ALL
                
                SELECT 
                    'session' as type,
                    ts.session_name as title,
                    CONCAT('Training session scheduled') as description,
                    ts.status,
                    ts.created_at as timestamp
                FROM training_sessions ts
                WHERE ts.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                
                ORDER BY timestamp DESC 
                LIMIT ?
            ");
            $stmt->execute([$limit]);
            $activities = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            return array_map(function($activity) {
                return [
                    'id' => $activity['type'] . '_' . time(),
                    'title' => $activity['title'],
                    'description' => $activity['description'],
                    'status' => $activity['status'],
                    'timestamp' => $activity['timestamp'],
                    'type' => $activity['type']
                ];
            }, $activities);
            
        } catch (Exception $e) {
            error_log("Training activities error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get course statistics
     */
    public function getCourseStats() {
        try {
            $stats = [];
            
            // Course status distribution
            $stmt = $this->db->prepare("
                SELECT status, COUNT(*) as count 
                FROM training_courses 
                GROUP BY status
            ");
            $stmt->execute();
            $statusResults = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $stats['status_distribution'] = [];
            foreach ($statusResults as $status) {
                $stats['status_distribution'][$status['status']] = (int)$status['count'];
            }
            
            // Course type distribution
            $stmt = $this->db->prepare("
                SELECT course_type, COUNT(*) as count 
                FROM training_courses 
                WHERE status = 'active'
                GROUP BY course_type
            ");
            $stmt->execute();
            $typeResults = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $stats['type_distribution'] = [];
            foreach ($typeResults as $type) {
                $stats['type_distribution'][$type['course_type']] = (int)$type['count'];
            }
            
            return $stats;
            
        } catch (Exception $e) {
            error_log("Training course stats error: " . $e->getMessage());
            return ['status_distribution' => [], 'type_distribution' => []];
        }
    }
    
    /**
     * Get enrollment overview
     */
    public function getEnrollmentOverview() {
        try {
            $stmt = $this->db->prepare("
                SELECT 
                    tc.course_name,
                    tc.course_type,
                    tc.max_participants,
                    COUNT(te.id) as enrolled_count,
                    tc.start_date,
                    tc.end_date,
                    tc.status
                FROM training_courses tc
                LEFT JOIN training_enrollments te ON tc.id = te.course_id 
                    AND te.status IN ('enrolled', 'in-progress')
                WHERE tc.status = 'active'
                GROUP BY tc.id
                ORDER BY tc.start_date ASC
                LIMIT 10
            ");
            $stmt->execute();
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (Exception $e) {
            error_log("Training enrollment overview error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get training progress metrics
     */
    public function getProgressMetrics() {
        try {
            $metrics = [];
            
            // Overall progress by course type
            $stmt = $this->db->prepare("
                SELECT 
                    tc.course_type,
                    COUNT(te.id) as total_enrollments,
                    SUM(CASE WHEN te.status = 'completed' THEN 1 ELSE 0 END) as completed,
                    SUM(CASE WHEN te.status = 'in-progress' THEN 1 ELSE 0 END) as in_progress
                FROM training_enrollments te
                JOIN training_courses tc ON te.course_id = tc.id
                GROUP BY tc.course_type
            ");
            $stmt->execute();
            $progressResults = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $metrics['by_type'] = [];
            foreach ($progressResults as $progress) {
                $total = (int)$progress['total_enrollments'];
                $completed = (int)$progress['completed'];
                $metrics['by_type'][$progress['course_type']] = [
                    'total' => $total,
                    'completed' => $completed,
                    'completion_rate' => $total > 0 ? round(($completed / $total) * 100, 1) : 0
                ];
            }
            
            // Monthly enrollment trends
            $stmt = $this->db->prepare("
                SELECT 
                    DATE_FORMAT(enrolled_at, '%Y-%m') as month,
                    COUNT(*) as enrollments
                FROM training_enrollments 
                WHERE enrolled_at >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
                GROUP BY DATE_FORMAT(enrolled_at, '%Y-%m')
                ORDER BY month
            ");
            $stmt->execute();
            $trendResults = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $metrics['enrollment_trends'] = [];
            foreach ($trendResults as $trend) {
                $metrics['enrollment_trends'][$trend['month']] = (int)$trend['enrollments'];
            }
            
            return $metrics;
            
        } catch (Exception $e) {
            error_log("Training progress metrics error: " . $e->getMessage());
            return ['by_type' => [], 'enrollment_trends' => []];
        }
    }
    
    /**
     * Get upcoming training sessions
     */
    public function getUpcomingSessions() {
        try {
            $stmt = $this->db->prepare("
                SELECT 
                    ts.*,
                    tc.course_name,
                    COUNT(tsp.personnel_id) as registered_count
                FROM training_sessions ts
                JOIN training_courses tc ON ts.course_id = tc.id
                LEFT JOIN training_session_participants tsp ON ts.id = tsp.session_id
                WHERE ts.start_date >= CURDATE() 
                AND ts.status = 'scheduled'
                GROUP BY ts.id
                ORDER BY ts.start_date ASC
                LIMIT 10
            ");
            $stmt->execute();
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (Exception $e) {
            error_log("Training upcoming sessions error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get certification status
     */
    public function getCertificationStatus() {
        try {
            $stmt = $this->db->prepare("
                SELECT 
                    certification_name,
                    status,
                    COUNT(*) as count,
                    SUM(CASE WHEN expiry_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY) THEN 1 ELSE 0 END) as expiring_soon
                FROM training_certifications
                GROUP BY certification_name, status
                ORDER BY certification_name, status
            ");
            $stmt->execute();
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (Exception $e) {
            error_log("Training certification status error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get training instructors overview
     */
    public function getInstructorOverview() {
        try {
            $stmt = $this->db->prepare("
                SELECT 
                    ti.*,
                    s.fname,
                    s.lname,
                    s.rank,
                    COUNT(ts.id) as scheduled_sessions
                FROM training_instructors ti
                JOIN staff s ON ti.personnel_id = s.id
                LEFT JOIN training_sessions ts ON ti.id = ts.instructor_id 
                    AND ts.start_date >= CURDATE() 
                    AND ts.status = 'scheduled'
                WHERE ti.status = 'active'
                GROUP BY ti.id
                ORDER BY scheduled_sessions DESC
            ");
            $stmt->execute();
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (Exception $e) {
            error_log("Training instructor overview error: " . $e->getMessage());
            return [];
        }
    }
}
?>