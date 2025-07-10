<?php
// Database configuration
$server = 'localhost';
$user = 'root';
$password = '';
$db = 'armis';

try {
    $pdo = new PDO("mysql:host=$server;dbname=$db;charset=utf8mb4", $user, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    
    // Keep legacy mysqli connection for backward compatibility
    $conn = new mysqli($server, $user, $password, $db);
    if ($conn->connect_error) {
        die($conn->connect_error);
    }
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}
?>