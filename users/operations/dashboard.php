<?php
    require_once __DIR__ . '/../includes/admin_init.php';

    // --- Operations CRUD Model ---
    class OperationAdmin {
        private $db;
        public function __construct($db) { $this->db = $db; }

        public function list() {
            return $this->db->query("SELECT * FROM Operations ORDER BY opDate DESC")->results();
        }
        public function get($id) {
            return $this->db->query("SELECT * FROM Operations WHERE id = ?", [$id])->first();
        }
        public function create($data) {
            $fields = ['opName','opType','opDesc','opDate','location','createdBy'];
            $insert = [];
            foreach($fields as $f) if(isset($data[$f])) $insert[$f] = $data[$f];
            $this->db->insert('Operations', $insert);
            return $this->db->lastId();
        }
        public function update($id, $data) {
            $fields = ['opName','opType','opDesc','opDate','location'];
            $update = [];
            foreach($fields as $f) if(isset($data[$f])) $update[$f] = $data[$f];
            $this->db->update('Operations', $id, $update, 'id');
        }
        public function delete($id) {
            $this->db->deleteById('Operations', $id, 'id');
        }
    }
    $operationAdmin = new OperationAdmin($db);

    // --- Handle Add/Edit/Delete ---
    $errors = $successes = [];
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!Token::check($_POST['csrf'] ?? '')) {
            $errors[] = "Invalid CSRF token.";
        } elseif (isset($_POST['addOperation'])) {
            $data = $_POST;
            $data['createdBy'] = $user->data()->username;
            $operationAdmin->create($data);
            $successes[] = "Operation created.";
            Redirect::to('dashboard.php?msg=created');
        } elseif (isset($_POST['editOperation'])) {
            $operationAdmin->update($_POST['id'], $_POST);
            $successes[] = "Operation updated.";
            Redirect::to('dashboard.php?msg=updated');
        } elseif (isset($_POST['delete_id'])) {
            $operationAdmin->delete($_POST['delete_id']);
            $successes[] = "Operation deleted.";
            Redirect::to('dashboard.php?msg=deleted');
        } elseif (isset($_POST['assignStaff'])) {
            $opID = $_POST['opID'];
            $svcNo = $_POST['svcNo'];
            if ($opID && $svcNo) {
                $db->insert('OperationStaff', ['opID' => $opID, 'svcNo' => $svcNo]);
                $successes[] = "Staff assigned to operation.";
                Redirect::to('dashboard.php?msg=assigned');
            }
        }
    }

    // --- Edit Mode ---
    $editOperation = null;
    if (isset($_GET['edit'])) {
        $editOperation = $operationAdmin->get($_GET['edit']);
    }

    $operations = $operationAdmin->list();
    $staffList = $db->query("SELECT svcNo, fName, sName FROM Staff ORDER BY fName, sName")->results();
    $opList = $db->query("SELECT id, opName FROM Operations ORDER BY opDate DESC")->results();

    // --- Reports ---
    $totalOps = $db->query("SELECT COUNT(*) as total FROM Operations")->first()->total;
    $personnelOps = $db->query("SELECT s.svcNo, s.fName, s.sName, COUNT(os.opID) as opsCount
        FROM OperationStaff os
        JOIN Staff s ON os.svcNo = s.svcNo
        GROUP BY s.svcNo, s.fName, s.sName
        ORDER BY opsCount DESC")->results();
    $disciplineReport = $db->query("SELECT s.svcNo, s.fName, s.sName, COUNT(d.id) as incidents
        FROM Discipline d
        JOIN Staff s ON d.svcNo = s.svcNo
        GROUP BY s.svcNo, s.fName, s.sName
        ORDER BY incidents DESC")->results();
?>
<div class="right_col" role="main">
  <div class="row tile_count">
    <div class="col-md-3 col-sm-6 col-xs-12 tile_stats_count">
      <span class="count_top"><i class="fa fa-flag"></i> Number of Operations</span>
      <div class="count"><?= (int)$totalOps ?></div>
      <span class="count_bottom"><i class="green">All Time</i></span>
    </div>
  </div>

  <!-- Alerts -->
  <?php foreach($errors as $e): ?>
    <div class="alert alert-danger"><?=htmlspecialchars($e)?></div>
  <?php endforeach; ?>
  <?php foreach($successes as $s): ?>
    <div class="alert alert-success"><?=htmlspecialchars($s)?></div>
  <?php endforeach; ?>

  <!-- Actions -->
  <div class="row mb-3">
    <div class="col-md-6">
      <div class="x_panel">
        <div class="x_title"><h2>Create Operation</h2></div>
        <div class="x_content">
          <form method="post">
            <input type="hidden" name="csrf" value="<?=Token::generate();?>">
            <?php if($editOperation): ?>
              <input type="hidden" name="id" value="<?=htmlspecialchars($editOperation->id)?>">
            <?php endif; ?>
            <div class="form-group mb-2">
              <input type="text" name="opName" class="form-control" placeholder="Operation Name" value="<?=htmlspecialchars($editOperation->opName ?? '')?>" required>
            </div>
            <div class="form-group mb-2">
              <input type="text" name="opType" class="form-control" placeholder="Operation Type" value="<?=htmlspecialchars($editOperation->opType ?? '')?>" required>
            </div>
            <div class="form-group mb-2">
              <input type="text" name="opDesc" class="form-control" placeholder="Description" value="<?=htmlspecialchars($editOperation->opDesc ?? '')?>">
            </div>
            <div class="form-group mb-2">
              <input type="date" name="opDate" class="form-control" value="<?=htmlspecialchars($editOperation->opDate ?? '')?>" required>
            </div>
            <div class="form-group mb-2">
              <input type="text" name="location" class="form-control" placeholder="Location" value="<?=htmlspecialchars($editOperation->location ?? '')?>">
            </div>
            <button type="submit" name="<?= $editOperation ? 'editOperation' : 'addOperation' ?>" class="btn btn-primary btn-block"><?= $editOperation ? 'Update' : 'Create' ?> Operation</button>
          </form>
        </div>
      </div>
    </div>
    <div class="col-md-6">
      <div class="x_panel">
        <div class="x_title"><h2>Assign Staff to Operation</h2></div>
        <div class="x_content">
          <form method="post">
            <input type="hidden" name="csrf" value="<?=Token::generate();?>">
            <div class="form-group mb-2">
              <select name="opID" class="form-control" required>
                <option value="">Select Operation</option>
                <?php foreach($opList as $op): ?>
                  <option value="<?=htmlspecialchars($op->id)?>"><?=htmlspecialchars($op->opName)?></option>
                <?php endforeach;?>
              </select>
            </div>
            <div class="form-group mb-2">
              <select name="svcNo" class="form-control" required>
                <option value="">Select Staff</option>
                <?php foreach($staffList as $s): ?>
                  <option value="<?=htmlspecialchars($s->svcNo)?>">
                    <?=htmlspecialchars($s->svcNo . ' - ' . $s->fName . ' ' . $s->sName)?>
                  </option>
                <?php endforeach;?>
              </select>
            </div>
            <button type="submit" name="assignStaff" class="btn btn-warning btn-block">Assign</button>
          </form>
        </div>
      </div>
    </div>
  </div>

  <!-- Operations Table -->
  <div class="row">
    <div class="col-md-12">
      <div class="x_panel">
        <div class="x_title"><h2>Operations List</h2></div>
        <div class="x_content">
          <table class="table table-hover table-bordered table-sm">
            <thead>
              <tr>
                <th>ID</th>
                <th>Name</th>
                <th>Type</th>
                <th>Description</th>
                <th>Date</th>
                <th>Location</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach($operations as $op): ?>
                <tr>
                  <td><?=htmlspecialchars($op->id)?></td>
                  <td><?=htmlspecialchars($op->opName)?></td>
                  <td><?=htmlspecialchars($op->opType)?></td>
                  <td><?=htmlspecialchars($op->opDesc)?></td>
                  <td><?=htmlspecialchars($op->opDate)?></td>
                  <td><?=htmlspecialchars($op->location)?></td>
                  <td>
                    <a href="?edit=<?=$op->id?>" class="btn btn-sm btn-primary">Edit</a>
                    <form method="post" style="display:inline;">
                      <input type="hidden" name="delete_id" value="<?=$op->id?>">
                      <input type="hidden" name="csrf" value="<?=Token::generate();?>">
                      <button type="submit" class="btn btn-sm btn-danger" onclick="return confirm('Delete this operation?')">Delete</button>
                    </form>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>

  <!-- Reports -->
  <div class="row" id="reports">
    <div class="col-md-6">
      <div class="x_panel">
        <div class="x_title"><h2>Personnel Participation in Operations</h2></div>
        <div class="x_content">
          <table class="table table-sm table-bordered mt-2">
            <thead><tr><th>Service No</th><th>Name</th><th>Operations Participated</th></tr></thead>
            <tbody>
              <?php foreach($personnelOps as $row): ?>
                <tr>
                  <td><?=htmlspecialchars($row->svcNo)?></td>
                  <td><?=htmlspecialchars($row->fName . ' ' . $row->sName)?></td>
                  <td><?=htmlspecialchars($row->opsCount)?></td>
                </tr>
              <?php endforeach;?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
    <div class="col-md-6">
      <div class="x_panel">
        <div class="x_title"><h2>Operational Conduct & Discipline</h2></div>
        <div class="x_content">
          <table class="table table-sm table-bordered mt-2">
            <thead><tr><th>Service No</th><th>Name</th><th>Discipline Incidents</th></tr></thead>
            <tbody>
              <?php foreach($disciplineReport as $row): ?>
                <tr>
                  <td><?=htmlspecialchars($row->svcNo)?></td>
                  <td><?=htmlspecialchars($row->fName . ' ' . $row->sName)?></td>
                  <td><?=htmlspecialchars($row->incidents)?></td>
                </tr>
              <?php endforeach;?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>
<?php require_once $abs_us_root . $us_url_root . 'users/includes/html_footer.php'; ?>