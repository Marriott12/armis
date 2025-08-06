<?php
/**
 * AJAX File Upload Handler for ARMIS
 * Handles document uploads for staff creation
 */

define('ARMIS_ADMIN_BRANCH', true);
require_once dirname(__DIR__) . '/shared/file_upload_handler.php';
require_once dirname(__DIR__) . '/admin_branch/includes/auth.php';

// Set JSON response headers
header('Content-Type: application/json');

// Check authentication
if (!isLoggedIn()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Authentication required']);
    exit;
}

// Handle POST requests only
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

try {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'upload_document':
            handleDocumentUpload();
            break;
            
        case 'delete_document':
            handleDocumentDelete();
            break;
            
        case 'get_documents':
            handleGetDocuments();
            break;
            
        default:
            throw new Exception('Invalid action');
    }
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

/**
 * Handle document upload
 */
function handleDocumentUpload() {
    // Validate required fields
    if (!isset($_FILES['document_file']) || !isset($_POST['document_type'])) {
        throw new Exception('Missing required fields');
    }
    
    $staffId = (int)($_POST['staff_id'] ?? 0);
    $documentType = sanitize($_POST['document_type']);
    $file = $_FILES['document_file'];
    
    // For new staff creation, use session storage temporarily
    if ($staffId === 0) {
        $result = handleTemporaryUpload($file, $documentType);
    } else {
        // For existing staff, upload directly
        $uploader = new ARMISFileUploader();
        $result = $uploader->uploadFile($file, $staffId, $documentType);
        
        if (!$result) {
            throw new Exception(implode(', ', $uploader->getErrors()));
        }
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'File uploaded successfully',
        'file' => $result
    ]);
}

/**
 * Handle temporary upload for new staff creation
 */
function handleTemporaryUpload($file, $documentType) {
    // Store file temporarily and save info in session
    $tempDir = dirname(__DIR__) . '/uploads/temp/';
    if (!file_exists($tempDir)) {
        mkdir($tempDir, 0755, true);
    }
    
    // Validate file
    $uploader = new ARMISFileUploader();
    $reflection = new ReflectionClass($uploader);
    $validateMethod = $reflection->getMethod('validateFile');
    $validateMethod->setAccessible(true);
    
    if (!$validateMethod->invoke($uploader, $file)) {
        throw new Exception(implode(', ', $uploader->getErrors()));
    }
    
    // Generate temporary filename
    $fileExtension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $tempFileName = 'temp_' . uniqid() . '.' . $fileExtension;
    $tempFilePath = $tempDir . $tempFileName;
    
    // Move file to temp directory
    if (!move_uploaded_file($file['tmp_name'], $tempFilePath)) {
        throw new Exception('Failed to save temporary file');
    }
    
    // Store file info in session
    if (!isset($_SESSION['temp_documents'])) {
        $_SESSION['temp_documents'] = [];
    }
    
    $fileInfo = [
        'id' => uniqid(),
        'original_name' => $file['name'],
        'temp_filename' => $tempFileName,
        'temp_path' => $tempFilePath,
        'document_type' => $documentType,
        'file_size' => $file['size'],
        'file_type' => $fileExtension,
        'uploaded_at' => date('Y-m-d H:i:s')
    ];
    
    $_SESSION['temp_documents'][] = $fileInfo;
    
    return $fileInfo;
}

/**
 * Handle document deletion
 */
function handleDocumentDelete() {
    $fileId = $_POST['file_id'] ?? '';
    $staffId = (int)($_POST['staff_id'] ?? 0);
    
    if ($staffId === 0) {
        // Remove from session for new staff
        if (isset($_SESSION['temp_documents'])) {
            $_SESSION['temp_documents'] = array_filter($_SESSION['temp_documents'], 
                function($doc) use ($fileId) {
                    if ($doc['id'] === $fileId) {
                        // Delete temporary file
                        if (file_exists($doc['temp_path'])) {
                            unlink($doc['temp_path']);
                        }
                        return false;
                    }
                    return true;
                }
            );
        }
    } else {
        // Delete from database for existing staff
        $uploader = new ARMISFileUploader();
        if (!$uploader->deleteFile($fileId, $staffId)) {
            throw new Exception(implode(', ', $uploader->getErrors()));
        }
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Document deleted successfully'
    ]);
}

/**
 * Handle getting documents list
 */
function handleGetDocuments() {
    $staffId = (int)($_GET['staff_id'] ?? 0);
    
    if ($staffId === 0) {
        // Return session documents for new staff
        $documents = $_SESSION['temp_documents'] ?? [];
    } else {
        // Return database documents for existing staff
        $uploader = new ARMISFileUploader();
        $documents = $uploader->getStaffFiles($staffId);
    }
    
    echo json_encode([
        'success' => true,
        'documents' => $documents
    ]);
}

/**
 * Sanitize input
 */
function sanitize($input) {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}
