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
        
        // Validate required fields (using actual form field names from personal tab)
        $requiredFields = [
            'fname' => 'First Name',
            'lname' => 'Surname',
            'email' => 'Email',
            'phone' => 'Phone',
            'DOB' => 'Date of Birth',
            'svcNo' => 'Service Number',
            'category' => 'Category',
            'rankID' => 'Rank',
            'gender' => 'Gender',
            'province' => 'Province',
            'district' => 'District',
            'religion' => 'Religion',
            'village' => 'Village',
            // Only fields present in the staff table
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
        
    // Height validation removed
        
        // Debug logging
        error_log("Form validation - Email: " . ($_POST['email'] ?? 'not set'));
        error_log("Form validation - Phone: " . ($_POST['phone'] ?? 'not set'));
        error_log("Form validation errors: " . print_r($errors, true));
        
        // If no errors, proceed with insertion
        if (empty($errors)) {
            
            // Include email mailer for welcome email
            require_once dirname(__DIR__, 2) . '/shared/email_mailer.php';
            $mailer = new ARMISMailer();
            
            // Generate username with prefix and service number
            $serviceNumber = trim($_POST['svcNo']);
            $prefix = !empty($_POST['prefix']) ? trim($_POST['prefix']) : ''; // Use form prefix
            $username = $prefix . $serviceNumber; // Combine prefix with service number for username
            $tempPassword = ARMISMailer::generateTempPassword(12);
            $hashedPassword = password_hash($tempPassword, PASSWORD_DEFAULT);
            $activationToken = ARMISMailer::generateActivationToken();
            
            // Check if username already exists
            $checkStmt = $conn->prepare("SELECT id FROM staff WHERE username = ? OR service_number = ?");
            $checkStmt->bind_param('ss', $username, $serviceNumber);
            $checkStmt->execute();
            $existing = $checkStmt->get_result();
            
            if ($existing->num_rows > 0) {
                $errors['svcNo'] = 'Service number already exists in the system';
            } else {
                // Prepare data for insertion (enhanced with user account fields)
                $insertData = [
                    'first_name' => trim($_POST['fname']),
                    'last_name' => trim($_POST['lname']),
                    'email' => trim($_POST['email']),
                    'tel' => trim($_POST['phone']),
                    'DOB' => $_POST['DOB'],
                    'gender' => $_POST['gender'],
                    'service_number' => $serviceNumber, // Store original service number
                    'category' => $_POST['category'], // Add the selected category
                    'rank_id' => $_POST['rankID'], // Add the selected rank
                    'province' => $_POST['province'],
                    'district' => $_POST['district'],
                    'religion' => $_POST['religion'],
                    'village' => $_POST['village'],
                    'username' => $username, // Username with prefix
                    'password' => $hashedPassword,
                    'role' => 'user', // Default role for all new staff members
                    'svcStatus' => 'active',
                    'accStatus' => 'active', // Set to active since we're sending credentials
                    'dateCreated' => date('Y-m-d H:i:s'),
                    'createdBy' => $_SESSION['userID'] ?? 1,
                    'is_first_login' => 1 // Flag to require password change on first login
                ];
                // Add prefix if provided
                if (!empty($_POST['prefix'])) {
                    $insertData['prefix'] = trim($_POST['prefix']);
                }
                // Remove any fields not present in the form
                
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
                    
                    // Prepare staff data for email
                    $staffData = [
                        'rank_name' => '', // Will be populated from rank lookup
                        'first_name' => trim($_POST['fname']),
                        'last_name' => trim($_POST['lname']),
                        'username' => $username,
                        'email' => trim($_POST['email']),
                        'service_number' => trim($_POST['svcNo'])
                    ];
                    
                    // Get rank name for email
                    if (!empty($_POST['rankID'])) {
                        $rankStmt = $conn->prepare("SELECT name as rankName FROM ranks WHERE id = ?");
                        $rankStmt->bind_param("i", $_POST['rankID']);
                        $rankStmt->execute();
                        $rankResult = $rankStmt->get_result();
                        if ($rankRow = $rankResult->fetch_assoc()) {
                            $staffData['rank_name'] = $rankRow['rankName'];
                        }
                        $rankStmt->close();
                    }
                    
                    // Send welcome email with credentials
                    try {
                        $mailer = new ARMISMailer();
                        $emailResult = $mailer->sendWelcomeEmail($staffData, $tempPassword);
                        
                        if ($emailResult['success']) {
                            $_SESSION['success_message'] = "Staff member successfully created! Login credentials have been sent to their email address.";
                            error_log("Welcome email sent successfully to: " . $staffData['email']);
                        } else {
                            $_SESSION['success_message'] = "Staff member created successfully, but failed to send email: " . $emailResult['message'];
                            error_log("Failed to send welcome email: " . $emailResult['message']);
                        }
                    } catch (Exception $emailError) {
                        $_SESSION['success_message'] = "Staff member created successfully, but email sending failed: " . $emailError->getMessage();
                        error_log("Email sending error: " . $emailError->getMessage());
                    }
                    
                    // Store temporary credentials in session for display
                    $_SESSION['temp_password'] = $tempPassword;
                    $_SESSION['username'] = $username;
                    $_SESSION['staff_name'] = trim($_POST['fname']) . ' ' . trim($_POST['lname']);
                    $_SESSION['staff_email'] = trim($_POST['email']);
                    
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
$success_message = '';
$display_credentials = false;
$temp_password = '';
$username = '';
$staff_name = '';
$staff_email = '';

if (isset($_GET['success']) && $_GET['success'] == '1') {
    if (isset($_SESSION['success_message'])) {
        $success_message = $_SESSION['success_message'];
        unset($_SESSION['success_message']);
    }
    
    // Show temporary credentials for the newly created staff
    if (isset($_SESSION['temp_password']) && isset($_SESSION['username'])) {
        $temp_password = $_SESSION['temp_password'];
        $username = $_SESSION['username'];
        $staff_name = $_SESSION['staff_name'] ?? '';
        $staff_email = $_SESSION['staff_email'] ?? '';
        $display_credentials = true;
        
        // Clear sensitive data from session
        unset($_SESSION['temp_password']);
        unset($_SESSION['username']);
        unset($_SESSION['staff_name']);
        unset($_SESSION['staff_email']);
    }
}

// Get form errors and data
$form_errors = $_SESSION['form_errors'] ?? [];
$form_data = $_SESSION['form_data'] ?? [];
unset($_SESSION['form_errors']);
unset($_SESSION['form_data']);

?>
