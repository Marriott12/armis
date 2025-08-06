/**
 * Real-time Form Validation JavaScript
 * Provides instant feedback for form fields
 */

class StaffFormValidator {
    constructor() {
        this.validationEndpoint = 'ajax_validate_field.php';
        this.validationCache = new Map();
        this.validationTimeout = null;
        this.init();
    }
    
    init() {
        this.setupEventListeners();
        this.initializeValidationIndicators();
    }
    
    setupEventListeners() {
        // Real-time validation on input
        document.addEventListener('input', (e) => {
            if (e.target.matches('[data-validate]')) {
                this.debounceValidation(e.target);
            }
        });
        
        // Validation on blur
        document.addEventListener('blur', (e) => {
            if (e.target.matches('[data-validate]')) {
                this.validateField(e.target);
            }
        }, true);
        
        // Clear validation on focus
        document.addEventListener('focus', (e) => {
            if (e.target.matches('[data-validate]')) {
                this.clearFieldValidation(e.target);
            }
        }, true);
    }
    
    debounceValidation(field) {
        clearTimeout(this.validationTimeout);
        this.validationTimeout = setTimeout(() => {
            this.validateField(field);
        }, 500);
    }
    
    async validateField(field) {
        const fieldName = field.name;
        const value = field.value.trim();
        
        // Skip validation for empty optional fields
        if (!value && !field.hasAttribute('required')) {
            this.clearFieldValidation(field);
            return;
        }
        
        // Check cache first
        const cacheKey = `${fieldName}:${value}`;
        if (this.validationCache.has(cacheKey)) {
            const cachedResult = this.validationCache.get(cacheKey);
            this.displayValidationResult(field, cachedResult);
            return;
        }
        
        // Show loading indicator
        this.showValidationLoading(field);
        
        try {
            const response = await this.sendValidationRequest(fieldName, value);
            
            // Cache the result
            this.validationCache.set(cacheKey, response);
            
            // Display result
            this.displayValidationResult(field, response);
            
        } catch (error) {
            console.error('Validation error:', error);
            this.showValidationError(field, 'Validation failed');
        }
    }
    
    async sendValidationRequest(field, value) {
        const response = await fetch(this.validationEndpoint, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({
                field: field,
                value: value,
                context: this.getFieldContext(field)
            })
        });
        
        if (!response.ok) {
            throw new Error('Network error');
        }
        
        return await response.json();
    }
    
    getFieldContext(field) {
        // Get context from form data that might affect validation
        const form = document.getElementById('createStaffForm');
        const formData = new FormData(form);
        
        return {
            maritalStatus: formData.get('maritalStatus'),
            hasChildren: formData.get('hasChildren'),
            hasMedicalConditions: formData.get('hasMedicalConditions')
        };
    }
    
    displayValidationResult(field, result) {
        this.clearFieldValidation(field);
        
        if (result.valid) {
            this.showValidationSuccess(field);
            
            // Show additional info if available
            if (result.info) {
                this.showValidationInfo(field, result.info);
            }
            
            // Show suggestions if available
            if (result.suggestions) {
                this.showValidationSuggestions(field, result.suggestions);
            }
            
        } else {
            this.showValidationError(field, result.message);
        }
    }
    
    showValidationLoading(field) {
        const container = this.getValidationContainer(field);
        container.innerHTML = `
            <div class="validation-loading">
                <i class="fa fa-spinner fa-spin text-info"></i>
                <span class="text-muted">Validating...</span>
            </div>
        `;
        
        field.classList.remove('is-valid', 'is-invalid');
        field.classList.add('validating');
    }
    
    showValidationSuccess(field) {
        field.classList.remove('is-invalid', 'validating');
        field.classList.add('is-valid');
        
        const container = this.getValidationContainer(field);
        container.innerHTML = `
            <div class="validation-success">
                <i class="fa fa-check-circle text-success"></i>
                <span class="text-success">Valid</span>
            </div>
        `;
    }
    
    showValidationError(field, message) {
        field.classList.remove('is-valid', 'validating');
        field.classList.add('is-invalid');
        
        const container = this.getValidationContainer(field);
        container.innerHTML = `
            <div class="validation-error">
                <i class="fa fa-exclamation-circle text-danger"></i>
                <span class="text-danger">${message}</span>
            </div>
        `;
    }
    
    showValidationInfo(field, info) {
        const container = this.getValidationContainer(field);
        const existingContent = container.innerHTML;
        
        container.innerHTML = existingContent + `
            <div class="validation-info mt-1">
                <i class="fa fa-info-circle text-info"></i>
                <small class="text-info">${info}</small>
            </div>
        `;
    }
    
    showValidationSuggestions(field, suggestions) {
        const container = this.getValidationContainer(field);
        const existingContent = container.innerHTML;
        
        let suggestionHtml = `
            <div class="validation-suggestions mt-1">
                <i class="fa fa-lightbulb text-warning"></i>
                <small class="text-warning">${suggestions.message}</small>
        `;
        
        if (suggestions.formatted) {
            suggestionHtml += `
                <br>
                <button type="button" class="btn btn-sm btn-outline-warning mt-1" 
                        onclick="this.closest('.form-group').querySelector('input, select').value = '${suggestions.formatted}'">
                    Use: ${suggestions.formatted}
                </button>
            `;
        }
        
        if (suggestions.data && Array.isArray(suggestions.data)) {
            suggestionHtml += `<br><small>Similar: ${suggestions.data.slice(0, 3).join(', ')}</small>`;
        }
        
        suggestionHtml += '</div>';
        
        container.innerHTML = existingContent + suggestionHtml;
    }
    
    clearFieldValidation(field) {
        field.classList.remove('is-valid', 'is-invalid', 'validating');
        
        const container = this.getValidationContainer(field);
        container.innerHTML = '';
    }
    
    getValidationContainer(field) {
        let container = field.parentElement.querySelector('.validation-feedback');
        
        if (!container) {
            container = document.createElement('div');
            container.className = 'validation-feedback';
            field.parentElement.appendChild(container);
        }
        
        return container;
    }
    
    initializeValidationIndicators() {
        // Add validation attributes to form fields
        const form = document.getElementById('createStaffForm');
        if (!form) return;
        
        // Required fields
        const requiredFields = [
            'firstName', 'lastName', 'email', 'phone', 'nationalID', 
            'dateOfBirth', 'serviceNumber', 'rankID', 'unitID', 'corps',
            'enlistmentDate', 'currentAddress', 'emergencyContact', 'emergencyPhone'
        ];
        
        requiredFields.forEach(fieldName => {
            const field = form.querySelector(`[name="${fieldName}"]`);
            if (field) {
                field.setAttribute('data-validate', 'true');
                field.setAttribute('required', 'true');
            }
        });
        
        // Optional fields that should still be validated
        const optionalValidatedFields = [
            'middleName', 'permanentAddress', 'spouseName', 'spousePhone',
            'numberOfChildren', 'medicalConditions', 'skills', 'languages'
        ];
        
        optionalValidatedFields.forEach(fieldName => {
            const field = form.querySelector(`[name="${fieldName}"]`);
            if (field) {
                field.setAttribute('data-validate', 'true');
            }
        });
    }
    
    // Public method to validate entire form
    async validateForm() {
        const form = document.getElementById('createStaffForm');
        const fields = form.querySelectorAll('[data-validate]');
        
        const validationPromises = Array.from(fields).map(field => {
            return this.validateField(field);
        });
        
        await Promise.all(validationPromises);
        
        // Check if form is valid
        const invalidFields = form.querySelectorAll('.is-invalid');
        return invalidFields.length === 0;
    }
    
    // Clear all validation
    clearAllValidation() {
        const form = document.getElementById('createStaffForm');
        const fields = form.querySelectorAll('[data-validate]');
        
        fields.forEach(field => {
            this.clearFieldValidation(field);
        });
        
        this.validationCache.clear();
    }
}

// Initialize validator when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
    window.staffFormValidator = new StaffFormValidator();
});

// Enhance existing form submission
document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('createStaffForm');
    if (form) {
        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            
            // Show loading state
            const submitBtn = form.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            submitBtn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Validating...';
            submitBtn.disabled = true;
            
            try {
                // Validate form
                const isValid = await window.staffFormValidator.validateForm();
                
                if (isValid) {
                    submitBtn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Creating Staff...';
                    form.submit();
                } else {
                    submitBtn.innerHTML = originalText;
                    submitBtn.disabled = false;
                    
                    // Show error notification
                    showNotification('Please fix validation errors before submitting', 'error');
                    
                    // Scroll to first error
                    const firstError = form.querySelector('.is-invalid');
                    if (firstError) {
                        firstError.scrollIntoView({ behavior: 'smooth', block: 'center' });
                        firstError.focus();
                    }
                }
            } catch (error) {
                console.error('Form validation error:', error);
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
                showNotification('Validation failed. Please try again.', 'error');
            }
        });
    }
});
