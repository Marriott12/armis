<?php
// Fix and import remaining failed rows
// Usage: php tools/fix_and_import_failed.php

chdir(dirname(__DIR__));
if (!file_exists(__DIR__ . '/../tmp/failed_rows_import.csv')) {
    echo "No failed_rows_import.csv found in tmp/. Nothing to do.\n";
    exit(1);
}

$in = __DIR__ . '/../tmp/failed_rows_import.csv';
$out = __DIR__ . '/../tmp/failed_rows_import_fixed.csv';

$h = fopen($in, 'r');
$header = fgetcsv($h);
if (!$header) { fclose($h); echo "Invalid failed CSV\n"; exit(2); }

$rows = [];
while (($r = fgetcsv($h)) !== false) {
    if (count($r) !== count($header)) continue;
    $rows[] = array_combine($header, $r);
}
fclose($h);

// Backup existing failed CSV
copy($in, __DIR__ . '/../tmp/failed_rows_import_backup_' . date('Ymd_His') . '.csv');

$fixed = [];
foreach ($rows as $row) {
    // Normalize/Infer gender: if missing or invalid, infer from prefix
    $genderKeys = ['gender','GENDER','Gender'];
    $genderFoundKey = null;
    foreach ($genderKeys as $gk) if (isset($row[$gk])) { $genderFoundKey = $gk; break; }
    $genderVal = $genderFoundKey ? trim($row[$genderFoundKey]) : '';
    $isValidGender = in_array(strtolower($genderVal), ['male','female']);
    if (!$isValidGender) {
        // Determine prefix key (various possible headers)
        $prefixKeys = ['prefix','PREFIX','Prefix','RANK PREFIX','Rank Prefix'];
        $pref = '';
        foreach ($prefixKeys as $pk) {
            if (isset($row[$pk]) && trim($row[$pk]) !== '') { $pref = strtoupper(trim($row[$pk])); break; }
        }
        // If prefix contains allowed female markers, set Female, else Male
        $femalePrefixes = ['SW','QW','W'];
        $setGender = 'Male';
        // Normalize prefix to just letters (in case like 'SW-') and check
        $prefLetters = preg_replace('/[^A-Z]/', '', $pref);
        if (in_array($prefLetters, $femalePrefixes)) $setGender = 'Female';
        if ($genderFoundKey) {
            $row[$genderFoundKey] = $setGender;
        } else {
            // fallback: add a 'gender' column
            $row['gender'] = $setGender;
            $genderFoundKey = 'gender';
        }
    }

    // infer fornames from full_name
    $forkeys = ['fornames','FORENAMES','firstname','fname'];
    $foundFor = null;
    foreach ($forkeys as $fk) if (isset($row[$fk])) { $foundFor = $fk; break; }
    $fullName = $row['full_name'] ?? ($row['Full Name'] ?? null);
    if ($foundFor && trim($row[$foundFor]) === '' && !empty($fullName)) {
        $parts = preg_split('/\s+/', trim($fullName));
        if (!empty($parts[0])) $row[$foundFor] = $parts[0];
    }

    // infer fornames from 'surnames' when formatted like 'Surname, Forename'
    $surnKey = null; foreach (['surnames','SURNAME','lName','lastname'] as $sk) if (isset($row[$sk])) { $surnKey = $sk; break; }
    if ($foundFor && trim($row[$foundFor]) === '' && $surnKey && strpos($row[$surnKey], ',') !== false) {
        $parts = array_map('trim', explode(',', $row[$surnKey]));
        if (isset($parts[1]) && $parts[1] !== '') $row[$foundFor] = preg_split('/\s+/', $parts[1])[0];
    }

    $fixed[] = $row;
}

$fh = fopen($out, 'w');
fputcsv($fh, $header);
foreach ($fixed as $r) {
    $line = [];
    foreach ($header as $hcol) $line[] = $r[$hcol] ?? '';
    fputcsv($fh, $line);
}
fclose($fh);

echo "Fixed CSV written to: $out\n";

// Run the import for this fixed file using apply_import.php
echo "Running import of fixed file...\n";
$cmd = 'php "' . __DIR__ . '/apply_import.php" "' . realpath($out) . '" update';
passthru($cmd, $rc);
exit($rc);
