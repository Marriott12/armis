<?php
/**
 * ARMIS Ordinance Module Functions
 * Common utility functions for ordinance operations
 */

require_once __DIR__ . '/config.php';
require_once dirname(dirname(__DIR__)) . '/shared/database_connection.php';

class OrdinanceUtils {
    
    public static function getInventoryItems($category = null) {
        $sql = "SELECT * FROM ordinance_inventory";
        $params = [];
        
        if ($category) {
            $sql .= " WHERE category = ?";
            $params[] = $category;
        }
        
        $sql .= " ORDER BY item_name ASC";
        return fetchAll($sql, $params) ?: [];
    }
    
    public static function getWeapons($status = 'Available') {
        $sql = "SELECT * FROM weapons_registry";
        $params = [];
        
        if ($status) {
            $sql .= " WHERE status = ?";
            $params[] = $status;
        }
        
        $sql .= " ORDER BY weapon_type ASC, serial_number ASC";
        return fetchAll($sql, $params) ?: [];
    }
    
    public static function getOrdinanceStats() {
        $stats = [];
        
        $result = fetchOne("SELECT COUNT(*) as total FROM ordinance_inventory");
        $stats['total_inventory'] = $result ? $result->total : 0;
        
        $result = fetchOne("SELECT COUNT(*) as total FROM weapons_registry WHERE status IN ('Available', 'Assigned')");
        $stats['weapons'] = $result ? $result->total : 0;
        
        $result = fetchOne("SELECT SUM(quantity) as total FROM ammunition_inventory");
        $stats['ammunition'] = $result ? $result->total : 0;
        
        $result = fetchOne("SELECT COUNT(*) as total FROM maintenance_records WHERE status = 'In Progress'");
        $stats['maintenance'] = $result ? $result->total : 0;
        
        return $stats;
    }
    
    public static function createTransaction($data) {
        $sql = "INSERT INTO ordinance_transactions (item_id, transaction_type, quantity, authorized_by, transaction_date) 
                VALUES (?, ?, ?, ?, NOW())";
        
        return executeQuery($sql, [
            $data['item_id'],
            $data['transaction_type'],
            $data['quantity'],
            $_SESSION['user_id']
        ]);
    }
    
    public static function getStatusBadgeClass($status) {
        $classes = [
            'Available' => 'bg-success',
            'Assigned' => 'bg-primary',
            'Maintenance' => 'bg-warning',
            'Damaged' => 'bg-danger',
            'Retired' => 'bg-secondary',
            'Lost' => 'bg-dark'
        ];
        return $classes[$status] ?? 'bg-secondary';
    }
    
    public static function logSecurityEvent($action, $description) {
        $sql = "INSERT INTO ordinance_security_logs (action, description, user_id, timestamp) 
                VALUES (?, ?, ?, NOW())";
        
        return executeQuery($sql, [$action, $description, $_SESSION['user_id']]);
    }
}