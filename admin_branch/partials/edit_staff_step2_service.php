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
    <?php
    // CHANGELOG (branch-scoping upgrade): Role used to be a free-text input,
    // meaning any typo became a real (and possibly meaningless) value in
    // staff.role. It's now a dropdown sourced from the `roles` table, paired
    // with a Branch dropdown (which branch this person is posted to for
    // RBAC purposes — separate from Unit Attached above). Both are
    // deliberately admin-only: reassigning someone's role/branch is a
    // privilege-escalation-relevant action, so Chief Clerks/Staff Officers
    // editing a colleague's profile see these as read-only text instead.
    $__currentRole = old('role', $staff->role ?? 'user');
    $__currentBranchId = old('branch_id', $staff->branch_id ?? '');
    $__canAssignRole = function_exists('isAdmin') && isAdmin();
    $__allRoles = function_exists('getAllRoles') ? getAllRoles(true) : [];
    $__allBranches = function_exists('getAllBranches') ? getAllBranches(true) : [];
    ?>
    <div class="col-md-3">
        <label class="form-label">Role</label>
        <?php if ($__canAssignRole): ?>
            <select class="form-control" name="role">
                <?php foreach ($__allRoles as $code => $r): ?>
                    <option value="<?= htmlspecialchars($code) ?>" <?= $__currentRole === $code ? 'selected' : '' ?>>
                        <?= htmlspecialchars($r['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        <?php else: ?>
            <input type="text" class="form-control" value="<?= htmlspecialchars($__allRoles[$__currentRole]['name'] ?? $__currentRole) ?>" disabled>
            <input type="hidden" name="role" value="<?= htmlspecialchars($__currentRole) ?>">
            <small class="text-muted">Only a System Administrator can change a role.</small>
        <?php endif; ?>
    </div>
    <div class="col-md-3">
        <label class="form-label">Branch</label>
        <?php if ($__canAssignRole): ?>
            <select class="form-control" name="branch_id">
                <option value="">— None —</option>
                <?php foreach ($__allBranches as $id => $b): ?>
                    <option value="<?= (int)$id ?>" <?= (string)$__currentBranchId === (string)$id ? 'selected' : '' ?>>
                        <?= htmlspecialchars($b['name']) ?><?= !empty($b['is_org_wide']) ? ' (Army-wide)' : '' ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <small class="text-muted">Which branch this person is posted to, for record-alteration scope.</small>
        <?php else: ?>
            <input type="text" class="form-control" value="<?= htmlspecialchars($__allBranches[$__currentBranchId]['name'] ?? '— None —') ?>" disabled>
            <input type="hidden" name="branch_id" value="<?= htmlspecialchars((string)$__currentBranchId) ?>">
        <?php endif; ?>
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