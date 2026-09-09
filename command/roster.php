<?php
/**
 * NEW FILE (branch-scoping upgrade). Command Branch roster: DG gets a
 * read-only snapshot, CC/SOI/SOII/SOIII get an editable roster - both
 * driven by shared/branch_roster.php, so this file just wires the branch
 * code in and delegates.
 */

$__branchCode = 'command';
$__moduleName = 'Command';
$__moduleIcon = 'chess-king';

require dirname(__DIR__) . '/shared/branch_roster.php';
