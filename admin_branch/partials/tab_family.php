<div class="tab-pane fade p-3 border rounded <?=isset($tabErrors['family']) ? 'show active' : ''?>" id="family" role="tabpanel">
    <h5 class="mb-3 text-success">Family Details</h5>
        <div class="row mb-3">
        <div class="col-md-4 mb-2">
            <label class="form-label form-label-sm" for="maritalStatus">Marital Status *</label>
            <select name="marital" class="form-select form-select-sm" id="maritalStatus" required>
            <option value="">Select</option>
            <option <?=old('marital')=='Single'?'selected':''?>>Single</option>
            <option <?=old('marital')=='Married'?'selected':''?>>Married</option>
            <option <?=old('marital')=='Divorced'?'selected':''?>>Divorced</option>
            <option <?=old('marital')=='Widowed'?'selected':''?>>Widowed</option>
            </select>
        </div>
        </div>
        <div id="spouseSection" style="display:none;">
        <div class="row mb-3">
            <div class="col-md-4 mb-2">
                <label class="form-label form-label-sm">Spouse Name</label>
                <input type="text" name="spouse_name" class="form-control form-control-sm" value="<?=old('spouse_name')?>">
            </div>
            <div class="col-md-4 mb-2">
                <label class="form-label form-label-sm">Spouse Date of Birth</label>
                <input type="date" name="spouse_dob" class="form-control form-control-sm" value="<?=old('spouse_dob')?>">
            </div>
            <div class="col-md-4 mb-2">
                <label class="form-label form-label-sm">Spouse NRC</label>
                <input type="text" name="spouse_nrc" class="form-control form-control-sm" value="<?=old('spouse_nrc')?>">
            </div>
            <div class="col-md-4 mb-2">
                <label class="form-label form-label-sm">Spouse Occupation</label>
                <input type="text" name="spouse_occup" class="form-control form-control-sm" value="<?=old('spouse_occup')?>">
            </div>
            <div class="col-md-4 mb-2">
                <label class="form-label form-label-sm">Spouse Contact</label>
                <input type="text" name="spouse_contact" class="form-control form-control-sm" value="<?=old('spouse_contact')?>">
            </div>
        </div>
        </div>
        <script>
        document.getElementById('maritalStatus').addEventListener('change', function() {
        document.getElementById('spouseSection').style.display = (this.value === 'Married') ? 'block' : 'none';
        });
        </script>
        <!-- Children/Dependants -->
        <div class="mb-3">
        <label class="form-label form-label-sm">Children/Dependants</label>
        <div id="childrenList"></div>
        <button type="button" class="btn btn-outline-secondary btn-sm" onclick="addChild()">Add Child/Dependant</button>
        </div>
        <!-- Next of Kin -->
        <div class="row mb-3">
        <div class="col-md-4  mb-2">
            <label class="form-label form-label-sm">Next of Kin Name *</label>
            <input type="text" name="nok" class="form-control form-control-sm" required value="<?=old('nok')?>">
        </div>
        <div class="col-md-4 mb-2">
            <label class="form-label form-label-sm">Next of Kin NRC *</label>
            <input type="text" name="nok_nrc" class="form-control form-control-sm" required value="<?=old('nok_nrc')?>">
        </div>
        <div class="col-md-4 mb-2">
            <label class="form-label form-label-sm">Next of Kin Relationship *</label>
            <select name="nok_relationship" class="form-select form-select-sm" required>
            <option value="">Select Relationship</option>
            <?php foreach ($relationshipOptions as $rel): ?>
                <option value="<?=$rel?>" <?=old('nok_relationship')==$rel?'selected':''?>><?=$rel?></option>
            <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-4 mb-2">
            <label class="form-label form-label-sm">Next of Kin Contact *</label>
            <input type="tel" name="nok_tel" class="form-control form-control-sm" required value="<?=old('nok_tel')?>">
        </div>
        </div>
        <div class="row mb-3">
        <div class="col-md-4 mb-2">
            <label class="form-label form-label-sm">Alternate Next of Kin Name</label>
            <input type="text" name="alt_nok" class="form-control form-control-sm" value="<?=old('alt_nok')?>">
        </div>
        <div class="col-md-4 mb-2">
            <label class="form-label form-label-sm">Alternate Next of Kin NRC</label>
            <input type="text" name="alt_nok_nrc" class="form-control form-control-sm" value="<?=old('alt_nok_nrc')?>">
        </div>
        <div class="col-md-4 mb-2">
            <label class="form-label form-label-sm">Alternate Next of Kin Relationship</label>
            <select name="alt_nok_relationship" class="form-select form-select-sm">
            <option value="">Select Relationship</option>
            <?php foreach ($relationshipOptions as $rel): ?>
                <option value="<?=$rel?>" <?=old('alt_nok_relationship')==$rel?'selected':''?>><?=$rel?></option>
            <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-4 mb-2">
            <label class="form-label form-label-sm">Alternate Next of Kin Contact</label>
            <input type="tel" name="alt_nok_tel" class="form-control form-control-sm" value="<?=old('alt_nok_tel')?>">
        </div>
    </div>
</div>
<script>
    // --- Dynamic add/remove for all sections ---
// Children/Dependants
function addChild() {
    const div = document.createElement('div');
    div.className = 'row mb-2 align-items-end';
    div.innerHTML = `
        <div class="col-md-3 mb-2"><input type="text" name="child_name[]" class="form-control form-control-sm" placeholder="Name"></div>
        <div class="col-md-2 mb-2"><input type="date" name="child_dob[]" class="form-control form-control-sm" placeholder="Date of Birth"></div>
        <div class="col-md-2 mb-2"><input type="text" name="child_nrc[]" class="form-control form-control-sm" placeholder="NRC"></div>
        <div class="col-md-3 mb-2"><select name="child_relationship[]" class="form-select form-select-sm"><option value="">Relationship</option><?php foreach ($relationshipOptions as $rel): ?><option value="<?=$rel?>"><?=$rel?></option><?php endforeach; ?></select></div>
        <div class="col-md-2 mb-2"><select name="child_gender[]" class="form-select form-select-sm"><option value="">Gender</option><option>Male</option><option>Female</option></select></div>
        <div class="col-auto mb-2"><button type="button" class="btn btn-danger btn-sm btn-remove-block" title="Remove"><i class="fa fa-times"></i></button></div>
    `;
    document.getElementById('childrenList').appendChild(div);
}

// Remove handler for all dynamic sections
function addDynamicRemoveHandler(listId) {
    $('#' + listId).on('click', '.btn-remove-block', function() {
        $(this).closest('.row').remove();
    });
}
['childrenList','academicList','profTechList','milCourseList','tradeGroupList','awardList','appointmentList','promotionList','languageList'].forEach(addDynamicRemoveHandler);

</script>