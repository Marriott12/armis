<?php
/**
 * User Profile Manager Class
 * Handles all user profile data operations
 */

require_once dirname(__DIR__) . '/shared/database_connection.php';

class UserProfileManager {
    private $pdo;
    private $userId;
    private $userSvcNo;
    
    public function __construct($userId) {
        $this->pdo = getDbConnection();
        $this->userId = $userId;
        $this->loadUserInfo();
    }
    
    /**
     * Load basic user information
     */
    private function loadUserInfo() {
        try {
            // Try different possible column names for service number
            $possibleColumns = ['svcNo', 'service_number', 'serviceNo', 'service_no', 'svc_no', 'svc_number', 'staff_number', 'emp_no', 'employee_number'];
            
            foreach ($possibleColumns as $column) {
                try {
                    $stmt = $this->pdo->prepare("SELECT $column FROM staff WHERE id = ? LIMIT 1");
                    $stmt->execute([$this->userId]);
                    $user = $stmt->fetch(PDO::FETCH_OBJ);
                    if ($user && isset($user->$column)) {
                        $this->userSvcNo = $user->$column;
                        return; // Found it, exit
                    }
                } catch (PDOException $e) {
                    // Column doesn't exist, try next one
                    continue;
                }
            }
            
            // Fallback - use user ID if no service number column found
            $this->userSvcNo = 'USER_' . $this->userId;
            error_log("No service number column found for user $this->userId, using fallback: $this->userSvcNo");
            
        } catch (PDOException $e) {
            error_log("Error loading user info: " . $e->getMessage());
            $this->userSvcNo = 'USER_' . $this->userId;
        }
    }
    
    /**
     * Get comprehensive user profile data
     */
    public function getUserProfile() {
        try {
            // Main profile data with rank and unit information
            $stmt = $this->pdo->prepare("
                SELECT s.*, r.name as rankName, r.abbreviation as rankAbbr, 
                       u.name as unitName, u.code as unitCode, u.type as unitType,
                       c.name as corps_name
                FROM staff s 
                LEFT JOIN ranks r ON s.rank_id = r.id
                LEFT JOIN units u ON s.unit_id = u.id
                LEFT JOIN corps c ON s.corps = c.abbreviation
                WHERE s.id = ?
            ");
            $stmt->execute([$this->userId]);
            $profile = $stmt->fetch(PDO::FETCH_OBJ);
            
            if (!$profile) {
                return null;
            }
            
            // Add calculated fields with proper field mapping
            $profile->age = $this->calculateAge($profile->DOB);
            $profile->serviceYears = $this->calculateServiceYears($profile->attestDate);
            $profile->fullName = trim(($profile->prefix ?? '') . ' ' . $profile->first_name . ' ' . $profile->last_name);
            $profile->displayRank = $profile->rankName ?? $profile->rankAbbr ?? 'N/A';
            
            // Add legacy field mappings for compatibility
            $profile->fname = $profile->first_name;
            $profile->lname = $profile->last_name;
            $profile->svcNo = $profile->service_number;
            $profile->rankID = $profile->rank_id;
            $profile->unitID = $profile->unit_id;
            
            return $profile;
            
        } catch (PDOException $e) {
            error_log("Error fetching user profile: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Get user's education records
     */
    public function getEducationRecords() {
        try {
            $stmt = $this->pdo->prepare("
                SELECT * FROM staff_education 
                WHERE staff_id = ? 
                ORDER BY year_completed DESC, level DESC
            ");
            $stmt->execute([$this->userId]);
            return $stmt->fetchAll(PDO::FETCH_OBJ);
        } catch (PDOException $e) {
            error_log("Error fetching education records: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Upload and process profile photo
     */
    public function uploadProfilePhoto($file) {
        try {
            // Validate file
            if (!isset($file['tmp_name']) || empty($file['tmp_name'])) {
                return ['success' => false, 'message' => 'No file uploaded'];
            }
            
            // Check file type
            $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png'];
            if (!in_array($file['type'], $allowedTypes)) {
                return ['success' => false, 'message' => 'Only JPEG and PNG files are allowed'];
            }
            
            // Check file size (max 5MB)
            if ($file['size'] > 5 * 1024 * 1024) {
                return ['success' => false, 'message' => 'File size must be less than 5MB'];
            }
            
            // Get user's service number for filename
            if (empty($this->userSvcNo)) {
                $this->loadUserInfo(); // Refresh user info
            }
            
            if (empty($this->userSvcNo)) {
                return ['success' => false, 'message' => 'User service number not available'];
            }
            
            // Create upload directory if it doesn't exist
            $uploadDir = dirname(__DIR__) . '/uploads/profile_photos/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            
            // Generate filename using service number
            $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
            $filename = $this->userSvcNo . '.' . $extension;
            $filepath = $uploadDir . $filename;
            
            // Remove any existing profile photos for this user
            $existingFiles = glob($uploadDir . $this->userSvcNo . '.*');
            foreach ($existingFiles as $existingFile) {
                if (is_file($existingFile)) {
                    unlink($existingFile);
                }
            }
            
            // Move uploaded file
            if (move_uploaded_file($file['tmp_name'], $filepath)) {
                // Try to update database - handle missing column gracefully
                try {
                    $stmt = $this->pdo->prepare("UPDATE staff SET profile_photo = ? WHERE id = ?");
                    $stmt->execute([$filename, $this->userId]);
                } catch (PDOException $e) {
                    // Column might not exist - that's OK, we'll use file-based system
                    error_log("Profile photo column not found, using file-based system: " . $e->getMessage());
                }
                
                return ['success' => true, 'message' => 'Profile photo updated successfully', 'filename' => $filename];
            } else {
                return ['success' => false, 'message' => 'Failed to upload file'];
            }
            
        } catch (Exception $e) {
            error_log("Error uploading profile photo: " . $e->getMessage());
            return ['success' => false, 'message' => 'Upload failed: ' . $e->getMessage()];
        }
    }
    
    /**
     * Upload and process CV file
     */
    public function uploadCV($file) {
        try {
            // Validate file
            if (!isset($file['tmp_name']) || empty($file['tmp_name'])) {
                return ['success' => false, 'message' => 'No CV file uploaded'];
            }
            
            // Check file type
            $allowedTypes = ['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
            if (!in_array($file['type'], $allowedTypes)) {
                return ['success' => false, 'message' => 'Only PDF and Word documents are allowed'];
            }
            
            // Check file size (max 10MB)
            if ($file['size'] > 10 * 1024 * 1024) {
                return ['success' => false, 'message' => 'CV file size must be less than 10MB'];
            }
            
            // Create upload directory if it doesn't exist
            $uploadDir = dirname(__DIR__) . '/uploads/cvs/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            
            // Generate unique filename
            $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
            $filename = 'cv_' . $this->userId . '_' . time() . '.' . $extension;
            $filepath = $uploadDir . $filename;
            
            // Move uploaded file
            if (move_uploaded_file($file['tmp_name'], $filepath)) {
                // Extract CV data
                $extractedData = $this->extractCVData($filepath, $file['type']);
                
                // Store CV record
                $stmt = $this->pdo->prepare("
                    INSERT INTO staff_cvs (staff_id, filename, original_name, file_type, file_size, extracted_data, upload_date) 
                    VALUES (?, ?, ?, ?, ?, ?, NOW())
                ");
                $stmt->execute([
                    $this->userId, 
                    $filename, 
                    $file['name'], 
                    $file['type'], 
                    $file['size'], 
                    json_encode($extractedData)
                ]);
                
                $cvId = $this->pdo->lastInsertId();
                
                return [
                    'success' => true, 
                    'message' => 'CV uploaded successfully', 
                    'id' => $cvId,
                    'filename' => $filename,
                    'extracted_data' => $extractedData
                ];
            } else {
                return ['success' => false, 'message' => 'Failed to upload CV file'];
            }
            
        } catch (Exception $e) {
            error_log("Error uploading CV: " . $e->getMessage());
            return ['success' => false, 'message' => 'CV upload failed: ' . $e->getMessage()];
        }
    }
    
    /**
     * Extract data from CV file
     */
    private function extractCVData($filepath, $fileType) {
        $extractedData = [
            'education' => [],
            'experience' => [],
            'skills' => [],
            'contact' => [],
            'certifications' => []
        ];
        
        try {
            $text = '';
            
            // Extract text based on file type
            if ($fileType === 'application/pdf') {
                $text = $this->extractTextFromPDF($filepath);
            } elseif (in_array($fileType, ['application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'])) {
                $text = $this->extractTextFromWord($filepath);
            }
            
            if (!empty($text)) {
                $extractedData = $this->parseCV($text);
            }
            
        } catch (Exception $e) {
            error_log("Error extracting CV data: " . $e->getMessage());
        }
        
        return $extractedData;
    }
    
    /**
     * Extract text from PDF (basic implementation)
     */
    private function extractTextFromPDF($filepath) {
        // This is a basic implementation - in production you might use a library like pdf-parser
        $text = '';
        
        // Try using pdftotext if available
        if (function_exists('shell_exec')) {
            $output = shell_exec("pdftotext '$filepath' -");
            if ($output) {
                $text = $output;
            }
        }
        
        return $text;
    }
    
    /**
     * Extract text from Word document (basic implementation)
     */
    private function extractTextFromWord($filepath) {
        $text = '';
        
        // Basic text extraction - in production you might use PHPWord or similar
        if (pathinfo($filepath, PATHINFO_EXTENSION) === 'docx') {
            // Try to extract from docx using zip
            try {
                $zip = new ZipArchive();
                if ($zip->open($filepath)) {
                    $xmlString = $zip->getFromName('word/document.xml');
                    $zip->close();
                    
                    if ($xmlString) {
                        // Remove XML tags and get text
                        $text = strip_tags($xmlString);
                        $text = preg_replace('/\s+/', ' ', $text);
                    }
                }
            } catch (Exception $e) {
                error_log("Error extracting Word text: " . $e->getMessage());
            }
        }
        
        return $text;
    }
    
    /**
     * Parse CV text to extract structured data
     */
    private function parseCV($text) {
        $data = [
            'personal' => [],
            'contact' => [],
            'education' => [],
            'experience' => [],
            'skills' => [],
            'certifications' => []
        ];
        
        // Extract email addresses
        if (preg_match_all('/\b[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Z|a-z]{2,}\b/', $text, $emails)) {
            foreach ($emails[0] as $email) {
                $data['contact']['email'] = $email;
                break; // Take the first email found
            }
        }
        
        // Extract phone numbers (basic pattern)
        if (preg_match_all('/(?:\+\d{1,3}\s?)?\(?\d{3}\)?[-.\s]?\d{3}[-.\s]?\d{4}/', $text, $phones)) {
            foreach ($phones[0] as $phone) {
                $data['contact']['phone'] = trim($phone);
                break; // Take the first phone found
            }
        }
        
        // Extract name (look for patterns at the beginning of the document)
        $lines = explode("\n", $text);
        $firstLines = array_slice($lines, 0, 10);
        foreach ($firstLines as $line) {
            $line = trim($line);
            if (preg_match('/^[A-Z][a-z]+\s+[A-Z][a-z]+/', $line) && strlen($line) < 50) {
                $data['personal']['full_name'] = $line;
                break;
            }
        }
        
        // Extract education (look for degree keywords)
        $educationPatterns = [
            '/\b(?:Bachelor|Master|PhD|Doctorate|Diploma|Certificate).*?(?:\d{4}|\d{2}\/\d{2}\/\d{4})/i',
            '/\b(?:BSc|MSc|MBA|PhD|BA|MA).*?(?:\d{4}|\d{2}\/\d{2}\/\d{4})/i'
        ];
        
        foreach ($educationPatterns as $pattern) {
            if (preg_match_all($pattern, $text, $matches)) {
                foreach ($matches[0] as $match) {
                    $data['education'][] = [
                        'qualification' => trim($match),
                        'institution' => '',
                        'degree' => trim($match),
                        'year' => ''
                    ];
                }
            }
        }
        
        // Extract skills (look for common skill section keywords)
        if (preg_match('/(?:Skills|Competencies|Technical Skills):?\s*(.+?)(?:\n\n|\n[A-Z]|$)/is', $text, $skillsMatch)) {
            $skillsText = $skillsMatch[1];
            $skills = preg_split('/[,;•\n]/', $skillsText);
            foreach ($skills as $skill) {
                $skill = trim($skill);
                if (!empty($skill) && strlen($skill) > 2) {
                    $data['skills'][] = ['skill' => $skill, 'level' => 'Intermediate'];
                }
            }
        }
        
        // Extract work experience (basic pattern)
        if (preg_match_all('/(?:Experience|Employment|Work History):?\s*(.+?)(?:\n\n|\n[A-Z]|$)/is', $text, $expMatches)) {
            foreach ($expMatches[1] as $expText) {
                $lines = explode("\n", trim($expText));
                foreach ($lines as $line) {
                    $line = trim($line);
                    if (!empty($line) && strlen($line) > 10) {
                        $data['experience'][] = [
                            'position' => $line,
                            'company' => '',
                            'duration' => '',
                            'description' => $line
                        ];
                        break; // Just take the first meaningful line for now
                    }
                }
            }
        }
        
        return $data;
    }
    
    /**
     * Get user's training records
     */
    public function getTrainingRecords() {
        try {
            $stmt = $this->pdo->prepare("
                SELECT * FROM training_records 
                WHERE staff_id = ? 
                ORDER BY start_date DESC
            ");
            $stmt->execute([$this->userId]);
            return $stmt->fetchAll(PDO::FETCH_OBJ);
        } catch (PDOException $e) {
            error_log("Error fetching training records: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get user's family members
     */
    public function getFamilyMembers() {
        try {
            $stmt = $this->pdo->prepare("
                SELECT * FROM staff_family_members 
                WHERE staff_id = ? 
                ORDER BY is_emergency_contact DESC, relationship ASC
            ");
            $stmt->execute([$this->userId]);
            return $stmt->fetchAll(PDO::FETCH_OBJ);
        } catch (PDOException $e) {
            error_log("Error fetching family members: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get user's contact information
     */
    public function getContactInfo() {
        try {
            $stmt = $this->pdo->prepare("
                SELECT * FROM staff_contact_info 
                WHERE staff_id = ? 
                ORDER BY is_primary DESC, contact_type ASC
            ");
            $stmt->execute([$this->userId]);
            return $stmt->fetchAll(PDO::FETCH_OBJ);
        } catch (PDOException $e) {
            error_log("Error fetching contact info: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get user's addresses
     */
    public function getAddresses() {
        try {
            $stmt = $this->pdo->prepare("
                SELECT * FROM staff_addresses 
                WHERE staff_id = ? 
                ORDER BY is_primary DESC, address_type ASC
            ");
            $stmt->execute([$this->userId]);
            return $stmt->fetchAll(PDO::FETCH_OBJ);
        } catch (PDOException $e) {
            error_log("Error fetching addresses: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get user's skills and competencies
     */
    public function getSkills() {
        try {
            $stmt = $this->pdo->prepare("
                SELECT * FROM staff_skills 
                WHERE staff_id = ? 
                ORDER BY skill_category ASC, proficiency_level DESC
            ");
            $stmt->execute([$this->userId]);
            return $stmt->fetchAll(PDO::FETCH_OBJ);
        } catch (PDOException $e) {
            error_log("Error fetching skills: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get user's awards and decorations
     */
    public function getAwards() {
        try {
            $stmt = $this->pdo->prepare("
                SELECT * FROM staff_awards 
                WHERE staff_id = ? 
                ORDER BY date_awarded DESC
            ");
            $stmt->execute([$this->userId]);
            return $stmt->fetchAll(PDO::FETCH_OBJ);
        } catch (PDOException $e) {
            error_log("Error fetching awards: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get user's deployment history
     */
    public function getDeployments() {
        try {
            $stmt = $this->pdo->prepare("
                SELECT * FROM staff_deployments 
                WHERE staff_id = ? 
                ORDER BY start_date DESC
            ");
            $stmt->execute([$this->userId]);
            return $stmt->fetchAll(PDO::FETCH_OBJ);
        } catch (PDOException $e) {
            error_log("Error fetching deployments: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get user's service history and promotions
     */
    public function getServiceHistory() {
        try {
            // Get promotion history from audit log or dedicated table
            $stmt = $this->pdo->prepare("
                SELECT 
                    'Promotion' as record_type,
                    CONCAT('Promoted to ', new_value) as description,
                    created_at as record_date,
                    new_value as rank_name
                FROM audit_log 
                WHERE staff_id = ? AND field_name = 'rankID' AND action = 'update'
                
                UNION ALL
                
                SELECT 
                    'Enlistment' as record_type,
                    'Initial Enlistment' as description,
                    attestDate as record_date,
                    'Recruit' as rank_name
                FROM staff 
                WHERE id = ? AND attestDate IS NOT NULL
                
                ORDER BY record_date ASC
            ");
            $stmt->execute([$this->userId, $this->userId]);
            return $stmt->fetchAll(PDO::FETCH_OBJ);
        } catch (PDOException $e) {
            // Fallback to basic service info if audit log doesn't exist
            try {
                $stmt = $this->pdo->prepare("
                    SELECT 
                        'Enlistment' as record_type,
                        'Service Record' as description,
                        attestDate as record_date,
                        'Active Service' as rank_name
                    FROM staff 
                    WHERE id = ? AND attestDate IS NOT NULL
                ");
                $stmt->execute([$this->userId]);
                return $stmt->fetchAll(PDO::FETCH_OBJ);
            } catch (PDOException $e2) {
                error_log("Error fetching service history: " . $e2->getMessage());
                return [];
            }
        }
    }
    
    /**
     * Get user's medical information (if authorized)
     */
    public function getMedicalInfo($includeDetails = false) {
        try {
            $fields = "medical_category, fitness_status, last_medical_exam, next_medical_due";
            if ($includeDetails) {
                $fields .= ", blood_group, height, weight, bmi, allergies";
            }
            
            $stmt = $this->pdo->prepare("
                SELECT {$fields} FROM staff_medical_records 
                WHERE staff_id = ?
            ");
            $stmt->execute([$this->userId]);
            return $stmt->fetch(PDO::FETCH_OBJ);
        } catch (PDOException $e) {
            error_log("Error fetching medical info: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Get recent activity summary
     */
    public function getRecentActivity($limit = 10) {
        $activities = [];
        
        try {
            // Recent training completions
            $stmt = $this->pdo->prepare("
                SELECT 'Training Completed' as activity_type, course_name as details, 
                       end_date as activity_date, status
                FROM training_records 
                WHERE staff_id = ? AND status = 'completed' AND end_date IS NOT NULL
                ORDER BY end_date DESC LIMIT ?
            ");
            $stmt->execute([$this->userId, $limit]);
            $training = $stmt->fetchAll(PDO::FETCH_OBJ);
            
            foreach ($training as $t) {
                $activities[] = [
                    'date' => $t->activity_date,
                    'type' => $t->activity_type,
                    'description' => $t->details,
                    'status' => ucfirst($t->status),
                    'icon' => 'graduation-cap',
                    'color' => 'success'
                ];
            }
            
            // Recent profile updates (from audit log if available)
            $stmt = $this->pdo->prepare("
                SELECT action, created_at, 
                       CASE 
                           WHEN action = 'profile_update' THEN 'Profile Updated'
                           WHEN action = 'contact_update' THEN 'Contact Info Updated'
                           ELSE CONCAT(UPPER(SUBSTRING(action, 1, 1)), SUBSTRING(action, 2))
                       END as activity_type
                FROM audit_log 
                WHERE staff_id = ? AND table_name = 'staff'
                ORDER BY created_at DESC LIMIT ?
            ");
            $stmt->execute([$this->userId, $limit]);
            $updates = $stmt->fetchAll(PDO::FETCH_OBJ);
            
            foreach ($updates as $u) {
                $activities[] = [
                    'date' => $u->created_at,
                    'type' => $u->activity_type,
                    'description' => 'Personal information modified',
                    'status' => 'Updated',
                    'icon' => 'edit',
                    'color' => 'info'
                ];
            }
            
            // Sort by date and limit
            usort($activities, function($a, $b) {
                return strtotime($b['date']) - strtotime($a['date']);
            });
            
            return array_slice($activities, 0, $limit);
            
        } catch (PDOException $e) {
            error_log("Error fetching recent activity: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Update basic profile information
     */
    public function updateBasicInfo($data) {
        try {
            $allowedFields = ['email', 'tel', 'height', 'bloodGp', 'marital', 'religion'];
            $updateFields = [];
            $updateValues = [];
            
            foreach ($allowedFields as $field) {
                if (isset($data[$field]) && $data[$field] !== '') {
                    $updateFields[] = "{$field} = ?";
                    $updateValues[] = $data[$field];
                }
            }
            
            if (empty($updateFields)) {
                return ['success' => false, 'message' => 'No valid fields to update'];
            }
            
            $updateValues[] = $this->userId;
            $sql = "UPDATE staff SET " . implode(', ', $updateFields) . ", updated_at = NOW() WHERE id = ?";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($updateValues);
            
            // Log the update
            $this->logActivity('profile_update', 'Basic profile information updated');
            
            return ['success' => true, 'message' => 'Profile updated successfully'];
            
        } catch (PDOException $e) {
            error_log("Error updating profile: " . $e->getMessage());
            return ['success' => false, 'message' => 'Error updating profile'];
        }
    }
    
    /**
     * Update contact information
     */
    public function updateContactInfo($contactData) {
        try {
            $this->pdo->beginTransaction();
            
            // Clear existing contact info
            $stmt = $this->pdo->prepare("DELETE FROM staff_contact_info WHERE staff_id = ?");
            $stmt->execute([$this->userId]);
            
            // Insert new contact info
            $stmt = $this->pdo->prepare("
                INSERT INTO staff_contact_info (staff_id, contact_type, contact_value, is_primary, created_at) 
                VALUES (?, ?, ?, ?, NOW())
            ");
            
            foreach ($contactData as $contact) {
                if (!empty($contact['value'])) {
                    $stmt->execute([
                        $this->userId,
                        $contact['type'],
                        $contact['value'],
                        $contact['is_primary'] ?? false
                    ]);
                }
            }
            
            $this->pdo->commit();
            $this->logActivity('contact_update', 'Contact information updated');
            
            return ['success' => true, 'message' => 'Contact information updated successfully'];
            
        } catch (PDOException $e) {
            $this->pdo->rollBack();
            error_log("Error updating contact info: " . $e->getMessage());
            return ['success' => false, 'message' => 'Error updating contact information'];
        }
    }
    
    /**
     * Calculate age from date of birth
     */
    public function calculateAge($dob) {
        if (!$dob) return 'N/A';
        
        $dobDate = new DateTime($dob);
        $now = new DateTime();
        $age = $now->diff($dobDate)->y;
        
        return $age;
    }
    
    /**
     * Add new family member
     */
    public function addFamilyMember($familyData) {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO staff_family_members 
                (staff_id, name, relationship, date_of_birth, phone, email, address, 
                 is_emergency_contact, is_dependent, notes, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            
            $stmt->execute([
                $this->userId,
                $familyData['name'],
                $familyData['relationship'],
                $familyData['date_of_birth'] ?: null,
                $familyData['phone'],
                $familyData['email'],
                $familyData['address'],
                $familyData['is_emergency_contact'],
                $familyData['is_dependent'],
                $familyData['notes']
            ]);
            
            $this->logActivity('family_add', 'Added family member: ' . $familyData['name']);
            
            return ['success' => true, 'message' => 'Family member added successfully'];
            
        } catch (PDOException $e) {
            error_log("Error adding family member: " . $e->getMessage());
            return ['success' => false, 'message' => 'Error adding family member'];
        }
    }
    
    /**
     * Update family member
     */
    public function updateFamilyMember($memberId, $familyData) {
        try {
            // Verify the family member belongs to this user
            $stmt = $this->pdo->prepare("SELECT id FROM staff_family_members WHERE id = ? AND staff_id = ?");
            $stmt->execute([$memberId, $this->userId]);
            
            if (!$stmt->fetch()) {
                return ['success' => false, 'message' => 'Family member not found or unauthorized'];
            }
            
            $stmt = $this->pdo->prepare("
                UPDATE staff_family_members 
                SET name = ?, relationship = ?, date_of_birth = ?, phone = ?, email = ?, 
                    address = ?, is_emergency_contact = ?, is_dependent = ?, notes = ?, 
                    updated_at = NOW()
                WHERE id = ? AND staff_id = ?
            ");
            
            $stmt->execute([
                $familyData['name'],
                $familyData['relationship'],
                $familyData['date_of_birth'] ?: null,
                $familyData['phone'],
                $familyData['email'],
                $familyData['address'],
                $familyData['is_emergency_contact'],
                $familyData['is_dependent'],
                $familyData['notes'],
                $memberId,
                $this->userId
            ]);
            
            $this->logActivity('family_update', 'Updated family member: ' . $familyData['name']);
            
            return ['success' => true, 'message' => 'Family member updated successfully'];
            
        } catch (PDOException $e) {
            error_log("Error updating family member: " . $e->getMessage());
            return ['success' => false, 'message' => 'Error updating family member'];
        }
    }
    
    /**
     * Delete family member
     */
    public function deleteFamilyMember($memberId) {
        try {
            // Get family member name for logging
            $stmt = $this->pdo->prepare("SELECT name FROM staff_family_members WHERE id = ? AND staff_id = ?");
            $stmt->execute([$memberId, $this->userId]);
            $member = $stmt->fetch(PDO::FETCH_OBJ);
            
            if (!$member) {
                return ['success' => false, 'message' => 'Family member not found or unauthorized'];
            }
            
            $stmt = $this->pdo->prepare("DELETE FROM staff_family_members WHERE id = ? AND staff_id = ?");
            $stmt->execute([$memberId, $this->userId]);
            
            $this->logActivity('family_delete', 'Deleted family member: ' . $member->name);
            
            return ['success' => true, 'message' => 'Family member deleted successfully'];
            
        } catch (PDOException $e) {
            error_log("Error deleting family member: " . $e->getMessage());
            return ['success' => false, 'message' => 'Error deleting family member'];
        }
    }
    
    /**
     * Calculate years of service
     */
    private function calculateServiceYears($enlistmentDate) {
        if (!$enlistmentDate) return 'N/A';
        
        $enlistmentDateTime = new DateTime($enlistmentDate);
        $now = new DateTime();
        $service = $now->diff($enlistmentDateTime);
        
        $years = $service->y;
        $months = $service->m;
        
        if ($years > 0) {
            return $years . ' years' . ($months > 0 ? ", {$months} months" : '');
        } else {
            return $months . ' months';
        }
    }
    
    /**
     * Log user activity
     */
    private function logActivity($action, $description) {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO audit_log (staff_id, action, table_name, record_id, new_values, created_at) 
                VALUES (?, ?, 'staff', ?, ?, NOW())
            ");
            $stmt->execute([
                $this->userId,
                $action,
                $this->userId,
                json_encode(['description' => $description])
            ]);
        } catch (PDOException $e) {
            error_log("Error logging activity: " . $e->getMessage());
        }
    }
    
    /**
     * Get profile completion percentage
     */
    public function getProfileCompleteness() {
        $profile = $this->getUserProfile();
        if (!$profile) return 0;
        
        $requiredFields = [
            'fname', 'lname', 'DOB', 'gender', 'email', 'tel', 
            'rankID', 'unitID', 'NRC', 'bloodGp', 'marital'
        ];
        
        $completedFields = 0;
        foreach ($requiredFields as $field) {
            if (!empty($profile->$field)) {
                $completedFields++;
            }
        }
        
        // Bonus points for additional data
        $bonusPoints = 0;
        if ($this->getEducationRecords()) $bonusPoints += 10;
        if ($this->getTrainingRecords()) $bonusPoints += 10;
        if ($this->getFamilyMembers()) $bonusPoints += 10;
        if ($this->getContactInfo()) $bonusPoints += 5;
        if ($this->getAddresses()) $bonusPoints += 5;
        
        $basePercentage = ($completedFields / count($requiredFields)) * 80;
        $totalPercentage = min(100, $basePercentage + $bonusPoints);
        
        return round($totalPercentage);
    }
    
    /**
     * Get user's uploaded CVs
     */
    public function getUserCVs() {
        try {
            $stmt = $this->pdo->prepare("
                SELECT id, filename, original_name, file_type, file_size, extracted_data, upload_date, is_verified
                FROM staff_cvs 
                WHERE staff_id = ? 
                ORDER BY upload_date DESC
            ");
            $stmt->execute([$this->userId]);
            return $stmt->fetchAll(PDO::FETCH_OBJ);
        } catch (PDOException $e) {
            error_log("Error fetching user CVs: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get CV extracted data for verification
     */
    public function getCVData($cvId) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT extracted_data, filename, original_name
                FROM staff_cvs 
                WHERE id = ? AND staff_id = ?
            ");
            $stmt->execute([$cvId, $this->userId]);
            $result = $stmt->fetch(PDO::FETCH_OBJ);
            
            if ($result) {
                $result->extracted_data = json_decode($result->extracted_data, true);
                return $result;
            }
            
            return null;
        } catch (PDOException $e) {
            error_log("Error fetching CV data: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Apply verified CV data to profile
     */
    public function applyCVData($cvId, $verifiedData) {
        try {
            $this->pdo->beginTransaction();
            
            // Apply contact information
            if (isset($verifiedData['contact']) && !empty($verifiedData['contact'])) {
                foreach ($verifiedData['contact'] as $contact) {
                    if (!empty($contact['value'])) {
                        $stmt = $this->pdo->prepare("
                            INSERT INTO staff_contact_info (staff_id, contact_type, contact_value, is_primary) 
                            VALUES (?, ?, ?, 0)
                        ");
                        $stmt->execute([$this->userId, $contact['type'], $contact['value']]);
                    }
                }
            }
            
            // Apply education information
            if (isset($verifiedData['education']) && !empty($verifiedData['education'])) {
                foreach ($verifiedData['education'] as $education) {
                    if (!empty($education['qualification'])) {
                        $stmt = $this->pdo->prepare("
                            INSERT INTO staff_education (staff_id, qualification, institution, level, year_completed) 
                            VALUES (?, ?, ?, ?, ?)
                        ");
                        $stmt->execute([
                            $this->userId,
                            $education['qualification'],
                            $education['institution'] ?? 'Not specified',
                            $education['level'] ?? 'Other',
                            $education['year'] ?? date('Y')
                        ]);
                    }
                }
            }
            
            // Apply skills information
            if (isset($verifiedData['skills']) && !empty($verifiedData['skills'])) {
                foreach ($verifiedData['skills'] as $skill) {
                    if (!empty($skill['skill'])) {
                        $stmt = $this->pdo->prepare("
                            INSERT INTO staff_skills (staff_id, skill_name, skill_level) 
                            VALUES (?, ?, 'Intermediate')
                        ");
                        $stmt->execute([$this->userId, $skill['skill']]);
                    }
                }
            }
            
            // Apply certifications
            if (isset($verifiedData['certifications']) && !empty($verifiedData['certifications'])) {
                foreach ($verifiedData['certifications'] as $cert) {
                    if (!empty($cert['certification'])) {
                        $stmt = $this->pdo->prepare("
                            INSERT INTO staff_certifications (staff_id, certification_name, issuer, issue_date, expiry_date) 
                            VALUES (?, ?, ?, ?, ?)
                        ");
                        $stmt->execute([
                            $this->userId,
                            $cert['certification'],
                            $cert['issuer'] ?? 'Not specified',
                            $cert['issue_date'] ?? date('Y-m-d'),
                            $cert['expiry_date'] ?? null
                        ]);
                    }
                }
            }
            
            // Mark CV as verified and applied
            $stmt = $this->pdo->prepare("UPDATE staff_cvs SET is_verified = 1, applied_date = NOW() WHERE id = ? AND staff_id = ?");
            $stmt->execute([$cvId, $this->userId]);
            
            $this->pdo->commit();
            
            return ['success' => true, 'message' => 'CV data applied to profile successfully'];
            
        } catch (PDOException $e) {
            $this->pdo->rollBack();
            error_log("Error applying CV data: " . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to apply CV data: ' . $e->getMessage()];
        }
    }
    
    /**
     * Get profile photo URL
     */
    public function getProfilePhotoURL() {
        try {
            // Ensure we have user service number
            if (empty($this->userSvcNo)) {
                $this->loadUserInfo();
            }
            
            if ($this->userSvcNo) {
                // Look for profile photo by service number
                $uploadDir = dirname(__DIR__) . '/uploads/profile_photos/';
                $extensions = ['jpg', 'jpeg', 'png', 'gif'];
                
                foreach ($extensions as $ext) {
                    $filename = $this->userSvcNo . '.' . $ext;
                    $filepath = $uploadDir . $filename;
                    
                    if (file_exists($filepath)) {
                        return '/Armis2/uploads/profile_photos/' . $filename;
                    }
                }
            }
            
            // Fallback: try database column (for backward compatibility)
            try {
                $stmt = $this->pdo->prepare("SELECT profile_photo FROM staff WHERE id = ?");
                $stmt->execute([$this->userId]);
                $result = $stmt->fetch(PDO::FETCH_OBJ);
                
                if ($result && $result->profile_photo) {
                    $photoPath = '/Armis2/uploads/profiles/' . $result->profile_photo;
                    if (file_exists(dirname(__DIR__) . $photoPath)) {
                        return $photoPath;
                    }
                }
            } catch (PDOException $e) {
                // Column doesn't exist, that's OK
                error_log("Profile photo column not found (using service number): " . $e->getMessage());
            }
            
            // Return default avatar with user's service number for initials
            if ($this->userSvcNo) {
                return '/Armis2/shared/default_avatar.php?name=' . urlencode($this->userSvcNo);
            }
            
            return '/Armis2/shared/default_avatar.php';
            
        } catch (PDOException $e) {
            error_log("Error getting profile photo: " . $e->getMessage());
            return '/Armis2/shared/default_avatar.php';
        }
    }
    
    /**
     * Delete a CV record and its file
     */
    public function deleteCVRecord($cvId) {
        try {
            $this->pdo->beginTransaction();
            
            // Get CV details first
            $stmt = $this->pdo->prepare("
                SELECT filename FROM staff_cvs 
                WHERE id = ? AND staff_id = ?
            ");
            $stmt->execute([$cvId, $this->userId]);
            $cv = $stmt->fetch(PDO::FETCH_OBJ);
            
            if (!$cv) {
                return ['success' => false, 'message' => 'CV not found or access denied'];
            }
            
            // Delete from database
            $stmt = $this->pdo->prepare("DELETE FROM staff_cvs WHERE id = ? AND staff_id = ?");
            $stmt->execute([$cvId, $this->userId]);
            
            // Delete physical file
            $uploadDir = dirname(__DIR__) . '/uploads/cvs/';
            $filepath = $uploadDir . $cv->filename;
            if (file_exists($filepath)) {
                unlink($filepath);
            }
            
            $this->pdo->commit();
            
            // Log activity
            $this->logActivity('cv_delete', "CV deleted: {$cv->filename}");
            
            return ['success' => true, 'message' => 'CV deleted successfully'];
            
        } catch (Exception $e) {
            $this->pdo->rollback();
            error_log("Error deleting CV: " . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to delete CV: ' . $e->getMessage()];
        }
    }
    
    /**
     * Re-extract data from an existing CV
     */
    public function reExtractCVData($cvId) {
        try {
            // Get CV details
            $stmt = $this->pdo->prepare("
                SELECT filename, file_type FROM staff_cvs 
                WHERE id = ? AND staff_id = ?
            ");
            $stmt->execute([$cvId, $this->userId]);
            $cv = $stmt->fetch(PDO::FETCH_OBJ);
            
            if (!$cv) {
                return ['success' => false, 'message' => 'CV not found or access denied'];
            }
            
            // Check if file still exists
            $uploadDir = dirname(__DIR__) . '/uploads/cvs/';
            $filepath = $uploadDir . $cv->filename;
            if (!file_exists($filepath)) {
                return ['success' => false, 'message' => 'CV file no longer exists'];
            }
            
            // Re-extract data
            $extractedData = $this->extractCVData($filepath, $cv->file_type);
            
            // Update database with new extracted data
            $stmt = $this->pdo->prepare("
                UPDATE staff_cvs 
                SET extracted_data = ?, is_verified = 0 
                WHERE id = ? AND staff_id = ?
            ");
            $stmt->execute([json_encode($extractedData), $cvId, $this->userId]);
            
            // Log activity
            $this->logActivity('cv_re_extract', "CV data re-extracted: {$cv->filename}");
            
            return ['success' => true, 'message' => 'CV data re-extracted successfully', 'data' => $extractedData];
            
        } catch (Exception $e) {
            error_log("Error re-extracting CV data: " . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to re-extract CV data: ' . $e->getMessage()];
        }
    }
    
    /**
     * Mark a CV as verified
     */
    public function markCVAsVerified($cvId) {
        try {
            $stmt = $this->pdo->prepare("
                UPDATE staff_cvs 
                SET is_verified = 1 
                WHERE id = ? AND staff_id = ?
            ");
            $result = $stmt->execute([$cvId, $this->userId]);
            
            if ($stmt->rowCount() > 0) {
                // Log activity
                $this->logActivity('cv_verify', "CV marked as verified");
                return ['success' => true, 'message' => 'CV marked as verified'];
            } else {
                return ['success' => false, 'message' => 'CV not found or already verified'];
            }
            
        } catch (Exception $e) {
            error_log("Error marking CV as verified: " . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to update CV status: ' . $e->getMessage()];
        }
    }
}
