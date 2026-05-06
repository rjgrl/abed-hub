-- Google Sign-In: OpenID Connect subject (nullable; unique when set)
--
-- Safe to run multiple times: skips steps already applied (e.g. if the app
-- auto-migrated via ensure_users_google_oauth_schema() in config/database.php).

SET @db = DATABASE();

-- Column google_sub
SET @col_exists := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'users' AND COLUMN_NAME = 'google_sub'
);
SET @sql := IF(
  @col_exists = 0,
  'ALTER TABLE users ADD COLUMN google_sub VARCHAR(255) NULL DEFAULT NULL AFTER email',
  'SELECT ''skip: column google_sub already exists'' AS migration_note'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Unique index on google_sub (multiple NULLs allowed in MySQL)
SET @idx_exists := (
  SELECT COUNT(*) FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'users' AND INDEX_NAME = 'idx_users_google_sub'
);
SET @sql2 := IF(
  @idx_exists = 0,
  'ALTER TABLE users ADD UNIQUE INDEX idx_users_google_sub (google_sub)',
  'SELECT ''skip: index idx_users_google_sub already exists'' AS migration_note'
);
PREPARE stmt2 FROM @sql2;
EXECUTE stmt2;
DEALLOCATE PREPARE stmt2;
