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

/**
 * Fetch all results from a query
 */
function fetchAll($sql, $params = []) {
    $stmt = executeQuery($sql, $params);
    return $stmt->fetchAll(PDO::FETCH_OBJ);
}

/**
 * Fetch single result from a query
 */
function fetchOne($sql, $params = []) {
    $stmt = executeQuery($sql, $params);
    return $stmt->fetch(PDO::FETCH_OBJ);
}

/**
 * Get the last inserted ID
 */
function getLastInsertId() {
    $pdo = getDbConnection();
    return $pdo->lastInsertId();
}

/**
 * Begin transaction
 */
function beginTransaction() {
    $pdo = getDbConnection();
    return $pdo->beginTransaction();
}

/**
 * Commit transaction
 */
function commitTransaction() {
    $pdo = getDbConnection();
    return $pdo->commit();
}

/**
 * Rollback transaction
 */
function rollbackTransaction() {
    $pdo = getDbConnection();
    return $pdo->rollback();
}

/**
 * Check if table exists
 */
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
?>
