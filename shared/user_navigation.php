<?php
/**
 * User Module Shared Navigation
 * Single source of truth for user navigation menu
 * 
 * Usage: require_once dirname(__DIR__) . '/shared/user_navigation.php';
 */

// User navigation items configuration
$userNavigationItems = [
    [
        'title' => 'My Profile',
        'url' => '/Armis2/users/index.php',
        'icon' => 'user',
        'page' => 'index',
        'description' => 'View your profile overview and statistics'
    ],
    [
        'title' => 'Personal Information',
        'url' => '/Armis2/users/personal.php',
        'icon' => 'id-card',
        'page' => 'personal',
        'description' => 'Edit your personal details and contact information'
    ],
    [
        'title' => 'Service Record',
        'url' => '/Armis2/users/service.php',
        'icon' => 'medal',
        'page' => 'service',
        'description' => 'View your awards, medals, and promotions'
    ],
    [
        'title' => 'Training History',
        'url' => '/Armis2/users/training.php',
        'icon' => 'graduation-cap',
        'page' => 'training',
        'description' => 'View your training courses and education records'
    ],
    [
        'title' => 'Family Members',
        'url' => '/Armis2/users/family.php',
        'icon' => 'users',
        'page' => 'family',
        'description' => 'Manage your family member information'
    ],
    [
        'title' => 'Analytics Dashboard',
        'url' => '/Armis2/users/analytics_dashboard.php',
        'icon' => 'chart-line',
        'page' => 'analytics_dashboard',
        'description' => 'Comprehensive analytics and insights'
    ],
    [
        'title' => 'Download CV',
        'url' => '/Armis2/users/cv_download.php',
        'icon' => 'download',
        'page' => 'cv_download',
        'description' => 'Download your CV in PDF format'
    ],
    [
        'title' => 'Account Settings',
        'url' => '/Armis2/users/settings.php',
        'icon' => 'cogs',
        'page' => 'settings',
        'description' => 'Change password and account preferences'
    ]
];

/**
 * Get the current page identifier
 * @return string Current page name
 */
function getCurrentUserPage() {
    $currentScript = basename($_SERVER['PHP_SELF'], '.php');
    return $currentScript;
}

/**
 * Render user navigation menu (for quick action buttons)
 * @param string $currentPage Current page identifier for active state
 * @param array $excludePages Pages to exclude from the menu
 */
function renderUserNavigationMenu($currentPage = null, $excludePages = []) {
    global $userNavigationItems;
    
    if ($currentPage === null) {
        $currentPage = getCurrentUserPage();
    }
    
    foreach ($userNavigationItems as $item) {
        if (in_array($item['page'], $excludePages)) {
            continue;
        }
        
        $isActive = ($item['page'] === $currentPage);
        $activeClass = $isActive ? 'btn-primary' : 'btn-outline-primary';
        
        echo '<a href="' . htmlspecialchars($item['url']) . '" class="btn ' . $activeClass . ' mb-2" title="' . htmlspecialchars($item['description']) . '">';
        echo '<i class="fas fa-' . htmlspecialchars($item['icon']) . ' me-2"></i>' . htmlspecialchars($item['title']);
        echo '</a>' . "\n";
    }
}

/**
 * Render user navigation as dropdown menu
 * @param string $currentPage Current page identifier
 */
function renderUserNavigationDropdown($currentPage = null) {
    global $userNavigationItems;
    
    if ($currentPage === null) {
        $currentPage = getCurrentUserPage();
    }
    
    // Find current page title
    $currentPageTitle = 'Menu';
    foreach ($userNavigationItems as $item) {
        if ($item['page'] === $currentPage) {
            $currentPageTitle = $item['title'];
            break;
        }
    }
    
    echo '<div class="dropdown">';
    echo '<button class="btn btn-outline-primary dropdown-toggle" type="button" data-bs-toggle="dropdown">';
    echo '<i class="fas fa-bars me-2"></i>' . htmlspecialchars($currentPageTitle);
    echo '</button>';
    echo '<ul class="dropdown-menu">';
    
    foreach ($userNavigationItems as $item) {
        $isActive = ($item['page'] === $currentPage);
        $activeClass = $isActive ? ' active' : '';
        
        echo '<li><a class="dropdown-item' . $activeClass . '" href="' . htmlspecialchars($item['url']) . '">';
        echo '<i class="fas fa-' . htmlspecialchars($item['icon']) . ' me-2"></i>' . htmlspecialchars($item['title']);
        echo '</a></li>';
    }
    
    echo '</ul>';
    echo '</div>';
}

/**
 * Render breadcrumb navigation
 * @param string $currentPage Current page identifier
 */
function renderUserBreadcrumb($currentPage = null) {
    global $userNavigationItems;
    
    if ($currentPage === null) {
        $currentPage = getCurrentUserPage();
    }
    
    echo '<nav aria-label="breadcrumb">';
    echo '<ol class="breadcrumb">';
    echo '<li class="breadcrumb-item"><a href="/Armis2/users/index.php"><i class="fas fa-home"></i> Home</a></li>';
    
    foreach ($userNavigationItems as $item) {
        if ($item['page'] === $currentPage) {
            echo '<li class="breadcrumb-item active" aria-current="page">';
            echo '<i class="fas fa-' . htmlspecialchars($item['icon']) . ' me-1"></i>' . htmlspecialchars($item['title']);
            echo '</li>';
            break;
        }
    }
    
    echo '</ol>';
    echo '</nav>';
}

/**
 * Get navigation item by page identifier
 * @param string $page Page identifier
 * @return array|null Navigation item or null if not found
 */
function getUserNavigationItem($page) {
    global $userNavigationItems;
    
    foreach ($userNavigationItems as $item) {
        if ($item['page'] === $page) {
            return $item;
        }
    }
    
    return null;
}

// Backward compatibility: keep $navigationItems variable for existing code
$navigationItems = $userNavigationItems;
?>
