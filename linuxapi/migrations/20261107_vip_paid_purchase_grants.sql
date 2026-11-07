-- Idempotency ledger for verified, paid FragCity VIP purchases.
-- Run once on GLOBAL_vip using a database administrator account.
-- Do not alter VIPCore-owned vip_users schema.

CREATE TABLE IF NOT EXISTS `fragcity_vip_paid_grants` (
    `order_uuid` CHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    `steamid64` CHAR(17) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    `account_id` INT UNSIGNED NOT NULL,
    `sid` INT UNSIGNED NOT NULL,
    `vip_group` VARCHAR(16) CHARACTER SET ascii NOT NULL,
    `duration_days` SMALLINT UNSIGNED NOT NULL,
    `duration_seconds` INT UNSIGNED NOT NULL,
    `granted_at` BIGINT UNSIGNED NOT NULL,
    `expires` BIGINT UNSIGNED NOT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`order_uuid`),
    KEY `idx_fragcity_vip_paid_grants_account_sid` (`account_id`, `sid`),
    KEY `idx_fragcity_vip_paid_grants_steamid_sid` (`steamid64`, `sid`)
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;
