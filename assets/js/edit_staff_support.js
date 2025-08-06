// Supporting JavaScript for Edit Staff Member page
// Includes dynamic add/remove for Operations, Deployments, Education, Skills/Courses
// Real-time client-side validation and confirmation
// Multi-step form functionality

// --- Utility ---
function escapeHtml(text) {
    return text == null ? '' : text.replace(/[&<>"']/g, function (m) {
        return ({
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        })[m];
    });
}

// --- Multi-step Form Functions ---
document.addEventListener('DOMContentLoaded', function() {
    console.log('Initializing multi-step form');
    initMultiStepForm();
    highlightInvalidFields();
    
    // Initialize flash alerts
    const alerts = document.querySelectorAll('.alert-success, .alert-danger');
    alerts.forEach(function(alert) {
        setTimeout(function() {
            if (alert && alert.parentNode) {
                const bsAlert = bootstrap.Alert.getInstance(alert);
                if (bsAlert) bsAlert.close();
                else alert.remove();
            }
        }, 5000);
    });
    
    // Validate form on submit
    const editForm = document.getElementById('editStaffForm');
    if (editForm) {
        editForm.addEventListener('submit', function(e) {
            if (!validateForm()) {
                e.preventDefault();
                alert('Please fix the validation errors before submitting.');
                return false;
            }
        });
    }
    
    // Validate form button
    const validateBtn = document.getElementById('validateFormBtn');
    if (validateBtn) {
        validateBtn.addEventListener('click', function() {
            validateForm(true);
        });
    }
});

function initMultiStepForm() {
    // Get all step elements
    const steps = document.querySelectorAll('.form-step');
    if (steps.length === 0) return; // Not a multi-step form
    
    // Set up step navigation
    const stepButtons = document.querySelectorAll('.step');
    stepButtons.forEach(function(button) {
        button.addEventListener('click', function() {
            const stepNum = parseInt(this.getAttribute('data-step'));
            goToStep(stepNum);
        });
    });
    
    // Show the first step by default
    goToStep(1);
}

function goToStep(stepNumber) {
    console.log(`Going to step ${stepNumber}`);
    // Hide all steps
    const steps = document.querySelectorAll('.form-step');
    steps.forEach(function(step) {
        step.classList.remove('active');
    });
    
    // Show the requested step
    const currentStep = document.getElementById(`step${stepNumber}`);
    if (currentStep) {
        currentStep.classList.add('active');
    }
    
    // Update step indicators
    const stepIndicators = document.querySelectorAll('.step');
    stepIndicators.forEach(function(indicator) {
        const indicatorStep = parseInt(indicator.getAttribute('data-step'));
        
        // Remove all classes first
        indicator.classList.remove('active', 'completed');
        
        // Add appropriate class
        if (indicatorStep === stepNumber) {
            indicator.classList.add('active');
        } else if (indicatorStep < stepNumber) {
            indicator.classList.add('completed');
        }
    });
    
    // Scroll to top of the step
    window.scrollTo({
        top: currentStep ? currentStep.offsetTop - 100 : 0,
        behavior: 'smooth'
    });
}

function validateStep(stepNumber) {
    const step = document.getElementById(`step${stepNumber}`);
    if (!step) return true; // Step not found, consider it valid
    
    const requiredFields = step.querySelectorAll('[required]');
    let isValid = true;
    
    requiredFields.forEach(function(field) {
        field.classList.remove('is-invalid-field');
        
        if (!field.value.trim()) {
            field.classList.add('is-invalid-field');
            isValid = false;
            
            // Show error message if there's a container for it
            const errorElement = document.getElementById(`${field.id}-error`);
            if (errorElement) {
                errorElement.textContent = 'This field is required';
                errorElement.style.display = 'block';
            }
        }
    });
    
    return isValid;
}

function validateForm(showAlert = false) {
    let isValid = true;
    let validationReport = [];
    
    // Check all required fields
    const requiredFields = document.querySelectorAll('[required]');
    requiredFields.forEach(function(field) {
        field.classList.remove('is-invalid-field');
        
        if (!field.value.trim()) {
            field.classList.add('is-invalid-field');
            isValid = false;
            
            // Show error message
            const errorElement = document.getElementById(`${field.id}-error`);
            if (errorElement) {
                errorElement.textContent = 'This field is required';
                errorElement.style.display = 'block';
            }
            
            validationReport.push(`Field "${field.name}" is required but empty`);
        }
    });
    
    // Additional validation logic can be added here
    
    // Report on dynamic sections
    const operationsCount = document.getElementById('operationsList')?.children.length || 0;
    const deploymentsCount = document.getElementById('deploymentsList')?.children.length || 0;
    const educationCount = document.getElementById('educationList')?.children.length || 0;
    const skillsCount = document.getElementById('skillsList')?.children.length || 0;
    
    validationReport.push(`Operations: ${operationsCount} items`);
    validationReport.push(`Deployments: ${deploymentsCount} items`);
    validationReport.push(`Education: ${educationCount} items`);
    validationReport.push(`Skills: ${skillsCount} items`);
    
    // If requested, show the validation report
    if (showAlert) {
        alert(`Validation Results:\n${validationReport.join('\n')}\n\nForm is ${isValid ? 'valid' : 'invalid'}`);
    }
    
    // If validation failed, scroll to the first invalid field
    if (!isValid) {
        const firstInvalid = document.querySelector('.is-invalid-field');
        if (firstInvalid) {
            firstInvalid.focus();
            firstInvalid.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
    }
    
    return isValid;
}

function highlightInvalidFields() {
    // This function highlights fields with validation errors
    const errorList = document.querySelector('.alert-danger ul');
    if (!errorList) return;
    
    // Extract error messages
    const errors = Array.from(errorList.querySelectorAll('li')).map(li => li.textContent.trim());
    
    // Common field names to check for in error messages
    const fieldNames = {
        'first name': 'fname',
        'last name': 'lname',
        'rank': 'rankID',
        'unit': 'unitID',
        'gender': 'gender',
        'service status': 'svcStatus',
        'NRC': 'NRC',
        'date of birth': 'DOB'
    };
    
    // Check each error and highlight corresponding field
    errors.forEach(error => {
        const lowerError = error.toLowerCase();
        
        // Try to match field name in error message
        for (const [errorText, fieldId] of Object.entries(fieldNames)) {
            if (lowerError.includes(errorText.toLowerCase())) {
                const field = document.getElementById(fieldId);
                if (field) {
                    field.classList.add('is-invalid-field');
                    
                    // Add error message to error container if it exists
                    const errorElement = document.getElementById(`${fieldId}-error`);
                    if (errorElement) {
                        errorElement.textContent = error;
                        errorElement.style.display = 'block';
                    }
                    
                    // If this is the first error, go to its form step
                    const stepContainer = field.closest('.form-step');
                    if (stepContainer) {
                        const stepId = stepContainer.id;
                        if (stepId && stepId.startsWith('step')) {
                            const stepNumber = parseInt(stepId.substring(4));
                            goToStep(stepNumber);
                        }
                    }
                    
                    break;
                }
            }
        }
    });
}

// --- Dynamic Row Functions ---
function addOperationRow(data = {}) {
    const container = document.getElementById('operationsList');
    const idx = container.children.length;
    // You must populate window.operationsOptions from PHP, e.g.:
    // <script>window.operationsOptions = <?=json_encode($operationsOptions)?>;</script>
    const operationsOptions = window.operationsOptions || [];
    let optionsHtml = '<option value="">Select Operation</option>';
    operationsOptions.forEach(op => {
        optionsHtml += `<option value="${op.id}"${data.operation_id == op.id ? ' selected' : ''}>${escapeHtml(op.name)} (${escapeHtml(op.code)})</option>`;
    });
    const row = document.createElement('div');
    row.className = 'row g-2 mb-2';
    row.innerHTML = `
        <div class="col-md-3"><select name="operations[${idx}][operation_id]" class="form-select">${optionsHtml}</select></div>
        <div class="col-md-2"><input type="text" name="operations[${idx}][role]" class="form-control" placeholder="Role" value="${escapeHtml(data.role || '')}"></div>
        <div class="col-md-2"><input type="date" name="operations[${idx}][start_date]" class="form-control" value="${escapeHtml(data.start_date || '')}"></div>
        <div class="col-md-2"><input type="date" name="operations[${idx}][end_date]" class="form-control" value="${escapeHtml(data.end_date || '')}"></div>
        <div class="col-md-2"><input type="number" name="operations[${idx}][performance_rating]" class="form-control" placeholder="Rating" min="1" max="10" value="${escapeHtml(data.performance_rating || '')}"></div>
        <div class="col-md-1"><input type="text" name="operations[${idx}][remarks]" class="form-control" placeholder="Remarks" value="${escapeHtml(data.remarks || '')}"></div>
        <div class="col-md-1"><button type="button" class="btn btn-danger" onclick="this.closest('.row').remove()"><i class="fa fa-trash"></i></button></div>
    `;
    container.appendChild(row);
}

function addDeploymentRow(data = {}) {
    const container = document.getElementById('deploymentsList');
    const idx = container.children.length;
    const row = document.createElement('div');
    row.className = 'row g-2 mb-2';
    row.innerHTML = `
        <div class="col-md-2"><input type="text" name="deployments[${idx}][deployment_name]" class="form-control" placeholder="Deployment Name" value="${escapeHtml(data.deployment_name || '')}"></div>
        <div class="col-md-2"><input type="text" name="deployments[${idx}][mission_type]" class="form-control" placeholder="Mission Type" value="${escapeHtml(data.mission_type || '')}"></div>
        <div class="col-md-2"><input type="text" name="deployments[${idx}][location]" class="form-control" placeholder="Location" value="${escapeHtml(data.location || '')}"></div>
        <div class="col-md-2"><input type="text" name="deployments[${idx}][country]" class="form-control" placeholder="Country" value="${escapeHtml(data.country || '')}"></div>
        <div class="col-md-2"><input type="date" name="deployments[${idx}][start_date]" class="form-control" value="${escapeHtml(data.start_date || '')}"></div>
        <div class="col-md-2"><input type="date" name="deployments[${idx}][end_date]" class="form-control" value="${escapeHtml(data.end_date || '')}"></div>
        <div class="col-md-1"><input type="number" name="deployments[${idx}][duration_months]" class="form-control" placeholder="Months" min="0" value="${escapeHtml(data.duration_months || '')}"></div>
        <div class="col-md-2"><input type="text" name="deployments[${idx}][deployment_status]" class="form-control" placeholder="Status" value="${escapeHtml(data.deployment_status || '')}"></div>
        <div class="col-md-2"><input type="text" name="deployments[${idx}][rank_during_deployment]" class="form-control" placeholder="Rank During" value="${escapeHtml(data.rank_during_deployment || '')}"></div>
        <div class="col-md-2"><input type="text" name="deployments[${idx}][role_during_deployment]" class="form-control" placeholder="Role During" value="${escapeHtml(data.role_during_deployment || '')}"></div>
        <div class="col-md-2"><input type="text" name="deployments[${idx}][commanding_officer]" class="form-control" placeholder="CO" value="${escapeHtml(data.commanding_officer || '')}"></div>
        <div class="col-md-2"><input type="number" name="deployments[${idx}][deployment_allowance]" class="form-control" placeholder="Allowance" min="0" value="${escapeHtml(data.deployment_allowance || '')}"></div>
        <div class="col-md-2"><input type="text" name="deployments[${idx}][notes]" class="form-control" placeholder="Notes" value="${escapeHtml(data.notes || '')}"></div>
        <div class="col-md-1"><button type="button" class="btn btn-danger" onclick="this.closest('.row').remove()"><i class="fa fa-trash"></i></button></div>
    `;
    container.appendChild(row);
}

function addEducationRow(data = {}) {
    const container = document.getElementById('educationList');
    const idx = container.children.length;
    const row = document.createElement('div');
    row.className = 'row g-2 mb-2';
    row.innerHTML = `
        <div class="col-md-2"><input type="text" name="education[${idx}][institution]" class="form-control" placeholder="Institution" value="${escapeHtml(data.institution || '')}"></div>
        <div class="col-md-2"><input type="text" name="education[${idx}][qualification]" class="form-control" placeholder="Qualification" value="${escapeHtml(data.qualification || '')}"></div>
        <div class="col-md-2"><input type="text" name="education[${idx}][level]" class="form-control" placeholder="Level" value="${escapeHtml(data.level || '')}"></div>
        <div class="col-md-2"><input type="text" name="education[${idx}][field_of_study]" class="form-control" placeholder="Field of Study" value="${escapeHtml(data.field_of_study || '')}"></div>
        <div class="col-md-1"><input type="number" name="education[${idx}][year_started]" class="form-control" placeholder="Start" min="1900" max="2099" value="${escapeHtml(data.year_started || '')}"></div>
        <div class="col-md-1"><input type="number" name="education[${idx}][year_completed]" class="form-control" placeholder="End" min="1900" max="2099" value="${escapeHtml(data.year_completed || '')}"></div>
        <div class="col-md-1"><input type="text" name="education[${idx}][grade_obtained]" class="form-control" placeholder="Grade" value="${escapeHtml(data.grade_obtained || '')}"></div>
        <div class="col-md-1"><input type="checkbox" name="education[${idx}][is_highest_qualification]" value="1" ${data.is_highest_qualification ? 'checked' : ''}> Highest</div>
        <div class="col-md-1"><button type="button" class="btn btn-danger" onclick="this.closest('.row').remove()"><i class="fa fa-trash"></i></button></div>
    `;
    container.appendChild(row);
}

function addSkillRow(data = {}) {
    const container = document.getElementById('skillsList');
    const idx = container.children.length;
    const row = document.createElement('div');
    row.className = 'row g-2 mb-2';
    row.innerHTML = `
        <div class="col-md-2"><input type="text" name="skills[${idx}][course_name]" class="form-control" placeholder="Course Name" value="${escapeHtml(data.course_name || '')}"></div>
        <div class="col-md-2"><input type="text" name="skills[${idx}][course_type]" class="form-control" placeholder="Course Type" value="${escapeHtml(data.course_type || '')}"></div>
        <div class="col-md-2"><input type="text" name="skills[${idx}][institution]" class="form-control" placeholder="Institution" value="${escapeHtml(data.institution || '')}"></div>
        <div class="col-md-2"><input type="date" name="skills[${idx}][start_date]" class="form-control" value="${escapeHtml(data.start_date || '')}"></div>
        <div class="col-md-2"><input type="date" name="skills[${idx}][end_date]" class="form-control" value="${escapeHtml(data.end_date || '')}"></div>
        <div class="col-md-1"><input type="number" name="skills[${idx}][duration_days]" class="form-control" placeholder="Days" min="0" value="${escapeHtml(data.duration_days || '')}"></div>
        <div class="col-md-2"><input type="text" name="skills[${idx}][certificate_number]" class="form-control" placeholder="Certificate No" value="${escapeHtml(data.certificate_number || '')}"></div>
        <div class="col-md-1"><input type="text" name="skills[${idx}][grade_obtained]" class="form-control" placeholder="Grade" value="${escapeHtml(data.grade_obtained || '')}"></div>
        <div class="col-md-2"><input type="text" name="skills[${idx}][location]" class="form-control" placeholder="Location" value="${escapeHtml(data.location || '')}"></div>
        <div class="col-md-2"><input type="number" name="skills[${idx}][cost]" class="form-control" placeholder="Cost" min="0" value="${escapeHtml(data.cost || '')}"></div>
        <div class="col-md-2"><input type="text" name="skills[${idx}][sponsored_by]" class="form-control" placeholder="Sponsored By" value="${escapeHtml(data.sponsored_by || '')}"></div>
        <div class="col-md-1"><button type="button" class="btn btn-danger" onclick="this.closest('.row').remove()"><i class="fa fa-trash"></i></button></div>
    `;
    container.appendChild(row);
}

// --- Event Listeners ---
document.addEventListener('DOMContentLoaded', function() {
    // Add dynamic row buttons
    const opBtn = document.getElementById('addOperationBtn');
    if (opBtn) opBtn.addEventListener('click', function() { addOperationRow(); });
    const depBtn = document.getElementById('addDeploymentBtn');
    if (depBtn) depBtn.addEventListener('click', function() { addDeploymentRow(); });
    const eduBtn = document.getElementById('addEducationBtn');
    if (eduBtn) eduBtn.addEventListener('click', function() { addEducationRow(); });
    const skillBtn = document.getElementById('addSkillBtn');
    if (skillBtn) skillBtn.addEventListener('click', function() { addSkillRow(); });

    // Real-time field validation
    const fields = ['fname', 'lname', 'rankID', 'unitID', 'NRC', 'DOB', 'gender', 'svcStatus'];
    fields.forEach(fieldName => {
        const field = document.getElementById(fieldName);
        if (field) {
            field.addEventListener('input', function() { validateField(fieldName, this.value); });
            field.addEventListener('blur', function() { validateField(fieldName, this.value); });
        }
    });

    function validateField(fieldName, value) {
        const field = document.getElementById(fieldName);
        const errorDiv = document.getElementById(fieldName + '-error');
        let isValid = true;
        let errorMessage = '';
        switch(fieldName) {
            case 'fname':
            case 'lname':
                if (value.trim().length < <?=MIN_NAME_LENGTH?>) {
                    isValid = false;
                    errorMessage = `Must be at least <?=MIN_NAME_LENGTH?> characters long`;
                } else if (value.trim().length > <?=MAX_NAME_LENGTH?>) {
                    isValid = false;
                    errorMessage = `Must not exceed <?=MAX_NAME_LENGTH?> characters`;
                } else if (!/^[a-zA-Z\s\-'\.]+$/.test(value.trim())) {
                    isValid = false;
                    errorMessage = 'Only letters, spaces, hyphens, apostrophes, and dots are allowed';
                }
                break;
            case 'NRC':
                if (value.trim().length > <?=MAX_NRC_LENGTH?>) {
                    isValid = false;
                    errorMessage = `Must not exceed <?=MAX_NRC_LENGTH?> characters`;
                }
                break;
            case 'DOB':
                if (value) {
                    const birthDate = new Date(value);
                    const today = new Date();
                    const age = Math.floor((today - birthDate) / (365.25 * 24 * 60 * 60 * 1000));
                    if (birthDate > today) {
                        isValid = false;
                        errorMessage = 'Date of birth cannot be in the future';
                    } else if (age < <?=MIN_AGE_YEARS?>) {
                        isValid = false;
                        errorMessage = `Must be at least <?=MIN_AGE_YEARS?> years old`;
                    } else if (age > <?=MAX_AGE_YEARS?>) {
                        isValid = false;
                        errorMessage = `Cannot be older than <?=MAX_AGE_YEARS?> years`;
                    }
                }
                break;
            case 'rankID':
            case 'unitID':
            case 'gender':
            case 'svcStatus':
                if (!value) {
                    isValid = false;
                    errorMessage = 'This field is required';
                }
                break;
        }
        if (isValid) {
            field.classList.remove('is-invalid', 'form-error');
            field.classList.add('is-valid');
            if (errorDiv) errorDiv.textContent = '';
        } else {
            field.classList.remove('is-valid');
            field.classList.add('is-invalid', 'form-error');
            if (errorDiv) errorDiv.textContent = errorMessage;
        }
        return isValid;
    }

    // Form submission
    const form = document.getElementById('editStaffForm');
    if (form) {
        form.addEventListener('submit', function(e) {
            console.log('Form submission initiated');
            let isFormValid = true;
            fields.forEach(fieldName => {
                const field = document.getElementById(fieldName);
                if (field && !validateField(fieldName, field.value)) {
                    isFormValid = false;
                    console.log('Field validation failed:', fieldName);
                }
            });
            if (!isFormValid) {
                e.preventDefault();
                console.log('Form validation failed, preventing submission');
                showAlert('danger', 'Please correct the errors in the form before submitting.');
                return false;
            }
            
            // Debug form data
            console.log('Form data before submission:');
            const formData = new FormData(form);
            for (let [key, value] of formData.entries()) {
                console.log(key, ':', value);
            }
            
            // Check dynamic fields
            console.log('Operations count:', document.getElementById('operationsList')?.children.length || 0);
            console.log('Deployments count:', document.getElementById('deploymentsList')?.children.length || 0);
            console.log('Education count:', document.getElementById('educationList')?.children.length || 0);
            console.log('Skills count:', document.getElementById('skillsList')?.children.length || 0);
            
            if (!confirm('Are you sure you want to update this staff member\'s information?')) {
                e.preventDefault();
                console.log('User cancelled form submission');
                return false;
            }
            
            console.log('Form submission confirmed by user');
        });
    }

    // Alert Utility
    function showAlert(type, message) {
        let alertContainer = document.getElementById('alertContainer');
        if (!alertContainer) {
            alertContainer = document.createElement('div');
            alertContainer.id = 'alertContainer';
            document.body.prepend(alertContainer);
        }
        const alertDiv = document.createElement('div');
        alertDiv.className = `alert alert-${type} alert-dismissible fade show`;
        alertDiv.setAttribute('role', 'alert');
        alertDiv.innerHTML = `
            ${type === 'success' ? '<i class="fa fa-check-circle"></i>' : '<i class="fa fa-exclamation-triangle"></i>'}
            ${escapeHtml(message)}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        `;
        alertContainer.innerHTML = '';
        alertContainer.appendChild(alertDiv);
        setTimeout(function() {
            alertDiv.classList.remove('show');
            alertDiv.classList.add('hide');
            alertDiv.remove();
        }, 5000);
    }
});