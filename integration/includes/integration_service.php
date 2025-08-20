<?php
/**
 * ARMIS Integration Service
 * Core service class for integration and data management
 */

class IntegrationService {
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    /**
     * Get all API endpoints
     */
    public function getApiEndpoints() {
        try {
            $stmt = $this->pdo->query("
                SELECT 
                    ae.id,
                    ae.name,
                    ae.endpoint_url,
                    ae.method,
                    ae.description,
                    ae.authentication_type,
                    ae.is_active,
                    ae.rate_limit_per_hour,
                    ae.created_at,
                    CONCAT(u.fname, ' ', u.lname) as created_by_name
                FROM api_endpoints ae
                LEFT JOIN users u ON ae.created_by = u.id
                ORDER BY ae.name ASC
            ");
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error getting API endpoints: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get all sync jobs
     */
    public function getSyncJobs() {
        try {
            $stmt = $this->pdo->query("
                SELECT 
                    dsj.id,
                    dsj.name,
                    dsj.source_system,
                    dsj.target_system,
                    dsj.sync_type,
                    dsj.schedule_cron,
                    dsj.last_run_at,
                    dsj.next_run_at,
                    dsj.status,
                    dsj.configuration,
                    CONCAT(u.fname, ' ', u.lname) as created_by_name
                FROM data_sync_jobs dsj
                LEFT JOIN users u ON dsj.created_by = u.id
                ORDER BY dsj.name ASC
            ");
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error getting sync jobs: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get recent sync logs
     */
    public function getRecentSyncLogs($limit = 10) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT 
                    dsl.id,
                    dsl.job_id,
                    dsl.status,
                    dsl.records_processed,
                    dsl.records_success,
                    dsl.records_error,
                    dsl.start_time,
                    dsl.end_time,
                    dsl.error_message,
                    dsj.name as job_name
                FROM data_sync_logs dsl
                JOIN data_sync_jobs dsj ON dsl.job_id = dsj.id
                ORDER BY dsl.start_time DESC
                LIMIT ?
            ");
            
            $stmt->execute([$limit]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error getting recent sync logs: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get integration system status
     */
    public function getIntegrationStatus() {
        try {
            $stmt = $this->pdo->query("
                SELECT 
                    COUNT(DISTINCT ae.id) as total_endpoints,
                    COUNT(DISTINCT CASE WHEN ae.is_active = 1 THEN ae.id END) as active_endpoints,
                    COUNT(DISTINCT dsj.id) as total_sync_jobs,
                    COUNT(DISTINCT CASE WHEN dsj.status = 'ACTIVE' THEN dsj.id END) as active_sync_jobs,
                    COUNT(DISTINCT CASE WHEN dsl.status = 'ERROR' AND dsl.start_time >= DATE_SUB(NOW(), INTERVAL 24 HOUR) THEN dsl.id END) as errors_24h
                FROM api_endpoints ae
                CROSS JOIN data_sync_jobs dsj
                CROSS JOIN data_sync_logs dsl
            ");
            
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            return [
                'external_systems' => $result['active_endpoints'] ?? 0,
                'total_endpoints' => $result['total_endpoints'] ?? 0,
                'active_endpoints' => $result['active_endpoints'] ?? 0,
                'total_sync_jobs' => $result['total_sync_jobs'] ?? 0,
                'active_sync_jobs' => $result['active_sync_jobs'] ?? 0,
                'errors_24h' => $result['errors_24h'] ?? 0
            ];
        } catch (Exception $e) {
            error_log("Error getting integration status: " . $e->getMessage());
            return [
                'external_systems' => 0,
                'total_endpoints' => 0,
                'active_endpoints' => 0,
                'total_sync_jobs' => 0,
                'active_sync_jobs' => 0,
                'errors_24h' => 0
            ];
        }
    }
    
    /**
     * Create new API endpoint
     */
    public function createApiEndpoint($data) {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO api_endpoints (
                    name, endpoint_url, method, description,
                    authentication_type, is_active, rate_limit_per_hour,
                    created_by
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $result = $stmt->execute([
                $data['name'],
                $data['endpoint_url'],
                $data['method'] ?? 'GET',
                $data['description'] ?? null,
                $data['authentication_type'] ?? 'API_KEY',
                $data['is_active'] ?? 1,
                $data['rate_limit_per_hour'] ?? 1000,
                $_SESSION['user_id']
            ]);
            
            if ($result) {
                $endpointId = $this->pdo->lastInsertId();
                $this->logActivity('api_endpoint_created', 'API endpoint created', 'api_endpoint', $endpointId);
                return $endpointId;
            }
            
            return false;
        } catch (Exception $e) {
            error_log("Error creating API endpoint: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Create new sync job
     */
    public function createSyncJob($data) {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO data_sync_jobs (
                    name, source_system, target_system, sync_type,
                    schedule_cron, status, configuration, created_by
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $result = $stmt->execute([
                $data['name'],
                $data['source_system'],
                $data['target_system'],
                $data['sync_type'],
                $data['schedule_cron'] ?? null,
                'ACTIVE',
                json_encode($data['configuration'] ?? []),
                $_SESSION['user_id']
            ]);
            
            if ($result) {
                $jobId = $this->pdo->lastInsertId();
                
                // Calculate next run time based on cron schedule
                if (!empty($data['schedule_cron'])) {
                    $this->updateNextRunTime($jobId, $data['schedule_cron']);
                }
                
                $this->logActivity('sync_job_created', 'Data sync job created', 'sync_job', $jobId);
                return $jobId;
            }
            
            return false;
        } catch (Exception $e) {
            error_log("Error creating sync job: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Execute sync job manually
     */
    public function runSyncJob($jobId) {
        try {
            // Get job details
            $stmt = $this->pdo->prepare("
                SELECT * FROM data_sync_jobs WHERE id = ? AND status = 'ACTIVE'
            ");
            $stmt->execute([$jobId]);
            $job = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$job) {
                throw new Exception('Sync job not found or inactive');
            }
            
            // Start sync log
            $logStmt = $this->pdo->prepare("
                INSERT INTO data_sync_logs (job_id, status, start_time)
                VALUES (?, 'STARTED', NOW())
            ");
            $logStmt->execute([$jobId]);
            $logId = $this->pdo->lastInsertId();
            
            // Simulate sync process (in real implementation, this would call actual sync logic)
            $this->performSync($job, $logId);
            
            // Update job last run time
            $updateStmt = $this->pdo->prepare("
                UPDATE data_sync_jobs 
                SET last_run_at = NOW()
                WHERE id = ?
            ");
            $updateStmt->execute([$jobId]);
            
            $this->logActivity('sync_job_executed', 'Data sync job executed', 'sync_job', $jobId);
            
            return true;
        } catch (Exception $e) {
            error_log("Error running sync job: " . $e->getMessage());
            
            // Update log with error
            if (isset($logId)) {
                $errorStmt = $this->pdo->prepare("
                    UPDATE data_sync_logs 
                    SET status = 'ERROR', end_time = NOW(), error_message = ?
                    WHERE id = ?
                ");
                $errorStmt->execute([$e->getMessage(), $logId]);
            }
            
            return false;
        }
    }
    
    /**
     * Test API endpoint connectivity
     */
    public function testApiEndpoint($endpointId) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT * FROM api_endpoints WHERE id = ?
            ");
            $stmt->execute([$endpointId]);
            $endpoint = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$endpoint) {
                throw new Exception('API endpoint not found');
            }
            
            // Perform test request (simplified)
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => $endpoint['endpoint_url'],
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 30,
                CURLOPT_CUSTOMREQUEST => $endpoint['method'],
                CURLOPT_HTTPHEADER => [
                    'Content-Type: application/json',
                    'User-Agent: ARMIS-Integration/1.0'
                ]
            ]);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);
            
            if ($error) {
                throw new Exception('cURL error: ' . $error);
            }
            
            // Log test result
            $this->logApiAccess($endpointId, $endpoint['method'], $endpoint['endpoint_url'], $httpCode, $response);
            
            return [
                'success' => $httpCode >= 200 && $httpCode < 300,
                'http_code' => $httpCode,
                'response' => $response
            ];
        } catch (Exception $e) {
            error_log("Error testing API endpoint: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Export data to various formats
     */
    public function exportData($table, $format = 'CSV', $filters = []) {
        try {
            // Validate table name for security
            $allowedTables = ['users', 'audit_logs', 'inventory_items', 'supply_requisitions'];
            if (!in_array($table, $allowedTables)) {
                throw new Exception('Table not allowed for export');
            }
            
            // Build query with filters
            $where = '';
            $params = [];
            
            if (!empty($filters)) {
                $conditions = [];
                foreach ($filters as $field => $value) {
                    $conditions[] = "$field = ?";
                    $params[] = $value;
                }
                $where = 'WHERE ' . implode(' AND ', $conditions);
            }
            
            $stmt = $this->pdo->prepare("SELECT * FROM `$table` $where");
            $stmt->execute($params);
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Format data based on requested format
            switch (strtoupper($format)) {
                case 'CSV':
                    return $this->formatAsCSV($data);
                case 'JSON':
                    return $this->formatAsJSON($data);
                case 'XML':
                    return $this->formatAsXML($data);
                default:
                    throw new Exception('Unsupported export format');
            }
        } catch (Exception $e) {
            error_log("Error exporting data: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Helper methods
     */
    private function performSync($job, $logId) {
        // Simulate sync process
        $recordsProcessed = rand(50, 500);
        $recordsSuccess = $recordsProcessed - rand(0, 5);
        $recordsError = $recordsProcessed - $recordsSuccess;
        
        // Update log with results
        $stmt = $this->pdo->prepare("
            UPDATE data_sync_logs 
            SET status = ?, end_time = NOW(), 
                records_processed = ?, records_success = ?, records_error = ?
            WHERE id = ?
        ");
        
        $status = $recordsError > 0 ? 'WARNING' : 'SUCCESS';
        $stmt->execute([$status, $recordsProcessed, $recordsSuccess, $recordsError, $logId]);
    }
    
    private function updateNextRunTime($jobId, $cronSchedule) {
        // Simple cron parsing - in production, use a proper cron library
        $nextRun = date('Y-m-d H:i:s', strtotime('+1 hour')); // Simplified
        
        $stmt = $this->pdo->prepare("
            UPDATE data_sync_jobs 
            SET next_run_at = ?
            WHERE id = ?
        ");
        $stmt->execute([$nextRun, $jobId]);
    }
    
    private function logActivity($action, $description, $entityType = null, $entityId = null) {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO audit_logs (
                    user_id, action, entity_type, entity_id, 
                    ip_address, user_agent, module, severity, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, 'integration', 'LOW', NOW())
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
    
    private function logApiAccess($endpointId, $method, $path, $responseCode, $responseBody) {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO api_access_logs (
                    endpoint_id, user_id, ip_address, request_method,
                    request_path, response_code, response_body, 
                    response_time_ms, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            
            $stmt->execute([
                $endpointId,
                $_SESSION['user_id'] ?? null,
                $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                $method,
                $path,
                $responseCode,
                substr($responseBody, 0, 1000), // Limit response body size
                rand(100, 2000) // Simulated response time
            ]);
        } catch (Exception $e) {
            error_log("Error logging API access: " . $e->getMessage());
        }
    }
    
    private function formatAsCSV($data) {
        if (empty($data)) return '';
        
        $output = fopen('php://temp', 'r+');
        
        // Write headers
        fputcsv($output, array_keys($data[0]));
        
        // Write data
        foreach ($data as $row) {
            fputcsv($output, $row);
        }
        
        rewind($output);
        $csv = stream_get_contents($output);
        fclose($output);
        
        return $csv;
    }
    
    private function formatAsJSON($data) {
        return json_encode($data, JSON_PRETTY_PRINT);
    }
    
    private function formatAsXML($data) {
        $xml = new SimpleXMLElement('<data/>');
        
        foreach ($data as $row) {
            $item = $xml->addChild('item');
            foreach ($row as $key => $value) {
                $item->addChild($key, htmlspecialchars($value));
            }
        }
        
        return $xml->asXML();
    }
}
?>