-- Training module base schema. Safe to run repeatedly.

CREATE TABLE IF NOT EXISTS courses (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(150) NOT NULL,
  description TEXT DEFAULT NULL,
  duration_weeks INT UNSIGNED DEFAULT NULL,
  category VARCHAR(100) DEFAULT NULL,
  code VARCHAR(50) DEFAULT NULL,
  type VARCHAR(100) DEFAULT NULL,
  institution_id VARCHAR(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  createdAt TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY ux_training_course_code (code),
  KEY idx_training_course_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS training_sessions (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  course_id INT UNSIGNED NOT NULL,
  title VARCHAR(150) NOT NULL,
  date DATE DEFAULT NULL,
  description TEXT DEFAULT NULL,
  createdAt TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_training_session_course_date (course_id, date),
  CONSTRAINT fk_training_session_course FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS training_assignments (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  personnel_id VARCHAR(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  course_id INT UNSIGNED NOT NULL,
  session_id INT UNSIGNED DEFAULT NULL,
  status VARCHAR(30) NOT NULL DEFAULT 'assigned',
  createdAt TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_training_assignment_personnel (personnel_id),
  KEY idx_training_assignment_session (session_id),
  CONSTRAINT fk_training_assignment_course FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE,
  CONSTRAINT fk_training_assignment_session FOREIGN KEY (session_id) REFERENCES training_sessions(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE training_assignments
  MODIFY personnel_id VARCHAR(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL;

ALTER TABLE courses
  MODIFY institution_id VARCHAR(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL;