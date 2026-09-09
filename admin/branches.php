<?php
/**
 * ARMIS — Manage Branches
 *
 * NEW FILE (branch-scoping upgrade). Lets a System Administrator create a
 * new branch (e.g. "Legal") without any code change: the moment it's saved
 * here it's available in the Role & Branch Assignment dropdown, gains
 * sidebar navigation for anyone posted to it, and is enforced by
 * hasModuleAccess()/canAlterRecord() in shared/rbac.php.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/shared/database_connection.php';
require_once dirname(__DIR__) . '/shared/rbac.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ' . dirname($_SERVER['PHP_SELF']) . '/../login.php');
    exit();
}

requireModuleAccess('admin');
requireBranchAdmin();

$pageTitle = "Manage Branches";
$moduleName = "System Admin";
$moduleIcon = "sitemap";
$currentPage = "branches";

require_once __DIR__ . '/includes/sidebar_nav.php';

$pdo = getDbConnection();
$branches = getAllBranches(false); // include inactive so admin can reactivate

include dirname(__DIR__) . '/shared/header.php';
include dirname(__DIR__) . '/shared/sidebar.php';
?>

<div class="content-wrapper with-sidebar">
  <div class="container-fluid">
    <div class="main-content">
      <div class="row mb-4">
        <div class="col-12">
          <div class="d-flex justify-content-between align-items-center">
            <div>
              <h1 class="admin-section-title"><i class="fas fa-sitemap text-primary"></i> Manage Branches</h1>
              <p class="text-muted mb-0">Create and manage ARMIS departments. A new branch here needs no code deployment to start working.</p>
            </div>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#branchModal" onclick="openBranchModal()">
              <i class="fas fa-plus"></i> New Branch
            </button>
          </div>
        </div>
      </div>

      <div class="card">
        <div class="card-header"><h5 class="card-title mb-0">All Branches (<?= count($branches) ?>)</h5></div>
        <div class="card-body">
          <div class="table-responsive">
            <table class="table table-striped table-hover">
              <thead class="table-dark">
                <tr>
                  <th>Branch</th><th>Code</th><th>Description</th><th>Reach</th><th>Status</th><th>Staffed</th><th>Actions</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($branches as $b): ?>
                  <?php
                    $countStmt = $pdo->prepare("SELECT COUNT(*) c FROM staff WHERE branch_id = :id");
                    $countStmt->execute(['id' => $b['id']]);
                    $staffed = (int)$countStmt->fetch()['c'];
                  ?>
                  <tr>
                    <td><i class="fas fa-<?= htmlspecialchars($b['icon']) ?>" style="color:<?= htmlspecialchars($b['color']) ?>;margin-right:8px;"></i><?= htmlspecialchars($b['name']) ?></td>
                    <td><code><?= htmlspecialchars($b['code']) ?></code></td>
                    <td class="text-muted"><?= htmlspecialchars($b['description'] ?? '') ?></td>
                    <td>
                      <?php if (!empty($b['is_org_wide'])): ?>
                        <span class="badge bg-warning">Army-wide</span>
                      <?php else: ?>
                        <span class="badge bg-secondary">Own branch only</span>
                      <?php endif; ?>
                    </td>
                    <td><span class="badge bg-<?= $b['status'] === 'Active' ? 'success' : 'secondary' ?>"><?= htmlspecialchars($b['status']) ?></span></td>
                    <td><?= $staffed ?> staff</td>
                    <td>
                      <button class="btn btn-outline-primary btn-sm" onclick='openBranchModal(<?= json_encode($b) ?>)' data-bs-toggle="modal" data-bs-target="#branchModal">
                        <i class="fas fa-edit"></i>
                      </button>
                      <?php if ($b['status'] === 'Active'): ?>
                        <button class="btn btn-outline-danger btn-sm" onclick="toggleStatus(<?= (int)$b['id'] ?>, 'Inactive')"><i class="fas fa-ban"></i></button>
                      <?php else: ?>
                        <button class="btn btn-outline-secondary btn-sm" onclick="toggleStatus(<?= (int)$b['id'] ?>, 'Active')"><i class="fas fa-undo"></i></button>
                      <?php endif; ?>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <div class="card mt-4">
        <div class="card-body">
          <h5><i class="fas fa-circle-info text-primary"></i> How "Army-wide" reach works</h5>
          <p class="text-muted mb-0">
            Only Admin Branch is flagged Army-wide by default, because personnel administration
            inherently covers everyone. For every other branch, a Chief Clerk / Staff Officer
            posted to it can only alter records belonging to staff assigned to that same branch,
            and a Director General posted to it only sees a read-only snapshot of that branch.
            Flip "Reach" here only for a branch whose Chief Clerks genuinely need to touch every
            staff record, not just their own branch's.
          </p>
        </div>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="branchModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="branchModalTitle">New Branch</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form id="branchForm">
        <div class="modal-body">
          <input type="hidden" name="id" id="f_id">
          <div class="mb-3">
            <label class="form-label">Branch Name</label>
            <input type="text" class="form-control" name="name" id="f_name" required placeholder="e.g. Legal Branch">
            <small class="text-muted" id="codePreview"></small>
          </div>
          <div class="mb-3">
            <label class="form-label">Description</label>
            <input type="text" class="form-control" name="description" id="f_description" placeholder="One line about this branch">
          </div>
          <div class="row">
            <div class="col-md-6 mb-3">
              <label class="form-label">Icon (Font Awesome name)</label>
              <input type="text" class="form-control" name="icon" id="f_icon" placeholder="e.g. gavel">
            </div>
            <div class="col-md-6 mb-3">
              <label class="form-label">Accent Color</label>
              <input type="text" class="form-control" name="color" id="f_color" placeholder="#8a6d2f">
            </div>
          </div>
          <div class="form-check">
            <input class="form-check-input" type="checkbox" name="is_org_wide" id="f_org_wide" value="1">
            <label class="form-check-label" for="f_org_wide">
              Army-wide reach (CC/SO here can alter <em>any</em> staff record, not just their own branch's)
            </label>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary">Save Branch</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
function openBranchModal(branch) {
  document.getElementById('branchForm').reset();
  document.getElementById('f_id').value = branch ? branch.id : '';
  document.getElementById('f_name').value = branch ? branch.name : '';
  document.getElementById('f_description').value = branch ? (branch.description || '') : '';
  document.getElementById('f_icon').value = branch ? branch.icon : 'folder';
  document.getElementById('f_color').value = branch ? branch.color : '#8a6d2f';
  document.getElementById('f_org_wide').checked = branch ? !!Number(branch.is_org_wide) : false;
  document.getElementById('branchModalTitle').innerText = branch ? 'Edit Branch' : 'New Branch';
  updateCodePreview();
}
function updateCodePreview() {
  const name = document.getElementById('f_name').value.trim().toLowerCase().replace(/[^a-z0-9]+/g, '_').replace(/^_|_$/g, '');
  document.getElementById('codePreview').innerText = name ? ('Branch code will be: ' + name) : '';
}
document.getElementById('f_name').addEventListener('input', updateCodePreview);

document.getElementById('branchForm').addEventListener('submit', async function (e) {
  e.preventDefault();
  const fd = new FormData(this);
  fd.append('action', fd.get('id') ? 'update' : 'create');
  if (!fd.get('is_org_wide')) fd.set('is_org_wide', '0');

  const res = await fetch('/Armis2/admin/branches_ajax.php', { method: 'POST', body: fd });
  const data = await res.json();
  if (data.ok) { location.reload(); } else { alert(data.message || 'Something went wrong.'); }
});

async function toggleStatus(id, status) {
  if (!confirm('Mark this branch ' + status.toLowerCase() + '?')) return;
  const fd = new FormData();
  fd.append('action', 'toggle_status');
  fd.append('id', id);
  fd.append('status', status);
  const res = await fetch('/Armis2/admin/branches_ajax.php', { method: 'POST', body: fd });
  const data = await res.json();
  if (data.ok) location.reload(); else alert(data.message);
}
</script>

<?php include dirname(__DIR__) . '/shared/footer.php'; ?>
