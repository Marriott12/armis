<?php
/**
 * Profile Validator Class
 * Comprehensive validation for military profile data
 */

class ProfileValidator {
    private $errors = [];
    private $militaryRules = [];
    
    public function __construct() {
        $this->initializeMilitaryRules();
    }
    
    /**
     * Initialize military-specific validation rules
     */
    private function initializeMilitaryRules() {
        $this->militaryRules = [
            'service_number' => [
                'required' => true,
                'pattern' => '/^[A-Z0-9]{4,15}$/',
                'message' => 'Service number must be 4-15 alphanumeric characters'
            ],
            'rank_id' => [
                'required' => true,
                'type' => 'integer',
                'min' => 1,
                'message' => 'Valid rank must be selected'
            ],
            'unit_id' => [
                'required' => true,
                'type' => 'integer',
                'min' => 1,
                'message' => 'Valid unit must be selected'
            ],
            'corps' => [
                'required' => true,
                'pattern' => '/^[A-Z]{2,5}$/',
                'message' => 'Valid corps abbreviation required'
            ],
            'security_clearance_level' => [
                'required' => false,
                'enum' => ['None', 'Confidential', 'Secret', 'Top Secret'],
                'message' => 'Invalid security clearance level'
            ],
            'medical_status' => [
                'required' => false,
                'enum' => ['Fit', 'Limited', 'Unfit', 'Pending'],
                'message' => 'Invalid medical status'
            ],
            'deployment_status' => [
                'required' => false,
                'enum' => ['Available', 'Deployed', 'Training', 'Leave', 'Medical'],
                'message' => 'Invalid deployment status'
            ],
            'blood_group' => [
                'required' => false,
                'pattern' => '/^(A|B|AB|O)[+-]$/',
                'message' => 'Invalid blood group format (e.g., A+, O-, AB+)'
            ],
            'nrc' => [
                'required' => false,
                'pattern' => '/^[A-Z0-9]{6,20}$/',
                'message' => 'NRC must be 6-20 alphanumeric characters'
            ],
            'phone_number' => [
                'required' => false,
                'pattern' => '/^[\+]?[0-9\-\s\(\)]{7,20}$/',
                'message' => 'Invalid phone number format'
            ],
            'email' => [
                'required' => false,
                'type' => 'email',
                'message' => 'Invalid email format'
            ],
            'date_of_birth' => [
                'required' => false,
                'type' => 'date',
                'min_age' => 16,
                'max_age' => 65,
                'message' => 'Invalid date of birth (must be between 16-65 years old)'
            ],
            'attestation_date' => [
                'required' => false,
                'type' => 'date',
                'past_only' => true,
                'message' => 'Attestation date must be in the past'
            ]
        ];
    }
    
    /**
     * Validate profile data according to military standards
     */
    public function validateProfile($data, $context = 'update') {
        $this->errors = [];
        $validated = [];
        
        foreach ($data as $field => $value) {
            $cleanValue = trim($value);
            
            // Get field rules (convert snake_case to underscore for lookup)
            $ruleField = str_replace('-', '_', strtolower($field));
            $rules = $this->militaryRules[$ruleField] ?? null;
            
            if (!$rules) {
                // No specific rules, apply basic sanitization
                $validated[$field] = $this->sanitizeValue($cleanValue);
                continue;
            }
            
            // Check required fields
            if ($rules['required'] && empty($cleanValue)) {
                $this->errors[$field] = ucfirst(str_replace('_', ' ', $field)) . ' is required';
                continue;
            }
            
            // Skip validation for empty optional fields
            if (empty($cleanValue) && !$rules['required']) {
                continue;
            }
            
            // Apply validation based on type
            $isValid = true;
            $errorMessage = $rules['message'] ?? 'Invalid value';
            
            switch ($rules['type'] ?? 'string') {
                case 'integer':
                    if (!is_numeric($cleanValue) || intval($cleanValue) != $cleanValue) {
                        $isValid = false;
                    } elseif (isset($rules['min']) && intval($cleanValue) < $rules['min']) {
                        $isValid = false;
                    } elseif (isset($rules['max']) && intval($cleanValue) > $rules['max']) {
                        $isValid = false;
                    }
                    break;
                    
                case 'email':
                    if (!filter_var($cleanValue, FILTER_VALIDATE_EMAIL)) {
                        $isValid = false;
                    }
                    break;
                    
                case 'date':
                    $date = DateTime::createFromFormat('Y-m-d', $cleanValue);
                    if (!$date || $date->format('Y-m-d') !== $cleanValue) {
                        $isValid = false;
                        $errorMessage = 'Invalid date format (YYYY-MM-DD required)';
                    } else {
                        $today = new DateTime();
                        
                        if (isset($rules['past_only']) && $rules['past_only'] && $date > $today) {
                            $isValid = false;
                            $errorMessage = 'Date cannot be in the future';
                        }
                        
                        if (isset($rules['min_age'])) {
                            $age = $today->diff($date)->y;
                            if ($age < $rules['min_age']) {
                                $isValid = false;
                                $errorMessage = "Minimum age is {$rules['min_age']} years";
                            }
                        }
                        
                        if (isset($rules['max_age'])) {
                            $age = $today->diff($date)->y;
                            if ($age > $rules['max_age']) {
                                $isValid = false;
                                $errorMessage = "Maximum age is {$rules['max_age']} years";
                            }
                        }
                    }
                    break;
            }
            
            // Apply pattern validation
            if ($isValid && isset($rules['pattern']) && !preg_match($rules['pattern'], $cleanValue)) {
                $isValid = false;
            }
            
            // Apply enum validation
            if ($isValid && isset($rules['enum']) && !in_array($cleanValue, $rules['enum'])) {
                $isValid = false;
            }
            
            if (!$isValid) {
                $this->errors[$field] = $errorMessage;
                continue;
            }
            
            $validated[$field] = $this->sanitizeValue($cleanValue);
        }
        
        // Additional cross-field validation
        $this->validateCrossFields($validated);
        
        return empty($this->errors) ? $validated : false;
    }
    
    /**
     * Validate relationships between fields
     */
    private function validateCrossFields($data) {
        // Security clearance validation
        if (isset($data['security_clearance_level']) && $data['security_clearance_level'] !== 'None') {
            if (empty($data['clearance_expiry_date'])) {
                $this->errors['clearance_expiry_date'] = 'Clearance expiry date required when clearance level is set';
            }
        }
        
        // Medical status validation
        if (isset($data['medical_status']) && $data['medical_status'] === 'Fit') {
            if (empty($data['medical_expiry_date'])) {
                $this->errors['medical_expiry_date'] = 'Medical expiry date required for fit status';
            }
        }
        
        // Emergency contact validation
        if (!empty($data['emergency_contact_name'])) {
            if (empty($data['emergency_contact_phone'])) {
                $this->errors['emergency_contact_phone'] = 'Emergency contact phone required when name is provided';
            }
            if (empty($data['emergency_contact_relationship'])) {
                $this->errors['emergency_contact_relationship'] = 'Emergency contact relationship required when name is provided';
            }
        }
    }
    
    /**
     * Validate service record data
     */
    public function validateServiceRecord($data) {
        $this->errors = [];
        $validated = [];
        
        $requiredFields = ['record_type', 'record_date', 'title', 'description'];
        $validRecordTypes = ['Enlistment', 'Promotion', 'Transfer', 'Deployment', 'Award', 'Discipline', 'Training', 'Medical', 'Leave'];
        
        foreach ($requiredFields as $field) {
            if (empty($data[$field])) {
                $this->errors[$field] = ucfirst(str_replace('_', ' ', $field)) . ' is required';
            }
        }
        
        // Validate record type
        if (!empty($data['record_type']) && !in_array($data['record_type'], $validRecordTypes)) {
            $this->errors['record_type'] = 'Invalid record type';
        }
        
        // Validate record date
        if (!empty($data['record_date'])) {
            $date = DateTime::createFromFormat('Y-m-d', $data['record_date']);
            if (!$date || $date->format('Y-m-d') !== $data['record_date']) {
                $this->errors['record_date'] = 'Invalid date format (YYYY-MM-DD required)';
            } elseif ($date > new DateTime()) {
                $this->errors['record_date'] = 'Record date cannot be in the future';
            }
        }
        
        // Type-specific validation
        if (!empty($data['record_type'])) {
            switch ($data['record_type']) {
                case 'Promotion':
                    if (empty($data['to_rank_id'])) {
                        $this->errors['to_rank_id'] = 'Target rank required for promotions';
                    }
                    break;
                    
                case 'Transfer':
                    if (empty($data['to_unit_id'])) {
                        $this->errors['to_unit_id'] = 'Target unit required for transfers';
                    }
                    break;
                    
                case 'Deployment':
                    if (empty($data['deployment_country'])) {
                        $this->errors['deployment_country'] = 'Deployment country required';
                    }
                    if (empty($data['deployment_duration_months'])) {
                        $this->errors['deployment_duration_months'] = 'Deployment duration required';
                    }
                    break;
            }
        }
        
        // Sanitize all fields
        foreach ($data as $field => $value) {
            $validated[$field] = $this->sanitizeValue($value);
        }
        
        return empty($this->errors) ? $validated : false;
    }
    
    /**
     * Validate file upload data
     */
    public function validateFileUpload($file, $allowedTypes = ['jpg', 'jpeg', 'png', 'pdf', 'doc', 'docx']) {
        $this->errors = [];
        
        if (!isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
            $this->errors['file'] = 'File upload failed';
            return false;
        }
        
        // Check file size (10MB max)
        $maxSize = 10 * 1024 * 1024;
        if ($file['size'] > $maxSize) {
            $this->errors['file'] = 'File size exceeds 10MB limit';
            return false;
        }
        
        // Check file type
        $fileExtension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($fileExtension, $allowedTypes)) {
            $this->errors['file'] = 'File type not allowed. Allowed: ' . implode(', ', $allowedTypes);
            return false;
        }
        
        // Check MIME type
        $allowedMimes = [
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'pdf' => 'application/pdf',
            'doc' => 'application/msword',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
        ];
        
        $expectedMime = $allowedMimes[$fileExtension] ?? null;
        if ($expectedMime && $file['type'] !== $expectedMime) {
            $this->errors['file'] = 'File MIME type does not match extension';
            return false;
        }
        
        return true;
    }
    
    /**
     * Sanitize input value
     */
    private function sanitizeValue($value) {
        if (is_string($value)) {
            return htmlspecialchars(trim($value), ENT_QUOTES, 'UTF-8');
        }
        return $value;
    }
    
    /**
     * Get validation errors
     */
    public function getErrors() {
        return $this->errors;
    }
    
    /**
     * Check if validation passed
     */
    public function isValid() {
        return empty($this->errors);
    }
    
    /**
     * Add custom error
     */
    public function addError($field, $message) {
        $this->errors[$field] = $message;
    }
    
    /**
     * Clear all errors
     */
    public function clearErrors() {
        $this->errors = [];
    }
}
?>