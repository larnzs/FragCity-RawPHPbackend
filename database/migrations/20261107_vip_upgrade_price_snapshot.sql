-- Preserve the catalogue price of the purchased tier for future upgrade calculations.
-- Run once on the main FragCity website database after 20261107_vip_orders.sql.
-- MySQL-compatible form: do not add IF NOT EXISTS after ADD COLUMN.

ALTER TABLE vip_orders
    ADD COLUMN tier_price_rial BIGINT UNSIGNED NOT NULL DEFAULT 0
    AFTER amount_rial;
