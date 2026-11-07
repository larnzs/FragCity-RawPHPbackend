-- VIP cancellation refunds require fractional Toman precision.
-- Run once against the main website database (kyonxcbt_fragcity_services).
USE kyonxcbt_fragcity_services;

-- DECIMAL keeps the historical whole-Toman values unchanged while allowing
-- prorated refunds to retain up to eight fractional digits.
ALTER TABLE user_wallets
    MODIFY COLUMN balance DECIMAL(20,8) NOT NULL DEFAULT 0.00000000;

ALTER TABLE wallet_transactions
    MODIFY COLUMN amount DECIMAL(20,8) NOT NULL;

-- Snapshot the exact remaining seconds used to price each VIP upgrade.
-- This makes later cancellation refunds reproducible to the second.
SET @has_upgrade_remaining_seconds := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'vip_orders'
      AND COLUMN_NAME = 'upgrade_remaining_seconds'
);
SET @upgrade_remaining_sql := IF(
    @has_upgrade_remaining_seconds = 0,
    'ALTER TABLE vip_orders ADD COLUMN upgrade_remaining_seconds INT UNSIGNED NULL AFTER upgrade_from_expires',
    'SELECT 1'
);
PREPARE upgrade_remaining_stmt FROM @upgrade_remaining_sql;
EXECUTE upgrade_remaining_stmt;
DEALLOCATE PREPARE upgrade_remaining_stmt;
