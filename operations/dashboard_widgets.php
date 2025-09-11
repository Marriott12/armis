<?php
// Dashboard widgets for operations module
require_once dirname(__DIR__) . '/shared/database_connection.php';

function getDashboardStats() {
    $db = getDbConnection();
    $stats = [];
    $stats['active_missions'] = $db->query("SELECT COUNT(*) FROM operations_missions WHERE status = 'active'")->fetchColumn();
    $stats['active_deployments'] = $db->query("SELECT COUNT(*) FROM operations_deployments WHERE status = 'active'")->fetchColumn();
    $stats['available_resources'] = $db->query("SELECT COUNT(*) FROM operations_resources WHERE maintenance_status = 'ok'")->fetchColumn();
    $stats['priority_alerts'] = $db->query("SELECT COUNT(*) FROM operations_notifications WHERE type = 'alert' AND is_read = 0")->fetchColumn();
    $stats['field_units'] = $db->query("SELECT COUNT(*) FROM staff WHERE role = 'field'")->fetchColumn();
    return $stats;
}
?>
