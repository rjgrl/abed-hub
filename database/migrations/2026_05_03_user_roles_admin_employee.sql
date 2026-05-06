-- Collapse user roles to admin + employee only (run once on existing databases).
-- Fresh installs: use database/db.sql which already defines the final ENUM.

START TRANSACTION;

UPDATE users
SET role = 'employee'
WHERE role IN ('coordinator', 'operator', 'viewer');

ALTER TABLE users
    MODIFY COLUMN role ENUM('admin', 'employee') NOT NULL DEFAULT 'employee';

COMMIT;
