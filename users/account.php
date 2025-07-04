<?php
  require_once '../users/init.php';
  if (!securePage($_SERVER['PHP_SELF'])) 
  {
    die();
  }
  require_once $abs_us_root . $us_url_root . 'users/includes/template/prep.php';
  $hooks = getMyHooks();
  if ($hooks['bottom'] == []) 
  {
    $resize = [];
  } 
  else 
  {
    $resize = [];
  }
  includeHook($hooks, 'pre');

  if (!empty($_POST['uncloak'])) 
  {
    logger($user->data()->id, 'Cloaking', 'Attempting Uncloak');
    if (isset($_SESSION['cloak_to'])) 
    {
      $to = $_SESSION['cloak_to'];
      $from = $_SESSION['cloak_from'];
      unset($_SESSION['cloak_to']);
      $_SESSION[Config::get('session/session_name')] = $_SESSION['cloak_from'];
      unset($_SESSION['cloak_from']);
      logger($from, 'Cloaking', 'uncloaked from ' . $to);
      $cloakHook =  getMyHooks(['page' => 'cloakEnd']);
      includeHook($cloakHook, 'body');
      usSuccess("You are now you");
      Redirect::to($us_url_root . 'users/admin.php?view=users');
    } 
    else 
    {
      usError("Something went wrong. Please login again");
      Redirect::to($us_url_root . 'users/logout.php');
    }
  }

  $grav = fetchProfilePicture($user->data()->id);
  $raw = date_parse($user->data()->join_date);
  $signupdate = $raw['month'] . '/' . $raw['year'];
  if ($hooks['bottom'] == []) { //no plugin hooks present
    $resize = [
      'cardClass' => 'col-md-6 offset-md-3',
      'nameSize' => 'style="font-size:3em;"',
      'sinceSize' => 'style="font-size:2.25em;"',
    ];
  } else {
    $resize = [
      'cardClass' => 'col-md-3',
      'nameSize' => '',
      'sinceSize' => '',
    ];
  }
?>

<div class="container">
  <div class="row">
    <div class="col-12 <?= $resize['cardClass'] ?> mt-2 mb-4 p-3 d-flex justify-content-center">
      <div class="card p-4 alternate-background" style="width:100%">
        <div class="image text-center">
          <!--<img src="<?= $grav; ?>" width="60%" alt="profile thumbnail" class="profile-replacer">-->
          <p class="mt-3" <?= $resize['nameSize'] ?>><span id="fname" class="font-weight-bold fw-bold"><?= $user->data()->fname . ' ' . $user->data()->lname; ?> </span>
            <br />
            <span class="idd">@<?= $user->data()->username ?></span>
          </p>
          <p><a href="<?= $us_url_root ?>users/user_settings.php" class="btn btn-primary btn-block mt-3">Profile</a></p>
          
          <p><a href="<?= $us_url_root ?>users/command_reports.php" class="btn btn-primary btn-block mt-3">Command</a></p>
          <p><a href="<?= $us_url_root ?>users/admin_branch.php" class="btn btn-primary btn-block mt-3">Admin Branch User</a></p>
          <!--<p><a href="<?= $us_url_root ?>usersc/admin/menu_manager.php" class="btn btn-primary btn-block mt-3">Admin Dashboard</a></p>-->

          <?php if (isset($_SESSION['cloak_to'])) { ?>
            <p>
            <form class="" action="" method="post">
              <input type="hidden" name="uncloak" value="Uncloak!">
              <button class="btn btn-danger btn-block" role="submit">Uncloak</button>
            </form>
            </p>
          <?php  } //end cloak button 
          ?>
          <?php includeHook($hooks, 'body'); ?>
          <!--<div class="px-2 rounded mt-2" <?= $resize['sinceSize'] ?>><span class="join small"><?= lang('ACCT_SINCE'); ?>: <?= $signupdate; ?></span> </div>-->
        </div>

      </div>
    </div>
    <div class="col-12 col-md-9">
      <?php
      includeHook($hooks, 'bottom');
      ?>
    </div>
  </div>
</div>

<?php require_once $abs_us_root . $us_url_root . 'users/includes/html_footer.php'; ?>