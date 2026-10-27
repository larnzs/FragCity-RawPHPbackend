-- Run in the VERIFIED WeaponPaints database on the Linux host.
-- This table stores used nonce hashes to prevent captured request replays.

CREATE TABLE IF NOT EXISTS fragcity_api_nonces (
    nonce_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (nonce_hash),
    KEY idx_fragcity_api_nonces_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Replace verified_weaponpaints_db with the exact database name.
-- Replace CLIENT_HOST with the real source host MariaDB sees (not necessarily localhost).
-- Generate a unique password yourself; never use the sample literal below.
-- Do NOT use '%' or MariaDB root as the API account.
--
-- CREATE USER 'fragcity_weaponpaints_api'@'CLIENT_HOST'
--   IDENTIFIED BY 'REPLACE_WITH_A_LONG_UNIQUE_PASSWORD';
-- GRANT SELECT, INSERT, UPDATE ON verified_weaponpaints_db.wp_player_skins
--   TO 'fragcity_weaponpaints_api'@'CLIENT_HOST';
-- GRANT INSERT, UPDATE ON verified_weaponpaints_db.wp_player_gloves
--   TO 'fragcity_weaponpaints_api'@'CLIENT_HOST';
-- GRANT SELECT, INSERT, UPDATE ON verified_weaponpaints_db.wp_player_knife
--   TO 'fragcity_weaponpaints_api'@'CLIENT_HOST';
-- GRANT SELECT, INSERT, UPDATE ON verified_weaponpaints_db.wp_player_agents
--   TO 'fragcity_weaponpaints_api'@'CLIENT_HOST';
-- GRANT SELECT, INSERT, UPDATE ON verified_weaponpaints_db.wp_player_music
--   TO 'fragcity_weaponpaints_api'@'CLIENT_HOST';
-- GRANT SELECT, INSERT, UPDATE ON verified_weaponpaints_db.wp_player_pins
--   TO 'fragcity_weaponpaints_api'@'CLIENT_HOST';
-- SELECT is required because the API deletes by created_at and tests may delete by nonce_hash.
-- GRANT SELECT, INSERT, DELETE ON verified_weaponpaints_db.fragcity_api_nonces
--   TO 'fragcity_weaponpaints_api'@'CLIENT_HOST';
