<?php
/**
 * ARMIS Finance Service
 * Business logic layer for finance management
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';
require_once dirname(dirname(__DIR__)) . '/shared/database_connection.php';

class FinanceService {
    private $pdo;
    
    public function __construct($pdo = null) {
        $this->pdo = $pdo ?: getDbConnection();
    }
    
    public function getDashboardData() {
        return [
            'stats' => FinanceUtils::getFinanceStats(),
            'active_budgets' => FinanceUtils::getBudgets('Active'),
            'recent_expenditures' => $this->getRecentExpenditures(10),
            'pending_procurements' => $this->getPendingProcurements(5)
        ];
    }
    
    public function createBudget($data) {
        $required = ['budget_name', 'fiscal_year', 'amount', 'category'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                throw new Exception("Field {$field} is required");
            }
        }
        
        if ($data['amount'] <= 0) {
            throw new Exception("Budget amount must be greater than zero");
        }
        
        $budgetId = FinanceUtils::createBudget($data);
        
        if ($budgetId) {
            logFinanceActivity('budget_created', "Budget '{$data['budget_name']}' created", [
                'budget_id' => $budgetId,
                'amount' => $data['amount']
            ]);
        }
        
        return $budgetId;
    }
    
    public function createExpenditure($data) {
        $sql = "INSERT INTO expenditures (description, amount, category, budget_id, status, created_by) 
                VALUES (?, ?, ?, ?, 'Pending', ?)";
        
        $result = executeQuery($sql, [
            $data['description'],
            $data['amount'],
            $data['category'],
            $data['budget_id'],
            $_SESSION['user_id']
        ]);
        
        if ($result) {
            logFinanceActivity('expenditure_created', "Expenditure created", [
                'amount' => $data['amount'],
                'category' => $data['category']
            ]);
        }
        
        return $result;
    }
    
    public function searchBudgets($params = []) {
        $sql = "SELECT * FROM budgets WHERE 1=1";
        $bindings = [];
        
        if (!empty($params['status'])) {
            $sql .= " AND status = ?";
            $bindings[] = $params['status'];
        }
        
        if (!empty($params['fiscal_year'])) {
            $sql .= " AND fiscal_year = ?";
            $bindings[] = $params['fiscal_year'];
        }
        
        $sql .= " ORDER BY fiscal_year DESC, budget_name ASC";
        
        return fetchAll($sql, $bindings) ?: [];
    }
    
    private function getRecentExpenditures($limit) {
        $sql = "SELECT * FROM expenditures ORDER BY created_at DESC LIMIT ?";
        return fetchAll($sql, [$limit]) ?: [];
    }
    
    private function getPendingProcurements($limit) {
        $sql = "SELECT * FROM procurement WHERE status = 'Pending' ORDER BY created_at DESC LIMIT ?";
        return fetchAll($sql, [$limit]) ?: [];
    }
}