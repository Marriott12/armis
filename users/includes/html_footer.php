<?php

if(file_exists($abs_us_root.$us_url_root.'usersc/includes/footer.php')){
  require_once $abs_us_root.$us_url_root.'usersc/includes/footer.php';
}

//Plugin hooks
foreach($usplugins as $k=>$v){
  if($v == 1){
  if(file_exists($abs_us_root.$us_url_root."usersc/plugins/".$k."/footer.php")){
    include($abs_us_root.$us_url_root."usersc/plugins/".$k."/footer.php");
    }
  }
}

require_once $abs_us_root . $us_url_root . 'usersc/templates/' . $settings->template . '/footer.php';

?>
<script type="text/javascript">
setTimeout(function(){
$(".errSpan").html("<h4><br></h4>");
} , "<?=$settings->err_time*1000?>");


var popoverTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="popover"]'))
  var popoverList = popoverTriggerList.map(function (popoverTriggerEl) {
  return new bootstrap.Popover(popoverTriggerEl)
})

var tooltipTriggerList = document.querySelectorAll('[data-bs-toggle="tooltip"]')
var tooltipList = [...tooltipTriggerList].map(tooltipTriggerEl => new bootstrap.Tooltip(tooltipTriggerEl))

</script>

  </body>
</html>
