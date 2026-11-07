-- FragCity official VIP checkout ledger.
-- Run once on the MAIN WEBSITE database (kyonxcbt_fragcity_services).
-- This table is separate from wallet_transactions: VIP purchases never credit/debit wallet balance.

CREATE TABLE IF NOT EXISTS `vip_purchase_orders` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `order_uuid` CHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    `active_purchase_lock` CHAR(17) CHARACTER SET ascii COLLATE ascii_bin DEFAULT NULL,
    `steamid64` CHAR(17) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    `player_name` VARCHAR(64) NOT NULL,
    `vip_level` TINYINT UNSIGNED NOT NULL,
    `vip_group` VARCHAR(16) CHARACTER SET ascii NOT NULL,
    `duration_days` SMALLINT UNSIGNED NOT NULL,
    `duration_seconds` INT UNSIGNED NOT NULL,
    `amount_rial` BIGINT UNSIGNED NOT NULL,
    `authority` VARCHAR(128) CHARACTER SET ascii COLLATE ascii_bin DEFAULT NULL,
    `ref_id` VARCHAR(255) DEFAULT NULL,
    `status` ENUM(
        'pending',
        'paid',
        'fulfilled',
        'fulfillment_failed',
        'failed',
        'cancelled'
    ) NOT NULL DEFAULT 'pending',
    `last_error_code` VARCHAR(64) CHARACTER SET ascii DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `paid_at` DATETIME DEFAULT NULL,
    `fulfilled_at` DATETIME DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_vip_purchase_order_uuid` (`order_uuid`),
    UNIQUE KEY `uq_vip_purchase_active_lock` (`active_purchase_lock`),
    UNIQUE KEY `uq_vip_purchase_authority` (`authority`),
    KEY `idx_vip_purchase_steamid_status` (`steamid64`, `status`, `created_at`),
    KEY `idx_vip_purchase_status_created` (`status`, `created_at`)
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;
