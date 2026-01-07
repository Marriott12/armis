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

    function formatStaffResult(staff) {
        if (!staff.id) return staff.text;
        return $(
            '<div class="d-flex align-items-center">' +
                '<div class="flex-grow-1">' +
                    '<div class="fw-bold">' + staff.svcNo + ' - ' + staff.lName + ' ' + staff.fName + '</div>' +
                    '<small class="text-muted">' + (staff.unit_name || 'No Unit') + '</small>' +
                '</div>' +
            '</div>'
        );
    }

    // Main init function (called from shared/footer.php)
    window.initAppointmentsPage = function() {
        var data = window.appointmentsServerData || {};
        var unitsData = data.unitsData || [];
        var eligibleStaff = data.eligibleStaff || [];
        var currentRankId = data.currentRankId || '';
        var standardPositions = data.standardPositions || [];
        var preselectedStaff = data.preselectedStaff || [];

        // All the page logic moved here. For brevity we only initialize the DataTable and
        // wire select/deselect handlers relevant to the user's request (columns + Select extension).

        if (typeof $.fn.DataTable === 'undefined') {
            console.error('DataTables not available');
            return;
        }

        // Normalize eligibleStaff rows so the DataTable always has unit_name, corps and status fields
        try {
            var unitLookup = {};
            (unitsData || []).forEach(function(u){
                var id = u.unitID || u.unit_id || u.id;
                var name = u.unitName || u.code || u.unitName || u.unit_name || u.name || '';
                if (id) unitLookup[String(id)] = name;
            });

            eligibleStaff = (eligibleStaff || []).map(function(row){
                // ensure plain object
                var r = Object.assign({}, row);
                if ((!r.unit_name || r.unit_name === null) && r.unitId) {
                    r.unit_name = unitLookup[String(r.unitId)] || null;
                }
                r.unit_name = r.unit_name || 'N/A';
                r.corps = r.corps || '';
                r.status = r.status || 'Active';
                r.rank_name = r.rank_name || r.rankId || '';
                r.rank_abbr = r.rank_abbr || r.rank_name || '';
                return r;
            });
        } catch (ex) {
            console.warn('appointments.init: failed to normalize eligibleStaff', ex);
        }

        // Debug: Log first staff record to check data structure
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
                { data: null, orderable: false, className: 'select-checkbox text-center', defaultContent: '' },
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

    // Hidden inputs container
        var $inputs = $('#selectedStaffInputs');

        function addSelectedStaffInput(svcNo){
            if ($inputs.find('input[value="'+svcNo+'"]').length===0){
                $inputs.append('<input type="hidden" name="selected_staff[]" value="'+svcNo+'">');
            }
        }
        function removeSelectedStaffInput(svcNo){
            $inputs.find('input[value="'+svcNo+'"]').remove();
        }

        staffTable.on('select', function(e, dt, type, indexes){
            if (type !== 'row') return;
            var rows = staffTable.rows(indexes).data().toArray();
            rows.forEach(function(r){ addSelectedStaffInput(r.service_number); });
            $('#selectionCount').text(staffTable.rows({selected:true}).count());
        });

        staffTable.on('deselect', function(e, dt, type, indexes){
            if (type !== 'row') return;
            var rows = staffTable.rows(indexes).data().toArray();
            rows.forEach(function(r){ removeSelectedStaffInput(r.service_number); });
            $('#selectionCount').text(staffTable.rows({selected:true}).count());
        });

        // If preselected staff were posted, select them in the table
        if (preselectedStaff && preselectedStaff.length>0){
            // build lookup to map service_number -> row index
            var lookup = {};
            staffTable.rows().every(function(idx){ var d = this.data(); lookup[d.service_number] = idx; });
            preselectedStaff.forEach(function(svc){ if (typeof lookup[svc] !== 'undefined'){ staffTable.row(lookup[svc]).select(); } });
        }

        // If server didn't provide eligibleStaff but current rank exists, try AJAX fallback
        if ((eligibleStaff === null || eligibleStaff.length===0) && currentRankId){
            $.ajax({ url: 'ajax_get_staff_by_rank.php', data: { rank_id: currentRankId }, type: 'GET', dataType: 'json' })
            .done(function(resp){ if (Array.isArray(resp) && resp.length>0){ var mapped = resp.map(function(row){ return { service_number: row.service_number||row.id||'', first_name: row.first_name||'', last_name: row.last_name||'', rank_name: row.rank_name||row.rank_id||'', rank_abbr: row.rank_abbr||row.rank_name||'', unit_name: row.unit_name||'', corps: row.corps||row.corps_id||'', appt: row.appt||row.appointment||'', status: row.status||row.svcStatus||'Active' }; }); staffTable.clear(); staffTable.rows.add(mapped).draw(); $('#totalStaffCount').text(mapped.length); } })
            .fail(function(xhr, status, err){ console.error('Failed to load staff via AJAX:', status, err); });
        } else {
            $('#totalStaffCount').text(staffTable.rows().count());
        }

        // Select/deselect all buttons
        $('#selectAllBtn').on('click', function(){ staffTable.rows().select(); });
        $('#deselectAllBtn').on('click', function(){ staffTable.rows().deselect(); });

        // update panels when selection changes via DataTables API
        staffTable.on('select.dt deselect.dt draw', function(){
            var selectedSvc = staffTable.rows({selected:true}).data().toArray().map(function(r){ return r.service_number; });
            // simple render of staff panels: keep it lightweight here
            var panel = $('#staffDetailsPanel'); panel.empty();
            selectedSvc.forEach(function(svc){ var s = eligibleStaff.find(function(x){ return x.service_number===svc; }) || {}; panel.append('<div class="card mb-2"><div class="card-body"><strong>'+svc+'</strong> '+(s.last_name||'')+' '+(s.first_name||'')+'</div></div>'); });
        });

        // Expose the DataTable instance for other inline code to reuse
        window.staffTable = staffTable;

        // Wire rank auto-submit (migrate inline handler from appointments.php)
        (function(){
            var rankSelect = document.getElementById('current_rank');
            var rankForm = document.getElementById('rankForm');
            if (rankSelect && rankForm) {
                rankSelect.addEventListener('change', function(){
                    if (this.value && this.value !== '') {
                        rankForm.submit();
                    }
                });
            }
        })();

        // Wire appointment type -> end date toggle (migrated from inline DOMContentLoaded handler)
        (function(){
            var apptTypeSelect = document.getElementById('appointment_type');
            var endDateField = document.getElementById('endDateField');
            var endDateInput = document.getElementById('end_date');
            if (!apptTypeSelect || !endDateField) return;

            function toggleEndDate() {
                var selected = apptTypeSelect.options[apptTypeSelect.selectedIndex];
                if (selected && selected.getAttribute('data-is-temporary') == '1') {
                    endDateField.style.display = '';
                } else {
                    endDateField.style.display = 'none';
                    if (endDateInput) endDateInput.value = '';
                }
            }

            apptTypeSelect.addEventListener('change', toggleEndDate);
            // call once to set initial state
            toggleEndDate();
        })();
    };

})(window, jQuery);
