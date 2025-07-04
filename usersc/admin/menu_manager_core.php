<?php
// Core logic for ARMIS menu manager (RBAC, drag-drop, logging, undo/redo, caching, etc.)

// Strict RBAC: Only allow users with ADMIN permission (permission_id = 1)
require_once $abs_us_root . $us_url_root . 'users/init.php';

if (!isset($user) || !$user->isLoggedIn()) {
    die("Access denied.");
}

$db = DB::getInstance();
// Check if user has permission_id = 1 (admin permission, default in UserSpice)
$adminPermissionQ = $db->query("SELECT id FROM user_permission_matches WHERE user_id = ? AND permission_id = 1", [$user->data()->id]);
if ($adminPermissionQ->count() == 0) {
    die("Access denied.");
}

// Logging helper for your logs table
function armis_log($user_id, $type, $note = '', $metadata = null) {
    global $db;
    $db->insert('logs', [
        'user_id' => (int)$user_id,
        'logdate' => date('Y-m-d H:i:s'),
        'logtype' => htmlspecialchars($type, ENT_QUOTES, 'UTF-8'),
        'lognote' => htmlspecialchars($note, ENT_QUOTES, 'UTF-8'),
        'ip' => $_SERVER['REMOTE_ADDR'],
        'metadata' => $metadata ? json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null
    ]);
}

// Undo/redo stack (session-based, for UI)
if (!isset($_SESSION['armis_menu_undo']) || !is_array($_SESSION['armis_menu_undo'])) $_SESSION['armis_menu_undo'] = [];
if (!isset($_SESSION['armis_menu_redo']) || !is_array($_SESSION['armis_menu_redo'])) $_SESSION['armis_menu_redo'] = [];

// Caching helper (for demonstration; production should use persistent cache with proper permissions)
function armis_menu_cache_set($key, $value, $ttl = 300) {
    $cacheDir = sys_get_temp_dir();
    $file = $cacheDir . DIRECTORY_SEPARATOR . "menu_cache_" . md5($key);
    $data = ['data'=>$value,'ttl'=>time()+$ttl];
    // Use file locking for safety
    $fp = fopen($file, 'c+');
    if ($fp) {
        if (flock($fp, LOCK_EX)) {
            ftruncate($fp, 0);
            fwrite($fp, serialize($data));
            fflush($fp);
            flock($fp, LOCK_UN);
        }
        fclose($fp);
    }
}
function armis_menu_cache_get($key) {
    $cacheDir = sys_get_temp_dir();
    $file = $cacheDir . DIRECTORY_SEPARATOR . "menu_cache_" . md5($key);
    if (!file_exists($file)) return false;
    $fp = fopen($file, 'r');
    if (!$fp) return false;
    $data = false;
    if (flock($fp, LOCK_SH)) {
        $contents = stream_get_contents($fp);
        $d = @unserialize($contents);
        if (is_array($d) && isset($d['ttl'], $d['data']) && time() < $d['ttl']) {
            $data = $d['data'];
        }
        flock($fp, LOCK_UN);
    }
    fclose($fp);
    return $data;
}

// Core menu CRUD, RBAC, drag-drop, soft delete, restore, search/filter, etc. handled in feature files

function render_menu_manager_page() {
    global $db;
    // For brevity, most UI rendering is handled in the feature modules included from menu_manager.php
    // This acts as the entry point and renderer for the modular system.
    include 'menu_manager_core_ui.php';
}
?>