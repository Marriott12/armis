/**
 * Admin Branch Dashboard JavaScript
 * Centralized script for dashboard functionality
 */

// Global declaration of refreshInterval at the top of the file
let refreshInterval;

document.addEventListener('DOMContentLoaded', function() {
        // Populate filter dropdowns with live data
        fetch('includes/dashboard_service.php?action=get_dashboard_data&type=filter_options')
            .then(response => response.json())
            .then(data => {
                if (data.units) {
                    const unitSelect = document.getElementById('filterUnit');
                    data.units.forEach(u => {
                        const opt = document.createElement('option');
                        opt.value = u; opt.textContent = u; unitSelect.appendChild(opt);
                    });
                }
                if (data.ranks) {
                    const rankSelect = document.getElementById('filterRank');
                    data.ranks.forEach(r => {
                        const opt = document.createElement('option');
                        opt.value = r; opt.textContent = r; rankSelect.appendChild(opt);
                    });
                }
                if (data.statuses) {
                    const statusSelect = document.getElementById('filterStatus');
                    data.statuses.forEach(s => {
                        const opt = document.createElement('option');
                        opt.value = s; opt.textContent = s; statusSelect.appendChild(opt);
                    });
                }
            });

        // Filter logic
        document.getElementById('applyFilters').addEventListener('click', function() {
            // Fetch filtered dashboard data and update all widgets/charts/tables
            // ...implementation...
        });
        document.getElementById('resetFilters').addEventListener('click', function() {
            // ...existing code...

        // Initialize all tooltips
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        tooltipTriggerList.map(function(tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });

        // Responsive adjustment for small screens
        handleResponsiveLayout();

        // Listen for window resize
        window.addEventListener('resize', handleResponsiveLayout);

        // Add smooth scrolling for mobile
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function(e) {
                const href = this.getAttribute('href');
                if (href !== "#" && href.startsWith('#')) {
                    e.preventDefault();
                    document.querySelector(href).scrollIntoView({
                        behavior: 'smooth'
                    });
                }
            });
        });

        // Add click handlers for unit cards
        document.querySelectorAll('.unit-card').forEach(card => {
            card.addEventListener('click', function() {
                const unitName = this.querySelector('.unit-name')?.textContent;
                if (unitName && typeof armisNotifications !== 'undefined') {
                    armisNotifications.info('Unit Details', `Loading details for ${unitName}...`);
                }
            });
            // Add hover effect
            card.style.cursor = 'pointer';
        });

        // Add click handlers for alert items
        document.querySelectorAll('.alert-item').forEach(item => {
            item.addEventListener('click', function() {
                const alertTitle = this.querySelector('.alert-title')?.textContent;
                if (alertTitle && typeof armisNotifications !== 'undefined') {
                    armisNotifications.info('Alert', `Opening: ${alertTitle}`);
                }
            });
            item.style.cursor = 'pointer';
        });

        // Add click handlers for event items
        document.querySelectorAll('.event-item').forEach(item => {
            item.addEventListener('click', function() {
                const eventTitle = this.querySelector('.event-title')?.textContent;
                if (eventTitle && typeof armisNotifications !== 'undefined') {
                    armisNotifications.info('Event', `Opening: ${eventTitle}`);
                }
            });
            item.style.cursor = 'pointer';
        });

        // Init chart data if applicable
        initCharts();
    // Initialize auto-refresh after initial load
    setTimeout(() => {
        initializeAutoRefresh();
    }, 2000);
    
    // Add visibility change handler
    document.addEventListener('visibilitychange', function() {
        if (document.hidden) {
            console.log('Tab hidden - maintaining background refresh');
        } else {
            console.log('Tab visible - resuming normal refresh cycle');
            refreshDashboard();
        }
    });
});

/**
 * Handle responsive layout adjustments
 */
function handleResponsiveLayout() {
    if (window.innerWidth < 768) {
        // Close any open collapse elements on small screens
        document.querySelectorAll('.collapse.show').forEach(collapse => {
            if (!collapse.id.includes('navbarNav')) { // Don't close the main navigation
                bootstrap.Collapse.getInstance(collapse)?.hide();
            }
        });
    }
}

/**
 * Modal action handlers
 */
function closeCurrentModal() {
    document.querySelectorAll('.modal.show').forEach(modal => {
        bootstrap.Modal.getInstance(modal)?.hide();
    });
}

/**
 * Action handlers
 */
function handleEmergencyProtocol() {
    // Implementation for emergency protocols
    alert('Emergency Protocol System activated');
}

function generateReport() {
    var reportsModal = new bootstrap.Modal(document.getElementById('reportsModal'));
    reportsModal.show();
}

function viewAllAlerts() {
    // Implementation for viewing all alerts - redirect to reports page
    window.location.href = 'reports_appointment.php';
}

function viewCalendar() {
    // Implementation for viewing full calendar
    window.location.href = 'appointments.php';
}

/**
 * Widget refresh functionality
 */
function refreshWidget(widgetId) {
    // Example implementation to refresh a widget
    const widget = document.getElementById(widgetId);
    if (widget) {
        widget.classList.add('refreshing');
        // Simulate refresh with timeout
        setTimeout(() => {
            widget.classList.remove('refreshing');
            // Could make an AJAX call here to refresh the data
        }, 1000);
    }
}

/**
 * Export functionality
 */
function exportReport(reportType, format = 'csv') {
    // Implementation for exporting reports
    const url = `export.php?type=${reportType}&format=${format}`;
    const link = document.createElement('a');
    link.href = url;
    link.download = `${reportType}_${new Date().toISOString().split('T')[0]}.${format}`;
    link.click();
}

function exportUnitReport() {
    exportReport('unit_report', 'csv');
}

/**
 * Chart initialization 
 */
function initCharts() {
    // Initialize empty charts on page load
    if (window.Chart) {
        window.personnelChart = new Chart(document.getElementById('personnelChart').getContext('2d'), {
            type: 'doughnut',
            data: { labels: ['Active', 'Leave', 'Training', 'Deployed', 'Retired'], datasets: [{ data: [0,0,0,0,0], backgroundColor: ['#007bff','#ffc107','#28a745','#6c757d','#343a40'] }] },
            options: { responsive: true, plugins: { legend: { position: 'bottom' } } }
        });
        window.recruitmentChart = new Chart(document.getElementById('recruitmentChart').getContext('2d'), {
            type: 'bar',
            data: { labels: ['Jan','Feb','Mar','Apr','May','Jun'], datasets: [{ label: 'Recruits', data: [0,0,0,0,0,0], backgroundColor: '#007bff' }] },
            options: { responsive: true, plugins: { legend: { display: false } } }
        });
        window.performanceChart = new Chart(document.getElementById('performanceChart').getContext('2d'), {
            type: 'line',
            data: { labels: ['Q1','Q2','Q3','Q4'], datasets: [{ label: 'Performance', data: [0,0,0,0], borderColor: '#28a745', backgroundColor: 'rgba(40,167,69,0.1)' }] },
            options: { responsive: true, plugins: { legend: { display: false } } }
        });
    }
}

function updatePersonnelChart(data) {
    if (window.personnelChart) {
        window.personnelChart.data.datasets[0].data = [data.active, data.leave, data.training, data.deployed, data.retired];
        window.personnelChart.update();
    }
}

function updateRecruitmentChart(data) {
    if (window.recruitmentChart) {
        window.recruitmentChart.data.labels = data.labels;
        window.recruitmentChart.data.datasets[0].data = data.data;
        window.recruitmentChart.update();
    }
}

function updatePerformanceChart(data) {
    if (window.performanceChart) {
        window.performanceChart.data.labels = data.labels;
        window.performanceChart.data.datasets[0].data = data.data;
        window.performanceChart.update();
    }
}

/**
 * Dashboard data refresh functions
 */
function refreshDashboard() {
    // Refresh all dashboard widgets
    document.querySelectorAll('[id$="-widget"]').forEach(widget => {
        refreshWidget(widget.id);
    });
    // Load all dashboard data from backend service
    fetch('includes/dashboard_service.php?action=get_dashboard_data&type=all')
        .then(response => response.json())
        .then(data => {
            // Update KPI cards
            if (data.kpi) {
                const totalPersonnel = document.querySelector('[data-kpi="total-personnel"]');
                if (totalPersonnel) totalPersonnel.textContent = data.kpi.total_personnel ?? '-';
                const activePersonnel = document.querySelector('[data-kpi="active-personnel"]');
                if (activePersonnel) activePersonnel.textContent = data.kpi.active_personnel ?? '-';
                const newRecruits = document.querySelector('[data-kpi="new-recruits"]');
                if (newRecruits) newRecruits.textContent = data.kpi.new_recruits ?? '-';
                const performanceAvg = document.querySelector('[data-kpi="performance-avg"]');
                if (performanceAvg) performanceAvg.textContent = (data.kpi.performance_avg ?? '-') + '%';
                const onLeaveTraining = document.querySelector('[data-kpi="on-leave-training"]');
                if (onLeaveTraining) onLeaveTraining.textContent = data.kpi.on_leave_training ?? '-';
            }
            // Update Personnel Category Snapshot
            if (data.personnel_categories) {
                document.getElementById('category-officer').textContent = data.personnel_categories.Officer ?? '-';
                document.getElementById('category-nco').textContent = data.personnel_categories.NCO ?? '-';
                document.getElementById('category-ce').textContent = data.personnel_categories.CE ?? '-';
                document.getElementById('category-retired').textContent = data.personnel_categories.Retired ?? '-';
            }
            // Update Gender Statistics for Categories
            if (data.gender_stats) {
                document.getElementById('category-officer-male').textContent = data.gender_stats.Officer?.male ?? '-';
                document.getElementById('category-officer-female').textContent = data.gender_stats.Officer?.female ?? '-';
                document.getElementById('category-nco-male').textContent = data.gender_stats.NCO?.male ?? '-';
                document.getElementById('category-nco-female').textContent = data.gender_stats.NCO?.female ?? '-';
                document.getElementById('category-ce-male').textContent = data.gender_stats.CE?.male ?? '-';
                document.getElementById('category-ce-female').textContent = data.gender_stats.CE?.female ?? '-';
                document.getElementById('category-retired-male').textContent = data.gender_stats.Retired?.male ?? '-';
                document.getElementById('category-retired-female').textContent = data.gender_stats.Retired?.female ?? '-';
            }
            // Update personnel distribution chart
            if (data.personnel_distribution) {
                updatePersonnelChart(data.personnel_distribution);
            }
            if (data.recruitment_trends) {
                updateRecruitmentChart(data.recruitment_trends);
            }
            if (data.performance_metrics) {
                updatePerformanceChart(data.performance_metrics);
            }
        })
        .catch(error => {
            console.error('Error loading dashboard data:', error);
        });
    // Load quick action stats
    loadQuickActionStats();
    // Load dynamic unit data
    loadDynamicUnits();
}

/**
 * Filter chart data by timeframe
 */
function filterChart(timeframe) {
    console.log('Filtering chart by timeframe:', timeframe);
    // Implementation would update chart data based on timeframe
}

/**
 * Load dynamic Quick Action stats
 */
async function loadQuickActionStats() {
    try {
    const response = await fetch('includes/dashboard_service.php?action=get_dashboard_data&type=quick_action_stats');
        const data = await response.json();
        
        if (data.success) {
            const stats = data.data;
            
            // Update the Quick Action badges
            const newPersonnelBadge = document.querySelector('.quick-action-card:nth-child(1) .badge');
            const pendingAssignmentsBadge = document.querySelector('.quick-action-card:nth-child(2) .badge');
            const emergencyBadge = document.querySelector('.quick-action-card:nth-child(3) .badge');
            const reportsBadge = document.querySelector('.quick-action-card:nth-child(4) .badge');
            
            if (newPersonnelBadge) newPersonnelBadge.textContent = stats.new_personnel || '0';
            if (pendingAssignmentsBadge) pendingAssignmentsBadge.textContent = stats.pending_assignments || '0';
            if (emergencyBadge) emergencyBadge.textContent = stats.emergency_protocols || '0';
            if (reportsBadge) reportsBadge.textContent = stats.reports_today || '0';
        }
    } catch (error) {
        console.error('Error loading quick action stats:', error);
    }
}

/**
 * Load dynamic Unit Overview data
 */
async function loadDynamicUnits() {
    try {
    const response = await fetch('includes/dashboard_service.php?action=get_dashboard_data&type=dynamic_unit_overview');
        const data = await response.json();
        
        if (data.success && data.data) {
            const units = data.data;
            const unitOverviewContainer = document.querySelector('.unit-overview-container');
            
            if (unitOverviewContainer && units.length > 0) {
                // Update unit cards
                console.log('Updating unit overview with new data');
            }
        }
    } catch (error) {
        console.error('Error loading dynamic units:', error);
    }
}

/**
 * Auto-refresh system
 */

function initializeAutoRefresh() {
    // Check if auto-refresh is already initialized
    if (refreshInterval) return;
    
    const refreshRate = 60000; // 1 minute
    refreshInterval = setInterval(() => {
        if (!document.hidden) {
            refreshDashboard();
        }
    }, refreshRate);
    
    console.log('Auto-refresh system initialized');
    
    const statusElement = document.querySelector('.refresh-status');
    if (statusElement) {
        statusElement.textContent = 'Auto-refresh: Enabled';
    }
}

function toggleAutoRefresh() {
    if (refreshInterval) {
        stopAutoRefresh();
    } else {
        initializeAutoRefresh();
    }
}

function stopAutoRefresh() {
    if (refreshInterval) {
        clearInterval(refreshInterval);
        refreshInterval = null;
        console.log('Auto-refresh system stopped');
        
        const statusElement = document.querySelector('.refresh-status');
        if (statusElement) {
            statusElement.textContent = 'Auto-refresh: Disabled';
        }
    }
}

// Add unhandled promise rejection handler

window.addEventListener('unhandledrejection', function(event) {
    console.error('Unhandled promise rejection:', event.reason);
    event.preventDefault(); // Prevent default browser error handling
    if (typeof armisNotifications !== 'undefined') {
        armisNotifications.warning('Warning', 'A background operation encountered an issue.');
    }
});

/**
 * Military-Grade Auto-Refresh System
 *
 * NOTE: Chart.js import error fix:
 * If you see 'Cannot use import statement outside a module', ensure you are NOT using 'import' in this file.
 * Use Chart.js via CDN in your HTML:
 * <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
 * Do NOT use: import Chart from 'chart.js';
 */
// refreshInterval is already declared at the top of the file
// ...existing code...

/**
 * Helper functions for UI elements 
 */

// Helper function to get priority icon
function getPriorityIcon(type) {
    switch (type) {
        case 'personnel': return 'fa-pulse';
        case 'training': return 'fa-spin';
        case 'equipment': return '';
        default: return '';
    }
}

// Helper function to get activity icon
function getActivityIcon(type) {
    switch (type) {
        case 'personnel': return 'user';
        case 'training': return 'graduation-cap';
        case 'equipment': return 'tools';
        case 'report': return 'file-alt';
        default: return 'info';
    }
}

// Helper function to calculate time ago
function getTimeAgo(datetime) {
    const now = new Date();
    const time = new Date(datetime);
    const diffInSeconds = Math.floor((now - time) / 1000);
    
    if (diffInSeconds < 60) return 'Just now';
    if (diffInSeconds < 3600) return Math.floor(diffInSeconds / 60) + ' minutes ago';
    if (diffInSeconds < 86400) return Math.floor(diffInSeconds / 3600) + ' hours ago';
    return Math.floor(diffInSeconds / 86400) + ' days ago';
}

/**
 * Alerts management
 */
function viewAllAlerts() {
    if (typeof armisNotifications !== 'undefined') {
        armisNotifications.info('Alerts', 'Opening alerts management panel...');
    }
    // Redirect to reports page instead of live notifications
    window.location.href = 'reports_appointment.php';
}

function handleAlertClick(alertId) {
    if (typeof armisNotifications !== 'undefined') {
        armisNotifications.info('Alert Details', `Opening alert ${alertId} details...`);
    }
    // Could open alert details modal or mark as read
}

function updateAlertsCount() {
    // This could fetch real alert count from API
    const alertCountElement = document.getElementById('alert-count');
    if (alertCountElement) {
        // Simulate dynamic count - in real implementation, fetch from API
        const currentCount = parseInt(alertCountElement.textContent) || 0;
        // Could update based on real data
    }
}

/**
 * Dynamic data loading functions
 */
async function loadDynamicAlerts() {
    try {
    const response = await fetch('includes/dashboard_service.php?action=get_dashboard_data&type=alerts');
        const data = await response.json();
        
        if (data.success) {
            // Update alerts count badge
            const alertsCount = document.getElementById('alerts-notifications-count');
            if (alertsCount) {
                alertsCount.textContent = data.data.length;
            }
            
            // Update alerts list
            const alertsList = document.getElementById('alerts-list');
            if (alertsList && data.data.length > 0) {
                let alertsHtml = '';
                
                data.data.forEach(alert => {
                    alertsHtml += `
                        <div class="alert-item border-${alert.type} border-start border-3 ps-3 mb-2">
                            <div class="d-flex align-items-start">
                                <div class="alert-icon text-${alert.type} me-2">
                                    <i class="fas fa-${alert.icon}"></i>
                                </div>
                                <div class="alert-content flex-grow-1">
                                    <h6 class="alert-title text-${alert.type} fw-bold mb-1">${alert.title}</h6>
                                    <p class="alert-text small text-muted mb-1">${alert.text}</p>
                                    <small class="alert-time text-muted">${alert.time}</small>
                                </div>
                            </div>
                        </div>
                    `;
                });
                
                alertsList.innerHTML = alertsHtml;
            }
        }
    } catch (error) {
        console.error('Error loading alerts:', error);
    }
}

/**
 * Additional action handlers
 */
function viewUnitDetails(unitName) {
    if (typeof armisNotifications !== 'undefined') {
        armisNotifications.info('Unit Details', `Loading details for ${unitName}...`);
    }
    // Could open unit details modal or navigate to unit page
}

// Ensure the main DOMContentLoaded handler is properly closed
});
