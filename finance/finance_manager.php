<?php
class FinanceManager
{
    private PDO $db;
    private string $userId;

    public function __construct(?string $userId = null)
    {
        require_once dirname(__DIR__) . '/shared/database_connection.php';
        $this->db = getDbConnection();
        $this->userId = $userId ?? (string) ($_SESSION['user_id'] ?? '');
    }

    public function getDashboardSummary(): array
    {
        $year = (int) date('Y');
        $budgetStmt = $this->db->prepare('SELECT COALESCE(SUM(amount), 0) FROM finance_budgets WHERE fiscal_year = ?');
        $budgetStmt->execute([$year]);
        $totalBudget = (float) $budgetStmt->fetchColumn();
        $spent = (float) $this->db->query("SELECT COALESCE(SUM(amount), 0) FROM finance_expenditures WHERE status IN ('approved', 'paid')")->fetchColumn();
        $activeProcurements = (int) $this->db->query("SELECT COUNT(*) FROM finance_procurements WHERE status IN ('pending', 'approved', 'ordered')")->fetchColumn();
        $pendingExpenditures = (int) $this->db->query("SELECT COUNT(*) FROM finance_expenditures WHERE status = 'pending'")->fetchColumn();
        return ['year' => $year, 'total_budget' => $totalBudget, 'spent' => $spent, 'remaining' => $totalBudget - $spent, 'utilization' => $totalBudget > 0 ? (int) round(($spent / $totalBudget) * 100) : 0, 'active_procurements' => $activeProcurements, 'pending_expenditures' => $pendingExpenditures];
    }

    public function getRecentActivity(int $limit = 8): array
    {
        $stmt = $this->db->prepare("SELECT expenditure_date AS activity_date, description, amount, category, status, 'Expenditure' AS activity_type FROM finance_expenditures UNION ALL SELECT order_date, description, amount, 'Procurement', status, 'Procurement' FROM finance_procurements ORDER BY activity_date DESC, status ASC LIMIT ?");
        $stmt->bindValue(1, $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getBudgets(): array { return $this->db->query('SELECT * FROM finance_budgets ORDER BY fiscal_year DESC, category')->fetchAll(PDO::FETCH_ASSOC); }
    public function getExpenditures(): array { return $this->db->query('SELECT * FROM finance_expenditures ORDER BY expenditure_date DESC, id DESC')->fetchAll(PDO::FETCH_ASSOC); }
    public function getProcurements(): array { return $this->db->query('SELECT * FROM finance_procurements ORDER BY order_date DESC, id DESC')->fetchAll(PDO::FETCH_ASSOC); }
    public function getAuditLog(): array { return $this->db->query('SELECT * FROM finance_audit_log ORDER BY created_at DESC LIMIT 100')->fetchAll(PDO::FETCH_ASSOC); }

    public function addBudget(array $data): void { $this->write('INSERT INTO finance_budgets (fiscal_year, category, amount, notes, created_by) VALUES (?, ?, ?, ?, ?)', [(int) $data['fiscal_year'], $data['category'], $data['amount'], $data['notes'] ?: null, $this->userId], 'budget_created', 'budget'); }
    public function addExpenditure(array $data): void { $this->write('INSERT INTO finance_expenditures (expenditure_date, description, category, amount, status, created_by) VALUES (?, ?, ?, ?, ?, ?)', [$data['expenditure_date'], $data['description'], $data['category'], $data['amount'], $data['status'], $this->userId], 'expenditure_created', 'expenditure'); }
    public function addProcurement(array $data): void { $this->write('INSERT INTO finance_procurements (reference_no, supplier, description, amount, status, order_date, created_by) VALUES (?, ?, ?, ?, ?, ?, ?)', [$data['reference_no'], $data['supplier'], $data['description'], $data['amount'], $data['status'], $data['order_date'] ?: null, $this->userId], 'procurement_created', 'procurement'); }

    private function write(string $sql, array $params, string $action, string $entityType): void
    {
        $this->db->beginTransaction();
        try { $statement = $this->db->prepare($sql); $statement->execute($params); $this->db->prepare('INSERT INTO finance_audit_log (user_id, action, entity_type, entity_id) VALUES (?, ?, ?, ?)')->execute([$this->userId, $action, $entityType, (int) $this->db->lastInsertId()]); $this->db->commit(); } catch (Throwable $exception) { $this->db->rollBack(); throw $exception; }
    }
}