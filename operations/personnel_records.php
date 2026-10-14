<?php
require_once dirname(__DIR__) . '/shared/csrf.php';
if (session_status() === PHP_SESSION_NONE) session_start();
require_once dirname(__DIR__) . '/shared/rbac.php';
require_once dirname(__DIR__) . '/shared/branch_owned_records.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: /Armis2/login.php?return_url=' . urlencode($_SERVER['REQUEST_URI'] ?? ''));
    exit;
}
requireModuleAccess('operations');
$pdo = getDbConnection();
$canWrite = (($_SESSION['role'] ?? '') === 'admin') || hasPermission(PERM_MANAGE_OPERATIONS);

$message = '';
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    if (!$canWrite) {
        http_response_code(403);
        exit('Access denied. Operations personnel-management permission is required.');
    }
    try {
        $action = $_POST['action'] ?? '';
        $svcNo = trim((string)($_POST['svcNo'] ?? ''));
        if (!armisScopedStaffExists($pdo, $svcNo)) throw new RuntimeException('The selected personnel record is outside your branch scope or does not exist.');

        if ($action === 'operation_save') {
            $id = (int)($_POST['id'] ?? 0);
            $opId = trim((string)($_POST['opId'] ?? ''));
            if ($opId === '') throw new RuntimeException('Operation is required.');
            $check = $pdo->prepare('SELECT 1 FROM operation WHERE opId = ? LIMIT 1');
            $check->execute([$opId]);
            if (!$check->fetchColumn()) throw new RuntimeException('The selected operation does not exist.');
            $data = [$opId, $_POST['opStart'] ?: null, $_POST['opEnd'] ?: null, trim((string)($_POST['remarks'] ?? '')), trim((string)($_POST['authID'] ?? ''))];
            if ($id > 0) {
                $stmt = $pdo->prepare('UPDATE staff_operation SET opId=?, opStart=?, opEnd=?, remarks=?, authID=? WHERE id=? AND svcNo=?');
                $stmt->execute([...$data, $id, $svcNo]);
                $message = 'Operation record updated successfully.';
            } else {
                $stmt = $pdo->prepare('INSERT INTO staff_operation (svcNo, opId, opStart, opEnd, remarks, authID) VALUES (?, ?, ?, ?, ?, ?)');
                $stmt->execute([$svcNo, ...$data]);
                $message = 'Operation record added successfully.';
            }
        } elseif ($action === 'operation_delete') {
            $stmt = $pdo->prepare('DELETE FROM staff_operation WHERE id=? AND svcNo=?');
            $stmt->execute([(int)$_POST['id'], $svcNo]);
            $message = 'Operation record removed.';
        } elseif ($action === 'deployment_save') {
            $id = (int)($_POST['id'] ?? 0);
            $name = trim((string)($_POST['deployment_name'] ?? ''));
            if ($name === '') throw new RuntimeException('Deployment name is required.');
            $fields = [
                trim((string)($_POST['deployment_name'] ?? '')), trim((string)($_POST['mission_type'] ?? '')),
                trim((string)($_POST['location'] ?? '')), trim((string)($_POST['country'] ?? '')),
                $_POST['startDate'] ?: null, $_POST['endDate'] ?: null,
                ($_POST['durationMonths'] ?? '') !== '' ? (int)$_POST['durationMonths'] : null,
                trim((string)($_POST['deployment_status'] ?? '')), trim((string)($_POST['rank_during_deployment'] ?? '')),
                trim((string)($_POST['role_during_deployment'] ?? '')), trim((string)($_POST['commanding_officer'] ?? '')),
                ($_POST['deployment_allowance'] ?? '') !== '' ? (float)$_POST['deployment_allowance'] : null,
                trim((string)($_POST['notes'] ?? ''))
            ];
            if ($id > 0) {
                $stmt = $pdo->prepare('UPDATE staff_deployments SET deployment_name=?, mission_type=?, location=?, country=?, startDate=?, endDate=?, durationMonths=?, deployment_status=?, rank_during_deployment=?, role_during_deployment=?, commanding_officer=?, deployment_allowance=?, notes=? WHERE id=? AND svcNo=?');
                $stmt->execute([...$fields, $id, $svcNo]);
                $message = 'Deployment record updated successfully.';
            } else {
                $stmt = $pdo->prepare('INSERT INTO staff_deployments (svcNo, deployment_name, mission_type, location, country, startDate, endDate, durationMonths, deployment_status, rank_during_deployment, role_during_deployment, commanding_officer, deployment_allowance, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
                $stmt->execute([$svcNo, ...$fields]);
                $message = 'Deployment record added successfully.';
            }
        } elseif ($action === 'deployment_delete') {
            $stmt = $pdo->prepare('DELETE FROM staff_deployments WHERE id=? AND svcNo=?');
            $stmt->execute([(int)$_POST['id'], $svcNo]);
            $message = 'Deployment record removed.';
        }
        logAccess('operations', 'personnel_record_change', true, $action . ' ' . $svcNo);
    } catch (Throwable $e) {
        $error = $e->getMessage();
        logAccess('operations', 'personnel_record_change', false, $e->getMessage());
    }
}

[$scopeSql, $scopeParams] = armisScopedStaffWhere('s');
$staffStmt = $pdo->prepare("SELECT s.svcNo, s.fName, s.mName, s.lName, s.rankId, s.unitId FROM staff s WHERE {$scopeSql} AND s.svcStatus='Active' ORDER BY s.lName, s.fName, s.svcNo");
$staffStmt->execute($scopeParams);
$personnel = $staffStmt->fetchAll(PDO::FETCH_ASSOC);
$operations = $pdo->query('SELECT opId, opType FROM operation ORDER BY opId')->fetchAll(PDO::FETCH_ASSOC);
$selectedSvc = trim((string)($_GET['svcNo'] ?? $_POST['svcNo'] ?? ''));
$selected = $selectedSvc ? armisScopedStaffExists($pdo, $selectedSvc) : null;
if (!$selected && !empty($personnel)) { $selected = $personnel[0]; $selectedSvc = $selected['svcNo']; }
$ops = $deps = [];
if ($selected) {
    $st=$pdo->prepare('SELECT so.*, o.opType FROM staff_operation so LEFT JOIN operation o ON o.opId=so.opId WHERE so.svcNo=? ORDER BY so.opStart DESC, so.id DESC'); $st->execute([$selectedSvc]); $ops=$st->fetchAll(PDO::FETCH_ASSOC);
    $st=$pdo->prepare('SELECT * FROM staff_deployments WHERE svcNo=? ORDER BY startDate DESC, id DESC'); $st->execute([$selectedSvc]); $deps=$st->fetchAll(PDO::FETCH_ASSOC);
}
$pageTitle='Personnel Operations & Deployments'; $moduleName='Operations'; $moduleIcon='map-marked-alt'; $currentPage='personnel_records';
require_once __DIR__.'/includes/sidebar_nav.php'; require_once dirname(__DIR__).'/shared/header.php'; require_once dirname(__DIR__).'/shared/sidebar.php';
?>
<div class="content-wrapper with-sidebar"><div class="container-fluid"><div class="main-content">
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4"><div><h1 class="section-title mb-1"><i class="fas fa-user-shield"></i> Personnel Operations &amp; Deployments</h1><p class="text-muted mb-0">Maintain operation participation and deployment history for personnel posted to the Operations branch.</p></div></div>
<?php if($message):?><div class="alert alert-success"><i class="fas fa-check-circle"></i> <?=htmlspecialchars($message)?></div><?php endif;?>
<?php if($error):?><div class="alert alert-danger"><i class="fas fa-exclamation-triangle"></i> <?=htmlspecialchars($error)?></div><?php endif;?>
<div class="card shadow-sm mb-4"><div class="card-body"><div class="row g-3 align-items-end"><div class="col-md-8"><label class="form-label fw-semibold" for="personnelSelect">Personnel</label><select id="personnelSelect" class="form-select" onchange="location.href='personnel_records.php?svcNo='+encodeURIComponent(this.value)"><option value="">Select personnel</option><?php foreach($personnel as $p):?><option value="<?=htmlspecialchars($p['svcNo'])?>" <?=$selectedSvc===$p['svcNo']?'selected':''?>><?=htmlspecialchars($p['svcNo'].' — '.trim($p['lName'].' '.$p['fName'].' '.$p['mName']))?></option><?php endforeach;?></select></div><div class="col-md-4"><div class="small text-muted">Selected personnel</div><div class="fw-semibold"><?= $selected ? htmlspecialchars(trim($selected['fName'].' '.$selected['mName'].' '.$selected['lName'])) : 'None' ?></div><div class="small text-muted">Current Unit: <?=htmlspecialchars($selected['unitId'] ?? 'Not assigned')?></div></div></div></div></div>
<ul class="nav nav-tabs mb-3" role="tablist"><li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#operationsTab" type="button"><i class="fas fa-tasks"></i> Operations History</button></li><li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#deploymentsTab" type="button"><i class="fas fa-plane"></i> Deployment History</button></li></ul>
<div class="tab-content">
<div class="tab-pane fade show active" id="operationsTab"><div class="card"><div class="card-header d-flex justify-content-between"><strong>Operation Participation</strong><?php if($canWrite&&$selected):?><button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#operationModal"><i class="fas fa-plus"></i> Add Operation</button><?php endif;?></div><div class="table-responsive"><table class="table table-hover mb-0"><thead><tr><th>Operation</th><th>Type</th><th>Start</th><th>End</th><th>Authority</th><th>Remarks</th><?php if($canWrite):?><th></th><?php endif;?></tr></thead><tbody><?php foreach($ops as $r):?><tr><td><?=htmlspecialchars($r['opId'])?></td><td><?=htmlspecialchars($r['opType']??'')?></td><td><?=htmlspecialchars($r['opStart']??'')?></td><td><?=htmlspecialchars($r['opEnd']??'')?></td><td><?=htmlspecialchars($r['authID']??'')?></td><td><?=htmlspecialchars($r['remarks']??'')?></td><?php if($canWrite):?><td><button class="btn btn-outline-primary btn-sm" onclick='editOperation(<?=json_encode($r,JSON_HEX_APOS|JSON_HEX_QUOT)?>)'>Edit</button></td><?php endif;?></tr><?php endforeach;?><?php if(!$ops):?><tr><td colspan="7" class="text-center text-muted py-4">No operation records for this personnel.</td></tr><?php endif;?></tbody></table></div></div></div>
<div class="tab-pane fade" id="deploymentsTab"><div class="card"><div class="card-header d-flex justify-content-between"><strong>Deployment History</strong><?php if($canWrite&&$selected):?><button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#deploymentModal"><i class="fas fa-plus"></i> Add Deployment</button><?php endif;?></div><div class="table-responsive"><table class="table table-hover mb-0"><thead><tr><th>Deployment</th><th>Mission Type</th><th>Location</th><th>Start</th><th>End</th><th>Status</th><?php if($canWrite):?><th></th><?php endif;?></tr></thead><tbody><?php foreach($deps as $r):?><tr><td><?=htmlspecialchars($r['deployment_name']??'')?></td><td><?=htmlspecialchars($r['mission_type']??'')?></td><td><?=htmlspecialchars(trim(($r['location']??'').' '.($r['country']??'')))?></td><td><?=htmlspecialchars($r['startDate']??'')?></td><td><?=htmlspecialchars($r['endDate']??'')?></td><td><?=htmlspecialchars($r['deployment_status']??'')?></td><?php if($canWrite):?><td><button class="btn btn-outline-primary btn-sm" onclick='editDeployment(<?=json_encode($r,JSON_HEX_APOS|JSON_HEX_QUOT)?>)'>Edit</button></td><?php endif;?></tr><?php endforeach;?><?php if(!$deps):?><tr><td colspan="7" class="text-center text-muted py-4">No deployment records for this personnel.</td></tr><?php endif;?></tbody></table></div></div></div>
</div>
<?php if($canWrite&&$selected):?><div class="modal fade" id="operationModal" tabindex="-1"><div class="modal-dialog modal-lg"><div class="modal-content"><form method="post"><input type="hidden" name="csrf" value="<?=htmlspecialchars(csrf_token())?>"><input type="hidden" name="action" value="operation_save"><input type="hidden" name="svcNo" value="<?=htmlspecialchars($selectedSvc)?>"><input type="hidden" name="id" id="op_id"><div class="modal-header"><h5 class="modal-title">Operation Record</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body"><div class="row g-3"><div class="col-md-7"><label class="form-label">Operation *</label><select name="opId" id="op_opId" class="form-select" required><option value="">Select operation</option><?php foreach($operations as $o):?><option value="<?=htmlspecialchars($o['opId'])?>"><?=htmlspecialchars($o['opId'].' — '.$o['opType'])?></option><?php endforeach;?></select></div><div class="col-md-5"><label class="form-label">Authority Reference</label><input name="authID" id="op_authID" class="form-control"></div><div class="col-md-6"><label class="form-label">Start Date</label><input type="date" name="opStart" id="op_start" class="form-control"></div><div class="col-md-6"><label class="form-label">End Date</label><input type="date" name="opEnd" id="op_end" class="form-control"></div><div class="col-12"><label class="form-label">Remarks</label><textarea name="remarks" id="op_remarks" class="form-control" rows="2"></textarea></div></div></div><div class="modal-footer"><button class="btn btn-primary">Save Record</button></div></form></div></div></div>
<div class="modal fade" id="deploymentModal" tabindex="-1"><div class="modal-dialog modal-lg"><div class="modal-content"><form method="post"><input type="hidden" name="csrf" value="<?=htmlspecialchars(csrf_token())?>"><input type="hidden" name="action" value="deployment_save"><input type="hidden" name="svcNo" value="<?=htmlspecialchars($selectedSvc)?>"><input type="hidden" name="id" id="dep_id"><div class="modal-header"><h5 class="modal-title">Deployment Record</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body"><div class="row g-3"><div class="col-md-6"><label class="form-label">Deployment Name *</label><input name="deployment_name" id="dep_name" class="form-control" required></div><div class="col-md-6"><label class="form-label">Mission Type</label><input name="mission_type" id="dep_type" class="form-control"></div><div class="col-md-4"><label class="form-label">Location</label><input name="location" id="dep_location" class="form-control"></div><div class="col-md-4"><label class="form-label">Country</label><input name="country" id="dep_country" class="form-control"></div><div class="col-md-4"><label class="form-label">Status</label><input name="deployment_status" id="dep_status" class="form-control"></div><div class="col-md-6"><label class="form-label">Start Date</label><input type="date" name="startDate" id="dep_start" class="form-control"></div><div class="col-md-6"><label class="form-label">End Date</label><input type="date" name="endDate" id="dep_end" class="form-control"></div><div class="col-md-4"><label class="form-label">Role</label><input name="role_during_deployment" id="dep_role" class="form-control"></div><div class="col-md-4"><label class="form-label">Rank During Deployment</label><input name="rank_during_deployment" id="dep_rank" class="form-control"></div><div class="col-md-4"><label class="form-label">Commanding Officer</label><input name="commanding_officer" id="dep_co" class="form-control"></div><div class="col-md-4"><label class="form-label">Duration (Months)</label><input type="number" min="0" name="durationMonths" id="dep_duration" class="form-control"></div><div class="col-md-4"><label class="form-label">Allowance</label><input type="number" step="0.01" min="0" name="deployment_allowance" id="dep_allowance" class="form-control"></div><div class="col-12"><label class="form-label">Notes</label><textarea name="notes" id="dep_notes" class="form-control" rows="2"></textarea></div></div></div><div class="modal-footer"><button class="btn btn-primary">Save Record</button></div></form></div></div></div><?php endif;?>
</div></div></div>
<script>function editOperation(r){document.getElementById('op_id').value=r.id;document.getElementById('op_opId').value=r.opId||'';document.getElementById('op_authID').value=r.authID||'';document.getElementById('op_start').value=r.opStart||'';document.getElementById('op_end').value=r.opEnd||'';document.getElementById('op_remarks').value=r.remarks||'';new bootstrap.Modal(document.getElementById('operationModal')).show()}function editDeployment(r){for(const [id,key] of Object.entries({dep_id:'id',dep_name:'deployment_name',dep_type:'mission_type',dep_location:'location',dep_country:'country',dep_status:'deployment_status',dep_start:'startDate',dep_end:'endDate',dep_role:'role_during_deployment',dep_rank:'rank_during_deployment',dep_co:'commanding_officer',dep_duration:'durationMonths',dep_allowance:'deployment_allowance',dep_notes:'notes'})){document.getElementById(id).value=r[key]??''}new bootstrap.Modal(document.getElementById('deploymentModal')).show()}</script>
<?php require_once dirname(__DIR__).'/shared/footer.php'; ?>
