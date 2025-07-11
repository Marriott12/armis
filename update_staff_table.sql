-- Add authentication-related fields to staff table for password reset functionality
-- This script adds fields needed for secure password reset and forced password changes

-- Add must_reset_password field to force password change on first login
ALTER TABLE `staff` 
ADD COLUMN `must_reset_password` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Flag to force password reset on next login';

-- Add password reset token field for secure password reset links
ALTER TABLE `staff` 
ADD COLUMN `reset_token` VARCHAR(255) NULL DEFAULT NULL COMMENT 'Secure token for password reset';

-- Add password reset token expiry field
ALTER TABLE `staff` 
ADD COLUMN `reset_token_expiry` DATETIME NULL DEFAULT NULL COMMENT 'Expiry time for password reset token';

-- Add last login tracking
ALTER TABLE `staff` 
ADD COLUMN `last_login` DATETIME NULL DEFAULT NULL COMMENT 'Last successful login timestamp';

-- Add failed login attempts counter for security
ALTER TABLE `staff` 
ADD COLUMN `failed_login_attempts` INT(11) NOT NULL DEFAULT 0 COMMENT 'Counter for failed login attempts';

-- Add account locked until field for temporary lockouts
ALTER TABLE `staff` 
ADD COLUMN `locked_until` DATETIME NULL DEFAULT NULL COMMENT 'Account locked until this time';

-- Update existing staff records to have proper password hashes if they don't already
-- Note: This is just a comment - actual password updates should be done manually for security

-- Create index for faster username lookups during authentication
CREATE INDEX `idx_staff_username` ON `staff` (`username`);

-- Create index for reset token lookups
CREATE INDEX `idx_staff_reset_token` ON `staff` (`reset_token`);