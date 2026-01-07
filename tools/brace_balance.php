<?php
$s = file_get_contents(__DIR__ . '/../admin_branch/lib/ImportProcessor.php');
$lines = explode("\n", $s);
$balance = 0;
foreach ($lines as $i => $line) {
    $open = substr_count($line, '{');
    $close = substr_count($line, '}');
    $balance += $open - $close;
    if ($balance < 0 || ($i>0 && ($i%50)==0)) {
        echo 'L'.($i+1)." balance=$balance line=".trim($line)."\n";
    }
}
echo "FINAL BALANCE: $balance\n";
?>