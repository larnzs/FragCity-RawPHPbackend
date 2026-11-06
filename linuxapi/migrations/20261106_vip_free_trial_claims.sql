-- One-time public Silver trial ledger.
-- Run as a database administrator on the VIPCore schema.
-- This record is intentionally retained after vip_users expires and is purged.

USE GLOBAL_vip;

CREATE TABLE IF NOT EXISTS fragcity_vip_free_trial_claims (
    steamid64 CHAR(17) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    account_id BIGINT NOT NULL,
    sid BIGINT NOT NULL,
    vip_group VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    claimed_at BIGINT NOT NULL,
    expires BIGINT NOT NULL,
    PRIMARY KEY (steamid64),
    UNIQUE KEY uq_fragcity_vip_free_trial_account_sid (account_id, sid),
    KEY idx_fragcity_vip_free_trial_expires (expires)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- The Linux API connects as the existing "plugins" MariaDB account.
-- Discover its exact host first; do not guess the host component:
-- SELECT User, Host FROM mysql.user WHERE User = 'plugins';
--
-- Grant only the permissions this immutable claim ledger requires.
-- Replace localhost with the exact Host returned above:
-- GRANT SELECT, INSERT ON GLOBAL_vip.fragcity_vip_free_trial_claims
--     TO 'plugins'@'localhost';
