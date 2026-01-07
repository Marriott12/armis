<?php
$s = file_get_contents(__DIR__ . '/../admin_branch/lib/ImportProcessor.php');
echo 'open:{' . substr_count($s,'{') . ' } close:' . substr_count($s,'}') . "\n";
$lines = explode("\n", $s);
foreach ($lines as $i => $line) {
    if (strpos($line, 'updateRankIdsFromTempRank') !== false) {
        echo 'L' . ($i+1) . ': ' . trim($line) . "\n";
    }
}

?>