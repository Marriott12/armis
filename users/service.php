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

$pageTitle = "Service Record";
$moduleName = "User Profile";
$moduleIcon = "medal";
$currentPage = "service";

// Load shared navigation (replaces duplicate navigation array)
require_once dirname(__DIR__) . '/shared/user_navigation.php';
$sidebarLinks = $userNavigationItems;

// Load component library for reusable UI components
require_once dirname(__DIR__) . '/shared/components/stat_card.php';
require_once dirname(__DIR__) . '/shared/components/info_card.php';
require_once dirname(__DIR__) . '/shared/components/empty_state.php';

// Load user profile data
require_once __DIR__ . '/profile_manager.php';

try {
    $profileManager = new UserProfileManager($_SESSION['user_id']);
    $userData = $profileManager->getUserProfile();
    $awards = $profileManager->getAwards();
    $medals = $profileManager->getMedals();
    $promotions = $profileManager->getPromotionHistory();
    $deployments = $profileManager->getDeployments();
    $appointments = $profileManager->getAppointments();
    $medicalInfo = $profileManager->getMedicalInfo(false); // Basic medical info only

} catch (Exception $e) {
    error_log("Service record page error: " . $e->getMessage());
    $userData = null;
    $awards = [];
    $medals = [];
    $promotions = [];
    $deployments = [];
    $medicalInfo = null;
}

include dirname(__DIR__) . '/shared/header.php';
include dirname(__DIR__) . '/shared/sidebar.php';
?>

<!-- Users Module Standard CSS -->
<link rel="stylesheet" href="/Armis2/assets/css/users-module-standard.css">

<!-- Main Content -->
<div class="content-wrapper with-sidebar">
    <div class="container-fluid">
        <div class="main-content">
            <!-- Header -->
            <div class="row">
                <div class="col-12">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h1 class="page-title">
                            <i class="fas fa-medal me-3"></i>Service Record
                        </h1>
                        <div>
                            <a href="/Armis2/users/cv_download.php" class="btn btn-primary me-2">
                                <i class="fas fa-download"></i> Download Full Record
                            </a>
                            <a href="/Armis2/users/index.php" class="btn btn-secondary">
                                <i class="fas fa-arrow-left"></i> Back to Profile
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Service Statistics Summary -->
            <?php
            $serviceStats = [
                [
                    'icon' => 'medal',
                    'value' => count($medals),
                    'label' => 'Medals & Honors',
                    'color' => 'danger',
                    'link' => '#medals-section'
                ],
                [
                    'icon' => 'chart-line',
                    'value' => count(array_filter($promotions, fn($p) => ($p->type ?? '') === 'promotion')),
                    'label' => 'Promotions',
                    'color' => 'success',
                    'link' => '#promotion-history-section'
                ],
                [
                    'icon' => 'globe',
                    'value' => count($deployments),
                    'label' => 'Deployments',
                    'color' => 'info',
                    'link' => '#deployment-history-section'
                ],
                [
                    'icon' => 'user-tie',
                    'value' => count($appointments),
                    'label' => 'Appointments',
                    'color' => 'primary',
                    'link' => '#appointment-history-section'
                ]
            ];
            renderStatCardRow($serviceStats, 4);
            ?>

            <!-- Service Overview -->
            <div class="row mb-4">
                <div class="col-lg-8">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="fas fa-id-badge"></i> Service Information</h5>
                        </div>
                        <div class="card-body">
                            <!-- Profile Photo Section -->
                            <div class="row mb-4">
                                <div class="col-md-12 text-center">
                                    <img src="<?= $profileManager->getProfilePhotoURL() ?>" 
                                         alt="Profile Photo" 
                                         class="rounded-circle border" 
                                         style="width: 120px; height: 120px; object-fit: cover;">
                                    <h5 class="mt-2 mb-0"><?= htmlspecialchars(($userData && isset($userData->fullName)) ? $userData->fullName : 'N/A') ?></h5>
                                    <p class="text-muted"><?= htmlspecialchars(($userData && isset($userData->svcNo)) ? $userData->svcNo : 'N/A') ?></p>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label text-muted small">Full Name</label>
                                    <p class="fw-bold"><?= htmlspecialchars(($userData && isset($userData->fullName)) ? $userData->fullName : 'N/A') ?></p>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label text-muted small">Service Number</label>
                                    <p class="fw-bold"><?= htmlspecialchars(($userData && isset($userData->svcNo)) ? $userData->svcNo : 'N/A') ?></p>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label text-muted small">Current Rank</label>
                                    <p class="fw-bold"><?= htmlspecialchars(($userData && isset($userData->displayRank)) ? $userData->displayRank : 'N/A') ?></p>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label text-muted small">Unit</label>
                                    <p class="fw-bold"><?= htmlspecialchars(($userData && isset($userData->unitName)) ? $userData->unitName : 'N/A') ?></p>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label text-muted small">Corps</label>
                                    <p class="fw-bold"><?= htmlspecialchars(($userData && (isset($userData->corps_name) || isset($userData->corps))) ? ($userData->corps_name ?? $userData->corps) : 'N/A') ?></p>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label text-muted small">Service Status</label>
                                    <p class="fw-bold">
                                        <span class="badge bg-<?= (($userData && isset($userData->svcStatus) && $userData->svcStatus) === 'active') ? 'success' : 'secondary' ?>">
                                            <?= ucfirst(($userData && isset($userData->svcStatus)) ? $userData->svcStatus : 'Unknown') ?>
                                        </span>
                                    </p>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label text-muted small">Date of Attestation</label>
                                    <p class="fw-bold">
                                        <?php if ($userData && isset($userData->attestDate) && $userData->attestDate): ?>
                                            <?= date('F j, Y', strtotime($userData->attestDate)) ?>
                                        <?php else: ?>
                                            Not Available
                                        <?php endif; ?>
                                    </p>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label text-muted small">Years of Service</label>
                                    <p class="fw-bold"><?= htmlspecialchars(($userData && isset($userData->serviceYears)) ? $userData->serviceYears : 'N/A') ?></p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Medical Category -->
                <div class="col-lg-4">
                    <div class="card h-100">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="fas fa-heartbeat"></i> Medical Category</h5>
                        </div>
                        <div class="card-body">
                            <?php if ($medicalInfo): ?>
                                <div class="text-center">
                                    <div class="display-4 text-primary mb-2">
                                        <?= htmlspecialchars($medicalInfo->medical_category ?? 'N/A') ?>
                                    </div>
                                    <p class="text-muted">Medical Category</p>
                                    
                                    <div class="mt-3">
                                        <span class="badge bg-<?= 
                                            ($medicalInfo->fitness_status ?? '') === 'Fit' ? 'success' : 
                                            (($medicalInfo->fitness_status ?? '') === 'Limited Duties' ? 'warning' : 'danger') 
                                        ?> p-2">
                                            <?= htmlspecialchars($medicalInfo->fitness_status ?? 'Unknown') ?>
                                        </span>
                                    </div>
                                    
                                    <?php if ($medicalInfo->lastMedicalExam): ?>
                                        <p class="text-muted mt-3 mb-0">
                                            <small>Last Exam: <?= date('M j, Y', strtotime($medicalInfo->lastMedicalExam)) ?></small>
                                        </p>
                                    <?php endif; ?>
                                    
                                    <?php if ($medicalInfo->next_medical_due): ?>
                                        <p class="text-muted mb-0">
                                            <small>Next Due: <?= date('M j, Y', strtotime($medicalInfo->next_medical_due)) ?></small>
                                        </p>
                                    <?php endif; ?>
                                </div>
                            <?php else: ?>
                                <div class="text-center text-muted">
                                    <i class="fas fa-medical-kit fa-3x mb-3 opacity-50"></i>
                                    <p>No medical information available</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Medals and Honors -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <a id="medals-section"></a>
                            <h5 class="mb-0"><i class="fas fa-medal"></i> Medals and Honors</h5>
                        </div>
                        <div class="card-body">
                            <?php if (empty($medals)): ?>
                                <?php renderEmptyState(
                                    'medal',
                                    'No medals or honors awarded yet',
                                    null,
                                    null,
                                    null,
                                    'danger'
                                ); ?>
                            <?php else: ?>
                                <div class="row">
                                    <?php foreach ($medals as $medal): ?>
                                        <div class="col-md-6 col-lg-4 mb-3">
                                            <div class="card h-100 border-0 shadow-sm">
                                                <div class="card-body">
                                                    <!-- Medal Icon/Image -->
                                                    <div class="text-center mb-3">
                                                        <?php if (!empty($medal->imagePath)): ?>
                                                            <img src="<?= htmlspecialchars($medal->imagePath) ?>" 
                                                                 alt="<?= htmlspecialchars($medal->medal_name) ?>" 
                                                                 class="img-fluid" 
                                                                 style="max-width: 80px; max-height: 80px;">
                                                        <?php else: ?>
                                                            <i class="fas fa-medal fa-3x text-warning"></i>
                                                        <?php endif; ?>
                                                    </div>
                                                    
                                                    <!-- Medal Name -->
                                                    <h6 class="text-center fw-bold mb-2">
                                                        <?= htmlspecialchars($medal->medal_name ?? 'Unknown Medal') ?>
                                                    </h6>
                                                    
                                                    <!-- Medal Description -->
                                                    <?php if (!empty($medal->medal_description)): ?>
                                                        <p class="text-center text-muted small mb-2">
                                                            <?= htmlspecialchars($medal->medal_description) ?>
                                                        </p>
                                                    <?php endif; ?>
                                                    
                                                    <!-- Award Date -->
                                                    <div class="text-center mb-2">
                                                        <span class="badge bg-success">
                                                            <i class="fas fa-calendar-alt"></i>
                                                            <?= !empty($medal->award_date) ? date('M j, Y', strtotime($medal->award_date)) : 'Date not specified' ?>
                                                        </span>
                                                    </div>
                                                    
                                                    <!-- Bar Number (if multiple awards) -->
                                                    <?php if (!empty($medal->bar_number) && $medal->bar_number > 0): ?>
                                                        <div class="text-center mb-2">
                                                            <span class="badge bg-info">
                                                                <i class="fas fa-star"></i> Bar: <?= htmlspecialchars($medal->bar_number) ?>
                                                            </span>
                                                        </div>
                                                    <?php endif; ?>
                                                    
                                                    <!-- Citation -->
                                                    <?php if (!empty($medal->citation)): ?>
                                                        <div class="mt-3 pt-3 border-top">
                                                            <p class="small mb-1 fw-bold text-secondary text-center">
                                                                <i class="fas fa-quote-left"></i> Citation
                                                            </p>
                                                            <p class="small text-muted mb-0">
                                                                <?= htmlspecialchars($medal->citation) ?>
                                                            </p>
                                                        </div>
                                                    <?php endif; ?>
                                                    
                                                    <!-- Gazette Reference -->
                                                    <?php if (!empty($medal->gazette_reference)): ?>
                                                        <div class="mt-2 text-center">
                                                            <small class="text-muted">
                                                                <i class="fas fa-file-alt"></i> 
                                                                Ref: <?= htmlspecialchars($medal->gazette_reference) ?>
                                                            </small>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Promotion History -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <a id="promotion-history-section"></a>
                            <h5 class="mb-0"><i class="fas fa-chart-line"></i> Promotion History</h5>
                        </div>
                        <div class="card-body">
                            <?php if (empty($promotions)): ?>
                                <?php renderEmptyState(
                                    'chart-line',
                                    'No promotion records found',
                                    null,
                                    null,
                                    null,
                                    'success'
                                ); ?>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-hover align-middle">
                                        <thead class="table-success">
                                            <tr>
                                                <th><i class="fas fa-calendar"></i> Effective Date</th>
                                                <th><i class="fas fa-arrow-up"></i> Type</th>
                                                <th><i class="fas fa-star"></i> From Rank</th>
                                                <th><i class="fas fa-star"></i> To Rank</th>
                                                <th><i class="fas fa-file-alt"></i> Authority</th>
                                                <th><i class="fas fa-comment"></i> Remarks</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($promotions as $promotion): ?>
                                                <tr>
                                                    <!-- Effective Date -->
                                                    <td>
                                                        <strong><?= !empty($promotion->dateFrom) ? date('M j, Y', strtotime($promotion->dateFrom)) : 'N/A' ?></strong>
                                                        <?php if (!empty($promotion->dateTo) && $promotion->dateTo !== '0000-00-00'): ?>
                                                            <br><small class="text-muted">to <?= date('M j, Y', strtotime($promotion->dateTo)) ?></small>
                                                        <?php endif; ?>
                                                    </td>
                                                    
                                                    <!-- Type Badge -->
                                                    <td>
                                                        <?php
                                                        $typeClass = match($promotion->type ?? 'promotion') {
                                                            'promotion' => 'success',
                                                            'reversion' => 'warning',
                                                            'demotion' => 'danger',
                                                            default => 'secondary'
                                                        };
                                                        $typeIcon = match($promotion->type ?? 'promotion') {
                                                            'promotion' => 'fa-arrow-up',
                                                            'reversion' => 'fa-undo',
                                                            'demotion' => 'fa-arrow-down',
                                                            default => 'fa-exchange-alt'
                                                        };
                                                        ?>
                                                        <span class="badge bg-<?= $typeClass ?>">
                                                            <i class="fas <?= $typeIcon ?>"></i>
                                                            <?= ucfirst($promotion->type ?? 'Promotion') ?>
                                                        </span>
                                                    </td>
                                                    
                                                    <!-- From Rank -->
                                                    <td>
                                                        <span class="badge bg-secondary">
                                                            <?= htmlspecialchars($promotion->current_rank_abbr ?? 'N/A') ?>
                                                        </span>
                                                    </td>
                                                    
                                                    <!-- To Rank -->
                                                    <td>
                                                        <span class="badge bg-primary">
                                                            <?= htmlspecialchars($promotion->new_rank_abbr ?? 'N/A') ?>
                                                        </span>
                                                    </td>
                                                    
                                                    <!-- Authority -->
                                                    <td>
                                                        <small><?= htmlspecialchars($promotion->authority ?? 'N/A') ?></small>
                                                    </td>
                                                    
                                                    <!-- Remarks -->
                                                    <td>
                                                        <?php if (!empty($promotion->remark)): ?>
                                                            <small class="text-muted"><?= htmlspecialchars($promotion->remark) ?></small>
                                                        <?php else: ?>
                                                            <small class="text-muted">-</small>
                                                        <?php endif; ?>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                                
                                <!-- Promotion Timeline Visualization -->
                                <div class="mt-4">
                                    <h6 class="mb-3"><i class="fas fa-timeline"></i> Promotion Timeline</h6>
                                    <div class="promotion-timeline">
                                        <?php 
                                        // Reverse to show chronological order
                                        $chronological = array_reverse($promotions);
                                        foreach ($chronological as $index => $promotion): 
                                            $isLatest = ($index === count($chronological) - 1);
                                            $typeClass = match($promotion->type ?? 'promotion') {
                                                'promotion' => 'success',
                                                'reversion' => 'warning',
                                                'demotion' => 'danger',
                                                default => 'secondary'
                                            };
                                        ?>
                                            <div class="timeline-item">
                                                <div class="timeline-marker bg-<?= $typeClass ?> <?= $isLatest ? 'pulse' : '' ?>">
                                                    <i class="fas fa-star text-white"></i>
                                                </div>
                                                <div class="timeline-content">
                                                    <div class="card border-<?= $typeClass ?> mb-3">
                                                        <div class="card-body p-3">
                                                            <div class="d-flex justify-content-between align-items-start">
                                                                <div>
                                                                    <h6 class="mb-1">
                                                                        <span class="badge bg-<?= $typeClass ?>">
                                                                            <?= htmlspecialchars($promotion->new_rank_name ?? 'N/A') ?>
                                                                        </span>
                                                                        <?php if ($isLatest): ?>
                                                                            <span class="badge bg-primary ms-2">Current</span>
                                                                        <?php endif; ?>
                                                                    </h6>
                                                                    <p class="text-muted mb-2 small">
                                                                        <i class="fas fa-calendar"></i>
                                                                        <?= !empty($promotion->dateFrom) ? date('F j, Y', strtotime($promotion->dateFrom)) : 'N/A' ?>
                                                                    </p>
                                                                    <?php if (!empty($promotion->authority)): ?>
                                                                        <p class="mb-0 small">
                                                                            <i class="fas fa-file-signature text-muted"></i>
                                                                            <strong>Authority:</strong> <?= htmlspecialchars($promotion->authority) ?>
                                                                        </p>
                                                                    <?php endif; ?>
                                                                    <?php if (!empty($promotion->remark)): ?>
                                                                        <p class="mb-0 small text-muted mt-1">
                                                                            <i class="fas fa-comment"></i>
                                                                            <?= htmlspecialchars($promotion->remark) ?>
                                                                        </p>
                                                                    <?php endif; ?>
                                                                </div>
                                                                <div class="text-end">
                                                                    <span class="badge bg-light text-dark">
                                                                        <?= ucfirst($promotion->type ?? 'Promotion') ?>
                                                                    </span>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                                
                                <!-- Summary Statistics -->
                                <div class="row mt-3">
                                    <div class="col-md-4">
                                        <div class="card bg-light">
                                            <div class="card-body text-center">
                                                <h3 class="text-success mb-0">
                                                    <?= count(array_filter($promotions, fn($p) => ($p->type ?? '') === 'promotion')) ?>
                                                </h3>
                                                <small class="text-muted">Total Promotions</small>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="card bg-light">
                                            <div class="card-body text-center">
                                                <h3 class="text-primary mb-0">
                                                    <?php
                                                    if (!empty($promotions)) {
                                                        $firstPromotion = end($promotions);
                                                        $lastPromotion = reset($promotions);
                                                        if (!empty($firstPromotion->dateFrom) && !empty($lastPromotion->dateFrom)) {
                                                            $years = (strtotime($lastPromotion->dateFrom) - strtotime($firstPromotion->dateFrom)) / (365.25 * 24 * 3600);
                                                            echo number_format($years, 1);
                                                        } else {
                                                            echo 'N/A';
                                                        }
                                                    } else {
                                                        echo '0';
                                                    }
                                                    ?>
                                                </h3>
                                                <small class="text-muted">Years of Service</small>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="card bg-light">
                                            <div class="card-body text-center">
                                                <h3 class="text-info mb-0">
                                                    <?php
                                                    if (!empty($promotions)) {
                                                        $latestPromotion = reset($promotions);
                                                        echo htmlspecialchars($latestPromotion->new_rank_name ?? 'N/A');
                                                    } else {
                                                        echo 'N/A';
                                                    }
                                                    ?>
                                                </h3>
                                                <small class="text-muted">Current Rank</small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Deployment History -->
            <div class="row">
                <div class="col-12">
                    <div class="card">
                                <!-- Appointment History -->
                                <div class="row mb-4">
                                    <div class="col-12">
                                        <div class="card">
                                            <div class="card-header">
                                                <a id="appointment-history-section"></a>
                                                <h5 class="mb-0"><i class="fas fa-user-tie"></i> Appointment History</h5>
                                            </div>
                                            <div class="card-body">
                                                <?php if (empty($appointments)): ?>
                                                    <?php renderEmptyState(
                                                        'user-tie',
                                                        'No appointment records found',
                                                        null,
                                                        null,
                                                        null,
                                                        'primary'
                                                    ); ?>
                                                <?php else: ?>
                                                    <div class="table-responsive">
                                                        <table class="table table-hover align-middle">
                                                            <thead class="table-primary">
                                                                <tr>
                                                                    <th><i class="fas fa-calendar"></i> Date</th>
                                                                    <th><i class="fas fa-user-tie"></i> Appointment</th>
                                                                    <th><i class="fas fa-building"></i> Unit</th>
                                                                    <th><i class="fas fa-clipboard-list"></i> Type</th>
                                                                    <th><i class="fas fa-user-tag"></i> Rank</th>
                                                                    <th><i class="fas fa-map-marker-alt"></i> Location</th>
                                                                    <th><i class="fas fa-calendar-day"></i> Start Date</th>
                                                                    <th><i class="fas fa-calendar-times"></i> End Date</th>
                                                                    <th><i class="fas fa-hourglass-half"></i> Duration (months)</th>
                                                                    <th><i class="fas fa-file-alt"></i> Posting Order</th>
                                                                    <th><i class="fas fa-comment"></i> Remarks</th>
                                                                </tr>
                                                            </thead>
                                                            <tbody>
                                                                <?php foreach ($appointments as $appt): ?>
                                                                    <tr>
                                                                        <td><strong><?= !empty($appt->appointment_date) ? date('M j, Y', strtotime($appt->appointment_date)) : 'N/A' ?></strong></td>
                                                                        <td><?= htmlspecialchars($appt->appointment ?? 'N/A') ?></td>
                                                                        <td><?= htmlspecialchars($appt->unit_name ?? 'N/A') ?></td>
                                                                        <td><span class="badge bg-info"><?= htmlspecialchars($appt->appointment_type_name ?? 'N/A') ?></span></td>
                                                                        <td><?= htmlspecialchars($appt->rank_abbr ?? $appt->rank_name ?? 'N/A') ?></td>
                                                                        <td><?= htmlspecialchars($appt->location ?? 'N/A') ?></td>
                                                                        <td><?= !empty($appt->startDate) ? date('M j, Y', strtotime($appt->startDate)) : 'N/A' ?></td>
                                                                        <td><?php if (!empty($appt->endDate) && $appt->endDate !== '0000-00-00'): ?><?= date('M j, Y', strtotime($appt->endDate)) ?><?php else: ?><span class="text-muted">Ongoing</span><?php endif; ?></td>
                                                                        <td><?= htmlspecialchars($appt->durationMonths ?? '-') ?></td>
                                                                        <td><?= htmlspecialchars($appt->posting_order_reference ?? '-') ?></td>
                                                                        <td><?= htmlspecialchars($appt->remarks ?? $appt->comment ?? '-') ?></td>
                                                                    </tr>
                                                                <?php endforeach; ?>
                                                            </tbody>
                                                        </table>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                        <div class="card-header">
                            <a id="deployment-history-section"></a>
                            <h5 class="mb-0"><i class="fas fa-globe"></i> Deployment History</h5>
                        </div>
                        <div class="card-body">
                            <?php if (empty($deployments)): ?>
                                <?php renderEmptyState(
                                    'globe',
                                    'No deployment records found',
                                    null,
                                    null,
                                    null,
                                    'info'
                                ); ?>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-striped">
                                        <thead>
                                            <tr>
                                                <th>Deployment</th>
                                                <th>Location</th>
                                                <th>Type</th>
                                                <th>Role</th>
                                                <th>Duration</th>
                                                <th>Status</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($deployments as $deployment): ?>
                                                <tr>
                                                    <td>
                                                        <strong><?= htmlspecialchars($deployment->deployment_name ?? '') ?></strong>
                                                    </td>
                                                    <td><?= htmlspecialchars($deployment->location ?? '') ?></td>
                                                    <td>
                                                        <span class="badge bg-info">
                                                            <?= htmlspecialchars(ucfirst(str_replace('_', ' ', $deployment->mission_type ?? ''))) ?>
                                                        </span>
                                                    </td>
                                                    <td><?= htmlspecialchars($deployment->role_during_deployment ?? 'N/A') ?></td>
                                                    <td>
                                                        <?php if (!empty($deployment->startDate)): ?>
                                                            <?= date('M j, Y', strtotime($deployment->startDate)) ?>
                                                            <?php if (!empty($deployment->endDate)): ?>
                                                                - <?= date('M j, Y', strtotime($deployment->endDate)) ?>
                                                            <?php else: ?>
                                                                - Ongoing
                                                            <?php endif; ?>
                                                        <?php else: ?>
                                                            N/A
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <?php 
                                                        $status = $deployment->deployment_status ?? '';
                                                        $statusClass = match($status) {
                                                            'active' => 'primary',
                                                            'completed' => 'success',
                                                            'planned' => 'info',
                                                            'cancelled' => 'danger',
                                                            default => 'secondary'
                                                        };
                                                        ?>
                                                        <span class="badge bg-<?= $statusClass ?>">
                                                            <?= htmlspecialchars(ucfirst($status)) ?>
                                                        </span>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include dirname(__DIR__) . '/shared/footer.php'; ?>
