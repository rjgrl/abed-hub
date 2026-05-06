-- Remove legacy users.address and users.contact_number (not collected at signup).
-- Safe when columns are missing (fresh installs from db.sql).

SET @db := DATABASE();

SET @has_addr := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'users' AND COLUMN_NAME = 'address'
);
SET @sql := IF(@has_addr > 0, 'ALTER TABLE users DROP COLUMN address', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has_cn := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'users' AND COLUMN_NAME = 'contact_number'
);
SET @sql2 := IF(@has_cn > 0, 'ALTER TABLE users DROP COLUMN contact_number', 'SELECT 1');
PREPARE stmt2 FROM @sql2; EXECUTE stmt2; DEALLOCATE PREPARE stmt2;
