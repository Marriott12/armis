<?php
// filepath: c:\wamp64\www\Armis\users\admin_branch\ajax_edit_staff_form.php

require_once '../init.php';

header('Content-Type: text/html; charset=utf-8');

// Helper for repopulating form
function old($name, $default = '') {
    return isset($_POST[$name]) ? htmlspecialchars($_POST[$name]) : htmlspecialchars($default ?? '');
}
function selected($a, $b) { return $a == $b ? 'selected' : ''; }
function checked($a, $b) { return $a == $b ? 'checked' : ''; }

$svcNo = $_GET['svcNo'] ?? '';
if (!$svcNo) {
    echo '<div class="alert alert-warning">No staff selected.</div>';
    exit;
}

$db = DB::getInstance();
$staff = $db->query("SELECT * FROM staff WHERE svcNo = ?", [$svcNo])->first();
if (!$staff) {
    echo '<div class="alert alert-danger">Staff member not found.</div>';
    exit;
}

// Fetch related data
$children = $db->query("SELECT * FROM staff_children WHERE svcNo = ?", [$svcNo])->results();
$spouse = $db->query("SELECT * FROM staff_spouse WHERE svcNo = ?", [$svcNo])->first();
$academic = $db->query("SELECT * FROM staff_academic WHERE svcNo = ?", [$svcNo])->results();
$proftech = $db->query("SELECT * FROM staff_proftech WHERE svcNo = ?", [$svcNo])->results();
$milcourse = $db->query("SELECT * FROM staff_milcourse WHERE svcNo = ?", [$svcNo])->results();
$tradegroup = $db->query("SELECT * FROM staff_tradegroup WHERE svcNo = ?", [$svcNo])->results();
$awards = $db->query("SELECT * FROM staff_awards WHERE svcNo = ?", [$svcNo])->results();
$appointments = $db->query("SELECT * FROM staff_appointments WHERE svcNo = ?", [$svcNo])->results();
$promotions = $db->query("SELECT * FROM staff_promotions WHERE svcNo = ?", [$svcNo])->results();

$ranks = $db->query("SELECT rankID, rankName, rankIndex FROM ranks ORDER BY rankIndex ASC")->results();
$units = $db->query("SELECT unitID, unitName FROM units ORDER BY unitName ASC")->results();
$corpsList = $db->query("SELECT DISTINCT corps FROM staff WHERE corps IS NOT NULL AND corps != '' ORDER BY corps ASC")->results();
$categories = ['Officer', 'NCO', 'Civilian'];

// Load Bootstrap Icons if needed
?>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">

<form method="post" action="edit_staff.php" autocomplete="off" id="edit-staff-form">
    <input type="hidden" name="svcNo" value="<?=htmlspecialchars($staff->svcNo)?>">
    <input type="hidden" name="edit_staff" value="1">

    <div class="d-flex flex-wrap gap-2 mb-4" id="staffTab" role="tablist">
        <button class="btn btn-outline-primary active" id="personal-tab" data-bs-toggle="tab" data-bs-target="#personal" type="button" role="tab">
            <i class="bi bi-person-fill me-1"></i> Personal Details
        </button>
        <button class="btn btn-outline-primary" id="service-tab" data-bs-toggle="tab" data-bs-target="#service" type="button" role="tab">
            <i class="bi bi-briefcase-fill me-1"></i> Service Details
        </button>
        <button class="btn btn-outline-primary" id="family-tab" data-bs-toggle="tab" data-bs-target="#family" type="button" role="tab">
            <i class="bi bi-people-fill me-1"></i> Family Details
        </button>
        <button class="btn btn-outline-primary" id="education-tab" data-bs-toggle="tab" data-bs-target="#education" type="button" role="tab">
            <i class="bi bi-mortarboard-fill me-1"></i> Education & Appointments
        </button>
        <!-- Add more tabs below as needed for ID, Residence, Language, etc. -->
        <!--
        <button class="btn btn-outline-primary" id="id-tab" data-bs-toggle="tab" data-bs-target="#id" type="button" role="tab">
            <i class="bi bi-card-text me-1"></i> Identification Docs
        </button>
        -->
    </div>
    <div class="tab-content border border-top-0 p-3 bg-light" id="editStaffTabsContent">
        <div class="tab-pane fade show active" id="personal" role="tabpanel" aria-labelledby="personal-tab">
            <?php include 'partials/edit_staff_step1_personal.php'; ?>
            <div class="mt-4 text-end">
                <button type="button" class="btn btn-outline-primary btn-next" data-next="#service-tab">
                    Next <i class="fa fa-arrow-right"></i>
                </button>
            </div>
        </div>
        <div class="tab-pane fade" id="service" role="tabpanel" aria-labelledby="service-tab">
            <?php include 'partials/edit_staff_step2_service.php'; ?>
            <div class="mt-4 d-flex justify-content-between">
                <button type="button" class="btn btn-outline-secondary btn-prev" data-prev="#personal-tab">
                    <i class="fa fa-arrow-left"></i> Previous
                </button>
                <button type="button" class="btn btn-outline-primary btn-next" data-next="#family-tab">
                    Next <i class="fa fa-arrow-right"></i>
                </button>
            </div>
        </div>
        <div class="tab-pane fade" id="family" role="tabpanel" aria-labelledby="family-tab">
            <?php include 'partials/edit_staff_step3_family.php'; ?>
            <div class="mt-4 d-flex justify-content-between">
                <button type="button" class="btn btn-outline-secondary btn-prev" data-prev="#service-tab">
                    <i class="fa fa-arrow-left"></i> Previous
                </button>
                <button type="button" class="btn btn-outline-primary btn-next" data-next="#education-tab">
                    Next <i class="fa fa-arrow-right"></i>
                </button>
            </div>
        </div>
        <div class="tab-pane fade" id="education" role="tabpanel" aria-labelledby="education-tab">
            <?php include 'partials/edit_staff_step4_education.php'; ?>
            <div class="mt-4 d-flex justify-content-between">
                <button type="button" class="btn btn-outline-secondary btn-prev" data-prev="#family-tab">
                    <i class="fa fa-arrow-left"></i> Previous
                </button>
                <button type="submit" class="btn btn-success">
                    <i class="fa fa-save"></i> Save Staff
                </button>
            </div>
        </div>
    </div>
</form>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function () {
    // --- TAB BUTTONS (Next/Prev) ---
    document.querySelectorAll('.btn-next').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var nextTabBtn = document.querySelector(btn.getAttribute('data-next'));
            if (nextTabBtn) {
                bootstrap.Tab.getOrCreateInstance(nextTabBtn).show();
                nextTabBtn.focus();
            }
        });
    });
    document.querySelectorAll('.btn-prev').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var prevTabBtn = document.querySelector(btn.getAttribute('data-prev'));
            if (prevTabBtn) {
                bootstrap.Tab.getOrCreateInstance(prevTabBtn).show();
                prevTabBtn.focus();
            }
        });
    });
     // --- SPOUSE SECTION TOGGLE ---
    function toggleSpouseFields() {
        var marital = document.querySelector('[name="marital"]');
        var spouseSection = document.getElementById('spouse-section');
        if (!marital || !spouseSection) return;
        if (marital.value === 'Married') {
            spouseSection.style.display = '';
        } else {
            spouseSection.style.display = 'none';
            spouseSection.querySelectorAll('input').forEach(function(input){ input.value = ''; });
        }
    }
    var maritalField = document.querySelector('[name="marital"]');
    if (maritalField) {
        maritalField.addEventListener('change', toggleSpouseFields);
        setTimeout(toggleSpouseFields, 10); // Run after DOM/render
    }
});
// Tab persistence
document.addEventListener('DOMContentLoaded', function () {
    var tabEl = document.querySelectorAll('button[data-bs-toggle="tab"]');
    tabEl.forEach(function (el) {
        el.addEventListener('shown.bs.tab', function (event) {
            localStorage.setItem('editStaffActiveTab', event.target.id);
            // Mark current tab as active button
            tabEl.forEach(function(b){ b.classList.remove('active'); });
            event.target.classList.add('active');
        });
    });
    var lastTab = localStorage.getItem('editStaffActiveTab');
    if (lastTab) {
        var triggerEl = document.getElementById(lastTab);
        if (triggerEl) new bootstrap.Tab(triggerEl).show();
    }
    // Dynamic Add/Remove for academic, proftech, milcourse, etc.
    function addDynamicRow(listId, inputNames) {
        var list = document.getElementById(listId);
        if (!list) return;
        var row = document.createElement('div');
        row.className = 'row mb-2';
        inputNames.forEach(function(name) {
            var col = document.createElement('div');
            col.className = 'col';
            var input = document.createElement('input');
            input.type = name.type || 'text';
            input.className = 'form-control form-control-sm';
            input.name = name.name + '[]';
            input.placeholder = name.placeholder;
            col.appendChild(input);
            row.appendChild(col);
        });
        // Add remove button
        var colBtn = document.createElement('div');
        colBtn.className = 'col-auto';
        var btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'btn btn-danger btn-sm';
        btn.innerHTML = '<i class="fa fa-minus"></i>';
        btn.onclick = function(){ row.remove(); };
        colBtn.appendChild(btn);
        row.appendChild(colBtn);

        list.appendChild(row);
    }

    document.getElementById('add-academic-row')?.addEventListener('click', function() {
        addDynamicRow('academic-list', [
            {name:'academic_institution', placeholder:'Institution'},
            {name:'academic_start', placeholder:'Start', type:'date'},
            {name:'academic_end', placeholder:'End', type:'date'},
            {name:'academic_qualification', placeholder:'Qualification'}
        ]);
    });
    document.getElementById('add-proftech-row')?.addEventListener('click', function() {
        addDynamicRow('proftech-list', [
            {name:'proftech_profession', placeholder:'Profession'},
            {name:'proftech_course', placeholder:'Course'},
            {name:'proftech_letters', placeholder:'Letters'},
            {name:'proftech_institution', placeholder:'Institution'},
            {name:'proftech_year', placeholder:'Year'}
        ]);
    });
    document.getElementById('add-milcourse-row')?.addEventListener('click', function() {
        addDynamicRow('milcourse-list', [
            {name:'milcourse_name', placeholder:'Course Name'},
            {name:'milcourse_institution', placeholder:'Institution'},
            {name:'milcourse_start', placeholder:'Start', type:'date'},
            {name:'milcourse_end', placeholder:'End', type:'date'},
            {name:'milcourse_result', placeholder:'Result'},
            {name:'milcourse_type', placeholder:'Type'}
        ]);
    });
    document.getElementById('add-tradegroup-row')?.addEventListener('click', function() {
        addDynamicRow('tradegroup-list', [
            {name:'tradegroup_employment', placeholder:'Employment'},
            {name:'tradegroup_group', placeholder:'Group'},
            {name:'tradegroup_class', placeholder:'Class'},
            {name:'tradegroup_date', placeholder:'Date', type:'date'},
            {name:'tradegroup_authority', placeholder:'Authority'}
        ]);
    });
    document.getElementById('add-award-row')?.addEventListener('click', function() {
        addDynamicRow('awards-list', [
            {name:'award_name', placeholder:'Award'},
            {name:'award_date', placeholder:'Date', type:'date'},
            {name:'award_authority', placeholder:'Authority'}
        ]);
    });
    document.getElementById('add-appointment-row')?.addEventListener('click', function() {
        addDynamicRow('appointments-list', [
            {name:'appointment_name', placeholder:'Appointment'},
            {name:'appointment_unit', placeholder:'Unit'},
            {name:'appointment_start', placeholder:'Start', type:'date'},
            {name:'appointment_end', placeholder:'End', type:'date'},
            {name:'appointment_authority', placeholder:'Authority'}
        ]);
    });
    document.getElementById('add-promotion-row')?.addEventListener('click', function() {
        addDynamicRow('promotions-list', [
            {name:'promotion_rank', placeholder:'Current Rank'},
            {name:'promotion_date_from', placeholder:'From', type:'date'},
            {name:'promotion_date_to', placeholder:'To', type:'date'},
            {name:'promotion_next_rank', placeholder:'Next Rank'},
            {name:'promotion_type', placeholder:'Type'},
            {name:'promotion_authority', placeholder:'Authority'},
            {name:'promotion_remark', placeholder:'Remark'}
        ]);
    });
});
</script>

<style>
    .nav-tabs .nav-link.active, #staffTab .btn.active {
        background-color: #0d6efd !important;
        color: #fff !important;
        border: 1px solid #0d6efd !important;
    }
    #staffTab .btn {
        border-radius: 100px;
        transition: background 0.15s, color 0.15s;
        font-weight: 500;
        font-size: 1rem;
    }
    .tab-content {
        min-height: 350px;
    }
    .btn-next, .btn-prev, .btn-success {
        min-width: 120px;
        font-size: 1rem;
        font-weight: 500;
        border-radius: .25rem;
    }
    .btn-next, .btn-success {
        background-color: #0d6efd;
        color: #fff;
        border: none;
    }
    .btn-next:hover, .btn-success:hover {
        background-color: #0b5ed7;
    }
    .btn-prev {
        background-color: #6c757d;
        color: #fff;
        border: none;
    }
    .btn-prev:hover {
        background-color: #495057;
    }
    /* Hide spouse section by default (handled by JS) */
    #spouse-section { transition: all 0.2s; }
</style>