<?php
// Step 2: Service/Contact Details
?>
<div class="row g-3">
    <div class="col-md-3">
        <label class="form-label">Service Status*</label>
        <input type="text" class="form-control" name="svcStatus" value="<?= old('svcStatus', $staff->svcStatus ?? '') ?>" required>
    </div>
    <div class="col-md-3">
        <label class="form-label">Date of Enlistment*</label>
        <input type="date" class="form-control" name="attestDate" value="<?= old('attestDate', $staff->attestDate ?? '') ?>" required>
    </div>
    <div class="col-md-3">
        <label class="form-label">Intake</label>
        <input type="text" class="form-control" name="intake" value="<?= old('intake', $staff->intake ?? '') ?>">
    </div>
    <div class="col-md-3">
        <label class="form-label">Passport</label>
        <input type="text" class="form-control" name="passport" value="<?= old('passport', $staff->passport ?? '') ?>">
    </div>
    <div class="col-md-3">
        <label class="form-label">Passport Expiry</label>
        <input type="date" class="form-control" name="passExp" value="<?= old('passExp', $staff->passExp ?? '') ?>">
    </div>
    <div class="col-md-3">
        <label class="form-label">Digital ID</label>
        <input type="text" class="form-control" name="digitalID" value="<?= old('digitalID', $staff->digitalID ?? '') ?>">
    </div>
    <div class="col-md-3">
        <label class="form-label">Unit Attached</label>
        <input type="text" class="form-control" name="unitAtt" value="<?= old('unitAtt', $staff->unitAtt ?? '') ?>">
    </div>
    <div class="col-md-3">
        <label class="form-label">Role</label>
        <input type="text" class="form-control" name="role" value="<?= old('role', $staff->role ?? '') ?>">
    </div>
    <div class="col-md-3">
        <label class="form-label">Email</label>
        <input type="email" class="form-control" name="email" value="<?= old('email', $staff->email ?? '') ?>">
    </div>
    <div class="col-md-3">
        <label class="form-label">Phone</label>
        <input type="text" class="form-control" name="tel" value="<?= old('tel', $staff->tel ?? '') ?>">
    </div>
    <div class="col-md-3">
        <label class="form-label">Next of Kin*</label>
        <input type="text" class="form-control" name="nok" value="<?= old('nok', $staff->nok ?? '') ?>" required>
    </div>
    <div class="col-md-3">
        <label class="form-label">NOK NRC*</label>
        <input type="text" class="form-control" name="nok_nrc" value="<?= old('nok_nrc', $staff->nok_nrc ?? '') ?>" required>
    </div>
    <div class="col-md-3">
        <label class="form-label">NOK Relationship*</label>
        <input type="text" class="form-control" name="nok_relationship" value="<?= old('nok_relationship', $staff->nok_relationship ?? '') ?>" required>
    </div>
    <div class="col-md-3">
        <label class="form-label">NOK Phone*</label>
        <input type="text" class="form-control" name="nok_tel" value="<?= old('nok_tel', $staff->nok_tel ?? '') ?>" required>
    </div>
    <div class="col-md-3">
        <label class="form-label">Alternate NOK</label>
        <input type="text" class="form-control" name="alt_nok" value="<?= old('alt_nok', $staff->alt_nok ?? '') ?>">
    </div>
    <div class="col-md-3">
        <label class="form-label">Alternate NOK NRC</label>
        <input type="text" class="form-control" name="alt_nok_nrc" value="<?= old('alt_nok_nrc', $staff->alt_nok_nrc ?? '') ?>">
    </div>
    <div class="col-md-3">
        <label class="form-label">Alternate NOK Relationship</label>
        <input type="text" class="form-control" name="alt_nok_relationship" value="<?= old('alt_nok_relationship', $staff->alt_nok_relationship ?? '') ?>">
    </div>
    <div class="col-md-3">
        <label class="form-label">Alternate NOK Phone</label>
        <input type="text" class="form-control" name="alt_nok_tel" value="<?= old('alt_nok_tel', $staff->alt_nok_tel ?? '') ?>">
    </div>
    <div class="col-md-3">
        <label class="form-label">Province*</label>
        <input type="text" class="form-control" name="province" value="<?= old('province', $staff->province ?? '') ?>" required>
    </div>
    <div class="col-md-3">
        <label class="form-label">District</label>
        <input type="text" class="form-control" name="district" value="<?= old('district', $staff->district ?? '') ?>">
    </div>
    <div class="col-md-3">
        <label class="form-label">Township</label>
        <input type="text" class="form-control" name="township" value="<?= old('township', $staff->township ?? '') ?>">
    </div>
    <div class="col-md-3">
        <label class="form-label">Village</label>
        <input type="text" class="form-control" name="village" value="<?= old('village', $staff->village ?? '') ?>">
    </div>
    <div class="col-md-3">
        <label class="form-label">Plot No</label>
        <input type="text" class="form-control" name="plot_no" value="<?= old('plot_no', $staff->plot_no ?? '') ?>">
    </div>
</div>