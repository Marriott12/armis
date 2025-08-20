<?php
/**
 * ARMIS Logistics Service
 * Core service class for logistics and supply chain management
 */

class LogisticsService {
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    /**
     * Get inventory summary statistics
     */
    public function getInventorySummary() {
        try {
            $stmt = $this->pdo->query("
                SELECT 
                    COUNT(*) as total_items,
                    SUM(current_stock) as total_stock,
                    SUM(current_stock * unit_cost) as total_value,
                    COUNT(CASE WHEN current_stock <= minimum_stock THEN 1 END) as low_stock_count
                FROM inventory_items 
                WHERE status = 'ACTIVE'
            ");
            
            return $stmt->fetch(PDO::FETCH_ASSOC) ?: [
                'total_items' => 0,
                'total_stock' => 0,
                'total_value' => 0,
                'low_stock_count' => 0
            ];
        } catch (Exception $e) {
            error_log("Error getting inventory summary: " . $e->getMessage());
            return [
                'total_items' => 0,
                'total_stock' => 0,
                'total_value' => 0,
                'low_stock_count' => 0
            ];
        }
    }
    
    /**
     * Get pending requisitions
     */
    public function getPendingRequisitions($limit = 10) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT 
                    sr.id,
                    sr.requisition_number,
                    sr.status,
                    sr.priority,
                    sr.requested_date,
                    sr.required_date,
                    CONCAT(u.fname, ' ', u.lname) as requester_name,
                    sr.total_estimated_cost
                FROM supply_requisitions sr
                LEFT JOIN users u ON sr.requester_id = u.id
                WHERE sr.status IN ('SUBMITTED', 'APPROVED')
                ORDER BY sr.requested_date DESC
                LIMIT ?
            ");
            
            $stmt->execute([$limit]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error getting pending requisitions: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get low stock items
     */
    public function getLowStockItems($limit = 20) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT 
                    i.id,
                    i.name,
                    i.part_number,
                    i.current_stock,
                    i.minimum_stock,
                    i.unit_of_measure,
                    c.name as category_name
                FROM inventory_items i
                LEFT JOIN inventory_categories c ON i.category_id = c.id
                WHERE i.status = 'ACTIVE' 
                AND i.current_stock <= i.minimum_stock
                ORDER BY (i.current_stock / NULLIF(i.minimum_stock, 0)) ASC
                LIMIT ?
            ");
            
            $stmt->execute([$limit]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error getting low stock items: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get equipment maintenance due
     */
    public function getMaintenanceDue($limit = 10) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT 
                    ems.id,
                    ems.equipment_id,
                    ems.maintenance_type,
                    ems.next_maintenance_date,
                    ems.status,
                    CONCAT(u.fname, ' ', u.lname) as technician_name
                FROM equipment_maintenance_schedules ems
                LEFT JOIN users u ON ems.assigned_technician_id = u.id
                WHERE ems.status IN ('SCHEDULED', 'OVERDUE')
                AND ems.next_maintenance_date <= DATE_ADD(CURDATE(), INTERVAL 7 DAY)
                ORDER BY ems.next_maintenance_date ASC
                LIMIT ?
            ");
            
            $stmt->execute([$limit]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error getting maintenance due: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get recent logistics activities
     */
    public function getRecentActivities($limit = 10) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT 
                    action,
                    entity_type,
                    entity_id,
                    created_at,
                    CONCAT(u.fname, ' ', u.lname) as user_name,
                    CASE 
                        WHEN action = 'requisition_created' THEN 'New requisition submitted'
                        WHEN action = 'stock_movement' THEN 'Inventory updated'
                        WHEN action = 'vendor_created' THEN 'New vendor added'
                        WHEN action = 'maintenance_scheduled' THEN 'Maintenance scheduled'
                        ELSE action
                    END as description
                FROM audit_logs al
                LEFT JOIN users u ON al.user_id = u.id
                WHERE al.module = 'logistics'
                ORDER BY al.created_at DESC
                LIMIT ?
            ");
            
            $stmt->execute([$limit]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error getting recent activities: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Create new inventory item
     */
    public function createInventoryItem($data) {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO inventory_items (
                    category_id, name, part_number, description, 
                    unit_of_measure, minimum_stock, maximum_stock, 
                    current_stock, unit_cost, location, status
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $result = $stmt->execute([
                $data['category_id'],
                $data['name'],
                $data['part_number'] ?? null,
                $data['description'] ?? null,
                $data['unit_of_measure'] ?? 'EACH',
                $data['minimum_stock'] ?? 0,
                $data['maximum_stock'] ?? 1000,
                $data['current_stock'] ?? 0,
                $data['unit_cost'] ?? 0.00,
                $data['location'] ?? null,
                $data['status'] ?? 'ACTIVE'
            ]);
            
            if ($result) {
                $itemId = $this->pdo->lastInsertId();
                logLogisticsActivity('inventory_item_created', 'New inventory item created', 'inventory_item', $itemId);
                return $itemId;
            }
            
            return false;
        } catch (Exception $e) {
            error_log("Error creating inventory item: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Create new requisition
     */
    public function createRequisition($data) {
        try {
            $this->pdo->beginTransaction();
            
            // Generate requisition number
            $requisitionNumber = 'REQ-' . date('Y') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
            
            // Create requisition
            $stmt = $this->pdo->prepare("
                INSERT INTO supply_requisitions (
                    requisition_number, requesting_unit_id, requester_id, 
                    status, priority, requested_date, required_date, 
                    justification
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $requisitionNumber,
                $data['requesting_unit_id'] ?? null,
                $_SESSION['user_id'],
                'DRAFT',
                $data['priority'] ?? 'NORMAL',
                $data['requested_date'] ?? date('Y-m-d'),
                $data['required_date'],
                $data['justification'] ?? null
            ]);
            
            $requisitionId = $this->pdo->lastInsertId();
            
            // Add requisition items
            if (!empty($data['items'])) {
                $itemStmt = $this->pdo->prepare("
                    INSERT INTO requisition_items (
                        requisition_id, item_id, quantity_requested, unit_cost, notes
                    ) VALUES (?, ?, ?, ?, ?)
                ");
                
                foreach ($data['items'] as $item) {
                    $itemStmt->execute([
                        $requisitionId,
                        $item['item_id'],
                        $item['quantity'],
                        $item['unit_cost'] ?? 0.00,
                        $item['notes'] ?? null
                    ]);
                }
            }
            
            $this->pdo->commit();
            logLogisticsActivity('requisition_created', 'New requisition created', 'requisition', $requisitionId);
            
            return $requisitionId;
        } catch (Exception $e) {
            $this->pdo->rollBack();
            error_log("Error creating requisition: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Record stock movement
     */
    public function recordStockMovement($itemId, $movementType, $quantity, $data = []) {
        try {
            $this->pdo->beginTransaction();
            
            // Record movement
            $stmt = $this->pdo->prepare("
                INSERT INTO stock_movements (
                    item_id, movement_type, quantity, unit_cost, 
                    reference_type, reference_id, from_location, 
                    to_location, notes, user_id
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $itemId,
                $movementType,
                $quantity,
                $data['unit_cost'] ?? 0.00,
                $data['reference_type'] ?? 'ADJUSTMENT',
                $data['reference_id'] ?? null,
                $data['from_location'] ?? null,
                $data['to_location'] ?? null,
                $data['notes'] ?? null,
                $_SESSION['user_id']
            ]);
            
            // Update item stock
            $multiplier = ($movementType === 'IN') ? 1 : -1;
            $updateStmt = $this->pdo->prepare("
                UPDATE inventory_items 
                SET current_stock = current_stock + (? * ?)
                WHERE id = ?
            ");
            $updateStmt->execute([$quantity, $multiplier, $itemId]);
            
            $this->pdo->commit();
            logLogisticsActivity('stock_movement', 'Stock movement recorded', 'inventory_item', $itemId);
            
            return true;
        } catch (Exception $e) {
            $this->pdo->rollBack();
            error_log("Error recording stock movement: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get inventory items with filters
     */
    public function getInventoryItems($filters = [], $page = 1, $limit = 25) {
        try {
            $where = ["i.status = 'ACTIVE'"];
            $params = [];
            
            // Apply filters
            if (!empty($filters['category_id'])) {
                $where[] = "i.category_id = ?";
                $params[] = $filters['category_id'];
            }
            
            if (!empty($filters['search'])) {
                $where[] = "(i.name LIKE ? OR i.part_number LIKE ?)";
                $searchTerm = '%' . $filters['search'] . '%';
                $params[] = $searchTerm;
                $params[] = $searchTerm;
            }
            
            if (isset($filters['low_stock']) && $filters['low_stock']) {
                $where[] = "i.current_stock <= i.minimum_stock";
            }
            
            $whereClause = implode(' AND ', $where);
            $offset = ($page - 1) * $limit;
            
            // Get total count
            $countStmt = $this->pdo->prepare("
                SELECT COUNT(*) as total
                FROM inventory_items i
                LEFT JOIN inventory_categories c ON i.category_id = c.id
                WHERE $whereClause
            ");
            $countStmt->execute($params);
            $total = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
            
            // Get items
            $stmt = $this->pdo->prepare("
                SELECT 
                    i.id, i.name, i.part_number, i.description,
                    i.unit_of_measure, i.minimum_stock, i.maximum_stock,
                    i.current_stock, i.unit_cost, i.location,
                    c.name as category_name,
                    CASE 
                        WHEN i.current_stock <= i.minimum_stock THEN 'LOW'
                        WHEN i.current_stock >= i.maximum_stock THEN 'HIGH'
                        ELSE 'NORMAL'
                    END as stock_status
                FROM inventory_items i
                LEFT JOIN inventory_categories c ON i.category_id = c.id
                WHERE $whereClause
                ORDER BY i.name ASC
                LIMIT ? OFFSET ?
            ");
            
            $params[] = $limit;
            $params[] = $offset;
            $stmt->execute($params);
            
            return [
                'items' => $stmt->fetchAll(PDO::FETCH_ASSOC),
                'total' => $total,
                'pages' => ceil($total / $limit),
                'current_page' => $page
            ];
        } catch (Exception $e) {
            error_log("Error getting inventory items: " . $e->getMessage());
            return [
                'items' => [],
                'total' => 0,
                'pages' => 0,
                'current_page' => 1
            ];
        }
    }
    
    /**
     * Get inventory categories
     */
    public function getInventoryCategories() {
        try {
            $stmt = $this->pdo->query("
                SELECT id, name, code, description
                FROM inventory_categories
                ORDER BY name ASC
            ");
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error getting inventory categories: " . $e->getMessage());
            return [];
        }
    }
}
?>