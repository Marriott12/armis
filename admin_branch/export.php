<?php
/**
 * Dashboard Export Functions
 * Generate various reports from dashboard data
 */

session_start();
require_once '../shared/session_init.php';
require_once 'includes/auth.php';
require_once '../shared/database_connection.php';
require_once 'includes/dashboard_service.php';

// Require authentication
requireAuth();

$action = $_GET['action'] ?? '';
$format = $_GET['format'] ?? 'csv';

try {
    $pdo = getDbConnection();
    $service = new DashboardService($pdo);
    
    switch ($action) {
        case 'unit_report':
            exportUnitReport($service, $format);
            break;
            
        case 'personnel_summary':
            exportPersonnelSummary($service, $format);
            break;
            
        case 'kpi_report':
            exportKPIReport($service, $format);
            break;
            
        default:
            http_response_code(400);
            echo json_encode(['error' => 'Invalid export action']);
            break;
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Export failed: ' . $e->getMessage()]);
}

function exportUnitReport($service, $format) {
    $unitData = $service->getUnitOverview();
    $filename = 'unit_report_' . date('Y-m-d') . '.' . $format;
    
    if ($format === 'csv') {
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        
        $output = fopen('php://output', 'w');
        fputcsv($output, ['Unit', 'Strength', 'Active Count', 'Readiness %']);
        
        foreach ($unitData as $unit) {
            fputcsv($output, [
                $unit['unit'],
                $unit['strength'],
                $unit['active_count'],
                round($unit['readiness'], 1)
            ]);
        }
        fclose($output);
    } else {
        // JSON format
        header('Content-Type: application/json');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        echo json_encode($unitData, JSON_PRETTY_PRINT);
    }
}

function exportPersonnelSummary($service, $format) {
    $kpiData = $service->getKPIData();
    $personnelData = $service->getPersonnelDetails();
    $filename = 'personnel_summary_' . date('Y-m-d') . '.' . $format;
    
    if ($format === 'csv') {
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        
        $output = fopen('php://output', 'w');
        
        // KPI Summary
        fputcsv($output, ['KPI Summary']);
        fputcsv($output, ['Total Personnel', $kpiData['total_personnel']]);
        fputcsv($output, ['Active Personnel', $kpiData['active_personnel']]);
        fputcsv($output, ['New Recruits', $kpiData['new_recruits']]);
        fputcsv($output, ['Performance Average', $kpiData['performance_avg'] . '%']);
        fputcsv($output, []); // Empty row
        
        // Personnel Details
        fputcsv($output, ['Personnel Details']);
        fputcsv($output, ['Name', 'Rank', 'Status', 'Unit', 'Years of Service', 'Enlisted Date']);
        
        foreach ($personnelData as $person) {
            fputcsv($output, [
                $person['full_name'],
                $person['rank'],
                $person['status'],
                $person['unit'] ?? 'N/A',
                $person['years_service'],
                $person['enlisted']
            ]);
        }
        fclose($output);
    } else {
        // JSON format
        header('Content-Type: application/json');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        echo json_encode([
            'kpi_summary' => $kpiData,
            'personnel_details' => $personnelData,
            'generated_at' => date('Y-m-d H:i:s')
        ], JSON_PRETTY_PRINT);
    }
}

function exportKPIReport($service, $format) {
    $kpiData = $service->getKPIData();
    $distributionData = $service->getPersonnelDistribution();
    $trendsData = $service->getRecruitmentTrends();
    $filename = 'kpi_report_' . date('Y-m-d') . '.' . $format;
    
    if ($format === 'csv') {
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        
        $output = fopen('php://output', 'w');
        
        fputcsv($output, ['ARMIS KPI Report - ' . date('Y-m-d H:i:s')]);
        fputcsv($output, []);
        
        fputcsv($output, ['Key Performance Indicators']);
        fputcsv($output, ['Metric', 'Value', 'Trend']);
        fputcsv($output, ['Total Personnel', $kpiData['total_personnel'], $kpiData['trends']['total_personnel'] . '%']);
        fputcsv($output, ['Active Personnel', $kpiData['active_personnel'], $kpiData['trends']['active_personnel'] . '%']);
        fputcsv($output, ['New Recruits', $kpiData['new_recruits'], $kpiData['trends']['new_recruits'] . '%']);
        fputcsv($output, ['Performance Average', $kpiData['performance_avg'] . '%', $kpiData['trends']['performance_avg'] . '%']);
        fputcsv($output, []);
        
        fputcsv($output, ['Personnel Distribution']);
        fputcsv($output, ['Status', 'Count']);
        for ($i = 0; $i < count($distributionData['labels']); $i++) {
            fputcsv($output, [$distributionData['labels'][$i], $distributionData['values'][$i]]);
        }
        
        fclose($output);
    } else {
        // JSON format
        header('Content-Type: application/json');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        echo json_encode([
            'kpi_data' => $kpiData,
            'personnel_distribution' => $distributionData,
            'recruitment_trends' => $trendsData,
            'generated_at' => date('Y-m-d H:i:s')
        ], JSON_PRETTY_PRINT);
    }
}
?>
