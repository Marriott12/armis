<?php
/**
 * ARMIS File Upload Handler
 * Secure file upload management system for staff documents
 */

if (!defined('ARMIS_ADMIN_BRANCH')) {
    require_once dirname(__DIR__) . '/shared/database_connection.php';
    require_once dirname(__DIR__) . '/config.php'; // Make sure config is loaded
}

class ARMISFileUploader {
    
    private $conn;
    private $uploadPath;
    private $maxFileSize;
    private $allowedTypes;
    private $errors = [];
    
    public function __construct() {
        $this->conn = getMysqliConnection();
        $this->uploadPath = dirname(__DIR__) . '/uploads/staff_documents/';
        $this->maxFileSize = 10 * 1024 * 1024; // 10MB
        $this->allowedTypes = [
            'jpg', 'jpeg', 'png', 'pdf', 'doc', 'docx', 
            'xls', 'xlsx', 'txt', 'rtf'
        ];
        
        // Create upload directory if it doesn't exist
        if (!file_exists($this->uploadPath)) {
            mkdir($this->uploadPath, 0755, true);
        }
    }
    
    /**
     * Upload file with security validation
     */
    public function uploadFile($file, $staffId, $documentType, $originalName = null) {
        if (!$this->validateFile($file)) {
            return false;
        }
        
        $fileInfo = $this->processFile($file, $staffId, $documentType, $originalName);
        if (!$fileInfo) {
            return false;
        }
        
        // Save file information to database
        return $this->saveFileRecord($fileInfo);
    }
    
    /**
     * Validate uploaded file
     */
    private function validateFile($file) {
        // Check for upload errors
        if (!isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
            $this->addError('File upload failed: ' . $this->getUploadErrorMessage($file['error']));
            return false;
        }
        
        // Check file size
        if ($file['size'] > $this->maxFileSize) {
            $this->addError('File size exceeds maximum limit of ' . ($this->maxFileSize / 1024 / 1024) . 'MB');
            return false;
        }
        
        // Check if file is in the blocked list (to prevent restoration of removed files)
        if (function_exists('validateFileUpload') && !validateFileUpload($file['name'])) {
            $this->addError('This file type is not allowed for security reasons');
            // Log the attempt
            error_log('Attempt to upload blocked file: ' . $file['name']);
            return false;
        }
        
        // Check file type
        $fileExtension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($fileExtension, $this->allowedTypes)) {
            $this->addError('File type not allowed. Allowed types: ' . implode(', ', $this->allowedTypes));
            return false;
        }
        
        // Check MIME type for additional security
        $allowedMimes = [
            'image/jpeg', 'image/jpg', 'image/png',
            'application/pdf',
            'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'text/plain', 'application/rtf'
        ];
        
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        
        if (!in_array($mimeType, $allowedMimes)) {
            $this->addError('Invalid file type detected');
            return false;
        }
        
        return true;
    }
    
    /**
     * Process and move uploaded file
     */
    private function processFile($file, $staffId, $documentType, $originalName = null) {
        $fileExtension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $fileName = $originalName ?: $file['name'];
        
        // Generate secure filename
        $secureFileName = $this->generateSecureFileName($staffId, $documentType, $fileExtension);
        $fullPath = $this->uploadPath . $secureFileName;
        
        // Move uploaded file
        if (!move_uploaded_file($file['tmp_name'], $fullPath)) {
            $this->addError('Failed to save uploaded file');
            return false;
        }
        
        return [
            'original_name' => $fileName,
            'secure_filename' => $secureFileName,
            'file_path' => $fullPath,
            'file_size' => $file['size'],
            'file_type' => $fileExtension,
            'mime_type' => $file['type'],
            'staff_id' => $staffId,
            'document_type' => $documentType,
            'document_type_id' => is_numeric($documentType) ? $documentType : 1
        ];
    }
    
    /**
     * Generate secure filename
     */
    private function generateSecureFileName($staffId, $documentType, $extension) {
        $timestamp = date('Y-m-d_H-i-s');
        $randomString = bin2hex(random_bytes(8));
        return "staff_{$staffId}_{$documentType}_{$timestamp}_{$randomString}.{$extension}";
    }
    
    /**
     * Save file record to database
     */
    private function saveFileRecord($fileInfo) {
        $sql = "INSERT INTO staff_documents (
            staff_id, document_type_id, original_filename, stored_filename, 
            file_path, file_size, mime_type, upload_date, uploaded_by
        ) VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), ?)";
        
        $stmt = $this->conn->prepare($sql);
        $uploadedBy = $_SESSION['user_id'] ?? 1; // Default to admin user
        $documentTypeId = $fileInfo['document_type_id'] ?? 1; // Default document type
        
        $stmt->bind_param('iisssisi',
            $fileInfo['staff_id'],
            $documentTypeId,
            $fileInfo['original_name'],
            $fileInfo['secure_filename'],
            $fileInfo['file_path'],
            $fileInfo['file_size'],
            $fileInfo['mime_type'],
            $uploadedBy
        );
        
        if ($stmt->execute()) {
            return [
                'success' => true,
                'file_id' => $this->conn->insert_id,
                'filename' => $fileInfo['secure_filename'],
                'original_name' => $fileInfo['original_name']
            ];
        } else {
            $this->addError('Database error: ' . $this->conn->error);
            return false;
        }
    }
    
    /**
     * Get files for a staff member
     */
    public function getStaffFiles($staffId) {
        $sql = "SELECT * FROM staff_documents WHERE staff_id = ? ORDER BY uploaded_at DESC";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param('i', $staffId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        return $result->fetch_all(MYSQLI_ASSOC);
    }
    
    /**
     * Delete file
     */
    public function deleteFile($fileId, $staffId) {
        // Get file info first
        $sql = "SELECT * FROM staff_documents WHERE id = ? AND staff_id = ?";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param('ii', $fileId, $staffId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            $this->addError('File not found');
            return false;
        }
        
        $fileInfo = $result->fetch_assoc();
        
        // Delete physical file
        if (file_exists($fileInfo['file_path'])) {
            unlink($fileInfo['file_path']);
        }
        
        // Delete database record
        $deleteSql = "DELETE FROM staff_documents WHERE id = ?";
        $deleteStmt = $this->conn->prepare($deleteSql);
        $deleteStmt->bind_param('i', $fileId);
        
        return $deleteStmt->execute();
    }
    
    /**
     * Get upload error message
     */
    private function getUploadErrorMessage($error) {
        $messages = [
            UPLOAD_ERR_INI_SIZE => 'File exceeds server upload limit',
            UPLOAD_ERR_FORM_SIZE => 'File exceeds form upload limit',
            UPLOAD_ERR_PARTIAL => 'File was only partially uploaded',
            UPLOAD_ERR_NO_FILE => 'No file was uploaded',
            UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
            UPLOAD_ERR_EXTENSION => 'File upload stopped by extension'
        ];
        
        return $messages[$error] ?? 'Unknown upload error';
    }
    
    /**
     * Add error message
     */
    private function addError($message) {
        $this->errors[] = $message;
    }
    
    /**
     * Get all errors
     */
    public function getErrors() {
        return $this->errors;
    }
    
    /**
     * Check if there are errors
     */
    public function hasErrors() {
        return !empty($this->errors);
    }
    
    /**
     * Clear errors
     */
    public function clearErrors() {
        $this->errors = [];
    }
    
    /**
     * Get file download URL
     */
    public function getFileUrl($filename) {
        return '/Armis2/shared/download_file.php?file=' . urlencode($filename);
    }
    
    /**
     * Get allowed file types for display
     */
    public function getAllowedTypesString() {
        return implode(', ', $this->allowedTypes);
    }
    
    /**
     * Get max file size for display
     */
    public function getMaxFileSizeString() {
        return ($this->maxFileSize / 1024 / 1024) . 'MB';
    }
}
