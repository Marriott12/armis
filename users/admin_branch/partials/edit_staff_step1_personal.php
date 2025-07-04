<?php
// Step 1: Personal Details
?>
<div class="row g-3">
    <div class="col-md-3">
        <label class="form-label">Service Number*</label>
        <input type="text" class="form-control" name="svcNo" value="<?= old('svcNo', $staff->svcNo ?? '') ?>" required readonly>
    </div>
    <div class="col-md-3">
        <label class="form-label">First Name*</label>
        <input type="text" class="form-control" name="fname" value="<?= old('fname', $staff->fname ?? '') ?>" required>
    </div>
    <div class="col-md-3">
        <label class="form-label">Last Name*</label>
        <input type="text" class="form-control" name="lname" value="<?= old('lname', $staff->lname ?? '') ?>" required>
    </div>
    <div class="col-md-3">
        <label class="form-label">NRC*</label>
        <input type="text" class="form-control" name="NRC" value="<?= old('NRC', $staff->NRC ?? '') ?>" required>
    </div>
    <div class="col-md-3">
        <label class="form-label">Gender*</label>
        <select name="gender" class="form-select" required>
            <option value="">Select...</option>
            <option value="Male" <?= old('gender', $staff->gender ?? '') == 'Male' ? 'selected' : '' ?>>Male</option>
            <option value="Female" <?= old('gender', $staff->gender ?? '') == 'Female' ? 'selected' : '' ?>>Female</option>
        </select>
    </div>
    <div class="col-md-3">
        <label class="form-label">DOB*</label>
        <input type="date" class="form-control" name="DOB" value="<?= old('DOB', $staff->DOB ?? '') ?>" required>
    </div>
    <div class="col-md-3">
        <label class="form-label">Marital Status*</label>
        <select name="marital" class="form-select" required>
            <option value="">Select...</option>
            <option value="Single" <?= old('marital', $staff->marital ?? '') == 'Single' ? 'selected' : '' ?>>Single</option>
            <option value="Married" <?= old('marital', $staff->marital ?? '') == 'Married' ? 'selected' : '' ?>>Married</option>
            <option value="Divorced" <?= old('marital', $staff->marital ?? '') == 'Divorced' ? 'selected' : '' ?>>Divorced</option>
            <option value="Widowed" <?= old('marital', $staff->marital ?? '') == 'Widowed' ? 'selected' : '' ?>>Widowed</option>
        </select>
    </div>
    <div class="col-md-3">
        <label class="form-label">Category*</label>
        <select name="category" class="form-select" required>
            <option value="">Select...</option>
            <?php foreach ($categories as $cat): ?>
                <option value="<?= $cat ?>" <?= old('category', $staff->category ?? '') == $cat ? 'selected' : '' ?>><?= $cat ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-md-3">
        <label class="form-label">Rank*</label>
        <select name="rankID" class="form-select" required>
            <option value="">Select...</option>
            <?php foreach ($ranks as $r): ?>
                <option value="<?= $r->rankID ?>" <?= old('rankID', $staff->rankID ?? '') == $r->rankID ? 'selected' : '' ?>>
                    <?= htmlspecialchars($r->rankName) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-md-3">
        <label class="form-label">Unit*</label>
        <select name="unitID" class="form-select" required>
            <option value="">Select...</option>
            <?php foreach ($units as $unit): ?>
                <option value="<?= $unit->unitID ?>" <?= old('unitID', $staff->unitID ?? '') == $unit->unitID ? 'selected' : '' ?>>
                    <?= htmlspecialchars($unit->unitName) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-md-3">
        <label class="form-label">Corps</label>
        <select name="corps" class="form-select">
            <option value="">Select...</option>
            <?php foreach ($corpsList as $c): ?>
                <option value="<?= htmlspecialchars($c->corps) ?>" <?= old('corps', $staff->corps ?? '') == $c->corps ? 'selected' : '' ?>>
                    <?= htmlspecialchars($c->corps) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-md-3">
        <label class="form-label">Prefix</label>
        <input type="text" class="form-control" name="prefix" value="<?= old('prefix', $staff->prefix ?? '') ?>">
    </div>
    <div class="col-md-3">
        <label class="form-label">Titles</label>
        <input type="text" class="form-control" name="titles" value="<?= old('titles', $staff->titles ?? '') ?>">
    </div>
    <div class="col-md-3">
        <label class="form-label">Initials</label>
        <input type="text" class="form-control" name="initials" value="<?= old('initials', $staff->initials ?? '') ?>">
    </div>
    <div class="col-md-3">
        <label class="form-label">Height (cm)</label>
        <input type="text" class="form-control" name="height" value="<?= old('height', $staff->height ?? '') ?>">
    </div>
    <div class="col-md-3">
        <label class="form-label">Blood Group</label>
        <input type="text" class="form-control" name="bloodGp" value="<?= old('bloodGp', $staff->bloodGp ?? '') ?>">
    </div>
    <div class="col-md-3">
        <label class="form-label">Trade</label>
        <input type="text" class="form-control" name="trade" value="<?= old('trade', $staff->trade ?? '') ?>">
    </div>
</div>