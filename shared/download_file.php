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

// Get file info from database.
// FIX: getMysqliConnection() does not exist anywhere in this codebase -
// only getDbConnection() (PDO) does. This whole file is converted to PDO
// to match.
$pdo = getDbConnection();
// FIX: queried `secure_filename`, but the upload handler
// (shared/file_upload_handler.php) actually writes to `stored_filename` -
// the two files were referencing different column names for the same
// concept, so this lookup could never match a real row. Also defensively
// creates staff_documents here in case this file is ever reached before
// ARMISFileUploader has run once (mirrors that class's own CREATE TABLE).
$pdo->exec("CREATE TABLE IF NOT EXISTS staff_documents (
    id INT AUTO_INCREMENT PRIMARY KEY,
    svcNo VARCHAR(10) NOT NULL,
    document_type_id INT DEFAULT 1,
    original_filename VARCHAR(255) NOT NULL,
    stored_filename VARCHAR(255) NOT NULL,
    file_path VARCHAR(500) NOT NULL,
    file_size INT NOT NULL,
    mime_type VARCHAR(100) NOT NULL,
    upload_date DATETIME NOT NULL,
    uploaded_by VARCHAR(10) DEFAULT NULL,
    KEY idx_svcNo (svcNo)
)");
$stmt = $pdo->prepare("SELECT * FROM staff_documents WHERE stored_filename = ?");
$stmt->execute([$filename]);
$fileInfo = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$fileInfo) {
    http_response_code(404);
    die('File not found');
}

// Check if user has permission to access this file
// For now, allow all authenticated users. Can be enhanced later.
// FIX: $_SESSION['user_id'] holds the person's svcNo (a string) - see the
// same convention documented in users/index.php and profile_manager.php -
// not a numeric id, so it's only checked for truthiness here, never bound
// as an int.
$userId = $_SESSION['user_id'] ?? '';
if ($userId === '') {
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

// Log file access.
// FIX: `file_access_log` does not exist anywhere in the schema dump -
// created here defensively, matching the pattern used for
// staff_password_resets/staff_documents elsewhere in this app. user_id is
// VARCHAR to hold a svcNo, not an int.
$pdo->exec("CREATE TABLE IF NOT EXISTS file_access_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    file_id INT NOT NULL,
    user_id VARCHAR(10) NOT NULL,
    accessed_at DATETIME NOT NULL,
    ip_address VARCHAR(45) DEFAULT NULL,
    KEY idx_file_id (file_id)
)");
$ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
$logStmt = $pdo->prepare("INSERT INTO file_access_log (file_id, user_id, accessed_at, ip_address) VALUES (?, ?, NOW(), ?)");
$logStmt->execute([$fileInfo['id'], $userId, $ipAddress]);

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
