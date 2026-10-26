-- Run in the VERIFIED WeaponPaints database on the Linux host.
-- This table stores used nonce hashes to prevent captured request replays.

CREATE TABLE IF NOT EXISTS fragcity_api_nonces (
    nonce_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (nonce_hash),
    KEY idx_fragcity_api_nonces_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create a dedicated database account outside this file and grant only the
-- required privileges. Example below; replace database name and account host.
-- Do NOT use '%' or MariaDB root as the web API account.
--
-- GRANT SELECT, INSERT, UPDATE ON verified_weaponpaints_db.wp_player_skins
--   TO 'fragcity_weaponpaints_api'@'localhost';
-- GRANT INSERT, UPDATE ON verified_weaponpaints_db.wp_player_gloves
--   TO 'fragcity_weaponpaints_api'@'localhost';
-- GRANT SELECT, INSERT, UPDATE ON verified_weaponpaints_db.wp_player_knife
--   TO 'fragcity_weaponpaints_api'@'localhost';
-- GRANT SELECT, INSERT, UPDATE ON verified_weaponpaints_db.wp_player_agents
--   TO 'fragcity_weaponpaints_api'@'localhost';
-- GRANT SELECT, INSERT, UPDATE ON verified_weaponpaints_db.wp_player_music
--   TO 'fragcity_weaponpaints_api'@'localhost';
-- GRANT SELECT, INSERT, UPDATE ON verified_weaponpaints_db.wp_player_pins
--   TO 'fragcity_weaponpaints_api'@'localhost';
-- GRANT INSERT, DELETE ON verified_weaponpaints_db.fragcity_api_nonces
--   TO 'fragcity_weaponpaints_api'@'localhost';
