-- FragCity -> Paymenter account mapping (MySQL 8.0+)
-- Apply once to kyonxcbt_fragcity_services after taking a database backup.
-- Existing accounts start as pending and will be synchronized after their next
-- successful password login or Steam account-completion flow.

ALTER TABLE users
    ADD COLUMN paymenter_user_id BIGINT UNSIGNED DEFAULT NULL AFTER phone,
    ADD COLUMN paymenter_sync_status ENUM('pending', 'synced', 'failed')
        NOT NULL DEFAULT 'pending' AFTER paymenter_user_id,
    ADD COLUMN paymenter_synced_at DATETIME DEFAULT NULL AFTER paymenter_sync_status,
    ADD COLUMN paymenter_sync_error VARCHAR(64) DEFAULT NULL AFTER paymenter_synced_at,
    ADD UNIQUE KEY uq_users_paymenter_user_id (paymenter_user_id);
