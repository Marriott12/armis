<?php
/**
 * NEW FILE (branch-scoping upgrade). Finance Branch roster: DG gets a
 * read-only snapshot, CC/SOI/SOII/SOIII get an editable roster - both
 * driven by shared/branch_roster.php, so this file just wires the branch
 * code in and delegates. Finance was previously a stub module (index.php
 * only) - this is its first real personnel-facing screen.
 */

$__branchCode = 'finance';
$__moduleName = 'Finance';
$__moduleIcon = 'calculator';

require dirname(__DIR__) . '/shared/branch_roster.php';
