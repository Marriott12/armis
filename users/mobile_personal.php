<?php
/**
 * Mobile-Enhanced Personal Information Form
 * Optimized for mobile devices with touch-friendly interface
 */

session_start();
require_once dirname(__DIR__) . '/shared/database_connection.php';
require_once 'profile_manager.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit();
}

$profileManager = new UserProfileManager($_SESSION['user_id']);
$personalInfo = $profileManager->getUserProfile();
$contactInfo = $profileManager->getContactInfo();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $result = ['success' => false, 'message' => ''];
    
    try {
        // Handle personal information update
        if (isset($_POST['action']) && $_POST['action'] === 'update_personal') {
            $personalData = [
                'fName' => $_POST['fName'] ?? '',
                'lName' => $_POST['lName'] ?? '',
                'DOB' => $_POST['dob'] ?? '',
                'gender' => $_POST['gender'] ?? '',
                'marital_status' => $_POST['marital_status'] ?? '',
                'tel' => $_POST['phone'] ?? '',
                'email' => $_POST['email'] ?? ''
            ];

            $result = $profileManager->updatePersonalInfo($personalData);
        }
        
        // Handle contact information update
        if (isset($_POST['action']) && $_POST['action'] === 'update_contact') {
            $contactData = [];
            
            if (!empty($_POST['contact_types'])) {
                foreach ($_POST['contact_types'] as $index => $type) {
                    if (!empty($_POST['contact_values'][$index])) {
                        $contactData[] = [
                            'contact_type' => $type,
                            'contact_value' => $_POST['contact_values'][$index],
                            'contact_name' => $_POST['contact_names'][$index] ?? '',
                            'relationship' => $_POST['contact_relationships'][$index] ?? '',
                            'is_primary' => !empty($_POST['contact_primary'][$index]) ? 1 : 0
                        ];
                    }
                }
            }
            
            $result = $profileManager->updateContactInfo($contactData);
        }
        
    } catch (Exception $e) {
        $result = ['success' => false, 'message' => 'Error processing request: ' . $e->getMessage()];
    }
    
    // Return JSON response for AJAX requests
    if (isset($_POST['ajax'])) {
        header('Content-Type: application/json');
        echo json_encode($result);
        exit();
    }
}

// Refresh data after update
$personalInfo = $profileManager->getUserProfile();
$contactInfo = $profileManager->getContactInfo();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <title>Personal Information - Mobile - ARMIS</title>
    
    <!-- Enhanced mobile styling -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    
    <style>
        :root {
            --military-green: #4a5d23;
            --military-tan: #c19b5b;
            --touch-target: 44px;
        }
        
        body {
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            padding-bottom: 80px; /* Space for floating action button */
        }
        
        /* Mobile-first navigation */
        .mobile-header {
            background: linear-gradient(135deg, var(--military-green), var(--military-tan));
            color: white;
            padding: 1rem 0;
            position: sticky;
            top: 0;
            z-index: 1000;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .mobile-header h1 {
            font-size: 1.2rem;
            margin: 0;
        }
        
        /* Touch-friendly form elements */
        .form-control, .form-select {
            min-height: var(--touch-target);
            font-size: 16px; /* Prevents zoom on iOS */
            border-radius: 12px;
            border: 2px solid #e9ecef;
            transition: all 0.3s ease;
        }
        
        .form-control:focus, .form-select:focus {
            border-color: var(--military-green);
            box-shadow: 0 0 0 0.2rem rgba(74, 93, 35, 0.25);
        }
        
        /* Mobile-optimized cards */
        .mobile-card {
            background: white;
            border-radius: 20px;
            margin: 1rem;
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
            overflow: hidden;
            border: none;
        }
        
        .mobile-card-header {
            background: linear-gradient(135deg, var(--military-green), var(--military-tan));
            color: white;
            padding: 1rem 1.5rem;
            font-weight: bold;
        }
        
        .mobile-card-body {
            padding: 1.5rem;
        }
        
        /* Touch-friendly buttons */
        .btn {
            min-height: var(--touch-target);
            border-radius: 12px;
            font-weight: 500;
            letter-spacing: 0.5px;
        }
        
        .btn-lg {
            min-height: 56px;
            font-size: 1.1rem;
        }
        
        /* Floating action button */
        .fab {
            position: fixed;
            bottom: 20px;
            right: 20px;
            width: 56px;
            height: 56px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--military-green), var(--military-tan));
            color: white;
            border: none;
            box-shadow: 0 4px 20px rgba(0,0,0,0.3);
            z-index: 1001;
            transition: all 0.3s ease;
        }
        
        .fab:hover {
            transform: scale(1.1);
            box-shadow: 0 6px 25px rgba(0,0,0,0.4);
        }
        
        /* Accordion-style sections for mobile */
        .accordion-button {
            min-height: var(--touch-target);
            background: var(--military-green);
            color: white;
            border: none;
            font-weight: bold;
        }
        
        .accordion-button:not(.collapsed) {
            background: var(--military-tan);
            color: white;
        }
        
        .accordion-button:focus {
            box-shadow: 0 0 0 0.2rem rgba(74, 93, 35, 0.25);
        }
        
        /* Contact item styling */
        .contact-item {
            background: #f8f9fa;
            border-radius: 15px;
            padding: 1rem;
            margin-bottom: 1rem;
            border: 1px solid #e9ecef;
        }
        
        .contact-item.primary {
            background: linear-gradient(135deg, rgba(74, 93, 35, 0.1), rgba(193, 155, 91, 0.1));
            border-color: var(--military-green);
        }
        
        /* Progress indicators */
        .progress-step {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            background: var(--military-green);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            margin-right: 10px;
        }
        
        /* Swipe gestures hints */
        .swipe-hint {
            font-size: 0.8rem;
            color: #6c757d;
            text-align: center;
            margin-top: 0.5rem;
        }
        
        /* Responsive adjustments */
        @media (max-width: 576px) {
            .container-fluid {
                padding: 0;
            }
            
            .mobile-card {
                margin: 0.5rem;
                border-radius: 15px;
            }
            
            .row {
                margin: 0;
            }
            
            .col-12 {
                padding: 0;
            }
        }
        
        /* Input groups for better mobile UX */
        .input-group {
            margin-bottom: 1rem;
        }
        
        .input-group-text {
            background: var(--military-green);
            color: white;
            border: none;
            min-width: 50px;
            justify-content: center;
        }
        
        /* Loading spinner */
        .spinner {
            border: 3px solid #f3f3f3;
            border-top: 3px solid var(--military-green);
            border-radius: 50%;
            width: 20px;
            height: 20px;
            animation: spin 1s linear infinite;
            display: inline-block;
            margin-right: 10px;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
    <!-- Mobile Header -->
    <div class="mobile-header">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-2">
                    <a href="index.php" class="text-white">
                        <i class="fas fa-arrow-left fa-lg"></i>
                    </a>
                </div>
                <div class="col-8 text-center">
                    <h1><i class="fas fa-user-edit me-2"></i>Personal Info</h1>
                </div>
                <div class="col-2 text-end">
                    <button class="btn btn-link text-white p-0" onclick="toggleHelp()">
                        <i class="fas fa-question-circle fa-lg"></i>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div class="container-fluid">
        <!-- Progress indicator -->
        <div class="d-flex justify-content-center align-items-center p-3">
            <div class="progress-step">1</div>
            <div class="flex-fill">
                <div class="progress" style="height: 8px;">
                    <div class="progress-bar" role="progressbar" style="width: 60%" aria-valuenow="60" aria-valuemin="0" aria-valuemax="100"></div>
                </div>
            </div>
            <small class="text-muted ms-2">60% Complete</small>
        </div>

        <!-- Mobile-optimized accordion sections -->
        <div class="accordion" id="mobileFormAccordion">
            <!-- Basic Information Section -->
            <div class="accordion-item mobile-card">
                <h2 class="accordion-header" id="basicInfoHeading">
                    <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#basicInfo">
                        <i class="fas fa-user me-2"></i>Basic Information
                    </button>
                </h2>
                <div id="basicInfo" class="accordion-collapse collapse show" data-bs-parent="#mobileFormAccordion">
                    <div class="accordion-body">
                        <form id="personalForm">
                            <input type="hidden" name="action" value="update_personal">
                            <input type="hidden" name="ajax" value="1">
                            
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fas fa-user"></i></span>
                                        <input type="text" class="form-control" name="fName" 
                                               placeholder="First Name" value="<?php echo htmlspecialchars($personalInfo->fName ?? ''); ?>">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fas fa-user"></i></span>
                                        <input type="text" class="form-control" name="lName" 
                                               placeholder="Last Name" value="<?php echo htmlspecialchars($personalInfo->lName ?? ''); ?>">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fas fa-calendar"></i></span>
                                        <input type="date" class="form-control" name="dob" 
                                               value="<?php echo $personalInfo->dob ?? ''; ?>">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fas fa-venus-mars"></i></span>
                                        <select class="form-select" name="gender">
                                            <option value="">Select Gender</option>
                                            <option value="Male" <?php echo ($personalInfo->gender ?? '') === 'Male' ? 'selected' : ''; ?>>Male</option>
                                            <option value="Female" <?php echo ($personalInfo->gender ?? '') === 'Female' ? 'selected' : ''; ?>>Female</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fas fa-phone"></i></span>
                                        <input type="tel" class="form-control" name="phone" 
                                               placeholder="Phone Number" value="<?php echo htmlspecialchars($personalInfo->phone ?? ''); ?>">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                                        <input type="email" class="form-control" name="email" 
                                               placeholder="Email Address" value="<?php echo htmlspecialchars($personalInfo->email ?? ''); ?>">
                                    </div>
                                </div>
                            </div>
                            
                            <div class="d-grid mt-3">
                                <button type="submit" class="btn btn-success btn-lg">
                                    <i class="fas fa-save me-2"></i>Save Basic Info
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Military Sizing Section -->
            <div class="accordion-item mobile-card">
                <h2 class="accordion-header" id="sizingHeading">
                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#sizing">
                        <i class="fas fa-ruler me-2"></i>Military Sizing
                    </button>
                </h2>
                <div id="sizing" class="accordion-collapse collapse" data-bs-parent="#mobileFormAccordion">
                    <div class="accordion-body">
                        <form id="sizingForm">
                            <input type="hidden" name="action" value="update_personal">
                            <input type="hidden" name="ajax" value="1">
                            
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fas fa-shoe-prints"></i></span>
                                        <select class="form-select" name="combatSize">
                                            <option value="">Combat Size</option>
                                            <?php 
                                            $combatSizes = [
                                                'XS' => 'Extra Small (XS)',
                                                'S' => 'Small (S)', 
                                                'M' => 'Medium (M)',
                                                'L' => 'Large (L)',
                                                'XL' => 'Extra Large (XL)',
                                                'XXL' => '2X Large (XXL)',
                                                '3XL' => '3X Large (3XL)',
                                                '4XL' => '4X Large (4XL)'
                                            ];
                                            foreach ($combatSizes as $value => $label): ?>
                                                <option value="<?php echo $value; ?>" <?php echo ($personalInfo->combatSize ?? '') === $value ? 'selected' : ''; ?>><?php echo $label; ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fas fa-shoe-prints"></i></span>
                                        <select class="form-select" name="bsize">
                                            <option value="">Boot Size</option>
                                            <?php for($i = 6; $i <= 14; $i += 0.5): ?>
                                                <option value="<?php echo $i; ?>" <?php echo ($personalInfo->bsize ?? '') == $i ? 'selected' : ''; ?>><?php echo $i; ?></option>
                                            <?php endfor; ?>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fas fa-shoe-prints"></i></span>
                                        <select class="form-select" name="ssize">
                                            <option value="">Staff Shoe Size</option>
                                            <?php for($i = 6; $i <= 14; $i += 0.5): ?>
                                                <option value="<?php echo $i; ?>" <?php echo ($personalInfo->ssize ?? '') == $i ? 'selected' : ''; ?>><?php echo $i; ?></option>
                                            <?php endfor; ?>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fas fa-hard-hat"></i></span>
                                        <select class="form-select" name="hdress">
                                            <option value="">Head Dress Size</option>
                                            <option value="XS" <?php echo ($personalInfo->hdress ?? '') === 'XS' ? 'selected' : ''; ?>>Extra Small (XS)</option>
                                            <option value="S" <?php echo ($personalInfo->hdress ?? '') === 'S' ? 'selected' : ''; ?>>Small (S)</option>
                                            <option value="M" <?php echo ($personalInfo->hdress ?? '') === 'M' ? 'selected' : ''; ?>>Medium (M)</option>
                                            <option value="L" <?php echo ($personalInfo->hdress ?? '') === 'L' ? 'selected' : ''; ?>>Large (L)</option>
                                            <option value="XL" <?php echo ($personalInfo->hdress ?? '') === 'XL' ? 'selected' : ''; ?>>Extra Large (XL)</option>
                                            <option value="XXL" <?php echo ($personalInfo->hdress ?? '') === 'XXL' ? 'selected' : ''; ?>>2X Large (XXL)</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="d-grid mt-3">
                                <button type="submit" class="btn btn-warning btn-lg">
                                    <i class="fas fa-save me-2"></i>Save Sizing Info
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Contact Information Section -->
            <div class="accordion-item mobile-card">
                <h2 class="accordion-header" id="contactHeading">
                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#contact">
                        <i class="fas fa-address-book me-2"></i>Contact Information
                    </button>
                </h2>
                <div id="contact" class="accordion-collapse collapse" data-bs-parent="#mobileFormAccordion">
                    <div class="accordion-body">
                        <form id="contactForm">
                            <input type="hidden" name="action" value="update_contact">
                            <input type="hidden" name="ajax" value="1">
                            <div class="d-flex justify-content-between align-items-start mb-3">
                                <div>
                                    <h5 class="mb-0">Contact Information</h5>
                                    <p class="small text-muted mb-0">Add emergency, family, and other contact details.</p>
                                </div>
                                <button type="button" class="btn btn-outline-secondary btn-sm" onclick="addContactRow()">
                                    <i class="fas fa-plus me-1"></i>Add Contact
                                </button>
                            </div>

                            <div id="mobileContactRows">
                                <?php if (!empty($contactInfo)): ?>
                                    <?php foreach ($contactInfo as $index => $contact): ?>
                                        <div class="card card-body mb-3 p-3 contact-row">
                                            <input type="hidden" name="contact_ids[<?= $index ?>]" value="<?= htmlspecialchars($contact->id ?? '') ?>">
                                            <input type="hidden" name="contact_verified[<?= $index ?>]" value="<?= $contact->is_verified ? 1 : 0 ?>">
                                            <input type="hidden" name="contact_notes[<?= $index ?>]" value="<?= htmlspecialchars($contact->notes ?? '') ?>">
                                            <div class="row g-2">
                                                <div class="col-12 col-md-6">
                                                    <label class="form-label small">Type</label>
                                                    <select class="form-select" name="contact_types[]">
                                                        <option value="">Select Type</option>
                                                        <option value="Mobile" <?= $contact->contact_type === 'Mobile' ? 'selected' : '' ?>>Mobile</option>
                                                        <option value="Home" <?= $contact->contact_type === 'Home' ? 'selected' : '' ?>>Home</option>
                                                        <option value="Work" <?= $contact->contact_type === 'Work' ? 'selected' : '' ?>>Work</option>
                                                        <option value="Email" <?= $contact->contact_type === 'Email' ? 'selected' : '' ?>>Email</option>
                                                        <option value="Emergency" <?= $contact->contact_type === 'Emergency' ? 'selected' : '' ?>>Emergency</option>
                                                    </select>
                                                </div>
                                                <div class="col-12 col-md-6">
                                                    <label class="form-label small">Value</label>
                                                    <input type="text" class="form-control" name="contact_values[]" value="<?= htmlspecialchars($contact->contact_value ?? '') ?>" placeholder="Contact value">
                                                </div>
                                                <div class="col-12 col-md-6">
                                                    <label class="form-label small">Name</label>
                                                    <input type="text" class="form-control" name="contact_names[]" value="<?= htmlspecialchars($contact->contact_name ?? '') ?>" placeholder="Contact name">
                                                </div>
                                                <div class="col-12 col-md-6">
                                                    <label class="form-label small">Relationship</label>
                                                    <input type="text" class="form-control" name="contact_relationships[]" value="<?= htmlspecialchars($contact->relationship ?? '') ?>" placeholder="Relationship">
                                                </div>
                                                <div class="col-12 d-flex align-items-center justify-content-between">
                                                    <div class="form-check form-switch">
                                                        <input type="hidden" name="contact_primary[<?= $index ?>]" value="0">
                                                        <input class="form-check-input" type="checkbox" name="contact_primary[<?= $index ?>]" value="1" <?= $contact->is_primary ? 'checked' : '' ?> id="contactPrimary<?= $index ?>">
                                                        <label class="form-check-label" for="contactPrimary<?= $index ?>">Primary contact</label>
                                                    </div>
                                                    <button type="button" class="btn btn-sm btn-danger" onclick="removeContactRow(this)">
                                                        <i class="fas fa-trash"></i> Remove
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <div class="card card-body mb-3 p-3 contact-row">
                                        <input type="hidden" name="contact_ids[0]" value="">
                                        <input type="hidden" name="contact_verified[0]" value="0">
                                        <input type="hidden" name="contact_notes[0]" value="">
                                        <div class="row g-2">
                                            <div class="col-12 col-md-6">
                                                <label class="form-label small">Type</label>
                                                <select class="form-select" name="contact_types[]">
                                                    <option value="">Select Type</option>
                                                    <option value="Mobile">Mobile</option>
                                                    <option value="Home">Home</option>
                                                    <option value="Work">Work</option>
                                                    <option value="Email">Email</option>
                                                    <option value="Emergency">Emergency</option>
                                                </select>
                                            </div>
                                            <div class="col-12 col-md-6">
                                                <label class="form-label small">Value</label>
                                                <input type="text" class="form-control" name="contact_values[]" placeholder="Contact value">
                                            </div>
                                            <div class="col-12 col-md-6">
                                                <label class="form-label small">Name</label>
                                                <input type="text" class="form-control" name="contact_names[]" placeholder="Contact name">
                                            </div>
                                            <div class="col-12 col-md-6">
                                                <label class="form-label small">Relationship</label>
                                                <input type="text" class="form-control" name="contact_relationships[]" placeholder="Relationship">
                                            </div>
                                            <div class="col-12 d-flex align-items-center justify-content-between">
                                                <div class="form-check form-switch">
                                                    <input type="hidden" name="contact_primary[0]" value="0">
                                                    <input class="form-check-input" type="checkbox" name="contact_primary[0]" value="1" id="contactPrimary0">
                                                    <label class="form-check-label" for="contactPrimary0">Primary contact</label>
                                                </div>
                                                <button type="button" class="btn btn-sm btn-danger" onclick="removeContactRow(this)">
                                                    <i class="fas fa-trash"></i> Remove
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <div class="d-grid mt-3">
                                <button type="submit" class="btn btn-success btn-lg">
                                    <i class="fas fa-save me-2"></i>Save Contacts
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Floating action button -->
    <button class="fab" onclick="scrollToTop()">
        <i class="fas fa-arrow-up"></i>
    </button>

    <!-- Core JS (jQuery/Bootstrap) are loaded centrally in shared/footer.php. -->
    <!-- Scripts -->
    <script>
        // Form submission handling
        document.getElementById('personalForm').addEventListener('submit', handleFormSubmit);
        document.getElementById('sizingForm').addEventListener('submit', handleFormSubmit);
        const contactForm = document.getElementById('contactForm');
        if (contactForm) {
            contactForm.addEventListener('submit', handleFormSubmit);
        }
        
        function handleFormSubmit(e) {
            e.preventDefault();
            const form = e.target;
            const submitBtn = form.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            
            // Show loading state
            submitBtn.innerHTML = '<div class="spinner"></div>Saving...';
            submitBtn.disabled = true;
            
            // Submit form data
            const formData = new FormData(form);
            
            fetch('mobile_personal.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showToast('Information saved successfully!', 'success');
                } else {
                    showToast(data.message || 'Error saving information', 'error');
                }
            })
            .catch(error => {
                showToast('Network error occurred', 'error');
            })
            .finally(() => {
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
            });
        }
        
        function showToast(message, type = 'info') {
            // Create toast element
            const toast = document.createElement('div');
            toast.className = `alert alert-${type === 'success' ? 'success' : 'danger'} alert-dismissible fade show position-fixed`;
            toast.style.cssText = 'top: 20px; right: 20px; z-index: 1055; min-width: 300px;';
            toast.innerHTML = `
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            `;
            
            document.body.appendChild(toast);
            
            // Auto-remove after 3 seconds
            setTimeout(() => {
                toast.remove();
            }, 3000);
        }
        
        function scrollToTop() {
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }
        
        function toggleHelp() {
            // Toggle help modal or overlay
            alert('Help: Use the accordion sections to organize your information. Tap each section to expand and edit your details.');
        }
        
        function openContactModal() {
            const contactHeader = document.querySelector('#contactHeading button');
            if (contactHeader && contactHeader.getAttribute('aria-expanded') !== 'true') {
                contactHeader.click();
            }
            const form = document.getElementById('contactForm');
            if (form) {
                form.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
        }

        function addContactRow(existing = null) {
            const container = document.getElementById('mobileContactRows');
            const index = container.querySelectorAll('.contact-row').length;
            const row = document.createElement('div');
            row.className = 'card card-body mb-3 p-3 contact-row';
            const type = existing?.contact_type || '';
            const value = existing?.contact_value || '';
            const name = existing?.contact_name || '';
            const relationship = existing?.relationship || '';
            const isPrimary = existing?.is_primary ? 'checked' : '';

            row.innerHTML = `
                <input type="hidden" name="contact_ids[${index}]" value="${existing?.id || ''}">
                <input type="hidden" name="contact_verified[${index}]" value="${existing?.is_verified ? 1 : 0}">
                <input type="hidden" name="contact_notes[${index}]" value="${existing?.notes || ''}">
                <div class="row g-2">
                    <div class="col-12 col-md-6">
                        <label class="form-label small">Type</label>
                        <select class="form-select" name="contact_types[]">
                            <option value="">Select Type</option>
                            <option value="Mobile" ${type === 'Mobile' ? 'selected' : ''}>Mobile</option>
                            <option value="Home" ${type === 'Home' ? 'selected' : ''}>Home</option>
                            <option value="Work" ${type === 'Work' ? 'selected' : ''}>Work</option>
                            <option value="Email" ${type === 'Email' ? 'selected' : ''}>Email</option>
                            <option value="Emergency" ${type === 'Emergency' ? 'selected' : ''}>Emergency</option>
                        </select>
                    </div>
                    <div class="col-12 col-md-6">
                        <label class="form-label small">Value</label>
                        <input type="text" class="form-control" name="contact_values[]" value="${value}" placeholder="Contact value">
                    </div>
                    <div class="col-12 col-md-6">
                        <label class="form-label small">Name</label>
                        <input type="text" class="form-control" name="contact_names[]" value="${name}" placeholder="Contact name">
                    </div>
                    <div class="col-12 col-md-6">
                        <label class="form-label small">Relationship</label>
                        <input type="text" class="form-control" name="contact_relationships[]" value="${relationship}" placeholder="Relationship">
                    </div>
                    <div class="col-12 d-flex align-items-center justify-content-between">
                        <div class="form-check form-switch">
                            <input type="hidden" name="contact_primary[${index}]" value="0">
                            <input class="form-check-input" type="checkbox" name="contact_primary[${index}]" value="1" ${isPrimary} id="contactPrimary${index}">
                            <label class="form-check-label" for="contactPrimary${index}">Primary contact</label>
                        </div>
                        <button type="button" class="btn btn-sm btn-danger" onclick="removeContactRow(this)">
                            <i class="fas fa-trash"></i> Remove
                        </button>
                    </div>
                </div>
            `;
            container.appendChild(row);
            reindexContactRows();
        }

        function removeContactRow(button) {
            const row = button.closest('.contact-row');
            if (row) {
                row.remove();
                reindexContactRows();
            }
        }

        function reindexContactRows() {
            const rows = document.querySelectorAll('#mobileContactRows .contact-row');
            rows.forEach((row, index) => {
                const idInput = row.querySelector('[name^="contact_ids["]');
                const verifiedInput = row.querySelector('[name^="contact_verified["]');
                const notesInput = row.querySelector('[name^="contact_notes["]');
                const primaryInputs = row.querySelectorAll('[name^="contact_primary["]');
                if (idInput) idInput.name = `contact_ids[${index}]`;
                if (verifiedInput) verifiedInput.name = `contact_verified[${index}]`;
                if (notesInput) notesInput.name = `contact_notes[${index}]`;
                primaryInputs.forEach(input => input.name = `contact_primary[${index}]`);
            });
        }

        // Progressive Web App features
        if ('serviceWorker' in navigator) {
            navigator.serviceWorker.register('/Armis2/sw.js');
        }
        
        // Touch gestures
        let touchStartY = 0;
        document.addEventListener('touchstart', e => {
            touchStartY = e.touches[0].clientY;
        });
        
        document.addEventListener('touchend', e => {
            const touchEndY = e.changedTouches[0].clientY;
            const diff = touchStartY - touchEndY;
            
            // Pull to refresh gesture
            if (diff < -100 && window.scrollY === 0) {
                location.reload();
            }
        });
    </script>
</body>
</html>
