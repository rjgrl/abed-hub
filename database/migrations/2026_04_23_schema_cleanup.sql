-- ============================================================
-- Schema Cleanup Migration — apply to existing databases only
-- Run ONCE in a maintenance window; safe to skip for fresh installs
-- (database/db.sql already contains the correct final schema).
-- ============================================================

START TRANSACTION;

-- 1. Add 'coordinator' to users.role if not already present.
--    MySQL ALTER COLUMN replaces the whole ENUM definition.
ALTER TABLE users
    MODIFY COLUMN role ENUM('admin', 'coordinator', 'operator', 'viewer') DEFAULT 'operator';

-- 2. Index for Super-Admin approval queue (idempotent CREATE IF NOT EXISTS not
--    supported for indexes before MySQL 8.0.31, so use a stored procedure guard).
SET @idx := (
    SELECT COUNT(*) FROM information_schema.statistics
    WHERE table_schema = DATABASE()
      AND table_name   = 'users'
      AND index_name   = 'idx_users_active_created_at'
);
SET @sql := IF(@idx = 0,
    'CREATE INDEX idx_users_active_created_at ON users (is_active, created_at)',
    'SELECT "index already exists"'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 3. Create normalized financial entries table (replaces project_financial_tracker).
CREATE TABLE IF NOT EXISTS project_financial_entries (
    id             INT AUTO_INCREMENT PRIMARY KEY,
    project_type   ENUM('FSPF', 'IDP', 'AFME') NOT NULL,
    project_id     INT NOT NULL,
    record_type    ENUM('Obligation', 'Disbursement', 'Liquidation') NOT NULL,
    amount         DECIMAL(15, 2) NOT NULL,
    reference_number VARCHAR(100),
    particulars    TEXT,
    record_date    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    record_status  ENUM('Active', 'Archived') DEFAULT 'Active',
    recorded_by    INT,
    FOREIGN KEY (recorded_by) REFERENCES users(id),
    INDEX idx_project_lookup (project_type, project_id, record_type),
    INDEX idx_record_type    (record_type),
    INDEX idx_record_date    (record_date)
);

-- 4. Migrate any existing rows from the old denormalized tracker.
INSERT IGNORE INTO project_financial_entries
    (project_type, project_id, record_type, amount, reference_number, particulars, record_date, record_status, recorded_by)
SELECT 'FSPF', fspf_project_id, record_type, amount, reference_number, particulars, record_date, record_status, recorded_by
FROM   project_financial_tracker
WHERE  fspf_project_id IS NOT NULL
UNION ALL
SELECT 'IDP', idp_project_id, record_type, amount, reference_number, particulars, record_date, record_status, recorded_by
FROM   project_financial_tracker
WHERE  idp_project_id IS NOT NULL
UNION ALL
SELECT 'AFME', afme_project_id, record_type, amount, reference_number, particulars, record_date, record_status, recorded_by
FROM   project_financial_tracker
WHERE  afme_project_id IS NOT NULL;

-- 5. Drop the old tracker once migration is verified.
--    Un-comment this line only after confirming project_financial_entries is correct.
-- DROP TABLE IF EXISTS project_financial_tracker;

-- 6. Notifications & alerts (api/notifications.php) — idempotent.
CREATE TABLE IF NOT EXISTS project_alerts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    project_id INT NOT NULL,
    alert_type VARCHAR(50) NOT NULL,
    severity ENUM('low', 'medium', 'high', 'critical') DEFAULT 'medium',
    message TEXT NOT NULL,
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    is_active BOOLEAN DEFAULT TRUE,
    FOREIGN KEY (created_by) REFERENCES users(id),
    INDEX idx_project_active (project_id, is_active),
    INDEX idx_created_at (created_at)
);

CREATE TABLE IF NOT EXISTS notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    project_id INT,
    alert_type VARCHAR(50),
    title VARCHAR(255) NOT NULL,
    message TEXT,
    is_read BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_unread (user_id, is_read),
    INDEX idx_created_at (created_at)
);

COMMIT;
