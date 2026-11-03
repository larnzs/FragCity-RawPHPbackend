-- FragCity client identity fields required by Paymenter/Pterodactyl provisioning.
-- Apply once to kyonxcbt_fragcity_services after taking a database backup.
-- Existing users remain NULL until they complete the required account form.

ALTER TABLE users
    ADD COLUMN first_name VARCHAR(191) DEFAULT NULL AFTER username,
    ADD COLUMN last_name VARCHAR(191) DEFAULT NULL AFTER first_name;
