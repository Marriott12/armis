<?php
// Usage: php tools/quickfix_generate.php "C:\path\to\file.csv"
if ($argc < 2) {
    echo "Usage: php tools/quickfix_generate.php <csv-file-path>\n";
    exit(1);
}

$in = $argv[1];
if (!file_exists($in)) { echo "File not found: $in\n"; exit(2); }

if (session_status() === PHP_SESSION_NONE) session_start();

chdir(dirname(__DIR__));

$handle = fopen($in, 'r');
if ($handle === false) { echo "Failed to open file: $in\n"; exit(3); }

$header = fgetcsv($handle);
if (!$header) { fclose($handle); echo "CSV empty or invalid\n"; exit(4); }

$rows = [];
$seenSvc = [];
$seenNrc = [];
$skipped = 0;
while (($r = fgetcsv($handle)) !== false) {
    if (!empty($r[0]) && substr(trim($r[0]),0,1) === '#') continue;
    if (count($r) !== count($header)) continue;
    $row = array_combine($header, $r);

    // Normalize service number quickly: strip non-digits, pad to 6
    $svcRaw = isset($row['service number']) ? $row['service number'] : ($row['SVC NO'] ?? '');
    $digits = preg_replace('/\D/', '', $svcRaw);
    if ($digits !== '') $svcNorm = str_pad($digits, 6, '0', STR_PAD_LEFT); else $svcNorm = '';
    if ($svcNorm !== '') {
        if (in_array($svcNorm, $seenSvc)) { $skipped++; continue; }
        $seenSvc[] = $svcNorm;
        // write back into common keys we might see
        if (isset($row['service number'])) $row['service number'] = $svcNorm;
        if (isset($row['SVC NO'])) $row['SVC NO'] = $svcNorm;
    }

    // Normalize NRC duplicates
    $nrc = '';
    if (isset($row['NRC'])) $nrc = strtoupper(trim($row['NRC']));
    if ($nrc !== '') {
        if (in_array($nrc, $seenNrc)) { $skipped++; continue; }
        $seenNrc[] = $nrc;
        $row['NRC'] = $nrc;
    }

    // Auto-fill fornames from full_name if missing
    $fornamesKey = null;
    foreach (['fornames','FORENAMES','firstname','fname'] as $k) { if (isset($row[$k])) { $fornamesKey = $k; break; } }
    if ($fornamesKey && trim($row[$fornamesKey]) === '') {
        $full = $row['full_name'] ?? ($row['Full Name'] ?? '');
        if (!empty($full)) {
            $parts = preg_split('/\s+/', trim($full));
            if (!empty($parts[0])) $row[$fornamesKey] = $parts[0];
        }
    }

    // Normalize gender
    $genderKeys = ['gender','GENDER','Gender'];
    foreach ($genderKeys as $gk) {
        if (!isset($row[$gk])) continue;
        $g = trim($row[$gk]);
        if ($g === '') continue;
        $lu = strtoupper($g);
        if (in_array($lu, ['M','MALE'])) $row[$gk] = 'Male';
        else if (in_array($lu, ['F','FEMALE'])) $row[$gk] = 'Female';
        // leave other values (e.g. 'O') untouched for manual review
    }

    // Normalize DOB to Y-m-d if possible
    $dobKeys = ['dob','DOB','Date of Birth','date_of_birth'];
    foreach ($dobKeys as $dk) {
        if (!isset($row[$dk])) continue;
        $v = trim($row[$dk]);
        if ($v === '') continue;
        $v2 = str_replace('/', '-', $v);
        $ts = strtotime($v2);
        if ($ts && date('Y-m-d', $ts) !== '1970-01-01') {
            $age = (int)floor((time() - $ts) / (365.25*24*3600));
            if ($age >= 0) $row[$dk] = date('Y-m-d', $ts);
        }
    }

    $rows[] = $row;
}
fclose($handle);

$outDir = __DIR__ . '/../tmp'; if (!is_dir($outDir)) mkdir($outDir, 0777, true);
$bn = pathinfo($in, PATHINFO_FILENAME);
$out = $outDir . '/' . $bn . '_quickfix.csv';
$fh = fopen($out, 'w');
fputcsv($fh, $header);
foreach ($rows as $r) {
    $line = [];
    foreach ($header as $h) $line[] = $r[$h] ?? '';
    fputcsv($fh, $line);
}
fclose($fh);

echo "Quickfix CSV written to: $out\n";
echo "Rows written: " . count($rows) . " (skipped duplicates: $skipped)\n";
exit(0);
