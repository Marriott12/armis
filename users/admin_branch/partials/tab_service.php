<div class="tab-pane fade p-3 border rounded <?=isset($tabErrors['service']) ? 'show active' : ''?>" id="service" role="tabpanel">
    <h5 class="mb-3 text-success">Service Details</h5>
    <div class="row mb-3">
        <input type="hidden" name="svcStatus" value="Serving">
        <div class="col-md-4 mb-2">
            <label class="form-label form-label-sm" for="unitID">Unit</label>
            <select name="unitID" id="unitID" class="form-select form-select-sm" aria-label="Unit">
                <option value="">Select Unit</option>
                <?php foreach ($units as $unit): ?>
                    <option value="<?=$unit->unitID?>" <?=old('unitID')==$unit->unitID?'selected':''?>><?=htmlspecialchars($unit->unitName)?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-4 mb-2">
            <label class="form-label form-label-sm" for="corps">Corps</label>
            <select name="corps" id="corps" class="form-select form-select-sm" aria-label="Corps">
                <option value="">Select Corps</option>
                <?php foreach ($corps as $corp): ?>
                    <option value="<?=$corp->corpsAbb?>" <?=old('corps')==$corp->corpsAbb?'selected':''?>><?=htmlspecialchars($corp->corpsName)?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-4 mb-2">
            <label class="form-label form-label-sm" for="dateOfEnlistment">Date of Enlistment *</label>
            <input type="date" name="dateOfEnlistment" id="dateOfEnlistment" class="form-control form-control-sm" required aria-label="Date of Enlistment" value="<?=old('dateOfEnlistment')?>">
        </div>
        <div class="col-md-4 mb-2">
            <label class="form-label form-label-sm" for="trade">Trade</label>
            <input type="text" name="trade" id="trade" class="form-control form-control-sm" maxlength="50" value="<?=old('trade')?>">
        </div>
    </div>

    <h5 class="mb-3 text-success">Current Appointment</h5>
    <div class="row mb-3">
        <div class="col-md-3 mb-2">
            <label class="form-label form-label-sm" for="current_appointment_name">Appointment Name</label>
            <input type="text" name="current_appointment_name" id="current_appointment_name" class="form-control form-control-sm" value="<?=old('current_appointment_name')?>">
        </div>
        <div class="col-md-3 mb-2">
            <label class="form-label form-label-sm" for="current_appointment_unit">Unit</label>
            <input type="text" name="current_appointment_unit" id="current_appointment_unit" class="form-control form-control-sm" value="<?=old('current_appointment_unit')?>">
        </div>
        <div class="col-md-3 mb-2">
            <label class="form-label form-label-sm" for="current_appointment_start">Start Date</label>
            <input type="date" name="current_appointment_start" id="current_appointment_start" class="form-control form-control-sm" value="<?=old('current_appointment_start')?>">
        </div>
        <div class="col-md-3 mb-2">
            <label class="form-label form-label-sm" for="current_appointment_authority">Authority</label>
            <input type="text" name="current_appointment_authority" id="current_appointment_authority" class="form-control form-control-sm" value="<?=old('current_appointment_authority')?>">
        </div>
    </div>

    <h5 class="mb-3 text-success">Past Appointments</h5>
    <div id="appointmentList"></div>
    <button type="button" class="btn btn-outline-secondary btn-sm mb-3" onclick="addAppointment()">Add Appointment</button>

    <h5 class="mb-3 text-success">Current Promotion</h5>
    <div class="row mb-3">
        <div class="col-md-3 mb-2">
            <label class="form-label form-label-sm" for="current_promotion_rank">Rank</label>
            <input type="text" name="current_promotion_rank" id="current_promotion_rank" class="form-control form-control-sm" value="<?=old('current_promotion_rank')?>" disabled>
        </div>
        <div class="col-md-3 mb-2">
            <label class="form-label form-label-sm" for="current_promotion_date_from">Date From</label>
            <input type="date" name="current_promotion_date_from" id="current_promotion_date_from" class="form-control form-control-sm" value="<?=old('current_promotion_date_from')?>">
        </div>
        <div class="col-md-3 mb-2">
            <label class="form-label form-label-sm" for="current_promotion_authority">Authority</label>
            <input type="text" name="current_promotion_authority" id="current_promotion_authority" class="form-control form-control-sm" value="<?=old('current_promotion_authority')?>">
        </div>
        <div class="col-md-3 mb-2">
            <label class="form-label form-label-sm" for="current_promotion_remark">Remark</label>
            <input type="text" name="current_promotion_remark" id="current_promotion_remark" class="form-control form-control-sm" value="<?=old('current_promotion_remark')?>">
        </div>
    </div>

    <h5 class="mb-3 text-success">Past Promotions / Reversions</h5>
    <div id="promotionList"></div>
    <button type="button" class="btn btn-outline-secondary btn-sm mb-3" onclick="addPromotion()">Add Promotion/Reversion</button>
</div>
<script>
// Auto-fill current appointment unit based on selected unit
document.getElementById('unitID').addEventListener('change', function() {
    var unitText = this.options[this.selectedIndex].text;
    document.getElementById('current_appointment_unit').value = unitText !== 'Select Unit' ? unitText : '';
});

// Auto-fill current promotion rank based on selected rank in personal details
var rankSelect = document.getElementById('rankSelect');
if(rankSelect){
    rankSelect.addEventListener('change', function() {
        var rankText = this.options[this.selectedIndex].text;
        document.getElementById('current_promotion_rank').value = rankText !== 'Select Rank' ? rankText : '';
    });
}
</script>