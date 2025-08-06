<?php
/**
 * ARMIS Admin Branch Configuration
 * Central configuration for admin branch module
 */

// Module Configuration
const ADMIN_BRANCH_VERSION = '2.0.0';
const ADMIN_BRANCH_NAME = 'Admin Branch Management System';

// Development/Production Settings
if (!defined('ARMIS_DEVELOPMENT')) {
    define('ARMIS_DEVELOPMENT', true); // Set to false in production
}

// Paths
const ADMIN_BRANCH_ROOT = __DIR__ . '/..';
const ADMIN_BRANCH_INCLUDES = __DIR__;
const ADMIN_BRANCH_ASSETS = ADMIN_BRANCH_ROOT . '/assets';

// URL Paths
const ADMIN_BRANCH_URL = '/Armis2/admin_branch';
const ADMIN_BRANCH_ASSETS_URL = ADMIN_BRANCH_URL . '/assets';

// Database Tables
const TABLES = [
    'staff' => 'staff',
    'ranks' => 'ranks',
    'units' => 'units',
    'corps' => 'corps',
    'appointments' => 'appointments',
    'medals' => 'medals',
    'staff_medals' => 'staff_medals',
    'activity_log' => 'activity_log',
    'promotions' => 'promotions'
];

// Rank Categories
const RANK_CATEGORIES = [
    'Officer' => [1, 13],      // rankIndex 1-13
    'NCO' => [15, 26],         // rankIndex 15-26
    'Enlisted' => [27, 35]     // rankIndex 27-35
];

// Excluded Ranks (not shown in normal lists)
const EXCLUDED_RANKS = [
    'Officer Cadet',
    'Recruit', 
    'Mister',
    'Miss'
];

// File Upload Settings
const UPLOAD_SETTINGS = [
    'max_size' => 5 * 1024 * 1024, // 5MB
    'allowed_types' => ['jpg', 'jpeg', 'png', 'pdf', 'doc', 'docx'],
    'upload_path' => ADMIN_BRANCH_ROOT . '/uploads',
    'temp_path' => ADMIN_BRANCH_ROOT . '/temp'
];

// Pagination Settings
const PAGINATION = [
    'default_per_page' => 25,
    'max_per_page' => 100,
    'options' => [10, 25, 50, 100]
];

// Status Options
const STATUS_OPTIONS = [
    'Active' => 'Active',
    'On Leave' => 'On Leave',
    'Training' => 'Training',
    'Deployed' => 'Deployed',
    'Retired' => 'Retired',
    'Deceased' => 'Deceased',
    'Discharged' => 'Discharged'
];

// Gender Options
const GENDER_OPTIONS = [
    'Male' => 'Male',
    'Female' => 'Female'
];

// Marital Status Options
const MARITAL_STATUS_OPTIONS = [
    'Single' => 'Single',
    'Married' => 'Married',
    'Divorced' => 'Divorced',
    'Widowed' => 'Widowed'
];

// Date Formats
const DATE_FORMATS = [
    'display' => 'M j, Y',
    'input' => 'Y-m-d',
    'datetime' => 'Y-m-d H:i:s',
    'long' => 'F j, Y g:i A'
];

// Error Messages
const ERROR_MESSAGES = [
    'db_error' => 'Database error occurred. Please try again.',
    'auth_required' => 'Authentication required to access this resource.',
    'admin_required' => 'Administrator privileges required.',
    'invalid_input' => 'Invalid input provided.',
    'record_not_found' => 'Record not found.',
    'upload_failed' => 'File upload failed.',
    'permission_denied' => 'Permission denied.'
];

// Success Messages
const SUCCESS_MESSAGES = [
    'record_created' => 'Record created successfully.',
    'record_updated' => 'Record updated successfully.',
    'record_deleted' => 'Record deleted successfully.',
    'upload_success' => 'File uploaded successfully.',
    'promotion_processed' => 'Promotion processed successfully.',
    'medal_assigned' => 'Medal assigned successfully.'
];

// Navigation Menu Items
const NAVIGATION_MENU = [
    [
        'title' => 'Dashboard',
        'url' => ADMIN_BRANCH_URL . '/index.php',
        'icon' => 'tachometer-alt',
        'page' => 'dashboard',
        'permission' => 'user'
    ],
    [
        'title' => 'Staff Management',
        'url' => ADMIN_BRANCH_URL . '/edit_staff.php',
        'icon' => 'users',
        'page' => 'staff',
        'permission' => 'admin'
    ],
    [
        'title' => 'Create Staff',
        'url' => ADMIN_BRANCH_URL . '/create_staff.php',
        'icon' => 'user-plus',
        'page' => 'create',
        'permission' => 'admin'
    ],
    [
        'title' => 'Promotions',
        'url' => ADMIN_BRANCH_URL . '/promote_staff.php',
        'icon' => 'arrow-up',
        'page' => 'promotions',
        'permission' => 'admin'
    ],
    [
        'title' => 'Medals',
        'url' => ADMIN_BRANCH_URL . '/assign_medal.php',
        'icon' => 'medal',
        'page' => 'medals',
        'permission' => 'admin'
    ],
    [
        'title' => 'Reports',
        'url' => ADMIN_BRANCH_URL . '/reports_seniority.php',
        'icon' => 'chart-bar',
        'page' => 'reports',
        'permission' => 'user'
    ],
    [
        'title' => 'System Settings',
        'url' => ADMIN_BRANCH_URL . '/system_settings.php',
        'icon' => 'cogs',
        'page' => 'settings',
        'permission' => 'admin'
    ]
];

/**
 * Get filtered navigation menu based on user permissions
 */
function getNavigationMenu($userRole = 'user') {
    $menu = [];
    foreach (NAVIGATION_MENU as $item) {
        if ($item['permission'] === 'user' || 
            ($item['permission'] === 'admin' && $userRole === 'administrator')) {
            $menu[] = $item;
        }
    }
    return $menu;
}

/**
 * Get configuration value
 */
function getConfig($key, $default = null) {
    $config = [
        'version' => ADMIN_BRANCH_VERSION,
        'name' => ADMIN_BRANCH_NAME,
        'development' => ARMIS_DEVELOPMENT,
        'tables' => TABLES,
        'rank_categories' => RANK_CATEGORIES,
        'excluded_ranks' => EXCLUDED_RANKS,
        'upload_settings' => UPLOAD_SETTINGS,
        'pagination' => PAGINATION,
        'status_options' => STATUS_OPTIONS,
        'gender_options' => GENDER_OPTIONS,
        'marital_status_options' => MARITAL_STATUS_OPTIONS,
        'date_formats' => DATE_FORMATS,
        'error_messages' => ERROR_MESSAGES,
        'success_messages' => SUCCESS_MESSAGES,
        'navigation_menu' => NAVIGATION_MENU
    ];
    
    return $config[$key] ?? $default;
}

/**
 * Check if rank is excluded
 */
function isRankExcluded($rankName) {
    return in_array($rankName, EXCLUDED_RANKS);
}

/**
 * Get rank category from rank index
 */
function getRankCategory($rankIndex) {
    foreach (RANK_CATEGORIES as $category => $range) {
        if ($rankIndex >= $range[0] && $rankIndex <= $range[1]) {
            return $category;
        }
    }
    return 'Unknown';
}

/**
 * Format file size for display
 */
function formatFileSize($bytes) {
    $units = ['B', 'KB', 'MB', 'GB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= (1 << (10 * $pow));
    return round($bytes, 2) . ' ' . $units[$pow];
}

/**
 * Sanitize string for database
 */
function sanitizeString($string) {
    return trim(strip_tags($string));
}

/**
 * Format date for display
 */
function formatDate($date, $format = null) {
    if (!$date) return '';
    if (!$format) $format = DATE_FORMATS['display'];
    
    if (is_string($date)) {
        $date = new DateTime($date);
    }
    
    return $date->format($format);
}
