<?php
/**
 * ARMIS Finance Module Configuration
 * Central configuration for finance module
 */

// Module Configuration
const FINANCE_VERSION = '2.0.0';
const FINANCE_NAME = 'Finance Management System';

// Development/Production Settings
if (!defined('ARMIS_DEVELOPMENT')) {
    define('ARMIS_DEVELOPMENT', true); // Set to false in production
}

// Paths
const FINANCE_ROOT = __DIR__ . '/..';
const FINANCE_INCLUDES = __DIR__;
const FINANCE_ASSETS = FINANCE_ROOT . '/assets';

// URL Paths
const FINANCE_URL = '/Armis2/finance';
const FINANCE_ASSETS_URL = FINANCE_URL . '/assets';

// Database Tables
const FINANCE_TABLES = [
    'budgets' => 'budgets',
    'expenditures' => 'expenditures',
    'procurement' => 'procurement',
    'financial_reports' => 'financial_reports',
    'audit_logs' => 'audit_logs',
    'budget_allocations' => 'budget_allocations',
    'financial_categories' => 'financial_categories',
    'activity_log' => 'activity_log'
];

// Module Settings
const FINANCE_SETTINGS = [
    'enable_budget_management' => true,
    'enable_procurement_tracking' => true,
    'enable_audit_trails' => true,
    'fiscal_year_start_month' => 4, // April
    'default_currency' => 'USD',
    'decimal_places' => 2
];

// Navigation Links
const FINANCE_NAVIGATION = [
    [
        'title' => 'Dashboard',
        'url' => FINANCE_URL . '/index.php',
        'icon' => 'tachometer-alt',
        'page' => 'dashboard',
        'permission' => 'user'
    ],
    [
        'title' => 'Budget Management',
        'url' => FINANCE_URL . '/budget.php',
        'icon' => 'chart-line',
        'page' => 'budget',
        'permission' => 'finance'
    ],
    [
        'title' => 'Expenditures',
        'url' => FINANCE_URL . '/expenditures.php',
        'icon' => 'money-bill-wave',
        'page' => 'expenditures',
        'permission' => 'finance'
    ],
    [
        'title' => 'Procurement',
        'url' => FINANCE_URL . '/procurement.php',
        'icon' => 'shopping-cart',
        'page' => 'procurement',
        'permission' => 'finance'
    ],
    [
        'title' => 'Financial Reports',
        'url' => FINANCE_URL . '/reports.php',
        'icon' => 'chart-bar',
        'page' => 'reports',
        'permission' => 'finance'
    ],
    [
        'title' => 'Audit Management',
        'url' => FINANCE_URL . '/audit.php',
        'icon' => 'search-dollar',
        'page' => 'audit',
        'permission' => 'finance'
    ]
];

// Pagination Settings
const FINANCE_PAGINATION = [
    'default_per_page' => 25,
    'max_per_page' => 100,
    'options' => [10, 25, 50, 100]
];

// Budget Status Options
const BUDGET_STATUS_OPTIONS = [
    'Draft' => 'Draft',
    'Proposed' => 'Proposed',
    'Approved' => 'Approved',
    'Active' => 'Active',
    'Locked' => 'Locked',
    'Closed' => 'Closed'
];

// Expenditure Categories
const EXPENDITURE_CATEGORIES = [
    'Personnel' => 'Personnel',
    'Equipment' => 'Equipment',
    'Operations' => 'Operations',
    'Maintenance' => 'Maintenance',
    'Training' => 'Training',
    'Infrastructure' => 'Infrastructure',
    'Research' => 'Research',
    'Other' => 'Other'
];

// Procurement Status Options
const PROCUREMENT_STATUS_OPTIONS = [
    'Requested' => 'Requested',
    'Approved' => 'Approved',
    'Ordered' => 'Ordered',
    'Delivered' => 'Delivered',
    'Cancelled' => 'Cancelled',
    'On Hold' => 'On Hold'
];

// Financial Priority Levels
const FINANCE_PRIORITY_LEVELS = [
    'Low' => 'Low',
    'Medium' => 'Medium',
    'High' => 'High',
    'Critical' => 'Critical',
    'Emergency' => 'Emergency'
];

// Audit Status Options
const AUDIT_STATUS_OPTIONS = [
    'Scheduled' => 'Scheduled',
    'In Progress' => 'In Progress',
    'Under Review' => 'Under Review',
    'Completed' => 'Completed',
    'Follow-up Required' => 'Follow-up Required'
];

// Payment Methods
const PAYMENT_METHODS = [
    'Bank Transfer' => 'Bank Transfer',
    'Check' => 'Check',
    'Credit Card' => 'Credit Card',
    'Petty Cash' => 'Petty Cash',
    'Government Card' => 'Government Card'
];

// Date Formats
const FINANCE_DATE_FORMATS = [
    'display' => 'M j, Y',
    'input' => 'Y-m-d',
    'datetime' => 'Y-m-d H:i:s',
    'fiscal' => 'Y-m-d'
];

// Currency Settings
const FINANCE_CURRENCY = [
    'symbol' => '$',
    'code' => 'USD',
    'position' => 'before', // before or after
    'thousands_separator' => ',',
    'decimal_separator' => '.'
];

// Security Settings
const FINANCE_SECURITY = [
    'require_dual_approval' => true,
    'log_all_transactions' => true,
    'encrypt_financial_data' => true,
    'audit_all_access' => true,
    'require_digital_signatures' => true
];

// Export Settings
const FINANCE_EXPORT = [
    'enable_pdf' => true,
    'enable_excel' => true,
    'enable_csv' => true,
    'default_format' => 'excel',
    'include_audit_trail' => true
];

// Feature Flags
const FINANCE_FEATURES = [
    'real_time_budgeting' => true,
    'mobile_responsive' => true,
    'advanced_search' => true,
    'bulk_operations' => true,
    'notification_system' => true,
    'automated_reporting' => true,
    'integration_apis' => true
];