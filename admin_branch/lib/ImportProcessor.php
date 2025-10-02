<?php
/**
 * Import Processor Class
 * Handles CSV/Excel parsing and staff record insertion
 * 
 * @package Armis
 * @subpackage AdminBranch
 * @version 2.0
 */

// Load Composer autoloader for PhpSpreadsheet (if installed)
if (file_exists(__DIR__ . '/../../vendor/autoload.php')) {
    require_once __DIR__ . '/../../vendor/autoload.php';
}

require_once __DIR__ . '/ImportValidator.php';
require_once __DIR__ . '/UserAccountGenerator.php';

class ImportProcessor {
    private $pdo;
    private $validator;
    private $accountGenerator;
    private $userId;
    
    // Field name mappings - allows multiple column header variations
    const FIELD_MAPPINGS = [
        'fornames' => ['fornames', 'FORENAMES', 'first_name', 'firstname', 'fname', 'first name', 'First Name'],
        'surnames' => ['surnames', 'SURNAME', 'last_name', 'lastname', 'lname', 'surname', 'last name', 'Last Name'],
        'email' => ['email', 'EMAIL', 'Email', 'e-mail', 'email_address', 'Email Address'],
        'dob' => ['dob', 'DOB', 'date_of_birth', 'dateofbirth', 'birth_date', 'Date of Birth'],
        'service number' => ['service number', 'SVC NO', 'service_number', 'svc_no', 'svcNo', 'service no'],
        'rank_id' => ['rank_id', 'RANK', 'rank', 'RANK PREFIX'],
        'subRank' => ['subRank', 'SUB RANK', 'sub_rank', 'sub rank'],
        'subWef' => ['subWef', 'SUB RANK WEF', 'sub_rank_wef', 'sub wef'],
        'tempRank' => ['tempRank', 'TEMP RANK', 'temp_rank', 'temp rank'],
        'tempWef' => ['tempWef', 'TEMP RANK WEF', 'temp_rank_wef', 'temp wef'],
        'initials' => ['initials', 'INITIALS', 'Initials'],
        'titles' => ['titles', 'TITLES', 'Titles'],
        'attestDate' => ['attestDate', 'ATTESTATION DATE', 'attestation_date', 'attest_date'],
        'unit_id' => ['unit_id', 'UNIT', 'unit', 'Unit'],
        'unitAtt' => ['unitAtt', 'UNIT ATTACHED', 'unit_attached', 'unit attached'],
        'appt' => ['appt', 'APPT', 'appointment', 'Appointment'],
        'gender' => ['gender', 'GENDER', 'Gender'],
        'province' => ['province', 'PROVINCE', 'Province'],
        'corps_id' => ['corps_id', 'CORPS', 'corps', 'Corps'],
        'bloodGp' => ['bloodGp', 'BLOOD GP', 'blood_group', 'blood group'],
        'NRC' => ['NRC', 'nrc', 'Nrc'],
        'intake' => ['intake', 'INTAKE', 'Intake'],
        'marital' => ['marital', 'MARITAL', 'marital_status', 'Marital'],
        'tel' => ['tel', 'PHONE', 'phone', 'telephone', 'Tel', 'Phone'],
        'prefix' => ['prefix', 'PREFIX', 'RANK PREFIX', 'Prefix']
    ];
    
    public function __construct($pdo, $userId = 0) {
        $this->pdo = $pdo;
        $this->validator = new ImportValidator($pdo);
        $this->accountGenerator = new UserAccountGenerator($pdo);
        $this->userId = $userId;
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
     * Strip special characters for comparison
     * 
     * @param string $str String to clean
     * @return string Cleaned string (alphanumeric only)
     */
    private function stripSpecialChars($str) {
        return preg_replace('/[^a-zA-Z0-9]/', '', strtoupper(trim($str)));
    }
    
    /**
     * Find or create unit by code
     * Compares unit code without special characters
     * 
     * @param string $unitCode Unit code from CSV
     * @return int|null Unit ID or null if creation failed
     */
    private function findOrCreateUnit($unitCode) {
        if (empty($unitCode)) {
            return null;
        }
        
        $cleanCode = $this->stripSpecialChars($unitCode);
        
        // Try to find existing unit by comparing codes without special characters
        $stmt = $this->pdo->query("SELECT id, code FROM units WHERE is_active = 1");
        $units = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($units as $unit) {
            if ($this->stripSpecialChars($unit['code']) === $cleanCode) {
                return (int)$unit['id'];
            }
        }
        
        // Unit not found, create new one
        $stmt = $this->pdo->prepare("INSERT INTO units (name, code, is_active) VALUES (?, ?, 1)");
        $stmt->execute([trim($unitCode), trim($unitCode)]);
        
        return (int)$this->pdo->lastInsertId();
    }
    
    /**
     * Find or create corps by abbreviation
     * 
     * @param string $corpsAbbr Corps abbreviation from CSV
     * @return int|null Corps ID or null if creation failed
     */
    private function findOrCreateCorps($corpsAbbr) {
        if (empty($corpsAbbr)) {
            return null;
        }
        
        $cleanAbbr = $this->stripSpecialChars($corpsAbbr);
        
        // Try to find existing corps by comparing abbreviations without special characters
        $stmt = $this->pdo->query("SELECT id, abbreviation FROM corps");
        $corpsList = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($corpsList as $corps) {
            if ($this->stripSpecialChars($corps['abbreviation']) === $cleanAbbr) {
                return (int)$corps['id'];
            }
        }
        
        // Corps not found, create new one
        $stmt = $this->pdo->prepare("INSERT INTO corps (name, abbreviation) VALUES (?, ?)");
        $stmt->execute([trim($corpsAbbr), trim($corpsAbbr)]);
        
        return (int)$this->pdo->lastInsertId();
    }
    
    /**
     * Find rank by abbreviation (ignoring special characters except for T/)
     * 
     * @param string $rankAbbr Rank abbreviation from CSV
     * @param bool $isTemporal Is this a temporal rank? If true, searches for T/ version
     * @return int|null Rank ID or null if not found
     */
    private function findRankByAbbreviation($rankAbbr, $isTemporal = false) {
        if (empty($rankAbbr)) {
            return null;
        }
        
        // For temporal ranks: If CSV has "Maj", search for "T/Maj" in database
        // If CSV has "T/Maj" or "T/ Maj", normalize and search
        // For substantive ranks: Search as-is
        $searchValue = trim($rankAbbr);
        
        if ($isTemporal) {
            // Remove any existing T/ or T / prefix (normalize)
            $searchValue = preg_replace('/^T\s*\/\s*/i', '', $searchValue);
            // Now add T/ prefix to search for temporal rank in database
            $searchValue = 'T/' . trim($searchValue);
        }
        
        // Get all ranks from database
        $stmt = $this->pdo->query("SELECT id, abbreviation FROM ranks");
        $ranks = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Try to find matching rank
        foreach ($ranks as $rank) {
            // For comparison, normalize both strings:
            // 1. Remove extra spaces around slash: "T/ Maj" -> "T/Maj"
            // 2. Keep forward slash
            // 3. Remove other special characters (dots, hyphens)
            // 4. Make uppercase
            
            $normalizedSearch = $this->normalizeRankAbbreviation($searchValue);
            $normalizedDb = $this->normalizeRankAbbreviation($rank['abbreviation']);
            
            if ($normalizedSearch === $normalizedDb) {
                return (int)$rank['id'];
            }
        }
        
        return null;
    }
    
    /**
     * Normalize rank abbreviation for comparison
     * Removes special chars except /, handles spacing around /
     * 
     * @param string $abbr Rank abbreviation
     * @return string Normalized abbreviation
     */
    private function normalizeRankAbbreviation($abbr) {
        if (empty($abbr)) {
            return '';
        }
        
        $normalized = trim($abbr);
        
        // Normalize spaces around forward slash: "T/ Maj" -> "T/Maj", "T / Maj" -> "T/Maj"
        $normalized = preg_replace('/\s*\/\s*/', '/', $normalized);
        
        // Remove other special characters (dots, hyphens) but keep letters, numbers, and /
        $normalized = preg_replace('/[^a-zA-Z0-9\/]/', '', $normalized);
        
        // Convert to uppercase for case-insensitive comparison
        $normalized = strtoupper($normalized);
        
        return $normalized;
    }
    
    /**
     * Process CSV file
     * 
     * @param string $filePath Path to uploaded file
     * @return array Result array with success/error messages
     */
    public function processCSV($filePath) {
        $dataRows = [];
        $handle = fopen($filePath, 'r');
        
        if ($handle === false) {
            return ['success' => [], 'errors' => ['Failed to open uploaded file']];
        }
        
        $header = fgetcsv($handle);
        if (!$header) {
            fclose($handle);
            return ['success' => [], 'errors' => ['CSV file is empty or invalid']];
        }
        
        // Skip comment/instruction rows that start with #
        while (($row = fgetcsv($handle)) !== false) {
            if (!empty($row[0]) && substr(trim($row[0]), 0, 1) === '#') {
                continue; // Skip instruction rows
            }
            if (count($row) === count($header)) {
                $dataRows[] = array_combine($header, $row);
            }
        }
        fclose($handle);
        
        return $this->processData($dataRows, 'CSV');
    }
    
    /**
     * Process Excel file (requires PhpSpreadsheet)
     * Falls back to error if library not available
     * 
     * @param string $filePath Path to uploaded file
     * @return array Result array with success/error messages
     */
    public function processExcel($filePath) {
        // Check if PhpSpreadsheet is available
        if (!class_exists('PhpOffice\PhpSpreadsheet\IOFactory')) {
            return [
                'success' => [],
                'errors' => [
                    'Excel import requires PhpSpreadsheet library.',
                    'Please install it with: composer require phpoffice/phpspreadsheet',
                    'Or convert your Excel file to CSV format and try again.'
                ]
            ];
        }
        
        try {
            $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($filePath);
            $worksheet = $spreadsheet->getActiveSheet();
            $dataRows = [];
            $header = [];
            
            // Get header row
            foreach ($worksheet->getRowIterator(1, 1) as $row) {
                foreach ($row->getCellIterator() as $cell) {
                    $header[] = $cell->getValue();
                }
            }
            
            // Get data rows
            foreach ($worksheet->getRowIterator(2) as $row) {
                $rowData = [];
                $cellIterator = $row->getCellIterator();
                $cellIterator->setIterateOnlyExistingCells(false);
                
                foreach ($cellIterator as $cell) {
                    $value = $cell->getValue();
                    
                    // Handle date values
                    if ($cell->getDataType() === \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC) {
                        if (\PhpOffice\PhpSpreadsheet\Shared\Date::isDateTime($cell)) {
                            $value = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($value)->format('Y-m-d');
                        }
                    }
                    
                    $rowData[] = $value;
                }
                
                // Skip comment/instruction rows
                if (!empty($rowData[0]) && substr(trim($rowData[0]), 0, 1) === '#') {
                    continue;
                }
                
                // Skip empty rows
                if (count(array_filter($rowData)) > 0) {
                    $dataRows[] = array_combine($header, $rowData);
                }
            }
            
            return $this->processData($dataRows, 'Excel');
            
        } catch (Exception $e) {
            return [
                'success' => [],
                'errors' => ['Excel processing failed: ' . $e->getMessage()]
            ];
        }
    }
    
    /**
     * Process data rows (main import logic)
     * 
     * @param array $dataRows Array of row data
     * @param string $fileType Type of file (CSV/Excel)
     * @return array Result array with success/error messages
     */
    private function processData($dataRows, $fileType = 'CSV') {
        $success = [];
        $errors = [];
        $userCredentials = [];
        
        if (empty($dataRows)) {
            return [
                'success' => [],
                'errors' => ['No data rows found in file']
            ];
        }
        
        // Column mapping
        $dbMap = [
            'prefix' => 'prefix',
            'service number' => 'service_number',
            'subRank' => 'subRank',
            'subWef' => 'subWef',
            'tempRank' => 'tempRank',
            'tempWef' => 'tempWef',
            'initials' => 'initials',
            'fornames' => 'first_name',
            'surnames' => 'last_name',
            'titles' => 'titles',
            'attestDate' => 'attestDate',
            'unit_id' => 'unit_id',
            'unitAtt' => 'unitAtt',
            'appt' => 'appt',
            'dob' => 'DOB',
            'gender' => 'gender',
            'province' => 'province',
            'corps_id' => 'corps_id',
            'bloodGp' => 'bloodGp',
            'NRC' => 'NRC',
            'intake' => 'intake',
            'marital' => 'marital',
            'email' => 'email',
            'tel' => 'tel',
            'rank_id' => 'rank_id'
        ];
        
        // Start transaction for all-or-nothing import
        $this->pdo->beginTransaction();
        
        try {
            $rowNum = 1;
            $skippedRows = 0;
            
            foreach ($dataRows as $data) {
                $rowNum++;
                
                if (!is_array($data)) {
                    continue;
                }
                
                // Normalize field names to handle different CSV column header variations
                $data = $this->normalizeFieldNames($data);
                
                // Sanitize data
                $data = $this->validator->sanitizeData($data);
                
                // Validate row
                $rowErrors = $this->validator->validateRow($data, $rowNum);
                
                if (!empty($rowErrors)) {
                    // Collect all errors for this row
                    $errors = array_merge($errors, $rowErrors);
                    continue;
                }
                
                // === SMART LOOKUPS AND AUTO-CREATION ===
                
                // 1. Handle UNIT - Find or create unit by code (ignore special characters)
                if (isset($data['unit_id']) && !empty($data['unit_id']) && !is_numeric($data['unit_id'])) {
                    $unitId = $this->findOrCreateUnit($data['unit_id']);
                    if ($unitId) {
                        $data['unit_id'] = $unitId;
                    }
                }
                
                // 2. Handle CORPS - Find or create corps by abbreviation
                if (isset($data['corps_id']) && !empty($data['corps_id']) && !is_numeric($data['corps_id'])) {
                    $corpsId = $this->findOrCreateCorps($data['corps_id']);
                    if ($corpsId) {
                        $data['corps_id'] = $corpsId;
                    }
                }
                
                // 3. Handle SUB RANK - If subRank is not empty, find rank by abbreviation
                if (isset($data['subRank']) && !empty($data['subRank'])) {
                    $rankId = $this->findRankByAbbreviation($data['subRank'], false);
                    if ($rankId) {
                        $data['rank_id'] = $rankId;
                        // Keep subRank and subWef as-is (they will be inserted)
                    }
                }
                
                // 4. Handle TEMP RANK - If tempRank is not empty, remove T/ and find rank
                if (isset($data['tempRank']) && !empty($data['tempRank'])) {
                    $tempRankId = $this->findRankByAbbreviation($data['tempRank'], true);
                    if ($tempRankId) {
                        // For temporal ranks, we still set rank_id but also keep tempRank fields
                        if (!isset($data['rank_id']) || empty($data['rank_id'])) {
                            $data['rank_id'] = $tempRankId;
                        }
                        // Keep tempRank and tempWef as-is (they will be inserted)
                    }
                }
                
                // === DATA NORMALIZATION BEFORE INSERT ===
                
                // Normalize gender (case-insensitive) - convert to proper case
                if (isset($data['gender']) && !empty($data['gender'])) {
                    $data['gender'] = ucfirst(strtolower(trim($data['gender'])));
                }
                
                // Normalize blood group (case-insensitive, handle placeholders)
                if (isset($data['bloodGp']) && !empty($data['bloodGp'])) {
                    $bloodGp = strtoupper(trim($data['bloodGp']));
                    
                    // Check for placeholder values
                    $placeholders = ['N/A', 'NA', 'UNKNOWN', 'NONE', '-', 'NULL', 'NIL'];
                    if (in_array($bloodGp, $placeholders)) {
                        $data['bloodGp'] = null;
                    } else {
                        // Normalize blood group format variations
                        $bloodGp = str_replace(['POSITIVE', 'NEGATIVE', 'POS', 'NEG', ' '], ['+', '-', '+', '-', ''], $bloodGp);
                        
                        // Handle blood groups without +/- sign (assume positive)
                        if (in_array($bloodGp, ['A', 'B', 'AB', 'O'])) {
                            $bloodGp = $bloodGp . '+';
                        }
                        
                        $data['bloodGp'] = $bloodGp;
                    }
                }
                
                // Normalize province (remove hyphens, match case-insensitively, handle placeholders and abbreviations)
                if (isset($data['province']) && !empty($data['province'])) {
                    $province = trim($data['province']);
                    
                    // Check for placeholder values
                    $placeholders = ['N/A', 'NA', 'UNKNOWN', 'NONE', '-', 'NULL', 'NIL'];
                    if (in_array(strtoupper($province), $placeholders)) {
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
                            $validProvinces = ['Central', 'Copperbelt', 'Eastern', 'Luapula', 'Lusaka', 'Muchinga', 'Northern', 'North-Western', 'Southern', 'Western'];
                            
                            // Find matching province (case-insensitive, hyphen-insensitive, space-insensitive)
                            $normalizedProvince = str_replace(['-', ' '], '', $province);
                            foreach ($validProvinces as $validProvince) {
                                $normalizedValid = str_replace(['-', ' '], '', $validProvince);
                                if (strcasecmp($normalizedProvince, $normalizedValid) === 0) {
                                    $data['province'] = $validProvince;
                                    break;
                                }
                            }
                        }
                    }
                }
                
                // Normalize NRC (handle placeholders)
                if (isset($data['NRC']) && !empty($data['NRC'])) {
                    $nrc = trim($data['NRC']);
                    $placeholders = ['N/A', 'NA', 'UNKNOWN', 'NONE', '-', 'NULL', 'NIL', '000000/00/0'];
                    if (in_array(strtoupper($nrc), $placeholders)) {
                        $data['NRC'] = null;
                    }
                }
                
                // Normalize service number (extract digits only, pad to 6 digits)
                if (isset($data['service number']) && !empty($data['service number'])) {
                    $serviceNum = trim($data['service number']);
                    // Extract only digits
                    $digitsOnly = preg_replace('/\D/', '', $serviceNum);
                    // Pad to 6 digits with leading zeros
                    if (!empty($digitsOnly)) {
                        $data['service number'] = str_pad($digitsOnly, 6, '0', STR_PAD_LEFT);
                    }
                }
                
                // Prepare insert fields and values
                $fields = [];
                $placeholders = [];
                $values = [];
                
                foreach ($dbMap as $csvField => $dbField) {
                    if (isset($data[$csvField]) && $data[$csvField] !== '' && $data[$csvField] !== null) {
                        $fields[] = $dbField;
                        $placeholders[] = '?';
                        $values[] = $data[$csvField];
                    }
                }
                
                if (empty($fields)) {
                    $errors[] = "Row $rowNum: No valid data to insert";
                    continue;
                }
                
                // Insert staff record
                $sql = 'INSERT INTO staff (' . implode(',', $fields) . ') VALUES (' . implode(',', $placeholders) . ')';
                $insert = $this->pdo->prepare($sql);
                
                try {
                    $insertSuccess = $insert->execute($values);
                } catch (PDOException $e) {
                    // Check if it's a duplicate entry error (for NRC, email, service number)
                    if ($e->getCode() == 23000 || strpos($e->getMessage(), 'Duplicate entry') !== false) {
                        // Skip this row - it's a duplicate
                        $skippedRows++;
                        continue;
                    } else {
                        // Other database error - add to errors
                        $errors[] = "Row $rowNum: Database error - " . $e->getMessage();
                        continue;
                    }
                }
                
                if ($insertSuccess) {
                    $staffId = $this->pdo->lastInsertId();
                    
                    // Create user account - Only if email is provided
                    if (isset($data['email']) && trim($data['email']) !== '') {
                        try {
                            $accountResult = $this->accountGenerator->createAccount(
                                $staffId,
                                $data['fornames'],
                                $data['surnames'],
                                $data['email']
                            );
                            
                            if ($accountResult['success']) {
                                $userCredentials[] = [
                                    'name' => $data['fornames'] . ' ' . $data['surnames'],
                                    'email' => $data['email'],
                                    'username' => $accountResult['username'],
                                    'password' => $accountResult['password']
                                ];
                                
                                $success[] = "Row $rowNum: {$data['fornames']} {$data['surnames']} ({$data['email']}) imported successfully";
                            } else {
                                // Staff inserted but account creation failed
                                $success[] = "Row $rowNum: {$data['fornames']} {$data['surnames']} imported (Warning: User account creation failed)";
                            }
                        } catch (Exception $e) {
                            $success[] = "Row $rowNum: {$data['fornames']} {$data['surnames']} imported (Warning: " . $e->getMessage() . ")";
                        }
                        
                        // Add to duplicate check lists for subsequent rows
                        $this->validator->addToEmailCheck($data['email']);
                    } else {
                        // Staff imported without email - no user account created
                        $success[] = "Row $rowNum: {$data['fornames']} {$data['surnames']} imported (No user account - email not provided)";
                    }
                    
                    // Add to duplicate check lists for subsequent rows
                    if (isset($data['service number'])) {
                        $this->validator->addToServiceNumberCheck($data['service number']);
                    }
                    if (isset($data['NRC'])) {
                        $this->validator->addToNRCCheck($data['NRC']);
                    }
                    
                } else {
                    $errors[] = "Row $rowNum: Database insert failed";
                }
            }
            
            // Commit transaction - allow partial import even with errors
            $this->pdo->commit();
            
            // Log import
            $this->logImport($fileType, count($dataRows), count($success), count($errors));
            
            return [
                'success' => $success,
                'errors' => $errors,
                'credentials' => $userCredentials
            ];
            
        } catch (Exception $e) {
            // Rollback on any exception
            $this->pdo->rollBack();
            return [
                'success' => [],
                'errors' => ['Import failed: ' . $e->getMessage()],
                'credentials' => []
            ];
        }
    }
    
    /**
     * Log import activity to audit trail
     * 
     * @param string $fileType Type of file
     * @param int $totalRows Total rows processed
     * @param int $successfulRows Successful imports
     * @param int $failedRows Failed imports
     */
    private function logImport($fileType, $totalRows, $successfulRows, $failedRows) {
        try {
            // Create import log table if it doesn't exist
            $this->pdo->exec("
                CREATE TABLE IF NOT EXISTS staff_imports (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    filename VARCHAR(255),
                    file_type VARCHAR(50),
                    imported_by INT,
                    import_date DATETIME,
                    total_rows INT,
                    successful_rows INT,
                    failed_rows INT,
                    import_log TEXT,
                    INDEX idx_import_date (import_date),
                    INDEX idx_imported_by (imported_by)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
            
            $filename = $_FILES['csv_file']['name'] ?? 'unknown';
            $importLog = json_encode([
                'user_id' => $this->userId,
                'timestamp' => date('Y-m-d H:i:s'),
                'file_type' => $fileType
            ]);
            
            $stmt = $this->pdo->prepare("
                INSERT INTO staff_imports 
                (filename, file_type, imported_by, import_date, total_rows, successful_rows, failed_rows, import_log)
                VALUES (?, ?, ?, NOW(), ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $filename,
                $fileType,
                $this->userId,
                $totalRows,
                $successfulRows,
                $failedRows,
                $importLog
            ]);
            
        } catch (Exception $e) {
            error_log("Failed to log import: " . $e->getMessage());
        }
    }
    
    /**
     * Export failed rows to CSV for correction
     * 
     * @param array $failedRows Array of failed row data
     * @param array $errors Array of error messages
     * @return string CSV content
     */
    public function exportFailedRows($failedRows, $errors) {
        $csv = "# FAILED ROWS - Please correct and re-import\n";
        $csv .= "# Errors encountered:\n";
        foreach ($errors as $error) {
            $csv .= "# " . str_replace("\n", "\n# ", $error) . "\n";
        }
        $csv .= "\n";
        
        // Add header
        if (!empty($failedRows)) {
            $csv .= implode(',', array_keys($failedRows[0])) . "\n";
            
            // Add failed rows
            foreach ($failedRows as $row) {
                $csv .= implode(',', $row) . "\n";
            }
        }
        
        return $csv;
    }
}
