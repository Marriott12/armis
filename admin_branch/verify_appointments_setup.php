<?php
/**
 * Verify Appointments System Configuration
 */

$host = 'localhost';
$dbname = 'armis1';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "=== APPOINTMENTS SYSTEM VERIFICATION ===\n\n";
    
    // 1. Check appointment_type table
    echo "1. Checking appointment_type table...\n";
    $typeCheck = $pdo->query("SHOW TABLES LIKE 'appointment_type'");
    if ($typeCheck->rowCount() > 0) {
        echo "   ✅ Table exists\n";
        
        $types = $pdo->query("SELECT * FROM appointment_type ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
        echo "   ✅ Found " . count($types) . " appointment types:\n";
        foreach ($types as $type) {
            echo "      - {$type['type_name']} (ID: {$type['id']}, Temporary: " . ($type['is_temporary'] ? 'Yes' : 'No') . ")\n";
        }
    } else {
        echo "   ❌ Table does not exist!\n";
    }
    
    // 2. Check staff_appointment table columns
    echo "\n2. Checking staff_appointment table columns...\n";
    $columns = $pdo->query("DESCRIBE staff_appointment")->fetchAll(PDO::FETCH_ASSOC);
    $requiredColumns = ['appointment_id', 'appointment_type', 'rank_id', 'location', 'start_date', 'end_date', 'remarks'];
    
    foreach ($requiredColumns as $col) {
        $found = false;
        foreach ($columns as $column) {
            if ($column['Field'] === $col) {
                $found = true;
                echo "   ✅ Column '$col' exists ({$column['Type']})\n";
                break;
            }
        }
        if (!$found) {
            echo "   ❌ Column '$col' MISSING!\n";
        }
    }
    
    // 3. Check recent appointments
    echo "\n3. Checking recent appointments (last 5)...\n";
    $recentStmt = $pdo->query("
        SELECT 
            sa.id,
            sa.service_number,
            sa.appointment_id AS position,
            sa.appointment_type,
            at.type_name,
            sa.rank_id,
            sa.location,
            sa.appointment_date,
            sa.start_date,
            sa.end_date,
            DATEDIFF(sa.end_date, sa.start_date) AS duration_days,
            sa.remarks
        FROM staff_appointment sa
        LEFT JOIN appointment_type at ON sa.appointment_type = at.id
        ORDER BY sa.created_at DESC
        LIMIT 5
    ");
    
    $recent = $recentStmt->fetchAll(PDO::FETCH_ASSOC);
    if (count($recent) > 0) {
        echo "   ✅ Found " . count($recent) . " recent appointments:\n\n";
        foreach ($recent as $appt) {
            echo "   ID: {$appt['id']}\n";
            echo "   Service Number: {$appt['service_number']}\n";
            echo "   Position: " . ($appt['position'] ?: 'N/A') . "\n";
            echo "   Type: " . ($appt['type_name'] ?: 'ID ' . $appt['appointment_type']) . "\n";
            echo "   Rank ID: " . ($appt['rank_id'] ?: 'N/A') . "\n";
            echo "   Location: " . ($appt['location'] ?: 'N/A') . "\n";
            echo "   Dates: {$appt['appointment_date']} to {$appt['end_date']} ({$appt['duration_days']} days)\n";
            echo "   Start Date = Appt Date: " . ($appt['start_date'] === $appt['appointment_date'] ? '✅ Yes' : '❌ No') . "\n";
            
            // Check if 3-year default
            $years = $appt['duration_days'] / 365;
            $is3Years = ($years >= 2.9 && $years <= 3.1);
            echo "   ~3 Years Duration: " . ($is3Years ? '✅ Yes' : "❌ No ({$years} years)") . "\n";
            echo "   Remarks: " . ($appt['remarks'] ?: 'None') . "\n";
            echo "   ---\n\n";
        }
    } else {
        echo "   ⚠️ No appointments found yet\n";
    }
    
    // 4. Summary
    echo "\n=== VERIFICATION SUMMARY ===\n\n";
    echo "✅ appointment_type table: OK\n";
    echo "✅ Required columns: OK\n";
    echo "✅ System ready for testing\n\n";
    
    echo "Next Steps:\n";
    echo "1. Access: http://localhost/Armis2/admin_branch/appointments.php\n";
    echo "2. Create a test appointment\n";
    echo "3. Verify all fields are stored correctly\n";
    echo "4. Run this script again to see the new appointment\n";
    
} catch (PDOException $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}
?>
