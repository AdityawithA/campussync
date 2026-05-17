-- Run this ONLY if you already have the campussync DB set up from the previous version
-- Skip this if you're doing a fresh setup (schema.sql already has these columns)

USE campussync;

-- Add OTP + approval columns if they don't exist
ALTER TABLE users
  ADD COLUMN IF NOT EXISTS is_verified TINYINT(1) DEFAULT 0 AFTER is_active,
  ADD COLUMN IF NOT EXISTS approval_status ENUM('approved','pending','rejected') DEFAULT 'approved' AFTER is_verified,
  ADD COLUMN IF NOT EXISTS otp_code VARCHAR(6) DEFAULT NULL AFTER approval_status,
  ADD COLUMN IF NOT EXISTS otp_expires_at DATETIME DEFAULT NULL AFTER otp_code;

-- Mark existing admin and students as verified + approved
UPDATE users SET is_verified = 1, approval_status = 'approved' WHERE role IN ('admin', 'student');

-- Existing faculty — set to approved so they aren't suddenly blocked
UPDATE users SET is_verified = 1, approval_status = 'approved' WHERE role = 'faculty';
