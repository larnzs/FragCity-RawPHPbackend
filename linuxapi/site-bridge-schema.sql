-- Run this on the SHARED-HOSTING WEBSITE database kyonxcbt_fragcity_services.
-- Do NOT run this on the Linux host and do not grant the Linux host a MySQL connection.
-- These tables support replay protection and login rate limiting for the HTTPS site bridge.

CREATE TABLE IF NOT EXISTS fragcity_api_bridge_nonces (
    nonce_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (nonce_hash),
    KEY idx_fc_site_bridge_nonce_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS fragcity_api_bridge_login_attempts (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    ip_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    attempted_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY idx_fc_bridge_login_ip_time (ip_hash, attempted_at),
    KEY idx_fc_bridge_login_attempted_at (attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
