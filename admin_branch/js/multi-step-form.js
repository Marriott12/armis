/**
 * Staff Form Multi-step Navigation
 * Handles the navigation between form steps
 */

// Current step tracker
let currentStep = 1;
const totalSteps = 5; // Update this if you add more steps

// Function to navigate to a specific step
function goToStep(stepNumber) {
    // Hide all steps
    document.querySelectorAll('.form-step').forEach(step => {
        step.classList.remove('active');
    });
    
    // Show the target step
    const targetStep = document.getElementById(`step${stepNumber}`);
    if (targetStep) {
        targetStep.classList.add('active');
        currentStep = stepNumber;
        
        // Update stepper UI
        updateStepperUI(stepNumber);
        
        // Scroll to top of form
        targetStep.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
    
    // Update buttons visibility
    updateNavigationButtons();
}

// Function to go to the next step
function nextStep() {
    if (currentStep < totalSteps) {
        if (validateCurrentStep()) {
            goToStep(currentStep + 1);
        }
    }
}

// Function to go to the previous step
function prevStep() {
    if (currentStep > 1) {
        goToStep(currentStep - 1);
    }
}

// Function to validate the current step before proceeding
function validateCurrentStep() {
    const currentStepElement = document.getElementById(`step${currentStep}`);
    if (!currentStepElement) return true;
    
    // Get all required fields in the current step
    const requiredFields = currentStepElement.querySelectorAll('[required]');
    let isValid = true;
    
    // Validate each required field
    requiredFields.forEach(field => {
        if (!field.value.trim()) {
            isValid = false;
            field.classList.add('is-invalid');
            const feedbackEl = document.getElementById(`${field.id}-error`);
            if (feedbackEl) feedbackEl.textContent = 'This field is required';
            field.focus();
        } else {
            field.classList.remove('is-invalid');
        }
    });
    
    if (!isValid) {
        // Show validation error alert
        const alertContainer = document.getElementById('alertContainer');
        if (alertContainer) {
            const alert = document.createElement('div');
            alert.className = 'alert alert-danger alert-dismissible fade show';
            alert.innerHTML = `
                <strong>Error!</strong> Please fill in all required fields in this step before proceeding.
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            `;
            alertContainer.appendChild(alert);
            
            // Auto-dismiss after 5 seconds
            setTimeout(() => {
                alert.remove();
            }, 5000);
        }
    }
    
    return isValid;
}

// Function to update the stepper UI based on current step
function updateStepperUI(stepNumber) {
    // Remove active/completed classes from all steps
    document.querySelectorAll('.step').forEach((step, index) => {
        const stepNum = index + 1;
        step.classList.remove('active', 'completed');
        
        if (stepNum < stepNumber) {
            // Mark previous steps as completed
            step.classList.add('completed');
        } else if (stepNum === stepNumber) {
            // Mark current step as active
            step.classList.add('active');
        }
    });
}

// Function to update the visibility of navigation buttons
function updateNavigationButtons() {
    const prevButton = document.getElementById('prevStepBtn');
    const nextButton = document.getElementById('nextStepBtn');
    const submitButton = document.getElementById('submitFormBtn');
    
    if (prevButton) {
        prevButton.style.display = currentStep === 1 ? 'none' : 'inline-block';
    }
    
    if (nextButton) {
        nextButton.style.display = currentStep === totalSteps ? 'none' : 'inline-block';
    }
    
    if (submitButton) {
        submitButton.style.display = currentStep === totalSteps ? 'inline-block' : 'none';
    }
}

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    // Set up initial step
    goToStep(1);
    
    // Set up event listeners for step navigation
    document.querySelectorAll('.step').forEach((step, index) => {
        step.addEventListener('click', () => {
            const stepNumber = index + 1;
            // Only allow clicking on previous steps or the next step
            if (stepNumber <= currentStep + 1) {
                goToStep(stepNumber);
            }
        });
    });
    
    // Set up event listeners for navigation buttons
    const prevButton = document.getElementById('prevStepBtn');
    const nextButton = document.getElementById('nextStepBtn');
    
    if (prevButton) {
        prevButton.addEventListener('click', prevStep);
    }
    
    if (nextButton) {
        nextButton.addEventListener('click', nextStep);
    }
});
