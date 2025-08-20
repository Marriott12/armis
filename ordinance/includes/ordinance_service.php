<?php
/**
 * Ordinance Dashboard Service
 * Provides dynamic data for ordinance dashboard and functionality
 */

if (!defined('ARMIS_ORDINANCE')) {
    die('Direct access not permitted');
}

class OrdinanceService {
    private $db;
    
    public function __construct($database_connection) {
        $this->db = $database_connection;
    }
    
    /**
     * Get ordinance KPI data
     */
    public function getKPIData() {
        try {
            $kpis = [];
            
            // Total Equipment
            $stmt = $this->db->prepare("SELECT COUNT(*) as total FROM ordinance_inventory WHERE status = 'active'");
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $kpis['total_equipment'] = (int)($result['total'] ?? 0);
            
            // Operational Equipment
            $stmt = $this->db->prepare("SELECT COUNT(*) as total FROM ordinance_inventory WHERE condition_status IN ('excellent', 'good') AND status = 'active'");
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $kpis['operational_equipment'] = (int)($result['total'] ?? 0);
            
            // Maintenance Pending
            $stmt = $this->db->prepare("SELECT COUNT(*) as total FROM maintenance_records WHERE status IN ('scheduled', 'in_progress')");
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $kpis['maintenance_pending'] = (int)($result['total'] ?? 0);
            
            // Inventory Value
            $stmt = $this->db->prepare("SELECT SUM(total_value) as total_value FROM ordinance_inventory WHERE status = 'active'");
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $kpis['inventory_value'] = (float)($result['total_value'] ?? 0);
            
            // Supply Orders (recent transactions)
            $stmt = $this->db->prepare("SELECT COUNT(*) as total FROM ordinance_transactions WHERE transaction_type = 'issue' AND DATE(transaction_date) >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)");
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $kpis['supply_orders'] = (int)($result['total'] ?? 0);
            
            error_log("Ordinance KPI data retrieved: " . json_encode($kpis));
            return $kpis;
            
        } catch (Exception $e) {
            error_log("Ordinance KPI error: " . $e->getMessage());
            return [
                'total_equipment' => 0,
                'operational_equipment' => 0,
                'maintenance_pending' => 0,
                'inventory_value' => 0,
                'supply_orders' => 0
            ];
        }
    }
    
    /**
     * Get recent ordinance activities
     */
    public function getRecentActivities($limit = 10) {
        try {
            $stmt = $this->db->prepare("
                SELECT 
                    id,
                    transaction_type,
                    item_type,
                    quantity,
                    purpose,
                    transaction_date,
                    created_at
                FROM ordinance_transactions 
                ORDER BY transaction_date DESC 
                LIMIT ?
            ");
            $stmt->execute([$limit]);
            $activities = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            return array_map(function($activity) {
                return [
                    'id' => $activity['id'],
                    'title' => ucfirst($activity['transaction_type']) . ' - ' . $activity['item_type'],
                    'description' => $activity['purpose'] ?? 'Equipment transaction',
                    'status' => 'completed',
                    'timestamp' => $activity['created_at'],
                    'quantity' => $activity['quantity'],
                    'type' => $activity['transaction_type']
                ];
            }, $activities);
            
        } catch (Exception $e) {
            error_log("Ordinance activities error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get inventory overview
     */
    public function getInventoryOverview() {
        try {
            $stmt = $this->db->prepare("
                SELECT 
                    category,
                    COUNT(*) as total_items,
                    SUM(CASE WHEN condition_status IN ('excellent', 'good') THEN 1 ELSE 0 END) as operational,
                    SUM(CASE WHEN condition_status IN ('fair', 'poor') THEN 1 ELSE 0 END) as maintenance_needed,
                    SUM(total_value) as category_value
                FROM ordinance_inventory
                WHERE status = 'active'
                GROUP BY category
                ORDER BY category
            ");
            $stmt->execute();
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (Exception $e) {
            error_log("Ordinance inventory overview error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get maintenance schedule
     */
    public function getMaintenanceSchedule() {
        try {
            $stmt = $this->db->prepare("
                SELECT 
                    mr.*,
                    CASE 
                        WHEN mr.item_type = 'weapon' THEN wr.weapon_name
                        WHEN mr.item_type = 'equipment' THEN oi.item_name
                        ELSE 'Unknown Item'
                    END as item_name
                FROM maintenance_records mr
                LEFT JOIN weapons_registry wr ON mr.item_type = 'weapon' AND mr.item_id = wr.id
                LEFT JOIN ordinance_inventory oi ON mr.item_type = 'equipment' AND mr.item_id = oi.id
                WHERE mr.status IN ('scheduled', 'in_progress')
                ORDER BY mr.scheduled_date ASC
                LIMIT 20
            ");
            $stmt->execute();
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (Exception $e) {
            error_log("Ordinance maintenance schedule error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get weapons status
     */
    public function getWeaponsStatus() {
        try {
            $stmt = $this->db->prepare("
                SELECT 
                    weapon_type,
                    COUNT(*) as total_weapons,
                    SUM(CASE WHEN operational_status = 'operational' THEN 1 ELSE 0 END) as operational,
                    SUM(CASE WHEN operational_status = 'maintenance' THEN 1 ELSE 0 END) as maintenance,
                    SUM(CASE WHEN assigned_to_personnel IS NOT NULL THEN 1 ELSE 0 END) as assigned
                FROM weapons_registry
                GROUP BY weapon_type
                ORDER BY weapon_type
            ");
            $stmt->execute();
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (Exception $e) {
            error_log("Ordinance weapons status error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get ammunition status
     */
    public function getAmmunitionStatus() {
        try {
            $stmt = $this->db->prepare("
                SELECT 
                    caliber,
                    SUM(remaining_quantity) as total_remaining,
                    SUM(quantity) as total_stock,
                    COUNT(*) as lot_count,
                    SUM(CASE WHEN expiry_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY) THEN remaining_quantity ELSE 0 END) as expiring_soon
                FROM ammunition_inventory
                WHERE status = 'active'
                GROUP BY caliber
                ORDER BY caliber
            ");
            $stmt->execute();
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (Exception $e) {
            error_log("Ordinance ammunition status error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get security alerts
     */
    public function getSecurityAlerts() {
        try {
            $stmt = $this->db->prepare("
                SELECT *
                FROM ordinance_security_logs
                WHERE resolved = FALSE
                AND severity IN ('critical', 'warning')
                ORDER BY timestamp DESC
                LIMIT 20
            ");
            $stmt->execute();
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (Exception $e) {
            error_log("Ordinance security alerts error: " . $e->getMessage());
            return [];
        }
    }
}
?>