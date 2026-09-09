<?php
/**
 * ARMIS Environment Loader
 *
 * Loads KEY=VALUE pairs from a .env file at the project root into
 * getenv()/$_ENV, so secrets (DB password, SMTP password, default
 * account password, etc.) live outside version control instead of
 * hardcoded in PHP source.
 *
 * .env is listed in .gitignore. Deploy a real .env on the server
 * (copy .env.example and fill in real values) — it is never
 * committed.
 */

if (!function_exists('armis_load_env')) {
    function armis_load_env(?string $path = null): void
    {
        static $loaded = false;
        if ($loaded) {
            return;
        }
        $loaded = true;

        $path = $path ?? dirname(__DIR__) . '/.env';
        if (!is_readable($path)) {
            // No .env present (e.g. values already set as real server
            // env vars). Not an error — env_get() below still falls
            // back to defaults.
            return;
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }
            if (!str_contains($line, '=')) {
                continue;
            }
            [$key, $value] = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);
            // Strip matching surrounding quotes
            if (strlen($value) >= 2 &&
                (($value[0] === '"' && $value[-1] === '"') ||
                 ($value[0] === "'" && $value[-1] === "'"))) {
                $value = substr($value, 1, -1);
            }
            if ($key === '') {
                continue;
            }
            // Don't overwrite real environment variables already set
            // by the server/container — those take precedence.
            if (getenv($key) === false) {
                putenv("$key=$value");
                $_ENV[$key] = $value;
            }
        }
    }
}

if (!function_exists('env_get')) {
    /**
     * Read a config value: real env var / .env value if present,
     * otherwise the given default.
     */
    function env_get(string $key, ?string $default = null): ?string
    {
        armis_load_env();
        $value = getenv($key);
        return $value !== false ? $value : $default;
    }
}
