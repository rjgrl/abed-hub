-- ABED IDM Hub - Refactored clean schema (fresh install)
-- Core domain consolidated into:
--   1) projects
--   2) afme
--
-- Fresh-install parity note:
-- This file already includes the schema outcomes from current migrations:
--   - 2026_05_03_user_roles_admin_employee.sql
--   - 2026_05_03_drop_users_address_contact_number.sql
--   - 2026_05_05_users_google_oauth.sql
--   - 2026_05_06_projects_rejection_comment.sql
--   - 2026_05_06_users_rejection_reason.sql
-- so a new database can be created from this file alone.

SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS notifications;
DROP TABLE IF EXISTS project_alerts;
DROP TABLE IF EXISTS audit_log;
DROP TABLE IF EXISTS saved_views;
DROP TABLE IF EXISTS password_reset_tokens;
DROP TABLE IF EXISTS afme;
DROP TABLE IF EXISTS projects;
DROP TABLE IF EXISTS users;

SET FOREIGN_KEY_CHECKS = 1;

CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(255) UNIQUE NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    google_sub VARCHAR(255) NULL DEFAULT NULL,
    first_name VARCHAR(255) NOT NULL DEFAULT '',
    last_name VARCHAR(255) NOT NULL DEFAULT '',
    employee_id VARCHAR(255) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin', 'employee') NOT NULL DEFAULT 'employee',
    office_unit VARCHAR(255),
    is_active BOOLEAN DEFAULT TRUE,
    rejection_reason TEXT NULL,
    rejected_at DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_users_email (email),
    UNIQUE INDEX idx_users_google_sub (google_sub),
    INDEX idx_users_username (username),
    INDEX idx_users_role (role),
    INDEX idx_users_active_created_at (is_active, created_at)
);

CREATE TABLE password_reset_tokens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    token VARCHAR(255) UNIQUE NOT NULL,
    code VARCHAR(6) NOT NULL,
    expires_at TIMESTAMP NOT NULL,
    is_used BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_password_reset_token (token),
    INDEX idx_password_reset_expires_at (expires_at)
);

CREATE TABLE projects (
    id INT AUTO_INCREMENT PRIMARY KEY,
    project_type ENUM('fspf', 'idp', 'afme') NOT NULL,
    project_insertion BOOLEAN DEFAULT FALSE,
    project_code VARCHAR(100) UNIQUE NOT NULL,
    classification VARCHAR(255),
    project_category VARCHAR(255),
    scope_of_work ENUM('Construction', 'Rehabilitation', 'Upgrading', 'Additional Work'),
    title VARCHAR(255) NOT NULL,
    description TEXT,
    beneficiary VARCHAR(255),
    fund_source VARCHAR(255),
    source_agency VARCHAR(255),
    funding_year INT,
    proposed_amount DECIMAL(15, 2),
    allocated_amount DECIMAL(15, 2),
    date_receipt_request DATE,
    implementation_schedule_days INT,
    quantity DECIMAL(10, 2),
    unit VARCHAR(50),
    province VARCHAR(255),
    district VARCHAR(255),
    municipality VARCHAR(255),
    barangay VARCHAR(255),
    barangay_ids JSON,
    households_benefited INT,
    current_stage VARCHAR(80) DEFAULT 'Proposal',
    status VARCHAR(80) DEFAULT 'For Validation',
    approval_status ENUM('Pending', 'Approved', 'Rejected') DEFAULT 'Approved',
    rejection_comment TEXT NULL,
    date_validation_start DATE,
    date_validation_end DATE,
    validation_report_path VARCHAR(500),
    validation_length_km DECIMAL(10, 2),
    validation_kml_path VARCHAR(500),
    implementing_office VARCHAR(255),
    implementation_type VARCHAR(255),
    latitude DECIMAL(11, 8),
    longitude DECIMAL(11, 8),
    is_fmr_project BOOLEAN DEFAULT FALSE,
    approved_date DATE,
    approval_remarks TEXT,
    physical_progress INT DEFAULT 0,
    financial_progress INT DEFAULT 0,
    date_completion DATE,
    is_active BOOLEAN DEFAULT TRUE,
    milestones JSON,
    documents JSON,
    financial_entries JSON,
    user_id INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id),
    INDEX idx_projects_type (project_type),
    INDEX idx_projects_code (project_code),
    INDEX idx_projects_stage (current_stage),
    INDEX idx_projects_status (status),
    INDEX idx_projects_funding_year (funding_year),
    INDEX idx_projects_user_id (user_id)
);

CREATE TABLE afme (
    id INT AUTO_INCREMENT PRIMARY KEY,
    project_id INT NOT NULL,
    machine_name VARCHAR(255) NOT NULL,
    machine_id VARCHAR(100),
    machinery_type VARCHAR(255),
    mode_of_procurement ENUM('Public Bidding', 'Small Value Procurement'),
    brand VARCHAR(255),
    engine_type VARCHAR(255),
    serial_number VARCHAR(100),
    chassis_serial_number VARCHAR(100),
    specifications TEXT,
    farm_operation VARCHAR(255),
    beneficiary_name VARCHAR(255),
    beneficiary_contact VARCHAR(255),
    recipient_type VARCHAR(255),
    farm_location VARCHAR(255),
    beneficiary_households INT,
    service_area VARCHAR(255),
    amount_proposed DECIMAL(15, 2),
    amount_allocated DECIMAL(15, 2),
    funding_year INT,
    indicative_funding_year INT,
    fund_source VARCHAR(255),
    current_status VARCHAR(80) DEFAULT 'For Validation',
    date_receipt DATE,
    date_validation_start DATE,
    date_validation_end DATE,
    validation_status ENUM('Completed', 'Pending') DEFAULT 'Pending',
    validation_report_path VARCHAR(500),
    geotagged_photo_paths JSON,
    latitude DECIMAL(11, 8),
    longitude DECIMAL(11, 8),
    notice_to_proceed_date DATE,
    delivery_date DATE,
    delivery_status ENUM('Not Delivered', 'In Transit', 'Delivered', 'Received') DEFAULT 'Not Delivered',
    delivery_location VARCHAR(255),
    delivery_remarks TEXT,
    turnover_date DATE,
    turnover_status ENUM('Pending', 'Completed') DEFAULT 'Pending',
    documentary_requirements_met BOOLEAN DEFAULT FALSE,
    operation_status ENUM('Operational', 'Non-operational', 'Intermittently Operational'),
    serviceability_status VARCHAR(255),
    operational_remarks TEXT,
    last_maintenance_date DATE,
    maintenance_remarks TEXT,
    audit_date DATE,
    milestones JSON,
    documents JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
    INDEX idx_afme_project_id (project_id),
    INDEX idx_afme_status (current_status),
    INDEX idx_afme_delivery_status (delivery_status),
    INDEX idx_afme_turnover_status (turnover_status)
);

CREATE TABLE saved_views (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    view_name VARCHAR(255) NOT NULL,
    project_type ENUM('fspf', 'idp', 'afme') NOT NULL,
    filters JSON NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_saved_view (user_id, view_name, project_type),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE audit_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    action VARCHAR(255) NOT NULL,
    project_type VARCHAR(50),
    project_id INT,
    old_values JSON,
    new_values JSON,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE SET NULL,
    INDEX idx_audit_created_at (created_at),
    INDEX idx_audit_user_id (user_id),
    INDEX idx_audit_project_id (project_id)
);

CREATE TABLE project_alerts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    project_id INT NOT NULL,
    alert_type VARCHAR(50) NOT NULL,
    severity ENUM('low', 'medium', 'high', 'critical') DEFAULT 'medium',
    message TEXT NOT NULL,
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    is_active BOOLEAN DEFAULT TRUE,
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id),
    INDEX idx_alert_project_active (project_id, is_active),
    INDEX idx_alert_created_at (created_at)
);

CREATE TABLE notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    project_id INT,
    alert_type VARCHAR(50),
    title VARCHAR(255) NOT NULL,
    message TEXT,
    is_read BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
    INDEX idx_notifications_user_unread (user_id, is_read),
    INDEX idx_notifications_created_at (created_at)
);
