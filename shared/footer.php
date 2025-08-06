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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Chart.js for modern dashboards -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/apexcharts@3.44.0/dist/apexcharts.min.js"></script>
    
    <!-- ARMIS Dashboard Utilities -->
    <script src="/Armis2/shared/dashboard-utils.js"></script>
    
    <!-- ARMIS Notifications System -->
    <script src="/Armis2/shared/notifications.js"></script>
    
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
        
        // Toggle sidebar function for mobile
        function toggleSidebar() {
            const sidebar = document.querySelector('.sidebar');
            const overlay = document.querySelector('.sidebar-overlay');
            if (sidebar) {
                sidebar.classList.toggle('show');
            }
            if (overlay) {
                overlay.style.display = sidebar.classList.contains('show') ? 'block' : 'none';
            }
        }
        
        // Initialize notifications when page loads
        document.addEventListener('DOMContentLoaded', function() {
            // Show welcome notification for new sessions
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
