<?php
/**
 * Secure File Download Handler for ARMIS
 * Handles secure file downloads with access control
 */

require_once dirname(__DIR__) . '/shared/database_connection.php';
require_once dirname(__DIR__) . '/admin_branch/includes/auth.php';

// Check authentication
if (!isLoggedIn()) {
    http_response_code(403);
    die('Access denied');
}

// Get file parameter
$filename = $_GET['file'] ?? '';
if (empty($filename)) {
    http_response_code(400);
    die('File not specified');
}

// Sanitize filename
$filename = basename($filename);

// Get file info from database
$conn = getMysqliConnection();
$sql = "SELECT * FROM staff_documents WHERE secure_filename = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param('s', $filename);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    http_response_code(404);
    die('File not found');
}

$fileInfo = $result->fetch_assoc();

// Check if user has permission to access this file
// For now, allow all authenticated users. Can be enhanced later.
$userId = $_SESSION['user_id'] ?? 0;
if (!$userId) {
    http_response_code(403);
    die('Access denied');
}

// Check if file exists on disk
$filePath = $fileInfo['file_path'];
if (!file_exists($filePath)) {
    http_response_code(404);
    die('File not found on server');
}

// Get file information
$fileSize = filesize($filePath);
$mimeType = $fileInfo['mime_type'];
$originalName = $fileInfo['original_filename'];

// Log file access
$logSql = "INSERT INTO file_access_log (file_id, user_id, accessed_at, ip_address) VALUES (?, ?, NOW(), ?)";
$logStmt = $conn->prepare($logSql);
$ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
$logStmt->bind_param('iis', $fileInfo['id'], $userId, $ipAddress);
$logStmt->execute();

// Set headers for file download
header('Content-Type: ' . $mimeType);
header('Content-Length: ' . $fileSize);
header('Content-Disposition: inline; filename="' . $originalName . '"');
header('Cache-Control: private, max-age=3600');
header('Pragma: private');
header('Expires: ' . gmdate('D, d M Y H:i:s \G\M\T', time() + 3600));

// Security headers
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');

// Output file content
if ($fileSize > 10 * 1024 * 1024) { // For files larger than 10MB, use chunked reading
    $handle = fopen($filePath, 'rb');
    if ($handle) {
        while (!feof($handle)) {
            echo fread($handle, 8192);
            if (ob_get_level()) {
                ob_flush();
                flush();
            }
        }
        fclose($handle);
    }
} else {
    // For smaller files, read all at once
    readfile($filePath);
}

exit;
