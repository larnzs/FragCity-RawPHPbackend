-- FragCity: browser-specific persistent login tokens ("Remember me").
-- Back up the target database before applying this migration.
-- Run once against kyonxcbt_fragcity_services (or the local Laragon copy).
-- The validator itself is never stored; only its SHA-256 hash is persisted.

CREATE TABLE IF NOT EXISTS `fragcity_remember_tokens` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `steamid64` CHAR(17) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    `selector` CHAR(24) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    `token_hash` CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    `expires_at` DATETIME NOT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `last_used_at` DATETIME NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_fragcity_remember_selector` (`selector`),
    KEY `idx_fragcity_remember_steamid` (`steamid64`),
    KEY `idx_fragcity_remember_expiry` (`expires_at`)
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;
