<?php
/**
 * ARMIS Ordinance Service
 * Business logic layer for ordinance management
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';
require_once dirname(dirname(__DIR__)) . '/shared/database_connection.php';

class OrdinanceService {
    private $pdo;
    
    public function __construct($pdo = null) {
        $this->pdo = $pdo ?: getDbConnection();
    }
    
    public function getDashboardData() {
        return [
            'stats' => OrdinanceUtils::getOrdinanceStats(),
            'recent_transactions' => $this->getRecentTransactions(10),
            'maintenance_schedule' => $this->getMaintenanceSchedule(5),
            'weapon_assignments' => $this->getWeaponAssignments(5)
        ];
    }
    
    public function createWeapon($data) {
        $required = ['serial_number', 'weapon_type', 'manufacturer', 'model'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                throw new Exception("Field {$field} is required");
            }
        }
        
        // Check for duplicate serial number
        $existing = fetchOne("SELECT id FROM weapons_registry WHERE serial_number = ?", [$data['serial_number']]);
        if ($existing) {
            throw new Exception("Weapon with serial number already exists");
        }
        
        $sql = "INSERT INTO weapons_registry (serial_number, weapon_type, manufacturer, model, status, created_by) 
                VALUES (?, ?, ?, ?, 'Available', ?)";
        
        $weaponId = executeQuery($sql, [
            $data['serial_number'],
            $data['weapon_type'],
            $data['manufacturer'],
            $data['model'],
            $_SESSION['user_id']
        ]);
        
        if ($weaponId) {
            OrdinanceUtils::logSecurityEvent('weapon_registered', "Weapon {$data['serial_number']} registered");
            logOrdinanceActivity('weapon_created', "Weapon registered", [
                'weapon_id' => $weaponId,
                'serial_number' => $data['serial_number']
            ]);
        }
        
        return $weaponId;
    }
    
    public function createTransaction($data) {
        $required = ['item_id', 'transaction_type', 'quantity'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                throw new Exception("Field {$field} is required");
            }
        }
        
        // Validate transaction type
        if (!in_array($data['transaction_type'], array_keys(TRANSACTION_TYPES))) {
            throw new Exception("Invalid transaction type");
        }
        
        $transactionId = OrdinanceUtils::createTransaction($data);
        
        if ($transactionId) {
            OrdinanceUtils::logSecurityEvent('transaction_created', "Transaction: {$data['transaction_type']} - Quantity: {$data['quantity']}");
            logOrdinanceActivity('transaction_created', "Ordinance transaction created", [
                'transaction_id' => $transactionId,
                'type' => $data['transaction_type'],
                'quantity' => $data['quantity']
            ]);
        }
        
        return $transactionId;
    }
    
    public function searchWeapons($params = []) {
        $sql = "SELECT * FROM weapons_registry WHERE 1=1";
        $bindings = [];
        
        if (!empty($params['status'])) {
            $sql .= " AND status = ?";
            $bindings[] = $params['status'];
        }
        
        if (!empty($params['weapon_type'])) {
            $sql .= " AND weapon_type = ?";
            $bindings[] = $params['weapon_type'];
        }
        
        if (!empty($params['search'])) {
            $sql .= " AND (serial_number LIKE ? OR manufacturer LIKE ? OR model LIKE ?)";
            $searchTerm = '%' . $params['search'] . '%';
            $bindings[] = $searchTerm;
            $bindings[] = $searchTerm;
            $bindings[] = $searchTerm;
        }
        
        $sql .= " ORDER BY weapon_type ASC, serial_number ASC";
        
        return fetchAll($sql, $bindings) ?: [];
    }
    
    private function getRecentTransactions($limit) {
        $sql = "SELECT ot.*, oi.item_name 
                FROM ordinance_transactions ot
                LEFT JOIN ordinance_inventory oi ON ot.item_id = oi.id
                ORDER BY ot.transaction_date DESC 
                LIMIT ?";
        return fetchAll($sql, [$limit]) ?: [];
    }
    
    private function getMaintenanceSchedule($limit) {
        $sql = "SELECT mr.*, wr.serial_number, wr.weapon_type
                FROM maintenance_records mr
                LEFT JOIN weapons_registry wr ON mr.weapon_id = wr.id
                WHERE mr.status = 'Scheduled'
                ORDER BY mr.scheduled_date ASC
                LIMIT ?";
        return fetchAll($sql, [$limit]) ?: [];
    }
    
    private function getWeaponAssignments($limit) {
        $sql = "SELECT wa.*, wr.serial_number, wr.weapon_type, s.fname, s.lname
                FROM weapon_assignments wa
                LEFT JOIN weapons_registry wr ON wa.weapon_id = wr.id
                LEFT JOIN staff s ON wa.staff_id = s.staffID
                WHERE wa.status = 'Active'
                ORDER BY wa.assigned_date DESC
                LIMIT ?";
        return fetchAll($sql, [$limit]) ?: [];
    }
}