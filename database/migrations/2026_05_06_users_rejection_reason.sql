ALTER TABLE users
ADD COLUMN rejection_reason TEXT NULL AFTER is_active,
ADD COLUMN rejected_at DATETIME NULL AFTER rejection_reason;
