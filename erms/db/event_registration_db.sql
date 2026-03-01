-- ============================================================
--  Event Registration Management System
--  Database: event_registration_db
--  Compatible with: MySQL 5.7+ / MariaDB 10.3+ (XAMPP)
--  Created for: Midterm Group Project
--
--  TABLES:
--    1. event_categories   — event classification groups
--    2. users              — students & admin accounts
--    3. events             — events created by admins
--    4. registrations      — student ↔ event bridge
--    5. admin_logs         — admin audit trail
--    6. login_attempts     — rate-limiting & IP tracking
--    7. password_resets    — forgot-password tokens
--
--  HOW TO IMPORT:
--    phpMyAdmin → Import tab → select this file → Go
-- ============================================================

CREATE DATABASE IF NOT EXISTS `event_registration_db`
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE `event_registration_db`;


-- ============================================================
--  TABLE 1: event_categories
--  Stores event classification groups (Academic, Sports, etc.)
-- ============================================================
CREATE TABLE IF NOT EXISTS `event_categories` (
  `category_id`   INT          NOT NULL AUTO_INCREMENT,
  `category_name` VARCHAR(100) NOT NULL,
  `description`   TEXT,
  `created_at`    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,

  PRIMARY KEY (`category_id`),
  UNIQUE KEY `uq_category_name` (`category_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ============================================================
--  TABLE 2: users
--  Stores student and admin accounts.
--
--  Security columns added beyond base schema:
--    is_active       — soft-disable accounts without deleting
--    failed_attempts — consecutive failed logins (for lockout)
--    locked_until    — account lockout expiry (NULL = not locked)
-- ============================================================
CREATE TABLE IF NOT EXISTS `users` (
  `user_id`         INT           NOT NULL AUTO_INCREMENT,
  `full_name`       VARCHAR(150)  NOT NULL,
  `student_id`      VARCHAR(50)   NOT NULL,
  `email`           VARCHAR(150)  NOT NULL,
  `password`        VARCHAR(255)  NOT NULL  COMMENT 'bcrypt hash',
  `role`            ENUM('student','admin') NOT NULL DEFAULT 'student',
  `is_active`       TINYINT(1)    NOT NULL DEFAULT 1
                    COMMENT '1 = active, 0 = deactivated by admin',
  `failed_attempts` INT           NOT NULL DEFAULT 0
                    COMMENT 'Consecutive failed login attempts',
  `locked_until`    DATETIME               DEFAULT NULL
                    COMMENT 'Lockout expiry — NULL means not locked',
  `created_at`      TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,

  PRIMARY KEY (`user_id`),
  UNIQUE KEY `uq_email`      (`email`),
  UNIQUE KEY `uq_student_id` (`student_id`),
  INDEX `idx_role`           (`role`),
  INDEX `idx_is_active`      (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ============================================================
--  TABLE 3: events
--  Stores all events created by admins.
--
--  status:
--    active    — visible and open for registration
--    inactive  — hidden from students (draft/paused)
--    cancelled — event cancelled; registrations frozen
-- ============================================================
CREATE TABLE IF NOT EXISTS `events` (
  `event_id`    INT          NOT NULL AUTO_INCREMENT,
  `category_id` INT                   DEFAULT NULL,
  `title`       VARCHAR(200) NOT NULL,
  `description` TEXT,
  `date_time`   DATETIME     NOT NULL,
  `venue`       VARCHAR(200) NOT NULL,
  `max_slots`   INT          NOT NULL DEFAULT 50,
  `status`      ENUM('active','inactive','cancelled') NOT NULL DEFAULT 'active',
  `created_at`  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,

  PRIMARY KEY (`event_id`),
  INDEX `idx_event_status`   (`status`),
  INDEX `idx_event_datetime` (`date_time`),

  CONSTRAINT `fk_events_category`
    FOREIGN KEY (`category_id`)
    REFERENCES `event_categories` (`category_id`)
    ON DELETE SET NULL
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ============================================================
--  TABLE 4: registrations
--  Bridge table — links students to events.
--
--  status:
--    confirmed — registration approved/active
--    pending   — awaiting admin confirmation
--    cancelled — student or admin cancelled
-- ============================================================
CREATE TABLE IF NOT EXISTS `registrations` (
  `registration_id` INT       NOT NULL AUTO_INCREMENT,
  `user_id`         INT       NOT NULL,
  `event_id`        INT       NOT NULL,
  `status`          ENUM('confirmed','pending','cancelled') NOT NULL DEFAULT 'confirmed',
  `registered_at`   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

  PRIMARY KEY (`registration_id`),
  UNIQUE KEY `uq_user_event`     (`user_id`, `event_id`),
  INDEX `idx_reg_status`         (`status`),
  INDEX `idx_reg_event`          (`event_id`),

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
--  Audit trail for all admin actions.
--  Kept even if the admin account is deleted (SET NULL).
-- ============================================================
CREATE TABLE IF NOT EXISTS `admin_logs` (
  `log_id`       INT          NOT NULL AUTO_INCREMENT,
  `admin_id`     INT                   DEFAULT NULL,
  `action`       VARCHAR(100) NOT NULL COMMENT 'e.g. CREATE_EVENT, DELETE_USER, TOGGLE_STATUS',
  `target_table` VARCHAR(100)          COMMENT 'Table that was affected',
  `target_id`    INT                   COMMENT 'PK of the affected record',
  `details`      TEXT                  COMMENT 'Additional context or diff notes',
  `logged_at`    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,

  PRIMARY KEY (`log_id`),
  INDEX `idx_log_admin`  (`admin_id`),
  INDEX `idx_log_action` (`action`),

  CONSTRAINT `fk_log_admin`
    FOREIGN KEY (`admin_id`)
    REFERENCES `users` (`user_id`)
    ON DELETE SET NULL
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ============================================================
--  TABLE 6: login_attempts
--  Tracks every login attempt for rate-limiting.
--  Used by login_limiter.php to:
--    - Block IPs with too many failures (IP_MAX_ATTEMPTS)
--    - Trigger per-account lockout (synced to users.locked_until)
-- ============================================================
CREATE TABLE IF NOT EXISTS `login_attempts` (
  `attempt_id`    INT          NOT NULL AUTO_INCREMENT,
  `email`         VARCHAR(150) NOT NULL,
  `ip_address`    VARCHAR(45)  NOT NULL COMMENT 'Supports IPv4 and IPv6',
  `is_successful` TINYINT(1)   NOT NULL DEFAULT 0
                  COMMENT '1 = success, 0 = failed',
  `attempted_at`  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,

  PRIMARY KEY (`attempt_id`),
  INDEX `idx_la_email`     (`email`),
  INDEX `idx_la_ip`        (`ip_address`),
  INDEX `idx_la_attempted` (`attempted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ============================================================
--  TABLE 7: password_resets
--  One-time tokens for the Forgot Password flow.
--  Used by forgot-password.php and reset-password.php.
--
--  Lifecycle:
--    1. forgot-password.php  → INSERT row (used = 0)
--    2. reset-password.php   → validates token + expiry
--    3. On success           → SET used = 1
--    4. Old tokens for same email expire on next request
--       (UPDATE SET used = 1 WHERE email = ? AND used = 0)
-- ============================================================
CREATE TABLE IF NOT EXISTS `password_resets` (
  `reset_id`  INT          NOT NULL AUTO_INCREMENT,
  `email`     VARCHAR(150) NOT NULL,
  `token`     VARCHAR(64)  NOT NULL  COMMENT 'bin2hex(random_bytes(32)) — 64 hex chars',
  `expires_at` DATETIME    NOT NULL  COMMENT 'Token valid for 1 hour from creation',
  `used`      TINYINT(1)   NOT NULL DEFAULT 0
              COMMENT '0 = unused, 1 = consumed or invalidated',
  `created_at` TIMESTAMP   NOT NULL DEFAULT CURRENT_TIMESTAMP,

  PRIMARY KEY (`reset_id`),
  UNIQUE KEY `uq_token`     (`token`),
  INDEX `idx_pr_email`      (`email`),
  INDEX `idx_pr_expires`    (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ============================================================
--  VIEWS
-- ============================================================

-- View: Full registration detail (used by admin registrations page)
CREATE OR REPLACE VIEW `view_registrations_detail` AS
  SELECT
    r.registration_id,
    u.user_id,
    u.full_name       AS student_name,
    u.student_id,
    u.email,
    u.is_active       AS student_active,
    e.event_id,
    e.title           AS event_title,
    e.status          AS event_status,
    e.date_time       AS event_date,
    e.venue,
    ec.category_name,
    r.status          AS reg_status,
    r.registered_at
  FROM registrations r
  JOIN users             u  ON r.user_id    = u.user_id
  JOIN events            e  ON r.event_id   = e.event_id
  LEFT JOIN event_categories ec ON e.category_id = ec.category_id;


-- View: Remaining slots per event
CREATE OR REPLACE VIEW `view_event_slots` AS
  SELECT
    e.event_id,
    e.title,
    e.status,
    e.date_time,
    e.venue,
    e.max_slots,
    COUNT(r.registration_id)                       AS registered_count,
    (e.max_slots - COUNT(r.registration_id))       AS remaining_slots
  FROM events e
  LEFT JOIN registrations r
    ON e.event_id = r.event_id AND r.status != 'cancelled'
  GROUP BY e.event_id;


-- View: Active users with their registration summary
CREATE OR REPLACE VIEW `view_user_summary` AS
  SELECT
    u.user_id,
    u.full_name,
    u.student_id,
    u.email,
    u.role,
    u.is_active,
    u.created_at,
    COUNT(r.registration_id)                                    AS total_regs,
    SUM(r.status = 'confirmed')                                 AS confirmed_regs,
    SUM(r.status = 'pending')                                   AS pending_regs,
    SUM(r.status != 'cancelled' AND e.date_time >= NOW())       AS upcoming_regs
  FROM users u
  LEFT JOIN registrations r ON r.user_id  = u.user_id
  LEFT JOIN events        e ON e.event_id = r.event_id
  GROUP BY u.user_id;


-- ============================================================
--  END OF SCRIPT
--
--  HOW TO IMPORT (fresh install):
--    1. Open phpMyAdmin → http://localhost/phpmyadmin
--    2. Click "Import" tab
--    3. Select this file → click "Go"
--
--  IF YOU ALREADY HAVE THE OLD DB (upgrade path):
--    Run only these ALTERs manually in phpMyAdmin SQL tab:
--
--    ALTER TABLE `users`
--      ADD COLUMN `is_active`       TINYINT(1) NOT NULL DEFAULT 1  AFTER `role`,
--      ADD COLUMN `failed_attempts` INT        NOT NULL DEFAULT 0  AFTER `is_active`,
--      ADD COLUMN `locked_until`    DATETIME   DEFAULT NULL        AFTER `failed_attempts`;
--
--    ALTER TABLE `events`
--      ADD COLUMN `status` ENUM('active','inactive','cancelled') NOT NULL DEFAULT 'active' AFTER `max_slots`;
-- ============================================================