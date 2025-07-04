<?php
// ARMIS User Navigation Bar with RBAC, Bookmarks, Search, Scheduling, Placement, Caching,
// Collapsible Sidebar, and Scroll-to-Top Button

$brandName = 'ARMIS';
$brandLogo = $us_url_root . 'users/images/logo.png';
$brandLink = $us_url_root;
$brandClass = 'armis-brand';

$db = DB::getInstance();

/**
 * Get user's permission ids (roles/RBAC)
 */
function getUserPermissionIds($user) {
    global $db;
    if (!is_object($user) || !$user->isLoggedIn()) return [];
    $q = $db->query("SELECT permission_id FROM user_permission_matches WHERE user_id = ?", [$user->data()->id]);
    $ids = [];
    foreach($q->results(true) as $r) {
        $ids[] = is_array($r) ? $r['permission_id'] : $r->permission_id;
    }
    return $ids;
}

/**
 * Get menu bookmarks for user
 */
function getUserBookmarks($user) {
    global $db;
    if (!is_object($user) || !$user->isLoggedIn()) return [];
    $q = $db->query("SELECT menu_id FROM menu_bookmarks WHERE user_id = ?", [$user->data()->id]);
    return array_map(function($a){ return is_object($a) ? $a->menu_id : $a['menu_id']; }, $q->results());
}

/**
 * Get menu items for current user, with RBAC, scheduling, placement, and caching
 */
function getUserMenus($permission_ids, $logged_in, $placement = 'top') {
    global $db, $user;
    $cacheKey = "menus_{$placement}_". ($user ? $user->data()->id : 0);
    if($menus = armis_menu_cache_get($cacheKey)) return $menus;

    $now = date('Y-m-d H:i:s');
    $all_menus = $db->query("SELECT * FROM menus WHERE menu_title = 'main' AND deleted=0 AND (placement=? OR placement IS NULL OR placement='') ORDER BY parent, display_order", [$placement])->results(true);

    // Scheduling filter and RBAC
    $perms_map = [];
    if ($permission_ids) {
        $perm_matches = $db->query("SELECT page FROM permission_page_matches WHERE permission_id IN (" . implode(',', $permission_ids) . ")")->results(true);
        foreach ($perm_matches as $m) $perms_map[$m['page']] = true;
    }
    $menuTree = [];
    foreach ($all_menus as $row) {
        // Scheduling
        if (isset($row['visible_from']) && $row['visible_from'] && $row['visible_from'] > $now) continue;
        if (isset($row['visible_to']) && $row['visible_to'] && $row['visible_to'] < $now) continue;
        // Placement
        if (isset($row['placement']) && $row['placement'] && $row['placement'] !== $placement) continue;
        // If menu is for logged-in users only and user is not logged in, skip
        if ($row['logged_in'] && !$logged_in) continue;
        // If menu is for guests and user is logged in, skip
        if (!$row['logged_in'] && $logged_in) continue;
        // If menu is RBAC-protected, check permission_page_matches
        $has_perm = isset($perms_map[$row['id']]);
        if ($row['logged_in'] && !$has_perm) continue;
        $menuTree[] = $row;
    }
    $tree = buildMenuTree($menuTree);
    armis_menu_cache_set($cacheKey, $tree, 180);
    return $tree;
}

/**
 * Recursively build a tree from flat menu items
 */
function buildMenuTree($flat, $parent = 0) {
    $branch = [];
    foreach ($flat as $item) {
        if ((int)$item['parent'] === (int)$parent) {
            $children = buildMenuTree($flat, $item['id']);
            if ($children) $item['children'] = $children;
            $branch[] = $item;
        }
    }
    return $branch;
}

/**
 * Render the menu as Bootstrap 5 nav items (supports dropdowns)
 */
function renderMenu($menu, $activePath, $bookmarks = [], $showBookmarks = false) {
    foreach ($menu as $item) {
        $isActive = ($activePath === $item['link']) ||
            (isset($item['children']) && in_array($activePath, array_column($item['children'], 'link')));
        $icon = empty($item['icon_class']) ? '' : '<i class="' . htmlspecialchars($item['icon_class']) . '"></i> ';
        $bookmarkStar = $showBookmarks ? "" : (in_array($item['id'], $bookmarks) ? '<span title="Bookmarked" style="color:gold;">&#9733;</span>' : '');
        if (!empty($item['children'])) {
            // Dropdown
            echo '<li class="nav-item dropdown'.($isActive?' active':'').'">';
            echo '<a class="nav-link dropdown-toggle '.($isActive?'active':'').'" href="#" id="menu'.$item['id'].'" role="button" data-bs-toggle="dropdown" aria-expanded="false">';
            echo $icon . htmlspecialchars($item['label']) . $bookmarkStar;
            echo '</a><ul class="dropdown-menu" aria-labelledby="menu'.$item['id'].'">';
            foreach ($item['children'] as $child) {
                $activeClass = ($activePath === $child['link']) ? 'active' : '';
                $iconC = empty($child['icon_class']) ? '' : '<i class="'.htmlspecialchars($child['icon_class']).'"></i> ';
                $bmStar = in_array($child['id'], $bookmarks) && !$showBookmarks ? '<span style="color:gold;">&#9733;</span>' : '';
                echo '<li><a class="dropdown-item '.$activeClass.'" href="'.htmlspecialchars($child['link']).'">'.$iconC . htmlspecialchars($child['label']).$bmStar.'</a></li>';
            }
            echo '</ul></li>';
        } else {
            echo '<li class="nav-item'.($isActive?' active':'').'">';
            echo '<a class="nav-link '.($isActive?'active':'').'" href="'.htmlspecialchars($item['link']).'">'.$icon . htmlspecialchars($item['label']).$bookmarkStar.'</a>';
            echo '</li>';
        }
    }
}

/**
 * Render the sidebar menu (vertical, collapsible)
 */
function renderSidebarMenu($menu, $activePath, $bookmarks = [], $level=0) {
    echo '<ul class="nav flex-column'.($level ? ' ms-2' : '').'">';
    foreach ($menu as $item) {
        $isActive = ($activePath === $item['link']) ||
            (isset($item['children']) && in_array($activePath, array_column($item['children'], 'link')));
        $icon = empty($item['icon_class']) ? '' : '<i class="' . htmlspecialchars($item['icon_class']) . '"></i> ';
        $bmStar = in_array($item['id'], $bookmarks) ? '<span style="color:gold;">&#9733;</span>' : '';
        if (!empty($item['children'])) {
            echo '<li class="nav-item">';
            echo '<a class="nav-link d-flex justify-content-between align-items-center '.($isActive?'active':'').'" data-bs-toggle="collapse" href="#sbmenu'.$item['id'].'" role="button" aria-expanded="false" aria-controls="sbmenu'.$item['id'].'">'.$icon . htmlspecialchars($item['label']).$bmStar.' <span class="fa fa-caret-down"></span></a>';
            echo '<div class="collapse'.($isActive?' show':'').'" id="sbmenu'.$item['id'].'">';
            renderSidebarMenu($item['children'], $activePath, $bookmarks, $level+1);
            echo '</div></li>';
        } else {
            echo '<li class="nav-item">';
            echo '<a class="nav-link '.($isActive?'active':'').'" href="'.htmlspecialchars($item['link']).'">'.$icon . htmlspecialchars($item['label']).$bmStar.'</a>';
            echo '</li>';
        }
    }
    echo '</ul>';
}

$userPermissionIds = getUserPermissionIds($user ?? null);
$userBookmarks = getUserBookmarks($user ?? null);
$menus = getUserMenus($userPermissionIds, $user && $user->isLoggedIn(), 'top');
$sidebarMenus = getUserMenus($userPermissionIds, $user && $user->isLoggedIn(), 'sidebar');
$bookmarkedMenus = [];
if(!empty($userBookmarks)) {
    $bmMenus = $db->query("SELECT * FROM menus WHERE id IN (" . implode(',', $userBookmarks) . ") AND deleted=0")->results(true);
    $bookmarkedMenus = buildMenuTree($bmMenus);
}
$currentPath = explode('?', $_SERVER['REQUEST_URI'])[0];
?>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="<?=$us_url_root?>usersc/css/armis_custom.css">
<style>
/* Sidebar Styles */
#armisSidebar {
    width: 240px;
    min-height: 100vh;
    background: #2e3542;
    color: #f4f4f4;
    position: fixed;
    top: 0;
    left: 0;
    z-index: 1050;
    transition: margin-left .2s;
    padding-top: 56px;
    overflow-y: auto;
}
#armisSidebar.collapsed {
    margin-left: -240px;
}
#sidebarToggle {
    position: fixed;
    top: 10px;
    left: 10px;
    z-index: 1100;
}
#armisSidebar .nav-link,
#armisSidebar .nav-link:visited {
    color: #f4f4f4;
}
#armisSidebar .nav-link.active, #armisSidebar .nav-link:focus {
    background: #44506b;
    color: #FFD700;
}
#mainContent {
    margin-left: 240px;
    transition: margin-left .2s;
}
#mainContent.sidebar-collapsed {
    margin-left: 0;
}
@media (max-width: 991px) {
    #armisSidebar {
        margin-left: -240px;
    }
    #armisSidebar.show {
        margin-left: 0;
    }
    #mainContent {
        margin-left: 0;
    }
}
#scrollToTopBtn {
    position: fixed;
    bottom: 32px;
    right: 32px;
    z-index: 1110;
    display: none;
    background: #355E3B;
    color: #FFD700;
    border: none;
    border-radius: 50%;
    width: 48px;
    height: 48px;
    font-size: 2em;
    align-items: center;
    justify-content: center;
}
</style>
<!-- Sidebar -->
<button class="btn btn-dark d-lg-none" id="sidebarToggle" title="Toggle sidebar"><i class="fa fa-bars"></i></button>
<div id="armisSidebar" class="d-none d-lg-block">
    <div class="p-3" style="border-bottom:1px solid #444;">
        <a class="navbar-brand <?=$brandClass?>" href="<?=$brandLink?>" style="color:#FFD700">
            <img src="<?=$brandLogo?>" alt="ARMIS Logo" style="height:32px; margin-right:10px; vertical-align:middle;">
            <?=htmlspecialchars($brandName)?>
        </a>
    </div>
    <!-- Sidebar Bookmarks -->
    <?php if($user && $user->isLoggedIn() && count($bookmarkedMenus)): ?>
    <div class="pt-2 pb-1 px-3" style="font-weight:bold;">Bookmarks</div>
    <?php renderSidebarMenu($bookmarkedMenus, $currentPath, $userBookmarks); ?>
    <?php endif; ?>
    <!-- Main Sidebar Menus -->
    <div class="pt-2 pb-1 px-3" style="font-weight:bold;">Menu</div>
    <?php renderSidebarMenu($sidebarMenus, $currentPath, $userBookmarks); ?>
</div>
<!-- Top Navbar -->
<nav class="navbar navbar-expand-lg navbar-dark armis-navbar shadow-sm py-1" style="margin-left:240px;" id="mainNavbar">
  <div class="container-fluid">
    <a class="navbar-brand d-lg-none <?=$brandClass?>" href="<?=$brandLink?>">
      <img src="<?=$brandLogo?>" alt="ARMIS Logo" style="height:32px; margin-right:10px; vertical-align:middle;">
      <?=htmlspecialchars($brandName)?>
    </a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarARMIS" aria-controls="navbarARMIS" aria-expanded="false" aria-label="Toggle navigation">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="navbarARMIS">
      <!-- Bookmarks Section -->
      <?php if($user && $user->isLoggedIn() && count($bookmarkedMenus)): ?>
      <ul class="navbar-nav me-2">
        <li class="nav-item dropdown">
          <a class="nav-link dropdown-toggle" href="#" id="bmDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
            <i class="fa fa-star"></i> Bookmarks
          </a>
          <ul class="dropdown-menu" aria-labelledby="bmDropdown">
            <?php renderMenu($bookmarkedMenus, $currentPath, $userBookmarks, true); ?>
          </ul>
        </li>
      </ul>
      <?php endif; ?>
      <!-- Main Menus -->
      <ul class="navbar-nav me-auto mb-2 mb-lg-0" id="mainMenuList">
        <?php renderMenu($menus, $currentPath, $userBookmarks); ?>
      </ul>
      <!-- Menu Search for users -->
      <form class="d-flex me-2" id="menuSearchForm" autocomplete="off" onsubmit="return false;">
        <input class="form-control" type="search" id="menuSearchInput" placeholder="Search menu..." aria-label="Search">
      </form>
      <ul class="navbar-nav ms-auto mb-2 mb-lg-0">
        <?php if($user && $user->isLoggedIn()): ?>
          <li class="nav-item dropdown">
            <a class="nav-link dropdown-toggle" href="#" id="armisUserMenu" role="button" data-bs-toggle="dropdown" aria-expanded="false">
              <i class="fa fa-user"></i> <?=htmlspecialchars($user->data()->fname ?? 'Account')?>
            </a>
            <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="armisUserMenu">
              <li><a class="dropdown-item" href="<?=$us_url_root?>users/account.php"><i class="fa fa-cog"></i> My Account</a></li>
              <li><a class="dropdown-item" href="<?=$us_url_root?>users/logout.php"><i class="fa fa-sign-out"></i> Logout</a></li>
            </ul>
          </li>
        <?php else: ?>
          <li class="nav-item"><a class="nav-link" href="<?=$us_url_root?>users/login.php"><i class="fa fa-sign-in"></i> Login</a></li>
        <?php endif; ?>
      </ul>
    </div>
  </div>
</nav>
<!-- Main content wrapper (use this in your main page layout) -->
<script>
document.addEventListener('DOMContentLoaded', function(){
    // Sidebar toggle
    var sidebar = document.getElementById('armisSidebar');
    var mainContent = document.getElementById('mainContent');
    var btn = document.getElementById('sidebarToggle');
    btn.addEventListener('click', function(){
        sidebar.classList.toggle('collapsed');
        document.getElementById('mainNavbar').style.marginLeft = sidebar.classList.contains('collapsed') ? '0' : '240px';
        if(mainContent) mainContent.classList.toggle('sidebar-collapsed');
    });
    // Responsive: show/hide sidebar on screen resize
    function adaptSidebar() {
        if(window.innerWidth < 992) {
            sidebar.classList.add('collapsed');
            document.getElementById('mainNavbar').style.marginLeft = '0';
            if(mainContent) mainContent.classList.add('sidebar-collapsed');
        } else {
            sidebar.classList.remove('collapsed');
            document.getElementById('mainNavbar').style.marginLeft = '240px';
            if(mainContent) mainContent.classList.remove('sidebar-collapsed');
        }
    }
    window.addEventListener('resize', adaptSidebar);
    adaptSidebar();

    // Scroll-to-top button
    var scrollBtn = document.getElementById('scrollToTopBtn');
    window.addEventListener('scroll', function() {
        if (window.scrollY > 200) {
            scrollBtn.style.display = 'flex';
        } else {
            scrollBtn.style.display = 'none';
        }
    });
    scrollBtn.addEventListener('click', function() {
        window.scrollTo({top: 0, behavior: 'smooth'});
    });
});

// Menu search logic
const menuItems = <?php
  $flatMenus = [];
  $addFlat = function($arr) use (&$addFlat, &$flatMenus) {
    foreach($arr as $item) {
      $flatMenus[] = [
        'id'=>$item['id'],
        'label'=>$item['label'],
        'link'=>$item['link'],
        'icon_class'=>$item['icon_class'] ?? '',
      ];
      if (!empty($item['children'])) $addFlat($item['children']);
    }
  };
  $addFlat($menus);
  echo json_encode($flatMenus);
?>;
const searchInput = document.getElementById('menuSearchInput');
const searchResults = document.createElement('div');
searchResults.id = 'menuSearchResults';
searchResults.style.display = 'none';
searchResults.style.position = 'absolute';
searchResults.style.zIndex = 9999;
searchResults.style.background = '#fff';
searchResults.style.border = '1px solid #ddd';
searchResults.style.minWidth = '250px';
document.body.appendChild(searchResults);

searchInput.oninput = function() {
  let q = this.value.trim().toLowerCase();
  if(!q) { searchResults.style.display='none'; return;}
  let matches = menuItems.filter(m=>m.label.toLowerCase().includes(q));
  let html = matches.length ? matches.map(m=>`<div style="padding:6px 12px;cursor:pointer;" onclick="window.location.href='${m.link}'"><i class="${m.icon_class}"></i> ${m.label}</div>`).join('') : '<div style="padding:6px 12px;">No matches.</div>';
  searchResults.innerHTML = html;
  let rect = searchInput.getBoundingClientRect();
  searchResults.style.display = 'block';
  searchResults.style.top = (rect.bottom + window.scrollY) + 'px';
  searchResults.style.left = (rect.left + window.scrollX) + 'px';
  searchResults.style.width = rect.width+'px';
};
document.body.addEventListener('click',()=>{searchResults.style.display='none';});
searchInput.addEventListener('focus',()=>{if(searchInput.value)searchInput.oninput();});

// Bookmark add/remove (AJAX)
document.querySelectorAll('.bookmark-btn').forEach(btn=>{
  btn.onclick = function(e){
    e.preventDefault();
    let menu_id = this.dataset.menuId;
    fetch('', {
      method:'POST',
      headers:{'Content-Type':'application/x-www-form-urlencoded'},
      body:'bookmark_menu_id='+encodeURIComponent(menu_id)
    }).then(r=>r.text()).then(msg=>{
      location.reload();
    });
  }
});
</script>
<!-- Scroll to top button -->
<button id="scrollToTopBtn" title="Scroll to top" class="d-flex align-items-center justify-content-center"><i class="fa fa-arrow-up"></i></button>