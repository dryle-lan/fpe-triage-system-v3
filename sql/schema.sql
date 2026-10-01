-- schema.sql
-- MySQL/MariaDB schema for XAMPP
-- Import via phpMyAdmin, or: mysql -u root -p < schema.sql

CREATE DATABASE IF NOT EXISTS fpe_triage_system
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE fpe_triage_system;

-- ---------------------------------------------------------------------
-- USERS
-- ---------------------------------------------------------------------
CREATE TABLE users (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  name          VARCHAR(150) NOT NULL,
  email         VARCHAR(150) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,          -- PHP password_hash(), never plaintext
  role          ENUM('health_worker', 'doctor', 'admin') NOT NULL,
  is_active     TINYINT(1) NOT NULL DEFAULT 1,
  created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- MISSIONS
-- ---------------------------------------------------------------------
CREATE TABLE missions (
  id           INT AUTO_INCREMENT PRIMARY KEY,
  site_name    VARCHAR(200) NOT NULL,
  location     VARCHAR(255),
  mission_date DATE NOT NULL,
  status       ENUM('open', 'closed') NOT NULL DEFAULT 'open',
  created_by   INT NOT NULL,
  created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  closed_at    DATETIME NULL,
  FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- PATIENTS  (pilot uses de-identified data; keep these fields
-- role-gated in the API layer even now, hardened further later)
-- ---------------------------------------------------------------------
CREATE TABLE patients (
  id             INT AUTO_INCREMENT PRIMARY KEY,
  case_number    VARCHAR(100),
  philhealth_id  VARCHAR(100),
  last_name      VARCHAR(150),
  first_name     VARCHAR(150),
  middle_name    VARCHAR(150),
  extension_name VARCHAR(50),
  date_of_birth  DATE,
  sex            ENUM('M', 'F'),
  client_type    VARCHAR(100), -- deprecated: no longer collected by the UI (kept, nullable, for backward compatibility)
  created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- ENCOUNTERS
-- Note: JSON columns require MySQL 5.7+/MariaDB 10.2+ (both bundled
-- with any current XAMPP release). MariaDB stores JSON as a
-- validated LONGTEXT alias, which is fine for our purposes.
-- ---------------------------------------------------------------------
CREATE TABLE encounters (
  id                            INT AUTO_INCREMENT PRIMARY KEY,
  mission_id                    INT NOT NULL,
  patient_id                    INT NOT NULL,
  visit_type                    ENUM('walk_in', 'appointment'),
  chief_complaint               TEXT,
  ros_json                      JSON,   -- Review of Systems Q1-8 answers
  personal_social_history_json  JSON,
  pmh_json                      JSON,   -- Past Medical History checklist
  vitals_json                   JSON,   -- BP, HR, RR, Temp, BMI, etc.
  general_survey                ENUM('awake_alert', 'altered_sensorium'),
  triage_color                  ENUM('red', 'orange', 'yellow', 'green'),
  triage_reason                 VARCHAR(255),
  entered_by                    INT NOT NULL,
  created_at                    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at                    DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (mission_id) REFERENCES missions(id),
  FOREIGN KEY (patient_id) REFERENCES patients(id),
  FOREIGN KEY (entered_by) REFERENCES users(id)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- AI RECOMMENDATIONS
-- ---------------------------------------------------------------------
CREATE TABLE ai_recommendations (
  id                    INT AUTO_INCREMENT PRIMARY KEY,
  mission_id            INT NOT NULL,
  prompt_snapshot_json  JSON NOT NULL,
  output_text           TEXT NOT NULL,
  generated_by          INT NOT NULL,
  generated_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (mission_id) REFERENCES missions(id),
  FOREIGN KEY (generated_by) REFERENCES users(id)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- AUDIT LOG
-- ---------------------------------------------------------------------
CREATE TABLE audit_log (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  table_name  VARCHAR(100) NOT NULL,
  record_id   INT NOT NULL,
  action      ENUM('create', 'update', 'delete') NOT NULL,
  changed_by  INT,
  changed_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  diff_json   JSON,
  FOREIGN KEY (changed_by) REFERENCES users(id)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- INDEXES
-- ---------------------------------------------------------------------
CREATE INDEX idx_encounters_mission ON encounters(mission_id);
CREATE INDEX idx_encounters_patient ON encounters(patient_id);
CREATE INDEX idx_encounters_triage  ON encounters(triage_color);
CREATE INDEX idx_missions_status    ON missions(status);
CREATE INDEX idx_ai_recs_mission    ON ai_recommendations(mission_id);
CREATE INDEX idx_audit_table_record ON audit_log(table_name, record_id);
