-- Laragon-only local site DB account.
-- Replace the placeholder in BOTH statements with the same unique strong password,
-- then place that same password in settings/settings.json -> databases.main.password.
-- This grants access to the ten core website tables plus the two public Server Browser scanner tables.
-- It does not grant access to GLOBAL_weaponpaints; WeaponPaints remains behind its HTTPS API.

CREATE USER IF NOT EXISTS 'fragcity_site_local'@'127.0.0.1'
  IDENTIFIED BY 'REPLACE_WITH_A_UNIQUE_HEX_PASSWORD';

ALTER USER 'fragcity_site_local'@'127.0.0.1'
  IDENTIFIED BY 'REPLACE_WITH_A_UNIQUE_HEX_PASSWORD';

GRANT SELECT, INSERT, UPDATE, DELETE ON `kyonxcbt_fragcity_services`.`donations`
  TO 'fragcity_site_local'@'127.0.0.1';
GRANT SELECT, INSERT, UPDATE, DELETE ON `kyonxcbt_fragcity_services`.`password_resets`
  TO 'fragcity_site_local'@'127.0.0.1';
GRANT SELECT, INSERT, UPDATE, DELETE ON `kyonxcbt_fragcity_services`.`tickets`
  TO 'fragcity_site_local'@'127.0.0.1';
GRANT SELECT, INSERT, UPDATE, DELETE ON `kyonxcbt_fragcity_services`.`ticket_replies`
  TO 'fragcity_site_local'@'127.0.0.1';
GRANT SELECT, INSERT, UPDATE, DELETE ON `kyonxcbt_fragcity_services`.`users`
  TO 'fragcity_site_local'@'127.0.0.1';
GRANT SELECT, INSERT, UPDATE, DELETE ON `kyonxcbt_fragcity_services`.`user_wallets`
  TO 'fragcity_site_local'@'127.0.0.1';
GRANT SELECT, INSERT, UPDATE, DELETE ON `kyonxcbt_fragcity_services`.`vip_codes`
  TO 'fragcity_site_local'@'127.0.0.1';
GRANT SELECT, INSERT, UPDATE, DELETE ON `kyonxcbt_fragcity_services`.`vip_usage`
  TO 'fragcity_site_local'@'127.0.0.1';
GRANT SELECT, INSERT, UPDATE, DELETE ON `kyonxcbt_fragcity_services`.`wallet_transactions`
  TO 'fragcity_site_local'@'127.0.0.1';
GRANT SELECT, INSERT, UPDATE, DELETE ON `kyonxcbt_fragcity_services`.`withdraw_requests`
  TO 'fragcity_site_local'@'127.0.0.1';

-- Public Server Browser scanner tables (shared-hosting / Laragon cron).
GRANT SELECT, INSERT, UPDATE ON `kyonxcbt_fragcity_services`.`public_server_scan_targets`
  TO 'fragcity_site_local'@'127.0.0.1';
GRANT SELECT, INSERT, UPDATE ON `kyonxcbt_fragcity_services`.`public_server_scan_ports`
  TO 'fragcity_site_local'@'127.0.0.1';

FLUSH PRIVILEGES;
