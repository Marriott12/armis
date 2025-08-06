/**
 * ARMIS Enhanced Dashboard JavaScript Framework
 * Provides interactive functionality for admin_branch analytics dashboard
 * Version 2.0 - Enhanced analytics integration
 */

(function() {
    'use strict';

    // ==========================================================================
    // GLOBAL CONFIGURATION
    // ==========================================================================
    
    const ARMIS = {
        config: {
            refreshInterval: 300000, // 5 minutes
            chartColors: [
                '#2563eb', '#16a34a', '#ca8a04', '#dc2626', '#0891b2',
                '#7c3aed', '#db2777', '#059669', '#ea580c', '#0d9488'
            ],
            apiEndpoints: {
                dashboardStats: 'api.php?action=dashboard_stats',
                chartData: 'api.php?action=personnel_chart_data',
                recentActivities: 'api.php?action=recent_activities',
                systemAlerts: 'api.php?action=system_alerts',
                searchStaff: 'api.php?action=search_staff'
            }
        },
        
        // Chart instances storage
        charts: {},
        
        // Auto-refresh timers
        timers: {},
        
        // Cached data
        cache: {}
    };

    // ==========================================================================
    // UTILITY FUNCTIONS
    // ==========================================================================
    
    /**
     * Format numbers with appropriate suffixes
     */
    function formatNumber(num) {
        if (num >= 1000000) {
            return (num / 1000000).toFixed(1) + 'M';
        } else if (num >= 1000) {
            return (num / 1000).toFixed(1) + 'K';
        }
        return num.toString();
    }

    /**
     * Format percentage values
     */
    function formatPercentage(value) {
        return value ? value.toFixed(1) + '%' : '0%';
    }

    /**
     * Show loading spinner
     */
    function showLoading(element) {
        const loader = document.createElement('div');
        loader.className = 'loading-spinner';
        loader.innerHTML = '<div class="spinner-border spinner-border-sm" role="status"></div>';
        
        if (typeof element === 'string') {
            element = document.querySelector(element);
        }
        
        if (element) {
            element.innerHTML = '';
            element.appendChild(loader);
        }
    }

    /**
     * Hide loading spinner and show content
     */
    function hideLoading(element, content) {
        if (typeof element === 'string') {
            element = document.querySelector(element);
        }
        
        if (element) {
            element.innerHTML = content || '';
        }
    }

    /**
     * Make AJAX request with error handling
     */
    function makeRequest(url, options = {}) {
        return fetch(url, {
            method: options.method || 'GET',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                ...options.headers
            },
            body: options.data ? JSON.stringify(options.data) : null
        })
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }
            return response.json();
        })
        .catch(error => {
            console.error('Request failed:', error);
            throw error;
        });
    }

    // ==========================================================================
    // DASHBOARD STATISTICS
    // ==========================================================================
    
    /**
     * Load and display dashboard statistics
     */
    function loadDashboardStats() {
        const statsContainer = document.querySelector('.kpi-grid');
        if (!statsContainer) return;

        showLoading(statsContainer);

        makeRequest(ARMIS.config.apiEndpoints.dashboardStats)
            .then(data => {
                if (data.success) {
                    updateKPICards(data.data);
                    ARMIS.cache.dashboardStats = data.data;
                } else {
                    throw new Error(data.message || 'Failed to load dashboard stats');
                }
            })
            .catch(error => {
                console.error('Error loading dashboard stats:', error);
                showErrorMessage(statsContainer, 'Failed to load dashboard statistics');
            });
    }

    /**
     * Update KPI cards with latest data
     */
    function updateKPICards(stats) {
        const kpiCards = [
            {
                id: 'total-personnel',
                title: 'Total Personnel',
                value: stats.total_personnel || 0,
                icon: 'fas fa-users',
                color: 'primary'
            },
            {
                id: 'active-personnel',
                title: 'Active Personnel', 
                value: stats.active_personnel || 0,
                icon: 'fas fa-user-check',
                color: 'success'
            },
            {
                id: 'deployed-personnel',
                title: 'Deployed',
                value: stats.deployed_personnel || 0,
                icon: 'fas fa-globe',
                color: 'info',
                percentage: stats.deployment_percentage
            },
            {
                id: 'training-completion',
                title: 'Training Completion',
                value: formatPercentage(stats.training_completion),
                icon: 'fas fa-graduation-cap',
                color: 'warning'
            },
            {
                id: 'medical-due',
                title: 'Medical Due',
                value: stats.medical_due || 0,
                icon: 'fas fa-heartbeat',
                color: 'danger'
            },
            {
                id: 'avg-performance',
                title: 'Avg Performance',
                value: stats.avg_performance_score ? stats.avg_performance_score.toFixed(1) : 'N/A',
                icon: 'fas fa-star',
                color: 'primary'
            }
        ];

        const container = document.querySelector('.kpi-grid');
        container.innerHTML = kpiCards.map(card => createKPICard(card)).join('');
        
        // Add animation
        const cards = container.querySelectorAll('.kpi-card');
        cards.forEach((card, index) => {
            card.style.opacity = '0';
            card.style.transform = 'translateY(20px)';
            
            setTimeout(() => {
                card.style.transition = 'all 0.5s ease';
                card.style.opacity = '1';
                card.style.transform = 'translateY(0)';
            }, index * 100);
        });
    }

    /**
     * Create KPI card HTML
     */
    function createKPICard(card) {
        return `
            <div class="kpi-card" id="${card.id}">
                <div class="kpi-header">
                    <div class="kpi-title">${card.title}</div>
                    <div class="kpi-icon ${card.color}">
                        <i class="${card.icon}"></i>
                    </div>
                </div>
                <div class="kpi-value">${formatNumber(card.value)}</div>
                ${card.percentage ? `
                    <div class="kpi-change positive">
                        <i class="fas fa-arrow-up"></i>
                        ${formatPercentage(card.percentage)} deployment rate
                    </div>
                ` : ''}
            </div>
        `;
    }

    // ==========================================================================
    // CHARTS AND VISUALIZATIONS
    // ==========================================================================
    
    /**
     * Initialize all charts
     */
    function initializeCharts() {
        // Personnel distribution chart
        initPersonnelChart();
        
        // Status distribution chart
        initStatusChart();
        
        // Performance trends chart
        initPerformanceChart();
    }

    /**
     * Initialize personnel distribution chart
     */
    function initPersonnelChart() {
        const ctx = document.getElementById('personnelChart');
        if (!ctx) return;

        makeRequest(ARMIS.config.apiEndpoints.chartData + '&type=rank_distribution')
            .then(data => {
                if (data.success && data.data.length > 0) {
                    createPieChart(ctx, {
                        title: 'Personnel by Rank',
                        data: data.data,
                        colors: ARMIS.config.chartColors
                    });
                }
            })
            .catch(error => {
                console.error('Error loading personnel chart:', error);
            });
    }

    /**
     * Initialize status distribution chart
     */
    function initStatusChart() {
        const ctx = document.getElementById('statusChart');
        if (!ctx) return;

        makeRequest(ARMIS.config.apiEndpoints.chartData + '&type=status_distribution')
            .then(data => {
                if (data.success && data.data.length > 0) {
                    createDoughnutChart(ctx, {
                        title: 'Personnel by Status',
                        data: data.data,
                        colors: ['#16a34a', '#ca8a04', '#dc2626', '#6b7280']
                    });
                }
            })
            .catch(error => {
                console.error('Error loading status chart:', error);
            });
    }

    /**
     * Initialize performance trends chart
     */
    function initPerformanceChart() {
        const ctx = document.getElementById('performanceChart');
        if (!ctx) return;

        // Mock data for performance trends
        const monthlyData = {
            labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun'],
            datasets: [{
                label: 'Average Performance Score',
                data: [85, 87, 89, 88, 91, 93],
                borderColor: ARMIS.config.chartColors[0],
                backgroundColor: ARMIS.config.chartColors[0] + '20',
                tension: 0.4,
                fill: true
            }]
        };

        createLineChart(ctx, {
            title: 'Performance Trends',
            data: monthlyData
        });
    }

    /**
     * Create pie chart
     */
    function createPieChart(ctx, options) {
        const chartData = {
            labels: options.data.map(item => item.label),
            datasets: [{
                data: options.data.map(item => item.value),
                backgroundColor: options.colors.slice(0, options.data.length),
                borderWidth: 2,
                borderColor: '#ffffff'
            }]
        };

        ARMIS.charts[ctx.id] = new Chart(ctx, {
            type: 'pie',
            data: chartData,
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            padding: 20,
                            usePointStyle: true
                        }
                    },
                    title: {
                        display: true,
                        text: options.title,
                        font: { size: 16, weight: 'bold' }
                    }
                }
            }
        });
    }

    /**
     * Create doughnut chart
     */
    function createDoughnutChart(ctx, options) {
        const chartData = {
            labels: options.data.map(item => item.label),
            datasets: [{
                data: options.data.map(item => item.value),
                backgroundColor: options.colors.slice(0, options.data.length),
                borderWidth: 3,
                borderColor: '#ffffff'
            }]
        };

        ARMIS.charts[ctx.id] = new Chart(ctx, {
            type: 'doughnut',
            data: chartData,
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            padding: 20,
                            usePointStyle: true
                        }
                    },
                    title: {
                        display: true,
                        text: options.title,
                        font: { size: 16, weight: 'bold' }
                    }
                },
                cutout: '60%'
            }
        });
    }

    /**
     * Create line chart
     */
    function createLineChart(ctx, options) {
        ARMIS.charts[ctx.id] = new Chart(ctx, {
            type: 'line',
            data: options.data,
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    title: {
                        display: true,
                        text: options.title,
                        font: { size: 16, weight: 'bold' }
                    },
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        max: 100,
                        ticks: {
                            callback: function(value) {
                                return value + '%';
                            }
                        }
                    }
                },
                elements: {
                    point: {
                        radius: 6,
                        hoverRadius: 8
                    }
                }
            }
        });
    }

    // ==========================================================================
    // ACTIVITY FEED
    // ==========================================================================
    
    /**
     * Load recent activities
     */
    function loadRecentActivities() {
        const container = document.querySelector('.activity-feed');
        if (!container) return;

        showLoading(container);

        makeRequest(ARMIS.config.apiEndpoints.recentActivities)
            .then(data => {
                if (data.success) {
                    displayActivities(container, data.data);
                } else {
                    throw new Error(data.message || 'Failed to load activities');
                }
            })
            .catch(error => {
                console.error('Error loading activities:', error);
                showErrorMessage(container, 'Failed to load recent activities');
            });
    }

    /**
     * Display activities in the feed
     */
    function displayActivities(container, activities) {
        if (!activities || activities.length === 0) {
            container.innerHTML = '<div class="text-center text-gray-500 p-4">No recent activities</div>';
            return;
        }

        const html = activities.map(activity => `
            <div class="activity-item">
                <div class="activity-icon ${activity.type}">
                    <i class="fas fa-${activity.icon}"></i>
                </div>
                <div class="activity-content">
                    <div class="activity-message">${activity.message}</div>
                    <div class="activity-time">${activity.time_ago}</div>
                </div>
            </div>
        `).join('');

        container.innerHTML = html;
    }

    // ==========================================================================
    // SYSTEM ALERTS
    // ==========================================================================
    
    /**
     * Load and display system alerts
     */
    function loadSystemAlerts() {
        const container = document.querySelector('.alerts-container');
        if (!container) return;

        makeRequest(ARMIS.config.apiEndpoints.systemAlerts)
            .then(data => {
                if (data.success) {
                    displayAlerts(container, data.data);
                } else {
                    throw new Error(data.message || 'Failed to load alerts');
                }
            })
            .catch(error => {
                console.error('Error loading alerts:', error);
            });
    }

    /**
     * Display system alerts
     */
    function displayAlerts(container, alerts) {
        if (!alerts || alerts.length === 0) {
            container.innerHTML = '<div class="alert info">No system alerts at this time.</div>';
            return;
        }

        const html = alerts.map(alert => `
            <div class="alert ${alert.severity}">
                <div class="alert-icon">
                    <i class="fas fa-${alert.icon}"></i>
                </div>
                <div class="alert-content">
                    <div class="alert-message">${alert.message}</div>
                </div>
            </div>
        `).join('');

        container.innerHTML = html;
    }

    // ==========================================================================
    // SEARCH FUNCTIONALITY
    // ==========================================================================
    
    /**
     * Initialize search functionality
     */
    function initializeSearch() {
        const searchInput = document.querySelector('#staff-search');
        const searchResults = document.querySelector('#search-results');
        
        if (!searchInput || !searchResults) return;

        let searchTimeout;

        searchInput.addEventListener('input', function() {
            const query = this.value.trim();
            
            clearTimeout(searchTimeout);
            
            if (query.length < 2) {
                searchResults.innerHTML = '';
                searchResults.style.display = 'none';
                return;
            }

            searchTimeout = setTimeout(() => {
                performSearch(query, searchResults);
            }, 300);
        });

        // Hide results when clicking outside
        document.addEventListener('click', function(e) {
            if (!searchInput.contains(e.target) && !searchResults.contains(e.target)) {
                searchResults.style.display = 'none';
            }
        });
    }

    /**
     * Perform staff search
     */
    function performSearch(query, resultsContainer) {
        showLoading(resultsContainer);
        resultsContainer.style.display = 'block';

        makeRequest(ARMIS.config.apiEndpoints.searchStaff + '&q=' + encodeURIComponent(query))
            .then(data => {
                if (data.success) {
                    displaySearchResults(resultsContainer, data.data, query);
                } else {
                    throw new Error(data.message || 'Search failed');
                }
            })
            .catch(error => {
                console.error('Search error:', error);
                resultsContainer.innerHTML = '<div class="p-3 text-danger">Search failed. Please try again.</div>';
            });
    }

    /**
     * Display search results
     */
    function displaySearchResults(container, results, query) {
        if (!results || results.length === 0) {
            container.innerHTML = '<div class="p-3 text-gray-500">No results found for "' + query + '"</div>';
            return;
        }

        const html = results.map(person => `
            <div class="search-result-item p-3 border-bottom" data-id="${person.service_number}">
                <div class="d-flex align-items-center">
                    <div class="flex-grow-1">
                        <div class="fw-semibold">${person.first_name} ${person.last_name}</div>
                        <div class="text-sm text-gray-600">${person.rank} • ${person.unit}</div>
                        <div class="text-xs text-gray-500">${person.service_number}</div>
                    </div>
                    <div class="text-end">
                        <span class="badge bg-${getStatusColor(person.status)}">${person.status}</span>
                    </div>
                </div>
            </div>
        `).join('');

        container.innerHTML = html;

        // Add click handlers
        container.querySelectorAll('.search-result-item').forEach(item => {
            item.addEventListener('click', function() {
                const serviceNumber = this.dataset.id;
                // Navigate to staff profile or perform action
                window.location.href = `view_staff.php?id=${serviceNumber}`;
            });
        });
    }

    /**
     * Get status badge color
     */
    function getStatusColor(status) {
        switch (status?.toLowerCase()) {
            case 'active': return 'success';
            case 'deployed': return 'info';
            case 'leave': return 'warning';
            case 'retired': return 'secondary';
            default: return 'secondary';
        }
    }

    // ==========================================================================
    // ERROR HANDLING
    // ==========================================================================
    
    /**
     * Show error message in container
     */
    function showErrorMessage(container, message) {
        if (typeof container === 'string') {
            container = document.querySelector(container);
        }
        
        if (container) {
            container.innerHTML = `
                <div class="alert danger">
                    <div class="alert-icon">
                        <i class="fas fa-exclamation-triangle"></i>
                    </div>
                    <div class="alert-content">
                        <div class="alert-message">${message}</div>
                    </div>
                </div>
            `;
        }
    }

    // ==========================================================================
    // AUTO-REFRESH
    // ==========================================================================
    
    /**
     * Setup auto-refresh for dashboard data
     */
    function setupAutoRefresh() {
        // Refresh dashboard stats
        ARMIS.timers.dashboardStats = setInterval(() => {
            loadDashboardStats();
        }, ARMIS.config.refreshInterval);

        // Refresh activities
        ARMIS.timers.activities = setInterval(() => {
            loadRecentActivities();
        }, ARMIS.config.refreshInterval);

        // Refresh alerts
        ARMIS.timers.alerts = setInterval(() => {
            loadSystemAlerts();
        }, ARMIS.config.refreshInterval * 2); // Less frequent
    }

    /**
     * Stop auto-refresh timers
     */
    function stopAutoRefresh() {
        Object.values(ARMIS.timers).forEach(timer => {
            if (timer) clearInterval(timer);
        });
        ARMIS.timers = {};
    }

    // ==========================================================================
    // INITIALIZATION
    // ==========================================================================
    
    /**
     * Initialize dashboard when DOM is ready
     */
    function initializeDashboard() {
        // Load initial data
        loadDashboardStats();
        loadRecentActivities();
        loadSystemAlerts();
        
        // Initialize charts if Chart.js is available
        if (typeof Chart !== 'undefined') {
            initializeCharts();
        }
        
        // Initialize search
        initializeSearch();
        
        // Setup auto-refresh
        setupAutoRefresh();
        
        // Add refresh button handler
        const refreshBtn = document.querySelector('.refresh-dashboard');
        if (refreshBtn) {
            refreshBtn.addEventListener('click', function() {
                this.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Refreshing...';
                this.disabled = true;
                
                Promise.all([
                    loadDashboardStats(),
                    loadRecentActivities(),
                    loadSystemAlerts()
                ]).finally(() => {
                    this.innerHTML = '<i class="fas fa-sync-alt"></i> Refresh';
                    this.disabled = false;
                });
            });
        }

        console.log('ARMIS Enhanced Dashboard initialized successfully');
    }

    // ==========================================================================
    // EVENT LISTENERS
    // ==========================================================================
    
    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initializeDashboard);
    } else {
        initializeDashboard();
    }

    // Handle page visibility changes (pause/resume auto-refresh)
    document.addEventListener('visibilitychange', function() {
        if (document.hidden) {
            stopAutoRefresh();
        } else {
            setupAutoRefresh();
            // Refresh data when page becomes visible again
            loadDashboardStats();
            loadRecentActivities();
        }
    });

    // Cleanup on page unload
    window.addEventListener('beforeunload', function() {
        stopAutoRefresh();
        
        // Destroy chart instances
        Object.values(ARMIS.charts).forEach(chart => {
            if (chart && typeof chart.destroy === 'function') {
                chart.destroy();
            }
        });
    });

    // Expose ARMIS object globally for debugging
    window.ARMIS = ARMIS;

})();
