<?php
    require_once '../users/init.php';
    require_once $abs_us_root . $us_url_root . 'users/includes/template/prep.php';
    require_once __DIR__ . '/../../vendor/autoload.php'; // Path to mPDF autoload (adjust if needed)

    if (!securePage($_SERVER['PHP_SELF'])) { die(); }
    $userId = $user->data()->id;
    $userdetails = $user->data();

    function calculateAge($dob) 
    {
      if (!$dob) return 'N/A';
      $dobDate = new DateTime($dob);
      $now = new DateTime();
      return $now->diff($dobDate)->y;
  }

// Fetch Courses
$courses = $db->query("
    SELECT sc.cseStart, sc.cseEnd, c.cseID, i.instID, i.instLoc, sc.result
    FROM Staff_Course sc
    LEFT JOIN Course c ON sc.cseID = c.cseID
    LEFT JOIN Institution i ON sc.instID = i.instID
    WHERE sc.svcNo = ?
    ORDER BY sc.cseStart DESC
", [$userdetails->svcNo])->results();

// Fetch Operations
$operations = $db->query("
    SELECT so.opStart, so.opEnd, o.opID, o.opType, so.opLoc
    FROM Staff_Operation so
    LEFT JOIN Operation o ON so.opID = o.opID
    WHERE so.svcNo = ?
    ORDER BY so.opStart DESC
", [$userdetails->svcNo])->results();

// Fetch Medals
$medals = $db->query("
    SELECT sm.issueDate, m.medID, m.medDesc, sm.auth, sm.comment
    FROM Staff_Medal sm
    LEFT JOIN Medal m ON sm.medID = m.medID
    WHERE sm.svcNo = ?
    ORDER BY sm.issueDate DESC
", [$userdetails->svcNo])->results();

// Build HTML for PDF
$html = '
<style>
.section-title { border-bottom: 2px solid #333; margin-top: 2rem; margin-bottom: 1rem; }
.cv-table { border-collapse: collapse; width: 100%; }
.cv-table th, .cv-table td { border: 1px solid #ccc; padding: 6px 8px; }
.cv-table th { background: #f5f5f5; }
</style>
<h2 style="text-align:center;">Curriculum Vitae</h2>
<h4>' . htmlspecialchars($userdetails->rank . ' ' . $userdetails->fname . ' ' . $userdetails->lname) . '</h4>
<dl>
  <dt>Service Number</dt><dd>' . htmlspecialchars($userdetails->svcNo) . '</dd>
  <dt>Gender</dt><dd>' . htmlspecialchars($userdetails->gender) . '</dd>
  <dt>Date of Birth / Age</dt><dd>' . htmlspecialchars($userdetails->dob) . ' (Age: ' . calculateAge($userdetails->dob) . ')</dd>
  <dt>Unit</dt><dd>' . htmlspecialchars($userdetails->unit) . '</dd>
  <dt>Category</dt><dd>' . htmlspecialchars($userdetails->category) . '</dd>
  <dt>Email</dt><dd>' . htmlspecialchars($userdetails->email) . '</dd>
  <dt>Phone</dt><dd>' . htmlspecialchars($userdetails->tel) . '</dd>
  <dt>Province</dt><dd>' . htmlspecialchars($userdetails->province) . '</dd>
  <dt>Blood Group</dt><dd>' . htmlspecialchars($userdetails->bloodGp) . '</dd>
  <dt>Date of Enlistment</dt><dd>' . htmlspecialchars($userdetails->attestDate) . '</dd>
  <dt>Intake</dt><dd>' . htmlspecialchars($userdetails->intake) . '</dd>
</dl>';

// Courses Section
$html .= '<h5 class="section-title">Courses Attended</h5>';
if(count($courses)) {
    $html .= '<table class="cv-table"><thead><tr>
        <th>Course</th>
        <th>Institution</th>
        <th>Location</th>
        <th>Start Date</th>
        <th>End Date</th>
        <th>Result</th>
    </tr></thead><tbody>';
    foreach($courses as $c) {
        $html .= '<tr>
            <td>' . htmlspecialchars($c->cseID) . '</td>
            <td>' . htmlspecialchars($c->instID) . '</td>
            <td>' . htmlspecialchars($c->instLoc) . '</td>
            <td>' . htmlspecialchars($c->cseStart) . '</td>
            <td>' . htmlspecialchars($c->cseEnd) . '</td>
            <td>' . htmlspecialchars($c->result) . '</td>
        </tr>';
    }
    $html .= '</tbody></table>';
} else {
    $html .= '<p>No courses recorded.</p>';
}

// Operations Section
$html .= '<h5 class="section-title">Operations Participated</h5>';
if(count($operations)) {
    $html .= '<table class="cv-table"><thead><tr>
        <th>Operation</th>
        <th>Type</th>
        <th>Location</th>
        <th>Start Date</th>
        <th>End Date</th>
    </tr></thead><tbody>';
    foreach($operations as $op) {
        $html .= '<tr>
            <td>' . htmlspecialchars($op->opID) . '</td>
            <td>' . htmlspecialchars($op->opType) . '</td>
            <td>' . htmlspecialchars($op->opLoc) . '</td>
            <td>' . htmlspecialchars($op->opStart) . '</td>
            <td>' . htmlspecialchars($op->opEnd) . '</td>
        </tr>';
    }
    $html .= '</tbody></table>';
} else {
    $html .= '<p>No operations recorded.</p>';
}

// Medals Section
$html .= '<h5 class="section-title">Medals Awarded</h5>';
if(count($medals)) {
    $html .= '<table class="cv-table"><thead><tr>
        <th>Medal</th>
        <th>Description</th>
        <th>Date Awarded</th>
        <th>Authority</th>
        <th>Comment</th>
    </tr></thead><tbody>';
    foreach($medals as $m) {
        $html .= '<tr>
            <td>' . htmlspecialchars($m->medID) . '</td>
            <td>' . htmlspecialchars($m->medDesc) . '</td>
            <td>' . htmlspecialchars($m->issueDate) . '</td>
            <td>' . htmlspecialchars($m->auth) . '</td>
            <td>' . htmlspecialchars($m->comment) . '</td>
        </tr>';
    }
    $html .= '</tbody></table>';
} else {
    $html .= '<p>No medals recorded.</p>';
}

// Output PDF
$mpdf = new \Mpdf\Mpdf(['format' => 'A4']);
$mpdf->SetTitle('Curriculum Vitae');
$mpdf->WriteHTML($html);
$mpdf->Output('My_CV.pdf', 'D');
exit;