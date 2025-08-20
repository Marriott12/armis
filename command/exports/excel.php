<?php
/**
 * ARMIS Command Module - Excel Export
 * Generate Excel reports for command operations
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/command_service.php';

// Require authentication
session_start();
requireAuth();
requireCommandAccess();

// Get report parameters
$reportType = $_GET['type'] ?? 'mission_summary';

try {
    $service = new CommandService();
    
    switch ($reportType) {
        case 'mission_summary':
            $data = $service->searchMissions();
            $filename = 'command_missions_' . date('Y-m-d') . '.csv';
            break;
        case 'personnel_deployment':
            $data = $service->generateCommandReport('personnel_deployment');
            $filename = 'personnel_deployment_' . date('Y-m-d') . '.csv';
            break;
        default:
            throw new Exception('Unknown report type');
    }
    
    // Set headers for Excel download (CSV format for simplicity)
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: max-age=0');
    
    // Open output stream
    $output = fopen('php://output', 'w');
    
    // Write header row
    if (!empty($data)) {
        if (is_array($data) && isset($data[0])) {
            $headers = array_keys((array)$data[0]);
            fputcsv($output, $headers);
            
            // Write data rows
            foreach ($data as $row) {
                fputcsv($output, (array)$row);
            }
        }
    } else {
        fputcsv($output, ['No data available']);
    }
    
    fclose($output);
    
} catch (Exception $e) {
    http_response_code(400);
    echo "Error generating Excel export: " . $e->getMessage();
}