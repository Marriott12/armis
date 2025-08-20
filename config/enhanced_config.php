<?php
/**
 * ARMIS Enhanced Configuration Manager
 * Centralized configuration management with environment support
 */

class ARMISConfig {
    private static $config = [];
    private static $environment = 'production';
    private static $initialized = false;
    
    /**
     * Initialize configuration system
     */
    public static function init() {
        if (self::$initialized) {
            return;
        }
        
        // Determine environment
        self::$environment = self::determineEnvironment();
        
        // Load base configuration
        self::loadBaseConfig();
        
        // Load environment-specific configuration
        self::loadEnvironmentConfig();
        
        // Load database configuration
        self::loadDatabaseConfig();
        
        // Load security configuration
        self::loadSecurityConfig();
        
        // Load feature flags
        self::loadFeatureFlags();
        
        self::$initialized = true;
        
        error_log("ARMIS Configuration initialized for environment: " . self::$environment);
    }
    
    /**
     * Determine current environment
     */
    private static function determineEnvironment() {
        // Check environment variable
        if (getenv('ARMIS_ENV')) {
            return getenv('ARMIS_ENV');
        }
        
        // Check if defined in config
        if (defined('ARMIS_ENV')) {
            return ARMIS_ENV;
        }
        
        // Check server name patterns
        $serverName = $_SERVER['SERVER_NAME'] ?? 'localhost';
        
        if (strpos($serverName, 'localhost') !== false || strpos($serverName, '127.0.0.1') !== false) {
            return 'development';
        } elseif (strpos($serverName, 'staging') !== false || strpos($serverName, 'test') !== false) {
            return 'staging';
        }
        
        return 'production';
    }
    
    /**
     * Load base configuration
     */
    private static function loadBaseConfig() {
        self::$config = [
            'app' => [
                'name' => 'ARMIS - Army Resource Management Information System',
                'version' => '2.0.0',
                'environment' => self::$environment,
                'timezone' => 'UTC',
                'locale' => 'en_US',
                'debug' => self::$environment !== 'production',
                'maintenance_mode' => false
            ],
            'paths' => [
                'root' => __DIR__ . '/..',
                'uploads' => __DIR__ . '/../uploads',
                'logs' => __DIR__ . '/../shared/logs',
                'cache' => __DIR__ . '/../cache',
                'keys' => __DIR__ . '/../shared/keys',
                'backups' => __DIR__ . '/../backups'
            ],
            'api' => [
                'version' => '1.0.0',
                'rate_limit' => 100, // requests per minute
                'timeout' => 30, // seconds
                'max_payload_size' => '10MB'
            ]
        ];
    }
    
    /**
     * Load environment-specific configuration
     */
    private static function loadEnvironmentConfig() {
        $configs = [
            'development' => [
                'app' => [
                    'debug' => true,
                    'log_level' => 'debug'
                ],
                'database' => [
                    'host' => '127.0.0.1',
                    'name' => 'armis1',
                    'user' => 'root',
                    'password' => 'root123',
                    'charset' => 'utf8mb4'
                ],
                'redis' => [
                    'host' => 'localhost',
                    'port' => 6379,
                    'password' => null,
                    'database' => 0
                ],
                'security' => [
                    'strict_ssl' => false,
                    'csrf_protection' => true,
                    'session_secure' => false
                ]
            ],
            'staging' => [
                'app' => [
                    'debug' => true,
                    'log_level' => 'info'
                ],
                'database' => [
                    'host' => 'localhost',
                    'name' => 'armis_staging',
                    'user' => 'armis_staging',
                    'password' => getenv('DB_PASSWORD') ?: 'staging_password',
                    'charset' => 'utf8mb4'
                ],
                'redis' => [
                    'host' => 'localhost',
                    'port' => 6379,
                    'password' => getenv('REDIS_PASSWORD'),
                    'database' => 1
                ],
                'security' => [
                    'strict_ssl' => true,
                    'csrf_protection' => true,
                    'session_secure' => true
                ]
            ],
            'production' => [
                'app' => [
                    'debug' => false,
                    'log_level' => 'warning'
                ],
                'database' => [
                    'host' => getenv('DB_HOST') ?: 'localhost',
                    'name' => getenv('DB_NAME') ?: 'armis_production',
                    'user' => getenv('DB_USER') ?: 'armis_user',
                    'password' => getenv('DB_PASSWORD') ?: 'secure_password_here',
                    'charset' => 'utf8mb4'
                ],
                'redis' => [
                    'host' => getenv('REDIS_HOST') ?: 'localhost',
                    'port' => getenv('REDIS_PORT') ?: 6379,
                    'password' => getenv('REDIS_PASSWORD'),
                    'database' => 0
                ],
                'security' => [
                    'strict_ssl' => true,
                    'csrf_protection' => true,
                    'session_secure' => true
                ]
            ]
        ];
        
        $envConfig = $configs[self::$environment] ?? $configs['production'];
        self::$config = array_merge_recursive(self::$config, $envConfig);
    }
    
    /**
     * Load database configuration
     */
    private static function loadDatabaseConfig() {
        self::$config['database'] = array_merge(self::$config['database'] ?? [], [
            'options' => [
                'pool_size' => 20,
                'max_connections' => 100,
                'connection_timeout' => 30,
                'query_timeout' => 60,
                'persistent' => true,
                'charset' => 'utf8mb4',
                'collation' => 'utf8mb4_unicode_ci'
            ],
            'backup' => [
                'enabled' => true,
                'schedule' => '0 2 * * *', // Daily at 2 AM
                'retention_days' => 30,
                'encryption' => true,
                'compression' => true
            ]
        ]);
    }
    
    /**
     * Load security configuration
     */
    private static function loadSecurityConfig() {
        self::$config['security'] = array_merge(self::$config['security'] ?? [], [
            'jwt' => [
                'secret' => getenv('JWT_SECRET') ?: hash('sha256', 'ARMIS_JWT_' . self::$config['database']['name']),
                'expiry' => 3600, // 1 hour
                'refresh_expiry' => 86400, // 24 hours
                'algorithm' => 'HS256'
            ],
            'password' => [
                'min_length' => 8,
                'require_uppercase' => true,
                'require_lowercase' => true,
                'require_numbers' => true,
                'require_special' => true,
                'history_check' => 12,
                'expiry_days' => 90
            ],
            'session' => [
                'lifetime' => 3600, // 1 hour
                'regenerate_interval' => 300, // 5 minutes
                'secure' => self::$config['security']['session_secure'] ?? true,
                'httponly' => true,
                'samesite' => 'Strict'
            ],
            'login' => [
                'max_attempts' => 5,
                'lockout_duration' => 900, // 15 minutes
                'track_attempts' => true
            ],
            'mfa' => [
                'required_for_admin' => true,
                'issuer' => 'ARMIS',
                'algorithm' => 'SHA1',
                'digits' => 6,
                'period' => 30
            ],
            'encryption' => [
                'algorithm' => 'AES-256-CBC',
                'key_rotation_days' => 30
            ],
            'audit' => [
                'enabled' => true,
                'retention_days' => 90,
                'log_level' => 'info'
            ]
        ]);
    }
    
    /**
     * Load feature flags
     */
    private static function loadFeatureFlags() {
        self::$config['features'] = [
            'api_v2' => false,
            'advanced_analytics' => true,
            'real_time_notifications' => true,
            'mobile_app_support' => true,
            'enhanced_reporting' => true,
            'data_export' => true,
            'bulk_operations' => true,
            'advanced_search' => true,
            'custom_fields' => false,
            'integration_webhooks' => false,
            'ai_assistance' => false,
            'advanced_permissions' => true,
            'audit_trail' => true,
            'performance_monitoring' => true,
            'automated_backups' => true,
            'disaster_recovery' => true,
            'multi_tenant' => false,
            'sso_integration' => false,
            'ldap_integration' => false
        ];
    }
    
    /**
     * Get configuration value
     */
    public static function get($key, $default = null) {
        if (!self::$initialized) {
            self::init();
        }
        
        return self::getNestedValue(self::$config, $key, $default);
    }
    
    /**
     * Set configuration value
     */
    public static function set($key, $value) {
        if (!self::$initialized) {
            self::init();
        }
        
        self::setNestedValue(self::$config, $key, $value);
    }
    
    /**
     * Get all configuration
     */
    public static function all() {
        if (!self::$initialized) {
            self::init();
        }
        
        return self::$config;
    }
    
    /**
     * Get environment
     */
    public static function environment() {
        return self::$environment;
    }
    
    /**
     * Check if feature is enabled
     */
    public static function feature($feature) {
        return self::get("features.{$feature}", false);
    }
    
    /**
     * Check if debug mode is enabled
     */
    public static function debug() {
        return self::get('app.debug', false);
    }
    
    /**
     * Get nested value from array using dot notation
     */
    private static function getNestedValue($array, $key, $default = null) {
        if (isset($array[$key])) {
            return $array[$key];
        }
        
        foreach (explode('.', $key) as $segment) {
            if (!is_array($array) || !array_key_exists($segment, $array)) {
                return $default;
            }
            $array = $array[$segment];
        }
        
        return $array;
    }
    
    /**
     * Set nested value in array using dot notation
     */
    private static function setNestedValue(&$array, $key, $value) {
        $keys = explode('.', $key);
        $current = &$array;
        
        foreach ($keys as $k) {
            if (!isset($current[$k]) || !is_array($current[$k])) {
                $current[$k] = [];
            }
            $current = &$current[$k];
        }
        
        $current = $value;
    }
    
    /**
     * Load configuration from database
     */
    public static function loadFromDatabase() {
        try {
            require_once __DIR__ . '/../shared/database_connection.php';
            $pdo = getDbConnection();
            
            $stmt = $pdo->query("SELECT config_key, config_value, config_type FROM system_config");
            
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $value = $row['config_value'];
                
                // Convert based on type
                switch ($row['config_type']) {
                    case 'integer':
                        $value = (int)$value;
                        break;
                    case 'boolean':
                        $value = $value === 'true' || $value === '1';
                        break;
                    case 'json':
                        $value = json_decode($value, true);
                        break;
                }
                
                self::set($row['config_key'], $value);
            }
            
        } catch (Exception $e) {
            error_log("Failed to load configuration from database: " . $e->getMessage());
        }
    }
    
    /**
     * Save configuration to database
     */
    public static function saveToDatabase($key, $value, $type = 'string') {
        try {
            require_once __DIR__ . '/../shared/database_connection.php';
            $pdo = getDbConnection();
            
            // Convert value to string based on type
            switch ($type) {
                case 'boolean':
                    $value = $value ? 'true' : 'false';
                    break;
                case 'json':
                    $value = json_encode($value);
                    break;
                default:
                    $value = (string)$value;
            }
            
            $stmt = $pdo->prepare("
                INSERT INTO system_config (config_key, config_value, config_type, updated_at)
                VALUES (?, ?, ?, NOW())
                ON DUPLICATE KEY UPDATE 
                config_value = VALUES(config_value),
                config_type = VALUES(config_type),
                updated_at = NOW()
            ");
            
            $stmt->execute([$key, $value, $type]);
            
            // Update in-memory configuration
            self::set($key, $value);
            
        } catch (Exception $e) {
            error_log("Failed to save configuration to database: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Validate configuration
     */
    public static function validate() {
        $errors = [];
        
        // Validate required configurations
        $required = [
            'database.host',
            'database.name',
            'database.user',
            'security.jwt.secret'
        ];
        
        foreach ($required as $key) {
            if (empty(self::get($key))) {
                $errors[] = "Required configuration missing: {$key}";
            }
        }
        
        // Validate database connection
        try {
            require_once __DIR__ . '/../shared/database_connection.php';
            getDbConnection();
        } catch (Exception $e) {
            $errors[] = "Database connection failed: " . $e->getMessage();
        }
        
        // Validate paths
        $paths = [
            'uploads' => self::get('paths.uploads'),
            'logs' => self::get('paths.logs'),
            'cache' => self::get('paths.cache'),
            'keys' => self::get('paths.keys')
        ];
        
        foreach ($paths as $name => $path) {
            if (!is_dir($path)) {
                mkdir($path, 0755, true);
            }
            
            if (!is_writable($path)) {
                $errors[] = "Path not writable: {$name} ({$path})";
            }
        }
        
        return empty($errors) ? true : $errors;
    }
    
    /**
     * Export configuration (excluding sensitive data)
     */
    public static function export($includeSensitive = false) {
        $config = self::all();
        
        if (!$includeSensitive) {
            // Remove sensitive configuration
            unset($config['database']['password']);
            unset($config['redis']['password']);
            unset($config['security']['jwt']['secret']);
        }
        
        return $config;
    }
}

// Initialize configuration
ARMISConfig::init();

// Define global constants for backward compatibility
define('ARMIS_VERSION', ARMISConfig::get('app.version'));
define('ARMIS_ENV', ARMISConfig::get('app.environment'));
define('ARMIS_DEBUG', ARMISConfig::get('app.debug'));

// Database constants
define('DB_HOST', ARMISConfig::get('database.host'));
define('DB_NAME', ARMISConfig::get('database.name'));
define('DB_USER', ARMISConfig::get('database.user'));
define('DB_PASS', ARMISConfig::get('database.password'));
define('DB_CHARSET', ARMISConfig::get('database.charset'));

// Load configuration from database
ARMISConfig::loadFromDatabase();
?>