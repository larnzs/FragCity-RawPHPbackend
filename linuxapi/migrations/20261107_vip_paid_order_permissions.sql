-- Grant the existing Linux API MariaDB account access to the paid VIP ledger.
-- Run after 20261107_vip_paid_orders.sql and
-- 20261107_vip_paid_order_tier_prices.sql.
-- The current FragCity deployment documents the existing account as plugins@%.
-- Do not create a new database user or change its password.

USE GLOBAL_vip;

GRANT SELECT, INSERT ON GLOBAL_vip.fragcity_vip_paid_orders
    TO 'plugins'@'%';

FLUSH PRIVILEGES;
