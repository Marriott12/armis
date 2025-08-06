<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ' . dirname($_SERVER['PHP_SELF']) . '/../login.php');
    exit();
}

// Load user profile data
require_once __DIR__ . '/profile_manager.php';

try {
    $profileManager = new UserProfileManager($_SESSION['user_id']);
    $userData = $profileManager->getUserProfile();
    $educationRecords = $profileManager->getEducationRecords();
    $trainingRecords = $profileManager->getTrainingRecords();
    $serviceRecord = $profileManager->getServiceHistory();
    $awards = $profileManager->getAwards();
    $deployments = $profileManager->getDeployments();
    $skills = $profileManager->getSkills();
    $contactInfo = $profileManager->getContactInfo();
    $familyMembers = $profileManager->getFamilyMembers();
    
    // Handle case where user profile doesn't exist
    if (!$userData) {
        $errorMessage = "Profile data not found. Please contact your administrator.";
    }
    
} catch (Exception $e) {
    error_log("CV Download error: " . $e->getMessage());
    $errorMessage = "Error loading profile data: " . $e->getMessage();
}

$pageTitle = "Download CV";
$moduleName = "User Profile";
$moduleIcon = "user";
$currentPage = "cv_download";

$sidebarLinks = [
    ['title' => 'My Profile', 'url' => '/Armis2/users/index.php', 'icon' => 'user', 'page' => 'profile'],
    ['title' => 'Personal Info', 'url' => '/Armis2/users/personal.php', 'icon' => 'id-card', 'page' => 'personal'],
    ['title' => 'Service Record', 'url' => '/Armis2/users/service.php', 'icon' => 'medal', 'page' => 'service'],
    ['title' => 'Training History', 'url' => '/Armis2/users/training.php', 'icon' => 'graduation-cap', 'page' => 'training'],
    ['title' => 'Download CV', 'url' => '/Armis2/users/cv_download.php', 'icon' => 'download', 'page' => 'cv_download'],
    ['title' => 'Account Settings', 'url' => '/Armis2/users/settings.php', 'icon' => 'cogs', 'page' => 'settings']
];

// Handle PDF download
if (isset($_GET['download']) && $_GET['download'] === 'pdf') {
    try {
        // Set headers for PDF download
        $filename = 'CV_' . ($userData->svcNo ?? 'Personnel') . '_' . date('Y-m-d') . '.pdf';
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        
        // Generate basic PDF content (in production, use TCPDF or similar)
        $cvData = [
            'service_number' => $userData->svcNo ?? 'N/A',
            'name' => $userData->fullName ?? 'N/A',
            'rank' => $userData->displayRank ?? 'N/A',
            'unit' => $userData->unitName ?? 'N/A',
            'email' => $userData->email ?? 'N/A',
            'phone' => $userData->tel ?? 'N/A',
            'dob' => $userData->DOB ? date('M j, Y', strtotime($userData->DOB)) : 'N/A',
            'enlistment' => $userData->attestDate ? date('M j, Y', strtotime($userData->attestDate)) : 'N/A'
        ];
        
        // Simple PDF content
        $pdfContent = "%PDF-1.4\n";
        $pdfContent .= "1 0 obj<</Type/Catalog/Pages 2 0 R>>endobj\n";
        $pdfContent .= "2 0 obj<</Type/Pages/Kids[3 0 R]/Count 1>>endobj\n";
        $pdfContent .= "3 0 obj<</Type/Page/Parent 2 0 R/MediaBox[0 0 612 792]/Contents 4 0 R/Resources<</Font<</F1 5 0 R>>>>>>endobj\n";
        
        $contentText = "ARMY RESOURCE MANAGEMENT INFORMATION SYSTEM\\nCURRICULUM VITAE\\n\\n";
        $contentText .= "Service Number: {$cvData['service_number']}\\n";
        $contentText .= "Name: {$cvData['name']}\\n";
        $contentText .= "Rank: {$cvData['rank']}\\n";
        $contentText .= "Unit: {$cvData['unit']}\\n";
        $contentText .= "Email: {$cvData['email']}\\n";
        $contentText .= "Phone: {$cvData['phone']}\\n";
        $contentText .= "Date of Birth: {$cvData['dob']}\\n";
        $contentText .= "Date of Enlistment: {$cvData['enlistment']}\\n";
        
        $pdfContent .= "4 0 obj<</Length " . strlen($contentText) . ">>stream\nBT/F1 12 Tf 50 750 Td({$contentText})Tj ET\nendstream endobj\n";
        $pdfContent .= "5 0 obj<</Type/Font/Subtype/Type1/BaseFont/Helvetica>>endobj\n";
        $pdfContent .= "xref\n0 6\n0000000000 65535 f\n0000000010 00000 n\n0000000053 00000 n\n0000000110 00000 n\n0000000251 00000 n\n0000000400 00000 n\ntrailer<</Size 6/Root 1 0 R>>\nstartxref\n470\n%%EOF";
        
        echo $pdfContent;
        exit();
        
    } catch (Exception $e) {
        error_log("PDF generation error: " . $e->getMessage());
        header('Location: /Armis2/users/cv_download.php?error=pdf_generation_failed');
        exit();
    }
}

include dirname(__DIR__) . '/shared/header.php';
include dirname(__DIR__) . '/shared/sidebar.php';
?>

<!-- Main Content -->
<div class="content-wrapper with-sidebar">
    <div class="container-fluid">
        <div class="main-content">
            <!-- Header Section -->
            <div class="row">
                <div class="col-12">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h1 class="section-title">
                            <i class="fas fa-download"></i> Download CV
                        </h1>
                        <nav aria-label="breadcrumb">
                            <ol class="breadcrumb">
                                <li class="breadcrumb-item"><a href="/Armis2/users/index.php">Profile</a></li>
                                <li class="breadcrumb-item active">Download CV</li>
                            </ol>
                        </nav>
                    </div>
                </div>
            </div>

            <!-- Error Messages -->
            <?php if (isset($errorMessage)): ?>
            <div class="row mb-3">
                <div class="col-12">
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-triangle"></i> <?= htmlspecialchars($errorMessage) ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <?php if (isset($_GET['error']) && $_GET['error'] === 'pdf_generation_failed'): ?>
            <div class="row mb-3">
                <div class="col-12">
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle"></i> PDF generation failed. Please try again or contact support.
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Download Options -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="fas fa-download"></i> Download Options</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <div class="text-center">
                                        <i class="fas fa-file-pdf fa-3x text-danger mb-2"></i>
                                        <h6>PDF Format</h6>
                                        <p class="text-muted small">Professional formatted CV for official use</p>
                                        <a href="?download=pdf" class="btn btn-danger">
                                            <i class="fas fa-file-pdf"></i> Download PDF
                                        </a>
                                    </div>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <div class="text-center">
                                        <i class="fas fa-print fa-3x text-primary mb-2"></i>
                                        <h6>Print Version</h6>
                                        <p class="text-muted small">Printer-friendly version</p>
                                        <button onclick="window.print()" class="btn btn-primary">
                                            <i class="fas fa-print"></i> Print CV
                                        </button>
                                    </div>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <div class="text-center">
                                        <i class="fas fa-eye fa-3x text-success mb-2"></i>
                                        <h6>Preview</h6>
                                        <p class="text-muted small">View complete CV below</p>
                                        <a href="#cv-preview" class="btn btn-success">
                                            <i class="fas fa-eye"></i> View Preview
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- CV Preview -->
            <div class="row" id="cv-preview">
                <div class="col-12">
                    <div class="card dashboard-card">
                        <div class="card-body" id="cv-content">
                            <!-- CV Header -->
                            <div class="text-center mb-4 border-bottom pb-3">
                                <h2 class="text-primary">ARMY RESOURCE MANAGEMENT INFORMATION SYSTEM</h2>
                                <h3>CURRICULUM VITAE</h3>
                                <p class="text-muted">Generated on <?= date('F j, Y') ?></p>
                            </div>

                            <?php if ($userData): ?>
                            <!-- Personal Information -->
                            <div class="mb-4">
                                <h4 class="text-primary border-bottom pb-2">PERSONAL INFORMATION</h4>
                                <div class="row">
                                    <div class="col-md-6">
                                        <table class="table table-borderless">
                                            <tr>
                                                <td><strong>Service Number:</strong></td>
                                                <td><?= htmlspecialchars($userData->svcNo ?? 'N/A') ?></td>
                                            </tr>
                                            <tr>
                                                <td><strong>Full Name:</strong></td>
                                                <td><?= htmlspecialchars($userData->fullName ?? 'N/A') ?></td>
                                            </tr>
                                            <tr>
                                                <td><strong>Current Rank:</strong></td>
                                                <td><?= htmlspecialchars($userData->displayRank ?? 'N/A') ?></td>
                                            </tr>
                                            <tr>
                                                <td><strong>Unit:</strong></td>
                                                <td><?= htmlspecialchars($userData->unitName ?? 'N/A') ?></td>
                                            </tr>
                                            <tr>
                                                <td><strong>Email:</strong></td>
                                                <td><?= htmlspecialchars($userData->email ?? 'N/A') ?></td>
                                            </tr>
                                        </table>
                                    </div>
                                    <div class="col-md-6">
                                        <table class="table table-borderless">
                                            <tr>
                                                <td><strong>Phone Number:</strong></td>
                                                <td><?= htmlspecialchars($userData->tel ?? 'N/A') ?></td>
                                            </tr>
                                            <tr>
                                                <td><strong>Date of Birth:</strong></td>
                                                <td><?= $userData->DOB ? date('F j, Y', strtotime($userData->DOB)) : 'N/A' ?></td>
                                            </tr>
                                            <tr>
                                                <td><strong>Date of Enlistment:</strong></td>
                                                <td><?= $userData->attestDate ? date('F j, Y', strtotime($userData->attestDate)) : 'N/A' ?></td>
                                            </tr>
                                            <tr>
                                                <td><strong>Service Status:</strong></td>
                                                <td><span class="badge bg-<?= $userData->svcStatus === 'active' ? 'success' : 'secondary' ?>"><?= ucfirst($userData->svcStatus ?? 'Unknown') ?></span></td>
                                            </tr>
                                            <tr>
                                                <td><strong>Years of Service:</strong></td>
                                                <td><?= htmlspecialchars($userData->serviceYears ?? 'N/A') ?></td>
                                            </tr>
                                        </table>
                                    </div>
                                </div>
                            </div>

                            <!-- Education Records -->
                            <?php if (!empty($educationRecords)): ?>
                            <div class="mb-4">
                                <h4 class="text-primary border-bottom pb-2">EDUCATION</h4>
                                <div class="table-responsive">
                                    <table class="table table-striped">
                                        <thead>
                                            <tr>
                                                <th>Institution</th>
                                                <th>Qualification</th>
                                                <th>Field of Study</th>
                                                <th>Year Completed</th>
                                                <th>Grade/Result</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($educationRecords as $education): ?>
                                            <tr>
                                                <td><?= htmlspecialchars($education->institution_name ?? 'N/A') ?></td>
                                                <td><?= htmlspecialchars($education->qualification_type ?? 'N/A') ?></td>
                                                <td><?= htmlspecialchars($education->field_of_study ?? 'N/A') ?></td>
                                                <td><?= $education->completion_date ? date('Y', strtotime($education->completion_date)) : 'N/A' ?></td>
                                                <td><?= htmlspecialchars($education->grade_result ?? 'N/A') ?></td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                            <?php endif; ?>

                            <!-- Training Records -->
                            <?php if (!empty($trainingRecords)): ?>
                            <div class="mb-4">
                                <h4 class="text-primary border-bottom pb-2">TRAINING HISTORY</h4>
                                <div class="table-responsive">
                                    <table class="table table-striped">
                                        <thead>
                                            <tr>
                                                <th>Course Name</th>
                                                <th>Institution</th>
                                                <th>Start Date</th>
                                                <th>End Date</th>
                                                <th>Status</th>
                                                <th>Result</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($trainingRecords as $training): ?>
                                            <tr>
                                                <td><?= htmlspecialchars($training->course_name ?? 'N/A') ?></td>
                                                <td><?= htmlspecialchars($training->institution ?? 'N/A') ?></td>
                                                <td><?= $training->start_date ? date('M j, Y', strtotime($training->start_date)) : 'N/A' ?></td>
                                                <td><?= $training->end_date ? date('M j, Y', strtotime($training->end_date)) : 'N/A' ?></td>
                                                <td>
                                                    <span class="badge bg-<?= $training->status === 'completed' ? 'success' : ($training->status === 'in_progress' ? 'warning' : 'secondary') ?>">
                                                        <?= ucfirst($training->status ?? 'N/A') ?>
                                                    </span>
                                                </td>
                                                <td><?= htmlspecialchars($training->result ?? 'N/A') ?></td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                            <?php endif; ?>

                            <!-- Service History -->
                            <?php if (!empty($serviceRecord)): ?>
                            <div class="mb-4">
                                <h4 class="text-primary border-bottom pb-2">SERVICE HISTORY</h4>
                                <div class="table-responsive">
                                    <table class="table table-striped">
                                        <thead>
                                            <tr>
                                                <th>Date</th>
                                                <th>Event</th>
                                                <th>Details</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($serviceRecord as $record): ?>
                                            <tr>
                                                <td><?= $record->record_date ? date('M j, Y', strtotime($record->record_date)) : 'N/A' ?></td>
                                                <td><?= htmlspecialchars($record->record_type ?? 'N/A') ?></td>
                                                <td><?= htmlspecialchars($record->description ?? 'N/A') ?></td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                            <?php endif; ?>

                            <!-- Awards and Decorations -->
                            <?php if (!empty($awards)): ?>
                            <div class="mb-4">
                                <h4 class="text-primary border-bottom pb-2">AWARDS AND DECORATIONS</h4>
                                <div class="table-responsive">
                                    <table class="table table-striped">
                                        <thead>
                                            <tr>
                                                <th>Award Name</th>
                                                <th>Date Awarded</th>
                                                <th>Citation</th>
                                                <th>Awarded By</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($awards as $award): ?>
                                            <tr>
                                                <td><?= htmlspecialchars($award->award_name ?? 'N/A') ?></td>
                                                <td><?= $award->date_awarded ? date('M j, Y', strtotime($award->date_awarded)) : 'N/A' ?></td>
                                                <td><?= htmlspecialchars($award->citation ?? 'N/A') ?></td>
                                                <td><?= htmlspecialchars($award->awarded_by ?? 'N/A') ?></td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                            <?php endif; ?>

                            <!-- Skills and Competencies -->
                            <?php if (!empty($skills)): ?>
                            <div class="mb-4">
                                <h4 class="text-primary border-bottom pb-2">SKILLS AND COMPETENCIES</h4>
                                <?php 
                                $skillCategories = [];
                                foreach ($skills as $skill) {
                                    $skillCategories[$skill->skill_category ?? 'General'][] = $skill;
                                }
                                ?>
                                <?php foreach ($skillCategories as $category => $categorySkills): ?>
                                <div class="mb-3">
                                    <h6 class="text-secondary"><?= htmlspecialchars($category) ?></h6>
                                    <div class="row">
                                        <?php foreach ($categorySkills as $skill): ?>
                                        <div class="col-md-6 mb-2">
                                            <div class="d-flex justify-content-between">
                                                <span><?= htmlspecialchars($skill->skill_name ?? 'N/A') ?></span>
                                                <span class="badge bg-<?= $skill->proficiency_level === 'Expert' ? 'success' : ($skill->proficiency_level === 'Intermediate' ? 'warning' : 'info') ?>">
                                                    <?= htmlspecialchars($skill->proficiency_level ?? 'Basic') ?>
                                                </span>
                                            </div>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <?php endif; ?>

                            <!-- Deployments -->
                            <?php if (!empty($deployments)): ?>
                            <div class="mb-4">
                                <h4 class="text-primary border-bottom pb-2">DEPLOYMENT HISTORY</h4>
                                <div class="table-responsive">
                                    <table class="table table-striped">
                                        <thead>
                                            <tr>
                                                <th>Operation/Mission</th>
                                                <th>Location</th>
                                                <th>Start Date</th>
                                                <th>End Date</th>
                                                <th>Role</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($deployments as $deployment): ?>
                                            <tr>
                                                <td><?= htmlspecialchars($deployment->operation_name ?? 'N/A') ?></td>
                                                <td><?= htmlspecialchars($deployment->location ?? 'N/A') ?></td>
                                                <td><?= $deployment->start_date ? date('M j, Y', strtotime($deployment->start_date)) : 'N/A' ?></td>
                                                <td><?= $deployment->end_date ? date('M j, Y', strtotime($deployment->end_date)) : 'Ongoing' ?></td>
                                                <td><?= htmlspecialchars($deployment->role ?? 'N/A') ?></td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                            <?php endif; ?>

                            <!-- Footer -->
                            <div class="text-center mt-4 pt-3 border-top">
                                <p class="text-muted small">
                                    This CV was generated from the Army Resource Management Information System (ARMIS)<br>
                                    Generated on <?= date('F j, Y \a\t g:i A') ?> | 
                                    Document ID: CV-<?= $userData->svcNo ?? 'UNKNOWN' ?>-<?= date('Ymd') ?>
                                </p>
                            </div>

                            <?php else: ?>
                            <div class="text-center py-5">
                                <i class="fas fa-exclamation-triangle fa-3x text-warning mb-3"></i>
                                <h4>Profile Data Not Available</h4>
                                <p class="text-muted">Unable to generate CV. Please ensure your profile is complete.</p>
                                <a href="/Armis2/users/personal.php" class="btn btn-primary">Complete Profile</a>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Print Styles -->
<style>
@media print {
    .content-wrapper .sidebar, 
    .navbar, 
    .btn, 
    .card-header,
    .breadcrumb {
        display: none !important;
    }
    
    .content-wrapper {
        margin-left: 0 !important;
        padding: 0 !important;
    }
    
    .card {
        border: none !important;
        box-shadow: none !important;
    }
    
    .table {
        font-size: 12px;
    }
    
    h2, h3, h4 {
        color: #000 !important;
    }
    
    .badge {
        border: 1px solid #000;
        color: #000 !important;
        background: #fff !important;
    }
}
</style>
                                            <tr>
                                                <th>Rank/Position</th>
                                                <th>Date of Promotion</th>
                                                <th>Years of Service</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($serviceRecord as $record): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($record['promotion']); ?></td>
                                                    <td><?php echo htmlspecialchars(date('F j, Y', strtotime($record['date']))); ?></td>
                                                    <td><?php echo number_format((time() - strtotime($record['date'])) / (365.25 * 24 * 3600), 1); ?> years</td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            <!-- Training History -->
                            <div class="mb-4">
                                <h4 class="text-primary border-bottom pb-2">TRAINING & CERTIFICATIONS</h4>
                                <div class="table-responsive">
                                    <table class="table table-striped">
                                        <thead>
                                            <tr>
                                                <th>Course/Training</th>
                                                <th>Completion Date</th>
                                                <th>Status</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($trainingHistory as $training): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($training['course']); ?></td>
                                                    <td><?php echo htmlspecialchars(date('F j, Y', strtotime($training['date']))); ?></td>
                                                    <td><span class="badge bg-success"><?php echo htmlspecialchars($training['status']); ?></span></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            <!-- Official Footer -->
                            <div class="text-center mt-5 pt-3 border-top">
                                <p class="text-muted small">
                                    This document was generated by the Army Resource Management Information System (ARMIS)<br>
                                    Document ID: CV-<?php echo $userData['svcNo']; ?>-<?php echo date('Ymd-His'); ?><br>
                                    Generated on: <?php echo date('F j, Y \a\t g:i A'); ?>
                                </p>
                                <p class="text-muted small">
                                    <strong>OFFICIAL USE ONLY</strong> - This document contains sensitive military information
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
@media print {
    .sidebar, .navbar, .btn, .card-header .btn {
        display: none !important;
    }
    
    .content-wrapper {
        margin-left: 0 !important;
        padding: 0 !important;
    }
    
    .card {
        border: none !important;
        box-shadow: none !important;
    }
    
    body {
        background: white !important;
    }
}
</style>

<?php include dirname(__DIR__) . '/shared/footer.php'; ?>
