-- Command module query indexes.
-- The information_schema checks make this safe to run repeatedly on MySQL.

SET @index_exists := (
  SELECT COUNT(*) FROM information_schema.statistics
  WHERE table_schema = DATABASE() AND table_name = 'staff' AND index_name = 'idx_staff_branch_status_rank'
);
SET @sql := IF(@index_exists = 0,
  'ALTER TABLE `staff` ADD INDEX `idx_staff_branch_status_rank` (`branch_id`, `svcStatus`, `rankId`)',
  'SELECT 1');
PREPARE command_index_statement FROM @sql;
EXECUTE command_index_statement;
DEALLOCATE PREPARE command_index_statement;

SET @index_exists := (
  SELECT COUNT(*) FROM information_schema.statistics
  WHERE table_schema = DATABASE() AND table_name = 'staff_appointment' AND index_name = 'idx_staff_appointment_svc_wef'
);
SET @sql := IF(@index_exists = 0,
  'ALTER TABLE `staff_appointment` ADD INDEX `idx_staff_appointment_svc_wef` (`svcNo`, `apptWef`)',
  'SELECT 1');
PREPARE command_index_statement FROM @sql;
EXECUTE command_index_statement;
DEALLOCATE PREPARE command_index_statement;

SET @index_exists := (
  SELECT COUNT(*) FROM information_schema.statistics
  WHERE table_schema = DATABASE() AND table_name = 'staff_course' AND index_name = 'idx_staff_course_svc_end'
);
SET @sql := IF(@index_exists = 0,
  'ALTER TABLE `staff_course` ADD INDEX `idx_staff_course_svc_end` (`svcNo`, `cseEnd`)',
  'SELECT 1');
PREPARE command_index_statement FROM @sql;
EXECUTE command_index_statement;
DEALLOCATE PREPARE command_index_statement;