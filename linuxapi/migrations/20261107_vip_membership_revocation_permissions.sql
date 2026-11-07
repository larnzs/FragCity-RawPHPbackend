-- Grant the existing Linux API account the exact additional permissions needed
-- for customer-initiated VIP revocation. Run after the revocation-table migration.
-- Current deployment uses plugins@%; verify the real account host before applying.

USE GLOBAL_vip;

GRANT SELECT, INSERT ON GLOBAL_vip.fragcity_vip_membership_revocations
    TO 'plugins'@'%';

GRANT DELETE ON GLOBAL_vip.vip_users
    TO 'plugins'@'%';

FLUSH PRIVILEGES;
