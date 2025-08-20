<?php
/**
 * ARMIS Command Module - PDF Export
 * Generate PDF reports for command operations
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
$format = $_GET['format'] ?? 'download';

try {
    $service = new CommandService();
    
    switch ($reportType) {
        case 'mission_summary':
            $data = $service->searchMissions();
            $title = 'Mission Summary Report';
            break;
        case 'personnel_deployment':
            $data = $service->generateCommandReport('personnel_deployment');
            $title = 'Personnel Deployment Report';
            break;
        default:
            throw new Exception('Unknown report type');
    }
    
    // Generate PDF content
    $html = generatePDFContent($title, $data, $reportType);
    
    if ($format === 'preview') {
        echo $html;
    } else {
        // Set headers for PDF download
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="command_' . $reportType . '_' . date('Y-m-d') . '.pdf"');
        
        // For demo purposes, output HTML. In production, use a PDF library like TCPDF or FPDF
        echo "PDF content would be generated here for: " . $title;
        echo "\n\nData: " . json_encode($data, JSON_PRETTY_PRINT);
    }
    
} catch (Exception $e) {
    http_response_code(400);
    echo "Error generating PDF: " . $e->getMessage();
}

function generatePDFContent($title, $data, $reportType) {
    return "
    <html>
    <head>
        <title>$title</title>
        <style>
            body { font-family: Arial, sans-serif; margin: 20px; }
            .header { text-align: center; border-bottom: 2px solid #000; padding-bottom: 10px; }
            .data-table { width: 100%; border-collapse: collapse; margin: 20px 0; }
            .data-table th, .data-table td { border: 1px solid #ddd; padding: 8px; text-align: left; }
            .data-table th { background-color: #f2f2f2; }
            .footer { margin-top: 30px; font-size: 12px; color: #666; }
        </style>
    </head>
    <body>
        <div class='header'>
            <h1>ARMIS Command Module</h1>
            <h2>$title</h2>
            <p>Generated on: " . date('Y-m-d H:i:s') . "</p>
        </div>
        
        <div class='content'>
            <!-- Report content would be generated here based on data -->
            <p>Report Type: $reportType</p>
            <p>Data Count: " . (is_array($data) ? count($data) : 'N/A') . "</p>
        </div>
        
        <div class='footer'>
            <p>ARMIS - Army Resource Management Information System</p>
            <p>Classification: For Official Use Only</p>
        </div>
    </body>
    </html>";
}