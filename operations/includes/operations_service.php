<?php
/**
 * Operations Dashboard Service
 * Provides dynamic data for operations dashboard and functionality
 */

if (!defined('ARMIS_OPERATIONS')) {
    die('Direct access not permitted');
}

class OperationsService {
    private $db;
    
    public function __construct($database_connection) {
        $this->db = $database_connection;
    }
    
    /**
     * Get operations KPI data
     */
    public function getKPIData() {
        try {
            $kpis = [];
            
            // Active Missions
            $stmt = $this->db->prepare("SELECT COUNT(*) as total FROM operations_missions WHERE status = 'active'");
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $kpis['active_missions'] = (int)($result['total'] ?? 0);
            
            // Total Deployments
            $stmt = $this->db->prepare("SELECT COUNT(*) as total FROM operations_deployments WHERE status IN ('active', 'deployed')");
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $kpis['active_deployments'] = (int)($result['total'] ?? 0);
            
            // Resource Utilization (percentage)
            $stmt = $this->db->prepare("
                SELECT 
                    COALESCE(AVG(CASE WHEN allocated_quantity > 0 THEN (allocated_quantity / available_quantity) * 100 END), 0) as utilization
                FROM operations_resources 
                WHERE available_quantity > 0
            ");
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $kpis['resource_utilization'] = round($result['utilization'] ?? 0, 1);
            
            // Priority Alerts
            $stmt = $this->db->prepare("SELECT COUNT(*) as total FROM operations_alerts WHERE priority IN ('high', 'critical') AND status = 'active'");
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $kpis['priority_alerts'] = (int)($result['total'] ?? 0);
            
            // Field Units
            $stmt = $this->db->prepare("SELECT COUNT(DISTINCT unit_id) as total FROM operations_deployments WHERE status = 'deployed'");
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $kpis['field_units'] = (int)($result['total'] ?? 0);
            
            error_log("Operations KPI data retrieved: " . json_encode($kpis));
            return $kpis;
            
        } catch (Exception $e) {
            error_log("Operations KPI error: " . $e->getMessage());
            return [
                'active_missions' => 0,
                'active_deployments' => 0, 
                'resource_utilization' => 0,
                'priority_alerts' => 0,
                'field_units' => 0
            ];
        }
    }
    
    /**
     * Get recent operations activities
     */
    public function getRecentActivities($limit = 10) {
        try {
            $stmt = $this->db->prepare("
                SELECT 
                    id,
                    mission_name as title,
                    description,
                    status,
                    priority,
                    created_at,
                    updated_at
                FROM operations_missions 
                ORDER BY updated_at DESC 
                LIMIT ?
            ");
            $stmt->execute([$limit]);
            $activities = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            return array_map(function($activity) {
                return [
                    'id' => $activity['id'],
                    'title' => $activity['title'],
                    'description' => $activity['description'],
                    'status' => $activity['status'],
                    'priority' => $activity['priority'],
                    'timestamp' => $activity['updated_at'],
                    'type' => 'mission'
                ];
            }, $activities);
            
        } catch (Exception $e) {
            error_log("Operations activities error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get mission statistics
     */
    public function getMissionStats() {
        try {
            $stats = [];
            
            // Mission status distribution
            $stmt = $this->db->prepare("
                SELECT status, COUNT(*) as count 
                FROM operations_missions 
                GROUP BY status
            ");
            $stmt->execute();
            $statusResults = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $stats['status_distribution'] = [];
            foreach ($statusResults as $status) {
                $stats['status_distribution'][$status['status']] = (int)$status['count'];
            }
            
            // Mission priority distribution
            $stmt = $this->db->prepare("
                SELECT priority, COUNT(*) as count 
                FROM operations_missions 
                WHERE status = 'active'
                GROUP BY priority
            ");
            $stmt->execute();
            $priorityResults = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $stats['priority_distribution'] = [];
            foreach ($priorityResults as $priority) {
                $stats['priority_distribution'][$priority['priority']] = (int)$priority['count'];
            }
            
            return $stats;
            
        } catch (Exception $e) {
            error_log("Operations mission stats error: " . $e->getMessage());
            return ['status_distribution' => [], 'priority_distribution' => []];
        }
    }
    
    /**
     * Get deployment overview
     */
    public function getDeploymentOverview() {
        try {
            $stmt = $this->db->prepare("
                SELECT 
                    d.*,
                    u.unit_name,
                    COUNT(dp.personnel_id) as personnel_count
                FROM operations_deployments d
                LEFT JOIN units u ON d.unit_id = u.id
                LEFT JOIN deployment_personnel dp ON d.id = dp.deployment_id
                WHERE d.status IN ('active', 'deployed')
                GROUP BY d.id
                ORDER BY d.start_date DESC
            ");
            $stmt->execute();
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (Exception $e) {
            error_log("Operations deployment overview error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get resource allocation data
     */
    public function getResourceAllocation() {
        try {
            $stmt = $this->db->prepare("
                SELECT 
                    resource_type,
                    SUM(available_quantity) as total_available,
                    SUM(allocated_quantity) as total_allocated,
                    SUM(available_quantity - allocated_quantity) as remaining
                FROM operations_resources
                GROUP BY resource_type
                ORDER BY resource_type
            ");
            $stmt->execute();
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (Exception $e) {
            error_log("Operations resource allocation error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get active alerts
     */
    public function getActiveAlerts() {
        try {
            $stmt = $this->db->prepare("
                SELECT *
                FROM operations_alerts 
                WHERE status = 'active'
                ORDER BY 
                    CASE priority 
                        WHEN 'critical' THEN 1 
                        WHEN 'high' THEN 2 
                        WHEN 'medium' THEN 3 
                        WHEN 'low' THEN 4 
                    END,
                    created_at DESC
                LIMIT 20
            ");
            $stmt->execute();
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (Exception $e) {
            error_log("Operations alerts error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get operational readiness metrics
     */
    public function getReadinessMetrics() {
        try {
            $metrics = [];
            
            // Equipment readiness
            $stmt = $this->db->prepare("
                SELECT 
                    COUNT(*) as total_equipment,
                    SUM(CASE WHEN status = 'operational' THEN 1 ELSE 0 END) as operational_equipment
                FROM operations_equipment
            ");
            $stmt->execute();
            $equipment = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $total = (int)$equipment['total_equipment'];
            $operational = (int)$equipment['operational_equipment'];
            $metrics['equipment_readiness'] = $total > 0 ? round(($operational / $total) * 100, 1) : 0;
            
            // Personnel readiness
            $stmt = $this->db->prepare("
                SELECT 
                    COUNT(*) as total_personnel,
                    SUM(CASE WHEN readiness_status = 'ready' THEN 1 ELSE 0 END) as ready_personnel
                FROM operations_personnel_readiness
            ");
            $stmt->execute();
            $personnel = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $totalPersonnel = (int)$personnel['total_personnel'];
            $readyPersonnel = (int)$personnel['ready_personnel'];
            $metrics['personnel_readiness'] = $totalPersonnel > 0 ? round(($readyPersonnel / $totalPersonnel) * 100, 1) : 0;
            
            return $metrics;
            
        } catch (Exception $e) {
            error_log("Operations readiness metrics error: " . $e->getMessage());
            return ['equipment_readiness' => 0, 'personnel_readiness' => 0];
        }
    }
}
?>