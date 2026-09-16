<?php
declare(strict_types=1);

/**
 * ARMIS scalability/runtime layer.
 *
 * The application can run fully on a single WAMP host. Redis, a CDN,
 * read replicas and a load balancer are optional production capabilities;
 * they are enabled only through environment variables and are health-checked
 * before being reported as active.
 */

require_once dirname(__DIR__) . '/shared/env.php';

if (!function_exists('armis_bool_env')) {
    function armis_bool_env(string $key, bool $default = false): bool
    {
        $value = env_get($key, $default ? '1' : '0');
        return in_array(strtolower(trim((string)$value)), ['1', 'true', 'yes', 'on'], true);
    }
}

if (!function_exists('armis_int_env')) {
    function armis_int_env(string $key, int $default): int
    {
        $value = env_get($key, (string)$default);
        return is_numeric($value) ? (int)$value : $default;
    }
}

class ScalabilityConfig
{
    public const DB_PERSISTENT = true;
    public const DB_CONNECTION_TIMEOUT = 10;

    public const REDIS_HOST = 'localhost';
    public const REDIS_PORT = 6379;
    public const REDIS_PASSWORD = null;
    public const CACHE_TTL = 3600;

    public const ENABLE_QUERY_CACHE = true;
    public const ENABLE_PAGE_CACHE = false;
    public const ENABLE_GZIP_COMPRESSION = true;
    public const MAX_EXECUTION_TIME = 60;
    public const MEMORY_LIMIT = '512M';

    public const CDN_ENABLED = false;
    public const CDN_BASE_URL = '';

    public const LOAD_BALANCER_ENABLED = false;
    public const READ_REPLICA_HOSTS = [];

    public const RATE_LIMIT_REQUESTS = 100;
    public const ENABLE_DDOS_PROTECTION = true;
    public const MAX_LOGIN_ATTEMPTS = 5;
    public const LOGIN_LOCKOUT_TIME = 900;

    public static function redisEnabled(): bool
    {
        return armis_bool_env('REDIS_ENABLED', false);
    }

    public static function redisHost(): string
    {
        return (string)env_get('REDIS_HOST', self::REDIS_HOST);
    }

    public static function redisPort(): int
    {
        return armis_int_env('REDIS_PORT', self::REDIS_PORT);
    }

    public static function redisPassword(): ?string
    {
        $value = env_get('REDIS_PASSWORD', '');
        return $value === '' ? null : $value;
    }

    public static function cdnEnabled(): bool
    {
        return armis_bool_env('CDN_ENABLED', self::CDN_ENABLED);
    }

    public static function cdnBaseUrl(): string
    {
        return rtrim((string)env_get('CDN_BASE_URL', self::CDN_BASE_URL), '/');
    }

    public static function loadBalancerEnabled(): bool
    {
        return armis_bool_env('LOAD_BALANCER_ENABLED', self::LOAD_BALANCER_ENABLED);
    }

    public static function readReplicaHosts(): array
    {
        $raw = trim((string)env_get('DB_READ_REPLICAS', ''));
        if ($raw === '') {
            return [];
        }
        $hosts = array_map('trim', preg_split('/[,;]+/', $raw) ?: []);
        return array_values(array_filter($hosts, static fn($host) => $host !== ''));
    }

    public static function getPerformanceConfig(): array
    {
        return [
            'enable_profiling' => armis_bool_env('PERFORMANCE_PROFILING', false),
            'log_slow_queries' => true,
            'slow_query_threshold' => (float)env_get('SLOW_QUERY_THRESHOLD', '2.0'),
            'enable_memory_monitoring' => true,
            'max_memory_usage' => '256M',
        ];
    }

    public static function setSecurityHeaders(): void
    {
        if (headers_sent()) {
            return;
        }
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: DENY');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        header('Permissions-Policy: geolocation=(), microphone=(), camera=()');
        // HSTS is meaningful only over HTTPS; never advertise it on localhost HTTP.
        if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
            header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
        }
    }

    public static function assetUrl(string $path): string
    {
        $path = '/' . ltrim($path, '/');
        if (self::cdnEnabled() && self::cdnBaseUrl() !== '') {
            return self::cdnBaseUrl() . $path;
        }
        return rtrim((string)env_get('ARMIS_BASE_URL', 'http://localhost/Armis2'), '/') . $path;
    }
}

// Runtime PHP settings are intentionally best-effort.
@ini_set('memory_limit', (string)env_get('ARMIS_MEMORY_LIMIT', ScalabilityConfig::MEMORY_LIMIT));
@ini_set('max_execution_time', (string)armis_int_env('ARMIS_MAX_EXECUTION_TIME', ScalabilityConfig::MAX_EXECUTION_TIME));

// Prefer Apache/mod_deflate in production. PHP gzip is a safe fallback when available.
if (ScalabilityConfig::ENABLE_GZIP_COMPRESSION && !headers_sent() && !ob_get_level() && function_exists('ob_gzhandler')) {
    @ob_start('ob_gzhandler');
}
ScalabilityConfig::setSecurityHeaders();

/** File-backed fallback cache. It keeps ARMIS functional when Redis is absent. */
class ARMISFileCache
{
    private static function directory(): string
    {
        $dir = dirname(__DIR__) . '/cache';
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        return $dir;
    }

    private static function filename(string $key): string
    {
        return self::directory() . '/' . hash('sha256', $key) . '.cache';
    }

    public static function get(string $key)
    {
        $file = self::filename($key);
        if (!is_file($file)) {
            return false;
        }
        $payload = @file_get_contents($file);
        if ($payload === false) {
            return false;
        }
        $data = json_decode($payload, true);
        if (!is_array($data) || !isset($data['expires'], $data['value'])) {
            return false;
        }
        if ((int)$data['expires'] < time()) {
            @unlink($file);
            return false;
        }
        return $data['value'];
    }

    public static function set(string $key, $value, int $ttl = ScalabilityConfig::CACHE_TTL): bool
    {
        $payload = json_encode(['expires' => time() + max(1, $ttl), 'value' => $value], JSON_UNESCAPED_SLASHES);
        return $payload !== false && @file_put_contents(self::filename($key), $payload, LOCK_EX) !== false;
    }

    public static function delete(string $key): bool
    {
        $file = self::filename($key);
        return !is_file($file) || @unlink($file);
    }
}

/** Redis cache with a safe file-cache fallback. */
class ARMISCache
{
    private static ?Redis $redis = null;
    private static bool $redisAttempted = false;

    private static function redis(): ?Redis
    {
        if (self::$redisAttempted) {
            return self::$redis;
        }
        self::$redisAttempted = true;

        if (!ScalabilityConfig::redisEnabled() || !class_exists('Redis')) {
            return null;
        }

        try {
            $redis = new Redis();
            $redis->connect(ScalabilityConfig::redisHost(), ScalabilityConfig::redisPort(), 1.5);
            if (ScalabilityConfig::redisPassword()) {
                $redis->auth(ScalabilityConfig::redisPassword());
            }
            $redis->ping();
            self::$redis = $redis;
        } catch (Throwable $e) {
            error_log('ARMIS Redis unavailable: ' . $e->getMessage());
            self::$redis = null;
        }
        return self::$redis;
    }

    public static function backend(): string
    {
        return self::redis() instanceof Redis ? 'redis' : 'file';
    }

    public static function get(string $key)
    {
        $redis = self::redis();
        if ($redis) {
            try {
                $value = $redis->get($key);
                if ($value === false) return false;
                $decoded = json_decode((string)$value, true);
                return json_last_error() === JSON_ERROR_NONE ? $decoded : $value;
            } catch (Throwable $e) {
                error_log('ARMIS Redis get failed: ' . $e->getMessage());
            }
        }
        return ARMISFileCache::get($key);
    }

    public static function set(string $key, $value, ?int $ttl = null): bool
    {
        $ttl = $ttl ?? ScalabilityConfig::CACHE_TTL;
        $redis = self::redis();
        if ($redis) {
            try {
                $payload = json_encode($value, JSON_UNESCAPED_SLASHES);
                if ($payload !== false) return (bool)$redis->setex($key, max(1, $ttl), $payload);
            } catch (Throwable $e) {
                error_log('ARMIS Redis set failed: ' . $e->getMessage());
            }
        }
        return ARMISFileCache::set($key, $value, $ttl);
    }

    public static function delete(string $key): bool
    {
        $redis = self::redis();
        if ($redis) {
            try { return (bool)$redis->del($key); } catch (Throwable $e) { error_log('ARMIS Redis delete failed: ' . $e->getMessage()); }
        }
        return ARMISFileCache::delete($key);
    }
}

class PerformanceMonitor
{
    private static float $startTime = 0.0;
    private static int $startMemory = 0;

    public static function start(): void
    {
        self::$startTime = microtime(true);
        self::$startMemory = memory_get_usage(true);
    }

    public static function end(string $operation = 'unknown'): array
    {
        $executionTime = microtime(true) - self::$startTime;
        $memoryUsed = memory_get_usage(true) - self::$startMemory;
        $threshold = ScalabilityConfig::getPerformanceConfig()['slow_query_threshold'];
        if ($executionTime > $threshold) {
            error_log(sprintf('ARMIS slow request: %s %.4fs %.2fMB', $operation, $executionTime, $memoryUsed / 1048576));
        }
        return [
            'execution_time' => $executionTime,
            'memory_used' => $memoryUsed,
            'peak_memory' => memory_get_peak_usage(true),
        ];
    }
}

class RateLimiter
{
    public static function checkLimit(string $identifier, ?int $limit = null, int $window = 60): bool
    {
        $limit = $limit ?: armis_int_env('RATE_LIMIT_REQUESTS', ScalabilityConfig::RATE_LIMIT_REQUESTS);
        $key = 'rate_limit:' . hash('sha256', $identifier) . ':' . floor(time() / $window);
        $current = (int)(ARMISCache::get($key) ?: 0);
        if ($current >= $limit) return false;
        ARMISCache::set($key, $current + 1, $window + 2);
        return true;
    }
}

PerformanceMonitor::start();
register_shutdown_function(static function (): void {
    try {
        $stats = PerformanceMonitor::end($_SERVER['REQUEST_URI'] ?? 'unknown');
        $logDir = dirname(__DIR__) . '/logs';
        if (!is_dir($logDir)) @mkdir($logDir, 0775, true);
        $entry = json_encode([
            'ts' => date('c'),
            'request' => $_SERVER['REQUEST_URI'] ?? 'unknown',
            'time_ms' => round($stats['execution_time'] * 1000, 2),
            'memory_mb' => round($stats['memory_used'] / 1048576, 2),
            'peak_memory_mb' => round($stats['peak_memory'] / 1048576, 2),
        ], JSON_UNESCAPED_SLASHES) . PHP_EOL;
        @file_put_contents($logDir . '/performance.log', $entry, FILE_APPEND | LOCK_EX);
    } catch (Throwable $e) {
        error_log('ARMIS performance logging failed: ' . $e->getMessage());
    }
});
