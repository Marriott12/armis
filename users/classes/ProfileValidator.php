<?php
/**
 * Profile Validator Class
 * Comprehensive input validation and sanitization for military personnel data
 */

class ProfileValidator {
    private $errors = [];
    private $warnings = [];
    
    /**
     * Validate complete profile data
     */
    public function validateProfile($data) {
        $this->errors = [];
        $this->warnings = [];
        
        // Personal information validation
        $this->validatePersonalInfo($data);
        
        // Contact information validation
        $this->validateContactInfo($data);
        
        // Military information validation
        $this->validateMilitaryInfo($data);
        
        // Medical information validation
        $this->validateMedicalInfo($data);
        
        return [
            'valid' => empty($this->errors),
            'errors' => $this->errors,
            'warnings' => $this->warnings
        ];
    }
    
    /**
     * Validate personal information
     */
    private function validatePersonalInfo($data) {
        // Name validation
        if (isset($data['first_name'])) {
            if (empty($data['first_name'])) {
                $this->errors['first_name'] = 'First name is required';
            } elseif (!preg_match('/^[a-zA-Z\s\'-]{2,50}$/', $data['first_name'])) {
                $this->errors['first_name'] = 'First name contains invalid characters';
            }
        }
        
        if (isset($data['last_name'])) {
            if (empty($data['last_name'])) {
                $this->errors['last_name'] = 'Last name is required';
            } elseif (!preg_match('/^[a-zA-Z\s\'-]{2,50}$/', $data['last_name'])) {
                $this->errors['last_name'] = 'Last name contains invalid characters';
            }
        }
        
        if (isset($data['middle_name']) && !empty($data['middle_name'])) {
            if (!preg_match('/^[a-zA-Z\s\'-]{1,50}$/', $data['middle_name'])) {
                $this->errors['middle_name'] = 'Middle name contains invalid characters';
            }
        }
        
        // National ID validation (Zambian format)
        if (isset($data['national_id']) && !empty($data['national_id'])) {
            if (!preg_match('/^[0-9]{6}\/[0-9]{2}\/[0-9]$/', $data['national_id'])) {
                $this->errors['national_id'] = 'National ID must be in format: 123456/78/9';
            } else {
                // Additional validation for valid date components
                $parts = explode('/', $data['national_id']);
                $day = substr($parts[0], 0, 2);
                $month = substr($parts[0], 2, 2);
                $year = substr($parts[0], 4, 2);
                
                if (!checkdate($month, $day, $year)) {
                    $this->errors['national_id'] = 'National ID contains invalid date';
                }
            }
        }
        
        // Date of birth validation
        if (isset($data['date_of_birth']) && !empty($data['date_of_birth'])) {
            $dob = DateTime::createFromFormat('Y-m-d', $data['date_of_birth']);
            if (!$dob || $dob->format('Y-m-d') !== $data['date_of_birth']) {
                $this->errors['date_of_birth'] = 'Invalid date format (YYYY-MM-DD required)';
            } else {
                $age = $this->calculateAge($data['date_of_birth']);
                if ($age < 16) {
                    $this->errors['date_of_birth'] = 'Age must be at least 16 years';
                } elseif ($age > 70) {
                    $this->errors['date_of_birth'] = 'Age cannot exceed 70 years';
                } elseif ($age < 18) {
                    $this->warnings['date_of_birth'] = 'Personnel under 18 requires special authorization';
                }
            }
        }
        
        // Gender validation
        if (isset($data['gender']) && !empty($data['gender'])) {
            if (!in_array($data['gender'], ['Male', 'Female', 'Other'])) {
                $this->errors['gender'] = 'Invalid gender selection';
            }
        }
        
        // Marital status validation
        if (isset($data['marital_status']) && !empty($data['marital_status'])) {
            $validStatuses = ['Single', 'Married', 'Divorced', 'Widowed', 'Separated'];
            if (!in_array($data['marital_status'], $validStatuses)) {
                $this->errors['marital_status'] = 'Invalid marital status';
            }
        }
        
        // Nationality validation
        if (isset($data['nationality']) && !empty($data['nationality'])) {
            if (!preg_match('/^[a-zA-Z\s]{2,50}$/', $data['nationality'])) {
                $this->errors['nationality'] = 'Invalid nationality format';
            }
        }
        
        // Place of birth validation
        if (isset($data['place_of_birth']) && !empty($data['place_of_birth'])) {
            if (!preg_match('/^[a-zA-Z\s,.\'-]{2,100}$/', $data['place_of_birth'])) {
                $this->errors['place_of_birth'] = 'Invalid place of birth format';
            }
        }
        
        // Religion validation
        if (isset($data['religion']) && !empty($data['religion'])) {
            if (!preg_match('/^[a-zA-Z\s]{2,50}$/', $data['religion'])) {
                $this->errors['religion'] = 'Invalid religion format';
            }
        }
    }
    
    /**
     * Validate contact information
     */
    private function validateContactInfo($data) {
        // Email validation
        if (isset($data['email']) && !empty($data['email'])) {
            if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
                $this->errors['email'] = 'Invalid email format';
            } elseif (strlen($data['email']) > 100) {
                $this->errors['email'] = 'Email address too long';
            }
        }
        
        // Phone validation
        if (isset($data['phone']) && !empty($data['phone'])) {
            // Remove spaces and special characters for validation
            $cleanPhone = preg_replace('/[\s\-\(\)]/', '', $data['phone']);
            
            if (!preg_match('/^\+?[0-9]{8,15}$/', $cleanPhone)) {
                $this->errors['phone'] = 'Invalid phone number format';
            } elseif (strlen($cleanPhone) < 8) {
                $this->errors['phone'] = 'Phone number too short';
            } elseif (strlen($cleanPhone) > 15) {
                $this->errors['phone'] = 'Phone number too long';
            }
        }
        
        // Emergency contact validation
        if (isset($data['emergency_contact_name']) && !empty($data['emergency_contact_name'])) {
            if (!preg_match('/^[a-zA-Z\s\'-]{2,100}$/', $data['emergency_contact_name'])) {
                $this->errors['emergency_contact_name'] = 'Invalid emergency contact name';
            }
        }
        
        if (isset($data['emergency_contact_phone']) && !empty($data['emergency_contact_phone'])) {
            $cleanPhone = preg_replace('/[\s\-\(\)]/', '', $data['emergency_contact_phone']);
            if (!preg_match('/^\+?[0-9]{8,15}$/', $cleanPhone)) {
                $this->errors['emergency_contact_phone'] = 'Invalid emergency contact phone';
            }
        }
        
        if (isset($data['emergency_contact_relationship']) && !empty($data['emergency_contact_relationship'])) {
            $validRelationships = [
                'Parent', 'Spouse', 'Child', 'Sibling', 'Grandparent', 
                'Aunt', 'Uncle', 'Cousin', 'Friend', 'Other'
            ];
            if (!in_array($data['emergency_contact_relationship'], $validRelationships)) {
                $this->errors['emergency_contact_relationship'] = 'Invalid relationship type';
            }
        }
    }
    
    /**
     * Validate military information
     */
    private function validateMilitaryInfo($data) {
        // Service number validation
        if (isset($data['service_number']) && !empty($data['service_number'])) {
            if (!preg_match('/^[A-Z0-9\/\-]{6,20}$/', $data['service_number'])) {
                $this->errors['service_number'] = 'Invalid service number format';
            }
        }
        
        // Enlistment date validation
        if (isset($data['enlistment_date']) && !empty($data['enlistment_date'])) {
            $enlistmentDate = DateTime::createFromFormat('Y-m-d', $data['enlistment_date']);
            if (!$enlistmentDate || $enlistmentDate->format('Y-m-d') !== $data['enlistment_date']) {
                $this->errors['enlistment_date'] = 'Invalid enlistment date format';
            } else {
                $now = new DateTime();
                if ($enlistmentDate > $now) {
                    $this->errors['enlistment_date'] = 'Enlistment date cannot be in the future';
                } elseif ($enlistmentDate < new DateTime('-50 years')) {
                    $this->errors['enlistment_date'] = 'Enlistment date too far in the past';
                }
                
                // Check if enlistment age is reasonable
                if (isset($data['date_of_birth']) && !empty($data['date_of_birth'])) {
                    $dob = new DateTime($data['date_of_birth']);
                    $enlistmentAge = $dob->diff($enlistmentDate)->y;
                    
                    if ($enlistmentAge < 16) {
                        $this->errors['enlistment_date'] = 'Enlistment age too young';
                    } elseif ($enlistmentAge > 40) {
                        $this->warnings['enlistment_date'] = 'Enlistment age over 40 requires verification';
                    }
                }
            }
        }
        
        // Security clearance validation
        if (isset($data['security_clearance_level']) && !empty($data['security_clearance_level'])) {
            $validLevels = ['None', 'Confidential', 'Secret', 'Top Secret'];
            if (!in_array($data['security_clearance_level'], $validLevels)) {
                $this->errors['security_clearance_level'] = 'Invalid security clearance level';
            }
        }
        
        // Clearance expiry validation
        if (isset($data['clearance_expiry_date']) && !empty($data['clearance_expiry_date'])) {
            $expiryDate = DateTime::createFromFormat('Y-m-d', $data['clearance_expiry_date']);
            if (!$expiryDate || $expiryDate->format('Y-m-d') !== $data['clearance_expiry_date']) {
                $this->errors['clearance_expiry_date'] = 'Invalid clearance expiry date';
            } else {
                $now = new DateTime();
                if ($expiryDate < $now) {
                    $this->warnings['clearance_expiry_date'] = 'Security clearance has expired';
                } elseif ($expiryDate < (new DateTime())->add(new DateInterval('P90D'))) {
                    $this->warnings['clearance_expiry_date'] = 'Security clearance expires within 90 days';
                }
            }
        }
    }
    
    /**
     * Validate medical information
     */
    private function validateMedicalInfo($data) {
        // Blood group validation
        if (isset($data['blood_group']) && !empty($data['blood_group'])) {
            $validBloodGroups = ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'];
            if (!in_array($data['blood_group'], $validBloodGroups)) {
                $this->errors['blood_group'] = 'Invalid blood group';
            }
        }
        
        // Height validation
        if (isset($data['height']) && !empty($data['height'])) {
            $height = (float)$data['height'];
            if ($height < 100 || $height > 250) {
                $this->errors['height'] = 'Height must be between 100 and 250 cm';
            } elseif ($height < 150) {
                $this->warnings['height'] = 'Height below minimum military standard';
            } elseif ($height > 220) {
                $this->warnings['height'] = 'Height above typical range';
            }
        }
        
        // Medical fitness status validation
        if (isset($data['medical_fitness_status']) && !empty($data['medical_fitness_status'])) {
            $validStatuses = ['Fit', 'Limited Duties', 'Medical Board', 'Unfit'];
            if (!in_array($data['medical_fitness_status'], $validStatuses)) {
                $this->errors['medical_fitness_status'] = 'Invalid medical fitness status';
            }
        }
        
        // Last medical exam validation
        if (isset($data['last_medical_exam']) && !empty($data['last_medical_exam'])) {
            $examDate = DateTime::createFromFormat('Y-m-d', $data['last_medical_exam']);
            if (!$examDate || $examDate->format('Y-m-d') !== $data['last_medical_exam']) {
                $this->errors['last_medical_exam'] = 'Invalid medical exam date';
            } else {
                $now = new DateTime();
                if ($examDate > $now) {
                    $this->errors['last_medical_exam'] = 'Medical exam date cannot be in the future';
                } elseif ($examDate < (new DateTime())->sub(new DateInterval('P5Y'))) {
                    $this->warnings['last_medical_exam'] = 'Medical exam over 5 years old';
                }
            }
        }
        
        // Next medical due validation
        if (isset($data['next_medical_due']) && !empty($data['next_medical_due'])) {
            $dueDate = DateTime::createFromFormat('Y-m-d', $data['next_medical_due']);
            if (!$dueDate || $dueDate->format('Y-m-d') !== $data['next_medical_due']) {
                $this->errors['next_medical_due'] = 'Invalid medical due date';
            } else {
                $now = new DateTime();
                if ($dueDate < $now) {
                    $this->warnings['next_medical_due'] = 'Medical exam is overdue';
                } elseif ($dueDate < (new DateTime())->add(new DateInterval('P30D'))) {
                    $this->warnings['next_medical_due'] = 'Medical exam due within 30 days';
                }
            }
        }
    }
    
    /**
     * Sanitize input data
     */
    public function sanitizeData($data) {
        $sanitized = [];
        
        foreach ($data as $key => $value) {
            if (is_string($value)) {
                // Basic sanitization
                $value = trim($value);
                $value = htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
                
                // Field-specific sanitization
                switch ($key) {
                    case 'email':
                        $value = filter_var($value, FILTER_SANITIZE_EMAIL);
                        break;
                    case 'phone':
                    case 'emergency_contact_phone':
                        $value = preg_replace('/[^0-9\+\-\(\)\s]/', '', $value);
                        break;
                    case 'national_id':
                    case 'service_number':
                        $value = strtoupper($value);
                        break;
                    case 'first_name':
                    case 'middle_name':
                    case 'last_name':
                    case 'emergency_contact_name':
                        $value = ucwords(strtolower($value));
                        break;
                    case 'height':
                        $value = (float)$value;
                        break;
                }
                
                $sanitized[$key] = $value;
            } else {
                $sanitized[$key] = $value;
            }
        }
        
        return $sanitized;
    }
    
    /**
     * Validate field individually for real-time validation
     */
    public function validateField($fieldName, $value, $context = []) {
        $tempData = array_merge($context, [$fieldName => $value]);
        $result = $this->validateProfile($tempData);
        
        return [
            'valid' => !isset($result['errors'][$fieldName]),
            'error' => $result['errors'][$fieldName] ?? null,
            'warning' => $result['warnings'][$fieldName] ?? null
        ];
    }
    
    /**
     * Calculate age from date of birth
     */
    private function calculateAge($dateOfBirth) {
        try {
            $dob = new DateTime($dateOfBirth);
            $now = new DateTime();
            return $now->diff($dob)->y;
        } catch (Exception $e) {
            return 0;
        }
    }
    
    /**
     * Check if value is empty (considering military standards)
     */
    private function isEmpty($value) {
        return $value === null || $value === '' || $value === '0000-00-00' || $value === 'N/A';
    }
    
    /**
     * Get validation rules for frontend
     */
    public function getValidationRules() {
        return [
            'first_name' => [
                'required' => true,
                'pattern' => '^[a-zA-Z\s\'-]{2,50}$',
                'message' => 'First name must be 2-50 characters, letters only'
            ],
            'last_name' => [
                'required' => true,
                'pattern' => '^[a-zA-Z\s\'-]{2,50}$',
                'message' => 'Last name must be 2-50 characters, letters only'
            ],
            'email' => [
                'required' => false,
                'type' => 'email',
                'maxlength' => 100,
                'message' => 'Valid email address required'
            ],
            'phone' => [
                'required' => false,
                'pattern' => '^\+?[0-9\s\-\(\)]{8,20}$',
                'message' => 'Phone number must be 8-20 digits'
            ],
            'national_id' => [
                'required' => false,
                'pattern' => '^[0-9]{6}\/[0-9]{2}\/[0-9]$',
                'message' => 'National ID format: 123456/78/9'
            ],
            'service_number' => [
                'required' => false,
                'pattern' => '^[A-Z0-9\/\-]{6,20}$',
                'message' => 'Service number: 6-20 characters, uppercase letters and numbers'
            ],
            'height' => [
                'required' => false,
                'type' => 'number',
                'min' => 100,
                'max' => 250,
                'message' => 'Height must be between 100-250 cm'
            ]
        ];
    }
}