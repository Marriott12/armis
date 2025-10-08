/**
 * ARMIS Session Manager
 * Handles idle session timeout with warning and activity state preservation
 */

class SessionManager {
    constructor(options = {}) {
        // Configuration
        this.sessionTimeout = options.sessionTimeout || 3600; // 1 hour in seconds
        this.warningTime = options.warningTime || 120; // 2 minutes warning in seconds
        this.checkInterval = options.checkInterval || 1000; // Check every second
        this.onWarning = options.onWarning || this.showWarningModal.bind(this);
        this.onTimeout = options.onTimeout || this.handleTimeout.bind(this);
        this.onExtend = options.onExtend || this.extendSession.bind(this);
        
        // State
        this.lastActivity = Date.now();
        this.warningShown = false;
        this.checkTimer = null;
        this.countdownTimer = null;
        this.warningModal = null;
        
        // Activity events to track
        this.activityEvents = [
            'mousedown',
            'mousemove', 
            'keypress',
            'scroll',
            'touchstart',
            'click',
            'focus'
        ];
        
        this.init();
    }
    
    init() {
        console.log('Session Manager initialized');
        console.log(`Session timeout: ${this.sessionTimeout}s, Warning at: ${this.sessionTimeout - this.warningTime}s`);
        
        // Register activity listeners
        this.registerActivityListeners();
        
        // Start monitoring
        this.startMonitoring();
        
        // Create warning modal
        this.createWarningModal();
        
        // Listen for storage events (multi-tab sync)
        window.addEventListener('storage', this.handleStorageEvent.bind(this));
        
        // Save state before page unload
        window.addEventListener('beforeunload', this.saveCurrentState.bind(this));
    }
    
    registerActivityListeners() {
        // Throttle activity updates to prevent excessive calls
        let throttleTimer = null;
        const throttleDelay = 500; // Update at most every 500ms
        
        this.activityEvents.forEach(eventName => {
            document.addEventListener(eventName, () => {
                if (!throttleTimer) {
                    this.updateActivity();
                    throttleTimer = setTimeout(() => {
                        throttleTimer = null;
                    }, throttleDelay);
                }
            }, { passive: true });
        });
    }
    
    updateActivity() {
        this.lastActivity = Date.now();
        
        // Store in localStorage for multi-tab sync
        localStorage.setItem('armis_last_activity', this.lastActivity.toString());
        
        // Hide warning if shown
        if (this.warningShown) {
            this.hideWarningModal();
        }
    }
    
    startMonitoring() {
        this.checkTimer = setInterval(() => {
            this.checkSessionStatus();
        }, this.checkInterval);
    }
    
    stopMonitoring() {
        if (this.checkTimer) {
            clearInterval(this.checkTimer);
            this.checkTimer = null;
        }
        if (this.countdownTimer) {
            clearInterval(this.countdownTimer);
            this.countdownTimer = null;
        }
    }
    
    checkSessionStatus() {
        const now = Date.now();
        const idleTime = Math.floor((now - this.lastActivity) / 1000); // in seconds
        const timeUntilTimeout = this.sessionTimeout - idleTime;
        
        // Debug logging (can be removed in production)
        // console.log(`Idle: ${idleTime}s, Until timeout: ${timeUntilTimeout}s`);
        
        if (timeUntilTimeout <= 0) {
            // Session has timed out
            this.stopMonitoring();
            this.onTimeout();
        } else if (timeUntilTimeout <= this.warningTime && !this.warningShown) {
            // Show warning
            this.onWarning(timeUntilTimeout);
        }
    }
    
    createWarningModal() {
        // Create modal HTML
        const modalHTML = `
            <div class="modal fade" id="sessionWarningModal" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content border-warning">
                        <div class="modal-header bg-warning text-dark">
                            <h5 class="modal-title">
                                <i class="fas fa-exclamation-triangle me-2"></i>
                                Session Expiring Soon
                            </h5>
                        </div>
                        <div class="modal-body text-center py-4">
                            <div class="mb-3">
                                <i class="fas fa-clock fa-3x text-warning"></i>
                            </div>
                            <h6 class="mb-3">Your session will expire due to inactivity</h6>
                            <div class="alert alert-warning mb-3">
                                <div class="fs-5 fw-bold mb-1">Time Remaining</div>
                                <div class="display-4 fw-bold" id="sessionCountdown">2:00</div>
                            </div>
                            <p class="text-muted small mb-0">
                                Click "Continue Working" to extend your session, or your work will be saved and you'll be logged out.
                            </p>
                        </div>
                        <div class="modal-footer justify-content-center">
                            <button type="button" class="btn btn-success btn-lg px-5" id="extendSessionBtn">
                                <i class="fas fa-check-circle me-2"></i>
                                Continue Working
                            </button>
                            <button type="button" class="btn btn-outline-secondary" id="logoutNowBtn">
                                <i class="fas fa-sign-out-alt me-2"></i>
                                Logout Now
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        `;
        
        // Append to body
        const modalContainer = document.createElement('div');
        modalContainer.innerHTML = modalHTML;
        document.body.appendChild(modalContainer);
        
        // Get modal instance
        this.warningModal = new bootstrap.Modal(document.getElementById('sessionWarningModal'));
        
        // Register button handlers
        document.getElementById('extendSessionBtn').addEventListener('click', () => {
            this.onExtend();
        });
        
        document.getElementById('logoutNowBtn').addEventListener('click', () => {
            this.logout();
        });
    }
    
    showWarningModal(timeRemaining) {
        this.warningShown = true;
        this.warningModal.show();
        
        // Start countdown
        this.startCountdown(timeRemaining);
    }
    
    hideWarningModal() {
        this.warningShown = false;
        if (this.countdownTimer) {
            clearInterval(this.countdownTimer);
            this.countdownTimer = null;
        }
        this.warningModal.hide();
    }
    
    startCountdown(seconds) {
        const countdownElement = document.getElementById('sessionCountdown');
        let remaining = seconds;
        
        const updateCountdown = () => {
            const minutes = Math.floor(remaining / 60);
            const secs = remaining % 60;
            countdownElement.textContent = `${minutes}:${secs.toString().padStart(2, '0')}`;
            
            if (remaining <= 0) {
                clearInterval(this.countdownTimer);
                this.onTimeout();
            }
            remaining--;
        };
        
        updateCountdown(); // Initial update
        this.countdownTimer = setInterval(updateCountdown, 1000);
    }
    
    extendSession() {
        console.log('Session extended');
        
        // Make AJAX request to extend session on server
        fetch('/Armis2/shared/extend_session.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({
                extend: true,
                timestamp: Date.now()
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                console.log('Session extended on server');
                this.updateActivity();
                this.hideWarningModal();
                
                // Show success toast (optional)
                this.showToast('Session Extended', 'Your session has been extended successfully.', 'success');
            } else {
                console.error('Failed to extend session:', data.message);
                this.showToast('Extension Failed', 'Please save your work and login again.', 'danger');
            }
        })
        .catch(error => {
            console.error('Error extending session:', error);
            this.showToast('Connection Error', 'Could not extend session. Please check your connection.', 'warning');
        });
    }
    
    handleTimeout() {
        console.log('Session timed out');
        
        // Save current state before logout
        this.saveCurrentState();
        
        // Hide warning modal if shown
        if (this.warningShown) {
            this.hideWarningModal();
        }
        
        // Show timeout message
        this.showToast('Session Expired', 'Your session has expired due to inactivity. Redirecting to login...', 'info', 3000);
        
        // Logout after delay
        setTimeout(() => {
            this.logout();
        }, 3000);
    }
    
    logout() {
        // Save state
        this.saveCurrentState();
        
        // Redirect to logout
        window.location.href = '/Armis2/logout.php?reason=timeout';
    }
    
    saveCurrentState() {
        try {
            const state = {
                url: window.location.href,
                pathname: window.location.pathname,
                search: window.location.search,
                timestamp: Date.now(),
                forms: this.captureFormData(),
                scrollPosition: window.scrollY,
                filters: this.captureFilters()
            };
            
            sessionStorage.setItem('armis_saved_state', JSON.stringify(state));
            console.log('Current state saved:', state);
        } catch (e) {
            console.error('Error saving state:', e);
        }
    }
    
    captureFormData() {
        const forms = {};
        document.querySelectorAll('form').forEach((form, index) => {
            const formData = {};
            const formId = form.id || `form_${index}`;
            
            form.querySelectorAll('input, select, textarea').forEach(field => {
                if (field.name && field.type !== 'password') {
                    if (field.type === 'checkbox' || field.type === 'radio') {
                        formData[field.name] = field.checked;
                    } else {
                        formData[field.name] = field.value;
                    }
                }
            });
            
            forms[formId] = formData;
        });
        
        return forms;
    }
    
    captureFilters() {
        const filters = {};
        
        // Capture period filter
        const startDate = document.getElementById('filterStartDate');
        const endDate = document.getElementById('filterEndDate');
        if (startDate && endDate) {
            filters.periodFilter = {
                startDate: startDate.value,
                endDate: endDate.value
            };
        }
        
        // Capture personnel filter
        const personnelFilter = document.querySelector('input[name="personnel-filter"]:checked');
        if (personnelFilter) {
            filters.personnelFilter = personnelFilter.value;
        }
        
        // Capture any DataTables state
        if (typeof $ !== 'undefined' && $.fn.DataTable) {
            $('.dataTable').each(function() {
                const table = $(this).DataTable();
                if (table) {
                    const tableId = $(this).attr('id');
                    filters[`datatable_${tableId}`] = {
                        search: table.search(),
                        page: table.page(),
                        order: table.order()
                    };
                }
            });
        }
        
        return filters;
    }
    
    handleStorageEvent(e) {
        // Sync activity across tabs
        if (e.key === 'armis_last_activity' && e.newValue) {
            const activityTime = parseInt(e.newValue);
            if (activityTime > this.lastActivity) {
                this.lastActivity = activityTime;
                if (this.warningShown) {
                    this.hideWarningModal();
                }
            }
        }
    }
    
    showToast(title, message, type = 'info', duration = 5000) {
        // Create toast HTML
        const toastHTML = `
            <div class="toast align-items-center text-white bg-${type} border-0" role="alert" aria-live="assertive" aria-atomic="true">
                <div class="d-flex">
                    <div class="toast-body">
                        <strong>${title}</strong><br>
                        ${message}
                    </div>
                    <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
                </div>
            </div>
        `;
        
        // Create or get toast container
        let toastContainer = document.getElementById('toastContainer');
        if (!toastContainer) {
            toastContainer = document.createElement('div');
            toastContainer.id = 'toastContainer';
            toastContainer.className = 'toast-container position-fixed top-0 end-0 p-3';
            toastContainer.style.zIndex = '9999';
            document.body.appendChild(toastContainer);
        }
        
        // Add toast
        const toastElement = document.createElement('div');
        toastElement.innerHTML = toastHTML;
        toastContainer.appendChild(toastElement.firstElementChild);
        
        // Show toast
        const toast = new bootstrap.Toast(toastContainer.lastElementChild, {
            autohide: true,
            delay: duration
        });
        toast.show();
        
        // Remove after hidden
        toastContainer.lastElementChild.addEventListener('hidden.bs.toast', function() {
            this.remove();
        });
    }
    
    destroy() {
        this.stopMonitoring();
        
        // Remove event listeners
        this.activityEvents.forEach(eventName => {
            document.removeEventListener(eventName, this.updateActivity);
        });
        
        window.removeEventListener('storage', this.handleStorageEvent);
        window.removeEventListener('beforeunload', this.saveCurrentState);
    }
}

// Auto-initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    // Check if we're on a page that requires session management
    // (exclude login page, logout page, etc.)
    const excludedPages = ['login.php', 'logout.php', 'unauthorized.php'];
    const currentPage = window.location.pathname.split('/').pop();
    
    if (!excludedPages.includes(currentPage)) {
        // Initialize session manager
        window.sessionManager = new SessionManager({
            sessionTimeout: 3600, // 1 hour (matches PHP SESSION_TIMEOUT)
            warningTime: 120 // 2 minutes warning
        });
        
        console.log('Session Manager active');
    }
});
