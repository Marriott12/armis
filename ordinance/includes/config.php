<?php
/**
 * ARMIS Ordinance Module Configuration
 * Central configuration for ordinance module
 */

// Module Configuration
const ORDINANCE_VERSION = '2.0.0';
const ORDINANCE_NAME = 'Ordinance Management System';

// Development/Production Settings
if (!defined('ARMIS_DEVELOPMENT')) {
    define('ARMIS_DEVELOPMENT', true); // Set to false in production
}

// Paths
const ORDINANCE_ROOT = __DIR__ . '/..';
const ORDINANCE_INCLUDES = __DIR__;
const ORDINANCE_ASSETS = ORDINANCE_ROOT . '/assets';

// URL Paths
const ORDINANCE_URL = '/Armis2/ordinance';
const ORDINANCE_ASSETS_URL = ORDINANCE_URL . '/assets';

// Database Tables
const ORDINANCE_TABLES = [
    'inventory' => 'ordinance_inventory',
    'weapons' => 'weapons_registry',
    'ammunition' => 'ammunition_inventory',
    'maintenance' => 'maintenance_records',
    'transactions' => 'ordinance_transactions',
    'security_logs' => 'ordinance_security_logs',
    'weapon_assignments' => 'weapon_assignments',
    'activity_log' => 'activity_log'
];

// Module Settings
const ORDINANCE_SETTINGS = [
    'enable_weapons_tracking' => true,
    'enable_ammunition_management' => true,
    'enable_maintenance_scheduling' => true,
    'require_security_clearance' => true,
    'default_security_level' => 'Restricted',
    'maintenance_interval_days' => 90
];

// Navigation Links
const ORDINANCE_NAVIGATION = [
    [
        'title' => 'Dashboard',
        'url' => ORDINANCE_URL . '/index.php',
        'icon' => 'tachometer-alt',
        'page' => 'dashboard',
        'permission' => 'user'
    ],
    [
        'title' => 'Inventory Management',
        'url' => ORDINANCE_URL . '/inventory.php',
        'icon' => 'boxes',
        'page' => 'inventory',
        'permission' => 'ordinance'
    ],
    [
        'title' => 'Weapons Registry',
        'url' => ORDINANCE_URL . '/weapons.php',
        'icon' => 'crosshairs',
        'page' => 'weapons',
        'permission' => 'ordinance'
    ],
    [
        'title' => 'Ammunition Tracking',
        'url' => ORDINANCE_URL . '/ammunition.php',
        'icon' => 'circle',
        'page' => 'ammunition',
        'permission' => 'ordinance'
    ],
    [
        'title' => 'Maintenance Schedules',
        'url' => ORDINANCE_URL . '/maintenance.php',
        'icon' => 'tools',
        'page' => 'maintenance',
        'permission' => 'ordinance'
    ],
    [
        'title' => 'Security Protocols',
        'url' => ORDINANCE_URL . '/security.php',
        'icon' => 'shield-alt',
        'page' => 'security',
        'permission' => 'ordinance'
    ],
    [
        'title' => 'Reports',
        'url' => ORDINANCE_URL . '/reports.php',
        'icon' => 'chart-bar',
        'page' => 'reports',
        'permission' => 'ordinance'
    ]
];

// Pagination Settings
const ORDINANCE_PAGINATION = [
    'default_per_page' => 25,
    'max_per_page' => 100,
    'options' => [10, 25, 50, 100]
];

// Weapon Status Options
const WEAPON_STATUS_OPTIONS = [
    'Available' => 'Available',
    'Assigned' => 'Assigned',
    'Maintenance' => 'Maintenance',
    'Damaged' => 'Damaged',
    'Retired' => 'Retired',
    'Lost' => 'Lost'
];

// Weapon Categories
const WEAPON_CATEGORIES = [
    'Rifle' => 'Rifle',
    'Pistol' => 'Pistol',
    'Machine Gun' => 'Machine Gun',
    'Sniper Rifle' => 'Sniper Rifle',
    'Shotgun' => 'Shotgun',
    'Grenade Launcher' => 'Grenade Launcher',
    'Anti-Tank' => 'Anti-Tank',
    'Artillery' => 'Artillery'
];

// Ammunition Types
const AMMUNITION_TYPES = [
    '5.56mm NATO' => '5.56mm NATO',
    '7.62mm NATO' => '7.62mm NATO',
    '9mm Parabellum' => '9mm Parabellum',
    '.45 ACP' => '.45 ACP',
    '12 Gauge' => '12 Gauge',
    '40mm Grenade' => '40mm Grenade',
    '20mm' => '20mm',
    '25mm' => '25mm'
];

// Maintenance Types
const MAINTENANCE_TYPES = [
    'Routine' => 'Routine',
    'Preventive' => 'Preventive',
    'Corrective' => 'Corrective',
    'Emergency' => 'Emergency',
    'Overhaul' => 'Overhaul'
];

// Security Classifications
const ORDINANCE_SECURITY_CLASSIFICATIONS = [
    'Unclassified' => 'Unclassified',
    'Restricted' => 'Restricted',
    'Confidential' => 'Confidential',
    'Secret' => 'Secret',
    'Top Secret' => 'Top Secret'
];

// Transaction Types
const TRANSACTION_TYPES = [
    'Issue' => 'Issue',
    'Return' => 'Return',
    'Transfer' => 'Transfer',
    'Dispose' => 'Dispose',
    'Inventory' => 'Inventory',
    'Loss' => 'Loss'
];

// Priority Levels
const ORDINANCE_PRIORITY_LEVELS = [
    'Low' => 'Low',
    'Medium' => 'Medium',
    'High' => 'High',
    'Critical' => 'Critical',
    'Emergency' => 'Emergency'
];

// Date Formats
const ORDINANCE_DATE_FORMATS = [
    'display' => 'M j, Y',
    'input' => 'Y-m-d',
    'datetime' => 'Y-m-d H:i:s',
    'audit' => 'd M Y H:i:s'
];

// Security Settings
const ORDINANCE_SECURITY = [
    'require_biometric_access' => false,
    'log_all_transactions' => true,
    'encrypt_weapon_data' => true,
    'audit_all_access' => true,
    'require_dual_authorization' => true,
    'real_time_monitoring' => true
];

// Export Settings
const ORDINANCE_EXPORT = [
    'enable_pdf' => true,
    'enable_excel' => true,
    'enable_csv' => true,
    'default_format' => 'pdf',
    'security_watermark' => true,
    'classification_headers' => true
];

// Feature Flags
const ORDINANCE_FEATURES = [
    'real_time_tracking' => true,
    'mobile_responsive' => true,
    'advanced_search' => true,
    'bulk_operations' => true,
    'notification_system' => true,
    'barcode_scanning' => false,
    'rfid_tracking' => false,
    'geolocation_tracking' => false
];