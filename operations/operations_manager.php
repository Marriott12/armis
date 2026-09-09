<?php
// Operations database manager class
// This class handles all database operations for the Operations module

class OperationsManager {
    private $db;
    private $userId;
    
    public function __construct($userId = null) {
    require_once dirname(__DIR__) . '/shared/database_connection.php';
    $this->db = getDbConnection();
        $this->userId = $userId ?? ($_SESSION['user_id'] ?? null);
        
        if (!$this->userId) {
            throw new Exception('User ID is required');
        }
    }
    
    /**
     * Get current active missions
     */
    public function getActiveMissions($limit = 5) {
        $query = "SELECT m.*, 
                    l.location_name, 
                    COUNT(DISTINCT r.resource_id) as resource_count, 
                    COUNT(DISTINCT p.svcNo) as personnel_count
                 FROM operations_missions m
                 LEFT JOIN operations_locations l ON m.location_id = l.location_id
                 LEFT JOIN operations_mission_resources r ON m.mission_id = r.mission_id
                 LEFT JOIN operations_mission_personnel p ON m.mission_id = p.mission_id
                 WHERE m.status = 'active'
                 GROUP BY m.mission_id
                 ORDER BY m.startDate DESC
                 LIMIT :limit";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get mission details
     */
    public function getMissionDetails($missionId) {
        $query = "SELECT m.*, l.location_name, l.coordinates, l.country,
                    u.username as created_by_name
                 FROM operations_missions m
                 LEFT JOIN operations_locations l ON m.location_id = l.location_id
                     LEFT JOIN staff u ON m.createdBy = u.svcNo
                 WHERE m.mission_id = :mission_id";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindValue(':mission_id', $missionId, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get mission resources
     */
    public function getMissionResources($missionId) {
        $query = "SELECT r.*, rt.resource_type_name, 
                    u.username as assigned_by_name
                 FROM operations_mission_resources r
                 LEFT JOIN operations_resource_types rt ON r.resource_type_id = rt.resource_type_id
                     LEFT JOIN staff u ON r.assigned_by = u.svcNo
                 WHERE r.mission_id = :mission_id";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindValue(':mission_id', $missionId, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get mission personnel
     */
    public function getMissionPersonnel($missionId) {
        $query = "SELECT p.*, s.fName, s.mName, s.lName, s.rankId, s.svcNo,
                    r.role_name
                 FROM operations_mission_personnel p
                 LEFT JOIN staff s ON p.svcNo = s.svcNo
                     LEFT JOIN operations_personnel_roles r ON p.role_id = r.role_id
                 WHERE p.mission_id = :mission_id";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindValue(':mission_id', $missionId, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Create new mission
     */
    public function createMission($data) {
        $this->db->beginTransaction();
        
        try {
            $query = "INSERT INTO operations_missions (
                        mission_name, mission_code, description, status, 
                        priority, location_id, startDate, endDate,
                        createdBy, createdAt, updatedAt
                    ) VALUES (
                        :mission_name, :mission_code, :description, :status,
                        :priority, :location_id, :startDate, :endDate,
                        :createdBy, NOW(), NOW()
                    )";
            
            $stmt = $this->db->prepare($query);
            $stmt->bindValue(':mission_name', $data['mission_name'], PDO::PARAM_STR);
            $stmt->bindValue(':mission_code', $data['mission_code'], PDO::PARAM_STR);
            $stmt->bindValue(':description', $data['description'], PDO::PARAM_STR);
            $stmt->bindValue(':status', $data['status'], PDO::PARAM_STR);
            $stmt->bindValue(':priority', $data['priority'], PDO::PARAM_STR);
            $stmt->bindValue(':location_id', $data['location_id'], PDO::PARAM_INT);
            $stmt->bindValue(':startDate', $data['startDate'], PDO::PARAM_STR);
            $stmt->bindValue(':endDate', $data['endDate'], PDO::PARAM_STR);
            $stmt->bindValue(':createdBy', $this->userId, PDO::PARAM_INT);
            $stmt->execute();
            
            $missionId = $this->db->lastInsertId();
            
            // Log activity
            $this->logActivity('mission_created', $missionId, "Created new mission: {$data['mission_name']}");
            
            $this->db->commit();
            return [
                'success' => true,
                'mission_id' => $missionId,
                'message' => 'Mission created successfully'
            ];
        } catch (Exception $e) {
            $this->db->rollBack();
            return [
                'success' => false,
                'message' => 'Failed to create mission: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Update mission
     */
    public function updateMission($missionId, $data) {
        $this->db->beginTransaction();
        
        try {
            $query = "UPDATE operations_missions SET
                        mission_name = :mission_name,
                        mission_code = :mission_code,
                        description = :description,
                        status = :status,
                        priority = :priority,
                        location_id = :location_id,
                        startDate = :startDate,
                        endDate = :endDate,
                        updatedAt = NOW()
                    WHERE mission_id = :mission_id";
            
            $stmt = $this->db->prepare($query);
            $stmt->bindValue(':mission_name', $data['mission_name'], PDO::PARAM_STR);
            $stmt->bindValue(':mission_code', $data['mission_code'], PDO::PARAM_STR);
            $stmt->bindValue(':description', $data['description'], PDO::PARAM_STR);
            $stmt->bindValue(':status', $data['status'], PDO::PARAM_STR);
            $stmt->bindValue(':priority', $data['priority'], PDO::PARAM_STR);
            $stmt->bindValue(':location_id', $data['location_id'], PDO::PARAM_INT);
            $stmt->bindValue(':startDate', $data['startDate'], PDO::PARAM_STR);
            $stmt->bindValue(':endDate', $data['endDate'], PDO::PARAM_STR);
            $stmt->bindValue(':mission_id', $missionId, PDO::PARAM_INT);
            $stmt->execute();
            
            // Log activity
            $this->logActivity('mission_updated', $missionId, "Updated mission: {$data['mission_name']}");
            
            $this->db->commit();
            return [
                'success' => true,
                'message' => 'Mission updated successfully'
            ];
        } catch (Exception $e) {
            $this->db->rollBack();
            return [
                'success' => false,
                'message' => 'Failed to update mission: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Delete mission
     */
    public function deleteMission($missionId) {
        $query = "DELETE FROM operations_missions WHERE mission_id = :mission_id";
        $stmt = $this->db->prepare($query);
        $stmt->bindValue(':mission_id', $missionId, PDO::PARAM_INT);
        $stmt->execute();
    }
    
    /**
     * Get active deployments
     */
    public function getActiveDeployments($limit = 5) {
        $query = "SELECT d.*, 
                    l.location_name, l.country,
                    COUNT(DISTINCT p.svcNo) as personnel_count
                 FROM operations_deployments d
                 LEFT JOIN operations_locations l ON d.location_id = l.location_id
                 LEFT JOIN operations_deployment_personnel p ON d.deployment_id = p.deployment_id
                 WHERE d.status = 'active'
                 GROUP BY d.deployment_id
                 ORDER BY d.startDate DESC
                 LIMIT :limit";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get resource allocation summary
     */
    public function getResourceAllocationSummary() {
        $query = "SELECT rt.resource_type_name,
                    COUNT(r.resource_id) as total_resources,
                    SUM(CASE WHEN r.status = 'deployed' THEN 1 ELSE 0 END) as deployed,
                    SUM(CASE WHEN r.status = 'available' THEN 1 ELSE 0 END) as available,
                    SUM(CASE WHEN r.status = 'maintenance' THEN 1 ELSE 0 END) as maintenance
                 FROM operations_resources r
                 LEFT JOIN operations_resource_types rt ON r.resource_type_id = rt.resource_type_id
                 GROUP BY r.resource_type_id, rt.resource_type_name
                 ORDER BY total_resources DESC";
        
        $stmt = $this->db->prepare($query);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get recent status reports
     */
    public function getRecentStatusReports($limit = 5) {
        $query = "SELECT r.*, m.mission_name, 
                    u.username as submitted_by_name
                 FROM operations_status_reports r
                 LEFT JOIN operations_missions m ON r.mission_id = m.mission_id
                     LEFT JOIN staff u ON r.submitted_by = u.svcNo
                 ORDER BY r.report_date DESC
                 LIMIT :limit";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get deployment statistics
     */
    public function getDeploymentStatistics() {
        $query = "SELECT 
                    COUNT(CASE WHEN status = 'active' THEN 1 END) as active_deployments,
                    COUNT(CASE WHEN status = 'planned' THEN 1 END) as planned_deployments,
                    COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed_deployments,
                    COUNT(*) as total_deployments
                 FROM operations_deployments";
        
        $stmt = $this->db->prepare($query);
        $stmt->execute();
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get mission statistics
     */
    public function getMissionStatistics() {
        $query = "SELECT 
                    COUNT(CASE WHEN status = 'active' THEN 1 END) as active_missions,
                    COUNT(CASE WHEN status = 'planned' THEN 1 END) as planned_missions,
                    COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed_missions,
                    COUNT(*) as total_missions
                 FROM operations_missions";
        
        $stmt = $this->db->prepare($query);
        $stmt->execute();
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get resource statistics
     */
    public function getResourceStatistics() {
        $query = "SELECT 
                    COUNT(CASE WHEN status = 'available' THEN 1 END) as available_resources,
                    COUNT(CASE WHEN status = 'deployed' THEN 1 END) as deployed_resources,
                    COUNT(CASE WHEN status = 'maintenance' THEN 1 END) as maintenance_resources,
                    COUNT(*) as total_resources
                 FROM operations_resources";
        
        $stmt = $this->db->prepare($query);
        $stmt->execute();
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Log activity in the operations module
     */
    private function logActivity($actionType, $entityId, $description) {
        $query = "INSERT INTO operations_activity_log (
                    user_id, action_type, entity_id, description, createdAt
                ) VALUES (
                    :user_id, :action_type, :entity_id, :description, NOW()
                )";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindValue(':user_id', $this->userId, PDO::PARAM_INT);
        $stmt->bindValue(':action_type', $actionType, PDO::PARAM_STR);
        $stmt->bindValue(':entity_id', $entityId, PDO::PARAM_INT);
        $stmt->bindValue(':description', $description, PDO::PARAM_STR);
        $stmt->execute();
    }
    
    /**
     * Get available locations
     */
    public function getLocations() {
        $query = "SELECT * FROM operations_locations ORDER BY location_name";
        $stmt = $this->db->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get personnel roles
     */
    public function getPersonnelRoles() {
        $query = "SELECT * FROM operations_personnel_roles ORDER BY role_name";
        $stmt = $this->db->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get resource types
     */
    public function getResourceTypes() {
        $query = "SELECT * FROM operations_resource_types ORDER BY resource_type_name";
        $stmt = $this->db->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get all missions
     */
    public function getAllMissions() {
        $query = "SELECT * FROM operations_missions ORDER BY startDate DESC";
        $stmt = $this->db->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get all deployments
     */
    public function getAllDeployments() {
        $query = "SELECT * FROM operations_deployments ORDER BY startDate DESC";
        $stmt = $this->db->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get available staff for assignment (not assigned to active missions/deployments)
     */
    public function getAvailableStaff($excludeMissionId = null) {
        $query = "SELECT s.* FROM staff s
            WHERE s.svcNo NOT IN (
                SELECT svcNo FROM operations_mission_personnel
                WHERE status IN ('assigned','active')
                " . ($excludeMissionId ? "AND mission_id != :excludeMissionId" : "") . "
            )";
        $stmt = $this->db->prepare($query);
        if ($excludeMissionId) {
            $stmt->bindValue(':excludeMissionId', $excludeMissionId, PDO::PARAM_INT);
        }
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Assign staff to mission
     */
    public function assignStaffToMission($missionId, $staffId, $roleId = null, $startDate = null, $endDate = null) {
        // Check for double-booking
        $query = "SELECT COUNT(*) FROM operations_mission_personnel
                  WHERE svcNo = :staffId AND status IN ('assigned','active')
                  AND ((startDate <= :endDate AND endDate >= :startDate) OR (startDate IS NULL OR endDate IS NULL))";
        $stmt = $this->db->prepare($query);
        $stmt->bindValue(':staffId', $staffId, PDO::PARAM_STR);
        $stmt->bindValue(':startDate', $startDate, PDO::PARAM_STR);
        $stmt->bindValue(':endDate', $endDate, PDO::PARAM_STR);
        $stmt->execute();
        if ($stmt->fetchColumn() > 0) {
            throw new Exception('Staff member is already assigned to another active mission during this period.');
        }

        $query = "INSERT INTO operations_mission_personnel
                  (mission_id, svcNo, role_id, startDate, endDate, status)
                  VALUES (:missionId, :staffId, :roleId, :startDate, :endDate, 'assigned')";
        $stmt = $this->db->prepare($query);
        $stmt->bindValue(':missionId', $missionId, PDO::PARAM_INT);
        $stmt->bindValue(':staffId', $staffId, PDO::PARAM_STR);
        $stmt->bindValue(':roleId', $roleId, PDO::PARAM_STR);
        $stmt->bindValue(':startDate', $startDate, PDO::PARAM_STR);
        $stmt->bindValue(':endDate', $endDate, PDO::PARAM_STR);
        $stmt->execute();
    }

    /**
     * Remove staff from mission
     */
    public function removeStaffFromMission($personnelId) {
        $query = "DELETE FROM operations_mission_personnel WHERE personnel_id = :personnelId";
        $stmt = $this->db->prepare($query);
        $stmt->bindValue(':personnelId', $personnelId, PDO::PARAM_INT);
        $stmt->execute();
    }

    /**
     * Get all current mission personnel assignments
     */
    public function getCurrentAssignments() {
        $query = "SELECT p.*, m.mission_name, CONCAT_WS(' ', s.fName, s.mName, s.lName) AS staff_name
                  FROM operations_mission_personnel p
              JOIN operations_missions m ON p.mission_id = m.mission_id
              JOIN staff s ON p.svcNo = s.svcNo
                  ORDER BY p.startDate DESC";
        $stmt = $this->db->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get all resources
     */
    public function getAllResources() {
        $query = "SELECT * FROM operations_resources ORDER BY name ASC";
        $stmt = $this->db->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getResourceDetails($resourceId) {
        $stmt = $this->db->prepare("SELECT resource_id, name AS resource_name, type AS resource_type, quantity, status, description FROM operations_resources WHERE resource_id = :resource_id");
        $stmt->execute(['resource_id' => $resourceId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Add a new resource
     */
    public function addResource($name, $type, $quantity, $status) {
        $query = "INSERT INTO operations_resources (name, type, quantity, status) VALUES (:name, :type, :quantity, :status)";
        $stmt = $this->db->prepare($query);
        $stmt->bindValue(':name', $name, PDO::PARAM_STR);
        $stmt->bindValue(':type', $type, PDO::PARAM_STR);
        $stmt->bindValue(':quantity', $quantity, PDO::PARAM_INT);
        $stmt->bindValue(':status', $status, PDO::PARAM_STR);
        $stmt->execute();
    }

    /**
     * Update resource
     */
    public function updateResource($id, $name, $type, $quantity, $status) {
        $query = "UPDATE operations_resources SET name = :name, type = :type, quantity = :quantity, status = :status WHERE resource_id = :id";
        $stmt = $this->db->prepare($query);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->bindValue(':name', $name, PDO::PARAM_STR);
        $stmt->bindValue(':type', $type, PDO::PARAM_STR);
        $stmt->bindValue(':quantity', $quantity, PDO::PARAM_INT);
        $stmt->bindValue(':status', $status, PDO::PARAM_STR);
        $stmt->execute();
    }

    /**
     * Delete resource
     */
    public function deleteResource($id) {
        $query = "DELETE FROM operations_resources WHERE resource_id = :id";
        $stmt = $this->db->prepare($query);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
    }

    /**
     * Get mission and resource analytics summary
     */
    public function getAnalyticsSummary() {
        $summary = [];
        // Mission count
        $stmt = $this->db->query("SELECT COUNT(*) AS total_missions FROM operations_missions");
        $summary['total_missions'] = $stmt->fetchColumn();
        // Active missions
        $stmt = $this->db->query("SELECT COUNT(*) AS active_missions FROM operations_missions WHERE status = 'active'");
        $summary['active_missions'] = $stmt->fetchColumn();
        // Personnel assigned
        $stmt = $this->db->query("SELECT COUNT(*) AS personnel_assigned FROM operations_mission_personnel WHERE status IN ('assigned','active')");
        $summary['personnel_assigned'] = $stmt->fetchColumn();
        // Resource count
        $stmt = $this->db->query("SELECT COUNT(*) AS total_resources FROM operations_resources");
        $summary['total_resources'] = $stmt->fetchColumn();
        // Resource status breakdown
        $stmt = $this->db->query("SELECT status, COUNT(*) AS count FROM operations_resources GROUP BY status");
        $summary['resource_status'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return $summary;
    }

    /**
     * Get all notifications for a user.
     *
     * FIX: this used to query `operations_notifications`, a table that
     * was never actually created in any migration — every call here
     * silently returned nothing forever. Delegates to the shared,
     * real notifications system instead (see
     * shared/notifications_helper.php and
     * database/migrations/2026_08_27_add_notifications_table.sql),
     * consistent with how admin_branch's events (staff created,
     * deleted, medal assigned) now notify through the same table.
     *
     * $userId is accepted as-is for backward compatibility with
     * existing callers, but note it's actually svcNo (a string) in
     * practice — see notifications_helper.php's own doc comment for
     * why (staff has no numeric id column).
     */
    public function getUserNotifications($userId) {
        require_once dirname(__DIR__) . '/shared/notifications_helper.php';
        return getUserNotifications((string) $userId);
    }

    /**
     * Mark notification as read.
     *
     * FIX: same phantom-table issue as getUserNotifications() above.
     * Note the new shared markNotificationRead() also requires the
     * owning user's id, to scope the update to their own notifications
     * — callers of this legacy wrapper that don't have that in scope
     * should call the shared function directly instead.
     */
    public function markNotificationRead($notificationId, $userId = null) {
        require_once dirname(__DIR__) . '/shared/notifications_helper.php';
        if ($userId === null) {
            $userId = $_SESSION['user_id'] ?? null;
        }
        if ($userId === null) {
            return false;
        }
        return markNotificationRead((int) $notificationId, (string) $userId);
    }

    /**
     * Advanced search for resources
     */
    public function searchResources($name = '', $type = '', $status = '') {
        $query = "SELECT * FROM operations_resources WHERE 1=1";
        $params = [];
        if ($name) {
            $query .= " AND name LIKE :name";
            $params[':name'] = "%$name%";
        }
        if ($type) {
            $query .= " AND type LIKE :type";
            $params[':type'] = "%$type%";
        }
        if ($status) {
            $query .= " AND status LIKE :status";
            $params[':status'] = "%$status%";
        }
        $stmt = $this->db->prepare($query);
        foreach ($params as $key => $val) {
            $stmt->bindValue($key, $val, PDO::PARAM_STR);
        }
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Advanced search for missions
     */
    public function searchMissions($name = '', $status = '', $priority = '') {
        $query = "SELECT * FROM operations_missions WHERE 1=1";
        $params = [];
        if ($name) {
            $query .= " AND mission_name LIKE :name";
            $params[':name'] = "%$name%";
        }
        if ($status) {
            $query .= " AND status LIKE :status";
            $params[':status'] = "%$status%";
        }
        if ($priority) {
            $query .= " AND priority LIKE :priority";
            $params[':priority'] = "%$priority%";
        }
        $stmt = $this->db->prepare($query);
        foreach ($params as $key => $val) {
            $stmt->bindValue($key, $val, PDO::PARAM_STR);
        }
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Advanced search for deployments
     */
    public function searchDeployments($name = '', $status = '', $location = '') {
        $query = "SELECT * FROM operations_deployments WHERE 1=1";
        $params = [];
        if ($name) {
            $query .= " AND name LIKE :name";
            $params[':name'] = "%$name%";
        }
        if ($status) {
            $query .= " AND status LIKE :status";
            $params[':status'] = "%$status%";
        }
        if ($location) {
            $query .= " AND location LIKE :location";
            $params[':location'] = "%$location%";
        }
        $stmt = $this->db->prepare($query);
        foreach ($params as $key => $val) {
            $stmt->bindValue($key, $val, PDO::PARAM_STR);
        }
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Advanced search for field operations
     */
    public function searchFieldOperations($name = '', $status = '', $location = '') {
        $query = "SELECT * FROM operations_field WHERE 1=1";
        $params = [];
        if ($name) {
            $query .= " AND name LIKE :name";
            $params[':name'] = "%$name%";
        }
        if ($status) {
            $query .= " AND status LIKE :status";
            $params[':status'] = "%$status%";
        }
        if ($location) {
            $query .= " AND location LIKE :location";
            $params[':location'] = "%$location%";
        }
        $stmt = $this->db->prepare($query);
        foreach ($params as $key => $val) {
            $stmt->bindValue($key, $val, PDO::PARAM_STR);
        }
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get all field operations
     */
    public function getAllFieldOperations() {
        $query = "SELECT id AS field_id, name AS field_name, '' AS field_type, status, location, startDate, endDate, description FROM operations_field ORDER BY startDate DESC";
        $stmt = $this->db->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getFieldDetails($fieldId) {
        $stmt = $this->db->prepare("SELECT id AS field_id, name AS field_name, '' AS field_type, status, location, startDate, endDate, description FROM operations_field WHERE id = :field_id");
        $stmt->execute(['field_id' => $fieldId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function addField(array $data) {
        return $this->createFieldOperation($data['field_name'], $data['status'], $data['location'], $data['startDate'] ?? null, $data['endDate'] ?? null);
    }

    public function updateField(array $data) {
        return $this->updateFieldOperation($data['field_id'], $data['field_name'], $data['status'], $data['location'], $data['startDate'] ?? null, $data['endDate'] ?? null);
    }

    public function deleteField($fieldId) {
        return $this->deleteFieldOperation($fieldId);
    }

    /**
     * Create a new field operation
     */
    public function createFieldOperation($name, $status, $location, $startDate, $endDate) {
        $query = "INSERT INTO operations_field (name, status, location, startDate, endDate) VALUES (:name, :status, :location, :startDate, :endDate)";
        $stmt = $this->db->prepare($query);
        $stmt->bindValue(':name', $name, PDO::PARAM_STR);
        $stmt->bindValue(':status', $status, PDO::PARAM_STR);
        $stmt->bindValue(':location', $location, PDO::PARAM_STR);
        $stmt->bindValue(':startDate', $startDate, PDO::PARAM_STR);
        $stmt->bindValue(':endDate', $endDate, PDO::PARAM_STR);
        $stmt->execute();
    }

    /**
     * Update field operation
     */
    public function updateFieldOperation($id, $name, $status, $location, $startDate, $endDate) {
        $query = "UPDATE operations_field SET name = :name, status = :status, location = :location, startDate = :startDate, endDate = :endDate WHERE id = :id";
        $stmt = $this->db->prepare($query);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->bindValue(':name', $name, PDO::PARAM_STR);
        $stmt->bindValue(':status', $status, PDO::PARAM_STR);
        $stmt->bindValue(':location', $location, PDO::PARAM_STR);
        $stmt->bindValue(':startDate', $startDate, PDO::PARAM_STR);
        $stmt->bindValue(':endDate', $endDate, PDO::PARAM_STR);
        $stmt->execute();
    }

    /**
     * Delete field operation
     */
    public function deleteFieldOperation($id) {
        $query = "DELETE FROM operations_field WHERE id = :id";
        $stmt = $this->db->prepare($query);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
    }
}
