<?php
ob_start();

?>
<!DOCTYPE html>
<html lang="<?=$html_lang ?>">
<head>
	<link rel="shortcut icon" href="/favicon.ico">
	<?php
	if(file_exists($abs_us_root.$us_url_root.'usersc/includes/head_tags.php')){
		require_once $abs_us_root.$us_url_root.'usersc/includes/head_tags.php';
	}

if(!file_exists($abs_us_root.$us_url_root."usersc/templates/".$settings->template."/assets/v2template.php")){
//the snippet below is meant to provide a basic btn-close class for bs 4 templates that don't have it

	?>
	<style>
	.close {
	  position: absolute;
	  right: 2rem;
	  top: 2rem;
	  width: 2rem;
	  height: 2rem;
	  opacity: 0.3;
	}
	.close:hover {
	  opacity: 1;
	}
	.close:before, .close:after {
	  position: absolute;
	  left: 15px;
	  content: ' ';
	  height: 1.25rem;
	  width: 2px;
	  /*background-color: #333333;*/
	  background-color: #355E3B;
	}
	.close:before {
	  transform: rotate(45deg);
	}
	.close:after {
	  transform: rotate(-45deg);
	}
	</style>
<?php } //end v2 template check
?>
	<script src="<?=$us_url_root?>users/js/messages.js"></script>
	<title><?= (($pageTitle != '') ? $pageTitle : ''); ?> <?=$settings->site_name?></title>
