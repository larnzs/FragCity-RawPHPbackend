-- Run this in the verified WeaponPaints plugin database on the Linux host.
-- This table holds hashes of used HMAC request nonces to reject replay.

CREATE TABLE IF NOT EXISTS fragcity_api_nonces (
    nonce_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (nonce_hash),
    KEY idx_fragcity_api_nonces_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- After confirming the actual database name and MariaDB user host, grant only:
-- SELECT, INSERT, UPDATE on wp_player_skins
-- INSERT, UPDATE on wp_player_gloves
-- SELECT, INSERT, UPDATE on wp_player_knife
-- SELECT, INSERT, UPDATE on wp_player_agents
-- SELECT, INSERT, UPDATE on wp_player_music
-- SELECT, INSERT, UPDATE on wp_player_pins
-- INSERT, DELETE on fragcity_api_nonces
--
-- Example only (replace both DB and account host; do not grant to '%'):
-- GRANT SELECT, INSERT, UPDATE ON verified_weaponpaints_db.wp_player_skins
--   TO 'fragcity_weaponpaints_api'@'localhost';
