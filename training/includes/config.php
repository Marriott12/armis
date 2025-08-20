<?php
/**
 * ARMIS Training Module Configuration
 * Central configuration for training module
 */

// Module Configuration
const TRAINING_VERSION = '2.0.0';
const TRAINING_NAME = 'Training Management System';

// Development/Production Settings
if (!defined('ARMIS_DEVELOPMENT')) {
    define('ARMIS_DEVELOPMENT', true); // Set to false in production
}

// Paths
const TRAINING_ROOT = __DIR__ . '/..';
const TRAINING_INCLUDES = __DIR__;
const TRAINING_ASSETS = TRAINING_ROOT . '/assets';

// URL Paths
const TRAINING_URL = '/Armis2/training';
const TRAINING_ASSETS_URL = TRAINING_URL . '/assets';

// Database Tables
const TRAINING_TABLES = [
    'courses' => 'courses',
    'training_records' => 'training_records',
    'certifications' => 'certifications',
    'training_schedule' => 'training_schedule',
    'instructors' => 'instructors',
    'course_materials' => 'course_materials',
    'activity_log' => 'activity_log'
];

// Module Settings
const TRAINING_SETTINGS = [
    'enable_certification_tracking' => true,
    'enable_course_catalog' => true,
    'enable_schedule_management' => true,
    'max_course_duration_hours' => 720,
    'default_certification_validity_months' => 24
];

// Navigation Links
const TRAINING_NAVIGATION = [
    [
        'title' => 'Dashboard',
        'url' => TRAINING_URL . '/index.php',
        'icon' => 'tachometer-alt',
        'page' => 'dashboard',
        'permission' => 'user'
    ],
    [
        'title' => 'Course Catalog',
        'url' => TRAINING_URL . '/courses.php',
        'icon' => 'book',
        'page' => 'courses',
        'permission' => 'user'
    ],
    [
        'title' => 'Training Records',
        'url' => TRAINING_URL . '/records.php',
        'icon' => 'certificate',
        'page' => 'records',
        'permission' => 'training'
    ],
    [
        'title' => 'Certifications',
        'url' => TRAINING_URL . '/certifications.php',
        'icon' => 'award',
        'page' => 'certifications',
        'permission' => 'training'
    ],
    [
        'title' => 'Schedule Management',
        'url' => TRAINING_URL . '/schedule.php',
        'icon' => 'calendar',
        'page' => 'schedule',
        'permission' => 'training'
    ],
    [
        'title' => 'Instructors',
        'url' => TRAINING_URL . '/instructors.php',
        'icon' => 'chalkboard-teacher',
        'page' => 'instructors',
        'permission' => 'training'
    ],
    [
        'title' => 'Reports',
        'url' => TRAINING_URL . '/reports.php',
        'icon' => 'chart-line',
        'page' => 'reports',
        'permission' => 'user'
    ]
];

// Pagination Settings
const TRAINING_PAGINATION = [
    'default_per_page' => 25,
    'max_per_page' => 100,
    'options' => [10, 25, 50, 100]
];

// Course Status Options
const COURSE_STATUS_OPTIONS = [
    'Draft' => 'Draft',
    'Active' => 'Active',
    'Suspended' => 'Suspended',
    'Completed' => 'Completed',
    'Cancelled' => 'Cancelled'
];

// Training Status Options
const TRAINING_STATUS_OPTIONS = [
    'Enrolled' => 'Enrolled',
    'In Progress' => 'In Progress',
    'Completed' => 'Completed',
    'Failed' => 'Failed',
    'Withdrawn' => 'Withdrawn'
];

// Certification Status Options
const CERTIFICATION_STATUS_OPTIONS = [
    'Valid' => 'Valid',
    'Expired' => 'Expired',
    'Pending' => 'Pending',
    'Suspended' => 'Suspended'
];

// Course Difficulty Levels
const DIFFICULTY_LEVELS = [
    'Beginner' => 'Beginner',
    'Intermediate' => 'Intermediate',
    'Advanced' => 'Advanced',
    'Expert' => 'Expert'
];

// Training Categories
const TRAINING_CATEGORIES = [
    'Leadership' => 'Leadership',
    'Technical' => 'Technical',
    'Physical' => 'Physical',
    'Weapons' => 'Weapons',
    'Safety' => 'Safety',
    'Medical' => 'Medical',
    'Communications' => 'Communications'
];

// Date Formats
const TRAINING_DATE_FORMATS = [
    'display' => 'M j, Y',
    'input' => 'Y-m-d',
    'datetime' => 'Y-m-d H:i:s',
    'schedule' => 'd M Y H:i'
];

// Security Settings
const TRAINING_SECURITY = [
    'require_certification_approval' => true,
    'log_all_activities' => true,
    'audit_instructor_access' => true,
    'encrypt_training_records' => true
];

// Export Settings
const TRAINING_EXPORT = [
    'enable_pdf' => true,
    'enable_excel' => true,
    'enable_csv' => true,
    'default_format' => 'pdf'
];

// Feature Flags
const TRAINING_FEATURES = [
    'real_time_progress' => true,
    'mobile_responsive' => true,
    'advanced_search' => true,
    'bulk_operations' => true,
    'notification_system' => true,
    'certificate_generation' => true
];