<?php
/**
 * ARMIS Command Module - API Controller
 * Handles API requests for command operations
 */

require_once dirname(__DIR__) . '/includes/config.php';
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/command_service.php';
require_once dirname(__DIR__) . '/models/MissionModel.php';

class CommandApiController {
    private $service;
    private $missionModel;
    
    public function __construct() {
        $this->service = new CommandService();
        $this->missionModel = new MissionModel();
    }
    
    /**
     * Handle API requests
     */
    public function handleRequest($method, $endpoint, $data = []) {
        // Validate authentication
        requireAuth();
        requireCommandAccess();
        
        switch ($endpoint) {
            case 'missions':
                return $this->handleMissions($method, $data);
            case 'dashboard':
                return $this->handleDashboard($method);
            case 'reports':
                return $this->handleReports($method, $data);
            default:
                throw new Exception('Unknown endpoint');
        }
    }
    
    private function handleMissions($method, $data) {
        switch ($method) {
            case 'GET':
                $filters = [
                    'status' => $data['status'] ?? '',
                    'priority' => $data['priority'] ?? '',
                    'search' => $data['search'] ?? '',
                    'limit' => $data['limit'] ?? 50
                ];
                return $this->missionModel->getAll($filters);
                
            case 'POST':
                if (!hasCommandPermission('manage_missions')) {
                    throw new Exception('Insufficient permissions');
                }
                return $this->missionModel->create($data);
                
            case 'PUT':
                if (!hasCommandPermission('manage_missions')) {
                    throw new Exception('Insufficient permissions');
                }
                $id = $data['id'] ?? 0;
                unset($data['id']);
                return $this->missionModel->update($id, $data);
                
            case 'DELETE':
                if (!hasCommandPermission('manage_missions')) {
                    throw new Exception('Insufficient permissions');
                }
                return $this->missionModel->delete($data['id']);
                
            default:
                throw new Exception('Method not allowed');
        }
    }
    
    private function handleDashboard($method) {
        if ($method !== 'GET') {
            throw new Exception('Method not allowed');
        }
        
        return $this->service->getDashboardData();
    }
    
    private function handleReports($method, $data) {
        if ($method !== 'GET') {
            throw new Exception('Method not allowed');
        }
        
        $reportType = $data['type'] ?? '';
        return $this->service->generateCommandReport($reportType, $data);
    }
}