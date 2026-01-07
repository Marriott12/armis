<?php
// Training module manager: handles CRUD and logic for training sessions, courses, assignments
class TrainingManager {
    private $db;
    public function __construct() {
        $this->db = new PDO('mysql:host=localhost;dbname=armis1;charset=utf8mb4', 'root', '');
    }
    // Dashboard stats
    public function getDashboardStats() {
    $courses = $this->db->query('SELECT COUNT(*) FROM courses')->fetchColumn();
        $sessions = $this->db->query('SELECT COUNT(*) FROM training_sessions')->fetchColumn();
        $assignments = $this->db->query('SELECT COUNT(*) FROM training_assignments')->fetchColumn();
        return [
            'total_courses' => $courses,
            'total_sessions' => $sessions,
            'total_assignments' => $assignments
        ];
    }
    // Courses CRUD
    public function getAllCourses() {
    $stmt = $this->db->query('SELECT c.*, (SELECT COUNT(*) FROM training_sessions s WHERE s.course_id = c.id) as session_count FROM courses c');
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    public function getCourse($id) {
    $stmt = $this->db->prepare('SELECT * FROM courses WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    public function addCourse($data) {
        $stmt = $this->db->prepare('INSERT INTO courses (name, description) VALUES (?, ?)');
        $stmt->execute([$data['name'], $data['description']]);
    }
    public function updateCourse($id, $data) {
        $stmt = $this->db->prepare('UPDATE courses SET name = ?, description = ? WHERE id = ?');
        $stmt->execute([$data['name'], $data['description'], $id]);
    }
    public function deleteCourse($id) {
        $stmt = $this->db->prepare('DELETE FROM courses WHERE id = ?');
        $stmt->execute([$id]);
    }
    // Sessions CRUD
    public function getAllSessions() {
    $stmt = $this->db->query('SELECT s.*, c.name as course_name, (SELECT COUNT(*) FROM training_assignments a WHERE a.session_id = s.id) as assigned_count FROM training_sessions s LEFT JOIN courses c ON s.course_id = c.id');
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
    $stmt = $this->db->query('SELECT a.*, CONCAT(s.rankId, " ", s.fName, " ", s.lName) as personnel_name, c.name as course_name, ts.title as session_title FROM training_assignments a LEFT JOIN staff s ON a.personnel_id = s.id LEFT JOIN courses c ON a.course_id = c.id LEFT JOIN training_sessions ts ON a.session_id = ts.id');
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
    $stmt = $this->db->prepare('SELECT a.*, p.name as personnel_name, c.name as course_name, s.title as session_title FROM training_assignments a LEFT JOIN personnel p ON a.personnel_id = p.id LEFT JOIN courses c ON a.course_id = c.id LEFT JOIN training_sessions s ON a.session_id = s.id WHERE p.name LIKE ? OR c.name LIKE ? OR s.title LIKE ?');
        $stmt->execute(['%' . $term . '%', '%' . $term . '%', '%' . $term . '%']);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    public function getDb() {
        return $this->db;
    }
}
