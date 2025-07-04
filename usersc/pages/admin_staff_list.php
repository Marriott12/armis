<?php
    require_once __DIR__ . '/../includes/admin_init.php';
    require_once __DIR__ . '/../models/StaffAdmin.php';
    $staffAdmin = new StaffAdmin($db);

    // Modify the list method to order by rankID DESC
    $staffList = $db->query("SELECT * FROM Staff ORDER BY rankID DESC, fName")->results();

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_svcNo'])) {
        $staffAdmin->delete($_POST['delete_svcNo']);
        Redirect::to('admin_staff_list.php?msg=deleted');
    }
    ?>
<h2>Staff List</h2>
<a href="admin_staff_create.php" class="btn btn-success mb-2">Add New Staff</a>
<table class="table table-bordered">
    <thead>
        <tr>
            <th>Service No</th>
            <th>Name</th>
            <th>Rank</th>
            <th>Unit</th>
            <th>Category</th>
            <th>Status</th>
            <th>Actions</th>
        </tr>
    </thead>
    <tbody>
    <?php foreach($staffList as $staff): ?>
        <tr>
            <td><?=htmlspecialchars($staff->svcNo)?></td>
            <td><?=htmlspecialchars($staff->fName . ' ' . $staff->sName)?></td>
            <td><?=htmlspecialchars($staff->rankID)?></td>
            <td><?=htmlspecialchars($staff->unitID)?></td>
            <td><?=htmlspecialchars($staff->category)?></td>
            <td><?=htmlspecialchars($staff->svcStatus)?></td>
            <td>
                <a href="admin_staff_edit.php?svcNo=<?=$staff->svcNo?>" class="btn btn-sm btn-primary">Edit</a>
                <form method="post" style="display:inline;">
                    <input type="hidden" name="delete_svcNo" value="<?=$staff->svcNo?>">
                    <button type="submit" class="btn btn-sm btn-danger" onclick="return confirm('Delete this staff?')">Delete</button>
                </form>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
<?php require_once $abs_us_root . $us_url_root . 'users/includes/html_footer.php'; ?>