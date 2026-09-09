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
        // FIX: getMysqliConnection() does not exist anywhere in this
        // codebase - only getDbConnection() (PDO) does, used everywhere
        // else in the app. This class is now PDO-based throughout.
        $this->conn = getDbConnection();
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

        // FIX: `staff_documents` does not exist anywhere in the schema
        // dump - every operation below would fail with "table doesn't
        // exist". Created defensively here, matching the pattern used for
        // staff_password_resets elsewhere in this app. svcNo is
        // VARCHAR(10) to match staff.svcNo (leading zeros are meaningful,
        // e.g. '007414' - an INT column would silently mangle them).
        $this->conn->exec("CREATE TABLE IF NOT EXISTS staff_documents (
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
            'svcNo' => $staffId,
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
        // FIX: svcNo/uploaded_by are strings (staff.svcNo is VARCHAR),
        // not the ints this mysqli bind_param string ('iisssisi') assumed.
        $sql = "INSERT INTO staff_documents (
            svcNo, document_type_id, original_filename, stored_filename, 
            file_path, file_size, mime_type, upload_date, uploaded_by
        ) VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), ?)";
        
        $stmt = $this->conn->prepare($sql);
        $uploadedBy = $_SESSION['user_id'] ?? null; // svcNo of the uploader, if known
        $documentTypeId = $fileInfo['document_type_id'] ?? 1; // Default document type

        try {
            $stmt->execute([
                $fileInfo['svcNo'],
                $documentTypeId,
                $fileInfo['original_name'],
                $fileInfo['secure_filename'],
                $fileInfo['file_path'],
                $fileInfo['file_size'],
                $fileInfo['mime_type'],
                $uploadedBy
            ]);
            return [
                'success' => true,
                'file_id' => $this->conn->lastInsertId(),
                'filename' => $fileInfo['secure_filename'],
                'original_name' => $fileInfo['original_name']
            ];
        } catch (Exception $e) {
            $this->addError('Database error: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get files for a staff member
     */
    public function getStaffFiles($staffId) {
        // FIX: ordered by `uploaded_at`, a column that was never actually
        // created (the real timestamp column here is `upload_date` - see
        // the CREATE TABLE in the constructor above).
        $sql = "SELECT * FROM staff_documents WHERE svcNo = ? ORDER BY upload_date DESC";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([$staffId]);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Delete file
     */
    public function deleteFile($fileId, $staffId) {
        // Get file info first
        $sql = "SELECT * FROM staff_documents WHERE id = ? AND svcNo = ?";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([$fileId, $staffId]);
        $fileInfo = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$fileInfo) {
            $this->addError('File not found');
            return false;
        }
        
        // Delete physical file
        if (file_exists($fileInfo['file_path'])) {
            unlink($fileInfo['file_path']);
        }
        
        // Delete database record
        $deleteStmt = $this->conn->prepare("DELETE FROM staff_documents WHERE id = ?");
        
        return $deleteStmt->execute([$fileId]);
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
