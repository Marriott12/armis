<?php
/**
 * NEW FILE (branch-scoping upgrade). Training Branch roster: DG gets a
 * read-only snapshot, CC/SOI/SOII/SOIII get an editable roster - both
 * driven by shared/branch_roster.php, so this file just wires the branch
 * code in and delegates.
 */

$__branchCode = 'training';
$__moduleName = 'Training';
$__moduleIcon = 'graduation-cap';

require dirname(__DIR__) . '/shared/branch_roster.php';
