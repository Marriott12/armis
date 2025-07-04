<div class="tab-pane fade p-3 border rounded <?=isset($tabErrors['residence']) ? 'show active' : ''?>" id="residence" role="tabpanel">
    <h5 class="mb-3 text-success">Residential Details</h5>
    <div class="row mb-3">
        <div class="col-md-3 mb-2">
            <label class="form-label form-label-sm">Plot/ House/ Flat No</label>
            <input type="text" name="plot_no" class="form-control form-control-sm" value="<?=old('plot_no')?>">
        </div>
        <div class="col-md-3 mb-2">
            <label class="form-label form-label-sm">Street/ Avenue</label>
            <input type="text" name="street" class="form-control form-control-sm" value="<?=old('street')?>">
        </div>
        <div class="col-md-3 mb-2">
            <label class="form-label form-label-sm">Road</label>
            <input type="text" name="road" class="form-control form-control-sm" value="<?=old('road')?>">
        </div>
        <div class="col-md-3 mb-2">
            <label class="form-label form-label-sm">Location</label>
            <input type="text" name="location" class="form-control form-control-sm" value="<?=old('location')?>">
        </div>
        <div class="col-md-3 mb-2">
            <label class="form-label form-label-sm">Town/ City</label>
            <input type="text" name="township" class="form-control form-control-sm" value="<?=old('township')?>">
        </div>
        <div class="col-md-3 mb-2">
            <label class="form-label form-label-sm">Location of Acc:</label>
            <select name="acc_location" class="form-select form-select-sm">
            <option value="">Select</option>
            <option>Barracks</option>
            <option>Outside Barracks</option>
            </select>
        </div>
        <div class="col-md-3 mb-2">
            <label class="form-label form-label-sm">Type of Residence</label>
            <select name="residence_type" class="form-select form-select-sm">
            <option value="">Select</option>
            <option>Institutional</option>
            <option>Rented</option>
            <option>Owner Occupier</option>
            </select>
        </div>
    </div>
</div>