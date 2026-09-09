// appointments.init.js
// This file contains the deferred initialization logic for admin_branch/appointments.php
// It reads window.appointmentsServerData injected by the PHP page and initializes
// the DataTable, Select2, and other UI behaviour. The shared/footer.php will call
// window.initAppointmentsPage() once dependencies are loaded.

(function(window, $) {
    'use strict';

    function toTitleCase(str) {
        if (!str) return '';
        return str.trim().split(/\s+/).map(function(word) {
            if (word.length === 0) return '';
            return word.split('-').map(function(part) {
                return part.split("'").map(function(sub) {
                    return sub.charAt(0).toUpperCase() + sub.slice(1).toLowerCase();
                }).join("'");
            }).join('-');
        }).join(' ');
    }

    // Main init function (called from shared/footer.php)
    window.initAppointmentsPage = function() {
        var data = window.appointmentsServerData || {};
        var unitsData = data.unitsData || [];
        var eligibleStaff = data.eligibleStaff || [];
        var currentRankId = data.currentRankId || '';
        var standardPositions = data.standardPositions || [];
        var preselectedStaff = data.preselectedStaff || [];

        if (typeof $.fn.DataTable === 'undefined') {
            console.error('DataTables not available');
            return;
        }

        // This page's staff-selection workflow depends entirely on the
        // DataTables Select extension (row (de)selection via td.select-checkbox
        // clicks - see the `select` config below). The page only <link>s the
        // Select extension's CSS directly; its JS is expected to come from
        // shared/footer.php. If that JS didn't load, row selection silently
        // does nothing and the whole page is unusable with no clear signal
        // why - so fail loudly instead.
        if (!$.fn.dataTable || !$.fn.dataTable.select) {
            console.error('DataTables Select extension not loaded - staff row selection will not work. Check that shared/footer.php includes datatables.net-select.');
            var $container = $('#staffTableContainer');
            if ($container.length) {
                $container.before(
                    '<div class="alert alert-danger"><i class="fa fa-exclamation-triangle"></i> ' +
                    'This page needs a component that failed to load, so staff selection is unavailable. ' +
                    'Please refresh the page, or contact support if this continues.</div>'
                );
            }
            return;
        }

        // Normalize eligibleStaff rows so the DataTable always has unit_name, corps and status fields.
        // NOTE: the PHP side (appointments.php) sends svcNo / fName / lName - NOT
        // service_number / first_name / last_name. Keep these field names in sync
        // with the `eligibleStaff` shape built in appointments.php.
        try {
            var unitLookup = {};
            (unitsData || []).forEach(function(u){
                var id = u.unitID || u.unit_id || u.id;
                var name = u.unitName || u.code || u.unit_name || u.name || '';
                if (id) unitLookup[String(id)] = name;
            });

            eligibleStaff = (eligibleStaff || []).map(function(row){
                var r = Object.assign({}, row);
                if ((!r.unit_name || r.unit_name === null) && r.unitId) {
                    r.unit_name = unitLookup[String(r.unitId)] || null;
                }
                r.unit_name = r.unit_name || 'N/A';
                r.corps = r.corps || '';
                r.status = r.status || 'Active';
                r.rank_name = r.rank_name || r.rankId || '';
                r.rank_abbr = r.rank_abbr || r.rank_name || '';
                r.svcNo = r.svcNo || '';
                r.fName = r.fName || '';
                r.lName = r.lName || '';
                r.appt = r.appt || '';
                return r;
            });
        } catch (ex) {
            console.warn('appointments.init: failed to normalize eligibleStaff', ex);
        }

        if (eligibleStaff.length > 0) {
            console.log('First staff record:', eligibleStaff[0]);
        }

        var staffTable = $('#staffSelectionTable').DataTable({
            data: eligibleStaff,
            pageLength: 25,
            order: [[1, 'asc']],
            responsive: true,
            select: { style: 'multi', selector: 'td.select-checkbox' },
            columns: [
                {
                    data: null, orderable: false, className: 'select-checkbox text-center', defaultContent: '',
                    render: function () {
                        // Visual-only checkbox; row (de)selection is driven by the
                        // DataTables Select extension via the cell click (selector
                        // above), then reflected onto this checkbox in syncCheckboxes().
                        return '<input type="checkbox" class="form-check-input staff-checkbox">';
                    }
                },
                { data: 'svcNo', title: 'Service No.', orderable: true },
                { data: null, title: 'Rank', orderable: false, render: function(d, t, r){ return '<span class="badge bg-primary">' + (r.rank_abbr||r.rank_name||'N/A') + '</span>'; } },
                { data: null, title: 'Name', orderable: true, render: function(d,t,r){ return '<div class="fw-bold">'+ (toTitleCase(r.fName||'') + ' ' + toTitleCase(r.lName||'')).trim() +'</div>'; } },
                { data: 'unit_name', title: 'Unit', orderable: true, defaultContent: 'N/A' },
                { data: 'corps', title: 'Corps', orderable: false, defaultContent: 'N/A' },
                { data: 'appt', title: 'Current Appointment', orderable: false, defaultContent: 'N/A' },
                { data: null, title: 'Status', orderable: false, render: function(d,t,r){ var status = r.status||'Active'; var cls = status==='Active' ? 'bg-success' : 'bg-secondary'; return '<span class="badge '+cls+'">'+status+'</span>'; } }
            ],
            dom: '<"row"<"col-sm-12 col-md-6"l><"col-sm-12 col-md-6"f>>rtip'
        });

        // Expose key objects for the rest of the inline page scripts
        window.eligibleStaff = eligibleStaff;
        window.unitsData = unitsData;

        // Reflect DataTables' row-selected state onto the visual checkboxes
        // (and the header master checkbox) after every select/deselect/draw.
        function syncCheckboxes(){
            var total = staffTable.rows({search:'applied'}).count();
            var selectedCount = staffTable.rows({selected:true}).count();

            staffTable.rows().every(function(){
                var isSelected = this.selected ? this.selected() : $(this.node()).hasClass('selected');
                $(this.node()).find('.staff-checkbox').prop('checked', !!isSelected);
            });

            var $master = $('#masterCheckbox');
            if (total > 0 && selectedCount === total) {
                $master.prop('checked', true).prop('indeterminate', false);
            } else if (selectedCount > 0) {
                $master.prop('checked', false).prop('indeterminate', true);
            } else {
                $master.prop('checked', false).prop('indeterminate', false);
            }

            $('#selectionCount').text(selectedCount);
        }

        // Hidden inputs container (tells the PHP backend which svcNo's were chosen)
        var $inputs = $('#selectedStaffInputs');

        function addSelectedStaffInput(svcNo){
            if (!svcNo) return;
            if ($inputs.find('input[value="'+svcNo+'"]').length===0){
                $inputs.append('<input type="hidden" name="selected_staff[]" value="'+svcNo+'">');
            }
        }
        function removeSelectedStaffInput(svcNo){
            if (!svcNo) return;
            $inputs.find('input[value="'+svcNo+'"]').remove();
        }

        staffTable.on('select', function(e, dt, type, indexes){
            if (type !== 'row') return;
            var rows = staffTable.rows(indexes).data().toArray();
            rows.forEach(function(r){ addSelectedStaffInput(r.svcNo); });
        });

        staffTable.on('deselect', function(e, dt, type, indexes){
            if (type !== 'row') return;
            var rows = staffTable.rows(indexes).data().toArray();
            rows.forEach(function(r){ removeSelectedStaffInput(r.svcNo); });
        });

        // Master "select all" checkbox - only affects currently filtered/visible rows
        $('#masterCheckbox').on('click', function(){
            if (this.checked) {
                staffTable.rows({search:'applied'}).select();
            } else {
                staffTable.rows({search:'applied'}).deselect();
            }
        });

        // If preselected staff were posted (e.g. validation failed server-side), re-select them
        if (preselectedStaff && preselectedStaff.length>0){
            var lookup = {};
            staffTable.rows().every(function(idx){ var d = this.data(); lookup[d.svcNo] = idx; });
            preselectedStaff.forEach(function(svc){ if (typeof lookup[svc] !== 'undefined'){ staffTable.row(lookup[svc]).select(); } });
        }

        // If server didn't provide eligibleStaff but current rank exists, try AJAX fallback
        if ((eligibleStaff === null || eligibleStaff.length===0) && currentRankId){
            $.ajax({ url: 'ajax_get_staff_by_rank.php', data: { rank_id: currentRankId }, type: 'GET', dataType: 'json' })
            .done(function(resp){
                if (Array.isArray(resp) && resp.length>0){
                    var mapped = resp.map(function(row){
                        return {
                            svcNo: row.svcNo || row.service_number || row.id || '',
                            fName: row.fName || row.first_name || '',
                            lName: row.lName || row.last_name || '',
                            rank_name: row.rank_name || row.rank_id || '',
                            rank_abbr: row.rank_abbr || row.rank_name || '',
                            unit_name: row.unit_name || '',
                            corps: row.corps || row.corps_id || '',
                            appt: row.appt || row.appointment || '',
                            status: row.status || row.svcStatus || 'Active'
                        };
                    });
                    staffTable.clear(); staffTable.rows.add(mapped).draw();
                    // Keep window.eligibleStaff in sync with what's actually in the
                    // table now, since other inline scripts (e.g. the confirm-modal
                    // summary built in appointments.php) read from it by svcNo.
                    window.eligibleStaff = mapped;
                    $('#totalStaffCount').text(mapped.length);
                }
            })
            .fail(function(xhr, status, err){ console.error('Failed to load staff via AJAX:', status, err); });
        } else {
            $('#totalStaffCount').text(staffTable.rows().count());
        }

        // Select/deselect all buttons.
        // FIX: these previously called staffTable.rows().select() / .deselect()
        // with no scope, which selects EVERY row including ones hidden by an
        // active search filter - inconsistent with the master checkbox above,
        // which only selects filtered/visible rows. That mismatch meant a
        // person could type a search, click "Select All", and unknowingly
        // select staff they can't even see on screen. Both now consistently
        // respect the current search filter.
        $('#selectAllBtn').on('click', function(){ staffTable.rows({search:'applied'}).select(); });
        $('#deselectAllBtn').on('click', function(){ staffTable.rows({search:'applied'}).deselect(); });

        function unitOptionsHtml(selectedUnitId){
            var html = '<option value="">-- Select Unit --</option>';
            (unitsData || []).forEach(function(u){
                var id = u.unitID || u.unit_id || u.id || '';
                var name = u.unitName || u.code || u.unit_name || u.name || id;
                var sel = (String(selectedUnitId||'') === String(id)) ? ' selected' : '';
                html += '<option value="'+id+'"'+sel+'>'+name+'</option>';
            });
            return html;
        }

        function buildStaffCard(s){
            var svc = s.svcNo;
            var displayName = (toTitleCase(s.fName||'') + ' ' + toTitleCase(s.lName||'')).trim() || 'Unknown';
            var rankLabel = s.rank_abbr || s.rank_name || '';

            var $col = $('<div class="col-md-6 col-lg-4" data-svcno="'+svc+'"></div>');
            $col.html(
                '<div class="card staff-post-card h-100">' +
                    '<div class="card-body">' +
                        '<div class="d-flex justify-content-between align-items-start mb-2">' +
                            '<div>' +
                                '<span class="badge bg-primary staff-badge me-1">'+svc+'</span>' +
                                '<strong>'+rankLabel+' '+displayName+'</strong>' +
                            '</div>' +
                            '<i class="fa fa-times-circle text-danger remove-staff-btn" title="Deselect this staff member"></i>' +
                        '</div>' +
                        '<div class="mb-2">' +
                            '<label class="form-label small mb-1">Unit *</label>' +
                            '<select class="form-select form-select-sm post-unit" name="unit['+svc+']" required>' +
                                unitOptionsHtml(s.unitId) +
                            '</select>' +
                        '</div>' +
                        '<div class="mb-2">' +
                            '<label class="form-label small mb-1">Position / Appointment *</label>' +
                            '<input type="text" class="form-control form-control-sm post-position" name="position['+svc+']" list="positionOptions" maxlength="50" placeholder="e.g. Company Commander" required>' +
                        '</div>' +
                        '<div class="mb-2">' +
                            '<label class="form-label small mb-1">With Powers Of <span class="text-muted">(optional)</span></label>' +
                            '<input type="text" class="form-control form-control-sm post-powers" name="with_powers_of['+svc+']" maxlength="100" placeholder="e.g. Officer Commanding">' +
                        '</div>' +
                        '<div>' +
                            '<label class="form-label small mb-1">Remarks <span class="text-muted">(optional)</span></label>' +
                            '<textarea class="form-control form-control-sm post-comment" name="comment['+svc+']" rows="2" placeholder="Any additional notes for this appointment"></textarea>' +
                        '</div>' +
                    '</div>' +
                '</div>'
            );
            return $col;
        }

        // Add/remove per-staff posting cards as selection changes, without
        // clobbering values already typed into cards that remain selected.
        function renderStaffDetailsPanel(){
            var selectedSvc = staffTable.rows({selected:true}).data().toArray().map(function(r){ return r.svcNo; });
            var $panel = $('#staffDetailsPanel');
            var existingSvc = $panel.children('[data-svcno]').map(function(){ return $(this).data('svcno').toString(); }).get();

            // Remove cards for staff no longer selected
            existingSvc.forEach(function(svc){
                if (selectedSvc.indexOf(svc) === -1) {
                    $panel.children('[data-svcno="'+svc+'"]').remove();
                }
            });

            // Add cards for newly selected staff
            selectedSvc.forEach(function(svc){
                if (existingSvc.indexOf(svc) === -1) {
                    var s = eligibleStaff.find(function(x){ return x.svcNo === svc; }) || { svcNo: svc };
                    $panel.append(buildStaffCard(s));
                }
            });

            $('#staffDetailsSection').toggle(selectedSvc.length > 0);
            $('#selectedStaffBadge').text(selectedSvc.length);
        }

        // Bulk-fill: apply the same unit/position to every currently selected staff card
        $('#applyBulkBtn').on('click', function(){
            var unit = $('#bulkUnit').val();
            var position = $('#bulkPosition').val().trim();
            if (!unit && !position) {
                alert('Choose a unit and/or type a position to apply first.');
                return;
            }
            $('#staffDetailsPanel [data-svcno]').each(function(){
                if (unit) $(this).find('.post-unit').val(unit);
                if (position) $(this).find('.post-position').val(position);
            });
        });

        // Remove-staff button inside a card deselects that row in the table
        $('#staffDetailsPanel').on('click', '.remove-staff-btn', function(){
            var svc = $(this).closest('[data-svcno]').data('svcno').toString();
            staffTable.rows().every(function(){
                if (String(this.data().svcNo) === svc) { this.deselect(); }
            });
        });

        // Update checkboxes + the per-staff posting panel whenever selection changes
        staffTable.on('select.dt deselect.dt draw', function(){
            syncCheckboxes();
            renderStaffDetailsPanel();
        });
        // Initial paint
        syncCheckboxes();
        renderStaffDetailsPanel();

        // Expose the DataTable instance for other inline code to reuse
        window.staffTable = staffTable;

        // Wire rank auto-submit
        // NOTE: appointments.php uses id="currentRank" (not current_rank). The
        // <select> itself only shows a loading spinner on change (see inline
        // onchange in appointments.php) - actual submission happens here, so
        // this listener is load-bearing, not just a resilience fallback.
        (function(){
            var rankSelect = document.getElementById('currentRank');
            var rankForm = document.getElementById('rankForm');
            if (rankSelect && rankForm) {
                rankSelect.addEventListener('change', function(){
                    if (this.value && this.value !== '') {
                        rankForm.submit();
                    }
                });
            }
        })();

        // Wire appointment type -> end date toggle
        // NOTE: appointments.php uses id="endDate" (not end_date).
        (function(){
            var apptTypeSelect = document.getElementById('appointment_type');
            var endDateField = document.getElementById('endDateField');
            var endDateInput = document.getElementById('endDate');
            if (!apptTypeSelect || !endDateField) return;

            function toggleEndDate() {
                var selected = apptTypeSelect.options[apptTypeSelect.selectedIndex];
                if (selected && selected.getAttribute('data-is-temporary') == '1') {
                    endDateField.style.display = '';
                    if (endDateInput && !endDateInput.value) {
                        var duration = selected.getAttribute('data-duration');
                        var apptDateInput = document.getElementById('appt_date');
                        if (duration && apptDateInput && apptDateInput.value) {
                            var d = new Date(apptDateInput.value);
                            d.setMonth(d.getMonth() + parseInt(duration, 10));
                            endDateInput.value = d.toISOString().slice(0, 10);
                        }
                    }
                } else {
                    endDateField.style.display = 'none';
                    if (endDateInput) endDateInput.value = '';
                }
            }

            apptTypeSelect.addEventListener('change', toggleEndDate);
            toggleEndDate();
        })();

        // Client-side guard: every selected staff member needs a unit and a
        // position before the form can be submitted. This mirrors (but does
        // not replace) the server-side validation in appointments.php, and
        // acts as a backstop behind the confirm-modal validation in
        // appointments.php's own inline script (which normally catches this
        // first, with a nicer non-blocking summary rather than alert()).
        //
        // FIX: alert()-based interruptions read as jarring/inconsistent next
        // to the rest of the page's inline-banner style, so this now shows a
        // dismissible in-page notice and scrolls/highlights the first
        // incomplete card instead of blocking with a native dialog.
        (function(){
            var form = document.getElementById('appointmentForm');
            if (!form) return;

            function showFormNotice(message) {
                var $notice = $('#formValidationNotice');
                if ($notice.length === 0) {
                    $notice = $('<div id="formValidationNotice" class="alert alert-danger" role="alert" tabindex="-1"></div>');
                    $(form).find('.text-end').first().before($notice);
                }
                $notice.html('<i class="fa fa-exclamation-triangle"></i> ' + message).show();
                $notice.get(0).scrollIntoView({ behavior: 'smooth', block: 'center' });
                $notice.trigger('focus');
            }

            form.addEventListener('submit', function(e){
                var selectedCount = staffTable.rows({selected:true}).count();
                if (selectedCount === 0) {
                    e.preventDefault();
                    showFormNotice('Please select at least one staff member.');
                    return;
                }

                var firstIncomplete = null;
                var incompleteCount = 0;

                $('#staffDetailsPanel [data-svcno]').each(function(){
                    var $card = $(this);
                    var unitVal = $card.find('.post-unit').val();
                    var positionVal = $card.find('.post-position').val().trim();
                    var incomplete = !unitVal || !positionVal;

                    $card.find('.staff-post-card').toggleClass('is-incomplete', incomplete);
                    if (incomplete) {
                        incompleteCount++;
                        if (!firstIncomplete) firstIncomplete = $card;
                    }
                });

                if (incompleteCount > 0) {
                    e.preventDefault();
                    showFormNotice('Please provide a Unit and Position for every selected staff member (' + incompleteCount + ' incomplete).');
                    if (firstIncomplete) {
                        firstIncomplete[0].scrollIntoView({ behavior: 'smooth', block: 'center' });
                    }
                } else {
                    $('#formValidationNotice').hide();
                }
            });
        })();
    };

})(window, jQuery);