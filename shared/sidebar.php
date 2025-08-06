<!-- Sidebar -->
<?php
// Include RBAC system if not already included
if (!function_exists('hasModuleAccess')) {
    require_once __DIR__ . '/rbac.php';
}

// Get user's accessible modules
$userModules = getUserModules();
$roleInfo = getRoleInfo();
?>
<div class="sidebar position-fixed" id="sidebar" style="height: calc(100vh - 60px); overflow-y: auto; z-index: 1001;" role="navigation" aria-label="Main navigation">
    <div class="sidebar-header p-3">
        <div class="d-flex justify-content-between align-items-center">
            <h5 class="mb-0">
                <i class="fas fa-<?php echo isset($moduleIcon) ? $moduleIcon : 'home'; ?>"></i>
                <?php echo isset($moduleName) ? $moduleName : 'ARMIS Dashboard'; ?>
            </h5>
            <div class="sidebar-controls">
                <button class="btn btn-sm btn-outline-light" id="sidebarSearch" title="Search Navigation" tabindex="0">
                    <i class="fas fa-search"></i>
                </button>
                <button class="btn btn-sm btn-outline-light" id="sidebarSettings" title="Sidebar Settings" tabindex="0">
                    <i class="fas fa-cog"></i>
                </button>
            </div>
        </div>
        <!-- Breadcrumb Navigation -->
        <div class="sidebar-breadcrumb mt-2">
            <small class="text-muted">
                <i class="fas fa-map-marker-alt me-1" aria-hidden="true"></i>
                <?php echo isset($moduleName) ? $moduleName : 'ARMIS'; ?> 
                <i class="fas fa-chevron-right mx-1" aria-hidden="true"></i> 
                <?php echo isset($currentPage) ? ucfirst($currentPage) : 'Dashboard'; ?>
            </small>
        </div>
    </div>

    <!-- Search Box -->
    <div class="sidebar-search p-3" id="sidebarSearchBox" style="display: none;">
        <div class="input-group">
            <input type="text" class="form-control form-control-sm" id="navSearchInput" 
                   placeholder="Search navigation..." aria-label="Search navigation">
            <button class="btn btn-outline-secondary btn-sm" type="button" id="clearSearch">
                <i class="fas fa-times"></i>
            </button>
        </div>
    </div>
    
    <nav class="nav flex-column p-3" role="menubar">
        <!-- Favorites Section -->
        <div class="sidebar-section" id="favoritesSection">
            <h6 class="sidebar-heading text-uppercase mb-2 d-flex justify-content-between align-items-center">
                <span><i class="fas fa-star me-1"></i> Favorites</span>
                <button class="btn btn-sm btn-link p-0 text-warning" id="manageFavorites" title="Manage Favorites">
                    <i class="fas fa-edit"></i>
                </button>
            </h6>
            <div id="favoritesList">
                <!-- Favorites will be populated by JavaScript -->
            </div>
        </div>

        <?php if (isset($sidebarLinks) && is_array($sidebarLinks)): ?>
            <div class="sidebar-section">
                <h6 class="sidebar-heading text-uppercase mb-2 collapsible-header" data-target="moduleMenu">
                    <i class="fas fa-chevron-down me-1 collapse-icon"></i>
                    <?php echo isset($moduleName) ? $moduleName : 'Module'; ?> Menu
                    <?php if (isset($moduleName) && $moduleName === 'Finance'): ?>
                        <span class="badge bg-warning ms-2" title="Pending items">3</span>
                    <?php endif; ?>
                </h6>
                <div class="collapsible-content" id="moduleMenu">
                    <?php foreach ($sidebarLinks as $index => $link): ?>
                        <?php if (isset($link['children']) && is_array($link['children'])): ?>
                            <?php $isReports = strtolower($link['title']) === 'reports'; ?>
                            <div class="sidebar-group mb-2">
                                <span class="sidebar-group-title d-flex align-items-center mb-1 <?php echo $isReports ? 'collapsible-header' : ''; ?>" <?php if ($isReports): ?>data-target="reportsSubMenu"<?php endif; ?> style="cursor:pointer;">
                                    <i class="fas fa-<?php echo htmlspecialchars($link['icon']); ?> me-2"></i>
                                    <?php echo htmlspecialchars($link['title']); ?>
                                    <?php if ($isReports): ?>
                                        <i class="fas fa-chevron-down ms-auto collapse-icon"></i>
                                    <?php endif; ?>
                                </span>
                                <div class="sidebar-submenu collapsible-content<?php echo $isReports ? ' collapsed' : ''; ?>" id="<?php echo $isReports ? 'reportsSubMenu' : 'submenu_' . $index; ?>">
                                <?php foreach ($link['children'] as $child): ?>
                                    <a class="nav-link"
                                       href="<?php echo isset($child['url']) ? $child['url'] : '#'; ?>"
                                       role="menuitem"
                                       tabindex="0"
                                       data-search-terms="<?php echo strtolower($child['title']); ?>">
                                        <span class="nav-link-content">
                                            <?php echo htmlspecialchars($child['title']); ?>
                                        </span>
                                        <button class="favorite-btn" data-url="<?php echo isset($child['url']) ? $child['url'] : '#'; ?>" 
                                                data-title="<?php echo htmlspecialchars($child['title']); ?>"
                                                data-icon="<?php echo isset($child['icon']) ? htmlspecialchars($child['icon']) : 'file-alt'; ?>"
                                                title="Add to favorites" tabindex="-1">
                                            <i class="fas fa-star"></i>
                                        </button>
                                    </a>
                                <?php endforeach; ?>
                                </div>
                            </div>
                        <?php else: ?>
                            <a class="nav-link <?php echo (isset($currentPage) && $currentPage === $link['page']) ? 'active' : ''; ?>" 
                               href="<?php echo isset($link['url']) ? $link['url'] : '#'; ?>" 
                               role="menuitem" 
                               tabindex="0"
                               data-search-terms="<?php echo strtolower($link['title'] . ' ' . $link['icon']); ?>"
                               data-link-index="<?php echo $index; ?>">
                                <span class="nav-link-content">
                                    <i class="fas fa-<?php echo htmlspecialchars($link['icon']); ?> me-2" aria-hidden="true"></i>
                                    <?php echo htmlspecialchars($link['title']); ?>
                                    <?php if (isset($link['page']) && $link['page'] === 'audit'): ?>
                                        <span class="badge bg-info ms-auto">2</span>
                                    <?php endif; ?>
                                </span>
                                <button class="favorite-btn" data-url="<?php echo isset($link['url']) ? $link['url'] : '#'; ?>" 
                                        data-title="<?php echo htmlspecialchars($link['title']); ?>" 
                                        data-icon="<?php echo htmlspecialchars($link['icon']); ?>" 
                                        title="Add to favorites" tabindex="-1">
                                    <i class="fas fa-star"></i>
                                </button>
                            </a>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
            </div>
            <hr class="my-3" style="border-color: var(--armis-gold);">
        <?php endif; ?>
        
        <!-- System Navigation -->
        <div class="sidebar-section">
            <h6 class="sidebar-heading text-uppercase mb-2 collapsible-header" data-target="systemBranches">
                <i class="fas fa-chevron-down me-1 collapse-icon"></i>
                System Branches
                <span class="badge bg-success ms-2" title="Accessible modules"><?php echo count($userModules); ?></span>
            </h6>
            <div class="collapsible-content" id="systemBranches">
                <?php if (in_array('dashboard', $userModules)): ?>
                <a class="nav-link" href="/Armis2/" role="menuitem" tabindex="0" 
                   data-search-terms="dashboard home main">
                    <span class="nav-link-content">
                        <i class="fas fa-home me-2" aria-hidden="true"></i> Dashboard Home
                    </span>
                    <button class="favorite-btn" data-url="/Armis2/" data-title="Dashboard Home" data-icon="home" title="Add to favorites" tabindex="-1">
                        <i class="fas fa-star"></i>
                    </button>
                </a>
                <?php endif; ?>
                
                <?php if (in_array('admin', $userModules)): ?>
                <a class="nav-link" href="/Armis2/admin/" role="menuitem" tabindex="0" 
                   data-search-terms="system admin administration settings">
                    <span class="nav-link-content">
                        <i class="fas fa-cogs me-2" aria-hidden="true"></i> System Admin
                        <span class="badge bg-warning ms-auto">!</span>
                    </span>
                    <button class="favorite-btn" data-url="/Armis2/admin/" data-title="System Admin" data-icon="cogs" title="Add to favorites" tabindex="-1">
                        <i class="fas fa-star"></i>
                    </button>
                </a>
                <?php endif; ?>
                
                <?php if (in_array('admin_branch', $userModules)): ?>
                <a class="nav-link" href="/Armis2/admin_branch/" role="menuitem" tabindex="0" 
                   data-search-terms="admin branch management staff">
                    <span class="nav-link-content">
                        <i class="fas fa-users-cog me-2" aria-hidden="true"></i> Admin Branch
                    </span>
                    <button class="favorite-btn" data-url="/Armis2/admin_branch/" data-title="Admin Branch" data-icon="users-cog" title="Add to favorites" tabindex="-1">
                        <i class="fas fa-star"></i>
                    </button>
                </a>
                <?php endif; ?>
                
                <?php if (in_array('command', $userModules)): ?>
                <a class="nav-link" href="/Armis2/command/" role="menuitem" tabindex="0" 
                   data-search-terms="command structure hierarchy">
                    <span class="nav-link-content">
                        <i class="fas fa-chess-king me-2" aria-hidden="true"></i> Command
                    </span>
                    <button class="favorite-btn" data-url="/Armis2/command/" data-title="Command" data-icon="chess-king" title="Add to favorites" tabindex="-1">
                        <i class="fas fa-star"></i>
                    </button>
                </a>
                <?php endif; ?>
                
                <?php if (in_array('operations', $userModules)): ?>
                <a class="nav-link" href="/Armis2/operations/" role="menuitem" tabindex="0" 
                   data-search-terms="operations mission deployment">
                    <span class="nav-link-content">
                        <i class="fas fa-map-marked-alt me-2" aria-hidden="true"></i> Operations
                    </span>
                    <button class="favorite-btn" data-url="/Armis2/operations/" data-title="Operations" data-icon="map-marked-alt" title="Add to favorites" tabindex="-1">
                        <i class="fas fa-star"></i>
                    </button>
                </a>
                <?php endif; ?>
                
                <?php if (in_array('training', $userModules)): ?>
                <a class="nav-link" href="/Armis2/training/" role="menuitem" tabindex="0" 
                   data-search-terms="training education courses certification">
                    <span class="nav-link-content">
                        <i class="fas fa-graduation-cap me-2" aria-hidden="true"></i> Training
                        <span class="badge bg-info ms-auto">3</span>
                    </span>
                    <button class="favorite-btn" data-url="/Armis2/training/" data-title="Training" data-icon="graduation-cap" title="Add to favorites" tabindex="-1">
                        <i class="fas fa-star"></i>
                    </button>
                </a>
                <?php endif; ?>
                
                <?php if (in_array('finance', $userModules)): ?>
                <a class="nav-link" href="/Armis2/finance/" role="menuitem" tabindex="0" 
                   data-search-terms="finance budget money accounting">
                    <span class="nav-link-content">
                        <i class="fas fa-calculator me-2" aria-hidden="true"></i> Finance
                        <span class="badge bg-info ms-auto">5</span>
                    </span>
                    <button class="favorite-btn" data-url="/Armis2/finance/" data-title="Finance" data-icon="calculator" title="Add to favorites" tabindex="-1">
                        <i class="fas fa-star"></i>
                    </button>
                </a>
                <?php endif; ?>
                
                <?php if (in_array('ordinance', $userModules)): ?>
                <a class="nav-link" href="/Armis2/ordinance/" role="menuitem" tabindex="0" 
                   data-search-terms="ordinance weapons equipment inventory">
                    <span class="nav-link-content">
                        <i class="fas fa-shield-alt me-2" aria-hidden="true"></i> Ordinance
                    </span>
                    <button class="favorite-btn" data-url="/Armis2/ordinance/" data-title="Ordinance" data-icon="shield-alt" title="Add to favorites" tabindex="-1">
                        <i class="fas fa-star"></i>
                    </button>
                </a>
                <?php endif; ?>
            </div>
        </div>
        
        <hr class="my-3" style="border-color: var(--armis-gold);">
        
        <!-- User Navigation -->
        <div class="sidebar-section">
            <h6 class="sidebar-heading text-uppercase mb-2 collapsible-header" data-target="userOptions">
                <i class="fas fa-chevron-down me-1 collapse-icon"></i>
                User Options
            </h6>
            <div class="collapsible-content" id="userOptions">
                <a class="nav-link" href="/Armis2/users/" role="menuitem" tabindex="0" 
                   data-search-terms="user profile personal account">
                    <span class="nav-link-content">
                        <i class="fas fa-user me-2" aria-hidden="true"></i> My Profile
                    </span>
                    <button class="favorite-btn" data-url="/Armis2/users/" data-title="My Profile" data-icon="user" title="Add to favorites" tabindex="-1">
                        <i class="fas fa-star"></i>
                    </button>
                </a>
                <a class="nav-link" href="/Armis2/users/my_cvs.php" role="menuitem" tabindex="0" 
                   data-search-terms="cv curriculum vitae resume upload manage">
                    <span class="nav-link-content">
                        <i class="fas fa-file-alt me-2" aria-hidden="true"></i> My CVs
                    </span>
                    <button class="favorite-btn" data-url="/Armis2/users/my_cvs.php" data-title="My CVs" data-icon="file-alt" title="Add to favorites" tabindex="-1">
                        <i class="fas fa-star"></i>
                    </button>
                </a>
                <a class="nav-link" href="/Armis2/users/cv_upload.php" role="menuitem" tabindex="0" 
                   data-search-terms="cv upload new curriculum vitae resume">
                    <span class="nav-link-content">
                        <i class="fas fa-upload me-2" aria-hidden="true"></i> Upload CV
                    </span>
                    <button class="favorite-btn" data-url="/Armis2/users/cv_upload.php" data-title="Upload CV" data-icon="upload" title="Add to favorites" tabindex="-1">
                        <i class="fas fa-star"></i>
                    </button>
                </a>
                <a class="nav-link" href="/Armis2/users/cv_download.php" role="menuitem" tabindex="0" 
                   data-search-terms="cv download resume document">
                    <span class="nav-link-content">
                        <i class="fas fa-download me-2" aria-hidden="true"></i> Download CV
                    </span>
                    <button class="favorite-btn" data-url="/Armis2/users/cv_download.php" data-title="Download CV" data-icon="download" title="Add to favorites" tabindex="-1">
                        <i class="fas fa-star"></i>
                    </button>
                </a>
                <a class="nav-link" href="/Armis2/logout.php" role="menuitem" tabindex="0" 
                   data-search-terms="logout signout exit">
                    <span class="nav-link-content">
                        <i class="fas fa-sign-out-alt me-2" aria-hidden="true"></i> Logout
                    </span>
                </a>
            </div>
        </div>

        <!-- Quick Access Toolbar -->
        <div class="sidebar-footer mt-auto p-2">
            <div class="quick-access-toolbar">
                <button class="btn btn-sm btn-outline-light" title="Notifications" id="notificationsBtn" tabindex="0">
                    <i class="fas fa-bell"></i>
                    <span class="badge bg-danger">3</span>
                </button>
                <button class="btn btn-sm btn-outline-light" title="Messages" id="messagesBtn" tabindex="0">
                    <i class="fas fa-envelope"></i>
                    <span class="badge bg-info">7</span>
                </button>
                <button class="btn btn-sm btn-outline-light" title="Help" id="helpBtn" tabindex="0">
                    <i class="fas fa-question-circle"></i>
                </button>
            </div>
        </div>
    </nav>
</div>

<!-- Settings Modal -->
<div class="modal fade" id="sidebarSettingsModal" tabindex="-1" aria-labelledby="sidebarSettingsModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="sidebarSettingsModalLabel">Sidebar Settings</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label for="sidebarWidth" class="form-label">Sidebar Width</label>
                    <input type="range" class="form-range" id="sidebarWidth" min="220" max="350" value="280">
                    <small class="text-muted">Current: <span id="widthValue">280</span>px</small>
                </div>
                <div class="mb-3">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="compactMode">
                        <label class="form-check-label" for="compactMode">Compact Mode</label>
                    </div>
                </div>
                <div class="mb-3">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="showBadges" checked>
                        <label class="form-check-label" for="showBadges">Show Notification Badges</label>
                    </div>
                </div>
                <div class="mb-3">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="autoCollapse">
                        <label class="form-check-label" for="autoCollapse">Auto-collapse Sections</label>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" id="saveSettings">Save Settings</button>
            </div>
        </div>
    </div>
</div>

<style>
.sidebar {
    width: 280px;
    z-index: 1001;
    top: 60px;
    background: linear-gradient(180deg, var(--armis-primary) 0%, var(--armis-secondary) 100%);
    border-right: 3px solid var(--armis-gold);
    box-shadow: 3px 0 15px rgba(0,0,0,0.15);
    min-height: calc(100vh - 60px);
    backdrop-filter: blur(10px);
    display: flex;
    flex-direction: column;
}

.sidebar-header {
    background: var(--armis-dark);
    color: var(--armis-gold);
    border-bottom: 2px solid var(--armis-gold);
    position: sticky;
    top: 0;
    z-index: 10;
}

.sidebar-controls {
    display: flex;
    gap: 5px;
}

.sidebar-breadcrumb {
    border-top: 1px solid rgba(255, 215, 0, 0.2);
    padding-top: 8px;
}

.sidebar-search {
    background: rgba(0,0,0,0.1);
    border-bottom: 1px solid rgba(255, 215, 0, 0.2);
}

.sidebar-search .form-control {
    background: rgba(255,255,255,0.1);
    border: 1px solid rgba(255, 215, 0, 0.3);
    color: white;
}

.sidebar-search .form-control::placeholder {
    color: rgba(255,255,255,0.6);
}

.sidebar-search .form-control:focus {
    background: rgba(255,255,255,0.15);
    border-color: var(--armis-gold);
    box-shadow: 0 0 0 0.2rem rgba(255, 215, 0, 0.25);
    color: white;
}

.sidebar-heading {
    font-weight: 600;
    letter-spacing: 1px;
    color: rgba(255, 215, 0, 0.9);
    font-size: 0.75rem;
    cursor: pointer;
    transition: all 0.3s ease;
}

.sidebar-heading:hover {
    color: var(--armis-gold);
}

.collapsible-header {
    user-select: none;
}

.collapse-icon {
    transition: transform 0.3s ease;
}

.collapse-icon.collapsed {
    transform: rotate(-90deg);
}

.collapsible-content {
    transition: all 0.3s ease;
    overflow: hidden;
}

.collapsible-content.collapsed {
    max-height: 0;
    opacity: 0;
    padding: 0;
}

.sidebar-section {
    margin-bottom: 1rem;
}

.nav {
    flex: 1;
    display: flex;
    flex-direction: column;
}

.sidebar .nav-link {
    color: rgba(255,255,255,0.8);
    padding: 8px 12px;
    margin-bottom: 2px;
    border-radius: 8px;
    transition: all 0.3s ease;
    border-left: 3px solid transparent;
    display: flex;
    align-items: center;
    justify-content: space-between;
    position: relative;
    will-change: transform, background-color;
    transform: translateZ(0);
}

.nav-link-content {
    display: flex;
    align-items: center;
    flex: 1;
}

.sidebar .nav-link:hover {
    background: rgba(255,215,0,0.2);
    color: var(--armis-gold);
    transform: translateX(5px);
    border-left-color: var(--armis-gold);
}

.sidebar .nav-link.active {
    background: var(--armis-gold);
    color: var(--armis-dark);
    font-weight: bold;
    border-left-color: var(--armis-gold);
    box-shadow: inset 0 0 10px rgba(255,215,0,0.1);
}

.sidebar .nav-link.active:hover {
    background: var(--armis-gold);
    color: var(--armis-dark);
    transform: translateX(0);
}

.favorite-btn {
    background: none;
    border: none;
    color: rgba(255,255,255,0.4);
    padding: 0;
    width: 20px;
    height: 20px;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.3s ease;
    opacity: 0;
}

.nav-link:hover .favorite-btn {
    opacity: 1;
}

.favorite-btn:hover {
    color: var(--armis-gold);
    transform: scale(1.2);
}

.favorite-btn.favorited {
    color: var(--armis-gold);
    opacity: 1;
}

.sidebar-footer {
    border-top: 1px solid rgba(255, 215, 0, 0.2);
    background: rgba(0,0,0,0.1);
}

.quick-access-toolbar {
    display: flex;
    justify-content: space-around;
    align-items: center;
}

.quick-access-toolbar .btn {
    position: relative;
    padding: 8px;
    border-radius: 50%;
    width: 40px;
    height: 40px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.quick-access-toolbar .badge {
    position: absolute;
    top: -5px;
    right: -5px;
    font-size: 0.6rem;
    padding: 2px 5px;
}

.content-wrapper {
    margin-left: 280px;
    min-height: calc(100vh - 120px);
    padding: 20px;
    transition: margin-left 0.3s ease;
}

.content-wrapper.with-sidebar {
    margin-left: 280px;
}

/* Search Highlighting */
.search-highlight {
    background: rgba(255, 215, 0, 0.3);
    border-radius: 3px;
    padding: 2px 4px;
}

.nav-link.search-hidden {
    display: none;
}

/* Loading States */
.sidebar-loading::after {
    content: '';
    position: absolute;
    top: 50%;
    left: 50%;
    width: 20px;
    height: 20px;
    margin: -10px 0 0 -10px;
    border: 2px solid rgba(255, 215, 0, 0.3);
    border-radius: 50%;
    border-top-color: var(--armis-gold);
    animation: spin 1s ease-in-out infinite;
}

@keyframes spin {
    to { transform: rotate(360deg); }
}

/* Enhanced Badges */
.badge {
    font-size: 0.7rem;
    padding: 3px 6px;
    border-radius: 12px;
}

/* Tooltip Styling */
.tooltip-inner {
    background-color: var(--armis-dark);
    color: var(--armis-gold);
    border: 1px solid var(--armis-gold);
}

.tooltip .arrow::before {
    border-color: var(--armis-gold);
}

/* Scrollbar styling for sidebar - Military theme */
.sidebar::-webkit-scrollbar {
    width: 6px;
}

.sidebar::-webkit-scrollbar-track {
    background: rgba(0,0,0,0.1);
}

.sidebar::-webkit-scrollbar-thumb {
    background: var(--armis-gold);
    border-radius: 3px;
}

.sidebar::-webkit-scrollbar-thumb:hover {
    background: #ffed4e;
}

/* Ensure footer is always below sidebar */
.footer {
    margin-left: 280px;
    transition: margin-left 0.3s ease;
}

/* Compact Mode */
.sidebar.compact {
    width: 220px;
}

.sidebar.compact .sidebar-heading {
    font-size: 0.65rem;
}

.sidebar.compact .nav-link {
    padding: 6px 8px;
    font-size: 0.9rem;
}

.sidebar.compact + .content-wrapper,
.sidebar.compact ~ .footer {
    margin-left: 220px;
}

/* Mobile responsiveness - sidebar remains static but responsive */
@media (max-width: 768px) {
    .sidebar {
        width: 250px;
    }
    
    .content-wrapper,
    .content-wrapper.with-sidebar {
        margin-left: 250px;
        padding: 15px;
    }
    
    .footer {
        margin-left: 250px;
    }

    .sidebar-controls {
        flex-direction: column;
        gap: 2px;
    }

    .quick-access-toolbar .btn {
        width: 35px;
        height: 35px;
        padding: 6px;
    }
}

@media (max-width: 576px) {
    .sidebar {
        width: 220px;
    }
    
    .content-wrapper,
    .content-wrapper.with-sidebar {
        margin-left: 220px;
        padding: 10px;
    }
    
    .footer {
        margin-left: 220px;
    }
    
    .sidebar-header h5 {
        font-size: 0.9rem;
    }
    
    .sidebar .nav-link {
        padding: 6px 8px;
        font-size: 0.9rem;
    }

    .sidebar-breadcrumb {
        font-size: 0.75rem;
    }
}

/* Focus indicators for accessibility */
.sidebar .nav-link:focus,
.sidebar button:focus {
    outline: 2px solid var(--armis-gold);
    outline-offset: 2px;
}

/* Animation for notifications */
@keyframes pulse {
    0% { transform: scale(1); }
    50% { transform: scale(1.1); }
    100% { transform: scale(1); }
}

.quick-access-toolbar .badge {
    animation: pulse 2s infinite;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Initialize sidebar functionality
    initializeSidebar();
    
    function initializeSidebar() {
        setupSearch();
        setupCollapsibleSections();
        setupFavorites();
        setupSettings();
        setupKeyboardNavigation();
        setupNotifications();
        loadUserPreferences();
    }

    // Search functionality
    function setupSearch() {
        const searchBtn = document.getElementById('sidebarSearch');
        const searchBox = document.getElementById('sidebarSearchBox');
        const searchInput = document.getElementById('navSearchInput');
        const clearBtn = document.getElementById('clearSearch');
        const navLinks = document.querySelectorAll('.nav-link[data-search-terms]');

        searchBtn.addEventListener('click', function() {
            const isVisible = searchBox.style.display !== 'none';
            searchBox.style.display = isVisible ? 'none' : 'block';
            if (!isVisible) {
                searchInput.focus();
            } else {
                clearSearch();
            }
        });

        searchInput.addEventListener('input', function() {
            const query = this.value.toLowerCase();
            filterNavigation(query, navLinks);
        });

        clearBtn.addEventListener('click', function() {
            searchInput.value = '';
            clearSearch();
        });

        function filterNavigation(query, links) {
            links.forEach(link => {
                const searchTerms = link.getAttribute('data-search-terms');
                const title = link.textContent.toLowerCase();
                
                if (!query || searchTerms.includes(query) || title.includes(query)) {
                    link.classList.remove('search-hidden');
                    highlightSearchTerms(link, query);
                } else {
                    link.classList.add('search-hidden');
                }
            });
        }

        function highlightSearchTerms(element, query) {
            if (!query) return;
            
            const textNodes = getTextNodes(element.querySelector('.nav-link-content'));
            textNodes.forEach(node => {
                const text = node.textContent;
                const regex = new RegExp(`(${query})`, 'gi');
                if (regex.test(text)) {
                    const highlightedText = text.replace(regex, '<span class="search-highlight">$1</span>');
                    const wrapper = document.createElement('span');
                    wrapper.innerHTML = highlightedText;
                    node.parentNode.replaceChild(wrapper, node);
                }
            });
        }

        function clearSearch() {
            navLinks.forEach(link => {
                link.classList.remove('search-hidden');
                // Remove highlighting
                const highlights = link.querySelectorAll('.search-highlight');
                highlights.forEach(highlight => {
                    highlight.outerHTML = highlight.innerHTML;
                });
            });
        }

        function getTextNodes(element) {
            const textNodes = [];
            const walker = document.createTreeWalker(
                element,
                NodeFilter.SHOW_TEXT,
                null,
                false
            );
            
            let node;
            while (node = walker.nextNode()) {
                if (node.textContent.trim()) {
                    textNodes.push(node);
                }
            }
            return textNodes;
        }
    }

    // Collapsible sections
    function setupCollapsibleSections() {
        const headers = document.querySelectorAll('.collapsible-header');
        
        headers.forEach(header => {
            header.addEventListener('click', function() {
                const targetId = this.getAttribute('data-target');
                const content = document.getElementById(targetId);
                const icon = this.querySelector('.collapse-icon');
                
                if (content.classList.contains('collapsed')) {
                    content.classList.remove('collapsed');
                    icon.classList.remove('collapsed');
                    content.style.maxHeight = content.scrollHeight + 'px';
                } else {
                    content.classList.add('collapsed');
                    icon.classList.add('collapsed');
                    content.style.maxHeight = '0px';
                }
                
                // Save state
                localStorage.setItem(`sidebar-${targetId}-collapsed`, content.classList.contains('collapsed'));
            });
        });

        // Restore saved states
        headers.forEach(header => {
            const targetId = header.getAttribute('data-target');
            const content = document.getElementById(targetId);
            const icon = header.querySelector('.collapse-icon');
            const isCollapsed = localStorage.getItem(`sidebar-${targetId}-collapsed`) === 'true';
            
            if (isCollapsed) {
                content.classList.add('collapsed');
                icon.classList.add('collapsed');
                content.style.maxHeight = '0px';
            }
        });
    }

    // Favorites system
    function setupFavorites() {
        const favoriteButtons = document.querySelectorAll('.favorite-btn');
        const favoritesList = document.getElementById('favoritesList');
        
        favoriteButtons.forEach(btn => {
            btn.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                
                const url = this.getAttribute('data-url');
                const title = this.getAttribute('data-title');
                const icon = this.getAttribute('data-icon');
                
                toggleFavorite(url, title, icon, this);
            });
        });

        function toggleFavorite(url, title, icon, button) {
            let favorites = JSON.parse(localStorage.getItem('sidebar-favorites') || '[]');
            const existing = favorites.findIndex(fav => fav.url === url);
            
            if (existing >= 0) {
                favorites.splice(existing, 1);
                button.classList.remove('favorited');
            } else {
                favorites.push({ url, title, icon });
                button.classList.add('favorited');
            }
            
            localStorage.setItem('sidebar-favorites', JSON.stringify(favorites));
            updateFavoritesList();
        }

        function updateFavoritesList() {
            const favorites = JSON.parse(localStorage.getItem('sidebar-favorites') || '[]');
            
            if (favorites.length === 0) {
                favoritesList.innerHTML = '<p class="text-muted small">No favorites yet</p>';
                return;
            }
            
            favoritesList.innerHTML = favorites.map(fav => `
                <a class="nav-link" href="${fav.url}" role="menuitem" tabindex="0">
                    <span class="nav-link-content">
                        <i class="fas fa-${fav.icon} me-2" aria-hidden="true"></i>
                        ${fav.title}
                    </span>
                    <button class="remove-favorite" data-url="${fav.url}" title="Remove from favorites">
                        <i class="fas fa-times"></i>
                    </button>
                </a>
            `).join('');
            
            // Add remove functionality
            favoritesList.querySelectorAll('.remove-favorite').forEach(btn => {
                btn.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    
                    const url = this.getAttribute('data-url');
                    let favorites = JSON.parse(localStorage.getItem('sidebar-favorites') || '[]');
                    favorites = favorites.filter(fav => fav.url !== url);
                    localStorage.setItem('sidebar-favorites', JSON.stringify(favorites));
                    
                    // Update button state
                    const originalBtn = document.querySelector(`[data-url="${url}"].favorite-btn`);
                    if (originalBtn) originalBtn.classList.remove('favorited');
                    
                    updateFavoritesList();
                });
            });
        }

        // Mark existing favorites
        const favorites = JSON.parse(localStorage.getItem('sidebar-favorites') || '[]');
        favorites.forEach(fav => {
            const btn = document.querySelector(`[data-url="${fav.url}"].favorite-btn`);
            if (btn) btn.classList.add('favorited');
        });
        
        updateFavoritesList();
    }

    // Settings functionality
    function setupSettings() {
        const settingsBtn = document.getElementById('sidebarSettings');
        const modal = new bootstrap.Modal(document.getElementById('sidebarSettingsModal'));
        const widthSlider = document.getElementById('sidebarWidth');
        const widthValue = document.getElementById('widthValue');
        const compactMode = document.getElementById('compactMode');
        const showBadges = document.getElementById('showBadges');
        const autoCollapse = document.getElementById('autoCollapse');
        const saveBtn = document.getElementById('saveSettings');
        
        settingsBtn.addEventListener('click', () => modal.show());
        
        widthSlider.addEventListener('input', function() {
            widthValue.textContent = this.value;
            updateSidebarWidth(this.value);
        });

        compactMode.addEventListener('change', function() {
            document.getElementById('sidebar').classList.toggle('compact', this.checked);
        });

        showBadges.addEventListener('change', function() {
            const badges = document.querySelectorAll('.sidebar .badge');
            badges.forEach(badge => {
                badge.style.display = this.checked ? '' : 'none';
            });
        });

        saveBtn.addEventListener('click', function() {
            const settings = {
                width: widthSlider.value,
                compact: compactMode.checked,
                showBadges: showBadges.checked,
                autoCollapse: autoCollapse.checked
            };
            
            localStorage.setItem('sidebar-settings', JSON.stringify(settings));
            modal.hide();
        });

        function updateSidebarWidth(width) {
            const sidebar = document.getElementById('sidebar');
            const contentWrapper = document.querySelector('.content-wrapper');
            const footer = document.querySelector('.footer');
            
            sidebar.style.width = width + 'px';
            if (contentWrapper) contentWrapper.style.marginLeft = width + 'px';
            if (footer) footer.style.marginLeft = width + 'px';
        }
    }

    // Keyboard navigation
    function setupKeyboardNavigation() {
        const navLinks = document.querySelectorAll('.sidebar .nav-link, .sidebar button');
        let currentIndex = -1;

        document.addEventListener('keydown', function(e) {
            if (!document.getElementById('sidebar').contains(document.activeElement)) return;

            switch(e.key) {
                case 'ArrowDown':
                    e.preventDefault();
                    navigateToNext();
                    break;
                case 'ArrowUp':
                    e.preventDefault();
                    navigateToPrevious();
                    break;
                case 'Enter':
                case ' ':
                    e.preventDefault();
                    if (document.activeElement) {
                        document.activeElement.click();
                    }
                    break;
                case 'Escape':
                    document.getElementById('sidebarSearchBox').style.display = 'none';
                    break;
            }
        });

        function navigateToNext() {
            const visibleItems = Array.from(navLinks).filter(item => 
                !item.closest('.search-hidden') && 
                !item.closest('.collapsed') &&
                item.offsetParent !== null
            );
            
            currentIndex = (currentIndex + 1) % visibleItems.length;
            visibleItems[currentIndex].focus();
        }

        function navigateToPrevious() {
            const visibleItems = Array.from(navLinks).filter(item => 
                !item.closest('.search-hidden') && 
                !item.closest('.collapsed') &&
                item.offsetParent !== null
            );
            
            currentIndex = currentIndex <= 0 ? visibleItems.length - 1 : currentIndex - 1;
            visibleItems[currentIndex].focus();
        }
    }

    // Notifications and quick access
    function setupNotifications() {
        const notificationsBtn = document.getElementById('notificationsBtn');
        const messagesBtn = document.getElementById('messagesBtn');
        const helpBtn = document.getElementById('helpBtn');

        notificationsBtn.addEventListener('click', function() {
            // Simulate notification panel
            alert('Notifications:\n• System maintenance scheduled\n• New training available\n• Budget approval pending');
        });

        messagesBtn.addEventListener('click', function() {
            // Simulate messages panel  
            alert('Messages:\n• Commander briefing at 0800\n• Equipment inspection reminder\n• Training completion certificate ready');
        });

        helpBtn.addEventListener('click', function() {
            // Simulate help system
            alert('ARMIS Help:\n• Press F1 for keyboard shortcuts\n• Use Ctrl+/ to search\n• Right-click items for options');
        });
    }

    // Load user preferences
    function loadUserPreferences() {
        const settings = JSON.parse(localStorage.getItem('sidebar-settings') || '{}');
        
        if (settings.width) {
            document.getElementById('sidebarWidth').value = settings.width;
            document.getElementById('widthValue').textContent = settings.width;
            updateSidebarWidth(settings.width);
        }
        
        if (settings.compact) {
            document.getElementById('compactMode').checked = true;
            document.getElementById('sidebar').classList.add('compact');
        }
        
        if (settings.showBadges === false) {
            document.getElementById('showBadges').checked = false;
            const badges = document.querySelectorAll('.sidebar .badge');
            badges.forEach(badge => badge.style.display = 'none');
        }

        function updateSidebarWidth(width) {
            const sidebar = document.getElementById('sidebar');
            const contentWrapper = document.querySelector('.content-wrapper');
            const footer = document.querySelector('.footer');
            
            sidebar.style.width = width + 'px';
            if (contentWrapper) contentWrapper.style.marginLeft = width + 'px';
            if (footer) footer.style.marginLeft = width + 'px';
        }
    }

    // Context menu functionality
    document.addEventListener('contextmenu', function(e) {
        if (e.target.closest('.sidebar .nav-link')) {
            e.preventDefault();
            // Could implement custom context menu here
        }
    });

    // Tooltip initialization
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[title]'));
    tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl, {
            placement: 'right',
            delay: { show: 500, hide: 100 }
        });
    });
});
</script>
