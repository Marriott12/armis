<?php
/**
 * Enhanced Military CV Processor Class
 * Handles advanced CV processing with military-specific templates and features
 * Includes text extraction, template generation, and document management
 */

require_once dirname(__DIR__, 2) . '/shared/database_connection.php';
require_once dirname(__DIR__, 2) . '/shared/security_audit_service.php';

class CVProcessor {
    private $pdo;
    private $auditService;
    private $userId;
    private $uploadDir;
    private $templateDir;
    
    // Military CV template types
    const TEMPLATE_OFFICER = 'officer';
    const TEMPLATE_NCO = 'nco';
    const TEMPLATE_ENLISTED = 'enlisted';
    const TEMPLATE_CIVILIAN = 'civilian';
    
    // Document approval statuses
    const STATUS_DRAFT = 'draft';
    const STATUS_PENDING = 'pending_approval';
    const STATUS_APPROVED = 'approved';
    const STATUS_REJECTED = 'rejected';
    
    public function __construct($userId) {
        $this->pdo = getDbConnection();
        $this->auditService = new SecurityAuditService();
        $this->userId = $userId;
        $this->uploadDir = dirname(__DIR__, 2) . '/uploads/cvs/';
        $this->templateDir = dirname(__DIR__) . '/cv_templates/';
        
        $this->ensureDirectories();
    }
    
    /**
     * Ensure required directories exist
     */
    private function ensureDirectories() {
        if (!is_dir($this->uploadDir)) {
            mkdir($this->uploadDir, 0755, true);
        }
        if (!is_dir($this->templateDir)) {
            mkdir($this->templateDir, 0755, true);
        }
    }
    
    /**
     * Process uploaded CV with enhanced military-specific extraction
     */
    public function processCV($file, $templateType = null) {
        try {
            // Validate file
            $validation = $this->validateFile($file);
            if (!$validation['valid']) {
                return ['success' => false, 'message' => $validation['message']];
            }
            
            // Security scan simulation
            if (!$this->performSecurityScan($file['tmp_name'])) {
                return ['success' => false, 'message' => 'File failed security scan'];
            }
            
            // Generate unique filename
            $filename = $this->generateSecureFilename($file['name']);
            $filepath = $this->uploadDir . $filename;
            
            // Move uploaded file
            if (!move_uploaded_file($file['tmp_name'], $filepath)) {
                return ['success' => false, 'message' => 'Failed to save CV file'];
            }
            
            // Extract data with enhanced processing
            $extractedData = $this->extractMilitaryData($filepath, $file['type'], $templateType);
            
            // Create CV record with version control
            $cvId = $this->createCVRecord($filename, $file, $extractedData, $templateType);
            
            // Log the activity
            $this->auditService->logActivity(
                $this->userId,
                'cv_upload',
                'CV uploaded and processed',
                'cv_document',
                $cvId,
                ['filename' => $filename, 'template_type' => $templateType]
            );
            
            return [
                'success' => true,
                'message' => 'CV processed successfully',
                'cv_id' => $cvId,
                'filename' => $filename,
                'extracted_data' => $extractedData,
                'template_type' => $templateType
            ];
            
        } catch (Exception $e) {
            error_log("CV processing error: " . $e->getMessage());
            return ['success' => false, 'message' => 'CV processing failed: ' . $e->getMessage()];
        }
    }
    
    /**
     * Enhanced file validation with military standards
     */
    private function validateFile($file) {
        if (!isset($file['tmp_name']) || empty($file['tmp_name'])) {
            return ['valid' => false, 'message' => 'No file uploaded'];
        }
        
        // Check file type
        $allowedTypes = [
            'application/pdf',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'text/plain'
        ];
        
        if (!in_array($file['type'], $allowedTypes)) {
            return ['valid' => false, 'message' => 'File type not allowed. Only PDF, Word, and text files are permitted.'];
        }
        
        // Check file size (max 15MB for military documents)
        $maxSize = 15 * 1024 * 1024;
        if ($file['size'] > $maxSize) {
            return ['valid' => false, 'message' => 'File size exceeds 15MB limit'];
        }
        
        // Validate file extension
        $pathInfo = pathinfo($file['name']);
        $allowedExtensions = ['pdf', 'doc', 'docx', 'txt'];
        if (!isset($pathInfo['extension']) || !in_array(strtolower($pathInfo['extension']), $allowedExtensions)) {
            return ['valid' => false, 'message' => 'Invalid file extension'];
        }
        
        return ['valid' => true];
    }
    
    /**
     * Perform security scan simulation
     */
    private function performSecurityScan($filepath) {
        // Basic file header check
        $handle = fopen($filepath, 'rb');
        if (!$handle) {
            return false;
        }
        
        $header = fread($handle, 1024);
        fclose($handle);
        
        // Check for suspicious patterns (simplified)
        $suspiciousPatterns = [
            'javascript:',
            '<script',
            'eval(',
            'exec(',
            'system(',
            'shell_exec'
        ];
        
        foreach ($suspiciousPatterns as $pattern) {
            if (stripos($header, $pattern) !== false) {
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Generate secure filename
     */
    private function generateSecureFilename($originalName) {
        $pathInfo = pathinfo($originalName);
        $extension = isset($pathInfo['extension']) ? '.' . strtolower($pathInfo['extension']) : '';
        $timestamp = time();
        $random = bin2hex(random_bytes(4));
        
        return "cv_{$this->userId}_{$timestamp}_{$random}{$extension}";
    }
    
    /**
     * Extract military-specific data from CV
     */
    private function extractMilitaryData($filepath, $fileType, $templateType) {
        $text = $this->extractText($filepath, $fileType);
        
        $extractedData = [
            'personal_info' => $this->extractPersonalInfo($text),
            'military_service' => $this->extractMilitaryService($text),
            'education' => $this->extractEducation($text),
            'training_certifications' => $this->extractTrainingCertifications($text),
            'deployments' => $this->extractDeployments($text),
            'awards_decorations' => $this->extractAwardsDecorations($text),
            'security_clearance' => $this->extractSecurityClearance($text),
            'skills' => $this->extractSkills($text),
            'languages' => $this->extractLanguages($text),
            'emergency_contacts' => $this->extractEmergencyContacts($text)
        ];
        
        // Apply template-specific processing
        if ($templateType) {
            $extractedData = $this->applyTemplateSpecificProcessing($extractedData, $templateType);
        }
        
        return $extractedData;
    }
    
    /**
     * Enhanced text extraction with better parsing
     */
    private function extractText($filepath, $fileType) {
        $text = '';
        
        try {
            switch ($fileType) {
                case 'application/pdf':
                    $text = $this->extractTextFromPDF($filepath);
                    break;
                    
                case 'application/msword':
                case 'application/vnd.openxmlformats-officedocument.wordprocessingml.document':
                    $text = $this->extractTextFromWord($filepath);
                    break;
                    
                case 'text/plain':
                    $text = file_get_contents($filepath);
                    break;
            }
            
            // Clean and normalize text
            $text = $this->cleanText($text);
            
        } catch (Exception $e) {
            error_log("Text extraction error: " . $e->getMessage());
        }
        
        return $text;
    }
    
    /**
     * Extract text from PDF with better parsing
     */
    private function extractTextFromPDF($filepath) {
        // Basic PDF text extraction (in production, use libraries like pdf-parser or pdftotext)
        if (function_exists('shell_exec') && !empty(shell_exec('which pdftotext'))) {
            $text = shell_exec("pdftotext '$filepath' -");
            return $text ?: '';
        }
        
        // Fallback: basic binary read (very limited)
        $content = file_get_contents($filepath);
        if (preg_match_all('/\(([^)]+)\)/', $content, $matches)) {
            return implode(' ', $matches[1]);
        }
        
        return '';
    }
    
    /**
     * Extract text from Word documents
     */
    private function extractTextFromWord($filepath) {
        // For .docx files, extract from XML
        if (pathinfo($filepath, PATHINFO_EXTENSION) === 'docx') {
            return $this->extractFromDocx($filepath);
        }
        
        // For .doc files, try basic extraction
        $content = file_get_contents($filepath);
        
        // Remove binary characters and extract readable text
        $content = preg_replace('/[^\x20-\x7E\x0A\x0D]/', ' ', $content);
        $content = preg_replace('/\s+/', ' ', $content);
        
        return trim($content);
    }
    
    /**
     * Extract from DOCX files
     */
    private function extractFromDocx($filepath) {
        $text = '';
        
        try {
            $zip = new ZipArchive();
            if ($zip->open($filepath) === TRUE) {
                $xml = $zip->getFromName('word/document.xml');
                if ($xml !== false) {
                    $dom = new DOMDocument();
                    $dom->loadXML($xml);
                    $text = $dom->textContent;
                }
                $zip->close();
            }
        } catch (Exception $e) {
            error_log("DOCX extraction error: " . $e->getMessage());
        }
        
        return $text;
    }
    
    /**
     * Clean and normalize extracted text
     */
    private function cleanText($text) {
        // Remove excessive whitespace
        $text = preg_replace('/\s+/', ' ', $text);
        
        // Normalize line endings
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        
        // Remove non-printable characters
        $text = preg_replace('/[^\x20-\x7E\x0A\x0D]/', ' ', $text);
        
        return trim($text);
    }
    
    /**
     * Extract personal information
     */
    private function extractPersonalInfo($text) {
        $info = [];
        
        // Extract name (look for common patterns)
        if (preg_match('/(?:name|NAME)[\s:]*([A-Z][a-z]+(?:\s+[A-Z][a-z]+)+)/i', $text, $matches)) {
            $info['full_name'] = trim($matches[1]);
        }
        
        // Extract email
        if (preg_match('/([a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,})/', $text, $matches)) {
            $info['email'] = $matches[1];
        }
        
        // Extract phone numbers
        if (preg_match('/(?:phone|tel|mobile)[\s:]*([+]?[\d\s\-\(\)]{10,})/i', $text, $matches)) {
            $info['phone'] = preg_replace('/[^\d+]/', '', $matches[1]);
        }
        
        return $info;
    }
    
    /**
     * Extract military service information
     */
    private function extractMilitaryService($text) {
        $service = [];
        
        // Extract service number
        if (preg_match('/(?:service\s+number|svc\s+no|staff\s+no)[\s:]*([A-Z0-9]+)/i', $text, $matches)) {
            $service['service_number'] = $matches[1];
        }
        
        // Extract rank
        $ranks = ['private', 'corporal', 'sergeant', 'lieutenant', 'captain', 'major', 'colonel', 'general'];
        foreach ($ranks as $rank) {
            if (preg_match('/\b' . $rank . '\b/i', $text)) {
                $service['rank'] = ucfirst($rank);
                break;
            }
        }
        
        // Extract unit
        if (preg_match('/(?:unit|battalion|regiment|company)[\s:]*([A-Z0-9\s]+)/i', $text, $matches)) {
            $service['unit'] = trim($matches[1]);
        }
        
        return $service;
    }
    
    /**
     * Extract education information
     */
    private function extractEducation($text) {
        $education = [];
        
        // Extract degrees
        $degrees = ['bachelor', 'master', 'phd', 'diploma', 'certificate'];
        foreach ($degrees as $degree) {
            if (preg_match('/\b' . $degree . '(?:\s+of\s+|\s+in\s+)([A-Z][a-z\s]+)/i', $text, $matches)) {
                $education[] = [
                    'degree' => ucfirst($degree),
                    'field' => trim($matches[1])
                ];
            }
        }
        
        return $education;
    }
    
    /**
     * Extract training and certifications
     */
    private function extractTrainingCertifications($text) {
        $training = [];
        
        // Look for common military training patterns
        $patterns = [
            '/(?:combat\s+training|basic\s+training|advanced\s+training)([^.]*)/i',
            '/(?:certification|certified)[\s:]*([A-Z][a-z\s]+)/i',
            '/(?:course|training)[\s:]*([A-Z][a-z\s]+)/i'
        ];
        
        foreach ($patterns as $pattern) {
            if (preg_match_all($pattern, $text, $matches)) {
                foreach ($matches[1] as $match) {
                    $training[] = trim($match);
                }
            }
        }
        
        return array_unique($training);
    }
    
    /**
     * Extract deployment information
     */
    private function extractDeployments($text) {
        $deployments = [];
        
        // Look for deployment patterns
        if (preg_match_all('/(?:deployed\s+to|deployment\s+in)[\s]*([A-Z][a-z\s,]+)/i', $text, $matches)) {
            foreach ($matches[1] as $deployment) {
                $deployments[] = trim($deployment);
            }
        }
        
        return $deployments;
    }
    
    /**
     * Extract awards and decorations
     */
    private function extractAwardsDecorations($text) {
        $awards = [];
        
        // Common military awards
        $awardPatterns = [
            'medal', 'ribbon', 'commendation', 'achievement', 'service', 'bronze star', 'silver star', 'purple heart'
        ];
        
        foreach ($awardPatterns as $pattern) {
            if (preg_match_all('/\b' . $pattern . '(?:\s+[A-Z][a-z\s]*)?/i', $text, $matches)) {
                foreach ($matches[0] as $award) {
                    $awards[] = trim($award);
                }
            }
        }
        
        return array_unique($awards);
    }
    
    /**
     * Extract security clearance information
     */
    private function extractSecurityClearance($text) {
        $clearance = [];
        
        $levels = ['confidential', 'secret', 'top secret', 'sci', 'cosmic top secret'];
        foreach ($levels as $level) {
            if (preg_match('/\b' . $level . '\s+clearance\b/i', $text)) {
                $clearance['level'] = ucwords($level);
                break;
            }
        }
        
        return $clearance;
    }
    
    /**
     * Extract skills
     */
    private function extractSkills($text) {
        $skills = [];
        
        // Look for skills section
        if (preg_match('/(?:skills|competencies|abilities)[\s:]*([^.]*)/i', $text, $matches)) {
            $skillText = $matches[1];
            $skills = array_map('trim', preg_split('/[,;\n]/', $skillText));
            $skills = array_filter($skills, function($skill) {
                return !empty($skill) && strlen($skill) > 2;
            });
        }
        
        return array_values($skills);
    }
    
    /**
     * Extract languages
     */
    private function extractLanguages($text) {
        $languages = [];
        
        $commonLanguages = ['english', 'spanish', 'french', 'german', 'arabic', 'chinese', 'japanese', 'korean'];
        foreach ($commonLanguages as $lang) {
            if (preg_match('/\b' . $lang . '\b/i', $text)) {
                $languages[] = ucfirst($lang);
            }
        }
        
        return array_unique($languages);
    }
    
    /**
     * Extract emergency contacts
     */
    private function extractEmergencyContacts($text) {
        $contacts = [];
        
        if (preg_match('/(?:emergency\s+contact|next\s+of\s+kin)[\s:]*([^.]*)/i', $text, $matches)) {
            $contactText = $matches[1];
            
            // Extract name and phone from contact text
            if (preg_match('/([A-Z][a-z\s]+)[\s,]*([+]?[\d\s\-\(\)]{10,})/', $contactText, $contactMatches)) {
                $contacts[] = [
                    'name' => trim($contactMatches[1]),
                    'phone' => preg_replace('/[^\d+]/', '', $contactMatches[2])
                ];
            }
        }
        
        return $contacts;
    }
    
    /**
     * Apply template-specific processing
     */
    private function applyTemplateSpecificProcessing($data, $templateType) {
        switch ($templateType) {
            case self::TEMPLATE_OFFICER:
                $data['leadership_experience'] = $this->extractLeadershipExperience($data);
                $data['command_positions'] = $this->extractCommandPositions($data);
                break;
                
            case self::TEMPLATE_NCO:
                $data['supervisory_experience'] = $this->extractSupervisoryExperience($data);
                $data['technical_expertise'] = $this->extractTechnicalExpertise($data);
                break;
                
            case self::TEMPLATE_ENLISTED:
                $data['technical_skills'] = $this->extractTechnicalSkills($data);
                $data['specializations'] = $this->extractSpecializations($data);
                break;
        }
        
        return $data;
    }
    
    /**
     * Extract leadership experience (for officers)
     */
    private function extractLeadershipExperience($data) {
        // Implementation for extracting leadership-specific information
        return [];
    }
    
    /**
     * Extract command positions
     */
    private function extractCommandPositions($data) {
        // Implementation for extracting command positions
        return [];
    }
    
    /**
     * Extract supervisory experience (for NCOs)
     */
    private function extractSupervisoryExperience($data) {
        // Implementation for extracting supervisory experience
        return [];
    }
    
    /**
     * Extract technical expertise
     */
    private function extractTechnicalExpertise($data) {
        // Implementation for extracting technical expertise
        return [];
    }
    
    /**
     * Extract technical skills (for enlisted)
     */
    private function extractTechnicalSkills($data) {
        // Implementation for extracting technical skills
        return [];
    }
    
    /**
     * Extract specializations
     */
    private function extractSpecializations($data) {
        // Implementation for extracting specializations
        return [];
    }
    
    /**
     * Create CV record with version control
     */
    private function createCVRecord($filename, $file, $extractedData, $templateType) {
        try {
            // Check if table exists, create if not
            $this->ensureCVTable();
            
            $stmt = $this->pdo->prepare("
                INSERT INTO staff_cvs (
                    staff_id, filename, original_name, file_type, file_size, 
                    extracted_data, template_type, status, version, 
                    upload_date, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1, NOW(), NOW())
            ");
            
            $stmt->execute([
                $this->userId,
                $filename,
                $file['name'],
                $file['type'],
                $file['size'],
                json_encode($extractedData),
                $templateType ?: self::TEMPLATE_CIVILIAN,
                self::STATUS_DRAFT
            ]);
            
            return $this->pdo->lastInsertId();
            
        } catch (PDOException $e) {
            error_log("Error creating CV record: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Ensure CV table exists
     */
    private function ensureCVTable() {
        try {
            $sql = "
                CREATE TABLE IF NOT EXISTS staff_cvs (
                    id INT PRIMARY KEY AUTO_INCREMENT,
                    staff_id INT NOT NULL,
                    filename VARCHAR(255) NOT NULL,
                    original_name VARCHAR(255) NOT NULL,
                    file_type VARCHAR(100) NOT NULL,
                    file_size INT NOT NULL,
                    extracted_data TEXT,
                    template_type ENUM('officer', 'nco', 'enlisted', 'civilian') DEFAULT 'civilian',
                    status ENUM('draft', 'pending_approval', 'approved', 'rejected') DEFAULT 'draft',
                    version INT DEFAULT 1,
                    approved_by INT NULL,
                    approved_at TIMESTAMP NULL,
                    upload_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    FOREIGN KEY (staff_id) REFERENCES staff(id) ON DELETE CASCADE,
                    FOREIGN KEY (approved_by) REFERENCES staff(id) ON DELETE SET NULL,
                    INDEX idx_staff_id (staff_id),
                    INDEX idx_status (status),
                    INDEX idx_template_type (template_type)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ";
            
            $this->pdo->exec($sql);
            
        } catch (PDOException $e) {
            error_log("Error creating CV table: " . $e->getMessage());
        }
    }
    
    /**
     * Generate military CV from template
     */
    public function generateMilitaryCV($cvId, $templateType, $format = 'pdf') {
        try {
            // Get CV data
            $cvData = $this->getCVData($cvId);
            if (!$cvData) {
                return ['success' => false, 'message' => 'CV not found'];
            }
            
            // Generate CV based on template and format
            switch ($format) {
                case 'pdf':
                    return $this->generatePDF($cvData, $templateType);
                case 'html':
                    return $this->generateHTML($cvData, $templateType);
                default:
                    return ['success' => false, 'message' => 'Unsupported format'];
            }
            
        } catch (Exception $e) {
            error_log("CV generation error: " . $e->getMessage());
            return ['success' => false, 'message' => 'CV generation failed'];
        }
    }
    
    /**
     * Get CV data by ID
     */
    private function getCVData($cvId) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT cv.*, s.first_name, s.last_name, s.service_number, s.rank_id, s.unit_id
                FROM staff_cvs cv
                JOIN staff s ON cv.staff_id = s.id
                WHERE cv.id = ? AND cv.staff_id = ?
            ");
            $stmt->execute([$cvId, $this->userId]);
            
            $cvRecord = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($cvRecord) {
                $cvRecord['extracted_data'] = json_decode($cvRecord['extracted_data'], true);
            }
            
            return $cvRecord;
            
        } catch (PDOException $e) {
            error_log("Error getting CV data: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Generate PDF CV (simplified implementation)
     */
    private function generatePDF($cvData, $templateType) {
        // In production, use libraries like TCPDF, mPDF, or Dompdf
        // This is a simplified implementation
        
        $html = $this->generateHTML($cvData, $templateType)['content'];
        $filename = "military_cv_{$cvData['staff_id']}_" . time() . ".pdf";
        $filepath = $this->uploadDir . $filename;
        
        // Simple HTML to text conversion for demo
        $text = strip_tags(html_entity_decode($html));
        file_put_contents($filepath, $text);
        
        return [
            'success' => true,
            'filename' => $filename,
            'filepath' => $filepath
        ];
    }
    
    /**
     * Generate HTML CV
     */
    private function generateHTML($cvData, $templateType) {
        $extractedData = $cvData['extracted_data'];
        
        $html = $this->getTemplateHeader($templateType);
        $html .= $this->generatePersonalSection($extractedData);
        $html .= $this->generateMilitarySection($extractedData);
        $html .= $this->generateEducationSection($extractedData);
        $html .= $this->generateSkillsSection($extractedData);
        $html .= $this->getTemplateFooter();
        
        return [
            'success' => true,
            'content' => $html
        ];
    }
    
    /**
     * Get template header
     */
    private function getTemplateHeader($templateType) {
        $title = match($templateType) {
            self::TEMPLATE_OFFICER => 'Officer Curriculum Vitae',
            self::TEMPLATE_NCO => 'Non-Commissioned Officer Curriculum Vitae',
            self::TEMPLATE_ENLISTED => 'Enlisted Personnel Curriculum Vitae',
            default => 'Military Curriculum Vitae'
        };
        
        return "
        <!DOCTYPE html>
        <html>
        <head>
            <title>{$title}</title>
            <style>
                body { font-family: 'Times New Roman', serif; margin: 20px; }
                .header { text-align: center; border-bottom: 2px solid #000; padding-bottom: 10px; }
                .section { margin: 20px 0; }
                .section-title { font-weight: bold; font-size: 14px; border-bottom: 1px solid #ccc; }
                .military-header { background: #2c5530; color: white; padding: 10px; text-align: center; }
            </style>
        </head>
        <body>
            <div class='military-header'>
                <h1>{$title}</h1>
                <p>CLASSIFIED ACCORDING TO MILITARY STANDARDS</p>
            </div>
        ";
    }
    
    /**
     * Generate personal information section
     */
    private function generatePersonalSection($data) {
        $personal = $data['personal_info'] ?? [];
        
        return "
        <div class='section'>
            <div class='section-title'>PERSONAL INFORMATION</div>
            <p><strong>Name:</strong> " . ($personal['full_name'] ?? 'N/A') . "</p>
            <p><strong>Email:</strong> " . ($personal['email'] ?? 'N/A') . "</p>
            <p><strong>Phone:</strong> " . ($personal['phone'] ?? 'N/A') . "</p>
        </div>
        ";
    }
    
    /**
     * Generate military service section
     */
    private function generateMilitarySection($data) {
        $military = $data['military_service'] ?? [];
        
        return "
        <div class='section'>
            <div class='section-title'>MILITARY SERVICE</div>
            <p><strong>Service Number:</strong> " . ($military['service_number'] ?? 'N/A') . "</p>
            <p><strong>Rank:</strong> " . ($military['rank'] ?? 'N/A') . "</p>
            <p><strong>Unit:</strong> " . ($military['unit'] ?? 'N/A') . "</p>
        </div>
        ";
    }
    
    /**
     * Generate education section
     */
    private function generateEducationSection($data) {
        $education = $data['education'] ?? [];
        
        $html = "
        <div class='section'>
            <div class='section-title'>EDUCATION</div>
        ";
        
        if (!empty($education)) {
            foreach ($education as $edu) {
                $html .= "<p><strong>" . ($edu['degree'] ?? '') . "</strong> in " . ($edu['field'] ?? '') . "</p>";
            }
        } else {
            $html .= "<p>No education information available</p>";
        }
        
        $html .= "</div>";
        return $html;
    }
    
    /**
     * Generate skills section
     */
    private function generateSkillsSection($data) {
        $skills = $data['skills'] ?? [];
        
        $html = "
        <div class='section'>
            <div class='section-title'>SKILLS & COMPETENCIES</div>
        ";
        
        if (!empty($skills)) {
            $html .= "<ul>";
            foreach ($skills as $skill) {
                $html .= "<li>" . htmlspecialchars($skill) . "</li>";
            }
            $html .= "</ul>";
        } else {
            $html .= "<p>No skills information available</p>";
        }
        
        $html .= "</div>";
        return $html;
    }
    
    /**
     * Get template footer
     */
    private function getTemplateFooter() {
        return "
            <div class='section' style='text-align: center; margin-top: 40px; font-size: 12px; color: #666;'>
                <p>Generated by ARMIS - Army Resource Management Information System</p>
                <p>Document generated on " . date('Y-m-d H:i:s') . "</p>
            </div>
        </body>
        </html>
        ";
    }
    
    /**
     * Approve CV document
     */
    public function approveCV($cvId, $approverId) {
        try {
            $stmt = $this->pdo->prepare("
                UPDATE staff_cvs 
                SET status = ?, approved_by = ?, approved_at = NOW()
                WHERE id = ? AND staff_id = ?
            ");
            
            $result = $stmt->execute([self::STATUS_APPROVED, $approverId, $cvId, $this->userId]);
            
            if ($result) {
                $this->auditService->logActivity(
                    $approverId,
                    'cv_approval',
                    'CV document approved',
                    'cv_document',
                    $cvId
                );
            }
            
            return $result;
            
        } catch (PDOException $e) {
            error_log("Error approving CV: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get available military templates
     */
    public function getAvailableTemplates() {
        return [
            self::TEMPLATE_OFFICER => [
                'name' => 'Officer Template',
                'description' => 'For commissioned officers and warrant officers',
                'features' => ['Leadership Experience', 'Command Positions', 'Strategic Planning']
            ],
            self::TEMPLATE_NCO => [
                'name' => 'NCO Template', 
                'description' => 'For non-commissioned officers',
                'features' => ['Supervisory Experience', 'Technical Expertise', 'Training Management']
            ],
            self::TEMPLATE_ENLISTED => [
                'name' => 'Enlisted Template',
                'description' => 'For enlisted personnel',
                'features' => ['Technical Skills', 'Specializations', 'Operational Experience']
            ],
            self::TEMPLATE_CIVILIAN => [
                'name' => 'Civilian Template',
                'description' => 'For civilian personnel',
                'features' => ['Professional Experience', 'Certifications', 'Administrative Skills']
            ]
        ];
    }
}