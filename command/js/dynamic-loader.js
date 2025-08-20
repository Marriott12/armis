/**
 * Dynamic Command Module JavaScript
 * Handles dynamic loading and rendering of command configurations
 */

class CommandDynamicLoader {
    constructor(options = {}) {
        this.apiEndpoint = options.apiEndpoint || 'api.php';
        this.autoRefresh = options.autoRefresh || false;
        this.refreshInterval = options.refreshInterval || 30000;
        this.cache = new Map();
        this.cacheTimeout = options.cacheTimeout || 300000; // 5 minutes
        this.refreshTimer = null;
        
        this.init();
    }
    
    /**
     * Initialize the dynamic loader
     */
    init() {
        console.log('Initializing Command Dynamic Loader...');
        this.loadSettings().then(() => {
            this.loadDynamicContent();
            if (this.autoRefresh) {
                this.startAutoRefresh();
            }
        }).catch(error => {
            console.error('Failed to initialize dynamic loader:', error);
            this.showError('Failed to load dynamic configuration');
        });
    }
    
    /**
     * Load settings from configuration
     */
    async loadSettings() {
        try {
            const settings = await this.apiCall('get_settings');
            this.autoRefresh = settings.autoRefresh || this.autoRefresh;
            this.refreshInterval = settings.refreshInterval || this.refreshInterval;
            this.cacheTimeout = (typeof settings.cacheTimeout === 'number' && !isNaN(settings.cacheTimeout) ? settings.cacheTimeout * 1000 : this.cacheTimeout);
            console.log('Settings loaded:', settings);
        } catch (error) {
            console.warn('Failed to load settings, using defaults:', error);
        }
    }
    
    /**
     * Load all dynamic content
     */
    async loadDynamicContent() {
        try {
            await Promise.all([
                this.loadDynamicNavigation(),
                this.loadDynamicDashboardModules(),
                this.loadDynamicOverviewStats()
            ]);
            console.log('All dynamic content loaded successfully');
        } catch (error) {
            console.error('Error loading dynamic content:', error);
        }
    }
    
    /**
     * Load dynamic navigation
     */
    async loadDynamicNavigation() {
        try {
            const navigation = await this.apiCall('get_navigation');
            this.renderNavigation(navigation);
        } catch (error) {
            console.error('Failed to load navigation:', error);
        }
    }
    
    /**
     * Load dynamic dashboard modules
     */
    async loadDynamicDashboardModules() {
        try {
            const modules = await this.apiCall('get_dashboard_modules');
            this.renderDashboardModules(modules);
        } catch (error) {
            console.error('Failed to load dashboard modules:', error);
        }
    }
    
    /**
     * Load dynamic overview statistics
     */
    async loadDynamicOverviewStats() {
        try {
            const stats = await this.apiCall('get_overview_stats');
            await this.renderOverviewStats(stats);
        } catch (error) {
            console.error('Failed to load overview stats:', error);
        }
    }
    
    /**
     * Render navigation items
     */
    renderNavigation(navigation) {
        // This would update the sidebar navigation
        // For now, just log the navigation items
        console.log('Navigation items:', navigation);
        
        // Update page title if needed
        const pageTitle = document.querySelector('.section-title');
        if (pageTitle) {
            // Could update based on current page from navigation config
        }
    }
    
    /**
     * Render dashboard modules
     */
    renderDashboardModules(modules) {
        const container = document.querySelector('.row.g-4');
        if (!container) {
            console.warn('Dashboard modules container not found');
            return;
        }
        
        // Clear existing modules (except overview section)
        const moduleCards = container.querySelectorAll('.col-md-6.col-lg-3');
        moduleCards.forEach(card => {
            // Only remove if it's a module card, not overview stats
            if (card.querySelector('.module-card')) {
                card.remove();
            }
        });
        
        // Render new modules
        modules.forEach(module => {
            const moduleHtml = this.createModuleCard(module);
            container.insertAdjacentHTML('beforeend', moduleHtml);
        });
        
        console.log(`Rendered ${modules.length} dashboard modules`);
    }
    
    /**
     * Create HTML for a dashboard module card
     */
    createModuleCard(module) {
        return `
            <div class="col-md-6 col-lg-3">
                <div class="card module-card" data-module-id="${module.id}">
                    <div class="card-body text-center">
                        <div class="mb-3">
                            <i class="fas fa-${module.icon} fa-2x ${module.iconColor || 'text-primary'}"></i>
                        </div>
                        <h5 class="card-title">${this.escapeHtml(module.title)}</h5>
                        <p class="card-text">${this.escapeHtml(module.description)}</p>
                        <a href="${module.url}" class="btn ${module.buttonClass || 'btn-primary'}">
                            ${this.escapeHtml(module.buttonText || 'Open')}
                        </a>
                    </div>
                </div>
            </div>
        `;
    }
    
    /**
     * Render overview statistics
     */
    async renderOverviewStats(statsConfig) {
        const overviewSection = document.querySelector('.row.mt-5');
        if (!overviewSection) {
            console.warn('Overview stats section not found');
            return;
        }
        
        // Find the stats container (col-md-6 col-lg-3 elements after the title)
        const statsContainer = overviewSection.querySelector('.col-12').parentNode;
        
        // Remove existing stat cards
        const existingStats = statsContainer.querySelectorAll('.col-md-6.col-lg-3');
        existingStats.forEach(stat => stat.remove());
        
        // Render new stats
        for (const statConfig of statsConfig) {
            try {
                const statData = await this.loadStatData(statConfig);
                const statHtml = this.createStatCard(statConfig, statData);
                statsContainer.insertAdjacentHTML('beforeend', statHtml);
            } catch (error) {
                console.error(`Failed to load stat ${statConfig.id}:`, error);
                // Use fallback value
                const statHtml = this.createStatCard(statConfig, { value: statConfig.fallbackValue });
                statsContainer.insertAdjacentHTML('beforeend', statHtml);
            }
        }
        
        console.log(`Rendered ${statsConfig.length} overview stats`);
    }
    
    /**
     * Load data for a specific statistic
     */
    async loadStatData(statConfig) {
        if (statConfig.dataSource === 'api' && statConfig.endpoint) {
            // Extract type parameter from endpoint
            const urlParams = new URLSearchParams(statConfig.endpoint.split('?')[1] || '');
            const type = urlParams.get('type') || statConfig.id;
            
            return await this.apiCall('get_stats_data', { type });
        }
        
        // Return fallback value if no API source
        return { value: statConfig.fallbackValue };
    }
    
    /**
     * Create HTML for a stat card
     */
    createStatCard(config, data) {
        return `
            <div class="col-md-6 col-lg-3">
                <div class="card ${config.color} text-white" data-stat-id="${config.id}">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h5>${this.escapeHtml(config.title)}</h5>
                                <h2>${this.escapeHtml(data.value)}</h2>
                                ${data.change ? `<small class="text-light">${this.escapeHtml(data.change)}</small>` : ''}
                            </div>
                            <i class="fas fa-${config.icon} fa-2x"></i>
                        </div>
                    </div>
                </div>
            </div>
        `;
    }
    
    /**
     * Make API calls with caching
     */
    async apiCall(action, params = {}) {
        const cacheKey = `${action}_${JSON.stringify(params)}`;
        
        // Check cache first
        if (this.cache.has(cacheKey)) {
            const cached = this.cache.get(cacheKey);
            if (Date.now() - cached.timestamp < this.cacheTimeout) {
                return cached.data;
            }
        }
        
        // Build URL
        let url;
        if (this.isAbsoluteUrl(this.apiEndpoint)) {
            url = new URL(this.apiEndpoint);
        } else {
            url = new URL(this.apiEndpoint, window.location.origin);
        }
        url.searchParams.set('action', action);
        
        // Add additional parameters
        Object.keys(params).forEach(key => {
            url.searchParams.set(key, params[key]);
        });
        
        const response = await fetch(url);
        if (!response.ok) {
            throw new Error(`API call failed: ${response.status} ${response.statusText}`);
        }
        
        const result = await response.json();
        if (!result.success) {
            throw new Error(result.message || 'API call failed');
        }
        
        // Cache the result
        this.cache.set(cacheKey, {
            data: result.data,
            timestamp: Date.now()
        });
        
        return result.data;
    }
    
    /**
     * Start auto-refresh timer
     */
    startAutoRefresh() {
        if (this.refreshTimer) {
            clearInterval(this.refreshTimer);
        }
        
        this.refreshTimer = setInterval(() => {
            console.log('Auto-refreshing dynamic content...');
            this.loadDynamicOverviewStats();
        }, this.refreshInterval);
        
        console.log(`Auto-refresh started with interval: ${this.refreshInterval}ms`);
    }
    
    /**
     * Stop auto-refresh timer
     */
    stopAutoRefresh() {
        if (this.refreshTimer) {
            clearInterval(this.refreshTimer);
            this.refreshTimer = null;
            console.log('Auto-refresh stopped');
        }
    }
    
    /**
     * Clear cache
     */
    clearCache() {
        this.cache.clear();
        console.log('Cache cleared');
    }
    
    /**
     * Show error message to user
     */
    showError(message) {
        console.error(message);
        // Could show a toast or alert to the user
        const errorHtml = `
            <div class="alert alert-warning alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-triangle"></i> ${this.escapeHtml(message)}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        `;
        
        const container = document.querySelector('.main-content');
        if (container) {
            container.insertAdjacentHTML('afterbegin', errorHtml);
        }
    }
    
    /**
     * Escape HTML to prevent XSS
     */
    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    
    /**
     * Refresh all dynamic content
     */
    async refresh() {
        this.clearCache();
        await this.loadDynamicContent();
    }
}

// Initialize the dynamic loader when the page loads
document.addEventListener('DOMContentLoaded', function() {
    if (typeof window.commandDynamicLoader === 'undefined') {
        window.commandDynamicLoader = new CommandDynamicLoader({
            apiEndpoint: 'api.php',
            autoRefresh: true
        });
    }
});

// Export for use in other scripts
window.CommandDynamicLoader = CommandDynamicLoader;