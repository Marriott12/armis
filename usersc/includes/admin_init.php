<?php
    require_once '../users/init.php';
    require_once $abs_us_root . $us_url_root . 'users/includes/template/prep.php';

    if (!securePage($_SERVER['PHP_SELF'])) { die(); }
    if (!$user->isLoggedIn() || $user->data()->role !== 'Admin') {
        Redirect::to($us_url_root . 'users/login.php');
        die();
    }
?>