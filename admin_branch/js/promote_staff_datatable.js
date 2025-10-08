/**
 * DataTable-specific enhancements for Staff Promotion
 * This file handles the DataTable integration and modal confirmation
 */

$(document).ready(function() {
    console.log('DataTable promotion module loading...');
    
    // Global variables
    const staffDetailsCache = {};
    window.staffDetailsCache = staffDetailsCache; // Make it globally accessible
    let staffSearchLoading = false;
    
    // Staff panel rendering function
    function renderStaffPanels(selected) {
        $('#staff_promotion_details').empty();
        
        selected.forEach(svcNo => {
            let staffText = $('#selected_staff option[value="' + svcNo + '"]').text() || svcNo;
            let panel = $(
                '<div class="card mb-3">' +
                    '<div class="card-body">' +
                        '<div class="row align-items-center">' +
                            '<div class="col-md-3 mb-2">' +
                                '<strong class="profile-trigger" data-svcno="' + svcNo + '" style="cursor: pointer; color: #0d6efd; text-decoration: underline;">' + staffText + '</strong>' +
                            '</div>' +
                            '<div class="col-md-3 mb-2">' +
                                '<label class="form-label mb-0">Authority</label>' +
                                '<input type="text" name="promotion_authority[' + svcNo + ']" class="form-control authority-input" aria-label="Authority for ' + staffText + '">' +
                            '</div>' +
                            '<div class="col-md-3 mb-2">' +
                                '<label class="form-label mb-0">Remark</label>' +
                                '<input type="text" name="promotion_remark[' + svcNo + ']" class="form-control remark-input" aria-label="Remark for ' + staffText + '">' +
                            '</div>' +
                            '<div class="col-md-3 mb-2">' +
                                '<span class="badge bg-info">Current Unit: <span class="current-unit">' + ($('#selected_staff option[value="' + svcNo + '"]').text().split('(')[1] ? $('#selected_staff option[value="' + svcNo + '"]').text().split('(')[1].replace(')', '') : '') + '</span></span>' +
                            '</div>' +
                        '</div>' +
                    '</div>' +
                '</div>'
            );
            $('#staff_promotion_details').append(panel);
        });
        
        enablePromoteButton();
        $('.authority-input, .remark-input').on('input', enablePromoteButton);
    }
    
    // Enable promote button function
    function enablePromoteButton() {
        let allFilled = true;
        $('.authority-input').each(function() { 
            if (!$(this).val()) allFilled = false; 
        });
        $('#showConfirmModal').prop('disabled', !allFilled);
    }
    
    // ===========================================
    // DataTable Initialization and Management
    // ===========================================
    let staffTable = null;
    let selectedStaffMap = new Map(); // Track selected staff: service_number => staff_data
    
    // Initialize DataTable when rank is selected
    function initStaffDataTable(rankId) {
        if (!rankId) return;
        
        // Show loading state
        $('#staffTableContainer').addClass('loading');
        
        // Destroy existing table if it exists
        if (staffTable) {
            staffTable.destroy();
            $('#staffSelectionTable tbody').empty();
        }
        
        // Initialize DataTable
        staffTable = $('#staffSelectionTable').DataTable({
            ajax: {
                url: 'get_staff_by_rank_ajax.php',
                data: {
                    rank_id: rankId
                },
                dataSrc: function(json) {
                    $('#totalStaffCount').text(json.data.length);
                    $('#staffTableContainer').removeClass('loading');
                    return json.data;
                },
                error: function(xhr, error, thrown) {
                    console.error('DataTable AJAX error:', error, thrown);
                    $('#staffTableContainer').removeClass('loading');
                    alert('Error loading staff data. Please try again.');
                }
            },
            columns: [
                {
                    data: null,
                    orderable: false,
                    className: 'text-center',
                    render: function(data, type, row) {
                        return '<input type="checkbox" ' +
                                      'class="form-check-input staff-checkbox" ' +
                                      'value="' + row.service_number + '" ' +
                                      'data-staff-id="' + row.id + '" ' +
                                      'data-staff-name="' + row.full_name + '" ' +
                                      'data-unit="' + row.unit_name + '" ' +
                                      'title="Select ' + row.full_name + '">';
                    }
                },
                { 
                    data: 'service_number',
                    render: function(data) {
                        return '<strong>' + data + '</strong>';
                    }
                },
                { 
                    data: 'full_name',
                    render: function(data, type, row) {
                        return '<span class="staff-name-link" style="cursor: pointer; color: #0d6efd;" data-staff-id="' + row.id + '">' + data + '</span>';
                    }
                },
                { 
                    data: 'unit_display',
                    defaultContent: 'N/A'
                },
                { 
                    data: 'age',
                    defaultContent: 'N/A'
                },
                { 
                    data: 'years_of_service',
                    defaultContent: 'N/A'
                },
                { 
                    data: 'attestDate_formatted',
                    defaultContent: 'N/A'
                }
            ],
            pageLength: 25,
            lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, "All"]],
            order: [[6, 'asc']], // Sort by attestation date by default (oldest first)
            language: {
                emptyTable: "No staff members found at this rank",
                zeroRecords: "No matching staff members found",
                info: "Showing _START_ to _END_ of _TOTAL_ staff members",
                infoEmpty: "Showing 0 to 0 of 0 staff members",
                infoFiltered: "(filtered from _MAX_ total staff members)",
                search: "Search:",
                lengthMenu: "Show _MENU_ staff members"
            },
            drawCallback: function() {
                // Restore checkbox states after redraw
                restoreCheckboxStates();
            }
        });
    }
    
    // Restore checkbox states after DataTable redraw
    function restoreCheckboxStates() {
        $('.staff-checkbox').each(function() {
            const serviceNumber = $(this).val();
            if (selectedStaffMap.has(serviceNumber)) {
                $(this).prop('checked', true);
                $(this).closest('tr').addClass('selected');
            }
        });
        updateSelectionCount();
    }
    
    // Update selection count and button states
    function updateSelectionCount() {
        const count = selectedStaffMap.size;
        $('#selectionCount').text(count);
        
        // Update master checkbox state
        const totalVisible = $('.staff-checkbox:visible').length;
        const checkedVisible = $('.staff-checkbox:checked:visible').length;
        
        if (totalVisible > 0) {
            $('#masterCheckbox').prop('checked', checkedVisible === totalVisible);
            $('#masterCheckbox').prop('indeterminate', checkedVisible > 0 && checkedVisible < totalVisible);
        }
        
        // Enable/disable promote button based on selection
        $('#showConfirmModal').prop('disabled', count === 0);
        
        // Update hidden inputs for form submission
        updateHiddenInputs();
    }
    
    // Update hidden inputs for form submission
    function updateHiddenInputs() {
        const container = $('#selectedStaffInputs');
        container.empty();
        
        selectedStaffMap.forEach((staffData, serviceNumber) => {
            container.append('<input type="hidden" name="selected_staff[]" value="' + serviceNumber + '">');
        });
    }
    
    // Select All button handler
    $('#selectAllBtn, #masterCheckbox').on('click', function(e) {
        if (e.target.id === 'masterCheckbox' && !e.target.checked && $(e.target).prop('indeterminate')) {
            // If indeterminate, first click should check all
            $(e.target).prop('indeterminate', false);
            $(e.target).prop('checked', true);
        }
        
        $('.staff-checkbox:visible').each(function() {
            const checkbox = $(this);
            const serviceNumber = checkbox.val();
            const staffData = {
                id: checkbox.data('staff-id'),
                service_number: serviceNumber,
                full_name: checkbox.data('staff-name'),
                unit_name: checkbox.data('unit')
            };
            
            checkbox.prop('checked', true);
            checkbox.closest('tr').addClass('selected');
            selectedStaffMap.set(serviceNumber, staffData);
            
            // Also populate staffDetailsCache for renderConfirmSummary compatibility
            window.staffDetailsCache[serviceNumber] = {
                id: serviceNumber,
                text: serviceNumber + ' - ' + staffData.full_name
            };
        });
        
        updateSelectionCount();
    });
    
    // Deselect All button handler
    $('#deselectAllBtn').on('click', function() {
        $('.staff-checkbox').prop('checked', false);
        $('.staff-checkbox').closest('tr').removeClass('selected');
        $('#masterCheckbox').prop('checked', false);
        $('#masterCheckbox').prop('indeterminate', false);
        selectedStaffMap.clear();
        window.staffDetailsCache = {}; // Clear the cache
        updateSelectionCount();
    });
    
    // Individual checkbox handler
    $(document).on('change', '.staff-checkbox', function() {
        const checkbox = $(this);
        const serviceNumber = checkbox.val();
        const row = checkbox.closest('tr');
        
        if (checkbox.is(':checked')) {
            // Add to selection
            const staffData = {
                id: checkbox.data('staff-id'),
                service_number: serviceNumber,
                full_name: checkbox.data('staff-name'),
                unit_name: checkbox.data('unit')
            };
            selectedStaffMap.set(serviceNumber, staffData);
            row.addClass('selected');
            
            // Also populate staffDetailsCache for renderConfirmSummary compatibility
            window.staffDetailsCache[serviceNumber] = {
                id: serviceNumber,
                text: serviceNumber + ' - ' + staffData.full_name
            };
        } else {
            // Remove from selection
            selectedStaffMap.delete(serviceNumber);
            row.removeClass('selected');
            
            // Remove from staffDetailsCache too
            delete window.staffDetailsCache[serviceNumber];
        }
        
        updateSelectionCount();
    });
    
    // Row click handler (click anywhere on row to toggle checkbox)
    $(document).on('click', '#staffSelectionTable tbody tr', function(e) {
        // Don't trigger if clicking on checkbox itself or staff name link
        if ($(e.target).hasClass('staff-checkbox') || 
            $(e.target).hasClass('staff-name-link') ||
            $(e.target).is('input[type="checkbox"]')) {
            return;
        }
        
        const checkbox = $(this).find('.staff-checkbox');
        checkbox.prop('checked', !checkbox.prop('checked')).trigger('change');
    });
    
    // Staff name click handler for profile view
    $(document).on('click', '.staff-name-link', function(e) {
        e.stopPropagation();
        const staffId = $(this).data('staff-id');
        if (staffId && typeof loadStaffProfile === 'function') {
            loadStaffProfile(staffId);
        }
    });
    
    // Custom renderConfirmSummary for DataTables version
    window.renderConfirmSummary = function(selected) {
        console.log('Custom renderConfirmSummary called with:', selected);
        
        const promotionType = $('#promotion_type').val() || 'Not specified';
        const nextRankName = $('#next_rank_display').val() || 'Not specified';
        const promotionDate = $('input[name="promotion_date"]').val() || 'Not specified';
        const bulkAuthority = $('#bulk_authority').val() || '';
        const bulkRemark = $('#bulk_remark').val() || '';
        
        let summary = 
            '<div class="alert alert-info mb-3">' +
                '<h5><i class="fa fa-info-circle me-2"></i>Promotion Summary</h5>' +
                '<p class="mb-1"><strong>Action:</strong> ' + promotionType.toUpperCase() + '</p>' +
                '<p class="mb-1"><strong>New Rank:</strong> ' + nextRankName + '</p>' +
                '<p class="mb-1"><strong>Effective Date:</strong> ' + promotionDate + '</p>' +
                '<p class="mb-0"><strong>Staff Members:</strong> ' + selected.length + '</p>' +
            '</div>' +
            '<div class="table-responsive">' +
                '<table class="table table-bordered table-hover table-sm">' +
                    '<thead class="table-light">' +
                        '<tr>' +
                            '<th>Service #</th>' +
                            '<th>Name</th>' +
                            '<th>Unit</th>' +
                            '<th>Authority</th>' +
                            '<th>Remark</th>' +
                        '</tr>' +
                    '</thead>' +
                    '<tbody>';
        
        // Iterate through selected staff and get details from selectedStaffMap
        selected.forEach(svcNo => {
            let staffData = selectedStaffMap.get(svcNo);
            if (!staffData) {
                console.warn('No data found for service number:', svcNo);
                staffData = { full_name: 'Unknown', unit_name: 'Unknown' };
            }
            
            let staffName = staffData.full_name || 'N/A';
            let unitName = staffData.unit_name || 'N/A';
            let authority = bulkAuthority || '<span class="text-warning">Not specified</span>';
            let remark = bulkRemark || '<em class="text-muted">None</em>';
            
            summary += 
                '<tr>' +
                    '<td><strong>' + svcNo + '</strong></td>' +
                    '<td>' + staffName + '</td>' +
                    '<td>' + unitName + '</td>' +
                    '<td>' + authority + '</td>' +
                    '<td>' + remark + '</td>' +
                '</tr>';
        });
        
        summary += 
                    '</tbody>' +
                '</table>' +
            '</div>' +
            '<div class="alert alert-warning mt-3">' +
                '<i class="fa fa-exclamation-triangle me-2"></i>' +
                '<strong>Warning:</strong> Please review the information above carefully. This action will update rank information for all listed staff members.' +
            '</div>';
        
        $('#confirmSummary').html(summary);
        console.log('Modal content updated successfully');
    };
    
    // Override validateForm to work with DataTables instead of Select2
    window.validateForm = function() {
        // Use selectedStaffMap for staff selection check
        let hasStaff = selectedStaffMap && selectedStaffMap.size > 0;
        let hasPromotionType = !!$('#promotion_type').val();
        let hasNextRank = !!$('#next_rank').val();
        let hasDate = !!$('input[name="promotion_date"]').val();
        
        // Update promote button state
        const canPromote = hasStaff && hasPromotionType && hasNextRank && hasDate;
        $('#showConfirmModal').prop('disabled', !canPromote);
        
        console.log('validateForm called - Staff:', hasStaff, 'Type:', hasPromotionType, 'Rank:', hasNextRank, 'Date:', hasDate, '=> Can Promote:', canPromote);
        
        return canPromote;
    };
    
    // Promote/Demote button click handler - integrates DataTables selection with modal
    // Note: External promote_staff.js has a handler expecting Select2, so we unbind it first
    $(document).ready(function() {
        // Wait for external JS to load, then override its handler
        setTimeout(function() {
            console.log('Setting up promote button handler...');
            $('#showConfirmModal').off('click').on('click', function(e) {
                console.log('Promote button clicked!');
                e.preventDefault();
                e.stopPropagation();
                
                // Convert selectedStaffMap to array of service numbers for compatibility
                const selected = Array.from(selectedStaffMap.keys());
                console.log('Selected staff:', selected);
                console.log('staffDetailsCache:', window.staffDetailsCache);
                
                if (selected.length === 0) {
                    console.warn('No staff selected');
                    return; // Button should be disabled, but extra safety check
                }
                
                // Update hidden inputs before showing modal
                updateHiddenInputs();
                console.log('Hidden inputs updated');
                
                // Check if bulk confirmation threshold is met (defined in external JS)
                const BULK_THRESHOLD = 10;
                if (selected.length >= BULK_THRESHOLD) {
                    console.log('Showing bulk confirmation modal');
                    var bulkModal = new bootstrap.Modal(document.getElementById('bulkConfirmModal'));
                    bulkModal.show();
                    
                    $('#bulkConfirmSubmitBtn').off('click').on('click', function() {
                        console.log('Bulk confirm clicked, showing detail modal');
                        if (typeof renderConfirmSummary === 'function') {
                            renderConfirmSummary(selected);
                        } else {
                            console.error('renderConfirmSummary function not found!');
                        }
                        var confirmModal = new bootstrap.Modal(document.getElementById('confirmModal'));
                        confirmModal.show();
                        bulkModal.hide();
                    });
                    return;
                }
                
                // For normal selection, show confirmation modal directly
                console.log('Showing confirmation modal directly');
                if (typeof renderConfirmSummary === 'function') {
                    console.log('Calling renderConfirmSummary with:', selected);
                    renderConfirmSummary(selected);
                } else {
                    console.error('renderConfirmSummary function not found!');
                }
                var confirmModal = new bootstrap.Modal(document.getElementById('confirmModal'));
                confirmModal.show();
            });
            console.log('Promote button handler attached');
        }, 1000); // Wait 1 second for external JS to finish loading
    });
    
    // Initialize DataTable on page load if rank is selected
    if (window.currentRankId && window.currentRankId > 0) {
        $(document).ready(function() {
            setTimeout(function() {
                initStaffDataTable(window.currentRankId);
            }, 500);
        });
    }
    
    // Re-initialize DataTable when rank changes
    $('#current_rank').on('change', function() {
        const rankId = $(this).val();
        if (rankId && $('#staffSelectionTable').length > 0) {
            // Clear previous selections
            selectedStaffMap.clear();
            window.staffDetailsCache = {}; // Clear the cache
            $('#selectionCount').text('0');
            
            // Initialize new table
            initStaffDataTable(rankId);
        }
    });
    
    // Initialize staff selection handler
    $('#selected_staff').on('change', function() {
        const selected = $(this).val() || [];
        renderStaffPanels(selected);
    });
    
    // Initial validation
    if (typeof enablePromoteButton === 'function') {
        enablePromoteButton();
    }
    
    // Enable tooltips
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.forEach(function (tooltipTriggerEl) {
        new bootstrap.Tooltip(tooltipTriggerEl);
    });
    
    console.log('DataTable promotion module loaded successfully');
});