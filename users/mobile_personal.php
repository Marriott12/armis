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
$personalInfo = $profileManager->getPersonalInfo();
$contactInfo = $profileManager->getContactInfo();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $result = ['success' => false, 'message' => ''];
    
    try {
        // Handle personal information update
        if (isset($_POST['action']) && $_POST['action'] === 'update_personal') {
            $personalData = [
                'first_name' => $_POST['first_name'] ?? '',
                'last_name' => $_POST['last_name'] ?? '',
                'dob' => $_POST['dob'] ?? '',
                'gender' => $_POST['gender'] ?? '',
                'marital_status' => $_POST['marital_status'] ?? '',
                'phone' => $_POST['phone'] ?? '',
                'email' => $_POST['email'] ?? '',
                'employment_date' => $_POST['employment_date'] ?? '',
                'combatSize' => $_POST['combatSize'] ?? '',
                'bsize' => $_POST['bsize'] ?? '',
                'ssize' => $_POST['ssize'] ?? '',
                'hdress' => $_POST['hdress'] ?? ''
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
                            'type' => $type,
                            'value' => $_POST['contact_values'][$index],
                            'contact_name' => $_POST['contact_names'][$index] ?? '',
                            'relationship' => $_POST['relationships'][$index] ?? '',
                            'is_primary' => isset($_POST['is_primary']) && $_POST['is_primary'] == $index
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
$personalInfo = $profileManager->getPersonalInfo();
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
                                        <input type="text" class="form-control" name="first_name" 
                                               placeholder="First Name" value="<?php echo htmlspecialchars($personalInfo->first_name ?? ''); ?>">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fas fa-user"></i></span>
                                        <input type="text" class="form-control" name="last_name" 
                                               placeholder="Last Name" value="<?php echo htmlspecialchars($personalInfo->last_name ?? ''); ?>">
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
                        <div id="contactList">
                            <?php if (!empty($contactInfo)): ?>
                                <?php foreach ($contactInfo as $index => $contact): ?>
                                    <div class="contact-item <?php echo $contact->is_primary ? 'primary' : ''; ?>">
                                        <div class="d-flex justify-content-between align-items-start mb-2">
                                            <strong><?php echo ucfirst($contact->contact_type); ?></strong>
                                            <?php if ($contact->is_primary): ?>
                                                <span class="badge bg-success">Primary</span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="mb-1">
                                            <i class="fas fa-phone me-2"></i><?php echo htmlspecialchars($contact->contact_value); ?>
                                        </div>
                                        <?php if ($contact->contact_name): ?>
                                            <div class="mb-1">
                                                <i class="fas fa-user me-2"></i><?php echo htmlspecialchars($contact->contact_name); ?>
                                            </div>
                                        <?php endif; ?>
                                        <?php if ($contact->relationship): ?>
                                            <div class="mb-1">
                                                <i class="fas fa-heart me-2"></i><?php echo htmlspecialchars($contact->relationship); ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="text-center text-muted py-4">
                                    <i class="fas fa-phone-slash fa-3x mb-3"></i>
                                    <p>No contact information added yet</p>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="d-grid">
                            <button type="button" class="btn btn-info btn-lg" onclick="openContactModal()">
                                <i class="fas fa-plus me-2"></i>Add/Edit Contacts
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Floating action button -->
    <button class="fab" onclick="scrollToTop()">
        <i class="fas fa-arrow-up"></i>
    </button>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Form submission handling
        document.getElementById('personalForm').addEventListener('submit', handleFormSubmit);
        document.getElementById('sizingForm').addEventListener('submit', handleFormSubmit);
        
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
            // For now, redirect to full contact form
            window.location.href = 'personal.php#contact-section';
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
