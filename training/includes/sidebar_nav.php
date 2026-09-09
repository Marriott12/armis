<?php
/** Shared, role-filtered Training sidebar navigation. */

require_once dirname(dirname(__DIR__)) . '/shared/module_navigation.php';
require_once dirname(dirname(__DIR__)) . '/shared/rbac.php';

$sidebarLinks = getModuleSidebarLinks('training');