<?php
  require_once '../users/init.php';
  require_once $abs_us_root . $us_url_root . 'users/includes/template/prep.php';

  if (!securePage($_SERVER['PHP_SELF'])) { die(); }
  $hooks = getMyHooks();
  includeHook($hooks, 'pre');

  $userId = $user->data()->id;

  // Get user details from users table
  $userdetails = $db->query("SELECT * FROM users WHERE id = ?", [$userId])->first();

  // Get rank abbreviation and rank name from ranks table using user's rank
  $rankData = $db->query("SELECT rankAbb, rankName FROM ranks WHERE rankName = ?", [$userdetails->rank])->first();
  $rankAbb = $rankData ? $rankData->rankAbb : $userdetails->rank;
  $rankName = $rankData ? $rankData->rankName : $userdetails->rank;

  // Function to calculate age from date of birth
  function calculateAge($dob) {
    if (!$dob) return 'N/A';
    $dobDate = new DateTime($dob);
    $now = new DateTime();
    $age = $now->diff($dobDate)->y;
    return $age;
  }

  // Function to get initials from first name
  function getInitials($fname) {
    $parts = preg_split('/\s+/', trim($fname));
    $initials = '';
    foreach ($parts as $part) {
      if ($part !== '') {
        $initials .= strtoupper($part[0]) . '.';
      }
    }
    return $initials;
  }

  // Function to format heading based on category and rankAbb
  function formatHeading($userdetails, $rankAbb) {
    $category = strtolower($userdetails->category);
    if ($category === 'officer' || $category === 'officer cadet') {
      // Officer/Officer Cadet: RankAbb FirstName Surname
      return htmlspecialchars($rankAbb . ' ' . $userdetails->lname . ' ' . getInitials($userdetails->fname));
    } else {
      // NCOs and Civilians: RankAbb FirstNameInitials Surname
      return htmlspecialchars($rankAbb . ' ' . getInitials($userdetails->fname) . ' ' . $userdetails->lname);
    }
  }

  // Zambia Army logo path (adjust if needed)
  $logoPath = $us_url_root . "users/images/logo.png";
  // Profile picture path
  $profilePic = !empty($userdetails->picture) ? htmlspecialchars($userdetails->picture) : $us_url_root . "users/images/default_profile.png";
?>

<style>
  .dossier-a4 {
    background: #fff;
    width: 210mm;
    min-height: 297mm;
    margin: 0 auto;
    padding: 32px 32px 48px 32px;
    box-shadow: 0 0 10px rgba(0,0,0,0.12);
    border-radius: 8px;
    font-family: 'Segoe UI', Arial, sans-serif;
    position: relative;
  }
  .dossier-header {
    text-align: center;
    margin-bottom: 24px;
  }
  .dossier-logo {
    width: 90px;
    height: 90px;
    object-fit: contain;
    margin-bottom: 8px;
  }
  .dossier-title {
    font-size: 2rem;
    font-weight: bold;
    letter-spacing: 2px;
    color: #355E3B;
    margin-bottom: 0;
  }
  .dossier-serviceno-pic {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 14px;
    flex-wrap: wrap;
  }
  .dossier-serviceno {
    font-size: 1.1rem;
    font-weight: 600;
    color: #333;
    margin-top: 10px;
  }
  .dossier-profilepic {
    max-width: 140px;
    border-radius: 8px;
    border: 2px solid #e0e0e0;
    margin-left: 16px;
    margin-bottom: 8px;
    float: right;
  }
  .dossier-section-title {
    font-size: 1.1rem;
    font-weight: 600;
    color: #355E3B;
    border-bottom: 2px solid #e0e0e0;
    margin-top: 18px;
    margin-bottom: 6px;
    padding-bottom: 2px;
  }
  .dossier-details dt {
    font-weight: 500;
    color: #444;
    width: 170px;
    margin-bottom: 0;
    margin-top: 0;
    line-height: 1.2;
  }
  .dossier-details dd {
    margin-bottom: 0;
    margin-top: 0;
    color: #222;
    line-height: 1.2;
  }
  .dossier-details .row {
    margin-bottom: 0;
  }
  .dossier-section-title:first-of-type {
    margin-top: 0;
  }
  .no-print {
    margin-top: 12px;
  }
  @media print {
    body > *:not(.dossier-a4) {
      display: none !important;
    }
    .dossier-a4 {
      box-shadow: none;
      border-radius: 0;
      margin: 0 !important;
      padding: 0 0 0 0 !important;
      width: 100vw;
      min-height: 0;
    }
    .no-print { display: none !important; }
    header, footer, .main-header, .main-footer, .navbar, .site-footer, .site-header {
      display: none !important;
      height: 0 !important;
      visibility: hidden !important;
    }
    html, body {
      background: #fff !important;
      margin: 0 !important;
      padding: 0 !important;
    }
  }
</style>

<div class="dossier-a4">
  <div class="dossier-header">
    <img src="<?= $logoPath ?>" alt="Zambia Army Logo" class="dossier-logo">
    <div class="dossier-title">ZAMBIA ARMY</div>
    <div style="font-size:1.1rem; color:#888; letter-spacing:1px;">Personal File</div>
  </div>
  <div class="dossier-serviceno-pic">
    <div class="dossier-serviceno">
      Service Number: <?= htmlspecialchars($userdetails->svcNo); ?>
    </div>
    <img src="<?= $profilePic ?>" alt="Profile Picture" class="dossier-profilepic">
  </div>
  <h2 style="color:#355E3B; font-size:1.5rem; margin-bottom:0.5rem;">
    <?= formatHeading($userdetails, $rankAbb); ?>
  </h2>
  <div style="font-size:1.1rem; color:#555; margin-bottom: 8px;">
    <?= htmlspecialchars($userdetails->unit); ?> | <?= htmlspecialchars($userdetails->category); ?>
  </div>

  <div class="dossier-section-title">Service Details</div>
  <dl class="row dossier-details mb-0">
    <dt class="col-sm-4">Rank</dt>
    <dd class="col-sm-8"><?= htmlspecialchars($rankName); ?></dd>
    <dt class="col-sm-4">Category</dt>
    <dd class="col-sm-8"><?= htmlspecialchars($userdetails->category); ?></dd>
    <dt class="col-sm-4">Unit</dt>
    <dd class="col-sm-8"><?= htmlspecialchars($userdetails->unit); ?></dd>
    <dt class="col-sm-4">Intake</dt>
    <dd class="col-sm-8"><?= htmlspecialchars($userdetails->intake); ?></dd>
    <dt class="col-sm-4">Date of Enlistment</dt>
    <dd class="col-sm-8"><?= htmlspecialchars($userdetails->doe); ?></dd>
  </dl>

  <div class="dossier-section-title">Personal Bio Data</div>
  <dl class="row dossier-details mb-0">
    <dt class="col-sm-4">Full Name</dt>
    <dd class="col-sm-8"><?= htmlspecialchars($userdetails->fname . ' ' . $userdetails->lname); ?></dd>
    <dt class="col-sm-4">Gender</dt>
    <dd class="col-sm-8"><?= htmlspecialchars($userdetails->gender); ?></dd>
    <dt class="col-sm-4">Date of Birth / Age</dt>
    <dd class="col-sm-8">
      <?= htmlspecialchars($userdetails->dob ?? 'N/A'); ?>
      <?php if (!empty($userdetails->dob)) : ?>
        (Age: <?= calculateAge($userdetails->dob); ?>)
      <?php endif; ?>
    </dd>
    <dt class="col-sm-4">Blood Group</dt>
    <dd class="col-sm-8"><?= htmlspecialchars($userdetails->blood); ?></dd>
    <dt class="col-sm-4">Phone</dt>
    <dd class="col-sm-8"><?= htmlspecialchars($userdetails->phone); ?></dd>
    <dt class="col-sm-4">Email</dt>
    <dd class="col-sm-8"><?= htmlspecialchars($userdetails->email); ?></dd>
    <dt class="col-sm-4">Province</dt>
    <dd class="col-sm-8"><?= htmlspecialchars($userdetails->prov); ?></dd>
  </dl>

  <div class="dossier-section-title">Operations</div>
  <ul style="margin-left:1.2rem; margin-bottom: 4px;">
    <li>Operation Example 1</li>
    <li>Operation Example 2</li>
    <li>Operation Example 3</li>
  </ul>

  <div class="dossier-section-title">Courses</div>
  <ul style="margin-left:1.2rem; margin-bottom: 10px;">
    <li>Course Example 1</li>
    <li>Course Example 2</li>
    <li>Course Example 3</li>
  </ul>

  <div class="no-print mt-2">
    <a href="cv_view.php" target="_blank" class="btn btn-outline-primary">
      <i class="fa fa-eye"></i> View CV
    </a>
    <a href="cv_download.php" class="btn btn-outline-success ms-2">
      <i class="fa fa-download"></i> Download CV (PDF)
    </a>
    <a href="user_settings2.php" class="btn btn-outline-secondary ms-2">
      <i class="fa fa-arrow-left"></i> Back to Settings
    </a>
    <button onclick="window.print()" class="btn btn-outline-primary ms-2">
      <i class="fa fa-print"></i> Print File
    </button>
  </div>
</div>
<?php require_once $abs_us_root . $us_url_root . 'users/includes/html_footer.php'; ?>

