-- Run this migration in the WeaponPaints database on the Linux host.
-- Confirm the database name before using the example USE statement.
-- This table stores only hashes of authenticated request nonces.

CREATE TABLE IF NOT EXISTS fragcity_api_nonces (
    nonce_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (nonce_hash),
    KEY idx_fragcity_api_nonces_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Example least-privilege grants (replace the database and user/host as needed):
-- GRANT SELECT, INSERT, UPDATE ON kyonxcbt_weaponpaints_plugin.wp_player_skins
--   TO 'fragcity_weaponpaints_api'@'localhost';
-- GRANT INSERT, UPDATE ON kyonxcbt_weaponpaints_plugin.wp_player_gloves
--   TO 'fragcity_weaponpaints_api'@'localhost';
-- GRANT SELECT, INSERT, UPDATE ON kyonxcbt_weaponpaints_plugin.wp_player_knife
--   TO 'fragcity_weaponpaints_api'@'localhost';
-- GRANT SELECT, INSERT, UPDATE ON kyonxcbt_weaponpaints_plugin.wp_player_agents
--   TO 'fragcity_weaponpaints_api'@'localhost';
-- GRANT SELECT, INSERT, UPDATE ON kyonxcbt_weaponpaints_plugin.wp_player_music
--   TO 'fragcity_weaponpaints_api'@'localhost';
-- GRANT SELECT, INSERT, UPDATE ON kyonxcbt_weaponpaints_plugin.wp_player_pins
--   TO 'fragcity_weaponpaints_api'@'localhost';
-- GRANT INSERT, DELETE ON kyonxcbt_weaponpaints_plugin.fragcity_api_nonces
--   TO 'fragcity_weaponpaints_api'@'localhost';
