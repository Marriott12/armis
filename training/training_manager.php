<?php
// Training module manager: handles CRUD and logic for training sessions, courses, assignments
class TrainingManager {
    private $db;
    public function __construct() {
        require_once dirname(__DIR__) . '/shared/database_connection.php';
        $this->db = getDbConnection();
    }

    /** Return true when a training table exists; keeps the dashboard usable before the optional training migration is run. */
    private function tableExists(string $table): bool {
        static $cache = [];
        if (array_key_exists($table, $cache)) return $cache[$table];
        $allowed = ['courses', 'training_sessions', 'training_assignments'];
        if (!in_array($table, $allowed, true)) return false;
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?');
        $stmt->execute([$table]);
        return $cache[$table] = ((int)$stmt->fetchColumn() > 0);
    }

    public function isSchemaReady(): bool {
        return $this->tableExists('courses') && $this->tableExists('training_sessions') && $this->tableExists('training_assignments');
    }
    // Dashboard stats
    public function getDashboardStats() {
        $courses = $this->tableExists('courses') ? $this->db->query('SELECT COUNT(*) FROM courses')->fetchColumn() : 0;
        $sessions = $this->tableExists('training_sessions') ? $this->db->query('SELECT COUNT(*) FROM training_sessions')->fetchColumn() : 0;
        $assignments = $this->tableExists('training_assignments') ? $this->db->query('SELECT COUNT(*) FROM training_assignments')->fetchColumn() : 0;
        return [
            'total_courses' => $courses,
            'total_sessions' => $sessions,
            'total_assignments' => $assignments
        ];
    }

    public function getDashboardSummary(): array {
        $summary = $this->getDashboardStats();
        $summary['completed_assignments'] = $this->tableExists('training_assignments') ? (int) $this->db->query("SELECT COUNT(*) FROM training_assignments WHERE LOWER(status) = 'completed'")->fetchColumn() : 0;
        $summary['pending_assignments'] = $this->tableExists('training_assignments') ? (int) $this->db->query("SELECT COUNT(*) FROM training_assignments WHERE LOWER(status) IN ('assigned', 'in progress', 'in_progress')")->fetchColumn() : 0;
        $summary['assigned_personnel'] = $this->tableExists('training_assignments') ? (int) $this->db->query('SELECT COUNT(DISTINCT personnel_id) FROM training_assignments')->fetchColumn() : 0;
        $summary['upcoming_sessions'] = $this->tableExists('training_sessions') ? (int) $this->db->query('SELECT COUNT(*) FROM training_sessions WHERE date >= CURDATE()')->fetchColumn() : 0;
        $summary['completion_rate'] = $summary['total_assignments'] > 0
            ? (int) round(($summary['completed_assignments'] / $summary['total_assignments']) * 100)
            : 0;
        return $summary;
    }
    // Courses CRUD
    public function getAllCourses() {
        if (!$this->tableExists('courses')) return [];
        $sessionCount = $this->tableExists('training_sessions') ? '(SELECT COUNT(*) FROM training_sessions s WHERE s.course_id = c.id)' : '0';
        $stmt = $this->db->query('SELECT c.*, ' . $sessionCount . ' as session_count FROM courses c');
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    public function getCourse($id) {
    $stmt = $this->db->prepare('SELECT * FROM courses WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    public function addCourse($data) {
        $stmt = $this->db->prepare('INSERT INTO courses (name, description, duration_weeks, category, code, type, institution_id) VALUES (?, ?, ?, ?, ?, ?, ?)');
        $stmt->execute([$data['name'], $data['description'], $data['duration_weeks'] ?: null, $data['category'] ?: null, $data['code'] ?: null, $data['type'] ?: null, $data['institution_id'] ?: null]);
    }
    public function updateCourse($id, $data) {
        $stmt = $this->db->prepare('UPDATE courses SET name = ?, description = ?, duration_weeks = ?, category = ?, code = ?, type = ?, institution_id = ? WHERE id = ?');
        $stmt->execute([$data['name'], $data['description'], $data['duration_weeks'] ?: null, $data['category'] ?: null, $data['code'] ?: null, $data['type'] ?: null, $data['institution_id'] ?: null, $id]);
    }
    public function deleteCourse($id) {
        $stmt = $this->db->prepare('DELETE FROM courses WHERE id = ?');
        $stmt->execute([$id]);
    }
    // Sessions CRUD
    public function getAllSessions() {
        if (!$this->tableExists('training_sessions')) return [];
        $courseJoin = $this->tableExists('courses') ? 'LEFT JOIN courses c ON s.course_id = c.id' : '';
        $courseName = $this->tableExists('courses') ? 'c.name as course_name' : "'' as course_name";
        $assignmentCount = $this->tableExists('training_assignments') ? '(SELECT COUNT(*) FROM training_assignments a WHERE a.session_id = s.id)' : '0';
        $stmt = $this->db->query('SELECT s.*, ' . $courseName . ', ' . $assignmentCount . ' as assigned_count FROM training_sessions s ' . $courseJoin);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    public function getSession($id) {
        $stmt = $this->db->prepare('SELECT * FROM training_sessions WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    public function addSession($data) {
        $stmt = $this->db->prepare('INSERT INTO training_sessions (course_id, title, date, description) VALUES (?, ?, ?, ?)');
        $stmt->execute([$data['course_id'], $data['title'], $data['date'], $data['description']]);
    }
    public function updateSession($id, $data) {
        $stmt = $this->db->prepare('UPDATE training_sessions SET course_id = ?, title = ?, date = ?, description = ? WHERE id = ?');
        $stmt->execute([$data['course_id'], $data['title'], $data['date'], $data['description'], $id]);
    }
    public function deleteSession($id) {
        $stmt = $this->db->prepare('DELETE FROM training_sessions WHERE id = ?');
        $stmt->execute([$id]);
    }
    // Assignments CRUD
    public function getAllAssignments() {
        if (!$this->tableExists('training_assignments')) return [];
        $courseJoin = $this->tableExists('courses') ? 'LEFT JOIN courses c ON a.course_id = c.id' : '';
        $courseName = $this->tableExists('courses') ? 'c.name as course_name' : "'' as course_name";
        $sessionJoin = $this->tableExists('training_sessions') ? 'LEFT JOIN training_sessions ts ON a.session_id = ts.id' : '';
        $sessionName = $this->tableExists('training_sessions') ? 'ts.title as session_title' : "'' as session_title";
        $stmt = $this->db->query('SELECT a.*, CONCAT_WS(" ", s.rankId, s.fName, s.mName, s.lName) as personnel_name, ' . $courseName . ', ' . $sessionName . ' FROM training_assignments a LEFT JOIN staff s ON a.personnel_id COLLATE utf8mb4_0900_ai_ci = s.svcNo ' . $courseJoin . ' ' . $sessionJoin);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    public function getAssignment($id) {
        $stmt = $this->db->prepare('SELECT * FROM training_assignments WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    public function addAssignment($data) {
        $stmt = $this->db->prepare('INSERT INTO training_assignments (personnel_id, course_id, session_id, status) VALUES (?, ?, ?, ?)');
        $stmt->execute([$data['personnel_id'], $data['course_id'], $data['session_id'], $data['status']]);
    }
    public function updateAssignment($id, $data) {
        $stmt = $this->db->prepare('UPDATE training_assignments SET personnel_id = ?, course_id = ?, session_id = ?, status = ? WHERE id = ?');
        $stmt->execute([$data['personnel_id'], $data['course_id'], $data['session_id'], $data['status'], $id]);
    }
    public function deleteAssignment($id) {
        $stmt = $this->db->prepare('DELETE FROM training_assignments WHERE id = ?');
        $stmt->execute([$id]);
    }
    // Search/filter (recommended enhancement)
    public function searchCourses($term) {
        $stmt = $this->db->prepare('SELECT * FROM courses WHERE name LIKE ?');
        $stmt->execute(['%' . $term . '%']);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    public function searchSessions($term) {
        $stmt = $this->db->prepare('SELECT * FROM training_sessions WHERE title LIKE ?');
        $stmt->execute(['%' . $term . '%']);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    public function searchAssignments($term) {
    $stmt = $this->db->prepare('SELECT a.*, CONCAT_WS(" ", p.fName, p.mName, p.lName) as personnel_name, c.name as course_name, s.title as session_title FROM training_assignments a LEFT JOIN staff p ON a.personnel_id COLLATE utf8mb4_0900_ai_ci = p.svcNo LEFT JOIN courses c ON a.course_id = c.id LEFT JOIN training_sessions s ON a.session_id = s.id WHERE p.fName LIKE ? OR p.mName LIKE ? OR p.lName LIKE ? OR c.name LIKE ? OR s.title LIKE ?');
        $stmt->execute(array_fill(0, 5, '%' . $term . '%'));
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    public function getDb() {
        return $this->db;
    }
}
