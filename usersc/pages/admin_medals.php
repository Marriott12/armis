<?php
    require_once __DIR__ . '/../includes/admin_init.php';

    // --- Medals CRUD Model ---
    class MedalAdmin {
        private $db;
        public function __construct($db) { $this->db = $db; }

        public function list() {
            return $this->db->query("SELECT * FROM Medals ORDER BY issueDate DESC")->results();
        }
        public function get($id) {
            return $this->db->query("SELECT * FROM Medals WHERE id = ?", [$id])->first();
        }
        public function create($data) {
            $fields = ['svcNo','medalName','medalDesc','issueDate','authority','comment','createdBy'];
            $insert = [];
            foreach($fields as $f) if(isset($data[$f])) $insert[$f] = $data[$f];
            $this->db->insert('Medals', $insert);
            return $this->db->lastId();
        }
        public function update($id, $data) {
            $fields = ['medalName','medalDesc','issueDate','authority','comment'];
            $update = [];
            foreach($fields as $f) if(isset($data[$f])) $update[$f] = $data[$f];
            $this->db->update('Medals', $id, $update, 'id');
        }
        public function delete($id) {
            $this->db->deleteById('Medals', $id, 'id');
        }
    }
    $medalAdmin = new MedalAdmin($db);

    // --- Handle Add/Edit/Delete ---
    $errors = $successes = [];
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!Token::check($_POST['csrf'] ?? '')) {
            $errors[] = "Invalid CSRF token.";
        } elseif (isset($_POST['addMedal'])) {
            $data = $_POST;
            $data['createdBy'] = $user->data()->username;
            $medalAdmin->create($data);
            $successes[] = "Medal awarded.";
            Redirect::to('admin_medals.php?msg=created');
        } elseif (isset($_POST['editMedal'])) {
            $medalAdmin->update($_POST['id'], $_POST);
            $successes[] = "Medal updated.";
            Redirect::to('admin_medals.php?msg=updated');
        } elseif (isset($_POST['delete_id'])) {
            $medalAdmin->delete($_POST['delete_id']);
            $successes[] = "Medal deleted.";
            Redirect::to('admin_medals.php?msg=deleted');
        }
    }

    // --- Edit Mode ---
    $editMedal = null;
    if (isset($_GET['edit'])) {
        $editMedal = $medalAdmin->get($_GET['edit']);
    }

    $medals = $medalAdmin->list();
?>

<div class="row">
  <div class="col-12 mb-2">
    <h2>Medals Awarded</h2>
    <?php foreach($errors as $e): ?>
      <div class="alert alert-danger"><?=htmlspecialchars($e)?></div>
    <?php endforeach; ?>
    <?php foreach($successes as $s): ?>
      <div class="alert alert-success"><?=htmlspecialchars($s)?></div>
    <?php endforeach; ?>
    <div class="row" style="margin-top:1vw;">
      <div class="col-6"></div>
      <div class="col-6 text-end">
        <button class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addmedal"><i class="fa fa-plus"></i> Award Medal</button>
      </div>
    </div>
  </div>
  <div class="col-12">
    <div class="card">
      <div class="card-body">
        <table class="table table-hover table-bordered table-sm">
          <thead>
            <tr>
              <th>ID</th>
              <th>Service No</th>
              <th>Medal Name</th>
              <th>Description</th>
              <th>Date Awarded</th>
              <th>Authority</th>
              <th>Comment</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach($medals as $medal): ?>
              <tr>
                <td><?=htmlspecialchars($medal->id)?></td>
                <td><?=htmlspecialchars($medal->svcNo)?></td>
                <td><?=htmlspecialchars($medal->medalName)?></td>
                <td><?=htmlspecialchars($medal->medalDesc)?></td>
                <td><?=htmlspecialchars($medal->issueDate)?></td>
                <td><?=htmlspecialchars($medal->authority)?></td>
                <td><?=htmlspecialchars($medal->comment)?></td>
                <td>
                  <a href="?edit=<?=$medal->id?>" class="btn btn-sm btn-primary">Edit</a>
                  <form method="post" style="display:inline;">
                    <input type="hidden" name="delete_id" value="<?=$medal->id?>">
                    <input type="hidden" name="csrf" value="<?=Token::generate();?>">
                    <button type="submit" class="btn btn-sm btn-danger" onclick="return confirm('Delete this medal?')">Delete</button>
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

<!-- Add/Edit Medal Modal -->
<div id="addmedal" class="modal fade" role="dialog" tabindex="-1" <?=($editMedal ? 'style="display:block;"' : '')?>>
  <div class="modal-dialog">
    <div class="modal-content">
      <form class="form-signup mb-0" action="" method="POST">
        <div class="modal-header">
          <h4 class="modal-title"><?= $editMedal ? 'Edit Medal' : 'Award Medal' ?></h4>
          <button type="button" class="btn btn-outline-secondary float-right" data-bs-dismiss="modal">&times;</button>
        </div>
        <div class="modal-body">
          <?php if($editMedal): ?>
            <input type="hidden" name="id" value="<?=htmlspecialchars($editMedal->id)?>">
          <?php endif; ?>
          <div class="form-group mb-2">
            <label>Service No</label>
            <input type="text" class="form-control form-control-sm" name="svcNo" value="<?=htmlspecialchars($editMedal->svcNo ?? '')?>" required>
          </div>
          <div class="form-group mb-2">
            <label>Medal Name</label>
            <input type="text" class="form-control form-control-sm" name="medalName" value="<?=htmlspecialchars($editMedal->medalName ?? '')?>" required>
          </div>
          <div class="form-group mb-2">
            <label>Description</label>
            <input type="text" class="form-control form-control-sm" name="medalDesc" value="<?=htmlspecialchars($editMedal->medalDesc ?? '')?>">
          </div>
          <div class="form-group mb-2">
            <label>Date Awarded</label>
            <input type="date" class="form-control form-control-sm" name="issueDate" value="<?=htmlspecialchars($editMedal->issueDate ?? '')?>" required>
          </div>
          <div class="form-group mb-2">
            <label>Authority</label>
            <input type="text" class="form-control form-control-sm" name="authority" value="<?=htmlspecialchars($editMedal->authority ?? '')?>">
          </div>
          <div class="form-group mb-2">
            <label>Comment</label>
            <input type="text" class="form-control form-control-sm" name="comment" value="<?=htmlspecialchars($editMedal->comment ?? '')?>">
          </div>
          <input type="hidden" name="csrf" value="<?=Token::generate();?>" />
        </div>
        <div class="modal-footer">
          <?php if($editMedal): ?>
            <button type="submit" name="editMedal" class="btn btn-primary">Update Medal</button>
          <?php else: ?>
            <button type="submit" name="addMedal" class="btn btn-primary">Award Medal</button>
          <?php endif; ?>
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
        </div>
      </form>
    </div>
  </div>
</div>
<?php if($editMedal): ?>
<script>
  // Auto-open modal if editing
  window.onload = function() {
    var modal = document.getElementById('addmedal');
    if(modal) $(modal).modal('show');
  }
</script>
<?php endif; ?>