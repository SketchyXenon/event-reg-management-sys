-- ============================================================
--  login_attempts.sql
--  Add this table to event_registration_db
--  Tracks failed login attempts per email for rate limiting
-- ============================================================

USE `event_registration_db`;

CREATE TABLE IF NOT EXISTS `login_attempts` (
  `attempt_id`   INT          NOT NULL AUTO_INCREMENT,
  `email`        VARCHAR(150) NOT NULL,
  `ip_address`   VARCHAR(45)  NOT NULL COMMENT 'Supports IPv4 and IPv6',
  `attempted_at` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `is_successful`TINYINT(1)   NOT NULL DEFAULT 0 COMMENT '1 = success, 0 = failed',

  PRIMARY KEY (`attempt_id`),
  INDEX `idx_email`      (`email`),
  INDEX `idx_ip`         (`ip_address`),
  INDEX `idx_attempted`  (`attempted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ============================================================
--  Also add lockout columns to users table
--  These track the lockout state per user account
-- ============================================================
ALTER TABLE `users`
  ADD COLUMN `failed_attempts` INT       NOT NULL DEFAULT 0
    COMMENT 'Consecutive failed login attempts',
  ADD COLUMN `locked_until`    DATETIME           DEFAULT NULL
    COMMENT 'Account locked until this datetime, NULL means not locked';
