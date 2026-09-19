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
    failed_attempts SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    locked_until DATETIME NULL,
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

-- Idempotent for installs created before the lockout columns existed.
ALTER TABLE students ADD COLUMN IF NOT EXISTS failed_attempts SMALLINT UNSIGNED NOT NULL DEFAULT 0 AFTER last_login_at;
ALTER TABLE students ADD COLUMN IF NOT EXISTS locked_until DATETIME NULL AFTER failed_attempts;

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
    failed_attempts SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    locked_until DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_admins_email (email),
    KEY idx_admins_role_active (role, is_active),
    CONSTRAINT chk_admins_expiry CHECK (expires_at IS NULL OR expires_at > created_at)
) ENGINE=InnoDB;

-- Idempotent for installs created before the lockout columns existed.
ALTER TABLE admins ADD COLUMN IF NOT EXISTS failed_attempts SMALLINT UNSIGNED NOT NULL DEFAULT 0 AFTER last_login_at;
ALTER TABLE admins ADD COLUMN IF NOT EXISTS locked_until DATETIME NULL AFTER failed_attempts;

CREATE TABLE IF NOT EXISTS departments (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    code VARCHAR(20) NOT NULL,
    name VARCHAR(180) NOT NULL,
    faculty_id BIGINT UNSIGNED NULL,
    hod_staff_id BIGINT UNSIGNED NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_departments_code (code),
    KEY idx_departments_faculty (faculty_id),
    KEY idx_departments_hod (hod_staff_id),
    CONSTRAINT fk_departments_faculty FOREIGN KEY (faculty_id) REFERENCES faculties (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS teachers (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    teacher_code VARCHAR(20) NULL,
    name VARCHAR(150) NOT NULL,
    email VARCHAR(190) NOT NULL,
    password VARCHAR(255) NOT NULL,
    department_code VARCHAR(20) NULL,
    designation VARCHAR(120) NULL,
    phone VARCHAR(30) NULL,
    nic VARCHAR(20) NULL,
    age TINYINT UNSIGNED NULL,
    gender ENUM('Male', 'Female', 'Other') NULL,
    caste VARCHAR(80) NULL,
    religion VARCHAR(80) NULL,
    salary DECIMAL(12,2) NULL,
    working_time VARCHAR(80) NULL,
    joining_date DATE NULL,
    profile_picture VARCHAR(500) NULL,
    must_change_password TINYINT(1) NOT NULL DEFAULT 1,
    created_by BIGINT UNSIGNED NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    last_login_at DATETIME NULL,
    failed_attempts SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    locked_until DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_teachers_email (email),
    UNIQUE KEY uq_teachers_teacher_code (teacher_code),
    KEY idx_teachers_department_active (department_code, is_active),
    KEY idx_teachers_created_by (created_by),
    CONSTRAINT fk_teachers_created_by FOREIGN KEY (created_by) REFERENCES admins (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS staff (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    staff_code VARCHAR(20) NULL,
    name VARCHAR(150) NOT NULL,
    email VARCHAR(190) NOT NULL,
    password VARCHAR(255) NOT NULL,
    role_title VARCHAR(120) NULL,
    role_type ENUM('hod', 'security', 'worker', 'clerical', 'other') NOT NULL DEFAULT 'other',
    department_code VARCHAR(20) NULL,
    phone VARCHAR(30) NULL,
    nic VARCHAR(20) NULL,
    salary DECIMAL(12,2) NULL,
    joining_date DATE NULL,
    must_change_password TINYINT(1) NOT NULL DEFAULT 1,
    created_by BIGINT UNSIGNED NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    last_login_at DATETIME NULL,
    failed_attempts SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    locked_until DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_staff_email (email),
    UNIQUE KEY uq_staff_staff_code (staff_code),
    KEY idx_staff_department_active (department_code, is_active),
    KEY idx_staff_created_by (created_by),
    CONSTRAINT fk_staff_created_by FOREIGN KEY (created_by) REFERENCES admins (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB;

ALTER TABLE departments ADD CONSTRAINT fk_departments_hod FOREIGN KEY IF NOT EXISTS (hod_staff_id) REFERENCES staff (id) ON DELETE SET NULL ON UPDATE CASCADE;

CREATE TABLE IF NOT EXISTS teacher_subjects (
    teacher_id BIGINT UNSIGNED NOT NULL,
    subject_id BIGINT UNSIGNED NOT NULL,
    assigned_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (teacher_id, subject_id),
    CONSTRAINT fk_teacher_subjects_teacher FOREIGN KEY (teacher_id) REFERENCES teachers (id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_teacher_subjects_subject FOREIGN KEY (subject_id) REFERENCES subjects (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS announcements (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    author_type ENUM('admin', 'teacher') NOT NULL,
    author_id BIGINT UNSIGNED NOT NULL,
    department_code VARCHAR(20) NULL,
    subject_id BIGINT UNSIGNED NULL,
    title VARCHAR(200) NOT NULL,
    body TEXT NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_announcements_department_date (department_code, created_at),
    CONSTRAINT fk_announcements_subject FOREIGN KEY (subject_id) REFERENCES subjects (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS timetable_slots (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    subject_id BIGINT UNSIGNED NOT NULL,
    teacher_id BIGINT UNSIGNED NULL,
    day_of_week TINYINT UNSIGNED NOT NULL COMMENT '1=Mon .. 6=Sat',
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    room VARCHAR(40) NULL,
    section VARCHAR(10) NOT NULL DEFAULT 'A',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_timetable_subject (subject_id),
    KEY idx_timetable_teacher_day (teacher_id, day_of_week),
    CONSTRAINT fk_timetable_subject FOREIGN KEY (subject_id) REFERENCES subjects (id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_timetable_teacher FOREIGN KEY (teacher_id) REFERENCES teachers (id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT chk_timetable_day CHECK (day_of_week BETWEEN 1 AND 6),
    CONSTRAINT chk_timetable_time CHECK (end_time > start_time)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS admin_permissions (
    admin_id BIGINT UNSIGNED NOT NULL,
    permission_key VARCHAR(60) NOT NULL,
    granted_by BIGINT UNSIGNED NULL,
    granted_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (admin_id, permission_key),
    CONSTRAINT fk_admin_permissions_admin FOREIGN KEY (admin_id) REFERENCES admins (id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_admin_permissions_granter FOREIGN KEY (granted_by) REFERENCES admins (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS audit_log (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    actor_type ENUM('admin', 'teacher', 'staff', 'student', 'system') NOT NULL,
    actor_id BIGINT UNSIGNED NULL,
    actor_label VARCHAR(150) NULL,
    action VARCHAR(80) NOT NULL,
    target_type VARCHAR(40) NULL,
    target_id BIGINT UNSIGNED NULL,
    meta JSON NULL,
    ip_address VARCHAR(45) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_audit_log_actor (actor_type, actor_id, created_at),
    KEY idx_audit_log_target (target_type, target_id, created_at)
) ENGINE=InnoDB;

INSERT INTO departments (code, name) VALUES
('BSCS','Computer Science'),('BSAI','Artificial Intelligence'),('BSSE','Software Engineering'),('BSCY','Cyber Security'),('BSDS','Data Science'),
('BSEE','Electrical Engineering'),('BSCE','Civil Engineering'),('BSME','Mechanical Engineering'),('BSARCH','Architecture'),('BSMATH','Mathematics'),
('BSENG','English'),('BSECO','Economics'),('BBA','Business Administration'),('BSEnE','Environmental Engineering'),('BSPHY','Physics')
ON DUPLICATE KEY UPDATE name = VALUES(name);

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
SELECT s.id, t.semester_no, IF(t.semester_no=4,1,0), IF(t.semester_no=4,1,0), 0 FROM students s CROSS JOIN seed_semesters t WHERE s.student_code='24BSCS001' AND t.semester_no <= 4
ON DUPLICATE KEY UPDATE semester_fee_enabled=VALUES(semester_fee_enabled), exam_challan_enabled=VALUES(exam_challan_enabled);
INSERT IGNORE INTO semester_challans (student_id, semester, challan_type, challan_no, amount, due_date, status)
SELECT id, 4, 'semester_fee', 'AIT-S4-DEMO-FEE', 50000, DATE_ADD(CURDATE(),INTERVAL 14 DAY), 'unpaid' FROM students WHERE student_code='24BSCS001';
INSERT IGNORE INTO semester_challans (student_id, semester, challan_type, challan_no, amount, due_date, status)
SELECT id, 4, 'exam_fee', 'AIT-S4-DEMO-EXAM', 3500, DATE_ADD(CURDATE(),INTERVAL 14 DAY), 'unpaid' FROM students WHERE student_code='24BSCS001';
DROP TEMPORARY TABLE seed_departments;
DROP TEMPORARY TABLE seed_course_types;
DROP TEMPORARY TABLE seed_semesters;

-- ---------------------------------------------------------------------------
-- Full faculty generation (pure SQL, deterministic): 56 fictional teachers
-- per department (840 total), 6-8 distinct teachers linked per subject
-- across all 8 semesters, one HOD per department, a shared pool of
-- security/worker/clerical staff, and a demo timetable for semesters 1-2.
-- All identities are fictional. Every generated account's password is
-- Ait12345! (must_change_password = 1, so the first login forces a real
-- password to be set).
-- ---------------------------------------------------------------------------
SET @demo_hash = '$2y$10$9lDO0KwxZqEJnm43patHgeSYxv9/lUg/tamaw4iDLF/T22wRGwqfi';

CREATE TEMPORARY TABLE seed_dept_idx AS
SELECT code AS department_code, name AS department_name, (ROW_NUMBER() OVER (ORDER BY code) - 1) AS dept_idx
FROM departments;

CREATE TEMPORARY TABLE seed_pool_no (n INT PRIMARY KEY);
INSERT INTO seed_pool_no (n)
SELECT x.val FROM (
    SELECT a.n + b.n * 10 + 1 AS val
    FROM (SELECT 0 n UNION SELECT 1 UNION SELECT 2 UNION SELECT 3 UNION SELECT 4 UNION SELECT 5 UNION SELECT 6 UNION SELECT 7 UNION SELECT 8 UNION SELECT 9) a
    CROSS JOIN (SELECT 0 n UNION SELECT 1 UNION SELECT 2 UNION SELECT 3 UNION SELECT 4 UNION SELECT 5) b
) x
WHERE x.val <= 56;

INSERT INTO teachers (teacher_code, name, email, password, department_code, designation, phone, nic, age, gender, caste, religion, salary, working_time, joining_date, must_change_password)
SELECT
    CONCAT('T', LPAD(gen.dept_idx * 56 + gen.n, 5, '0')),
    CONCAT(
        CASE WHEN MOD(gen.seed, 2) = 0 THEN ELT(MOD(gen.seed, 15) + 1, 'Ahmed', 'Ali', 'Hamza', 'Usman', 'Bilal', 'Owais', 'Faisal', 'Kamran', 'Imran', 'Shahzad', 'Adnan', 'Tariq', 'Zeeshan', 'Waqas', 'Asad')
             ELSE ELT(MOD(gen.seed, 15) + 1, 'Ayesha', 'Sara', 'Mariam', 'Nadia', 'Hina', 'Faiza', 'Sana', 'Amina', 'Rabia', 'Uzma', 'Sadia', 'Sidra', 'Farah', 'Beenish', 'Anum') END,
        ' ',
        ELT(MOD(gen.seed * 3 + 7, 20) + 1, 'Khan', 'Raza', 'Ahmed', 'Malik', 'Ali', 'Shah', 'Hussain', 'Tariq', 'Qureshi', 'Baig', 'Sheikh', 'Chaudhry', 'Awan', 'Rajput', 'Syed', 'Abbasi', 'Bhatti', 'Memon', 'Gill', 'Mirza')
    ),
    LOWER(CONCAT(
        CASE WHEN MOD(gen.seed, 2) = 0 THEN ELT(MOD(gen.seed, 15) + 1, 'ahmed', 'ali', 'hamza', 'usman', 'bilal', 'owais', 'faisal', 'kamran', 'imran', 'shahzad', 'adnan', 'tariq', 'zeeshan', 'waqas', 'asad')
             ELSE ELT(MOD(gen.seed, 15) + 1, 'ayesha', 'sara', 'mariam', 'nadia', 'hina', 'faiza', 'sana', 'amina', 'rabia', 'uzma', 'sadia', 'sidra', 'farah', 'beenish', 'anum') END,
        '.', ELT(MOD(gen.seed * 3 + 7, 20) + 1, 'khan', 'raza', 'ahmed', 'malik', 'ali', 'shah', 'hussain', 'tariq', 'qureshi', 'baig', 'sheikh', 'chaudhry', 'awan', 'rajput', 'syed', 'abbasi', 'bhatti', 'memon', 'gill', 'mirza'),
        gen.seed, '@ait.demo'
    )),
    @demo_hash,
    gen.department_code,
    CASE WHEN MOD(gen.seed, 10) < 4 THEN 'Lecturer' WHEN MOD(gen.seed, 10) < 7 THEN 'Assistant Professor' WHEN MOD(gen.seed, 10) < 9 THEN 'Associate Professor' ELSE 'Professor' END,
    CONCAT('+92-300-', LPAD(1120100 + gen.seed, 7, '0')),
    CONCAT('42101-', LPAD(gen.seed + 1000, 7, '0'), '-', MOD(gen.seed, 10)),
    CASE WHEN MOD(gen.seed, 10) < 4 THEN 25 + MOD(gen.seed, 8) WHEN MOD(gen.seed, 10) < 7 THEN 30 + MOD(gen.seed, 11) WHEN MOD(gen.seed, 10) < 9 THEN 38 + MOD(gen.seed, 13) ELSE 45 + MOD(gen.seed, 16) END,
    CASE WHEN MOD(gen.seed, 2) = 0 THEN 'Male' ELSE 'Female' END,
    ELT(MOD(gen.seed, 10) + 1, 'Rajput', 'Awan', 'Sheikh', 'Malik', 'Syed', 'Chaudhry', 'Qureshi', 'Jatt', 'Arain', 'Bhatti'),
    'Islam',
    CASE WHEN MOD(gen.seed, 10) < 4 THEN (140 + MOD(gen.seed, 51)) * 1000 WHEN MOD(gen.seed, 10) < 7 THEN (195 + MOD(gen.seed, 46)) * 1000 WHEN MOD(gen.seed, 10) < 9 THEN (245 + MOD(gen.seed, 66)) * 1000 ELSE (315 + MOD(gen.seed, 106)) * 1000 END,
    ELT(MOD(gen.seed, 4) + 1, 'Mon-Fri, 9am-4pm', 'Mon-Fri, 9am-1pm', 'Mon-Fri, 10am-5pm', 'Mon-Thu, 9am-3pm'),
    DATE_ADD('2015-01-01', INTERVAL MOD(gen.seed, 3600) DAY),
    1
FROM (
    SELECT d.department_code, d.department_name, d.dept_idx, p.n, (d.dept_idx * 56 + p.n) AS seed
    FROM seed_dept_idx d CROSS JOIN seed_pool_no p
) gen
ON DUPLICATE KEY UPDATE name = VALUES(name);

-- Link 6-8 distinct teachers (a wrapping window over the department's pool) to every subject.
INSERT INTO teacher_subjects (teacher_id, subject_id)
SELECT t.id, s.id
FROM (
    SELECT id, department_code, ROW_NUMBER() OVER (PARTITION BY department_code ORDER BY semester, code) AS rn
    FROM subjects
) s
JOIN (
    SELECT id, department_code, ROW_NUMBER() OVER (PARTITION BY department_code ORDER BY id) AS trn, COUNT(*) OVER (PARTITION BY department_code) AS tcount
    FROM teachers
) t ON t.department_code = s.department_code
WHERE MOD(MOD(t.trn - 1 - (s.rn - 1) * 7, t.tcount) + t.tcount, t.tcount) < (6 + MOD(s.rn, 3))
ON DUPLICATE KEY UPDATE subject_id = VALUES(subject_id);

-- One HOD per department.
INSERT INTO staff (staff_code, name, email, password, role_title, role_type, department_code, phone, nic, salary, joining_date, must_change_password)
SELECT
    CONCAT('S', LPAD(d.dept_idx + 1, 5, '0')),
    CONCAT('Prof. ', ELT(MOD(d.dept_idx, 15) + 1, 'Ahmed', 'Ali', 'Hamza', 'Usman', 'Bilal', 'Owais', 'Faisal', 'Kamran', 'Imran', 'Shahzad', 'Adnan', 'Tariq', 'Zeeshan', 'Waqas', 'Asad'), ' ', ELT(MOD(d.dept_idx * 5 + 3, 20) + 1, 'Khan', 'Raza', 'Ahmed', 'Malik', 'Ali', 'Shah', 'Hussain', 'Tariq', 'Qureshi', 'Baig', 'Sheikh', 'Chaudhry', 'Awan', 'Rajput', 'Syed', 'Abbasi', 'Bhatti', 'Memon', 'Gill', 'Mirza')),
    LOWER(CONCAT('hod.', d.department_code, '@ait.demo')),
    @demo_hash,
    CONCAT('Head of Department, ', d.department_name),
    'hod',
    d.department_code,
    CONCAT('+92-300-', LPAD(1130000 + d.dept_idx, 7, '0')),
    CONCAT('42101-', LPAD(d.dept_idx + 9000, 7, '0'), '-', MOD(d.dept_idx, 10)),
    (300 + MOD(d.dept_idx, 80)) * 1000,
    DATE_ADD('2015-01-01', INTERVAL MOD(d.dept_idx * 97, 2500) DAY),
    1
FROM seed_dept_idx d
ON DUPLICATE KEY UPDATE name = VALUES(name);

UPDATE departments d
JOIN staff s ON s.department_code = d.code AND s.role_type = 'hod'
SET d.hod_staff_id = s.id;

-- Shared support staff pool: 8 security, 8 workers, 6 clerical.
CREATE TEMPORARY TABLE seed_shared_staff (idx INT PRIMARY KEY, role_type VARCHAR(20));
INSERT INTO seed_shared_staff VALUES
(1, 'security'), (2, 'security'), (3, 'security'), (4, 'security'), (5, 'security'), (6, 'security'), (7, 'security'), (8, 'security'),
(9, 'worker'), (10, 'worker'), (11, 'worker'), (12, 'worker'), (13, 'worker'), (14, 'worker'), (15, 'worker'), (16, 'worker'),
(17, 'clerical'), (18, 'clerical'), (19, 'clerical'), (20, 'clerical'), (21, 'clerical'), (22, 'clerical');

INSERT INTO staff (staff_code, name, email, password, role_title, role_type, department_code, phone, nic, salary, joining_date, must_change_password)
SELECT
    CONCAT('S', LPAD(15 + idx, 5, '0')),
    CONCAT(ELT(MOD(idx, 15) + 1, 'Ahmed', 'Ali', 'Hamza', 'Usman', 'Bilal', 'Owais', 'Faisal', 'Kamran', 'Imran', 'Shahzad', 'Adnan', 'Tariq', 'Zeeshan', 'Waqas', 'Asad'), ' ', ELT(MOD(idx * 5 + 3, 20) + 1, 'Khan', 'Raza', 'Ahmed', 'Malik', 'Ali', 'Shah', 'Hussain', 'Tariq', 'Qureshi', 'Baig', 'Sheikh', 'Chaudhry', 'Awan', 'Rajput', 'Syed', 'Abbasi', 'Bhatti', 'Memon', 'Gill', 'Mirza')),
    LOWER(CONCAT(role_type, idx, '@ait.demo')),
    @demo_hash,
    ELT(FIELD(role_type, 'security', 'worker', 'clerical'), 'Security Guard', 'Facilities Worker', 'Clerical Assistant'),
    role_type,
    NULL,
    CONCAT('+92-300-', LPAD(1140000 + idx, 7, '0')),
    CONCAT('42101-', LPAD(idx + 9500, 7, '0'), '-', MOD(idx, 10)),
    (32 + MOD(idx, 40)) * 1000,
    DATE_ADD('2018-01-01', INTERVAL MOD(idx * 133, 2500) DAY),
    1
FROM seed_shared_staff
ON DUPLICATE KEY UPDATE name = VALUES(name);

-- Demo timetable for semesters 1-2 (keeps volume sane): one slot per subject.
INSERT INTO timetable_slots (subject_id, teacher_id, day_of_week, start_time, end_time, room, section)
SELECT
    s.id,
    (SELECT MIN(ts.teacher_id) FROM teacher_subjects ts WHERE ts.subject_id = s.id),
    MOD(s.id, 6) + 1,
    ELT(MOD(s.id, 5) + 1, '08:00:00', '09:30:00', '11:00:00', '13:00:00', '14:30:00'),
    ELT(MOD(s.id, 5) + 1, '09:20:00', '10:50:00', '12:20:00', '14:20:00', '15:50:00'),
    ELT(MOD(s.id, 7) + 1, 'A-101', 'A-102', 'B-201', 'B-202', 'C-Lab1', 'C-Lab2', 'D-301'),
    'A'
FROM subjects s
WHERE s.semester IN (1, 2)
AND EXISTS (SELECT 1 FROM teacher_subjects ts WHERE ts.subject_id = s.id);

DROP TEMPORARY TABLE seed_dept_idx;
DROP TEMPORARY TABLE seed_pool_no;
DROP TEMPORARY TABLE seed_shared_staff;
