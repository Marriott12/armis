-- Fix staff_skills, staff_medical_records, and staff_performance_reviews
-- Convert old integer staff_id values to actual svcNo values

-- This assumes the old staff_id (int) was the row number/id in staff table
-- We need to update the svcNo column in these tables to match actual service numbers

-- For staff_skills
UPDATE staff_skills sk
INNER JOIN staff s ON CAST(sk.svcNo AS UNSIGNED) = s.old_id_backup
SET sk.svcNo = s.svcNo
WHERE sk.svcNo REGEXP '^[0-9]+$' AND LENGTH(sk.svcNo) < 6;

-- Note: This assumes you had an old_id_backup column. If not, we need a different approach.
-- Let me check if there's a pattern we can use...

-- Alternative: If svcNo in these tables still has small integers (1, 2, 3, etc.)
-- and staff table used to have an auto-increment id, we can try to map them.
-- But this is risky without knowing the exact relationship.

-- SAFER APPROACH: Clear these tables if data isn't critical
-- TRUNCATE TABLE staff_skills;
-- TRUNCATE TABLE staff_medical_records;
-- TRUNCATE TABLE staff_performance_reviews;

-- Or manually update specific records if you know the mappings
