<div class="tab-pane fade p-3 border rounded <?=isset($tabErrors['academic']) ? 'show active' : ''?>" id="academic" role="tabpanel">
    <h5 class="mb-3 text-success">Academic Qualifications</h5>
    <div id="academicList"></div>
    <button type="button" class="btn btn-outline-secondary btn-sm mb-3" onclick="addAcademic()">Add Academic Qualification</button>
    <h5 class="mb-3 text-success">Professional/ Technical Qualifications</h5>
    <div id="profTechList"></div>
    <button type="button" class="btn btn-outline-secondary btn-sm mb-3" onclick="addProfTech()">Add Professional/Technical Qualification</button>
    <h5 class="mb-3 text-success">Military Courses</h5>
    <div id="milCourseList"></div>
    <button type="button" class="btn btn-outline-secondary btn-sm mb-3" onclick="addMilCourse()">Add Military Course</button>
    <h5 class="mb-3 text-success">Trade/ Group Classifications</h5>
    <div id="tradeGroupList"></div>
    <button type="button" class="btn btn-outline-secondary btn-sm mb-3" onclick="addTradeGroup()">Add Trade/Group</button>
</div>
<script>
    function addAcademic() {
    const div = document.createElement('div');
    div.className = 'row mb-2';
    div.innerHTML = `
        <div class="col-md-3 mb-2"><input type="text" name="academic_institution[]" class="form-control form-control-sm" placeholder="Institution"></div>
        <div class="col-md-2 mb-2"><input type="month" name="academic_start[]" class="form-control form-control-sm" placeholder="Start"></div>
        <div class="col-md-2 mb-2"><input type="month" name="academic_end[]" class="form-control form-control-sm" placeholder="End"></div>
        <div class="col-md-3 mb-2"><input type="text" name="academic_qualification[]" class="form-control form-control-sm" placeholder="Qualification"></div>
        <div class="col-md-2 mb-2"><button type="button" class="btn btn-danger btn-sm" onclick="this.closest('.row').remove()">Remove</button></div>
    `;
    document.getElementById('academicList').appendChild(div);
}
function addProfTech() {
    const div = document.createElement('div');
    div.className = 'row mb-2';
    div.innerHTML = `
        <div class="col-md-3 mb-2"><input type="text" name="proftech_profession[]" class="form-control form-control-sm" placeholder="Profession"></div>
        <div class="col-md-3 mb-2"><input type="text" name="proftech_course[]" class="form-control form-control-sm" placeholder="Course/Qualification"></div>
        <div class="col-md-2 mb-2"><input type="text" name="proftech_institution[]" class="form-control form-control-sm" placeholder="Institution"></div>
        <div class="col-md-2 mb-2"><input type="date" name="proftech_year[]" class="form-control form-control-sm" placeholder="Year"></div>
        <div class="col-md-2 mb-2"><button type="button" class="btn btn-danger btn-sm" onclick="this.closest('.row').remove()">Remove</button></div>
    `;
    document.getElementById('profTechList').appendChild(div);
}
function addMilCourse() {
    const div = document.createElement('div');
    div.className = 'row mb-2';
    div.innerHTML = `
        <div class="col-md-2 mb-2"><input type="text" name="milcourse_name[]" class="form-control form-control-sm" placeholder="Course Name"></div>
        <div class="col-md-2 mb-2"><input type="text" name="milcourse_institution[]" class="form-control form-control-sm" placeholder="Institution"></div>
        <div class="col-md-2 mb-2"><input type="date" name="milcourse_start[]" class="form-control form-control-sm" placeholder="Start Date"></div>
        <div class="col-md-2 mb-2"><input type="date" name="milcourse_end[]" class="form-control form-control-sm" placeholder="End Date"></div>
        <div class="col-md-2 mb-2"><input type="text" name="milcourse_result[]" class="form-control form-control-sm" placeholder="Result"></div>
        <div class="col-md-1 mb-2"><input type="text" name="milcourse_type[]" class="form-control form-control-sm" placeholder="Type"></div>
        <div class="col-md-1 mb-2"><button type="button" class="btn btn-danger btn-sm" onclick="this.closest('.row').remove()">Remove</button></div>
    `;
    document.getElementById('milCourseList').appendChild(div);
}
function addTradeGroup() {
    const div = document.createElement('div');
    div.className = 'row mb-2';
    div.innerHTML = `
        <div class="col-md-2 mb-2"><input type="text" name="tradegroup_employment[]" class="form-control form-control-sm" placeholder="Employment"></div>
        <div class="col-md-2 mb-2"><input type="text" name="tradegroup_group[]" class="form-control form-control-sm" placeholder="Group"></div>
        <div class="col-md-2 mb-2"><input type="text" name="tradegroup_class[]" class="form-control form-control-sm" placeholder="Class"></div>
        <div class="col-md-2 mb-2"><input type="date" name="tradegroup_date[]" class="form-control form-control-sm" placeholder="Date"></div>
        <div class="col-md-2 mb-2"><input type="text" name="tradegroup_authority[]" class="form-control form-control-sm" placeholder="Authority"></div>
        <div class="col-md-2 mb-2"><button type="button" class="btn btn-danger btn-sm" onclick="this.closest('.row').remove()">Remove</button></div>
    `;
    document.getElementById('tradeGroupList').appendChild(div);
}
// Optionally, add a default row on page load:
window.addEventListener('DOMContentLoaded', function() {
    if(document.getElementById('academicList')) addAcademic();
    if(document.getElementById('profTechList')) addProfTech();
    if(document.getElementById('milCourseList')) addMilCourse();
    if(document.getElementById('tradeGroupList')) addTradeGroup();
});
</script>