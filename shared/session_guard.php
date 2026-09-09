<?php
/**
 * ARMIS Session Timeout Guard
 * ---------------------------
 * Previously admin_branch/includes/auth.php had its own inline
 * inactivity-timeout check (20 minutes), completely independent of
 * config.php's SESSION_TIMEOUT constant (1 hour) — which itself
 * wasn't actually enforced anywhere. Two different timeouts existed
 * in the codebase and neither was the single source of truth.
 *
 * This file is that single source of truth now. It does the same
 * thing admin_branch's version did (check LAST_ACTIVITY, destroy the
 * session and redirect to login with a return URL and a message if
 * expired) but reads the timeout from one place, so every module
 * using it agrees on how long "inactive" means.
 *
 * Usage, right after session_start(), before any other session reads:
 *   require_once dirname(__DIR__) . '/shared/session_guard.php';
 *   enforceSessionTimeout();
 */

if (!function_exists('enforceSessionTimeout')) {
    function enforceSessionTimeout(?string $loginUrl = '/Armis2/login.php'): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        // SESSION_TIMEOUT is defined in config.php (falls back to 20
        // minutes if config.php somehow wasn't loaded — matching the
        // shorter, more conservative value that was already actually
        // being enforced in admin_branch, the module handling the most
        // sensitive personnel data, rather than the unenforced 1-hour
        // constant from config.php).
        $timeout = defined('SESSION_TIMEOUT') ? SESSION_TIMEOUT : 1200;

        if (isset($_SESSION['LAST_ACTIVITY']) && (time() - $_SESSION['LAST_ACTIVITY'] > $timeout)) {
            $currentUrl = $_SERVER['REQUEST_URI'] ?? '';
            $currentPath = parse_url($currentUrl, PHP_URL_PATH);
            $currentQuery = parse_url($currentUrl, PHP_URL_QUERY);
            $returnUrl = $currentPath . ($currentQuery ? '?' . $currentQuery : '');

            session_unset();
            session_destroy();

            session_start();
            $_SESSION['timeout_message'] = 'Your session has expired due to inactivity. Please log in again.';
            $_SESSION['timeout_return_url'] = $returnUrl;

            header('Location: ' . $loginUrl . '?reason=timeout&return_url=' . urlencode($returnUrl));
            exit();
        }

        $_SESSION['LAST_ACTIVITY'] = time();
    }
}
