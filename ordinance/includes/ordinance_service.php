<?php
/**
 * Ordinance Dashboard Service
 */

if (!defined('ARMIS_ORDINANCE')) {
    die('Direct access not permitted');
}

class OrdinanceService {
    private $db;
    
    public function __construct($database_connection) {
        $this->db = $database_connection;
    }
    
    public function getKPIData() {
        try {
            return [
                'total_equipment' => 450,
                'operational_equipment' => 425,
                'maintenance_pending' => 15,
                'inventory_value' => 2500000,
                'supply_orders' => 8
            ];
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
    
    public function getRecentActivities($limit = 10) {
        try {
            return [
                [
                    'id' => 1,
                    'title' => 'Equipment Maintenance Completed',
                    'description' => 'Vehicle fleet maintenance completed',
                    'status' => 'completed',
                    'timestamp' => date('Y-m-d H:i:s'),
                    'type' => 'maintenance'
                ],
                [
                    'id' => 2,
                    'title' => 'New Equipment Received',
                    'description' => 'Communications equipment delivery',
                    'status' => 'received',
                    'timestamp' => date('Y-m-d H:i:s', strtotime('-1 hour')),
                    'type' => 'inventory'
                ]
            ];
        } catch (Exception $e) {
            error_log("Ordinance activities error: " . $e->getMessage());
            return [];
        }
    }
    
    public function getInventoryOverview() {
        try {
            return [
                [
                    'category' => 'Vehicles',
                    'total_items' => 45,
                    'operational' => 42,
                    'maintenance' => 3,
                    'value' => 1500000
                ],
                [
                    'category' => 'Communications',
                    'total_items' => 120,
                    'operational' => 118,
                    'maintenance' => 2,
                    'value' => 300000
                ],
                [
                    'category' => 'Medical Equipment',
                    'total_items' => 85,
                    'operational' => 83,
                    'maintenance' => 2,
                    'value' => 200000
                ]
            ];
        } catch (Exception $e) {
            error_log("Ordinance inventory overview error: " . $e->getMessage());
            return [];
        }
    }
    
    public function getMaintenanceSchedule() {
        try {
            return [
                [
                    'equipment' => 'Combat Vehicle CV-001',
                    'type' => 'scheduled',
                    'due_date' => date('Y-m-d', strtotime('+7 days')),
                    'priority' => 'medium'
                ],
                [
                    'equipment' => 'Communications Array CA-010',
                    'type' => 'inspection',
                    'due_date' => date('Y-m-d', strtotime('+3 days')),
                    'priority' => 'high'
                ]
            ];
        } catch (Exception $e) {
            error_log("Ordinance maintenance schedule error: " . $e->getMessage());
            return [];
        }
    }
}
?>