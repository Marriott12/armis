-- =====================================================
-- Migration: Prevent Duplicate Promotions
-- Date: 2025-10-07
-- Purpose: Add database constraints and indexes to prevent duplicate promotion records
-- =====================================================

-- Add additional tracking columns to staff_promotions table
ALTER TABLE `staff_promotions` 
ADD COLUMN IF NOT EXISTS `user_ip` VARCHAR(45) DEFAULT NULL COMMENT 'IP address of user who created the promotion',
ADD COLUMN IF NOT EXISTS `user_agent` TEXT DEFAULT NULL COMMENT 'Browser/client information';

-- Add index to improve duplicate detection performance
ALTER TABLE `staff_promotions` 
ADD INDEX IF NOT EXISTS `idx_duplicate_check` (`staff_id`, `new_rank`, `date_to`, `created_at`);

-- Add index for date-based queries
ALTER TABLE `staff_promotions` 
ADD INDEX IF NOT EXISTS `idx_promotion_date` (`date_to`, `staff_id`);

-- Add index for type-based queries
ALTER TABLE `staff_promotions` 
ADD INDEX IF NOT EXISTS `idx_promotion_type` (`type`, `staff_id`);

-- OPTIONAL: Uncomment the following to add a strict unique constraint
-- WARNING: This will prevent ANY duplicate promotions to the same rank on the same date
-- Only enable this if your business rules require strict uniqueness

/*
ALTER TABLE `staff_promotions` 
ADD UNIQUE INDEX `unique_promotion_per_staff_rank_date` (`staff_id`, `new_rank`, `date_to`);
*/

-- Create a view to easily identify potential duplicates
CREATE OR REPLACE VIEW `v_potential_duplicate_promotions` AS
SELECT 
    sp1.id,
    sp1.staff_id,
    s.service_number,
    CONCAT(s.first_name, ' ', s.last_name) AS staff_name,
    sp1.current_rank,
    sp1.new_rank,
    r.name AS new_rank_name,
    sp1.date_to AS promotion_date,
    sp1.type,
    sp1.created_at,
    COUNT(*) OVER (PARTITION BY sp1.staff_id, sp1.new_rank, sp1.date_to) AS duplicate_count
FROM staff_promotions sp1
JOIN staff s ON sp1.staff_id = s.id
JOIN ranks r ON sp1.new_rank = r.id
HAVING duplicate_count > 1
ORDER BY sp1.created_at DESC;

-- Create a stored procedure to check for duplicates before promotion
DELIMITER $$

CREATE PROCEDURE IF NOT EXISTS `sp_check_duplicate_promotion`(
    IN p_staff_id INT,
    IN p_new_rank_id INT,
    IN p_promotion_date DATE,
    OUT p_is_duplicate BOOLEAN,
    OUT p_duplicate_message VARCHAR(255)
)
BEGIN
    DECLARE v_existing_id INT DEFAULT NULL;
    DECLARE v_existing_created_at DATETIME DEFAULT NULL;
    DECLARE v_rank_name VARCHAR(100);
    
    -- Check for exact duplicate within last 24 hours
    SELECT id, created_at INTO v_existing_id, v_existing_created_at
    FROM staff_promotions
    WHERE staff_id = p_staff_id
    AND new_rank = p_new_rank_id
    AND date_to = p_promotion_date
    AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
    ORDER BY created_at DESC
    LIMIT 1;
    
    IF v_existing_id IS NOT NULL THEN
        -- Get rank name for message
        SELECT name INTO v_rank_name FROM ranks WHERE id = p_new_rank_id;
        
        SET p_is_duplicate = TRUE;
        SET p_duplicate_message = CONCAT(
            'Duplicate promotion detected: Record #', v_existing_id,
            ' already exists for this staff to ', v_rank_name,
            ' on ', p_promotion_date,
            ' (created ', TIME_FORMAT(TIMEDIFF(NOW(), v_existing_created_at), '%H:%i'), ' ago)'
        );
    ELSE
        SET p_is_duplicate = FALSE;
        SET p_duplicate_message = NULL;
    END IF;
END$$

DELIMITER ;

-- Create a trigger to log duplicate prevention attempts
CREATE TABLE IF NOT EXISTS `promotion_duplicate_prevention_log` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `staff_id` INT UNSIGNED NOT NULL,
    `service_number` VARCHAR(50) DEFAULT NULL,
    `attempted_rank` INT UNSIGNED NOT NULL,
    `attempted_date` DATE NOT NULL,
    `existing_promotion_id` INT UNSIGNED DEFAULT NULL,
    `prevented_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `user_id` INT UNSIGNED DEFAULT NULL,
    `ip_address` VARCHAR(45) DEFAULT NULL,
    `reason` TEXT,
    PRIMARY KEY (`id`),
    INDEX `idx_staff` (`staff_id`),
    INDEX `idx_date` (`prevented_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci
COMMENT='Log of prevented duplicate promotion attempts for audit trail';

-- =====================================================
-- Verification Queries (Run after migration)
-- =====================================================

-- 1. Check for existing duplicates in the database
-- SELECT staff_id, new_rank, date_to, COUNT(*) as count
-- FROM staff_promotions
-- GROUP BY staff_id, new_rank, date_to
-- HAVING count > 1;

-- 2. View potential duplicates
-- SELECT * FROM v_potential_duplicate_promotions;

-- 3. Test the duplicate check procedure
-- CALL sp_check_duplicate_promotion(1, 5, '2025-10-07', @is_dup, @msg);
-- SELECT @is_dup AS is_duplicate, @msg AS message;
