<?php
/**
 * ARMIS Operations Module Functions
 * Common utility functions for operations management
 */

require_once __DIR__ . '/config.php';
require_once dirname(dirname(__DIR__)) . '/shared/database_connection.php';

class OperationsUtils {
    
    public static function getActiveOperations($limit = null) {
        $sql = "SELECT * FROM operations WHERE status = 'Active' ORDER BY priority DESC, start_date ASC";
        if ($limit) $sql .= " LIMIT " . intval($limit);
        return fetchAll($sql) ?: [];
    }
    
    public static function getOperationById($operationId) {
        $sql = "SELECT * FROM operations WHERE id = ?";
        return fetchOne($sql, [$operationId]);
    }
    
    public static function getOperationsStats() {
        $stats = [];
        
        $result = fetchOne("SELECT COUNT(*) as total FROM operations WHERE status = 'Active'");
        $stats['active_missions'] = $result ? $result->total : 0;
        
        $result = fetchOne("SELECT COUNT(*) as total FROM resources WHERE status = 'Available'");
        $totalResources = $result ? $result->total : 1;
        
        $result = fetchOne("SELECT COUNT(*) as total FROM resource_allocation WHERE status = 'Allocated'");
        $allocatedResources = $result ? $result->total : 0;
        
        $stats['resources_percent'] = $totalResources > 0 ? round(($allocatedResources / $totalResources) * 100) : 0;
        
        $result = fetchOne("SELECT COUNT(*) as total FROM operations WHERE priority = 'Critical' AND status = 'Active'");
        $stats['priority_alerts'] = $result ? $result->total : 0;
        
        $result = fetchOne("SELECT COUNT(*) as total FROM deployments WHERE status = 'Deployed'");
        $stats['field_units'] = $result ? $result->total : 0;
        
        return $stats;
    }
    
    public static function createOperation($data) {
        $sql = "INSERT INTO operations (operation_name, description, start_date, end_date, priority, status, classification, created_by) 
                VALUES (?, ?, ?, ?, ?, 'Planning', ?, ?)";
        
        return executeQuery($sql, [
            $data['operation_name'],
            $data['description'], 
            $data['start_date'],
            $data['end_date'],
            $data['priority'],
            $data['classification'],
            $_SESSION['user_id']
        ]);
    }
    
    public static function getStatusBadgeClass($status) {
        $classes = [
            'Planning' => 'bg-secondary',
            'Active' => 'bg-success', 
            'On Hold' => 'bg-warning',
            'Completed' => 'bg-primary',
            'Cancelled' => 'bg-danger',
            'Classified' => 'bg-dark'
        ];
        return $classes[$status] ?? 'bg-secondary';
    }
}