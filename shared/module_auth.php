<?php
/** Shared bootstrap for authenticated ARMIS module pages and APIs. */

require_once dirname(__DIR__) . '/config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/session_guard.php';
require_once __DIR__ . '/module_navigation.php';
require_once __DIR__ . '/rbac.php';

if (!function_exists('bootModule')) {
    function bootModule(array $options): void
    {
        enforceSessionTimeout();

        if (!isset($_SESSION['user_id'], $_SESSION['role'])) {
            $returnUrl = $_SERVER['REQUEST_URI'] ?? '/Armis2/';
            header('Location: /Armis2/login.php?return_url=' . urlencode($returnUrl));
            exit();
        }

        $module = $options['module'] ?? '';
        if ($module === '') {
            throw new InvalidArgumentException('A module is required.');
        }

        requireModuleAccess($module);

        $GLOBALS['currentPage'] = $options['page'] ?? 'dashboard';
        $GLOBALS['pageTitle'] = $options['pageTitle'] ?? 'ARMIS';
        $GLOBALS['moduleName'] = $options['moduleName'] ?? ucfirst($module);
        $GLOBALS['moduleIcon'] = $options['moduleIcon'] ?? 'home';
        $GLOBALS['moduleStylesheet'] = $options['stylesheet'] ?? ($module === 'command' ? '/Armis2/command/module.css' : ($module === 'operations' ? '/Armis2/operations/module.css' : ($module === 'training' ? '/Armis2/training/module.css' : ($module === 'finance' ? '/Armis2/finance/module.css' : null))));
        $GLOBALS['moduleScript'] = $options['script'] ?? ($module === 'command' ? '/Armis2/command/module.js' : ($module === 'training' ? '/Armis2/training/module.js' : null));
        $GLOBALS['sidebarLinks'] = $options['sidebarLinks'] ?? getModuleSidebarLinks($module);
    }
}

if (!function_exists('bootModuleApi')) {
    function bootModuleApi(string $module): void
    {
        enforceSessionTimeout();

        if (!isset($_SESSION['user_id'], $_SESSION['role'])) {
            http_response_code(401);
            echo json_encode(['error' => 'Authentication required']);
            exit();
        }

        if (!hasModuleAccess($module)) {
            http_response_code(403);
            echo json_encode(['error' => 'Access denied']);
            exit();
        }
    }
}