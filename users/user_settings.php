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
        $initials .= strtoupper($part[0]) . ' ';
      }
    }
    return $initials;
  }

  // Function to format heading based on category and rankAbb
  function formatHeading($userdetails, $rankAbb) {
    $category = strtolower($userdetails->category);
    if ($category === 'officer' || $category === 'officer cadet') {
      // Officer/Officer Cadet: RankAbb FirstNameInitials Surname
      return htmlspecialchars($rankAbb . ' ' . getInitials($userdetails->fname) . ' ' . $userdetails->lname. ' ('.htmlspecialchars($userdetails->svcNo). ')');
    } else {
      // NCOs and Civilians: RankAbb Surname FirstNameInitials
      return htmlspecialchars($rankAbb . ' ' . $userdetails->lname. ' ' . getInitials($userdetails->fname). ' ('.htmlspecialchars($userdetails->svcNo). ')');
    }
  }

  // Zambia Army logo path (adjust if needed)
  $logoPath = $us_url_root . "users/images/logo.png";
  // Profile picture path
  $profilePic = !empty($userdetails->picture) ? htmlspecialchars($userdetails->picture) : $us_url_root . "users/images/default_profile.png";
?>
<style>
  /* Reduce white space between details lines */
  dl.row dt,
  dl.row dd {
    margin-bottom: 2px !important;
    margin-top: 2px !important;
    line-height: 1.2 !important;
    padding-top: 0 !important;
    padding-bottom: 0 !important;
  }
  dl.row {
    margin-bottom: 0 !important;
  }
</style>
<div class="container my-5">
  <div class="row">
    <!-- Sidebar: Profile Picture and Vertical Accordion Menu -->
    <aside class="col-md-3 mb-3 mb-md-0">
      <div class="border rounded bg-light p-3 shadow-sm text-center mb-4">
        <img src="<?= htmlspecialchars($userdetails->picture); ?>" class="img-thumbnail profile-replacer mb-3" alt="Profile picture" style="max-width: 180px;">

      </div>
      <!-- Vertical Accordion Menu -->
      <div class="list-group" id="ompfMenu">
        <button class="list-group-item list-group-item-action active" data-section="bio">Section I: Personal Bio Data</button>
        <button class="list-group-item list-group-item-action" data-section="service">Section II: Service Details</button>
        <button class="list-group-item list-group-item-action" data-section="ops">Section III: Operations</button>
        <button class="list-group-item list-group-item-action" data-section="courses">Section IV: Courses</button>
        <!-- Add more menu items as needed -->
      </div>
    </aside>
    <!-- Main Content: Section Information -->
    <main class="col-md-9">
      <h1 class="mb-4 text-uppercase border-bottom pb-2">
    <?= formatHeading($userdetails, $rankAbb); ?></h1>
      <!-- ...CV...-->
      <!--<div class="mb-3 d-flex gap-2">
        <a href="cv_view.php" target="_blank" class="btn btn-outline-primary">
          <i class="fa fa-eye"></i> View CV
        </a>
        <a href="cv_download.php" class="btn btn-outline-success">
          <i class="fa fa-download"></i> Download CV (PDF)
        </a>
      </div>-->
      <p class="text-muted">This section contains your official military personnel file, including personal bio data, service details, operations, courses, and more.</p>
  
      <!-- ...CV...-->
      <div id="ompfSections">
        <!-- Personal Bio Data -->
        <section id="section-bio">
          <h3>Personal Bio Data</h3>
          <dl class="row mb-0">
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
        </section>
        <!-- Service Details -->
        <section id="section-service" class="d-none">
          <h3>Service Details</h3>
          <dl class="row mb-0">
            <dt class="col-sm-4">Rank Effective From</dt>
            <dd class="col-sm-8"><?= htmlspecialchars($userdetails->subWef ?? ''); ?></dd>
            <dt class="col-sm-4">Category</dt>
            <dd class="col-sm-8"><?= htmlspecialchars($userdetails->category ?? ''); ?></dd>
            <dt class="col-sm-4">Unit</dt>
            <dd class="col-sm-8"><?= htmlspecialchars($userdetails->unit ?? ''); ?></dd>
            <dt class="col-sm-4">Intake</dt>
            <dd class="col-sm-8"><?= htmlspecialchars($userdetails->intake ?? ''); ?></dd>
            <dt class="col-sm-4">Date of Enlistment</dt>
            <dd class="col-sm-8"><?= htmlspecialchars($userdetails->doe ?? ''); ?></dd>
          </dl>
        </section>
        <!-- Operations -->
        <section id="section-ops" class="d-none">
          <h3>Operations</h3>
          <ul>
            <li>Operation Example 1</li>
            <li>Operation Example 2</li>
            <li>Operation Example 3</li>
          </ul>
        </section>
        <!-- Courses -->
        <section id="section-courses" class="d-none">
          <h3>Courses</h3>
          <ul>
            <li>Course Example 1</li>
            <li>Course Example 2</li>
            <li>Course Example 3</li>
          </ul>
        </section>
        <!-- Add more sections as needed -->
      </div>
    </main>
  </div>
</div>

<script>
  // JS to toggle sections based on menu click
  document.addEventListener('DOMContentLoaded', function() {
    const menuButtons = document.querySelectorAll('#ompfMenu button');
    const sections = {
      bio: document.getElementById('section-bio'),
      service: document.getElementById('section-service'),
      ops: document.getElementById('section-ops'),
      courses: document.getElementById('section-courses')
    };
    menuButtons.forEach(btn => {
      btn.addEventListener('click', function() {
        // Remove active from all
        menuButtons.forEach(b => b.classList.remove('active'));
        // Hide all sections
        Object.values(sections).forEach(sec => sec.classList.add('d-none'));
        // Activate clicked
        btn.classList.add('active');
        // Show selected section
        const section = sections[btn.getAttribute('data-section')];
        if(section) section.classList.remove('d-none');
      });
    });
  });
</script>
<!-- Bootstrap JS (required for accordion) -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<?php require_once $abs_us_root . $us_url_root . 'users/includes/html_footer.php'; ?>