<?php
// Define module constants
define('ARMIS_ADMIN_BRANCH', true);
define('ARMIS_DEVELOPMENT', true);

// Enable detailed error reporting and logging
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', dirname(__DIR__) . '/logs/appointments_errors.log');
error_reporting(E_ALL);

// Log script start
error_log("APPOINTMENTS: Script started at " . date('Y-m-d H:i:s'));

// Include admin branch authentication and database
try {
    error_log("APPOINTMENTS: Including auth.php");
    require_once __DIR__ . '/includes/auth.php';
    error_log("APPOINTMENTS: Auth included successfully");
    
    error_log("APPOINTMENTS: Including database connection");
    require_once dirname(__DIR__) . '/shared/database_connection.php';
    error_log("APPOINTMENTS: Database connection included successfully");
} catch (Exception $e) {
    error_log("APPOINTMENTS CRITICAL ERROR: Unable to load required files - " . $e->getMessage());
    error_log("APPOINTMENTS CRITICAL ERROR: File: " . $e->getFile() . " Line: " . $e->getLine());
    die("Critical Error: Unable to load required files - " . htmlspecialchars($e->getMessage()));
}

// Require authentication
try {
    error_log("APPOINTMENTS: Checking authentication");
    requireAuth();
    error_log("APPOINTMENTS: Authentication successful");
} catch (Exception $e) {
    error_log("APPOINTMENTS AUTH ERROR: " . $e->getMessage());
    error_log("APPOINTMENTS AUTH ERROR: File: " . $e->getFile() . " Line: " . $e->getLine());
    die("Authentication Error: " . htmlspecialchars($e->getMessage()));
}

// Generate CSRF token
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$pageTitle = "Appointments - Admin Branch";
$moduleName = "Admin Branch";
$moduleIcon = "users-cog";
$currentPage = "appointments";

// Sidebar navigation
$sidebarLinks = [
    ['title' => 'Dashboard', 'url' => '/Armis2/admin_branch/index.php', 'icon' => 'tachometer-alt', 'page' => 'dashboard'],
    ['title' => 'Staff Management', 'url' => '/Armis2/admin_branch/edit_staff.php', 'icon' => 'users', 'page' => 'staff'],
    ['title' => 'Create Staff', 'url' => '/Armis2/admin_branch/create_staff.php', 'icon' => 'user-plus', 'page' => 'create'],
    ['title' => 'Promotions', 'url' => '/Armis2/admin_branch/promote_staff.php', 'icon' => 'arrow-up', 'page' => 'promotions'],
    ['title' => 'Appointments', 'url' => '/Armis2/admin_branch/appointments.php', 'icon' => 'user-tie', 'page' => 'appointments'],
    ['title' => 'Medals', 'url' => '/Armis2/admin_branch/assign_medal.php', 'icon' => 'medal', 'page' => 'medals'],
    [
        'title' => 'Seniority Rolls',
        'icon' => 'users',
        'page' => 'seniority',
        'children' => [
            ['title' => 'Officer Seniority', 'url' => '/Armis2/admin_branch/reports_seniority.php?report_type=officer'],
            ['title' => 'NCO Seniority', 'url' => '/Armis2/admin_branch/reports_nco_seniority.php?report_type=nco'],
            ['title' => 'CE Seniority', 'url' => '/Armis2/admin_branch/reports_ce_seniority.php?report_type=ce'],
        ]
    ],
    [
        'title' => 'Norminal Rolls',
        'icon' => 'bars',
        'page' => 'norminal',
        'children' => [
            ['title' => 'Officer Norminal Roll', 'url' => '/Armis2/admin_branch/reports_officer_norminal.php?report_type=officer'],
            ['title' => 'NCO Norminal Roll', 'url' => '/Armis2/admin_branch/reports_nco_norminal.php?report_type=nco'],
            ['title' => 'CE Norminal Roll', 'url' => '/Armis2/admin_branch/reports_ce_norminal.php?report_type=ce'],
        ]
    ],
    [
        'title' => 'Reports',
        'icon' => 'chart-bar',
        'page' => 'reports',
        'children' => [
            ['title' => 'Unit List', 'url' => '/Armis2/admin_branch/reports_units.php'],
            ['title' => 'Appointments', 'url' => '/Armis2/admin_branch/reports_appointment.php'],
            ['title' => 'Contracts', 'url' => '/Armis2/admin_branch/reports_contract.php'],
            ['title' => 'Courses', 'url' => '/Armis2/admin_branch/reports_courses.php'],
            ['title' => 'Deceased', 'url' => '/Armis2/admin_branch/reports_deceased.php'],
            ['title' => 'Gender', 'url' => '/Armis2/admin_branch/reports_gender.php'],
            ['title' => 'Marital', 'url' => '/Armis2/admin_branch/reports_marital.php'],
            ['title' => 'Rank', 'url' => '/Armis2/admin_branch/reports_rank.php'],
            ['title' => 'Retired', 'url' => '/Armis2/admin_branch/reports_retired.php'],
            ['title' => 'Trade', 'url' => '/Armis2/admin_branch/reports_trade.php'],
            ['title' => 'Corps', 'url' => '/Armis2/admin_branch/reports_corps.php'],
            ['title' => 'Units', 'url' => '/Armis2/admin_branch/reports_units.php'],
        ]
    ],
];

// Use PDO for all DB operations with caching
try {
    $pdo = getDbConnection();
    if (!$pdo) {
        throw new Exception("Failed to establish database connection");
    }
} catch (Exception $e) {
    die("Database Connection Error: " . htmlspecialchars($e->getMessage()));
}

$ranks = [];
$units = [];
$positions = [];
$appointmentTypes = [];

// Fetch appointment types from database
try {
    $appointmentTypesStmt = $pdo->query("SELECT id, type_name, description, is_temporary, default_duration_months FROM appointment_type ORDER BY id");
    $appointmentTypes = $appointmentTypesStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error fetching appointment types: " . $e->getMessage());
    $appointmentTypes = []; // Fallback to empty array
}

// Define standard military appointment positions
$standardPositions = [
    'Commanding Officer',
    'Second In Command', 
    'Operations Officer',
    'Training Officer',
    'Adjutant',
    'Battalion Commander',
    'Battalion Second In Command',
    'Detachment Commander',
    'Detachment Second In Command',
    'Intelligence Officer',
    'Logistics Officer',
    'Regimental Medical Officer',
    'Signals Officer',
    'Engineer Officer',
    'Administrative Officer',
    'Finance Officer',
    'Personnel Officer',
    'Survey Officer',
    'Transport Officer',
    'Quartermaster',
    'Company Commander',
    'Platoon Commander',
    'Section Commander',
    'Regimental Sergeant Major',
    'Company Sergeant Major',
    'Platoon Sergeant',
    'Ward Master',
    'Drill Instructor',
    'Weapons Instructor',
    'Physical Training Instructor',
    'Driver',
    'Radio Operator',
    'Layer',
    'Other'
];

// Initialize session cache if not exists
if (!isset($_SESSION['dropdown_cache'])) {
    $_SESSION['dropdown_cache'] = [];
}

try {
    error_log("APPOINTMENTS: Starting dropdown data fetch");
    
    // Check if ranks are cached and still valid (5 minutes)
    $cache_key = 'ranks_data';
    $cache_timeout = 300; // 5 minutes
    
    if (isset($_SESSION['dropdown_cache'][$cache_key]) && 
        time() - $_SESSION['dropdown_cache'][$cache_key]['timestamp'] < $cache_timeout) {
        $ranks = $_SESSION['dropdown_cache'][$cache_key]['data'];
        error_log("APPOINTMENTS: Using cached ranks data");
    } else {
        error_log("APPOINTMENTS: Fetching ranks from database");
        // Get all ranks ordered by rank level (seniority)
        $ranksStmt = $pdo->query("SELECT id as rankID, name as rankName, level as rankIndex FROM ranks ORDER BY rankIndex ASC");
        $ranks = $ranksStmt->fetchAll(PDO::FETCH_OBJ);
        error_log("APPOINTMENTS: Fetched " . count($ranks) . " ranks successfully");
        
        // Cache the results
        $_SESSION['dropdown_cache'][$cache_key] = [
            'data' => $ranks,
            'timestamp' => time()
        ];
    }
    
    // Check if units are cached and still valid
    $cache_key = 'units_data';
    
    if (isset($_SESSION['dropdown_cache'][$cache_key]) && 
        time() - $_SESSION['dropdown_cache'][$cache_key]['timestamp'] < $cache_timeout) {
        $units = $_SESSION['dropdown_cache'][$cache_key]['data'];
        error_log("APPOINTMENTS: Using cached units data");
    } else {
        error_log("APPOINTMENTS: Fetching units from database");
        // Get all units ordered by name
        $unitsStmt = $pdo->query("SELECT id as unitID, name as unitName FROM units ORDER BY unitName ASC");
        $units = $unitsStmt->fetchAll(PDO::FETCH_OBJ);
        error_log("APPOINTMENTS: Fetched " . count($units) . " units successfully");
        
        // Cache the results
        $_SESSION['dropdown_cache'][$cache_key] = [
            'data' => $units,
            'timestamp' => time()
        ];
    }
    
    error_log("APPOINTMENTS: Fetching rank counts");
    // Count total staff at each rank for display (not cached as it changes frequently)
    $rankCounts = [];
    $rankCountStmt = $pdo->query("SELECT rank_id, COUNT(*) as count FROM staff WHERE svcStatus = 'Active' GROUP BY rank_id");
    while ($row = $rankCountStmt->fetch(PDO::FETCH_ASSOC)) {
        $rankCounts[$row['rank_id']] = $row['count'];
    }
    error_log("APPOINTMENTS: Rank counts fetched successfully");
    
} catch (Exception $e) {
    error_log("APPOINTMENTS DATABASE ERROR: " . $e->getMessage());
    error_log("APPOINTMENTS DATABASE ERROR: File: " . $e->getFile() . " Line: " . $e->getLine());
    $errors[] = "Error fetching ranks or units: " . htmlspecialchars($e->getMessage());
    error_log("Dropdown data fetch error: " . $e->getMessage());
}

// Exclude Officer Cadet, Recruit, and CE ranks (Mister, Miss)
$excludedRanks = ['Officer Cadet', 'Recruit', 'Mister', 'Miss'];
$excludedRankIds = array_map(function($r) use ($excludedRanks) {
    return in_array($r->rankName, $excludedRanks) ? $r->rankID : null;
}, $ranks);
$excludedRankIds = array_filter($excludedRankIds);

$errors = [];
$success = false;
$eligibleStaff = [];

// Step 1: Select current rank
$currentRankId = $_POST['current_rank'] ?? $_GET['current_rank'] ?? '';
$currentRank = null;
if ($currentRankId) {
    $stmt = $pdo->prepare("SELECT * FROM ranks WHERE id = ? LIMIT 1");
    $stmt->execute([$currentRankId]);
    $currentRank = $stmt->fetch(PDO::FETCH_OBJ);
    
    // Fetch staff at the selected rank
    if ($currentRank) {
        try {
            $staffStmt = $pdo->prepare("
                SELECT s.id, s.service_number, s.first_name, s.last_name, s.rank_id, 
                       s.attestDate, s.unit_id, s.subWef, s.tempWef, s.DOB as dateOfBirth,
                       s.corps, s.svcStatus as status,
                       u.name as unit_name, r.level, r.name as rank_name, r.abbreviation as rank_abbr
                FROM staff s
                LEFT JOIN units u ON s.unit_id = u.id
                LEFT JOIN ranks r ON s.rank_id = r.id
                WHERE s.rank_id = ? AND s.svcStatus = 'Active'
                ORDER BY r.level ASC, s.subWef ASC, s.tempWef ASC, s.attestDate ASC, s.service_number ASC
            ");
            $staffStmt->execute([$currentRankId]);
            $eligibleStaff = $staffStmt->fetchAll(PDO::FETCH_OBJ);
            
            error_log("APPOINTMENTS: Found " . count($eligibleStaff) . " staff members at rank ID " . $currentRankId);
        } catch (Exception $e) {
            error_log("APPOINTMENTS: Error fetching staff - " . $e->getMessage());
            $errors[] = "Error loading staff data: " . htmlspecialchars($e->getMessage());
        }
    }
}

// Step 2: Handle form submission for appointments
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['appoint_staff'])) {
    error_log("APPOINTMENTS: Form submission received");
    
    // CSRF Protection
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== ($_SESSION['csrf_token'] ?? '')) {
        error_log("APPOINTMENTS: CSRF token validation failed");
        $errors[] = "Invalid security token. Please refresh the page and try again.";
    }
    
    if (empty($errors)) {
        error_log("APPOINTMENTS: Processing form data");
        $selectedStaff = $_POST['selected_staff'] ?? [];
        $appointmentTypeId = $_POST['appointment_type'] ?? '';
        $apptDate = $_POST['appt_date'] ?? '';
        $endDate = $_POST['end_date'] ?? null;
        $requiresApproval = isset($_POST['requires_approval']);
        $unitsSelected = $_POST['unit'] ?? [];
        $positions = $_POST['position'] ?? [];
        $locations = $_POST['location'] ?? [];
        $comments = $_POST['comment'] ?? [];
        $createdBy = $_SESSION['user_id'] ?? 0;
    $dateCreated = date('Y-m-d H:i:s');
    $status = $requiresApproval ? 'pending' : 'approved';

    if (empty($selectedStaff)) $errors[] = "Please select at least one staff member.";
    if (empty($apptDate)) {
        $errors[] = "Please select the appointment date.";
    } else {
        // Validate appointment date format and range
        $apptTimestamp = strtotime($apptDate);
        if (!$apptTimestamp) {
            $errors[] = "Invalid appointment date format.";
        } else {
            $today = strtotime(date('Y-m-d'));
            $maxFutureDate = strtotime('+2 years');
            if ($apptTimestamp < $today) {
                $errors[] = "Appointment date cannot be in the past.";
            } elseif ($apptTimestamp > $maxFutureDate) {
                $errors[] = "Appointment date cannot be more than 2 years in the future.";
            }
        }
    }
    if (empty($appointmentTypeId)) $errors[] = "Please select an appointment type.";
    
    // Validate appointment type exists in database
    if (!empty($appointmentTypeId)) {
        $typeStmt = $pdo->prepare("SELECT id, is_temporary FROM appointment_type WHERE id = ?");
        $typeStmt->execute([$appointmentTypeId]);
        $typeInfo = $typeStmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$typeInfo) {
            $errors[] = "Invalid appointment type selected.";
        } else {
            $isTemporary = $typeInfo['is_temporary'];
        }
    }

    // Calculate end date if not provided (3 years from appointment date for all types if not specified)
    if (empty($endDate) && !empty($apptDate)) {
        $endDate = date('Y-m-d', strtotime($apptDate . ' +3 years'));
        error_log("APPOINTMENTS: Auto-calculated end date as 3 years from appointment: $endDate");
    }

    // Validate end date if provided
    if (!empty($endDate)) {
        $endTimestamp = strtotime($endDate);
        $apptTimestamp = strtotime($apptDate);
        
        if (!$endTimestamp) {
            $errors[] = "Invalid end date format.";
        } elseif ($apptTimestamp && $endTimestamp <= $apptTimestamp) {
            $errors[] = "End date must be after the appointment date.";
        } elseif ($endTimestamp > strtotime('+10 years')) {
            $errors[] = "End date cannot be more than 10 years in the future.";
        }
    }

    // Validate each selected staff member and their units
    foreach ($selectedStaff as $svcNo) {
        $unitId = trim($unitsSelected[$svcNo] ?? '');
        if (empty($unitId)) {
            $errors[] = "Please select a unit for staff member " . htmlspecialchars($svcNo) . ".";
        } elseif (!is_numeric($unitId)) {
            $errors[] = "Invalid unit selection for staff member " . htmlspecialchars($svcNo) . ".";
        }
        
        // Validate service number format (allow both numeric and alphanumeric)
        if (!preg_match('/^([A-Z]{2}\d{6}|\d{6})$/', $svcNo)) {
            $errors[] = "Invalid service number format for " . htmlspecialchars($svcNo) . ". Expected format: AR123456 or 103003";
        }
    }
    
    if (empty($errors)) {
        // Use PDO for all DB operations
        try {
            $pdo = getDbConnection();
            if (!$pdo) {
                throw new Exception("Database connection failed");
            }
        } catch (Exception $e) {
            $errors[] = "Database connection error: " . htmlspecialchars($e->getMessage());
            error_log("Database connection error in appointments: " . $e->getMessage());
        }
        
        // Check for existing active appointments and enforce business rules
        // Business Rule: A staff can have max 1 substantive + 1 temporary/acting appointment
        $existingAppointments = [];
        
        if (empty($errors)) {
            try {
                // Fetch ALL active appointments for each staff member with appointment type info
                $duplicateCheckStmt = $pdo->prepare("
                    SELECT sa.service_number, sa.appointment_id, sa.appointment_date, 
                           sa.end_date, u.name as unit_name, sa.id as appt_record_id,
                           sa.appointment_type, at.type_name, at.is_temporary
                    FROM staff_appointment sa
                    LEFT JOIN units u ON sa.unit_id = u.id
                    LEFT JOIN appointment_type at ON sa.appointment_type = at.id
                    WHERE sa.service_number = ? 
                    AND (
                        sa.end_date IS NULL 
                        OR sa.end_date >= CURDATE()
                    )
                    ORDER BY sa.appointment_date DESC
                ");
                
                foreach ($selectedStaff as $svcNo) {
                    $duplicateCheckStmt->execute([$svcNo]);
                    $existingAppts = $duplicateCheckStmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    if (!empty($existingAppts)) {
                        // Store all existing appointments for this staff member
                        $existingAppointments[$svcNo] = $existingAppts;
                        
                        // Check business rule: validate appointment type conflicts
                        // Get the new appointment type being created
                        $newTypeStmt = $pdo->prepare("SELECT is_temporary FROM appointment_type WHERE id = ?");
                        $newTypeStmt->execute([$appointmentTypeId]);
                        $newTypeInfo = $newTypeStmt->fetch(PDO::FETCH_ASSOC);
                        $newIsTemporary = $newTypeInfo['is_temporary'] ?? 0;
                        
                        // Count existing appointment types
                        $hasSubstantive = false;
                        $hasTemporary = false;
                        
                        foreach ($existingAppts as $appt) {
                            if ($appt['is_temporary']) {
                                $hasTemporary = true;
                            } else {
                                $hasSubstantive = true;
                            }
                        }
                        
                        // Validate business rule
                        if (!$newIsTemporary && $hasSubstantive) {
                            $errors[] = "Staff member {$svcNo} already has an active substantive appointment. Cannot create another substantive appointment. The existing appointment will be automatically ended.";
                        } elseif ($newIsTemporary && $hasTemporary && $hasSubstantive) {
                            $errors[] = "Staff member {$svcNo} already has both substantive and temporary appointments. Cannot create additional temporary appointment. Existing appointments will be automatically ended.";
                        }
                        
                        // Note: We don't block - we inform user that previous appointments will be auto-ended
                        // Clear the errors as we'll handle this automatically
                        $errors = array_filter($errors, function($error) use ($svcNo) {
                            return strpos($error, $svcNo) === false;
                        });
                    }
                }
                
                // Store existing appointments in session for informational display
                if (!empty($existingAppointments)) {
                    $_SESSION['existing_appointments'] = $existingAppointments;
                }
            } catch (Exception $e) {
                $errors[] = "Error checking for existing appointments: " . htmlspecialchars($e->getMessage());
                error_log("Duplicate check error in appointments: " . $e->getMessage());
            }
        }
        
        if (empty($errors)) {
        
        // Prepare all statements outside the loop for better performance
        $selectStaffStmt = $pdo->prepare("SELECT id, service_number, rank_id FROM staff WHERE service_number = ? LIMIT 1");
        $insertApptStmt = $pdo->prepare("INSERT INTO staff_appointment (
            staff_id, 
            appointment_id, 
            appointment_type, 
            rank_id,
            unit_id, 
            location,
            service_number, 
            appointment_date, 
            start_date,
            end_date, 
            comment,
            remarks,
            created_by, 
            created_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $updateStaffStmt = $pdo->prepare("UPDATE staff SET unit_id = ? WHERE service_number = ?");
        $endApptStmt = $pdo->prepare("UPDATE staff_appointment SET end_date = ?, comment = CONCAT(comment, ?) WHERE id = ?");
        
        // Use SINGLE transaction for all appointments (performance optimization)
        try {
            $pdo->beginTransaction();
            $appointmentsCreated = 0;
            $appointmentsEnded = 0;
            
            // Process each staff member
            foreach ($selectedStaff as $serviceNumber) {
                $unitId = htmlspecialchars(trim($unitsSelected[$serviceNumber]));
                $position = htmlspecialchars(trim($positions[$serviceNumber] ?? ''));
                $location = htmlspecialchars(trim($locations[$serviceNumber] ?? ''));
                $comment = htmlspecialchars(trim($comments[$serviceNumber] ?? ''));
                
                // Get staff details including rank_id
                $selectStaffStmt->execute([$serviceNumber]);
                $staff = $selectStaffStmt->fetch(PDO::FETCH_OBJ);
                $staffId = $staff ? $staff->id : null;
                $rankId = $staff ? $staff->rank_id : null;
                
                if (!$staffId) {
                    throw new Exception("Staff member with service number {$serviceNumber} not found");
                }
                
                // ALWAYS end previous appointments automatically (no checkbox needed)
                // Set end date to day before new appointment starts
                if (isset($existingAppointments[$serviceNumber])) {
                    $existingAppts = $existingAppointments[$serviceNumber];
                    $newEndDate = date('Y-m-d', strtotime($apptDate . ' -1 day'));
                    $endComment = " | Ended automatically for new appointment on " . date('d M Y');
                    
                    // End ALL existing active appointments for this staff member
                    foreach ($existingAppts as $existingAppt) {
                        $endApptStmt->execute([
                            $newEndDate,
                            $endComment,
                            $existingAppt['appt_record_id']
                        ]);
                        
                        $appointmentsEnded++;
                        
                        // Enhanced audit log for ended appointment
                        error_log(json_encode([
                            'action' => 'appointment_ended_automatically',
                            'timestamp' => date('Y-m-d H:i:s'),
                            'user_id' => $createdBy,
                            'service_number' => $serviceNumber,
                            'old_appointment_position' => $existingAppt['appointment_id'],
                            'old_unit' => $existingAppt['unit_name'],
                            'old_type' => $existingAppt['type_name'],
                            'end_date' => $newEndDate,
                            'reason' => 'Automatic end for new appointment',
                            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
                        ]));
                    }
                }
                
                // Insert new appointment record
                // NOTE: appointment_id now stores the position/role
                $appointmentPosition = $position ?: 'Not Specified';
                $startDate = $apptDate; // Start date is same as appointment date
                $remarks = $comment; // Additional comments go to remarks field
                
                $insertApptStmt->execute([
                    $staffId,
                    $appointmentPosition,      // appointment_id = position/role
                    $appointmentTypeId,        // appointment_type = FK to appointment_type table
                    $rankId,                   // rank_id = staff's current rank at time of appointment
                    $unitId,                   // unit_id
                    $location,                 // location field
                    $serviceNumber,            // service_number
                    $apptDate,                 // appointment_date
                    $startDate,                // start_date (same as appointment_date)
                    $endDate,                  // end_date (3 years from appointment if not specified)
                    '',                        // comment field (keeping empty, using remarks instead)
                    $remarks,                  // remarks = user comments
                    $createdBy,                // created_by
                    $dateCreated               // created_at
                ]);
                
                // Update staff unit for the appointment
                $updateStaffStmt->execute([$unitId, $serviceNumber]);
                
                // Update staff appt column with the appointment position
                $updateApptStmt = $pdo->prepare("UPDATE staff SET appt = ? WHERE service_number = ?");
                $updateApptStmt->execute([$appointmentPosition, $serviceNumber]);
                
                $appointmentsCreated++;
                
                // Enhanced audit log for new appointment
                error_log(json_encode([
                    'action' => 'appointment_created',
                    'timestamp' => $dateCreated,
                    'user_id' => $createdBy,
                    'staff_id' => $staffId,
                    'service_number' => $serviceNumber,
                    'rank_id' => $rankId,
                    'appointment_position' => $appointmentPosition,
                    'unit_id' => $unitId,
                    'location' => $location,
                    'appointment_type' => $appointmentTypeId,
                    'appointment_date' => $apptDate,
                    'start_date' => $startDate,
                    'end_date' => $endDate,
                    'remarks' => $remarks,
                    'requires_approval' => $requiresApproval,
                    'previous_ended' => isset($existingAppointments[$serviceNumber]),
                    'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
                ]));
            }
            
            // Commit all changes at once
            $pdo->commit();
            $success = true;
            
            // Clear session data
            unset($_SESSION['existing_appointments']);
            
            // Build success message
            $successMessage = "Successfully created {$appointmentsCreated} appointment(s)";
            if ($appointmentsEnded > 0) {
                $successMessage .= " and automatically ended {$appointmentsEnded} previous appointment(s)";
            }
            $successMessage .= ". " . ($requiresApproval ? "Appointments submitted for approval." : "");
            
        } catch (Exception $e) {
            // Rollback ALL changes on any error
            $pdo->rollback();
            
            error_log("Appointment batch failed: " . $e->getMessage());
            error_log("Stack trace: " . $e->getTraceAsString());
            
            $errors[] = "Error processing appointments: " . 
                       (strpos($e->getMessage(), 'not found') !== false ? 'Staff member not found' : htmlspecialchars($e->getMessage()));
        }
        } // Close inner if (empty($errors))
    } // Close if (empty($errors)) from line 214 (validation check)
    } // Close if (empty($errors)) from line 140 (first validation check)
} // Close if ($_SERVER['REQUEST_METHOD'] === 'POST')

include dirname(__DIR__) . '/shared/header.php';
include dirname(__DIR__) . '/shared/sidebar.php';
?>

<!-- DataTables CSS -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/datatables.net-bs5@1.13.6/css/dataTables.bootstrap5.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/datatables.net-responsive-bs5@2.5.0/css/responsive.bootstrap5.min.css">

<style>
.stepper { list-style: none; padding: 0; display: flex; gap: 10px; }
.step { padding: 4px 10px; border-radius: 12px; background: #eee; color: #333; }
.step.active { background: #17a2b8; color: #fff; font-weight: bold; }

/* DataTables Custom Styling */
#staffSelectionTable {
    font-size: 0.9rem;
}

#staffSelectionTable thead th {
    background-color: #f8f9fa;
    font-weight: 600;
    border-bottom: 2px solid #dee2e6;
    padding: 12px 8px;
}

#staffSelectionTable tbody tr {
    transition: background-color 0.2s ease;
}

#staffSelectionTable tbody tr:hover {
    background-color: #f1f3f5;
    cursor: pointer;
}

/* Selected row styling */
#staffSelectionTable tbody tr.selected {
    background-color: #cfe2ff !important;
    border-left: 4px solid #0d6efd !important;
}

#staffSelectionTable tbody tr.selected:hover {
    background-color: #b6d4fe !important;
}

.staff-checkbox {
    cursor: pointer;
    width: 18px;
    height: 18px;
}

#masterCheckbox {
    cursor: pointer;
    width: 18px;
    height: 18px;
}

/* Loading state */
#staffTableContainer.loading {
    position: relative;
    opacity: 0.6;
    pointer-events: none;
}

#staffTableContainer.loading::after {
    content: 'Loading staff data...';
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    background: rgba(255, 255, 255, 0.95);
    padding: 20px 40px;
    border-radius: 8px;
    box-shadow: 0 4px 6px rgba(0,0,0,0.1);
    font-weight: 600;
    color: #0d6efd;
}

/* Mobile responsive */
@media (max-width: 768px) {
    #staffSelectionTable {
        font-size: 0.8rem;
    }
    
    #staffSelectionTable thead th,
    #staffSelectionTable tbody td {
        padding: 0.5rem 0.25rem;
    }
    
    /* Hide less important columns on mobile */
    #staffSelectionTable th:nth-child(6),
    #staffSelectionTable td:nth-child(6),
    #staffSelectionTable th:nth-child(7),
    #staffSelectionTable td:nth-child(7),
    #staffSelectionTable th:nth-child(8),
    #staffSelectionTable td:nth-child(8) {
        display: none;
    }
}
</style>

<!-- Custom styles for staff select dropdown -->
<style>
    /* Enhanced staff selection styling */
    .select2-container--default .select2-results__option {
        padding: 6px 12px;
        border-bottom: 1px solid #f0f0f0;
    }
    
    .select2-container--default .select2-results__option:last-child {
        border-bottom: none;
    }
    
    .select2-container--default .select2-results__option--highlighted[aria-selected] .staff-badge {
        background-color: rgba(255,255,255,0.2) !important;
        color: #fff !important;
    }
    
    .select2-container--default .select2-results__option--highlighted[aria-selected] .staff-details {
        color: rgba(255,255,255,0.8) !important;
    }
    
    .select2-container--default .select2-selection--multiple .select2-selection__choice {
        background-color: #f8f9fa;
        border: 1px solid #dee2e6;
        padding: 3px 8px;
        margin-right: 5px;
        margin-top: 5px;
    }
    
    /* Fix for selected staff display */
    .select2-selection__choice {
        max-width: 100%;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    
    /* Badge styles */
    .staff-badge {
        font-family: monospace;
        font-weight: bold;
    }
</style>

<!-- Main Content -->
<div class="content-wrapper with-sidebar">
    <div class="container-fluid">
        <div class="main-content">
            <div class="row">
                <div class="col-12">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h1 class="section-title">
                            <i class="fas fa-briefcase"></i> Staff Appointments
                        </h1>
                        <div>
                            <a href="/Armis2/admin_branch/index.php" class="btn btn-outline-secondary">
                                <i class="fas fa-arrow-left"></i> Back to Dashboard
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-12">
                    <div class="card shadow-sm">
                        <div class="card-header bg-success text-white">
                            <h4 class="mb-0"><i class="fa fa-user-plus"></i> Staff Appointments</h4>
                        </div>
                        <div class="card-body">
            <?php if ($success): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i> <?= $successMessage ?? 'Appointments successful for all selected staff.' ?>
                </div>
            <?php endif; ?>
            <?php if ($errors): ?>
                <div class="alert alert-danger">
                    <ul class="mb-0">
                        <?php foreach ($errors as $err): ?>
                            <li><?=htmlspecialchars($err)?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <!-- Step 1: Select current rank -->
            <form class="mb-4" id="rankForm" method="get">
                <div class="row">
                    <div class="col-md-6 mb-2">
                        <label class="form-label">Current Rank for Appointment *</label>
                        <select name="current_rank" id="current_rank" class="form-select" required>
                            <option value="">Select Current Rank...</option>
                            <?php foreach ($ranks as $r):
                                // Skip excluded ranks (Officer Cadet and Recruit)
                                if (in_array($r->rankName, $excludedRanks)) continue;
                                
                                // Get count of staff at this rank
                                $staffCount = isset($rankCounts[$r->rankID]) ? $rankCounts[$r->rankID] : 0;
                                ?>
                                <option value="<?=$r->rankID?>" <?=($currentRankId==$r->rankID)?'selected':''?>>
                                    <?=$r->rankName?> (<?=$staffCount?> staff)
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small class="text-muted">Only staff members at this rank will be available for selection.</small>
                    </div>
                    <div class="col-md-2 mb-2 d-flex align-items-end">
                        <?php if($currentRankId): ?><?php endif; ?>
                    </div>
                </div>
            </form>

            <!-- Step 2: Multi-Select + Panel -->
            <?php if ($currentRankId): ?>
            <form method="post" action="" id="appointmentForm">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                <input type="hidden" name="current_rank" value="<?=htmlspecialchars($currentRankId)?>">
                <div class="row mb-3">
                    <div class="col-md-12">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h5 class="mb-0">
                                <i class="fa fa-users"></i> 
                                Select Staff Members for Appointment
                            </h5>
                            <div>
                                <button type="button" class="btn btn-sm btn-success" id="selectAllBtn">
                                    <i class="fa fa-check-double"></i> Select All
                                </button>
                                <button type="button" class="btn btn-sm btn-warning" id="deselectAllBtn">
                                    <i class="fa fa-times"></i> Deselect All
                                </button>
                            </div>
                        </div>
                        
                        <div class="alert alert-info d-flex justify-content-between align-items-center">
                            <div>
                                <i class="fa fa-info-circle"></i>
                                <strong>Rank Selected:</strong> <?= htmlspecialchars($currentRank->name ?? '') ?>
                            </div>
                        </div>
                        
                    <!-- Staff Selection Count -->
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div class="text-muted">
                            <strong>Total:</strong> <span id="totalStaffCount">0</span> staff member(s)
                        </div>
                        <div class="text-primary">
                            <strong>Selected:</strong> <span id="selectionCount">0</span> staff member(s)
                        </div>
                    </div>
                    
                    <!-- Staff Selection Table -->
                    <div class="table-responsive" id="staffTableContainer">
                        <table class="table table-hover table-sm" id="staffSelectionTable" style="width:100%">
                            <thead class="table-light">
                                <tr>
                                    <th style="width: 40px;">
                                        <input type="checkbox" id="masterCheckbox" class="form-check-input" title="Select/Deselect All">
                                    </th>
                                    <th>Service No.</th>
                                    <th>Rank</th>
                                    <th>Name</th>
                                    <th>Unit</th>
                                    <th>Corps</th>
                                    <th>Status</th>
                                    <th style="width: 80px;">History</th>
                                </tr>
                            </thead>
                            <tbody>
                                <!-- DataTables will populate this -->
                            </tbody>
                        </table>
                    </div>
                        
                        <!-- Hidden container to store selected staff for form submission -->
                        <div id="selectedStaffInputs"></div>
                    </div>
                </div>
                
                <div id="staffDetailsPanel"></div>
                <div class="row mb-3">
                    <div class="col-md-3 mb-2">
                        <label class="form-label">Appointment Type *</label>
                        <select name="appointment_type" id="appointment_type" class="form-select" required>
                            <option value="">Select Appointment Type</option>
                            <?php foreach ($appointmentTypes as $type): ?>
                            <option value="<?= $type['id'] ?>" 
                                    data-is-temporary="<?= $type['is_temporary'] ?>" 
                                    data-duration="<?= $type['default_duration_months'] ?? '' ?>">
                                <?= htmlspecialchars($type['type_name']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3 mb-2">
                        <label class="form-label">Appointment Date *</label>
                        <input type="date" name="appt_date" id="appt_date" class="form-control" required value="<?=htmlspecialchars($_POST['appt_date'] ?? date('Y-m-d'))?>">
                    </div>
                    <div class="col-md-3 mb-2">
                        <label class="form-label">End Date</label>
                        <input type="date" name="end_date" id="end_date" class="form-control" value="<?=htmlspecialchars($_POST['end_date'] ?? '')?>">
                        <small class="text-muted">Default: 3 years from appointment date</small>
                    </div>
                    <div class="col-md-3 mb-2">
                        <!-- Automatic ending - no checkbox needed -->
                    </div>
                </div>
                
                <?php if (!empty($_SESSION['existing_appointments'])): ?>
                <div class="alert alert-info mb-3">
                    <h6 class="alert-heading"><i class="fas fa-info-circle"></i> Active Appointments Will Be Automatically Ended</h6>
                    <p class="mb-2">The following staff members have existing active appointments that will be automatically ended one day before the new appointment date:</p>
                    <ul class="mb-2">
                    <?php foreach ($_SESSION['existing_appointments'] as $svcNo => $appts): ?>
                        <?php 
                        // Handle both single appointment (legacy) and multiple appointments (new)
                        $appointmentList = isset($appts['service_number']) ? [$appts] : $appts;
                        foreach ($appointmentList as $appt): 
                        ?>
                        <li>
                            <strong><?= htmlspecialchars($svcNo) ?></strong>: 
                            <?= htmlspecialchars($appt['appointment_id'] ?? 'Unknown Position') ?> 
                            at <?= htmlspecialchars($appt['unit_name'] ?? 'Unknown Unit') ?>
                            <span class="badge bg-secondary"><?= htmlspecialchars($appt['type_name'] ?? 'Unknown Type') ?></span>
                            <?php if ($appt['end_date']): ?>
                                (current end date: <?= date('d M Y', strtotime($appt['end_date'])) ?>)
                            <?php else: ?>
                                (permanent)
                            <?php endif; ?>
                        </li>
                        <?php endforeach; ?>
                    <?php endforeach; ?>
                    </ul>
                    <p class="mb-0"><i class="fas fa-check-circle text-success"></i> <strong>Automatic Action:</strong> Creating this new appointment will automatically end the above appointment(s) and set their end date to one day before the new appointment starts.</p>
                </div>
                <?php endif; ?>
                
                <div class="text-end">
                    <button type="submit" id="submitBtn" name="appoint_staff" class="btn btn-primary px-5 py-2"><i class="fa fa-user-plus"></i> Appoint</button>
                </div>
            </form>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Back to Top Button -->
<button type="button" id="backToTopBtn" class="btn btn-secondary rounded-circle" style="position:fixed;bottom:30px;right:30px;display:none;z-index:999;">
    <i class="fa fa-arrow-up"></i>
</button>

<script src="https://cdn.jsdelivr.net/npm/jquery@3.6.0/dist/jquery.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/datatables.net@1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/datatables.net-bs5@1.13.6/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/datatables.net-responsive@2.5.0/js/dataTables.responsive.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/datatables.net-responsive-bs5@2.5.0/js/responsive.bootstrap5.min.js"></script>
<script>
const unitsData = <?=json_encode($units, JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT)?>;
const eligibleStaff = <?=json_encode($eligibleStaff ?? [], JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT)?>;

// Define missing formatStaffResult function
function formatStaffResult(staff) {
    if (!staff.id) {
        return staff.text;
    }
    return $(
        '<div class="d-flex align-items-center">' +
            '<div class="flex-grow-1">' +
                '<div class="fw-bold">' + staff.service_number + ' - ' + staff.last_name + ' ' + staff.first_name + '</div>' +
                '<small class="text-muted">' + (staff.unit_name || 'No Unit') + '</small>' +
            '</div>' +
        '</div>'
    );
}

function renderStaffPanels(selected) {
    const panel = $('#staffDetailsPanel');
    panel.empty();
    if (!selected || selected.length === 0) return;
    
    selected.forEach(svcNo => {
        // Get staff name from the eligible staff data
        const staffMember = eligibleStaff.find(s => s.service_number === svcNo);
        const staffName = staffMember ? `${staffMember.last_name} ${staffMember.first_name}` : svcNo;
        
        let unitOptions = '<option value="">Select Unit</option>';
        unitsData.forEach(u => {
            unitOptions += `<option value="${u.unitID}">${u.unitName}</option>`;
        });
        
        let positionOptions = '<option value="">Select Position</option>';
        <?php foreach ($standardPositions as $pos): ?>
        positionOptions += '<option value="<?= htmlspecialchars($pos) ?>"><?= htmlspecialchars($pos) ?></option>';
        <?php endforeach; ?>
        
        panel.append(`
            <div class="card mb-3 staff-detail-card" data-svcno="${svcNo}">
                <div class="card-header bg-light">
                    <h5 class="mb-0">
                        <span class="badge bg-primary me-2">${svcNo}</span>
                        ${staffName}
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row align-items-center">
                        <div class="col-md-3 mb-2">
                            <label class="form-label mb-1">Unit *</label>
                            <select name="unit[${svcNo}]" class="form-select unit-select" style="width: 100%;" required>${unitOptions}</select>
                        </div>
                        <div class="col-md-3 mb-2">
                            <label class="form-label mb-1">Position/Role *</label>
                            <select name="position[${svcNo}]" class="form-select position-select" style="width: 100%;" required>
                                ${positionOptions}
                            </select>
                        </div>
                        <div class="col-md-3 mb-2">
                            <label class="form-label mb-1">Location</label>
                            <input type="text" name="location[${svcNo}]" class="form-control location-input" 
                                   maxlength="200" placeholder="e.g., Camp Ayanganna">
                        </div>
                        <div class="col-md-3 mb-2">
                            <label class="form-label mb-1">Comments</label>
                            <input type="text" name="comment[${svcNo}]" class="form-control comment-input" 
                                   maxlength="255" placeholder="Additional comments">
                        </div>
                    </div>
                </div>
            </div>
        `);
    });
    $('.unit-select').select2({ placeholder: "Select unit", allowClear: true, width: 'resolve' });
    $('.position-select').select2({ placeholder: "Select position", allowClear: true, width: 'resolve', tags: true });

    // Update hidden inputs for form submission
    $('#selectedStaffInputs').empty();
    selected.forEach(svcNo => {
        $('#selectedStaffInputs').append(`<input type="hidden" name="selected_staff[]" value="${svcNo}">`);
    });
}

// Format staff display in dropdown results - REMOVED (using DataTables now)
// Format selected staff in the input box - REMOVED (using DataTables now)

$(function() {
    // Helper function to get current rank ID consistently
    function getCurrentRankId() {
        // Try hidden input first (Step 2), then dropdown (Step 1), then PHP fallback
        let rankId = $('input[name="current_rank"]').val() || $('#current_rank').val() || '';
        <?php if ($currentRankId): ?>
        if (!rankId) {
            rankId = '<?=$currentRankId?>';
        }
        <?php endif; ?>
        return rankId;
    }
    
    // Initialize DataTables for staff selection
    let staffTable;
    console.log('Eligible Staff Data:', eligibleStaff);
    console.log('Current Rank ID:', '<?= $currentRankId ?>');
    <?php if ($currentRankId && !empty($eligibleStaff)): ?>
    $(document).ready(function() {
        console.log('Document ready, checking DataTables availability...');
        
        if (typeof $.fn.DataTable === 'undefined') {
            console.error('DataTables is not loaded!');
            $('#staffSelectionTable tbody').html('<tr><td colspan="7" class="text-center text-danger">DataTables library not loaded. Please refresh the page.</td></tr>');
            return;
        }
        
        console.log('Initializing DataTables with', eligibleStaff.length, 'staff members');
        console.log('Staff data sample:', eligibleStaff.slice(0, 2)); // Show first 2 records
        
        try {
            staffTable = $('#staffSelectionTable').DataTable({
                data: eligibleStaff,
                pageLength: 25,
                order: [[1, 'asc']], // Sort by service number
                responsive: true,
                columns: [
                    {
                        data: null,
                        orderable: false,
                        className: 'select-checkbox text-center',
                        render: function(data, type, row) {
                            console.log('Rendering checkbox for:', row.service_number);
                            return `<input type="checkbox" class="staff-checkbox form-check-input" value="${row.service_number}" data-staff-id="${row.id}">`;
                        }
                    },
                    { 
                        data: 'service_number', 
                        title: 'Service No.',
                        orderable: true
                    },
                    { 
                        data: null,
                        title: 'Rank',
                        orderable: true,
                        render: function(data, type, row) {
                            return `<span class="badge bg-primary">${row.rank_abbr || row.rank_name || 'N/A'}</span>`;
                        }
                    },
                    {
                        data: null,
                        title: 'Name',
                        orderable: true,
                        render: function(data, type, row) {
                            // Helper function for proper title case
                            function toTitleCase(str) {
                                if (!str) return '';
                                return str.trim().split(/\s+/).map(word => {
                                    if (word.length === 0) return '';
                                    // Handle hyphenated names and apostrophes
                                    return word.split('-').map(part => 
                                        part.split("'").map(subpart => 
                                            subpart.charAt(0).toUpperCase() + subpart.slice(1).toLowerCase()
                                        ).join("'")
                                    ).join('-');
                                }).join(' ');
                            }
                            
                            const surname = toTitleCase(row.last_name || '');
                            const firstName = toTitleCase(row.first_name || '');
                            
                            // Properly format name without comma
                            let fullName = '';
                            if (surname && firstName) {
                                fullName = `${firstName} ${surname}`;
                            } else if (surname) {
                                fullName = surname;
                            } else if (firstName) {
                                fullName = firstName;
                            } else {
                                fullName = 'N/A';
                            }
                            
                            return `<div class="fw-bold">${fullName}</div>`;
                        }
                    },
                    { 
                        data: 'unit_name', 
                        title: 'Unit',
                        orderable: true,
                        defaultContent: 'N/A'
                    },
                    { 
                        data: 'corps', 
                        title: 'Corps',
                        orderable: true,
                        defaultContent: 'N/A'
                    },
                    { 
                        data: null,
                        title: 'Status',
                        orderable: true,
                        render: function(data, type, row) {
                            const status = row.status || 'Active';
                            const statusClass = status === 'Active' ? 'bg-success' : 'bg-secondary';
                            return `<span class="badge ${statusClass}">${status}</span>`;
                        }
                    },
                    {
                        data: null,
                        title: 'History',
                        orderable: false,
                        className: 'text-center',
                        render: function(data, type, row) {
                            return `<button type="button" class="btn btn-sm btn-outline-info view-history-btn" 
                                    data-svcno="${row.service_number}" 
                                    title="View Appointment History">
                                    <i class="fas fa-history"></i>
                                    </button>`;
                        }
                    }
                ],
                language: {
                    emptyTable: "No staff members found at this rank",
                    info: "Showing _START_ to _END_ of _TOTAL_ staff members",
                    infoEmpty: "No staff members available",
                    search: "Search staff:"
                },
                dom: '<"row"<"col-sm-12 col-md-6"l><"col-sm-12 col-md-6"f>>rtip',
                drawCallback: function(settings) {
                    console.log('DataTables draw complete. Rows:', this.api().rows().count());
                }
            });
            
            console.log('DataTables initialized successfully');
        } catch(error) {
            console.error('DataTables initialization failed:', error);
            
            // Fallback: Show data in basic table format
            console.log('Attempting fallback table rendering...');
            let tableHTML = '';
            eligibleStaff.forEach(function(staff) {
                const age = staff.dateOfBirth ? Math.floor((new Date() - new Date(staff.dateOfBirth)) / (365.25 * 24 * 60 * 60 * 1000)) : 'N/A';
                const yearsService = staff.attestDate ? Math.floor((new Date() - new Date(staff.attestDate)) / (365.25 * 24 * 60 * 60 * 1000)) : 'N/A';
                const attestDate = staff.attestDate ? new Date(staff.attestDate).toLocaleDateString('en-GB') : 'N/A';
                
                tableHTML += `
                    <tr>
                        <td class="text-center">
                            <input type="checkbox" class="staff-checkbox form-check-input" value="${staff.service_number}" data-staff-id="${staff.id}">
                        </td>
                        <td>${staff.service_number}</td>
                        <td><a href="#" class="staff-name-link" data-service-number="${staff.service_number}">${staff.first_name} ${staff.last_name}</a></td>
                        <td>${staff.unit_name || 'No Unit Assigned'}</td>
                        <td>${age} years</td>
                        <td>${yearsService} years</td>
                        <td>${attestDate}</td>
                    </tr>
                `;
            });
            $('#staffSelectionTable tbody').html(tableHTML);
        }
        
        // Update staff count
        $('#totalStaffCount').text(eligibleStaff.length);
    });
    <?php else: ?>
    $(document).ready(function() {
        console.log('No staff found or no rank selected');
        // Initialize empty DataTable
        staffTable = $('#staffSelectionTable').DataTable({
            data: [],
            pageLength: 25,
            order: [[1, 'asc']],
            responsive: true,
            columns: [
                { data: null, orderable: false, className: 'select-checkbox text-center', defaultContent: '' },
                { data: null, title: 'Service No.', defaultContent: '' },
                { data: null, title: 'Rank', defaultContent: '' },
                { data: null, title: 'Name', defaultContent: '' },
                { data: null, title: 'Unit', defaultContent: '' },
                { data: null, title: 'Corps', defaultContent: '' },
                { data: null, title: 'Status', defaultContent: '' }
            ],
            language: {
                emptyTable: "<?php echo $currentRankId ? 'No staff members found at this rank' : 'Please select a rank to view staff members'; ?>",
                info: "Showing _START_ to _END_ of _TOTAL_ staff members",
                infoEmpty: "No staff members available",
                search: "Search staff:"
            },
            dom: '<"row"<"col-sm-12 col-md-6"l><"col-sm-12 col-md-6"f>>rtip',
            responsive: true
        });
        $('#totalStaffCount').text('0');
    });
    <?php endif; ?>
    
    // Auto-reload when rank changes
    $('#current_rank').on('change', function() {
        const selectedRank = $(this).val();
        if (selectedRank) {
            // Add loading indicator
            $('#staffSelectionTable tbody').html('<tr><td colspan="7" class="text-center"><div class="spinner-border spinner-border-sm" role="status"><span class="visually-hidden">Loading...</span></div> Loading staff...</td></tr>');
            
            // Submit form to reload with selected rank
            const form = $('<form method="GET" action=""></form>');
            form.append($('<input type="hidden" name="current_rank" value="' + selectedRank + '">'));
            $('body').append(form);
            form.submit();
        } else {
            // Clear table if no rank selected
            if (staffTable) {
                staffTable.clear().draw();
            }
            $('#totalStaffCount').text('0');
        }
    });

    // Handle checkbox selection
    $(document).on('change', '.staff-checkbox', function() {
        const row = $(this).closest('tr');
        if ($(this).is(':checked')) {
            row.addClass('selected');
        } else {
            row.removeClass('selected');
        }
        updateSelectionCount();
        updateStaffPanels();
    });
    
    // Handle row click
    $(document).on('click', '#staffSelectionTable tbody tr', function(e) {
        if (!$(e.target).is('input[type="checkbox"], a')) {
            const checkbox = $(this).find('.staff-checkbox');
            checkbox.prop('checked', !checkbox.prop('checked')).trigger('change');
        }
    });
    
    // Master checkbox functionality
    $(document).on('change', '#masterCheckbox', function() {
        const isChecked = $(this).is(':checked');
        $('.staff-checkbox').prop('checked', isChecked).trigger('change');
    });
    
    // Select/Deselect all buttons
    $('#selectAllBtn').on('click', function() {
        $('#masterCheckbox').prop('checked', true).trigger('change');
    });
    
    $('#deselectAllBtn').on('click', function() {
        $('#masterCheckbox').prop('checked', false).trigger('change');
    });
    
    // Update selection count
    function updateSelectionCount() {
        const selectedCount = $('.staff-checkbox:checked').length;
        $('#selectionCount').text(selectedCount);
        
        // Update master checkbox state
        const totalCount = $('.staff-checkbox').length;
        if (selectedCount === 0) {
            $('#masterCheckbox').prop('indeterminate', false).prop('checked', false);
        } else if (selectedCount === totalCount) {
            $('#masterCheckbox').prop('indeterminate', false).prop('checked', true);
        } else {
            $('#masterCheckbox').prop('indeterminate', true);
        }
    }
    
    // Update staff panels based on selection
    function updateStaffPanels() {
        const selected = [];
        $('.staff-checkbox:checked').each(function() {
            selected.push($(this).val());
        });
        renderStaffPanels(selected);
    }
    
    // Select2 for staff multi-select with AJAX search
    $('#selected_staff').select2({
        placeholder: "Type to search for staff members...",
        allowClear: true,
        width: 'resolve',
        minimumInputLength: 1, // Require at least 1 character to search
        templateResult: formatStaffResult,
        templateSelection: formatStaffSelection,
        escapeMarkup: function(m) { return m; }, // Allow HTML in the formatting
        ajax: {
            url: 'search_staff_fixed.php',
            dataType: 'json',
            delay: 250,
            method: 'GET',  // Explicitly set method to GET
            data: function(params) {
                // Get rank ID using helper function
                const rankId = getCurrentRankId();
                
                // Make sure we're sending the correct parameters
                const queryData = {
                    q: params.term || '',  // Handle null/undefined
                    rank_id: rankId
                };
                
                return queryData;
            },
            processResults: function(data) {
                var results = [];
                if (Array.isArray(data)) {
                    if (data.length === 0) {
                        // No staff found message - removed for production
                    }
                    data.forEach(function(item) {
                        if (!item.service_number) {
                            console.error('Missing service_number in item:', item);
                            return;
                        }
                        var fullName = (item.first_name || '') + ' ' + (item.last_name || '');
                        var serviceNumber = item.service_number;
                        var unitInfo = item.unit_name ? ' (' + item.unit_name + ')' : '';
                        results.push({
                            id: serviceNumber,
                            text: serviceNumber + ' - ' + fullName + unitInfo,
                            service_number: serviceNumber,
                            last_name: item.last_name || '',
                            first_name: item.first_name || '',
                            unit_name: item.unit_name || '',
                            rank_name: item.rank_name || ''
                        });
                    });
                } else {
                    // Error handling for unexpected data type - removed for production
                }
                return {
                    results: results
                };
            },
            error: function(xhr, status, error) {
                console.error('AJAX Error:', status, error);
                console.error('Response Text:', xhr.responseText);
                
                // Enhanced error handling with user feedback
                let errorMessage = 'An error occurred while searching for staff.';
                
                if (xhr.status === 0) {
                    errorMessage = 'Network error - please check your internet connection.';
                } else if (xhr.status === 401 || xhr.status === 403) {
                    errorMessage = 'Session expired - please log in again.';
                    setTimeout(() => {
                        window.location.href = '/Armis2/login.php';
                    }, 2000);
                } else if (xhr.status === 404) {
                    errorMessage = 'Search service not found - please contact system administrator.';
                    console.error('search_staff_fixed.php not found - check file path');
                } else if (xhr.status === 500) {
                    errorMessage = 'Server error occurred - please try again or contact support.';
                    console.error('Server error in search_staff_fixed.php - check logs');
                } else if (xhr.status >= 400) {
                    errorMessage = 'Request failed - please refresh and try again.';
                }
                
                // Show user-friendly error message
                $('#selected_staff').empty().append(new Option(errorMessage, '', false, false));
                
                // Show debug information for administrators
                if (xhr.responseText && xhr.responseText.indexOf('error') !== -1) {
                    console.error('Server response:', xhr.responseText);
                }
            },
            cache: true
        }
    });

    // Render staff panels on selection
    $('#selected_staff').on('change', function() {
        const selected = $(this).val() || [];
        renderStaffPanels(selected);
    });

    // Load staff for the selected rank on page load
    function loadStaffForRank(rankId, keepExisting = false) {
        if (!rankId) return;
        
        // Show loading indicator in the select box
        if (!keepExisting) {
            $('#selected_staff').empty().append(new Option('Loading staff members...', '', false, false));
        }
        
        // Make AJAX call to get all staff with this rank
        $.ajax({
            url: 'search_staff_fixed.php',
            data: { 
                rank_id: rankId,
                q: 'all' // Using 'all' to get all staff at this rank
            },
            type: 'GET',
            dataType: 'json',
            success: function(data) {
                if (!keepExisting) {
                    $('#selected_staff').empty();
                }
                
                if (data && Array.isArray(data) && data.length > 0) {
                    // Add each staff member as an option (but don't select them)
                    data.forEach(function(staff) {
                        // Determine ID and prepare data for the option
                        const serviceNumber = staff.service_number || staff.id;
                        
                        // Skip if missing critical data
                        if (!serviceNumber || (!staff.last_name && !staff.text)) {
                            console.error('Staff record missing required data:', staff);
                            return;
                        }
                        
                        // Prepare properly formatted option with staff data
                        const displayText = serviceNumber + ' - ' + (staff.first_name || '') + ' ' + (staff.last_name || '') + 
                                          (staff.unit_name ? ' (' + staff.unit_name + ')' : '');
                        
                        // Check if already exists
                        if ($('#selected_staff option[value="' + serviceNumber + '"]').length === 0) {
                            const option = new Option(displayText, serviceNumber, false, false);
                            $('#selected_staff').append(option);
                        }
                    });
                    
                    // Update dropdown with message showing count - removed for production
                } else {
                    // Show message if no staff found
                    $('#search_debug').removeClass('d-none')
                        .html('<div class="alert alert-warning mt-2 p-2">No staff members found at this rank.</div>');
                        
                    // Let's run a check to see if there really are no staff at this rank in the database
                    $.ajax({
                        url: 'test_staff_loading.php',
                        data: { rank_id: rankId, check_only: true },
                        type: 'GET',
                        dataType: 'json',
                        success: function(checkData) {
                            if (checkData && checkData.staff_count > 0) {
                                // There ARE staff in the database, but our search isn't finding them
                                $('#search_debug').append(
                                    '<div class="alert alert-danger mt-2 p-2">' +
                                    'Database contains ' + checkData.staff_count + ' staff at this rank, but search API returned none. ' +
                                    'This indicates a search API issue.' +
                                    '</div>' +
                                    '<div class="mt-2"><a href="test_staff_loading.php?rank_id=' + rankId + '" ' +
                                    'target="_blank" class="btn btn-sm btn-primary">Run Diagnostic Test</a></div>'
                                );
                            }
                        }
                    });
                }
            },
            error: function(xhr, status, error) {
                console.error('Error loading staff for rank:', error);
                console.error('Response:', xhr.responseText);
                $('#selected_staff').empty().append(new Option('Error loading staff members', '', false, false));
                // Basic error reporting for troubleshooting
                if (xhr.status === 404) {
                    console.error('search_staff.php not found');
                } else if (xhr.status === 500) {
                    console.error('Server error - check PHP logs');
                }
            }
        });
    }
    
    // Load staff on page load if rank is selected (disabled - let user search manually)
    <?php if ($currentRankId): ?>
    $(document).ready(function() {
        // Show helpful message instead of auto-loading
        $('#search_debug').removeClass('d-none')
            .html('<div class="alert alert-info mt-2 p-2">📋 Rank pre-selected. Use the search bar above to find and select staff members for appointments.</div>');
    });
    <?php else: ?>
    console.log('No current rank selected - user will need to select rank first');
    <?php endif; ?>
    
    // Initial render if POSTed back
    <?php if (!empty($_POST['selected_staff'])): ?>
        renderStaffPanels(<?=json_encode($_POST['selected_staff'])?>);
        $('#selected_staff').val(<?=json_encode($_POST['selected_staff'])?>).trigger('change');
    <?php endif; ?>

    // Loading state management
    function showLoading(element, message = 'Loading...') {
        $(element).html(`<i class="fas fa-spinner fa-spin"></i> ${message}`).prop('disabled', true);
    }
    
    function hideLoading(element, originalText) {
        $(element).html(originalText).prop('disabled', false);
    }
    
    // Enhanced bulk operations with loading states
    $('#apply_bulk_unit').on('click', function() {
        let unitID = $('#bulk_unit').val();
        if (!unitID) {
            alert('Please select a unit first.');
            return;
        }
        
        showLoading(this, 'Applying...');
        
        setTimeout(() => {
            $('.staff-detail-card').each(function() {
                $(this).find('.unit-select').val(unitID).trigger('change');
            });
            hideLoading($('#apply_bulk_unit'), '<i class="fa fa-check"></i> Apply to All');
            
            // Show success feedback
            const toast = $(`
                <div class="toast position-fixed top-0 end-0 m-3" style="z-index: 9999;">
                    <div class="toast-header bg-success text-white">
                        <i class="fas fa-check-circle me-2"></i>
                        <strong class="me-auto">Success</strong>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="toast"></button>
                    </div>
                    <div class="toast-body">
                        Unit applied to all selected staff members.
                    </div>
                </div>
            `);
            $('body').append(toast);
            toast.toast({delay: 3000}).toast('show');
            toast.on('hidden.bs.toast', () => toast.remove());
        }, 300);
    });

    // Bulk position assignment with validation
    $('#apply_bulk_position').on('click', function() {
        let position = $('#bulk_position').val();
        if (!position) {
            alert('Please select a position first.');
            return;
        }
        
        showLoading(this, 'Applying...');
        
        setTimeout(() => {
            $('.staff-detail-card .position-select').val(position).trigger('change');
            hideLoading($('#apply_bulk_position'), '<i class="fa fa-check"></i> Apply to All');
            
            // Show success feedback
            const toast = $(`
                <div class="toast position-fixed top-0 end-0 m-3" style="z-index: 9999;">
                    <div class="toast-header bg-success text-white">
                        <i class="fas fa-check-circle me-2"></i>
                        <strong class="me-auto">Success</strong>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="toast"></button>
                    </div>
                    <div class="toast-body">
                        Position "${position}" applied to all selected staff members.
                    </div>
                </div>
            `);
            $('body').append(toast);
            toast.toast({delay: 3000}).toast('show');
            toast.on('hidden.bs.toast', () => toast.remove());
        }, 300);
    });

    // Bulk comment assignment
    $('#apply_bulk_comment').on('click', function() {
        let comment = $('#bulk_comment').val().trim();
        if (!comment) {
            alert('Please enter a comment first.');
            return;
        }
        
        showLoading(this, 'Applying...');
        
        setTimeout(() => {
            $('.staff-detail-card .comment-input').val(comment);
            hideLoading($('#apply_bulk_comment'), '<i class="fa fa-check"></i> Apply to All');
            
            // Show success feedback
            const toast = $(`
                <div class="toast position-fixed top-0 end-0 m-3" style="z-index: 9999;">
                    <div class="toast-header bg-success text-white">
                        <i class="fas fa-check-circle me-2"></i>
                        <strong class="me-auto">Success</strong>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="toast"></button>
                    </div>
                    <div class="toast-body">
                        Comment applied to all selected staff members.
                    </div>
                </div>
            `);
            $('body').append(toast);
            toast.toast({delay: 3000}).toast('show');
            toast.on('hidden.bs.toast', () => toast.remove());
        }, 300);
    });

    // Bulk unit assignment
    $('#apply_bulk_unit').on('click', function() {
        let unitID = $('#bulk_unit').val();
        if (!unitID) return;
        $('.staff-detail-card').each(function() {
            $(this).find('.unit-select').val(unitID).trigger('change');
        });
    });

    // Bulk comment assignment
    $('#apply_bulk_comment').on('click', function() {
        let comment = $('#bulk_comment').val();
        if (!comment) return;
        $('.staff-detail-card .comment-input').val(comment);
    });

    // View appointment history
    $(document).on('click', '.view-history-btn', function() {
        const serviceNumber = $(this).data('svcno');
        console.log('Loading history for:', serviceNumber);
        
        // Show modal with loading state
        const modal = new bootstrap.Modal(document.getElementById('appointmentHistoryModal'));
        $('#appointmentHistoryContent').html(`
            <div class="text-center py-4">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
                <p class="mt-2">Loading appointment history...</p>
            </div>
        `);
        modal.show();
        
        // Load history via AJAX
        $.ajax({
            url: 'ajax_get_appointment_history.php',
            method: 'POST',
            data: { service_number: serviceNumber },
            success: function(response) {
                $('#appointmentHistoryContent').html(response);
            },
            error: function(xhr, status, error) {
                console.error('Error loading history:', error);
                $('#appointmentHistoryContent').html(`
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-triangle"></i> 
                        Error loading appointment history. Please try again.
                    </div>
                `);
            }
        });
    });

    // Form validation before submission
    $('#appointmentForm').on('submit', function(e) {
        let isValid = true;
        let errors = [];
        
        // Clear previous validation states
        $('.is-invalid').removeClass('is-invalid');
        $('.invalid-feedback').remove();
        
        // Validate staff selection
        const selectedStaff = $('.staff-checkbox:checked');
        if (selectedStaff.length === 0) {
            isValid = false;
            errors.push('Please select at least one staff member.');
            $('#staffTableContainer').addClass('border border-danger');
        } else {
            $('#staffTableContainer').removeClass('border border-danger');
        }
        
        // Validate appointment type
        const appointmentType = $('#appointment_type').val();
        if (!appointmentType) {
            isValid = false;
            errors.push('Please select an appointment type.');
            $('#appointment_type').addClass('is-invalid');
        }
        
        // Validate appointment date
        const apptDate = $('#appt_date').val();
        if (!apptDate) {
            isValid = false;
            errors.push('Please select an appointment date.');
            $('#appt_date').addClass('is-invalid');
        } else {
            // Check if date is in the past
            const today = new Date();
            today.setHours(0, 0, 0, 0);
            const selectedDate = new Date(apptDate);
            
            if (selectedDate < today) {
                isValid = false;
                errors.push('Appointment date cannot be in the past.');
                $('#appt_date').addClass('is-invalid');
            }
        }
        
        // Validate end date if provided (optional for temporary appointments)
        const selectedOption = $('#appointment_type').find(':selected');
        const isTemporary = selectedOption.data('is-temporary') == 1;
        const endDate = $('#end_date').val();
        
        // Only validate if end date is provided
        if (endDate && apptDate) {
            const apptDateTime = new Date(apptDate);
            const endDateTime = new Date(endDate);
            
            if (endDateTime <= apptDateTime) {
                isValid = false;
                errors.push('End date must be after the appointment date.');
                $('#end_date').addClass('is-invalid');
            }
        }
        // Note: End date is optional but recommended for temporary appointments
        
        // Validate unit selection for each staff member
        let missingUnits = [];
        $('.staff-detail-card').each(function() {
            const svcNo = $(this).data('svcno');
            const unitSelect = $(this).find('.unit-select');
            
            if (!unitSelect.val()) {
                isValid = false;
                missingUnits.push(svcNo);
                unitSelect.addClass('is-invalid');
            }
        });
        
        if (missingUnits.length > 0) {
            errors.push(`Please select units for staff members: ${missingUnits.join(', ')}`);
        }
        
        // Show validation errors
        if (!isValid) {
            e.preventDefault();
            
            // Show error summary
            let errorHtml = '<div class="alert alert-danger alert-dismissible fade show mt-3" role="alert">';
            errorHtml += '<h6><i class="fas fa-exclamation-triangle"></i> Please correct the following errors:</h6>';
            errorHtml += '<ul class="mb-0">';
            errors.forEach(error => {
                errorHtml += `<li>${error}</li>`;
            });
            errorHtml += '</ul>';
            errorHtml += '<button type="button" class="btn-close" data-bs-dismiss="alert"></button>';
            errorHtml += '</div>';
            
            // Remove existing validation alerts and add new one
            $('.alert-danger').remove();
            $('#appointmentForm').before(errorHtml);
            
            // Scroll to first error
            $('html, body').animate({
                scrollTop: $('.alert-danger').offset().top - 100
            }, 500);
            
            return false;
        }
        
        // Show loading state
        $('#submitBtn').prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Processing...');
        
        return true;
    });

    // Auto-submit rank form on change (but don't auto-load staff)
    $('#current_rank').on('change', function() {
        const rankId = $(this).val();
        if (rankId) {
            // Show message that rank is selected
            $('#search_debug').removeClass('d-none')
                .html('<div class="alert alert-info mt-2 p-2">� Rank selected. Use the search bar above to find and select staff members.</div>');
            
            // If we're already in Step 2 (staff selection visible), just update the rank
            if ($('#selected_staff').length > 0 && $('#selected_staff').is(':visible') && $('input[name="current_rank"]').length > 0) {
                console.log('Updating rank for existing staff selection interface:', rankId);
                
                // Update the hidden rank input for Step 2
                $('input[name="current_rank"]').val(rankId);
                
                // Clear any existing selections to prevent confusion
                $('#selected_staff').val(null).trigger('change');
            } else {
                // Submit the form to reload with the selected rank (Step 1 → Step 2)
                $('#rankForm').submit();
            }
        } else {
            // Clear staff dropdown if no rank selected
            $('#selected_staff').empty();
            $('#search_debug').addClass('d-none');
        }
    });
    
    // Handle appointment type changes
    $('#appointment_type').on('change', function() {
        // Always show end_date container since all appointments get 3-year default
        $('#end_date_container').show();
        
        // Calculate default end date (3 years from appointment date)
        const startDate = $('#appt_date').val();
        if (startDate && !$('#end_date').val()) {
            const endDate = new Date(startDate);
            endDate.setFullYear(endDate.getFullYear() + 3); // 3 years default
            $('#end_date').val(endDate.toISOString().split('T')[0]);
        }
    });
    
    // Update end date when appointment date changes (3 years default)
    $('#appt_date').on('change', function() {
        const startDate = $(this).val();
        if (startDate && !$('#end_date').val()) {
            const endDate = new Date(startDate);
            endDate.setFullYear(endDate.getFullYear() + 3); // 3 years default
            $('#end_date').val(endDate.toISOString().split('T')[0]);
        }
    });
    
    // Test search directly - removed for production
    $('#test_search_link').on('click', function() {
        const rankId = getCurrentRankId();
        if (!rankId) {
            alert('Please select a rank first');
            return;
        }
        window.open('search_staff.php?test=1&q=test&rank_id=' + rankId, '_blank');
    });
    
    // Load all staff for the selected rank
    $('#loadAllStaffBtn').on('click', function() {
        const rankId = getCurrentRankId();
        if (!rankId) {
            alert('Please select a rank first');
            return;
        }
        
        // Show loading indicator
        $('#search_debug').removeClass('d-none')
            .html('<div class="alert alert-info mt-2 p-2">Loading all staff with rank ID: ' + rankId + '</div>');
        
        // Create a direct AJAX call to get all staff with this rank
        $.ajax({
            url: 'search_staff_fixed.php',
            data: { 
                rank_id: rankId,
                q: 'all' // Using a value to ensure it passes any checks
            },
            type: 'GET',
            dataType: 'json',
            success: function(data) {
                console.log('All staff for rank:', data);
                
                if (data && Array.isArray(data) && data.length > 0) {
                    // Clear existing selections
                    $('#selected_staff').empty();
                    
                    // Add each staff member as an option and select it
                    data.forEach(function(staff) {
                        // Use consistent data structure - search_staff.php returns service_number as id
                        const staffId = staff.service_number || staff.id;
                        const staffText = staff.text || (staffId + ' - ' + (staff.first_name || '') + ' ' + (staff.last_name || ''));
                        const option = new Option(staffText, staffId, true, true);
                        $('#selected_staff').append(option);
                    });
                    
                    // Trigger change to update the UI
                    $('#selected_staff').trigger('change');
                    
                    // Success message - removed for production
                } else {
                    $('#search_debug').append('<div class="alert alert-warning mt-2 p-2">No staff found with the selected rank</div>');
                }
            },
            error: function(xhr, status, error) {
                console.error('Error loading all staff:', error);
                $('#search_debug').append('<div class="alert alert-danger mt-2 p-2">Error loading staff: ' + error + '</div>');
            }
        });
    });
});
</script>
            </div>
        </div>
    </div>
</div>

<!-- Appointment History Modal -->
<div class="modal fade" id="appointmentHistoryModal" tabindex="-1" aria-labelledby="appointmentHistoryModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title" id="appointmentHistoryModalLabel">
                    <i class="fas fa-history"></i> Appointment History
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="appointmentHistoryContent">
                <div class="text-center">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<?php include dirname(__DIR__) . '/shared/footer.php'; ?>