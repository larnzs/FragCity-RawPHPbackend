-- Persist the source membership for a paid VIP upgrade.
-- Run once on the main FragCity website database after 20261107_vip_orders.sql.
-- MySQL-compatible form: do not add IF NOT EXISTS after ADD COLUMN.
-- If re-running, inspect INFORMATION_SCHEMA.COLUMNS first; these statements
-- are intentionally one-time migrations.

ALTER TABLE vip_orders
    ADD COLUMN purchase_type VARCHAR(16) NOT NULL DEFAULT 'new' AFTER status;

ALTER TABLE vip_orders
    ADD COLUMN upgrade_from_order_ref CHAR(32) CHARACTER SET ascii COLLATE ascii_bin NULL
    AFTER purchase_type;

ALTER TABLE vip_orders
    ADD COLUMN upgrade_from_level TINYINT UNSIGNED NULL
    AFTER upgrade_from_order_ref;

ALTER TABLE vip_orders
    ADD COLUMN upgrade_from_expires BIGINT UNSIGNED NULL
    AFTER upgrade_from_level;
