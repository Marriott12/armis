<?php
/**
 * ARMIS one-time export endpoint. Temporary plaintext credentials are held in a
 * short-lived, web-denied cache file keyed by a random token in the admin session.
 */
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/shared/database_connection.php';
require_once dirname(__DIR__) . '/shared/rbac.php';
if (!isset($_SESSION['user_id'])) { header('Location: ../login.php'); exit(); }
requireModuleAccess('admin');
requireBranchAdmin();
$csrf = $_POST['csrf_token'] ?? '';
if (!isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $csrf)) { http_response_code(403); exit('Invalid CSRF token.'); }
function exportCredentialDir(): string {
    $dir = dirname(__DIR__) . '/cache/generated_credentials';
    if (!is_dir($dir)) @mkdir($dir, 0700, true);
    return $dir;
}
$token = $_SESSION['armis_generated_credentials_token'] ?? '';
if (!is_string($token) || !preg_match('/^[a-f0-9]{64}$/', $token)) {
    http_response_code(404); exit('No newly generated credentials are available for export. Run account creation first.');
}
$path = exportCredentialDir() . '/' . $token . '.json';
$raw = is_file($path) ? @file_get_contents($path) : false;
$data = is_string($raw) ? json_decode($raw, true) : null;
if (!is_array($data) || !isset($data['expires_at'], $data['credentials']) || (int)$data['expires_at'] < time() || !is_array($data['credentials']) || !$data['credentials']) {
    @unlink($path); unset($_SESSION['armis_generated_credentials_token']);
    http_response_code(404); exit('No newly generated credentials are available for export. The one-time export may already have been used or the temporary credential file expired.');
}
$credentials = $data['credentials'];
@unlink($path); unset($_SESSION['armis_generated_credentials_token'], $_SESSION['armis_generated_credentials']);
logAccess('admin', 'bulk_user_credentials_export', true, 'Exported ' . count($credentials) . ' newly generated user credentials.');
header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="ARMIS_new_user_credentials_' . date('Ymd_His') . '.csv"');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
$out = fopen('php://output', 'w');
fputcsv($out, ['Service Number', 'Name', 'Username', 'Temporary Password']);
foreach ($credentials as $credential) { fputcsv($out, [$credential['svcNo'] ?? '', $credential['name'] ?? '', $credential['username'] ?? '', $credential['password'] ?? '']); }
fclose($out);
exit;
