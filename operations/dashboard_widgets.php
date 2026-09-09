<?php
// Dashboard widgets for operations module
require_once dirname(__DIR__) . '/shared/database_connection.php';

function getDashboardStats() {
    $db = getDbConnection();
    $stats = [];
    $stats['active_missions'] = $db->query("SELECT COUNT(*) FROM operations_missions WHERE status = 'active'")->fetchColumn();
    $stats['active_deployments'] = $db->query("SELECT COUNT(*) FROM operations_deployments WHERE status = 'active'")->fetchColumn();
    $stats['available_resources'] = $db->query("SELECT COUNT(*) FROM operations_resources WHERE maintenance_status = 'ok'")->fetchColumn();
    try {
        $alertStmt = $db->prepare("SELECT COUNT(*) FROM notifications WHERE module = 'operations' AND type = 'alert' AND status = 'unread'");
        $alertStmt->execute();
        $stats['priority_alerts'] = (int) $alertStmt->fetchColumn();
    } catch (PDOException $exception) {
        error_log('Operations priority alert count unavailable: ' . $exception->getMessage());
        $stats['priority_alerts'] = 0;
    }
    $stats['field_units'] = $db->query("SELECT COUNT(*) FROM staff WHERE role = 'field'")->fetchColumn();
    return $stats;
}
?>
