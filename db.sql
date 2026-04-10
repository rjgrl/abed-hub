SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS audit_log;
DROP TABLE IF EXISTS potential_duplicates;
DROP TABLE IF EXISTS geotagged_photos;
DROP TABLE IF EXISTS project_liquidations;
DROP TABLE IF EXISTS project_disbursements;
DROP TABLE IF EXISTS project_obligations;
DROP TABLE IF EXISTS financial_progress;
DROP TABLE IF EXISTS s_curve_monitoring;
DROP TABLE IF EXISTS program_of_works;
DROP TABLE IF EXISTS project_status_updates;
DROP TABLE IF EXISTS project_documents;
DROP TABLE IF EXISTS project_milestones;
DROP TABLE IF EXISTS afme_machinery_operation;
DROP TABLE IF EXISTS afme_machinery_turnover;
DROP TABLE IF EXISTS afme_machinery_delivery;
DROP TABLE IF EXISTS afme_machinery_documents;
DROP TABLE IF EXISTS afme_machinery_milestones;
DROP TABLE IF EXISTS afme_machinery_validation;
DROP TABLE IF EXISTS afme_machinery_specs;
DROP TABLE IF EXISTS afme_machinery;
DROP TABLE IF EXISTS afme_projects;
DROP TABLE IF EXISTS idp_projects;
DROP TABLE IF EXISTS fspf_projects;
DROP TABLE IF EXISTS password_reset_tokens;
DROP TABLE IF EXISTS users;

SET FOREIGN_KEY_CHECKS = 1;

CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(255) UNIQUE NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    full_name VARCHAR(255) NOT NULL,
    employee_id VARCHAR(255) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin', 'operator', 'viewer') DEFAULT 'operator',
    office_unit VARCHAR(255),
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_email (email),
    INDEX idx_username (username),
    INDEX idx_role (role)
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
    INDEX idx_token (token),
    INDEX idx_expires_at (expires_at)
);

-- =====================================================
-- FSPF PROJECTS (Farm Structure and Processing Facilities)
-- =====================================================

CREATE TABLE fspf_projects (
    id INT AUTO_INCREMENT PRIMARY KEY,
    project_insertion BOOLEAN DEFAULT FALSE,
    project_code VARCHAR(100) UNIQUE NOT NULL,
    classification VARCHAR(255),
    project_type VARCHAR(255),
    scope_of_work ENUM('Construction', 'Rehabilitation', 'Upgrading', 'Additional Work'),
    project_title VARCHAR(255) NOT NULL,
    fund_source VARCHAR(255),
    source_agency VARCHAR(255),
    beneficiary VARCHAR(255),
    description TEXT,
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
    barangay_ids JSON,
    households_benefited INT,
    
    current_stage ENUM('Proposal','Pre-Implementation','Procurement','Implementation','Completed') DEFAULT 'Proposal',
    proposal_status ENUM('For Validation','Proposal Validated','Not Feasible','Archived','Cancelled') DEFAULT 'For Validation',
    
    date_validation_start DATE,
    date_validation_end DATE,
    validation_report_path VARCHAR(500),
    validation_length_km DECIMAL(10, 2),
    validation_kml_path VARCHAR(500),
    implementing_office VARCHAR(255),
    implementation_type VARCHAR(255),
    
    latitude DECIMAL(11,8),
    longitude DECIMAL(11,8),
    is_fmr_project BOOLEAN DEFAULT FALSE,
    approved_date DATE,
    approval_remarks TEXT,
    
    physical_progress INT DEFAULT 0,
    financial_progress INT DEFAULT 0,
    
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id),
    INDEX idx_status (proposal_status),
    INDEX idx_stage (current_stage),
    INDEX idx_funding_year (funding_year),
    INDEX idx_project_code (project_code)
);

-- =====================================================
-- IDP PROJECTS (Irrigation Development Projects)
-- =====================================================

CREATE TABLE idp_projects (
    id INT AUTO_INCREMENT PRIMARY KEY,
    project_insertion BOOLEAN DEFAULT FALSE,
    project_code VARCHAR(100) UNIQUE NOT NULL,
    classification VARCHAR(255),
    project_type VARCHAR(255),
    scope_of_work ENUM('Construction', 'Rehabilitation', 'Upgrading', 'Additional Work'),
    project_title VARCHAR(255) NOT NULL,
    fund_source VARCHAR(255),
    source_agency VARCHAR(255),
    beneficiary VARCHAR(255),
    description TEXT,
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
    barangay_ids JSON,
    households_benefited INT,
    
    current_stage ENUM('Proposal','Pre-Implementation','Procurement','Implementation','Completed') DEFAULT 'Proposal',
    proposal_status ENUM('For Validation','Proposal Validated','Not Feasible','Archived','Cancelled') DEFAULT 'For Validation',
    
    date_validation_start DATE,
    date_validation_end DATE,
    validation_report_path VARCHAR(500),
    validation_length_km DECIMAL(10, 2),
    validation_kml_path VARCHAR(500),
    implementing_office VARCHAR(255),
    implementation_type VARCHAR(255),
    
    latitude DECIMAL(11,8),
    longitude DECIMAL(11,8),
    is_fmr_project BOOLEAN DEFAULT FALSE,
    approved_date DATE,
    approval_remarks TEXT,
    
    physical_progress INT DEFAULT 0,
    financial_progress INT DEFAULT 0,
    
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id),
    INDEX idx_status (proposal_status),
    INDEX idx_stage (current_stage),
    INDEX idx_funding_year (funding_year),
    INDEX idx_project_code (project_code)
);

-- =====================================================
-- AFME PROJECTS (Agricultural and Fisheries Machineries and Equipment)
-- =====================================================

CREATE TABLE afme_projects (
    id INT AUTO_INCREMENT PRIMARY KEY,
    project_insertion BOOLEAN DEFAULT FALSE,
    project_code VARCHAR(100) UNIQUE NOT NULL,
    classification VARCHAR(255),
    project_type VARCHAR(255),
    project_title VARCHAR(255) NOT NULL,
    fund_source VARCHAR(255),
    source_agency VARCHAR(255),
    beneficiary VARCHAR(255),
    description TEXT,
    funding_year INT,
    proposed_amount DECIMAL(15, 2),
    allocated_amount DECIMAL(15, 2),
    date_receipt_request DATE,
    
    current_stage ENUM('Proposal','Pre-Implementation','Procurement','Implementation','Delivered','Turned-Over','Operation and Maintenance') DEFAULT 'Proposal',
    proposal_status ENUM('For Validation','Proposal Validated','Not Feasible','Archived','Cancelled') DEFAULT 'For Validation',
    
    latitude DECIMAL(11,8),
    longitude DECIMAL(11,8),
    implementing_office VARCHAR(255),
    
    date_completion DATE,
    is_active BOOLEAN DEFAULT TRUE,
    
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id),
    INDEX idx_status (proposal_status),
    INDEX idx_stage (current_stage),
    INDEX idx_funding_year (funding_year),
    INDEX idx_project_code (project_code)
);

-- =====================================================
-- AFME MACHINERY
-- =====================================================

CREATE TABLE afme_machinery (
    id INT AUTO_INCREMENT PRIMARY KEY,
    afme_project_id INT NOT NULL,
    machine_name VARCHAR(255) NOT NULL,
    machine_id VARCHAR(100),
    farm_operation VARCHAR(255),
    beneficiary_name VARCHAR(255),
    beneficiary_contact VARCHAR(255),
    recipient_type ENUM('Registered Farmers Organization','Farmers Cooperative','Agrarian Reform Beneficiary Organization','Rural-based Organization','Local Government Unit','Agricultural School','University or College','Others') DEFAULT 'Farmers Cooperative',
    farm_location VARCHAR(255),
    beneficiary_households INT,
    description TEXT,
    proposed_amount DECIMAL(15, 2),
    allocated_amount DECIMAL(15, 2),
    funding_year INT,
    indicative_funding_year INT,
    fund_source VARCHAR(255),
    current_status ENUM('For Validation','Proposal Validated','Not Feasible','Archived','Cancelled','Pre-Implementation','Procurement','Implementation','Delivered','Turned-Over') DEFAULT 'For Validation',
    date_receipt DATE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (afme_project_id) REFERENCES afme_projects(id) ON DELETE CASCADE,
    INDEX idx_status (current_status),
    INDEX idx_funding_year (funding_year),
    INDEX idx_project (afme_project_id)
);

CREATE TABLE afme_machinery_specs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    machinery_id INT NOT NULL,
    machinery_type VARCHAR(255),
    mode_of_procurement ENUM('Public Bidding','Small Value Procurement'),
    brand VARCHAR(255),
    engine_type VARCHAR(255),
    serial_number VARCHAR(100),
    chassis_serial_number VARCHAR(100),
    specifications TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (machinery_id) REFERENCES afme_machinery(id) ON DELETE CASCADE,
    UNIQUE KEY unique_machinery (machinery_id)
);

CREATE TABLE afme_machinery_validation (
    id INT AUTO_INCREMENT PRIMARY KEY,
    machinery_id INT NOT NULL,
    date_validation_start DATE,
    date_validation_end DATE,
    implementation_type ENUM('Operating Unit','Beneficiary'),
    service_area VARCHAR(255),
    validation_report_path VARCHAR(500),
    geotagged_photo_paths JSON,
    latitude DECIMAL(11,8),
    longitude DECIMAL(11,8),
    validation_status ENUM('Completed','Pending') DEFAULT 'Pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (machinery_id) REFERENCES afme_machinery(id) ON DELETE CASCADE,
    UNIQUE KEY unique_machinery (machinery_id)
);

CREATE TABLE afme_machinery_milestones (
    id INT AUTO_INCREMENT PRIMARY KEY,
    machinery_id INT NOT NULL,
    stage ENUM('Proposal','Pre-Implementation','Procurement','Implementation','Delivered','Turned-Over'),
    milestone_name VARCHAR(255),
    target_date DATE,
    actual_date DATE,
    remarks TEXT,
    factors_affecting_progress TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (machinery_id) REFERENCES afme_machinery(id) ON DELETE CASCADE,
    INDEX idx_stage (stage),
    INDEX idx_machinery (machinery_id)
);

CREATE TABLE afme_machinery_documents (
    id INT AUTO_INCREMENT PRIMARY KEY,
    machinery_id INT NOT NULL,
    doc_type ENUM('LGU Deed of Donation','Proof of Shed','Accreditation Certificate','Proof of Organization Structure','Validation Report','Geotagged Photo','Others'),
    file_path VARCHAR(500),
    file_name VARCHAR(255),
    stage VARCHAR(50),
    upload_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    uploaded_by INT,
    FOREIGN KEY (machinery_id) REFERENCES afme_machinery(id) ON DELETE CASCADE,
    FOREIGN KEY (uploaded_by) REFERENCES users(id),
    INDEX idx_doc_type (doc_type),
    INDEX idx_machinery (machinery_id)
);

CREATE TABLE afme_machinery_delivery (
    id INT AUTO_INCREMENT PRIMARY KEY,
    machinery_id INT NOT NULL,
    notice_to_proceed_date DATE,
    delivery_date DATE,
    delivery_status ENUM('Not Delivered','In Transit','Delivered','Received') DEFAULT 'Not Delivered',
    delivery_location VARCHAR(255),
    delivery_remarks TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (machinery_id) REFERENCES afme_machinery(id) ON DELETE CASCADE,
    UNIQUE KEY unique_machinery (machinery_id)
);

CREATE TABLE afme_machinery_turnover (
    id INT AUTO_INCREMENT PRIMARY KEY,
    machinery_id INT NOT NULL,
    turnover_date DATE,
    turnover_status ENUM('Pending','Completed') DEFAULT 'Pending',
    documentary_requirements_met BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (machinery_id) REFERENCES afme_machinery(id) ON DELETE CASCADE,
    UNIQUE KEY unique_machinery (machinery_id)
);

CREATE TABLE afme_machinery_operation (
    id INT AUTO_INCREMENT PRIMARY KEY,
    machinery_id INT NOT NULL,
    operation_status ENUM('Operational','Non-operational','Intermittently Operational'),
    serviceability_status VARCHAR(255),
    operational_remarks TEXT,
    last_maintenance_date DATE,
    maintenance_remarks TEXT,
    audit_date DATE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (machinery_id) REFERENCES afme_machinery(id) ON DELETE CASCADE,
    INDEX idx_machinery (machinery_id)
);

-- =====================================================
-- PROJECT MILESTONES AND TRACKING
-- =====================================================

CREATE TABLE project_milestones (
    id INT AUTO_INCREMENT PRIMARY KEY,
    project_type ENUM('FSPF', 'IDP', 'AFME'),
    project_id INT NOT NULL,
    stage ENUM('Proposal','Pre-Implementation','Procurement','Implementation','Completed'),
    milestone_name VARCHAR(255) NOT NULL,
    target_date DATE,
    actual_date DATE,
    remarks TEXT,
    factors_affecting_progress TEXT,
    is_critical BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_project (project_type, project_id),
    INDEX idx_stage (stage)
);

CREATE TABLE project_documents (
    id INT AUTO_INCREMENT PRIMARY KEY,
    project_type ENUM('FSPF', 'IDP', 'AFME'),
    project_id INT NOT NULL,
    stage VARCHAR(50),
    doc_type ENUM('Validation Report','Geotagged Photo','KML','POW','ES','DED','Feasibility Study','Others'),
    file_path VARCHAR(500) NOT NULL,
    file_name VARCHAR(255),
    upload_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    uploaded_by INT,
    FOREIGN KEY (uploaded_by) REFERENCES users(id),
    INDEX idx_project (project_type, project_id),
    INDEX idx_doc_type (doc_type)
);

CREATE TABLE project_status_updates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    project_type ENUM('FSPF', 'IDP', 'AFME'),
    project_id INT NOT NULL,
    previous_stage VARCHAR(50),
    new_stage VARCHAR(50),
    previous_status VARCHAR(50),
    new_status VARCHAR(50),
    update_remarks TEXT,
    update_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_by INT,
    FOREIGN KEY (updated_by) REFERENCES users(id),
    INDEX idx_update_time (update_time),
    INDEX idx_project (project_type, project_id)
);

CREATE TABLE program_of_works (
    id INT AUTO_INCREMENT PRIMARY KEY,
    project_type ENUM('FSPF', 'IDP', 'AFME'),
    project_id INT NOT NULL,
    pow_file_path VARCHAR(500),
    upload_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    uploaded_by INT,
    is_current BOOLEAN DEFAULT TRUE,
    FOREIGN KEY (uploaded_by) REFERENCES users(id),
    INDEX idx_project (project_type, project_id)
);

CREATE TABLE s_curve_monitoring (
    id INT AUTO_INCREMENT PRIMARY KEY,
    project_type ENUM('FSPF', 'IDP', 'AFME'),
    project_id INT NOT NULL,
    target_physical_progress INT,
    actual_physical_progress INT,
    slippage_status ENUM('On Track','Delayed','Critical') DEFAULT 'On Track',
    slippage_days INT,
    recorded_date DATE,
    remarks TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_project_date (project_type, project_id, recorded_date)
);

CREATE TABLE financial_progress (
    id INT AUTO_INCREMENT PRIMARY KEY,
    project_type ENUM('FSPF', 'IDP', 'AFME'),
    project_id INT NOT NULL,
    total_project_cost DECIMAL(15, 2),
    mobilization_percentage DECIMAL(5, 2),
    target_financial_progress INT,
    actual_financial_progress INT,
    recorded_date DATE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_project (project_type, project_id)
);

CREATE TABLE project_obligations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    project_type ENUM('FSPF', 'IDP', 'AFME'),
    project_id INT NOT NULL,
    cbr_number VARCHAR(100),
    obligation_amount DECIMAL(15, 2),
    obligation_date DATE,
    recorded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_project (project_type, project_id)
);

CREATE TABLE project_disbursements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    project_type ENUM('FSPF', 'IDP', 'AFME'),
    project_id INT NOT NULL,
    disbursement_amount DECIMAL(15, 2),
    disbursement_date DATE,
    check_number VARCHAR(100),
    remarks TEXT,
    recorded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_project (project_type, project_id)
);

CREATE TABLE project_liquidations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    project_type ENUM('FSPF', 'IDP', 'AFME'),
    project_id INT NOT NULL,
    total_disbursements DECIMAL(15, 2),
    total_obligations DECIMAL(15, 2),
    liquidated_amount DECIMAL(15, 2),
    recorded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_project (project_type, project_id)
);

CREATE TABLE geotagged_photos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    project_type ENUM('FSPF', 'IDP', 'AFME'),
    project_id INT NOT NULL,
    photo_path VARCHAR(500),
    latitude DECIMAL(11,8),
    longitude DECIMAL(11,8),
    photo_date DATE,
    stage VARCHAR(50),
    upload_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    uploaded_by INT,
    FOREIGN KEY (uploaded_by) REFERENCES users(id),
    INDEX idx_project (project_type, project_id)
);

CREATE TABLE project_financial_tracker (
    id INT AUTO_INCREMENT PRIMARY KEY,
    fspf_project_id INT,
    idp_project_id INT,
    afme_project_id INT,
    record_type ENUM('Obligation', 'Disbursement', 'Liquidation'),
    amount DECIMAL(15, 2) NOT NULL,
    reference_number VARCHAR(100),
    particulars TEXT,
    record_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    record_status ENUM('Active', 'Archived') DEFAULT 'Active',
    recorded_by INT,
    FOREIGN KEY (recorded_by) REFERENCES users(id),
    FOREIGN KEY (fspf_project_id) REFERENCES fspf_projects(id) ON DELETE CASCADE,
    FOREIGN KEY (idp_project_id) REFERENCES idp_projects(id) ON DELETE CASCADE,
    FOREIGN KEY (afme_project_id) REFERENCES afme_projects(id) ON DELETE CASCADE,
    INDEX idx_fspf (fspf_project_id),
    INDEX idx_idp (idp_project_id),
    INDEX idx_afme (afme_project_id),
    INDEX idx_record_type (record_type),
    INDEX idx_record_date (record_date)
);

CREATE TABLE potential_duplicates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    project_type ENUM('FSPF', 'IDP', 'AFME'),
    project_id_1 INT NOT NULL,
    project_id_2 INT NOT NULL,
    similarity_score DECIMAL(3, 2),
    reason TEXT,
    status ENUM('pending', 'merged', 'dismissed') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_status (status)
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
    INDEX idx_created_at (created_at),
    INDEX idx_user_id (user_id)
);