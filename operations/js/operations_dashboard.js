/**
 * Operations Dashboard JavaScript
 * Handles dynamic data loading and user interactions
 */

class OperationsDashboard {
    constructor() {
        this.apiUrl = '/operations/api.php';
        this.refreshInterval = 30000; // 30 seconds
        this.charts = {};
        this.init();
    }

    /**
     * Initialize dashboard
     */
    init() {
        this.bindEvents();
        this.loadDashboardData();
        this.startAutoRefresh();
    }

    /**
     * Bind event listeners
     */
    bindEvents() {
        // Refresh button
        $(document).on('click', '.refresh-dashboard', () => {
            this.loadDashboardData();
        });

        // KPI card clicks for drill-down
        $(document).on('click', '.kpi-card', function() {
            const target = $(this).data('target');
            if (target) {
                window.operationsDashboard.showDrillDown(target);
            }
        });

        // Alert actions
        $(document).on('click', '.acknowledge-alert', function() {
            const alertId = $(this).data('alert-id');
            window.operationsDashboard.acknowledgeAlert(alertId);
        });

        // Real-time updates toggle
        $(document).on('change', '#realTimeUpdates', function() {
            if ($(this).is(':checked')) {
                window.operationsDashboard.startAutoRefresh();
            } else {
                window.operationsDashboard.stopAutoRefresh();
            }
        });
    }

    /**
     * Load all dashboard data
     */
    async loadDashboardData() {
        try {
            this.showLoading();
            
            const response = await this.makeApiCall('get_all_dashboard_data');
            
            if (response.success) {
                this.updateKPICards(response.data.kpi);
                this.updateRecentActivities(response.data.recent_activities);
                this.updateMissionStats(response.data.mission_stats);
                this.updateDeploymentOverview(response.data.deployment_overview);
                this.updateResourceAllocation(response.data.resource_allocation);
                this.updateReadinessMetrics(response.data.readiness_metrics);
                this.loadActiveAlerts();
            } else {
                this.showError('Failed to load dashboard data: ' + response.error);
            }
        } catch (error) {
            console.error('Dashboard loading error:', error);
            this.showError('Error loading dashboard data');
        } finally {
            this.hideLoading();
        }
    }

    /**
     * Update KPI cards
     */
    updateKPICards(kpiData) {
        $('#activeMissions').text(kpiData.active_missions || 0);
        $('#activeDeployments').text(kpiData.active_deployments || 0);
        $('#resourceUtilization').text((kpiData.resource_utilization || 0) + '%');
        $('#priorityAlerts').text(kpiData.priority_alerts || 0);
        $('#fieldUnits').text(kpiData.field_units || 0);

        // Update resource utilization progress bar
        const utilization = kpiData.resource_utilization || 0;
        $('#resourceProgress').css('width', utilization + '%');
        
        // Color code based on utilization
        const progressBar = $('#resourceProgress');
        progressBar.removeClass('bg-success bg-warning bg-danger');
        if (utilization < 60) {
            progressBar.addClass('bg-success');
        } else if (utilization < 80) {
            progressBar.addClass('bg-warning');
        } else {
            progressBar.addClass('bg-danger');
        }
    }

    /**
     * Update recent activities
     */
    updateRecentActivities(activities) {
        const container = $('#recentActivities');
        container.empty();

        if (!activities || activities.length === 0) {
            container.html('<div class="text-center text-muted">No recent activities</div>');
            return;
        }

        activities.forEach(activity => {
            const statusClass = this.getStatusClass(activity.status);
            const priorityDot = this.getPriorityDot(activity.priority);
            
            const activityHtml = `
                <div class="list-group-item d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="mb-1">${priorityDot}${this.escapeHtml(activity.title)}</h6>
                        <p class="mb-1">${this.escapeHtml(activity.description || '')}</p>
                        <small class="text-muted">${this.formatDateTime(activity.timestamp)}</small>
                    </div>
                    <span class="badge mission-status status-${activity.status}">${activity.status}</span>
                </div>
            `;
            container.append(activityHtml);
        });
    }

    /**
     * Update mission statistics
     */
    updateMissionStats(missionStats) {
        // Update status distribution chart
        if (missionStats.status_distribution) {
            this.createPieChart('missionStatusChart', 'Mission Status Distribution', missionStats.status_distribution);
        }

        // Update priority distribution chart
        if (missionStats.priority_distribution) {
            this.createBarChart('missionPriorityChart', 'Mission Priority Distribution', missionStats.priority_distribution);
        }
    }

    /**
     * Update deployment overview
     */
    updateDeploymentOverview(deployments) {
        const container = $('#deploymentOverview');
        container.empty();

        if (!deployments || deployments.length === 0) {
            container.html('<div class="text-center text-muted">No active deployments</div>');
            return;
        }

        deployments.forEach(deployment => {
            const deploymentHtml = `
                <div class="col-md-6 col-lg-4 mb-3">
                    <div class="card operations-card h-100">
                        <div class="card-body">
                            <h6 class="card-title">${this.escapeHtml(deployment.deployment_name)}</h6>
                            <p class="card-text small">${this.escapeHtml(deployment.location || 'Location TBD')}</p>
                            <div class="d-flex justify-content-between align-items-center">
                                <span class="badge mission-status status-${deployment.status}">${deployment.status}</span>
                                <small class="text-muted">${deployment.personnel_count || 0} personnel</small>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            container.append(deploymentHtml);
        });
    }

    /**
     * Update resource allocation
     */
    updateResourceAllocation(resources) {
        const container = $('#resourceAllocation');
        container.empty();

        if (!resources || resources.length === 0) {
            container.html('<div class="text-center text-muted">No resource data available</div>');
            return;
        }

        resources.forEach(resource => {
            const total = parseInt(resource.total_available) || 0;
            const allocated = parseInt(resource.total_allocated) || 0;
            const utilization = total > 0 ? Math.round((allocated / total) * 100) : 0;
            
            const resourceHtml = `
                <div class="col-md-6 col-lg-3 mb-3">
                    <div class="card operations-card">
                        <div class="card-body text-center">
                            <h6 class="card-title">${this.escapeHtml(resource.resource_type)}</h6>
                            <div class="resource-progress mb-2">
                                <div class="resource-progress-bar bg-primary" style="width: ${utilization}%">
                                    <span class="progress-text">${utilization}%</span>
                                </div>
                            </div>
                            <small class="text-muted">${allocated}/${total} allocated</small>
                        </div>
                    </div>
                </div>
            `;
            container.append(resourceHtml);
        });
    }

    /**
     * Update readiness metrics
     */
    updateReadinessMetrics(metrics) {
        this.updateReadinessGauge('#equipmentReadiness', metrics.equipment_readiness || 0);
        this.updateReadinessGauge('#personnelReadiness', metrics.personnel_readiness || 0);
    }

    /**
     * Update readiness gauge
     */
    updateReadinessGauge(selector, percentage) {
        const gauge = $(selector);
        const fill = gauge.find('.readiness-fill');
        const text = gauge.find('.readiness-text');
        
        fill.css('height', percentage + '%');
        text.text(percentage + '%');
        
        // Color coding
        fill.removeClass('bg-success bg-warning bg-danger');
        if (percentage >= 80) {
            fill.addClass('bg-success');
        } else if (percentage >= 60) {
            fill.addClass('bg-warning');
        } else {
            fill.addClass('bg-danger');
        }
    }

    /**
     * Load active alerts
     */
    async loadActiveAlerts() {
        try {
            const response = await this.makeApiCall('get_active_alerts');
            
            if (response.success) {
                this.updateActiveAlerts(response.data);
            }
        } catch (error) {
            console.error('Error loading alerts:', error);
        }
    }

    /**
     * Update active alerts
     */
    updateActiveAlerts(alerts) {
        const container = $('#activeAlerts');
        container.empty();

        if (!alerts || alerts.length === 0) {
            container.html('<div class="text-center text-muted">No active alerts</div>');
            return;
        }

        alerts.forEach(alert => {
            const alertHtml = `
                <div class="operations-alert alert-${alert.priority} mb-2">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <h6 class="mb-1">${this.escapeHtml(alert.alert_title)}</h6>
                            <p class="mb-1 small">${this.escapeHtml(alert.alert_message)}</p>
                            <small class="text-muted">${this.formatDateTime(alert.created_at)}</small>
                        </div>
                        <div>
                            <span class="badge bg-${this.getPriorityColor(alert.priority)}">${alert.priority}</span>
                            <button class="btn btn-sm btn-outline-primary acknowledge-alert ms-1" 
                                    data-alert-id="${alert.id}">
                                Acknowledge
                            </button>
                        </div>
                    </div>
                </div>
            `;
            container.append(alertHtml);
        });
    }

    /**
     * Acknowledge alert
     */
    async acknowledgeAlert(alertId) {
        try {
            const response = await this.makeApiCall('acknowledge_alert', { alert_id: alertId });
            
            if (response.success) {
                this.showSuccess('Alert acknowledged successfully');
                this.loadActiveAlerts();
            } else {
                this.showError('Failed to acknowledge alert');
            }
        } catch (error) {
            console.error('Error acknowledging alert:', error);
            this.showError('Error acknowledging alert');
        }
    }

    /**
     * Create pie chart
     */
    createPieChart(canvasId, title, data) {
        const ctx = document.getElementById(canvasId);
        if (!ctx) return;

        // Destroy existing chart
        if (this.charts[canvasId]) {
            this.charts[canvasId].destroy();
        }

        const labels = Object.keys(data);
        const values = Object.values(data);
        const colors = ['#dc3545', '#fd7e14', '#198754', '#0dcaf0', '#ffc107'];

        this.charts[canvasId] = new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: labels,
                datasets: [{
                    data: values,
                    backgroundColor: colors.slice(0, labels.length),
                    borderWidth: 2,
                    borderColor: '#fff'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    title: {
                        display: true,
                        text: title
                    },
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });
    }

    /**
     * Create bar chart
     */
    createBarChart(canvasId, title, data) {
        const ctx = document.getElementById(canvasId);
        if (!ctx) return;

        // Destroy existing chart
        if (this.charts[canvasId]) {
            this.charts[canvasId].destroy();
        }

        const labels = Object.keys(data);
        const values = Object.values(data);

        this.charts[canvasId] = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Count',
                    data: values,
                    backgroundColor: '#dc3545',
                    borderColor: '#b02a37',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    title: {
                        display: true,
                        text: title
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });
    }

    /**
     * Make API call
     */
    async makeApiCall(action, params = {}) {
        const url = new URL(this.apiUrl, window.location.origin);
        url.searchParams.append('action', action);
        
        Object.keys(params).forEach(key => {
            url.searchParams.append(key, params[key]);
        });

        const response = await fetch(url);
        return await response.json();
    }

    /**
     * Start auto refresh
     */
    startAutoRefresh() {
        this.stopAutoRefresh();
        this.refreshTimer = setInterval(() => {
            this.loadDashboardData();
        }, this.refreshInterval);
    }

    /**
     * Stop auto refresh
     */
    stopAutoRefresh() {
        if (this.refreshTimer) {
            clearInterval(this.refreshTimer);
        }
    }

    /**
     * Utility functions
     */
    getStatusClass(status) {
        const statusClasses = {
            'active': 'status-active',
            'planning': 'status-planning',
            'completed': 'status-completed',
            'cancelled': 'status-cancelled',
            'on-hold': 'status-on-hold'
        };
        return statusClasses[status] || 'status-planning';
    }

    getPriorityDot(priority) {
        const colors = {
            'low': '#198754',
            'medium': '#ffc107',
            'high': '#fd7e14',
            'critical': '#dc3545'
        };
        const color = colors[priority] || '#6c757d';
        return `<span class="priority-indicator" style="background-color: ${color}"></span>`;
    }

    getPriorityColor(priority) {
        const colors = {
            'low': 'success',
            'medium': 'warning',
            'high': 'info',
            'critical': 'danger'
        };
        return colors[priority] || 'secondary';
    }

    formatDateTime(dateString) {
        const date = new Date(dateString);
        return date.toLocaleString();
    }

    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    showLoading() {
        $('#dashboardLoading').show();
    }

    hideLoading() {
        $('#dashboardLoading').hide();
    }

    showSuccess(message) {
        this.showToast(message, 'success');
    }

    showError(message) {
        this.showToast(message, 'error');
    }

    showToast(message, type) {
        // Create toast element
        const toast = $(`
            <div class="toast align-items-center text-white bg-${type === 'success' ? 'success' : 'danger'} border-0" role="alert">
                <div class="d-flex">
                    <div class="toast-body">${message}</div>
                    <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
                </div>
            </div>
        `);

        // Add to toast container
        $('#toastContainer').append(toast);
        
        // Show toast
        const bsToast = new bootstrap.Toast(toast[0]);
        bsToast.show();
        
        // Remove after hidden
        toast.on('hidden.bs.toast', function() {
            $(this).remove();
        });
    }
}

// Initialize dashboard when DOM is ready
$(document).ready(function() {
    window.operationsDashboard = new OperationsDashboard();
});