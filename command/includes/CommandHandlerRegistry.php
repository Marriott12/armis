<?php
/**
 * Command Handler Registry
 * Provides extensible command type handling and event system
 */

class CommandHandlerRegistry {
    private static $instance = null;
    private $handlers = [];
    private $events = [];
    private $middleware = [];
    
    private function __construct() {
        $this->registerDefaultHandlers();
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Register a command handler
     */
    public function registerHandler($type, $handlerClass) {
        if (!class_exists($handlerClass)) {
            throw new Exception("Handler class does not exist: {$handlerClass}");
        }
        
        $this->handlers[$type] = $handlerClass;
        $this->fireEvent('handler_registered', ['type' => $type, 'class' => $handlerClass]);
        
        return $this;
    }
    
    /**
     * Get handler for command type
     */
    public function getHandler($type) {
        if (!isset($this->handlers[$type])) {
            throw new Exception("No handler registered for command type: {$type}");
        }
        
        $handlerClass = $this->handlers[$type];
        return new $handlerClass();
    }
    
    /**
     * Check if handler exists for type
     */
    public function hasHandler($type) {
        return isset($this->handlers[$type]);
    }
    
    /**
     * Get all registered handlers
     */
    public function getRegisteredHandlers() {
        return $this->handlers;
    }
    
    /**
     * Execute a command
     */
    public function executeCommand($type, $command, $context = []) {
        // Apply middleware
        foreach ($this->middleware as $middleware) {
            $command = $middleware->process($command, $context);
        }
        
        $this->fireEvent('command_executing', ['type' => $type, 'command' => $command, 'context' => $context]);
        
        try {
            $handler = $this->getHandler($type);
            $result = $handler->execute($command, $context);
            
            $this->fireEvent('command_executed', ['type' => $type, 'command' => $command, 'result' => $result]);
            
            return $result;
        } catch (Exception $e) {
            $this->fireEvent('command_failed', ['type' => $type, 'command' => $command, 'error' => $e]);
            throw $e;
        }
    }
    
    /**
     * Register event listener
     */
    public function addEventListener($event, $callback) {
        if (!isset($this->events[$event])) {
            $this->events[$event] = [];
        }
        
        $this->events[$event][] = $callback;
        return $this;
    }
    
    /**
     * Fire event
     */
    public function fireEvent($event, $data = []) {
        if (isset($this->events[$event])) {
            foreach ($this->events[$event] as $callback) {
                if (is_callable($callback)) {
                    call_user_func($callback, $data);
                }
            }
        }
    }
    
    /**
     * Add middleware
     */
    public function addMiddleware($middleware) {
        $this->middleware[] = $middleware;
        return $this;
    }
    
    /**
     * Register default command handlers
     */
    private function registerDefaultHandlers() {
        $this->registerHandler('dashboard_module', 'DashboardModuleHandler');
        $this->registerHandler('navigation_item', 'NavigationItemHandler');
        $this->registerHandler('stat_widget', 'StatWidgetHandler');
        $this->registerHandler('api_endpoint', 'ApiEndpointHandler');
    }
}

/**
 * Base Command Handler Interface
 */
interface CommandHandlerInterface {
    public function execute($command, $context = []);
    public function validate($command);
    public function getRequiredPermissions($command);
}

/**
 * Dashboard Module Handler
 */
class DashboardModuleHandler implements CommandHandlerInterface {
    public function execute($command, $context = []) {
        // Validate command
        $this->validate($command);
        
        // Check permissions
        $permissions = $this->getRequiredPermissions($command);
        if (!empty($permissions) && !$this->hasPermissions($permissions, $context)) {
            throw new Exception('Insufficient permissions for dashboard module');
        }
        
        // Generate module HTML
        return $this->renderModule($command);
    }
    
    public function validate($command) {
        $required = ['id', 'title', 'description'];
        foreach ($required as $field) {
            if (empty($command[$field])) {
                throw new Exception("Missing required field for dashboard module: {$field}");
            }
        }
        
        // URL is optional but if provided should be valid
        if (isset($command['url']) && empty($command['url'])) {
            throw new Exception("URL cannot be empty if provided");
        }
        
        return true;
    }
    
    public function getRequiredPermissions($command) {
        return $command['permissions'] ?? [];
    }
    
    private function hasPermissions($required, $context) {
        $userPermissions = $context['user_permissions'] ?? [];
        
        // If no permissions required, allow access
        if (empty($required)) {
            return true;
        }
        
        // Check if user has any of the required permissions
        foreach ($required as $permission) {
            if (in_array($permission, $userPermissions)) {
                return true;
            }
        }
        
        // For dashboard modules, if no specific permissions are set in context,
        // allow basic access (this maintains backward compatibility)
        if (empty($userPermissions)) {
            return true;
        }
        
        return false;
    }
    
    private function renderModule($command) {
        return [
            'type' => 'dashboard_module',
            'html' => $this->generateModuleHtml($command),
            'data' => $command
        ];
    }
    
    private function generateModuleHtml($module) {
        $iconColor = $module['iconColor'] ?? 'text-primary';
        $buttonClass = $module['buttonClass'] ?? 'btn-primary';
        $buttonText = $module['buttonText'] ?? 'Open';
        $url = $module['url'] ?? '#';
        
        return '
            <div class="col-md-6 col-lg-3">
                <div class="card module-card" data-module-id="' . htmlspecialchars($module['id']) . '">
                    <div class="card-body text-center">
                        <div class="mb-3">
                            <i class="fas fa-' . htmlspecialchars($module['icon']) . ' fa-2x ' . htmlspecialchars($iconColor) . '"></i>
                        </div>
                        <h5 class="card-title">' . htmlspecialchars($module['title']) . '</h5>
                        <p class="card-text">' . htmlspecialchars($module['description']) . '</p>
                        <a href="' . htmlspecialchars($url) . '" class="btn ' . htmlspecialchars($buttonClass) . '">
                            ' . htmlspecialchars($buttonText) . '
                        </a>
                    </div>
                </div>
            </div>
        ';
    }
}

/**
 * Navigation Item Handler
 */
class NavigationItemHandler implements CommandHandlerInterface {
    public function execute($command, $context = []) {
        $this->validate($command);
        
        return [
            'type' => 'navigation_item',
            'data' => $command
        ];
    }
    
    public function validate($command) {
        $required = ['id', 'title', 'url'];
        foreach ($required as $field) {
            if (empty($command[$field])) {
                throw new Exception("Missing required field for navigation item: {$field}");
            }
        }
        return true;
    }
    
    public function getRequiredPermissions($command) {
        return $command['permissions'] ?? [];
    }
}

/**
 * Stat Widget Handler
 */
class StatWidgetHandler implements CommandHandlerInterface {
    public function execute($command, $context = []) {
        $this->validate($command);
        
        // Load data if needed
        $data = $this->loadStatData($command, $context);
        
        return [
            'type' => 'stat_widget',
            'html' => $this->generateStatHtml($command, $data),
            'data' => array_merge($command, $data)
        ];
    }
    
    public function validate($command) {
        $required = ['id', 'title', 'icon'];
        foreach ($required as $field) {
            if (empty($command[$field])) {
                throw new Exception("Missing required field for stat widget: {$field}");
            }
        }
        return true;
    }
    
    public function getRequiredPermissions($command) {
        return $command['permissions'] ?? [];
    }
    
    private function loadStatData($command, $context) {
        if ($command['dataSource'] === 'api' && !empty($command['endpoint'])) {
            // This would normally make an API call
            // For now, return sample data
            return [
                'value' => $command['fallbackValue'] ?? 'N/A',
                'change' => '+5%',
                'trend' => 'up'
            ];
        }
        
        return ['value' => $command['fallbackValue'] ?? 'N/A'];
    }
    
    private function generateStatHtml($config, $data) {
        $color = $config['color'] ?? 'bg-primary';
        $change = !empty($data['change']) ? '<small class="text-light">' . htmlspecialchars($data['change']) . '</small>' : '';
        
        return '
            <div class="col-md-6 col-lg-3">
                <div class="card ' . htmlspecialchars($color) . ' text-white" data-stat-id="' . htmlspecialchars($config['id']) . '">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h5>' . htmlspecialchars($config['title']) . '</h5>
                                <h2>' . htmlspecialchars($data['value']) . '</h2>
                                ' . $change . '
                            </div>
                            <i class="fas fa-' . htmlspecialchars($config['icon']) . ' fa-2x"></i>
                        </div>
                    </div>
                </div>
            </div>
        ';
    }
}

/**
 * API Endpoint Handler
 */
class ApiEndpointHandler implements CommandHandlerInterface {
    public function execute($command, $context = []) {
        $this->validate($command);
        
        // This would handle API endpoint registration
        return [
            'type' => 'api_endpoint',
            'endpoint' => $command['endpoint'],
            'method' => $command['method'] ?? 'GET',
            'data' => $command
        ];
    }
    
    public function validate($command) {
        if (empty($command['endpoint'])) {
            throw new Exception("Missing endpoint for API handler");
        }
        return true;
    }
    
    public function getRequiredPermissions($command) {
        return $command['permissions'] ?? [];
    }
}

/**
 * Command Validation Middleware
 */
class CommandValidationMiddleware {
    public function process($command, $context) {
        // Basic sanitization
        if (is_array($command)) {
            $command = $this->sanitizeArray($command);
        }
        
        return $command;
    }
    
    private function sanitizeArray($array) {
        foreach ($array as $key => $value) {
            if (is_string($value)) {
                $array[$key] = htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
            } elseif (is_array($value)) {
                $array[$key] = $this->sanitizeArray($value);
            }
        }
        return $array;
    }
}

/**
 * Logging Middleware
 */
class CommandLoggingMiddleware {
    public function process($command, $context) {
        error_log("Command executed: " . json_encode([
            'command' => $command,
            'user' => $context['user_id'] ?? 'anonymous',
            'timestamp' => date('Y-m-d H:i:s')
        ]));
        
        return $command;
    }
}