<?php
/**
 * ARMIS Command Module Configuration
 * Central configuration for command module
 */

// Module Configuration
const COMMAND_VERSION = '2.0.0';
const COMMAND_NAME = 'Command Management System';

// Development/Production Settings
if (!defined('ARMIS_DEVELOPMENT')) {
    define('ARMIS_DEVELOPMENT', true); // Set to false in production
}

// Paths
const COMMAND_ROOT = __DIR__ . '/..';
const COMMAND_INCLUDES = __DIR__;
const COMMAND_ASSETS = COMMAND_ROOT . '/assets';

// URL Paths
const COMMAND_URL = '/Armis2/command';
const COMMAND_ASSETS_URL = COMMAND_URL . '/assets';

// Database Tables
const COMMAND_TABLES = [
    'commands' => 'commands',
    'command_hierarchy' => 'command_hierarchy',
    'missions' => 'missions',
    'personnel_assignments' => 'personnel_assignments',
    'communication_logs' => 'communication_logs',
    'command_reports' => 'command_reports',
    'activity_log' => 'activity_log'
];

// Module Settings
const COMMAND_SETTINGS = [
    'enable_real_time_updates' => true,
    'enable_mission_tracking' => true,
    'enable_hierarchy_management' => true,
    'max_mission_duration_days' => 365,
    'default_command_level' => 'Unit'
];

// Navigation Links
const COMMAND_NAVIGATION = [
    [
        'title' => 'Dashboard',
        'url' => COMMAND_URL . '/index.php',
        'icon' => 'tachometer-alt',
        'page' => 'dashboard',
        'permission' => 'user'
    ],
    [
        'title' => 'Command Hierarchy',
        'url' => COMMAND_URL . '/hierarchy.php',
        'icon' => 'sitemap',
        'page' => 'hierarchy',
        'permission' => 'command'
    ],
    [
        'title' => 'Mission Planning',
        'url' => COMMAND_URL . '/missions.php',
        'icon' => 'map-marked-alt',
        'page' => 'missions',
        'permission' => 'command'
    ],
    [
        'title' => 'Personnel Assignments',
        'url' => COMMAND_URL . '/assignments.php',
        'icon' => 'users',
        'page' => 'assignments',
        'permission' => 'command'
    ],
    [
        'title' => 'Communication Logs',
        'url' => COMMAND_URL . '/communications.php',
        'icon' => 'comments',
        'page' => 'communications',
        'permission' => 'command'
    ],
    [
        'title' => 'Reports',
        'url' => COMMAND_URL . '/reports.php',
        'icon' => 'chart-line',
        'page' => 'reports',
        'permission' => 'user'
    ]
];

// Pagination Settings
const COMMAND_PAGINATION = [
    'default_per_page' => 25,
    'max_per_page' => 100,
    'options' => [10, 25, 50, 100]
];

// Mission Status Options
const MISSION_STATUS_OPTIONS = [
    'Planning' => 'Planning',
    'Active' => 'Active',
    'On Hold' => 'On Hold',
    'Completed' => 'Completed',
    'Cancelled' => 'Cancelled'
];

// Command Level Options
const COMMAND_LEVEL_OPTIONS = [
    'Strategic' => 'Strategic',
    'Operational' => 'Operational',
    'Tactical' => 'Tactical',
    'Unit' => 'Unit'
];

// Priority Levels
const PRIORITY_LEVELS = [
    'Low' => 'Low',
    'Medium' => 'Medium',
    'High' => 'High',
    'Critical' => 'Critical'
];

// Date Formats
const COMMAND_DATE_FORMATS = [
    'display' => 'M j, Y',
    'input' => 'Y-m-d',
    'datetime' => 'Y-m-d H:i:s',
    'military' => 'd M Y H:i'
];

// Security Settings
const COMMAND_SECURITY = [
    'require_secure_communications' => true,
    'log_all_activities' => true,
    'encrypt_sensitive_data' => true,
    'audit_mission_access' => true
];

// Export Settings
const COMMAND_EXPORT = [
    'enable_pdf' => true,
    'enable_excel' => true,
    'enable_csv' => true,
    'default_format' => 'pdf'
];

// Feature Flags
const COMMAND_FEATURES = [
    'real_time_dashboard' => true,
    'mobile_responsive' => true,
    'advanced_search' => true,
    'bulk_operations' => true,
    'notification_system' => true
];