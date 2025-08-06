// NOK and ALT NOK real-time validation
document.addEventListener('DOMContentLoaded', function() {
    // NOK NRC
    const nokNrcInput = document.querySelector('[name="nok_nrc"]');
    if (nokNrcInput) {
        nokNrcInput.addEventListener('blur', function() {
            clearFieldError('nok_nrc');
            if (nokNrcInput.value && !validateNRC(nokNrcInput.value)) {
                showFieldError('nok_nrc', 'Invalid NRC format for Next of Kin.');
            }
        });
    }
    // NOK phone
    const nokPhoneInput = document.querySelector('[name="nok_phone"]');
    if (nokPhoneInput) {
        nokPhoneInput.addEventListener('blur', function() {
            clearFieldError('nok_phone');
            if (nokPhoneInput.value && !validatePhone(nokPhoneInput.value)) {
                showFieldError('nok_phone', 'Invalid phone number for Next of Kin.');
            }
        });
    }
    // NOK email
    const nokEmailInput = document.querySelector('[name="nok_email"]');
    if (nokEmailInput) {
        nokEmailInput.addEventListener('blur', function() {
            clearFieldError('nok_email');
            if (nokEmailInput.value && !validateEmail(nokEmailInput.value)) {
                showFieldError('nok_email', 'Invalid email address for Next of Kin.');
            }
        });
    }
    // ALT NOK NRC
    const altnokNrcInput = document.querySelector('[name="altnok_nrc"]');
    if (altnokNrcInput) {
        altnokNrcInput.addEventListener('blur', function() {
            clearFieldError('altnok_nrc');
            if (altnokNrcInput.value && !validateNRC(altnokNrcInput.value)) {
                showFieldError('altnok_nrc', 'Invalid NRC format for Alternate Next of Kin.');
            }
        });
    }
    // ALT NOK phone
    const altnokPhoneInput = document.querySelector('[name="altnok_phone"]');
    if (altnokPhoneInput) {
        altnokPhoneInput.addEventListener('blur', function() {
            clearFieldError('altnok_phone');
            if (altnokPhoneInput.value && !validatePhone(altnokPhoneInput.value)) {
                showFieldError('altnok_phone', 'Invalid phone number for Alternate Next of Kin.');
            }
        });
    }
    // ALT NOK email
    const altnokEmailInput = document.querySelector('[name="altnok_email"]');
    if (altnokEmailInput) {
        altnokEmailInput.addEventListener('blur', function() {
            clearFieldError('altnok_email');
            if (altnokEmailInput.value && !validateEmail(altnokEmailInput.value)) {
                showFieldError('altnok_email', 'Invalid email address for Alternate Next of Kin.');
            }
        });
    }
});
// Real-time validation helpers
function validateNRC(nrc) {
    return /^\d{2}\/\w{3,}\d{4,}\/\d{2}$/i.test(nrc);
}
function validateEmail(email) {
    return /^[^@\s]+@[^@\s]+\.[^@\s]+$/.test(email);
}
function validatePhone(phone) {
    return /^\+?\d{8,15}$/.test(phone);
}
function validateRequired(val) {
    return val && val.trim().length > 0;
}
function validateLength(val, max) {
    return val.length <= max;
}
function validateAge(dob) {
    if (!dob) return false;
    const birth = new Date(dob);
    if (isNaN(birth.getTime())) return false;
    const today = new Date();
    let age = today.getFullYear() - birth.getFullYear();
    const m = today.getMonth() - birth.getMonth();
    if (m < 0 || (m === 0 && today.getDate() < birth.getDate())) age--;
    return age >= 18;
}

function showFieldError(field, msg) {
    let el = document.getElementById(field + 'Error');
    if (!el) {
        const input = document.getElementsByName(field)[0];
        if (!input) return;
        el = document.createElement('div');
        el.className = 'invalid-feedback d-block';
        el.id = field + 'Error';
        input.parentNode.appendChild(el);
    }
    el.textContent = msg;
}
function clearFieldError(field) {
    const el = document.getElementById(field + 'Error');
    if (el) el.remove();
}

// Attach real-time validation events
document.addEventListener('DOMContentLoaded', function() {
    const nrcInput = document.querySelector('[name="nrc"]');
    if (nrcInput) {
        nrcInput.addEventListener('blur', function() {
            clearFieldError('nrc');
            if (!validateRequired(nrcInput.value)) {
                showFieldError('nrc', 'NRC is required.');
            } else if (!validateNRC(nrcInput.value)) {
                showFieldError('nrc', 'Invalid NRC format.');
            }
        });
    }
    const emailInput = document.querySelector('[name="email"]');
    if (emailInput) {
        emailInput.addEventListener('blur', function() {
            clearFieldError('email');
            if (!validateRequired(emailInput.value)) {
                showFieldError('email', 'Email is required.');
            } else if (!validateEmail(emailInput.value)) {
                showFieldError('email', 'Invalid email address.');
            }
        });
    }
    const phoneInput = document.querySelector('[name="phone"]');
    if (phoneInput) {
        phoneInput.addEventListener('blur', function() {
            clearFieldError('phone');
            if (phoneInput.value && !validatePhone(phoneInput.value)) {
                showFieldError('phone', 'Invalid phone number.');
            }
        });
    }
    const firstNameInput = document.querySelector('[name="first_name"]');
    if (firstNameInput) {
        firstNameInput.addEventListener('blur', function() {
            clearFieldError('first_name');
            if (!validateRequired(firstNameInput.value)) {
                showFieldError('first_name', 'First name is required.');
            } else if (!validateLength(firstNameInput.value, 50)) {
                showFieldError('first_name', 'First name must be 50 characters or less.');
            }
        });
    }
    const lastNameInput = document.querySelector('[name="last_name"]');
    if (lastNameInput) {
        lastNameInput.addEventListener('blur', function() {
            clearFieldError('last_name');
            if (!validateRequired(lastNameInput.value)) {
                showFieldError('last_name', 'Last name is required.');
            } else if (!validateLength(lastNameInput.value, 50)) {
                showFieldError('last_name', 'Last name must be 50 characters or less.');
            }
        });
    }
    const dobInput = document.querySelector('[name="dob"]');
    if (dobInput) {
        dobInput.addEventListener('blur', function() {
            clearFieldError('dob');
            if (!validateRequired(dobInput.value)) {
                showFieldError('dob', 'Date of birth is required.');
            } else if (!validateAge(dobInput.value)) {
                showFieldError('dob', 'Staff member must be at least 18 years old.');
            }
        });
    }
});
(function() {
    // ...existing code...
    document.addEventListener('DOMContentLoaded', function() {
        // ...existing code...
        setupDuplicateChecks();
    });

    function setupDuplicateChecks() {
        var nrcInput = document.getElementById('nrc');
        var emailInput = document.getElementById('email');
        if (nrcInput) {
            nrcInput.addEventListener('blur', function() {
                checkDuplicate('nrc', nrcInput.value, function(exists) {
                    showDuplicateError(nrcInput, exists, 'A staff member with this NRC already exists.');
                });
            });
        }
        if (emailInput) {
            emailInput.addEventListener('blur', function() {
                checkDuplicate('email', emailInput.value, function(exists) {
                    showDuplicateError(emailInput, exists, 'A staff member with this email address already exists.');
                });
            });
        }
    }

    function checkDuplicate(type, value, callback) {
        if (!value) return callback(false);
        var data = new FormData();
        data.append(type, value);
        fetch('/Armis2/admin_branch/ajax_check_duplicate.php', {
            method: 'POST',
            body: data
        })
        .then(response => response.json())
        .then(json => {
            if (type === 'nrc') callback(json.nrc_exists);
            if (type === 'email') callback(json.email_exists);
        })
        .catch(() => callback(false));
    }

    function showDuplicateError(input, exists, message) {
        var errorId = input.name + '-error';
        var errorDiv = document.getElementById(errorId);
        if (!errorDiv) {
            errorDiv = document.createElement('div');
            errorDiv.id = errorId;
            errorDiv.className = 'text-danger field-help-text';
            input.parentNode.appendChild(errorDiv);
        }
        if (exists) {
            errorDiv.textContent = message;
            input.classList.add('form-validation-error');
        } else {
            errorDiv.textContent = '';
            input.classList.remove('form-validation-error');
        }
    }
})();
// Enhanced Staff Registration Form Management (moved from inline script)
(function() {
    'use strict';
    let autoSaveInterval;
    let currentTabIndex = 0;
    let tabs = [];
    let validationErrors = {};
    let startTime = new Date();
    let timeTracker;
    let formStartTime;

    // Initialize form enhancement
    document.addEventListener('DOMContentLoaded', function() {
        initializeForm();
        setupAutoSave();
        setupValidation();
        setupNavigation();
        setupDraftManagement();
        setupTimeTracking();
        setupAdvancedFeatures();
        updateFormStatistics();
    });

    function initializeForm() {
        tabs = Array.from(document.querySelectorAll('.tab-pane'));
        // Add completion indicators to tabs
        document.querySelectorAll('#staffTab button[data-bs-toggle="tab"]').forEach((tab, index) => {
            const indicator = document.createElement('span');
            indicator.className = 'tab-completion-indicator';
            indicator.textContent = '0%';
            indicator.id = `tab-indicator-${index}`;
            tab.appendChild(indicator);
            // Track tab changes
            tab.addEventListener('shown.bs.tab', function() {
                currentTabIndex = index;
                updateFormStatistics();
            });
        });
        // Load saved draft if exists
        loadDraftFromStorage();
        // Initialize timer
        formStartTime = new Date();
        updateTimeSpent();
    }

    function setupAutoSave() {
        const form = document.getElementById('createStaffForm');
        if (!form) return;
        // Auto-save every 30 seconds (debounced)
        autoSaveInterval = setInterval(() => {
            if (hasFormChanged()) {
                try { saveDraft(); } catch (e) { console.error('Auto-save error', e); }
            }
        }, 30000);
        // Save on input change (debounced, 3s)
        let saveTimeout;
        form.addEventListener('input', function(e) {
            clearTimeout(saveTimeout);
            saveTimeout = setTimeout(() => {
                try { saveDraft(); updateFormStatistics(); } catch (e) { console.error('Draft save error', e); }
            }, 3000);
        });
    }

    function setupValidation() {
        const form = document.getElementById('createStaffForm');
        if (!form) return;
        // Real-time validation
        form.addEventListener('input', function(e) {
            validateField(e.target);
        });
        form.addEventListener('blur', function(e) {
            validateField(e.target);
        }, true);
        // Validate button
        const validateBtn = document.getElementById('validateFormBtn');
        if (validateBtn) {
            validateBtn.addEventListener('click', function() {
                if (!validateAllFields()) {
                    showValidationSummary();
                    // Auto-focus first invalid field
                    const firstInvalid = form.querySelector('.form-validation-error');
                    if (firstInvalid) firstInvalid.focus();
                }
            });
        }
        // Form submission validation
        form.addEventListener('submit', function(e) {
            if (!validateAllFields()) {
                e.preventDefault();
                showValidationSummary();
                // Auto-focus first invalid field
                const firstInvalid = form.querySelector('.form-validation-error');
                if (firstInvalid) firstInvalid.focus();
                // Log failed submission
                if (window.fetch) {
                    fetch('/Armis2/command/log.php', {method:'POST',body:JSON.stringify({event:'staff_form_failed',time:Date.now()}),headers:{'Content-Type':'application/json'}});
                }
            }
        });
    }

    function setupNavigation() {
        const prevBtn = document.getElementById('prevTab');
        const nextBtn = document.getElementById('nextTab');
        if (prevBtn) {
            prevBtn.addEventListener('click', function() { navigateTab(-1); });
        }
        if (nextBtn) {
            nextBtn.addEventListener('click', function() { navigateTab(1); });
        }
    }
    function navigateTab(direction) {
        const tabButtons = document.querySelectorAll('#staffTab button[data-bs-toggle="tab"]');
        if (tabButtons.length === 0) return;
        const newIndex = currentTabIndex + direction;
        if (newIndex >= 0 && newIndex < tabButtons.length) {
            currentTabIndex = newIndex;
            const targetTab = new bootstrap.Tab(tabButtons[currentTabIndex]);
            targetTab.show();
            updateFormStatistics();
        }
    }
    function setupDraftManagement() {
        const loadDraftBtn = document.getElementById('loadDraftBtn');
        const saveDraftBtn = document.getElementById('saveDraftBtn');
        const clearFormBtn = document.getElementById('clearFormBtn');
        const saveAndContinueBtn = document.getElementById('saveAndContinueBtn');
        if (loadDraftBtn) {
            loadDraftBtn.addEventListener('click', function() { try { loadDraftFromStorage(); } catch(e){} });
        }
        if (saveDraftBtn) {
            saveDraftBtn.addEventListener('click', function() { try { saveDraft(true); } catch(e){} });
        }
        if (clearFormBtn) {
            clearFormBtn.addEventListener('click', function() {
                if (confirm('Are you sure you want to clear the entire form? This action cannot be undone.')) {
                    try { clearForm(); } catch(e){}
                }
            });
        }
        if (saveAndContinueBtn) {
            saveAndContinueBtn.addEventListener('click', function() {
                try { saveDraft(true); } catch(e){}
                navigateTab(1);
                showNotification('Progress saved! Moving to next section.', 'success');
            });
        }
    }
    function validateField(field) {
        if (!field.name) return true;
        const value = field.value.trim();
        const isRequired = field.hasAttribute('required');
        let isValid = true;
        let errorMessage = '';
        // Required field validation
        if (isRequired && !value) {
            isValid = false;
            errorMessage = 'This field is required';
        }
        // Specific field validations
        switch(field.type) {
            case 'email':
                if (value && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value)) {
                    isValid = false;
                    errorMessage = 'Please enter a valid email address';
                }
                break;
            case 'tel':
                if (value && !/^\+?[\d\s\-\(\)]+$/.test(value)) {
                    isValid = false;
                    errorMessage = 'Please enter a valid phone number';
                }
                break;
            case 'date':
                if (value && field.name !== 'dateOfEnlistment') {
                    const date = new Date(value);
                    const today = new Date();
                    if (date > today) {
                        isValid = false;
                        errorMessage = 'Date cannot be in the future';
                    }
                }
                break;
        }
        // Custom validations
        if (field.name === 'svcNo' && value) {
            if (!/^[A-Za-z0-9\-\/]+$/.test(value)) {
                isValid = false;
                errorMessage = 'Service number can only contain letters, numbers, hyphens, and forward slashes';
            }
        }
        // Update field appearance
        field.classList.remove('form-validation-error', 'form-validation-success');
        if (isValid) {
            if (value) field.classList.add('form-validation-success');
        } else {
            field.classList.add('form-validation-error');
        }
        // Update error message
        updateFieldError(field, errorMessage);
        return isValid;
    }
    function updateFieldError(field, message) {
        let errorDiv = document.getElementById(field.name + '-error');
        if (!errorDiv) {
            errorDiv = document.createElement('div');
            errorDiv.id = field.name + '-error';
            errorDiv.className = 'text-danger field-help-text';
            field.parentNode.appendChild(errorDiv);
        }
        errorDiv.textContent = message;
    }
    function validateAllFields() {
        const form = document.getElementById('createStaffForm');
        const fields = form.querySelectorAll('input, select, textarea');
        let isValid = true;
        let errorCount = 0;
        fields.forEach(field => {
            if (!validateField(field)) {
                isValid = false;
                errorCount++;
            }
        });
        // Update error count
        const errorCountElement = document.getElementById('errorCount');
        if (errorCountElement) {
            errorCountElement.textContent = errorCount;
            errorCountElement.className = errorCount === 0 ? 'badge bg-success' : 'badge bg-danger';
        }
        return isValid;
    }
    function showValidationSummary() {
        const errorElements = document.querySelectorAll('.form-validation-error');
        const modal = document.getElementById('validationModal');
        const content = document.getElementById('validationSummaryContent');
        if (errorElements.length === 0) {
            content.innerHTML = `<div class="alert alert-success"><i class="fa fa-check-circle"></i> All validations passed! The form is ready for submission.</div>`;
            if (document.getElementById('fixErrorsBtn')) {
                document.getElementById('fixErrorsBtn').style.display = 'none';
            }
        } else {
            let errorHtml = `<div class="alert alert-danger"><i class="fa fa-exclamation-triangle"></i> Found ${errorElements.length} validation errors:</div><div class="list-group">`;
            errorElements.forEach((element, index) => {
                const label = element.parentNode.querySelector('label');
                const errorMsg = element.parentNode.querySelector('[id$="-error"]');
                const fieldName = label ? label.textContent : element.name;
                const errorText = errorMsg ? errorMsg.textContent : 'Invalid value';
                errorHtml += `<div class="list-group-item d-flex justify-content-between align-items-center"><div><strong>${fieldName}:</strong> ${errorText}</div><button class="btn btn-sm btn-outline-primary" onclick="scrollToField('${element.id || element.name}')">Go to field</button></div>`;
            });
            errorHtml += '</div>';
            content.innerHTML = errorHtml;
            if (document.getElementById('fixErrorsBtn')) {
                document.getElementById('fixErrorsBtn').style.display = 'block';
            }
        }
        new bootstrap.Modal(modal).show();
    }
    window.scrollToField = function(fieldId) {
        const field = document.getElementById(fieldId) || document.querySelector(`[name="${fieldId}"]`);
        if (field) {
            const modal = bootstrap.Modal.getInstance(document.getElementById('validationModal'));
            if (modal) modal.hide();
            const tabPane = field.closest('.tab-pane');
            if (tabPane) {
                const tabButton = document.querySelector(`[data-bs-target="#${tabPane.id}"]`);
                if (tabButton) {
                    new bootstrap.Tab(tabButton).show();
                }
            }
            field.scrollIntoView({ behavior: 'smooth', block: 'center' });
            field.focus();
        }
    };
    function setupTimeTracking() {
        timeTracker = setInterval(() => {
            const now = new Date();
            const elapsed = Math.floor((now - startTime) / 60000);
            const timeElement = document.getElementById('timeSpent');
            if (timeElement) {
                if (elapsed < 60) {
                    timeElement.textContent = elapsed + ' min';
                } else {
                    const hours = Math.floor(elapsed / 60);
                    const minutes = elapsed % 60;
                    timeElement.textContent = hours + 'h ' + minutes + 'm';
                }
            }
        }, 60000);
    }
    function updateFormStatistics() {
        const form = document.getElementById('createStaffForm');
        if (!form) return;
        const allFields = form.querySelectorAll('input, select, textarea');
        const requiredFields = form.querySelectorAll('input[required], select[required], textarea[required]');
        const filledFields = Array.from(allFields).filter(field => field.value.trim() !== '');
        const filledRequiredFields = Array.from(requiredFields).filter(field => field.value.trim() !== '');
        const errorFields = form.querySelectorAll('.form-validation-error');
        const completionPercentage = Math.round((filledFields.length / allFields.length) * 100);
        const requiredCompletion = `${filledRequiredFields.length}/${requiredFields.length}`;
        const completionElement = document.getElementById('completionPercentage');
        const requiredElement = document.getElementById('requiredFields');
        const progressElement = document.getElementById('formProgress');
        const progressText = document.getElementById('progressText');
        const errorCountElement = document.getElementById('errorCount');
        if (completionElement) completionElement.textContent = completionPercentage + '%';
        if (requiredElement) requiredElement.textContent = requiredCompletion;
        if (progressElement) progressElement.style.width = completionPercentage + '%';
        if (progressText) progressText.textContent = completionPercentage + '% Complete';
        if (errorCountElement) {
            errorCountElement.textContent = errorFields.length;
            errorCountElement.className = errorFields.length === 0 ? 'badge bg-success' : 'badge bg-danger';
        }
        updateTabIndicators();
    }
    function updateTabIndicators() {
        tabs.forEach((tab, index) => {
            const indicator = document.getElementById(`tab-indicator-${index}`);
            if (!indicator) return;
            const tabFields = tab.querySelectorAll('input, select, textarea');
            const filledFields = Array.from(tabFields).filter(field => field.value.trim() !== '');
            const percentage = tabFields.length > 0 ? Math.round((filledFields.length / tabFields.length) * 100) : 0;
            indicator.textContent = percentage + '%';
            indicator.className = 'tab-completion-indicator';
            if (percentage === 100) {
                indicator.classList.add('complete');
            } else if (percentage > 0) {
                indicator.classList.add('partial');
            }
        });
    }
    function saveDraft(showNotification = false) {
        const form = document.getElementById('createStaffForm');
        if (!form) return;
        const formData = new FormData(form);
        const draftData = {};
        for (let [key, value] of formData.entries()) {
            draftData[key] = value;
        }
        // Save to localStorage
        const draftInfo = {
            data: draftData,
            timestamp: new Date().toISOString(),
            progress: document.getElementById('completionPercentage')?.textContent || '0%'
        };
        try {
            localStorage.setItem('staffDraft', JSON.stringify(draftInfo));
        } catch (e) {
            console.error('localStorage error', e);
        }
        if (showNotification) {
            showAutoSaveNotification();
        }
    }
    function loadDraftFromStorage() {
        try {
            const saved = localStorage.getItem('staffDraft');
            if (saved) {
                const draft = JSON.parse(saved);
                const timestamp = new Date(draft.timestamp).toLocaleString();
                if (confirm(`Found a saved draft from ${timestamp} with ${draft.progress} completion. Would you like to load it?`)) {
                    loadDraft(draft.data);
                }
            }
        } catch (e) { console.error('Draft load error', e); }
    }
    function loadDraft(data) {
        const form = document.getElementById('createStaffForm');
        if (!form) return;
        Object.entries(data).forEach(([key, value]) => {
            const field = form.querySelector(`[name="${key}"]`);
            if (field) {
                field.value = value;
            }
        });
        updateFormStatistics();
    }
    function clearForm() {
        const form = document.getElementById('createStaffForm');
        if (form) {
            form.reset();
            form.querySelectorAll('.form-validation-error, .form-validation-success').forEach(field => {
                field.classList.remove('form-validation-error', 'form-validation-success');
            });
            form.querySelectorAll('[id$="-error"]').forEach(errorDiv => {
                errorDiv.textContent = '';
            });
            try { localStorage.removeItem('staffDraft'); } catch(e){}
            updateFormStatistics();
        }
    }
    function showAutoSaveNotification() {
        const indicator = document.getElementById('autoSaveIndicator');
        if (indicator) {
            indicator.classList.add('show');
            const toast = new bootstrap.Toast(indicator.querySelector('.toast'));
            toast.show();
            setTimeout(() => {
                indicator.classList.remove('show');
            }, 3000);
        }
    }
    function hasFormChanged() {
        const form = document.getElementById('createStaffForm');
        if (!form) return false;
        const currentData = new FormData(form);
        const currentString = JSON.stringify(Object.fromEntries(currentData));
        let savedString = '';
        try { savedString = localStorage.getItem('staffDraftString'); } catch(e){}
        if (currentString !== savedString) {
            try { localStorage.setItem('staffDraftString', currentString); } catch(e){}
            return true;
        }
        return false;
    }
    function updateTimeSpent() {
        const now = new Date();
        const timeDiff = Math.floor((now - formStartTime) / 60000);
        const timeSpentElement = document.getElementById('timeSpent');
        if (timeSpentElement) {
            timeSpentElement.textContent = `${timeDiff} min`;
        }
    }
    // Advanced Features Setup
    function setupAdvancedFeatures() {
        setupKeyboardShortcuts();
        setupSmartSuggestions();
        setupBulkActions();
        setupFieldSearch();
    }
    function setupKeyboardShortcuts() {
        document.addEventListener('keydown', function(e) {
            if (e.ctrlKey) {
                // Add keyboard shortcuts as needed
            } else if (e.key === 'Escape') {
                hideKeyboardShortcuts();
            }
        });
    }
    function hideKeyboardShortcuts() {
        const shortcuts = document.getElementById('keyboardShortcuts');
        if (shortcuts) {
            shortcuts.style.display = 'none';
        }
    }
    function setupSmartSuggestions() {
        const form = document.getElementById('createStaffForm');
        if (!form) return;
        form.addEventListener('input', function(e) {
            // Add smart suggestions logic here
        });
    }
    function setupBulkActions() {
        const panel = document.getElementById('bulkActionsPanel');
        // Show/hide bulk actions panel
        document.addEventListener('keydown', function(e) {
            // Add bulk actions logic here
        });
    }
    function setupFieldSearch() {
        const btn = document.getElementById('jumpToFieldBtn');
        if (btn) {
            btn.addEventListener('click', showFieldSearch);
        }
    }
    function showFieldSearch() {
        const modal = new bootstrap.Modal(document.getElementById('jumpToFieldModal'));
        modal.show();
    }
    function showNotification(message, type = 'success') {
        // Add notification logic here
    }
    setInterval(updateTimeSpent, 60000);
    window.addEventListener('beforeunload', function() {
        // Add any cleanup logic here
    });
})();
