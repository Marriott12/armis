<?php
/**
 * Database Helper Functions for Admin Branch
 * Provides fetchAll and related functions using the modern PDO connection
 */

// Prevent direct access
if (!defined('ARMIS_ADMIN_BRANCH')) {
    die('Direct access not permitted');
}

/**
 * Execute a query and return the statement
 */
if (!function_exists('executeQuery')) {
    function executeQuery($sql, $params = []) {
        try {
            $pdo = getDbConnection(); // Use the modern connection
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt;
        } catch (PDOException $e) {
            error_log("Query execution failed: " . $e->getMessage());
            throw new Exception("Database query failed: " . $e->getMessage());
        }
    }
}

/**
 * Fetch all results from a query
 */
if (!function_exists('fetchAll')) {
    function fetchAll($sql, $params = []) {
        try {
            $pdo = getDbConnection();
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_OBJ);
        } catch (PDOException $e) {
            error_log("fetchAll failed: " . $e->getMessage());
            return [];
        }
    }
}

/**
 * Fetch single result from a query
 */
if (!function_exists('fetchOne')) {
    function fetchOne($sql, $params = []) {
        try {
            $pdo = getDbConnection();
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetch(PDO::FETCH_OBJ);
        } catch (PDOException $e) {
            error_log("fetchOne failed: " . $e->getMessage());
            return null;
        }
    }
}

/**
 * Get the last inserted ID
 */
if (!function_exists('getLastInsertId')) {
    function getLastInsertId() {
        $pdo = getDbConnection();
        return $pdo->lastInsertId();
    }
}

/**
 * Begin transaction
 */
if (!function_exists('beginTransaction')) {
    function beginTransaction() {
        $pdo = getDbConnection();
        return $pdo->beginTransaction();
    }
}

/**
 * Commit transaction
 */
if (!function_exists('commitTransaction')) {
    function commitTransaction() {
        $pdo = getDbConnection();
        return $pdo->commit();
    }
}

/**
 * Rollback transaction
 */
if (!function_exists('rollbackTransaction')) {
    function rollbackTransaction() {
        $pdo = getDbConnection();
        return $pdo->rollback();
    }
}

/**
 * Check if table exists
 */
if (!function_exists('tableExists')) {
    function tableExists($tableName) {
        try {
            $pdo = getDbConnection();
            $stmt = $pdo->prepare("SHOW TABLES LIKE ?");
            $stmt->execute([$tableName]);
            return $stmt->rowCount() > 0;
        } catch (PDOException $e) {
            error_log("Table check failed: " . $e->getMessage());
            return false;
        }
    }
}
?>
