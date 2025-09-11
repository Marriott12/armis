<?php
// RESTful API for operations module
// Endpoints: missions, deployments, resources, personnel, reports
header('Content-Type: application/json');
require_once dirname(__DIR__) . '/shared/database_connection.php';
$db = getDbConnection();

$endpoint = $_GET['endpoint'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];

function respond($data, $code = 200) {
    http_response_code($code);
    echo json_encode($data);
    exit();
}

switch ($endpoint) {
    case 'missions':
        if ($method === 'GET') {
            $stmt = $db->query('SELECT * FROM operations_missions');
            respond($stmt->fetchAll(PDO::FETCH_ASSOC));
        }
        // ...implement POST, PUT, DELETE
        break;
    case 'deployments':
        if ($method === 'GET') {
            $stmt = $db->query('SELECT * FROM operations_deployments');
            respond($stmt->fetchAll(PDO::FETCH_ASSOC));
        }
        // ...implement POST, PUT, DELETE
        break;
    case 'resources':
        if ($method === 'GET') {
            $stmt = $db->query('SELECT * FROM operations_resources');
            respond($stmt->fetchAll(PDO::FETCH_ASSOC));
        }
        // ...implement POST, PUT, DELETE
        break;
    case 'personnel':
        if ($method === 'GET') {
            $stmt = $db->query('SELECT * FROM staff');
            respond($stmt->fetchAll(PDO::FETCH_ASSOC));
        }
        // ...implement POST, PUT, DELETE
        break;
    case 'reports':
        if ($method === 'GET') {
            $stmt = $db->query('SELECT * FROM operations_status_reports');
            respond($stmt->fetchAll(PDO::FETCH_ASSOC));
        }
        // ...implement POST, PUT, DELETE
        break;
    default:
        respond(['error' => 'Invalid endpoint'], 404);
}
?>
