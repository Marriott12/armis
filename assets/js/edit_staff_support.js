// Removed duplicate EditStaffForm declaration to fix SyntaxError
if (typeof EditStaffForm === 'undefined') {
const EditStaffForm = (() => {
    const steps = document.querySelectorAll('.form-step');
    const stepIndicators = document.querySelectorAll('.step');
    let currentStep = 0;

    function escapeHtml(text) {
        const map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return text ? text.toString().replace(/[&<>"']/g, m => map[m]) : '';
    }


    function validateForm(showResults = false) {
        let isValid = true;
        const report = [];

        const requiredFields = document.querySelectorAll('[required]');
        requiredFields.forEach(field => {
            const feedbackEl = document.getElementById(`${field.id}-error`);
            if (!field.value.trim()) {
                isValid = false;
                field.classList.add('is-invalid');
                feedbackEl && (feedbackEl.textContent = 'This field is required');
                report.push(`Missing: ${field.name}`);
            } else {
                field.classList.remove('is-invalid');
                feedbackEl && (feedbackEl.textContent = '');
            }
        });

        const counts = ['operationsList', 'deploymentsList', 'educationList', 'skillsList'].map(id => {
            const count = document.getElementById(id)?.children.length || 0;
            return `${id.replace('List', '')}: ${count}`;
        });

        report.push(...counts);

        const form = document.getElementById('editStaffForm');
        const data = new FormData(form);
        const csrf = data.get('csrf_token');
        const svcNo = data.get('svcNo');

        if (!csrf) {
            isValid = false;
            report.push('Missing CSRF token');
        } else {
            report.push(`CSRF OK: ${csrf.slice(0, 10)}...`);
        }

        if (!svcNo) {
            isValid = false;
            report.push('Missing Service Number');
        } else {
            report.push(`Service Number: ${svcNo}`);
        }

        if (showResults) {
            alert(report.join('\n') + `\nForm is ${isValid ? 'valid ✅' : 'invalid ❌'}`);
        }

        return isValid;
    }

    const DynamicFields = (() => {
        const templates = {
            operations: (i, d = {}, options = []) => `
                <div class="row g-2 mb-2">
                    <div class="col-md-3">
                        <select name="operations[${i}][operation_id]" class="form-select">
                            <option value="">Select Operation</option>
                            ${options.map(op => `
                                <option value="${op.id}" ${d.operation_id == op.id ? 'selected' : ''}>${escapeHtml(op.name)} (${escapeHtml(op.code)})</option>
                            `).join('')}
                        </select>
                    </div>
                    <div class="col-md-2"><input type="text" name="operations[${i}][role]" class="form-control" placeholder="Role" value="${escapeHtml(d.role || '')}"></div>
                    <div class="col-md-2"><input type="date" name="operations[${i}][start_date]" class="form-control" value="${escapeHtml(d.start_date || '')}"></div>
                    <div class="col-md-2"><input type="date" name="operations[${i}][end_date]" class="form-control" value="${escapeHtml(d.end_date || '')}"></div>
                    <div class="col-md-2"><input type="number" name="operations[${i}][performance_rating]" class="form-control" placeholder="Rating" value="${escapeHtml(d.performance_rating || '')}"></div>
                    <div class="col-md-1"><input type="text" name="operations[${i}][remarks]" class="form-control" placeholder="Remarks" value="${escapeHtml(d.remarks || '')}"></div>
                    <div class="col-md-1"><button type="button" class="btn btn-danger" onclick="this.closest('.row').remove()"><i class="fa fa-trash"></i></button></div>
                </div>
            `,
            deployments: (i, d = {}) => `
                <div class="row g-2 mb-2">
                    <div class="col-md-2"><input type="text" name="deployments[${i}][deployment_name]" class="form-control" placeholder="Deployment Name" value="${escapeHtml(d.deployment_name || '')}"></div>
                    <div class="col-md-2"><input type="text" name="deployments[${i}][mission_type]" class="form-control" placeholder="Mission Type" value="${escapeHtml(d.mission_type || '')}"></div>
                    <div class="col-md-2"><input type="text" name="deployments[${i}][location]" class="form-control" placeholder="Location" value="${escapeHtml(d.location || '')}"></div>
                    <div class="col-md-2"><input type="text" name="deployments[${i}][country]" class="form-control" placeholder="Country" value="${escapeHtml(d.country || '')}"></div>
                    <div class="col-md-2"><input type="date" name="deployments[${i}][start_date]" class="form-control" value="${escapeHtml(d.start_date || '')}"></div>
                    <div class="col-md-2"><input type="date" name="deployments[${i}][end_date]" class="form-control" value="${escapeHtml(d.end_date || '')}"></div>
                    <div class="col-md-1"><input type="number" name="deployments[${i}][duration_months]" class="form-control" placeholder="Months" value="${escapeHtml(d.duration_months || '')}"></div>
                    <div class="col-md-2"><input type="text" name="deployments[${i}][deployment_status]" class="form-control" placeholder="Status" value="${escapeHtml(d.deployment_status || '')}"></div>
                    <div class="col-md-2"><input type="text" name="deployments[${i}][rank_during_deployment]" class="form-control" placeholder="Rank" value="${escapeHtml(d.rank_during_deployment || '')}"></div>
                    <div class="col-md-2"><input type="text" name="deployments[${i}][role_during_deployment]" class="form-control" placeholder="Role" value="${escapeHtml(d.role_during_deployment || '')}"></div>
                    <div class="col-md-2"><input type="text" name="deployments[${i}][commanding_officer]" class="form-control" placeholder="CO" value="${escapeHtml(d.commanding_officer || '')}"></div>
                    <div class="col-md-2"><input type="number" name="deployments[${i}][deployment_allowance]" class="form-control" placeholder="Allowance" value="${escapeHtml(d.deployment_allowance || '')}"></div>
                    <div class="col-md-2"><input type="text" name="deployments[${i}][notes]" class="form-control" placeholder="Notes" value="${escapeHtml(d.notes || '')}"></div>
                    <div class="col-md-1"><button type="button" class="btn btn-danger" onclick="this.closest('.row').remove()"><i class="fa fa-trash"></i></button></div>
                </div>
            `,
            education: (i, d = {}) => `
                <div class="row g-2 mb-2">
                    <div class="col-md-3"><input type="text" name="education[${i}][institution]" class="form-control" placeholder="Institution" value="${escapeHtml(d.institution || '')}"></div>
                    <div class="col-md-3"><input type="text" name="education[${i}][qualification]" class="form-control" placeholder="Qualification" value="${escapeHtml(d.qualification || '')}"></div>
                    <div class="col-md-2"><input type="text" name="education[${i}][field_of_study]" class="form-control" placeholder="Field" value="${escapeHtml(d.field_of_study || '')}"></div>
                    <div class="col-md-1"><input type="number" name="education[${i}][year_started]" class="form-control" placeholder="Start" value="${escapeHtml(d.year_started || '')}"></div>
                    <div class="col-md-1"><input type="number" name="education[${i}][year_completed]" class="form-control" placeholder="End" value="${escapeHtml(d.year_completed || '')}"></div>
                    <div class="col-md-1"><input type="text" name="education[${i}][grade_obtained]" class="form-control" placeholder="Grade" value="${escapeHtml(d.grade_obtained || '')}"></div>
                    <div class="col-md-1"><input type="checkbox" name="education[${i}][is_highest_qualification]" value="1" ${d.is_highest_qualification ? 'checked' : ''}> Highest</div>
                    <div class="col-md-1"><button type="button" class="btn btn-danger" onclick="this.closest('.row').remove()"><i class="fa fa-trash"></i></button></div>
                </div>
            `,
            skills: (i, d = {}) => `
                <div class="row g-2 mb-2">
                    <div class="col-md-3"><input type="text" name="skills[${i}][course_name]" class="form-control" placeholder="Course" value="${escapeHtml(d.course_name || '')}"></div>
                    <div class="col-md-2"><input type="text" name="skills[${i}][course_type]" class="form-control" placeholder="Type" value="${escapeHtml(d.course_type || '')}"></div>
                    <div class="col-md-2"><input type="date" name="skills[${i}][start_date]" class="form-control" value="${escapeHtml(d.start_date || '')}"></div>
                    <div class="col-md-2"><input type="date" name="skills[${i}][end_date]" class="form-control" value="${escapeHtml(d.end_date || '')}"></div>
                    <div class="col-md-1"><input type="number" name="skills[${i}][duration_days]" class="form-control" placeholder="Days" value="${escapeHtml(d.duration_days || '')}"></div>
                    <div class="col-md-1"><button type="button" class="btn btn-danger" onclick="this.closest('.row').remove()"><i class="fa fa-trash"></i></button></div>
                </div>
            `
        };

        const counters = { operations: 0, deployments: 0, education: 0, skills: 0 };

        function addRow(section, data = {}) {
            const container = document.getElementById(`${section}List`);
            if (!container || !templates[section]) return;
            const index = counters[section]++;
            const html = templates[section](index, data, window.operationsOptions || []);
            container.insertAdjacentHTML('beforeend', html);
        }

        function init() {
            ['Operation', 'Deployment', 'Education', 'Skill'].forEach(type => {
                const btn = document.getElementById(`add${type}Btn`);
                if (btn) btn.addEventListener('click', () => addRow(type.toLowerCase() + 's'));
            });
        }

        return { addRow, init };
    })();

    function init() {
        goToStep(0);
        DynamicFields.init();

    // Step navigation is now handled by multi-step-form.js
        document.getElementById('validateFormBtn')?.addEventListener('click', () => validateForm(true));

        document.getElementById('editStaffForm')?.addEventListener('submit', function (e) {
            if (!validateForm()) {
                e.preventDefault();
                window.scrollTo(0, 0);
            }
        });
    }

    return { init, goToStep, validateForm, escapeHtml };
})();

document.addEventListener('DOMContentLoaded', EditStaffForm.init);
}
    