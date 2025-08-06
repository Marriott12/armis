/**
 * Advanced Search JavaScript for ARMIS
 * Handles search functionality, filters, and results display
 */

class ARMISAdvancedSearch {
    constructor() {
        this.currentPage = 1;
        this.pageSize = 25;
        this.selectedIds = new Set();
        this.searchResults = [];
        this.viewMode = 'table';
        this.searchEndpoint = '/Armis2/admin_branch/ajax_search.php';
        
        this.init();
    }
    
    init() {
        this.setupEventListeners();
        this.loadFilterOptions();
        this.setupQuickFilters();
        this.setupViewModeToggle();
        this.setupStaffByRankDropdown();
    }

    setupStaffByRankDropdown() {
        // Requires jQuery and Select2 to be loaded
        if (typeof $ === 'undefined' || typeof $.fn.select2 === 'undefined') return;
        const $dropdown = $('#staffByRank');
        $dropdown.select2({
            theme: 'bootstrap-5',
            placeholder: 'Select staff at current rank...',
            allowClear: true,
            minimumInputLength: 0,
            ajax: {
                url: '/Armis2/admin_branch/ajax_search.php',
                type: 'POST',
                dataType: 'json',
                delay: 250,
                data: function(params) {
                    return {
                        action: 'dropdown_by_rank',
                        rank_id: $('#filterRank').val(),
                        unit_id: $('#filterUnit').val(),
                        q: params.term || ''
                    };
                },
                processResults: function(data) {
                    return { results: data.results };
                },
                cache: true
            }
        });
        // Refresh dropdown when rank/unit changes
        $('#filterRank, #filterUnit').on('change', function() {
            $dropdown.val(null).trigger('change');
        });
    }
    
    setupEventListeners() {
        // Search button
        document.getElementById('searchBtn').addEventListener('click', () => {
            this.performSearch();
        });
        
        // Clear button
        document.getElementById('clearBtn').addEventListener('click', () => {
            this.clearForm();
        });
        
        // Search on Enter key
        document.getElementById('searchQuery').addEventListener('keypress', (e) => {
            if (e.key === 'Enter') {
                e.preventDefault();
                this.performSearch();
            }
        });
        
        // Export dropdown
        document.querySelectorAll('[data-format]').forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.preventDefault();
                this.exportResults(e.target.dataset.format);
            });
        });
        
        // Select all checkbox
        document.getElementById('selectAll').addEventListener('change', (e) => {
            this.selectAll(e.target.checked);
        });
        
        // Auto-search on filter changes (debounced)
        let debounceTimer;
        const filterInputs = document.querySelectorAll('#searchForm select, #searchForm input');
        filterInputs.forEach(input => {
            input.addEventListener('change', () => {
                clearTimeout(debounceTimer);
                debounceTimer = setTimeout(() => {
                    if (document.getElementById('searchQuery').value || this.hasActiveFilters()) {
                        this.performSearch();
                    }
                }, 500);
            });
        });
    }
    
    async loadFilterOptions() {
        try {
            const response = await fetch(`${this.searchEndpoint}?action=get_filter_options`);
            const data = await response.json();
            
            if (data.success) {
                this.populateSelect('filterRank', data.ranks, 'rankIndex', 'rankName');
                this.populateSelect('filterUnit', data.units, 'id', 'unitName');
                this.populateSelect('filterCorps', data.corps, 'corps', 'corps');
            }
        } catch (error) {
            console.error('Failed to load filter options:', error);
        }
    }
    
    populateSelect(selectId, options, valueField, textField) {
        const select = document.getElementById(selectId);
        if (!select) return;
        
        // Keep the first option (All...)
        const firstOption = select.children[0];
        select.innerHTML = '';
        select.appendChild(firstOption);
        
        options.forEach(option => {
            const optionElement = document.createElement('option');
            optionElement.value = option[valueField];
            optionElement.textContent = option[textField];
            select.appendChild(optionElement);
        });
    }
    
    setupQuickFilters() {
        document.querySelectorAll('[data-filter]').forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.preventDefault();
                this.applyQuickFilter(e.target.dataset.filter);
                e.target.classList.toggle('active');
            });
        });
    }
    
    applyQuickFilter(filterType) {
        const form = document.getElementById('searchForm');
        
        switch (filterType) {
            case 'officers':
                document.getElementById('filterRank').value = '';
                // Add logic to filter officer ranks
                break;
            case 'ncos':
                document.getElementById('filterRank').value = '';
                // Add logic to filter NCO ranks
                break;
            case 'enlisted':
                document.getElementById('filterRank').value = '';
                // Add logic to filter enlisted ranks
                break;
            case 'recent':
                const threeMonthsAgo = new Date();
                threeMonthsAgo.setMonth(threeMonthsAgo.getMonth() - 3);
                document.getElementById('enlistmentFrom').value = threeMonthsAgo.toISOString().split('T')[0];
                break;
            case 'retirement_eligible':
                const retirementAge = new Date();
                retirementAge.setFullYear(retirementAge.getFullYear() - 55);
                document.getElementById('birthTo').value = retirementAge.toISOString().split('T')[0];
                break;
        }
        
        this.performSearch();
    }
    
    setupViewModeToggle() {
        document.getElementById('viewModeTable').addEventListener('click', () => {
            this.setViewMode('table');
        });
        
        document.getElementById('viewModeGrid').addEventListener('click', () => {
            this.setViewMode('grid');
        });
    }
    
    setViewMode(mode) {
        this.viewMode = mode;
        
        // Update button states
        document.querySelectorAll('[id^="viewMode"]').forEach(btn => {
            btn.classList.remove('active');
        });
        document.getElementById(`viewMode${mode.charAt(0).toUpperCase() + mode.slice(1)}`).classList.add('active');
        
        // Show/hide appropriate view
        document.getElementById('resultsTable').style.display = mode === 'table' ? 'block' : 'none';
        document.getElementById('resultsGrid').style.display = mode === 'grid' ? 'block' : 'none';
        
        // Re-render results in new view mode
        if (this.searchResults.length > 0) {
            this.displayResults(this.searchResults);
        }
    }
    
    async performSearch(page = 1) {
        this.currentPage = page;
        this.showLoading();
        
        const formData = new FormData(document.getElementById('searchForm'));
        formData.append('action', 'search');
        formData.append('page', page);
        formData.append('page_size', this.pageSize);
        
        try {
            const response = await fetch(this.searchEndpoint, {
                method: 'POST',
                body: formData
            });
            
            const data = await response.json();
            
            if (data.success) {
                this.searchResults = data.results;
                this.displayResults(data.results);
                this.updatePagination(data.pagination);
                this.updateResultCount(data.pagination.total);
                
                // Enable export if there are results
                document.getElementById('exportBtn').disabled = data.results.length === 0;
            } else {
                this.showError(data.message || 'Search failed');
            }
        } catch (error) {
            console.error('Search failed:', error);
            this.showError('Search request failed');
        } finally {
            this.hideLoading();
        }
    }
    
    displayResults(results) {
        if (results.length === 0) {
            this.showNoResults();
            return;
        }
        
        this.hideNoResults();
        
        if (this.viewMode === 'table') {
            this.displayTableResults(results);
        } else {
            this.displayGridResults(results);
        }
        
        this.showResultsContainer();
    }
    
    displayTableResults(results) {
        const tbody = document.getElementById('resultsTableBody');
        tbody.innerHTML = '';
        
        results.forEach(staff => {
            const row = document.createElement('tr');
            row.innerHTML = `
                <td>
                    <input type="checkbox" class="form-check-input staff-checkbox" 
                           value="${staff.id}" ${this.selectedIds.has(staff.id) ? 'checked' : ''}>
                </td>
                <td>
                    <img src="${this.getPhotoUrl(staff.id)}" alt="Photo" 
                         class="rounded-circle" width="40" height="40" 
                         onerror="this.src='/Armis2/shared/images/default-avatar.png'">
                </td>
                <td>
                    <div>
                        <strong>${this.escapeHtml(staff.fname)} ${this.escapeHtml(staff.lname)}</strong>
                        <br><small class="text-muted">${this.escapeHtml(staff.email || '')}</small>
                    </div>
                </td>
                <td><code>${this.escapeHtml(staff.svcNo || 'N/A')}</code></td>
                <td>
                    <span class="badge bg-${this.getRankBadgeColor(staff.rankCategory)}">
                        ${this.escapeHtml(staff.rankName || 'N/A')}
                    </span>
                </td>
                <td>${this.escapeHtml(staff.unitName || 'N/A')}</td>
                <td>
                    <span class="badge bg-${this.getStatusBadgeColor(staff.svcStatus)}">
                        ${this.escapeHtml(staff.svcStatus || 'N/A')}
                    </span>
                </td>
                <td>
                    <div class="btn-group btn-group-sm">
                        <button class="btn btn-outline-primary" onclick="viewStaff(${staff.id})" 
                                title="View Details">
                            <i class="fas fa-eye"></i>
                        </button>
                        <button class="btn btn-outline-secondary" onclick="editStaff(${staff.id})" 
                                title="Edit">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button class="btn btn-outline-info" onclick="viewDocuments(${staff.id})" 
                                title="Documents">
                            <i class="fas fa-paperclip"></i>
                        </button>
                    </div>
                </td>
            `;
            
            tbody.appendChild(row);
        });
        
        // Update checkbox event listeners
        this.updateCheckboxListeners();
    }
    
    displayGridResults(results) {
        const grid = document.getElementById('resultsGrid');
        grid.innerHTML = '';
        
        results.forEach(staff => {
            const card = document.createElement('div');
            card.className = 'col-md-6 col-lg-4 col-xl-3 mb-3';
            card.innerHTML = `
                <div class="card h-100">
                    <div class="card-body text-center">
                        <div class="position-relative">
                            <input type="checkbox" class="form-check-input staff-checkbox position-absolute top-0 start-0" 
                                   value="${staff.id}" ${this.selectedIds.has(staff.id) ? 'checked' : ''}>
                            <img src="${this.getPhotoUrl(staff.id)}" alt="Photo" 
                                 class="rounded-circle mb-3" width="80" height="80"
                                 onerror="this.src='/Armis2/shared/images/default-avatar.png'">
                        </div>
                        <h6 class="card-title">${this.escapeHtml(staff.fname)} ${this.escapeHtml(staff.lname)}</h6>
                        <p class="card-text">
                            <small class="text-muted">${this.escapeHtml(staff.svcNo || 'N/A')}</small><br>
                            <span class="badge bg-${this.getRankBadgeColor(staff.rankCategory)} mb-1">
                                ${this.escapeHtml(staff.rankName || 'N/A')}
                            </span><br>
                            <small>${this.escapeHtml(staff.unitName || 'N/A')}</small>
                        </p>
                        <div class="btn-group btn-group-sm">
                            <button class="btn btn-outline-primary" onclick="viewStaff(${staff.id})">
                                <i class="fas fa-eye"></i>
                            </button>
                            <button class="btn btn-outline-secondary" onclick="editStaff(${staff.id})">
                                <i class="fas fa-edit"></i>
                            </button>
                        </div>
                    </div>
                </div>
            `;
            
            grid.appendChild(card);
        });
        
        // Update checkbox event listeners
        this.updateCheckboxListeners();
    }
    
    updateCheckboxListeners() {
        document.querySelectorAll('.staff-checkbox').forEach(checkbox => {
            checkbox.addEventListener('change', (e) => {
                const staffId = parseInt(e.target.value);
                if (e.target.checked) {
                    this.selectedIds.add(staffId);
                } else {
                    this.selectedIds.delete(staffId);
                }
                this.updateSelectAllState();
                this.updateBulkActionsVisibility();
            });
        });
    }
    
    selectAll(checked) {
        document.querySelectorAll('.staff-checkbox').forEach(checkbox => {
            checkbox.checked = checked;
            const staffId = parseInt(checkbox.value);
            if (checked) {
                this.selectedIds.add(staffId);
            } else {
                this.selectedIds.delete(staffId);
            }
        });
        this.updateBulkActionsVisibility();
    }
    
    updateSelectAllState() {
        const checkboxes = document.querySelectorAll('.staff-checkbox');
        const checkedBoxes = document.querySelectorAll('.staff-checkbox:checked');
        const selectAllCheckbox = document.getElementById('selectAll');
        
        if (checkboxes.length === 0) {
            selectAllCheckbox.indeterminate = false;
            selectAllCheckbox.checked = false;
        } else if (checkedBoxes.length === checkboxes.length) {
            selectAllCheckbox.indeterminate = false;
            selectAllCheckbox.checked = true;
        } else if (checkedBoxes.length > 0) {
            selectAllCheckbox.indeterminate = true;
        } else {
            selectAllCheckbox.indeterminate = false;
            selectAllCheckbox.checked = false;
        }
    }
    
    updateBulkActionsVisibility() {
        const selectedCount = this.selectedIds.size;
        document.getElementById('selectedCount').textContent = selectedCount;
        
        if (selectedCount > 0) {
            // Show bulk actions button or modal trigger
            // Implementation depends on UI design
        }
    }
    
    updatePagination(pagination) {
        const container = document.getElementById('paginationContainer');
        const paginationUl = document.getElementById('pagination');
        
        if (pagination.total_pages <= 1) {
            container.style.display = 'none';
            return;
        }
        
        container.style.display = 'flex';
        
        // Update showing text
        document.getElementById('showingStart').textContent = pagination.start;
        document.getElementById('showingEnd').textContent = pagination.end;
        document.getElementById('totalResults').textContent = pagination.total;
        
        // Build pagination
        paginationUl.innerHTML = '';
        
        // Previous button
        const prevLi = document.createElement('li');
        prevLi.className = `page-item ${pagination.current_page === 1 ? 'disabled' : ''}`;
        prevLi.innerHTML = `
            <a class="page-link" href="#" data-page="${pagination.current_page - 1}">
                <i class="fas fa-chevron-left"></i>
            </a>
        `;
        paginationUl.appendChild(prevLi);
        
        // Page numbers
        for (let i = 1; i <= pagination.total_pages; i++) {
            if (i === 1 || i === pagination.total_pages || 
                (i >= pagination.current_page - 2 && i <= pagination.current_page + 2)) {
                const li = document.createElement('li');
                li.className = `page-item ${i === pagination.current_page ? 'active' : ''}`;
                li.innerHTML = `<a class="page-link" href="#" data-page="${i}">${i}</a>`;
                paginationUl.appendChild(li);
            } else if (i === pagination.current_page - 3 || i === pagination.current_page + 3) {
                const li = document.createElement('li');
                li.className = 'page-item disabled';
                li.innerHTML = '<span class="page-link">...</span>';
                paginationUl.appendChild(li);
            }
        }
        
        // Next button
        const nextLi = document.createElement('li');
        nextLi.className = `page-item ${pagination.current_page === pagination.total_pages ? 'disabled' : ''}`;
        nextLi.innerHTML = `
            <a class="page-link" href="#" data-page="${pagination.current_page + 1}">
                <i class="fas fa-chevron-right"></i>
            </a>
        `;
        paginationUl.appendChild(nextLi);
        
        // Add click handlers
        paginationUl.addEventListener('click', (e) => {
            e.preventDefault();
            if (e.target.closest('a') && !e.target.closest('.disabled')) {
                const page = parseInt(e.target.closest('a').dataset.page);
                if (page && page !== pagination.current_page) {
                    this.performSearch(page);
                }
            }
        });
    }
    
    updateResultCount(count) {
        document.getElementById('resultCount').textContent = count;
    }
    
    async exportResults(format) {
        const formData = new FormData(document.getElementById('searchForm'));
        formData.append('action', 'export');
        formData.append('format', format);
        formData.append('selected_ids', JSON.stringify([...this.selectedIds]));
        
        try {
            const response = await fetch(this.searchEndpoint, {
                method: 'POST',
                body: formData
            });
            
            if (response.ok) {
                const blob = await response.blob();
                const url = window.URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                a.download = `staff_export_${new Date().toISOString().split('T')[0]}.${format}`;
                document.body.appendChild(a);
                a.click();
                window.URL.revokeObjectURL(url);
                document.body.removeChild(a);
            } else {
                throw new Error('Export failed');
            }
        } catch (error) {
            console.error('Export failed:', error);
            alert('Export failed. Please try again.');
        }
    }
    
    clearForm() {
        document.getElementById('searchForm').reset();
        this.selectedIds.clear();
        this.hideResultsContainer();
        this.hideNoResults();
        document.getElementById('exportBtn').disabled = true;
    }
    
    hasActiveFilters() {
        const form = document.getElementById('searchForm');
        const formData = new FormData(form);
        
        for (let [key, value] of formData.entries()) {
            if (key !== 'query' && value.trim() !== '') {
                return true;
            }
        }
        return false;
    }
    
    // Utility methods
    showLoading() {
        document.getElementById('loadingIndicator').style.display = 'block';
        this.hideResultsContainer();
        this.hideNoResults();
    }
    
    hideLoading() {
        document.getElementById('loadingIndicator').style.display = 'none';
    }
    
    showNoResults() {
        document.getElementById('noResults').style.display = 'block';
        this.hideResultsContainer();
    }
    
    hideNoResults() {
        document.getElementById('noResults').style.display = 'none';
    }
    
    showResultsContainer() {
        document.getElementById('resultsTable').style.display = this.viewMode === 'table' ? 'block' : 'none';
        document.getElementById('resultsGrid').style.display = this.viewMode === 'grid' ? 'block' : 'none';
        document.getElementById('paginationContainer').style.display = 'flex';
    }
    
    hideResultsContainer() {
        document.getElementById('resultsTable').style.display = 'none';
        document.getElementById('resultsGrid').style.display = 'none';
        document.getElementById('paginationContainer').style.display = 'none';
    }
    
    showError(message) {
        // Implement error display
        console.error(message);
    }
    
    getPhotoUrl(staffId) {
        return `/Armis2/uploads/staff_photos/${staffId}.jpg`;
    }
    
    getRankBadgeColor(category) {
        const colors = {
            'Officer': 'primary',
            'NCO': 'success',
            'Enlisted': 'info'
        };
        return colors[category] || 'secondary';
    }
    
    getStatusBadgeColor(status) {
        const colors = {
            'Active': 'success',
            'On Leave': 'warning',
            'Training': 'info',
            'Deployed': 'primary',
            'Retired': 'secondary',
            'Deceased': 'dark',
            'Discharged': 'danger'
        };
        return colors[status] || 'secondary';
    }
    
    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
}

// Global functions for button actions
function viewStaff(id) {
    window.open(`/Armis2/admin_branch/view_staff.php?id=${id}`, '_blank');
}

function editStaff(id) {
    window.location.href = `/Armis2/admin_branch/edit_staff.php?id=${id}`;
}

function viewDocuments(id) {
    window.open(`/Armis2/admin_branch/staff_documents.php?id=${id}`, '_blank');
}

// Initialize when DOM is loaded
document.addEventListener('DOMContentLoaded', () => {
    window.armisSearch = new ARMISAdvancedSearch();
});
