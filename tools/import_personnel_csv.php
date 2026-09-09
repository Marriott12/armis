<?php
/**
 * Import personnel records from CSV into staff table
 * Maps units and ranks from existing database tables
 */

require_once __DIR__ . '/../shared/database_connection.php';

set_time_limit(300); // 5 minutes for large imports

try {
    $pdo = getDbConnection();
    
    // Read CSV file
    $csvFile = 'c:\\Users\\user\\Downloads\\Zambia_Army_Personnel_Records 10 Feb 2026.csv';
    
    if (!file_exists($csvFile)) {
        die("❌ CSV file not found: $csvFile\n");
    }
    
    echo "=== Personnel Data Import ===\n\n";
    echo "Reading CSV file...\n";
    
    $file = fopen($csvFile, 'r');
    $headers = fgetcsv($file); // Read header row
    
    // Remove BOM from first header if present
    if (isset($headers[0])) {
        $headers[0] = preg_replace('/^\xEF\xBB\xBF/', '', $headers[0]);
    }
    
    echo "CSV Headers found:\n";
    print_r($headers);
    echo "\n";
    
    // Get all existing units
    echo "Loading units from database...\n";
    $unitStmt = $pdo->query("SELECT unitId FROM unit");
    $existingUnits = $unitStmt->fetchAll(PDO::FETCH_COLUMN);
    echo "Found " . count($existingUnits) . " units\n";
    
    // Get all existing ranks
    echo "Loading ranks from database...\n";
    $rankStmt = $pdo->query("SELECT rankId FROM `rank`");
    $existingRanks = $rankStmt->fetchAll(PDO::FETCH_COLUMN);
    echo "Found " . count($existingRanks) . " ranks\n\n";
    
    // Create rank abbreviation mapping
    $rankMapping = [
        'Maj Gen' => 'Maj Gen',
        'Brig Gen' => 'Brig Gen',
        'Col' => 'Col',
        'Lt Col' => 'Lt Col',
        'Maj' => 'Maj',
        'Capt' => 'Capt',
        'Lt' => 'Lt',
        '2Lt' => '2Lt',
        'WO1' => 'WO1',
        'WO2' => 'WO2',
        'SSgt' => 'SSgt',
        'Sgt' => 'Sgt',
        'Cpl' => 'Cpl',
        'LCpl' => 'LCpl',
        'Pte' => 'Pte',
        'Rct' => 'Rct'
    ];
    
    // Prepare INSERT statement
    $insertSQL = "INSERT INTO staff (
        svcNo, prefix, rankId, initials, fName, mName, lName, gender, NRC, 
        attestDate, DOB, unitId, apptId, corps, bloodGp, telNo, emailPvt, 
        marital, province, district, intake, combatSize, bootSize, 
        svcStatus, dateCreated
    ) VALUES (
        :svcNo, :prefix, :rankId, :initials, :fName, :mName, :lName, :gender, :NRC,
        :attestDate, :DOB, :unitId, :apptId, :corps, :bloodGp, :telNo, :emailPvt,
        :marital, :province, :district, :intake, :combatSize, :bootSize,
        'Active', NOW()
    ) ON DUPLICATE KEY UPDATE
        prefix = VALUES(prefix),
        rankId = VALUES(rankId),
        initials = VALUES(initials),
        fName = VALUES(fName),
        mName = VALUES(mName),
        lName = VALUES(lName),
        gender = VALUES(gender),
        NRC = VALUES(NRC),
        attestDate = VALUES(attestDate),
        DOB = VALUES(DOB),
        unitId = VALUES(unitId),
        apptId = VALUES(apptId),
        corps = VALUES(corps),
        bloodGp = VALUES(bloodGp),
        telNo = VALUES(telNo),
        emailPvt = VALUES(emailPvt),
        marital = VALUES(marital),
        province = VALUES(province),
        district = VALUES(district),
        intake = VALUES(intake),
        combatSize = VALUES(combatSize),
        bootSize = VALUES(bootSize),
        updatedAt = NOW()";
    
    $stmt = $pdo->prepare($insertSQL);
    
    $imported = 0;
    $updated = 0;
    $skipped = 0;
    $errors = [];
    $newUnits = [];
    $debugFirst = 5; // Debug first 5 rows
    
    // Function to convert Excel date format to MySQL date
    function convertDate($dateStr) {
        if (empty($dateStr)) return null;
        
        // Handle Excel serial number (e.g., 45673)
        if (is_numeric($dateStr) && strlen($dateStr) <= 5) {
            $excelEpoch = new DateTime('1899-12-30'); // Excel epoch
            $excelEpoch->modify("+{$dateStr} days");
            return $excelEpoch->format('Y-m-d');
        }
        
        // Try parsing DD/MM/YYYY format
        if (preg_match('/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/', $dateStr, $matches)) {
            return sprintf('%04d-%02d-%02d', $matches[3], $matches[2], $matches[1]);
        }
        
        // Try other common formats
        $timestamp = strtotime($dateStr);
        if ($timestamp !== false) {
            return date('Y-m-d', $timestamp);
        }
        
        return null;
    }
    
    // Function to find or create unitId
    function getOrCreateUnit($unitName, &$pdo, &$existingUnits, &$newUnits) {
        if (empty($unitName)) return null;
        
        // Clean unit name
        $unitName = trim($unitName);
        
        // Check if unit exists
        if (in_array($unitName, $existingUnits)) {
            return $unitName;
        }
        
        // Check if we already created it in this import
        if (isset($newUnits[$unitName])) {
            return $unitName;
        }
        
        // Create new unit
        try {
            $stmt = $pdo->prepare("INSERT INTO unit (unitId) VALUES (?)");
            $stmt->execute([$unitName]);
            $existingUnits[] = $unitName;
            $newUnits[$unitName] = true;
            return $unitName;
        } catch (Exception $e) {
            return null;
        }
    }
    
    // Process each row
    $rowNumber = 1;
    while (($row = fgetcsv($file)) !== false) {
        $rowNumber++;
        
        if (count($row) < count($headers)) {
            $skipped++;
            continue;
        }
        
        // Map CSV columns to array
        $data = array_combine($headers, $row);
        
        // Debug first few rows
        if ($debugFirst > 0) {
            echo "\n--- Row $rowNumber Debug ---\n";
            echo "SvcNo: '{$data['SvcNo']}'\n";
            echo "Rank: '{$data['Rank']}'\n";
            echo "Name: '{$data['First Name']} {$data['Surname']}'\n";
            $debugFirst--;
        }
        
        // Skip if no service number
        if (empty($data['SvcNo'])) {
            if ($rowNumber <= 5) echo "⚠️  Skipping row $rowNumber: No service number\n";
            $skipped++;
            continue;
        }
        
        try {
            // Get or create unitId
            $unitId = getOrCreateUnit($data['Unit'], $pdo, $existingUnits, $newUnits);
            
            // Map rank
            $rankId = isset($rankMapping[$data['Rank']]) ? $rankMapping[$data['Rank']] : null;
            if (!$rankId && !empty($data['Rank'])) {
                $rankId = $data['Rank']; // Use as-is if not in mapping
            }
            
            // Convert dates
            $attestDate = convertDate($data['Attestation']);
            $dob = convertDate($data['DOB']);
            
            // Clean phone number (remove scientific notation)
            $telNo = $data['Tel No'];
            if (strpos($telNo, 'E+') !== false) {
                $telNo = sprintf('%.0f', floatval($telNo));
            }
            
            // Prepare data for insertion
            $insertData = [
                ':svcNo' => trim($data['SvcNo']),
                ':prefix' => !empty($data['PREFIX']) ? trim($data['PREFIX']) : null,
                ':rankId' => $rankId,
                ':initials' => !empty($data['Initials']) ? trim($data['Initials']) : null,
                ':fName' => !empty($data['First Name']) ? trim($data['First Name']) : null,
                ':mName' => !empty($data['Other Names']) ? trim($data['Other Names']) : null,
                ':lName' => !empty($data['Surname']) ? trim($data['Surname']) : null,
                ':gender' => $data['Gender'] === 'Female' ? 'Female' : 'Male',
                ':NRC' => !empty($data['NRC']) ? trim($data['NRC']) : null,
                ':attestDate' => $attestDate,
                ':DOB' => $dob,
                ':unitId' => $unitId,
                ':apptId' => !empty($data['Appt']) ? trim($data['Appt']) : null,
                ':corps' => !empty($data['Corps']) ? trim($data['Corps']) : null,
                ':bloodGp' => !empty($data['Blood GP']) ? trim($data['Blood GP']) : null,
                ':telNo' => !empty($telNo) ? trim($telNo) : null,
                ':emailPvt' => !empty($data['Email']) ? trim($data['Email']) : null,
                ':marital' => !empty($data['Marital']) ? trim($data['Marital']) : null,
                ':province' => !empty($data['Province']) ? trim($data['Province']) : null,
                ':district' => !empty($data['District']) ? trim($data['District']) : null,
                ':intake' => !empty($data['Intake']) ? trim($data['Intake']) : null,
                ':combatSize' => !empty($data['Combat']) ? trim($data['Combat']) : null,
                ':bootSize' => !empty($data['Boot']) ? trim($data['Boot']) : null
            ];
            
            $stmt->execute($insertData);
            
            if ($stmt->rowCount() > 0) {
                $imported++;
                if ($imported % 100 == 0) {
                    echo "Processed $imported records...\n";
                }
            } else {
                $updated++;
            }
            
        } catch (Exception $e) {
            $errorMsg = "Row $rowNumber (SvcNo: {$data['SvcNo']}): " . $e->getMessage();
            $errors[] = $errorMsg;
            if (count($errors) <= 5) {
                echo "❌ $errorMsg\n";
            }
            $skipped++;
        }
    }
    
    fclose($file);
    
    echo "\n=== Import Summary ===\n";
    echo "✅ Imported: $imported new records\n";
    echo "✅ Updated: $updated existing records\n";
    echo "⏭️  Skipped: $skipped records\n";
    echo "🆕 Created: " . count($newUnits) . " new units\n";
    
    if (!empty($newUnits)) {
        echo "\nNew units created:\n";
        foreach (array_keys($newUnits) as $unit) {
            echo "  - $unit\n";
        }
    }
    
    if (!empty($errors)) {
        echo "\n⚠️  Errors (" . count($errors) . "):\n";
        foreach (array_slice($errors, 0, 10) as $error) {
            echo "  - $error\n";
        }
        if (count($errors) > 10) {
            echo "  ... and " . (count($errors) - 10) . " more errors\n";
        }
    }
    
    echo "\n✅ Import complete!\n";
    
} catch (Exception $e) {
    echo "❌ FATAL ERROR: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
}
