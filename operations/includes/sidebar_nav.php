<?php
/** Shared, role-filtered Operations sidebar navigation. */

require_once dirname(dirname(__DIR__)) . '/shared/module_navigation.php';
require_once dirname(dirname(__DIR__)) . '/shared/rbac.php';

$sidebarLinks = getModuleSidebarLinks('operations');
