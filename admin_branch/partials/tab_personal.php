<?php
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
?>
<div class="tab-pane fade show <?=!$tabErrors || isset($tabErrors['personal']) ? 'active' : ''?> p-3 border rounded" id="personal" role="tabpanel">
    <h5 class="mb-3 text-success">Personal Details</h5>
    <div class="row mb-3">
        <div class="col-md-4 mb-2">
            <label class="form-label form-label-sm" for="prefix">Prefix *</label>
            <select name="prefix" id="prefix" class="form-select form-select-sm" required>
                <option value="">Select Prefix</option>
                <?php foreach ($prefixOptions as $p): ?>
                <option value="<?=$p?>" <?=old('prefix')==$p?'selected':''?>><?=$p?></option>
                <?php endforeach; ?>
            </select>
        </div>
            <div class="col-md-4 mb-2">
              <label class="form-label form-label-sm" for="svcNo">Service Number *</label>
              <input type="text" name="svcNo" id="svcNo" class="form-control form-control-sm" required maxlength="20" pattern="[A-Za-z0-9\-\/]+" aria-label="Service Number" value="<?=old('svcNo')?>">
            </div>
            <div class="col-md-4 mb-2">
              <label class="form-label form-label-sm" for="categorySelect">Category *</label>
              <select name="category" class="form-select form-select-sm" id="categorySelect" required aria-label="Category">
                <option value="">Select Category</option>
                <option <?=old('category')=='Officer'?'selected':''?>>Officer</option>
                <option <?=old('category')=='Non-Commissioned Officer'?'selected':''?>>Non-Commissioned Officer</option>
                <option <?=old('category')=='Civilian Employee'?'selected':''?>>Civilian Employee</option>
              </select>
            </div>
            <div class="col-md-4 mb-2" id="rankDiv">
              <label class="form-label form-label-sm" for="rankSelect">Rank *</label>
              <select name="rankID" class="form-select form-select-sm" id="rankSelect" aria-label="Rank" required data-validate="true">
                <option value="">Select Rank</option>
                <?php 
                $currentCategory = '';
                $seenCivilian = false;
                $rankOptionsByCategory = [];
                foreach ($ranks as $rank):
                    $cat = trim($rank->category);
                    if (strtolower($cat) === 'civilian employee' || strtolower($cat) === 'ce' || strtolower($cat) === 'civilian') {
                        if (!isset($rankOptionsByCategory['Civilian Employee'])) {
                            $rankOptionsByCategory['Civilian Employee'] = [
                                '<option value="mr" ' . (old('rankID')=='mr'?'selected':'') . '>Mr</option>',
                                '<option value="ms" ' . (old('rankID')=='ms'?'selected':'') . '>Ms</option>'
                            ];
                        }
                        continue;
                    }
                    if (!isset($rankOptionsByCategory[$cat])) $rankOptionsByCategory[$cat] = [];
                    ob_start();
                    ?>
                    <option value="<?=$rank->rankID?>" 
                            data-rankindex="<?=$rank->rankIndex ?? $rank->level ?? ''?>" 
                            data-category="<?=htmlspecialchars($rank->category)?>"
                            data-abbreviation="<?=htmlspecialchars($rank->abbreviation ?? '')?>"
                            data-staff-count="<?=$rank->staff_count ?? 0?>"
                            <?=old('rankID')==$rank->rankID?'selected':''?>>
                        <?=htmlspecialchars($rank->rankName)?>
                        <?php if (!empty($rank->abbreviation)): ?> (<?=htmlspecialchars($rank->abbreviation)?>)<?php endif; ?>
                    </option>
                    <?php
                    $rankOptionsByCategory[$cat][] = trim(ob_get_clean());
                endforeach; 
                // Output all options for all categories, but hide with JS
                foreach ($rankOptionsByCategory as $cat => $options) {
                    echo '<optgroup label="' . htmlspecialchars($cat) . '" data-category="' . htmlspecialchars($cat) . '">';
                    foreach ($options as $opt) echo $opt;
                    echo '</optgroup>';
                }
                ?>
              </select>
              <div class="validation-feedback"></div>
              <small class="form-text text-muted" id="rank-info"></small>
            </div>
            <div class="col-md-4 mb-2">
              <label class="form-label form-label-sm" for="lname">Surname *</label>
              <input type="text" name="lname" id="lname" class="form-control form-control-sm" required maxlength="100" aria-label="Surname" value="<?=old('lname')?>">
            </div>
            <div class="col-md-4 mb-2">
              <label class="form-label form-label-sm" for="fname">Forename(s) *</label>
              <input type="text" name="fname" id="fname" class="form-control form-control-sm" required maxlength="100" aria-label="First Name(s)" value="<?=old('fname')?>">
            </div>
            <div class="col-md-4 mb-2">
              <label class="form-label form-label-sm" for="email">Email Address *</label>
              <input type="email" name="email" id="email" class="form-control form-control-sm <?= hasError('email') ? 'is-invalid' : '' ?>" required maxlength="100" aria-label="Email Address" value="<?=old('email')?>" data-validate="true">
              <div class="validation-feedback"><?= getError('email') ?></div>
              <?php if (hasError('email')): ?>
                <div class="invalid-feedback"><?= getError('email') ?></div>
              <?php endif; ?>
            </div>
            <div class="col-md-4 mb-2">
              <label class="form-label form-label-sm" for="phone">Phone Number *</label>
              <input type="tel" name="phone" id="phone" class="form-control form-control-sm <?= hasError('phone') ? 'is-invalid' : '' ?>" required maxlength="20" aria-label="Phone Number" value="<?=old('phone')?>" placeholder="+260 XX XXX XXXX" data-validate="true">
              <div class="validation-feedback"><?= getError('phone') ?></div>
              <?php if (hasError('phone')): ?>
                <div class="invalid-feedback"><?= getError('phone') ?></div>
              <?php endif; ?>
            </div>
            <div class="col-md-4 mb-2">
              <label class="form-label form-label-sm" for="titles">Titles</label>
              <input type="text" name="titles" id="titles" class="form-control form-control-sm" maxlength="50" value="<?=old('titles')?>">
            </div>
            <div class="col-md-4 mb-2">
              <label class="form-label form-label-sm" for="gender">Gender *</label>
              <select name="gender" id="gender" class="form-select form-select-sm" required aria-label="Gender">
                <option value="">Select Gender</option>
                <option <?=old('gender')=='Male'?'selected':''?>>Male</option>
                <option <?=old('gender')=='Female'?'selected':''?>>Female</option>
              </select>
            </div>
            <div class="col-md-4 mb-2">
              <label class="form-label form-label-sm" for="DOB">Date of Birth *</label>
              <input type="date" name="DOB" id="DOB" class="form-control form-control-sm" required aria-label="Date of Birth" value="<?=old('DOB')?>">
            </div>
            <div class="col-md-4 mb-2">
              <label class="form-label form-label-sm" for="blood_group">Blood Group *</label>
              <select name="blood_group" id="blood_group" class="form-select form-select-sm" required>
                <option value="">Select</option>
                <option <?=old('blood_group')=='A+'?'selected':''?>>A+</option>
                <option <?=old('blood_group')=='A-'?'selected':''?>>A-</option>
                <option <?=old('blood_group')=='B+'?'selected':''?>>B+</option>
                <option <?=old('blood_group')=='B-'?'selected':''?>>B-</option>
                <option <?=old('blood_group')=='AB+'?'selected':''?>>AB+</option>
                <option <?=old('blood_group')=='AB-'?'selected':''?>>AB-</option>
                <option <?=old('blood_group')=='O+'?'selected':''?>>O+</option>
                <option <?=old('blood_group')=='O-'?'selected':''?>>O-</option>
              </select>
            </div>
        <div class="col-md-4 mb-2">
            <label class="form-label form-label-sm" for="province">Province</label>
            <select name="province" id="province" class="form-select form-select-sm" required>
                <option value="">Select Province</option>
                <?php foreach ($provinceOptions as $p): ?>
                <option value="<?=$p?>" <?=old('province')==$p?'selected':''?>><?=$p?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-4 mb-2">
            <label class="form-label form-label-sm" for="district">District</label>
            <select name="district" id="district" class="form-select form-select-sm" required>
                <option value="">Select District</option>
                <?php
                if (isset($_POST['province']) && isset($provinceDistricts[$_POST['province']])) {
                    foreach ($provinceDistricts[$_POST['province']] as $d) {
                        echo '<option value="'.htmlspecialchars($d).'"'.(old('district')==$d?' selected':'').'>'.htmlspecialchars($d).'</option>';
                    }
                }
                ?>
            </select>
        </div>
        <input type="hidden" name="nationality" value="Zambia">
        <div class="col-md-4 mb-2">
          <label class="form-label form-label-sm" for="religion">Religion</label>
          <select name="religion" id="religion" class="form-select form-select-sm" required>
            <option value="">Select Religion</option>
            <?php foreach ($religionOptions as $r): ?>
              <option value="<?=$r?>" <?=old('religion')==$r?'selected':''?>><?=$r?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-4 mb-2">
          <label class="form-label form-label-sm" for="village">Village</label>
          <input type="text" name="village" id="village" class="form-control form-control-sm" value="<?=old('village')?>">
        </div>
        <div class="col-md-4 mb-2">
          <label class="form-label form-label-sm" for="height">Height (cm) *</label>
          <input type="number" min="100" max="250" name="height" id="height" class="form-control form-control-sm" required value="<?=old('height')?>">
        </div>
        <div class="col-md-4 mb-2">
          <label class="form-label form-label-sm" for="combat_size">Combat Size *</label>
          <select name="combat_size" id="combat_size" class="form-select form-select-sm" required>
          <option value="">Select</option>
          <?php foreach($combatSizes as $size): ?>
            <option value="<?=$size?>" <?=old('combat_size')==$size?'selected':''?>><?=$size?></option>
          <?php endforeach; ?>
        </select>
        </div>
        <div class="col-md-4 mb-2">
          <label class="form-label form-label-sm" for="boot_size">Boot Size *</label>
          <select name="boot_size" id="boot_size" class="form-select form-select-sm" required>
          <option value="">Select</option>
          <?php foreach($bootSizes as $size): ?>
            <option value="<?=$size?>" <?=old('boot_size')==$size?'selected':''?>><?=$size?></option>
          <?php endforeach; ?>
        </select>
        </div>
        <div class="col-md-4 mb-2">
          <label class="form-label form-label-sm" for="shoe_size">Staff Shoe Size *</label>
          <select name="shoe_size" id="shoe_size" class="form-select form-select-sm" required>
          <option value="">Select</option>
          <?php foreach($shoeSizes as $size): ?>
            <option value="<?=$size?>" <?=old('shoe_size')==$size?'selected':''?>><?=$size?></option>
          <?php endforeach; ?>
        </select>
        </div>
        <div class="col-md-4 mb-2">
          <label class="form-label form-label-sm" for="headdress_size">Headdress Size *</label>
          <select name="headdress_size" id="headdress_size" class="form-select form-select-sm" required>
            <option value="">Select</option>
            <?php foreach($headdressSizes as $size): ?>
              <option value="<?=$size?>" <?=old('headdress_size')==$size?'selected':''?>><?=$size?></option>
            <?php endforeach; ?>
          </select>
        </div>
    </div>
</div>
<script>
// Update district options based on selected province
document.getElementById('province').addEventListener('change', function() {
    const province = this.value;
    const districtSelect = document.getElementById('district');
    districtSelect.innerHTML = '<option value="">Select District</option>';
    <?php if (isset($provinceDistricts)): ?>
        <?php foreach ($provinceDistricts as $p => $districts): ?>
            if (province === '<?=$p?>') {
                <?php foreach ($districts as $d): ?>
                    districtSelect.innerHTML += '<option value="<?=htmlspecialchars($d)?>" <?=old('district')==htmlspecialchars($d)?'selected':''?>><?=htmlspecialchars($d)?></option>';
                <?php endforeach; ?>
            }
        <?php endforeach; ?>
    <?php endif; ?>
});

// Filter ranks by category
document.getElementById('categorySelect').addEventListener('change', function() {
    var selectedCat = this.value.trim();
    var rankSelect = document.getElementById('rankSelect');
    var optgroups = rankSelect.querySelectorAll('optgroup');
    var found = false;
    rankSelect.value = '';
    optgroups.forEach(function(optgroup) {
        var cat = optgroup.getAttribute('label').trim();
        if (cat === selectedCat || (selectedCat === 'Civilian Employee' && cat === 'Civilian Employee')) {
            optgroup.style.display = '';
            found = true;
        } else {
            optgroup.style.display = 'none';
        }
    });
    // If no match, show all
    if (!found) {
        optgroups.forEach(function(optgroup) { optgroup.style.display = ''; });
    }
});
// On page load, trigger filter if category is preselected
window.addEventListener('DOMContentLoaded', function() {
    var catSel = document.getElementById('categorySelect');
    if (catSel && catSel.value) {
        var event = new Event('change');
        catSel.dispatchEvent(event);
    }
});
</script>