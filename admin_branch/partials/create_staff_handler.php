<?php
/**
 * Comprehensive Staff Creation Handler
 * Handles all staff table fields with proper validation
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
            'blood_group' => 'Blood Group',
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
            $checkStmt = $conn->prepare("SELECT id FROM staff WHERE username = ? OR svcNo = ?");
            $checkStmt->bind_param('ss', $username, $serviceNumber);
            $checkStmt->execute();
            $existing = $checkStmt->get_result();
            
            if ($existing->num_rows > 0) {
                $errors['svcNo'] = 'Service number already exists in the system';
            } else {
                // Prepare comprehensive data for insertion
                $insertData = [
                    // Required Personal Information
                    'fName' => trim($_POST['fname']),
                    'lName' => trim($_POST['lname']),
                    'email' => trim($_POST['email']),
                    'tel' => trim($_POST['phone']),
                    'DOB' => $_POST['DOB'],
                    'gender' => $_POST['gender'],
                    
                    // Service Information
                    'svcNo' => $serviceNumber,
                    'category' => $_POST['category'],
                    'rankId' => $_POST['rankID'],
                    'svcStatus' => 'Active',
                    
                    // Location Information
                    'province' => $_POST['province'],
                    'district' => $_POST['district'],
                    'religion' => $_POST['religion'],
                    'village' => $_POST['village'],
                    
                    // User Account Fields
                    'username' => $username,
                    'password' => $hashedPassword,
                    'role' => 'user',
                    'accStatus' => 'Active',
                    'createdBy' => $_SESSION['user_id'] ?? $_SESSION['userID'] ?? 1,
                    'isFirstLogin' => 1
                ];
                
                // Add optional personal fields
                if (!empty($_POST['prefix'])) $insertData['prefix'] = trim($_POST['prefix']);
                if (!empty($_POST['initials'])) $insertData['initials'] = trim($_POST['initials']);
                if (!empty($_POST['titles'])) $insertData['titles'] = trim($_POST['titles']);
                if (!empty($_POST['blood_group'])) $insertData['bloodGp'] = trim($_POST['blood_group']);
                if (!empty($_POST['height'])) $insertData['height'] = trim($_POST['height']);
                if (!empty($_POST['maritalStatus']) || !empty($_POST['marital'])) {
                    $insertData['marital'] = trim($_POST['maritalStatus'] ?? $_POST['marital']);
                }
                if (!empty($_POST['address'])) $insertData['address'] = trim($_POST['address']);
                
                // Add identification documents
                if (!empty($_POST['nrc']) || !empty($_POST['NRC'])) {
                    $insertData['NRC'] = trim($_POST['nrc'] ?? $_POST['NRC']);
                }
                if (!empty($_POST['passport'])) $insertData['passPort'] = trim($_POST['passport']);
                if (!empty($_POST['passport_expiry'])) $insertData['passExp'] = $_POST['passport_expiry'];
                if (!empty($_POST['digitalId'])) $insertData['digitalId'] = trim($_POST['digitalId']);
                
                // Add service details
                if (!empty($_POST['unitID']) || !empty($_POST['unitId'])) {
                    $insertData['unitId'] = trim($_POST['unitID'] ?? $_POST['unitId']);
                }
                if (!empty($_POST['corps']) || !empty($_POST['corpsId'])) {
                    $insertData['corpsId'] = trim($_POST['corps'] ?? $_POST['corpsId']);
                }
                if (!empty($_POST['apptId'])) $insertData['apptId'] = trim($_POST['apptId']);
                if (!empty($_POST['attestDate'])) $insertData['attestDate'] = $_POST['attestDate'];
                if (!empty($_POST['intake'])) $insertData['intake'] = trim($_POST['intake']);
                if (!empty($_POST['trade'])) $insertData['trade'] = trim($_POST['trade']);
                if (!empty($_POST['profession'])) $insertData['profession'] = trim($_POST['profession']);
                if (!empty($_POST['unitAtt'])) $insertData['unitAtt'] = trim($_POST['unitAtt']);
                
                // Add uniform/sizing information
                if (!empty($_POST['bootSize'])) $insertData['bootSize'] = trim($_POST['bootSize']);
                if (!empty($_POST['shoeSize'])) $insertData['shoeSize'] = trim($_POST['shoeSize']);
                if (!empty($_POST['hDress'])) $insertData['hDress'] = trim($_POST['hDress']);
                if (!empty($_POST['combatSize'])) $insertData['combatSize'] = trim($_POST['combatSize']);
                
                // Add rank progression fields
                if (!empty($_POST['subRank'])) $insertData['subRank'] = trim($_POST['subRank']);
                if (!empty($_POST['subWef'])) $insertData['subWef'] = $_POST['subWef'];
                if (!empty($_POST['tempRank'])) $insertData['tempRank'] = trim($_POST['tempRank']);
                if (!empty($_POST['tempWef'])) $insertData['tempWef'] = $_POST['tempWef'];
                if (!empty($_POST['localRank'])) $insertData['localRank'] = trim($_POST['localRank']);
                if (!empty($_POST['localWef'])) $insertData['localWef'] = $_POST['localWef'];
                
                // Add Next of Kin information
                if (!empty($_POST['nokName']) || !empty($_POST['nok'])) {
                    $insertData['nok'] = trim($_POST['nokName'] ?? $_POST['nok']);
                }
                if (!empty($_POST['nokNRC']) || !empty($_POST['nokNrc'])) {
                    $insertData['nokNrc'] = trim($_POST['nokNRC'] ?? $_POST['nokNrc']);
                }
                if (!empty($_POST['nokRelationship']) || !empty($_POST['nokRelat'])) {
                    $insertData['nokRelat'] = trim($_POST['nokRelationship'] ?? $_POST['nokRelat']);
                }
                if (!empty($_POST['nokPhone']) || !empty($_POST['nokTel']) || !empty($_POST['nok_tel'])) {
                    $insertData['nokTel'] = trim($_POST['nokPhone'] ?? $_POST['nokTel'] ?? $_POST['nok_tel']);
                }
                
                // Add Alternate Next of Kin information
                if (!empty($_POST['altNokName']) || !empty($_POST['altNok'])) {
                    $insertData['altNok'] = trim($_POST['altNokName'] ?? $_POST['altNok']);
                }
                if (!empty($_POST['altNokNrc'])) $insertData['altNokNrc'] = trim($_POST['altNokNrc']);
                if (!empty($_POST['altNokRelat'])) $insertData['altNokRelat'] = trim($_POST['altNokRelat']);
                if (!empty($_POST['altNokTel']) || !empty($_POST['alt_nok_tel'])) {
                    $insertData['altNokTel'] = trim($_POST['altNokTel'] ?? $_POST['alt_nok_tel']);
                }
                
                // Add profile photo if uploaded
                if (!empty($_POST['profilePhoto'])) $insertData['profilePhoto'] = trim($_POST['profilePhoto']);
                
                // Add renewal date if provided
                if (!empty($_POST['renewDate'])) $insertData['renewDate'] = $_POST['renewDate'];
                
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
                        'fName' => trim($_POST['fname']),
                        'lName' => trim($_POST['lname']),
                        'username' => $username,
                        'email' => trim($_POST['email']),
                        'svcNo' => trim($_POST['svcNo'])
                    ];
                    
                    // Get rank name for email (rank table only has rankId and level)
                    if (!empty($_POST['rankID'])) {
                        $rankStmt = $conn->prepare("SELECT rankId as rankName FROM rank WHERE rankId = ?");
                        $rankStmt->bind_param("s", $_POST['rankID']);
                        $rankStmt->execute();
                        $rankResult = $rankStmt->get_result();
                        if ($rankRow = $rankResult->fetch_assoc()) {
                            $staffData['rank_name'] = $rankRow['rankName'];
                        }
                        $rankStmt->close();
                    }
                    
                    // Send welcome email with credentials (with development/production awareness)
                    try {
                        $mailer = new ARMISMailer();
                        $emailResult = $mailer->sendWelcomeEmail($staffData, $tempPassword);
                        
                        // Determine appropriate success message based on environment and email result
                        if ($emailResult['success']) {
                            $mode = $emailResult['mode'] ?? 'unknown';
                            
                            if ($mode === 'development') {
                                // Development mode: Credentials shown on screen
                                $_SESSION['success_message'] = "✅ Staff member successfully created! (Development Mode: Email logged, credentials displayed below)";
                                error_log("DEVELOPMENT: Welcome email logged for: " . $staffData['email']);
                            } elseif (isset($emailResult['sent']) && $emailResult['sent'] === true) {
                                // Production mode: Email sent successfully
                                $_SESSION['success_message'] = "✅ Staff member successfully created! Login credentials have been sent to " . $staffData['email'];
                                error_log("PRODUCTION: Welcome email sent successfully to: " . $staffData['email']);
                            } else {
                                // Production mode: Email logged but not sent (mail server issue)
                                $_SESSION['success_message'] = "✅ Staff member successfully created! Note: Email credentials are displayed below (mail server may need configuration)";
                                error_log("PRODUCTION: Welcome email logged but not sent to: " . $staffData['email']);
                            }
                        } else {
                            // Fallback: Staff created but email had issues
                            $_SESSION['success_message'] = "✅ Staff member successfully created! Login credentials are displayed below.";
                            error_log("Email notification skipped: " . ($emailResult['message'] ?? 'Unknown reason'));
                        }
                    } catch (Exception $emailError) {
                        // Exception caught: Staff still created successfully
                        $_SESSION['success_message'] = "✅ Staff member successfully created! Login credentials are displayed below.";
                        error_log("Email exception (non-critical): " . $emailError->getMessage());
                    }
                    
                    // Store temporary credentials in session for display
                    $_SESSION['temp_password'] = $tempPassword;
                    $_SESSION['username'] = $username;
                    $_SESSION['staff_name'] = trim($_POST['fname']) . ' ' . trim($_POST['lname']);
                    $_SESSION['staff_email'] = trim($_POST['email']);
                    $_SESSION['created_staff_id'] = $staffId; // Store staff ID for view profile button
                    
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
