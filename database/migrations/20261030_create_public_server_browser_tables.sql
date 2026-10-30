-- Public CS2 Server Browser scanner.
-- Run once against kyonxcbt_fragcity_services after taking a database backup.
-- The initial target is the FragCity public IPv4 host and requested port range.

CREATE TABLE IF NOT EXISTS public_server_scan_targets (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    host VARCHAR(253) NOT NULL,
    port_start SMALLINT UNSIGNED NOT NULL,
    port_end SMALLINT UNSIGNED NOT NULL,
    region VARCHAR(80) NOT NULL DEFAULT '',
    enabled TINYINT(1) NOT NULL DEFAULT 1,
    query_timeout_ms SMALLINT UNSIGNED NOT NULL DEFAULT 1500,
    retry_offline_seconds INT UNSIGNED NOT NULL DEFAULT 3600,
    refresh_online_seconds INT UNSIGNED NOT NULL DEFAULT 90,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_public_server_scan_target (host, port_start, port_end),
    KEY idx_public_server_scan_targets_enabled (enabled),
    CONSTRAINT chk_public_server_scan_target_range
        CHECK (port_start >= 1 AND port_end <= 65535 AND port_start <= port_end),
    CONSTRAINT chk_public_server_scan_target_span
        CHECK (port_end - port_start <= 4095),
    CONSTRAINT chk_public_server_scan_timeout
        CHECK (query_timeout_ms BETWEEN 250 AND 5000),
    CONSTRAINT chk_public_server_scan_retries
        CHECK (retry_offline_seconds BETWEEN 300 AND 86400),
    CONSTRAINT chk_public_server_scan_refresh
        CHECK (refresh_online_seconds BETWEEN 30 AND 3600)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS public_server_scan_ports (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    target_id BIGINT UNSIGNED NOT NULL,
    port SMALLINT UNSIGNED NOT NULL,
    status ENUM('pending', 'online', 'offline', 'not_cs2', 'error') NOT NULL DEFAULT 'pending',
    app_id SMALLINT UNSIGNED NULL,
    server_name VARCHAR(255) NULL,
    map_name VARCHAR(128) NULL,
    players TINYINT UNSIGNED NOT NULL DEFAULT 0,
    max_players TINYINT UNSIGNED NOT NULL DEFAULT 0,
    bots TINYINT UNSIGNED NOT NULL DEFAULT 0,
    vac TINYINT(1) NULL,
    password TINYINT(1) NULL,
    query_rtt_ms DECIMAL(8,2) NULL,
    last_error VARCHAR(64) NULL,
    last_queried_at DATETIME NULL,
    next_query_at DATETIME NULL,
    last_seen_online_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_public_server_scan_port (target_id, port),
    KEY idx_public_server_scan_due (target_id, next_query_at, port),
    KEY idx_public_server_scan_online (status, target_id, port),
    CONSTRAINT fk_public_server_scan_ports_target
        FOREIGN KEY (target_id) REFERENCES public_server_scan_targets (id)
        ON DELETE CASCADE,
    CONSTRAINT chk_public_server_scan_port_range
        CHECK (port BETWEEN 1 AND 65535)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO public_server_scan_targets
    (host, port_start, port_end, region, enabled)
VALUES
    ('85.185.40.85', 27085, 29100, '', 1)
ON DUPLICATE KEY UPDATE
    host = VALUES(host);
