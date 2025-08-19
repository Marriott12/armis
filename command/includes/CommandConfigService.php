<?php
/**
 * Command Configuration Service
 * Handles dynamic loading and management of command configurations
 */

class CommandConfigService {
    private $configPath;
    private $cache = [];
    private $cacheTimeout = 300; // 5 minutes
    
    public function __construct($configPath = null) {
        $this->configPath = $configPath ?: __DIR__ . '/../config/commands.json';
    }
    
    /**
     * Load configuration from JSON file
     */
    public function loadConfig($useCache = true) {
        $cacheKey = 'command_config';
        
        // Check cache first
        if ($useCache && isset($this->cache[$cacheKey])) {
            $cached = $this->cache[$cacheKey];
            if (time() - $cached['timestamp'] < $this->cacheTimeout) {
                return $cached['data'];
            }
        }
        
        // Load from file
        if (!file_exists($this->configPath)) {
            throw new Exception("Command configuration file not found: {$this->configPath}");
        }
        
        $configContent = file_get_contents($this->configPath);
        if ($configContent === false) {
            throw new Exception("Failed to read command configuration file");
        }
        
        $config = json_decode($configContent, true);
        if ($config === null) {
            throw new Exception("Invalid JSON in command configuration file: " . json_last_error_msg());
        }
        
        // Validate configuration structure
        $this->validateConfig($config);
        
        // Cache the result
        if ($useCache) {
            $this->cache[$cacheKey] = [
                'data' => $config,
                'timestamp' => time()
            ];
        }
        
        return $config;
    }
    
    /**
     * Get navigation items
     */
    public function getNavigation($filterEnabled = true) {
        $config = $this->loadConfig();
        $navigation = $config['commands']['navigation'] ?? [];
        
        if ($filterEnabled) {
            $navigation = array_filter($navigation, function($item) {
                return $item['enabled'] ?? true;
            });
        }
        
        // Sort by order
        usort($navigation, function($a, $b) {
            return ($a['order'] ?? 999) - ($b['order'] ?? 999);
        });
        
        return $navigation;
    }
    
    /**
     * Get dashboard modules
     */
    public function getDashboardModules($filterEnabled = true, $userPermissions = []) {
        $config = $this->loadConfig();
        $modules = $config['commands']['dashboard_modules'] ?? [];
        
        if ($filterEnabled) {
            $modules = array_filter($modules, function($item) use ($userPermissions) {
                // Check if enabled
                if (!($item['enabled'] ?? true)) {
                    return false;
                }
                
                // Check permissions if specified
                if (!empty($item['permissions']) && !empty($userPermissions)) {
                    foreach ($item['permissions'] as $permission) {
                        if (in_array($permission, $userPermissions)) {
                            return true;
                        }
                    }
                    return false;
                }
                
                return true;
            });
        }
        
        // Sort by order
        usort($modules, function($a, $b) {
            return ($a['order'] ?? 999) - ($b['order'] ?? 999);
        });
        
        return $modules;
    }
    
    /**
     * Get overview statistics configuration
     */
    public function getOverviewStats($filterEnabled = true) {
        $config = $this->loadConfig();
        $stats = $config['commands']['overview_stats'] ?? [];
        
        if ($filterEnabled) {
            $stats = array_filter($stats, function($item) {
                return $item['enabled'] ?? true;
            });
        }
        
        // Sort by order
        usort($stats, function($a, $b) {
            return ($a['order'] ?? 999) - ($b['order'] ?? 999);
        });
        
        return $stats;
    }
    
    /**
     * Get settings
     */
    public function getSettings() {
        $config = $this->loadConfig();
        return $config['settings'] ?? [];
    }
    
    /**
     * Update configuration
     */
    public function updateConfig($newConfig) {
        $this->validateConfig($newConfig);
        
        $newConfig['lastUpdated'] = date('c');
        
        $json = json_encode($newConfig, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            throw new Exception("Failed to encode configuration to JSON");
        }
        
        if (file_put_contents($this->configPath, $json) === false) {
            throw new Exception("Failed to write configuration file");
        }
        
        // Clear cache
        $this->cache = [];
        
        return true;
    }
    
    /**
     * Add or update a dashboard module
     */
    public function updateDashboardModule($moduleId, $moduleData) {
        $config = $this->loadConfig();
        
        // Find existing module or add new one
        $found = false;
        foreach ($config['commands']['dashboard_modules'] as &$module) {
            if ($module['id'] === $moduleId) {
                $module = array_merge($module, $moduleData);
                $found = true;
                break;
            }
        }
        
        if (!$found) {
            $moduleData['id'] = $moduleId;
            $config['commands']['dashboard_modules'][] = $moduleData;
        }
        
        return $this->updateConfig($config);
    }
    
    /**
     * Remove a dashboard module
     */
    public function removeDashboardModule($moduleId) {
        $config = $this->loadConfig();
        
        $config['commands']['dashboard_modules'] = array_filter(
            $config['commands']['dashboard_modules'],
            function($module) use ($moduleId) {
                return $module['id'] !== $moduleId;
            }
        );
        
        return $this->updateConfig($config);
    }
    
    /**
     * Validate configuration structure
     */
    private function validateConfig($config) {
        $required = ['version', 'commands'];
        foreach ($required as $field) {
            if (!isset($config[$field])) {
                throw new Exception("Missing required field in configuration: {$field}");
            }
        }
        
        if (!isset($config['commands']['navigation']) || !is_array($config['commands']['navigation'])) {
            throw new Exception("Invalid navigation configuration");
        }
        
        if (!isset($config['commands']['dashboard_modules']) || !is_array($config['commands']['dashboard_modules'])) {
            throw new Exception("Invalid dashboard modules configuration");
        }
        
        // Validate each navigation item
        foreach ($config['commands']['navigation'] as $item) {
            if (empty($item['id']) || empty($item['title'])) {
                throw new Exception("Navigation items must have 'id' and 'title' fields");
            }
        }
        
        // Validate each dashboard module
        foreach ($config['commands']['dashboard_modules'] as $module) {
            if (empty($module['id']) || empty($module['title'])) {
                throw new Exception("Dashboard modules must have 'id' and 'title' fields");
            }
        }
        
        return true;
    }
    
    /**
     * Get configuration file path
     */
    public function getConfigPath() {
        return $this->configPath;
    }
    
    /**
     * Check if configuration file exists
     */
    public function configExists() {
        return file_exists($this->configPath);
    }
    
    /**
     * Create default configuration file
     */
    public function createDefaultConfig() {
        if (!$this->configExists()) {
            $defaultConfig = [
                'version' => '1.0.0',
                'lastUpdated' => date('c'),
                'commands' => [
                    'navigation' => [],
                    'dashboard_modules' => [],
                    'overview_stats' => []
                ],
                'settings' => [
                    'autoRefresh' => true,
                    'refreshInterval' => 30000,
                    'enableCache' => true,
                    'cacheTimeout' => 300,
                    'debugMode' => false
                ]
            ];
            
            return $this->updateConfig($defaultConfig);
        }
        
        return true;
    }
}