-- FragCity: persist public Steam Community profile data on existing users.
-- BACK UP the target database before applying this migration.
-- Run exactly once against kyonxcbt_fragcity_services (or the local Laragon copy).
-- Image bytes stay in img/steam-avatars; steam_avatar_path stores the relative path.

ALTER TABLE `users`
    ADD COLUMN `steam_personaname` VARCHAR(128) NULL DEFAULT NULL,
    ADD COLUMN `steam_profileurl` VARCHAR(255) NULL DEFAULT NULL,
    ADD COLUMN `steam_avatar_url` VARCHAR(512) NULL DEFAULT NULL,
    ADD COLUMN `steam_avatar_path` VARCHAR(255) NULL DEFAULT NULL,
    ADD COLUMN `steam_personastate` TINYINT UNSIGNED NULL DEFAULT NULL,
    ADD COLUMN `steam_communityvisibilitystate` TINYINT UNSIGNED NULL DEFAULT NULL,
    ADD COLUMN `steam_profilestate` TINYINT UNSIGNED NULL DEFAULT NULL,
    ADD COLUMN `steam_timecreated` BIGINT UNSIGNED NULL DEFAULT NULL,
    ADD COLUMN `steam_lastlogoff` BIGINT UNSIGNED NULL DEFAULT NULL,
    ADD COLUMN `steam_loccountrycode` CHAR(2) NULL DEFAULT NULL,
    ADD COLUMN `steam_gameextrainfo` VARCHAR(255) NULL DEFAULT NULL,
    ADD COLUMN `steam_gameid` VARCHAR(20) NULL DEFAULT NULL,
    ADD COLUMN `steam_profile_updated_at` DATETIME NULL DEFAULT NULL;
