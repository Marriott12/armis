<?php
require_once '../usersc/includes/admin_init.php';
require_once '../usersc/models/StaffAdmin.php';
$staffAdmin = new StaffAdmin($db);

$svcNo = $_GET['svcNo'] ?? '';
$staff = $staffAdmin->get($svcNo);

if (!$staff) { die('Staff not found'); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = $_POST;
    unset($data['svcNo']); // Don't update PK
    $staffAdmin->update($svcNo, $data);
    Redirect::to('admin_staff_list.php?msg=updated');
}
?>
<h2>Edit Staff</h2>
<form method="post">
    <input type="hidden" name="svcNo" value="<?=htmlspecialchars($staff->svcNo)?>">
    <input type="text" name="fName" value="<?=htmlspecialchars($staff->fName)?>" required class="form-control mb-2">
    <input type="text" name="sName" value="<?=htmlspecialchars($staff->sName)?>" required class="form-control mb-2">
    <input type="text" name="rankID" value="<?=htmlspecialchars($staff->rankID)?>" class="form-control mb-2">
    <input type="text" name="unitID" value="<?=htmlspecialchars($staff->unitID)?>" class="form-control mb-2">
    <input type="text" name="category" value="<?=htmlspecialchars($staff->category)?>" class="form-control mb-2">
    <input type="text" name="svcStatus" value="<?=htmlspecialchars($staff->svcStatus)?>" class="form-control mb-2">
    <input type="text" name="username" value="<?=htmlspecialchars($staff->username)?>" required class="form-control mb-2">
    <input type="password" name="password" placeholder="Leave blank to keep current password" class="form-control mb-2">
    <input type="text" name="role" value="<?=htmlspecialchars($staff->role)?>" required class="form-control mb-2">
    <!-- Add more fields as needed -->
    <button type="submit" class="btn btn-primary">Update Staff</button>
    <a href="admin_staff_list.php" class="btn btn-secondary">Cancel</a>
</form>