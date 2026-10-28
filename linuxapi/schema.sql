-- Run in the VERIFIED WeaponPaints database on the Linux host.
-- API-owned support tables. The existing CS2 plugin tables are never recreated.

CREATE TABLE IF NOT EXISTS fragcity_api_nonces (
    nonce_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (nonce_hash),
    KEY idx_fragcity_api_nonces_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- One-time-safe backfill: preserve every currently active plugin row as an
-- inventory entry. INSERT IGNORE makes rerunning this migration non-destructive.
INSERT IGNORE INTO wp_player_skin_inventory
    (steamid, weapon_team, weapon_defindex, weapon_paint_id, weapon_wear, weapon_seed,
     weapon_nametag, weapon_stattrak, weapon_sticker_0, weapon_sticker_1,
     weapon_sticker_2, weapon_sticker_3, weapon_sticker_4, weapon_keychain)
SELECT steamid, weapon_team, weapon_defindex, weapon_paint_id, weapon_wear, weapon_seed,
       weapon_nametag, weapon_stattrak, weapon_sticker_0, weapon_sticker_1,
       weapon_sticker_2, weapon_sticker_3, weapon_sticker_4, weapon_keychain
FROM wp_player_skins;

-- Replace verified_weaponpaints_db with the exact database name.
-- Replace CLIENT_HOST with the real source host MariaDB sees (not necessarily localhost).
-- Generate a unique password yourself; never use the sample literal below.
-- Do NOT use '%' or MariaDB root as the API account.
--
-- CREATE USER 'fragcity_weaponpaints_api'@'CLIENT_HOST'
--   IDENTIFIED BY 'REPLACE_WITH_A_LONG_UNIQUE_PASSWORD';
-- GRANT SELECT, INSERT, UPDATE ON verified_weaponpaints_db.wp_player_skins
--   TO 'fragcity_weaponpaints_api'@'CLIENT_HOST';
-- GRANT SELECT, INSERT, UPDATE ON verified_weaponpaints_db.wp_player_skin_inventory
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

-- Per-player, per-team, per-weapon, per-paint customization inventory.
-- The existing wp_player_skins table remains the single active loadout row
-- consumed by the CS2 plugin; this table preserves saved configurations for
-- paint finishes when the active paint changes.
CREATE TABLE IF NOT EXISTS wp_player_skin_inventory (
    steamid VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    weapon_team TINYINT UNSIGNED NOT NULL,
    weapon_defindex INT UNSIGNED NOT NULL,
    weapon_paint_id INT UNSIGNED NOT NULL,
    weapon_wear DECIMAL(10,6) NOT NULL DEFAULT 0.001,
    weapon_seed INT UNSIGNED NOT NULL DEFAULT 0,
    weapon_nametag VARCHAR(80) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL,
    weapon_stattrak TINYINT(1) NOT NULL DEFAULT 0,
    weapon_sticker_0 VARCHAR(255) NOT NULL DEFAULT '0;0;0;0;0.001;1;0',
    weapon_sticker_1 VARCHAR(255) NOT NULL DEFAULT '0;0;0;0;0.001;1;0',
    weapon_sticker_2 VARCHAR(255) NOT NULL DEFAULT '0;0;0;0;0.001;1;0',
    weapon_sticker_3 VARCHAR(255) NOT NULL DEFAULT '0;0;0;0;0.001;1;0',
    weapon_sticker_4 VARCHAR(255) NOT NULL DEFAULT '0;0;0;0;0.001;1;0',
    weapon_keychain VARCHAR(255) NOT NULL DEFAULT '0;0;0;0;0',
    PRIMARY KEY (steamid, weapon_team, weapon_defindex, weapon_paint_id),
    KEY idx_wp_skin_inventory_weapon (steamid, weapon_team, weapon_defindex)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

