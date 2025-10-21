/**
 * ARMIS Session Management and Form State Preservation
 * 
 * This script handles:
 * - Session timeout detection via AJAX polling
 * - Form state preservation before timeout redirect
 * - Form state restoration after re-login
 */

(function() {
    'use strict';
    
    // Configuration
    const SESSION_CHECK_INTERVAL = 60000; // Check every 60 seconds
    const SESSION_WARNING_TIME = 300; // Warn 5 minutes before timeout
    const SESSION_TIMEOUT = 1200; // 20 minutes in seconds
    
    let sessionCheckInterval;
    let lastActivityTime = Date.now();
    let warningShown = false;
    
    /**
     * Save current page state to sessionStorage
     */
    function savePageState() {
        try {
            const state = {
                pathname: window.location.pathname,
                search: window.location.search,
                hash: window.location.hash,
                timestamp: Date.now()
            };
            
            // Save all form data
            const forms = document.querySelectorAll('form');
            if (forms.length > 0) {
                state.forms = [];
                
                forms.forEach((form, formIndex) => {
                    const formData = {};
                    const formElements = form.elements;
                    
                    for (let i = 0; i < formElements.length; i++) {
                        const element = formElements[i];
                        
                        // Skip buttons, submits, and elements without names
                        if (!element.name || element.type === 'submit' || element.type === 'button') {
                            continue;
                        }
                        
                        // Handle different input types
                        if (element.type === 'checkbox') {
                            formData[element.name] = element.checked;
                        } else if (element.type === 'radio') {
                            if (element.checked) {
                                formData[element.name] = element.value;
                            }
                        } else if (element.tagName === 'SELECT' && element.multiple) {
                            const selectedOptions = Array.from(element.selectedOptions).map(opt => opt.value);
                            formData[element.name] = selectedOptions;
                        } else {
                            formData[element.name] = element.value;
                        }
                    }
                    
                    state.forms.push({
                        action: form.action,
                        method: form.method,
                        data: formData,
                        formIndex: formIndex
                    });
                });
            }
            
            // Save selected items (e.g., checkboxes for staff selection)
            const selectedCheckboxes = document.querySelectorAll('input[type="checkbox"]:checked');
            if (selectedCheckboxes.length > 0) {
                state.selectedItems = Array.from(selectedCheckboxes).map(cb => ({
                    name: cb.name,
                    value: cb.value,
                    id: cb.id,
                    dataset: cb.dataset
                }));
            }
            
            sessionStorage.setItem('armis_saved_state', JSON.stringify(state));
            console.log('Page state saved:', state);
            return true;
        } catch (e) {
            console.error('Error saving page state:', e);
            return false;
        }
    }
    
    /**
     * Restore page state from sessionStorage
     */
    function restorePageState() {
        try {
            const savedState = sessionStorage.getItem('armis_saved_state');
            if (!savedState) {
                return false;
            }
            
            const state = JSON.parse(savedState);
            console.log('Restoring page state:', state);
            
            // Check if we're on the same page
            if (state.pathname !== window.location.pathname) {
                console.log('Different page, not restoring state');
                return false;
            }
            
            // Restore form data
            if (state.forms && state.forms.length > 0) {
                const forms = document.querySelectorAll('form');
                
                state.forms.forEach(savedForm => {
                    const form = forms[savedForm.formIndex];
                    if (!form) return;
                    
                    Object.keys(savedForm.data).forEach(fieldName => {
                        const elements = form.elements[fieldName];
                        if (!elements) return;
                        
                        // Handle NodeList (radio buttons, checkboxes with same name)
                        if (elements.length > 1) {
                            Array.from(elements).forEach(element => {
                                if (element.type === 'checkbox') {
                                    element.checked = savedForm.data[fieldName];
                                } else if (element.type === 'radio') {
                                    element.checked = (element.value === savedForm.data[fieldName]);
                                }
                            });
                        } else {
                            const element = elements.length ? elements[0] : elements;
                            
                            if (element.type === 'checkbox') {
                                element.checked = savedForm.data[fieldName];
                            } else if (element.tagName === 'SELECT' && element.multiple) {
                                Array.from(element.options).forEach(option => {
                                    option.selected = savedForm.data[fieldName].includes(option.value);
                                });
                            } else {
                                element.value = savedForm.data[fieldName];
                            }
                            
                            // Trigger change event for any listeners
                            element.dispatchEvent(new Event('change', { bubbles: true }));
                        }
                    });
                });
            }
            
            // Restore selected checkboxes
            if (state.selectedItems && state.selectedItems.length > 0) {
                state.selectedItems.forEach(item => {
                    let checkbox = null;
                    
                    // Try to find by ID first
                    if (item.id) {
                        checkbox = document.getElementById(item.id);
                    }
                    
                    // Try by value
                    if (!checkbox && item.value) {
                        checkbox = document.querySelector(`input[type="checkbox"][value="${item.value}"]`);
                    }
                    
                    // Try by name
                    if (!checkbox && item.name) {
                        checkbox = document.querySelector(`input[type="checkbox"][name="${item.name}"][value="${item.value}"]`);
                    }
                    
                    if (checkbox) {
                        checkbox.checked = true;
                        checkbox.dispatchEvent(new Event('change', { bubbles: true }));
                    }
                });
            }
            
            // Show restoration notification
            showNotification('Your previous work has been restored', 'success');
            
            // Clear saved state
            sessionStorage.removeItem('armis_saved_state');
            
            return true;
        } catch (e) {
            console.error('Error restoring page state:', e);
            return false;
        }
    }
    
    /**
     * Show notification to user
     */
    function showNotification(message, type = 'info') {
        const alertDiv = document.createElement('div');
        alertDiv.className = `alert alert-${type} alert-dismissible fade show position-fixed top-0 start-50 translate-middle-x mt-3`;
        alertDiv.style.zIndex = '9999';
        alertDiv.innerHTML = `
            <i class="fas fa-${type === 'success' ? 'check-circle' : 'info-circle'}"></i>
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        `;
        document.body.appendChild(alertDiv);
        
        // Auto-dismiss after 5 seconds
        setTimeout(() => {
            alertDiv.remove();
        }, 5000);
    }
    
    /**
     * Update last activity time on user interaction
     */
    function updateActivity() {
        lastActivityTime = Date.now();
        warningShown = false;
    }
    
    /**
     * Check session status
     */
    function checkSession() {
        const inactiveTime = (Date.now() - lastActivityTime) / 1000; // in seconds
        const timeRemaining = SESSION_TIMEOUT - inactiveTime;
        
        // Show warning if approaching timeout
        if (timeRemaining <= SESSION_WARNING_TIME && timeRemaining > 0 && !warningShown) {
            warningShown = true;
            const minutes = Math.ceil(timeRemaining / 60);
            showNotification(`Your session will expire in ${minutes} minute${minutes > 1 ? 's' : ''} due to inactivity`, 'warning');
        }
    }
    
    /**
     * Handle beforeunload event to save state if needed
     */
    function handleBeforeUnload(event) {
        // Only save state if this might be a session timeout redirect
        const inactiveTime = (Date.now() - lastActivityTime) / 1000;
        if (inactiveTime > (SESSION_TIMEOUT - 60)) { // Within 1 minute of timeout
            savePageState();
        }
    }
    
    /**
     * Initialize session management
     */
    function init() {
        console.log('ARMIS Session Management initialized');
        
        // Track user activity
        ['mousedown', 'keydown', 'scroll', 'touchstart', 'click'].forEach(eventName => {
            document.addEventListener(eventName, updateActivity, { passive: true });
        });
        
        // Check session periodically
        sessionCheckInterval = setInterval(checkSession, SESSION_CHECK_INTERVAL);
        
        // Save state before page unload
        window.addEventListener('beforeunload', handleBeforeUnload);
        
        // Check if we need to restore state
        const urlParams = new URLSearchParams(window.location.search);
        const restoreState = urlParams.get('restore_state') === 'true';
        
        if (restoreState || (typeof PHP_SESSION_RESTORE_STATE !== 'undefined' && PHP_SESSION_RESTORE_STATE)) {
            // Wait for DOM to be fully loaded
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', () => {
                    setTimeout(restorePageState, 500);
                });
            } else {
                setTimeout(restorePageState, 500);
            }
        }
    }
    
    // Auto-initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
    
    // Expose functions globally for manual use if needed
    window.ARMISSession = {
        saveState: savePageState,
        restoreState: restorePageState,
        updateActivity: updateActivity
    };
})();
