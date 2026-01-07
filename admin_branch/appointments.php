<?php
// Always initialize selectedStaff to an array to avoid null warnings
$selectedStaff = [];
// Define module constants
define('ARMIS_ADMIN_BRANCH', true);
define('ARMIS_DEVELOPMENT', true);

// Configure minimal error logging for this page
ini_set('log_errors', 1);
ini_set('error_log', dirname(__DIR__) . '/logs/appointments_errors.log');
error_reporting(E_ALL);

// Include admin branch authentication and database
try {
    require_once __DIR__ . '/includes/auth.php';
    require_once dirname(__DIR__) . '/shared/database_connection.php';
} catch (Exception $e) {
    // Critical failure - stop here
    die("Critical Error: Unable to load required files - " . htmlspecialchars($e->getMessage()));
}

// Require authentication
try {
    requireAuth();
} catch (Exception $e) {
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
    $appointmentTypesStmt = $pdo->query("SELECT id, id as type_name, '' as description, 0 as is_temporary, 36 as default_duration_months FROM appointment_type ORDER BY id");
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

// Initialize arrays to prevent undefined variable errors
$ranks = [];
$units = [];
$rankCounts = [];

try {
    // Fetch dropdown data (ranks and units) with short session cache
    $cache_key = 'ranks_data';
    $cache_timeout = 300; // 5 minutes

    if (isset($_SESSION['dropdown_cache'][$cache_key]) && 
        time() - $_SESSION['dropdown_cache'][$cache_key]['timestamp'] < $cache_timeout) {
        $ranks = $_SESSION['dropdown_cache'][$cache_key]['data'];
    } else {
        $ranksStmt = $pdo->query("SELECT rankId as rankID, rankId as rankName, rankId as rankAbbr, level as rankIndex FROM `rank` ORDER BY level ASC");
        $ranks = $ranksStmt->fetchAll(PDO::FETCH_OBJ);
        $_SESSION['dropdown_cache'][$cache_key] = [
            'data' => $ranks,
            'timestamp' => time()
        ];
    }

    $cache_key = 'units_data';
    if (isset($_SESSION['dropdown_cache'][$cache_key]) && 
        time() - $_SESSION['dropdown_cache'][$cache_key]['timestamp'] < $cache_timeout) {
        $units = $_SESSION['dropdown_cache'][$cache_key]['data'];
    } else {
        $unitsStmt = $pdo->query("SELECT unitId as unitID, code as unitName, location FROM `unit` ORDER BY code ASC");
        $units = $unitsStmt->fetchAll(PDO::FETCH_OBJ);
        $_SESSION['dropdown_cache'][$cache_key] = [
            'data' => $units,
            'timestamp' => time()
        ];
    }

    // Count total staff at each rank for display
    $rankCountStmt = $pdo->query("SELECT rankId, COUNT(*) as count FROM staff WHERE svcStatus = 'Active' GROUP BY rankId");
    while ($row = $rankCountStmt->fetch(PDO::FETCH_ASSOC)) {
        $rankCounts[$row['rankId']] = $row['count'];
    }

} catch (Exception $e) {
    $errors[] = "Error fetching ranks or units: " . htmlspecialchars($e->getMessage());
    error_log("Error in appointments.php fetching ranks: " . $e->getMessage());
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
$currentRankId = $_POST['currentRank'] ?? $_GET['currentRank'] ?? '';
$currentRank = null;
    if ($currentRankId) {
    // Current rank may be a string rankId; use `rank` table
    $stmt = $pdo->prepare("SELECT rankId as id, rankId as name, rankId as abbreviation, level FROM `rank` WHERE rankId = ? LIMIT 1");
    $stmt->execute([$currentRankId]);
    $currentRank = $stmt->fetch(PDO::FETCH_OBJ);
    
    // Fetch staff at the selected rank
    if ($currentRank) {
        try {
            $staffStmt = $pdo->prepare("
                SELECT s.svcNo, s.fName, s.lName, s.rankId, 
                       s.attestDate, s.unitId, s.subWef, s.tempWef, s.DOB as dateOfBirth,
                       s.corpsId as corps, s.svcStatus as status, s.apptId as appt,
                       u.code as unit_name, r.level, r.rankId as rank_name, r.rankId as rank_abbr,
                       -- Use the staff's substantive/temporal/attestation dates as best-effort rank_date
                       COALESCE(s.subWef, s.tempWef, s.attestDate, '1900-01-01') as rank_date
                FROM staff s
                LEFT JOIN unit u ON s.unitId = u.unitId
                LEFT JOIN `rank` r ON s.rankId = r.rankId
                WHERE s.rankId = ? AND s.svcStatus = 'Active'
                ORDER BY rank_date ASC, s.svcNo ASC
            ");
            $staffStmt->execute([$currentRankId]);
            $eligibleStaff = $staffStmt->fetchAll(PDO::FETCH_OBJ);
            
            // Debug: Log query results
            error_log("APPOINTMENTS DEBUG: Rank=$currentRankId, Staff found=" . count($eligibleStaff));

            // Normalize eligibleStaff into an array of associative arrays with stable keys
            $normalized = [];
            foreach ($eligibleStaff as $s) {
                // convert object to array safely
                $svc = [];
                $svc['svcNo'] = (string)($s->svcNo ?? $s->serviceNumber ?? '');
                $svc['fName'] = (string)($s->fName ?? $s->firstName ?? '');
                $svc['lName'] = (string)($s->lName ?? $s->lastName ?? '');
                $svc['unitId'] = (string)($s->unitId ?? $s->unitID ?? '');
                $svc['unit_name'] = $s->unit_name ?? $s->code ?? null;
                $svc['corps'] = $s->corps ?? null;
                $svc['status'] = $s->status ?? $s->svcStatus ?? 'Active';
                $svc['appt'] = $s->appt ?? '';
                $svc['rankId'] = $s->rankId ?? '';
                $svc['rank_name'] = $s->rank_name ?? $s->rankId ?? '';
                $svc['rank_abbr'] = $s->rank_abbr ?? $s->rank_name ?? '';
                $normalized[] = $svc;
            }
            $eligibleStaff = $normalized;

            // If no eligible staff were returned (edge cases), try a fallback query
            // Quiet: removed temporary debug logging used during troubleshooting

            if (empty($eligibleStaff) && !empty($currentRankId)) {
                try {
                    $fallbackSql = "SELECT s.svcNo, s.fName, s.lName, s.rankId, r.rankId as rank_name, r.rankId as rank_abbr, u.code as unit_name, s.corpsId as corps, s.svcStatus as status, s.apptId as appt FROM staff s LEFT JOIN `rank` r ON s.rankId = r.rankId LEFT JOIN `unit` u ON s.unitId = u.unitId WHERE s.rankId = ? AND s.svcStatus = 'Active' ORDER BY COALESCE(s.subWef, s.tempWef, s.attestDate, '1900-01-01') ASC, s.svcNo ASC";
                    $fbStmt = $pdo->prepare($fallbackSql);
                    $fbStmt->execute([$currentRankId]);
                    $fbRows = $fbStmt->fetchAll(PDO::FETCH_ASSOC);
                    if (!empty($fbRows)) {
                        // normalize associative rows to expected keys
                        $eligibleStaff = array_map(function($r){
                            return [
                                'svcNo' => (string)($r['svcNo'] ?? ''),
                                'fName' => (string)($r['fName'] ?? ''),
                                'lName' => (string)($r['lName'] ?? ''),
                                'unitId' => (string)($r['unitId'] ?? $r['unitID'] ?? ''),
                                'unit_name' => $r['unit_name'] ?? $r['code'] ?? null,
                                'corps' => $r['corps'] ?? null,
                                'status' => $r['status'] ?? $r['svcStatus'] ?? 'Active',
                                'appt' => $r['appt'] ?? '',
                                'rankId' => $r['rankId'] ?? '',
                                'rank_name' => $r['rank_name'] ?? $r['rankId'] ?? '',
                                'rank_abbr' => $r['rank_abbr'] ?? $r['rank_name'] ?? ''
                            ];
                        }, $fbRows);
                    }
                } catch (Exception $e) {
                    error_log('APPOINTMENTS: Fallback staff fetch failed: ' . $e->getMessage());
                }
            }
            
            // Found eligible staff for selected rank
        } catch (Exception $e) {
            error_log("APPOINTMENTS: Error fetching staff - " . $e->getMessage());
            $errors[] = "Error loading staff data: " . htmlspecialchars($e->getMessage());
        }
    }
}

// If we still have no eligibleStaff but a rank id was provided, attempt a safe fallback
// outside the $currentRank conditional. This covers cases where the rank lookup
// didn't return a row but staff records exist (some environments have staff.rankId
// values not present in the `rank` table). This mirrors the AJAX endpoint logic.
if (empty($eligibleStaff) && !empty($currentRankId)) {
    try {
        $fallbackSql = "SELECT s.svcNo, s.fName, s.lName, s.rankId, COALESCE(r.rankId, r.rankId) as rank_name, r.rankId as rank_abbr, u.code as unit_name, s.corps, s.svcStatus as status, s.appt FROM staff s LEFT JOIN `rank` r ON s.rankId = r.rankId LEFT JOIN `unit` u ON s.unitId = u.unitId WHERE s.rankId = ? AND s.svcStatus = 'Active' ORDER BY COALESCE(s.subWef, s.tempWef, s.attestDate, '1900-01-01') ASC, s.svcNo ASC";
        $fbStmt = $pdo->prepare($fallbackSql);
        $fbStmt->execute([$currentRankId]);
        $fbRows = $fbStmt->fetchAll(PDO::FETCH_ASSOC);
            if (!empty($fbRows)) {
            $eligibleStaff = array_map(function($r){
                return [
                    'svcNo' => (string)($r['svcNo'] ?? ''),
                    'fName' => (string)($r['fName'] ?? ''),
                    'lName' => (string)($r['lName'] ?? ''),
                    'unitId' => (string)($r['unitId'] ?? $r['unitID'] ?? ''),
                    'unit_name' => $r['unit_name'] ?? $r['code'] ?? null,
                    'corps' => $r['corps'] ?? null,
                    'status' => $r['status'] ?? $r['svcStatus'] ?? 'Active',
                    'appt' => $r['appt'] ?? $r['appointment'] ?? '',
                    'rankId' => $r['rankId'] ?? '',
                    'rank_name' => $r['rank_name'] ?? $r['rankId'] ?? '',
                    'rank_abbr' => $r['rank_abbr'] ?? $r['rank_name'] ?? ''
                ];
            }, $fbRows);
            
            // Fallback populated eligibleStaff (no debug logging)
        } else {
            // no rows from fallback query for this rank
        }
    } catch (Exception $e) {
        error_log('APPOINTMENTS: Fallback staff fetch failed (outer): ' . $e->getMessage());
    }
}

// Step 2: Handle form submission for appointments
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['appoint_staff'])) {
    error_log("APPOINTMENTS: Form submission received");

    // Always initialize POST variables to arrays/values to avoid null warnings
    $selectedStaff = $_POST['selected_staff'] ?? [];
    $appointmentTypeId = $_POST['appointment_type'] ?? '';
    $apptDate = $_POST['appt_date'] ?? '';
    $endDate = null; // Will be set dynamically below
    $requiresApproval = isset($_POST['requires_approval']);
    $unitsSelected = $_POST['unit'] ?? [];
    $positions = $_POST['position'] ?? [];
    $withPowersOf = $_POST['with_powers_of'] ?? [];
    $comments = $_POST['comment'] ?? [];
    $createdBy = $_SESSION['user_id'] ?? 0;
    $dateCreated = date('Y-m-d H:i:s');
    $status = $requiresApproval ? 'pending' : 'approved';

    // CSRF Protection
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== ($_SESSION['csrf_token'] ?? '')) {
        error_log("APPOINTMENTS: CSRF token validation failed");
        $errors[] = "Invalid security token. Please refresh the page and try again.";
    }

    if (empty($errors)) {
        error_log("APPOINTMENTS: Processing form data");

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
        // ...existing code for processing appointments...
    } // End if (empty($errors))
    if (empty($appointmentTypeId)) {
        $errors[] = "Please select an appointment type.";
    }
    // Validate appointment type exists in database
    if (!empty($appointmentTypeId)) {
        $typeStmt = $pdo->prepare("SELECT id FROM appointment_type WHERE id = ?");
        $typeStmt->execute([$appointmentTypeId]);
        $typeInfo = $typeStmt->fetch(PDO::FETCH_ASSOC);
        if (!$typeInfo) {
            $errors[] = "Invalid appointment type selected.";
        } else {
            $isTemporary = 0; // Default to non-temporary
            // Only set end date for acting/secondment (temporary) appointments
            if ($isTemporary && !empty($apptDate)) {
                // Use user-supplied end date if present, else default to 3 years
                $endDate = $_POST['endDate'] ?? null;
                if (empty($endDate)) {
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
            } else {
                $endDate = null; // Permanent appointment
            }
        }
    }
} // End if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['appoint_staff']))

    // Validate each selected staff member and their units
    // Build a list of valid unit IDs from the fetched $units (unitId values)
    $validUnitIDs = [];
    foreach ($units as $u) {
        $validUnitIDs[] = (string)($u->unitID ?? $u->unitId ?? '');
    }

    foreach ($selectedStaff as $svcNo) {
        $unitId = trim($unitsSelected[$svcNo] ?? '');
        if (empty($unitId)) {
            $errors[] = "Please select a unit for staff member " . htmlspecialchars($svcNo) . ".";
        } elseif (!in_array($unitId, $validUnitIDs, true)) {
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
                // Fetch ALL active appointments for each staff member
                                $duplicateCheckStmt = $pdo->prepare("
                            SELECT sa.svcNo, sa.apptId as appointment, sa.apptWef as appointment_date, 
                                    sa.endDate, u.code as unit_name, sa.id as appt_record_id,
                                    sa.apptType as appointment_type,
                                    0 as is_temporary
                                        FROM staff_appointment sa
                                        LEFT JOIN `unit` u ON sa.unitId = u.unitId
                                        WHERE sa.svcNo = ? 
                                        AND (
                                                sa.endDate IS NULL 
                                                OR sa.endDate >= CURDATE()
                                        )
                                        ORDER BY sa.apptWef DESC
                                ");
                
                foreach ($selectedStaff as $svcNo) {
                    $duplicateCheckStmt->execute([$svcNo]);
                    $existingAppts = $duplicateCheckStmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    if (!empty($existingAppts)) {
                        // Store all existing appointments for this staff member
                        $existingAppointments[$svcNo] = $existingAppts;
                        
                        // Check business rule: validate appointment type conflicts
                        // Get the new appointment type being created
                        $newTypeStmt = $pdo->prepare("SELECT id FROM appointment_type WHERE id = ?");
                        $newTypeStmt->execute([$appointmentTypeId]);
                        $newTypeInfo = $newTypeStmt->fetch(PDO::FETCH_ASSOC);
                        $newIsTemporary = 0; // Default to non-temporary
                        
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
        $selectStaffStmt = $pdo->prepare("SELECT svcNo, rankId FROM staff WHERE svcNo = ? LIMIT 1");
        $insertApptStmt = $pdo->prepare("INSERT INTO staff_appointment (
            svcNo, 
            apptId, 
            apptType, 
            unitId, 
            apptWef,
            powers,
            endDate, 
            durationMonths,
            authorityId,
            remarks,
            createdBy, 
            createdAt
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $updateStaffStmt = $pdo->prepare("UPDATE staff SET unitId = ? WHERE svcNo = ?");
        $endApptStmt = $pdo->prepare("UPDATE staff_appointment SET endDate = ?, remarks = CONCAT(COALESCE(remarks, ''), ?) WHERE id = ?");
        
        // Use SINGLE transaction for all appointments (performance optimization)
        try {
            $pdo->beginTransaction();
            $appointmentsCreated = 0;
            $appointmentsEnded = 0;
            
            // Process each staff member
            foreach ($selectedStaff as $serviceNumber) {
                $unitId = htmlspecialchars(trim($unitsSelected[$serviceNumber]));
                $position = htmlspecialchars(trim($positions[$serviceNumber] ?? ''));
                $powers = htmlspecialchars(trim($withPowersOf[$serviceNumber] ?? ''));
                $comment = htmlspecialchars(trim($comments[$serviceNumber] ?? ''));
                // Duplicate appointment check: prevent same person, position, and unit if active
                $duplicateCheckStmt = $pdo->prepare("SELECT COUNT(*) FROM staff_appointment WHERE svcNo = ? AND apptId = ? AND unitId = ? AND (endDate IS NULL OR endDate > ?)" );
                $duplicateCheckStmt->execute([$serviceNumber, $position, $unitId, date('Y-m-d')]);
                $duplicateCount = $duplicateCheckStmt->fetchColumn();
                if ($duplicateCount > 0) {
                    $errors[] = "Cannot appoint {$serviceNumber} to '{$position}' in this unit: already holding this appointment.";
                    continue;
                }

                // Get staff details for validation
                $selectStaffStmt->execute([$serviceNumber]);
                $staff = $selectStaffStmt->fetch(PDO::FETCH_OBJ);
                $staffId = $staff ? $staff->svcNo : null;
                
                if (!$staffId) {
                    throw new Exception("Staff member with service number {$serviceNumber} not found");
                }
                
                // ALWAYS end previous appointments automatically (no checkbox needed)
                // Set end date to day before new appointment starts
                if (isset($existingAppointments[$serviceNumber])) {
                    $existingAppts = $existingAppointments[$serviceNumber];
                    $newEndDate = date('Y-m-d', strtotime($apptDate . ' -1 day'));
                $endComment = " | Ended automatically for new appointment on " . date('d-M-Y');
                    
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
                            'timestamp' => date('d-M-Y H:i:s'),
                            'user_id' => $createdBy,
                            'svcNo' => $serviceNumber,
                            'old_appointment_position' => $existingAppt['appointment'],
                            'old_unit' => $existingAppt['unit_name'],
                            'old_type_id' => $existingAppt['appointment_type'],
                            'endDate' => $newEndDate,
                            'reason' => 'Automatic end for new appointment',
                            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
                        ]));
                    }
                }
                
                // Insert new appointment record
                // NOTE: apptId now stores the position/role
                $appointmentPosition = $position ?: 'Not Specified';
                $remarks = $comment; // Additional comments go to remarks field
                
                // Calculate duration in months if endDate is set
                $durationMonths = null;
                if ($endDate) {
                    $start = new DateTime($apptDate);
                    $end = new DateTime($endDate);
                    $interval = $start->diff($end);
                    $durationMonths = ($interval->y * 12) + $interval->m;
                }
                
                $insertApptStmt->execute([
                    $serviceNumber,            // svcNo
                    $appointmentPosition,      // apptId = position/role
                    $appointmentTypeId,        // apptType = FK to appointment_type table
                    $unitId,                   // unitId
                    $apptDate,                 // apptWef - appointment date
                    $powers,                   // powers field
                    $endDate,                  // endDate (3 years from appointment if not specified)
                    $durationMonths,           // durationMonths - calculated
                    null,                      // authorityId - can be added later if needed
                    $remarks,                  // remarks = user comments
                    $createdBy,                // createdBy
                    $dateCreated               // createdAt
                ]);
                
                // Update staff unit for the appointment
                $updateStaffStmt->execute([$unitId, $serviceNumber]);
                
                // Update staff apptId column with the current appointment position
                $updateApptStmt = $pdo->prepare("UPDATE staff SET apptId = ? WHERE svcNo = ?");
                $updateApptStmt->execute([$appointmentPosition, $serviceNumber]);
                
                $appointmentsCreated++;
                
                // Enhanced audit log for new appointment
                error_log(json_encode([
                    'action' => 'appointment_created',
                    'timestamp' => $dateCreated,
                    'user_id' => $createdBy,
                    'svcNo' => $serviceNumber,
                    'appointment_position' => $appointmentPosition,
                    'unitId' => $unitId,
                    'appointment_type' => $appointmentTypeId,
                    'apptWef' => $apptDate,
                    'endDate' => $endDate,
                    'durationMonths' => $durationMonths,
                    'powers' => $powers,
                    'remarks' => $remarks,
                    'previous_ended' => isset($existingAppointments[$serviceNumber]),
                    'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
                ]));
            }
            
            // Commit all changes at once
            $pdo->commit();
            $success = true;
            
            // Clear session data
            unset($_SESSION['existing_appointments']);
            
            // Build success message only if at least one appointment was created
            if ($appointmentsCreated > 0) {
                $successMessage = "Successfully created {$appointmentsCreated} appointment(s)";
                if ($appointmentsEnded > 0) {
                    $successMessage .= " and automatically ended {$appointmentsEnded} previous appointment(s)";
                }
                $successMessage .= ". " . ($requiresApproval ? "Appointments submitted for approval." : "");
            } else {
                $successMessage = '';
            }
            
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

include dirname(__DIR__) . '/shared/header.php';
include dirname(__DIR__) . '/shared/sidebar.php';
?>

<!-- DataTables CSS -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/datatables.net-bs5@1.13.6/css/dataTables.bootstrap5.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/datatables.net-responsive-bs5@2.5.0/css/responsive.bootstrap5.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/datatables.net-select-bs5@1.6.3/css/select.bootstrap5.min.css">

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
            <?php if (!empty($successMessage)): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i> <?= $successMessage ?>
                    <?php if (!empty($selectedStaff) && $appointmentsCreated > 0) : 
                        // Get the most recently appointed staff member (last in array)
                        $recentSvcNo = end($selectedStaff);
                    ?>
                    <button type="button" class="btn btn-outline-info btn-sm ms-3" id="viewAppointmentSummaryBtn" data-svcno="<?= htmlspecialchars($recentSvcNo) ?>">
                        <i class="fas fa-list"></i> View Appointment Summary
                    </button>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            <!-- Appointment Summary Modal -->
            <div class="modal fade" id="appointmentSummaryModal" tabindex="-1" aria-labelledby="appointmentSummaryModalLabel" aria-hidden="true">
                <div class="modal-dialog modal-lg modal-dialog-centered">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="appointmentSummaryModalLabel"><i class="fas fa-list"></i> Appointment Summary</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body" id="appointmentSummaryContent">
                            <div class="text-center text-muted"><i class="fas fa-spinner fa-spin"></i> Loading...</div>
                        </div>
                    </div>
                </div>
            </div>
            <?php if (!empty($errors)): ?>
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
                        <select name="currentRank" id="currentRank" class="form-select" required onchange="this.form.submit()">
                            <option value="">Select Current Rank...</option>
                            <?php foreach ($ranks as $r):
                                // Skip excluded ranks (Officer Cadet and Recruit)
                                if (in_array($r->rankName, $excludedRanks)) continue;
                                
                                // Get count of staff at this rank
                                $staffCount = isset($rankCounts[$r->rankID]) ? $rankCounts[$r->rankID] : 0;
                                ?>
                                <option value="<?=$r->rankID?>" <?=($currentRankId==$r->rankID)?'selected':''?>>
                                    <?=$r->rankAbbr ? $r->rankAbbr : $r->rankName?> (<?=$staffCount?> Personnel)
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

            <script>
                <!-- Rank auto-submit moved to appointments.init.js -->
            </script>

            <!-- Step 2: Multi-Select + Panel -->
            <?php if ($currentRankId): ?>
            <form method="post" action="" id="appointmentForm">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                <input type="hidden" name="currentRank" value="<?=htmlspecialchars($currentRankId)?>">
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
                                <strong>Rank Selected:</strong> <?= htmlspecialchars($currentRank->abbreviation ?? $currentRank->name ?? '') ?>
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
                                    <th class="select-checkbox" style="width: 40px;"></th>
                                    <th>Service No.</th>
                                    <th>Rank</th>
                                    <th>Name</th>
                                    <th>Unit</th>
                                    <th>Corps</th>
                                    <th>Current Appointment</th>
                                    <th>Status</th>
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
                        <label class="form-label">Effective From *</label>
                        <input type="date" name="appt_date" id="appt_date" class="form-control" required value="<?=htmlspecialchars($_POST['appt_date'] ?? date('Y-m-d'))?>">
                    </div>
                    <div class="col-md-3 mb-2" id="endDateField" style="display:none;">
                        <label class="form-label">End Date</label>
                        <input type="date" name="endDate" id="endDate" class="form-control" value="<?=htmlspecialchars($_POST['endDate'] ?? '')?>">
                        <small class="text-muted">Default: 3 years from appointment date</small>
                    </div>
                    <div class="col-md-3 mb-2">
                        <!-- Automatic ending - no checkbox needed -->
                    </div>
                    <!-- Appointment type end-date toggle moved to appointments.init.js -->
                </div>
                
                <?php if (!empty($_SESSION['existing_appointments'])): ?>
                <div class="alert alert-info mb-3">
                    <h6 class="alert-heading"><i class="fas fa-info-circle"></i> Active Appointments Will Be Automatically Ended</h6>
                    <p class="mb-2">The following staff members have existing active appointments that will be automatically ended one day before the new appointment date:</p>
                    <ul class="mb-2">
                    <?php foreach ($_SESSION['existing_appointments'] as $svcNo => $appts): ?>
                        <?php 
                        // Handle both single appointment (legacy) and multiple appointments (new)
                        $appointmentList = isset($appts['svcNo']) ? [$appts] : $appts;
                        foreach ($appointmentList as $appt): 
                        ?>
                        <li>
                            <strong><?= htmlspecialchars($svcNo) ?></strong>: 
                            <?= htmlspecialchars($appt['appointment'] ?? 'Unknown Position') ?> 
                            at <?= htmlspecialchars($appt['unit_name'] ?? 'Unknown Unit') ?>
                            <span class="badge bg-secondary">Type: <?= htmlspecialchars($appt['appointment_type'] ?? 'N/A') ?></span>
                            <?php if ($appt['endDate']): ?>
                                (current end date: <?= date('d-M-Y', strtotime($appt['endDate'])) ?>)
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

<!-- Core JS (jQuery/Bootstrap) are loaded centrally in shared/footer.php. Page-specific scripts continue below. -->
<!-- Expose server-side data for the appointments page as a safe JSON blob, then load the external initializer (avoids PHP-in-JS parsing issues) -->
<?php
// TEMP DEBUG: log what we are about to emit for client-side consumption
// Removed diagnostic emission logs

?>
<script>
// Emit a single JSON-encoded object to ensure valid JSON is produced for diagnostics and client consumption
window.appointmentsServerData = <?= json_encode([
    'unitsData' => $units,
    'eligibleStaff' => $eligibleStaff ?? [],
    'currentRankId' => $currentRankId ?? '',
    'standardPositions' => $standardPositions ?? [],
    'preselectedStaff' => $_POST['selected_staff'] ?? []
], JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) ?>;
</script>
</script>
            </div>
        </div>
    </div>
</div>

<?php include dirname(__DIR__) . '/shared/footer.php'; ?>
<script src="/Armis2/admin_branch/appointments.init.js?v=<?= time() ?>"></script>