-- ============================================================
--  Event Registration Management System
--  Database: event_registration_db
--  Compatible with: MySQL 5.7+ / MariaDB (XAMPP)
--  Created for: Midterm Group Project
-- ============================================================

-- Create and select the database
CREATE DATABASE IF NOT EXISTS `event_registration_db`
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE `event_registration_db`;

-- ============================================================
--  TABLE 1: event_categories
--  Stores event category types (e.g. Academic, Sports, Arts)
-- ============================================================
CREATE TABLE IF NOT EXISTS `event_categories` (
  `category_id`   INT           NOT NULL AUTO_INCREMENT,
  `category_name` VARCHAR(100)  NOT NULL,
  `description`   TEXT,
  `created_at`    TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,

  PRIMARY KEY (`category_id`),
  UNIQUE KEY `uq_category_name` (`category_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ============================================================
--  TABLE 2: users
--  Stores student and admin accounts
-- ============================================================
CREATE TABLE IF NOT EXISTS `users` (
  `user_id`    INT           NOT NULL AUTO_INCREMENT,
  `full_name`  VARCHAR(150)  NOT NULL,
  `student_id` VARCHAR(50)   NOT NULL,
  `email`      VARCHAR(150)  NOT NULL,
  `password`   VARCHAR(255)  NOT NULL COMMENT 'Bcrypt hashed password',
  `role`       ENUM('student','admin') NOT NULL DEFAULT 'student',
  `created_at` TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,

  PRIMARY KEY (`user_id`),
  UNIQUE KEY `uq_email`      (`email`),
  UNIQUE KEY `uq_student_id` (`student_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ============================================================
--  TABLE 3: events
--  Stores all events created by admins
-- ============================================================
CREATE TABLE IF NOT EXISTS `events` (
  `event_id`    INT           NOT NULL AUTO_INCREMENT,
  `category_id` INT                    DEFAULT NULL,
  `title`       VARCHAR(200)  NOT NULL,
  `description` TEXT,
  `date_time`   DATETIME      NOT NULL,
  `venue`       VARCHAR(200)  NOT NULL,
  `max_slots`   INT           NOT NULL DEFAULT 50,
  `created_at`  TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,

  PRIMARY KEY (`event_id`),
  CONSTRAINT `fk_events_category`
    FOREIGN KEY (`category_id`)
    REFERENCES `event_categories` (`category_id`)
    ON DELETE SET NULL
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ============================================================
--  TABLE 4: registrations
--  Bridge table — links users to events
-- ============================================================
CREATE TABLE IF NOT EXISTS `registrations` (
  `registration_id` INT       NOT NULL AUTO_INCREMENT,
  `user_id`         INT       NOT NULL,
  `event_id`        INT       NOT NULL,
  `status`          ENUM('confirmed','pending','cancelled') NOT NULL DEFAULT 'confirmed',
  `registered_at`   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

  PRIMARY KEY (`registration_id`),

  -- Prevents a student from registering to the same event twice
  UNIQUE KEY `uq_user_event` (`user_id`, `event_id`),

  CONSTRAINT `fk_reg_user`
    FOREIGN KEY (`user_id`)
    REFERENCES `users` (`user_id`)
    ON DELETE CASCADE
    ON UPDATE CASCADE,

  CONSTRAINT `fk_reg_event`
    FOREIGN KEY (`event_id`)
    REFERENCES `events` (`event_id`)
    ON DELETE CASCADE
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ============================================================
--  TABLE 5: admin_logs
--  Tracks all admin actions for audit purposes
-- ============================================================
CREATE TABLE IF NOT EXISTS `admin_logs` (
  `log_id`      INT           NOT NULL AUTO_INCREMENT,
  `admin_id`    INT           NOT NULL,
  `action`      VARCHAR(100)  NOT NULL COMMENT 'e.g. CREATE_EVENT, DELETE_USER',
  `target_table`VARCHAR(100)           COMMENT 'Table that was affected',
  `target_id`   INT                    COMMENT 'ID of the affected record',
  `details`     TEXT                   COMMENT 'Additional context or notes',
  `logged_at`   TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,

  PRIMARY KEY (`log_id`),

  CONSTRAINT `fk_log_admin`
    FOREIGN KEY (`admin_id`)
    REFERENCES `users` (`user_id`)
    ON DELETE CASCADE
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ============================================================
--  USEFUL VIEWS (optional but handy)
-- ============================================================

-- View: See all registrations with student and event details
CREATE OR REPLACE VIEW `view_registrations_detail` AS
  SELECT
    r.registration_id,
    u.full_name        AS student_name,
    u.student_id,
    u.email,
    e.title            AS event_title,
    ec.category_name,
    e.date_time        AS event_date,
    e.venue,
    r.status,
    r.registered_at
  FROM registrations r
  JOIN users           u  ON r.user_id   = u.user_id
  JOIN events          e  ON r.event_id  = e.event_id
  LEFT JOIN event_categories ec ON e.category_id = ec.category_id;


-- View: See remaining slots per event
CREATE OR REPLACE VIEW `view_event_slots` AS
  SELECT
    e.event_id,
    e.title,
    e.date_time,
    e.venue,
    e.max_slots,
    COUNT(r.registration_id)              AS registered_count,
    (e.max_slots - COUNT(r.registration_id)) AS remaining_slots
  FROM events e
  LEFT JOIN registrations r
    ON e.event_id = r.event_id AND r.status = 'confirmed'
  GROUP BY e.event_id;


-- ============================================================
--  END OF SCRIPT
--  Import this file via phpMyAdmin:
--    1. Open phpMyAdmin (http://localhost/phpmyadmin)
--    2. Click "Import" tab
--    3. Choose this .sql file
--    4. Click "Go"
-- ============================================================
