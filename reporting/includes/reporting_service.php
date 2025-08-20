<?php
/**
 * ARMIS Reporting Service
 * Core service class for reporting and analytics
 */

class ReportingService {
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    /**
     * Get all report templates
     */
    public function getReportTemplates() {
        try {
            $stmt = $this->pdo->query("
                SELECT 
                    rt.id,
                    rt.name,
                    rt.description,
                    rt.category,
                    rt.output_format,
                    rt.is_public,
                    rt.created_at,
                    CONCAT(u.fname, ' ', u.lname) as created_by_name
                FROM report_templates rt
                LEFT JOIN users u ON rt.created_by = u.id
                ORDER BY rt.category, rt.name
            ");
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error getting report templates: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get scheduled reports
     */
    public function getScheduledReports() {
        try {
            $stmt = $this->pdo->query("
                SELECT 
                    sr.id,
                    sr.name,
                    sr.schedule_cron,
                    sr.output_format,
                    sr.is_active,
                    sr.last_run_at,
                    sr.next_run_at,
                    sr.created_at,
                    rt.name as template_name,
                    CONCAT(u.fname, ' ', u.lname) as created_by_name
                FROM scheduled_reports sr
                JOIN report_templates rt ON sr.template_id = rt.id
                LEFT JOIN users u ON sr.created_by = u.id
                ORDER BY sr.next_run_at ASC
            ");
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error getting scheduled reports: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get recent reports (simulated - would come from report execution log table)
     */
    public function getRecentReports($limit = 10) {
        try {
            // Simulate recent reports data
            return [
                [
                    'id' => 1,
                    'report_name' => 'Personnel Summary Report',
                    'generated_at' => date('Y-m-d H:i:s', strtotime('-2 hours')),
                    'file_size' => 2048576,
                    'format' => 'PDF'
                ],
                [
                    'id' => 2,
                    'report_name' => 'Inventory Status Report',
                    'generated_at' => date('Y-m-d H:i:s', strtotime('-4 hours')),
                    'file_size' => 1024768,
                    'format' => 'EXCEL'
                ],
                [
                    'id' => 3,
                    'report_name' => 'System Activity Log',
                    'generated_at' => date('Y-m-d H:i:s', strtotime('-1 day')),
                    'file_size' => 512384,
                    'format' => 'CSV'
                ]
            ];
        } catch (Exception $e) {
            error_log("Error getting recent reports: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get analytics summary
     */
    public function getAnalyticsSummary() {
        try {
            $stmt = $this->pdo->query("
                SELECT 
                    COUNT(DISTINCT am.id) as total_data_points,
                    COUNT(DISTINCT DATE(am.timestamp)) as days_with_data,
                    COUNT(DISTINCT rt.id) as total_templates,
                    COUNT(DISTINCT sr.id) as total_scheduled
                FROM analytics_metrics am
                CROSS JOIN report_templates rt
                CROSS JOIN scheduled_reports sr
                WHERE am.timestamp >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            ");
            
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            return [
                'total_data_points' => $result['total_data_points'] ?? 0,
                'days_with_data' => $result['days_with_data'] ?? 0,
                'total_templates' => $result['total_templates'] ?? 0,
                'total_scheduled' => $result['total_scheduled'] ?? 0
            ];
        } catch (Exception $e) {
            error_log("Error getting analytics summary: " . $e->getMessage());
            return [
                'total_data_points' => 0,
                'days_with_data' => 0,
                'total_templates' => 0,
                'total_scheduled' => 0
            ];
        }
    }
    
    /**
     * Create new report template
     */
    public function createReportTemplate($data) {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO report_templates (
                    name, description, category, sql_query, parameters,
                    output_format, chart_config, is_public, created_by
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $result = $stmt->execute([
                $data['name'],
                $data['description'] ?? null,
                $data['category'] ?? 'General',
                $data['sql_query'],
                json_encode($data['parameters'] ?? []),
                $data['output_format'] ?? 'TABLE',
                json_encode($data['chart_config'] ?? []),
                $data['is_public'] ?? 0,
                $_SESSION['user_id']
            ]);
            
            if ($result) {
                $templateId = $this->pdo->lastInsertId();
                $this->logActivity('report_template_created', 'Report template created', 'report_template', $templateId);
                return $templateId;
            }
            
            return false;
        } catch (Exception $e) {
            error_log("Error creating report template: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Generate report from template
     */
    public function generateReport($templateId, $parameters = []) {
        try {
            // Get template
            $stmt = $this->pdo->prepare("
                SELECT * FROM report_templates WHERE id = ?
            ");
            $stmt->execute([$templateId]);
            $template = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$template) {
                throw new Exception('Report template not found');
            }
            
            // Execute report query
            $sql = $template['sql_query'];
            $reportData = $this->executeReportQuery($sql, $parameters);
            
            // Format output based on template configuration
            $output = $this->formatReportOutput($reportData, $template);
            
            $this->logActivity('report_generated', 'Report generated from template', 'report_template', $templateId);
            
            return [
                'success' => true,
                'data' => $reportData,
                'formatted_output' => $output,
                'template' => $template
            ];
        } catch (Exception $e) {
            error_log("Error generating report: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Create scheduled report
     */
    public function scheduleReport($templateId, $scheduleData) {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO scheduled_reports (
                    template_id, name, schedule_cron, recipients,
                    parameters, output_format, is_active, created_by
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $result = $stmt->execute([
                $templateId,
                $scheduleData['name'],
                $scheduleData['schedule_cron'],
                json_encode($scheduleData['recipients'] ?? []),
                json_encode($scheduleData['parameters'] ?? []),
                $scheduleData['output_format'] ?? 'PDF',
                $scheduleData['is_active'] ?? 1,
                $_SESSION['user_id']
            ]);
            
            if ($result) {
                $scheduleId = $this->pdo->lastInsertId();
                
                // Calculate next run time
                $nextRun = $this->calculateNextRunTime($scheduleData['schedule_cron']);
                if ($nextRun) {
                    $updateStmt = $this->pdo->prepare("
                        UPDATE scheduled_reports 
                        SET next_run_at = ?
                        WHERE id = ?
                    ");
                    $updateStmt->execute([$nextRun, $scheduleId]);
                }
                
                $this->logActivity('report_scheduled', 'Report schedule created', 'scheduled_report', $scheduleId);
                return $scheduleId;
            }
            
            return false;
        } catch (Exception $e) {
            error_log("Error scheduling report: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Generate quick reports
     */
    public function generateQuickReport($reportType) {
        try {
            switch ($reportType) {
                case 'personnel_summary':
                    return $this->generatePersonnelSummaryReport();
                case 'activity_log':
                    return $this->generateActivityLogReport();
                case 'system_health':
                    return $this->generateSystemHealthReport();
                case 'inventory_status':
                    return $this->generateInventoryStatusReport();
                case 'workflow_performance':
                    return $this->generateWorkflowPerformanceReport();
                default:
                    throw new Exception('Unknown report type');
            }
        } catch (Exception $e) {
            error_log("Error generating quick report: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Record analytics metric
     */
    public function recordMetric($metricName, $value, $dimensions = []) {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO analytics_metrics (
                    metric_name, metric_value, dimensions, timestamp
                ) VALUES (?, ?, ?, NOW())
            ");
            
            return $stmt->execute([
                $metricName,
                $value,
                json_encode($dimensions)
            ]);
        } catch (Exception $e) {
            error_log("Error recording metric: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Helper methods
     */
    private function executeReportQuery($sql, $parameters = []) {
        // Security: Validate SQL query (basic validation)
        $allowedTables = ['users', 'audit_logs', 'inventory_items', 'supply_requisitions', 'workflow_instances'];
        $sql = strtolower($sql);
        
        // Check for dangerous keywords
        $dangerousKeywords = ['drop', 'delete', 'update', 'insert', 'create', 'alter'];
        foreach ($dangerousKeywords as $keyword) {
            if (strpos($sql, $keyword) !== false) {
                throw new Exception('SQL query contains forbidden operations');
            }
        }
        
        // Execute query
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($parameters);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    private function formatReportOutput($data, $template) {
        switch ($template['output_format']) {
            case 'TABLE':
                return $this->formatAsTable($data);
            case 'CHART':
                return $this->formatAsChart($data, $template['chart_config']);
            case 'DASHBOARD':
                return $this->formatAsDashboard($data, $template);
            default:
                return $data;
        }
    }
    
    private function formatAsTable($data) {
        if (empty($data)) return '<p>No data available</p>';
        
        $html = '<table class="table table-striped table-responsive">';
        
        // Headers
        $html .= '<thead><tr>';
        foreach (array_keys($data[0]) as $header) {
            $html .= '<th>' . htmlspecialchars(ucwords(str_replace('_', ' ', $header))) . '</th>';
        }
        $html .= '</tr></thead>';
        
        // Data
        $html .= '<tbody>';
        foreach ($data as $row) {
            $html .= '<tr>';
            foreach ($row as $value) {
                $html .= '<td>' . htmlspecialchars($value ?? '') . '</td>';
            }
            $html .= '</tr>';
        }
        $html .= '</tbody></table>';
        
        return $html;
    }
    
    private function formatAsChart($data, $chartConfig) {
        // Return chart configuration for JavaScript rendering
        return [
            'type' => 'chart',
            'data' => $data,
            'config' => json_decode($chartConfig, true)
        ];
    }
    
    private function formatAsDashboard($data, $template) {
        // Return dashboard configuration
        return [
            'type' => 'dashboard',
            'data' => $data,
            'template' => $template
        ];
    }
    
    private function calculateNextRunTime($cronSchedule) {
        // Simple cron calculation - in production, use a proper cron library
        // For now, just add 1 day for daily reports
        if (strpos($cronSchedule, 'daily') !== false) {
            return date('Y-m-d H:i:s', strtotime('+1 day'));
        }
        
        return date('Y-m-d H:i:s', strtotime('+1 hour'));
    }
    
    // Quick report generators
    private function generatePersonnelSummaryReport() {
        $stmt = $this->pdo->query("
            SELECT 
                status,
                COUNT(*) as count,
                ROUND(COUNT(*) * 100.0 / (SELECT COUNT(*) FROM users), 2) as percentage
            FROM users
            GROUP BY status
        ");
        
        return [
            'success' => true,
            'title' => 'Personnel Summary Report',
            'data' => $stmt->fetchAll(PDO::FETCH_ASSOC),
            'generated_at' => date('Y-m-d H:i:s')
        ];
    }
    
    private function generateActivityLogReport() {
        $stmt = $this->pdo->prepare("
            SELECT 
                module,
                action,
                COUNT(*) as count,
                DATE(created_at) as date
            FROM audit_logs
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
            GROUP BY module, action, DATE(created_at)
            ORDER BY created_at DESC
        ");
        
        $stmt->execute();
        
        return [
            'success' => true,
            'title' => 'Activity Log Report (Last 7 Days)',
            'data' => $stmt->fetchAll(PDO::FETCH_ASSOC),
            'generated_at' => date('Y-m-d H:i:s')
        ];
    }
    
    private function generateSystemHealthReport() {
        // Simulate system health data
        return [
            'success' => true,
            'title' => 'System Health Report',
            'data' => [
                ['component' => 'Database', 'status' => 'Healthy', 'last_check' => date('Y-m-d H:i:s')],
                ['component' => 'File System', 'status' => 'Healthy', 'last_check' => date('Y-m-d H:i:s')],
                ['component' => 'Memory Usage', 'status' => 'Warning', 'last_check' => date('Y-m-d H:i:s')],
                ['component' => 'CPU Usage', 'status' => 'Healthy', 'last_check' => date('Y-m-d H:i:s')]
            ],
            'generated_at' => date('Y-m-d H:i:s')
        ];
    }
    
    private function generateInventoryStatusReport() {
        $stmt = $this->pdo->query("
            SELECT 
                name,
                current_stock,
                minimum_stock,
                CASE 
                    WHEN current_stock <= minimum_stock THEN 'LOW'
                    WHEN current_stock >= maximum_stock THEN 'HIGH'
                    ELSE 'NORMAL'
                END as status
            FROM inventory_items
            WHERE status = 'ACTIVE'
            ORDER BY current_stock / NULLIF(minimum_stock, 1) ASC
        ");
        
        return [
            'success' => true,
            'title' => 'Inventory Status Report',
            'data' => $stmt->fetchAll(PDO::FETCH_ASSOC),
            'generated_at' => date('Y-m-d H:i:s')
        ];
    }
    
    private function generateWorkflowPerformanceReport() {
        $stmt = $this->pdo->query("
            SELECT 
                wt.name as template_name,
                COUNT(wi.id) as total_instances,
                COUNT(CASE WHEN wi.status = 'COMPLETED' THEN 1 END) as completed,
                COUNT(CASE WHEN wi.status = 'ACTIVE' THEN 1 END) as active,
                AVG(TIMESTAMPDIFF(HOUR, wi.started_at, wi.completed_at)) as avg_completion_hours
            FROM workflow_templates wt
            LEFT JOIN workflow_instances wi ON wt.id = wi.template_id
            WHERE wi.started_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            GROUP BY wt.id, wt.name
        ");
        
        return [
            'success' => true,
            'title' => 'Workflow Performance Report (Last 30 Days)',
            'data' => $stmt->fetchAll(PDO::FETCH_ASSOC),
            'generated_at' => date('Y-m-d H:i:s')
        ];
    }
    
    private function logActivity($action, $description, $entityType = null, $entityId = null) {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO audit_logs (
                    user_id, action, entity_type, entity_id, 
                    ip_address, user_agent, module, severity, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, 'reporting', 'LOW', NOW())
            ");
            
            $stmt->execute([
                $_SESSION['user_id'],
                $action,
                $entityType,
                $entityId,
                $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
            ]);
        } catch (Exception $e) {
            error_log("Error logging activity: " . $e->getMessage());
        }
    }
}
?>