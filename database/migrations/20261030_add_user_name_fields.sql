-- FragCity: add editable personal-name fields for the profile account settings section.
-- Back up the target database before applying this migration.
-- Run exactly once against kyonxcbt_fragcity_services (and the local Laragon clone).
-- Existing users remain valid; the new fields are nullable until each player fills them in.

ALTER TABLE `users`
    ADD COLUMN `first_name` VARCHAR(100) NULL DEFAULT NULL AFTER `username`,
    ADD COLUMN `last_name` VARCHAR(100) NULL DEFAULT NULL AFTER `first_name`;
