<div class="row mb-4">
    <form id="dashboardFilters" class="col-12 d-flex flex-wrap gap-3 align-items-end">
      <div>
        <label class="form-label mb-0">Category</label>
        <select class="form-select form-select-sm" name="category">
          <option value="">All</option>
          <?php foreach($allCategories as $cat): ?>
            <option value="<?=htmlspecialchars($cat->category)?>" <?=($filters['category']==$cat->category)?'selected':''?>><?=htmlspecialchars($cat->category)?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label class="form-label mb-0">Province</label>
        <select class="form-select form-select-sm" name="province">
          <option value="">All</option>
          <?php foreach($allProvinces as $prov): ?>
            <option value="<?=htmlspecialchars($prov->province)?>" <?=($filters['province']==$prov->province)?'selected':''?>><?=htmlspecialchars($prov->province)?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label class="form-label mb-0">Unit</label>
        <select class="form-select form-select-sm" name="unitID">
          <option value="">All</option>
          <?php foreach($allUnits as $unit): ?>
            <option value="<?=htmlspecialchars($unit->unitID)?>" <?=($filters['unitID']==$unit->unitID)?'selected':''?>><?=htmlspecialchars($unit->unitID)?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <button type="submit" class="btn btn-primary btn-sm"><i class="fa fa-filter"></i> Apply Filter</button>
        <button type="button" id="resetFilters" class="btn btn-outline-secondary btn-sm ms-1">Reset</button>
        <a class="btn btn-outline-success btn-sm ms-1" href="?export=csv<?=($filters['category']?"&category=".urlencode($filters['category']):"")?><?=($filters['province']?"&province=".urlencode($filters['province']):"")?><?=($filters['unitID']?"&unitID=".urlencode($filters['unitID']):"")?>"><i class="fa fa-download"></i> Export CSV</a>
      </div>
      <div class="ms-auto">
        <button type="button" class="btn btn-success btn-sm" id="liveRefresh"><i class="fa fa-refresh"></i> Live Refresh</button>
      </div>
    </form>
  </div>