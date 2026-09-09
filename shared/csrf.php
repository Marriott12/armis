<?php
/**
 * ARMIS CSRF protection — single shared implementation.
 *
 * Previously this logic was copy-pasted independently in ~10 files
 * with two different field-name conventions ($_POST['csrf'] vs
 * $_POST['csrf_token']) and a non-timing-safe `!==` comparison.
 * Everything now goes through this file instead.
 *
 * Usage in a page that renders a <form>:
 *   require_once dirname(__DIR__) . '/shared/csrf.php';
 *   ... inside the <form> ...
 *   <?= csrf_field() ?>
 *
 * Usage in the handler that processes the POST:
 *   require_once dirname(__DIR__) . '/shared/csrf.php';
 *   require_csrf(); // exits with 403 if the token is missing/invalid
 *
 * Usage from JS (fetch/AJAX) — send the token as a header instead of
 * a form field:
 *   fetch(url, { headers: { 'X-CSRF-Token': token }, ... })
 * require_csrf() checks the header too, so either works.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!function_exists('csrf_token')) {
    /**
     * Get (and lazily create) the CSRF token for this session.
     */
    function csrf_token(): string
    {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }
}

if (!function_exists('csrf_field')) {
    /**
     * Hidden <input> to embed inside a <form>.
     */
    function csrf_field(): string
    {
        return '<input type="hidden" name="csrf_token" value="' .
            htmlspecialchars(csrf_token(), ENT_QUOTES) . '">';
    }
}

if (!function_exists('csrf_valid')) {
    /**
     * Check a submitted token (POST field 'csrf_token', legacy POST
     * field 'csrf', or X-CSRF-Token header) against the session's
     * token, using a timing-safe comparison. Does not exit — use
     * require_csrf() in handlers that should stop the request.
     */
    function csrf_valid(): bool
    {
        $provided = $_POST['csrf_token']
            ?? $_POST['csrf']
            ?? $_SERVER['HTTP_X_CSRF_TOKEN']
            ?? '';

        if (empty($_SESSION['csrf_token']) || $provided === '') {
            return false;
        }
        return hash_equals($_SESSION['csrf_token'], $provided);
    }
}

if (!function_exists('require_csrf')) {
    /**
     * Call at the top of any POST handler. Exits with 403 (JSON if
     * the request looks like AJAX/fetch, otherwise plain text) if the
     * token is missing or invalid.
     */
    function require_csrf(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return;
        }
        if (csrf_valid()) {
            return;
        }

        http_response_code(403);
        $isAjax = (
            ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest'
            || str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json')
            || str_contains($_SERVER['CONTENT_TYPE'] ?? '', 'application/json')
        );
        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Invalid or expired session (CSRF check failed). Please refresh the page and try again.']);
        } else {
            echo 'Invalid or expired session. Please go back, refresh the page, and try again.';
        }
        exit;
    }
}
