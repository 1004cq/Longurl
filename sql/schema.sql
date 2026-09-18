SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE TABLE IF NOT EXISTS `links` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `e_length` INT UNSIGNED NOT NULL,
  `target_url` TEXT NOT NULL,
  `clicks` BIGINT UNSIGNED NOT NULL DEFAULT 0,
  `enabled` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL,
  `updated_at` DATETIME NOT NULL,
  `expires_at` DATETIME DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_e_length` (`e_length`),
  KEY `idx_enabled` (`enabled`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `click_logs` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `link_id` BIGINT UNSIGNED NOT NULL,
  `clicked_at` DATETIME NOT NULL,
  `ip` VARCHAR(45) NOT NULL DEFAULT '',
  `user_agent` VARCHAR(512) NOT NULL DEFAULT '',
  `referer` VARCHAR(1024) NOT NULL DEFAULT '',
  `request_uri` VARCHAR(2048) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  KEY `idx_link_id` (`link_id`),
  KEY `idx_clicked_at` (`clicked_at`),
  CONSTRAINT `fk_click_logs_link` FOREIGN KEY (`link_id`) REFERENCES `links` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `rate_limits` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `bucket` VARCHAR(80) NOT NULL,
  `ip` VARCHAR(45) NOT NULL,
  `hits` INT UNSIGNED NOT NULL DEFAULT 1,
  `window_start` DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_bucket_ip_window` (`bucket`, `ip`, `window_start`),
  KEY `idx_window_start` (`window_start`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
