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
    student_code VARCHAR(20) NULL,
    department_code VARCHAR(20) NULL,
    admission_year SMALLINT UNSIGNED NULL,
    roll_number INT UNSIGNED NULL,
    current_semester TINYINT UNSIGNED NOT NULL DEFAULT 1,
    must_change_password TINYINT(1) NOT NULL DEFAULT 1,
    admitted_at DATETIME NULL,
    address TEXT NULL,
    profile_picture VARCHAR(500) NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    last_login_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_students_email (email),
    UNIQUE KEY uq_students_cnic (cnic),
    UNIQUE KEY uq_students_student_code (student_code),
    UNIQUE KEY uq_students_year_department_roll (admission_year, department_code, roll_number),
    KEY idx_students_active (is_active),
    CONSTRAINT chk_students_email CHECK (email LIKE '%@%')
) ENGINE=InnoDB;

INSERT INTO students (name, father_name, email, cnic, dob, phone, password, student_code, department_code, admission_year, roll_number, must_change_password, admitted_at, is_active)
VALUES ('Demo Student', 'AIT Test Account', 'demo.student@ait.test', NULL, '2004-01-01', '+923000000000', '$2y$10$uM0vebdWZRzTJFXl1u87H.5tkjpoXlV8BmawpqLgqGal5lXd48djy', '24BSCS001', 'BSCS', 2024, 1, 0, NOW(), 1)
ON DUPLICATE KEY UPDATE name = VALUES(name), password = VALUES(password), student_code = VALUES(student_code), department_code = VALUES(department_code), admission_year = VALUES(admission_year), roll_number = VALUES(roll_number), must_change_password = 0, admitted_at = COALESCE(admitted_at, NOW()), is_active = 1;

CREATE TABLE IF NOT EXISTS subjects (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    department_code VARCHAR(20) NOT NULL,
    code VARCHAR(30) NOT NULL,
    name VARCHAR(180) NOT NULL,
    semester TINYINT UNSIGNED NOT NULL,
    credit_hours TINYINT UNSIGNED NOT NULL DEFAULT 3,
    teacher_name VARCHAR(150) NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    PRIMARY KEY (id),
    UNIQUE KEY uq_subjects_code_semester (code, semester),
    KEY idx_subjects_department_semester (department_code, semester, is_active),
    KEY idx_subjects_semester_active (semester, is_active)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS student_subjects (
    student_id BIGINT UNSIGNED NOT NULL,
    subject_id BIGINT UNSIGNED NOT NULL,
    academic_year SMALLINT UNSIGNED NOT NULL,
    semester TINYINT UNSIGNED NOT NULL,
    PRIMARY KEY (student_id, subject_id, academic_year, semester),
    CONSTRAINT fk_student_subjects_student FOREIGN KEY (student_id) REFERENCES students (id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_student_subjects_subject FOREIGN KEY (subject_id) REFERENCES subjects (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS attendance (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    student_id BIGINT UNSIGNED NOT NULL,
    subject_id BIGINT UNSIGNED NOT NULL,
    class_date DATE NOT NULL,
    status ENUM('present', 'absent', 'late', 'excused') NOT NULL DEFAULT 'present',
    PRIMARY KEY (id),
    UNIQUE KEY uq_attendance_student_subject_date (student_id, subject_id, class_date),
    CONSTRAINT fk_attendance_student FOREIGN KEY (student_id) REFERENCES students (id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_attendance_subject FOREIGN KEY (subject_id) REFERENCES subjects (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS exam_marks (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    student_id BIGINT UNSIGNED NOT NULL,
    subject_id BIGINT UNSIGNED NOT NULL,
    exam_name VARCHAR(80) NOT NULL,
    marks_obtained DECIMAL(6,2) NOT NULL DEFAULT 0,
    total_marks DECIMAL(6,2) NOT NULL DEFAULT 100,
    published_at DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_exam_marks_student_subject_exam (student_id, subject_id, exam_name),
    CONSTRAINT fk_exam_marks_student FOREIGN KEY (student_id) REFERENCES students (id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_exam_marks_subject FOREIGN KEY (subject_id) REFERENCES subjects (id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT chk_exam_marks_range CHECK (marks_obtained >= 0 AND marks_obtained <= total_marks)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS study_materials (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    subject_id BIGINT UNSIGNED NULL,
    title VARCHAR(180) NOT NULL,
    description TEXT NULL,
    file_url VARCHAR(500) NOT NULL,
    published_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_study_materials_subject_date (subject_id, published_at),
    CONSTRAINT fk_study_materials_subject FOREIGN KEY (subject_id) REFERENCES subjects (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS student_report_requests (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    student_id BIGINT UNSIGNED NOT NULL,
    report_type ENUM('attendance', 'marks', 'fee', 'profile', 'technical', 'other') NOT NULL,
    subject VARCHAR(180) NOT NULL,
    message TEXT NOT NULL,
    status ENUM('new', 'in_progress', 'resolved', 'closed') NOT NULL DEFAULT 'new',
    admin_note TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_student_reports_status_date (student_id, status, created_at),
    CONSTRAINT fk_student_reports_student FOREIGN KEY (student_id) REFERENCES students (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS student_semesters (
    student_id BIGINT UNSIGNED NOT NULL,
    semester TINYINT UNSIGNED NOT NULL,
    attendance_override TINYINT(1) NOT NULL DEFAULT 0,
    attendance_override_percent DECIMAL(5,2) NULL,
    appeal_status ENUM('none', 'pending', 'approved', 'rejected') NOT NULL DEFAULT 'none',
    appeal_reason TEXT NULL,
    admin_note TEXT NULL,
    semester_fee_enabled TINYINT(1) NOT NULL DEFAULT 0,
    exam_challan_enabled TINYINT(1) NOT NULL DEFAULT 0,
    exam_slip_enabled TINYINT(1) NOT NULL DEFAULT 0,
    updated_by BIGINT UNSIGNED NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (student_id, semester),
    CONSTRAINT fk_student_semesters_student FOREIGN KEY (student_id) REFERENCES students (id) ON DELETE CASCADE ON UPDATE CASCADE,
    KEY idx_student_semesters_updated_by (updated_by)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS semester_challans (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    student_id BIGINT UNSIGNED NOT NULL,
    semester TINYINT UNSIGNED NOT NULL,
    challan_type ENUM('semester_fee', 'exam_fee') NOT NULL,
    challan_no VARCHAR(60) NOT NULL,
    amount DECIMAL(12,2) NOT NULL,
    due_date DATE NULL,
    status ENUM('disabled', 'unpaid', 'uploaded', 'verified', 'rejected') NOT NULL DEFAULT 'disabled',
    receipt_file VARCHAR(500) NULL,
    decline_reason TEXT NULL,
    verified_by BIGINT UNSIGNED NULL,
    verified_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_semester_challans_student_term_type (student_id, semester, challan_type),
    UNIQUE KEY uq_semester_challans_number (challan_no),
    KEY idx_semester_challans_review (status, semester),
    CONSTRAINT fk_semester_challans_student FOREIGN KEY (student_id) REFERENCES students (id) ON DELETE CASCADE ON UPDATE CASCADE,
    KEY idx_semester_challans_verified_by (verified_by)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS attendance_appeals (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    student_id BIGINT UNSIGNED NOT NULL,
    semester TINYINT UNSIGNED NOT NULL,
    reason TEXT NOT NULL,
    status ENUM('pending', 'approved', 'rejected') NOT NULL DEFAULT 'pending',
    admin_note TEXT NULL,
    reviewed_by BIGINT UNSIGNED NULL,
    reviewed_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_attendance_appeals_review (status, created_at),
    CONSTRAINT fk_attendance_appeals_student FOREIGN KEY (student_id) REFERENCES students (id) ON DELETE CASCADE ON UPDATE CASCADE,
    KEY idx_attendance_appeals_reviewed_by (reviewed_by)
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

CREATE TABLE IF NOT EXISTS site_content (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    content_key VARCHAR(100) NOT NULL,
    content_value TEXT NOT NULL,
    updated_by BIGINT UNSIGNED NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_site_content_key (content_key),
    CONSTRAINT fk_site_content_admin FOREIGN KEY (updated_by) REFERENCES admins (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS contact_messages (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(120) NOT NULL,
    email VARCHAR(190) NOT NULL,
    subject VARCHAR(180) NOT NULL,
    message TEXT NOT NULL,
    ip_address VARCHAR(45) NULL,
    status ENUM('new', 'read', 'archived') NOT NULL DEFAULT 'new',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_contact_messages_status_date (status, created_at),
    CONSTRAINT chk_contact_messages_email CHECK (email LIKE '%@%')
) ENGINE=InnoDB;

INSERT INTO admins (name, email, password, role, role_title, is_active)
VALUES ('AIT Support', 'support@ahmershah.dev', '$2y$10$5FBRLpUCGSJxDnMHNcSyweBmj0YixeK7mjInKUBtUQEVhFC5sOdnK', 'super_admin', 'Super Administrator', 1)
ON DUPLICATE KEY UPDATE name = VALUES(name), role = VALUES(role), role_title = VALUES(role_title), is_active = 1;

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

INSERT INTO programs (code, name, degree_level, duration_years) VALUES
    ('CS', 'Computer Science', 'BS', 4.0),
    ('MATH', 'Mathematics', 'BS', 4.0),
    ('ENG', 'English', 'BS', 4.0),
    ('PHY', 'Physics', 'BS', 4.0),
    ('DS', 'Data Science', 'BS', 4.0),
    ('ARCH', 'Architecture', 'BS', 5.0),
    ('ENV', 'Environmental Engineering', 'BS', 4.0),
    ('ECO', 'Economics', 'BS', 4.0),
    ('BBA', 'Business Administration', 'BS', 4.0)
ON DUPLICATE KEY UPDATE name = VALUES(name), degree_level = VALUES(degree_level), duration_years = VALUES(duration_years);

INSERT INTO site_content (content_key, content_value) VALUES
    ('home_eyebrow', 'Ahmer Institute for Technology'),
    ('home_headline', 'Build a future that feels'),
    ('home_intro', 'A forward-looking university for people who want to think clearly, make boldly, and leave a mark that matters.'),
    ('admissions_ribbon', 'Fall 2026 admissions are open')
ON DUPLICATE KEY UPDATE content_value = VALUES(content_value);

-- Academic catalog: 15 bachelor's departments, 8 semesters, 6 courses and 2 labs per semester.
CREATE TEMPORARY TABLE seed_departments (department_code VARCHAR(20) PRIMARY KEY, department_name VARCHAR(180) NOT NULL);
INSERT INTO seed_departments VALUES
('BSCS','Computer Science'),('BSAI','Artificial Intelligence'),('BSSE','Software Engineering'),('BSCY','Cyber Security'),('BSDS','Data Science'),
('BSEE','Electrical Engineering'),('BSCE','Civil Engineering'),('BSME','Mechanical Engineering'),('BSARCH','Architecture'),('BSMATH','Mathematics'),
('BSENG','English'),('BSECO','Economics'),('BBA','Business Administration'),('BSEnE','Environmental Engineering'),('BSPHY','Physics');
CREATE TEMPORARY TABLE seed_course_types (slot_no TINYINT PRIMARY KEY, course_label VARCHAR(100), credit_hours TINYINT);
INSERT INTO seed_course_types VALUES (1,'Foundations',3),(2,'Core Theory',3),(3,'Applied Practice',3),(4,'Quantitative Methods',3),(5,'Communication & Ethics',2),(6,'Elective Studies',3),(7,'Laboratory I',1),(8,'Laboratory II',1);
CREATE TEMPORARY TABLE seed_semesters (semester_no TINYINT PRIMARY KEY);
INSERT INTO seed_semesters VALUES (1),(2),(3),(4),(5),(6),(7),(8);
INSERT IGNORE INTO subjects (department_code, code, name, semester, credit_hours, teacher_name, is_active)
SELECT d.department_code, CONCAT(d.department_code, LPAD(s.semester_no,2,'0'), LPAD(c.slot_no,2,'0')), CONCAT(d.department_name,' ',c.course_label), s.semester_no, c.credit_hours,
CONCAT('Dr. ', ELT(MOD(s.semester_no+c.slot_no-2,10)+1,'Ayesha Khan','Hamza Raza','Sara Ahmed','Usman Malik','Mariam Ali','Bilal Ahmed','Nadia Hussain','Owais Shah','Hina Tariq','Faisal Qureshi'),' - ',d.department_code,' S',s.semester_no,'C',c.slot_no), 1
FROM seed_departments d CROSS JOIN seed_semesters s CROSS JOIN seed_course_types c;
UPDATE students SET current_semester = 4, cnic = COALESCE(cnic,'42401-1234567-1'), phone = COALESCE(phone,'+923001234567'), address = COALESCE(address,'Main Campus Road, Jamshoro, Sindh'), profile_picture = COALESCE(profile_picture,'assets/images/dashboard_sample/sample_1.png') WHERE student_code = '24BSCS001';
INSERT INTO student_semesters (student_id, semester, semester_fee_enabled, exam_challan_enabled, exam_slip_enabled)
SELECT s.id, t.semester, IF(t.semester=4,1,0), IF(t.semester=4,1,0), 0 FROM students s CROSS JOIN seed_semesters t WHERE s.student_code='24BSCS001' AND t.semester <= 4
ON DUPLICATE KEY UPDATE semester_fee_enabled=VALUES(semester_fee_enabled), exam_challan_enabled=VALUES(exam_challan_enabled);
INSERT IGNORE INTO semester_challans (student_id, semester, challan_type, challan_no, amount, due_date, status)
SELECT id, 4, 'semester_fee', 'AIT-S4-DEMO-FEE', 50000, DATE_ADD(CURDATE(),INTERVAL 14 DAY), 'unpaid' FROM students WHERE student_code='24BSCS001';
INSERT IGNORE INTO semester_challans (student_id, semester, challan_type, challan_no, amount, due_date, status)
SELECT id, 4, 'exam_fee', 'AIT-S4-DEMO-EXAM', 3500, DATE_ADD(CURDATE(),INTERVAL 14 DAY), 'unpaid' FROM students WHERE student_code='24BSCS001';
DROP TEMPORARY TABLE seed_departments;
DROP TEMPORARY TABLE seed_course_types;
DROP TEMPORARY TABLE seed_semesters;
