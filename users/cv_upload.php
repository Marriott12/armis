<?php
require_once '../shared/session_init.php';
require_once 'profile_manager.php';

// Ensure user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

$profileManager = new UserProfileManager($_SESSION['user_id']);
$cvData = null;
$errors = [];
$success = '';

// Handle CV upload
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['upload_cv']) && isset($_FILES['cv_file'])) {
        try {
            $result = $profileManager->uploadCV($_FILES['cv_file']);
            if ($result['success']) {
                $success = "CV uploaded successfully!";
                // Use the extracted data returned from uploadCV
                $cvData = $result['extracted_data'];
                $cvData['filename'] = $result['filename'];
                $cvData['id'] = $result['id'];
                
                // Debug: Show what data was extracted
                error_log("CV Data extracted: " . print_r($cvData, true));
                
                // Add some test data if nothing was extracted (for demonstration)
                if (empty($cvData['personal']) && empty($cvData['contact']) && empty($cvData['education']) && empty($cvData['skills'])) {
                    $cvData['personal'] = ['full_name' => 'Data extraction in progress...'];
                    $cvData['contact'] = ['note' => 'CV text parsing may require manual review'];
                    $success = "CV uploaded successfully! Please review and edit the extracted information below.";
                }
            } else {
                $errors[] = $result['message'];
            }
        } catch (Exception $e) {
            $errors[] = "Error uploading CV: " . $e->getMessage();
        }
    }
    
    if (isset($_POST['apply_cv_data']) && isset($_POST['cv_id'])) {
        try {
            $result = $profileManager->applyCVData($_POST['cv_id'], $_POST);
            if ($result['success']) {
                $success = "CV data has been successfully applied to your profile!";
                // Redirect to profile page after successful application
                header('Location: personal.php?cv_applied=1');
                exit;
            } else {
                $errors[] = $result['message'];
            }
        } catch (Exception $e) {
            $errors[] = "Error applying CV data: " . $e->getMessage();
        }
    }
}

// Get existing CV data if available
if (!$cvData && isset($_GET['review']) && $_GET['review']) {
    // Try to get specific CV data if cv_id is provided
    if (isset($_GET['cv_id'])) {
        try {
            $cvResult = $profileManager->getCVData($_GET['cv_id']);
            if ($cvResult) {
                $cvData = $cvResult->extracted_data;
                $cvData['filename'] = $cvResult->filename;
                $cvData['id'] = $_GET['cv_id'];
            }
        } catch (Exception $e) {
            $errors[] = "Error loading CV data: " . $e->getMessage();
        }
    } else {
        // Try to get the most recent CV data
        try {
            $recentCVs = $profileManager->getUserCVs();
            if (!empty($recentCVs)) {
                $recentCV = $recentCVs[0]; // Get the most recent CV
                $cvResult = $profileManager->getCVData($recentCV->id);
                if ($cvResult) {
                    $cvData = $cvResult->extracted_data;
                    $cvData['filename'] = $cvResult->filename;
                    $cvData['id'] = $recentCV->id;
                }
            }
        } catch (Exception $e) {
            $errors[] = "Error loading CV data: " . $e->getMessage();
        }
    }
}

$pageTitle = "CV Upload & Verification";
include '../shared/header.php';
?>

<div class="container mt-4">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2><i class="fas fa-file-upload"></i> CV Upload & Verification</h2>
                <a href="index.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Back to Profile
                </a>
            </div>
        </div>
    </div>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger">
            <h6><i class="fas fa-exclamation-triangle"></i> Error(s) occurred:</h6>
            <ul class="mb-0">
                <?php foreach ($errors as $error): ?>
                    <li><?= htmlspecialchars($error ?? 'Unknown error') ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i> <?= htmlspecialchars($success ?? 'Operation completed successfully') ?>
        </div>
    <?php endif; ?>

    <?php if (!$cvData): ?>
    <!-- CV Upload Form -->
    <div class="row">
        <div class="col-md-8 mx-auto">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="fas fa-cloud-upload-alt"></i> Upload Your CV</h5>
                </div>
                <div class="card-body">
                    <p class="text-muted mb-4">
                        Upload your CV in PDF or Word format. Our system will extract information from your CV 
                        and allow you to review and verify it before applying to your profile.
                    </p>
                    
                    <form method="POST" enctype="multipart/form-data" id="cvUploadForm">
                        <div class="mb-3">
                            <label for="cv_file" class="form-label">Select CV File</label>
                            <input type="file" class="form-control" id="cv_file" name="cv_file" 
                                   accept=".pdf,.doc,.docx" required>
                            <div class="form-text">
                                Supported formats: PDF, Word (.doc, .docx). Maximum size: 10MB
                            </div>
                        </div>
                        
                        <div class="d-grid">
                            <button type="submit" name="upload_cv" class="btn btn-primary btn-lg">
                                <i class="fas fa-upload"></i> Upload & Extract Data
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            
            <div class="card mt-4">
                <div class="card-body text-center">
                    <h6>What information will be extracted?</h6>
                    <div class="row mt-3">
                        <div class="col-md-6">
                            <ul class="list-unstyled text-start">
                                <li><i class="fas fa-check text-success"></i> Personal Information</li>
                                <li><i class="fas fa-check text-success"></i> Contact Details</li>
                                <li><i class="fas fa-check text-success"></i> Education History</li>
                                <li><i class="fas fa-check text-success"></i> Work Experience</li>
                            </ul>
                        </div>
                        <div class="col-md-6">
                            <ul class="list-unstyled text-start">
                                <li><i class="fas fa-check text-success"></i> Skills & Competencies</li>
                                <li><i class="fas fa-check text-success"></i> Certifications</li>
                                <li><i class="fas fa-check text-success"></i> Languages</li>
                                <li><i class="fas fa-check text-success"></i> Professional Summary</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <?php else: ?>
    <!-- CV Data Verification Form -->
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header bg-success text-white">
                    <h5 class="mb-0"><i class="fas fa-check-double"></i> Review & Verify CV Data</h5>
                </div>
                <div class="card-body">
                    <p class="text-muted mb-4">
                        Please review the information extracted from your CV. You can edit any field before 
                        applying the data to your profile. Unchecked items will not be applied.
                    </p>
                    
                    <form method="POST" id="cvVerificationForm">
                        <input type="hidden" name="cv_id" value="<?= htmlspecialchars($cvData['id'] ?? '') ?>">
                        <input type="hidden" name="cv_filename" value="<?= htmlspecialchars($cvData['filename'] ?? '') ?>">
                        
                        <!-- Personal Information -->
                        <?php if (!empty($cvData['personal'])): ?>
                        <div class="card mb-4">
                            <div class="card-header">
                                <div class="form-check">
                                    <input class="form-check-input section-toggle" type="checkbox" 
                                           id="apply_personal" name="apply_sections[personal]" value="1" checked>
                                    <label class="form-check-label fw-bold" for="apply_personal">
                                        <i class="fas fa-user"></i> Personal Information
                                    </label>
                                </div>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <?php foreach ($cvData['personal'] as $key => $value): ?>
                                        <?php if (!empty($value)): ?>
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label"><?= ucwords(str_replace('_', ' ', $key)) ?></label>
                                            <input type="text" class="form-control" 
                                                   name="personal[<?= $key ?>]" 
                                                   value="<?= htmlspecialchars($value ?? '') ?>">
                                        </div>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <!-- Contact Information -->
                        <?php if (!empty($cvData['contact'])): ?>
                        <div class="card mb-4">
                            <div class="card-header">
                                <div class="form-check">
                                    <input class="form-check-input section-toggle" type="checkbox" 
                                           id="apply_contact" name="apply_sections[contact]" value="1" checked>
                                    <label class="form-check-label fw-bold" for="apply_contact">
                                        <i class="fas fa-address-book"></i> Contact Information
                                    </label>
                                </div>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <?php foreach ($cvData['contact'] as $key => $value): ?>
                                        <?php if (!empty($value)): ?>
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label"><?= ucwords(str_replace('_', ' ', $key)) ?></label>
                                            <input type="text" class="form-control" 
                                                   name="contact[<?= $key ?>]" 
                                                   value="<?= htmlspecialchars($value ?? '') ?>">
                                        </div>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <!-- Education -->
                        <?php if (!empty($cvData['education'])): ?>
                        <div class="card mb-4">
                            <div class="card-header">
                                <div class="form-check">
                                    <input class="form-check-input section-toggle" type="checkbox" 
                                           id="apply_education" name="apply_sections[education]" value="1" checked>
                                    <label class="form-check-label fw-bold" for="apply_education">
                                        <i class="fas fa-graduation-cap"></i> Education
                                    </label>
                                </div>
                            </div>
                            <div class="card-body">
                                <div id="educationList">
                                    <?php foreach ($cvData['education'] as $index => $edu): ?>
                                    <div class="border p-3 mb-3 rounded">
                                        <div class="row">
                                            <div class="col-md-4 mb-2">
                                                <label class="form-label">Institution</label>
                                                <input type="text" class="form-control" 
                                                       name="education[<?= $index ?>][institution]" 
                                                       value="<?= htmlspecialchars($edu['institution'] ?? '') ?>">
                                            </div>
                                            <div class="col-md-4 mb-2">
                                                <label class="form-label">Degree/Certificate</label>
                                                <input type="text" class="form-control" 
                                                       name="education[<?= $index ?>][degree]" 
                                                       value="<?= htmlspecialchars($edu['degree'] ?? '') ?>">
                                            </div>
                                            <div class="col-md-4 mb-2">
                                                <label class="form-label">Year/Period</label>
                                                <input type="text" class="form-control" 
                                                       name="education[<?= $index ?>][year]" 
                                                       value="<?= htmlspecialchars($edu['year'] ?? '') ?>">
                                            </div>
                                            <?php if (!empty($edu['field'])): ?>
                                            <div class="col-md-6 mb-2">
                                                <label class="form-label">Field of Study</label>
                                                <input type="text" class="form-control" 
                                                       name="education[<?= $index ?>][field]" 
                                                       value="<?= htmlspecialchars($edu['field'] ?? '') ?>">
                                            </div>
                                            <?php endif; ?>
                                            <?php if (!empty($edu['grade'])): ?>
                                            <div class="col-md-6 mb-2">
                                                <label class="form-label">Grade/GPA</label>
                                                <input type="text" class="form-control" 
                                                       name="education[<?= $index ?>][grade]" 
                                                       value="<?= htmlspecialchars($edu['grade'] ?? '') ?>">
                                            </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <!-- Skills -->
                        <?php if (!empty($cvData['skills'])): ?>
                        <div class="card mb-4">
                            <div class="card-header">
                                <div class="form-check">
                                    <input class="form-check-input section-toggle" type="checkbox" 
                                           id="apply_skills" name="apply_sections[skills]" value="1" checked>
                                    <label class="form-check-label fw-bold" for="apply_skills">
                                        <i class="fas fa-cogs"></i> Skills & Competencies
                                    </label>
                                </div>
                            </div>
                            <div class="card-body">
                                <textarea class="form-control" name="skills" rows="4" 
                                          placeholder="Enter skills separated by commas"><?= htmlspecialchars(implode(', ', $cvData['skills'] ?? [])) ?></textarea>
                                <div class="form-text">Skills will be stored individually in the system</div>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <!-- Certifications -->
                        <?php if (!empty($cvData['certifications'])): ?>
                        <div class="card mb-4">
                            <div class="card-header">
                                <div class="form-check">
                                    <input class="form-check-input section-toggle" type="checkbox" 
                                           id="apply_certifications" name="apply_sections[certifications]" value="1" checked>
                                    <label class="form-check-label fw-bold" for="apply_certifications">
                                        <i class="fas fa-certificate"></i> Certifications
                                    </label>
                                </div>
                            </div>
                            <div class="card-body">
                                <div id="certificationsList">
                                    <?php foreach ($cvData['certifications'] as $index => $cert): ?>
                                    <div class="border p-3 mb-3 rounded">
                                        <div class="row">
                                            <div class="col-md-4 mb-2">
                                                <label class="form-label">Certification Name</label>
                                                <input type="text" class="form-control" 
                                                       name="certifications[<?= $index ?>][name]" 
                                                       value="<?= htmlspecialchars($cert['name'] ?? $cert) ?>">
                                            </div>
                                            <div class="col-md-4 mb-2">
                                                <label class="form-label">Issuing Organization</label>
                                                <input type="text" class="form-control" 
                                                       name="certifications[<?= $index ?>][issuer]" 
                                                       value="<?= htmlspecialchars($cert['issuer'] ?? '') ?>">
                                            </div>
                                            <div class="col-md-4 mb-2">
                                                <label class="form-label">Date Obtained</label>
                                                <input type="text" class="form-control" 
                                                       name="certifications[<?= $index ?>][date]" 
                                                       value="<?= htmlspecialchars($cert['date'] ?? '') ?>">
                                            </div>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <!-- Action Buttons -->
                        <div class="d-flex justify-content-between">
                            <a href="cv_upload.php" class="btn btn-outline-secondary">
                                <i class="fas fa-upload"></i> Upload Different CV
                            </a>
                            <div>
                                <button type="button" class="btn btn-warning me-2" onclick="selectAll(false)">
                                    <i class="fas fa-times"></i> Uncheck All
                                </button>
                                <button type="button" class="btn btn-info me-2" onclick="selectAll(true)">
                                    <i class="fas fa-check"></i> Check All
                                </button>
                                <button type="submit" name="apply_cv_data" class="btn btn-success btn-lg">
                                    <i class="fas fa-check-circle"></i> Apply Selected Data
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<script>
function selectAll(checked) {
    const checkboxes = document.querySelectorAll('.section-toggle');
    checkboxes.forEach(cb => cb.checked = checked);
    
    // Also enable/disable form fields
    toggleSectionFields();
}

function toggleSectionFields() {
    const checkboxes = document.querySelectorAll('.section-toggle');
    checkboxes.forEach(cb => {
        const card = cb.closest('.card');
        const inputs = card.querySelectorAll('input:not(.section-toggle), textarea, select');
        inputs.forEach(input => {
            input.disabled = !cb.checked;
            if (cb.checked) {
                input.style.opacity = '1';
            } else {
                input.style.opacity = '0.5';
            }
        });
    });
}

// Initialize field toggling
document.addEventListener('DOMContentLoaded', function() {
    // Add event listeners to section toggles
    const sectionToggles = document.querySelectorAll('.section-toggle');
    sectionToggles.forEach(cb => {
        cb.addEventListener('change', toggleSectionFields);
    });
    
    // Initial state
    toggleSectionFields();
});

// File upload validation
document.getElementById('cvUploadForm')?.addEventListener('submit', function(e) {
    const fileInput = document.getElementById('cv_file');
    if (fileInput.files.length === 0) {
        e.preventDefault();
        alert('Please select a CV file to upload');
        return;
    }
    
    const file = fileInput.files[0];
    if (file.size > 10 * 1024 * 1024) { // 10MB
        e.preventDefault();
        alert('File size must be less than 10MB');
        return;
    }
    
    const allowedTypes = ['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
    if (!allowedTypes.includes(file.type)) {
        e.preventDefault();
        alert('Please upload a PDF or Word document');
        return;
    }
});
</script>

<?php include '../shared/footer.php'; ?>
