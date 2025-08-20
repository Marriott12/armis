<?php
/**
 * ARMIS Dashboard Controller
 * Handles dashboard data and analytics
 */

require_once __DIR__ . '/../middleware/authentication.php';

class DashboardController {
    private $db;
    
    public function __construct() {
        $this->db = getDbConnection();
    }
    
    /**
     * Get dashboard statistics
     */
    public function getStats($params, $data) {
        try {
            $stats = [
                'personnel' => $this->getPersonnelStats(),
                'operations' => $this->getOperationsStats(),
                'training' => $this->getTrainingStats(),
                'system' => $this->getSystemStats()
            ];
            
            return $stats;
            
        } catch (Exception $e) {
            error_log("Dashboard stats error: " . $e->getMessage());
            return ['error' => 'Failed to fetch dashboard statistics'];
        }
    }
    
    /**
     * Get KPI data
     */
    public function getKPI($params, $data) {
        try {
            $kpi = [
                'total_personnel' => $this->getTotalPersonnel(),
                'active_personnel' => $this->getActivePersonnel(),
                'new_recruits' => $this->getNewRecruits(),
                'training_completion' => $this->getTrainingCompletion(),
                'system_health' => $this->getSystemHealth()
            ];
            
            return $kpi;
            
        } catch (Exception $e) {
            error_log("Dashboard KPI error: " . $e->getMessage());
            return ['error' => 'Failed to fetch KPI data'];
        }
    }
    
    /**
     * Get recent activities
     */
    public function getActivities($params, $data) {
        try {
            $limit = isset($data['limit']) ? (int)$data['limit'] : 20;
            
            $stmt = $this->db->prepare("
                SELECT sal.*, s.username, s.first_name, s.last_name
                FROM security_audit_log sal
                LEFT JOIN staff s ON sal.user_id = s.id
                ORDER BY sal.timestamp DESC
                LIMIT ?
            ");
            $stmt->execute([$limit]);
            
            $activities = [];
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $activities[] = [
                    'id' => $row['id'],
                    'action' => $row['action'],
                    'resource' => $row['resource'],
                    'user' => [
                        'username' => $row['username'],
                        'name' => trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? ''))
                    ],
                    'timestamp' => $row['timestamp'],
                    'severity' => $row['severity']
                ];
            }
            
            return $activities;
            
        } catch (Exception $e) {
            error_log("Dashboard activities error: " . $e->getMessage());
            return ['error' => 'Failed to fetch activities'];
        }
    }
    
    /**
     * Get personnel statistics
     */
    private function getPersonnelStats() {
        try {
            $stmt = $this->db->query("
                SELECT 
                    COUNT(*) as total,
                    SUM(CASE WHEN svcStatus = 'Active' THEN 1 ELSE 0 END) as active,
                    SUM(CASE WHEN category = 'Officer' THEN 1 ELSE 0 END) as officers,
                    SUM(CASE WHEN category = 'NCO' THEN 1 ELSE 0 END) as ncos,
                    SUM(CASE WHEN category = 'Enlisted' THEN 1 ELSE 0 END) as enlisted
                FROM staff 
                WHERE svcStatus != 'Discharged'
            ");
            
            return $stmt->fetch(PDO::FETCH_ASSOC);
            
        } catch (Exception $e) {
            error_log("Personnel stats error: " . $e->getMessage());
            return ['total' => 0, 'active' => 0, 'officers' => 0, 'ncos' => 0, 'enlisted' => 0];
        }
    }
    
    /**
     * Get operations statistics
     */
    private function getOperationsStats() {
        // This would be connected to operations module
        return [
            'active_operations' => 7,
            'completed_this_month' => 23,
            'success_rate' => 94.5,
            'resources_deployed' => 1247
        ];
    }
    
    /**
     * Get training statistics
     */
    private function getTrainingStats() {
        // This would be connected to training module
        return [
            'active_courses' => 12,
            'enrolled_personnel' => 186,
            'completion_rate' => 87.3,
            'certifications_issued' => 45
        ];
    }
    
    /**
     * Get system statistics
     */
    private function getSystemStats() {
        try {
            // Get recent login activity
            $stmt = $this->db->query("
                SELECT COUNT(*) as logins_today
                FROM login_attempts 
                WHERE attempted_at >= CURDATE() AND successful = 1
            ");
            $logins = $stmt->fetch()['logins_today'] ?? 0;
            
            // Get system performance metrics
            $stmt = $this->db->query("
                SELECT AVG(metric_value) as avg_response_time
                FROM performance_metrics 
                WHERE metric_name LIKE '%response_time%' 
                AND timestamp >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
            ");
            $responseTime = $stmt->fetch()['avg_response_time'] ?? 0;
            
            return [
                'logins_today' => $logins,
                'avg_response_time' => round($responseTime, 2),
                'uptime' => $this->getSystemUptime(),
                'disk_usage' => $this->getDiskUsage()
            ];
            
        } catch (Exception $e) {
            error_log("System stats error: " . $e->getMessage());
            return [
                'logins_today' => 0,
                'avg_response_time' => 0,
                'uptime' => '99.9%',
                'disk_usage' => 45.2
            ];
        }
    }
    
    /**
     * Get total personnel count
     */
    private function getTotalPersonnel() {
        try {
            $stmt = $this->db->query("
                SELECT COUNT(*) as count 
                FROM staff 
                WHERE svcStatus != 'Discharged' AND service_number IS NOT NULL
            ");
            return $stmt->fetch()['count'] ?? 0;
        } catch (Exception $e) {
            return 0;
        }
    }
    
    /**
     * Get active personnel count
     */
    private function getActivePersonnel() {
        try {
            $stmt = $this->db->query("
                SELECT COUNT(*) as count 
                FROM staff 
                WHERE svcStatus = 'Active' AND service_number IS NOT NULL
            ");
            return $stmt->fetch()['count'] ?? 0;
        } catch (Exception $e) {
            return 0;
        }
    }
    
    /**
     * Get new recruits (last 30 days)
     */
    private function getNewRecruits() {
        try {
            $stmt = $this->db->query("
                SELECT COUNT(*) as count 
                FROM staff 
                WHERE attestDate >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
                AND svcStatus != 'Discharged'
                AND attestDate IS NOT NULL
            ");
            return $stmt->fetch()['count'] ?? 0;
        } catch (Exception $e) {
            return 0;
        }
    }
    
    /**
     * Get training completion rate
     */
    private function getTrainingCompletion() {
        // This would be calculated from training module data
        return 87.3;
    }
    
    /**
     * Get system health percentage
     */
    private function getSystemHealth() {
        try {
            // Check database connectivity
            $dbHealth = $this->db ? 1 : 0;
            
            // Check disk space
            $diskFree = disk_free_space(__DIR__);
            $diskTotal = disk_total_space(__DIR__);
            $diskHealth = $diskFree && $diskTotal && (($diskFree / $diskTotal) > 0.1) ? 1 : 0;
            
            // Check memory usage
            $memoryUsage = memory_get_usage() / memory_get_peak_usage();
            $memoryHealth = $memoryUsage < 0.9 ? 1 : 0;
            
            $healthScore = (($dbHealth + $diskHealth + $memoryHealth) / 3) * 100;
            
            return round($healthScore, 1);
        } catch (Exception $e) {
            return 85.0; // Default health score
        }
    }
    
    /**
     * Get system uptime
     */
    private function getSystemUptime() {
        if (function_exists('sys_getloadavg') && file_exists('/proc/uptime')) {
            $uptime = file_get_contents('/proc/uptime');
            $uptime = floatval(explode(' ', $uptime)[0]);
            $days = floor($uptime / 86400);
            $hours = floor(($uptime % 86400) / 3600);
            return "{$days}d {$hours}h";
        }
        return "99.9%";
    }
    
    /**
     * Get disk usage percentage
     */
    private function getDiskUsage() {
        try {
            $diskFree = disk_free_space(__DIR__);
            $diskTotal = disk_total_space(__DIR__);
            
            if ($diskFree && $diskTotal) {
                $usagePercent = (($diskTotal - $diskFree) / $diskTotal) * 100;
                return round($usagePercent, 1);
            }
        } catch (Exception $e) {
            error_log("Disk usage check failed: " . $e->getMessage());
        }
        
        return 45.2; // Default value
    }
}
?>