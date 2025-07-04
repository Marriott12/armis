<?php
// Per-menu scheduling
// Add columns: visible_from DATETIME, visible_to DATETIME to menus table
// Rendering: only show menu if now >= visible_from and now <= visible_to (if set)
// UI: see menu_manager_core_ui.php for date pickers
?>