-- FragCity: replay protection for the signed Paymenter profile API.
-- BACK UP kyonxcbt_fragcity_services first. Run exactly once on the MAIN
-- website database, not on GLOBAL_weaponpaints or any Paymenter database.

CREATE TABLE IF NOT EXISTS fragcity_paymenter_profile_api_nonces (
    nonce_hash CHAR(64) NOT NULL,
    expires_at DATETIME NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (nonce_hash),
    KEY idx_fragcity_paymenter_profile_nonce_expiry (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
