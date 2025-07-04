<?php
    require_once __DIR__ . '/../includes/admin_init.php';
    require_once __DIR__ . '/../models/StaffAdmin.php';
    $staffAdmin = new StaffAdmin($db);

    $errors = [];
    $successes = [];
    $form_valid = true;

    // Advanced form validation and Add Staff logic
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['addStaff'])) {
        if (!Token::check($_POST['csrf'] ?? '')) {
            $errors[] = "Invalid CSRF token.";
            $form_valid = false;
        } else {
            // Advanced validation
            $svcNo = trim($_POST['svcNo'] ?? '');
            $fName = trim($_POST['fName'] ?? '');
            $sName = trim($_POST['sName'] ?? '');
            $username = trim($_POST['username'] ?? '');
            $password = $_POST['password'] ?? '';
            $confirm = $_POST['confirm'] ?? '';
            $rankID = trim($_POST['rankID'] ?? '');
            $unitID = trim($_POST['unitID'] ?? '');
            $category = trim($_POST['category'] ?? '');
            $svcStatus = trim($_POST['svcStatus'] ?? 'Active');
            $role = trim($_POST['role'] ?? 'Staff');

            // Required fields
            if ($svcNo === '' || $fName === '' || $sName === '' || $username === '' || $password === '' || $confirm === '') {
                $errors[] = "All required fields must be filled.";
                $form_valid = false;
            }
            // Username validation
            if (!preg_match('/^[a-zA-Z0-9_]{4,32}$/', $username)) {
                $errors[] = "Username must be 4-32 characters and contain only letters, numbers, and underscores.";
                $form_valid = false;
            }
            // Password validation
            if (strlen($password) < 8) {
                $errors[] = "Password must be at least 8 characters.";
                $form_valid = false;
            }
            if ($password !== $confirm) {
                $errors[] = "Passwords do not match.";
                $form_valid = false;
            }
            // Unique checks
            $exists = $db->query("SELECT * FROM Staff WHERE username = ? OR svcNo = ?", [$username, $svcNo])->count();
            if ($exists > 0) {
                $errors[] = "Username or Service No already exists.";
                $form_valid = false;
            }
            // Optional: Validate rank/unit/category if you have reference tables

            if ($form_valid) {
                $data = [
                    'svcNo' => $svcNo,
                    'fName' => $fName,
                    'sName' => $sName,
                    'username' => $username,
                    'password' => $password,
                    'rankID' => $rankID,
                    'unitID' => $unitID,
                    'category' => $category,
                    'svcStatus' => $svcStatus,
                    'role' => $role,
                    'createdBy' => $user->data()->username
                ];
                $staffAdmin->create($data);
                $successes[] = "Staff member added successfully.";
                Redirect::to('admin_staff_list.php?msg=created');
            }
        }
    }

    // Handle Delete
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_svcNo'])) {
        $staffAdmin->delete($_POST['delete_svcNo']);
        $successes[] = "Staff member deleted.";
        Redirect::to('admin_staff_list.php?msg=deleted');
    }

    // List staff by rankID DESC
    $staffList = $db->query("SELECT * FROM Staff ORDER BY rankID DESC, fName")->results();
?>

<h2>Staff List</h2>
<?php foreach($errors as $e): ?>
  <div class="alert alert-danger"><?=htmlspecialchars($e)?></div>
<?php endforeach; ?>
<?php foreach($successes as $s): ?>
  <div class="alert alert-success"><?=htmlspecialchars($s)?></div>
<?php endforeach; ?>
<div class="mb-2 text-end">
  <button class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addstaff"><i class="fa fa-plus"></i> Add Staff Member</button>
</div>
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

<!-- Add Staff Member Modal -->
<div id="addstaff" class="modal fade" role="dialog" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <form class="form-signup mb-0" action="" method="POST">
        <div class="modal-header">
          <h4 class="modal-title">Add Staff Member</h4>
          <button type="button" class="btn btn-outline-secondary float-right" data-bs-dismiss="modal">&times;</button>
        </div>
        <div class="modal-body">
          <div class="form-group mb-2">
            <label>Service No *</label>
            <input type="text" class="form-control form-control-sm" name="svcNo" required pattern="[A-Za-z0-9\-]{2,32}">
          </div>
          <div class="form-group mb-2">
            <label>First Name *</label>
            <input type="text" class="form-control form-control-sm" name="fName" required pattern="[A-Za-z\s\-]{2,50}">
          </div>
          <div class="form-group mb-2">
            <label>Surname *</label>
            <input type="text" class="form-control form-control-sm" name="sName" required pattern="[A-Za-z\s\-]{2,50}">
          </div>
          <div class="form-group mb-2">
            <label>Username *</label>
            <input type="text" class="form-control form-control-sm" name="username" required pattern="[A-Za-z0-9_]{4,32}">
          </div>
          <div class="form-group mb-2">
            <label>Password *</label>
            <input type="password" class="form-control form-control-sm" name="password" required minlength="8">
          </div>
          <div class="form-group mb-2">
            <label>Confirm Password *</label>
            <input type="password" class="form-control form-control-sm" name="confirm" required minlength="8">
          </div>
          <div class="form-group mb-2">
            <label>Rank</label>
            <input type="text" class="form-control form-control-sm" name="rankID">
          </div>
          <div class="form-group mb-2">
            <label>Unit</label>
            <input type="text" class="form-control form-control-sm" name="unitID">
          </div>
          <div class="form-group mb-2">
            <label>Category</label>
            <input type="text" class="form-control form-control-sm" name="category">
          </div>
          <div class="form-group mb-2">
            <label>Status</label>
            <input type="text" class="form-control form-control-sm" name="svcStatus" value="Active">
          </div>
          <div class="form-group mb-2">
            <label>Role</label>
            <input type="text" class="form-control form-control-sm" name="role" value="Staff">
          </div>
          <input type="hidden" name="csrf" value="<?=Token::generate();?>" />
        </div>
        <div class="modal-footer">
          <button type="submit" name="addStaff" class="btn btn-primary">Add Staff Member</button>
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
        </div>
      </form>
    </div>
  </div>
</div>