<?php
/**
 * ARMIS Workflow Service
 * Core service class for workflow and task management
 */

class WorkflowService {
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    /**
     * Get workflow summary statistics
     */
    public function getWorkflowSummary() {
        try {
            $stmt = $this->pdo->query("
                SELECT 
                    COUNT(CASE WHEN status = 'ACTIVE' THEN 1 END) as active_workflows,
                    COUNT(CASE WHEN status = 'COMPLETED' AND DATE(completed_at) = CURDATE() THEN 1 END) as completed_today,
                    COUNT(CASE WHEN status = 'CANCELLED' THEN 1 END) as cancelled_workflows,
                    AVG(CASE WHEN status = 'COMPLETED' THEN TIMESTAMPDIFF(HOUR, started_at, completed_at) END) as avg_completion_hours
                FROM workflow_instances
                WHERE started_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            ");
            
            return $stmt->fetch(PDO::FETCH_ASSOC) ?: [
                'active_workflows' => 0,
                'completed_today' => 0,
                'cancelled_workflows' => 0,
                'avg_completion_hours' => 0
            ];
        } catch (Exception $e) {
            error_log("Error getting workflow summary: " . $e->getMessage());
            return [
                'active_workflows' => 0,
                'completed_today' => 0,
                'cancelled_workflows' => 0,
                'avg_completion_hours' => 0
            ];
        }
    }
    
    /**
     * Get active workflows
     */
    public function getActiveWorkflows($limit = 10) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT 
                    wi.id,
                    wi.instance_name,
                    wi.current_step,
                    wi.priority,
                    wi.started_at,
                    CONCAT(u.fname, ' ', u.lname) as initiator_name,
                    wt.name as template_name
                FROM workflow_instances wi
                LEFT JOIN users u ON wi.initiated_by = u.id
                LEFT JOIN workflow_templates wt ON wi.template_id = wt.id
                WHERE wi.status = 'ACTIVE'
                ORDER BY wi.started_at DESC
                LIMIT ?
            ");
            
            $stmt->execute([$limit]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error getting active workflows: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get pending tasks for a user
     */
    public function getPendingTasks($userId, $limit = 10) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT 
                    wsi.id,
                    wsi.workflow_instance_id as workflow_id,
                    wsi.step_number,
                    wsi.assigned_at,
                    wsi.status,
                    wi.instance_name as workflow_name,
                    wi.priority,
                    ws.step_name,
                    ws.escalation_hours,
                    ws.instructions
                FROM workflow_step_instances wsi
                JOIN workflow_instances wi ON wsi.workflow_instance_id = wi.id
                JOIN workflow_steps ws ON wsi.step_id = ws.id
                WHERE wsi.assignee_id = ? 
                AND wsi.status = 'PENDING'
                AND wi.status = 'ACTIVE'
                ORDER BY wi.priority DESC, wsi.assigned_at ASC
                LIMIT ?
            ");
            
            $stmt->execute([$userId, $limit]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error getting pending tasks: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get workflow templates
     */
    public function getWorkflowTemplates($activeOnly = true) {
        try {
            $whereClause = $activeOnly ? "WHERE is_active = 1" : "";
            
            $stmt = $this->pdo->query("
                SELECT 
                    wt.id,
                    wt.name,
                    wt.description,
                    wt.category,
                    wt.version,
                    wt.is_active,
                    COUNT(ws.id) as step_count,
                    CONCAT(u.fname, ' ', u.lname) as created_by_name,
                    wt.created_at
                FROM workflow_templates wt
                LEFT JOIN workflow_steps ws ON wt.id = ws.template_id
                LEFT JOIN users u ON wt.created_by = u.id
                $whereClause
                GROUP BY wt.id
                ORDER BY wt.category, wt.name
            ");
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error getting workflow templates: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get recent workflow activities
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
                        WHEN action = 'workflow_started' THEN 'Workflow started'
                        WHEN action = 'workflow_completed' THEN 'Workflow completed'
                        WHEN action = 'task_approved' THEN 'Task approved'
                        WHEN action = 'task_rejected' THEN 'Task rejected'
                        WHEN action = 'workflow_escalated' THEN 'Workflow escalated'
                        ELSE action
                    END as description
                FROM audit_logs al
                LEFT JOIN users u ON al.user_id = u.id
                WHERE al.module = 'workflow'
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
     * Create new workflow template
     */
    public function createWorkflowTemplate($data) {
        try {
            $this->pdo->beginTransaction();
            
            // Create template
            $stmt = $this->pdo->prepare("
                INSERT INTO workflow_templates (
                    name, description, category, version, 
                    is_active, created_by
                ) VALUES (?, ?, ?, ?, ?, ?)
            ");
            
            $result = $stmt->execute([
                $data['name'],
                $data['description'] ?? null,
                $data['category'] ?? 'General',
                $data['version'] ?? 1,
                $data['is_active'] ?? 1,
                $_SESSION['user_id']
            ]);
            
            if (!$result) {
                throw new Exception('Failed to create workflow template');
            }
            
            $templateId = $this->pdo->lastInsertId();
            
            // Add workflow steps
            if (!empty($data['steps'])) {
                $stepStmt = $this->pdo->prepare("
                    INSERT INTO workflow_steps (
                        template_id, step_number, step_name, step_type,
                        assignee_type, assignee_value, is_required,
                        auto_approve_hours, escalation_hours, escalation_to,
                        instructions
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                
                foreach ($data['steps'] as $index => $step) {
                    $stepStmt->execute([
                        $templateId,
                        $index + 1,
                        $step['step_name'],
                        $step['step_type'] ?? 'APPROVAL',
                        $step['assignee_type'] ?? 'USER',
                        $step['assignee_value'],
                        $step['is_required'] ?? 1,
                        $step['auto_approve_hours'] ?? 0,
                        $step['escalation_hours'] ?? 72,
                        $step['escalation_to'] ?? null,
                        $step['instructions'] ?? null
                    ]);
                }
            }
            
            $this->pdo->commit();
            logWorkflowActivity('workflow_template_created', 'New workflow template created', 'workflow_template', $templateId);
            
            return $templateId;
        } catch (Exception $e) {
            $this->pdo->rollBack();
            error_log("Error creating workflow template: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Start new workflow instance
     */
    public function startWorkflow($templateId, $instanceName, $data = []) {
        try {
            $this->pdo->beginTransaction();
            
            // Get template
            $template = $this->getWorkflowTemplate($templateId);
            if (!$template) {
                throw new Exception('Workflow template not found');
            }
            
            // Create workflow instance
            $stmt = $this->pdo->prepare("
                INSERT INTO workflow_instances (
                    template_id, instance_name, reference_type, reference_id,
                    initiated_by, current_step, status, priority, data
                ) VALUES (?, ?, ?, ?, ?, 1, 'ACTIVE', ?, ?)
            ");
            
            $result = $stmt->execute([
                $templateId,
                $instanceName,
                $data['reference_type'] ?? null,
                $data['reference_id'] ?? null,
                $_SESSION['user_id'],
                $data['priority'] ?? 'NORMAL',
                json_encode($data['workflow_data'] ?? [])
            ]);
            
            if (!$result) {
                throw new Exception('Failed to create workflow instance');
            }
            
            $workflowId = $this->pdo->lastInsertId();
            
            // Create step instances
            $steps = $this->getWorkflowSteps($templateId);
            foreach ($steps as $step) {
                $assigneeId = $this->resolveAssignee($step);
                
                $stepStmt = $this->pdo->prepare("
                    INSERT INTO workflow_step_instances (
                        workflow_instance_id, step_id, step_number, 
                        assignee_id, status, assigned_at
                    ) VALUES (?, ?, ?, ?, ?, NOW())
                ");
                
                $stepStatus = ($step['step_number'] == 1) ? 'PENDING' : 'PENDING';
                
                $stepStmt->execute([
                    $workflowId,
                    $step['id'],
                    $step['step_number'],
                    $assigneeId,
                    $stepStatus
                ]);
            }
            
            $this->pdo->commit();
            logWorkflowActivity('workflow_started', 'New workflow started', 'workflow_instance', $workflowId);
            
            return $workflowId;
        } catch (Exception $e) {
            $this->pdo->rollBack();
            error_log("Error starting workflow: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Complete workflow step
     */
    public function completeStep($stepInstanceId, $action, $comments = null) {
        try {
            $this->pdo->beginTransaction();
            
            // Get step instance
            $stepInstance = $this->getStepInstance($stepInstanceId);
            if (!$stepInstance) {
                throw new Exception('Step instance not found');
            }
            
            // Update step instance
            $stmt = $this->pdo->prepare("
                UPDATE workflow_step_instances 
                SET status = 'COMPLETED', completed_at = NOW(), 
                    action_taken = ?, comments = ?
                WHERE id = ?
            ");
            
            $stmt->execute([$action, $comments, $stepInstanceId]);
            
            // Check if workflow should advance
            if ($action === 'APPROVED') {
                $this->advanceWorkflow($stepInstance['workflow_instance_id']);
            } elseif ($action === 'REJECTED') {
                $this->rejectWorkflow($stepInstance['workflow_instance_id'], $comments);
            }
            
            $this->pdo->commit();
            logWorkflowActivity('step_completed', "Workflow step {$action}", 'workflow_step', $stepInstanceId);
            
            return true;
        } catch (Exception $e) {
            $this->pdo->rollBack();
            error_log("Error completing workflow step: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get workflow template by ID
     */
    private function getWorkflowTemplate($templateId) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT * FROM workflow_templates WHERE id = ? AND is_active = 1
            ");
            $stmt->execute([$templateId]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error getting workflow template: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Get workflow steps
     */
    private function getWorkflowSteps($templateId) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT * FROM workflow_steps 
                WHERE template_id = ? 
                ORDER BY step_number ASC
            ");
            $stmt->execute([$templateId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error getting workflow steps: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Resolve assignee based on assignee type and value
     */
    private function resolveAssignee($step) {
        try {
            switch ($step['assignee_type']) {
                case 'USER':
                    return (int)$step['assignee_value'];
                    
                case 'ROLE':
                    // Find a user with this role
                    $stmt = $this->pdo->prepare("
                        SELECT id FROM users 
                        WHERE role = ? AND status = 'ACTIVE' 
                        ORDER BY RAND() LIMIT 1
                    ");
                    $stmt->execute([$step['assignee_value']]);
                    $user = $stmt->fetch(PDO::FETCH_ASSOC);
                    return $user ? $user['id'] : null;
                    
                case 'UNIT':
                    // Find unit commander or first user in unit
                    $stmt = $this->pdo->prepare("
                        SELECT id FROM users 
                        WHERE unit_id = ? AND status = 'ACTIVE' 
                        ORDER BY rank_id DESC LIMIT 1
                    ");
                    $stmt->execute([$step['assignee_value']]);
                    $user = $stmt->fetch(PDO::FETCH_ASSOC);
                    return $user ? $user['id'] : null;
                    
                default:
                    return null;
            }
        } catch (Exception $e) {
            error_log("Error resolving assignee: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Get step instance
     */
    private function getStepInstance($stepInstanceId) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT * FROM workflow_step_instances WHERE id = ?
            ");
            $stmt->execute([$stepInstanceId]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error getting step instance: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Advance workflow to next step
     */
    private function advanceWorkflow($workflowId) {
        try {
            // Get current step
            $stmt = $this->pdo->prepare("
                SELECT current_step FROM workflow_instances WHERE id = ?
            ");
            $stmt->execute([$workflowId]);
            $currentStep = $stmt->fetchColumn();
            
            // Check if there are more steps
            $stmt = $this->pdo->prepare("
                SELECT COUNT(*) FROM workflow_step_instances 
                WHERE workflow_instance_id = ?
            ");
            $stmt->execute([$workflowId]);
            $totalSteps = $stmt->fetchColumn();
            
            if ($currentStep < $totalSteps) {
                // Advance to next step
                $nextStep = $currentStep + 1;
                $stmt = $this->pdo->prepare("
                    UPDATE workflow_instances 
                    SET current_step = ? 
                    WHERE id = ?
                ");
                $stmt->execute([$nextStep, $workflowId]);
                
                // Activate next step
                $stmt = $this->pdo->prepare("
                    UPDATE workflow_step_instances 
                    SET status = 'PENDING', assigned_at = NOW()
                    WHERE workflow_instance_id = ? AND step_number = ?
                ");
                $stmt->execute([$workflowId, $nextStep]);
            } else {
                // Complete workflow
                $stmt = $this->pdo->prepare("
                    UPDATE workflow_instances 
                    SET status = 'COMPLETED', completed_at = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([$workflowId]);
                
                logWorkflowActivity('workflow_completed', 'Workflow completed', 'workflow_instance', $workflowId);
            }
            
            return true;
        } catch (Exception $e) {
            error_log("Error advancing workflow: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Reject workflow
     */
    private function rejectWorkflow($workflowId, $reason = null) {
        try {
            $stmt = $this->pdo->prepare("
                UPDATE workflow_instances 
                SET status = 'CANCELLED', completed_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$workflowId]);
            
            logWorkflowActivity('workflow_rejected', 'Workflow rejected: ' . $reason, 'workflow_instance', $workflowId);
            
            return true;
        } catch (Exception $e) {
            error_log("Error rejecting workflow: " . $e->getMessage());
            return false;
        }
    }
}
?>