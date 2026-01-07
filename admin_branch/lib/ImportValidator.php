<?php
/**
 * Import Validator Class
 * Comprehensive validation for staff import data
 * 
 * @package Armis
 * @subpackage AdminBranch
 * @version 2.0
 */

class ImportValidator {
    private $pdo;
    private $errors = [];
    private $validRanks = null;
    private $validUnits = null;
    private $validCorps = null;
    private $existingEmails = [];
    private $existingServiceNumbers = [];
    private $existingNRCs = [];
    
    // Valid enum values
    const VALID_GENDERS = ['Male', 'Female'];
    const VALID_MARITAL = ['Single', 'Married', 'Divorced', 'Widowed', 'Separated'];
    const VALID_BLOOD_GROUPS = ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'];
    const VALID_PROVINCES = [
        'Central', 'Copperbelt', 'Eastern', 'Luapula', 'Lusaka', 
        'Muchinga', 'Northern', 'North-Western', 'Southern', 'Western'
    ];
    
    // Field name mappings - allows multiple column header variations
    const FIELD_MAPPINGS = [
        'fornames' => ['fornames', 'FORENAMES', 'fName', 'firstname', 'fname', 'first name', 'First Name'],
        'surnames' => ['surnames', 'SURNAME', 'lName', 'lastname', 'lname', 'surname', 'last name', 'Last Name'],
        'email' => ['email', 'EMAIL', 'Email', 'e-mail', 'email_address', 'Email Address'],
        'dob' => ['dob', 'DOB', 'date_of_birth', 'dateofbirth', 'birth_date', 'Date of Birth'],
        'service number' => ['service number', 'SVC NO', 'svcNo', 'svc_no', 'svcNo', 'service no'],
        'rankId' => ['rankId', 'RANK', 'rank', 'RANK PREFIX'],
        'subRank' => ['subRank', 'SUB RANK', 'sub_rank', 'sub rank'],
        'subWef' => ['subWef', 'SUB RANK WEF', 'sub_rank_wef', 'sub wef'],
        'tempRank' => ['tempRank', 'TEMP RANK', 'temp_rank', 'temp rank'],
        'tempWef' => ['tempWef', 'TEMP RANK WEF', 'temp_rank_wef', 'temp wef'],
        'initials' => ['initials', 'INITIALS', 'Initials'],
        'titles' => ['titles', 'TITLES', 'Titles'],
        'attestDate' => ['attestDate', 'ATTESTATION DATE', 'attestation_date', 'attest_date'],
        'unitId' => ['unitId', 'UNIT', 'unit', 'Unit'],
        'unitAtt' => ['unitAtt', 'UNIT ATTACHED', 'unit_attached', 'unit attached'],
        'appt' => ['appt', 'APPT', 'appointment', 'Appointment'],
        'gender' => ['gender', 'GENDER', 'Gender'],
        'province' => ['province', 'PROVINCE', 'Province'],
        'corpsId' => ['corpsId', 'CORPS', 'corps', 'Corps'],
        'bloodGp' => ['bloodGp', 'BLOOD GP', 'blood_group', 'blood group'],
        'NRC' => ['NRC', 'nrc', 'Nrc'],
        'intake' => ['intake', 'INTAKE', 'Intake'],
        'marital' => ['marital', 'MARITAL', 'marital_status', 'Marital'],
        'tel' => ['tel', 'PHONE', 'phone', 'telephone', 'Tel', 'Phone'],
        'prefix' => ['prefix', 'PREFIX', 'RANK PREFIX', 'Prefix']
    ];
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
        $this->loadValidForeignKeys();
        $this->loadExistingRecords();
    }
    
    /**
     * Normalize field names - map various column header formats to standard names
     * 
     * @param array $data Raw CSV row data with original headers
     * @return array Normalized data with standardized field names
     */
    private function normalizeFieldNames($data) {
        $normalized = [];
        
        // First pass: copy all data as-is
        foreach ($data as $key => $value) {
            $normalized[$key] = $value;
        }
        
        // Second pass: add normalized field names for known variations
        foreach (self::FIELD_MAPPINGS as $standardName => $variations) {
            foreach ($variations as $variation) {
                if (isset($data[$variation])) {
                    $normalized[$standardName] = $data[$variation];
                    break; // Use first match
                }
            }
        }
        
        return $normalized;
    }
    
    /**
     * Load valid foreign key IDs for ranks, units, and corps
     */
    private function loadValidForeignKeys() {
        try {
            // Ranks table is named `rank` and uses rankId as primary key
            $this->validRanks = $this->pdo->query('SELECT rankId FROM `rank`')->fetchAll(PDO::FETCH_COLUMN);
            $this->validUnits = $this->pdo->query('SELECT unitId FROM unit')->fetchAll(PDO::FETCH_COLUMN);
            // Corps uses corpsId (varchar) as primary key
            $this->validCorps = $this->pdo->query('SELECT corpsId FROM corps')->fetchAll(PDO::FETCH_COLUMN);
        } catch (Exception $e) {
            error_log("Failed to load foreign keys: " . $e->getMessage());
        }
    }
    
    /**
     * Load existing emails, service numbers, and NRCs to check duplicates
     */
    private function loadExistingRecords() {
        try {
            $this->existingEmails = $this->pdo->query('SELECT email FROM staff WHERE email IS NOT NULL')->fetchAll(PDO::FETCH_COLUMN);
            $this->existingServiceNumbers = $this->pdo->query('SELECT svcNo FROM staff WHERE svcNo IS NOT NULL')->fetchAll(PDO::FETCH_COLUMN);
            $this->existingNRCs = $this->pdo->query('SELECT NRC FROM staff WHERE NRC IS NOT NULL')->fetchAll(PDO::FETCH_COLUMN);
        } catch (Exception $e) {
            error_log("Failed to load existing records: " . $e->getMessage());
        }
    }
    
    /**
     * Validate a single row of data
     * 
     * @param array $data Row data
     * @param int $rowNum Row number
     * @return array Array of error messages (empty if valid)
     */
    public function validateRow($data, $rowNum) {
        $errors = [];
        
        // Normalize field names first
        $data = $this->normalizeFieldNames($data);
        
        // Required fields validation - only fornames and surnames are mandatory
        $required = ['fornames', 'surnames'];
        $fieldLabels = [
            'fornames' => 'First Name (fornames/firstname/fname)',
            'surnames' => 'Surname (surnames/lastname/lname)'
        ];

        foreach ($required as $field) {
            if (empty($data[$field]) || trim($data[$field]) === '') {
                $label = $fieldLabels[$field] ?? $field;
                $errors[] = "Row $rowNum: Missing required field '$label'";
            }
        }

        // Do not return early here; other fields (email, dob) are optional and validated if present
        
        // Email validation (optional). If provided, validate format and uniqueness
        if (isset($data['email']) && trim($data['email']) !== '') {
            if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
                $errors[] = "Row $rowNum: Invalid email format '{$data['email']}'";
            } else {
                // Check duplicate email in DB
                if (in_array(strtolower($data['email']), array_map('strtolower', $this->existingEmails))) {
                    $errors[] = "Row $rowNum: Email '{$data['email']}' already exists in database";
                }
            }
        }
        
        // Date of Birth validation (optional). If provided, must be YYYY-MM-DD and reasonable age
        if (isset($data['dob']) && trim($data['dob']) !== '') {
            $dobDate = DateTime::createFromFormat('Y-m-d', $data['dob']);
            if (!$dobDate || $dobDate->format('Y-m-d') !== $data['dob']) {
                $errors[] = "Row $rowNum: Invalid date of birth format (expected YYYY-MM-DD, got '{$data['dob']}')";
            } else {
                // Age validation (must be 18+)
                $age = $dobDate->diff(new DateTime('now'))->y;
                if ($age < 18) {
                    $errors[] = "Row $rowNum: Staff member must be at least 18 years old (current age: $age)";
                }
                if ($age > 100) {
                    $errors[] = "Row $rowNum: Invalid age (over 100 years old)";
                }
            }
        }
        
        // Service number validation
        if (isset($data['service number']) && trim($data['service number']) !== '') {
            $serviceNum = trim($data['service number']);
            
            // Extract only digits from service number
            $digitsOnly = preg_replace('/\D/', '', $serviceNum);
            
            if (empty($digitsOnly)) {
                $errors[] = "Row $rowNum: Service number must contain at least one digit (got '$serviceNum')";
            } else {
                // Pad to 6 digits with leading zeros
                $normalizedServiceNum = str_pad($digitsOnly, 6, '0', STR_PAD_LEFT);
                
                // Do not treat existing DB service-numbers as a blocking validation error here.
                // The importer will choose how to handle DB-duplicates (skip, update, or report a summary).
                // Store normalized value back to data for later checks.
                $data['service number'] = $normalizedServiceNum;
            }
        }

    
        
        // NRC validation (Zambian format: 123456/78/9, optional)
        if (isset($data['NRC']) && trim($data['NRC']) !== '') {
            $nrc = trim($data['NRC']);
            
            // Skip validation for placeholder values
            $placeholders = ['N/A', 'NA', 'UNKNOWN', 'NONE', '-', 'NULL', 'NIL', '000000/00/0'];
            if (in_array(strtoupper($nrc), $placeholders)) {
                // Set to NULL for placeholders
                $data['NRC'] = null;
            } else {
                if (!preg_match('/^\d{6}\/\d{2}\/\d{1}$/', $nrc)) {
                    $errors[] = "Row $rowNum: Invalid NRC format (expected 123456/78/9, got '$nrc')";
                }
                
                // Check duplicate NRC - CONVERT TO WARNING instead of blocking error
                if (in_array($nrc, $this->existingNRCs)) {
                    // Skip this row silently or add as warning (not blocking error)
                    // For now, we'll allow it and let database unique constraint handle it
                    // The row will be skipped during insert if duplicate
                }
            }
        }
        
        // Phone number validation
        if (isset($data['tel']) && trim($data['tel']) !== '') {
            if (!preg_match('/^\+?\d{8,15}$/', trim($data['tel']))) {
                $errors[] = "Row $rowNum: Invalid phone number format (expected +260XXXXXXXXX or similar)";
            }
        }
        
        // Gender validation (case-insensitive)
        if (isset($data['gender']) && trim($data['gender']) !== '') {
            $gender = ucfirst(strtolower(trim($data['gender'])));
            if (!in_array($gender, self::VALID_GENDERS)) {
                $errors[] = "Row $rowNum: Invalid gender (must be 'Male' or 'Female', got '{$data['gender']}')";
            } else {
                // Normalize gender to proper case
                $data['gender'] = $gender;
            }
        }
        
        // Marital status validation
        if (isset($data['marital']) && trim($data['marital']) !== '') {
            if (!in_array($data['marital'], self::VALID_MARITAL)) {
                $validOptions = implode(', ', self::VALID_MARITAL);
                $errors[] = "Row $rowNum: Invalid marital status (must be one of: $validOptions)";
            }
        }
        
        // Blood group validation (case-insensitive, optional)
        if (isset($data['bloodGp']) && trim($data['bloodGp']) !== '') {
            $bloodGp = strtoupper(trim($data['bloodGp']));
            
            // Skip validation for common placeholder values
            $placeholders = ['N/A', 'NA', 'UNKNOWN', 'NONE', '-', 'NULL', 'NIL'];
            if (in_array($bloodGp, $placeholders)) {
                // Set to NULL or empty for placeholders
                $data['bloodGp'] = null;
            } else {
                // Normalize common variants and mis-typed zeros
                $bloodGp = str_replace('0', 'O', $bloodGp); // map zero to letter O
                $bloodGp = str_replace(['POSITIVE', 'NEGATIVE', 'POS', 'NEG', ' '], ['+', '-', '+', '-', ''], $bloodGp);
                
                // Handle blood groups without +/- sign (assume positive)
                // A, B, AB, O → A+, B+, AB+, O+
                if (in_array($bloodGp, ['A', 'B', 'AB', 'O'])) {
                    $bloodGp = $bloodGp . '+';
                }
                
                if (!in_array($bloodGp, self::VALID_BLOOD_GROUPS)) {
                    $validOptions = implode(', ', self::VALID_BLOOD_GROUPS);
                    $errors[] = "Row $rowNum: Invalid blood group (must be one of: $validOptions, or leave empty, got '{$data['bloodGp']}')";
                } else {
                    // Normalize blood group
                    $data['bloodGp'] = $bloodGp;
                }
            }
        }
        
        // Province validation (normalize hyphens and case-insensitive, optional)
        if (isset($data['province']) && trim($data['province']) !== '') {
            $province = trim($data['province']);
            
            // Skip validation for common placeholder values
            $placeholders = ['N/A', 'NA', 'UNKNOWN', 'NONE', '-', 'NULL', 'NIL'];
            if (in_array(strtoupper($province), $placeholders)) {
                // Set to NULL for placeholders
                $data['province'] = null;
            } else {
                // Province abbreviations mapping
                $abbreviations = [
                    'CB' => 'Copperbelt',
                    'CP' => 'Copperbelt',
                    'NW' => 'North-Western',
                    'NWP' => 'North-Western',
                    'EP' => 'Eastern',
                    'LP' => 'Luapula',
                    'LK' => 'Lusaka',
                    'LSK' => 'Lusaka',
                    'MP' => 'Muchinga',
                    'NP' => 'Northern',
                    'SP' => 'Southern',
                    'WP' => 'Western',
                    'CT' => 'Central'
                ];
                
                // Check if it's an abbreviation
                $provinceUpper = strtoupper($province);
                if (isset($abbreviations[$provinceUpper])) {
                    $data['province'] = $abbreviations[$provinceUpper];
                } else {
                    // Normalize province: remove hyphens and spaces for comparison
                    $normalizedProvince = str_replace(['-', ' '], '', $province);
                    
                    // Find matching province (case-insensitive, hyphen-insensitive, space-insensitive)
                    $matchedProvince = null;
                    foreach (self::VALID_PROVINCES as $validProvince) {
                        $normalizedValid = str_replace(['-', ' '], '', $validProvince);
                        if (strcasecmp($normalizedProvince, $normalizedValid) === 0) {
                            $matchedProvince = $validProvince;
                            break;
                        }
                    }
                    
                    if ($matchedProvince === null) {
                        $validOptions = implode(', ', self::VALID_PROVINCES);
                        $errors[] = "Row $rowNum: Invalid province (must be one of: $validOptions, or leave empty, got '{$province}')";
                    } else {
                        // Normalize province to database format
                        $data['province'] = $matchedProvince;
                    }
                }
            }
        }
        
        // Foreign key validation - rankId
        if (isset($data['rankId']) && trim($data['rankId']) !== '') {
            $rankId = trim($data['rankId']);
            if (!ctype_digit($rankId)) {
                $errors[] = "Row $rowNum: rankId must be a number (got '$rankId')";
            } elseif (!in_array((int)$rankId, $this->validRanks)) {
                $errors[] = "Row $rowNum: Invalid rankId '$rankId' (does not exist in rank table)";
            }
        }
        
        // Foreign key validation - unitId
        // Note: unitId can be text (unit code) - ImportProcessor will find or create
        if (isset($data['unitId']) && trim($data['unitId']) !== '') {
            $unitId = trim($data['unitId']);
            // Only validate if it's already a numeric ID
            if (ctype_digit($unitId) && !in_array((int)$unitId, $this->validUnits)) {
                $errors[] = "Row $rowNum: Invalid unitId '$unitId' (does not exist in unit table)";
            }
            // If it's text, ImportProcessor will handle lookup/creation
        }
        
        // Foreign key validation - corpsId
        // Note: corpsId can be text (corps abbreviation) - ImportProcessor will find or create
        if (isset($data['corpsId']) && trim($data['corpsId']) !== '') {
            $corpsId = trim($data['corpsId']);
            // Only validate if it's already a numeric ID
            if (ctype_digit($corpsId) && !in_array((int)$corpsId, $this->validCorps)) {
                $errors[] = "Row $rowNum: Invalid corpsId '$corpsId' (does not exist in corps table)";
            }
            // If it's text, ImportProcessor will handle lookup/creation
        }
        
        // Date field validation
        $dateFields = ['attestDate', 'subWef', 'tempWef'];
        foreach ($dateFields as $field) {
            if (isset($data[$field]) && trim($data[$field]) !== '') {
                $date = DateTime::createFromFormat('Y-m-d', $data[$field]);
                if (!$date || $date->format('Y-m-d') !== $data[$field]) {
                    $errors[] = "Row $rowNum: Invalid $field format (expected YYYY-MM-DD, got '{$data[$field]}')";
                }
            }
        }
        
        // Date logic validation (tempWef should be after subWef)
        if (isset($data['tempWef']) && isset($data['subWef']) && 
            trim($data['tempWef']) !== '' && trim($data['subWef']) !== '') {
            $tempWefDate = DateTime::createFromFormat('Y-m-d', $data['tempWef']);
            $subWefDate = DateTime::createFromFormat('Y-m-d', $data['subWef']);
            
            if ($tempWefDate && $subWefDate && $tempWefDate < $subWefDate) {
                $errors[] = "Row $rowNum: tempWef date cannot be before subWef date";
            }
        }
        
        return $errors;
    }
    
    /**
     * Sanitize data (trim whitespace, normalize case)
     * 
     * @param array $data Row data
     * @return array Sanitized data
     */
    public function sanitizeData($data) {
        $sanitized = [];
        
        foreach ($data as $key => $value) {
            if (is_string($value)) {
                // Trim whitespace
                $value = trim($value);
                
                // Title case for names
                if (in_array($key, ['fornames', 'surnames', 'prefix', 'titles'])) {
                    $value = ucwords(strtolower($value));
                }
                
                // Uppercase for initials
                if ($key === 'initials') {
                    $value = strtoupper($value);
                }
                
                // Lowercase for email
                if ($key === 'email') {
                    $value = strtolower($value);
                }
                
                // Remove spaces from service number
                if ($key === 'service number') {
                    $value = preg_replace('/\s+/', '', $value);
                }
                
                // Normalize NRC format
                if ($key === 'NRC') {
                    $value = preg_replace('/\s+/', '', $value);
                }
            }
            
            $sanitized[$key] = $value;
        }
        
        return $sanitized;
    }

    /**
     * Check whether a service number exists in the loaded existing records
     * @param string $svc normalized service number
     * @return bool
     */
    public function existsServiceNumber($svc) {
        return in_array($svc, $this->existingServiceNumbers);
    }
    
    /**
     * Add email to duplicate check list (for batch validation)
     */
    public function addToEmailCheck($email) {
        if (!in_array(strtolower($email), array_map('strtolower', $this->existingEmails))) {
            $this->existingEmails[] = $email;
        }
    }
    
    /**
     * Add service number to duplicate check list (for batch validation)
     */
    public function addToServiceNumberCheck($serviceNum) {
        if (!in_array($serviceNum, $this->existingServiceNumbers)) {
            $this->existingServiceNumbers[] = $serviceNum;
        }
    }
    
    /**
     * Add NRC to duplicate check list (for batch validation)
     */
    public function addToNRCCheck($nrc) {
        if (!in_array($nrc, $this->existingNRCs)) {
            $this->existingNRCs[] = $nrc;
        }
    }
}
