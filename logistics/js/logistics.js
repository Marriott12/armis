/**
 * ARMIS Logistics Module JavaScript
 * Enhanced functionality for logistics and supply chain management
 */

(function() {
    'use strict';
    
    // Global logistics configuration
    const ARMIS_LOGISTICS = {
        config: {
            refreshInterval: 300000, // 5 minutes
            apiBaseUrl: 'api/',
            maxFileSize: 10 * 1024 * 1024, // 10MB
            allowedFileTypes: ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'jpg', 'jpeg', 'png']
        },
        
        // State management
        state: {
            currentPage: 1,
            selectedItems: [],
            filters: {},
            sortBy: 'name',
            sortOrder: 'asc'
        },
        
        // Utility functions
        utils: {
            formatCurrency: function(amount) {
                return new Intl.NumberFormat('en-US', {
                    style: 'currency',
                    currency: 'USD'
                }).format(amount);
            },
            
            formatDate: function(dateString) {
                return new Date(dateString).toLocaleDateString('en-US', {
                    year: 'numeric',
                    month: 'short',
                    day: 'numeric'
                });
            },
            
            formatDateTime: function(dateString) {
                return new Date(dateString).toLocaleString('en-US', {
                    year: 'numeric',
                    month: 'short',
                    day: 'numeric',
                    hour: '2-digit',
                    minute: '2-digit'
                });
            },
            
            showToast: function(message, type = 'info') {
                // Create toast notification
                const toast = document.createElement('div');
                toast.className = `toast align-items-center text-white bg-${type} border-0`;
                toast.setAttribute('role', 'alert');
                toast.innerHTML = `
                    <div class="d-flex">
                        <div class="toast-body">${message}</div>
                        <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
                    </div>
                `;
                
                // Add to toast container
                let toastContainer = document.querySelector('.toast-container');
                if (!toastContainer) {
                    toastContainer = document.createElement('div');
                    toastContainer.className = 'toast-container position-fixed bottom-0 end-0 p-3';
                    document.body.appendChild(toastContainer);
                }
                
                toastContainer.appendChild(toast);
                
                // Show toast
                const bsToast = new bootstrap.Toast(toast);
                bsToast.show();
                
                // Remove from DOM after hidden
                toast.addEventListener('hidden.bs.toast', function() {
                    toast.remove();
                });
            },
            
            showLoading: function(element) {
                element.classList.add('loading');
            },
            
            hideLoading: function(element) {
                element.classList.remove('loading');
            },
            
            debounce: function(func, wait, immediate) {
                let timeout;
                return function executedFunction(...args) {
                    const later = function() {
                        timeout = null;
                        if (!immediate) func(...args);
                    };
                    const callNow = immediate && !timeout;
                    clearTimeout(timeout);
                    timeout = setTimeout(later, wait);
                    if (callNow) func(...args);
                };
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
                    const response = await fetch(ARMIS_LOGISTICS.config.apiBaseUrl + endpoint, config);
                    
                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }
                    
                    const data = await response.json();
                    return data;
                } catch (error) {
                    console.error('API request failed:', error);
                    ARMIS_LOGISTICS.utils.showToast('Request failed: ' + error.message, 'danger');
                    throw error;
                }
            },
            
            getInventoryItems: function(filters = {}, page = 1) {
                const params = new URLSearchParams({
                    page: page,
                    ...filters
                });
                
                return this.request(`inventory_items.php?${params}`);
            },
            
            getRequisitions: function(filters = {}, page = 1) {
                const params = new URLSearchParams({
                    page: page,
                    ...filters
                });
                
                return this.request(`requisitions.php?${params}`);
            },
            
            createRequisition: function(data) {
                return this.request('requisitions.php', {
                    method: 'POST',
                    body: JSON.stringify(data)
                });
            },
            
            updateStock: function(itemId, data) {
                return this.request(`inventory_items.php?id=${itemId}`, {
                    method: 'PUT',
                    body: JSON.stringify(data)
                });
            }
        },
        
        // Inventory management functions
        inventory: {
            loadItems: async function(page = 1) {
                try {
                    const container = document.getElementById('inventoryItems');
                    if (container) {
                        ARMIS_LOGISTICS.utils.showLoading(container);
                    }
                    
                    const data = await ARMIS_LOGISTICS.api.getInventoryItems(
                        ARMIS_LOGISTICS.state.filters, 
                        page
                    );
                    
                    if (container) {
                        container.innerHTML = ARMIS_LOGISTICS.inventory.renderItems(data.items);
                        ARMIS_LOGISTICS.utils.hideLoading(container);
                    }
                    
                    // Update pagination
                    ARMIS_LOGISTICS.inventory.updatePagination(data.pages, data.current_page);
                    
                } catch (error) {
                    console.error('Failed to load inventory items:', error);
                    const container = document.getElementById('inventoryItems');
                    if (container) {
                        container.innerHTML = '<div class="alert alert-danger">Failed to load inventory items</div>';
                        ARMIS_LOGISTICS.utils.hideLoading(container);
                    }
                }
            },
            
            renderItems: function(items) {
                if (!items || items.length === 0) {
                    return '<div class="text-center text-muted py-4">No items found</div>';
                }
                
                return items.map(item => `
                    <tr>
                        <td>
                            <div class="fw-bold">${this.escapeHtml(item.name)}</div>
                            <small class="text-muted">${this.escapeHtml(item.part_number || 'N/A')}</small>
                        </td>
                        <td>${this.escapeHtml(item.category_name || 'N/A')}</td>
                        <td>
                            <span class="stock-status-${item.stock_status.toLowerCase()}">
                                ${item.current_stock} ${item.unit_of_measure}
                            </span>
                        </td>
                        <td>${item.minimum_stock} ${item.unit_of_measure}</td>
                        <td>${ARMIS_LOGISTICS.utils.formatCurrency(item.unit_cost)}</td>
                        <td>${this.escapeHtml(item.location || 'N/A')}</td>
                        <td>
                            <div class="btn-group btn-group-sm">
                                <button class="btn btn-outline-primary" onclick="ARMIS_LOGISTICS.inventory.viewItem(${item.id})">
                                    <i class="fas fa-eye"></i>
                                </button>
                                <button class="btn btn-outline-secondary" onclick="ARMIS_LOGISTICS.inventory.editItem(${item.id})">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button class="btn btn-outline-success" onclick="ARMIS_LOGISTICS.inventory.adjustStock(${item.id})">
                                    <i class="fas fa-plus-minus"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                `).join('');
            },
            
            updatePagination: function(totalPages, currentPage) {
                const pagination = document.getElementById('inventoryPagination');
                if (!pagination || totalPages <= 1) return;
                
                let paginationHtml = '';
                
                // Previous button
                paginationHtml += `
                    <li class="page-item ${currentPage === 1 ? 'disabled' : ''}">
                        <a class="page-link" href="#" onclick="ARMIS_LOGISTICS.inventory.loadItems(${currentPage - 1})">Previous</a>
                    </li>
                `;
                
                // Page numbers
                for (let i = Math.max(1, currentPage - 2); i <= Math.min(totalPages, currentPage + 2); i++) {
                    paginationHtml += `
                        <li class="page-item ${i === currentPage ? 'active' : ''}">
                            <a class="page-link" href="#" onclick="ARMIS_LOGISTICS.inventory.loadItems(${i})">${i}</a>
                        </li>
                    `;
                }
                
                // Next button
                paginationHtml += `
                    <li class="page-item ${currentPage === totalPages ? 'disabled' : ''}">
                        <a class="page-link" href="#" onclick="ARMIS_LOGISTICS.inventory.loadItems(${currentPage + 1})">Next</a>
                    </li>
                `;
                
                pagination.innerHTML = paginationHtml;
            },
            
            viewItem: function(itemId) {
                window.open(`inventory_item.php?id=${itemId}`, '_blank');
            },
            
            editItem: function(itemId) {
                window.location.href = `edit_inventory_item.php?id=${itemId}`;
            },
            
            adjustStock: function(itemId) {
                // Show stock adjustment modal
                this.showStockAdjustmentModal(itemId);
            },
            
            showStockAdjustmentModal: function(itemId) {
                // Implementation for stock adjustment modal
                console.log('Show stock adjustment modal for item:', itemId);
            },
            
            escapeHtml: function(text) {
                const div = document.createElement('div');
                div.textContent = text;
                return div.innerHTML;
            }
        },
        
        // Requisition management functions
        requisitions: {
            create: async function(formData) {
                try {
                    const result = await ARMIS_LOGISTICS.api.createRequisition(formData);
                    
                    if (result.success) {
                        ARMIS_LOGISTICS.utils.showToast('Requisition created successfully', 'success');
                        
                        // Redirect to requisition details
                        setTimeout(() => {
                            window.location.href = `requisition_details.php?id=${result.requisition_id}`;
                        }, 1500);
                    } else {
                        ARMIS_LOGISTICS.utils.showToast('Failed to create requisition: ' + result.error, 'danger');
                    }
                } catch (error) {
                    console.error('Failed to create requisition:', error);
                    ARMIS_LOGISTICS.utils.showToast('Failed to create requisition', 'danger');
                }
            },
            
            addItem: function(itemData) {
                const itemsList = document.getElementById('requisitionItems');
                if (!itemsList) return;
                
                const itemRow = document.createElement('tr');
                itemRow.innerHTML = `
                    <td>${ARMIS_LOGISTICS.inventory.escapeHtml(itemData.name)}</td>
                    <td>
                        <input type="number" class="form-control form-control-sm" 
                               name="quantity[]" value="${itemData.quantity}" min="1" required>
                    </td>
                    <td>${ARMIS_LOGISTICS.utils.formatCurrency(itemData.unit_cost)}</td>
                    <td>
                        <input type="text" class="form-control form-control-sm" 
                               name="notes[]" placeholder="Optional notes">
                    </td>
                    <td>
                        <button type="button" class="btn btn-sm btn-danger" onclick="this.closest('tr').remove()">
                            <i class="fas fa-trash"></i>
                        </button>
                        <input type="hidden" name="item_id[]" value="${itemData.id}">
                        <input type="hidden" name="unit_cost[]" value="${itemData.unit_cost}">
                    </td>
                `;
                
                itemsList.appendChild(itemRow);
                this.updateRequisitionTotal();
            },
            
            updateRequisitionTotal: function() {
                const rows = document.querySelectorAll('#requisitionItems tr');
                let total = 0;
                
                rows.forEach(row => {
                    const quantity = parseFloat(row.querySelector('input[name="quantity[]"]')?.value || 0);
                    const unitCost = parseFloat(row.querySelector('input[name="unit_cost[]"]')?.value || 0);
                    total += quantity * unitCost;
                });
                
                const totalElement = document.getElementById('requisitionTotal');
                if (totalElement) {
                    totalElement.textContent = ARMIS_LOGISTICS.utils.formatCurrency(total);
                }
            }
        },
        
        // Search and filter functions
        search: {
            init: function() {
                const searchInput = document.getElementById('searchInput');
                if (searchInput) {
                    searchInput.addEventListener('input', 
                        ARMIS_LOGISTICS.utils.debounce(this.performSearch.bind(this), 300)
                    );
                }
            },
            
            performSearch: function(event) {
                const query = event.target.value.trim();
                ARMIS_LOGISTICS.state.filters.search = query;
                
                // Reload current view with search filter
                if (typeof ARMIS_LOGISTICS.inventory.loadItems === 'function') {
                    ARMIS_LOGISTICS.inventory.loadItems(1);
                }
            },
            
            applyFilters: function(filters) {
                ARMIS_LOGISTICS.state.filters = { ...ARMIS_LOGISTICS.state.filters, ...filters };
                
                // Reload current view with filters
                if (typeof ARMIS_LOGISTICS.inventory.loadItems === 'function') {
                    ARMIS_LOGISTICS.inventory.loadItems(1);
                }
            }
        },
        
        // Dashboard functions
        dashboard: {
            refresh: async function() {
                try {
                    // Refresh dashboard data
                    const data = await ARMIS_LOGISTICS.api.request('dashboard_data.php');
                    
                    if (data.success) {
                        this.updateKPIs(data.kpis);
                        this.updateAlerts(data.alerts);
                        this.updateActivities(data.activities);
                        
                        ARMIS_LOGISTICS.utils.showToast('Dashboard refreshed', 'success');
                    }
                } catch (error) {
                    console.error('Failed to refresh dashboard:', error);
                    ARMIS_LOGISTICS.utils.showToast('Failed to refresh dashboard', 'danger');
                }
            },
            
            updateKPIs: function(kpis) {
                // Update KPI values in the dashboard
                Object.keys(kpis).forEach(key => {
                    const element = document.getElementById(`kpi-${key}`);
                    if (element) {
                        element.textContent = kpis[key];
                    }
                });
            },
            
            updateAlerts: function(alerts) {
                const alertsContainer = document.getElementById('dashboardAlerts');
                if (alertsContainer && alerts) {
                    alertsContainer.innerHTML = alerts.map(alert => `
                        <div class="alert alert-${alert.type} alert-dismissible fade show">
                            <i class="fas fa-${alert.icon}"></i>
                            ${alert.message}
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    `).join('');
                }
            },
            
            updateActivities: function(activities) {
                const activitiesContainer = document.getElementById('recentActivities');
                if (activitiesContainer && activities) {
                    activitiesContainer.innerHTML = activities.map(activity => `
                        <div class="timeline-item">
                            <small class="text-muted">${ARMIS_LOGISTICS.utils.formatDateTime(activity.created_at)}</small>
                            <p class="mb-0">${ARMIS_LOGISTICS.inventory.escapeHtml(activity.description)}</p>
                        </div>
                    `).join('');
                }
            }
        }
    };
    
    // Initialize module when DOM is ready
    document.addEventListener('DOMContentLoaded', function() {
        console.log('ARMIS Logistics module loaded');
        
        // Initialize search functionality
        ARMIS_LOGISTICS.search.init();
        
        // Auto-refresh dashboard if on dashboard page
        if (document.querySelector('.logistics-dashboard')) {
            setInterval(ARMIS_LOGISTICS.dashboard.refresh, ARMIS_LOGISTICS.config.refreshInterval);
        }
        
        // Initialize any existing inventory tables
        if (document.getElementById('inventoryItems')) {
            ARMIS_LOGISTICS.inventory.loadItems();
        }
        
        // Initialize requisition form handlers
        const requisitionForm = document.getElementById('requisitionForm');
        if (requisitionForm) {
            requisitionForm.addEventListener('submit', function(e) {
                e.preventDefault();
                
                const formData = new FormData(this);
                const data = Object.fromEntries(formData.entries());
                
                ARMIS_LOGISTICS.requisitions.create(data);
            });
        }
        
        // Initialize quantity change handlers for requisition totals
        document.addEventListener('input', function(e) {
            if (e.target.name === 'quantity[]') {
                ARMIS_LOGISTICS.requisitions.updateRequisitionTotal();
            }
        });
    });
    
    // Expose ARMIS_LOGISTICS globally
    window.ARMIS_LOGISTICS = ARMIS_LOGISTICS;
    
})();