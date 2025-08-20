<?php
/**
 * ARMIS Finance Module Functions
 * Common utility functions for finance operations
 */

require_once __DIR__ . '/config.php';
require_once dirname(dirname(__DIR__)) . '/shared/database_connection.php';

class FinanceUtils {
    
    public static function getBudgets($status = 'Active') {
        $sql = "SELECT * FROM budgets";
        $params = [];
        
        if ($status) {
            $sql .= " WHERE status = ?";
            $params[] = $status;
        }
        
        $sql .= " ORDER BY fiscal_year DESC";
        return fetchAll($sql, $params) ?: [];
    }
    
    public static function getFinanceStats() {
        $stats = [];
        
        $result = fetchOne("SELECT SUM(amount) as total FROM budgets WHERE status = 'Active'");
        $stats['total_budget'] = $result ? $result->total : 0;
        
        $result = fetchOne("SELECT SUM(amount) as total FROM expenditures WHERE status = 'Approved'");
        $stats['expenditures'] = $result ? $result->total : 0;
        
        $result = fetchOne("SELECT COUNT(*) as total FROM procurement WHERE status IN ('Ordered', 'Approved')");
        $stats['procurement'] = $result ? $result->total : 0;
        
        $result = fetchOne("SELECT COUNT(*) as total FROM audit_logs WHERE status = 'Pending'");
        $stats['audits'] = $result ? $result->total : 0;
        
        return $stats;
    }
    
    public static function createBudget($data) {
        $sql = "INSERT INTO budgets (budget_name, fiscal_year, amount, category, status, created_by) 
                VALUES (?, ?, ?, ?, 'Draft', ?)";
        
        return executeQuery($sql, [
            $data['budget_name'],
            $data['fiscal_year'],
            $data['amount'],
            $data['category'],
            $_SESSION['user_id']
        ]);
    }
    
    public static function formatCurrency($amount) {
        return '$' . number_format($amount, 2);
    }
    
    public static function getStatusBadgeClass($status) {
        $classes = [
            'Draft' => 'bg-secondary',
            'Proposed' => 'bg-info',
            'Approved' => 'bg-success',
            'Active' => 'bg-primary',
            'Locked' => 'bg-warning',
            'Closed' => 'bg-dark'
        ];
        return $classes[$status] ?? 'bg-secondary';
    }
}