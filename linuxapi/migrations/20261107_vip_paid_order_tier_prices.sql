-- Preserve the full catalogue price of the tier granted by each paid order.
-- Run once on GLOBAL_vip after 20261107_vip_paid_orders.sql.

ALTER TABLE fragcity_vip_paid_orders
    ADD COLUMN IF NOT EXISTS tier_price_rial BIGINT UNSIGNED NOT NULL DEFAULT 0 AFTER vip_group;
