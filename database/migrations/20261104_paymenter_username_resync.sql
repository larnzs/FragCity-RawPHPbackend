-- One-time cutover for the FragCity username -> Paymenter/Pterodactyl mapping.
-- Run only AFTER the updated website and Paymenter FragCityAPI v1.2.0 are deployed.
-- Existing linked users will be asked to re-confirm their identity/password or
-- will re-sync automatically on their next successful password login.
-- This is intentionally not an automatic schema migration.

UPDATE users
SET paymenter_sync_status = 'pending',
    paymenter_synced_at = NULL,
    paymenter_sync_error = NULL
WHERE paymenter_user_id IS NOT NULL
  AND paymenter_sync_status = 'synced';
