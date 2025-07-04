<?php
require_once '../users/init.php';
require_once $abs_us_root.$us_url_root.'users/includes/template/prep.php';

$action = Input::get('action');
if($action == "thank_you_verify"){
    require $abs_us_root.$us_url_root.'users/views/_joinThankYou_verify.php';
}elseif($action == "thank_you_join"){
    require_once $abs_us_root.$us_url_root.'usersc/views/_joinThankYou.php';
}elseif($action == "thank_you"){
    require $abs_us_root.$us_url_root.'users/views/_joinThankYou.php';
}
require_once $abs_us_root.$us_url_root.'users/includes/html_footer.php'; ?>
