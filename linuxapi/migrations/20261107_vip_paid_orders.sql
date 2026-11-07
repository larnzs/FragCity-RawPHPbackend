CREATE TABLE IF NOT EXISTS fragcity_vip_paid_orders (
    order_ref CHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    steamid64 CHAR(17) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    account_id BIGINT UNSIGNED NOT NULL,
    sid INT UNSIGNED NOT NULL,
    vip_level TINYINT UNSIGNED NOT NULL,
    vip_group VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    duration_seconds INT UNSIGNED NOT NULL,
    granted_at BIGINT UNSIGNED NOT NULL,
    expires BIGINT UNSIGNED NOT NULL,
    PRIMARY KEY (order_ref),
    KEY idx_vip_paid_orders_account_sid (account_id, sid),
    KEY idx_vip_paid_orders_steamid (steamid64)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- The Linux API connects as the existing "plugins" MariaDB account.
-- Discover its exact host first; do not guess the host component:
-- SELECT User, Host FROM mysql.user WHERE User = 'plugins';
--
-- The service inserts paid grants and reads the active paid grant for upgrades.
-- Replace localhost with the exact Host returned above:
-- GRANT SELECT, INSERT ON GLOBAL_vip.fragcity_vip_paid_orders
--     TO 'plugins'@'localhost';
