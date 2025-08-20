# Dynamic Command Module Documentation

## Overview

The Command Module has been enhanced with a fully dynamic architecture that eliminates hardcoded elements and provides configurable, extensible functionality. All command configurations, UI rendering, and data loading are now driven by runtime configuration files and API endpoints.

## Architecture

### Core Components

1. **CommandConfigService**: PHP service for loading and managing command configurations
2. **Command API**: RESTful endpoint for dynamic data access
3. **Dynamic Loader**: JavaScript class for runtime UI updates
4. **JSON Configuration**: Structured configuration files for commands and settings

### Configuration Files

#### commands.json
Location: `/command/config/commands.json`

The main configuration file containing:
- Navigation structure
- Dashboard modules
- Overview statistics
- System settings

```json
{
  "version": "1.0.0",
  "lastUpdated": "2024-01-01T00:00:00Z",
  "commands": {
    "navigation": [...],
    "dashboard_modules": [...],
    "overview_stats": [...]
  },
  "settings": {
    "autoRefresh": true,
    "refreshInterval": 30000,
    "enableCache": true,
    "cacheTimeout": 300
  }
}
```

## Configuration Schema

### Navigation Items
```json
{
  "id": "unique_identifier",
  "title": "Display Title",
  "url": "/path/to/page",
  "icon": "fontawesome-icon-name",
  "page": "page_identifier",
  "enabled": true,
  "order": 1
}
```

### Dashboard Modules
```json
{
  "id": "module_id",
  "title": "Module Title",
  "description": "Module description",
  "icon": "fontawesome-icon",
  "iconColor": "text-primary",
  "url": "/module/url",
  "buttonText": "Button Text",
  "buttonClass": "btn-armis",
  "enabled": true,
  "order": 1,
  "permissions": ["command.view"]
}
```

### Overview Statistics
```json
{
  "id": "stat_id",
  "title": "Statistic Title",
  "icon": "fontawesome-icon",
  "color": "bg-primary",
  "dataSource": "api",
  "endpoint": "/api/endpoint",
  "fallbackValue": "Default Value",
  "enabled": true,
  "order": 1
}
```

## API Endpoints

### Command API (`/command/api.php`)

#### Available Actions

1. **get_config** - Get complete configuration
2. **get_navigation** - Get navigation items
3. **get_dashboard_modules** - Get dashboard modules with permission filtering
4. **get_overview_stats** - Get overview statistics configuration
5. **get_settings** - Get system settings
6. **get_stats_data** - Get actual statistics data
7. **update_module** - Update a dashboard module (POST)
8. **remove_module** - Remove a dashboard module (POST)

#### Example Usage

```javascript
// Get dashboard modules
fetch('api.php?action=get_dashboard_modules')
  .then(response => response.json())
  .then(data => console.log(data));

// Update a module
fetch('api.php?action=update_module', {
  method: 'POST',
  body: new FormData()
    .append('module_id', 'my_module')
    .append('module_data', JSON.stringify(moduleData))
});
```

## Dynamic Features

### 1. Dynamic Navigation Loading
- Navigation items loaded from configuration
- Automatic permission filtering
- Order-based sorting
- Graceful fallback to defaults

### 2. Dynamic Dashboard Modules
- Modules rendered from configuration
- Permission-based visibility
- Real-time updates without code changes
- Extensible module types

### 3. Dynamic Statistics
- Real-time data loading from APIs
- Fallback values for reliability
- Automatic refresh capabilities
- Error handling and recovery

### 4. Auto-Refresh System
- Configurable refresh intervals
- Selective content updates
- Performance optimized
- User-controllable

## Adding New Commands/Modules

### 1. Through Configuration File

Edit `/command/config/commands.json` and add new entries to the appropriate sections:

```json
{
  "commands": {
    "dashboard_modules": [
      {
        "id": "new_module",
        "title": "New Module",
        "description": "Description of new module",
        "icon": "cog",
        "iconColor": "text-info",
        "url": "/command/new-module",
        "buttonText": "Open Module",
        "enabled": true,
        "order": 10,
        "permissions": ["command.new_module"]
      }
    ]
  }
}
```

### 2. Through API

```javascript
// Add new module programmatically
const moduleData = {
  title: "New Module",
  description: "Dynamic module",
  icon: "star",
  url: "/command/dynamic",
  enabled: true
};

fetch('api.php?action=update_module', {
  method: 'POST',
  headers: {'Content-Type': 'application/x-www-form-urlencoded'},
  body: new URLSearchParams({
    module_id: 'dynamic_module',
    module_data: JSON.stringify(moduleData)
  })
});
```

## Extension Points

### 1. Custom Command Handlers
Implement new command types by extending the configuration schema and adding handlers.

### 2. Data Sources
Add new data sources by implementing additional API endpoints.

### 3. UI Components
Create new UI component types by extending the dynamic loader.

### 4. Permission Systems
Integrate with existing or custom permission systems.

## Error Handling

### Configuration Errors
- Invalid JSON format validation
- Missing required fields detection
- Schema validation
- Graceful fallback to defaults

### Runtime Errors
- API endpoint failures
- Network connectivity issues
- Data loading errors
- User feedback mechanisms

### Example Error Handling
```php
try {
    $configService = new CommandConfigService();
    $modules = $configService->getDashboardModules();
} catch (Exception $e) {
    error_log("Configuration error: " . $e->getMessage());
    // Use fallback configuration
    $modules = getDefaultModules();
}
```

## Performance Considerations

### Caching
- Configuration file caching (5 minutes default)
- API response caching
- Browser-level caching headers
- Selective cache invalidation

### Optimization
- Lazy loading of components
- Efficient DOM updates
- Minimal API calls
- Batch operations

## Security Features

### Access Control
- Session-based authentication
- Role-based module access
- Permission-based filtering
- API endpoint protection

### Input Validation
- JSON schema validation
- XSS prevention
- SQL injection protection
- CSRF token verification

## Testing

### Configuration Testing
```php
// Test configuration loading
$configService = new CommandConfigService();
$config = $configService->loadConfig();
assert(!empty($config['commands']['navigation']));
```

### API Testing
```javascript
// Test API endpoints
async function testAPI() {
  const response = await fetch('api.php?action=get_navigation');
  const data = await response.json();
  console.assert(data.success === true);
}
```

## Troubleshooting

### Common Issues

1. **Configuration not loading**
   - Check file permissions
   - Validate JSON syntax
   - Review error logs

2. **Modules not appearing**
   - Check user permissions
   - Verify enabled status
   - Review API responses

3. **Auto-refresh not working**
   - Check settings configuration
   - Verify API endpoints
   - Review browser console

### Debug Mode
Enable debug mode in configuration:
```json
{
  "settings": {
    "debugMode": true
  }
}
```

## Migration Guide

### From Static to Dynamic

1. **Backup existing files**
2. **Update index.php** to use dynamic loading
3. **Create configuration files**
4. **Test functionality**
5. **Update navigation links**

### Compatibility
- Maintains existing URL structure
- Preserves user permissions
- Backward compatible with static fallbacks

## Future Enhancements

### Planned Features
- Visual configuration editor
- Module templates
- Advanced permission models
- Real-time collaboration
- Plugin architecture
- Multi-tenant configurations

### Extension Possibilities
- Database-driven configurations
- External API integrations
- Custom dashboard builders
- Advanced analytics
- Mobile-optimized interfaces

## Support

For technical support or feature requests related to the dynamic command module, please refer to the system documentation or contact the development team.