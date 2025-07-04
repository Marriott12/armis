<div class="container">
  <div class="row justify-content-md-center alternate-background">
      <?php
      includeHook($hooks, 'body');
      ?>

      <h1 class="form-signin-heading mt-4 mb-3 alternate-background"> <?= lang("SIGNUP_TEXT", ""); ?></h1>

      <form class="form-signup border p-4 bg-light mb-5" action="" method="POST" id="payment-form">

        <div class="row mb-2">
          <label id="username-label" class="col-form-label col-12 col-md-4 text-md-right text-md-end"><?= lang("GEN_UNAME"); ?> *</label>

          <div class="col-12 col-md-8">
            <span id="usernameCheck" class="small"></span>
            <input type="text" class="form-control" id="username" name="username" placeholder="<?= lang("GEN_UNAME"); ?>" value="<?php if (!$form_valid && !empty($_POST)) {
                                                                                                                                    echo $username;
                                                                                                                                  } ?>" required autofocus autocomplete="username">
          </div>
        </div>


        <div class="row mb-2">
          <label for="unit" id="unit-label" class="col-form-label col-12 col-md-4 text-md-right text-md-end"><?= lang("GEN_MUNIT"); ?> *</label>
          <div class="col-12 col-md-8">
            <select type="text" class="form-control" id="unit" name="unit" placeholder="<?= lang("GEN_MUNIT"); ?>" value="<?php if (!$form_valid && !empty($_POST)) {
                                                                                                                              echo $category;
                                                                                                                            } ?>" required autocomplete="family-name">
              <option value="Command">Command</option>
              <option value="1 INF BDE">1 INF BDE</option>
              <option value="2 INF BDE">2 INF BDE</option>
              <option value="3 INF BDE">3 INF BDE</option>
                
            </select>
          </div>
        </div>


        <div class="row mb-2">
          <label for="category" id="snumber-label" class="col-form-label col-12 col-md-4 text-md-right text-md-end"><?= lang("GEN_CAT"); ?> *</label>
          <div class="col-12 col-md-8">
            <select type="text" class="form-control" id="category" name="category" placeholder="<?= lang("GEN_CAT"); ?>" value="<?php if (!$form_valid && !empty($_POST)) {
                                                                                                                              echo $category;
                                                                                                                            } ?>" required autocomplete="family-name">
              <option value="Officer">Officer</option>
              <option value="Non-Commissioned Officer">Non-Commissioned Officer</option>
              <option value="Civilian Employee">Civilian Employee</option>
                
            </select>
          </div>
        </div>

        <div class="row mb-2">
          <label for="svcNo" id="snumber-label" class="col-form-label col-12 col-md-4 text-md-right text-md-end"><?= lang("GEN_SNUMBER"); ?> *</label>
          <div class="col-12 col-md-8">
            <input type="text" class="form-control" id="svcNo" name="svcNo" placeholder="<?= lang("GEN_SNUMBER"); ?>" value="<?php if (!$form_valid && !empty($_POST)) {
                                                                                                                              echo $svcNo;
                                                                                                                            } ?>" required autocomplete="family-name">
          </div>
        </div>

        <div class="row mb-2">
          <label for="rank" id="rank-label" class="col-form-label col-12 col-md-4 text-md-right text-md-end"><?= lang("GEN_RANK"); ?> *</label>
          <div class="col-12 col-md-8">
            <input type="text" class="form-control" id="rank" name="rank" placeholder="<?= lang("GEN_RANK"); ?>" value="<?php if (!$form_valid && !empty($_POST)) {
                                                                                                                              echo $rank;
                                                                                                                            } ?>" required autocomplete="family-name">
          </div>
        </div>

        <div class="row mb-2">
          <label for="combat" id="combat-label" class="col-form-label col-12 col-md-4 text-md-right text-md-end"><?= lang("GEN_COMBAT"); ?> *</label>
          <div class="col-12 col-md-8">
            <input type="text" class="form-control" id="combat" name="combat" placeholder="<?= lang("GEN_COMBAT"); ?>" value="<?php if (!$form_valid && !empty($_POST)) {
                                                                                                                              echo $combat;
                                                                                                                            } ?>" required autocomplete="family-name">
          </div>
        </div>

        <div class="row mb-2">
          <label for="blood" id="snumber-label" class="col-form-label col-12 col-md-4 text-md-right text-md-end"><?= lang("GEN_BLOOD"); ?> *</label>
          <div class="col-12 col-md-8">
            <input type="text" class="form-control" id="blood" name="blood" placeholder="<?= lang("GEN_BLOOD"); ?>" value="<?php if (!$form_valid && !empty($_POST)) {
                                                                                                                              echo $blood;
                                                                                                                            } ?>" required autocomplete="family-name">
          </div>
        </div>

        <div class="row mb-2">
          <label for="fname" id="fname-label" class="col-form-label col-12 col-md-4 text-md-right text-md-end"><?= lang("GEN_FNAME"); ?> *</label>
          <div class="col-12 col-md-8">
            <input type="text" class="form-control" id="fname" name="fname" placeholder="<?= lang("GEN_FNAME"); ?>" value="<?php if (!$form_valid && !empty($_POST)) {
                                                                                                                              echo $fname;
                                                                                                                            } ?>" required autofocus autocomplete="given-name">
          </div>
        </div>

        <div class="row mb-2">
          <label for="gender" id="gender-label" class="col-form-label col-12 col-md-4 text-md-right text-md-end"><?= lang("GEN_GENDER"); ?> *</label>
          <div class="col-12 col-md-8">
            Male <input type="radio" class="flat" id="genderM" name="gender" Value="Male" checked="">
            Female <input type="radio" class="flat" id="genderF" name="gender" Value="Female" >
          </div>
        </div>


        <div class="row mb-2">
          <label for="lname" id="lname-label" class="col-form-label col-12 col-md-4 text-md-right text-md-end"><?= lang("GEN_LNAME"); ?> *</label>
          <div class="col-12 col-md-8">
            <input type="text" class="form-control" id="lname" name="lname" placeholder="<?= lang("GEN_LNAME"); ?>" value="<?php if (!$form_valid && !empty($_POST)) {
                                                                                                                              echo $lname;
                                                                                                                            } ?>" required autocomplete="family-name">
          </div>
        </div>

        <div class="row mb-2">
          <label for="boot" id="snumber-label" class="col-form-label col-12 col-md-4 text-md-right text-md-end"><?= lang("GEN_BOOT"); ?> *</label>
          <div class="col-12 col-md-8">
            <input type="text" class="form-control" id="boot" name="boot" placeholder="<?= lang("GEN_BOOT"); ?>" value="<?php if (!$form_valid && !empty($_POST)) {
                                                                                                                              echo $boot;
                                                                                                                            } ?>" required autocomplete="family-name">
          </div>
        </div>

        <div class="row mb-2">
          <label for="unit" id="unit-label" class="col-form-label col-12 col-md-4 text-md-right text-md-end"><?= lang("GEN_UNIT"); ?> *</label>
          <div class="col-12 col-md-8">
            <input type="text" class="form-control" id="unit" name="unit" placeholder="<?= lang("GEN_UNIT"); ?>" value="<?php if (!$form_valid && !empty($_POST)) {
                                                                                                                              echo $unit;
                                                                                                                            } ?>" required autocomplete="family-name">
          </div>
        </div>
        <div class="row mb-2">
          <label for="nrc" id="nrc-label" class="col-form-label col-12 col-md-4 text-md-right text-md-end"><?= lang("GEN_NRC"); ?> *</label>
          <div class="col-12 col-md-8">
            <input type="text" class="form-control" id="nrc" name="nrc" placeholder="<?= lang("GEN_NRC"); ?>" value="<?php if (!$form_valid && !empty($_POST)) {
                                                                                                                              echo $nrc;
                                                                                                                            } ?>" required autocomplete="family-name">
          </div>
        </div>

        <div class="row mb-2">
          <label for="email" id="email-label" class="col-form-label col-12 col-md-4 text-md-right text-md-end"><?= lang("GEN_EMAIL"); ?> *</label>
          <div class="col-12 col-md-8">
            <input class="form-control" type="text" name="email" id="email" placeholder="<?= lang("GEN_EMAIL"); ?>" value="<?php if (!$form_valid && !empty($_POST)) {
                                                                                                                              echo $email;
                                                                                                                            } ?>" required autocomplete="email">
          </div>
        </div>

        <div class="row mb-2">
          <?php if ($settings->no_passwords == 0) { ?>
            <div class="col-12 col-sm-6 col-lg-5 col-xl-4 password-verification">
            <?php 
              if(file_exists($abs_us_root . $us_url_root . 'usersc/includes/password_meter.php')) {
                include($abs_us_root . $us_url_root . 'usersc/includes/password_meter.php');
              } else {
                include($abs_us_root . $us_url_root . 'users/includes/password_meter.php');
              }
            ?>

            </div>
            <div class="col-12 col-sm-6 col-lg-7 col-xl-8">
              <div class="row mb-3">
                <label for="password" id="password-label" class="form-label col-12"><?= lang("GEN_PASS"); ?>
                  *
                  <a class="password_view_control ms-2" style="cursor: pointer; color:black; font-size:.8rem; text-decoration:none;">
                    <span class="fa fa-eye"></span>
                  </a>
                </label>
                <div class="col-12">
                  <input class="form-control" type="password" name="password" id="password" placeholder="<?= lang("GEN_PASS"); ?>" required autocomplete="new-password" aria-describedby="passwordhelp">

                </div>
              </div>
              <div class="row mb-3">
                <label for="confirm" id="confirm-label" class="form-label col-12"><?= lang("PW_CONF"); ?>
                  *
                  <a class="password_view_control ms-2" style="cursor: pointer; color:black; font-size:.8rem; text-decoration:none;">
                    <span class="fa fa-eye"></span>
                  </a>
                </label>
                <div class="col-12">
                  <input type="password" id="confirm" name="confirm" class="form-control" placeholder="<?= lang("PW_CONF"); ?>" required autocomplete="new-password">
                </div>
              </div>
            </div>
          <?php } //end no passwords 
          ?>
        </div>

        <?php
        includeHook($hooks, 'form');
        include($abs_us_root . $us_url_root . 'usersc/scripts/additional_join_form_fields.php');
        ?>

        <input type="hidden" value="<?= Token::generate(); ?>" name="csrf">

        <div class="row">
          <div class="col-12 col-md-8 offset-md-4">
            <button class="submit btn btn-primary " type="submit" id="next_button">
              <span class="fa fa-user-plus mr-2 me-2"></span> <?= lang("SIGNUP_TEXT"); ?>
            </button>
          </div>
        </div>

      </form>
      <?php
      if (file_exists($abs_us_root . $us_url_root . "usersc/views/_social_logins.php")) {
        require_once $abs_us_root . $us_url_root . "usersc/views/_social_logins.php";
      } else {
        require_once $abs_us_root . $us_url_root . "users/views/_social_logins.php";
      }
      ?>
    </main>
  </div>
</div>
