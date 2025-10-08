<?php
// Define module constants
define('ARMIS_ADMIN_BRANCH', true);
define('ARMIS_DEVELOPMENT', true);

// Enable detailed error reporting and logging
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', dirname(__DIR__) . '/logs/promote_staff_errors.log');
error_reporting(E_ALL);

// Log script start
error_log("PROMOTE_STAFF: Script started at " . date('Y-m-d H:i:s'));

// Include admin branch authentication and database
try {
    error_log("PROMOTE_STAFF: Including auth.php");
    require_once __DIR__ . '/includes/auth.php';
    error_log("PROMOTE_STAFF: Auth included successfully");
    
    error_log("PROMOTE_STAFF: Including database connection");
    require_once dirname(__DIR__) . '/shared/database_connection.php';
    error_log("PROMOTE_STAFF: Database connection included successfully");
} catch (Exception $e) {
    error_log("PROMOTE_STAFF CRITICAL ERROR: Unable to load required files - " . $e->getMessage());
    error_log("PROMOTE_STAFF CRITICAL ERROR: File: " . $e->getFile() . " Line: " . $e->getLine());
    die("Critical Error: Unable to load required files - " . htmlspecialchars($e->getMessage()));
}

// Require authentication
try {
    error_log("PROMOTE_STAFF: Checking authentication");
    requireAuth();
    error_log("PROMOTE_STAFF: Authentication successful");
} catch (Exception $e) {
    error_log("PROMOTE_STAFF AUTH ERROR: " . $e->getMessage());
    error_log("PROMOTE_STAFF AUTH ERROR: File: " . $e->getFile() . " Line: " . $e->getLine());
    die("Authentication Error: " . htmlspecialchars($e->getMessage()));
}

// Generate CSRF token
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$pageTitle = "Staff Promotions - Admin Branch";
$moduleName = "Admin Branch";
$moduleIcon = "arrow-up";
$currentPage = "promotions";

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

// Initialize variables
$errors = [];
$successMessages = [];
$success = false;
$currentRankId = '';
$currentRank = null;
$eligibleStaff = [];
$staffCount = 0;
$category = '';
$ranks = [];
$pdo = null; // Initialize PDO variable

// Use PDO for all DB operations
try {
    error_log("PROMOTE_STAFF: Establishing database connection");
    $pdo = getDbConnection();
    if (!$pdo) {
        throw new Exception("Failed to establish database connection");
    }
    error_log("PROMOTE_STAFF: Database connection successful");
    
    // Test the database connection
    $testQuery = $pdo->query("SELECT 1");
    if (!$testQuery) {
        throw new Exception("Database connection test failed");
    }
    
    // Load all ranks for the dropdown
    $ranksStmt = $pdo->prepare("SELECT id, name, abbreviation, level, category FROM ranks ORDER BY level ASC");
    $ranksStmt->execute();
    $ranks = $ranksStmt->fetchAll(PDO::FETCH_OBJ);
    error_log("PROMOTE_STAFF: Loaded " . count($ranks) . " ranks");
    
} catch (Exception $e) {
    error_log("PROMOTE_STAFF DATABASE ERROR: " . $e->getMessage());
    $errors[] = "Database connection failed: " . htmlspecialchars($e->getMessage());
    die("Database Connection Error: " . htmlspecialchars($e->getMessage()));
}

// Handle rank selection
if (isset($_GET['current_rank']) && !empty($_GET['current_rank'])) {
    $currentRankId = intval($_GET['current_rank']);
    error_log("PROMOTE_STAFF: Processing rank selection: $currentRankId");
    
    try {
        // Ensure database connection is available
        if (!$pdo) {
            $pdo = getDbConnection();
            if (!$pdo) {
                throw new Exception("Database connection failed");
            }
        }
        
        // Get current rank details
        $stmt = $pdo->prepare("SELECT * FROM ranks WHERE id = ? LIMIT 1");
        $stmt->execute([$currentRankId]);
        $currentRank = $stmt->fetch(PDO::FETCH_OBJ);
        
        if ($currentRank) {
            // Get category directly from database
            $category = $currentRank->category;
            $currentRankName = $currentRank->name;
            $currentRankLevel = $currentRank->level;
            
            // Fetch staff at the selected rank
            $staffStmt = $pdo->prepare("
                SELECT s.id, s.service_number, s.first_name, s.last_name, s.rank_id, 
                       s.attestDate, s.unit_id, s.subWef, s.tempWef, s.corps, s.svcStatus as status,
                       u.name as unit_name, r.level, r.name as rank_name, r.abbreviation as rank_abbr
                FROM staff s
                LEFT JOIN units u ON s.unit_id = u.id
                LEFT JOIN ranks r ON s.rank_id = r.id
                WHERE s.rank_id = ? AND s.svcStatus = 'Active'
                ORDER BY r.level ASC, s.subWef ASC, s.tempWef ASC, s.attestDate ASC, s.service_number ASC
            ");
            $staffStmt->execute([$currentRankId]);
            $eligibleStaff = $staffStmt->fetchAll(PDO::FETCH_OBJ);
            $staffCount = count($eligibleStaff);
            
            error_log("PROMOTE_STAFF: Found $staffCount staff members at rank $currentRankName");
            
        } else {
            $errors[] = "Invalid rank selected.";
        }
    } catch (Exception $e) {
        error_log("PROMOTE_STAFF ERROR: " . $e->getMessage());
        $errors[] = "Error loading staff data: " . htmlspecialchars($e->getMessage());
    }
}

// Handle promotion form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    error_log("PROMOTE_STAFF: Processing POST request - Action: " . $_POST['action']);
    
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $errors[] = "Invalid security token. Please try again.";
    } else {
        if ($_POST['action'] === 'promote_staff') {
            // Handle promotion logic
            if (!empty($_POST['selected_staff']) && !empty($_POST['new_rank'])) {
                $selectedStaff = $_POST['selected_staff'];
                $newRankId = intval($_POST['new_rank']);
                $promotionDate = $_POST['promotion_date'] ?? date('Y-m-d');
                
                error_log("PROMOTE_STAFF: Promoting " . count($selectedStaff) . " staff to rank $newRankId");
                
                try {
                    // Ensure database connection is available
                    if (!$pdo) {
                        $pdo = getDbConnection();
                        if (!$pdo) {
                            throw new Exception("Database connection failed");
                        }
                    }
                    
                    $pdo->beginTransaction();
                    $promoted = 0;
                    
                    foreach ($selectedStaff as $serviceNumber) {
                        // Update staff rank
                        $updateStmt = $pdo->prepare("
                            UPDATE staff 
                            SET rank_id = ?, promotion_date = ? 
                            WHERE service_number = ? AND svcStatus = 'Active'
                        ");
                        
                        if ($updateStmt->execute([$newRankId, $promotionDate, $serviceNumber])) {
                            $promoted++;
                        }
                    }
                    
                    $pdo->commit();
                    $successMessages[] = "Successfully promoted $promoted staff member(s).";
                    $success = true;
                    
                    // Reload staff data
                    if ($currentRankId) {
                        $staffStmt = $pdo->prepare("
                            SELECT s.id, s.service_number, s.first_name, s.last_name, s.rank_id, 
                                   s.attestDate, s.unit_id, s.subWef, s.tempWef, s.corps, s.svcStatus as status,
                                   u.name as unit_name, r.level, r.name as rank_name, r.abbreviation as rank_abbr
                            FROM staff s
                            LEFT JOIN units u ON s.unit_id = u.id
                            LEFT JOIN ranks r ON s.rank_id = r.id
                            WHERE s.rank_id = ? AND s.svcStatus = 'Active'
                            ORDER BY r.level ASC, s.subWef ASC, s.tempWef ASC, s.attestDate ASC, s.service_number ASC
                        ");
                        $staffStmt->execute([$currentRankId]);
                        $eligibleStaff = $staffStmt->fetchAll(PDO::FETCH_OBJ);
                        $staffCount = count($eligibleStaff);
                    }
                    
                } catch (Exception $e) {
                    $pdo->rollback();
                    error_log("PROMOTE_STAFF PROMOTION ERROR: " . $e->getMessage());
                    $errors[] = "Promotion failed: " . htmlspecialchars($e->getMessage());
                }
            } else {
                $errors[] = "Please select staff members and target rank.";
            }
        }
    }
}

// Include header
require_once dirname(__DIR__) . '/shared/header.php';
?>

<style>
.table-container {
    border: 1px solid #dee2e6;
    border-radius: 0.375rem;
    overflow: hidden;
    background: white;
}

#staffSelectionTable {
    margin-bottom: 0;
    border: 0;
}

#staffSelectionTable thead th {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border: none;
    font-weight: 600;
    text-transform: uppercase;
    font-size: 0.85rem;
    letter-spacing: 0.5px;
    padding: 1rem 0.75rem;
}

#staffSelectionTable tbody tr {
    transition: all 0.2s ease;
    border-bottom: 1px solid #f8f9fa;
}

#staffSelectionTable tbody tr:hover {
    background-color: #f8f9fa;
    transform: translateY(-1px);
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

#staffSelectionTable tbody tr.selected {
    background-color: #e3f2fd;
    border-left: 4px solid #2196f3;
}

#staffSelectionTable tbody tr.selected:hover {
    background-color: #bbdefb;
}

.badge {
    font-size: 0.75rem;
    padding: 0.375rem 0.75rem;
}

.staff-checkbox {
    transform: scale(1.2);
    cursor: pointer;
}

.promotion-controls {
    background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
    border-radius: 0.5rem;
    padding: 1.5rem;
    margin-bottom: 1.5rem;
    border: 1px solid #dee2e6;
}

.btn-promote {
    background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
    border: none;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.btn-promote:hover {
    background: linear-gradient(135deg, #218838 0%, #1e7e34 100%);
    transform: translateY(-1px);
    box-shadow: 0 4px 8px rgba(0,0,0,0.2);
}

@media (max-width: 768px) {
    #staffSelectionTable {
        font-size: 0.875rem;
    }
    
    #staffSelectionTable thead th,
    #staffSelectionTable tbody td {
        padding: 0.5rem 0.25rem;
    }
    
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

<div class="container-fluid">
    <!-- Page Header -->
    <div class="row">
        <div class="col-12">
            <div class="page-header mb-4">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <h1 class="page-title">
                            <i class="fas fa-arrow-up me-2"></i>
                            Staff Promotions
                        </h1>
                        <p class="page-description mb-0">Manage staff promotions and rank changes</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Success/Error Messages -->
    <?php if (!empty($successMessages)): ?>
        <div class="row">
            <div class="col-12">
                <?php foreach ($successMessages as $message): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="fas fa-check-circle me-2"></i>
                        <?= htmlspecialchars($message) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

    <?php if (!empty($errors)): ?>
        <div class="row">
            <div class="col-12">
                <?php foreach ($errors as $error): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="fas fa-exclamation-circle me-2"></i>
                        <?= htmlspecialchars($error) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

    <!-- Rank Selection Form -->
    <div class="row">
        <div class="col-12">
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-filter me-2"></i>
                        Select Current Rank
                    </h5>
                </div>
                <div class="card-body">
                    <form method="GET" class="row g-3">
                        <div class="col-md-8">
                            <label for="current_rank" class="form-label">Current Rank</label>
                            <select name="current_rank" id="current_rank" class="form-select" required>
                                <option value="">Select a rank...</option>
                                <?php foreach ($ranks as $rank): ?>
                                    <option value="<?= htmlspecialchars($rank->id) ?>" 
                                            <?= $currentRankId == $rank->id ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($rank->name) ?> (<?= htmlspecialchars($rank->abbreviation) ?>) - Level <?= htmlspecialchars($rank->level) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-search me-2"></i>
                                Load Staff
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <?php if ($currentRankId && !empty($eligibleStaff)): ?>
    <!-- Promotion Controls -->
    <div class="row">
        <div class="col-12">
            <div class="promotion-controls">
                <form method="POST" id="promotionForm">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                    <input type="hidden" name="action" value="promote_staff">
                    <input type="hidden" name="current_rank" value="<?= htmlspecialchars($currentRankId) ?>">
                    
                    <div class="row g-3 align-items-end">
                        <div class="col-md-4">
                            <label for="new_rank" class="form-label">Promote To Rank</label>
                            <select name="new_rank" id="new_rank" class="form-select" required>
                                <option value="">Select target rank...</option>
                                <?php foreach ($ranks as $rank): ?>
                                    <?php if ($rank->level > $currentRank->level): ?>
                                        <option value="<?= htmlspecialchars($rank->id) ?>">
                                            <?= htmlspecialchars($rank->name) ?> (<?= htmlspecialchars($rank->abbreviation) ?>) - Level <?= htmlspecialchars($rank->level) ?>
                                        </option>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label for="promotion_date" class="form-label">Promotion Date</label>
                            <input type="date" name="promotion_date" id="promotion_date" 
                                   class="form-control" value="<?= date('Y-m-d') ?>" required>
                        </div>
                        <div class="col-md-5">
                            <div class="d-flex gap-2">
                                <button type="button" id="selectAllBtn" class="btn btn-outline-primary">
                                    <i class="fas fa-check-square me-2"></i>
                                    Select All
                                </button>
                                <button type="button" id="clearSelectionBtn" class="btn btn-outline-secondary">
                                    <i class="fas fa-square me-2"></i>
                                    Clear All
                                </button>
                                <button type="submit" id="promoteBtn" class="btn btn-promote" disabled>
                                    <i class="fas fa-arrow-up me-2"></i>
                                    Promote Selected (<span id="selectedCount">0</span>)
                                </button>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Staff Table -->
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-users me-2"></i>
                        Staff at Rank: <?= htmlspecialchars($currentRank->name) ?> 
                        <span class="badge bg-primary ms-2"><?= $staffCount ?> members</span>
                    </h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-container">
                        <table class="table table-hover table-sm" id="staffSelectionTable" style="width:100%">
                            <thead>
                                <tr>
                                    <th width="50">Select</th>
                                    <th width="120">Service No.</th>
                                    <th width="100">Rank</th>
                                    <th>Name</th>
                                    <th width="150">Unit</th>
                                    <th width="100">Corps</th>
                                    <th width="100">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <!-- Data will be populated by DataTables -->
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php elseif ($currentRankId): ?>
    <!-- No Staff Found -->
    <div class="row">
        <div class="col-12">
            <div class="alert alert-info">
                <i class="fas fa-info-circle me-2"></i>
                No staff members found at the selected rank level.
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    console.log('Document ready, initializing promotion page...');
    
    // Make staff data available globally
    const eligibleStaff = <?= json_encode($eligibleStaff ?? []) ?>;
    let staffTable = null;
    
    console.log('Staff data loaded:', eligibleStaff.length, 'members');
    
    // Initialize DataTable if we have staff data
    if (eligibleStaff.length > 0) {
        initializeDataTable();
    }
    
    function initializeDataTable() {
        console.log('Document ready, checking DataTables availability...');
        
        if (typeof $.fn.DataTable === 'undefined') {
            console.error('DataTables is not loaded!');
            $('#staffSelectionTable tbody').html('<tr><td colspan="7" class="text-center text-danger">DataTables library not loaded. Please refresh the page.</td></tr>');
            return;
        }
        
        console.log('Initializing DataTables with', eligibleStaff.length, 'staff members');
        
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
                    }
                ],
                language: {
                    emptyTable: "No staff members found at this rank",
                    info: "Showing _START_ to _END_ of _TOTAL_ staff members",
                    infoEmpty: "No staff members available",
                    search: "Search staff:"
                },
                dom: '<"row"<"col-sm-12 col-md-6"l><"col-sm-12 col-md-6"f>>rtip'
            });
            
            console.log('DataTable initialized successfully');
            
            // Add event handlers for checkboxes
            setupCheckboxHandlers();
            
        } catch (error) {
            console.error('DataTables initialization failed:', error);
            $('#staffSelectionTable tbody').html('<tr><td colspan="7" class="text-center text-danger">Failed to initialize table. Please refresh the page.</td></tr>');
        }
    }
    
    function setupCheckboxHandlers() {
        // Handle individual checkbox changes
        $(document).on('change', '.staff-checkbox', function() {
            updateSelectedCount();
            updateRowHighlight($(this));
        });
        
        // Select All button
        $('#selectAllBtn').on('click', function() {
            $('.staff-checkbox').prop('checked', true).trigger('change');
        });
        
        // Clear Selection button
        $('#clearSelectionBtn').on('click', function() {
            $('.staff-checkbox').prop('checked', false).trigger('change');
        });
        
        // Form submission
        $('#promotionForm').on('submit', function(e) {
            const selectedStaff = $('.staff-checkbox:checked');
            if (selectedStaff.length === 0) {
                e.preventDefault();
                alert('Please select at least one staff member to promote.');
                return false;
            }
            
            if (!$('#new_rank').val()) {
                e.preventDefault();
                alert('Please select a target rank.');
                return false;
            }
            
            // Add selected staff to form
            selectedStaff.each(function() {
                $('<input>').attr({
                    type: 'hidden',
                    name: 'selected_staff[]',
                    value: $(this).val()
                }).appendTo('#promotionForm');
            });
            
            // Confirm promotion
            if (!confirm(`Are you sure you want to promote ${selectedStaff.length} staff member(s)?`)) {
                e.preventDefault();
                return false;
            }
        });
    }
    
    function updateSelectedCount() {
        const count = $('.staff-checkbox:checked').length;
        $('#selectedCount').text(count);
        $('#promoteBtn').prop('disabled', count === 0);
    }
    
    function updateRowHighlight(checkbox) {
        const row = checkbox.closest('tr');
        if (checkbox.prop('checked')) {
            row.addClass('selected');
        } else {
            row.removeClass('selected');
        }
    }
});
</script>

<?php require_once dirname(__DIR__) . '/shared/footer.php'; ?>