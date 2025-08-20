<?php
/**
 * Finance Dashboard Service
 * Provides dynamic data for finance dashboard and functionality
 */

if (!defined('ARMIS_FINANCE')) {
    die('Direct access not permitted');
}

class FinanceService {
    private $db;
    
    public function __construct($database_connection) {
        $this->db = $database_connection;
    }
    
    /**
     * Get finance KPI data
     */
    public function getKPIData() {
        try {
            $kpis = [];
            
            // Total Budget for current fiscal year
            $stmt = $this->db->prepare("
                SELECT SUM(allocated_amount) as total_budget 
                FROM finance_budgets 
                WHERE fiscal_year = YEAR(CURDATE()) AND status = 'approved'
            ");
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $kpis['total_budget'] = (float)($result['total_budget'] ?? 0);
            
            // Total Expenses YTD
            $stmt = $this->db->prepare("
                SELECT SUM(amount) as total_expenses 
                FROM finance_transactions 
                WHERE transaction_type = 'expense' 
                AND YEAR(transaction_date) = YEAR(CURDATE())
                AND status = 'approved'
            ");
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $kpis['total_expenses'] = (float)($result['total_expenses'] ?? 0);
            
            // Budget Utilization
            $kpis['budget_utilization'] = $kpis['total_budget'] > 0 ? 
                round(($kpis['total_expenses'] / $kpis['total_budget']) * 100, 1) : 0;
            
            // Pending Approvals
            $stmt = $this->db->prepare("
                SELECT COUNT(*) as pending_count 
                FROM finance_transactions 
                WHERE status = 'pending'
            ");
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $kpis['pending_approvals'] = (int)($result['pending_count'] ?? 0);
            
            // Active Contracts
            $stmt = $this->db->prepare("
                SELECT COUNT(*) as active_contracts 
                FROM finance_contracts 
                WHERE status = 'active' AND end_date >= CURDATE()
            ");
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $kpis['active_contracts'] = (int)($result['active_contracts'] ?? 0);
            
            error_log("Finance KPI data retrieved: " . json_encode($kpis));
            return $kpis;
            
        } catch (Exception $e) {
            error_log("Finance KPI error: " . $e->getMessage());
            return [
                'total_budget' => 0,
                'total_expenses' => 0,
                'budget_utilization' => 0,
                'pending_approvals' => 0,
                'active_contracts' => 0
            ];
        }
    }
    
    /**
     * Get recent finance activities
     */
    public function getRecentActivities($limit = 10) {
        try {
            $stmt = $this->db->prepare("
                SELECT 
                    id,
                    transaction_type,
                    description,
                    amount,
                    status,
                    transaction_date,
                    created_at
                FROM finance_transactions 
                ORDER BY created_at DESC 
                LIMIT ?
            ");
            $stmt->execute([$limit]);
            $activities = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            return array_map(function($activity) {
                return [
                    'id' => $activity['id'],
                    'title' => ucfirst($activity['transaction_type']) . ' - $' . number_format($activity['amount'], 2),
                    'description' => $activity['description'],
                    'status' => $activity['status'],
                    'timestamp' => $activity['created_at'],
                    'amount' => $activity['amount'],
                    'type' => $activity['transaction_type']
                ];
            }, $activities);
            
        } catch (Exception $e) {
            error_log("Finance activities error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get budget overview
     */
    public function getBudgetOverview() {
        try {
            $stmt = $this->db->prepare("
                SELECT 
                    b.*,
                    COALESCE(SUM(t.amount), 0) as spent_amount
                FROM finance_budgets b
                LEFT JOIN finance_transactions t ON b.category = t.category 
                    AND t.transaction_type = 'expense' 
                    AND t.status = 'approved'
                    AND YEAR(t.transaction_date) = b.fiscal_year
                WHERE b.fiscal_year = YEAR(CURDATE()) 
                AND b.status = 'approved'
                GROUP BY b.id
                ORDER BY b.allocated_amount DESC
            ");
            $stmt->execute();
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (Exception $e) {
            error_log("Finance budget overview error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get expense breakdown
     */
    public function getExpenseBreakdown() {
        try {
            $stmt = $this->db->prepare("
                SELECT 
                    category,
                    SUM(amount) as total_amount,
                    COUNT(*) as transaction_count
                FROM finance_transactions 
                WHERE transaction_type = 'expense' 
                AND YEAR(transaction_date) = YEAR(CURDATE())
                AND status = 'approved'
                GROUP BY category
                ORDER BY total_amount DESC
            ");
            $stmt->execute();
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (Exception $e) {
            error_log("Finance expense breakdown error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get monthly spending trends
     */
    public function getMonthlyTrends() {
        try {
            $stmt = $this->db->prepare("
                SELECT 
                    DATE_FORMAT(transaction_date, '%Y-%m') as month,
                    SUM(CASE WHEN transaction_type = 'expense' THEN amount ELSE 0 END) as expenses,
                    SUM(CASE WHEN transaction_type = 'income' THEN amount ELSE 0 END) as income
                FROM finance_transactions 
                WHERE transaction_date >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
                AND status = 'approved'
                GROUP BY DATE_FORMAT(transaction_date, '%Y-%m')
                ORDER BY month
            ");
            $stmt->execute();
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (Exception $e) {
            error_log("Finance monthly trends error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get pending transactions
     */
    public function getPendingTransactions() {
        try {
            $stmt = $this->db->prepare("
                SELECT *
                FROM finance_transactions 
                WHERE status = 'pending'
                ORDER BY created_at DESC
                LIMIT 20
            ");
            $stmt->execute();
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (Exception $e) {
            error_log("Finance pending transactions error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get contract overview
     */
    public function getContractOverview() {
        try {
            $stmt = $this->db->prepare("
                SELECT 
                    *,
                    DATEDIFF(end_date, CURDATE()) as days_remaining
                FROM finance_contracts 
                WHERE status = 'active'
                ORDER BY end_date ASC
                LIMIT 10
            ");
            $stmt->execute();
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (Exception $e) {
            error_log("Finance contract overview error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get financial alerts
     */
    public function getFinancialAlerts() {
        try {
            $alerts = [];
            
            // Budget alerts - over 90% utilization
            $stmt = $this->db->prepare("
                SELECT 
                    b.category,
                    b.allocated_amount,
                    COALESCE(SUM(t.amount), 0) as spent_amount
                FROM finance_budgets b
                LEFT JOIN finance_transactions t ON b.category = t.category 
                    AND t.transaction_type = 'expense' 
                    AND t.status = 'approved'
                    AND YEAR(t.transaction_date) = b.fiscal_year
                WHERE b.fiscal_year = YEAR(CURDATE()) 
                AND b.status = 'approved'
                GROUP BY b.id
                HAVING (spent_amount / b.allocated_amount) > 0.9
            ");
            $stmt->execute();
            $budgetAlerts = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($budgetAlerts as $alert) {
                $utilization = round(($alert['spent_amount'] / $alert['allocated_amount']) * 100, 1);
                $alerts[] = [
                    'type' => 'budget_alert',
                    'priority' => $utilization > 100 ? 'high' : 'medium',
                    'title' => 'Budget Alert: ' . $alert['category'],
                    'message' => "Budget utilization at {$utilization}%",
                    'created_at' => date('Y-m-d H:i:s')
                ];
            }
            
            // Contract expiration alerts
            $stmt = $this->db->prepare("
                SELECT *
                FROM finance_contracts 
                WHERE status = 'active' 
                AND end_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)
                ORDER BY end_date ASC
            ");
            $stmt->execute();
            $contractAlerts = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($contractAlerts as $contract) {
                $days = floor((strtotime($contract['end_date']) - time()) / (60*60*24));
                $priority = $days <= 7 ? 'high' : 'medium';
                $alerts[] = [
                    'type' => 'contract_expiry',
                    'priority' => $priority,
                    'title' => 'Contract Expiring: ' . $contract['contract_name'],
                    'message' => "Expires in {$days} days",
                    'created_at' => date('Y-m-d H:i:s')
                ];
            }
            
            return $alerts;
            
        } catch (Exception $e) {
            error_log("Finance alerts error: " . $e->getMessage());
            return [];
        }
    }
}
?>