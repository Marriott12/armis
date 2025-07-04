<div class="tab-pane fade p-3 border rounded <?=isset($tabErrors['id']) ? 'show active' : ''?>" id="id" role="tabpanel">
    <h5 class="mb-3 text-success">Identification Details</h5>
    <div class="row mb-3 align-items-center">
        <label class="form-label form-label-sm" for="nrc">National Registration Card</label>
        <div class="col-auto">
        <input type="text" name="nrc_part1" id="nrc_part1" class="form-control form-control-sm" maxlength="6" placeholder="123456" value="<?=old('nrc_part1')?>"></div>/
        <div class="col-md-1 mb-1">
        <input type="text" name="nrc_part2" id="nrc_part2" class="form-control form-control-sm" maxlength="2" placeholder="78" value="<?=old('nrc_part2')?>"></div>/
        <div class="col-md-1 mb-1">
        <input type="text" name="nrc_const" id="nrc_const" class="form-control form-control-sm" value="1" disabled>
        </div>
    </div>
    <div class="row mb-3">
    <h6>Military Identification Card</h6>
    <div class="col-md-3 mb-2">
        <label class="form-label form-label-sm" for="milLicence_no">Card Number</label>
        <input type="text" name="card_no" id="card_no" class="form-control form-control-sm" maxlength="30" value="<?=old('card_no')?>">
    </div>
    <div class="col-md-3 mb-2">
        <label class="form-label form-label-sm" for="card_issue_date">Date of Issue</label>
        <input type="date" name="card_issue_date" id="card_issue_date" class="form-control form-control-sm" value="<?=old('card_issue_date')?>">
    </div>
    <div class="col-md-3 mb-2">
        <label class="form-label form-label-sm" for="eye_color">Color of eyes</label>
        <input type="text" name="eye_color" id="eye_color" class="form-control form-control-sm" maxlength="50" value="<?=old('eye_color')?>">
    </div>
    <div class="col-md-3 mb-2">
        <label class="form-label form-label-sm" for="hair_color">Color of hair</label>
        <input type="text" name="hair_color" id="hair_color" class="form-control form-control-sm" value="<?=old('hair_color')?>">
    </div>
    <div class="col-md-3 mb-2">
        <label class="form-label form-label-sm" for="issuing_unit">Issuing Unit</label>
        <input type="text" name="issuing_unit" id="issuing_unit" class="form-control form-control-sm" value="<?=old('issuing_unit')?>">
    </div>
    <div class="col-md-3 mb-2">
        <label class="form-label form-label-sm" for="special_features">Special Features</label>
        <input type="text" name="special_features" id="special_features" class="form-control form-control-sm" value="<?=old('special_features')?>">
    </div>
    </div>
    <h6>Passport Details</h6>
    <div class="row mb-3">
    <div class="col-md-3 mb-2">
        <label class="form-label form-label-sm" for="passport_no">Passport Number</label>
        <input type="text" name="passport_no" id="passport_no" class="form-control form-control-sm" maxlength="30" value="<?=old('passport_no')?>" required>
    </div>
    <div class="col-md-3 mb-2">
        <label class="form-label form-label-sm" for="passport_issue_date">Issue Date</label>
        <input type="date" name="passport_issue_date" id="passport_issue_date" class="form-control form-control-sm" value="<?=old('passport_issue_date')?>">
    </div>
    <div class="col-md-3 mb-2">
        <label class="form-label form-label-sm" for="passport_issue_place">Issue Place</label>
        <input type="text" name="passport_issue_place" id="passport_issue_place" class="form-control form-control-sm" maxlength="50" value="<?=old('passport_issue_place')?>">
    </div>
    <div class="col-md-3 mb-2">
        <label class="form-label form-label-sm" for="passport_exp_date">Expiry Date</label>
        <input type="date" name="passport_exp_date" id="passport_exp_date" class="form-control form-control-sm" value="<?=old('passport_exp_date')?>">
    </div>
    <h5>Driving License Details</h5>
    <div class="row mb-3">
        <h6>Military License</h6>
        <div class="col-md-3 mb-2">
            <label class="form-label form-label-sm" for="milLicence_no">License Number</label>
            <input type="text" name="milLicence_no" id="milLicence_no" class="form-control form-control-sm" maxlength="30" value="<?=old('milLicence_no')?>">
        </div>
        <div class="col-md-3 mb-2">
            <label class="form-label form-label-sm" for="milLicence_issue_date">Date of Issue</label>
            <input type="date" name="milLicence_issue_date" id="milLicence_issue_date" class="form-control form-control-sm" value="<?=old('milLicence_issue_date')?>">
        </div>
        <div class="col-md-3 mb-2">
            <label class="form-label form-label-sm" for="milLicence_class">Class</label>
            <input type="text" name="milLicence_class" id="milLicence_class" class="form-control form-control-sm" maxlength="50" value="<?=old('milLicence_class')?>">
        </div>
        <div class="col-md-3 mb-2">
            <label class="form-label form-label-sm" for="milLicense_issue_place">Place of Issue</label>
            <input type="text" name="milLicense_issue_place" id="milLicense_issue_place" class="form-control form-control-sm" value="<?=old('milLicense_issue_place')?>">
        </div>
    </div>
    <div class="row mb-3">
        <h6>Civil License</h6>
        <div class="col-md-3 mb-2">
            <label class="form-label form-label-sm" for="milLicence_no">License Number</label>
            <input type="text" name="civLicence_no" id="civLicence_no" class="form-control form-control-sm" maxlength="30" value="<?=old('civLicence_no')?>">
        </div>
        <div class="col-md-3 mb-2">
            <label class="form-label form-label-sm" for="civLicence_issue_date">Date of Issue</label>
            <input type="date" name="civLicence_issue_date" id="civLicence_issue_date" class="form-control form-control-sm" value="<?=old('milLicence_issue_date')?>">
        </div>
        <div class="col-md-3 mb-2">
            <label class="form-label form-label-sm" for="civLicence_class">Class</label>
            <input type="text" name="civLicence_class" id="civLicence_class" class="form-control form-control-sm" maxlength="50" value="<?=old('civLicence_class')?>">
        </div>
        <div class="col-md-3 mb-2">
            <label class="form-label form-label-sm" for="civLicense_issue_place">Place of Issue</label>
            <input type="text" name="civLicense_issue_place" id="civLicense_issue_place" class="form-control form-control-sm" value="<?=old('civLicense_issue_place')?>">
        </div>
        <div class="col-md-3 mb-2">
            <label class="form-label form-label-sm" for="civLicense_type">Type of License</label>
            <input type="text" name="civLicense_type" id="civLicense_type" class="form-control form-control-sm" value="<?=old('civLicense_type')?>">
        </div>
    </div>
</div>
</div>