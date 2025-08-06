<?php
/**
 * Simplified Working Form Handler for Staff Creation
 */

if (!defined('ARMIS_ADMIN_BRANCH')) {
    die('Direct access not permitted');
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // Debug: Log all POST data
    error_log("=== FORM SUBMISSION DEBUG ===");
    error_log("POST data: " . print_r($_POST, true));
    error_log("Email field: " . (isset($_POST['email']) ? $_POST['email'] : 'NOT SET'));
    error_log("Phone field: " . (isset($_POST['phone']) ? $_POST['phone'] : 'NOT SET'));
    
    // Start session for CSRF and success messages
    // Include necessary files
    require_once dirname(dirname(__DIR__)) . '/shared/database_connection.php';
    require_once dirname(dirname(__DIR__)) . '/shared/email_mailer.php';
    
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    $errors = [];
    $success = false;
    
    try {
        // Database connection using centralized function
        $conn = getMysqliConnection();
        
        // Basic CSRF validation (simplified)
        if (!isset($_POST['csrf']) || empty($_POST['csrf'])) {
            throw new Exception("Security token missing. Please refresh the page and try again.");
        }
        
        // Validate required fields (using actual form field names)
        $requiredFields = [
            'fname' => 'First Name',
            'lname' => 'Last Name', 
            'email' => 'Email',
            'phone' => 'Phone',
            'DOB' => 'Date of Birth',
            'svcNo' => 'Service Number',
            'rankID' => 'Rank',
            'unitID' => 'Unit',
            'corps' => 'Corps'
        ];
        
        foreach ($requiredFields as $field => $label) {
            if (!isset($_POST[$field]) || trim($_POST[$field]) === '') {
                $errors[$field] = "$label is required";
            }
        }
        
        // Enhanced email validation
        if (isset($_POST['email']) && trim($_POST['email']) !== '') {
            $email = trim($_POST['email']);
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errors['email'] = 'Please enter a valid email address';
            }
        }
        
        // Enhanced phone validation
        if (isset($_POST['phone']) && trim($_POST['phone']) !== '') {
            $phone = trim($_POST['phone']);
            // Allow various phone formats: +260123456789, 0123456789, 123-456-7890, etc.
            if (!preg_match('/^[\+]?[0-9\-\(\)\s]+$/', $phone)) {
                $errors['phone'] = 'Please enter a valid phone number (numbers, +, -, (), spaces only)';
            }
        }
        
        // Debug logging
        error_log("Form validation - Email: " . ($_POST['email'] ?? 'not set'));
        error_log("Form validation - Phone: " . ($_POST['phone'] ?? 'not set'));
        error_log("Form validation errors: " . print_r($errors, true));
        
        // If no errors, proceed with insertion
        if (empty($errors)) {
            
            // Include email mailer for welcome email
            require_once dirname(__DIR__, 2) . '/shared/email_mailer.php';
            $mailer = new ARMISMailer();
            
            // Generate username (service number) and temporary password
            $username = trim($_POST['svcNo']); // Use service number as username
            $tempPassword = ARMISMailer::generateTempPassword(12);
            $hashedPassword = password_hash($tempPassword, PASSWORD_DEFAULT);
            $activationToken = ARMISMailer::generateActivationToken();
            
            // Check if username already exists
            $checkStmt = $conn->prepare("SELECT id FROM staff WHERE username = ? OR service_number = ?");
            $checkStmt->bind_param('ss', $username, $username);
            $checkStmt->execute();
            $existing = $checkStmt->get_result();
            
            if ($existing->num_rows > 0) {
                $errors['svcNo'] = 'Service number already exists in the system';
            } else {
                // Prepare data for insertion (enhanced with user account fields)
                $insertData = [
                    'first_name' => trim($_POST['fname']),
                    'last_name' => trim($_POST['lname']),
                    'NRC' => trim($_POST['nrc'] ?? ''),
                    'email' => trim($_POST['email']),
                    'tel' => trim($_POST['phone']),
                    'DOB' => $_POST['DOB'],
                    'gender' => $_POST['gender'] ?? 'M',
                    'service_number' => trim($_POST['svcNo']),
                    'username' => $username,
                    'password' => $hashedPassword,
                    'temp_password' => 1,
                    'force_password_change' => 1,
                    'activation_token' => $activationToken,
                    'account_activated' => 0,
                    'rank_id' => intval($_POST['rankID']),
                    'unit_id' => intval($_POST['unitID']),
                    'corps' => trim($_POST['corps']),
                    'bloodGp' => $_POST['blood_group'] ?? '',
                    'height' => intval($_POST['height'] ?? 0),
                    'province' => $_POST['province'] ?? '',
                    'district' => $_POST['district'] ?? '',
                    'religion' => $_POST['religion'] ?? '',
                    'village' => $_POST['village'] ?? '',
                    'role' => 'user', // Default role
                    'svcStatus' => 'active',
                    'accStatus' => 'pending', // Pending until email activation
                    'dateCreated' => date('Y-m-d H:i:s'),
                    'createdBy' => $_SESSION['userID'] ?? 1
                ];
                
                // Build insert query
                $fields = array_keys($insertData);
                $placeholders = str_repeat('?,', count($fields) - 1) . '?';
                $values = array_values($insertData);
                
                $query = "INSERT INTO staff (" . implode(',', $fields) . ") VALUES ($placeholders)";
                
                // Execute insertion
                $stmt = $conn->prepare($query);
                if (!$stmt) {
                    throw new Exception("Database prepare error: " . $conn->error);
                }
                
                // Bind parameters (all as strings for simplicity)
                $types = str_repeat('s', count($values));
                $stmt->bind_param($types, ...$values);
                
                if ($stmt->execute()) {
                    $staffId = $conn->insert_id;
                    $service_number = $insertData['service_number'];
                    // Insert spouse if provided
                    if (!empty($_POST['spouse_name'])) {
                        $spouse_name = trim($_POST['spouse_name']);
                        $spouse_dob = $_POST['spouse_dob'] ?? null;
                        $spouse_nrc = $_POST['spouse_nrc'] ?? null;
                        $spouse_occup = $_POST['spouse_occup'] ?? null;
                        $spouse_contact = $_POST['spouse_contact'] ?? null;
                        $spouseStmt = $conn->prepare("INSERT INTO staff_spouse (service_number, spouseName, spouseDOB, spouseNRC, spouseOccup, spouseContact) VALUES (?, ?, ?, ?, ?, ?)");
                        $spouseStmt->bind_param('ssssss', $service_number, $spouse_name, $spouse_dob, $spouse_nrc, $spouse_occup, $spouse_contact);
                        $spouseStmt->execute();
                    }
                    // Insert children if provided
                    if (!empty($_POST['child_name']) && is_array($_POST['child_name'])) {
                        $child_names = $_POST['child_name'];
                        $child_dobs = $_POST['child_dob'] ?? [];
                        $child_genders = $_POST['child_gender'] ?? [];
                        for ($i = 0; $i < count($child_names); $i++) {
                            $name = trim($child_names[$i] ?? '');
                            $dob = $child_dobs[$i] ?? null;
                            $gender = $child_genders[$i] ?? null;
                            if ($name !== '') {
                                $childStmt = $conn->prepare("INSERT INTO staff_family_members (staff_id, name, relationship, date_of_birth, phone, occupation, is_next_of_kin, is_emergency_contact, created_at, updated_at) VALUES (?, ?, 'Child', ?, NULL, NULL, 0, 0, NOW(), NOW())");
                                $childStmt->bind_param('iss', $staffId, $name, $dob);
                                $childStmt->execute();
                            }
                        }
                    }
                    // Get rank information for email
                    $rankStmt = $conn->prepare("SELECT name as rank_name FROM ranks WHERE id = ?");
                    $rankStmt->bind_param('i', $insertData['rankID']);
                    $rankStmt->execute();
                    $rankResult = $rankStmt->get_result();
                    $rankData = $rankResult->fetch_assoc();
                    // Prepare staff data for email
                    $staffEmailData = array_merge($insertData, [
                        'id' => $staffId,
                        'rank_name' => $rankData['rank_name'] ?? 'Staff'
                    ]);
                    // Send welcome email
                    $emailResult = $mailer->sendWelcomeEmail($staffEmailData, $tempPassword);
                    if ($emailResult['success']) {
                        // Mark email as sent
                        $updateStmt = $conn->prepare("UPDATE staff SET welcome_email_sent = 1 WHERE id = ?");
                        $updateStmt->bind_param('i', $staffId);
                        $updateStmt->execute();
                        $_SESSION['success_message'] = "Staff member created successfully! Staff ID: $staffId. Welcome email sent to " . $insertData['email'] . ".";
                    } else {
                        $_SESSION['success_message'] = "Staff member created successfully! Staff ID: $staffId. Note: Welcome email could not be sent (" . $emailResult['message'] . ").";
                        error_log("Failed to send welcome email: " . $emailResult['message']);
                    }
                    $_SESSION['form_success'] = true;
                    $_SESSION['temp_password'] = $tempPassword; // For display purposes (remove in production)
                    $_SESSION['username'] = $username;
                    // Log the activity
                    error_log("New staff created - ID: $staffId, Username: $username, Email: " . $insertData['email']);
                    // Redirect to prevent resubmission
                    header('Location: ' . $_SERVER['PHP_SELF'] . '?success=1');
                    exit;
                    
                } else {
                    throw new Exception("Database execution error: " . $stmt->error);
                }
            }
        }
        
    } catch (Exception $e) {
        error_log("Staff creation error: " . $e->getMessage());
        $errors['general'] = $e->getMessage();
    }
    
    // Store errors in session for display
    if (!empty($errors)) {
        $_SESSION['form_errors'] = $errors;
        $_SESSION['form_data'] = $_POST; // Preserve form data
    }
}

// Check for success message
if (isset($_GET['success']) && isset($_SESSION['success_message'])) {
    $success_message = $_SESSION['success_message'];
    $display_credentials = false;
    
    // Show temporary credentials for admin (remove in production for security)
    if (isset($_SESSION['temp_password']) && isset($_SESSION['username'])) {
        $temp_password = $_SESSION['temp_password'];
        $username = $_SESSION['username'];
        $display_credentials = true;
        unset($_SESSION['temp_password']);
        unset($_SESSION['username']);
    }
    
    unset($_SESSION['success_message']);
    unset($_SESSION['form_success']);
}

// Get form errors and data
$form_errors = $_SESSION['form_errors'] ?? [];
$form_data = $_SESSION['form_data'] ?? [];
unset($_SESSION['form_errors']);
unset($_SESSION['form_data']);

?>
