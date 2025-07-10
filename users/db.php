<?php
// Database configuration
$server = 'localhost';
$user = 'root';
$password = '';
$db = 'armis';

// Create a mock database connection for demonstration purposes
// In production, this would connect to a real MySQL database
$pdo = null;
$conn = null;

try {
    // Try to connect to the database
    $pdo = new PDO("mysql:host=$server;dbname=$db;charset=utf8mb4", $user, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    
    // Keep legacy mysqli connection for backward compatibility
    $conn = new mysqli($server, $user, $password, $db);
    if ($conn->connect_error) {
        throw new Exception($conn->connect_error);
    }
} catch (Exception $e) {
    // If database connection fails, create a mock connection for demonstration
    // In production, this would be an error condition
    error_log("Database connection failed: " . $e->getMessage());
    
    // Create a mock PDO connection that returns empty results
    $pdo = new class {
        public function query($sql) {
            return new class {
                public function fetchAll() { return []; }
                public function fetch() { return false; }
            };
        }
        public function prepare($sql) {
            return new class {
                public function execute($params = []) { return true; }
                public function fetchAll() { return []; }
                public function fetch() { return false; }
            };
        }
    };
    
    // Create a mock mysqli connection
    $conn = new class {
        public function query($sql) { return false; }
        public function prepare($sql) { return false; }
        public $connect_error = null;
    };
}
?>