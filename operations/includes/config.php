<?php
/**
 * ARMIS Operations Module Configuration
 * Central configuration for operations module
 */

// Module Configuration
const OPERATIONS_VERSION = '2.0.0';
const OPERATIONS_NAME = 'Operations Management System';

// Development/Production Settings
if (!defined('ARMIS_DEVELOPMENT')) {
    define('ARMIS_DEVELOPMENT', true); // Set to false in production
}

// Paths
const OPERATIONS_ROOT = __DIR__ . '/..';
const OPERATIONS_INCLUDES = __DIR__;
const OPERATIONS_ASSETS = OPERATIONS_ROOT . '/assets';

// URL Paths
const OPERATIONS_URL = '/Armis2/operations';
const OPERATIONS_ASSETS_URL = OPERATIONS_URL . '/assets';

// Database Tables
const OPERATIONS_TABLES = [
    'operations' => 'operations',
    'missions' => 'missions',
    'deployments' => 'deployments',
    'resources' => 'resources',
    'resource_allocation' => 'resource_allocation',
    'field_reports' => 'field_reports',
    'operation_status' => 'operation_status',
    'activity_log' => 'activity_log'
];

// Module Settings
const OPERATIONS_SETTINGS = [
    'enable_mission_planning' => true,
    'enable_resource_tracking' => true,
    'enable_field_operations' => true,
    'max_operation_duration_days' => 365,
    'default_security_level' => 'Classified'
];

// Navigation Links
const OPERATIONS_NAVIGATION = [
    [
        'title' => 'Dashboard',
        'url' => OPERATIONS_URL . '/index.php',
        'icon' => 'tachometer-alt',
        'page' => 'dashboard',
        'permission' => 'user'
    ],
    [
        'title' => 'Mission Planning',
        'url' => OPERATIONS_URL . '/missions.php',
        'icon' => 'map-marked-alt',
        'page' => 'missions',
        'permission' => 'operations'
    ],
    [
        'title' => 'Deployments',
        'url' => OPERATIONS_URL . '/deployments.php',
        'icon' => 'plane',
        'page' => 'deployments',
        'permission' => 'operations'
    ],
    [
        'title' => 'Resource Allocation',
        'url' => OPERATIONS_URL . '/resources.php',
        'icon' => 'boxes',
        'page' => 'resources',
        'permission' => 'operations'
    ],
    [
        'title' => 'Field Operations',
        'url' => OPERATIONS_URL . '/field.php',
        'icon' => 'crosshairs',
        'page' => 'field',
        'permission' => 'operations'
    ],
    [
        'title' => 'Status Reports',
        'url' => OPERATIONS_URL . '/reports.php',
        'icon' => 'clipboard-list',
        'page' => 'reports',
        'permission' => 'operations'
    ]
];

// Pagination Settings
const OPERATIONS_PAGINATION = [
    'default_per_page' => 25,
    'max_per_page' => 100,
    'options' => [10, 25, 50, 100]
];

// Operation Status Options
const OPERATION_STATUS_OPTIONS = [
    'Planning' => 'Planning',
    'Active' => 'Active',
    'On Hold' => 'On Hold',
    'Completed' => 'Completed',
    'Cancelled' => 'Cancelled',
    'Classified' => 'Classified'
];

// Mission Types
const MISSION_TYPES = [
    'Reconnaissance' => 'Reconnaissance',
    'Combat' => 'Combat',
    'Peacekeeping' => 'Peacekeeping',
    'Training' => 'Training',
    'Support' => 'Support',
    'Humanitarian' => 'Humanitarian'
];

// Security Classifications
const SECURITY_CLASSIFICATIONS = [
    'Unclassified' => 'Unclassified',
    'Restricted' => 'Restricted',
    'Confidential' => 'Confidential',
    'Secret' => 'Secret',
    'Top Secret' => 'Top Secret'
];

// Resource Types
const RESOURCE_TYPES = [
    'Personnel' => 'Personnel',
    'Equipment' => 'Equipment',
    'Vehicles' => 'Vehicles',
    'Weapons' => 'Weapons',
    'Supplies' => 'Supplies',
    'Support' => 'Support'
];

// Deployment Status Options
const DEPLOYMENT_STATUS_OPTIONS = [
    'Preparing' => 'Preparing',
    'Deployed' => 'Deployed',
    'En Route' => 'En Route',
    'Operational' => 'Operational',
    'Returning' => 'Returning',
    'Returned' => 'Returned'
];

// Priority Levels
const OPERATIONS_PRIORITY_LEVELS = [
    'Low' => 'Low',
    'Medium' => 'Medium',
    'High' => 'High',
    'Critical' => 'Critical',
    'Emergency' => 'Emergency'
];

// Date Formats
const OPERATIONS_DATE_FORMATS = [
    'display' => 'M j, Y',
    'input' => 'Y-m-d',
    'datetime' => 'Y-m-d H:i:s',
    'military' => 'd M Y H:i\Z'
];

// Security Settings
const OPERATIONS_SECURITY = [
    'require_security_clearance' => true,
    'log_all_activities' => true,
    'encrypt_mission_data' => true,
    'audit_field_access' => true,
    'require_dual_authorization' => true
];

// Export Settings
const OPERATIONS_EXPORT = [
    'enable_pdf' => true,
    'enable_excel' => true,
    'enable_csv' => true,
    'default_format' => 'pdf',
    'security_watermark' => true
];

// Feature Flags
const OPERATIONS_FEATURES = [
    'real_time_tracking' => true,
    'mobile_responsive' => true,
    'advanced_search' => true,
    'bulk_operations' => true,
    'notification_system' => true,
    'gis_integration' => true,
    'satellite_imagery' => false
];