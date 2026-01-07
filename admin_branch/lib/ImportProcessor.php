<?php
require_once __DIR__ . '/ImportValidator.php';
require_once __DIR__ . '/UserAccountGenerator.php';
/**
 * ImportProcessor
 * Handles staff data import with auto-correction, temporal rank mapping, upsert logic, and quick-fix CSV generation.
 */
class ImportProcessor {
    private $pdo;
    private $validator;
    private $accountGenerator;
    private $userId;

    // Field name mappings
    const FIELD_MAPPINGS = [
        'fornames' => ['fornames', 'FORENAMES', 'fName', 'firstname', 'fname', 'first name', 'First Name'],
        'surnames' => ['surnames', 'SURNAME', 'lName', 'lastname', 'lname', 'surname', 'last name', 'Last Name'],
        'email' => ['email', 'EMAIL', 'Email', 'e-mail', 'email_address', 'Email Address'],
        'dob' => ['dob', 'DOB', 'date_of_birth', 'dateofbirth', 'birth_date', 'Date of Birth'],
        'service number' => ['service number', 'SVC NO', 'svcNo', 'svc_no', 'service no'],
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

    public function __construct($pdo, $userId = 0) {
        $this->pdo = $pdo;
        $this->validator = new ImportValidator($pdo);
        $this->accountGenerator = new UserAccountGenerator($pdo);
        $this->userId = $userId;
    }

    private function normalizeFieldNames($data) {
        $normalized = [];
        foreach ($data as $key => $value) {
            $normalized[$key] = $value;
        }
        foreach (self::FIELD_MAPPINGS as $standardName => $variations) {
            foreach ($variations as $variation) {
                if (isset($data[$variation])) {
                    $normalized[$standardName] = $data[$variation];
                    break;
                }
            }
        }
        return $normalized;
    }

    private function stripSpecialChars($str) {
        return preg_replace('/[^a-zA-Z0-9]/', '', strtoupper(trim($str)));
    }

    private function findOrCreateUnit($unitCode) {
        if (empty($unitCode)) return null;
        $cleanCode = $this->stripSpecialChars($unitCode);
        $stmt = $this->pdo->query("SELECT unitId, code FROM unit");
        $units = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($units as $unit) {
            if ($this->stripSpecialChars($unit['code']) === $cleanCode) return $unit['unitId'];
        }
        $newUnitId = $cleanCode ?: strtoupper(trim($unitCode));
        $checkStmt = $this->pdo->prepare("SELECT unitId FROM unit WHERE unitId = ?");
        $checkStmt->execute([$newUnitId]);
        if ($checkStmt->fetch()) return $newUnitId;
        try {
            $stmt = $this->pdo->prepare("INSERT INTO unit (unitId, code) VALUES (?, ?)");
            $stmt->execute([$newUnitId, trim($unitCode)]);
        } catch (PDOException $e) {
            if ($e->getCode() == 23000) return $newUnitId;
            throw $e;
        }
        return $newUnitId;
    }

    private function findOrCreateCorps($corpsAbbr) {
        if (empty($corpsAbbr)) return null;
        $cleanAbbr = $this->stripSpecialChars($corpsAbbr);
        $stmt = $this->pdo->query("SELECT corpsId AS id, abbreviation FROM corps");
        $corpsList = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($corpsList as $corps) {
            if ($this->stripSpecialChars($corps['abbreviation']) === $cleanAbbr) return $corps['id'];
        }
        $newId = $cleanAbbr ?: strtoupper(trim($corpsAbbr));
        $checkStmt = $this->pdo->prepare("SELECT corpsId FROM corps WHERE corpsId = ?");
        $checkStmt->execute([$newId]);
        if ($checkStmt->fetch()) return $newId;
        try {
            $stmt = $this->pdo->prepare("INSERT INTO corps (corpsId, abbreviation) VALUES (?, ?)");
            $stmt->execute([$newId, trim($corpsAbbr)]);
        } catch (PDOException $e) {
            if ($e->getCode() == 23000) return $newId;
            throw $e;
        }
        return $newId;
    }

    private function findRankByAbbreviation($rankAbbr, $isTemporal = false) {
        if (empty($rankAbbr)) return null;
        $searchValue = trim($rankAbbr);
        if ($isTemporal) {
            $searchValue = preg_replace('/^T\s*\/\s*/i', '', $searchValue);
            $searchValue = 'T/' . trim($searchValue);
        }
        $stmt = $this->pdo->query("SELECT rankId AS id, rankId AS abbreviation FROM `rank`");
        $ranks = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($ranks as $rank) {
            $normalizedSearch = $this->normalizeRankAbbreviation($searchValue);
            $normalizedDb = $this->normalizeRankAbbreviation($rank['abbreviation']);
            if ($normalizedSearch === $normalizedDb) return $rank['id'];
        }
        return null;
    }

    private function normalizeRankAbbreviation($abbr) {
        if (empty($abbr)) return '';
        $normalized = trim($abbr);
        $normalized = preg_replace('/\s*\/\s*/', '/', $normalized);
        $normalized = preg_replace('/[^a-zA-Z0-9\/]/', '', $normalized);
        return strtoupper($normalized);
    }

    private function autoCorrectRows($rows) {
        $provinceAbbr = [
            'CB' => 'Copperbelt', 'CE' => 'Central', 'EA' => 'Eastern', 'LP' => 'Luapula',
            'LK' => 'Lusaka', 'MU' => 'Muchinga', 'NO' => 'Northern', 'NW' => 'North-Western',
            'SO' => 'Southern', 'WE' => 'Western'
        ];
        $validProvinces = array_values($provinceAbbr);
        $validBloodGps = ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'];
        $validMarital = ['Single', 'Married', 'Divorced', 'Widowed', 'Separated'];
        $correctedRows = [];
        foreach ($rows as $row) {
            $row = $this->normalizeFieldNames($row);
            // Province
            if (!empty($row['province'])) {
                $prov = strtoupper(str_replace(['-', ' '], '', $row['province']));
                // Direct abbreviation
                if (isset($provinceAbbr[$prov])) {
                    $row['province'] = $provinceAbbr[$prov];
                } else {
                    // Find closest match using Levenshtein distance
                    $bestMatch = '';
                    $bestScore = 99;
                    foreach ($validProvinces as $valid) {
                        $score = levenshtein($prov, strtoupper(str_replace(['-', ' '], '', $valid)));
                        if ($score < $bestScore) {
                            $bestScore = $score;
                            $bestMatch = $valid;
                        }
                    }
                    // Accept match if reasonably close (score <= 3)
                    if ($bestScore <= 3) {
                        $row['province'] = $bestMatch;
                    } else {
                        $row['province'] = '';
                    }
                }
                } // Closing the province if-block
            // Blood group
            if (!empty($row['bloodGp'])) {
                $bg = strtoupper(str_replace([' ', '-', '0'], ['','','O'], $row['bloodGp']));
                if ($bg === 'O+' || $bg === 'O-') {
                    $row['bloodGp'] = $bg;
                } else if ($bg === 'O') {
                    $row['bloodGp'] = 'O+';
                } else {
                    // Remove extra trailing chars (e.g. O+B -> O+)
                    foreach ($validBloodGps as $valid) {
                        if (strpos($bg, str_replace(['+', '-'], '', $valid)) === 0) {
                            $row['bloodGp'] = $valid;
                            break;
                        }
                    }
                }
                // If still invalid, set empty
                if (!in_array($row['bloodGp'], $validBloodGps)) {
                    $row['bloodGp'] = '';
                }
            }
            // Marital status
            if (empty($row['marital']) || !in_array(ucfirst(strtolower(trim($row['marital']))), $validMarital)) {
                $row['marital'] = 'Single';
            } else {
                $row['marital'] = ucfirst(strtolower(trim($row['marital'])));
            }
            // Date of birth
            if (!empty($row['dob'])) {
                $dob = trim($row['dob']);
                // Fix double hyphens and other common errors
                $dob = preg_replace('/-+/', '-', $dob);
                // Convert slashes to dashes
                $dob = str_replace('/', '-', $dob);
                // Try to parse to Y-m-d
                $ts = strtotime($dob);
                if ($ts && date('Y-m-d', $ts) !== '1970-01-01') {
                    $row['dob'] = date('Y-m-d', $ts);
                } else {
                    $row['dob'] = '';
                }
            } else {
                $row['dob'] = '';
            }
            // AttestDate
            if (!empty($row['attestDate'])) {
                $ad = trim($row['attestDate']);
                $ad = str_replace('/', '-', $ad);
                $ts = strtotime($ad);
                if ($ts && date('Y-m-d', $ts) !== '1970-01-01') {
                    $row['attestDate'] = date('Y-m-d', $ts);
                } else {
                    $row['attestDate'] = '';
                }
            }
            // subWef
            if (!empty($row['subWef'])) {
                $sw = trim($row['subWef']);
                $sw = str_replace('/', '-', $sw);
                $ts = strtotime($sw);
                if ($ts && date('Y-m-d', $ts) !== '1970-01-01') {
                    $row['subWef'] = date('Y-m-d', $ts);
                } else {
                    $row['subWef'] = '';
                }
            }
            // NRC
            if (!empty($row['NRC'])) {
                $nrc = strtoupper(trim($row['NRC']));
                if (in_array($nrc, ['N/A', 'NA', 'UNKNOWN', 'NONE', '-', 'NULL', 'NIL', '000000/00/0'])) {
                    $nrc = '';
                } elseif (preg_match('/^(\d{6}\/\d{2})$/', $nrc)) {
                    $nrc .= '/1';
                }
                if (!preg_match('/^\d{6}\/\d{2}\/\d{1}$/', $nrc)) $nrc = '';
                $row['NRC'] = $nrc;
            }
            // SERVICE NUMBER normalization and prefix extraction
            if (!empty($row['service number'])) {
                $svcRaw = trim($row['service number']);
                // Extract up to two leading letters (if present)
                if (preg_match('/^([A-Za-z]{1,2})(?:[-\s]*)/u', $svcRaw, $m)) {
                    $lead = strtoupper($m[1]);
                    $svcRest = preg_replace('/^' . preg_quote($m[1], '/') . '(?:[-\s]*)/u', '', $svcRaw);
                    // Allowed leading sequences (max two letters)
                    $allowed = ['W','S','SW','Q','QW'];
                    // If the row already has a prefix and it's one of allowed, prefer existing prefix
                    $existingPrefix = isset($row['prefix']) ? strtoupper(trim($row['prefix'])) : '';
                    if (in_array($lead, $allowed)) {
                        if (empty($existingPrefix) || !in_array($existingPrefix, $allowed)) {
                            // Only set prefix if empty or not an allowed value
                            $row['prefix'] = $lead;
                        }
                        // Remove leading letters from svc portion
                        $svcRaw = $svcRest;
                    } else {
                        // Leading letters present but not allowed -> remove them
                        $svcRaw = $svcRest;
                    }
                }
                // If no leading letters matched or after removal, remove any stray non-digits
                $digits = preg_replace('/\D/', '', $svcRaw);
                // If the svc has fewer than 6 digits, left-pad with zeros to 6 digits
                if ($digits === '') {
                    $row['service number'] = '';
                } else {
                    if (strlen($digits) < 6) {
                        $digits = str_pad($digits, 6, '0', STR_PAD_LEFT);
                    }
                    $row['service number'] = $digits;
                }
            }
            // First name - do not auto-fill unknowns; leave empty to allow validator to reject
            if (empty($row['fornames'])) {
                if (!empty($row['full_name'])) {
                    $parts = explode(' ', $row['full_name']);
                    $row['fornames'] = $parts[0];
                } else {
                    $row['fornames'] = '';
                }
            }
            // Surname correction - leave empty if missing so validator enforces requirement
            if (empty($row['surnames'])) {
                $row['surnames'] = '';
            }
            // Temporal rank mapping
            if (!empty($row['tempWef']) && !empty($row['rankId'])) {
                $row['rankId'] = $this->findRankByAbbreviation($row['rankId'], true);
            }
            // Gender inference from prefix when gender is missing/invalid
            if (empty($row['gender']) || !in_array(strtolower(trim($row['gender'])), ['male', 'female', 'm', 'f'])) {
                $prefixVal = '';
                if (isset($row['prefix'])) $prefixVal = strtoupper(trim($row['prefix']));
                // Also consider normalized prefix variants (e.g., 'W', 'Sw', 'Qw')
                $femalePrefixes = ['SW', 'QW', 'W'];
                if (in_array($prefixVal, $femalePrefixes)) {
                    $row['gender'] = 'Female';
                } else {
                    $row['gender'] = 'Male';
                }
            }
            $correctedRows[] = $row;
        }
        return $correctedRows;
    }

    public function processCSV($filePath, $importMode = 'skip', $dryRun = false) {
        $dataRows = [];
        $handle = fopen($filePath, 'r');
        if ($handle === false) return ['success' => [], 'errors' => ['Failed to open uploaded file']];
        $header = fgetcsv($handle);
        if (!$header) { fclose($handle); return ['success' => [], 'errors' => ['CSV file is empty or invalid']]; }
        while (($row = fgetcsv($handle)) !== false) {
            if (!empty($row[0]) && substr(trim($row[0]), 0, 1) === '#') continue;
            if (count($row) === count($header)) $dataRows[] = array_combine($header, $row);
        }
        fclose($handle);
        $correctedRows = $this->autoCorrectRows($dataRows);
        // Pass original rows alongside corrected rows so failed-rows CSV can mirror original file format
        return $this->processData($correctedRows, 'CSV', $dataRows, $importMode, $dryRun);
    }

    public function processExcel($filePath, $importMode = 'skip', $dryRun = false) {
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
            foreach ($worksheet->getRowIterator(1, 1) as $row) {
                foreach ($row->getCellIterator() as $cell) {
                    $header[] = $cell->getValue();
                }
            }
            foreach ($worksheet->getRowIterator(2) as $row) {
                $rowData = [];
                $cellIterator = $row->getCellIterator();
                $cellIterator->setIterateOnlyExistingCells(false);
                foreach ($cellIterator as $cell) {
                    $value = $cell->getValue();
                    if ($cell->getDataType() === \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC) {
                        if (\PhpOffice\PhpSpreadsheet\Shared\Date::isDateTime($cell)) {
                            $value = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($value)->format('Y-m-d');
                        }
                    }
                    $rowData[] = $value;
                }
                if (!empty($rowData[0]) && substr(trim($rowData[0]), 0, 1) === '#') continue;
                if (count(array_filter($rowData)) > 0) $dataRows[] = array_combine($header, $rowData);
            }
            $correctedRows = $this->autoCorrectRows($dataRows);
            // Pass original rows alongside corrected rows so failed-rows CSV can mirror original file format
            return $this->processData($correctedRows, 'Excel', $dataRows, $importMode, $dryRun);
        } catch (Exception $e) {
            return [
                'success' => [],
                'errors' => ['Excel processing failed: ' . $e->getMessage()]
            ];
        }
    }

    private function processData($dataRows, $fileType = 'CSV', $originalRows = null, $importMode = 'skip', $dryRun = false) {
        $success = [];
        $errors = [];
        $userCredentials = [];
        $debugQueries = [];
        $validRows = [];
        $dbDuplicatesCount = 0;
        $preview = ['would_insert' => 0, 'would_update' => 0, 'would_skip' => 0];
        $previewSample = [];

        // If dryRun requested, start a transaction that we'll roll back at the end
        $weStartedTx = false;
        if ($dryRun) {
            if (!$this->pdo->inTransaction()) {
                $this->pdo->beginTransaction();
                $weStartedTx = true;
            }
        }

        // Perform rankId update from tempRank as part of the run.
        // For dryRun we run without managing transaction so the caller's transaction (started above) will roll it back.
        $rankUpdateRes = ['affected' => 0, 'backup' => null];
        try {
            if ($dryRun) {
                // run without internal transaction so it is rolled back with caller transaction
                $rankUpdateRes = $this->updateRankIdsFromTempRank(false);
            } else {
                // run with its own transaction and commit (real run)
                $rankUpdateRes = $this->updateRankIdsFromTempRank(true);
            }
            if (isset($rankUpdateRes['affected']) && $rankUpdateRes['affected'] > 0) {
                $debugQueries[] = ['type' => 'rank_update', 'detail' => $rankUpdateRes];
            }
        } catch (Exception $e) {
            $errors[] = 'Rank update failed: ' . $e->getMessage();
        }

        // Pre-validate all rows and check for duplicates within the file and against DB
        $rowNum = 1;
        $seenEmails = [];
        $seenSvcNos = [];
        $seenNRCs = [];
        $failedRows = [];
        $total = count($dataRows);
        for ($i = 0; $i < $total; $i++) {
            $rowNum = $i + 2; // header is row 1, data starts at row 2
            $data = $dataRows[$i];
            if (!is_array($data)) continue;
            $origRow = null;
            if (is_array($originalRows) && isset($originalRows[$i])) $origRow = $originalRows[$i];
            $data = $this->normalizeFieldNames($data);
            $data = $this->validator->sanitizeData($data);

            $rowErrors = $this->validator->validateRow($data, $rowNum);

            // file-level duplicates
            if (!empty($data['email'])) {
                $emailKey = strtolower(trim($data['email']));
                if (in_array($emailKey, $seenEmails)) {
                    $rowErrors[] = "Row $rowNum: Duplicate email '{$data['email']}' in import file";
                } else {
                    $seenEmails[] = $emailKey;
                }
            }
            if (!empty($data['service number'])) {
                $svc = trim($data['service number']);
                $svcDigits = preg_replace('/\D/', '', $svc);
                $normalizedSvc = str_pad($svcDigits ?: $svc, 6, '0', STR_PAD_LEFT);
                if (in_array($normalizedSvc, $seenSvcNos)) {
                    $rowErrors[] = "Row $rowNum: Duplicate service number '$normalizedSvc' in import file";
                } else {
                    $seenSvcNos[] = $normalizedSvc;
                    $data['service number'] = $normalizedSvc;
                }
            }
            if (!empty($data['NRC'])) {
                $nrcNorm = trim($data['NRC']);
                if (in_array($nrcNorm, $seenNRCs)) {
                    $rowErrors[] = "Row $rowNum: Duplicate NRC '$nrcNorm' in import file";
                } else {
                    $seenNRCs[] = $nrcNorm;
                }
            }

            // Determine mapped rank for preview purposes (try tempRank then subRank)
            $mappedRank = null;
            if (!empty($data['tempRank'])) {
                $mappedRank = $this->findRankByAbbreviation($data['tempRank'], true);
            }
            if (empty($mappedRank) && !empty($data['subRank'])) {
                $mappedRank = $this->findRankByAbbreviation($data['subRank'], false);
            }
            $data['__mapped_rank'] = $mappedRank;

            if (!empty($rowErrors)) {
                // Merge errors for UI
                $errors = array_merge($errors, $rowErrors);
                // If the errors are duplicates within the file, DO NOT include them in the downloadable CSV
                $hasDuplicate = false;
                foreach ($rowErrors as $re) {
                    if (stripos($re, 'Duplicate email') !== false || stripos($re, 'Duplicate service number') !== false || stripos($re, 'Duplicate NRC') !== false) {
                        $hasDuplicate = true;
                        break;
                    }
                }
                if (!$hasDuplicate) {
                    // Map error messages to column labels for highlighting
                    $errorCols = [];
                    foreach ($rowErrors as $re) {
                        $r = strtolower($re);
                        if (strpos($r, 'first name') !== false || strpos($r, 'fornames') !== false) $errorCols[] = 'FORENAMES';
                        if (strpos($r, 'surname') !== false || strpos($r, 'surnames') !== false) $errorCols[] = 'SURNAME';
                        if (strpos($r, 'service number') !== false || strpos($r, 'svcno') !== false) $errorCols[] = 'SVC NO';
                        if (strpos($r, 'email') !== false) $errorCols[] = 'EMAIL';
                        if (strpos($r, 'nrc') !== false) $errorCols[] = 'NRC';
                        if (strpos($r, 'date') !== false || strpos($r, 'dob') !== false) $errorCols[] = 'DOB';
                        if (strpos($r, 'rank') !== false) $errorCols[] = 'RANK PREFIX';
                        if (strpos($r, 'unit') !== false) $errorCols[] = 'UNIT';
                        if (strpos($r, 'corps') !== false) $errorCols[] = 'CORPS';
                        if (strpos($r, 'phone') !== false || strpos($r, 'tel') !== false || strpos($r, 'mobile') !== false) $errorCols[] = 'MOBILE';
                        if (strpos($r, 'gender') !== false) $errorCols[] = 'GENDER';
                        if (strpos($r, 'marital') !== false) $errorCols[] = 'MARITAL';
                    }
                    $errorCols = array_values(array_unique($errorCols));
                    $failedRows[] = ['__original' => $origRow ?: $data, '__normalized' => $data, '__errors' => implode(' | ', $rowErrors), '__error_cols' => $errorCols];
                }
            } else {
                // Ensure service number present - do not import rows without service number
                if (empty($data['service number']) || trim($data['service number']) === '') {
                    $err = "Row $rowNum: Missing service number - row will not be imported";
                    $errors[] = $err;
                    $failedRows[] = ['__original' => $origRow ?: $data, '__normalized' => $data, '__errors' => $err, '__error_cols' => ['SVC NO']];
                } else {
                    // If service number already exists in DB, treat as DB-duplicate: skip and count it
                    $svcToCheck = $data['service number'];
                    if ($this->validator->existsServiceNumber($svcToCheck)) {
                        if ($importMode === 'skip') {
                            $dbDuplicatesCount++;
                            // skip
                            $preview['would_skip']++;
                            $data['__import_action'] = 'skip';
                            // record sample if within first 20
                            if (count($previewSample) < 20) {
                                $previewSample[] = ['row' => $rowNum, 'svc' => $svcToCheck, 'action' => 'skip', 'mapped_rank' => $data['__mapped_rank'] ?? null];
                            }
                            continue;
                        } else {
                            // update or merge => include in validRows to be updated
                            $data['__existing_svc'] = $svcToCheck;
                            $data['__import_action'] = ($importMode === 'merge') ? 'merge' : 'update';
                            $validRows[] = $data;
                            $preview['would_update']++;
                            if (count($previewSample) < 20) {
                                $previewSample[] = ['row' => $rowNum, 'svc' => $svcToCheck, 'action' => ($importMode === 'merge' ? 'merge' : 'update'), 'mapped_rank' => $data['__mapped_rank'] ?? null];
                            }
                        }
                    } else {
                        $data['__import_action'] = 'insert';
                        $validRows[] = $data;
                        $preview['would_insert']++;
                        if (count($previewSample) < 20) {
                            $previewSample[] = ['row' => $rowNum, 'svc' => $data['service number'] ?? '', 'action' => 'insert', 'mapped_rank' => $data['__mapped_rank'] ?? null];
                        }
                    }
                }
            }
        }
        // Continue: we will insert validRows and return failedRows for download

        $dbMap = [
            'prefix' => 'prefix', 'service number' => 'svcNo', 'subRank' => 'subRank', 'subWef' => 'subWef',
            'tempRank' => 'tempRank', 'tempWef' => 'tempWef', 'initials' => 'initials', 'fornames' => 'fName',
            'surnames' => 'lName', 'titles' => 'titles', 'attestDate' => 'attestDate', 'unitId' => 'unitId',
            'unitAtt' => 'unitAtt', 'appt' => 'apptId', 'dob' => 'DOB', 'gender' => 'gender', 'province' => 'province',
            'corpsId' => 'corpsId', 'bloodGp' => 'bloodGp', 'NRC' => 'NRC', 'intake' => 'intake', 'marital' => 'marital',
            'email' => 'email', 'tel' => 'tel', 'rankId' => 'rankId'
        ];

        try {
            // We will perform inserts/updates per-row; validation errors do not roll back already-inserted rows.
            $rowNum = 1;
            foreach ($validRows as $data) {
                $rowNum++;
                // smart lookups
                if (isset($data['unitId']) && !empty($data['unitId']) && !is_numeric($data['unitId'])) {
                    $unitId = $this->findOrCreateUnit($data['unitId']);
                    if ($unitId) $data['unitId'] = $unitId;
                }
                if (isset($data['corpsId']) && !empty($data['corpsId']) && !is_numeric($data['corpsId'])) {
                    $corpsId = $this->findOrCreateCorps($data['corpsId']);
                    if ($corpsId) $data['corpsId'] = $corpsId;
                }

                // Map subRank then tempRank override
                if (isset($data['subRank']) && !empty($data['subRank'])) {
                    $subRankId = $this->findRankByAbbreviation($data['subRank'], false);
                    if ($subRankId) $data['rankId'] = $subRankId;
                }
                if (isset($data['tempRank']) && !empty($data['tempRank'])) {
                    $tempRankId = $this->findRankByAbbreviation($data['tempRank'], true);
                    if ($tempRankId) $data['rankId'] = $tempRankId;
                }

                // normalize placeholders
                if (isset($data['NRC']) && $data['NRC'] !== '') {
                    $nrc = trim($data['NRC']);
                    $placeholders = ['N/A', 'NA', 'UNKNOWN', 'NONE', '-', 'NULL', 'NIL', '000000/00/0'];
                    if (in_array(strtoupper($nrc), $placeholders)) $data['NRC'] = null;
                }
                if (isset($data['service number']) && !empty($data['service number'])) {
                    $serviceNum = trim($data['service number']);
                    $digitsOnly = preg_replace('/\D/', '', $serviceNum);
                    if (!empty($digitsOnly)) $data['service number'] = str_pad($digitsOnly, 6, '0', STR_PAD_LEFT);
                }

                // Build a mapping of DB field => value to preserve ordering when filtering for merge
                $fieldValues = [];
                foreach ($dbMap as $csvField => $dbField) {
                    if (isset($data[$csvField]) && $data[$csvField] !== '' && $data[$csvField] !== null) {
                        $fieldValues[$dbField] = $data[$csvField];
                    }
                }
                if (empty($fieldValues)) {
                    $err = "Row $rowNum: No fields to insert for this row";
                    $errors[] = $err;
                    $failedRows[] = ['__original' => $data, '__normalized' => $data, '__errors' => $err];
                    continue;
                }

                // upsert detection
                $whereClauses = [];
                $whereValues = [];
                if (!empty($data['NRC'])) { $whereClauses[] = 'NRC = ?'; $whereValues[] = $data['NRC']; }
                if (!empty($data['service number'])) { $whereClauses[] = 'svcNo = ?'; $whereValues[] = $data['service number']; }
                if (!empty($data['email'])) { $whereClauses[] = 'email = ?'; $whereValues[] = $data['email']; }

                $svcNo = null;
                if (!empty($data['service number'])) $svcNo = $data['service number'];
                if (!empty($whereClauses)) {
                    $sqlCheck = 'SELECT svcNo FROM staff WHERE ' . implode(' OR ', $whereClauses) . ' LIMIT 1';
                    $stmtCheck = $this->pdo->prepare($sqlCheck);
                    $stmtCheck->execute($whereValues);
                    $existing = $stmtCheck->fetch(PDO::FETCH_ASSOC);
                    if ($existing && isset($existing['svcNo'])) $svcNo = $existing['svcNo'];
                }

                if ($svcNo) {
                    try {
                        $updateFields = [];
                        // Determine which DB fields will be updated and preserve corresponding values
                        if (isset($data['__import_action']) && $data['__import_action'] === 'merge') {
                            $allowedMergeDbFields = ['email', 'tel', 'rankId', 'unitId', 'corpsId', 'apptId', 'bloodGp', 'marital', 'province', 'prefix'];
                            $fieldsToUpdate = array_values(array_intersect(array_keys($fieldValues), $allowedMergeDbFields));
                        } else {
                            $fieldsToUpdate = array_values(array_keys($fieldValues));
                        }
                        // Never allow updating the primary key `svcNo` via an UPDATE operation
                        if (($idx = array_search('svcNo', $fieldsToUpdate)) !== false) {
                            unset($fieldsToUpdate[$idx]);
                            $fieldsToUpdate = array_values($fieldsToUpdate);
                        }
                        if (empty($fieldsToUpdate)) {
                            throw new Exception("No fields to update for svcNo $svcNo (after merge filtering)");
                        }
                        foreach ($fieldsToUpdate as $field) $updateFields[] = "$field = ?";
                        $sqlUpdate = 'UPDATE staff SET ' . implode(', ', $updateFields) . ' WHERE svcNo = ?';
                        $stmtUpdate = $this->pdo->prepare($sqlUpdate);
                        $updateValues = [];
                        foreach ($fieldsToUpdate as $f) $updateValues[] = $fieldValues[$f];
                        $updateValues[] = $svcNo;
                        $stmtUpdate->execute($updateValues);
                        $success[] = "Row $rowNum: Updated existing staff record (svcNo: $svcNo)";
                        $debugQueries[] = ['type' => 'update', 'sql' => $sqlUpdate, 'values' => $updateValues];
                    } catch (Exception $e) {
                        $err = "Row $rowNum: Failed to update existing staff (svcNo: $svcNo) - " . $e->getMessage();
                        $errors[] = $err;
                        $failedRows[] = ['__original' => $data, '__normalized' => $data, '__errors' => $err];
                        continue;
                    }
                } else {
                    try {
                        // Prepare insert using fieldValues map to preserve alignment
                        $insertFields = array_keys($fieldValues);
                        $insertPlaceholders = array_fill(0, count($insertFields), '?');
                        $insertValues = [];
                        foreach ($insertFields as $f) $insertValues[] = $fieldValues[$f];
                        $sql = 'INSERT INTO staff (' . implode(',', $insertFields) . ') VALUES (' . implode(',', $insertPlaceholders) . ')';
                        $insert = $this->pdo->prepare($sql);
                        $insertSuccess = $insert->execute($insertValues);
                        $debugQueries[] = ['type' => 'insert', 'sql' => $sql, 'values' => $insertValues];
                        if (!$insertSuccess) throw new Exception("Database insert failed for row $rowNum");

                        $svcNo = $data['service number'] ?? null;
                        if (!empty($data['email'])) {
                            $accountResult = $this->accountGenerator->createAccount(
                                $svcNo,
                                $data['fornames'] ?? '',
                                $data['surnames'] ?? '',
                                $data['email']
                            );
                            if (!empty($accountResult['success'])) {
                                $userCredentials[] = [
                                    'name' => trim(($data['fornames'] ?? '') . ' ' . ($data['surnames'] ?? '')),
                                    'email' => $data['email'],
                                    'username' => $accountResult['username'],
                                    'password' => $accountResult['password']
                                ];
                            }
                        }

                        $success[] = "Row $rowNum: " . trim(($data['fornames'] ?? '') . ' ' . ($data['surnames'] ?? '')) . " imported successfully";
                        $this->validator->addToEmailCheck($data['email'] ?? '');
                        if (isset($data['service number'])) $this->validator->addToServiceNumberCheck($data['service number']);
                        if (isset($data['NRC'])) $this->validator->addToNRCCheck($data['NRC']);
                    } catch (Exception $e) {
                        $err = "Row $rowNum: Failed to insert staff - " . $e->getMessage();
                        $errors[] = $err;
                        $failedRows[] = ['__original' => $data, '__normalized' => $data, '__errors' => $err];
                        continue;
                    }
                }
            }

            // If dryRun, roll back any DB changes we just made
            if ($dryRun && $weStartedTx) {
                try { $this->pdo->rollBack(); } catch (Exception $ex) {}
                $transactionRolledBack = true;
            } else {
                $transactionRolledBack = false;
            }

            // After processing all valid rows, build CSV of failed rows and store for download
            if (!empty($failedRows)) {
                $_SESSION['quick_fix_csv'] = $this->exportFailedRows($failedRows, $errors);
            } else {
                unset($_SESSION['quick_fix_csv']);
            }

            $summaryMessage = count($success) . ' staff members imported. ' . count($failedRows) . ' rows failed and are available for download.';
            if (!empty($dbDuplicatesCount)) {
                $summaryMessage .= ' ' . $dbDuplicatesCount . ' rows matched existing records and were skipped.';
                $errors[] = $dbDuplicatesCount . ' rows matched existing records and were skipped (duplicates in database).';
            }
            $summary = [
                'title' => 'Import Completed',
                'message' => $summaryMessage
            ];
            $this->logImport($fileType, count($dataRows), count($success), count($failedRows), $debugQueries);
            return [
                'success' => $success,
                'errors' => $errors,
                'credentials' => $userCredentials,
                'summary' => $summary,
                'transaction_rolled_back' => !empty($transactionRolledBack),
                'failed_rows_count' => count($failedRows),
                'preview' => $preview,
                'rank_update' => $rankUpdateRes,
                'preview_sample' => $previewSample
            ];

        } catch (Exception $e) {
            try { if ($this->pdo->inTransaction()) $this->pdo->rollBack(); } catch (Exception $ex) {}
            error_log("ImportProcessor unexpected error: " . $e->getMessage());
            $errors[] = 'Import failed: ' . $e->getMessage();
            $summary = [
                'title' => 'Import Failed - Database Error',
                'message' => 'Transaction rolled back - No records were imported.'
            ];
            $this->logImport($fileType, count($dataRows), 0, count($errors), $debugQueries);
            return [
                'success' => [],
                'errors' => $errors,
                'credentials' => [],
                'summary' => $summary,
                'transaction_rolled_back' => true
            ];
        }
    }

    private function logImport($fileType, $totalRows, $successfulRows, $failedRows, $debugQueries = []) {
        try {
            $filename = $_FILES['csv_file']['name'] ?? 'unknown';
            $importLog = json_encode([
                'user_id' => $this->userId,
                'timestamp' => date('Y-m-d H:i:s'),
                'file_type' => $fileType,
                'debug' => $debugQueries
            ]);
            $stmt = $this->pdo->prepare("INSERT INTO staff_imports (filename, file_type, imported_by, import_date, total_rows, successful_rows, failed_rows, import_log) VALUES (?, ?, ?, NOW(), ?, ?, ?, ?)");
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
     * Update staff.rankId for rows where rankId IS NULL and tempRank is present
     * Uses rank.abbreviation = CONCAT('T', tempRank)
     * Creates a backup CSV of affected rows in tmp/ before applying update
     * Returns number of rows affected or throws on error
     */
    public function updateRankIdsFromTempRank($useTransaction = true) {
        $backupFile = __DIR__ . '/../../tmp/rankid_update_backup_' . date('Ymd_His') . '.csv';
        // Fetch affected rows for backup
        $stmt = $this->pdo->prepare("SELECT s.* FROM staff s JOIN `rank` r ON r.rankId = CONCAT('T', s.tempRank) WHERE s.rankId IS NULL AND s.tempRank IS NOT NULL");
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if (!empty($rows)) {
            $fp = fopen($backupFile, 'w');
            fputcsv($fp, array_keys($rows[0]));
            foreach ($rows as $r) fputcsv($fp, $r);
            fclose($fp);
        }

        // Prepare the update SQL
        $sql = "UPDATE staff s JOIN `rank` r ON r.rankId = CONCAT('T', s.tempRank) SET s.rankId = r.rankId WHERE s.rankId IS NULL AND s.tempRank IS NOT NULL";

        // If caller asked us to manage a transaction but one is already active, use a SAVEPOINT
        if ($useTransaction) {
            if ($this->pdo->inTransaction()) {
                $sp = 'sp_rank_update_' . time() . '_' . rand(1000,9999);
                try {
                    $this->pdo->exec("SAVEPOINT $sp");
                    $affected = $this->pdo->exec($sql);
                    $this->pdo->exec("RELEASE SAVEPOINT $sp");
                    return ['affected' => $affected, 'backup' => file_exists($backupFile) ? $backupFile : null];
                } catch (Exception $e) {
                    try { $this->pdo->exec("ROLLBACK TO SAVEPOINT $sp"); } catch (Exception $ex) {}
                    throw $e;
                }
            } else {
                try {
                    $this->pdo->beginTransaction();
                    $affected = $this->pdo->exec($sql);
                    $this->pdo->commit();
                    return ['affected' => $affected, 'backup' => file_exists($backupFile) ? $backupFile : null];
                } catch (Exception $e) {
                    try { if ($this->pdo->inTransaction()) $this->pdo->rollBack(); } catch (Exception $ex) {}
                    throw $e;
                }
            }
        } else {
            // Run without managing transaction so caller (e.g. dry-run) can control rollback
            $affected = $this->pdo->exec($sql);
            return ['affected' => $affected, 'backup' => file_exists($backupFile) ? $backupFile : null];
        }
    }

    public function exportFailedRows($failedRows, $errors) {
        // Use an in-memory stream to build CSV with proper escaping
        $fp = fopen('php://temp', 'r+');
        // NOTE: Per request, do not output commented error messages at the top.
        // We will include per-row 'ImportErrors' and 'ErrorColumns' fields instead.

        if (empty($failedRows)) {
            rewind($fp);
            $csv = stream_get_contents($fp);
            fclose($fp);
            return $csv;
        }

        // Enforce strict header order as requested
        $headerMap = [
            'RANK PREFIX' => 'prefix',
            'SVC NO' => 'service number',
            'PREFIX NORMALIZED' => '__normalized_prefix',
            'SVC NO NORMALIZED' => '__normalized_svc',
            'SUB RANK' => 'subRank',
            'SUB RANK WEF' => 'subWef',
            'TEMP RANK' => 'tempRank',
            'TEMP RANK WEF' => 'tempWef',
            'INITIALS' => 'initials',
            'FORENAMES' => 'fornames',
            'SURNAME' => 'surnames',
            'TITLES' => 'titles',
            'ATTESTATION DATE' => 'attestDate',
            'UNIT' => 'unitId',
            'UNIT ATTACHED' => 'unitAtt',
            'APPT' => 'appt',
            'DOB' => 'dob',
            'GENDER' => 'gender',
            'PROVINCE' => 'province',
            'CORPS' => 'corpsId',
            'BLOOD GP' => 'bloodGp',
            'NRC' => 'NRC',
            'INTAKE' => 'intake',
            'MARITAL' => 'marital',
            'EMAIL' => 'email',
            'MOBILE' => 'tel'
        ];

        // Build header row (map keys) and append ErrorColumns column only
        $headerCols = array_keys($headerMap);
        $headerCols[] = 'ErrorColumns';
        fputcsv($fp, $headerCols);

        // For each failed row, normalize the original row to standard keys and pull values in order
        foreach ($failedRows as $row) {
            $orig = isset($row['__original']) && is_array($row['__original']) ? $row['__original'] : [];
            $normalized = $this->normalizeFieldNames($orig);
            $line = [];
            foreach ($headerMap as $label => $stdKey) {
                $val = '';
                // Special-case normalized placeholders that come from the processing step
                if ($stdKey === '__normalized_prefix') {
                    $val = isset($row['__normalized']['prefix']) ? $row['__normalized']['prefix'] : '';
                } elseif ($stdKey === '__normalized_svc') {
                    $val = isset($row['__normalized']['service number']) ? $row['__normalized']['service number'] : '';
                } else {
                    if (isset($normalized[$stdKey]) && $normalized[$stdKey] !== null) {
                        $val = $normalized[$stdKey];
                    } elseif (isset($orig[$label])) {
                        // Fallback if original used exact header label
                        $val = $orig[$label];
                    } else {
                        // Try case-insensitive lookup in original
                        foreach ($orig as $k => $v) {
                            if (strcasecmp($k, $label) === 0 || strcasecmp($k, $stdKey) === 0) { $val = $v; break; }
                        }
                    }
                }
                $line[] = $val;
            }
            // Append ErrorColumns list only (column-level highlighting)
            $line[] = isset($row['__error_cols']) ? implode(',', $row['__error_cols']) : '';
            fputcsv($fp, $line);
        }

        rewind($fp);
        $csv = stream_get_contents($fp);
        fclose($fp);
        return $csv;
    }
}
