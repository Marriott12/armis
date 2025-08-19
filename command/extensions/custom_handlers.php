<?php
/**
 * Example Custom Command Extension
 * Demonstrates how to extend the dynamic command system
 */

require_once __DIR__ . '/../includes/CommandHandlerRegistry.php';

/**
 * Custom Training Module Handler
 * Example of extending the system with new command types
 */
class TrainingModuleHandler implements CommandHandlerInterface {
    public function execute($command, $context = []) {
        $this->validate($command);
        
        // Check permissions
        $permissions = $this->getRequiredPermissions($command);
        if (!empty($permissions) && !$this->hasPermissions($permissions, $context)) {
            throw new Exception('Insufficient permissions for training module');
        }
        
        // Generate training-specific module HTML
        return $this->renderTrainingModule($command);
    }
    
    public function validate($command) {
        $required = ['id', 'title', 'course_type'];
        foreach ($required as $field) {
            if (empty($command[$field])) {
                throw new Exception("Missing required field for training module: {$field}");
            }
        }
        
        // Validate course type
        $validTypes = ['mandatory', 'optional', 'certification', 'refresher'];
        if (!in_array($command['course_type'], $validTypes)) {
            throw new Exception('Invalid course type for training module');
        }
        
        return true;
    }
    
    public function getRequiredPermissions($command) {
        $basePermissions = ['training.view'];
        
        // Add specific permissions based on course type
        if ($command['course_type'] === 'mandatory') {
            $basePermissions[] = 'training.mandatory';
        } elseif ($command['course_type'] === 'certification') {
            $basePermissions[] = 'training.certification';
        }
        
        return array_merge($basePermissions, $command['permissions'] ?? []);
    }
    
    private function hasPermissions($required, $context) {
        $userPermissions = $context['user_permissions'] ?? [];
        foreach ($required as $permission) {
            if (in_array($permission, $userPermissions)) {
                return true;
            }
        }
        return false;
    }
    
    private function renderTrainingModule($command) {
        $iconColor = $this->getCourseTypeIconColor($command['course_type']);
        $badge = $this->getCourseTypeBadge($command['course_type']);
        
        return [
            'type' => 'training_module',
            'html' => $this->generateTrainingModuleHtml($command, $iconColor, $badge),
            'data' => $command
        ];
    }
    
    private function getCourseTypeIconColor($courseType) {
        $colors = [
            'mandatory' => 'text-danger',
            'optional' => 'text-info',
            'certification' => 'text-warning',
            'refresher' => 'text-success'
        ];
        
        return $colors[$courseType] ?? 'text-primary';
    }
    
    private function getCourseTypeBadge($courseType) {
        $badges = [
            'mandatory' => '<span class="badge bg-danger">Mandatory</span>',
            'optional' => '<span class="badge bg-info">Optional</span>',
            'certification' => '<span class="badge bg-warning">Certification</span>',
            'refresher' => '<span class="badge bg-success">Refresher</span>'
        ];
        
        return $badges[$courseType] ?? '';
    }
    
    private function generateTrainingModuleHtml($module, $iconColor, $badge) {
        $buttonClass = $module['buttonClass'] ?? 'btn-primary';
        $buttonText = $module['buttonText'] ?? 'Start Course';
        $duration = $module['duration'] ?? 'Unknown';
        $difficulty = $module['difficulty'] ?? 'Beginner';
        $url = $module['url'] ?? '#';
        
        return '
            <div class="col-md-6 col-lg-4">
                <div class="card training-module-card" data-module-id="' . htmlspecialchars($module['id']) . '">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start mb-3">
                            <div class="module-icon">
                                <i class="fas fa-' . htmlspecialchars($module['icon'] ?? 'graduation-cap') . ' fa-2x ' . htmlspecialchars($iconColor) . '"></i>
                            </div>
                            ' . $badge . '
                        </div>
                        <h5 class="card-title">' . htmlspecialchars($module['title']) . '</h5>
                        <p class="card-text">' . htmlspecialchars($module['description']) . '</p>
                        <div class="training-details mb-3">
                            <small class="text-muted">
                                <i class="fas fa-clock"></i> Duration: ' . htmlspecialchars($duration) . ' | 
                                <i class="fas fa-signal"></i> Level: ' . htmlspecialchars($difficulty) . '
                            </small>
                        </div>
                        <a href="' . htmlspecialchars($url) . '" class="btn ' . htmlspecialchars($buttonClass) . ' w-100">
                            ' . htmlspecialchars($buttonText) . '
                        </a>
                    </div>
                </div>
            </div>
        ';
    }
}

/**
 * Custom Weather Widget Handler
 * Example of a specialized stat widget
 */
class WeatherWidgetHandler implements CommandHandlerInterface {
    public function execute($command, $context = []) {
        $this->validate($command);
        
        // Load weather data
        $weatherData = $this->loadWeatherData($command['location'] ?? 'Unknown');
        
        return [
            'type' => 'weather_widget',
            'html' => $this->generateWeatherHtml($command, $weatherData),
            'data' => array_merge($command, $weatherData)
        ];
    }
    
    public function validate($command) {
        if (empty($command['id'])) {
            throw new Exception('Weather widget must have an ID');
        }
        return true;
    }
    
    public function getRequiredPermissions($command) {
        return ['command.view', 'weather.view'];
    }
    
    private function loadWeatherData($location) {
        // This would normally call a weather API
        // For demonstration, return mock data
        $conditions = ['Sunny', 'Cloudy', 'Rainy', 'Clear'];
        $temperatures = range(15, 35);
        
        return [
            'location' => $location,
            'temperature' => $temperatures[array_rand($temperatures)] . '°C',
            'condition' => $conditions[array_rand($conditions)],
            'humidity' => rand(30, 80) . '%',
            'wind_speed' => rand(5, 25) . ' km/h',
            'last_updated' => date('H:i')
        ];
    }
    
    private function generateWeatherHtml($config, $data) {
        $icon = $this->getWeatherIcon($data['condition']);
        
        return '
            <div class="col-md-6 col-lg-3">
                <div class="card weather-widget bg-gradient-primary text-white" data-widget-id="' . htmlspecialchars($config['id']) . '">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6>' . htmlspecialchars($data['location']) . '</h6>
                                <h3>' . htmlspecialchars($data['temperature']) . '</h3>
                                <small>' . htmlspecialchars($data['condition']) . '</small>
                            </div>
                            <div class="weather-icon">
                                <i class="fas fa-' . htmlspecialchars($icon) . ' fa-2x"></i>
                            </div>
                        </div>
                        <hr class="my-2" style="border-color: rgba(255,255,255,0.3);">
                        <div class="weather-details">
                            <small>
                                <i class="fas fa-tint"></i> ' . htmlspecialchars($data['humidity']) . ' | 
                                <i class="fas fa-wind"></i> ' . htmlspecialchars($data['wind_speed']) . '
                            </small>
                        </div>
                    </div>
                </div>
            </div>
        ';
    }
    
    private function getWeatherIcon($condition) {
        $icons = [
            'Sunny' => 'sun',
            'Cloudy' => 'cloud',
            'Rainy' => 'cloud-rain',
            'Clear' => 'sun',
            'Storm' => 'bolt'
        ];
        
        return $icons[$condition] ?? 'cloud';
    }
}

/**
 * Event Logging Extension
 * Demonstrates event system usage
 */
class CommandEventLogger {
    public static function register() {
        $registry = CommandHandlerRegistry::getInstance();
        
        // Register event listeners
        $registry->addEventListener('handler_registered', [self::class, 'onHandlerRegistered']);
        $registry->addEventListener('command_executing', [self::class, 'onCommandExecuting']);
        $registry->addEventListener('command_executed', [self::class, 'onCommandExecuted']);
        $registry->addEventListener('command_failed', [self::class, 'onCommandFailed']);
    }
    
    public static function onHandlerRegistered($data) {
        error_log("Handler registered: {$data['type']} -> {$data['class']}");
    }
    
    public static function onCommandExecuting($data) {
        error_log("Executing command: {$data['type']} with ID: " . ($data['command']['id'] ?? 'unknown'));
    }
    
    public static function onCommandExecuted($data) {
        error_log("Command executed successfully: {$data['type']}");
    }
    
    public static function onCommandFailed($data) {
        error_log("Command failed: {$data['type']} - Error: " . $data['error']->getMessage());
    }
}

// Example usage and registration
function registerCustomHandlers() {
    $registry = CommandHandlerRegistry::getInstance();
    
    // Register custom handlers
    $registry->registerHandler('training_module', 'TrainingModuleHandler');
    $registry->registerHandler('weather_widget', 'WeatherWidgetHandler');
    
    // Register event logger
    CommandEventLogger::register();
    
    return $registry;
}

// Example configuration for custom modules
function getCustomModuleConfig() {
    return [
        'training_modules' => [
            [
                'id' => 'cyber_security_basic',
                'title' => 'Cyber Security Basics',
                'description' => 'Essential cybersecurity training for all personnel',
                'icon' => 'shield-alt',
                'course_type' => 'mandatory',
                'duration' => '2 hours',
                'difficulty' => 'Beginner',
                'url' => '/training/cyber-security-basic',
                'buttonText' => 'Start Training',
                'enabled' => true,
                'permissions' => ['training.mandatory']
            ],
            [
                'id' => 'leadership_skills',
                'title' => 'Leadership Skills',
                'description' => 'Advanced leadership techniques for NCOs and officers',
                'icon' => 'users-cog',
                'course_type' => 'optional',
                'duration' => '4 hours',
                'difficulty' => 'Advanced',
                'url' => '/training/leadership-skills',
                'buttonText' => 'Enroll Now',
                'enabled' => true,
                'permissions' => ['training.optional']
            ]
        ],
        'weather_widgets' => [
            [
                'id' => 'base_weather',
                'title' => 'Base Weather',
                'location' => 'Military Base Alpha',
                'enabled' => true
            ]
        ]
    ];
}

// Demonstration function
function demonstrateExtensions() {
    echo "=== Custom Command Extensions Demo ===\n\n";
    
    try {
        // Register custom handlers
        $registry = registerCustomHandlers();
        echo "✓ Custom handlers registered\n";
        
        // Get sample configuration
        $config = getCustomModuleConfig();
        echo "✓ Custom configuration loaded\n";
        
        // Test training module
        $trainingModule = $config['training_modules'][0];
        $result = $registry->executeCommand('training_module', $trainingModule, [
            'user_permissions' => ['training.view', 'training.mandatory']
        ]);
        echo "✓ Training module executed: " . $result['type'] . "\n";
        
        // Test weather widget
        $weatherWidget = $config['weather_widgets'][0];
        $result = $registry->executeCommand('weather_widget', $weatherWidget);
        echo "✓ Weather widget executed: " . $result['type'] . "\n";
        
        // Show registered handlers
        $handlers = $registry->getRegisteredHandlers();
        echo "\nRegistered handlers:\n";
        foreach ($handlers as $type => $class) {
            echo "  - {$type}: {$class}\n";
        }
        
    } catch (Exception $e) {
        echo "✗ Extension demo failed: " . $e->getMessage() . "\n";
    }
}

// Run demonstration if called directly
if (basename(__FILE__) === basename($_SERVER['SCRIPT_NAME'])) {
    demonstrateExtensions();
}
?>