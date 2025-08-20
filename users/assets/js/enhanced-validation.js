/**
 * Enhanced Profile Validation JavaScript
 * Real-time validation with military-grade security
 */

class ProfileValidator {
    constructor() {
        this.apiBase = '/users/api/profile.php';
        this.csrfToken = null;
        this.validationTimeout = null;
        this.securityScore = 0;
        this.init();
    }
    
    async init() {
        await this.loadCSRFToken();
        this.setupEventListeners();
        this.initializeSecurityIndicators();
        this.startAutoSave();
    }
    
    async loadCSRFToken() {
        try {
            const response = await fetch(`${this.apiBase}/csrf-token`);
            const data = await response.json();
            if (data.success) {
                this.csrfToken = data.csrf_token;
            }
        } catch (error) {
            console.error('Failed to load CSRF token:', error);
        }
    }
    
    setupEventListeners() {
        // Real-time validation for all form fields
        const fields = document.querySelectorAll('[data-validate="true"]');
        fields.forEach(field => {
            field.addEventListener('input', (e) => this.debounceValidation(e.target));
            field.addEventListener('blur', (e) => this.validateField(e.target));
            field.addEventListener('focus', (e) => this.clearFieldMessages(e.target));
        });
        
        // Form submission with enhanced validation
        const forms = document.querySelectorAll('form[data-enhanced-validation="true"]');
        forms.forEach(form => {
            form.addEventListener('submit', (e) => this.handleFormSubmit(e));
        });
        
        // Auto-save functionality
        const autoSaveFields = document.querySelectorAll('[data-auto-save="true"]');
        autoSaveFields.forEach(field => {
            field.addEventListener('input', (e) => this.scheduleAutoSave(e.target));
        });
    }
    
    debounceValidation(field) {
        clearTimeout(this.validationTimeout);
        this.validationTimeout = setTimeout(() => {
            this.validateField(field);
        }, 500);
    }
    
    async validateField(field) {
        const fieldType = field.dataset.fieldType || field.name;
        const value = field.value;
        
        if (!value.trim() && !field.required) {
            this.clearFieldMessages(field);
            return;
        }
        
        this.showValidationLoading(field);
        
        try {
            const response = await fetch(`${this.apiBase}/validate-field`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': this.csrfToken
                },
                body: JSON.stringify({
                    field_type: fieldType,
                    value: value,
                    context: 'real_time_validation'
                })
            });
            
            const data = await response.json();
            
            if (data.success) {
                this.displayValidationResult(field, data.validation);
                this.updateSecurityScore(data.validation.security_score);
            } else {
                this.showValidationError(field, 'Validation failed');
            }
            
        } catch (error) {
            console.error('Validation error:', error);
            this.showValidationError(field, 'Network error during validation');
        }
    }
    
    displayValidationResult(field, result) {
        this.clearFieldMessages(field);
        
        if (result.valid) {
            this.showValidationSuccess(field);
            if (result.warnings && result.warnings.length > 0) {
                this.showValidationWarning(field, result.warnings.join(', '));
            }
        } else {
            this.showValidationError(field, result.errors.join(', '));
        }
        
        // Update field security indicator
        this.updateFieldSecurityIndicator(field, result.security_score);
    }
    
    showValidationLoading(field) {
        const container = this.getValidationContainer(field);
        container.innerHTML = `
            <div class="validation-loading">
                <i class="fas fa-spinner fa-spin text-info"></i>
                <span class="text-info">Validating...</span>
            </div>
        `;
    }
    
    showValidationSuccess(field) {
        const container = this.getValidationContainer(field);
        container.innerHTML = `
            <div class="validation-success">
                <i class="fas fa-check-circle text-success"></i>
                <span class="text-success">Valid</span>
            </div>
        `;
        field.classList.remove('is-invalid');
        field.classList.add('is-valid');
    }
    
    showValidationError(field, message) {
        const container = this.getValidationContainer(field);
        container.innerHTML = `
            <div class="validation-error">
                <i class="fas fa-exclamation-circle text-danger"></i>
                <span class="text-danger">${message}</span>
            </div>
        `;
        field.classList.remove('is-valid');
        field.classList.add('is-invalid');
    }
    
    showValidationWarning(field, message) {
        const container = this.getValidationContainer(field);
        const existingContent = container.innerHTML;
        container.innerHTML = existingContent + `
            <div class="validation-warning">
                <i class="fas fa-exclamation-triangle text-warning"></i>
                <span class="text-warning">${message}</span>
            </div>
        `;
    }
    
    clearFieldMessages(field) {
        const container = this.getValidationContainer(field);
        container.innerHTML = '';
        field.classList.remove('is-valid', 'is-invalid');
    }
    
    getValidationContainer(field) {
        let container = field.parentNode.querySelector('.validation-messages');
        if (!container) {
            container = document.createElement('div');
            container.className = 'validation-messages mt-1';
            field.parentNode.appendChild(container);
        }
        return container;
    }
    
    updateFieldSecurityIndicator(field, score) {
        let indicator = field.parentNode.querySelector('.security-indicator');
        if (!indicator) {
            indicator = document.createElement('div');
            indicator.className = 'security-indicator';
            field.parentNode.appendChild(indicator);
        }
        
        const level = this.getSecurityLevel(score);
        indicator.innerHTML = `
            <div class="security-score ${level.class}">
                <i class="${level.icon}"></i>
                <span>${level.text} (${score}%)</span>
            </div>
        `;
    }
    
    getSecurityLevel(score) {
        if (score >= 90) {
            return { class: 'security-excellent', icon: 'fas fa-shield-alt', text: 'Excellent' };
        } else if (score >= 70) {
            return { class: 'security-good', icon: 'fas fa-shield-alt', text: 'Good' };
        } else if (score >= 50) {
            return { class: 'security-moderate', icon: 'fas fa-shield-alt', text: 'Moderate' };
        } else {
            return { class: 'security-poor', icon: 'fas fa-exclamation-triangle', text: 'Poor' };
        }
    }
    
    updateSecurityScore(newScore) {
        this.securityScore = newScore;
        const scoreElement = document.getElementById('overall-security-score');
        if (scoreElement) {
            const level = this.getSecurityLevel(newScore);
            scoreElement.innerHTML = `
                <div class="security-display ${level.class}">
                    <i class="${level.icon}"></i>
                    <span>Security Score: ${newScore}% (${level.text})</span>
                </div>
            `;
        }
    }
    
    initializeSecurityIndicators() {
        // Add security score display to header
        const headerElement = document.querySelector('.main-header, .content-header');
        if (headerElement) {
            const securityDisplay = document.createElement('div');
            securityDisplay.id = 'overall-security-score';
            securityDisplay.className = 'security-score-display';
            headerElement.appendChild(securityDisplay);
        }
        
        // Initialize field indicators
        const fields = document.querySelectorAll('[data-validate="true"]');
        fields.forEach(field => {
            this.updateFieldSecurityIndicator(field, 100);
        });
    }
    
    async updateProfileCompletion() {
        try {
            const response = await fetch(`${this.apiBase}/completion`);
            const data = await response.json();
            
            if (data.success) {
                this.displayProfileCompletion(data.data);
            }
        } catch (error) {
            console.error('Failed to update profile completion:', error);
        }
    }
    
    displayProfileCompletion(completionData) {
        const completionElement = document.getElementById('profile-completion');
        if (completionElement) {
            const percentage = completionData.overall_completion;
            completionElement.innerHTML = `
                <div class="progress">
                    <div class="progress-bar bg-success" role="progressbar" 
                         style="width: ${percentage}%" aria-valuenow="${percentage}" 
                         aria-valuemin="0" aria-valuemax="100">
                        ${percentage}% Complete
                    </div>
                </div>
            `;
        }
    }
    
    showAlert(type, message) {
        const alertContainer = document.getElementById('alert-container') || document.querySelector('.main-content');
        const alert = document.createElement('div');
        alert.className = `alert alert-${type} alert-dismissible fade show`;
        alert.innerHTML = `
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        `;
        alertContainer.insertBefore(alert, alertContainer.firstChild);
        
        // Auto-remove after 5 seconds
        setTimeout(() => {
            if (alert.parentNode) {
                alert.remove();
            }
        }, 5000);
    }
    
    startAutoSave() {
        console.log('Auto-save initialized for enhanced profile validation');
    }
}

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
    window.profileValidator = new ProfileValidator();
});