/**
 * Enhanced Form Field Management for ARMIS Staff Creation
 * Handles conditional fields, dynamic sections, and form validation
 */

class ARMISFormManager {
    constructor() {
        this.init();
    }
    
    init() {
        this.setupConditionalFields();
        this.setupDynamicSections();
        this.setupFormValidation();
        this.setupAutoSave();
    }
    
    /**
     * Setup conditional field display/hide logic
     */
    setupConditionalFields() {
        // Marital Status -> Spouse Details
        const maritalStatus = document.getElementById('maritalStatus');
        if (maritalStatus) {
            maritalStatus.addEventListener('change', (e) => {
                const spouseSection = document.getElementById('spouseSection');
                if (spouseSection) {
                    spouseSection.style.display = (e.target.value === 'Married') ? 'block' : 'none';
                    this.toggleRequiredFields(spouseSection, e.target.value === 'Married');
                }
            });
            
            // Initialize on page load
            maritalStatus.dispatchEvent(new Event('change'));
        }
        
        // Gender -> Specific fields
        const gender = document.getElementById('gender');
        if (gender) {
            gender.addEventListener('change', (e) => {
                this.handleGenderSpecificFields(e.target.value);
            });
        }
        
        // Category -> Rank filtering
        const category = document.getElementById('categorySelect');
        if (category) {
            category.addEventListener('change', (e) => {
                this.filterRanksByCategory(e.target.value);
            });
        }
        
        // Service Status -> Additional fields
        const serviceStatus = document.getElementById('svcStatus');
        if (serviceStatus) {
            serviceStatus.addEventListener('change', (e) => {
                this.handleServiceStatusFields(e.target.value);
            });
        }
    }
    
    /**
     * Setup dynamic add/remove sections
     */
    setupDynamicSections() {
        // Initialize counters
        this.childrenCount = 0;
        this.educationCount = 0;
        this.languageCount = 0;
        
        // Setup add buttons
        this.setupAddChildButton();
        this.setupAddEducationButton();
        this.setupAddLanguageButton();
    }
    
    /**
     * Handle gender-specific field visibility
     */
    handleGenderSpecificFields(gender) {
        const genderSpecificSections = document.querySelectorAll('.gender-specific');
        genderSpecificSections.forEach(section => {
            const requiredGender = section.dataset.gender;
            if (requiredGender && requiredGender !== gender) {
                section.style.display = 'none';
                this.toggleRequiredFields(section, false);
            } else {
                section.style.display = 'block';
                this.toggleRequiredFields(section, true);
            }
        });
    }
    
    /**
     * Filter ranks based on selected category
     */
    filterRanksByCategory(category) {
        const rankSelect = document.getElementById('rankSelect');
        if (!rankSelect) return;
        
        const options = rankSelect.querySelectorAll('option');
        options.forEach(option => {
            if (option.value === '') return; // Keep placeholder
            
            const optionCategory = option.dataset.category;
            if (category === '' || optionCategory === category || 
                (category === 'Civilian Employee' && optionCategory === 'Civilian')) {
                option.style.display = 'block';
                option.disabled = false;
            } else {
                option.style.display = 'none';
                option.disabled = true;
            }
        });
        
        // Reset rank selection if current selection is not valid for new category
        if (rankSelect.value) {
            const selectedOption = rankSelect.querySelector(`option[value="${rankSelect.value}"]`);
            if (selectedOption && selectedOption.disabled) {
                rankSelect.value = '';
            }
        }
    }
    
    /**
     * Handle service status specific fields
     */
    handleServiceStatusFields(status) {
        const retiredFields = document.querySelectorAll('.retired-fields');
        const activeFields = document.querySelectorAll('.active-fields');
        
        retiredFields.forEach(field => {
            field.style.display = (status === 'retired') ? 'block' : 'none';
            this.toggleRequiredFields(field, status === 'retired');
        });
        
        activeFields.forEach(field => {
            field.style.display = (status === 'active') ? 'block' : 'none';
            this.toggleRequiredFields(field, status === 'active');
        });
    }
    
    /**
     * Toggle required attribute on form fields within a container
     */
    toggleRequiredFields(container, makeRequired) {
        const inputs = container.querySelectorAll('input, select, textarea');
        inputs.forEach(input => {
            if (makeRequired && input.dataset.conditionalRequired === 'true') {
                input.required = true;
                input.closest('.form-group')?.classList.add('required');
            } else if (!makeRequired) {
                input.required = false;
                input.closest('.form-group')?.classList.remove('required');
            }
        });
    }
    
    /**
     * Setup add child/dependant functionality
     */
    setupAddChildButton() {
        window.addChild = () => {
            this.childrenCount++;
            const container = document.getElementById('childrenList');
            const childDiv = document.createElement('div');
            childDiv.className = 'child-entry row mb-2 border-bottom pb-2';
            childDiv.innerHTML = `
                <div class="col-md-3">
                    <input type="text" name="child_name_${this.childrenCount}" class="form-control form-control-sm" placeholder="Child Name">
                </div>
                <div class="col-md-2">
                    <input type="date" name="child_dob_${this.childrenCount}" class="form-control form-control-sm">
                </div>
                <div class="col-md-2">
                    <select name="child_gender_${this.childrenCount}" class="form-select form-select-sm">
                        <option value="">Gender</option>
                        <option value="Male">Male</option>
                        <option value="Female">Female</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <select name="child_relationship_${this.childrenCount}" class="form-select form-select-sm">
                        <option value="">Relationship</option>
                        <option value="Son">Son</option>
                        <option value="Daughter">Daughter</option>
                        <option value="Stepson">Stepson</option>
                        <option value="Stepdaughter">Stepdaughter</option>
                        <option value="Ward">Ward</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <button type="button" class="btn btn-danger btn-sm" onclick="this.parentElement.parentElement.remove()">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            `;
            container.appendChild(childDiv);
        };
    }
    
    /**
     * Setup add education functionality
     */
    setupAddEducationButton() {
        window.addEducation = () => {
            this.educationCount++;
            const container = document.getElementById('educationList');
            const eduDiv = document.createElement('div');
            eduDiv.className = 'education-entry row mb-2 border-bottom pb-2';
            eduDiv.innerHTML = `
                <div class="col-md-3">
                    <input type="text" name="education_institution_${this.educationCount}" class="form-control form-control-sm" placeholder="Institution">
                </div>
                <div class="col-md-3">
                    <input type="text" name="education_qualification_${this.educationCount}" class="form-control form-control-sm" placeholder="Qualification">
                </div>
                <div class="col-md-2">
                    <input type="number" name="education_year_${this.educationCount}" class="form-control form-control-sm" placeholder="Year" min="1950" max="2030">
                </div>
                <div class="col-md-2">
                    <input type="text" name="education_grade_${this.educationCount}" class="form-control form-control-sm" placeholder="Grade/Result">
                </div>
                <div class="col-md-2">
                    <button type="button" class="btn btn-danger btn-sm" onclick="this.parentElement.parentElement.remove()">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            `;
            container.appendChild(eduDiv);
        };
    }
    
    /**
     * Setup add language functionality
     */
    setupAddLanguageButton() {
        window.addLanguage = () => {
            this.languageCount++;
            const container = document.getElementById('languagesList');
            const langDiv = document.createElement('div');
            langDiv.className = 'language-entry row mb-2 border-bottom pb-2';
            langDiv.innerHTML = `
                <div class="col-md-4">
                    <input type="text" name="language_name_${this.languageCount}" class="form-control form-control-sm" placeholder="Language">
                </div>
                <div class="col-md-3">
                    <select name="language_speaking_${this.languageCount}" class="form-select form-select-sm">
                        <option value="">Speaking Level</option>
                        <option value="Native">Native</option>
                        <option value="Fluent">Fluent</option>
                        <option value="Intermediate">Intermediate</option>
                        <option value="Basic">Basic</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <select name="language_writing_${this.languageCount}" class="form-select form-select-sm">
                        <option value="">Writing Level</option>
                        <option value="Native">Native</option>
                        <option value="Fluent">Fluent</option>
                        <option value="Intermediate">Intermediate</option>
                        <option value="Basic">Basic</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <button type="button" class="btn btn-danger btn-sm" onclick="this.parentElement.parentElement.remove()">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            `;
            container.appendChild(langDiv);
        };
    }
    
    /**
     * Setup form validation
     */
    setupFormValidation() {
        const form = document.getElementById('createStaffForm');
        if (!form) return;
        
        form.addEventListener('submit', (e) => {
            if (!this.validateForm()) {
                e.preventDefault();
                this.showValidationErrors();
            }
        });
        
        // Real-time validation
        form.addEventListener('input', (e) => {
            this.validateField(e.target);
        });
    }
    
    /**
     * Validate entire form
     */
    validateForm() {
        const form = document.getElementById('createStaffForm');
        const inputs = form.querySelectorAll('input[required], select[required], textarea[required]');
        let isValid = true;
        
        inputs.forEach(input => {
            if (!this.validateField(input)) {
                isValid = false;
            }
        });
        
        return isValid;
    }
    
    /**
     * Validate individual field
     */
    validateField(field) {
        const value = field.value.trim();
        let isValid = true;
        let errorMessage = '';
        
        // Check required fields
        if (field.required && !value) {
            isValid = false;
            errorMessage = 'This field is required';
        }
        
        // Specific field validations
        switch (field.type) {
            case 'email':
                if (value && !this.isValidEmail(value)) {
                    isValid = false;
                    errorMessage = 'Please enter a valid email address';
                }
                break;
                
            case 'tel':
                if (value && !this.isValidPhone(value)) {
                    isValid = false;
                    errorMessage = 'Please enter a valid phone number';
                }
                break;
                
            case 'date':
                if (value && !this.isValidDate(value)) {
                    isValid = false;
                    errorMessage = 'Please enter a valid date';
                }
                break;
        }
        
        // Service number validation
        if (field.name === 'svcNo' && value) {
            if (!this.isValidServiceNumber(value)) {
                isValid = false;
                errorMessage = 'Service number format: ZA123456 (2 letters + 6-8 digits)';
            }
        }
        
        // Update field UI
        this.updateFieldValidation(field, isValid, errorMessage);
        
        return isValid;
    }
    
    /**
     * Email validation
     */
    isValidEmail(email) {
        const regex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return regex.test(email);
    }
    
    /**
     * Phone validation
     */
    isValidPhone(phone) {
        const regex = /^[\+]?[0-9\-\(\)\s]+$/;
        return regex.test(phone) && phone.replace(/\D/g, '').length >= 9;
    }
    
    /**
     * Date validation
     */
    isValidDate(dateString) {
        const date = new Date(dateString);
        return date instanceof Date && !isNaN(date);
    }
    
    /**
     * Service number validation
     */
    isValidServiceNumber(svcNo) {
        const regex = /^[A-Z]{2}[0-9]{6,8}$/;
        return regex.test(svcNo.toUpperCase());
    }
    
    /**
     * Update field validation UI
     */
    updateFieldValidation(field, isValid, errorMessage) {
        const feedbackElement = field.parentElement.querySelector('.validation-feedback');
        
        if (isValid) {
            field.classList.remove('is-invalid');
            field.classList.add('is-valid');
            if (feedbackElement) {
                feedbackElement.textContent = '';
            }
        } else {
            field.classList.remove('is-valid');
            field.classList.add('is-invalid');
            if (feedbackElement) {
                feedbackElement.textContent = errorMessage;
            }
        }
    }
    
    /**
     * Show validation errors summary
     */
    showValidationErrors() {
        const errorFields = document.querySelectorAll('.is-invalid');
        if (errorFields.length > 0) {
            // Focus on first error field
            errorFields[0].focus();
            
            // Show notification
            this.showNotification('Please correct the highlighted errors before submitting.', 'error');
        }
    }
    
    /**
     * Setup auto-save functionality
     */
    setupAutoSave() {
        const form = document.getElementById('createStaffForm');
        if (!form) return;
        
        let autoSaveTimer;
        
        form.addEventListener('input', () => {
            clearTimeout(autoSaveTimer);
            autoSaveTimer = setTimeout(() => {
                this.autoSaveForm();
            }, 5000); // Auto-save after 5 seconds of inactivity
        });
    }
    
    /**
     * Auto-save form data to localStorage
     */
    autoSaveForm() {
        const form = document.getElementById('createStaffForm');
        const formData = new FormData(form);
        const data = {};
        
        for (let [key, value] of formData.entries()) {
            data[key] = value;
        }
        
        localStorage.setItem('armis_staff_form_draft', JSON.stringify(data));
        this.showAutoSaveIndicator();
    }
    
    /**
     * Show auto-save indicator
     */
    showAutoSaveIndicator() {
        const indicator = document.getElementById('autoSaveIndicator');
        if (indicator) {
            indicator.style.display = 'block';
            setTimeout(() => {
                indicator.style.display = 'none';
            }, 2000);
        }
    }
    
    /**
     * Load saved form data
     */
    loadSavedData() {
        const savedData = localStorage.getItem('armis_staff_form_draft');
        if (savedData) {
            try {
                const data = JSON.parse(savedData);
                const form = document.getElementById('createStaffForm');
                
                Object.keys(data).forEach(key => {
                    const field = form.querySelector(`[name="${key}"]`);
                    if (field) {
                        field.value = data[key];
                    }
                });
                
                this.showNotification('Draft data loaded from previous session.', 'info');
            } catch (e) {
                console.warn('Failed to load saved form data:', e);
            }
        }
    }
    
    /**
     * Clear saved form data
     */
    clearSavedData() {
        localStorage.removeItem('armis_staff_form_draft');
    }
    
    /**
     * Show notification
     */
    showNotification(message, type = 'info') {
        // Use existing notification system if available
        if (typeof showNotification === 'function') {
            showNotification(message, type);
        } else {
            console.log(`${type.toUpperCase()}: ${message}`);
        }
    }
}

// Initialize form manager when DOM is loaded
document.addEventListener('DOMContentLoaded', () => {
    window.armisFormManager = new ARMISFormManager();
});

// Clear saved data on successful form submission
document.addEventListener('DOMContentLoaded', () => {
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.get('success') === '1') {
        window.armisFormManager?.clearSavedData();
    }
});
