/**
 * State Restoration Handler
 * Restores user activity state after session timeout and re-login
 */

(function() {
    'use strict';
    
    // Check if state restoration is needed
    if (typeof PHP_SESSION_RESTORE_STATE !== 'undefined' && PHP_SESSION_RESTORE_STATE === true) {
        restoreState();
    }
    
    function restoreState() {
        console.log('Attempting to restore state...');
        
        // Get saved state from sessionStorage
        const savedStateJson = sessionStorage.getItem('armis_saved_state');
        
        if (!savedStateJson) {
            console.log('No saved state found');
            return;
        }
        
        try {
            const state = JSON.parse(savedStateJson);
            console.log('Restoring state:', state);
            
            // Restore form data
            if (state.forms && Object.keys(state.forms).length > 0) {
                restoreForms(state.forms);
            }
            
            // Restore filters
            if (state.filters && Object.keys(state.filters).length > 0) {
                restoreFilters(state.filters);
            }
            
            // Restore scroll position
            if (state.scrollPosition) {
                setTimeout(() => {
                    window.scrollTo(0, state.scrollPosition);
                }, 100);
            }
            
            // Show restoration notification
            showRestorationNotification(state);
            
            // Clear the saved state
            sessionStorage.removeItem('armis_saved_state');
            
        } catch (e) {
            console.error('Error restoring state:', e);
        }
    }
    
    function restoreForms(forms) {
        console.log('Restoring forms:', forms);
        
        Object.keys(forms).forEach(formId => {
            const form = document.getElementById(formId) || document.querySelector(`form:nth-of-type(${formId.replace('form_', '')})`);
            
            if (!form) {
                console.log('Form not found:', formId);
                return;
            }
            
            const formData = forms[formId];
            
            Object.keys(formData).forEach(fieldName => {
                const field = form.querySelector(`[name="${fieldName}"]`);
                
                if (!field) {
                    console.log('Field not found:', fieldName);
                    return;
                }
                
                if (field.type === 'checkbox' || field.type === 'radio') {
                    field.checked = formData[fieldName];
                } else {
                    field.value = formData[fieldName];
                }
                
                // Trigger change event for any listeners
                field.dispatchEvent(new Event('change', { bubbles: true }));
            });
            
            console.log('Restored form:', formId);
        });
    }
    
    function restoreFilters(filters) {
        console.log('Restoring filters:', filters);
        
        // Restore period filter
        if (filters.periodFilter) {
            const startDate = document.getElementById('filterStartDate');
            const endDate = document.getElementById('filterEndDate');
            
            if (startDate && endDate) {
                startDate.value = filters.periodFilter.startDate || '';
                endDate.value = filters.periodFilter.endDate || '';
                
                // Trigger filter application
                const periodFilterForm = document.getElementById('periodFilterForm');
                if (periodFilterForm && startDate.value && endDate.value) {
                    setTimeout(() => {
                        periodFilterForm.dispatchEvent(new Event('submit'));
                    }, 500);
                }
            }
        }
        
        // Restore personnel filter
        if (filters.personnelFilter) {
            const filterRadio = document.querySelector(`input[name="personnel-filter"][value="${filters.personnelFilter}"]`);
            if (filterRadio) {
                filterRadio.checked = true;
                filterRadio.dispatchEvent(new Event('change', { bubbles: true }));
            }
        }
        
        // Restore DataTable states
        Object.keys(filters).forEach(key => {
            if (key.startsWith('datatable_')) {
                const tableId = key.replace('datatable_', '');
                const tableState = filters[key];
                
                // Wait for DataTable to initialize
                setTimeout(() => {
                    if (typeof $ !== 'undefined' && $.fn.DataTable) {
                        const table = $(`#${tableId}`).DataTable();
                        if (table) {
                            // Restore search
                            if (tableState.search) {
                                table.search(tableState.search).draw(false);
                            }
                            
                            // Restore page
                            if (tableState.page !== undefined) {
                                table.page(tableState.page).draw(false);
                            }
                            
                            // Restore order
                            if (tableState.order) {
                                table.order(tableState.order).draw(false);
                            }
                        }
                    }
                }, 1000);
            }
        });
    }
    
    function showRestorationNotification(state) {
        // Create notification
        const notification = document.createElement('div');
        notification.className = 'alert alert-success alert-dismissible fade show position-fixed';
        notification.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px; box-shadow: 0 4px 12px rgba(0,0,0,0.15);';
        
        const timeAgo = getTimeAgo(state.timestamp);
        
        notification.innerHTML = `
            <div class="d-flex align-items-center">
                <i class="fas fa-check-circle text-success me-2 fs-5"></i>
                <div class="flex-grow-1">
                    <strong>Session Restored</strong><br>
                    <small class="text-muted">Your work from ${timeAgo} has been restored</small>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        `;
        
        document.body.appendChild(notification);
        
        // Auto-dismiss after 10 seconds
        setTimeout(() => {
            const bsAlert = new bootstrap.Alert(notification);
            bsAlert.close();
        }, 10000);
    }
    
    function getTimeAgo(timestamp) {
        const now = Date.now();
        const diff = Math.floor((now - timestamp) / 1000); // in seconds
        
        if (diff < 60) {
            return 'moments ago';
        } else if (diff < 3600) {
            const minutes = Math.floor(diff / 60);
            return `${minutes} minute${minutes > 1 ? 's' : ''} ago`;
        } else if (diff < 86400) {
            const hours = Math.floor(diff / 3600);
            return `${hours} hour${hours > 1 ? 's' : ''} ago`;
        } else {
            const days = Math.floor(diff / 86400);
            return `${days} day${days > 1 ? 's' : ''} ago`;
        }
    }
})();
