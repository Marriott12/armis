<?php
/**
 * NEW FILE (branch-scoping upgrade). Operations Branch roster: DG gets a
 * read-only snapshot, CC/SOI/SOII/SOIII get an editable roster - both
 * driven by shared/branch_roster.php, so this file just wires the branch
 * code in and delegates.
 */

$__branchCode = 'operations';
$__moduleName = 'Operations';
$__moduleIcon = 'map-marked-alt';

require dirname(__DIR__) . '/shared/branch_roster.php';
