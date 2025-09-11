/**
 * Staff Promotion Module JavaScript
 * Hand            for (let i=0; i < window.ranksDataFromServer.length; i++) {
                if (window.ranksDataFromServer[i].id == rankId) {
                    currentRankLevel = parseInt(window.ranksDataFromServer[i].level);
                    console.log("Updated current rank level:", currentRankLevel);
                    break;
                }
            }ff selection, promotion/demotion workflow, and profile viewing
 */

// Cache for staff details to avoid repeated AJAX calls
const staffDetailsCache = {};
let staffSearchLoading = false;
let currentRankLevel = null;
let ranksData = [];
const BULK_CONFIRMATION_THRESHOLD = 5;

/**
 * Initialize the promotion page functionality
 * @function init
 */
function initPromotionPage() {
    console.log("Initializing promotion page...");
    
    // Initialize current rank level from global variable or find it dynamically
    if (window.currentRankLevel !== undefined && window.currentRankLevel !== null) {
        currentRankLevel = parseInt(window.currentRankLevel);
        console.log("Current rank level loaded from global:", currentRankLevel);
    } else {
        // Find the current rank's level dynamically
        const currentRankId = document.getElementById('current_rank')?.value;
        if (currentRankId && window.ranksDataFromServer) {
            ranksData = window.ranksDataFromServer;
            for (let i=0; i < ranksData.length; i++) {
                if (ranksData[i].id == currentRankId) {
                    currentRankLevel = parseInt(ranksData[i].level);
                    console.log("Current rank level found dynamically:", currentRankLevel);
                    break;
                }
            }
        }
    }
    
    // Set ranksData if available
    if (window.ranksDataFromServer) {
        ranksData = window.ranksDataFromServer;
        console.log("Ranks data initialized:", ranksData.length, "ranks");
    }

    // Auto-submit rank form on change AND auto-load staff
    $('#current_rank').on('change', function() {
        const rankId = $(this).val();
        
        // Update currentRankLevel when rank changes
        if (rankId && window.ranksDataFromServer) {
            for (let i = 0; i < window.ranksDataFromServer.length; i++) {
                if (window.ranksDataFromServer[i].rankID == rankId) {
                    currentRankLevel = parseInt(window.ranksDataFromServer[i].level);
                    console.log("Current rank level updated to:", currentRankLevel);
                    break;
                }
            }
        }
        
        if (rankId) {
            // Show loading message immediately
            $('#search_debug').removeClass('d-none')
                .html('<div class="alert alert-info mt-2 p-2">🔄 Loading staff for selected rank...</div>');
            
            // If we're already in Step 2 (staff selection visible), auto-load immediately
            if ($('#selected_staff').length > 0 && $('#selected_staff').is(':visible')) {
                console.log('Auto-loading promotion staff for newly selected rank:', rankId);
                
                // Auto-load staff for the new rank without form submission
                setTimeout(function() {
                    if (typeof loadStaffForRank === 'function') {
                        loadStaffForRank(rankId);
                    } else {
                        console.error('loadStaffForRank function not available');
                        $('#rankForm').submit();
                    }
                }, 500);
            } else {
                // Submit the form to reload with the selected rank (Step 1 → Step 2)
                $('#rankForm').submit();
            }
        }
    });

    // Initialize Select2 for staff selection
    initSelect2StaffSelect();

    // Initialize next rank on page load if promotion type is already selected
    if ($('#promotion_type').val()) {
        $('#promotion_type').trigger('change');
    }

    // Initialize profile modal
    if (document.getElementById('staffProfileModal')) {
        console.log("Profile modal found and initialized");
    } else {
        console.error("Profile modal not found in DOM");
    }

    // Bind promotion type change
    $('#promotion_type').on('change', function() {
        var type = $(this).val().toLowerCase();
        console.log("Promotion type changed to:", type);
        updateNextRankBasedOnType(type);
        validateForm();
    });

    // Selected staff change handler
    $('#selected_staff').on('change', function() {
        const selected = $(this).val() || [];
        renderStaffPanels(selected);
        validateForm();
    });

    // Bulk authority/remark handlers
    $('#apply_bulk_authority').on('click', function() {
        let authority = $('#bulk_authority').val();
        if (!authority) return;
        $('.authority-input').val(authority);
        validateForm();
        showToast('Authority applied to all selected staff', 'success');
    });

    $('#apply_bulk_remark').on('click', function() {
        let remark = $('#bulk_remark').val();
        if (!remark) return;
        $('.remark-input').val(remark);
        showToast('Remark applied to all selected staff', 'success');
    });

    // Confirmation modal handlers
    $('#showConfirmModal').on('click', function() {
        let selected = $('#selected_staff').val() || [];
        if (selected.length >= BULK_CONFIRMATION_THRESHOLD) {
            var modal = new bootstrap.Modal(document.getElementById('bulkConfirmModal'));
            modal.show();
            $('#bulkConfirmSubmitBtn').off('click').on('click', function() {
                renderConfirmSummary(selected);
                var confirmModal = new bootstrap.Modal(document.getElementById('confirmModal'));
                confirmModal.show();
                modal.hide();
            });
            return;
        }
        renderConfirmSummary(selected);
        var modal = new bootstrap.Modal(document.getElementById('confirmModal'));
        modal.show();
    });

    $('#confirmSubmitBtn').on('click', function() {
        showLoading('Processing promotion/demotion...');
        $('#promotionForm').submit();
    });

    // Enable tooltips
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.forEach(function (tooltipTriggerEl) {
        new bootstrap.Tooltip(tooltipTriggerEl);
    });

    // Initial form validation
    validateForm();

    // Date picker enhancement
    if (typeof flatpickr !== 'undefined') {
        flatpickr('input[type="date"]', {
            dateFormat: 'Y-m-d',
            maxDate: new Date().fp_incr(30), // Allow dates up to 30 days in the future
            minDate: new Date().fp_incr(-365), // Allow dates up to 1 year in the past
        });
    } else {
        console.log('Flatpickr not available, using native date inputs');
    }

    // Handle form submission via AJAX if available
    $('#promotionForm').on('submit', function(e) {
        if (window.ajaxFormSubmit) {
            e.preventDefault();
            const formData = new FormData(this);
            
            $.ajax({
                url: 'ajax_promote_staff_minimal.php',
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    hideLoading();
                    if (response.success) {
                        showToast(response.message || 'Promotion successful!', 'success');
                        setTimeout(() => {
                            window.location.reload();
                        }, 2000);
                    } else {
                        showToast(response.message || 'Error during promotion', 'error');
                    }
                },
                error: function() {
                    hideLoading();
                    showToast('Server error during promotion', 'error');
                }
            });
        }
    });
    
    // Initialize next rank if promotion type is already selected
    const existingPromotionType = $('#promotion_type').val();
    if (existingPromotionType && currentRankLevel !== null) {
        console.log("Triggering initial next rank calculation for existing promotion type:", existingPromotionType);
        updateNextRankBasedOnType(existingPromotionType.toLowerCase());
    }
    
    console.log("Promotion page initialization complete");
}

/**
 * Initialize Select2 dropdown for staff selection
 * @function initSelect2StaffSelect
 */
function initSelect2StaffSelect() {
    $('#selected_staff').select2({
        theme: 'bootstrap-5',
        width: '100%',
        placeholder: 'Select staff...',
        allowClear: true,
        ajax: {
            url: 'search_staff.php',
            dataType: 'json',
            delay: 250,
            data: function(params) {
                return {
                    q: params.term,
                    rank_id: $('#current_rank').val()
                };
            },
            processResults: function(data) {
                // Save full staff data in cache for later use
                data.forEach(function(staff) {
                    staffDetailsCache[staff.service_number] = staff;
                });
                return {
                    results: data.map(function(staff) {
                        return {
                            id: staff.service_number,
                            text: staff.service_number + ' - ' + staff.last_name + ', ' + staff.first_name + ' (' + (staff.unit_name || staff.unit_id || '') + ')'
                        };
                    })
                };
            },
            cache: true
        },
        minimumInputLength: 1
    });
}

/**
 * Load all staff at a specific rank for promotion selection
 * @function loadStaffForRank
 * @param {string} rankId - The rank ID to load staff for
 */
function loadStaffForRank(rankId) {
    if (!rankId) return;
    
    console.log('Loading promotion staff for rank ID:', rankId);
    
    // Show loading in dropdown
    $('#selected_staff').empty().append(new Option('Loading staff members...', '', false, false));
    
    // Make AJAX call to get all staff with this rank
    $.ajax({
        url: 'search_staff.php',
        data: { 
            rank_id: rankId,
            q: 'all' // Get all staff at this rank
        },
        type: 'GET',
        dataType: 'json',
        success: function(data) {
            console.log('Promotion staff loaded for rank:', data);
            
            // Clear the dropdown
            $('#selected_staff').empty();
            
            if (data && Array.isArray(data) && data.length > 0) {
                // Add each staff member as an option (unselected by default)
                data.forEach(function(staff) {
                    if (staff.service_number && staff.text) {
                        const option = new Option(staff.text, staff.service_number, false, false);
                        $('#selected_staff').append(option);
                    }
                });
                
                // Trigger change to refresh Select2 display
                $('#selected_staff').trigger('change');
                
                // Show success message
                $('#search_debug').html('<div class="alert alert-success mt-2 p-2">✅ Loaded ' + data.length + ' staff members available for promotion. Select the ones to promote.</div>');
            } else {
                // No staff found
                $('#selected_staff').append(new Option('No staff found at this rank', '', false, false));
                $('#search_debug').html('<div class="alert alert-warning mt-2 p-2">⚠️ No staff found at this rank for promotion.</div>');
            }
        },
        error: function(xhr, status, error) {
            console.error('Error loading promotion staff:', error);
            $('#selected_staff').empty().append(new Option('Error loading staff', '', false, false));
            $('#search_debug').html('<div class="alert alert-danger mt-2 p-2">❌ Error loading staff: ' + error + '</div>');
        }
    });
}

/**
 * Update the next rank field based on promotion type
 * @function updateNextRankBasedOnType
 * @param {string} type - Promotion type ('promotion' or 'reversion')
 */
function updateNextRankBasedOnType(type) {
    const nextId = getNextRankId(type);
    const nextName = getNextRankName(type);
    console.log("Next rank calculated:", nextId, nextName);
    $('#next_rank').val(nextId);
    $('#next_rank_display').val(nextName);
    
    // Store the calculated rank data globally for access by other functions
    window.nextRankData = {
        id: nextId,
        name: nextName
    };
}

/**
 * Get the ID of the next rank based on promotion type
 * @function getNextRankId
 * @param {string} type - Promotion type ('promotion' or 'reversion')
 * @returns {string} The ID of the next rank
 */
function getNextRankId(type) {
    console.log("Getting next rank ID for type:", type, "Current level:", currentRankLevel);
    if (currentRankLevel === null) {
        console.warn("Current rank level is null");
        return '';
    }
    
    // Find current rank to get its category
    let currentRankCategory = '';
    for (let i = 0; i < ranksData.length; i++) {
        if (parseInt(ranksData[i].level) === currentRankLevel) {
            currentRankCategory = ranksData[i].category;
            break;
        }
    }
    
    console.log("Current rank category:", currentRankCategory);
    
    let targetLevel = null;
    if (type === 'promotion') {
        targetLevel = currentRankLevel - 1;  // Higher rank has a lower level number
    } else if (type === 'reversion' || type === 'demotion') {
        targetLevel = currentRankLevel + 1;  // Lower rank has a higher level number
    }
    
    console.log("Target level:", targetLevel);
    
    // Debug output all ranks
    console.log("Available ranks:", ranksData.map(r => ({ id: r.id, name: r.name, level: r.level, category: r.category })));
    
    // Find rank with target level in the same category
    for (let i=0; i<ranksData.length; i++) {
        const rankLevel = parseInt(ranksData[i].level);
        const rankCategory = ranksData[i].category;
        
        if (rankLevel === targetLevel && rankCategory === currentRankCategory) {
            console.log("Found matching rank:", ranksData[i].name, "ID:", ranksData[i].id);
            return ranksData[i].id;
        }
    }
    
    console.log("No rank found with level", targetLevel, "in category", currentRankCategory);
    return '';
}

/**
 * Get the name of the next rank based on promotion type
 * @function getNextRankName
 * @param {string} type - Promotion type ('promotion' or 'reversion')
 * @returns {string} The name of the next rank
 */
function getNextRankName(type) {
    if (currentRankLevel === null) {
        console.warn("Current rank level is null");
        return '';
    }
    
    // Find current rank to get its category
    let currentRankCategory = '';
    for (let i = 0; i < ranksData.length; i++) {
        if (parseInt(ranksData[i].level) === currentRankLevel) {
            currentRankCategory = ranksData[i].category;
            break;
        }
    }
    
    let targetLevel = null;
    if (type === 'promotion') {
        targetLevel = currentRankLevel - 1;  // Higher rank has a lower level number
    } else if (type === 'reversion' || type === 'demotion') {
        targetLevel = currentRankLevel + 1;  // Lower rank has a higher level number
    }
    
    // Find rank with target level in the same category
    for (let i = 0; i < ranksData.length; i++) {
        const rankLevel = parseInt(ranksData[i].level);
        const rankCategory = ranksData[i].category;
        
        if (rankLevel === targetLevel && rankCategory === currentRankCategory) {
            return ranksData[i].name;
        }
    }
    return '';
}

/**
 * Render staff detail panels for selected staff
 * @function renderStaffPanels
 * @param {Array} selected - Array of selected staff service numbers
 */
function renderStaffPanels(selected) {
    const panel = $('#staffDetailsPanel');
    panel.empty();
    
    if (!selected || selected.length === 0) {
        $('#showConfirmModal').prop('disabled', true);
        return;
    }
    
    // Use document fragment for better performance
    const fragment = document.createDocumentFragment();
    const wrapper = document.createElement('div');
    
    selected.forEach(svcNo => {
        let staff = staffDetailsCache[svcNo] || {id: svcNo, text: svcNo};
        
        // Create staff panel with enhanced UI
        const staffCard = `
            <div class="card mb-2 staff-detail-card" data-svcno="${svcNo}">
                <div class="card-body">
                    <div class="row align-items-center">
                        <div class="col-md-3 col-sm-6 mb-2 profile-trigger" style="cursor:pointer;" data-svcno="${svcNo}">
                            <div class="d-flex align-items-center">
                                <div class="staff-avatar me-2">
                                    ${staff.photo_url ? `<img src="${staff.photo_url}" class="rounded-circle" width="40" height="40" alt="Staff photo">` : 
                                     `<div class="avatar-placeholder rounded-circle bg-secondary text-white d-flex align-items-center justify-content-center" style="width:40px;height:40px;">
                                        <i class="fa fa-user"></i>
                                      </div>`}
                                </div>
                                <div>
                                    <strong>${staff.text}</strong>
                                    <br>
                                    <small class="text-muted"><i class="fa fa-id-card me-1"></i>Click for profile</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3 col-sm-6 mb-2">
                            <label class="form-label mb-0">Authority <span class="text-danger">*</span></label>
                            <div class="input-group has-validation">
                                <input type="text" name="promotion_authority[${svcNo}]" class="form-control authority-input" 
                                       aria-label="Authority for ${staff.text}" required data-bs-toggle="tooltip" 
                                       title="Required: Authority for this promotion/demotion">
                                <div class="invalid-feedback">
                                    Authority is required
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3 col-sm-6 mb-2">
                            <label class="form-label mb-0">Remark</label>
                            <input type="text" name="promotion_remark[${svcNo}]" class="form-control remark-input" 
                                   aria-label="Remark for ${staff.text}" data-bs-toggle="tooltip" 
                                   title="Optional: Additional remarks">
                        </div>
                        <div class="col-md-3 col-sm-6 mb-2">
                            <div class="d-flex flex-column">
                                <span class="badge bg-info mb-1">
                                    <i class="fa fa-building me-1"></i>Unit: <span class="current-unit">${staff.unit_name || staff.unit_id || 'N/A'}</span>
                                </span>
                                ${staff.trade ? 
                                `<span class="badge bg-secondary mb-1">
                                    <i class="fa fa-tools me-1"></i>Trade: ${staff.trade}
                                </span>` : ''}
                                ${staff.corps ? 
                                `<span class="badge bg-dark">
                                    <i class="fa fa-shield-alt me-1"></i>Corps: ${staff.corps}
                                </span>` : ''}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        `;
        
        wrapper.innerHTML = staffCard;
        while (wrapper.firstChild) {
            fragment.appendChild(wrapper.firstChild);
        }
    });
    
    panel.append(fragment);
    
    // Reinitialize tooltips
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.forEach(function (tooltipTriggerEl) {
        new bootstrap.Tooltip(tooltipTriggerEl);
    });
    
    // Form validation
    validateForm();
    $('.authority-input').on('input', validateForm);

    // Profile pop-up
    $('.profile-trigger').off('click').on('click', function(e) {
        e.preventDefault();
        let svcNo = $(this).data('svcno');
        console.log("Profile trigger clicked for:", svcNo);
        showStaffProfile(svcNo);
    });
}

/**
 * Validate the promotion form
 * @function validateForm
 */
function validateForm() {
    let allFilled = true;
    let hasStaff = $('#selected_staff').val()?.length > 0;
    let hasPromotionType = !!$('#promotion_type').val();
    let hasNextRank = !!$('#next_rank').val();
    let hasDate = !!$('input[name="promotion_date"]').val();
    
    // Check authority fields
    $('.authority-input').each(function() {
        if (!$(this).val()) {
            allFilled = false;
            $(this).addClass('is-invalid');
        } else {
            $(this).removeClass('is-invalid');
        }
    });
    
    // Highlight required fields
    $('#promotion_type')[hasPromotionType ? 'removeClass' : 'addClass']('is-invalid');
    $('input[name="promotion_date"]')[hasDate ? 'removeClass' : 'addClass']('is-invalid');
    
    // Update promote button state
    const canPromote = allFilled && hasStaff && hasPromotionType && hasNextRank && hasDate;
    $('#showConfirmModal').prop('disabled', !canPromote);
    
    // Show help text if button is disabled
    if (!canPromote) {
        let missingFields = [];
        if (!hasStaff) missingFields.push('staff selection');
        if (!hasPromotionType) missingFields.push('promotion type');
        if (!hasNextRank) missingFields.push('next rank');
        if (!hasDate) missingFields.push('promotion date');
        if (!allFilled) missingFields.push('authority information');
        
        if (missingFields.length) {
            $('#validation-message').html(`<div class="alert alert-warning">
                <i class="fa fa-exclamation-triangle me-2"></i>
                Please complete the following: ${missingFields.join(', ')}
            </div>`).show();
        }
    } else {
        $('#validation-message').hide();
    }
    
    return canPromote;
}

/**
 * Show staff profile in modal
 * @function showStaffProfile
 * @param {string} svcNo - Service number of the staff
 */
async function showStaffProfile(svcNo) {
    console.log("Loading profile for:", svcNo);
    
    // Check if modal element exists
    if (!document.getElementById('staffProfileModal')) {
        console.error("Staff profile modal element not found!");
        showToast("Error: Profile modal not found in the page.", "error");
        return;
    }
    
    // Show loading indicator
    $('#staffProfileContent').html(`
        <div class="text-center p-4">
            <div class="spinner-border text-primary mb-3" role="status">
                <span class="visually-hidden">Loading...</span>
            </div>
            <p>Loading profile data...</p>
        </div>
    `);
    
    try {
        // Make sure modal is shown before AJAX completes
        const staffModal = new bootstrap.Modal(document.getElementById('staffProfileModal'));
        staffModal.show();
        
        try {
            // Determine if we can use JSON format (browser capability check)
            const useJson = window.fetch && window.JSON;
            
            if (useJson) {
                // Use fetch API with JSON response format
                const response = await fetch(`ajax_staff_profile.php?service_number=${encodeURIComponent(svcNo)}&format=json&t=${Date.now()}`);
                
                if (!response.ok) {
                    throw new Error(`Server returned ${response.status}: ${response.statusText}`);
                }
                
                const data = await response.json();
                console.log("Profile data received:", data);
                
                if (!data.success) {
                    throw new Error(data.message || "Failed to load profile data");
                }
                
                // Render the profile data with custom template
                renderProfileFromJson(data.data);
            } else {
                // Fallback to HTML response for older browsers
                const response = await fetch(`ajax_staff_profile.php?service_number=${encodeURIComponent(svcNo)}&t=${Date.now()}`);
                
                if (!response.ok) {
                    throw new Error(`Server returned ${response.status}: ${response.statusText}`);
                }
                
                const html = await response.text();
                console.log("Profile HTML received");
                
                // Render the HTML directly
                $('#staffProfileContent').html(html);
            }
            
            // Initialize any interactive elements in the profile
            initProfileInteractiveElements();
            
        } catch (error) {
            console.error("Profile load error:", error);
            $('#staffProfileContent').html(`
                <div class="alert alert-danger">
                    <h5><i class="fa fa-exclamation-circle me-2"></i>Failed to load profile</h5>
                    <p>${error.message}</p>
                    <button class="btn btn-sm btn-outline-danger mt-2" onclick="showStaffProfile('${svcNo}')">
                        <i class="fa fa-refresh me-1"></i>Try Again
                    </button>
                </div>
            `);
        }
    } catch (e) {
        console.error("Error showing modal:", e);
        showToast("Error initializing modal. See console for details.", "error");
    }
}

/**
 * Render profile from JSON data
 * @function renderProfileFromJson
 * @param {Object} data - The profile data object containing staff and promotionHistory
 */
function renderProfileFromJson(data) {
    if (!data || !data.staff) {
        $('#staffProfileContent').html('<div class="alert alert-warning">No profile data available</div>');
        return;
    }
    
    const staff = data.staff;
    const promotionHistory = data.promotionHistory || [];
    
    let html = `
    <div class="container-fluid profile-container">
        <div class="row mb-3">
            <div class="col-md-6">
                <h5 class="staff-name">${escapeHtml(staff.rank_short_name || '')} ${escapeHtml(staff.last_name + ', ' + staff.first_name)}</h5>
                <p><strong>Service Number:</strong> <span class="service-number">${escapeHtml(staff.service_number)}</span></p>
                <p><strong>Rank:</strong> <span class="rank">${escapeHtml(staff.rank_name || staff.rank_id)}</span></p>
                <p><strong>Unit:</strong> <span class="unit">${escapeHtml(staff.unit_name || staff.unit_id || '-')}</span></p>
                <p><strong>Date of Birth:</strong> <span class="dob">${escapeHtml(staff.dob || '-')}</span></p>
                <p><strong>Gender:</strong> <span class="gender">${escapeHtml(staff.gender || '-')}</span></p>
            </div>
            <div class="col-md-6">
                <p><strong>Email:</strong> <span class="email">${escapeHtml(staff.email || '-')}</span></p>
                <p><strong>Phone:</strong> <span class="phone">${escapeHtml(staff.phone || '-')}</span></p>
                <p><strong>Address:</strong> <span class="address">${escapeHtml(staff.address || '-')}</span></p>
                <p><strong>Status:</strong> <span class="status ${(staff.status || '').toLowerCase()}">${escapeHtml(staff.status || '-')}</span></p>
                <p><strong>Date of Attestation:</strong> <span class="attest-date">${escapeHtml(staff.attestDate || '-')}</span></p>
                <p><strong>Current Rank Since:</strong> <span class="rank-date">${escapeHtml(staff.subWef || '-')}</span></p>
            </div>
        </div>`;
    
    // Add promotion history if available
    if (promotionHistory && promotionHistory.length > 0) {
        html += `
        <div class="row mt-3">
            <div class="col-12">
                <h6 class="promotion-history-title">Promotion History</h6>
                <div class="table-responsive">
                    <table class="table table-sm table-bordered promotion-history-table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Type</th>
                                <th>From</th>
                                <th>To</th>
                                <th>Authority</th>
                            </tr>
                        </thead>
                        <tbody>`;
        
        promotionHistory.forEach(p => {
            html += `
                            <tr>
                                <td>${escapeHtml(formatDate(p.date_to))}</td>
                                <td>${escapeHtml(capitalizeFirstLetter(p.type))}</td>
                                <td>${escapeHtml(p.old_rank_name)}</td>
                                <td>${escapeHtml(p.new_rank_name)}</td>
                                <td>${escapeHtml(p.authority)}</td>
                            </tr>`;
        });
        
        html += `
                        </tbody>
                    </table>
                </div>
            </div>
        </div>`;
    }
    
    html += `</div>`;
    
    $('#staffProfileContent').html(html);
}

/**
 * Format a date string as dd-MMM-yyyy
 * @function formatDate
 * @param {string} dateString - The date string to format
 * @returns {string} Formatted date string
 */
function formatDate(dateString) {
    if (!dateString) return '-';
    try {
        const date = new Date(dateString);
        const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
        return `${date.getDate().toString().padStart(2, '0')}-${months[date.getMonth()]}-${date.getFullYear()}`;
    } catch (e) {
        return dateString;
    }
}

/**
 * Capitalize the first letter of a string
 * @function capitalizeFirstLetter
 * @param {string} string - The string to capitalize
 * @returns {string} String with first letter capitalized
 */
function capitalizeFirstLetter(string) {
    if (!string) return '';
    return string.charAt(0).toUpperCase() + string.slice(1);
}

/**
 * Escape HTML special characters
 * @function escapeHtml
 * @param {string} html - The string to escape
 * @returns {string} Escaped string
 */
function escapeHtml(html) {
    if (!html) return '';
    const div = document.createElement('div');
    div.textContent = html;
    return div.innerHTML;
}

/**
 * Initialize interactive elements in the profile modal
 * @function initProfileInteractiveElements
 */
function initProfileInteractiveElements() {
    // Initialize tabs if present
    if ($('#staffProfileContent .nav-tabs').length) {
        $('#staffProfileContent .nav-tabs .nav-link').on('click', function(e) {
            e.preventDefault();
            $(this).tab('show');
        });
        
        // Activate first tab
        $('#staffProfileContent .nav-tabs .nav-link:first').tab('show');
    }
    
    // Initialize any charts
    if (window.Chart && $('#staffProfileContent canvas.chart').length) {
        // Implementation depends on what charts are in the profile
        $('#staffProfileContent canvas.chart').each(function() {
            // Chart initialization logic here
        });
    }
}

/**
 * Render confirmation summary for selected staff
 * @function renderConfirmSummary
 * @param {Array} selected - Array of selected staff service numbers
 */
function renderConfirmSummary(selected) {
    const promotionType = $('#promotion_type').val();
    const nextRankName = $('#next_rank').closest('.col-md-4').find('input.form-control[readonly]').val();
    const promotionDate = $('input[name="promotion_date"]').val();
    
    let summary = `
        <div class="alert alert-info mb-3">
            <h5><i class="fa fa-info-circle me-2"></i>Promotion Summary</h5>
            <p><strong>Action:</strong> ${promotionType.toUpperCase()}</p>
            <p><strong>New Rank:</strong> ${nextRankName}</p>
            <p><strong>Effective Date:</strong> ${promotionDate}</p>
            <p><strong>Staff Members:</strong> ${selected.length}</p>
        </div>
        <div class="table-responsive">
            <table class="table table-bordered table-hover">
                <thead class="table-light">
                    <tr>
                        <th>Service #</th>
                        <th>Name</th>
                        <th>Authority</th>
                        <th>Remark</th>
                    </tr>
                </thead>
                <tbody>
    `;
    
    selected.forEach(svcNo => {
        let staff = staffDetailsCache[svcNo] || {id: svcNo, text: svcNo};
        let staffName = staff.text ? staff.text.split('-')[1]?.trim() : '';
        let authority = $(`input[name='promotion_authority[${svcNo}]']`).val();
        let remark = $(`input[name='promotion_remark[${svcNo}]']`).val();
        
        summary += `
            <tr>
                <td>${svcNo}</td>
                <td>${staffName || 'N/A'}</td>
                <td>${authority || '<span class="text-danger">Missing</span>'}</td>
                <td>${remark || '<em class="text-muted">None</em>'}</td>
            </tr>
        `;
    });
    
    summary += `
                </tbody>
            </table>
        </div>
        <div class="alert alert-warning mt-3">
            <i class="fa fa-exclamation-triangle me-2"></i>
            Please review the information above carefully. This action will update rank information for all listed staff members.
        </div>
    `;
    
    $('#confirmSummary').html(summary);
}

/**
 * Show a toast notification
 * @function showToast
 * @param {string} message - The message to display
 * @param {string} type - The type of toast (success, error, warning, info)
 */
function showToast(message, type = 'info') {
    // Create toast container if it doesn't exist
    if (!document.getElementById('toast-container')) {
        const container = document.createElement('div');
        container.id = 'toast-container';
        container.className = 'position-fixed bottom-0 end-0 p-3';
        container.style.zIndex = '1050';
        document.body.appendChild(container);
    }
    
    // Create a unique ID for this toast
    const toastId = 'toast-' + Date.now();
    
    // Set the icon based on type
    let icon = 'info-circle';
    let bgClass = 'bg-info';
    if (type === 'success') {
        icon = 'check-circle';
        bgClass = 'bg-success';
    } else if (type === 'error') {
        icon = 'exclamation-circle';
        bgClass = 'bg-danger';
    } else if (type === 'warning') {
        icon = 'exclamation-triangle';
        bgClass = 'bg-warning';
    }
    
    // Create and append the toast
    const toastHtml = `
        <div id="${toastId}" class="toast" role="alert" aria-live="assertive" aria-atomic="true">
            <div class="toast-header ${bgClass} text-white">
                <i class="fa fa-${icon} me-2"></i>
                <strong class="me-auto">Notification</strong>
                <small>${new Date().toLocaleTimeString()}</small>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="toast" aria-label="Close"></button>
            </div>
            <div class="toast-body">
                ${message}
            </div>
        </div>
    `;
    
    document.getElementById('toast-container').insertAdjacentHTML('beforeend', toastHtml);
    
    // Initialize and show the toast
    const toastElement = document.getElementById(toastId);
    const toast = new bootstrap.Toast(toastElement, {
        delay: 5000,
        autohide: true
    });
    
    toast.show();
    
    // Remove the toast from DOM after it's hidden
    toastElement.addEventListener('hidden.bs.toast', function() {
        toastElement.remove();
    });
}

/**
 * Show a loading overlay
 * @function showLoading
 * @param {string} message - The message to display
 */
function showLoading(message = 'Loading...') {
    // Create loading overlay if it doesn't exist
    if (!document.getElementById('loading-overlay')) {
        const overlay = document.createElement('div');
        overlay.id = 'loading-overlay';
        overlay.className = 'position-fixed top-0 start-0 w-100 h-100 d-flex justify-content-center align-items-center';
        overlay.style.zIndex = '2000';
        overlay.style.backgroundColor = 'rgba(0,0,0,0.5)';
        overlay.style.display = 'none';
        
        const spinner = `
            <div class="bg-white p-4 rounded shadow-lg text-center">
                <div class="spinner-border text-primary mb-3" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
                <div id="loading-message" class="text-dark">${message}</div>
            </div>
        `;
        
        overlay.innerHTML = spinner;
        document.body.appendChild(overlay);
    }
    
    // Update message and show overlay
    document.getElementById('loading-message').textContent = message;
    document.getElementById('loading-overlay').style.display = 'flex';
}

/**
 * Hide the loading overlay
 * @function hideLoading
 */
function hideLoading() {
    const overlay = document.getElementById('loading-overlay');
    if (overlay) {
        overlay.style.display = 'none';
    }
}

// Initialize when the document is ready
$(document).ready(function() {
    initPromotionPage();
});

// Export functions for use in other contexts
window.showStaffProfile = showStaffProfile;
window.validateForm = validateForm;
window.showToast = showToast;
window.showLoading = showLoading;
window.hideLoading = hideLoading;
window.loadStaffForRank = loadStaffForRank;
window.updateNextRankBasedOnType = updateNextRankBasedOnType;
