// session-warning.js
// Show a warning modal before session timeout and auto-logout after timeout

(function() {
    // Configurable values (should match PHP session timeout)
    const SESSION_TIMEOUT_SECONDS = 20 * 60; // 20 minutes
    const WARNING_BEFORE_SECONDS = 60; // Show warning 1 minute before timeout

    let warningTimeout, logoutTimeout;
    let warningModal;

    function showWarningModal() {
        if (!warningModal) {
            warningModal = document.createElement('div');
            warningModal.innerHTML = `
                <div class="modal fade" id="sessionTimeoutModal" tabindex="-1" aria-labelledby="sessionTimeoutLabel" aria-hidden="true">
                  <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content">
                      <div class="modal-header bg-warning">
                        <h5 class="modal-title" id="sessionTimeoutLabel"><i class="fas fa-exclamation-triangle text-danger me-2"></i>Session Expiring Soon</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                      </div>
                      <div class="modal-body">
                        <p>Your session will expire in <span id="session-timeout-countdown">60</span> seconds due to inactivity.</p>
                        <p>Please interact with the page to stay logged in.</p>
                      </div>
                      <div class="modal-footer">
                        <button type="button" class="btn btn-primary" data-bs-dismiss="modal">Continue Session</button>
                      </div>
                    </div>
                  </div>
                </div>
            `;
            document.body.appendChild(warningModal);
        }
        // Show modal
        const modal = new bootstrap.Modal(document.getElementById('sessionTimeoutModal'));
        modal.show();
        // Countdown
        let secondsLeft = WARNING_BEFORE_SECONDS;
        const countdownSpan = document.getElementById('session-timeout-countdown');
        const interval = setInterval(() => {
            secondsLeft--;
            if (countdownSpan) countdownSpan.textContent = secondsLeft;
            if (secondsLeft <= 0) clearInterval(interval);
        }, 1000);
        // Reset timer on modal close
        document.getElementById('sessionTimeoutModal').addEventListener('hidden.bs.modal', function() {
            resetSessionTimeouts();
        }, { once: true });
    }

    function resetSessionTimeouts() {
        clearTimeout(warningTimeout);
        clearTimeout(logoutTimeout);
        // Reset timers on any user activity
        setupSessionTimeouts();
    }

    function setupSessionTimeouts() {
        // Time until warning
        const warningDelay = (SESSION_TIMEOUT_SECONDS - WARNING_BEFORE_SECONDS) * 1000;
        // Time until logout
        const logoutDelay = SESSION_TIMEOUT_SECONDS * 1000;
        warningTimeout = setTimeout(showWarningModal, warningDelay);
        logoutTimeout = setTimeout(() => {
            window.location.href = '/Armis2/logout.php?timeout=1';
        }, logoutDelay);
    }

    // Reset timers on user activity
    ['click', 'mousemove', 'keydown', 'scroll', 'touchstart'].forEach(evt => {
        document.addEventListener(evt, resetSessionTimeouts, true);
    });

    // Start timers on page load
    setupSessionTimeouts();
})();
