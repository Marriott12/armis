/**
 * ARMIS Admin Module JavaScript
 * Enhanced functionality for system administration
 */

(function() {
    'use strict';
    
    // Global admin configuration
    const ARMIS_ADMIN = {
        config: {
            refreshInterval: 60000, // 1 minute
            apiBaseUrl: 'api/',
            healthCheckInterval: 300000, // 5 minutes
            alertThresholds: {
                cpu: 80,
                memory: 85,
                disk: 90,
                database: 75
            }
        },
        
        // State management
        state: {
            systemHealth: 'UNKNOWN',
            performanceData: [],
            alerts: [],
            lastUpdate: null
        },
        
        // Utility functions
        utils: {
            formatBytes: function(bytes, decimals = 2) {
                if (bytes === 0) return '0 Bytes';
                const k = 1024;
                const dm = decimals < 0 ? 0 : decimals;
                const sizes = ['Bytes', 'KB', 'MB', 'GB', 'TB'];
                const i = Math.floor(Math.log(bytes) / Math.log(k));
                return parseFloat((bytes / Math.pow(k, i)).toFixed(dm)) + ' ' + sizes[i];
            },
            
            formatUptime: function(days) {
                if (days < 1) return 'Less than 1 day';
                if (days < 7) return `${days} day${days > 1 ? 's' : ''}`;
                if (days < 30) return `${Math.floor(days / 7)} week${Math.floor(days / 7) > 1 ? 's' : ''}`;
                return `${Math.floor(days / 30)} month${Math.floor(days / 30) > 1 ? 's' : ''}`;
            },
            
            getHealthStatusColor: function(status) {
                switch (status) {
                    case 'HEALTHY': return 'success';
                    case 'WARNING': return 'warning';
                    case 'ERROR': return 'danger';
                    default: return 'secondary';
                }
            },
            
            showToast: function(message, type = 'info', duration = 5000) {
                const toast = document.createElement('div');
                toast.className = `toast align-items-center text-white bg-${type} border-0`;
                toast.setAttribute('role', 'alert');
                toast.innerHTML = `
                    <div class="d-flex">
                        <div class="toast-body">${message}</div>
                        <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
                    </div>
                `;
                
                let toastContainer = document.querySelector('.toast-container');
                if (!toastContainer) {
                    toastContainer = document.createElement('div');
                    toastContainer.className = 'toast-container position-fixed bottom-0 end-0 p-3';
                    document.body.appendChild(toastContainer);
                }
                
                toastContainer.appendChild(toast);
                
                const bsToast = new bootstrap.Toast(toast, { delay: duration });
                bsToast.show();
                
                toast.addEventListener('hidden.bs.toast', function() {
                    toast.remove();
                });
            },
            
            confirmAction: function(message, callback) {
                if (confirm(message)) {
                    callback();
                }
            }
        },
        
        // API functions
        api: {
            request: async function(endpoint, options = {}) {
                const defaultOptions = {
                    method: 'GET',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                };
                
                const config = { ...defaultOptions, ...options };
                
                try {
                    const response = await fetch(ARMIS_ADMIN.config.apiBaseUrl + endpoint, config);
                    
                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }
                    
                    const data = await response.json();
                    return data;
                } catch (error) {
                    console.error('API request failed:', error);
                    ARMIS_ADMIN.utils.showToast('Request failed: ' + error.message, 'danger');
                    throw error;
                }
            },
            
            getSystemHealth: function() {
                return this.request('system_health.php');
            },
            
            getPerformanceMetrics: function() {
                return this.request('performance_metrics.php');
            },
            
            updateSystemConfig: function(key, value) {
                return this.request('update_config.php', {
                    method: 'POST',
                    body: JSON.stringify({ key: key, value: value })
                });
            },
            
            createBackup: function() {
                return this.request('create_backup.php', {
                    method: 'POST'
                });
            },
            
            getAuditLogs: function(filters = {}) {
                const params = new URLSearchParams(filters);
                return this.request(`audit_logs.php?${params}`);
            }
        },
        
        // Dashboard management
        dashboard: {
            init: function() {
                this.updateSystemStatus();
                this.startHealthMonitoring();
                this.setupEventListeners();
            },
            
            updateSystemStatus: async function() {
                try {
                    const healthData = await ARMIS_ADMIN.api.getSystemHealth();
                    const performanceData = await ARMIS_ADMIN.api.getPerformanceMetrics();
                    
                    this.updateHealthCards(healthData);
                    this.updatePerformanceMetrics(performanceData);
                    this.checkAlerts(performanceData);
                    
                    ARMIS_ADMIN.state.lastUpdate = new Date();
                    this.updateLastUpdateTime();
                } catch (error) {
                    console.error('Failed to update system status:', error);
                    ARMIS_ADMIN.utils.showToast('Failed to update system status', 'danger');
                }
            },
            
            updateHealthCards: function(healthData) {
                // Update overall health status
                const healthCard = document.querySelector('.health-card');
                if (healthCard) {
                    const status = healthData.overall_status;
                    const colorClass = ARMIS_ADMIN.utils.getHealthStatusColor(status);
                    
                    healthCard.className = `card border-0 shadow-sm health-card bg-${colorClass}`;
                    healthCard.querySelector('h5').textContent = status;
                }
                
                // Update individual health indicators
                if (healthData.database_status) {
                    this.updateHealthIndicator('database', healthData.database_status);
                }
                if (healthData.file_system_status) {
                    this.updateHealthIndicator('filesystem', healthData.file_system_status);
                }
            },
            
            updateHealthIndicator: function(component, status) {
                const indicator = document.querySelector(`[data-health="${component}"]`);
                if (indicator) {
                    const colorClass = ARMIS_ADMIN.utils.getHealthStatusColor(status);
                    indicator.className = `badge bg-${colorClass}`;
                    indicator.textContent = status;
                }
            },
            
            updatePerformanceMetrics: function(data) {
                // Update progress bars
                this.updateProgressBar('cpu-usage', data.cpu_usage);
                this.updateProgressBar('memory-usage', data.memory_usage);
                this.updateProgressBar('disk-usage', data.disk_usage);
                this.updateProgressBar('database-load', data.database_load);
                
                // Update metric values
                this.updateMetricValue('cpu-value', data.cpu_usage + '%');
                this.updateMetricValue('memory-value', data.memory_usage + '%');
                this.updateMetricValue('disk-value', data.disk_usage + '%');
                this.updateMetricValue('database-value', data.database_load + '%');
                
                // Update uptime
                this.updateMetricValue('uptime-value', ARMIS_ADMIN.utils.formatUptime(data.uptime_days));
            },
            
            updateProgressBar: function(id, value) {
                const progressBar = document.getElementById(id);
                if (progressBar) {
                    progressBar.style.width = value + '%';
                    progressBar.setAttribute('aria-valuenow', value);
                    
                    // Update color based on value
                    let colorClass = 'bg-success';
                    if (value > 80) colorClass = 'bg-danger';
                    else if (value > 60) colorClass = 'bg-warning';
                    
                    progressBar.className = `progress-bar ${colorClass}`;
                }
            },
            
            updateMetricValue: function(id, value) {
                const element = document.getElementById(id);
                if (element) {
                    element.textContent = value;
                }
            },
            
            checkAlerts: function(data) {
                const alerts = [];
                
                if (data.cpu_usage > ARMIS_ADMIN.config.alertThresholds.cpu) {
                    alerts.push({
                        type: 'warning',
                        message: `High CPU usage: ${data.cpu_usage}%`
                    });
                }
                
                if (data.memory_usage > ARMIS_ADMIN.config.alertThresholds.memory) {
                    alerts.push({
                        type: 'warning',
                        message: `High memory usage: ${data.memory_usage}%`
                    });
                }
                
                if (data.disk_usage > ARMIS_ADMIN.config.alertThresholds.disk) {
                    alerts.push({
                        type: 'danger',
                        message: `High disk usage: ${data.disk_usage}%`
                    });
                }
                
                this.displayAlerts(alerts);
            },
            
            displayAlerts: function(alerts) {
                const alertContainer = document.getElementById('systemAlerts');
                if (!alertContainer) return;
                
                if (alerts.length === 0) {
                    alertContainer.innerHTML = '';
                    return;
                }
                
                alertContainer.innerHTML = alerts.map(alert => `
                    <div class="alert alert-${alert.type} alert-dismissible fade show">
                        <i class="fas fa-exclamation-triangle"></i>
                        ${alert.message}
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                `).join('');
            },
            
            updateLastUpdateTime: function() {
                const updateTimeElement = document.getElementById('lastUpdate');
                if (updateTimeElement && ARMIS_ADMIN.state.lastUpdate) {
                    updateTimeElement.textContent = 'Last updated: ' + 
                        ARMIS_ADMIN.state.lastUpdate.toLocaleTimeString();
                }
            },
            
            startHealthMonitoring: function() {
                // Initial update
                this.updateSystemStatus();
                
                // Regular updates
                setInterval(() => {
                    this.updateSystemStatus();
                }, ARMIS_ADMIN.config.healthCheckInterval);
            },
            
            setupEventListeners: function() {
                // Refresh button
                const refreshBtn = document.getElementById('refreshDashboard');
                if (refreshBtn) {
                    refreshBtn.addEventListener('click', () => {
                        this.updateSystemStatus();
                    });
                }
                
                // Performance metric cards click handlers
                document.addEventListener('click', function(e) {
                    if (e.target.closest('.metric-card')) {
                        const card = e.target.closest('.metric-card');
                        const metric = card.dataset.metric;
                        if (metric) {
                            ARMIS_ADMIN.performance.showMetricDetails(metric);
                        }
                    }
                });
            }
        },
        
        // Performance monitoring
        performance: {
            showMetricDetails: function(metric) {
                // Show detailed performance information in a modal
                this.openPerformanceModal(metric);
            },
            
            openPerformanceModal: function(metric) {
                // Create or update performance details modal
                console.log('Show performance details for:', metric);
                // Implementation would show detailed charts and history
            }
        },
        
        // Backup management
        backup: {
            create: async function() {
                try {
                    ARMIS_ADMIN.utils.showToast('Creating backup...', 'info');
                    
                    const result = await ARMIS_ADMIN.api.createBackup();
                    
                    if (result.success) {
                        ARMIS_ADMIN.utils.showToast(
                            `Backup created successfully (${ARMIS_ADMIN.utils.formatBytes(result.size)})`,
                            'success'
                        );
                        
                        // Refresh database status
                        ARMIS_ADMIN.dashboard.updateSystemStatus();
                    } else {
                        throw new Error(result.error || 'Backup creation failed');
                    }
                } catch (error) {
                    console.error('Backup creation failed:', error);
                    ARMIS_ADMIN.utils.showToast('Backup creation failed: ' + error.message, 'danger');
                }
            },
            
            restore: function(backupFile) {
                ARMIS_ADMIN.utils.confirmAction(
                    'Are you sure you want to restore from this backup? This will overwrite current data.',
                    () => {
                        this.performRestore(backupFile);
                    }
                );
            },
            
            performRestore: async function(backupFile) {
                try {
                    ARMIS_ADMIN.utils.showToast('Restoring from backup...', 'warning');
                    
                    const result = await ARMIS_ADMIN.api.request('restore_backup.php', {
                        method: 'POST',
                        body: JSON.stringify({ backup_file: backupFile })
                    });
                    
                    if (result.success) {
                        ARMIS_ADMIN.utils.showToast('Backup restored successfully', 'success');
                        
                        // Refresh the page after successful restore
                        setTimeout(() => {
                            window.location.reload();
                        }, 2000);
                    } else {
                        throw new Error(result.error || 'Restore failed');
                    }
                } catch (error) {
                    console.error('Backup restore failed:', error);
                    ARMIS_ADMIN.utils.showToast('Backup restore failed: ' + error.message, 'danger');
                }
            }
        },
        
        // Configuration management
        config: {
            update: async function(key, value) {
                try {
                    const result = await ARMIS_ADMIN.api.updateSystemConfig(key, value);
                    
                    if (result.success) {
                        ARMIS_ADMIN.utils.showToast('Configuration updated successfully', 'success');
                        return true;
                    } else {
                        throw new Error(result.error || 'Configuration update failed');
                    }
                } catch (error) {
                    console.error('Configuration update failed:', error);
                    ARMIS_ADMIN.utils.showToast('Configuration update failed: ' + error.message, 'danger');
                    return false;
                }
            },
            
            setupConfigForm: function() {
                const configForm = document.getElementById('configForm');
                if (configForm) {
                    configForm.addEventListener('submit', this.handleConfigSubmit.bind(this));
                }
            },
            
            handleConfigSubmit: async function(e) {
                e.preventDefault();
                
                const form = e.target;
                const formData = new FormData(form);
                
                const updates = [];
                for (const [key, value] of formData.entries()) {
                    updates.push(this.update(key, value));
                }
                
                try {
                    await Promise.all(updates);
                    ARMIS_ADMIN.utils.showToast('All configurations updated successfully', 'success');
                } catch (error) {
                    console.error('Some configuration updates failed:', error);
                }
            }
        },
        
        // Audit logs management
        audit: {
            loadLogs: async function(filters = {}) {
                try {
                    const data = await ARMIS_ADMIN.api.getAuditLogs(filters);
                    
                    if (data.success) {
                        this.displayLogs(data.logs);
                        this.updatePagination(data.pagination);
                    }
                } catch (error) {
                    console.error('Failed to load audit logs:', error);
                    ARMIS_ADMIN.utils.showToast('Failed to load audit logs', 'danger');
                }
            },
            
            displayLogs: function(logs) {
                const logsContainer = document.getElementById('auditLogsTable');
                if (!logsContainer) return;
                
                const tbody = logsContainer.querySelector('tbody');
                if (!tbody) return;
                
                tbody.innerHTML = logs.map(log => `
                    <tr>
                        <td>${log.created_at}</td>
                        <td><span class="badge bg-${this.getSeverityColor(log.severity)}">${log.severity}</span></td>
                        <td>${log.module}</td>
                        <td>${log.action}</td>
                        <td>${log.user_name || 'System'}</td>
                        <td>${log.ip_address}</td>
                        <td>
                            <button class="btn btn-sm btn-outline-primary" onclick="viewLogDetails(${log.id})">
                                <i class="fas fa-eye"></i>
                            </button>
                        </td>
                    </tr>
                `).join('');
            },
            
            getSeverityColor: function(severity) {
                switch (severity) {
                    case 'CRITICAL': return 'danger';
                    case 'HIGH': return 'warning';
                    case 'MEDIUM': return 'info';
                    case 'LOW': return 'secondary';
                    default: return 'secondary';
                }
            },
            
            updatePagination: function(pagination) {
                // Update pagination controls
                const paginationContainer = document.getElementById('auditLogsPagination');
                if (paginationContainer && pagination) {
                    // Implementation for pagination controls
                    console.log('Update pagination:', pagination);
                }
            }
        },
        
        // Main initialization
        init: function() {
            console.log('ARMIS Admin module initialized');
            
            // Initialize dashboard
            this.dashboard.init();
            
            // Initialize configuration management
            this.config.setupConfigForm();
            
            // Setup global event listeners
            this.setupGlobalEventListeners();
        },
        
        setupGlobalEventListeners: function() {
            // Handle window visibility changes to pause/resume monitoring
            document.addEventListener('visibilitychange', function() {
                if (document.hidden) {
                    console.log('Admin dashboard hidden - reducing update frequency');
                } else {
                    console.log('Admin dashboard visible - resuming normal updates');
                    ARMIS_ADMIN.dashboard.updateSystemStatus();
                }
            });
            
            // Handle keyboard shortcuts
            document.addEventListener('keydown', function(e) {
                // Ctrl/Cmd + R to refresh dashboard
                if ((e.ctrlKey || e.metaKey) && e.key === 'r') {
                    e.preventDefault();
                    ARMIS_ADMIN.dashboard.updateSystemStatus();
                }
            });
        }
    };
    
    // Global functions
    window.viewLogDetails = function(logId) {
        // Show detailed log information in a modal
        console.log('View log details for:', logId);
    };
    
    window.createBackup = function() {
        ARMIS_ADMIN.backup.create();
    };
    
    window.refreshDashboard = function() {
        ARMIS_ADMIN.dashboard.updateSystemStatus();
    };
    
    // Initialize when DOM is ready
    document.addEventListener('DOMContentLoaded', function() {
        ARMIS_ADMIN.init();
    });
    
    // Expose globally
    window.ARMIS_ADMIN = ARMIS_ADMIN;
    
})();