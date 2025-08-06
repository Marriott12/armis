<div class="tab-pane fade p-3 border rounded <?=isset($tabErrors['academic']) ? 'show active' : ''?>" id="academic" role="tabpanel">
    <h5 class="mb-3 text-success">Academic Qualifications</h5>
    <div id="academicList"></div>
    <button type="button" class="btn btn-outline-secondary btn-sm mb-3" onclick="addAcademic()">Add Academic Qualification</button>
    
    <h5 class="mb-3 text-success">Professional/ Technical Qualifications</h5>
    <div id="profTechList"></div>
    <button type="button" class="btn btn-outline-secondary btn-sm mb-3" onclick="addProfTech()">Add Professional/Technical Qualification</button>
    
    <h5 class="mb-3 text-success">Military Training/Courses</h5>
    <div id="milCourseList"></div>
    <button type="button" class="btn btn-outline-secondary btn-sm mb-3" onclick="addMilCourse()">Add Military Course</button>
    
    <h5 class="mb-3 text-success">Skills & Competencies</h5>
    <div id="skillsList"></div>
    <button type="button" class="btn btn-outline-secondary btn-sm mb-3" onclick="addSkill()">Add Skill/Competency</button>
</div>

<script>
class AcademicFieldManager {
    constructor() {
        this.init();
    }
    
    init() {
        this.setupConditionalValidation();
        // Don't load default rows - only add when button is clicked
    }
    
    setupConditionalValidation() {
        // Set up conditional field validation for academics
        document.addEventListener('input', (e) => {
            if (e.target.matches('[name*="education_"], [name*="training_"], [name*="skill_"]')) {
                this.validateAcademicRow(e.target);
            }
        });
        
        document.addEventListener('change', (e) => {
            if (e.target.matches('[name*="education_"], [name*="training_"], [name*="skill_"]')) {
                this.validateAcademicRow(e.target);
            }
        });
    }
    
    validateAcademicRow(changedField) {
        const row = changedField.closest('.row');
        if (!row) return;
        
        const fieldName = changedField.name;
        
        // Education qualification validation
        if (fieldName.includes('education_')) {
            this.validateEducationFields(row);
        }
        // Training/Course validation  
        else if (fieldName.includes('training_')) {
            this.validateTrainingFields(row);
        }
        // Skills validation
        else if (fieldName.includes('skill_')) {
            this.validateSkillFields(row);
        }
    }
    
    validateEducationFields(row) {
        const institution = row.querySelector('[name="education_institution[]"]');
        const qualification = row.querySelector('[name="education_qualification[]"]');
        const level = row.querySelector('[name="education_level[]"]');
        const yearStarted = row.querySelector('[name="education_year_started[]"]');
        const yearCompleted = row.querySelector('[name="education_year_completed[]"]');
        
        // If any field is filled, key fields become required
        const hasAnyValue = institution.value.trim() || qualification.value.trim() || 
                           level.value || yearStarted.value || yearCompleted.value;
        
        if (hasAnyValue) {
            this.setFieldRequired(institution, true, 'Institution name is required');
            this.setFieldRequired(qualification, true, 'Qualification is required');
            this.setFieldRequired(level, true, 'Education level is required');
            
            // Validate year range
            if (yearStarted.value && yearCompleted.value) {
                if (parseInt(yearStarted.value) > parseInt(yearCompleted.value)) {
                    this.showFieldError(yearCompleted, 'Completion year must be after start year');
                } else {
                    this.clearFieldError(yearCompleted);
                }
            }
        } else {
            this.setFieldRequired(institution, false);
            this.setFieldRequired(qualification, false);
            this.setFieldRequired(level, false);
        }
    }
    
    validateTrainingFields(row) {
        const courseName = row.querySelector('[name="training_course_name[]"]');
        const courseCode = row.querySelector('[name="training_course_code[]"]');
        const startDate = row.querySelector('[name="training_start_date[]"]');
        const endDate = row.querySelector('[name="training_end_date[]"]');
        const status = row.querySelector('[name="training_status[]"]');
        
        const hasAnyValue = courseName.value.trim() || courseCode.value.trim() || 
                           startDate.value || endDate.value || status.value;
        
        if (hasAnyValue) {
            this.setFieldRequired(courseName, true, 'Course name is required');
            this.setFieldRequired(status, true, 'Training status is required');
            
            // Validate date range
            if (startDate.value && endDate.value) {
                if (new Date(startDate.value) > new Date(endDate.value)) {
                    this.showFieldError(endDate, 'End date must be after start date');
                } else {
                    this.clearFieldError(endDate);
                }
            }
        } else {
            this.setFieldRequired(courseName, false);
            this.setFieldRequired(status, false);
        }
    }
    
    validateSkillFields(row) {
        const skillName = row.querySelector('[name="skill_name[]"]');
        const skillCategory = row.querySelector('[name="skill_category[]"]');
        const proficiencyLevel = row.querySelector('[name="skill_proficiency[]"]');
        
        const hasAnyValue = skillName.value.trim() || skillCategory.value || proficiencyLevel.value;
        
        if (hasAnyValue) {
            this.setFieldRequired(skillName, true, 'Skill name is required');
            this.setFieldRequired(skillCategory, true, 'Skill category is required');
            this.setFieldRequired(proficiencyLevel, true, 'Proficiency level is required');
        } else {
            this.setFieldRequired(skillName, false);
            this.setFieldRequired(skillCategory, false);
            this.setFieldRequired(proficiencyLevel, false);
        }
    }
    
    setFieldRequired(field, required, message = '') {
        if (required) {
            field.setAttribute('required', 'required');
            field.setAttribute('data-conditional-required', 'true');
            if (message) {
                field.setAttribute('title', message);
            }
            field.classList.add('required-field');
        } else {
            field.removeAttribute('required');
            field.removeAttribute('data-conditional-required');
            field.removeAttribute('title');
            field.classList.remove('required-field');
            this.clearFieldError(field);
        }
    }
    
    showFieldError(field, message) {
        this.clearFieldError(field);
        field.classList.add('is-invalid');
        
        const errorDiv = document.createElement('div');
        errorDiv.className = 'invalid-feedback';
        errorDiv.textContent = message;
        field.parentNode.appendChild(errorDiv);
    }
    
    clearFieldError(field) {
        field.classList.remove('is-invalid');
        const errorDiv = field.parentNode.querySelector('.invalid-feedback');
        if (errorDiv) {
            errorDiv.remove();
        }
    }
}

// Academic Qualifications (maps to staff_education table)
function addAcademic() {
    const div = document.createElement('div');
    div.className = 'row mb-2 align-items-end';
    div.innerHTML = `
        <div class="col-md-3 mb-2">
            <input type="text" name="education_institution[]" class="form-control form-control-sm" placeholder="Institution" title="Educational institution name">
        </div>
        <div class="col-md-2 mb-2">
            <select name="education_level[]" class="form-select form-select-sm" title="Level of education">
                <option value="">Level</option>
                <option value="Primary">Primary</option>
                <option value="Secondary">Secondary</option>
                <option value="Certificate">Certificate</option>
                <option value="Diploma">Diploma</option>
                <option value="Degree">Degree</option>
                <option value="Masters">Masters</option>
                <option value="PhD">PhD</option>
                <option value="Professional">Professional</option>
            </select>
        </div>
        <div class="col-md-2 mb-2">
            <input type="text" name="education_qualification[]" class="form-control form-control-sm" placeholder="Qualification" title="Qualification obtained">
        </div>
        <div class="col-md-2 mb-2">
            <input type="text" name="education_field_of_study[]" class="form-control form-control-sm" placeholder="Field of Study" title="Field of study">
        </div>
        <div class="col-md-1 mb-2">
            <input type="number" name="education_year_started[]" class="form-control form-control-sm" placeholder="Start" min="1950" max="${new Date().getFullYear()}" title="Year started">
        </div>
        <div class="col-md-1 mb-2">
            <input type="number" name="education_year_completed[]" class="form-control form-control-sm" placeholder="End" min="1950" max="${new Date().getFullYear() + 10}" title="Year completed">
        </div>
        <div class="col-auto mb-2">
            <button type="button" class="btn btn-danger btn-sm btn-remove-block" title="Remove this qualification">
                <i class="fa fa-times"></i>
            </button>
        </div>
    `;
    document.getElementById('academicList').appendChild(div);
}

// Professional/Technical Qualifications (maps to staff_education table with Professional level)
function addProfTech() {
    const div = document.createElement('div');
    div.className = 'row mb-2 align-items-end';
    div.innerHTML = `
        <div class="col-md-3 mb-2">
            <input type="text" name="education_institution[]" class="form-control form-control-sm" placeholder="Institution" title="Training institution or certifying body">
        </div>
        <div class="col-md-3 mb-2">
            <input type="text" name="education_qualification[]" class="form-control form-control-sm" placeholder="Professional Qualification" title="Professional qualification or certification">
        </div>
        <div class="col-md-2 mb-2">
            <input type="text" name="education_field_of_study[]" class="form-control form-control-sm" placeholder="Field/Profession" title="Professional field">
        </div>
        <div class="col-md-2 mb-2">
            <input type="number" name="education_year_completed[]" class="form-control form-control-sm" placeholder="Year" min="1950" max="${new Date().getFullYear()}" title="Year obtained">
        </div>
        <div class="col-md-1 mb-2">
            <input type="text" name="education_certificate_number[]" class="form-control form-control-sm" placeholder="Cert #" title="Certificate number">
        </div>
        <div class="col-auto mb-2">
            <button type="button" class="btn btn-danger btn-sm btn-remove-block" title="Remove this qualification">
                <i class="fa fa-times"></i>
            </button>
        </div>
        <input type="hidden" name="education_level[]" value="Professional">
    `;
    document.getElementById('profTechList').appendChild(div);
}

// Military Training/Courses (maps to training_records table)
function addMilCourse() {
    const div = document.createElement('div');
    div.className = 'row mb-2 align-items-end';
    div.innerHTML = `
        <div class="col-md-2 mb-2">
            <input type="text" name="training_course_name[]" class="form-control form-control-sm" placeholder="Course Name" title="Name of military course">
        </div>
        <div class="col-md-2 mb-2">
            <input type="text" name="training_course_code[]" class="form-control form-control-sm" placeholder="Course Code" title="Course code">
        </div>
        <div class="col-md-2 mb-2">
            <input type="date" name="training_start_date[]" class="form-control form-control-sm" title="Course start date">
        </div>
        <div class="col-md-2 mb-2">
            <input type="date" name="training_end_date[]" class="form-control form-control-sm" title="Course completion date">
        </div>
        <div class="col-md-1 mb-2">
            <select name="training_status[]" class="form-select form-select-sm" title="Training status">
                <option value="">Status</option>
                <option value="scheduled">Scheduled</option>
                <option value="in_progress">In Progress</option>
                <option value="completed">Completed</option>
                <option value="failed">Failed</option>
                <option value="cancelled">Cancelled</option>
            </select>
        </div>
        <div class="col-md-1 mb-2">
            <input type="number" name="training_score[]" class="form-control form-control-sm" placeholder="Score" min="0" max="100" step="0.1" title="Course score/grade">
        </div>
        <div class="col-md-1 mb-2">
            <input type="text" name="training_location[]" class="form-control form-control-sm" placeholder="Location" title="Training location">
        </div>
        <div class="col-auto mb-2">
            <button type="button" class="btn btn-danger btn-sm btn-remove-block" title="Remove this course">
                <i class="fa fa-times"></i>
            </button>
        </div>
    `;
    document.getElementById('milCourseList').appendChild(div);
}

// Skills & Competencies (maps to staff_skills table)
function addSkill() {
    const div = document.createElement('div');
    div.className = 'row mb-2 align-items-end';
    div.innerHTML = `
        <div class="col-md-3 mb-2">
            <input type="text" name="skill_name[]" class="form-control form-control-sm" placeholder="Skill/Competency" title="Name of skill or competency">
        </div>
        <div class="col-md-2 mb-2">
            <select name="skill_category[]" class="form-select form-select-sm" title="Category of skill">
                <option value="">Category</option>
                <option value="Technical">Technical</option>
                <option value="Leadership">Leadership</option>
                <option value="Communication">Communication</option>
                <option value="Military">Military</option>
                <option value="Professional">Professional</option>
                <option value="Other">Other</option>
            </select>
        </div>
        <div class="col-md-2 mb-2">
            <select name="skill_proficiency[]" class="form-select form-select-sm" title="Proficiency level">
                <option value="">Proficiency</option>
                <option value="Beginner">Beginner</option>
                <option value="Intermediate">Intermediate</option>
                <option value="Advanced">Advanced</option>
                <option value="Expert">Expert</option>
            </select>
        </div>
        <div class="col-md-2 mb-2">
            <input type="number" name="skill_years_experience[]" class="form-control form-control-sm" placeholder="Years Exp." min="0" max="50" title="Years of experience">
        </div>
        <div class="col-md-2 mb-2">
            <input type="text" name="skill_certification[]" class="form-control form-control-sm" placeholder="Certification" title="Related certification">
        </div>
        <div class="col-auto mb-2">
            <button type="button" class="btn btn-danger btn-sm btn-remove-block" title="Remove this skill">
                <i class="fa fa-times"></i>
            </button>
        </div>
    `;
    document.getElementById('skillsList').appendChild(div);
}

// Remove handler for all dynamic sections (matching children/dependants style)
function addDynamicRemoveHandler(listId) {
    $('#' + listId).on('click', '.btn-remove-block', function() {
        $(this).closest('.row').remove();
    });
}

// Initialize the academic field manager when the page loads
document.addEventListener('DOMContentLoaded', function() {
    new AcademicFieldManager();
    
    // Add remove handlers for academic sections
    ['academicList', 'profTechList', 'milCourseList', 'skillsList'].forEach(addDynamicRemoveHandler);
});
</script>