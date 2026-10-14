<?php
/** ARMIS centralized secure session bootstrap. */
if (!function_exists('armisStartSecureSession')) {
    function armisStartSecureSession(): void
    {
        if (session_status() !== PHP_SESSION_NONE) return;

        $secure = !empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off';
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/Armis2/',
            'domain' => '',
            'secure' => $secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        session_start();
    }
}
