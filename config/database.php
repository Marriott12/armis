<?php
/**
 * ARMIS Database Configuration Bridge
 * This file exists to maintain backward compatibility with old code
 * while consolidating database connectivity to the shared/database_connection.php file
 */

// Include the main database connection file
require_once dirname(__DIR__) . '/shared/database_connection.php';

// This file is intentionally minimal to ensure all database connections use the centralized configuration
?>
