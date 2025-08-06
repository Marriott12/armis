<div class="row g-3">
    <div class="col-12">
        <h6>Spouse (if married)</h6>
        <div class="row">
            <div class="col-md-3">
                <input type="text" class="form-control mb-2" name="spouse_name" placeholder="Name" value="<?= old('spouse_name', $spouse->name ?? '') ?>">
            </div>
            <div class="col-md-2">
                <input type="date" class="form-control mb-2" name="spouse_dob" placeholder="DOB" value="<?= old('spouse_dob', $spouse->dob ?? '') ?>">
            </div>
            <div class="col-md-3">
                <input type="text" class="form-control mb-2" name="spouse_nrc" placeholder="NRC" value="<?= old('spouse_nrc', $spouse->nrc ?? '') ?>">
            </div>
            <div class="col-md-2">
                <input type="text" class="form-control mb-2" name="spouse_occupation" placeholder="Occupation" value="<?= old('spouse_occupation', $spouse->occupation ?? '') ?>">
            </div>
            <div class="col-md-2">
                <input type="text" class="form-control mb-2" name="spouse_contact" placeholder="Contact" value="<?= old('spouse_contact', $spouse->contact ?? '') ?>">
            </div>
        </div>
    </div>
    <div class="col-12">
        <h6>Children</h6>
        <div id="children-list">
            <?php
            $num = max(count($_POST['child_name'] ?? []), count($children ?? []), 1);
            for ($i = 0; $i < $num; $i++): ?>
                <div class="row mb-2">
                    <div class="col">
                        <input type="text" class="form-control" name="child_name[]" placeholder="Name"
                               value="<?= old('child_name', $children[$i]->name ?? '') ?>">
                    </div>
                    <div class="col">
                        <input type="date" class="form-control" name="child_dob[]" placeholder="DOB"
                               value="<?= old('child_dob', $children[$i]->dob ?? '') ?>">
                    </div>
                    <div class="col">
                        <input type="text" class="form-control" name="child_nrc[]" placeholder="NRC"
                               value="<?= old('child_nrc', $children[$i]->nrc ?? '') ?>">
                    </div>
                    <div class="col">
                        <input type="text" class="form-control" name="child_relationship[]" placeholder="Relationship"
                               value="<?= old('child_relationship', $children[$i]->relationship ?? '') ?>">
                    </div>
                    <div class="col">
                        <select class="form-select" name="child_gender[]">
                            <option value="">Gender</option>
                            <option value="Male" <?= old('child_gender', $children[$i]->gender ?? '') == 'Male' ? 'selected' : '' ?>>Male</option>
                            <option value="Female" <?= old('child_gender', $children[$i]->gender ?? '') == 'Female' ? 'selected' : '' ?>>Female</option>
                        </select>
                    </div>
                    <div class="col-auto">
                        <button type="button" class="btn btn-danger btn-sm remove-child-row"><i class="fa fa-minus"></i></button>
                    </div>
                </div>
            <?php endfor; ?>
        </div>
        <button type="button" id="add-child-row" class="btn btn-outline-primary btn-sm mt-2"><i class="fa fa-plus"></i> Add Child</button>
    </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Dynamic Add/Remove for Children
    document.getElementById('add-child-row').addEventListener('click', function() {
        var list = document.getElementById('children-list');
        var row = document.createElement('div');
        row.className = 'row mb-2';
        row.innerHTML = `
            <div class="col"><input type="text" class="form-control" name="child_name[]" placeholder="Name"></div>
            <div class="col"><input type="date" class="form-control" name="child_dob[]" placeholder="DOB"></div>
            <div class="col"><input type="text" class="form-control" name="child_nrc[]" placeholder="NRC"></div>
            <div class="col"><input type="text" class="form-control" name="child_relationship[]" placeholder="Relationship"></div>
            <div class="col">
                <select class="form-select" name="child_gender[]">
                    <option value="">Gender</option>
                    <option value="Male">Male</option>
                    <option value="Female">Female</option>
                </select>
            </div>
            <div class="col-auto">
                <button type="button" class="btn btn-danger btn-sm remove-child-row"><i class="fa fa-minus"></i></button>
            </div>
        `;
        list.appendChild(row);
    });
    document.getElementById('children-list').addEventListener('click', function(e) {
        if (e.target.classList.contains('remove-child-row') || e.target.closest('.remove-child-row')) {
            var btn = e.target.closest('.remove-child-row');
            btn.closest('.row').remove();
        }
    });
});
</script>