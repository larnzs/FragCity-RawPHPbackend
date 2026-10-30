<?php
declare(strict_types=1);

require_once __DIR__ . '/PterodactylMonitoringApiClient.php';

/** Website repository boundary for sanitized Pterodactyl monitoring snapshots. */
final class PterodactylMonitoringRepository
{
    private PterodactylMonitoringApiClient $api;

    public function __construct()
    {
        $this->api = new PterodactylMonitoringApiClient();
    }

    /** @return array<string, mixed> */
    public function getSnapshot(): array
    {
        return $this->api->getSnapshot();
    }
}
