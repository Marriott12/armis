-- ARMIS: Posting / Appointment schema alignment
-- Purpose: keep current staff posting fields large enough to store the
-- canonical appointment and unit identifiers used by appointment/unit tables.
-- Safe for existing data: widening VARCHAR columns only.

ALTER TABLE `staff`
  MODIFY COLUMN `apptId` VARCHAR(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NULL;

ALTER TABLE `staff_appointment`
  MODIFY COLUMN `apptId` VARCHAR(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NULL,
  MODIFY COLUMN `unitId` VARCHAR(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NULL;

-- Verification:
-- SHOW COLUMNS FROM staff LIKE 'apptId';
-- SHOW COLUMNS FROM staff_appointment WHERE Field IN ('apptId','unitId');
