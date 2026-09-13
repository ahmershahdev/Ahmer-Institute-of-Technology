-- AIT Admissions Database
-- MySQL 8.0+, InnoDB, utf8mb4.
-- Cardinalities:
--   students 1:N applications (one application per admission cycle)
--   applications 1:N documents, academic_records, status_history
--   applications 1:1 challans (the unique application_id enforces this)
--   applications N:1 campuses/faculties/programs through the catalog IDs

CREATE DATABASE IF NOT EXISTS `ait`
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE `ait`;

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE TABLE IF NOT EXISTS admissions_cycles (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    code VARCHAR(30) NOT NULL,
    name VARCHAR(120) NOT NULL,
    opens_at DATETIME NULL,
    closes_at DATETIME NULL,
    status ENUM('draft', 'open', 'closed', 'archived') NOT NULL DEFAULT 'draft',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_admissions_cycles_code (code),
    KEY idx_admissions_cycles_status_dates (status, opens_at, closes_at),
    CONSTRAINT chk_admissions_cycles_dates CHECK (closes_at IS NULL OR opens_at IS NULL OR closes_at > opens_at)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS students (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(150) NOT NULL,
    father_name VARCHAR(150) NULL,
    email VARCHAR(190) NOT NULL,
    cnic VARCHAR(20) NULL,
    dob DATE NULL,
    phone VARCHAR(30) NULL,
    password VARCHAR(255) NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    last_login_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_students_email (email),
    UNIQUE KEY uq_students_cnic (cnic),
    KEY idx_students_active (is_active),
    CONSTRAINT chk_students_email CHECK (email LIKE '%@%')
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS admins (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(150) NOT NULL,
    email VARCHAR(190) NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('super_admin', 'sub_admin') NOT NULL DEFAULT 'sub_admin',
    role_title VARCHAR(120) NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    expires_at DATETIME NULL,
    last_login_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_admins_email (email),
    KEY idx_admins_role_active (role, is_active),
    CONSTRAINT chk_admins_expiry CHECK (expires_at IS NULL OR expires_at > created_at)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS campuses (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(180) NOT NULL,
    code VARCHAR(30) NOT NULL,
    city VARCHAR(100) NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    PRIMARY KEY (id),
    UNIQUE KEY uq_campuses_code (code),
    UNIQUE KEY uq_campuses_name (name)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS faculties (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(180) NOT NULL,
    code VARCHAR(30) NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    PRIMARY KEY (id),
    UNIQUE KEY uq_faculties_code (code),
    UNIQUE KEY uq_faculties_name (name)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS programs (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    faculty_id BIGINT UNSIGNED NULL,
    code VARCHAR(30) NOT NULL,
    name VARCHAR(180) NOT NULL,
    degree_level ENUM('Diploma', 'BS', 'MS', 'MPhil', 'PhD') NOT NULL,
    duration_years DECIMAL(3,1) NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    PRIMARY KEY (id),
    UNIQUE KEY uq_programs_code (code),
    UNIQUE KEY uq_programs_faculty_name_level (faculty_id, name, degree_level),
    KEY idx_programs_level_active (degree_level, is_active),
    CONSTRAINT fk_programs_faculty FOREIGN KEY (faculty_id) REFERENCES faculties (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS applications (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    student_id BIGINT UNSIGNED NOT NULL,
    cycle_id BIGINT UNSIGNED NULL,
    campus_id BIGINT UNSIGNED NULL,
    faculty_id BIGINT UNSIGNED NULL,
    program_id BIGINT UNSIGNED NULL,

    -- Compatibility fields used by the current PHP pages.
    first_name VARCHAR(80) NULL,
    last_name VARCHAR(80) NULL,
    full_name VARCHAR(180) NOT NULL,
    father_name VARCHAR(180) NOT NULL,
    father_cnic VARCHAR(20) NULL,
    caste VARCHAR(80) NULL,
    department VARCHAR(180) NULL,
    country VARCHAR(80) NOT NULL DEFAULT 'Pakistan',
    state VARCHAR(100) NULL,
    city VARCHAR(100) NULL,
    cnic VARCHAR(20) NOT NULL,
    dob DATE NULL,
    gender ENUM('Male', 'Female', 'Other') NULL,
    religion VARCHAR(80) NULL,
    nationality VARCHAR(80) NOT NULL DEFAULT 'Pakistani',
    domicile_province VARCHAR(100) NULL,
    district_city VARCHAR(120) NULL,
    phone VARCHAR(30) NOT NULL,
    alt_phone VARCHAR(30) NULL,
    email VARCHAR(190) NOT NULL,
    permanent_address TEXT NULL,
    postal_address TEXT NULL,

    matric_board VARCHAR(180) NULL,
    matric_roll VARCHAR(80) NULL,
    matric_reg VARCHAR(80) NULL,
    matric_year SMALLINT UNSIGNED NULL,
    matric_group VARCHAR(80) NULL,
    matric_total_marks DECIMAL(8,2) NULL,
    matric_obtained_marks DECIMAL(8,2) NULL,
    matric_percentage DECIMAL(5,2) NULL,
    inter_board VARCHAR(180) NULL,
    inter_roll VARCHAR(80) NULL,
    inter_reg VARCHAR(80) NULL,
    inter_year SMALLINT UNSIGNED NULL,
    inter_group VARCHAR(80) NULL,
    inter_total_marks DECIMAL(8,2) NULL,
    inter_obtained_marks DECIMAL(8,2) NULL,
    inter_percentage DECIMAL(5,2) NULL,

    campus VARCHAR(180) NULL,
    faculty VARCHAR(180) NULL,
    degree_level VARCHAR(30) NULL,
    shift ENUM('Morning', 'Evening') NULL,
    pref_1 VARCHAR(180) NULL,
    pref_2 VARCHAR(180) NULL,
    pref_3 VARCHAR(180) NULL,
    test_roll_no VARCHAR(80) NULL,
    test_date DATE NULL,
    challan_pic VARCHAR(255) NULL,
    status ENUM('applied', 'challan_uploaded', 'verified', 'approved', 'rejected') NOT NULL DEFAULT 'applied',
    review_note TEXT NULL,
    reviewed_by BIGINT UNSIGNED NULL,
    reviewed_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_applications_cycle_cnic (cycle_id, cnic),
    KEY idx_applications_student_status (student_id, status, id),
    KEY idx_applications_status_created (status, created_at),
    KEY idx_applications_program (program_id, cycle_id),
    CONSTRAINT fk_applications_student FOREIGN KEY (student_id) REFERENCES students (id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_applications_cycle FOREIGN KEY (cycle_id) REFERENCES admissions_cycles (id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_applications_campus FOREIGN KEY (campus_id) REFERENCES campuses (id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_applications_faculty FOREIGN KEY (faculty_id) REFERENCES faculties (id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_applications_program FOREIGN KEY (program_id) REFERENCES programs (id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_applications_reviewer FOREIGN KEY (reviewed_by) REFERENCES admins (id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT chk_applications_marks CHECK (matric_obtained_marks IS NULL OR matric_total_marks IS NULL OR matric_obtained_marks <= matric_total_marks),
    CONSTRAINT chk_applications_inter_marks CHECK (inter_obtained_marks IS NULL OR inter_total_marks IS NULL OR inter_obtained_marks <= inter_total_marks)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS academic_records (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    application_id BIGINT UNSIGNED NOT NULL,
    qualification ENUM('matric', 'intermediate', 'diploma', 'bachelors', 'masters') NOT NULL,
    board VARCHAR(180) NOT NULL,
    roll_no VARCHAR(80) NULL,
    registration_no VARCHAR(80) NULL,
    passing_year SMALLINT UNSIGNED NULL,
    group_name VARCHAR(100) NULL,
    total_marks DECIMAL(8,2) NULL,
    obtained_marks DECIMAL(8,2) NULL,
    percentage DECIMAL(5,2) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_academic_records_application_qualification (application_id, qualification),
    KEY idx_academic_records_application (application_id),
    CONSTRAINT fk_academic_records_application FOREIGN KEY (application_id) REFERENCES applications (id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT chk_academic_records_marks CHECK (obtained_marks IS NULL OR total_marks IS NULL OR obtained_marks <= total_marks)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS documents (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    application_id BIGINT UNSIGNED NOT NULL,
    doc_type ENUM('10th_marksheet', '12th_marksheet', 'b_form', 'domicile', 'profile_picture', 'challan_receipt', 'other') NOT NULL,
    file_url VARCHAR(500) NOT NULL,
    original_name VARCHAR(255) NULL,
    mime_type VARCHAR(100) NULL,
    file_size BIGINT UNSIGNED NULL,
    checksum CHAR(64) NULL,
    is_current TINYINT(1) NOT NULL DEFAULT 1,
    uploaded_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_documents_application_type (application_id, doc_type, is_current),
    KEY idx_documents_checksum (checksum),
    CONSTRAINT fk_documents_application FOREIGN KEY (application_id) REFERENCES applications (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS challans (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    application_id BIGINT UNSIGNED NOT NULL,
    bank_name VARCHAR(100) NOT NULL DEFAULT 'HBL',
    challan_no VARCHAR(60) NOT NULL,
    amount DECIMAL(12,2) NOT NULL,
    due_date DATE NULL,
    paid_at DATETIME NULL,
    status ENUM('unpaid', 'uploaded', 'verified', 'rejected', 'expired') NOT NULL DEFAULT 'unpaid',
    verified_by BIGINT UNSIGNED NULL,
    verified_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_challans_application (application_id),
    UNIQUE KEY uq_challans_number (challan_no),
    KEY idx_challans_status_due_date (status, due_date),
    CONSTRAINT fk_challans_application FOREIGN KEY (application_id) REFERENCES applications (id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_challans_verifier FOREIGN KEY (verified_by) REFERENCES admins (id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT chk_challans_amount CHECK (amount >= 0)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS application_program_choices (
    application_id BIGINT UNSIGNED NOT NULL,
    preference_no TINYINT UNSIGNED NOT NULL,
    program_id BIGINT UNSIGNED NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (application_id, preference_no),
    UNIQUE KEY uq_application_program_choice (application_id, program_id),
    CONSTRAINT fk_choices_application FOREIGN KEY (application_id) REFERENCES applications (id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_choices_program FOREIGN KEY (program_id) REFERENCES programs (id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT chk_choices_preference CHECK (preference_no BETWEEN 1 AND 3)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS application_status_history (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    application_id BIGINT UNSIGNED NOT NULL,
    from_status ENUM('applied', 'challan_uploaded', 'verified', 'approved', 'rejected') NULL,
    to_status ENUM('applied', 'challan_uploaded', 'verified', 'approved', 'rejected') NOT NULL,
    note TEXT NULL,
    changed_by BIGINT UNSIGNED NULL,
    changed_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_status_history_application_date (application_id, changed_at),
    CONSTRAINT fk_status_history_application FOREIGN KEY (application_id) REFERENCES applications (id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_status_history_admin FOREIGN KEY (changed_by) REFERENCES admins (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB;

DROP VIEW IF EXISTS v_application_overview;
CREATE VIEW v_application_overview AS
SELECT
    a.id AS application_id,
    a.student_id,
    s.email AS student_email,
    a.cycle_id,
    c.name AS cycle_name,
    a.full_name,
    a.father_name,
    a.cnic,
    a.phone,
    a.email,
    a.status,
    a.review_note,
    a.created_at,
    a.updated_at,
    ca.name AS campus_name,
    f.name AS faculty_name,
    p.name AS program_name,
    p.degree_level,
    ch.challan_no,
    ch.amount AS challan_amount,
    ch.status AS challan_status,
    ch.paid_at,
    MAX(CASE WHEN d.doc_type = 'profile_picture' AND d.is_current = 1 THEN d.file_url END) AS profile_pic,
    MAX(CASE WHEN d.doc_type = 'challan_receipt' AND d.is_current = 1 THEN d.file_url END) AS challan_receipt
FROM applications a
JOIN students s ON s.id = a.student_id
LEFT JOIN admissions_cycles c ON c.id = a.cycle_id
LEFT JOIN campuses ca ON ca.id = a.campus_id
LEFT JOIN faculties f ON f.id = a.faculty_id
LEFT JOIN programs p ON p.id = a.program_id
LEFT JOIN challans ch ON ch.application_id = a.id
LEFT JOIN documents d ON d.application_id = a.id
GROUP BY a.id;

DROP VIEW IF EXISTS v_admission_dashboard_metrics;
CREATE VIEW v_admission_dashboard_metrics AS
SELECT
    COALESCE(cycle_id, 0) AS cycle_id,
    COUNT(*) AS total_applications,
    SUM(status IN ('applied', 'challan_uploaded', 'verified')) AS pending_applications,
    SUM(status = 'challan_uploaded') AS challan_uploaded,
    SUM(status = 'approved') AS approved_applications,
    SUM(status = 'rejected') AS rejected_applications,
    ROUND(100 * SUM(status = 'approved') / NULLIF(COUNT(*), 0), 2) AS approval_rate
FROM applications
GROUP BY COALESCE(applications.cycle_id, 0);

DROP TRIGGER IF EXISTS trg_applications_status_history_insert;
DROP TRIGGER IF EXISTS trg_applications_status_history_update;

DELIMITER //
CREATE TRIGGER trg_applications_status_history_insert
AFTER INSERT ON applications
FOR EACH ROW
BEGIN
    INSERT INTO application_status_history (application_id, from_status, to_status, changed_by)
    VALUES (NEW.id, NULL, NEW.status, NEW.reviewed_by);
END//

CREATE TRIGGER trg_applications_status_history_update
AFTER UPDATE ON applications
FOR EACH ROW
BEGIN
    IF NOT (OLD.status <=> NEW.status) THEN
        INSERT INTO application_status_history (application_id, from_status, to_status, note, changed_by)
        VALUES (NEW.id, OLD.status, NEW.status, NEW.review_note, NEW.reviewed_by);
    END IF;
END//
DELIMITER ;

SET FOREIGN_KEY_CHECKS = 1;

-- Optional catalog seed data. These inserts are idempotent and safe to rerun.
INSERT INTO campuses (name, code, city) VALUES
    ('Main Campus Jamshoro', 'JAMSHORO', 'Jamshoro'),
    ('AIT SZAB Campus Khairpur', 'KHAIRPUR', 'Khairpur')
ON DUPLICATE KEY UPDATE name = VALUES(name), city = VALUES(city);

INSERT INTO faculties (name, code) VALUES
    ('Electrical, Electronic & Computer Engineering', 'EECE'),
    ('Civil & Architecture', 'CIVARCH'),
    ('Mechanical & Process Engineering', 'MECHPROC'),
    ('Basic Sciences & Humanities', 'BSH')
ON DUPLICATE KEY UPDATE name = VALUES(name);

INSERT INTO programs (faculty_id, code, name, degree_level)
SELECT f.id, p.code, p.name, 'BS'
FROM faculties f
JOIN (
    SELECT 'SE' AS code, 'Software Engineering' AS name, 'EECE' AS faculty_code
    UNION ALL SELECT 'CSE', 'Computer Systems Engineering', 'EECE'
    UNION ALL SELECT 'AI', 'Artificial Intelligence', 'EECE'
    UNION ALL SELECT 'CE', 'Civil Engineering', 'CIVARCH'
    UNION ALL SELECT 'ELEC', 'Electrical Engineering', 'EECE'
    UNION ALL SELECT 'ME', 'Mechanical Engineering', 'MECHPROC'
    UNION ALL SELECT 'CYBER', 'Cyber Security', 'EECE'
) p ON p.faculty_code = f.code
ON DUPLICATE KEY UPDATE faculty_id = VALUES(faculty_id), name = VALUES(name);
