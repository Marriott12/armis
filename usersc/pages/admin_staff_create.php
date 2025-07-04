<?php
require_once '../usersc/includes/admin_init.php';
require_once '../usersc/models/StaffAdmin.php';
$staffAdmin = new StaffAdmin($db);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate input here!
    $data = $_POST;
    $data['createdBy'] = $user->data()->username;
    $staffAdmin->create($data);
    Redirect::to('admin_staff_list.php?msg=created');
}
?>
<h2>Add New Staff</h2>
<form method="post">
    <input type="text" name="svcNo" placeholder="Service No" required class="form-control mb-2">
    <input type="text" name="fName" placeholder="First Name" required class="form-control mb-2">
    <input type="text" name="sName" placeholder="Surname" required class="form-control mb-2">
    <input type="text" name="rankID" placeholder="Rank" class="form-control mb-2">
    <input type="text" name="unitID" placeholder="Unit" class="form-control mb-2">
    <input type="text" name="category" placeholder="Category" class="form-control mb-2">
    <input type="text" name="svcStatus" placeholder="Status" class="form-control mb-2">
    <input type="text" name="username" placeholder="Username" required class="form-control mb-2">
    <input type="password" name="password" placeholder="Password" required class="form-control mb-2">
    <input type="text" name="role" placeholder="Role" required class="form-control mb-2" value="Staff">
    <!-- Add more fields as needed -->
    <button type="submit" class="btn btn-success">Create Staff</button>
    <a href="admin_staff_list.php" class="btn btn-secondary">Cancel</a>
</form>