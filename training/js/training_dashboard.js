/**
 * Training Dashboard JavaScript
 * Handles dynamic data loading and user interactions
 */

class TrainingDashboard {
    constructor() {
        this.apiUrl = '/training/api.php';
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
        $(document).on('click', '.training-kpi-card', function() {
            const target = $(this).data('target');
            if (target) {
                window.trainingDashboard.showDrillDown(target);
            }
        });

        // Course enrollment actions
        $(document).on('click', '.enroll-course', function() {
            const courseId = $(this).data('course-id');
            window.trainingDashboard.enrollInCourse(courseId);
        });

        // Real-time updates toggle
        $(document).on('change', '#realTimeUpdates', function() {
            if ($(this).is(':checked')) {
                window.trainingDashboard.startAutoRefresh();
            } else {
                window.trainingDashboard.stopAutoRefresh();
            }
        });

        // Session attendance marking
        $(document).on('click', '.mark-attendance', function() {
            const sessionId = $(this).data('session-id');
            const personnelId = $(this).data('personnel-id');
            window.trainingDashboard.markAttendance(sessionId, personnelId);
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
                this.updateCourseStats(response.data.course_stats);
                this.updateEnrollmentOverview(response.data.enrollment_overview);
                this.updateProgressMetrics(response.data.progress_metrics);
                this.updateUpcomingSessions(response.data.upcoming_sessions);
                this.updateCertificationStatus(response.data.certification_status);
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
        $('#activeCourses').text(kpiData.active_courses || 0);
        $('#enrolledPersonnel').text(kpiData.enrolled_personnel || 0);
        $('#completionRate').text((kpiData.completion_rate || 0) + '%');
        $('#upcomingSessions').text(kpiData.upcoming_sessions || 0);
        $('#pendingCertifications').text(kpiData.pending_certifications || 0);

        // Update completion rate progress bar
        const completionRate = kpiData.completion_rate || 0;
        $('#completionProgress').css('width', completionRate + '%');
        
        // Color code based on completion rate
        const progressBar = $('#completionProgress');
        progressBar.removeClass('bg-success bg-warning bg-danger');
        if (completionRate >= 80) {
            progressBar.addClass('bg-success');
        } else if (completionRate >= 60) {
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
            const typeIcon = this.getTypeIcon(activity.type);
            
            const activityHtml = `
                <div class="list-group-item d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="mb-1">${typeIcon} ${this.escapeHtml(activity.title)}</h6>
                        <p class="mb-1">${this.escapeHtml(activity.description || '')}</p>
                        <small class="text-muted">${this.formatDateTime(activity.timestamp)}</small>
                    </div>
                    <span class="badge enrollment-status enrollment-${activity.status}">${activity.status}</span>
                </div>
            `;
            container.append(activityHtml);
        });
    }

    /**
     * Update course statistics
     */
    updateCourseStats(courseStats) {
        // Update status distribution chart
        if (courseStats.status_distribution) {
            this.createPieChart('courseStatusChart', 'Course Status Distribution', courseStats.status_distribution);
        }

        // Update type distribution chart
        if (courseStats.type_distribution) {
            this.createBarChart('courseTypeChart', 'Course Type Distribution', courseStats.type_distribution);
        }
    }

    /**
     * Update enrollment overview
     */
    updateEnrollmentOverview(enrollments) {
        const container = $('#enrollmentOverview');
        container.empty();

        if (!enrollments || enrollments.length === 0) {
            container.html('<div class="text-center text-muted">No active enrollments</div>');
            return;
        }

        enrollments.forEach(enrollment => {
            const capacity = parseInt(enrollment.max_participants) || 0;
            const enrolled = parseInt(enrollment.enrolled_count) || 0;
            const utilizationPercent = capacity > 0 ? Math.round((enrolled / capacity) * 100) : 0;
            
            const enrollmentHtml = `
                <div class="col-md-6 col-lg-4 mb-3">
                    <div class="card course-card h-100">
                        <div class="card-body">
                            <h6 class="card-title">${this.escapeHtml(enrollment.course_name)}</h6>
                            <span class="badge course-difficulty difficulty-${enrollment.difficulty_level || 'beginner'} mb-2">
                                ${enrollment.difficulty_level || 'Beginner'}
                            </span>
                            <p class="card-text small text-muted">${this.escapeHtml(enrollment.course_type)}</p>
                            <div class="training-progress mb-2">
                                <div class="training-progress-bar bg-info" style="width: ${utilizationPercent}%">
                                    <span class="progress-text">${enrolled}/${capacity}</span>
                                </div>
                            </div>
                            <div class="d-flex justify-content-between align-items-center">
                                <span class="badge course-status status-${enrollment.status}">${enrollment.status}</span>
                                <small class="text-muted">${this.formatDate(enrollment.start_date)}</small>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            container.append(enrollmentHtml);
        });
    }

    /**
     * Update progress metrics
     */
    updateProgressMetrics(metrics) {
        // Update progress by type
        if (metrics.by_type) {
            this.updateProgressByType(metrics.by_type);
        }

        // Update enrollment trends chart
        if (metrics.enrollment_trends) {
            this.createLineChart('enrollmentTrendsChart', 'Enrollment Trends', metrics.enrollment_trends);
        }
    }

    /**
     * Update progress by course type
     */
    updateProgressByType(progressData) {
        const container = $('#progressByType');
        container.empty();

        Object.keys(progressData).forEach(courseType => {
            const data = progressData[courseType];
            const progressHtml = `
                <div class="col-md-6 col-lg-4 mb-3">
                    <div class="card training-card">
                        <div class="card-body text-center">
                            <h6 class="card-title">${this.escapeHtml(courseType)}</h6>
                            <div class="training-progress mb-2">
                                <div class="training-progress-bar bg-success" style="width: ${data.completion_rate}%">
                                    <span class="progress-text">${data.completion_rate}%</span>
                                </div>
                            </div>
                            <small class="text-muted">${data.completed}/${data.total} completed</small>
                        </div>
                    </div>
                </div>
            `;
            container.append(progressHtml);
        });
    }

    /**
     * Update upcoming sessions
     */
    updateUpcomingSessions(sessions) {
        const container = $('#upcomingSessions');
        container.empty();

        if (!sessions || sessions.length === 0) {
            container.html('<div class="text-center text-muted">No upcoming sessions</div>');
            return;
        }

        sessions.forEach(session => {
            const sessionHtml = `
                <div class="session-schedule">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <h6 class="mb-1">${this.escapeHtml(session.session_name)}</h6>
                            <p class="mb-1 text-muted">${this.escapeHtml(session.course_name)}</p>
                            <div class="session-time">${this.formatDateTime(session.start_date)}</div>
                            <div class="session-location">
                                <i class="fas fa-map-marker-alt"></i> ${this.escapeHtml(session.location || 'TBD')}
                            </div>
                        </div>
                        <div class="text-end">
                            <span class="badge course-status status-${session.status}">${session.status}</span>
                            <br>
                            <small class="text-muted">${session.registered_count || 0} registered</small>
                        </div>
                    </div>
                </div>
            `;
            container.append(sessionHtml);
        });
    }

    /**
     * Update certification status
     */
    updateCertificationStatus(certifications) {
        const container = $('#certificationStatus');
        container.empty();

        if (!certifications || certifications.length === 0) {
            container.html('<div class="text-center text-muted">No certification data available</div>');
            return;
        }

        // Group certifications by name
        const groupedCerts = {};
        certifications.forEach(cert => {
            if (!groupedCerts[cert.certification_name]) {
                groupedCerts[cert.certification_name] = {};
            }
            groupedCerts[cert.certification_name][cert.status] = {
                count: parseInt(cert.count),
                expiring_soon: parseInt(cert.expiring_soon)
            };
        });

        Object.keys(groupedCerts).forEach(certName => {
            const certData = groupedCerts[certName];
            const totalActive = (certData.active?.count || 0);
            const expiringCount = (certData.active?.expiring_soon || 0);
            
            const certHtml = `
                <div class="certification-card cert-${certData.active ? 'active' : 'expired'}">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="mb-1">${this.escapeHtml(certName)}</h6>
                            <small class="text-muted">
                                Active: ${totalActive} 
                                ${expiringCount > 0 ? `| Expiring Soon: ${expiringCount}` : ''}
                            </small>
                        </div>
                        ${expiringCount > 0 ? '<i class="fas fa-exclamation-triangle text-warning"></i>' : ''}
                    </div>
                </div>
            `;
            container.append(certHtml);
        });
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
        const colors = ['#0dcaf0', '#6f42c1', '#198754', '#ffc107', '#fd7e14'];

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
                    backgroundColor: '#0dcaf0',
                    borderColor: '#0891b2',
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
     * Create line chart
     */
    createLineChart(canvasId, title, data) {
        const ctx = document.getElementById(canvasId);
        if (!ctx) return;

        // Destroy existing chart
        if (this.charts[canvasId]) {
            this.charts[canvasId].destroy();
        }

        const labels = Object.keys(data);
        const values = Object.values(data);

        this.charts[canvasId] = new Chart(ctx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Enrollments',
                    data: values,
                    borderColor: '#0dcaf0',
                    backgroundColor: 'rgba(13, 202, 240, 0.1)',
                    borderWidth: 2,
                    fill: true,
                    tension: 0.4
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
            'enrolled': 'enrollment-enrolled',
            'in-progress': 'enrollment-in-progress',
            'completed': 'enrollment-completed',
            'failed': 'enrollment-failed',
            'active': 'status-active',
            'scheduled': 'status-active'
        };
        return statusClasses[status] || 'enrollment-enrolled';
    }

    getTypeIcon(type) {
        const typeIcons = {
            'enrollment': '<i class="fas fa-user-plus text-info"></i>',
            'completion': '<i class="fas fa-graduation-cap text-success"></i>',
            'session': '<i class="fas fa-chalkboard-teacher text-primary"></i>'
        };
        return typeIcons[type] || '<i class="fas fa-info-circle"></i>';
    }

    formatDateTime(dateString) {
        const date = new Date(dateString);
        return date.toLocaleString();
    }

    formatDate(dateString) {
        if (!dateString) return 'TBD';
        const date = new Date(dateString);
        return date.toLocaleDateString();
    }

    escapeHtml(text) {
        if (!text) return '';
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
    window.trainingDashboard = new TrainingDashboard();
});