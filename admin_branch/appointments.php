<?php
// Always initialize state variables up front so they exist before any
// try/catch block below can reference them (fixes "Undefined variable" warnings).
$selectedStaff = [];
$errors = [];
$success = false;
$successMessage = '';
$eligibleStaff = [];
$appointmentsCreated = 0;
$appointmentsEnded = 0;
$appointedStaffRows = []; // structured data for the post-submit summary table

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
$sidebarLinks = []; // set by shared nav include below
require_once __DIR__ . '/includes/sidebar_nav.php';

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

// Fetch appointment types from the (now-existing) appointment_type table.
// NOTE: run the armis1_schema_fixes.sql migration once before this will work;
// that migration creates appointment_type and seeds default rows.
try {
    $appointmentTypesStmt = $pdo->query(
        "SELECT id, type_name, description, is_temporary, default_duration_months
         FROM appointment_type
         ORDER BY type_name"
    );
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

$rankCounts = [];

try {
    // Fetch dropdown data (ranks and units) with short session cache
    $cache_key = 'ranks_data';
    $cache_timeout = 300; // 5 minutes

    if (isset($_SESSION['dropdown_cache'][$cache_key]) &&
        time() - $_SESSION['dropdown_cache'][$cache_key]['timestamp'] < $cache_timeout) {
        $ranks = $_SESSION['dropdown_cache'][$cache_key]['data'];
    } else {
        // FIX: `rank` has no `level` column - the real column is `rankIndex`.
        $ranksStmt = $pdo->query("SELECT rankId as rankID, rankId as rankName, rankId as rankAbbr, rankIndex FROM `rank` ORDER BY rankIndex ASC");
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
        // FIX: `unit` has no `code` or `location` column - the real columns
        // are `unitId` (used as the human-readable code/name) and `unitLoc`.
        $unitsStmt = $pdo->query("SELECT unitId as unitID, unitId as unitName, unitLoc as location FROM `unit` ORDER BY unitId ASC");
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

// Exclude Officer Cadet, Recruit, and CE ranks (Mister, Miss).
// (A previously-computed but unused $excludedRankIds lookup was removed here -
// the dropdown below already filters by name against $excludedRanks directly.)
$excludedRanks = ['Officer Cadet', 'Recruit', 'Mister', 'Miss'];

// Step 1: Select current rank
$currentRankId = $_POST['currentRank'] ?? $_GET['currentRank'] ?? '';
$currentRank = null;

if ($currentRankId) {
    // FIX: `rank` has no `level` column - use `rankIndex`.
    $stmt = $pdo->prepare("SELECT rankId as id, rankId as name, rankId as abbreviation, rankIndex FROM `rank` WHERE rankId = ? LIMIT 1");
    $stmt->execute([$currentRankId]);
    $currentRank = $stmt->fetch(PDO::FETCH_OBJ);

    if ($currentRank) {
        try {
            $staffStmt = $pdo->prepare("
                SELECT s.svcNo, s.fName, s.lName, s.rankId,
                       s.attestDate, s.unitId, s.subWef, s.tempWef, s.DOB as dateOfBirth,
                       s.corps as corps, s.svcStatus as status, s.apptId as appt,
                       u.unitId as unit_name, r.rankIndex, r.rankId as rank_name, r.rankId as rank_abbr,
                       COALESCE(s.subWef, s.tempWef, s.attestDate, '1900-01-01') as rank_date
                FROM staff s
                LEFT JOIN unit u ON s.unitId = u.unitId
                LEFT JOIN `rank` r ON s.rankId = r.rankId
                WHERE s.rankId = ? AND s.svcStatus = 'Active'
                ORDER BY rank_date ASC, s.svcNo ASC
            ");
            $staffStmt->execute([$currentRankId]);
            $rawStaffRows = $staffStmt->fetchAll(PDO::FETCH_OBJ);

            error_log("APPOINTMENTS DEBUG: Rank=$currentRankId, Staff found=" . count($rawStaffRows));

            // Normalize into a flat array of associative arrays with stable keys
            $eligibleStaff = array_map(function ($s) {
                return [
                    'svcNo'      => (string)($s->svcNo ?? ''),
                    'fName'      => (string)($s->fName ?? ''),
                    'lName'      => (string)($s->lName ?? ''),
                    'unitId'     => (string)($s->unitId ?? ''),
                    'unit_name'  => $s->unit_name ?? null,
                    'corps'      => $s->corps ?? null,
                    'status'     => $s->status ?? 'Active',
                    'appt'       => $s->appt ?? '',
                    'rankId'     => $s->rankId ?? '',
                    'rank_name'  => $s->rank_name ?? $s->rankId ?? '',
                    'rank_abbr'  => $s->rank_abbr ?? $s->rank_name ?? '',
                ];
            }, $rawStaffRows);
        } catch (Exception $e) {
            error_log("APPOINTMENTS: Error fetching staff - " . $e->getMessage());
            $errors[] = "Error loading staff data: " . htmlspecialchars($e->getMessage());
        }
    }

    // Fallback: some environments have staff.rankId values not present in the
    // `rank` table, or the rank lookup above returned no staff for another
    // reason. Try once more directly by rankId.
    if (empty($eligibleStaff) && !empty($currentRankId)) {
        try {
            $fallbackStmt = $pdo->prepare("
                SELECT s.svcNo, s.fName, s.lName, s.rankId,
                       r.rankId as rank_name, r.rankId as rank_abbr,
                       u.unitId as unit_name, s.corps, s.svcStatus as status, s.apptId as appt
                FROM staff s
                LEFT JOIN `rank` r ON s.rankId = r.rankId
                LEFT JOIN `unit` u ON s.unitId = u.unitId
                WHERE s.rankId = ? AND s.svcStatus = 'Active'
                ORDER BY COALESCE(s.subWef, s.tempWef, s.attestDate, '1900-01-01') ASC, s.svcNo ASC
            ");
            $fallbackStmt->execute([$currentRankId]);
            $fbRows = $fallbackStmt->fetchAll(PDO::FETCH_ASSOC);

            $eligibleStaff = array_map(function ($r) {
                return [
                    'svcNo'      => (string)($r['svcNo'] ?? ''),
                    'fName'      => (string)($r['fName'] ?? ''),
                    'lName'      => (string)($r['lName'] ?? ''),
                    'unitId'     => (string)($r['unitId'] ?? ''),
                    'unit_name'  => $r['unit_name'] ?? null,
                    'corps'      => $r['corps'] ?? null,
                    'status'     => $r['status'] ?? 'Active',
                    'appt'       => $r['appt'] ?? '',
                    'rankId'     => $r['rankId'] ?? '',
                    'rank_name'  => $r['rank_name'] ?? $r['rankId'] ?? '',
                    'rank_abbr'  => $r['rank_abbr'] ?? $r['rank_name'] ?? '',
                ];
            }, $fbRows);
        } catch (Exception $e) {
            error_log('APPOINTMENTS: Fallback staff fetch failed: ' . $e->getMessage());
        }
    }
}

// Step 2: Handle form submission for appointments
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['appoint_staff'])) {
    error_log("APPOINTMENTS: Form submission received");

    $selectedStaff = $_POST['selected_staff'] ?? [];
    $appointmentTypeId = $_POST['appointment_type'] ?? '';
    $apptDate = $_POST['appt_date'] ?? '';
    $endDate = null;
    $requiresApproval = isset($_POST['requires_approval']);
    $unitsSelected = $_POST['unit'] ?? [];
    $positions = $_POST['position'] ?? [];
    $withPowersOf = $_POST['with_powers_of'] ?? [];
    $comments = $_POST['comment'] ?? [];
    $createdBy = $_SESSION['user_id'] ?? 0;
    $dateCreated = date('Y-m-d H:i:s');
    $requiresApproval; // kept for future use in a status column if added later

    // CSRF Protection
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'])) {
        error_log("APPOINTMENTS: CSRF token validation failed");
        $errors[] = "Invalid security token. Please refresh the page and try again.";
    }

    if (empty($selectedStaff)) {
        $errors[] = "Please select at least one staff member.";
    }

    if (empty($apptDate)) {
        $errors[] = "Please select the appointment date.";
    } else {
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

    if (empty($appointmentTypeId)) {
        $errors[] = "Please select an appointment type.";
    }

    // Validate appointment type exists and determine if it's temporary
    $isTemporary = false;
    if (!empty($appointmentTypeId)) {
        $typeStmt = $pdo->prepare("SELECT id, is_temporary, default_duration_months FROM appointment_type WHERE id = ?");
        $typeStmt->execute([$appointmentTypeId]);
        $typeInfo = $typeStmt->fetch(PDO::FETCH_ASSOC);
        if (!$typeInfo) {
            $errors[] = "Invalid appointment type selected.";
        } else {
            $isTemporary = (bool)$typeInfo['is_temporary'];

            if ($isTemporary && !empty($apptDate)) {
                $endDate = $_POST['endDate'] ?? null;
                if (empty($endDate)) {
                    $durationMonthsDefault = $typeInfo['default_duration_months'] ?: 36;
                    $endDate = date('Y-m-d', strtotime($apptDate . " +{$durationMonthsDefault} months"));
                    error_log("APPOINTMENTS: Auto-calculated end date ($durationMonthsDefault months): $endDate");
                }
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

    // Validate each selected staff member and their units
    $validUnitIDs = array_map(function ($u) {
        return (string)($u->unitID ?? '');
    }, $units);

    foreach ($selectedStaff as $svcNo) {
        $unitId = trim($unitsSelected[$svcNo] ?? '');
        if (empty($unitId)) {
            $errors[] = "Please select a unit for staff member " . htmlspecialchars($svcNo) . ".";
        } elseif (!in_array($unitId, $validUnitIDs, true)) {
            $errors[] = "Invalid unit selection for staff member " . htmlspecialchars($svcNo) . ".";
        }

        // UX FIX: position previously had no server-side requirement and
        // silently fell back to "Not Specified" further down - for a
        // records system that's the wrong default; require it explicitly,
        // the same way unit is already required above.
        $positionCheck = trim($positions[$svcNo] ?? '');
        if ($positionCheck === '') {
            $errors[] = "Please enter a position/appointment for staff member " . htmlspecialchars($svcNo) . ".";
        }

        // FIX: the previous pattern ^([A-Z]{2}\d{6}|\d{6})$ required either a
        // 2-letter prefix + 6 digits, or exactly 6 digits. Real service
        // numbers in this database are mostly 4 digits (e.g. "3260"), with a
        // handful at 6 digits (e.g. "007414") - the old regex rejected the
        // overwhelming majority of real staff and made the form unusable.
        // `staff.svcNo` is varchar(10), so validate against that instead.
        if (!preg_match('/^[A-Za-z0-9]{1,10}$/', $svcNo)) {
            $errors[] = "Invalid service number format for " . htmlspecialchars($svcNo) . ". Expected up to 10 letters/digits.";
        }
    }

    if (empty($errors)) {
        $existingAppointments = [];

        try {
            // svcNo/unitId comparisons here are safe once armis1_schema_fixes.sql
            // has been applied (staff_appointment now shares the project-wide
            // utf8mb4_0900_ai_ci collation with staff/unit/rank).
            $duplicateCheckStmt = $pdo->prepare("
                SELECT sa.svcNo, sa.apptId as appointment, sa.apptWef as appointment_date,
                       sa.endDate, u.unitId as unit_name, sa.id as appt_record_id,
                       sa.apptType as appointment_type_id,
                       COALESCE(at.is_temporary, 0) as is_temporary
                FROM staff_appointment sa
                LEFT JOIN `unit` u ON sa.unitId = u.unitId
                LEFT JOIN appointment_type at ON sa.apptType = at.id
                WHERE sa.svcNo = ?
                  AND (sa.endDate IS NULL OR sa.endDate >= CURDATE())
                ORDER BY sa.apptWef DESC
            ");

            foreach ($selectedStaff as $svcNo) {
                $duplicateCheckStmt->execute([$svcNo]);
                $existingAppts = $duplicateCheckStmt->fetchAll(PDO::FETCH_ASSOC);

                if (!empty($existingAppts)) {
                    $existingAppointments[$svcNo] = $existingAppts;

                    $hasSubstantive = false;
                    $hasTemporary = false;
                    foreach ($existingAppts as $appt) {
                        if ($appt['is_temporary']) {
                            $hasTemporary = true;
                        } else {
                            $hasSubstantive = true;
                        }
                    }

                    // Informational only: previous appointments are auto-ended below,
                    // so we don't block submission based on this - just log intent.
                    if (!$isTemporary && $hasSubstantive) {
                        error_log("APPOINTMENTS: {$svcNo} has an active substantive appointment that will be auto-ended.");
                    } elseif ($isTemporary && $hasTemporary && $hasSubstantive) {
                        error_log("APPOINTMENTS: {$svcNo} has both substantive and temporary appointments; both will be auto-ended.");
                    }
                }
            }

            if (!empty($existingAppointments)) {
                $_SESSION['existing_appointments'] = $existingAppointments;
            }
        } catch (Exception $e) {
            $errors[] = "Error checking for existing appointments: " . htmlspecialchars($e->getMessage());
            error_log("Duplicate check error in appointments: " . $e->getMessage());
        }

        if (empty($errors)) {
            $selectStaffStmt = $pdo->prepare("SELECT svcNo, rankId FROM staff WHERE svcNo = ? LIMIT 1");
            // NOTE: staff_appointment.unitId/apptId and the two audit columns
            // below (createdBy, createdAt) require the schema-fixes migration
            // - see armis1_schema_fixes_appointments.sql: it widens unitId to
            // varchar(50) (real unit IDs run up to 27 chars, the original
            // varchar(10) truncated/rejected most of them), widens apptId to
            // varchar(50) (several standard position names exceed the
            // original varchar(20), e.g. "Physical Training Instructor" is
            // 28 chars), and adds createdBy/createdAt which didn't exist at
            // all even though this INSERT always tried to write them.
            $insertApptStmt = $pdo->prepare("INSERT INTO staff_appointment (
                svcNo, apptId, apptType, unitId, apptWef, powers, endDate,
                durationMonths, authorityId, remarks, createdBy, createdAt
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $updateStaffStmt = $pdo->prepare("UPDATE staff SET unitId = ?, apptId = ? WHERE svcNo = ?");
            $endApptStmt = $pdo->prepare("UPDATE staff_appointment SET endDate = ?, remarks = CONCAT(COALESCE(remarks, ''), ?) WHERE id = ?");
            $perStaffDuplicateStmt = $pdo->prepare("
                SELECT COUNT(*) FROM staff_appointment
                WHERE svcNo = ? AND apptId = ? AND unitId = ?
                  AND (endDate IS NULL OR endDate > ?)
            ");

            // Lookup for display names in the post-submit summary table -
            // $eligibleStaff only holds staff at the currently-selected rank,
            // which is exactly the set that can be appointed here.
            $eligibleStaffBySvcNo = array_column($eligibleStaff, null, 'svcNo');

            try {
                $pdo->beginTransaction();

                foreach ($selectedStaff as $serviceNumber) {
                    // Store raw trimmed values - HTML-escape only at output time,
                    // never before writing to the database.
                    $unitId = trim($unitsSelected[$serviceNumber]);
                    $position = trim($positions[$serviceNumber] ?? '');
                    $powers = trim($withPowersOf[$serviceNumber] ?? '');
                    $comment = trim($comments[$serviceNumber] ?? '');

                    $appointmentPosition = $position ?: 'Not Specified';
                    $remarks = $comment;

                    // FIX: truncate to fit staff_appointment.apptId / staff.apptId
                    // BEFORE using the value anywhere (including the duplicate
                    // check below) - previously the duplicate check compared the
                    // untruncated position while the insert stored a truncated
                    // one, so the two could silently disagree.
                    $apptIdForAppointment = mb_substr($appointmentPosition, 0, 50);
                    if ($apptIdForAppointment !== $appointmentPosition) {
                        $remarks = trim(($remarks !== '' ? $remarks . ' | ' : '') . "Full position: {$appointmentPosition}");
                    }
                    $powers = mb_substr($powers, 0, 100);

                    $perStaffDuplicateStmt->execute([$serviceNumber, $apptIdForAppointment, $unitId, date('Y-m-d')]);
                    if ($perStaffDuplicateStmt->fetchColumn() > 0) {
                        $errors[] = "Cannot appoint {$serviceNumber} to '{$appointmentPosition}' in this unit: already holding this appointment.";
                        continue;
                    }

                    $selectStaffStmt->execute([$serviceNumber]);
                    $staff = $selectStaffStmt->fetch(PDO::FETCH_OBJ);
                    if (!$staff) {
                        throw new Exception("Staff member with service number {$serviceNumber} not found");
                    }

                    // Automatically end previous active appointments
                    if (isset($existingAppointments[$serviceNumber])) {
                        $newEndDate = date('Y-m-d', strtotime($apptDate . ' -1 day'));
                        $endComment = " | Ended automatically for new appointment on " . date('d-M-Y');

                        foreach ($existingAppointments[$serviceNumber] as $existingAppt) {
                            $endApptStmt->execute([$newEndDate, $endComment, $existingAppt['appt_record_id']]);
                            $appointmentsEnded++;

                            error_log(json_encode([
                                'action' => 'appointment_ended_automatically',
                                'timestamp' => date('d-M-Y H:i:s'),
                                'user_id' => $createdBy,
                                'svcNo' => $serviceNumber,
                                'old_appointment_position' => $existingAppt['appointment'],
                                'old_unit' => $existingAppt['unit_name'],
                                'old_type_id' => $existingAppt['appointment_type_id'],
                                'endDate' => $newEndDate,
                                'reason' => 'Automatic end for new appointment',
                                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
                            ]));
                        }
                    }

                    $durationMonths = null;
                    if ($endDate) {
                        $start = new DateTime($apptDate);
                        $end = new DateTime($endDate);
                        $interval = $start->diff($end);
                        $durationMonths = ($interval->y * 12) + $interval->m;
                    }

                    $insertApptStmt->execute([
                        $serviceNumber,
                        $apptIdForAppointment,
                        $appointmentTypeId,
                        $unitId,
                        $apptDate,
                        $powers,
                        $endDate,
                        $durationMonths,
                        null,
                        $remarks,
                        $createdBy,
                        $dateCreated
                    ]);

                    $updateStaffStmt->execute([$unitId, $apptIdForAppointment, $serviceNumber]);

                    $appointmentsCreated++;

                    $staffInfo = $eligibleStaffBySvcNo[$serviceNumber] ?? null;
                    $appointedStaffRows[] = [
                        'svcNo'    => $serviceNumber,
                        'name'     => $staffInfo ? trim(($staffInfo['fName'] ?? '') . ' ' . ($staffInfo['lName'] ?? '')) : '',
                        'unit'     => $unitId,
                        'position' => $appointmentPosition,
                    ];

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

                if (!empty($errors)) {
                    // One or more staff had a duplicate-appointment conflict;
                    // roll everything back so the batch is all-or-nothing.
                    $pdo->rollBack();
                } else {
                    $pdo->commit();
                    $success = true;
                    unset($_SESSION['existing_appointments']);

                    if ($appointmentsCreated > 0) {
                        $successMessage = "Successfully created {$appointmentsCreated} appointment(s)";
                        if ($appointmentsEnded > 0) {
                            $successMessage .= " and automatically ended {$appointmentsEnded} previous appointment(s)";
                        }
                        $successMessage .= ".";
                    }
                }
            } catch (Exception $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                error_log("Appointment batch failed: " . $e->getMessage());
                error_log("Stack trace: " . $e->getTraceAsString());
                $errors[] = "Error processing appointments: " .
                    (strpos($e->getMessage(), 'not found') !== false ? 'Staff member not found' : htmlspecialchars($e->getMessage()));
            }
        }
    }
}

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

#staffSelectionTable { font-size: 0.9rem; }
#staffSelectionTable thead th {
    background-color: #f8f9fa;
    font-weight: 600;
    border-bottom: 2px solid #dee2e6;
    padding: 12px 8px;
}
#staffSelectionTable tbody tr { transition: background-color 0.2s ease; }
#staffSelectionTable tbody tr:hover { background-color: #f1f3f5; cursor: pointer; }
#staffSelectionTable tbody tr.selected {
    background-color: #cfe2ff !important;
    border-left: 4px solid #0d6efd !important;
}
#staffSelectionTable tbody tr.selected:hover { background-color: #b6d4fe !important; }
.staff-checkbox {
    cursor: pointer;
    width: 18px;
    height: 18px;
    /* Row selection is handled by the DataTables Select extension via the
       td.select-checkbox cell click; the checkbox itself is a visual
       reflection of that state, so it must not intercept its own clicks
       (that would double-toggle and desync from the row's selected state). */
    pointer-events: none;
}
#masterCheckbox { cursor: pointer; width: 18px; height: 18px; }

.border-dashed { border: 1px dashed #adb5bd !important; }

#staffDetailsPanel .staff-post-card {
    border-left: 4px solid #0d6efd;
    transition: box-shadow 0.15s ease;
}
#staffDetailsPanel .staff-post-card:hover { box-shadow: 0 2px 8px rgba(0,0,0,0.08); }
#staffDetailsPanel .staff-post-card.is-incomplete { border-left-color: #dc3545; }
#staffDetailsPanel .staff-post-card .remove-staff-btn { cursor: pointer; }

#staffTableContainer.loading { position: relative; opacity: 0.6; pointer-events: none; }
#staffTableContainer.loading::after {
    content: 'Loading staff data...';
    position: absolute; top: 50%; left: 50%;
    transform: translate(-50%, -50%);
    background: rgba(255, 255, 255, 0.95);
    padding: 20px 40px; border-radius: 8px;
    box-shadow: 0 4px 6px rgba(0,0,0,0.1);
    font-weight: 600; color: #0d6efd;
}

@media (max-width: 768px) {
    #staffSelectionTable { font-size: 0.8rem; }
    #staffSelectionTable thead th,
    #staffSelectionTable tbody td { padding: 0.5rem 0.25rem; }
    #staffSelectionTable th:nth-child(6),
    #staffSelectionTable td:nth-child(6),
    #staffSelectionTable th:nth-child(7),
    #staffSelectionTable td:nth-child(7),
    #staffSelectionTable th:nth-child(8),
    #staffSelectionTable td:nth-child(8) { display: none; }
}

.select2-container--default .select2-results__option { padding: 6px 12px; border-bottom: 1px solid #f0f0f0; }
.select2-container--default .select2-results__option:last-child { border-bottom: none; }
.select2-container--default .select2-results__option--highlighted[aria-selected] .staff-badge {
    background-color: rgba(255,255,255,0.2) !important; color: #fff !important;
}
.select2-container--default .select2-results__option--highlighted[aria-selected] .staff-details {
    color: rgba(255,255,255,0.8) !important;
}
.select2-container--default .select2-selection--multiple .select2-selection__choice {
    background-color: #f8f9fa; border: 1px solid #dee2e6; padding: 3px 8px;
    margin-right: 5px; margin-top: 5px;
}
.select2-selection__choice { max-width: 100%; overflow: hidden; text-overflow: ellipsis; }
.staff-badge { font-family: monospace; font-weight: bold; }
</style>

<div class="content-wrapper with-sidebar">
    <div class="container-fluid">
        <div class="main-content">
            <div class="row">
                <div class="col-12">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h1 class="section-title"><i class="fas fa-briefcase"></i> Staff Appointments</h1>
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
                <div class="alert alert-success" id="successAlert" role="alert" aria-live="polite">
                    <i class="fas fa-check-circle"></i> <?= htmlspecialchars($successMessage) ?>
                    <?php if (!empty($selectedStaff) && $appointmentsCreated > 0):
                        $recentSvcNo = end($selectedStaff);
                    ?>
                    <button type="button" class="btn btn-outline-info btn-sm ms-3" id="viewAppointmentSummaryBtn" data-svcno="<?= htmlspecialchars($recentSvcNo) ?>">
                        <i class="fas fa-list"></i> View Appointment Summary
                    </button>
                    <?php endif; ?>
                    <?php if (!empty($appointedStaffRows)): ?>
                        <div class="table-responsive mt-3">
                            <table class="table table-sm table-bordered mb-0 bg-white">
                                <thead class="table-light">
                                    <tr>
                                        <th scope="col">Service No.</th>
                                        <th scope="col">Name</th>
                                        <th scope="col">Unit</th>
                                        <th scope="col">Position</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($appointedStaffRows as $row): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($row['svcNo']) ?></td>
                                            <td><?= htmlspecialchars($row['name']) ?></td>
                                            <td><?= htmlspecialchars($row['unit']) ?></td>
                                            <td><?= htmlspecialchars($row['position']) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <div class="modal fade" id="appointmentSummaryModal" tabindex="-1" aria-labelledby="appointmentSummaryModalLabel" aria-hidden="true">
                <div class="modal-dialog modal-lg modal-dialog-centered">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="appointmentSummaryModalLabel"><i class="fas fa-list"></i> Appointment Summary</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body" id="appointmentSummaryContent" aria-live="polite">
                            <div class="text-center text-muted"><i class="fas fa-spinner fa-spin"></i> Loading...</div>
                        </div>
                    </div>
                </div>
            </div>

            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger" id="errorAlert" role="alert" tabindex="-1">
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
                        <div class="d-flex align-items-center gap-2">
                            <select name="currentRank" id="currentRank" class="form-select" required onchange="document.getElementById('rankLoadingSpinner').classList.remove('d-none');">
                                <option value="">Select Current Rank...</option>
                                <?php foreach ($ranks as $r):
                                    if (in_array($r->rankName, $excludedRanks, true)) continue;
                                    $staffCount = $rankCounts[$r->rankID] ?? 0;
                                    ?>
                                    <option value="<?=htmlspecialchars($r->rankID)?>" <?=($currentRankId==$r->rankID)?'selected':''?>>
                                        <?=htmlspecialchars($r->rankAbbr ?: $r->rankName)?> (<?=$staffCount?> Personnel)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <span id="rankLoadingSpinner" class="spinner-border spinner-border-sm text-primary d-none" role="status" aria-hidden="true"></span>
                        </div>
                        <small class="text-muted">Only staff members at this rank will be available for selection.</small>
                    </div>
                </div>
            </form>

            <!-- Step 2: Multi-Select + Panel -->
            <?php if ($currentRankId): ?>
            <form method="post" action="" id="appointmentForm">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                <input type="hidden" name="currentRank" value="<?=htmlspecialchars($currentRankId)?>">
                <input type="hidden" name="appoint_staff" value="1">
                <div class="row mb-3">
                    <div class="col-md-12">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h5 class="mb-0"><i class="fa fa-users"></i> Select Staff Members for Appointment</h5>
                            <div>
                                <button type="button" class="btn btn-sm btn-success" id="selectAllBtn" title="Selects all staff matching the current search filter">
                                    <i class="fa fa-check-double"></i> Select All
                                </button>
                                <button type="button" class="btn btn-sm btn-warning" id="deselectAllBtn" title="Deselects all staff matching the current search filter">
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

                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div class="text-muted"><strong>Total:</strong> <span id="totalStaffCount">0</span> staff member(s)</div>
                        <div class="text-primary"><strong>Selected:</strong> <span id="selectionCount">0</span> staff member(s)</div>
                    </div>

                    <div class="table-responsive" id="staffTableContainer">
                        <table class="table table-hover table-sm" id="staffSelectionTable" style="width:100%">
                            <thead class="table-light">
                                <tr>
                                    <th scope="col" class="select-checkbox text-center" style="width: 40px;">
                                        <input type="checkbox" id="masterCheckbox" class="form-check-input" title="Select/Deselect all matching the current search filter">
                                    </th>
                                    <th scope="col">Service No.</th>
                                    <th scope="col">Rank</th>
                                    <th scope="col">Name</th>
                                    <th scope="col">Unit</th>
                                    <th scope="col">Corps</th>
                                    <th scope="col">Current Appointment</th>
                                    <th scope="col">Status</th>
                                </tr>
                            </thead>
                            <tbody><!-- DataTables will populate this --></tbody>
                        </table>
                    </div>

                        <div id="selectedStaffInputs"></div>
                    </div>
                </div>

                <!-- Shared appointment parameters (apply to every selected staff member) -->
                <div class="card mb-3 border-primary-subtle">
                    <div class="card-header bg-light">
                        <i class="fa fa-sliders-h"></i> Appointment Details <small class="text-muted">(applies to all selected staff)</small>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-3 mb-2">
                                <label class="form-label">Appointment Type *</label>
                                <select name="appointment_type" id="appointment_type" class="form-select" required>
                                    <option value="">Select Appointment Type</option>
                                    <?php foreach ($appointmentTypes as $type): ?>
                                    <option value="<?= htmlspecialchars($type['id']) ?>"
                                            data-is-temporary="<?= (int)$type['is_temporary'] ?>"
                                            data-duration="<?= htmlspecialchars($type['default_duration_months'] ?? '') ?>">
                                        <?= htmlspecialchars($type['type_name']) ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                                <?php if (empty($appointmentTypes)): ?>
                                    <small class="text-danger">No appointment types found — run the schema migration to seed appointment_type.</small>
                                <?php endif; ?>
                            </div>
                            <div class="col-md-3 mb-2">
                                <label class="form-label">Effective From *</label>
                                <input type="date" name="appt_date" id="appt_date" class="form-control" required value="<?=htmlspecialchars($_POST['appt_date'] ?? date('Y-m-d'))?>">
                            </div>
                            <div class="col-md-3 mb-2" id="endDateField" style="display:none;">
                                <label class="form-label">End Date</label>
                                <input type="date" name="endDate" id="endDate" class="form-control" value="<?=htmlspecialchars($_POST['endDate'] ?? '')?>">
                                <small class="text-muted">Defaults to the appointment type's standard duration</small>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Per-staff appointment posts (unit / position / powers / remarks) -->
                <div id="staffDetailsSection" class="mb-3" style="display:none;">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <h6 class="mb-0"><i class="fa fa-user-tag"></i> Individual Posting Details
                            <span class="badge bg-primary" id="selectedStaffBadge">0</span>
                        </h6>
                    </div>

                    <!-- Bulk-fill toolbar: quickly apply the same unit/position to every selected staff, still editable per person afterwards -->
                    <div class="card mb-3 bg-light border-dashed">
                        <div class="card-body py-2">
                            <div class="row g-2 align-items-end">
                                <div class="col-md-4">
                                    <label class="form-label small mb-1"><i class="fa fa-bolt"></i> Bulk-fill Unit</label>
                                    <select id="bulkUnit" class="form-select form-select-sm">
                                        <option value="">-- Select Unit --</option>
                                        <?php foreach ($units as $u): ?>
                                            <option value="<?=htmlspecialchars($u->unitID)?>"><?=htmlspecialchars($u->unitName)?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label small mb-1"><i class="fa fa-bolt"></i> Bulk-fill Position</label>
                                    <input type="text" id="bulkPosition" class="form-control form-control-sm" list="positionOptions" maxlength="50" placeholder="e.g. Company Commander">
                                </div>
                                <div class="col-md-4">
                                    <button type="button" id="applyBulkBtn" class="btn btn-sm btn-outline-primary w-100">
                                        <i class="fa fa-paint-roller"></i> Apply to All Selected
                                    </button>
                                </div>
                            </div>
                            <small class="text-muted"><i class="fa fa-info-circle"></i> Applies to every selected staff member below; each can still be edited individually.</small>
                        </div>
                    </div>

                    <datalist id="positionOptions">
                        <?php foreach ($standardPositions as $pos): ?>
                            <option value="<?=htmlspecialchars($pos)?>">
                        <?php endforeach; ?>
                    </datalist>

                    <div id="staffDetailsPanel" class="row g-3" aria-live="polite"></div>
                </div>

                <?php if (!empty($_SESSION['existing_appointments'])): ?>
                <div class="alert alert-info mb-3">
                    <h6 class="alert-heading"><i class="fas fa-info-circle"></i> Active Appointments Will Be Automatically Ended</h6>
                    <p class="mb-2">The following staff members have existing active appointments that will be automatically ended one day before the new appointment date:</p>
                    <ul class="mb-2">
                    <?php foreach ($_SESSION['existing_appointments'] as $svcNo => $appts): ?>
                        <?php foreach ($appts as $appt): ?>
                        <li>
                            <strong><?= htmlspecialchars($svcNo) ?></strong>:
                            <?= htmlspecialchars($appt['appointment'] ?? 'Unknown Position') ?>
                            at <?= htmlspecialchars($appt['unit_name'] ?? 'Unknown Unit') ?>
                            <?php if (!empty($appt['endDate'])): ?>
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

                <!-- Confirmation modal: appointments.php previously submitted the
                     moment "Appoint" was clicked, with no review step - risky,
                     since submitting also silently ends the person's *current*
                     appointment(s). promote_staff.php already has this pattern;
                     mirror it here. -->
                <div class="modal fade" id="appointConfirmModal" tabindex="-1" aria-labelledby="appointConfirmModalLabel" aria-hidden="true">
                    <div class="modal-dialog modal-xl">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title" id="appointConfirmModalLabel">Confirm Appointment(s)</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body">
                                <div id="appointConfirmSummary" aria-live="polite"></div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                <button type="button" id="appointConfirmSubmitBtn" class="btn btn-primary">Confirm</button>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="text-end">
                    <button type="button" id="showAppointConfirmModal" class="btn btn-primary px-5 py-2" aria-describedby="appointConfirmHelp" disabled>
                        <i class="fa fa-user-plus"></i> Appoint
                    </button>
                    <div id="appointConfirmHelp" class="form-text text-end" role="status" aria-live="polite">
                        Select at least one staff member to continue.
                    </div>
                </div>
            </form>
            <?php endif; ?>
        </div>
    </div>
</div>

<button type="button" id="backToTopBtn" class="btn btn-secondary rounded-circle" style="position:fixed;bottom:30px;right:30px;display:none;z-index:999;">
    <i class="fa fa-arrow-up"></i>

</button>

<script>
window.appointmentsServerData = <?= json_encode([
    'unitsData' => $units,
    'eligibleStaff' => $eligibleStaff,
    'currentRankId' => $currentRankId ?? '',
    'standardPositions' => $standardPositions,
    'preselectedStaff' => $_POST['selected_staff'] ?? []
], JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) ?>;
</script>
            </div>
        </div>
    </div>
</div>

<?php include dirname(__DIR__) . '/shared/footer.php'; ?>
<script src="/Armis2/admin_branch/appointments.init.js?v=<?= time() ?>"></script>

<script>
// UX additions layered on top of appointments.init.js (which owns the
// staff DataTable / per-staff posting-detail cards). These handlers are
// written defensively against the field-naming convention the backend
// already expects (unit[svcNo], position[svcNo], with_powers_of[svcNo]),
// rather than against a specific DOM structure, so they keep working
// regardless of how appointments.init.js renders the per-staff cards.
$(function() {
    // Scroll straight to the error/success alert on load, rather than
    // leaving the person to notice/scroll up on a long form.
    var $errorAlert = $('#errorAlert');
    var $successAlert = $('#successAlert');
    if ($errorAlert.length) {
        $errorAlert.get(0).scrollIntoView({ behavior: 'smooth', block: 'center' });
        $errorAlert.attr('tabindex', '-1').trigger('focus');
    } else if ($successAlert.length) {
        $successAlert.get(0).scrollIntoView({ behavior: 'smooth', block: 'center' });
    }

    function selectedServiceNumbers() {
        return $('.staff-checkbox:checked').map(function() { return $(this).val(); }).get();
    }

    // Enable the "Appoint" trigger only once at least one staff member is
    // selected, and show/hide a helper line explaining why it's disabled
    // (previously it just sat greyed out with no explanation).
    function updateAppointButtonState() {
        var hasSelection = selectedServiceNumbers().length > 0;
        $('#showAppointConfirmModal').prop('disabled', !hasSelection);
        $('#appointConfirmHelp').toggleClass('d-none', hasSelection);
    }
    $(document).on('change', '.staff-checkbox', updateAppointButtonState);
    updateAppointButtonState();

    function fieldFor(prefix, svcNo) {
        // Matches inputs/selects named e.g. unit[3260] or position[3260],
        // whichever element type the per-staff card actually uses.
        return $('[name="' + prefix + '[' + svcNo + ']"]');
    }

    function staffDisplayName(svcNo) {
        // Prefer window.eligibleStaff - appointments.init.js keeps this in sync
        // with what's actually loaded into the table (including its AJAX
        // fallback path), whereas window.appointmentsServerData.eligibleStaff
        // is always the original server-rendered snapshot.
        var list = window.eligibleStaff || (window.appointmentsServerData && window.appointmentsServerData.eligibleStaff) || [];
        for (var i = 0; i < list.length; i++) {
            if (list[i].svcNo === svcNo) {
                var last = (list[i].lName || '').toUpperCase();
                var first = list[i].fName || '';
                return (last + ' ' + first).trim() || svcNo;
            }
        }
        return svcNo;
    }

    // Build the confirmation summary and block confirmation until every
    // selected staff member has a unit and a position filled in.
    $('#showAppointConfirmModal').on('click', function() {
        var svcNos = selectedServiceNumbers();
        var apptTypeText = $('#appointment_type option:selected').text().trim();
        var apptDate = $('#appt_date').val();
        var endDateVisible = $('#endDateField').is(':visible');
        var endDate = endDateVisible ? $('#endDate').val() : '';

        var missing = [];
        var rowsHtml = '';

        svcNos.forEach(function(svcNo) {
            var unitVal = fieldFor('unit', svcNo).val() || '';
            var positionVal = fieldFor('position', svcNo).val() || '';
            var powersVal = fieldFor('with_powers_of', svcNo).val() || '';

            if (!unitVal || !positionVal) {
                missing.push(svcNo);
            }

            rowsHtml += '<tr' + (!unitVal || !positionVal ? ' class="table-danger"' : '') + '>'
                + '<td>' + svcNo + '</td>'
                + '<td>' + staffDisplayName(svcNo) + '</td>'
                + '<td>' + (unitVal || '<span class="text-danger"><i class="fa fa-exclamation-triangle"></i> Missing</span>') + '</td>'
                + '<td>' + (positionVal || '<span class="text-danger"><i class="fa fa-exclamation-triangle"></i> Missing</span>') + '</td>'
                + '<td>' + (powersVal || '<span class="text-muted">—</span>') + '</td>'
                + '</tr>';
        });

        var html = '<div class="mb-3">'
            + '<strong>Appointment Type:</strong> ' + (apptTypeText || '<span class="text-danger">Not selected</span>') + '<br>'
            + '<strong>Effective From:</strong> ' + (apptDate || '<span class="text-danger">Not set</span>')
            + (endDate ? ('<br><strong>End Date:</strong> ' + endDate) : '')
            + '</div>';

        if (missing.length > 0) {
            html += '<div class="alert alert-danger"><i class="fa fa-exclamation-triangle"></i> '
                + 'Please fill in a unit and position for: <strong>' + missing.join(', ') + '</strong> before confirming.</div>';
        }

        html += '<div class="table-responsive"><table class="table table-sm table-bordered mb-0">'
            + '<thead class="table-light"><tr>'
            + '<th scope="col">Service No.</th><th scope="col">Name</th><th scope="col">Unit</th><th scope="col">Position</th><th scope="col">With Powers Of</th>'
            + '</tr></thead><tbody>' + rowsHtml + '</tbody></table></div>'
            + '<div class="alert alert-warning mt-3 mb-0"><i class="fa fa-exclamation-triangle"></i> '
            + 'This will create <strong>' + svcNos.length + '</strong> appointment(s) and automatically end any current active appointment(s) for these staff.</div>';

        $('#appointConfirmSummary').html(html);
        $('#appointConfirmSubmitBtn').prop('disabled', missing.length > 0 || !apptTypeText || !apptDate);

        var modal = new bootstrap.Modal(document.getElementById('appointConfirmModal'));
        modal.show();
    });

    $('#appointConfirmSubmitBtn').on('click', function() {
        var instance = bootstrap.Modal.getInstance(document.getElementById('appointConfirmModal'));
        if (instance) { instance.hide(); }
        var formEl = document.getElementById('appointmentForm');
        // FIX: form.submit() bypasses both HTML5 required-field constraint
        // validation and the form's own 'submit' event entirely - which
        // would have silently skipped appointments.init.js's own
        // completeness backstop check. requestSubmit() behaves like a real
        // submit-button click, so both still run as intended.
        if (formEl.requestSubmit) {
            formEl.requestSubmit();
        } else {
            formEl.submit();
        }
    });
});
</script>