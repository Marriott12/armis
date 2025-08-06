<?php
/**
 * Advanced Form Validation Helper Functions
 * Enhanced validation system for staff creation form
 */

if (!defined('ARMIS_ADMIN_BRANCH')) {
    die('Direct access not permitted');
}

class StaffFormValidator {
    
    private $conn;
    private $errors = [];
    private $validationRules;
    private $fieldDependencies;
    
    public function __construct($dbConnection, $validationRules = [], $fieldDependencies = []) {
        $this->conn = $dbConnection;
        $this->validationRules = $validationRules;
        $this->fieldDependencies = $fieldDependencies;
    }
    
    /**
     * Validate all form data
     */
    public function validateForm($data) {
        $this->errors = [];
        
        // Validate each section
        foreach ($this->validationRules as $section => $rules) {
            $this->validateSection($section, $rules, $data);
        }
        
        // Check conditional dependencies
        $this->validateDependencies($data);
        
        // Custom business logic validation
        $this->validateBusinessRules($data);
        
        return empty($this->errors);
    }
    
    /**
     * Validate a specific section
     */
    private function validateSection($section, $rules, $data) {
        foreach ($rules as $field => $fieldRules) {
            $value = isset($data[$field]) ? trim($data[$field]) : '';
            $this->validateField($field, $value, $fieldRules);
        }
    }
    
    /**
     * Validate individual field (public method for AJAX)
     */
    public function validateSingleField($field, $value, $rules) {
        $this->errors = []; // Clear previous errors
        $this->validateField($field, $value, $rules);
        return !$this->hasErrors();
    }
    
    /**
     * Validate individual field (private method)
     */
    private function validateField($field, $value, $rules) {
        // Required field validation
        if (isset($rules['required']) && $rules['required'] && empty($value)) {
            $this->addError($field, 'This field is required');
            return; // Skip other validations if required field is empty
        }
        
        // Skip other validations if field is empty and not required
        if (empty($value) && (!isset($rules['required']) || !$rules['required'])) {
            return;
        }
        
        // Length validation
        if (isset($rules['min']) && strlen($value) < $rules['min']) {
            $this->addError($field, "Must be at least {$rules['min']} characters long");
        }
        
        if (isset($rules['max']) && strlen($value) > $rules['max']) {
            $this->addError($field, "Must not exceed {$rules['max']} characters");
        }
        
        // Pattern validation
        if (isset($rules['pattern']) && !preg_match($rules['pattern'], $value)) {
            $this->addError($field, 'Please enter a valid format');
        }
        
        // Type-specific validation
        if (isset($rules['type'])) {
            $this->validateType($field, $value, $rules['type'], $rules);
        }
        
        // Uniqueness validation
        if (isset($rules['unique'])) {
            $this->validateUnique($field, $value, $rules['unique']);
        }
        
        // Existence validation
        if (isset($rules['exists'])) {
            $this->validateExists($field, $value, $rules['exists']);
        }
    }
    
    /**
     * Validate data types
     */
    private function validateType($field, $value, $type, $rules) {
        switch ($type) {
            case 'email':
                if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
                    $this->addError($field, 'Please enter a valid email address');
                }
                break;
                
            case 'date':
                if (!$this->validateDate($value)) {
                    $this->addError($field, 'Please enter a valid date');
                } else {
                    // Age validation for date of birth
                    if (isset($rules['min_age']) || isset($rules['max_age'])) {
                        $this->validateAge($field, $value, $rules);
                    }
                }
                break;
                
            case 'phone':
                if (!$this->validatePhone($value)) {
                    $this->addError($field, 'Please enter a valid phone number');
                }
                break;
                
            case 'numeric':
                if (!is_numeric($value)) {
                    $this->addError($field, 'Please enter a valid number');
                }
                break;
        }
    }
    
    /**
     * Validate date format and validity
     */
    private function validateDate($date) {
        $d = DateTime::createFromFormat('Y-m-d', $date);
        return $d && $d->format('Y-m-d') === $date;
    }
    
    /**
     * Validate age based on date of birth
     */
    private function validateAge($field, $dateOfBirth, $rules) {
        $birthDate = new DateTime($dateOfBirth);
        $today = new DateTime();
        $age = $today->diff($birthDate)->y;
        
        if (isset($rules['min_age']) && $age < $rules['min_age']) {
            $this->addError($field, "Must be at least {$rules['min_age']} years old");
        }
        
        if (isset($rules['max_age']) && $age > $rules['max_age']) {
            $this->addError($field, "Must not exceed {$rules['max_age']} years old");
        }
    }
    
    /**
     * Validate phone number format
     */
    private function validatePhone($phone) {
        // Remove all non-numeric characters except + for international format
        $cleanPhone = preg_replace('/[^\d+]/', '', $phone);
        
        // Check if it's a valid length (7-15 digits, optionally starting with +)
        return preg_match('/^(\+?[1-9]\d{1,14})$/', $cleanPhone);
    }
    
    /**
     * Validate uniqueness in database
     */
    private function validateUnique($field, $value, $tableColumn) {
        list($table, $column) = explode('.', $tableColumn);
        
        $stmt = $this->conn->prepare("SELECT COUNT(*) as count FROM {$table} WHERE {$column} = ?");
        if ($stmt) {
            $stmt->bind_param('s', $value);
            $stmt->execute();
            $result = $stmt->get_result();
            $row = $result->fetch_assoc();
            
            if ($row['count'] > 0) {
                $this->addError($field, 'This value already exists in the system');
            }
            $stmt->close();
        }
    }
    
    /**
     * Validate existence in database
     */
    private function validateExists($field, $value, $tableColumn) {
        list($table, $column) = explode('.', $tableColumn);
        
        $stmt = $this->conn->prepare("SELECT COUNT(*) as count FROM {$table} WHERE {$column} = ? AND is_active = 1");
        if ($stmt) {
            $stmt->bind_param('s', $value);
            $stmt->execute();
            $result = $stmt->get_result();
            $row = $result->fetch_assoc();
            
            if ($row['count'] == 0) {
                $this->addError($field, 'Selected value does not exist or is inactive');
            }
            $stmt->close();
        }
    }
    
    /**
     * Validate conditional field dependencies
     */
    private function validateDependencies($data) {
        foreach ($this->fieldDependencies as $triggerField => $conditions) {
            if (isset($data[$triggerField])) {
                $triggerValue = $data[$triggerField];
                
                if (isset($conditions[$triggerValue])) {
                    $requiredFields = $conditions[$triggerValue];
                    
                    foreach ($requiredFields as $requiredField => $isRequired) {
                        if ($isRequired && (empty($data[$requiredField]) || trim($data[$requiredField]) === '')) {
                            $this->addError($requiredField, "This field is required when {$triggerField} is '{$triggerValue}'");
                        }
                    }
                }
            }
        }
    }
    
    /**
     * Validate business logic rules
     */
    private function validateBusinessRules($data) {
        // Enlistment date should not be in the future
        if (isset($data['enlistmentDate']) && !empty($data['enlistmentDate'])) {
            $enlistmentDate = new DateTime($data['enlistmentDate']);
            $today = new DateTime();
            
            if ($enlistmentDate > $today) {
                $this->addError('enlistmentDate', 'Enlistment date cannot be in the future');
            }
        }
        
        // Date of birth should be before enlistment date
        if (isset($data['dateOfBirth']) && isset($data['enlistmentDate']) && 
            !empty($data['dateOfBirth']) && !empty($data['enlistmentDate'])) {
            
            $birthDate = new DateTime($data['dateOfBirth']);
            $enlistmentDate = new DateTime($data['enlistmentDate']);
            
            if ($birthDate >= $enlistmentDate) {
                $this->addError('enlistmentDate', 'Enlistment date must be after date of birth');
            }
        }
        
        // Service number format validation
        if (isset($data['serviceNumber']) && !empty($data['serviceNumber'])) {
            if (!$this->validateServiceNumberFormat($data['serviceNumber'])) {
                $this->addError('serviceNumber', 'Service number must follow the correct format (e.g., ZA123456)');
            }
        }
        
        // Email domain validation for military emails
        if (isset($data['email']) && !empty($data['email'])) {
            $this->validateMilitaryEmail($data['email']);
        }
    }
    
    /**
     * Validate service number format
     */
    private function validateServiceNumberFormat($serviceNumber) {
        // Example format: Two letters followed by 6-8 digits
        return preg_match('/^[A-Z]{2}[0-9]{6,8}$/', strtoupper($serviceNumber));
    }
    
    /**
     * Validate military email domains
     */
    private function validateMilitaryEmail($email) {
        $allowedDomains = ['mod.gov.zm', 'zaf.mil.zm', 'army.gov.zm', 'defense.gov.zm'];
        $domain = strtolower(substr(strrchr($email, "@"), 1));
        
        // Allow civilian emails but log military email usage
        if (in_array($domain, $allowedDomains)) {
            error_log("Military email domain used: {$domain}");
        }
    }
    
    /**
     * Add validation error
     */
    private function addError($field, $message) {
        if (!isset($this->errors[$field])) {
            $this->errors[$field] = [];
        }
        $this->errors[$field][] = $message;
    }
    
    /**
     * Get all validation errors
     */
    public function getErrors() {
        return $this->errors;
    }
    
    /**
     * Get errors for a specific field
     */
    public function getFieldErrors($field) {
        return isset($this->errors[$field]) ? $this->errors[$field] : [];
    }
    
    /**
     * Check if validation passed
     */
    public function hasErrors() {
        return !empty($this->errors);
    }
    
    /**
     * Get formatted error messages for display
     */
    public function getFormattedErrors() {
        $formatted = [];
        
        foreach ($this->errors as $field => $fieldErrors) {
            $fieldName = $this->formatFieldName($field);
            
            foreach ($fieldErrors as $error) {
                $formatted[] = "{$fieldName}: {$error}";
            }
        }
        
        return $formatted;
    }
    
    /**
     * Format field name for display
     */
    private function formatFieldName($field) {
        // Convert camelCase to Title Case
        $formatted = preg_replace('/([A-Z])/', ' $1', $field);
        return ucfirst(trim($formatted));
    }
    
    /**
     * Validate file uploads
     */
    public function validateFileUpload($file, $fieldName) {
        if (!isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
            $this->addError($fieldName, 'File upload failed');
            return false;
        }
        
        // Check file size (5MB limit)
        $maxSize = 5 * 1024 * 1024; // 5MB
        if ($file['size'] > $maxSize) {
            $this->addError($fieldName, 'File size must not exceed 5MB');
            return false;
        }
        
        // Check file type
        $allowedTypes = ['image/jpeg', 'image/png', 'application/pdf', 'application/msword', 
                        'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
        
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        
        if (!in_array($mimeType, $allowedTypes)) {
            $this->addError($fieldName, 'Only JPG, PNG, PDF, DOC, and DOCX files are allowed');
            return false;
        }
        
        return true;
    }
}

/**
 * Helper function to create validator instance
 */
function createStaffFormValidator($dbConnection, $validationRules = [], $fieldDependencies = []) {
    return new StaffFormValidator($dbConnection, $validationRules, $fieldDependencies);
}

/**
 * Quick validation function for AJAX requests
 */
function validateFieldAjax($field, $value, $rules, $dbConnection) {
    $validator = new StaffFormValidator($dbConnection);
    $validator->validateSingleField($field, $value, $rules);
    
    return [
        'valid' => !$validator->hasErrors(),
        'errors' => $validator->getFieldErrors($field)
    ];
}
?>
