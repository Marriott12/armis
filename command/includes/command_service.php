<?php
/**
 * ARMIS Command Service
 * Business logic layer for command operations
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';
require_once dirname(dirname(__DIR__)) . '/shared/database_connection.php';

class CommandService {
    private $pdo;
    
    public function __construct($pdo = null) {
        $this->pdo = $pdo ?: getDbConnection();
    }
    
    /**
     * Get dashboard data
     */
    public function getDashboardData() {
        return [
            'stats' => CommandUtils::getCommandStats(),
            'active_missions' => CommandUtils::getActiveMissions(5),
            'recent_communications' => CommandUtils::getCommunicationLogs(10),
            'command_hierarchy' => CommandUtils::getCommandHierarchy()
        ];
    }
    
    /**
     * Create mission with validation
     */
    public function createMission($data) {
        // Validate required fields
        $required = ['mission_name', 'description', 'start_date', 'priority'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                throw new Exception("Field {$field} is required");
            }
        }
        
        // Validate dates
        if (!empty($data['end_date']) && $data['end_date'] < $data['start_date']) {
            throw new Exception("End date cannot be before start date");
        }
        
        // Create mission
        $missionId = CommandUtils::createMission($data);
        
        if ($missionId) {
            logCommandActivity('mission_created', "Mission '{$data['mission_name']}' created", [
                'mission_id' => $missionId
            ]);
        }
        
        return $missionId;
    }
    
    /**
     * Update mission status
     */
    public function updateMissionStatus($missionId, $status) {
        $sql = "UPDATE missions SET status = ?, updated_at = NOW() WHERE id = ?";
        $result = executeQuery($sql, [$status, $missionId]);
        
        if ($result) {
            logCommandActivity('mission_status_updated', "Mission status updated to {$status}", [
                'mission_id' => $missionId,
                'new_status' => $status
            ]);
        }
        
        return $result;
    }
    
    /**
     * Get mission details with personnel
     */
    public function getMissionDetails($missionId) {
        $mission = CommandUtils::getMissionById($missionId);
        if (!$mission) {
            return null;
        }
        
        $mission->personnel = CommandUtils::getMissionPersonnel($missionId);
        return $mission;
    }
    
    /**
     * Search missions
     */
    public function searchMissions($params = []) {
        $sql = "SELECT * FROM missions WHERE 1=1";
        $bindings = [];
        
        if (!empty($params['status'])) {
            $sql .= " AND status = ?";
            $bindings[] = $params['status'];
        }
        
        if (!empty($params['priority'])) {
            $sql .= " AND priority = ?";
            $bindings[] = $params['priority'];
        }
        
        if (!empty($params['search'])) {
            $sql .= " AND (mission_name LIKE ? OR description LIKE ?)";
            $searchTerm = '%' . $params['search'] . '%';
            $bindings[] = $searchTerm;
            $bindings[] = $searchTerm;
        }
        
        $sql .= " ORDER BY priority DESC, start_date ASC";
        
        if (!empty($params['limit'])) {
            $sql .= " LIMIT " . intval($params['limit']);
        }
        
        return fetchAll($sql, $bindings) ?: [];
    }
    
    /**
     * Generate command report
     */
    public function generateCommandReport($type, $params = []) {
        switch ($type) {
            case 'mission_summary':
                return $this->generateMissionSummaryReport($params);
            case 'personnel_deployment':
                return $this->generatePersonnelDeploymentReport($params);
            case 'communication_log':
                return $this->generateCommunicationReport($params);
            default:
                throw new Exception("Unknown report type: {$type}");
        }
    }
    
    private function generateMissionSummaryReport($params) {
        $sql = "SELECT 
                    COUNT(*) as total_missions,
                    SUM(CASE WHEN status = 'Active' THEN 1 ELSE 0 END) as active_missions,
                    SUM(CASE WHEN status = 'Completed' THEN 1 ELSE 0 END) as completed_missions,
                    SUM(CASE WHEN priority = 'Critical' THEN 1 ELSE 0 END) as critical_missions
                FROM missions";
        
        $bindings = [];
        if (!empty($params['date_from'])) {
            $sql .= " WHERE start_date >= ?";
            $bindings[] = $params['date_from'];
        }
        
        return fetchOne($sql, $bindings);
    }
    
    private function generatePersonnelDeploymentReport($params) {
        $sql = "SELECT 
                    s.fname, s.lname, s.svcno, r.rankName,
                    COUNT(pa.id) as total_assignments,
                    GROUP_CONCAT(m.mission_name SEPARATOR ', ') as missions
                FROM personnel_assignments pa
                LEFT JOIN staff s ON pa.staff_id = s.staffID
                LEFT JOIN ranks r ON s.rankID = r.rankID
                LEFT JOIN missions m ON pa.mission_id = m.id
                GROUP BY pa.staff_id
                ORDER BY r.rankIndex DESC";
        
        return fetchAll($sql) ?: [];
    }
    
    private function generateCommunicationReport($params) {
        $sql = "SELECT * FROM communication_logs";
        $bindings = [];
        
        if (!empty($params['date_from'])) {
            $sql .= " WHERE timestamp >= ?";
            $bindings[] = $params['date_from'];
        }
        
        $sql .= " ORDER BY timestamp DESC";
        
        if (!empty($params['limit'])) {
            $sql .= " LIMIT " . intval($params['limit']);
        }
        
        return fetchAll($sql, $bindings) ?: [];
    }
}