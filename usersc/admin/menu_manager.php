<?php
// ARMIS Menu Manager Admin UI (Production-Grade, All Features)
// Place at: usersc/admin/menu_manager.php

require_once '../../users/init.php';
require_once $abs_us_root . $us_url_root . 'users/includes/template/prep.php';

// ---- Core logic and helpers
require_once 'menu_manager_core.php';
// --- Optional features (uncomment to enable)
// require_once 'menu_manager_multilang.php'; // Multi-language (commented out as requested)
require_once 'menu_manager_bulk.php';
require_once 'menu_manager_preview.php';
require_once 'menu_manager_import_export.php';
require_once 'menu_manager_versioning.php';
require_once 'menu_manager_schedule.php';
require_once 'menu_manager_iconpicker.php';
require_once 'menu_manager_search.php';
require_once 'menu_manager_bookmarks.php';
require_once 'menu_manager_api.php';
require_once 'menu_manager_notifications.php';
require_once 'menu_manager_customfields.php';

render_menu_manager_page(); // Main UI logic, see core file
?>