/**
 * Enhanced Dashboard Management
 * 
 * Provides advanced functionality for ARMIS dashboard including:
 * - Widget state persistence
 * - Real-time notifications
 * - Data filtering and pagination
 * - Dashboard exports
 * - Advanced analytics integration
 */

// Dashboard Manager Class
class DashboardManager {
    constructor(options = {}) {
        // Configuration
        this.config = {
            apiEndpoint: 'dashboard_api.php',
            refreshInterval: 60000, // 1 minute
            notificationInterval: 30000, // 30 seconds
            csrfToken: document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
            ...options
        };
        
        // State
        this.widgetStates = {};
        this.lastNotificationCheck = new Date().toISOString();
        this.refreshTimers = {};
        this.notificationCount = 0;
        
        // Initialize
        this.init();
    }
    
    /**
     * Initialize dashboard manager
     */
    init() {
        this.setupEventListeners();
        this.initializeWidgets();
        // Notification polling disabled
        // this.startNotificationPolling();
        
        // Log initialization
        console.log('Dashboard Manager initialized');
    }
    
    /**
     * Set up event listeners
     */
    setupEventListeners() {
        // Widget control buttons
        document.querySelectorAll('.widget-refresh').forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.preventDefault();
                const widgetId = e.currentTarget.closest('.dashboard-widget').dataset.widgetId;
                this.refreshWidget(widgetId);
            });
        });
        
        // Widget configuration
        document.querySelectorAll('.widget-settings').forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.preventDefault();
                const widget = e.currentTarget.closest('.dashboard-widget');
                this.openWidgetSettings(widget.dataset.widgetId);
            });
        });
        
        // Export buttons
        document.querySelectorAll('.export-button').forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.preventDefault();
                const format = e.currentTarget.dataset.format || 'csv';
                const type = e.currentTarget.dataset.type || 'personnel';
                this.exportData(type, format);
            });
        });
        
        // Notification panel toggle - DISABLED
        // const notificationToggle = document.getElementById('notification-toggle');
        // if (notificationToggle) {
        //     notificationToggle.addEventListener('click', (e) => {
        //         e.preventDefault();
        //         this.toggleNotificationPanel();
        //     });
        // }
        
        // Advanced analytics tabs
        document.querySelectorAll('.analytics-tab').forEach(tab => {
            tab.addEventListener('click', (e) => {
                e.preventDefault();
                this.switchAnalyticsTab(e.currentTarget.dataset.tab);
            });
        });
    }
    
    /**
     * Initialize dashboard widgets
     */
    initializeWidgets() {
        document.querySelectorAll('.dashboard-widget').forEach(widget => {
            const widgetId = widget.dataset.widgetId;
            
            if (widgetId) {
                // Load saved state
                this.loadWidgetState(widgetId).then(state => {
                    if (state && state.success) {
                        this.applyWidgetState(widgetId, state.state);
                    }
                    
                    // Set up auto-refresh if needed
                    if (widget.dataset.autoRefresh === 'true') {
                        const interval = parseInt(widget.dataset.refreshInterval) || this.config.refreshInterval;
                        this.setupWidgetRefresh(widgetId, interval);
                    }
                });
            }
        });
    }
    
    /**
     * Load widget state from server
     */
    loadWidgetState(widgetId) {
        return fetch(`${this.config.apiEndpoint}?action=widget_state&widget_id=${widgetId}`)
            .then(response => response.json())
            .catch(error => {
                console.error('Error loading widget state:', error);
                return { success: false };
            });
    }
    
    /**
     * Apply widget state to the DOM
     */
    applyWidgetState(widgetId, state) {
        const widget = document.querySelector(`.dashboard-widget[data-widget-id="${widgetId}"]`);
        if (!widget || !state) return;
        
        // Apply position if grid layout is used
        if (state.position) {
            widget.style.gridArea = state.position;
        }
        
        // Apply visibility
        if (state.visible === 0) {
            widget.classList.add('d-none');
        } else {
            widget.classList.remove('d-none');
        }
        
        // Apply widget-specific state if available
        if (state.state) {
            try {
                const widgetState = JSON.parse(state.state);
                this.widgetStates[widgetId] = widgetState;
                
                // Apply specific widget settings
                this.applyWidgetSpecificState(widget, widgetState);
            } catch (e) {
                console.error('Error parsing widget state:', e);
            }
        }
    }
    
    /**
     * Apply widget-specific state settings
     */
    applyWidgetSpecificState(widget, state) {
        // Chart specific settings
        const chart = widget.querySelector('canvas[id]');
        if (chart && chart.chart && state.chartType) {
            chart.chart.config.type = state.chartType;
            chart.chart.update();
        }
        
        // Table specific settings
        const table = widget.querySelector('table');
        if (table && state.pageSize) {
            // Apply table pagination settings
            this.updateTablePagination(table, state.pageSize);
        }
        
        // Widget specific display options
        if (state.displayOptions) {
            // Toggle specific elements based on display options
            Object.entries(state.displayOptions).forEach(([key, value]) => {
                const element = widget.querySelector(`[data-display-option="${key}"]`);
                if (element) {
                    element.style.display = value ? '' : 'none';
                }
            });
        }
    }
    
    /**
     * Save widget state to server
     */
    saveWidgetState(widgetId, state) {
        const formData = new FormData();
        formData.append('action', 'save');
        formData.append('widget_id', widgetId);
        formData.append('state', JSON.stringify(state));
        formData.append('csrf_token', this.config.csrfToken);
        
        // Add position and visibility if available
        const widget = document.querySelector(`.dashboard-widget[data-widget-id="${widgetId}"]`);
        if (widget) {
            const position = widget.style.gridArea || '';
            const visible = widget.classList.contains('d-none') ? 0 : 1;
            
            formData.append('position', position);
            formData.append('visible', visible);
        }
        
        return fetch(this.config.apiEndpoint + '?action=widget_state', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .catch(error => {
            console.error('Error saving widget state:', error);
            return { success: false };
        });
    }
    
    /**
     * Reset widget to default state
     */
    resetWidgetState(widgetId) {
        const formData = new FormData();
        formData.append('action', 'reset');
        formData.append('widget_id', widgetId);
        formData.append('csrf_token', this.config.csrfToken);
        
        return fetch(this.config.apiEndpoint + '?action=widget_state', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Reload the widget
                this.refreshWidget(widgetId);
            }
            return data;
        })
        .catch(error => {
            console.error('Error resetting widget state:', error);
            return { success: false };
        });
    }
    
    /**
     * Open widget settings modal
     */
    openWidgetSettings(widgetId) {
        const widget = document.querySelector(`.dashboard-widget[data-widget-id="${widgetId}"]`);
        if (!widget) return;
        
        // Get current state
        const state = this.widgetStates[widgetId] || {};
        
        // Create modal content based on widget type
        const widgetType = widget.dataset.widgetType || 'default';
        let settingsHtml = '';
        
        switch (widgetType) {
            case 'chart':
                settingsHtml = this.getChartSettingsHtml(widgetId, state);
                break;
            case 'table':
                settingsHtml = this.getTableSettingsHtml(widgetId, state);
                break;
            case 'kpi':
                settingsHtml = this.getKpiSettingsHtml(widgetId, state);
                break;
            default:
                settingsHtml = this.getDefaultSettingsHtml(widgetId, state);
        }
        
        // Create modal
        const modal = document.createElement('div');
        modal.className = 'modal fade';
        modal.id = `widget-settings-${widgetId}`;
        modal.innerHTML = `
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Widget Settings: ${widget.querySelector('.widget-title')?.textContent || widgetId}</h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        ${settingsHtml}
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                        <button type="button" class="btn btn-danger widget-reset-btn">Reset to Default</button>
                        <button type="button" class="btn btn-primary widget-save-btn">Save Changes</button>
                    </div>
                </div>
            </div>
        `;
        
        // Add to document
        document.body.appendChild(modal);
        
        // Initialize modal
        const modalElement = $(`#widget-settings-${widgetId}`);
        modalElement.modal('show');
        
        // Event listeners
        modalElement.find('.widget-save-btn').on('click', () => {
            this.saveWidgetSettingsFromModal(widgetId, modalElement);
            modalElement.modal('hide');
        });
        
        modalElement.find('.widget-reset-btn').on('click', () => {
            if (confirm('Are you sure you want to reset this widget to default settings?')) {
                this.resetWidgetState(widgetId);
                modalElement.modal('hide');
            }
        });
        
        // Clean up when modal is hidden
        modalElement.on('hidden.bs.modal', function () {
            document.body.removeChild(modal);
        });
    }
    
    /**
     * Generate chart settings HTML
     */
    getChartSettingsHtml(widgetId, state) {
        return `
            <form id="widget-form-${widgetId}">
                <div class="form-group">
                    <label for="chart-type-${widgetId}">Chart Type</label>
                    <select class="form-control" id="chart-type-${widgetId}" name="chartType">
                        <option value="bar" ${state.chartType === 'bar' ? 'selected' : ''}>Bar Chart</option>
                        <option value="line" ${state.chartType === 'line' ? 'selected' : ''}>Line Chart</option>
                        <option value="pie" ${state.chartType === 'pie' ? 'selected' : ''}>Pie Chart</option>
                        <option value="doughnut" ${state.chartType === 'doughnut' ? 'selected' : ''}>Doughnut Chart</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="chart-legend-${widgetId}">Show Legend</label>
                    <div class="custom-control custom-switch">
                        <input type="checkbox" class="custom-control-input" id="chart-legend-${widgetId}" name="showLegend" 
                            ${state.showLegend !== false ? 'checked' : ''}>
                        <label class="custom-control-label" for="chart-legend-${widgetId}">Display chart legend</label>
                    </div>
                </div>
                <div class="form-group">
                    <label>Display Options</label>
                    <div class="custom-control custom-switch">
                        <input type="checkbox" class="custom-control-input" id="show-values-${widgetId}" name="displayOptions.showValues" 
                            ${state.displayOptions?.showValues !== false ? 'checked' : ''}>
                        <label class="custom-control-label" for="show-values-${widgetId}">Show data values</label>
                    </div>
                    <div class="custom-control custom-switch">
                        <input type="checkbox" class="custom-control-input" id="show-grid-${widgetId}" name="displayOptions.showGrid" 
                            ${state.displayOptions?.showGrid !== false ? 'checked' : ''}>
                        <label class="custom-control-label" for="show-grid-${widgetId}">Show grid lines</label>
                    </div>
                </div>
                <div class="form-group">
                    <label for="refresh-interval-${widgetId}">Auto-Refresh Interval (seconds)</label>
                    <input type="number" class="form-control" id="refresh-interval-${widgetId}" name="refreshInterval" 
                        value="${state.refreshInterval || 60}" min="30" max="600">
                </div>
            </form>
        `;
    }
    
    /**
     * Generate table settings HTML
     */
    getTableSettingsHtml(widgetId, state) {
        return `
            <form id="widget-form-${widgetId}">
                <div class="form-group">
                    <label for="page-size-${widgetId}">Rows Per Page</label>
                    <select class="form-control" id="page-size-${widgetId}" name="pageSize">
                        <option value="5" ${state.pageSize === 5 ? 'selected' : ''}>5 rows</option>
                        <option value="10" ${state.pageSize === 10 ? 'selected' : ''}>10 rows</option>
                        <option value="25" ${state.pageSize === 25 ? 'selected' : ''}>25 rows</option>
                        <option value="50" ${state.pageSize === 50 ? 'selected' : ''}>50 rows</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Display Options</label>
                    <div class="custom-control custom-switch">
                        <input type="checkbox" class="custom-control-input" id="show-search-${widgetId}" name="displayOptions.showSearch" 
                            ${state.displayOptions?.showSearch !== false ? 'checked' : ''}>
                        <label class="custom-control-label" for="show-search-${widgetId}">Show search box</label>
                    </div>
                    <div class="custom-control custom-switch">
                        <input type="checkbox" class="custom-control-input" id="show-pagination-${widgetId}" name="displayOptions.showPagination" 
                            ${state.displayOptions?.showPagination !== false ? 'checked' : ''}>
                        <label class="custom-control-label" for="show-pagination-${widgetId}">Show pagination controls</label>
                    </div>
                </div>
                <div class="form-group">
                    <label for="refresh-interval-${widgetId}">Auto-Refresh Interval (seconds)</label>
                    <input type="number" class="form-control" id="refresh-interval-${widgetId}" name="refreshInterval" 
                        value="${state.refreshInterval || 60}" min="30" max="600">
                </div>
            </form>
        `;
    }
    
    /**
     * Generate KPI settings HTML
     */
    getKpiSettingsHtml(widgetId, state) {
        return `
            <form id="widget-form-${widgetId}">
                <div class="form-group">
                    <label>Display Options</label>
                    <div class="custom-control custom-switch">
                        <input type="checkbox" class="custom-control-input" id="show-change-${widgetId}" name="displayOptions.showChange" 
                            ${state.displayOptions?.showChange !== false ? 'checked' : ''}>
                        <label class="custom-control-label" for="show-change-${widgetId}">Show change indicator</label>
                    </div>
                    <div class="custom-control custom-switch">
                        <input type="checkbox" class="custom-control-input" id="show-trend-${widgetId}" name="displayOptions.showTrend" 
                            ${state.displayOptions?.showTrend !== false ? 'checked' : ''}>
                        <label class="custom-control-label" for="show-trend-${widgetId}">Show trend sparkline</label>
                    </div>
                </div>
                <div class="form-group">
                    <label for="refresh-interval-${widgetId}">Auto-Refresh Interval (seconds)</label>
                    <input type="number" class="form-control" id="refresh-interval-${widgetId}" name="refreshInterval" 
                        value="${state.refreshInterval || 60}" min="30" max="600">
                </div>
            </form>
        `;
    }
    
    /**
     * Generate default settings HTML
     */
    getDefaultSettingsHtml(widgetId, state) {
        return `
            <form id="widget-form-${widgetId}">
                <div class="form-group">
                    <label for="refresh-interval-${widgetId}">Auto-Refresh Interval (seconds)</label>
                    <input type="number" class="form-control" id="refresh-interval-${widgetId}" name="refreshInterval" 
                        value="${state.refreshInterval || 60}" min="30" max="600">
                </div>
                <div class="form-group">
                    <label>Visibility</label>
                    <div class="custom-control custom-switch">
                        <input type="checkbox" class="custom-control-input" id="widget-visible-${widgetId}" name="visible" 
                            ${state.visible !== false ? 'checked' : ''}>
                        <label class="custom-control-label" for="widget-visible-${widgetId}">Widget visible</label>
                    </div>
                </div>
            </form>
        `;
    }
    
    /**
     * Save widget settings from modal form
     */
    saveWidgetSettingsFromModal(widgetId, modalElement) {
        const form = modalElement.find(`#widget-form-${widgetId}`)[0];
        if (!form) return;
        
        const formData = new FormData(form);
        const state = {};
        
        // Convert form data to nested object
        for (let [key, value] of formData.entries()) {
            if (key.includes('.')) {
                // Handle nested properties (e.g., displayOptions.showValues)
                const [parent, child] = key.split('.');
                state[parent] = state[parent] || {};
                state[parent][child] = this.normalizeValue(value);
            } else {
                state[key] = this.normalizeValue(value);
            }
        }
        
        // Handle checkboxes that might be unchecked (not included in formData)
        if (form.querySelector('[name="displayOptions.showValues"]') && !state.displayOptions?.showValues) {
            state.displayOptions = state.displayOptions || {};
            state.displayOptions.showValues = false;
        }
        
        // Save the state
        this.widgetStates[widgetId] = state;
        this.saveWidgetState(widgetId, state).then(response => {
            if (response.success) {
                // Apply the new state
                this.applyWidgetState(widgetId, { state: JSON.stringify(state) });
                
                // Update refresh interval if changed
                if (state.refreshInterval) {
                    this.setupWidgetRefresh(widgetId, state.refreshInterval * 1000);
                }
            }
        });
    }
    
    /**
     * Convert form values to appropriate types
     */
    normalizeValue(value) {
        if (value === 'on') return true;
        if (value === 'off') return false;
        if (value === '') return null;
        if (!isNaN(value) && value.trim() !== '') return Number(value);
        return value;
    }
    
    /**
     * Set up automatic widget refresh
     */
    setupWidgetRefresh(widgetId, interval) {
        // Clear existing timer if any
        if (this.refreshTimers[widgetId]) {
            clearInterval(this.refreshTimers[widgetId]);
        }
        
        // Set up new timer
        this.refreshTimers[widgetId] = setInterval(() => {
            this.refreshWidget(widgetId);
        }, interval);
    }
    
    /**
     * Refresh a specific widget
     */
    refreshWidget(widgetId) {
        const widget = document.querySelector(`.dashboard-widget[data-widget-id="${widgetId}"]`);
        if (!widget) return;
        
        // Add loading indicator
        widget.classList.add('widget-loading');
        
        // Get refresh endpoint from widget data attribute
        const endpoint = widget.dataset.refreshEndpoint || '';
        if (!endpoint) {
            console.error(`No refresh endpoint specified for widget ${widgetId}`);
            widget.classList.remove('widget-loading');
            return;
        }
        
        // Fetch updated data
        fetch(endpoint)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    this.updateWidgetContent(widgetId, data.data);
                } else {
                    console.error(`Error refreshing widget ${widgetId}:`, data.message);
                }
            })
            .catch(error => {
                console.error(`Error refreshing widget ${widgetId}:`, error);
            })
            .finally(() => {
                widget.classList.remove('widget-loading');
            });
    }
    
    /**
     * Update widget content with new data
     */
    updateWidgetContent(widgetId, data) {
        const widget = document.querySelector(`.dashboard-widget[data-widget-id="${widgetId}"]`);
        if (!widget) return;
        
        const widgetType = widget.dataset.widgetType || 'default';
        
        switch (widgetType) {
            case 'chart':
                this.updateChartWidget(widget, data);
                break;
            case 'table':
                this.updateTableWidget(widget, data);
                break;
            case 'kpi':
                this.updateKpiWidget(widget, data);
                break;
            default:
                // For custom widgets, dispatch an event with the data
                const event = new CustomEvent('widget-data-update', {
                    detail: { widgetId, data }
                });
                widget.dispatchEvent(event);
        }
    }
    
    /**
     * Update chart widget with new data
     */
    updateChartWidget(widget, data) {
        const canvas = widget.querySelector('canvas[id]');
        if (!canvas || !canvas.chart) return;
        
        const chart = canvas.chart;
        
        // Update chart data
        chart.data.labels = data.labels || [];
        
        // Update datasets
        if (data.datasets && Array.isArray(data.datasets)) {
            chart.data.datasets = data.datasets;
        }
        
        // Update chart type if specified in widget state
        const widgetId = widget.dataset.widgetId;
        const state = this.widgetStates[widgetId] || {};
        
        if (state.chartType && chart.config.type !== state.chartType) {
            chart.config.type = state.chartType;
        }
        
        // Update chart
        chart.update();
        
        // Update last refreshed timestamp
        this.updateLastRefreshed(widget);
    }
    
    /**
     * Update table widget with new data
     */
    updateTableWidget(widget, data) {
        const table = widget.querySelector('table tbody');
        if (!table) return;
        
        // Clear existing rows
        table.innerHTML = '';
        
        // Add new rows
        if (Array.isArray(data)) {
            data.forEach(row => {
                const tr = document.createElement('tr');
                
                // Create cells based on object properties
                Object.values(row).forEach(cellData => {
                    const td = document.createElement('td');
                    td.textContent = cellData;
                    tr.appendChild(td);
                });
                
                table.appendChild(tr);
            });
        }
        
        // Update pagination if applicable
        const widgetId = widget.dataset.widgetId;
        const state = this.widgetStates[widgetId] || {};
        
        if (state.pageSize) {
            this.updateTablePagination(table, state.pageSize);
        }
        
        // Update last refreshed timestamp
        this.updateLastRefreshed(widget);
    }
    
    /**
     * Update KPI widget with new data
     */
    updateKpiWidget(widget, data) {
        // Update value
        const valueElement = widget.querySelector('.kpi-value');
        if (valueElement && data.value !== undefined) {
            valueElement.textContent = data.value;
        }
        
        // Update change indicator
        const changeElement = widget.querySelector('.kpi-change');
        if (changeElement && data.change !== undefined) {
            const changeValue = parseFloat(data.change);
            
            // Update change value
            changeElement.textContent = `${changeValue > 0 ? '+' : ''}${data.change}%`;
            
            // Update change indicator classes
            changeElement.classList.remove('text-success', 'text-danger', 'text-warning');
            
            if (changeValue > 0) {
                changeElement.classList.add('text-success');
            } else if (changeValue < 0) {
                changeElement.classList.add('text-danger');
            } else {
                changeElement.classList.add('text-warning');
            }
        }
        
        // Update label if provided
        const labelElement = widget.querySelector('.kpi-label');
        if (labelElement && data.label) {
            labelElement.textContent = data.label;
        }
        
        // Update trend sparkline if available
        const sparklineElement = widget.querySelector('.kpi-sparkline');
        if (sparklineElement && data.trend && Array.isArray(data.trend)) {
            // This would typically use a mini chart library or sparkline plugin
            // For simplicity, we'll just update a data attribute
            sparklineElement.dataset.trend = JSON.stringify(data.trend);
            
            // If you're using a library like jQuery Sparklines, you would call:
            // $(sparklineElement).sparkline(data.trend, { type: 'line' });
        }
        
        // Update last refreshed timestamp
        this.updateLastRefreshed(widget);
    }
    
    /**
     * Update table pagination
     */
    updateTablePagination(table, pageSize) {
        // Implement table pagination logic here
        // This would typically involve showing/hiding rows based on current page
        console.log(`Table pagination updated with page size: ${pageSize}`);
    }
    
    /**
     * Update last refreshed timestamp
     */
    updateLastRefreshed(widget) {
        const timestampElement = widget.querySelector('.widget-last-refreshed');
        if (timestampElement) {
            const now = new Date();
            timestampElement.textContent = `Last updated: ${now.toLocaleTimeString()}`;
        }
    }
    
    /**
     * Start polling for notifications - DISABLED
     */
    startNotificationPolling() {
        // Notifications completely disabled
        console.log('Notification polling disabled');
        return;
    }
    
    /**
     * Check for new notifications - DISABLED
     */
    checkNotifications() {
        // Notifications completely disabled
        console.log('Notification checking disabled');
        return;
    }
    
    /**
     * Process new notifications
     */
    processNotifications(data) {
        // Update last check time
        this.lastNotificationCheck = new Date().toISOString();
        
        // Update notification badge
        this.updateNotificationBadge(data.unread);
        
        // Add new notifications to panel
        if (data.notifications && data.notifications.length > 0) {
            this.updateNotificationPanel(data.notifications);
        }
    }
    
    /**
     * Update notification badge counter
     */
    updateNotificationBadge(count) {
        const badge = document.querySelector('#notification-toggle .notification-badge');
        if (badge) {
            if (count > 0) {
                badge.textContent = count;
                badge.classList.remove('d-none');
            } else {
                badge.classList.add('d-none');
            }
        }
        
        // Update notification count
        this.notificationCount = count;
    }
    
    /**
     * Update notification panel content - DISABLED
     */
    updateNotificationPanel(notifications) {
        // Notifications completely disabled
        console.log('Notification panel update disabled');
        return;
            notificationElement.dataset.notificationId = notification.id;
            
            // Format time
            const time = new Date(notification.created_at);
            const timeStr = time.toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});
            const dateStr = time.toLocaleDateString();
            
            // Create notification content
            notificationElement.innerHTML = `
                <div class="notification-content">
                    <div class="notification-message">${notification.message}</div>
                    <div class="notification-time">${dateStr} ${timeStr}</div>
                </div>
                <div class="notification-actions">
                    <button class="btn btn-sm btn-link mark-read" title="Mark as read">
                        <i class="fas fa-check"></i>
                    </button>
                </div>
            `;
            
            // Add click handler to mark as read
            notificationElement.querySelector('.mark-read').addEventListener('click', (e) => {
                e.preventDefault();
                e.stopPropagation();
                this.markNotificationRead(notification.id);
            });
            
            // Add click handler for the notification
            notificationElement.addEventListener('click', () => {
                // Mark as read when clicked
                this.markNotificationRead(notification.id);
                
                // Follow link if available
                if (notification.link) {
                    window.location.href = notification.link;
                }
            });
            
            // Add to container at the top
            if (container.firstChild) {
                container.insertBefore(notificationElement, container.firstChild);
            } else {
                container.appendChild(notificationElement);
            }
        });
        
        // Update empty state message
        const emptyMessage = panel.querySelector('.empty-notifications');
        if (emptyMessage) {
            if (container.children.length > 0) {
                emptyMessage.classList.add('d-none');
            } else {
                emptyMessage.classList.remove('d-none');
            }
        }
    }
    
    /**
     * Toggle notification panel visibility - DISABLED
     */
    toggleNotificationPanel() {
        // Notifications completely disabled
        console.log('Notification panel toggle disabled');
        return;
    }
    
    /**
     * Mark notification as read - DISABLED
     */
    markNotificationRead(notificationId) {
        // Notifications completely disabled
        console.log('Mark notification read disabled');
        return;
                this.updateNotificationBadge(this.notificationCount);
            }
        })
        .catch(error => {
            console.error('Error marking notification as read:', error);
        });
    }
    
    /**
     * Switch to a different analytics tab
     */
    switchAnalyticsTab(tabId) {
        // Hide all tabs
        document.querySelectorAll('.analytics-content').forEach(tab => {
            tab.classList.remove('active');
        });
        
        // Deactivate all tab buttons
        document.querySelectorAll('.analytics-tab').forEach(tab => {
            tab.classList.remove('active');
        });
        
        // Show selected tab
        const selectedTab = document.getElementById(`analytics-${tabId}`);
        if (selectedTab) {
            selectedTab.classList.add('active');
        }
        
        // Activate selected tab button
        const selectedButton = document.querySelector(`.analytics-tab[data-tab="${tabId}"]`);
        if (selectedButton) {
            selectedButton.classList.add('active');
        }
        
        // Load data if needed
        this.loadAnalyticsData(tabId);
    }
    
    /**
     * Load data for analytics tab
     */
    loadAnalyticsData(tabId) {
        const tabContent = document.getElementById(`analytics-${tabId}`);
        if (!tabContent || tabContent.dataset.loaded === 'true') return;
        
        // Show loading state
        tabContent.innerHTML = '<div class="text-center p-4"><div class="spinner-border text-primary" role="status"></div><p class="mt-2">Loading analytics data...</p></div>';
        
        // Determine endpoint based on tab ID
        let endpoint = '';
        switch (tabId) {
            case 'predictive':
                endpoint = `${this.config.apiEndpoint}?action=get_predictive_attrition`;
                break;
            case 'training':
                endpoint = `${this.config.apiEndpoint}?action=get_training_completion`;
                break;
            case 'cohort':
                endpoint = `${this.config.apiEndpoint}?action=get_cohort_analysis`;
                break;
            default:
                console.error(`Unknown analytics tab: ${tabId}`);
                return;
        }
        
        // Fetch data
        fetch(endpoint)
            .then(response => response.json())
            .then(data => {
                if (data.success && data.data) {
                    // Render tab content
                    this.renderAnalyticsTab(tabId, data.data, tabContent);
                    
                    // Mark as loaded
                    tabContent.dataset.loaded = 'true';
                } else {
                    tabContent.innerHTML = '<div class="alert alert-danger">Failed to load analytics data</div>';
                }
            })
            .catch(error => {
                console.error(`Error loading analytics data for ${tabId}:`, error);
                tabContent.innerHTML = '<div class="alert alert-danger">Error loading analytics data</div>';
            });
    }
    
    /**
     * Render analytics tab content
     */
    renderAnalyticsTab(tabId, data, tabElement) {
        switch (tabId) {
            case 'predictive':
                this.renderPredictiveAnalytics(data, tabElement);
                break;
            case 'training':
                this.renderTrainingAnalytics(data, tabElement);
                break;
            case 'cohort':
                this.renderCohortAnalytics(data, tabElement);
                break;
        }
    }
    
    /**
     * Render predictive analytics content
     */
    renderPredictiveAnalytics(data, tabElement) {
        // Implementation would depend on your specific data structure
        // This is a placeholder implementation
        let html = `
            <div class="row">
                <div class="col-md-12">
                    <h4>Predictive Attrition Analysis</h4>
                    <p class="text-muted">Forecasting potential personnel losses based on historical data and patterns</p>
                </div>
            </div>
            <div class="row">
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-body">
                            <h5 class="card-title">Attrition Risk by Department</h5>
                            <canvas id="attrition-risk-chart" height="250"></canvas>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-body">
                            <h5 class="card-title">Personnel at Risk</h5>
                            <div class="table-responsive">
                                <table class="table table-sm">
                                    <thead>
                                        <tr>
                                            <th>Name</th>
                                            <th>Position</th>
                                            <th>Risk Level</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
        `;
        
        // Add table rows
        if (data.personnel && data.personnel.length > 0) {
            data.personnel.forEach(person => {
                let riskClass = '';
                if (person.risk_level >= 75) riskClass = 'danger';
                else if (person.risk_level >= 50) riskClass = 'warning';
                else if (person.risk_level >= 25) riskClass = 'info';
                else riskClass = 'success';
                
                html += `
                    <tr>
                        <td>${person.name}</td>
                        <td>${person.position}</td>
                        <td><span class="badge badge-${riskClass}">${person.risk_level}%</span></td>
                        <td>
                            <button class="btn btn-sm btn-outline-primary">View</button>
                        </td>
                    </tr>
                `;
            });
        } else {
            html += '<tr><td colspan="4" class="text-center">No personnel at risk detected</td></tr>';
        }
        
        html += `
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="row mt-4">
                <div class="col-md-12">
                    <div class="card">
                        <div class="card-body">
                            <h5 class="card-title">Attrition Factors</h5>
                            <div id="attrition-factors-chart"></div>
                        </div>
                    </div>
                </div>
            </div>
        `;
        
        tabElement.innerHTML = html;
        
        // Initialize charts
        if (data.department_risk) {
            const ctx = document.getElementById('attrition-risk-chart').getContext('2d');
            new Chart(ctx, {
                type: 'horizontalBar',
                data: {
                    labels: data.department_risk.map(d => d.department),
                    datasets: [{
                        label: 'Attrition Risk %',
                        data: data.department_risk.map(d => d.risk_level),
                        backgroundColor: 'rgba(255, 99, 132, 0.7)',
                        borderColor: 'rgba(255, 99, 132, 1)',
                        borderWidth: 1
                    }]
                },
                options: {
                    scales: {
                        xAxes: [{
                            ticks: {
                                beginAtZero: true,
                                max: 100
                            }
                        }]
                    }
                }
            });
        }
    }
    
    /**
     * Render training analytics content
     */
    renderTrainingAnalytics(data, tabElement) {
        // Implementation would depend on your specific data structure
        // This is a placeholder implementation
        let html = `
            <div class="row">
                <div class="col-md-12">
                    <h4>Training Completion Analysis</h4>
                    <p class="text-muted">Monitoring training progress and certification status across departments</p>
                </div>
            </div>
            <div class="row">
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-body">
                            <h5 class="card-title">Overall Training Completion</h5>
                            <canvas id="training-completion-chart" height="250"></canvas>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-body">
                            <h5 class="card-title">Training Status by Department</h5>
                            <canvas id="training-department-chart" height="250"></canvas>
                        </div>
                    </div>
                </div>
            </div>
            <div class="row mt-4">
                <div class="col-md-12">
                    <div class="card">
                        <div class="card-body">
                            <h5 class="card-title">Upcoming Training Deadlines</h5>
                            <div class="table-responsive">
                                <table class="table table-sm">
                                    <thead>
                                        <tr>
                                            <th>Training Program</th>
                                            <th>Personnel Count</th>
                                            <th>Deadline</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
        `;
        
        // Add table rows
        if (data.upcoming_deadlines && data.upcoming_deadlines.length > 0) {
            data.upcoming_deadlines.forEach(item => {
                let statusClass = '';
                if (item.days_remaining <= 7) statusClass = 'danger';
                else if (item.days_remaining <= 14) statusClass = 'warning';
                else statusClass = 'success';
                
                html += `
                    <tr>
                        <td>${item.program}</td>
                        <td>${item.personnel_count}</td>
                        <td>${item.deadline}</td>
                        <td><span class="badge badge-${statusClass}">${item.days_remaining} days remaining</span></td>
                    </tr>
                `;
            });
        } else {
            html += '<tr><td colspan="4" class="text-center">No upcoming training deadlines</td></tr>';
        }
        
        html += `
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        `;
        
        tabElement.innerHTML = html;
        
        // Initialize charts
        if (data.overall_completion) {
            const ctx = document.getElementById('training-completion-chart').getContext('2d');
            new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: ['Completed', 'In Progress', 'Not Started'],
                    datasets: [{
                        data: [
                            data.overall_completion.completed,
                            data.overall_completion.in_progress,
                            data.overall_completion.not_started
                        ],
                        backgroundColor: [
                            'rgba(75, 192, 192, 0.7)',
                            'rgba(255, 206, 86, 0.7)',
                            'rgba(255, 99, 132, 0.7)'
                        ],
                        borderColor: [
                            'rgba(75, 192, 192, 1)',
                            'rgba(255, 206, 86, 1)',
                            'rgba(255, 99, 132, 1)'
                        ],
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    legend: {
                        position: 'bottom'
                    }
                }
            });
        }
        
        if (data.department_completion) {
            const ctx = document.getElementById('training-department-chart').getContext('2d');
            new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: data.department_completion.map(d => d.department),
                    datasets: [{
                        label: 'Completion Rate %',
                        data: data.department_completion.map(d => d.completion_rate),
                        backgroundColor: 'rgba(54, 162, 235, 0.7)',
                        borderColor: 'rgba(54, 162, 235, 1)',
                        borderWidth: 1
                    }]
                },
                options: {
                    scales: {
                        yAxes: [{
                            ticks: {
                                beginAtZero: true,
                                max: 100
                            }
                        }]
                    }
                }
            });
        }
    }
    
    /**
     * Render cohort analytics content
     */
    renderCohortAnalytics(data, tabElement) {
        // Implementation would depend on your specific data structure
        // This is a placeholder implementation
        let html = `
            <div class="row">
                <div class="col-md-12">
                    <h4>Cohort Analysis</h4>
                    <p class="text-muted">Analysis of personnel retention by enlistment year</p>
                </div>
            </div>
            <div class="row">
                <div class="col-md-8">
                    <div class="card">
                        <div class="card-body">
                            <h5 class="card-title">Retention Rate by Cohort Year</h5>
                            <canvas id="cohort-retention-chart" height="300"></canvas>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card">
                        <div class="card-body">
                            <h5 class="card-title">Average Retention</h5>
                            <div class="text-center mb-4">
                                <div class="display-4 font-weight-bold">
                                    ${data.average_retention ? data.average_retention.toFixed(1) + '%' : 'N/A'}
                                </div>
                                <div class="text-muted">Overall retention rate</div>
                            </div>
                            <div class="progress" style="height: 25px;">
                                <div class="progress-bar bg-success" role="progressbar" 
                                    style="width: ${data.average_retention || 0}%;" 
                                    aria-valuenow="${data.average_retention || 0}" 
                                    aria-valuemin="0" 
                                    aria-valuemax="100">
                                    ${data.average_retention ? data.average_retention.toFixed(1) + '%' : '0%'}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="row mt-4">
                <div class="col-md-12">
                    <div class="card">
                        <div class="card-body">
                            <h5 class="card-title">Detailed Cohort Data</h5>
                            <div class="table-responsive">
                                <table class="table table-striped">
                                    <thead>
                                        <tr>
                                            <th>Cohort Year</th>
                                            <th>Total Recruits</th>
                                            <th>Still Active</th>
                                            <th>Retention Rate</th>
                                        </tr>
                                    </thead>
                                    <tbody>
        `;
        
        // Add table rows
        if (Array.isArray(data)) {
            data.forEach(cohort => {
                let retentionClass = '';
                const retentionRate = parseFloat(cohort.retention_rate);
                
                if (retentionRate >= 75) retentionClass = 'success';
                else if (retentionRate >= 50) retentionClass = 'info';
                else if (retentionRate >= 25) retentionClass = 'warning';
                else retentionClass = 'danger';
                
                html += `
                    <tr>
                        <td>${cohort.cohort_year}</td>
                        <td>${cohort.total_recruits}</td>
                        <td>${cohort.still_active}</td>
                        <td><span class="badge badge-${retentionClass}">${cohort.retention_rate}%</span></td>
                    </tr>
                `;
            });
        } else {
            html += '<tr><td colspan="4" class="text-center">No cohort data available</td></tr>';
        }
        
        html += `
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        `;
        
        tabElement.innerHTML = html;
        
        // Initialize charts
        if (Array.isArray(data)) {
            const ctx = document.getElementById('cohort-retention-chart').getContext('2d');
            new Chart(ctx, {
                type: 'line',
                data: {
                    labels: data.map(c => c.cohort_year),
                    datasets: [{
                        label: 'Retention Rate %',
                        data: data.map(c => c.retention_rate),
                        backgroundColor: 'rgba(75, 192, 192, 0.2)',
                        borderColor: 'rgba(75, 192, 192, 1)',
                        borderWidth: 2,
                        pointBackgroundColor: 'rgba(75, 192, 192, 1)',
                        pointBorderColor: '#fff',
                        pointRadius: 4
                    }]
                },
                options: {
                    scales: {
                        yAxes: [{
                            ticks: {
                                beginAtZero: true,
                                max: 100
                            }
                        }]
                    }
                }
            });
        }
    }
    
    /**
     * Export dashboard data
     */
    exportData(type, format) {
        // Show export modal
        const modal = document.createElement('div');
        modal.className = 'modal fade';
        modal.id = 'export-modal';
        modal.innerHTML = `
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Export Dashboard Data</h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        <form id="export-form">
                            <div class="form-group">
                                <label for="export-type">Data to Export</label>
                                <select class="form-control" id="export-type" name="type">
                                    <option value="personnel" ${type === 'personnel' ? 'selected' : ''}>Personnel Data</option>
                                    <option value="activities" ${type === 'activities' ? 'selected' : ''}>Activity Log</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="export-format">Export Format</label>
                                <select class="form-control" id="export-format" name="format">
                                    <option value="csv" ${format === 'csv' ? 'selected' : ''}>CSV</option>
                                    <option value="json" ${format === 'json' ? 'selected' : ''}>JSON</option>
                                    <option value="excel" ${format === 'excel' ? 'selected' : ''}>Excel</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <div class="custom-control custom-checkbox">
                                    <input type="checkbox" class="custom-control-input" id="export-download" name="download" value="1" checked>
                                    <label class="custom-control-label" for="export-download">Download file</label>
                                </div>
                            </div>
                            <input type="hidden" name="csrf_token" value="${this.config.csrfToken}">
                        </form>
                        <div id="export-status" class="mt-3 d-none">
                            <div class="progress">
                                <div class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar" style="width: 100%"></div>
                            </div>
                            <p class="text-center mt-2">Generating export...</p>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                        <button type="button" class="btn btn-primary" id="export-submit">Export</button>
                    </div>
                </div>
            </div>
        `;
        
        // Add to document
        document.body.appendChild(modal);
        
        // Initialize modal
        const modalElement = $('#export-modal');
        modalElement.modal('show');
        
        // Submit handler
        document.getElementById('export-submit').addEventListener('click', () => {
            const form = document.getElementById('export-form');
            const formData = new FormData(form);
            
            // Show progress
            document.getElementById('export-form').classList.add('d-none');
            document.getElementById('export-status').classList.remove('d-none');
            document.getElementById('export-submit').disabled = true;
            
            // Submit export request
            fetch(this.config.apiEndpoint + '?action=export_data', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // If download requested and URL available
                    if (formData.get('download') === '1' && data.data && data.data.download_url) {
                        window.location.href = data.data.download_url;
                    }
                    
                    // Show success message
                    document.getElementById('export-status').innerHTML = `
                        <div class="alert alert-success">
                            <i class="fas fa-check-circle mr-2"></i> Export completed successfully!
                        </div>
                    `;
                    
                    // Close modal after delay
                    setTimeout(() => {
                        modalElement.modal('hide');
                    }, 2000);
                } else {
                    // Show error
                    document.getElementById('export-status').innerHTML = `
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-circle mr-2"></i> Export failed: ${data.message || 'Unknown error'}
                        </div>
                    `;
                    
                    // Re-enable form
                    document.getElementById('export-form').classList.remove('d-none');
                    document.getElementById('export-submit').disabled = false;
                }
            })
            .catch(error => {
                console.error('Export error:', error);
                
                // Show error
                document.getElementById('export-status').innerHTML = `
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-circle mr-2"></i> Export failed: Network error
                    </div>
                `;
                
                // Re-enable form
                document.getElementById('export-form').classList.remove('d-none');
                document.getElementById('export-submit').disabled = false;
            });
        });
        
        // Clean up when modal is hidden
        modalElement.on('hidden.bs.modal', function () {
            document.body.removeChild(modal);
        });
    }
}

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    // Initialize dashboard manager
    window.dashboardManager = new DashboardManager({
        // Optional configuration here
    });
});
