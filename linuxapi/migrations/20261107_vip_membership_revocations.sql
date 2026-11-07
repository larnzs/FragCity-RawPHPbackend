-- Preserve a tamper-resistant history of customer-triggered VIP membership revocations.
-- Run once on GLOBAL_vip before deploying the revocation endpoint.
-- The live VIPCore entitlement remains owned by vip_users; this table is an audit ledger.

USE GLOBAL_vip;

CREATE TABLE IF NOT EXISTS fragcity_vip_membership_revocations (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    steamid64 CHAR(17) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    account_id BIGINT UNSIGNED NOT NULL,
    sid INT UNSIGNED NOT NULL,
    vip_group VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    vip_level TINYINT UNSIGNED NOT NULL,
    expires BIGINT UNSIGNED NOT NULL,
    paid_order_ref CHAR(32) CHARACTER SET ascii COLLATE ascii_bin NULL,
    revoked_at BIGINT UNSIGNED NOT NULL,
    revoked_by VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'customer',
    PRIMARY KEY (id),
    KEY idx_vip_revocations_steam_sid (steamid64, sid, id),
    KEY idx_vip_revocations_account_sid (account_id, sid, id),
    KEY idx_vip_revocations_order_ref (paid_order_ref)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
