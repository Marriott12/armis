<div class="mb-3">
    <h6>Academic Qualifications</h6>
    <div id="academic-list">
        <?php $numAcademic = max(count($_POST['academic_institution'] ?? []), count($academic ?? []), 1); ?>
        <?php for ($i = 0; $i < $numAcademic; $i++): ?>
            <div class="row mb-2">
                <div class="col">
                    <input type="text" class="form-control" name="academic_institution[]" placeholder="Institution" value="<?= old('academic_institution', $academic[$i]->institution ?? '') ?>">
                </div>
                <div class="col">
                    <input type="date" class="form-control" name="academic_start[]" placeholder="Start" value="<?= old('academic_start', $academic[$i]->date_start ?? '') ?>">
                </div>
                <div class="col">
                    <input type="date" class="form-control" name="academic_end[]" placeholder="End" value="<?= old('academic_end', $academic[$i]->date_end ?? '') ?>">
                </div>
                <div class="col">
                    <input type="text" class="form-control" name="academic_qualification[]" placeholder="Qualification" value="<?= old('academic_qualification', $academic[$i]->qualification ?? '') ?>">
                </div>
                <div class="col-auto">
                    <button type="button" class="btn btn-danger btn-sm remove-academic-row"><i class="fa fa-minus"></i></button>
                </div>
            </div>
        <?php endfor; ?>
    </div>
    <button type="button" id="add-academic-row" class="btn btn-outline-primary btn-sm mt-2"><i class="fa fa-plus"></i> Add Academic</button>
</div>

<div class="mb-3">
    <h6>Professional/Technical Qualifications</h6>
    <div id="proftech-list">
        <?php $numProftech = max(count($_POST['proftech_profession'] ?? []), count($proftech ?? []), 1); ?>
        <?php for ($i = 0; $i < $numProftech; $i++): ?>
            <div class="row mb-2">
                <div class="col">
                    <input type="text" class="form-control" name="proftech_profession[]" placeholder="Profession" value="<?= old('proftech_profession', $proftech[$i]->profession ?? '') ?>">
                </div>
                <div class="col">
                    <input type="text" class="form-control" name="proftech_course[]" placeholder="Course" value="<?= old('proftech_course', $proftech[$i]->course ?? '') ?>">
                </div>
                <div class="col">
                    <input type="text" class="form-control" name="proftech_letters[]" placeholder="Letters" value="<?= old('proftech_letters', $proftech[$i]->letters ?? '') ?>">
                </div>
                <div class="col">
                    <input type="text" class="form-control" name="proftech_institution[]" placeholder="Institution" value="<?= old('proftech_institution', $proftech[$i]->institution ?? '') ?>">
                </div>
                <div class="col">
                    <input type="text" class="form-control" name="proftech_year[]" placeholder="Year" value="<?= old('proftech_year', $proftech[$i]->year ?? '') ?>">
                </div>
                <div class="col-auto">
                    <button type="button" class="btn btn-danger btn-sm remove-proftech-row"><i class="fa fa-minus"></i></button>
                </div>
            </div>
        <?php endfor; ?>
    </div>
    <button type="button" id="add-proftech-row" class="btn btn-outline-primary btn-sm mt-2"><i class="fa fa-plus"></i> Add Prof/Tech</button>
</div>

<div class="mb-3">
    <h6>Military Courses</h6>
    <div id="milcourse-list">
        <?php $numMilcourse = max(count($_POST['milcourse_name'] ?? []), count($milcourse ?? []), 1); ?>
        <?php for ($i = 0; $i < $numMilcourse; $i++): ?>
            <div class="row mb-2">
                <div class="col">
                    <input type="text" class="form-control" name="milcourse_name[]" placeholder="Course Name" value="<?= old('milcourse_name', $milcourse[$i]->course ?? '') ?>">
                </div>
                <div class="col">
                    <input type="text" class="form-control" name="milcourse_institution[]" placeholder="Institution" value="<?= old('milcourse_institution', $milcourse[$i]->institution ?? '') ?>">
                </div>
                <div class="col">
                    <input type="date" class="form-control" name="milcourse_start[]" placeholder="Start" value="<?= old('milcourse_start', $milcourse[$i]->start_date ?? '') ?>">
                </div>
                <div class="col">
                    <input type="date" class="form-control" name="milcourse_end[]" placeholder="End" value="<?= old('milcourse_end', $milcourse[$i]->end_date ?? '') ?>">
                </div>
                <div class="col">
                    <input type="text" class="form-control" name="milcourse_result[]" placeholder="Result" value="<?= old('milcourse_result', $milcourse[$i]->result ?? '') ?>">
                </div>
                <div class="col">
                    <input type="text" class="form-control" name="milcourse_type[]" placeholder="Type" value="<?= old('milcourse_type', $milcourse[$i]->type ?? '') ?>">
                </div>
                <div class="col-auto">
                    <button type="button" class="btn btn-danger btn-sm remove-milcourse-row"><i class="fa fa-minus"></i></button>
                </div>
            </div>
        <?php endfor; ?>
    </div>
    <button type="button" id="add-milcourse-row" class="btn btn-outline-primary btn-sm mt-2"><i class="fa fa-plus"></i> Add Military Course</button>
</div>

<div class="mb-3">
    <h6>Appointments</h6>
    <div id="appointments-list">
        <?php $numAppt = max(count($_POST['appointment_name'] ?? []), count($appointments ?? []), 1); ?>
        <?php for ($i = 0; $i < $numAppt; $i++): ?>
            <div class="row mb-2">
                <div class="col">
                    <input type="text" class="form-control" name="appointment_name[]" placeholder="Appointment" value="<?= old('appointment_name', $appointments[$i]->appointment ?? '') ?>">
                </div>
                <div class="col">
                    <select class="form-select" name="appointment_unit[]">
                        <option value="">Unit</option>
                        <?php foreach ($units as $unit): ?>
                            <option value="<?= $unit->unitID ?>" <?= old('appointment_unit', $appointments[$i]->unitID ?? '') == $unit->unitID ? 'selected' : '' ?>>
                                <?= htmlspecialchars($unit->unitName) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col">
                    <input type="date" class="form-control" name="appointment_start[]" placeholder="Start" value="<?= old('appointment_start', $appointments[$i]->start_date ?? '') ?>">
                </div>
                <div class="col">
                    <input type="date" class="form-control" name="appointment_end[]" placeholder="End" value="<?= old('appointment_end', $appointments[$i]->end_date ?? '') ?>">
                </div>
                <div class="col">
                    <input type="text" class="form-control" name="appointment_authority[]" placeholder="Authority" value="<?= old('appointment_authority', $appointments[$i]->authority ?? '') ?>">
                </div>
                <div class="col-auto">
                    <button type="button" class="btn btn-danger btn-sm remove-appointment-row"><i class="fa fa-minus"></i></button>
                </div>
            </div>
        <?php endfor; ?>
    </div>
    <button type="button" id="add-appointment-row" class="btn btn-outline-primary btn-sm mt-2"><i class="fa fa-plus"></i> Add Appointment</button>
</div>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Remove academic/proftech/milcourse/appointment row handlers
    function removeRowHandler(listId, className) {
        document.getElementById(listId).addEventListener('click', function(e) {
            if (e.target.classList.contains(className) || (e.target.closest && e.target.closest('.' + className))) {
                var btn = e.target.closest('.' + className);
                btn.closest('.row').remove();
            }
        });
    }
    removeRowHandler('academic-list', 'remove-academic-row');
    removeRowHandler('proftech-list', 'remove-proftech-row');
    removeRowHandler('milcourse-list', 'remove-milcourse-row');
    removeRowHandler('appointments-list', 'remove-appointment-row');
});
</script>