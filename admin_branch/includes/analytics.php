<?php
/**
 * ARMIS Enhanced Analytics Engine
 * Comprehensive database-driven analytics for personnel management system
 * Integrates with enhanced database schema for real-time statistics
 */

require_once dirname(dirname(__DIR__)) . '/shared/database_connection.php';

class EnhancedAnalytics {
    
    /**
     * Get database connection
     * @return mysqli
     */
    private static function getConnection() {
        try {
            return getDbConnection();
        } catch (Exception $e) {
            error_log("Analytics database connection error: " . $e->getMessage());
            throw new Exception("Database connection failed");
        }
    }
    
    /**
     * Get comprehensive dashboard statistics
     * @return array
     */
    public static function getDashboardStats() {
        try {
            $conn = self::getConnection();
            
            // Get basic personnel counts
            $result = $conn->query("
                SELECT 
                    COUNT(*) as total_personnel,
                    SUM(CASE WHEN svcStatus = 'Active' THEN 1 ELSE 0 END) as active_personnel,
                    SUM(CASE WHEN svcStatus = 'Deployed' THEN 1 ELSE 0 END) as deployed_personnel,
                    SUM(CASE WHEN svcStatus = 'Leave' THEN 1 ELSE 0 END) as on_leave,
                    SUM(CASE WHEN svcStatus = 'Retired' THEN 1 ELSE 0 END) as retired_personnel
                FROM staff
            ");
            
            $basicStats = $result->fetch_assoc();
            
            // Calculate deployment percentage
            $deploymentPercentage = $basicStats['active_personnel'] > 0 ? 
                round(($basicStats['deployed_personnel'] / $basicStats['active_personnel']) * 100, 1) : 0;
            
            // Get training completion rate
            $trainingResult = $conn->query("
                SELECT 
                    AVG(CASE WHEN completion_status = 'completed' THEN 100 ELSE 0 END) as completion_rate
                FROM staff_training_records
                WHERE training_date >= DATE_SUB(CURDATE(), INTERVAL 1 YEAR)
            ");
            
            $trainingStats = $trainingResult->fetch_assoc();
            $trainingCompletion = round($trainingStats['completion_rate'] ?? 0, 1);
            
            // Get medical due count
            $medicalResult = $conn->query("
                SELECT COUNT(*) as medical_due
                FROM staff_medical_records smr
                JOIN staff s ON smr.staff_id = s.service_number
                WHERE smr.next_medical_exam <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)
                AND smr.next_medical_exam IS NOT NULL
                AND s.svcStatus = 'Active'
            ");
            
            $medicalStats = $medicalResult->fetch_assoc();
            
            // Get average performance score
            $performanceResult = $conn->query("
                SELECT AVG(overall_score) as avg_score
                FROM staff_performance_reviews
                WHERE review_date >= DATE_SUB(CURDATE(), INTERVAL 1 YEAR)
            ");
            
            $performanceStats = $performanceResult->fetch_assoc();
            $avgPerformance = $performanceStats['avg_score'] ? round($performanceStats['avg_score'], 1) : null;
            
            return [
                'total_personnel' => (int)$basicStats['total_personnel'],
                'active_personnel' => (int)$basicStats['active_personnel'],
                'deployed_personnel' => (int)$basicStats['deployed_personnel'],
                'on_leave' => (int)$basicStats['on_leave'],
                'retired_personnel' => (int)$basicStats['retired_personnel'],
                'deployment_percentage' => $deploymentPercentage,
                'training_completion' => $trainingCompletion,
                'medical_due' => (int)$medicalStats['medical_due'],
                'avg_performance_score' => $avgPerformance
            ];
            
        } catch (Exception $e) {
            error_log("Error getting dashboard stats: " . $e->getMessage());
            
            // Return default stats if database fails
            return [
                'total_personnel' => 0,
                'active_personnel' => 0,
                'deployed_personnel' => 0,
                'on_leave' => 0,
                'retired_personnel' => 0,
                'deployment_percentage' => 0,
                'training_completion' => 0,
                'medical_due' => 0,
                'avg_performance_score' => null
            ];
        }
    }
    
    /**
     * Get chart data for personnel visualization
     * @param string $type
     * @return array
     */
    public static function getPersonnelChartData($type = 'rank_distribution') {
        try {
            $conn = self::getConnection();
            
            switch ($type) {
                case 'rank_distribution':
                    $result = $conn->query("
                        SELECT r.name as label, COUNT(*) as value
                        FROM staff s
                        JOIN ranks r ON s.rank_id = r.id
                        WHERE s.svcStatus = 'Active'
                        GROUP BY r.id, r.name
                        ORDER BY r.level ASC
                    ");
                    break;
                    
                case 'status_distribution':
                    $result = $conn->query("
                        SELECT svcStatus as label, COUNT(*) as value
                        FROM staff
                        GROUP BY svcStatus
                        ORDER BY value DESC
                    ");
                    break;
                    
                case 'unit_distribution':
                    $result = $conn->query("
                        SELECT u.name as label, COUNT(*) as value
                        FROM staff s
                        JOIN units u ON s.unit_id = u.id
                        WHERE s.svcStatus = 'Active'
                        GROUP BY u.id, u.name
                        ORDER BY value DESC
                        LIMIT 10
                    ");
                    break;
                    
                default:
                    return [];
            }
            
            $data = [];
            while ($row = $result->fetch_assoc()) {
                $data[] = $row;
            }
            
            return $data;
            
        } catch (Exception $e) {
            error_log("Error getting chart data: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get recent activities for dashboard
     * @param int $limit
     * @return array
     */
    public static function getRecentActivities($limit = 10) {
        try {
            $conn = self::getConnection();
            
            // Get recent activities
            $activities = [];
            
            // Recent staff additions
            $result = $conn->query("
                SELECT 
                    CONCAT('New staff member: ', first_name, ' ', last_name) as message,
                    'success' as type,
                    'user-plus' as icon,
                    created_at,
                    TIMESTAMPDIFF(HOUR, created_at, NOW()) as hours_ago
                FROM staff 
                WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                ORDER BY created_at DESC 
                LIMIT " . intval($limit/2)
            );
            
            while ($row = $result->fetch_assoc()) {
                $activities[] = [
                    'message' => $row['message'],
                    'type' => $row['type'],
                    'icon' => $row['icon'],
                    'time_ago' => $row['hours_ago'] < 24 ? 
                        $row['hours_ago'] . ' hours ago' : 
                        ceil($row['hours_ago']/24) . ' days ago'
                ];
            }
            
            return array_slice($activities, 0, $limit);
            
        } catch (Exception $e) {
            error_log("Error getting recent activities: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get system alerts
     * @return array
     */
    public static function getSystemAlerts() {
        try {
            $conn = self::getConnection();
            $alerts = [];
            
            // Medical exams due soon
            $result = $conn->query("
                SELECT COUNT(*) as count
                FROM staff_medical_records smr
                JOIN staff s ON smr.staff_id = s.service_number
                WHERE smr.next_medical_exam <= DATE_ADD(CURDATE(), INTERVAL 7 DAY)
                AND smr.next_medical_exam IS NOT NULL
                AND s.svcStatus = 'Active'
            ");
            
            $medicalDue = $result->fetch_assoc()['count'];
            if ($medicalDue > 0) {
                $alerts[] = [
                    'message' => "$medicalDue personnel have medical exams due within 7 days",
                    'severity' => 'warning',
                    'icon' => 'heartbeat'
                ];
            }
            
            return $alerts;
            
        } catch (Exception $e) {
            error_log("Error getting system alerts: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Search staff members
     * @param string $query
     * @return array
     */
    public static function searchStaff($query) {
        try {
            $conn = self::getConnection();
            
            $stmt = $conn->prepare("
                SELECT 
                    s.service_number,
                    s.first_name,
                    s.last_name,
                    r.name as rank,
                    u.name as unit,
                    s.svcStatus as status
                FROM staff s
                LEFT JOIN ranks r ON s.rank_id = r.id
                LEFT JOIN units u ON s.unit_id = u.id
                WHERE s.first_name LIKE ? 
                   OR s.last_name LIKE ? 
                   OR s.service_number LIKE ?
                   OR CONCAT(s.first_name, ' ', s.last_name) LIKE ?
                ORDER BY s.last_name, s.first_name
                LIMIT 20
            ");
            
            $searchTerm = '%' . $query . '%';
            $stmt->bind_param('ssss', $searchTerm, $searchTerm, $searchTerm, $searchTerm);
            $stmt->execute();
            $result = $stmt->get_result();
            
            $staff = [];
            while ($row = $result->fetch_assoc()) {
                $staff[] = $row;
            }
            
            return $staff;
            
        } catch (Exception $e) {
            error_log("Error searching staff: " . $e->getMessage());
            return [];
        }
    }
}
