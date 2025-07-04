<?php
ini_set('allow_url_fopen', 1);
require_once '../users/init.php';

// Restrict access to Command role
if (!$user->isLoggedIn() || getUserRole($user) !== 'Command') {
    Redirect::to($us_url_root . 'users/account.php');
    die();
}

// You can add a $view variable if you want to support dynamic views
$view = Input::get('view');

// Define the modules to load for the Command dashboard
$modules = [
  "dashboard_prep",    // open class, overrides, basic checks
  "styling",           // custom styles for dashboard
  "sidebar",           // sidebar navigation if needed
  "content_open",      // open main content area
  "header",            // header and any notices
  "views",             // main content/views for Command (reports, stats, etc)
  "command_dashboard_reports", // specific reports for Command role
  "system_messages",   // success/fail messages
  "content_close",     // close main content area
  "system_messages_footer",
  "footer",            // footer and plugin footers
  "dashboard_js",      // ajax calls and dashboard JS
];

// Load each module, preferring usersc/modules overrides
foreach($modules as $m){
  if(file_exists($abs_us_root . $us_url_root . "usersc/command/".$m.".php")){
    require_once $abs_us_root . $us_url_root . "usersc/command/".$m.".php";
  }else{
    require_once $abs_us_root . $us_url_root . "users/modules/".$m.".php";
  }
}