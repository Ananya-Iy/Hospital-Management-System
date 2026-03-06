-- ============================================================
--  Valora Hospital Management System — Full Database Schema
--  Import this file into phpMyAdmin
--  Database: hospital_management
-- ============================================================

CREATE DATABASE IF NOT EXISTS hospital_management
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE hospital_management;

-- ============================================================
--  1. USERS  (shared login table for all roles)
-- ============================================================
CREATE TABLE users (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(100)  NOT NULL,
    email       VARCHAR(150)  NOT NULL UNIQUE,
    password    VARCHAR(255)  NOT NULL,          -- store hashed passwords (password_hash)
    role        ENUM('patient','doctor','receptionist','admin') NOT NULL DEFAULT 'patient',
    phone       VARCHAR(20)   DEFAULT NULL,
    is_active   TINYINT(1)    NOT NULL DEFAULT 1,
    created_at  TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- ============================================================
--  2. PATIENTS
-- ============================================================
CREATE TABLE patients (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    user_id         INT          NOT NULL UNIQUE,
    date_of_birth   DATE         DEFAULT NULL,
    gender          ENUM('Male','Female','Other') DEFAULT NULL,
    blood_group     ENUM('A+','A-','B+','B-','O+','O-','AB+','AB-') DEFAULT NULL,
    address         TEXT         DEFAULT NULL,
    allergies       TEXT         DEFAULT NULL,
    medical_notes   TEXT         DEFAULT NULL,
    emergency_contact_name  VARCHAR(100) DEFAULT NULL,
    emergency_contact_phone VARCHAR(20)  DEFAULT NULL,
    created_at      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- ============================================================
--  3. SPECIALIZATIONS
-- ============================================================
CREATE TABLE specializations (
    id    INT AUTO_INCREMENT PRIMARY KEY,
    name  VARCHAR(100) NOT NULL UNIQUE
);

INSERT INTO specializations (name) VALUES
    ('General Practice'),
    ('Cardiology'),
    ('Dermatology'),
    ('Neurology'),
    ('Orthopedics'),
    ('Pediatrics'),
    ('Psychiatry'),
    ('Radiology'),
    ('Surgery'),
    ('Gynecology'),
    ('Ophthalmology'),
    ('ENT');

-- ============================================================
--  4. DOCTORS
-- ============================================================
CREATE TABLE doctors (
    id                  INT AUTO_INCREMENT PRIMARY KEY,
    user_id             INT          NOT NULL UNIQUE,
    specialization_id   INT          NOT NULL,
    qualification       VARCHAR(200) DEFAULT NULL,
    experience_years    INT          DEFAULT 0,
    consultation_fee    DECIMAL(8,2) DEFAULT 0.00,
    bio                 TEXT         DEFAULT NULL,
    available_days      VARCHAR(100) DEFAULT 'Mon,Tue,Wed,Thu,Fri', -- comma separated
    available_from      TIME         DEFAULT '09:00:00',
    available_to        TIME         DEFAULT '17:00:00',
    created_at          TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id)           REFERENCES users(id)           ON DELETE CASCADE,
    FOREIGN KEY (specialization_id) REFERENCES specializations(id) ON DELETE RESTRICT
);

-- ============================================================
--  5. APPOINTMENTS
-- ============================================================
CREATE TABLE appointments (
    id                  INT AUTO_INCREMENT PRIMARY KEY,
    patient_id          INT          NOT NULL,
    doctor_id           INT          NOT NULL,
    appointment_date    DATE         NOT NULL,
    appointment_time    TIME         NOT NULL,
    reason              TEXT         DEFAULT NULL,
    status              ENUM('Requested','Confirmed','Completed','Cancelled') NOT NULL DEFAULT 'Requested',
    notes               TEXT         DEFAULT NULL,   -- doctor notes after visit
    created_at          TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE,
    FOREIGN KEY (doctor_id)  REFERENCES doctors(id)  ON DELETE CASCADE
);

-- ============================================================
--  6. PRESCRIPTIONS
-- ============================================================
CREATE TABLE prescriptions (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    appointment_id  INT          NOT NULL,
    patient_id      INT          NOT NULL,
    doctor_id       INT          NOT NULL,
    diagnosis       TEXT         DEFAULT NULL,
    notes           TEXT         DEFAULT NULL,
    issued_date     DATE         NOT NULL DEFAULT (CURRENT_DATE),
    created_at      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (appointment_id) REFERENCES appointments(id) ON DELETE CASCADE,
    FOREIGN KEY (patient_id)     REFERENCES patients(id)     ON DELETE CASCADE,
    FOREIGN KEY (doctor_id)      REFERENCES doctors(id)      ON DELETE CASCADE
);

-- ============================================================
--  7. PRESCRIPTION ITEMS  (medicines per prescription)
-- ============================================================
CREATE TABLE prescription_items (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    prescription_id INT          NOT NULL,
    medicine_name   VARCHAR(150) NOT NULL,
    dosage          VARCHAR(100) DEFAULT NULL,   -- e.g. "500mg"
    frequency       VARCHAR(100) DEFAULT NULL,   -- e.g. "Twice daily"
    duration        VARCHAR(100) DEFAULT NULL,   -- e.g. "7 days"
    instructions    TEXT         DEFAULT NULL,
    FOREIGN KEY (prescription_id) REFERENCES prescriptions(id) ON DELETE CASCADE
);

-- ============================================================
--  8. INVOICES
-- ============================================================
CREATE TABLE invoices (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    patient_id      INT             NOT NULL,
    appointment_id  INT             DEFAULT NULL,
    invoice_number  VARCHAR(20)     NOT NULL UNIQUE,  -- e.g. INV-0001
    total_amount    DECIMAL(10,2)   NOT NULL DEFAULT 0.00,
    paid_amount     DECIMAL(10,2)   NOT NULL DEFAULT 0.00,
    status          ENUM('Unpaid','Paid','Partial','Cancelled') NOT NULL DEFAULT 'Unpaid',
    due_date        DATE            DEFAULT NULL,
    notes           TEXT            DEFAULT NULL,
    created_at      TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (patient_id)     REFERENCES patients(id)     ON DELETE CASCADE,
    FOREIGN KEY (appointment_id) REFERENCES appointments(id) ON DELETE SET NULL
);

-- ============================================================
--  9. INVOICE ITEMS
-- ============================================================
CREATE TABLE invoice_items (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    invoice_id  INT             NOT NULL,
    description VARCHAR(200)    NOT NULL,
    quantity    INT             NOT NULL DEFAULT 1,
    unit_price  DECIMAL(8,2)   NOT NULL DEFAULT 0.00,
    total       DECIMAL(8,2)   NOT NULL DEFAULT 0.00,
    FOREIGN KEY (invoice_id) REFERENCES invoices(id) ON DELETE CASCADE
);

-- ============================================================
--  10. MEDICAL RECORDS
-- ============================================================
CREATE TABLE medical_records (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    patient_id      INT          NOT NULL,
    doctor_id       INT          NOT NULL,
    appointment_id  INT          DEFAULT NULL,
    record_type     ENUM('Lab Report','Scan','X-Ray','Consultation Note','Other') NOT NULL,
    title           VARCHAR(200) NOT NULL,
    description     TEXT         DEFAULT NULL,
    file_path       VARCHAR(500) DEFAULT NULL,   -- for uploaded files
    record_date     DATE         NOT NULL DEFAULT (CURRENT_DATE),
    created_at      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (patient_id)     REFERENCES patients(id)     ON DELETE CASCADE,
    FOREIGN KEY (doctor_id)      REFERENCES doctors(id)      ON DELETE CASCADE,
    FOREIGN KEY (appointment_id) REFERENCES appointments(id) ON DELETE SET NULL
);

-- ============================================================
--  DEMO DATA — Sample users for testing
--  Passwords are all:  password123
--  Hash generated with: password_hash('password123', PASSWORD_DEFAULT)
-- ============================================================

INSERT INTO users (name, email, password, role, phone) VALUES
    ('Admin User',       'admin@valora.com',       '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin',        '+973 1000 0001'),
    ('Dr. Sarah Ahmed',  'doctor@valora.com',       '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'doctor',       '+973 1000 0002'),
    ('John Patient',     'patient@valora.com',      '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'patient',      '+973 1000 0003'),
    ('Sara Reception',   'reception@valora.com',    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'receptionist', '+973 1000 0004'),
    ('Dr. Ali Hassan',   'doctor2@valora.com',      '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'doctor',       '+973 1000 0005');

INSERT INTO patients (user_id, date_of_birth, gender, blood_group, address, allergies) VALUES
    (3, '1995-06-15', 'Male', 'O+', 'Manama, Bahrain', 'Penicillin');

INSERT INTO doctors (user_id, specialization_id, qualification, experience_years, consultation_fee, bio) VALUES
    (2, 2, 'MBBS, MD Cardiology', 10, 25.00, 'Specialist in cardiovascular diseases with 10 years experience.'),
    (5, 1, 'MBBS, General Practice', 5,  15.00, 'Experienced general practitioner.');

INSERT INTO appointments (patient_id, doctor_id, appointment_date, appointment_time, reason, status) VALUES
    (1, 1, CURDATE() + INTERVAL 2 DAY, '10:00:00', 'Routine checkup',      'Confirmed'),
    (1, 2, CURDATE() - INTERVAL 5 DAY, '14:00:00', 'Chest pain follow-up', 'Completed');

INSERT INTO invoices (patient_id, appointment_id, invoice_number, total_amount, paid_amount, status, due_date) VALUES
    (1, 2, 'INV-0001', 25.00, 25.00, 'Paid',   CURDATE() - INTERVAL 3 DAY),
    (1, 1, 'INV-0002', 15.00,  0.00, 'Unpaid', CURDATE() + INTERVAL 7 DAY);