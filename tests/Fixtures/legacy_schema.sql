-- Legacy schema (copy of legacy-app/docker/initdb/01_schema.sql) for the import test.
SET SESSION sql_mode = '';
DROP TABLE IF EXISTS municipalities;
DROP TABLE IF EXISTS owners;
DROP TABLE IF EXISTS parcels;
DROP TABLE IF EXISTS ownerships;
DROP TABLE IF EXISTS owner_notes;
DROP TABLE IF EXISTS access_log;
DROP TABLE IF EXISTS report_runs;

CREATE TABLE municipalities (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  code VARCHAR(6) NOT NULL DEFAULT '',
  name VARCHAR(120) NOT NULL DEFAULT '',
  district VARCHAR(80) NOT NULL DEFAULT '',
  region VARCHAR(80) NOT NULL DEFAULT '',
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci;

CREATE TABLE owners (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  type CHAR(1) NOT NULL DEFAULT 'F',
  title VARCHAR(30) NULL,
  first_name VARCHAR(60) NULL,
  last_name VARCHAR(160) NULL,
  birth_date DATE NULL,
  ico VARCHAR(10) NULL,
  street VARCHAR(120) NULL,
  city VARCHAR(120) NULL,
  zip VARCHAR(6) NULL,
  municipality_id INT UNSIGNED NOT NULL DEFAULT 0,
  phone VARCHAR(20) NULL,
  email VARCHAR(120) NULL,
  do_not_contact TINYINT(1) NOT NULL DEFAULT 0,
  duedate DATE NOT NULL DEFAULT '0000-00-00',
  note TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00',
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci;

CREATE TABLE parcels (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  municipality_id INT UNSIGNED NOT NULL DEFAULT 0,
  cadastral_area VARCHAR(160) NOT NULL DEFAULT '',
  cadastral_code VARCHAR(6) NOT NULL DEFAULT '',
  parcel_number VARCHAR(20) NOT NULL DEFAULT '',
  area_m2 INT UNSIGNED NOT NULL DEFAULT 0,
  land_type VARCHAR(60) NULL,
  PRIMARY KEY (id),
  KEY idx_parcels_municipality (municipality_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci;

CREATE TABLE ownerships (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  owner_id INT UNSIGNED NOT NULL DEFAULT 0,
  parcel_id INT UNSIGNED NOT NULL DEFAULT 0,
  share_num INT UNSIGNED NOT NULL DEFAULT 1,
  share_den INT UNSIGNED NOT NULL DEFAULT 1,
  lv_number INT UNSIGNED NULL,
  created_at DATETIME NULL,
  PRIMARY KEY (id),
  KEY idx_ownerships_parcel (parcel_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci;

CREATE TABLE owner_notes (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  owner_id INT UNSIGNED NOT NULL DEFAULT 0,
  note TEXT NOT NULL,
  created_by VARCHAR(60) NULL,
  created_at DATETIME NULL,
  PRIMARY KEY (id),
  KEY idx_owner_notes_owner (owner_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci;

CREATE TABLE access_log (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  page VARCHAR(255) NOT NULL DEFAULT '',
  ip VARCHAR(45) NOT NULL DEFAULT '',
  user_name VARCHAR(60) NULL,
  created_at DATETIME NULL,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci;

CREATE TABLE report_runs (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  run_date DATE NOT NULL,
  hostname VARCHAR(120) NULL,
  duration DECIMAL(8,2) NULL,
  owners_total INT UNSIGNED NULL,
  report_file VARCHAR(255) NULL,
  created_at DATETIME NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uniq_report_runs_date (run_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci;
