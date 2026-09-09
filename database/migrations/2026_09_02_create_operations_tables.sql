-- Operations module base schema. Safe to run repeatedly.

CREATE TABLE IF NOT EXISTS operations_locations (
  location_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  location_name VARCHAR(150) NOT NULL,
  coordinates VARCHAR(100) DEFAULT NULL,
  country VARCHAR(100) DEFAULT NULL,
  UNIQUE KEY ux_operations_location_name (location_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS operations_personnel_roles (
  role_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  role_name VARCHAR(100) NOT NULL,
  UNIQUE KEY ux_operations_personnel_role_name (role_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS operations_resource_types (
  resource_type_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  resource_type_name VARCHAR(100) NOT NULL,
  UNIQUE KEY ux_operations_resource_type_name (resource_type_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS operations_missions (
  mission_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  mission_name VARCHAR(150) NOT NULL,
  mission_code VARCHAR(50) NOT NULL,
  description TEXT DEFAULT NULL,
  status VARCHAR(30) NOT NULL DEFAULT 'planned',
  priority VARCHAR(30) NOT NULL DEFAULT 'medium',
  location_id INT UNSIGNED DEFAULT NULL,
  startDate DATE DEFAULT NULL,
  endDate DATE DEFAULT NULL,
  createdBy VARCHAR(10) DEFAULT NULL,
  createdAt TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  updatedAt TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY ux_operations_mission_code (mission_code),
  KEY idx_operations_mission_status_date (status, startDate),
  CONSTRAINT fk_operations_mission_location FOREIGN KEY (location_id) REFERENCES operations_locations(location_id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS operations_deployments (
  deployment_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(150) NOT NULL,
  location_id INT UNSIGNED DEFAULT NULL,
  location VARCHAR(150) DEFAULT NULL,
  status VARCHAR(30) NOT NULL DEFAULT 'planned',
  startDate DATE DEFAULT NULL,
  endDate DATE DEFAULT NULL,
  createdAt TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_operations_deployment_status_date (status, startDate),
  CONSTRAINT fk_operations_deployment_location FOREIGN KEY (location_id) REFERENCES operations_locations(location_id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS operations_resources (
  resource_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  resource_type_id INT UNSIGNED DEFAULT NULL,
  name VARCHAR(150) NOT NULL,
  type VARCHAR(100) DEFAULT NULL,
  quantity INT UNSIGNED NOT NULL DEFAULT 0,
  status VARCHAR(30) NOT NULL DEFAULT 'available',
  maintenance_status VARCHAR(30) NOT NULL DEFAULT 'ok',
  description TEXT DEFAULT NULL,
  KEY idx_operations_resource_status (status),
  CONSTRAINT fk_operations_resource_type FOREIGN KEY (resource_type_id) REFERENCES operations_resource_types(resource_type_id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS operations_mission_personnel (
  personnel_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  mission_id INT UNSIGNED NOT NULL,
  svcNo VARCHAR(10) NOT NULL,
  role_id VARCHAR(100) DEFAULT NULL,
  startDate DATE DEFAULT NULL,
  endDate DATE DEFAULT NULL,
  status VARCHAR(30) NOT NULL DEFAULT 'assigned',
  KEY idx_operations_personnel_mission (mission_id),
  KEY idx_operations_personnel_staff_status (svcNo, status),
  CONSTRAINT fk_operations_personnel_mission FOREIGN KEY (mission_id) REFERENCES operations_missions(mission_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS operations_mission_resources (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  mission_id INT UNSIGNED NOT NULL,
  resource_id INT UNSIGNED NOT NULL,
  resource_type_id INT UNSIGNED DEFAULT NULL,
  assigned_by VARCHAR(10) DEFAULT NULL,
  KEY idx_operations_mission_resource_mission (mission_id),
  CONSTRAINT fk_operations_mission_resource_mission FOREIGN KEY (mission_id) REFERENCES operations_missions(mission_id) ON DELETE CASCADE,
  CONSTRAINT fk_operations_mission_resource_resource FOREIGN KEY (resource_id) REFERENCES operations_resources(resource_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS operations_deployment_personnel (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  deployment_id INT UNSIGNED NOT NULL,
  svcNo VARCHAR(10) NOT NULL,
  KEY idx_operations_deployment_personnel_deployment (deployment_id),
  CONSTRAINT fk_operations_deployment_personnel_deployment FOREIGN KEY (deployment_id) REFERENCES operations_deployments(deployment_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS operations_status_reports (
  report_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  mission_id INT UNSIGNED DEFAULT NULL,
  report_date DATE NOT NULL,
  submitted_by VARCHAR(10) DEFAULT NULL,
  report_text TEXT DEFAULT NULL,
  KEY idx_operations_status_report_date (report_date),
  CONSTRAINT fk_operations_status_report_mission FOREIGN KEY (mission_id) REFERENCES operations_missions(mission_id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS operations_activity_log (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id VARCHAR(10) DEFAULT NULL,
  action_type VARCHAR(100) NOT NULL,
  entity_id INT UNSIGNED DEFAULT NULL,
  description TEXT DEFAULT NULL,
  createdAt TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_operations_activity_created (createdAt)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS operations_mission_history (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  mission_id INT UNSIGNED NOT NULL,
  changed_by VARCHAR(10) DEFAULT NULL,
  change_type VARCHAR(100) NOT NULL,
  change_details TEXT DEFAULT NULL,
  changed_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_operations_history_mission_date (mission_id, changed_at),
  CONSTRAINT fk_operations_history_mission FOREIGN KEY (mission_id) REFERENCES operations_missions(mission_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS operations_field (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(150) NOT NULL,
  status VARCHAR(30) NOT NULL DEFAULT 'planned',
  location VARCHAR(150) DEFAULT NULL,
  startDate DATE DEFAULT NULL,
  endDate DATE DEFAULT NULL,
  description TEXT DEFAULT NULL,
  KEY idx_operations_field_status_date (status, startDate)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;