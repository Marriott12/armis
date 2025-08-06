/**
 * ARMIS Dashboard Utilities
 * Provides common dashboard functionality and chart configurations
 */

// ARMIS Chart Color Scheme
const ARMIS_CHART_COLORS = {
    primary: '#2c5530',     // Army Green
    secondary: '#8b4513',   // Brown
    accent: '#4a90e2',      // Blue
    gold: '#ffd700',        // Gold
    success: '#28a745',     // Green
    warning: '#ffc107',     // Yellow
    danger: '#dc3545',      // Red
    info: '#17a2b8',        // Cyan
    light: '#f8f9fa',       // Light gray
    dark: '#343a40'         // Dark gray
};

// Common Chart Options
const ARMIS_CHART_OPTIONS = {
    responsive: true,
    maintainAspectRatio: false,
    animation: {
        duration: 800,
        easing: 'easeInOutQuart'
    },
    plugins: {
        legend: {
            display: true,
            position: 'top',
            labels: {
                padding: 20,
                usePointStyle: true,
                font: {
                    size: 12,
                    family: "'Segoe UI', Tahoma, Geneva, Verdana, sans-serif"
                }
            }
        },
        tooltip: {
            backgroundColor: 'rgba(0,0,0,0.8)',
            titleColor: '#fff',
            bodyColor: '#fff',
            borderColor: ARMIS_CHART_COLORS.gold,
            borderWidth: 1,
            cornerRadius: 6,
            displayColors: true,
            intersect: false,
            mode: 'index'
        }
    },
    elements: {
        point: {
            radius: 4,
            hoverRadius: 8
        },
        line: {
            tension: 0.4
        }
    }
};

/**
 * ARMIS Dashboard Class
 * Main dashboard management functionality
 */
class ARMISDashboard {
    constructor(module = 'main') {
        this.module = module;
        this.charts = {};
        this.widgets = new Map();
        this.refreshIntervals = new Map();
        this.init();
    }

    init() {
        this.setupEventListeners();
        this.loadChartLibrary();
        console.log(`ARMIS Dashboard initialized for module: ${this.module}`);
    }

    setupEventListeners() {
        // Global refresh button
        document.addEventListener('click', (e) => {
            if (e.target.matches('[onclick*="refreshDashboard"]')) {
                e.preventDefault();
                this.refreshAllWidgets();
            }
            
            if (e.target.matches('[onclick*="refreshWidget"]')) {
                e.preventDefault();
                const widgetId = e.target.getAttribute('onclick').match(/refreshWidget\('([^']+)'\)/);
                if (widgetId) {
                    this.refreshWidget(widgetId[1]);
                }
            }
        });

        // Widget resize observer
        if (window.ResizeObserver) {
            const resizeObserver = new ResizeObserver(entries => {
                entries.forEach(entry => {
                    const chartCanvas = entry.target.querySelector('canvas');
                    if (chartCanvas && this.charts[chartCanvas.id]) {
                        this.charts[chartCanvas.id].resize();
                    }
                });
            });

            document.querySelectorAll('.chart-widget').forEach(widget => {
                resizeObserver.observe(widget);
            });
        }
    }

    loadChartLibrary() {
        if (typeof Chart === 'undefined') {
            const script = document.createElement('script');
            script.src = 'https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js';
            script.onload = () => {
                console.log('Chart.js loaded successfully');
                this.initializeCharts();
            };
            document.head.appendChild(script);
        } else {
            this.initializeCharts();
        }
    }

    initializeCharts() {
        // Override in specific modules if needed
        console.log('Charts ready for initialization');
    }

    refreshAllWidgets() {
        console.log('Refreshing all dashboard widgets...');
        document.querySelectorAll('.dashboard-widget').forEach(widget => {
            this.refreshWidget(widget.id);
        });
        
        // Show refresh animation
        const refreshButtons = document.querySelectorAll('[onclick*="refreshDashboard"]');
        refreshButtons.forEach(btn => {
            const icon = btn.querySelector('i');
            if (icon) {
                icon.classList.add('fa-spin');
                setTimeout(() => icon.classList.remove('fa-spin'), 1000);
            }
        });
    }

    refreshWidget(widgetId) {
        console.log(`Refreshing widget: ${widgetId}`);
        const widget = document.getElementById(widgetId);
        if (!widget) return;

        // Add loading state
        this.setWidgetLoading(widgetId, true);
        
        // Simulate data refresh
        setTimeout(() => {
            this.setWidgetLoading(widgetId, false);
            console.log(`Widget ${widgetId} refreshed`);
        }, 1000);
    }

    setWidgetLoading(widgetId, loading) {
        const widget = document.getElementById(widgetId);
        if (!widget) return;

        if (loading) {
            widget.classList.add('widget-loading');
            const refreshBtn = widget.querySelector('[onclick*="refreshWidget"]');
            if (refreshBtn) {
                const icon = refreshBtn.querySelector('i');
                if (icon) icon.classList.add('fa-spin');
            }
        } else {
            widget.classList.remove('widget-loading');
            const refreshBtn = widget.querySelector('[onclick*="refreshWidget"]');
            if (refreshBtn) {
                const icon = refreshBtn.querySelector('i');
                if (icon) icon.classList.remove('fa-spin');
            }
        }
    }

    createChart(canvasId, config) {
        const canvas = document.getElementById(canvasId);
        if (!canvas) {
            console.warn(`Canvas with ID ${canvasId} not found`);
            return null;
        }

        if (this.charts[canvasId]) {
            this.charts[canvasId].destroy();
        }

        this.charts[canvasId] = new Chart(canvas, config);
        return this.charts[canvasId];
    }

    updateChart(canvasId, newData) {
        const chart = this.charts[canvasId];
        if (!chart) return;

        chart.data = newData;
        chart.update('active');
    }

    destroyChart(canvasId) {
        if (this.charts[canvasId]) {
            this.charts[canvasId].destroy();
            delete this.charts[canvasId];
        }
    }
}

/**
 * Utility Functions
 */
function formatNumber(num, decimals = 0) {
    return new Intl.NumberFormat('en-US', {
        minimumFractionDigits: decimals,
        maximumFractionDigits: decimals
    }).format(num);
}

function formatCurrency(amount, currency = 'USD') {
    return new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency: currency
    }).format(amount);
}

function formatPercentage(value, decimals = 1) {
    return (value * 100).toFixed(decimals) + '%';
}

function animateCounter(element, start, end, duration = 1000) {
    const range = end - start;
    const startTime = performance.now();

    function updateCounter(currentTime) {
        const elapsed = currentTime - startTime;
        const progress = Math.min(elapsed / duration, 1);
        
        const currentValue = start + (range * progress);
        element.textContent = Math.round(currentValue);

        if (progress < 1) {
            requestAnimationFrame(updateCounter);
        }
    }

    requestAnimationFrame(updateCounter);
}

// Global dashboard instance
window.armisDashboard = null;

// Initialize dashboard when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    if (!window.armisDashboard) {
        window.armisDashboard = new ARMISDashboard();
    }
});

// Export for module use
if (typeof module !== 'undefined' && module.exports) {
    module.exports = {
        ARMISDashboard,
        ARMIS_CHART_COLORS,
        ARMIS_CHART_OPTIONS,
        formatNumber,
        formatCurrency,
        formatPercentage,
        animateCounter
    };
}
