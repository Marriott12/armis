<?php
/**
 * User Profile Manager Class
 * Handles all user profile data operations
 */

require_once dirname(__DIR__) . '/shared/database_connection.php';

class UserProfileManager {

    private $pdo;
    private $userId;    // The logged-in person's svcNo (see loadUserInfo note below)
    private $userSvcNo;

    public function __construct($userId) {
        $this->pdo = getDbConnection();
        $this->userId = $userId;
        $this->loadUserInfo();
    }

    /**
     * Load basic user information.
     *
     * NOTE: `staff`'s primary key is `svcNo` - there is no `id` column on
     * staff anywhere in the schema. The users module stores the person's
     * svcNo directly in $_SESSION['user_id'] (see profile.php), so $userId
     * passed into the constructor already IS the svcNo.
     */
    private function loadUserInfo() {
        try {
            $stmt = $this->pdo->prepare("SELECT svcNo FROM staff WHERE svcNo = ? LIMIT 1");
            $stmt->execute([$this->userId]);
            $user = $stmt->fetch(PDO::FETCH_OBJ);
            if ($user && !empty($user->svcNo)) {
                $this->userSvcNo = $user->svcNo;
            } else {
                $this->userSvcNo = 'USER_' . $this->userId;
                error_log("No staff record found for svcNo '{$this->userId}', using fallback: {$this->userSvcNo}");
            }
        } catch (PDOException $e) {
            error_log("Error loading user info: " . $e->getMessage());
            $this->userSvcNo = 'USER_' . $this->userId;
        }
    }

    /**
     * Get comprehensive user profile data.
     * Explicit column list (rather than SELECT *) so the auth columns that
     * live on this same table - password hash, username, role, etc. - never
     * end up loaded into a profile object that gets passed around the view.
     */
    public function getUserProfile() {
        try {
            $stmt = $this->pdo->prepare("
                SELECT
                    svcNo, prefix, rankId, initials, fName, mName, lName, gender, NRC,
                    attestDate, DOB, unitId, corps, bloodGp, telNo, tel2, emailPvt,
                    officialEmail, marital, province, district, village, height,
                    combatSize, bootSize, sSize, hDress, trade, titles, religion,
                    profession, nok, nokNrc, nokRelat, nokTel, altNok, altNokTel,
                    altNokRelat, address, profilePhoto, svcStatus, subWef, tempWef,
                    lastProfileUpdate, dateCreated
                FROM staff
                WHERE svcNo = ?
            ");
            $stmt->execute([$this->userId]);
            $profile = $stmt->fetch(PDO::FETCH_OBJ);

            if (!$profile) {
                error_log("No staff record found for svcNo: " . $this->userId);
                return null;
            }

            // Rank: `rank` has no separate name/abbreviation column - rankId
            // IS the display value (same convention used across this app).
            try {
                if (!empty($profile->rankId)) {
                    $rankStmt = $this->pdo->prepare("SELECT rankId, rankIndex, rankType FROM `rank` WHERE rankId = ?");
                    $rankStmt->execute([$profile->rankId]);
                    $rank = $rankStmt->fetch(PDO::FETCH_OBJ);
                    if ($rank) {
                        $profile->rankName = $rank->rankId;
                        $profile->rankAbbr = $rank->rankId;
                    }
                }
            } catch (PDOException $e) {
                error_log("Rank lookup failed: " . $e->getMessage());
            }

            // Unit: `unit` only has unitId / unitLoc - no code/level columns.
            try {
                if (!empty($profile->unitId)) {
                    $unitStmt = $this->pdo->prepare("SELECT unitId, unitLoc FROM unit WHERE unitId = ?");
                    $unitStmt->execute([$profile->unitId]);
                    $unit = $unitStmt->fetch(PDO::FETCH_OBJ);
                    if ($unit) {
                        $profile->unitName = $unit->unitId . ($unit->unitLoc ? ' - ' . $unit->unitLoc : '');
                        $profile->unitCode = $unit->unitId;
                    }
                }
            } catch (PDOException $e) {
                error_log("Unit lookup failed: " . $e->getMessage());
            }

            // Corps: plain text column directly on staff - there is no
            // separate corps lookup table anywhere in the schema.
            $profile->corps_name = $profile->corps ?: null;

            // Calculated fields
            $profile->age = $this->calculateAge($profile->DOB ?? null);
            $profile->serviceYears = $this->calculateServiceYears($profile->attestDate ?? null);
            $profile->fullName = trim(($profile->fName ?? '') . ' ' . ($profile->mName ?? '') . ' ' . ($profile->lName ?? ''));
            $profile->displayRank = $profile->rankName ?? $profile->rankAbbr ?? 'N/A';

            // Legacy/compatibility field aliases used elsewhere in this app.
            // NOTE: svcNo itself is deliberately left untouched (see
            // displaySvcNo below) - it's the real primary key value and
            // callers rely on it round-tripping cleanly into lookups/forms.
            $profile->fname = $profile->fName ?? '';
            $profile->lname = $profile->lName ?? '';
            $profile->email = $profile->officialEmail ?: ($profile->emailPvt ?: null);
            $profile->tel = $profile->telNo ?: ($profile->tel2 ?: null);
            $profile->displaySvcNo = (!empty($profile->prefix) ? $profile->prefix . ' ' : '') . ($profile->svcNo ?? '');
            $profile->rankID = $profile->rankId ?? null;
            $profile->unitID = $profile->unitId ?? null;
            $profile->unitName = $profile->unitName ?? 'No Unit Assigned';

            // Sizing field aliases: several pages/forms use the short
            // lowercase names (bsize/ssize/hdress) that were never actually
            // real columns - the real columns are bootSize/sSize/hDress.
            // Aliasing here means every page that reads $userData->bsize
            // etc. now actually gets the stored value instead of always
            // showing blank.
            $profile->bsize = $profile->bootSize ?? '';
            $profile->ssize = $profile->sSize ?? '';
            $profile->hdress = $profile->hDress ?? '';

            return $profile;

        } catch (PDOException $e) {
            error_log("Error fetching user profile: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Get all appointments/postings for the user.
     * Real staff_appointment columns: id, apptId, svcNo, apptType, unitId,
     * apptWef, powers, endDate, durationMonths, authorityId, remarks.
     * Aliased to the names used by service.php's display template
     * (appointment/appointment_date/appointment_type_name/unit_name/location),
     * since none of those were ever real column names on this table.
     */
    public function getAppointments() {
        try {
            if (empty($this->userId)) {
                return [];
            }
            $stmt = $this->pdo->prepare("
                SELECT
                    sa.id, sa.apptId AS appointment, sa.apptType,
                    sa.apptWef AS appointment_date, sa.apptWef AS startDate,
                    sa.endDate, sa.durationMonths, sa.powers, sa.remarks,
                    sa.unitId, u.unitId AS unit_name, u.unitLoc AS location,
                    at.type_name AS appointment_type_name,
                    auth.description AS authorityDescription
                FROM staff_appointment sa
                LEFT JOIN unit u ON sa.unitId = u.unitId
                LEFT JOIN appointment_type at ON sa.apptType = at.id
                LEFT JOIN authority auth ON sa.authorityId = auth.authID
                WHERE sa.svcNo = ?
                ORDER BY sa.apptWef DESC
            ");
            $stmt->execute([$this->userId]);
            return $stmt->fetchAll(PDO::FETCH_OBJ);
        } catch (PDOException $e) {
            error_log("Error fetching appointments: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get user's education records (self-reported - see staff_education,
     * a separate table from the admin-entered staff_course).
     */
    public function getEducationRecords() {
        try {
            // Admin-entered qualifications are stored in staff_course in the
            // live schema. Keep the users module's display shape stable.
                $stmt = $this->pdo->prepare("SELECT
                        id, svcNo, instId AS institution, cseId AS course_id,
                        qualification, cseStart AS year_started, cseEnd AS year_completed,
                        grade, result, isHighest AS is_highest_qualification, authID
                    FROM staff_course
                    WHERE svcNo = ?
                    ORDER BY cseEnd DESC, cseStart DESC, id DESC");
            $stmt->execute([$this->userId]);
            $records = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($records as &$record) {
                $record['is_highest_qualification'] = !empty($record['is_highest_qualification']);
                $record['year_started'] = !empty($record['year_started']) ? substr((string)$record['year_started'], 0, 4) : null;
                $record['year_completed'] = !empty($record['year_completed']) ? substr((string)$record['year_completed'], 0, 4) : null;
                $record['start_year'] = $record['year_started'];
                $record['end_year'] = $record['year_completed'];
                $record['level'] = $record['qualification'] ?? '';
                $record['grade_obtained'] = $record['grade'] ?? '';
                $record['status'] = 'Completed';
            }

            return $records;
        } catch (PDOException $e) {
            error_log("Error fetching education records: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Update education records in the live staff_course table.
     */
    public function updateEducationRecords($educationData) {
        try {
            $this->pdo->beginTransaction();
            $existing = $this->getEducationRecords();
            $existingIds = array_map('intval', array_column($existing, 'id'));
            $submittedIds = [];
            $updatedCount = 0;
            $insertedCount = 0;

            foreach ((array)$educationData as $education) {
                $institution = trim((string)($education['institution'] ?? ''));
                $qualification = trim((string)($education['qualification'] ?? ''));
                if ($institution === '' && $qualification === '') continue;

                $id = !empty($education['id']) ? (int)$education['id'] : null;
                $submittedIds[] = $id;
                $startYear = trim((string)($education['year_started'] ?? ''));
                $endYear = trim((string)($education['year_completed'] ?? ''));
                $startDate = preg_match('/^\d{4}$/', $startYear) ? $startYear . '-01-01' : ($startYear ?: null);
                $endDate = preg_match('/^\d{4}$/', $endYear) ? $endYear . '-12-31' : ($endYear ?: null);
                $values = [
                    $institution,
                    trim((string)($education['course_id'] ?? '')),
                    $qualification,
                    $startDate,
                    $endDate,
                    trim((string)($education['grade_obtained'] ?? '')),
                    trim((string)($education['result'] ?? '')),
                    !empty($education['is_highest']) ? 1 : 0,
                    trim((string)($education['authID'] ?? ''))
                ];

                if ($id && in_array($id, $existingIds, true)) {
                    $stmt = $this->pdo->prepare('UPDATE staff_course SET instId = ?, cseId = ?, qualification = ?, cseStart = ?, cseEnd = ?, grade = ?, result = ?, isHighest = ?, authID = ? WHERE id = ? AND svcNo = ?');
                    $stmt->execute(array_merge($values, [$id, $this->userId]));
                    $updatedCount += $stmt->rowCount() > 0 ? 1 : 0;
                } else {
                    $stmt = $this->pdo->prepare('INSERT INTO staff_course (svcNo, instId, cseId, qualification, cseStart, cseEnd, grade, result, isHighest, authID) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
                    $stmt->execute(array_merge([$this->userId], $values));
                    $insertedCount++;
                }
            }

            $deletedCount = 0;
            foreach ($existingIds as $id) {
                if (!in_array($id, $submittedIds, true)) {
                    $stmt = $this->pdo->prepare('DELETE FROM staff_course WHERE id = ? AND svcNo = ?');
                    $stmt->execute([$id, $this->userId]);
                    $deletedCount += $stmt->rowCount();
                }
            }
            $this->pdo->commit();
            return ['success' => true, 'updated' => $updatedCount, 'inserted' => $insertedCount, 'deleted' => $deletedCount, 'message' => "Education records updated successfully. Updated: $updatedCount, Added: $insertedCount, Removed: $deletedCount"];
        } catch (PDOException $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            error_log("Error updating education records: " . $e->getMessage());
            return ['success' => false, 'message' => 'Error updating education records: ' . $e->getMessage()];
        }
    }

    /**
     * Update personal information with change detection.
     */
    public function updatePersonalInfo($personalData) {
        try {
            $currentProfile = $this->getUserProfile();
            if (!$currentProfile) {
                return ['success' => false, 'message' => 'Profile not found'];
            }

            $fieldMappings = [
                'fName' => 'fName',
                'lName' => 'lName',
                'mName' => 'mName',
                'nrc' => 'NRC',
                'DOB' => 'DOB',
                'gender' => 'gender',
                'religion' => 'religion',
                'marital_status' => 'marital',
                'address' => 'address',
                'tel' => 'telNo',
                'email' => 'officialEmail',
                'height' => 'height',
                'combatSize' => 'combatSize',
                'bsize' => 'bootSize',
                'ssize' => 'sSize',
                'hdress' => 'hDress',
                'blood_group' => 'bloodGp',
                'province' => 'province',
                'district' => 'district'
            ];

            $changedFields = [];
            $updateValues = [];

            foreach ($fieldMappings as $formField => $dbField) {
                if (isset($personalData[$formField])) {
                    $newValue = trim($personalData[$formField]);
                    $currentValue = $currentProfile->$dbField ?? '';

                    if ($newValue !== $currentValue) {
                        $changedFields[] = "$dbField = ?";
                        $updateValues[] = $newValue;
                    }
                }
            }

            if (empty($changedFields)) {
                return ['success' => true, 'message' => 'No changes detected', 'updated' => 0];
            }

            $updateValues[] = $this->userId;

            $updateSQL = "UPDATE staff SET " . implode(', ', $changedFields) . ", updatedAt = NOW() WHERE svcNo = ?";
            $stmt = $this->pdo->prepare($updateSQL);
            $result = $stmt->execute($updateValues);

            if ($result) {
                $this->logActivity('profile_update', 'Personal information updated');
                return [
                    'success' => true,
                    'message' => 'Personal information updated successfully',
                    'updated' => count($changedFields)
                ];
            } else {
                return ['success' => false, 'message' => 'Failed to update personal information'];
            }

        } catch (PDOException $e) {
            error_log("Error updating personal info: " . $e->getMessage());
            return ['success' => false, 'message' => 'Database error: ' . $e->getMessage()];
        }
    }

    /**
     * Get contact information
     */
    public function getContactInfo() {
        try {
            $stmt = $this->pdo->prepare("
                SELECT * FROM staff_contact_info
                WHERE svcNo = ?
                ORDER BY is_primary DESC, contact_type, id
            ");
            $stmt->execute([$this->userId]);
            return $stmt->fetchAll(PDO::FETCH_OBJ);
        } catch (PDOException $e) {
            error_log("Error fetching contact info: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Update contact information with change detection
     */
    public function updateContactInfo($contactData) {
        try {
            $this->pdo->beginTransaction();
            $existingContacts = $this->getContactInfo();
            $existingById = [];
            foreach ($existingContacts as $contact) {
                $existingById[$contact->id] = $contact;
            }
            $updatedCount = 0;
            $insertedCount = 0;
            $deletedCount = 0;
            $submittedIds = [];
            foreach ($contactData as $contact) {
                if (empty($contact['contact_value']) || empty($contact['contact_type'])) {
                    continue;
                }
                $contactId = !empty($contact['id']) ? (int)$contact['id'] : null;
                $submittedIds[] = $contactId;
                $data = [
                    'svcNo' => $this->userId,
                    'contact_type' => $contact['contact_type'],
                    'contact_value' => trim($contact['contact_value']),
                    'contact_name' => trim($contact['contact_name'] ?? ''),
                    'relationship' => trim($contact['relationship'] ?? ''),
                    'is_primary' => !empty($contact['is_primary']) ? 1 : 0,
                    'is_verified' => !empty($contact['is_verified']) ? 1 : 0,
                    'notes' => trim($contact['notes'] ?? '')
                ];
                if ($contactId && isset($existingById[$contactId])) {
                    $existing = $existingById[$contactId];
                    $hasChanges = false;
                    foreach ($data as $key => $value) {
                        if ($key !== 'svcNo' && $existing->$key != $value) {
                            $hasChanges = true;
                            break;
                        }
                    }
                    if ($hasChanges) {
                        $updateFields = [];
                        $updateValues = [];
                        foreach ($data as $key => $value) {
                            if ($key !== 'svcNo') {
                                $updateFields[] = "$key = ?";
                                $updateValues[] = $value;
                            }
                        }
                        $updateValues[] = $contactId;
                        $updateValues[] = $this->userId;
                        $updateSQL = "UPDATE staff_contact_info SET " . implode(', ', $updateFields) . ", updatedAt = NOW() WHERE id = ? AND svcNo = ?";
                        $stmt = $this->pdo->prepare($updateSQL);
                        $stmt->execute($updateValues);
                        $updatedCount++;
                    }
                } else {
                    $insertSQL = "INSERT INTO staff_contact_info (svcNo, contact_type, contact_value, contact_name, relationship, is_primary, is_verified, notes, createdAt, updatedAt) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())";
                    $stmt = $this->pdo->prepare($insertSQL);
                    $stmt->execute(array_values($data));
                    $insertedCount++;
                }
            }
            foreach ($existingById as $id => $record) {
                if (!in_array($id, $submittedIds)) {
                    $deleteStmt = $this->pdo->prepare("DELETE FROM staff_contact_info WHERE id = ? AND svcNo = ?");
                    $deleteStmt->execute([$id, $this->userId]);
                    $deletedCount++;
                }
            }
            $this->pdo->commit();
            return [
                'success' => true,
                'updated' => $updatedCount,
                'inserted' => $insertedCount,
                'deleted' => $deletedCount,
                'message' => "Contact information updated successfully. Updated: $updatedCount, Added: $insertedCount, Removed: $deletedCount"
            ];
        } catch (PDOException $e) {
            $this->pdo->rollback();
            error_log("Error updating contact info: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Error updating contact information: ' . $e->getMessage()
            ];
        }
    }

    public function uploadProfilePhoto($file) {
        try {
            if (!isset($file['tmp_name']) || empty($file['tmp_name'])) {
                return ['success' => false, 'message' => 'No file uploaded'];
            }

            $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png'];
            if (!in_array($file['type'], $allowedTypes)) {
                return ['success' => false, 'message' => 'Only JPEG and PNG files are allowed'];
            }

            if ($file['size'] > 5 * 1024 * 1024) {
                return ['success' => false, 'message' => 'File size must be less than 5MB'];
            }

            if (empty($this->userSvcNo)) {
                $this->loadUserInfo();
            }

            if (empty($this->userSvcNo)) {
                return ['success' => false, 'message' => 'User service number not available'];
            }

            $uploadDir = dirname(__DIR__) . '/uploads/profile_photos/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }

            $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
            $filename = $this->userSvcNo . '.' . $extension;
            $filepath = $uploadDir . $filename;

            $existingFiles = glob($uploadDir . $this->userSvcNo . '.*');
            foreach ($existingFiles as $existingFile) {
                if (is_file($existingFile)) {
                    unlink($existingFile);
                }
            }

            if (move_uploaded_file($file['tmp_name'], $filepath)) {
                try {
                    $stmt = $this->pdo->prepare("UPDATE staff SET profilePhoto = ? WHERE svcNo = ?");
                    $stmt->execute([$filename, $this->userId]);
                } catch (PDOException $e) {
                    error_log("Could not update profilePhoto column, continuing with file-based lookup: " . $e->getMessage());
                }

                $this->logActivity('photo_update', 'Profile photo updated');

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
            if (!isset($file['tmp_name']) || empty($file['tmp_name'])) {
                return ['success' => false, 'message' => 'No CV file uploaded'];
            }

            $allowedTypes = ['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
            if (!in_array($file['type'], $allowedTypes)) {
                return ['success' => false, 'message' => 'Only PDF and Word documents are allowed'];
            }

            if ($file['size'] > 10 * 1024 * 1024) {
                return ['success' => false, 'message' => 'CV file size must be less than 10MB'];
            }

            $uploadDir = dirname(__DIR__) . '/uploads/cvs/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }

            $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
            $filename = 'cv_' . $this->userId . '_' . time() . '.' . $extension;
            $filepath = $uploadDir . $filename;

            if (move_uploaded_file($file['tmp_name'], $filepath)) {
                $extractedData = $this->extractCVData($filepath, $file['type']);

                $stmt = $this->pdo->prepare("
                    INSERT INTO staff_cvs (svcNo, filename, original_name, file_type, file_size, extracted_data, upload_date)
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
                $this->logActivity('cv_upload', "CV uploaded: {$file['name']}");

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
     * Extract text from PDF.
     * NOTE: shells out to the system `pdftotext` binary. $filepath is always
     * a filename this class generated itself, never a raw user-supplied
     * path, which limits (but doesn't eliminate) the risk of this
     * shell_exec call - a dedicated PDF-parsing library would be safer
     * than shelling out at all.
     */
    private function extractTextFromPDF($filepath) {
        $text = '';

        if (function_exists('shell_exec')) {
            $output = shell_exec('pdftotext ' . escapeshellarg($filepath) . ' -');
            if ($output) {
                $text = $output;
            }
        }

        return $text;
    }

    private function extractTextFromWord($filepath) {
        $text = '';

        if (pathinfo($filepath, PATHINFO_EXTENSION) === 'docx') {
            try {
                $zip = new ZipArchive();
                if ($zip->open($filepath)) {
                    $xmlString = $zip->getFromName('word/document.xml');
                    $zip->close();

                    if ($xmlString) {
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

    private function parseCV($text) {
        $data = [
            'personal' => [],
            'contact' => [],
            'education' => [],
            'experience' => [],
            'skills' => [],
            'certifications' => []
        ];

        if (preg_match_all('/\b[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Z|a-z]{2,}\b/', $text, $emails)) {
            foreach ($emails[0] as $email) {
                $data['contact']['email'] = $email;
                break;
            }
        }

        if (preg_match_all('/(?:\+\d{1,3}\s?)?\(?\d{3}\)?[-.\s]?\d{3}[-.\s]?\d{4}/', $text, $phones)) {
            foreach ($phones[0] as $phone) {
                $data['contact']['phone'] = trim($phone);
                break;
            }
        }

        $lines = explode("\n", $text);
        $firstLines = array_slice($lines, 0, 10);
        foreach ($firstLines as $line) {
            $line = trim($line);
            if (preg_match('/^[A-Z][a-z]+\s+[A-Z][a-z]+/', $line) && strlen($line) < 50) {
                $data['personal']['full_name'] = $line;
                break;
            }
        }

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
                        break;
                    }
                }
            }
        }

        return $data;
    }

    /**
     * Get user's training records (self-reported - distinct from the
     * admin-entered staff_skills table).
     */
    public function getTrainingRecords() {
        try {
            $stmt = $this->pdo->prepare("
                SELECT * FROM training_records
                WHERE svcNo = ?
                ORDER BY startDate DESC
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
            $profileStmt = $this->pdo->prepare('SELECT gender FROM staff WHERE svcNo = ? LIMIT 1');
            $profileStmt->execute([$this->userId]);
            $personnelGender = $profileStmt->fetchColumn() ?: '';
            $stmt = $this->pdo->prepare("
                SELECT * FROM staff_family_members
                WHERE svcNo = ?
                ORDER BY is_emergency_contact DESC, relationship ASC
            ");
            $stmt->execute([$this->userId]);
            $members = $stmt->fetchAll(PDO::FETCH_OBJ);

            if (!empty($members)) {
                foreach ($members as $member) {
                    $member->relationshipLabel = $this->formatSpouseRelationship($member->relationship ?? '', $personnelGender);
                }
                return $members;
            }

            // Fallback to legacy staff table NOK/alternate NOK fields if no family member
            // records exist in staff_family_members.
            $fallbackStmt = $this->pdo->prepare("SELECT nok, nokRelat, nokTel, altNok, altNokTel, altNokRelat FROM staff WHERE svcNo = ? LIMIT 1");
            $fallbackStmt->execute([$this->userId]);
            $legacyNok = $fallbackStmt->fetch(PDO::FETCH_OBJ);
            if (!$legacyNok) {
                return [];
            }

            $legacyMembers = [];
            if (!empty($legacyNok->nok)) {
                $member = new stdClass();
                $member->id = 0;
                $member->name = $legacyNok->nok;
                $member->relationship = $legacyNok->nokRelat ?? 'Next of Kin';
                $member->relationshipLabel = $this->formatSpouseRelationship($member->relationship, $personnelGender);
                $member->phone = $legacyNok->nokTel ?? null;
                $member->email = null;
                $member->address = null;
                $member->occupation = null;
                $member->is_emergency_contact = 1;
                $member->is_dependent = 0;
                $member->is_next_of_kin = 1;
                $member->nok_type = 'Primary';
                $member->notes = 'Legacy NOK record from staff table';
                $legacyMembers[] = $member;
            }
            if (!empty($legacyNok->altNok)) {
                $member = new stdClass();
                $member->id = 0;
                $member->name = $legacyNok->altNok;
                $member->relationship = $legacyNok->altNokRelat ?? 'Alternate Next of Kin';
                $member->relationshipLabel = $this->formatSpouseRelationship($member->relationship, $personnelGender);
                $member->phone = $legacyNok->altNokTel ?? null;
                $member->email = null;
                $member->address = null;
                $member->occupation = null;
                $member->is_emergency_contact = 1;
                $member->is_dependent = 0;
                $member->is_next_of_kin = 1;
                $member->nok_type = 'Secondary';
                $member->notes = 'Legacy alternate NOK record from staff table';
                $legacyMembers[] = $member;
            }

            return $legacyMembers;
        } catch (PDOException $e) {
            error_log("Error fetching family members: " . $e->getMessage());
            return [];
        }
    }

    private function formatSpouseRelationship($relationship, $personnelGender) {
        if (strcasecmp(trim((string)$relationship), 'spouse') !== 0) {
            return $relationship;
        }
        return strcasecmp((string)$personnelGender, 'Male') === 0 ? 'Wife' : 'Husband';
    }

    /**
     * Get user's addresses
     */
    public function getAddresses() {
        try {
            $stmt = $this->pdo->prepare("
                SELECT * FROM staff_addresses
                WHERE svcNo = ?
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
     * Get user's skills and competencies.
     * Real staff_skills columns are course_name/course_type/
     * certification_status/certificateNumber - aliased to the
     * skillName/skillCategory/proficiency_level/certification names
     * training.php's template actually reads. years_experience has no
     * backing column anywhere, so it's aliased to NULL rather than left
     * undefined (training.php accesses it without ?? / isset(), which
     * would otherwise throw an "Undefined property" warning).
     */
    public function getSkills() {
        try {
            $stmt = $this->pdo->prepare("
                SELECT
                    *,
                    course_type AS skillCategory,
                    course_name AS skillName,
                    certification_status AS proficiency_level,
                    certificateNumber AS certification,
                    NULL AS years_experience
                FROM staff_skills
                WHERE svcNo = ?
                ORDER BY course_type ASC, grade_obtained DESC
            ");
            $stmt->execute([$this->userId]);
            return $stmt->fetchAll(PDO::FETCH_OBJ);
        } catch (PDOException $e) {
            error_log("Error fetching skills: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get user's awards and decorations.
     */
    public function getAwards() {
        try {
            $stmt = $this->pdo->prepare("
                SELECT * FROM staff_awards
                WHERE svcNo = ?
                ORDER BY award_date DESC
            ");
            $stmt->execute([$this->userId]);
            return $stmt->fetchAll(PDO::FETCH_OBJ);
        } catch (PDOException $e) {
            error_log("Error fetching awards: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get user's deployment history (already matched the real schema).
     */
    public function getDeployments() {
        try {
            $stmt = $this->pdo->prepare("
                SELECT * FROM staff_deployments
                WHERE svcNo = ?
                ORDER BY startDate DESC
            ");
            $stmt->execute([$this->userId]);
            return $stmt->fetchAll(PDO::FETCH_OBJ);
        } catch (PDOException $e) {
            error_log("Error fetching deployments: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get user's medals.
     * Real table is `staff_awards` (joined to `honors` for the citation
     * authority text) - the same table getAwards() above uses. There is
     * no separate staff_medals/medals table anywhere in the schema;
     * narrowed by award_type = 'Medal' so this and getAwards() return
     * distinct, non-overlapping results instead of duplicating each other.
     */
    public function getMedals() {
        try {
            $stmt = $this->pdo->prepare("
                SELECT
                    sa.*,
                    sa.award_name as medal_name,
                    sa.citation as medal_description,
                    h.honorDesc,
                    h.honorAuth
                FROM staff_awards sa
                LEFT JOIN honors h ON sa.honorId = h.honorId
                WHERE sa.svcNo = ? AND sa.award_type = 'Medal'
                ORDER BY sa.award_date DESC
            ");
            $stmt->execute([$this->userId]);
            return $stmt->fetchAll(PDO::FETCH_OBJ);
        } catch (PDOException $e) {
            error_log("Error fetching medals: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get user's promotion history with rank details.
     */
    public function getPromotionHistory() {
        try {
            $stmt = $this->pdo->prepare("
                SELECT
                    sp.*,
                    r1.rankId as current_rank_abbr,
                    r1.rankIndex as current_rank_level,
                    r2.rankId as new_rank_abbr,
                    r2.rankIndex as new_rank_level
                FROM staff_promotion sp
                LEFT JOIN `rank` r1 ON sp.currentRank = r1.rankId
                LEFT JOIN `rank` r2 ON sp.newRank = r2.rankId
                WHERE sp.svcNo = ?
                ORDER BY sp.wefDate DESC
            ");
            $stmt->execute([$this->userId]);
            return $stmt->fetchAll(PDO::FETCH_OBJ);
        } catch (PDOException $e) {
            error_log("Error fetching promotion history: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get user's service history (enlistment + promotions/reversions).
     */
    public function getServiceHistory() {
        try {
            $stmt = $this->pdo->prepare("
                SELECT
                    CASE WHEN type = 'demotion' THEN 'Reversion' ELSE 'Promotion' END as record_type,
                    CONCAT(CASE WHEN type = 'demotion' THEN 'Reverted to ' ELSE 'Promoted to ' END, newRank) as description,
                    wefDate as record_date,
                    newRank as rank_name
                FROM staff_promotion
                WHERE svcNo = ?

                UNION ALL

                SELECT
                    'Enlistment' as record_type,
                    'Initial Enlistment' as description,
                    attestDate as record_date,
                    'Recruit' as rank_name
                FROM staff
                WHERE svcNo = ? AND attestDate IS NOT NULL

                ORDER BY record_date ASC
            ");
            $stmt->execute([$this->userId, $this->userId]);
            return $stmt->fetchAll(PDO::FETCH_OBJ);
        } catch (PDOException $e) {
            error_log("Error fetching service history: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get user's medical information (if authorized).
     * NOTE: medical data warrants its own access-control review beyond
     * what this class enforces - callers should confirm the requester is
     * viewing their own record (or has an explicit medical-access role)
     * before calling this with $includeDetails = true.
     */
    public function getMedicalInfo($includeDetails = false) {
        try {
            $fields = "medical_category, fitness_status, lastMedicalExam, next_medical_due";
            if ($includeDetails) {
                $fields .= ", blood_group, height, weight, bmi, allergies";
            }

            $stmt = $this->pdo->prepare("
                SELECT {$fields} FROM staff_medical_records
                WHERE svcNo = ?
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
        $limit = (int)$limit;

        try {
            $stmt = $this->pdo->prepare("
                SELECT 'Training Completed' as activity_type, course_name as details,
                       endDate as activity_date, status
                FROM training_records
                WHERE svcNo = ? AND status = 'completed' AND endDate IS NOT NULL
                ORDER BY endDate DESC LIMIT $limit
            ");
            $stmt->execute([$this->userId]);
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

            $stmt = $this->pdo->prepare("
                SELECT action, createdAt,
                       CASE
                           WHEN action = 'profile_update' THEN 'Profile Updated'
                           WHEN action = 'contact_update' THEN 'Contact Info Updated'
                           WHEN action = 'photo_update' THEN 'Photo Updated'
                           WHEN action = 'cv_upload' THEN 'CV Uploaded'
                           ELSE CONCAT(UPPER(SUBSTRING(action, 1, 1)), SUBSTRING(action, 2))
                       END as activity_type
                FROM audit_log
                WHERE svcNo = ?
                ORDER BY createdAt DESC LIMIT $limit
            ");
            $stmt->execute([$this->userId]);
            $updates = $stmt->fetchAll(PDO::FETCH_OBJ);

            foreach ($updates as $u) {
                $activities[] = [
                    'date' => $u->createdAt,
                    'type' => $u->activity_type,
                    'description' => 'Personal information modified',
                    'status' => 'Updated',
                    'icon' => 'edit',
                    'color' => 'info'
                ];
            }

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
     * Update basic profile information.
     */
    public function updateBasicInfo($data) {
        try {
            $fieldMap = [
                'email' => 'officialEmail',
                'tel' => 'telNo',
                'height' => 'height',
                'bloodGp' => 'bloodGp',
                'marital' => 'marital',
                'religion' => 'religion'
            ];
            $updateFields = [];
            $updateValues = [];

            foreach ($fieldMap as $inputKey => $dbColumn) {
                if (isset($data[$inputKey]) && $data[$inputKey] !== '') {
                    $updateFields[] = "$dbColumn = ?";
                    $updateValues[] = $data[$inputKey];
                }
            }

            if (empty($updateFields)) {
                return ['success' => false, 'message' => 'No valid fields to update'];
            }

            $updateValues[] = $this->userId;
            $sql = "UPDATE staff SET " . implode(', ', $updateFields) . ", updatedAt = NOW() WHERE svcNo = ?";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($updateValues);

            return ['success' => true, 'message' => 'Profile updated successfully'];

        } catch (PDOException $e) {
            error_log("Error updating profile: " . $e->getMessage());
            return ['success' => false, 'message' => 'Error updating profile'];
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
     * Validate Next of Kin (NOK) rules
     */
    public function validateNOKRules($familyData, $excludeMemberId = null) {
        try {
            $query = "SELECT id, is_next_of_kin, nok_type FROM staff_family_members WHERE svcNo = ? AND is_next_of_kin = 1";
            $params = [$this->userId];

            if ($excludeMemberId) {
                $query .= " AND id != ?";
                $params[] = $excludeMemberId;
            }

            $stmt = $this->pdo->prepare($query);
            $stmt->execute($params);
            $currentNOKs = $stmt->fetchAll(PDO::FETCH_OBJ);

            if (isset($familyData['is_next_of_kin']) && $familyData['is_next_of_kin']) {
                $requestedNOKType = $familyData['nok_type'] ?? '';

                if (empty($requestedNOKType) || !in_array($requestedNOKType, ['Primary', 'Secondary'])) {
                    return ['valid' => false, 'message' => 'NOK type must be either Primary or Secondary.'];
                }

                foreach ($currentNOKs as $nok) {
                    if ($nok->nok_type === $requestedNOKType) {
                        return ['valid' => false, 'message' => "A {$requestedNOKType} Next of Kin is already designated."];
                    }
                }

                if (count($currentNOKs) >= 2) {
                    return ['valid' => false, 'message' => 'Maximum of 2 Next of Kin allowed (1 Primary, 1 Secondary).'];
                }
            }

            if (isset($familyData['is_next_of_kin']) && !$familyData['is_next_of_kin'] && count($currentNOKs) <= 1) {
                return ['valid' => false, 'message' => 'At least 1 Next of Kin must be maintained.'];
            }

            return ['valid' => true];

        } catch (PDOException $e) {
            error_log("Error validating NOK rules: " . $e->getMessage());
            return ['valid' => false, 'message' => 'Error validating Next of Kin rules.'];
        }
    }

    /**
     * Get current NOK status summary
     */
    public function getNOKStatus() {
        try {
            $stmt = $this->pdo->prepare("
                SELECT name, nok_type
                FROM staff_family_members
                WHERE svcNo = ? AND is_next_of_kin = 1
                ORDER BY nok_type
            ");
            $stmt->execute([$this->userId]);
            $noks = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (empty($noks)) {
                $legacyStmt = $this->pdo->prepare("SELECT nok, nokRelat, nokTel, altNok, altNokTel, altNokRelat FROM staff WHERE svcNo = ? LIMIT 1");
                $legacyStmt->execute([$this->userId]);
                $legacyNok = $legacyStmt->fetch(PDO::FETCH_OBJ);

                if (!empty($legacyNok)) {
                    if (!empty($legacyNok->nok)) {
                        $noks[] = ['name' => $legacyNok->nok, 'nok_type' => 'Primary'];
                    }
                    if (!empty($legacyNok->altNok)) {
                        $noks[] = ['name' => $legacyNok->altNok, 'nok_type' => 'Secondary'];
                    }
                }
            }

            $primary = null;
            $secondary = null;
            $primaryCount = 0;
            $secondaryCount = 0;

            foreach ($noks as $nok) {
                if ($nok['nok_type'] === 'Primary') {
                    $primary = $nok['name'];
                    $primaryCount++;
                } elseif ($nok['nok_type'] === 'Secondary') {
                    $secondary = $nok['name'];
                    $secondaryCount++;
                }
            }

            $totalCount = $primaryCount + $secondaryCount;
            $availableSlots = 2 - $totalCount;

            return [
                'primary' => $primary,
                'secondary' => $secondary,
                'primary_count' => $primaryCount,
                'secondary_count' => $secondaryCount,
                'total_count' => $totalCount,
                'available_slots' => max(0, $availableSlots),
                'has_primary' => $primaryCount > 0,
                'has_secondary' => $secondaryCount > 0,
                'is_complete' => $primaryCount == 1 && $secondaryCount == 1
            ];
        } catch (PDOException $e) {
            error_log("Error getting NOK status: " . $e->getMessage());
            return [
                'primary' => null,
                'secondary' => null,
                'primary_count' => 0,
                'secondary_count' => 0,
                'total_count' => 0,
                'available_slots' => 2,
                'has_primary' => false,
                'has_secondary' => false,
                'is_complete' => false
            ];
        }
    }

    /**
     * Add new family member.
     * Now persists email/address/is_dependent/notes (previously silently
     * dropped despite the add-member form collecting them) and accepts
     * 'occupation' consistently with updateFamilyMember() below.
     */
    public function addFamilyMember($familyData) {
        try {
            if (isset($familyData['is_next_of_kin']) && $familyData['is_next_of_kin']) {
                $nokValidation = $this->validateNOKRules($familyData);
                if (!$nokValidation['valid']) {
                    return ['success' => false, 'message' => $nokValidation['message']];
                }
            }

            $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM staff_family_members WHERE svcNo = ? AND name = ? AND relationship = ?");
            $stmt->execute([
                $this->userId,
                $familyData['name'],
                $familyData['relationship']
            ]);
            if ($stmt->fetchColumn() > 0) {
                return ['success' => false, 'message' => 'This family member already exists.'];
            }

            $stmt = $this->pdo->prepare(
                "INSERT INTO staff_family_members
                (svcNo, name, relationship, date_of_birth, phone, email, address, occupation,
                 is_next_of_kin, nok_type, is_emergency_contact, is_dependent, notes, createdAt, updatedAt)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())"
            );
            $stmt->execute([
                $this->userId,
                $familyData['name'],
                $familyData['relationship'],
                $familyData['date_of_birth'] ?: null,
                $familyData['phone'] ?? null,
                $familyData['email'] ?? null,
                $familyData['address'] ?? null,
                $familyData['occupation'] ?? '',
                isset($familyData['is_next_of_kin']) ? (int)$familyData['is_next_of_kin'] : 0,
                (isset($familyData['is_next_of_kin']) && $familyData['is_next_of_kin']) ? ($familyData['nok_type'] ?? null) : null,
                $familyData['is_emergency_contact'] ?? 0,
                $familyData['is_dependent'] ?? 0,
                $familyData['notes'] ?? null
            ]);
            $this->logActivity('family_add', 'Added family member: ' . $familyData['name']);
            return ['success' => true, 'message' => 'Family member added successfully'];
        } catch (PDOException $e) {
            error_log("Error adding family member: " . $e->getMessage());
            return ['success' => false, 'message' => 'Error adding family member'];
        }
    }

    /**
     * Update family member.
     * Now includes 'occupation' in the UPDATE (was collected in the edit
     * form but discarded before - see the matching family.php fix).
     */
    public function updateFamilyMember($memberId, $familyData) {
        try {
            $stmt = $this->pdo->prepare("SELECT id FROM staff_family_members WHERE id = ? AND svcNo = ?");
            $stmt->execute([$memberId, $this->userId]);

            if (!$stmt->fetch()) {
                return ['success' => false, 'message' => 'Family member not found or unauthorized'];
            }

            if (isset($familyData['is_next_of_kin']) && $familyData['is_next_of_kin']) {
                $nokValidation = $this->validateNOKRules($familyData, $memberId);
                if (!$nokValidation['valid']) {
                    return ['success' => false, 'message' => $nokValidation['message']];
                }
            }

            $stmt = $this->pdo->prepare("
                UPDATE staff_family_members
                SET name = ?, relationship = ?, date_of_birth = ?, phone = ?, email = ?,
                    address = ?, occupation = ?, is_emergency_contact = ?, is_dependent = ?, notes = ?,
                    is_next_of_kin = ?, nok_type = ?, updatedAt = NOW()
                WHERE id = ? AND svcNo = ?
            ");

            $stmt->execute([
                $familyData['name'],
                $familyData['relationship'],
                $familyData['date_of_birth'] ?: null,
                $familyData['phone'] ?? null,
                $familyData['email'] ?? null,
                $familyData['address'] ?? null,
                $familyData['occupation'] ?? '',
                $familyData['is_emergency_contact'] ?? 0,
                $familyData['is_dependent'] ?? 0,
                $familyData['notes'] ?? null,
                isset($familyData['is_next_of_kin']) ? (int)$familyData['is_next_of_kin'] : 0,
                (isset($familyData['is_next_of_kin']) && $familyData['is_next_of_kin']) ? ($familyData['nok_type'] ?? null) : null,
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
            $stmt = $this->pdo->prepare("SELECT name FROM staff_family_members WHERE id = ? AND svcNo = ?");
            $stmt->execute([$memberId, $this->userId]);
            $member = $stmt->fetch(PDO::FETCH_OBJ);

            if (!$member) {
                return ['success' => false, 'message' => 'Family member not found or unauthorized'];
            }

            $stmt = $this->pdo->prepare("DELETE FROM staff_family_members WHERE id = ? AND svcNo = ?");
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
     * Log user activity. Writes to `audit_log`, a table dedicated to this
     * module - deliberately NOT the existing `activity_log` table, whose
     * user_id column is a strict int and can't safely hold a svcNo string.
     */
    private function logActivity($action, $description) {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO audit_log (svcNo, action, table_name, record_id, new_values, createdAt)
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
     * Get profile completion percentage. This is the single source of
     * truth for "how complete is this profile" - pages should call this
     * rather than computing their own ad-hoc completeness estimate, so
     * the number shown is always consistent and reflects real data.
     */
    public function getProfileCompleteness() {
        $profile = $this->getUserProfile();
        if (!$profile) return 0;

        $requiredFields = [
            'fName', 'lName', 'DOB', 'gender', 'officialEmail', 'telNo',
            'rankId', 'unitId', 'NRC', 'bloodGp', 'marital', 'address',
            'province', 'district', 'combatSize', 'bootSize', 'sSize', 'hDress'
        ];

        $completedFields = 0;
        foreach ($requiredFields as $field) {
            if (!empty($profile->$field)) {
                $completedFields++;
            }
        }

        $bonusPoints = 0;
        if ($this->getEducationRecords()) $bonusPoints += 8;
        if ($this->getTrainingRecords()) $bonusPoints += 4;
        if ($this->getFamilyMembers()) $bonusPoints += 4;
        if ($this->getContactInfo()) $bonusPoints += 4;

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
                WHERE svcNo = ?
                ORDER BY upload_date DESC
            ");
            $stmt->execute([$this->userId]);
            return $stmt->fetchAll(PDO::FETCH_OBJ);
        } catch (PDOException $e) {
            error_log("Error fetching user CVs: " . $e->getMessage());
            return [];
        }
    }

    public function getCVData($cvId) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT extracted_data, filename, original_name
                FROM staff_cvs
                WHERE id = ? AND svcNo = ?
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

    public function applyCVData($cvId, $verifiedData) {
        try {
            $this->pdo->beginTransaction();

            if (isset($verifiedData['contact']) && !empty($verifiedData['contact'])) {
                foreach ($verifiedData['contact'] as $contact) {
                    if (!empty($contact['value'])) {
                        $stmt = $this->pdo->prepare("
                            INSERT INTO staff_contact_info (svcNo, contact_type, contact_value, is_primary)
                            VALUES (?, ?, ?, 0)
                        ");
                        $stmt->execute([$this->userId, $contact['type'], $contact['value']]);
                    }
                }
            }

            if (isset($verifiedData['education']) && !empty($verifiedData['education'])) {
                foreach ($verifiedData['education'] as $education) {
                    if (!empty($education['qualification'])) {
                        $stmt = $this->pdo->prepare("
                            INSERT INTO staff_course (svcNo, instId, cseId, qualification, cseStart, cseEnd, grade, result, isHighest, authID)
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                        ");
                        $stmt->execute([
                            $this->userId,
                            $education['institution'] ?? 'Not specified',
                            $education['course_id'] ?? '',
                            $education['qualification'],
                            null,
                            !empty($education['year']) ? $education['year'] . '-12-31' : null,
                            $education['grade'] ?? '',
                            $education['result'] ?? '',
                            0,
                            'CV Import'
                        ]);
                    }
                }
            }

            if (isset($verifiedData['skills']) && !empty($verifiedData['skills'])) {
                foreach ($verifiedData['skills'] as $skill) {
                    if (!empty($skill['skill'])) {
                        $stmt = $this->pdo->prepare("
                            INSERT INTO staff_skills (svcNo, course_name, course_type, grade_obtained)
                            VALUES (?, ?, 'CV Import', ?)
                        ");
                        $stmt->execute([$this->userId, $skill['skill'], $skill['level'] ?? 'Intermediate']);
                    }
                }
            }

            if (isset($verifiedData['certifications']) && !empty($verifiedData['certifications'])) {
                foreach ($verifiedData['certifications'] as $cert) {
                    if (!empty($cert['certification'])) {
                        $stmt = $this->pdo->prepare("
                            INSERT INTO staff_certifications (svcNo, certification_name, issuer, issue_date, expiry_date)
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

            $stmt = $this->pdo->prepare("UPDATE staff_cvs SET is_verified = 1, applied_date = NOW() WHERE id = ? AND svcNo = ?");
            $stmt->execute([$cvId, $this->userId]);

            $this->pdo->commit();

            return ['success' => true, 'message' => 'CV data applied to profile successfully'];

        } catch (PDOException $e) {
            $this->pdo->rollBack();
            error_log("Error applying CV data: " . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to apply CV data: ' . $e->getMessage()];
        }
    }

    public function getProfilePhotoURL() {
        try {
            if (empty($this->userSvcNo)) {
                $this->loadUserInfo();
            }

            if ($this->userSvcNo) {
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

            try {
                $stmt = $this->pdo->prepare("SELECT profilePhoto FROM staff WHERE svcNo = ?");
                $stmt->execute([$this->userId]);
                $result = $stmt->fetch(PDO::FETCH_OBJ);

                if ($result && $result->profilePhoto) {
                    $photoPath = '/Armis2/uploads/profile_photos/' . $result->profilePhoto;
                    if (file_exists(dirname(__DIR__) . $photoPath)) {
                        return $photoPath;
                    }
                }
            } catch (PDOException $e) {
                error_log("Profile photo lookup failed: " . $e->getMessage());
            }

            if ($this->userSvcNo) {
                return '/Armis2/shared/default_avatar.php?name=' . urlencode($this->userSvcNo);
            }

            return '/Armis2/shared/default_avatar.php';

        } catch (PDOException $e) {
            error_log("Error getting profile photo: " . $e->getMessage());
            return '/Armis2/shared/default_avatar.php';
        }
    }

    public function deleteCVRecord($cvId) {
        try {
            $this->pdo->beginTransaction();

            $stmt = $this->pdo->prepare("
                SELECT filename FROM staff_cvs
                WHERE id = ? AND svcNo = ?
            ");
            $stmt->execute([$cvId, $this->userId]);
            $cv = $stmt->fetch(PDO::FETCH_OBJ);

            if (!$cv) {
                return ['success' => false, 'message' => 'CV not found or access denied'];
            }

            $stmt = $this->pdo->prepare("DELETE FROM staff_cvs WHERE id = ? AND svcNo = ?");
            $stmt->execute([$cvId, $this->userId]);

            $uploadDir = dirname(__DIR__) . '/uploads/cvs/';
            $filepath = $uploadDir . $cv->filename;
            if (file_exists($filepath)) {
                unlink($filepath);
            }

            $this->pdo->commit();

            $this->logActivity('cv_delete', "CV deleted: {$cv->filename}");

            return ['success' => true, 'message' => 'CV deleted successfully'];

        } catch (Exception $e) {
            $this->pdo->rollback();
            error_log("Error deleting CV: " . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to delete CV: ' . $e->getMessage()];
        }
    }

    public function reExtractCVData($cvId) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT filename, file_type FROM staff_cvs
                WHERE id = ? AND svcNo = ?
            ");
            $stmt->execute([$cvId, $this->userId]);
            $cv = $stmt->fetch(PDO::FETCH_OBJ);

            if (!$cv) {
                return ['success' => false, 'message' => 'CV not found or access denied'];
            }

            $uploadDir = dirname(__DIR__) . '/uploads/cvs/';
            $filepath = $uploadDir . $cv->filename;
            if (!file_exists($filepath)) {
                return ['success' => false, 'message' => 'CV file no longer exists'];
            }

            $extractedData = $this->extractCVData($filepath, $cv->file_type);

            $stmt = $this->pdo->prepare("
                UPDATE staff_cvs
                SET extracted_data = ?, is_verified = 0
                WHERE id = ? AND svcNo = ?
            ");
            $stmt->execute([json_encode($extractedData), $cvId, $this->userId]);

            $this->logActivity('cv_re_extract', "CV data re-extracted: {$cv->filename}");

            return ['success' => true, 'message' => 'CV data re-extracted successfully', 'data' => $extractedData];

        } catch (Exception $e) {
            error_log("Error re-extracting CV data: " . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to re-extract CV data: ' . $e->getMessage()];
        }
    }

    public function markCVAsVerified($cvId) {
        try {
            $stmt = $this->pdo->prepare("
                UPDATE staff_cvs
                SET is_verified = 1
                WHERE id = ? AND svcNo = ?
            ");
            $stmt->execute([$cvId, $this->userId]);

            if ($stmt->rowCount() > 0) {
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

    public function getLanguageRecords() {
        try {
            $stmt = $this->pdo->prepare("
                SELECT * FROM staff_languages
                WHERE svcNo = ?
                ORDER BY language_name ASC
            ");
            $stmt->execute([$this->userId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error fetching language records: " . $e->getMessage());
            return [];
        }
    }

    public function updateLanguageRecords($languageData) {
        try {
            $this->pdo->beginTransaction();
            $existingLanguages = $this->getLanguageRecords();
            $existingMap = [];
            foreach ($existingLanguages as $lang) {
                $existingMap[$lang['id']] = $lang;
            }
            $processedIds = [];
            $changesMade = false;
            $now = date('Y-m-d H:i:s');
            if (!empty($languageData)) {
                foreach ($languageData as $index => $langInfo) {
                    if (empty($langInfo['language_name'])) continue;
                    $langId = $langInfo['id'] ?? null;
                    $languageRecord = [
                        'language_name' => trim($langInfo['language_name']),
                        'proficiency_level' => $langInfo['proficiency_level'] ?? '',
                        'can_read' => isset($langInfo['can_read']) ? 1 : 0,
                        'can_write' => isset($langInfo['can_write']) ? 1 : 0,
                        'can_speak' => isset($langInfo['can_speak']) ? 1 : 0,
                        'can_understand' => isset($langInfo['can_understand']) ? 1 : 0
                    ];
                    if ($langId && isset($existingMap[$langId])) {
                        $existing = $existingMap[$langId];
                        $hasChanges = false;
                        foreach ($languageRecord as $field => $value) {
                            if ($existing[$field] != $value) {
                                $hasChanges = true;
                                break;
                            }
                        }
                        if ($hasChanges) {
                            $stmt = $this->pdo->prepare(
                                "UPDATE staff_languages SET language_name = ?, proficiency_level = ?, can_read = ?, can_write = ?, can_speak = ?, can_understand = ?, updatedAt = ? WHERE id = ? AND svcNo = ?"
                            );
                            $stmt->execute([
                                $languageRecord['language_name'],
                                $languageRecord['proficiency_level'],
                                $languageRecord['can_read'],
                                $languageRecord['can_write'],
                                $languageRecord['can_speak'],
                                $languageRecord['can_understand'],
                                $now,
                                $langId,
                                $this->userId
                            ]);
                            $changesMade = true;
                        }
                        $processedIds[] = $langId;
                    } else {
                        $stmt = $this->pdo->prepare(
                            "INSERT INTO staff_languages (svcNo, language_name, proficiency_level, can_read, can_write, can_speak, can_understand, createdAt, updatedAt) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
                        );
                        $stmt->execute([
                            $this->userId,
                            $languageRecord['language_name'],
                            $languageRecord['proficiency_level'],
                            $languageRecord['can_read'],
                            $languageRecord['can_write'],
                            $languageRecord['can_speak'],
                            $languageRecord['can_understand'],
                            $now,
                            $now
                        ]);
                        $changesMade = true;
                    }
                }
            }
            foreach ($existingMap as $id => $existing) {
                if (!in_array($id, $processedIds)) {
                    $stmt = $this->pdo->prepare("DELETE FROM staff_languages WHERE id = ? AND svcNo = ?");
                    $stmt->execute([$id, $this->userId]);
                    $changesMade = true;
                }
            }
            $this->pdo->commit();
            if ($changesMade) {
                $this->logActivity('language_update', "Language information updated");
            }
            return ['success' => true, 'message' => 'Language information updated successfully'];
        } catch (Exception $e) {
            $this->pdo->rollBack();
            error_log("Error updating language records: " . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to update language information: ' . $e->getMessage()];
        }
    }
} // End of class