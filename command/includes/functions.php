<?php
/**
 * ARMIS Command Module Functions
 * Common utility functions for command operations
 */

// Include required files
require_once __DIR__ . '/config.php';
require_once dirname(dirname(__DIR__)) . '/shared/database_connection.php';

/**
 * Command utility functions
 */
class CommandUtils {
    
    /**
     * Get command hierarchy data
     */
    public static function getCommandHierarchy() {
        $sql = "SELECT * FROM command_hierarchy ORDER BY hierarchy_level ASC, command_name ASC";
        return fetchAll($sql) ?: [];
    }
    
    /**
     * Get active missions
     */
    public static function getActiveMissions($limit = null) {
        $sql = "SELECT * FROM missions WHERE status = 'Active' ORDER BY priority DESC, start_date ASC";
        if ($limit) {
            $sql .= " LIMIT " . intval($limit);
        }
        return fetchAll($sql) ?: [];
    }
    
    /**
     * Get mission by ID
     */
    public static function getMissionById($missionId) {
        $sql = "SELECT * FROM missions WHERE id = ?";
        return fetchOne($sql, [$missionId]);
    }
    
    /**
     * Get personnel assignments for a mission
     */
    public static function getMissionPersonnel($missionId) {
        $sql = "SELECT pa.*, s.fname, s.lname, s.svcno, r.rankName 
                FROM personnel_assignments pa
                LEFT JOIN staff s ON pa.staff_id = s.staffID
                LEFT JOIN ranks r ON s.rankID = r.rankID
                WHERE pa.mission_id = ?
                ORDER BY r.rankIndex DESC";
        return fetchAll($sql, [$missionId]) ?: [];
    }
    
    /**
     * Get command statistics
     */
    public static function getCommandStats() {
        $stats = [];
        
        // Active missions
        $result = fetchOne("SELECT COUNT(*) as total FROM missions WHERE status = 'Active'");
        $stats['active_missions'] = $result ? $result->total : 0;
        
        // Personnel ready
        $result = fetchOne("SELECT COUNT(*) as total FROM staff WHERE status = 'Active'");
        $totalPersonnel = $result ? $result->total : 1;
        
        $result = fetchOne("SELECT COUNT(*) as total FROM staff WHERE status = 'Active' AND availability = 'Available'");
        $availablePersonnel = $result ? $result->total : 0;
        
        $stats['personnel_ready_percent'] = $totalPersonnel > 0 ? round(($availablePersonnel / $totalPersonnel) * 100) : 0;
        
        // Alerts
        $result = fetchOne("SELECT COUNT(*) as total FROM alerts WHERE status = 'Active' AND priority IN ('High', 'Critical')");
        $stats['alerts'] = $result ? $result->total : 0;
        
        // Mission status
        $result = fetchOne("SELECT COUNT(*) as total FROM missions WHERE status = 'Active' AND priority = 'Critical'");
        $criticalMissions = $result ? $result->total : 0;
        $stats['mission_status'] = $criticalMissions > 0 ? 'RED' : 'GREEN';
        
        return $stats;
    }
    
    /**
     * Create new mission
     */
    public static function createMission($data) {
        $sql = "INSERT INTO missions (mission_name, description, start_date, end_date, priority, status, created_by) 
                VALUES (?, ?, ?, ?, ?, ?, ?)";
        
        return executeQuery($sql, [
            $data['mission_name'],
            $data['description'],
            $data['start_date'],
            $data['end_date'],
            $data['priority'],
            'Planning',
            $_SESSION['user_id']
        ]);
    }
    
    /**
     * Assign personnel to mission
     */
    public static function assignPersonnelToMission($missionId, $staffId, $role) {
        $sql = "INSERT INTO personnel_assignments (mission_id, staff_id, role, assigned_date, assigned_by) 
                VALUES (?, ?, ?, NOW(), ?)";
        
        return executeQuery($sql, [$missionId, $staffId, $role, $_SESSION['user_id']]);
    }
    
    /**
     * Get communication logs
     */
    public static function getCommunicationLogs($limit = 50) {
        $sql = "SELECT * FROM communication_logs ORDER BY timestamp DESC LIMIT ?";
        return fetchAll($sql, [$limit]) ?: [];
    }
    
    /**
     * Log communication
     */
    public static function logCommunication($type, $message, $priority = 'Medium') {
        $sql = "INSERT INTO communication_logs (type, message, priority, timestamp, user_id) 
                VALUES (?, ?, ?, NOW(), ?)";
        
        return executeQuery($sql, [$type, $message, $priority, $_SESSION['user_id']]);
    }
    
    /**
     * Format mission duration
     */
    public static function formatMissionDuration($startDate, $endDate) {
        $start = new DateTime($startDate);
        $end = new DateTime($endDate);
        $diff = $start->diff($end);
        
        if ($diff->days > 0) {
            return $diff->days . ' days';
        } elseif ($diff->h > 0) {
            return $diff->h . ' hours';
        } else {
            return $diff->i . ' minutes';
        }
    }
    
    /**
     * Get mission status badge class
     */
    public static function getMissionStatusBadgeClass($status) {
        $classes = [
            'Planning' => 'bg-secondary',
            'Active' => 'bg-success',
            'On Hold' => 'bg-warning',
            'Completed' => 'bg-primary',
            'Cancelled' => 'bg-danger'
        ];
        
        return $classes[$status] ?? 'bg-secondary';
    }
    
    /**
     * Get priority badge class
     */
    public static function getPriorityBadgeClass($priority) {
        $classes = [
            'Low' => 'bg-secondary',
            'Medium' => 'bg-info',
            'High' => 'bg-warning',
            'Critical' => 'bg-danger'
        ];
        
        return $classes[$priority] ?? 'bg-secondary';
    }
}