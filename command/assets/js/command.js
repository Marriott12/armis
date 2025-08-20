/**
 * ARMIS Command Module JavaScript
 * Dynamic functionality for command operations
 */

class CommandModule {
    constructor() {
        this.apiBaseUrl = '/Armis2/command/api.php';
        this.refreshInterval = 30000; // 30 seconds
        this.init();
    }
    
    init() {
        this.setupEventListeners();
        this.startRealTimeUpdates();
        this.loadDashboardData();
    }
    
    setupEventListeners() {
        // Mission form submission
        document.addEventListener('submit', (e) => {
            if (e.target.id === 'missionForm') {
                e.preventDefault();
                this.submitMissionForm(e.target);
            }
        });
        
        // Mission status updates
        document.addEventListener('change', (e) => {
            if (e.target.classList.contains('mission-status-select')) {
                this.updateMissionStatus(e.target);
            }
        });
        
        // Real-time refresh button
        document.addEventListener('click', (e) => {
            if (e.target.id === 'refreshDashboard') {
                this.loadDashboardData();
            }
        });
        
        // Search functionality
        const searchInput = document.getElementById('missionSearch');
        if (searchInput) {
            searchInput.addEventListener('input', this.debounce(() => {
                this.searchMissions(searchInput.value);
            }, 300));
        }
    }
    
    async loadDashboardData() {
        try {
            this.showLoading('dashboard-stats');
            
            const response = await fetch(`${this.apiBaseUrl}?endpoint=dashboard`);
            const data = await response.json();
            
            if (data.success) {
                this.updateDashboardStats(data.data.stats);
                this.updateActiveMissions(data.data.active_missions);
                this.updateCommunications(data.data.recent_communications);
            }
            
            this.hideLoading('dashboard-stats');
        } catch (error) {
            console.error('Failed to load dashboard data:', error);
            this.showError('Failed to load dashboard data');
        }
    }
    
    updateDashboardStats(stats) {
        const elements = {
            'active-missions': stats.active_missions,
            'personnel-ready': stats.personnel_ready_percent + '%',
            'alerts': stats.alerts,
            'mission-status': stats.mission_status
        };
        
        Object.entries(elements).forEach(([id, value]) => {
            const element = document.getElementById(id);
            if (element) {
                element.textContent = value;
                
                // Add status color for mission status
                if (id === 'mission-status') {
                    element.className = stats.mission_status === 'GREEN' ? 'text-success' : 'text-danger';
                }
            }
        });
    }
    
    updateActiveMissions(missions) {
        const container = document.getElementById('active-missions-list');
        if (!container) return;
        
        container.innerHTML = missions.map(mission => `
            <div class="mission-item mb-2 p-2 border rounded">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="mb-1">${this.escapeHtml(mission.mission_name)}</h6>
                        <small class="text-muted">${this.escapeHtml(mission.description)}</small>
                    </div>
                    <span class="badge bg-${this.getPriorityClass(mission.priority)}">
                        ${mission.priority}
                    </span>
                </div>
            </div>
        `).join('');
    }
    
    updateCommunications(communications) {
        const container = document.getElementById('communications-list');
        if (!container) return;
        
        container.innerHTML = communications.map(comm => `
            <div class="communication-item mb-2 p-2 border-left border-primary">
                <div class="d-flex justify-content-between">
                    <strong>${this.escapeHtml(comm.type)}</strong>
                    <small class="text-muted">${this.formatDateTime(comm.timestamp)}</small>
                </div>
                <p class="mb-0 small">${this.escapeHtml(comm.message)}</p>
            </div>
        `).join('');
    }
    
    async submitMissionForm(form) {
        const formData = new FormData(form);
        const data = Object.fromEntries(formData.entries());
        
        try {
            const response = await fetch(`${this.apiBaseUrl}?endpoint=missions`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(data)
            });
            
            const result = await response.json();
            
            if (result.success) {
                this.showSuccess('Mission created successfully');
                form.reset();
                this.loadDashboardData();
            } else {
                this.showError(result.error || 'Failed to create mission');
            }
        } catch (error) {
            console.error('Error submitting mission:', error);
            this.showError('Failed to create mission');
        }
    }
    
    async updateMissionStatus(select) {
        const missionId = select.dataset.missionId;
        const newStatus = select.value;
        
        try {
            const response = await fetch(`${this.apiBaseUrl}?endpoint=mission&id=${missionId}`, {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ status: newStatus })
            });
            
            const result = await response.json();
            
            if (result.success) {
                this.showSuccess('Mission status updated');
                this.loadDashboardData();
            } else {
                this.showError(result.error || 'Failed to update mission status');
            }
        } catch (error) {
            console.error('Error updating mission status:', error);
            this.showError('Failed to update mission status');
        }
    }
    
    async searchMissions(query) {
        try {
            const response = await fetch(`${this.apiBaseUrl}?endpoint=missions&search=${encodeURIComponent(query)}`);
            const data = await response.json();
            
            if (data.success) {
                this.displaySearchResults(data.data);
            }
        } catch (error) {
            console.error('Search error:', error);
        }
    }
    
    displaySearchResults(missions) {
        const container = document.getElementById('search-results');
        if (!container) return;
        
        if (missions.length === 0) {
            container.innerHTML = '<p class="text-muted">No missions found</p>';
            return;
        }
        
        container.innerHTML = missions.map(mission => `
            <div class="card mb-2">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="card-title mb-1">${this.escapeHtml(mission.mission_name)}</h6>
                            <p class="card-text small">${this.escapeHtml(mission.description)}</p>
                        </div>
                        <div class="text-end">
                            <span class="badge bg-${this.getStatusClass(mission.status)} mb-1">
                                ${mission.status}
                            </span><br>
                            <span class="badge bg-${this.getPriorityClass(mission.priority)}">
                                ${mission.priority}
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        `).join('');
    }
    
    startRealTimeUpdates() {
        setInterval(() => {
            this.loadDashboardData();
        }, this.refreshInterval);
    }
    
    // Utility functions
    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    
    formatDateTime(datetime) {
        return new Date(datetime).toLocaleString();
    }
    
    getPriorityClass(priority) {
        const classes = {
            'Low': 'secondary',
            'Medium': 'info',
            'High': 'warning',
            'Critical': 'danger'
        };
        return classes[priority] || 'secondary';
    }
    
    getStatusClass(status) {
        const classes = {
            'Planning': 'secondary',
            'Active': 'success',
            'On Hold': 'warning',
            'Completed': 'primary',
            'Cancelled': 'danger'
        };
        return classes[status] || 'secondary';
    }
    
    showLoading(elementId) {
        const element = document.getElementById(elementId);
        if (element) {
            element.style.opacity = '0.5';
        }
    }
    
    hideLoading(elementId) {
        const element = document.getElementById(elementId);
        if (element) {
            element.style.opacity = '1';
        }
    }
    
    showSuccess(message) {
        this.showToast(message, 'success');
    }
    
    showError(message) {
        this.showToast(message, 'danger');
    }
    
    showToast(message, type) {
        // Create toast element
        const toast = document.createElement('div');
        toast.className = `alert alert-${type} alert-dismissible fade show position-fixed`;
        toast.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px;';
        toast.innerHTML = `
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        `;
        
        document.body.appendChild(toast);
        
        // Auto remove after 5 seconds
        setTimeout(() => {
            if (toast.parentNode) {
                toast.parentNode.removeChild(toast);
            }
        }, 5000);
    }
    
    debounce(func, wait) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    }
}

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
    window.commandModule = new CommandModule();
});