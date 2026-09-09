    </div> <!-- Close main-wrapper -->
    
    <!-- Scroll to Top Button -->
    <button onclick="scrollToTop()" id="scrollToTopBtn" class="btn btn-secondary" 
            style="display:none; position:fixed; bottom:20px; right:20px; z-index:999; border-radius:50%; width:50px; height:50px;">
        <i class="fas fa-arrow-up"></i>
    </button>
    
    <!-- Footer -->
    <footer class="footer mt-auto py-4" style="background-color: var(--armis-primary); color: white; position: relative; z-index: 1;">
        <div class="container-fluid">
            <div class="row align-items-center">
                <div class="col-md-4">
                    <h6 style="color: var(--armis-gold);">ARMIS v1.0</h6>
                    <p class="mb-0 small">Army Resource Management Information System</p>
                    <p class="mb-0 small">Direct Access Mode • Optimized Performance</p>
                </div>
                <div class="col-md-4 text-center">
                    <h5 style="color: var(--armis-gold); margin-bottom: 5px;">ARMIS</h5>
                    <p class="mb-0 small">© <?php echo date('Y'); ?> Army Resource Management Information System</p>
                    <p class="mb-0 small">All Rights Reserved</p>
                </div>
                <div class="col-md-4 text-md-end">
                    <p class="mb-0 small">🔒 Secure • ⚡ Fast Access • 🎯 Professional</p>
                    <p class="mb-0 small">Last updated: <?php echo date('Y-m-d H:i'); ?></p>
                    <p class="mb-0 small">Optimized for 1M+ users</p>
                </div>
            </div>
        </div>
    </footer>

    <!-- Core JS libraries (jQuery + Bootstrap) - loaded once via footer -->
    <script src="https://cdn.jsdelivr.net/npm/jquery@3.6.0/dist/jquery.min.js"></script>
    <script>window.jQuery || document.write('\x3Cscript src="/Armis2/assets/js/jquery-3.6.0.min.js">\x3C/script>');</script>

    <?php if (!defined('ARMIS_SCRIPTS_LOADED')): ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <?php define('ARMIS_SCRIPTS_LOADED', true); ?>
    <!-- Chart.js for modern dashboards (UMD version to avoid ESM import errors) -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.umd.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/apexcharts@3.44.0/dist/apexcharts.min.js"></script>
    <!-- DataTables core and extensions (loaded after jQuery & Bootstrap) -->
    <script src="https://cdn.jsdelivr.net/npm/datatables.net@1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/datatables.net-bs5@1.13.6/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/datatables.net-responsive@2.5.0/js/dataTables.responsive.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/datatables.net-responsive-bs5@2.5.0/js/responsive.bootstrap5.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/datatables.net-select@1.7.0/js/dataTables.select.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/datatables.net-select-bs5@1.6.3/js/select.bootstrap5.min.js"></script>
    <!-- Select2 for enhanced selects (load after jQuery) -->
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    
    <!-- ARMIS Dashboard Utilities -->
    <script src="/Armis2/shared/dashboard-utils.js"></script>
    
    <!-- ARMIS Notifications System -->
    <script src="/Armis2/shared/notifications.js"></script>
    
    <!-- ARMIS Session Management and Form State Preservation -->
    <script>
        // Pass session restore flag from PHP to JavaScript
        var PHP_SESSION_RESTORE_STATE = <?php echo (isset($_SESSION['restore_state']) && $_SESSION['restore_state'] === true) ? 'true' : 'false'; ?>;
        <?php if (isset($_SESSION['restore_state'])) { unset($_SESSION['restore_state']); } ?>
    </script>
    <script src="/Armis2/shared/session-management.js"></script>
    <?php endif; ?>
    <?php if (!empty($moduleScript)): ?>
    <script src="<?= htmlspecialchars($moduleScript, ENT_QUOTES) ?>"></script>
    <?php endif; ?>
    <script>
        // If page defines initAppointmentsPage, call it after DOM is ready
        document.addEventListener('DOMContentLoaded', function() {
            if (typeof window.initAppointmentsPage === 'function') {
                try { window.initAppointmentsPage(); } catch (e) { console.error('Error initializing appointments page:', e); }
            }
        });
    </script>
    
    <script>
        // Scroll to Top functionality
        function scrollToTop() {
            window.scrollTo({
                top: 0,
                behavior: 'smooth'
            });
        }
        
        // Show/Hide scroll to top button
        window.addEventListener('scroll', function() {
            const scrollBtn = document.getElementById('scrollToTopBtn');
            if (scrollBtn) {
                if (window.pageYOffset > 300) {
                    scrollBtn.style.display = 'block';
                } else {
                    scrollBtn.style.display = 'none';
                }
            }
        });
        
        // Note: toggleSidebar() is defined once in shared/header.php (toggles the
        // .active class, matching shared/sidebar.php's CSS) - a second conflicting
        // definition used to live here toggling a non-existent .show/.sidebar-overlay
        // pair, silently breaking the mobile toggle button by overriding header's version.

        // Initialize notifications when page loads
        document.addEventListener('DOMContentLoaded', function() {
            // Welcome notification deactivated
            console.log('ARMIS: Welcome notification disabled');
            
            // Original code commented out:
            // // Show welcome notification for new sessions
            if (sessionStorage.getItem('armis_welcome_shown') !== 'true') {
                 setTimeout(() => {
                     if (typeof armisNotifications !== 'undefined') {
                         armisNotifications.success(
                             'Welcome to ARMIS',
                             'All systems operational. Ready for efficient military resource management.',
                             5000
                         );
                         sessionStorage.setItem('armis_welcome_shown', 'true');
                     }
                 }, 1000);
             }
        });
    </script>
</body>
</html>
