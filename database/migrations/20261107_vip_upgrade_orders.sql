-- Persist the source membership for a paid VIP upgrade.
-- Run once on the main FragCity website database after 20261107_vip_orders.sql.

ALTER TABLE vip_orders
    ADD COLUMN IF NOT EXISTS purchase_type VARCHAR(16) NOT NULL DEFAULT 'new' AFTER status,
    ADD COLUMN IF NOT EXISTS upgrade_from_order_ref CHAR(32) CHARACTER SET ascii COLLATE ascii_bin NULL AFTER purchase_type,
    ADD COLUMN IF NOT EXISTS upgrade_from_level TINYINT UNSIGNED NULL AFTER upgrade_from_order_ref,
    ADD COLUMN IF NOT EXISTS upgrade_from_expires BIGINT UNSIGNED NULL AFTER upgrade_from_level;
