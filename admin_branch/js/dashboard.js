/**
 * Admin Branch Dashboard JavaScript
 * Centralized script for dashboard functionality
 */

document.addEventListener('DOMContentLoaded', function() {
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
    // Implementation for viewing all alerts
    window.location.href = 'live_notifications.php';
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
    // Personnel Distribution Chart
    const personnelChartElement = document.getElementById('personnelChart');
    if (personnelChartElement) {
        // Initialize personnel chart here
    }
    
    // Recruitment Chart
    const recruitmentChartElement = document.getElementById('recruitmentChart');
    if (recruitmentChartElement) {
        // Initialize recruitment chart here
    }
    
    // Performance Chart
    const performanceChartElement = document.getElementById('performanceChart');
    if (performanceChartElement) {
        // Initialize performance chart here
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
        const response = await fetch('dashboard_api.php?action=get_quick_action_stats');
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
        const response = await fetch('dashboard_api.php?action=get_dynamic_units');
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
let refreshInterval = null;

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
 */
let refreshInterval;
let refreshCounter = 0;
const REFRESH_INTERVAL = 30000; // 30 seconds for real-time operations

function initializeAutoRefresh() {
    console.log('🔄 Initializing military-grade auto-refresh system...');
    
    // Start the auto-refresh timer
    refreshInterval = setInterval(() => {
        refreshCounter++;
        console.log(`⏰ Auto-refresh cycle ${refreshCounter} initiated`);
        
        // Refresh dynamic components silently
        Promise.allSettled([
            loadDynamicAlerts(),
            updateAlertsCount(),
            // Only refresh heavy components every 3rd cycle (90 seconds)
            refreshCounter % 3 === 0 ? refreshDashboard() : null
        ]).then(results => {
            const failures = results.filter(r => r.status === 'rejected').length;
            if (failures > 0) {
                console.warn(`⚠️ Auto-refresh cycle ${refreshCounter}: ${failures} operations failed`);
            } else {
                console.log(`✅ Auto-refresh cycle ${refreshCounter} completed successfully`);
            }
        });
    }, REFRESH_INTERVAL);
    
    // Add visible indicator
    const statusElement = document.querySelector('.refresh-status');
    if (statusElement) {
        statusElement.innerHTML = '<i class="fas fa-sync-alt fa-spin text-success"></i> Auto-refresh active';
    }
}

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
    // Redirect to alerts page
    window.location.href = 'live_notifications.php';
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
        const response = await fetch('dashboard_api.php?action=get_alerts');
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
