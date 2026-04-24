-- Phase 5 (do this last): normalization-oriented migration plan
-- Run in staging first, then production during maintenance window.

START TRANSACTION;

-- 1) Keep account approval index efficient for Super Admin queue.
CREATE INDEX idx_users_active_created_at ON users (is_active, created_at);

-- 2) Normalize project financial tracker references into a single relationship model.
-- New polymorphic style table for future writes (3NF-friendly for repeated financial entries).
CREATE TABLE IF NOT EXISTS project_financial_entries (
    id INT AUTO_INCREMENT PRIMARY KEY,
    project_type ENUM('FSPF', 'IDP', 'AFME') NOT NULL,
    project_id INT NOT NULL,
    record_type ENUM('Obligation', 'Disbursement', 'Liquidation') NOT NULL,
    amount DECIMAL(15, 2) NOT NULL,
    reference_number VARCHAR(100),
    particulars TEXT,
    record_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    record_status ENUM('Active', 'Archived') DEFAULT 'Active',
    recorded_by INT,
    INDEX idx_project_lookup (project_type, project_id, record_type),
    INDEX idx_record_date (record_date),
    FOREIGN KEY (recorded_by) REFERENCES users(id)
);

-- 3) Optional data migration from denormalized tracker.
INSERT INTO project_financial_entries (
    project_type, project_id, record_type, amount, reference_number, particulars, record_date, record_status, recorded_by
)
SELECT
    'FSPF', fspf_project_id, record_type, amount, reference_number, particulars, record_date, record_status, recorded_by
FROM project_financial_tracker
WHERE fspf_project_id IS NOT NULL
UNION ALL
SELECT
    'IDP', idp_project_id, record_type, amount, reference_number, particulars, record_date, record_status, recorded_by
FROM project_financial_tracker
WHERE idp_project_id IS NOT NULL
UNION ALL
SELECT
    'AFME', afme_project_id, record_type, amount, reference_number, particulars, record_date, record_status, recorded_by
FROM project_financial_tracker
WHERE afme_project_id IS NOT NULL;

-- 4) Keep legacy table for backward compatibility.
-- Drop only after app fully reads/writes project_financial_entries.
-- DROP TABLE project_financial_tracker;

COMMIT;
