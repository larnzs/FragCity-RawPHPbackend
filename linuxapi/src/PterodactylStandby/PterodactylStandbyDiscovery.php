<?php
declare(strict_types=1);

require_once __DIR__ . '/A2SBatchQuery.php';
require_once __DIR__ . '/PterodactylApplicationClient.php';

/**
 * Discovers explicitly allowlisted Pterodactyl CS2 allocations and performs
 * live, bounded A2S_INFO queries. No scheduled cache-refresh process is needed.
 */
final class PterodactylStandbyDiscovery
{
    private array $serverConfig;
    private FragCityPterodactylApplicationClient $pterodactyl;
    private FragCityA2SBatchQuery $a2s;

    public function __construct(array $config)
    {
        $serverConfig = is_array($config['pterodactyl_standby'] ?? null)
            ? $config['pterodactyl_standby']
            : [];

        if (empty($serverConfig['enabled'])) {
            throw new RuntimeException('Pterodactyl Server Browser discovery is disabled in runtime config.');
        }

        $pteroConfig = is_array($serverConfig['pterodactyl'] ?? null)
            ? $serverConfig['pterodactyl']
            : [];

        $this->serverConfig = $serverConfig;
        $this->pterodactyl = new FragCityPterodactylApplicationClient($pteroConfig);
        $this->a2s = new FragCityA2SBatchQuery((int)($serverConfig['query_timeout_ms'] ?? 1500));
    }

    public function discover(): array
    {
        $candidates = $this->pterodactyl->listCs2Servers();
        $maxServers = max(1, min(100, (int)($this->serverConfig['max_servers'] ?? 40)));
        if (count($candidates) > $maxServers) {
            $candidates = array_slice($candidates, 0, $maxServers);
        }

        $defaultPublicHost = trim((string)($this->serverConfig['public_connect_host'] ?? ''));
        $nodePublicHosts = is_array($this->serverConfig['node_public_connect_hosts'] ?? null)
            ? $this->serverConfig['node_public_connect_hosts']
            : [];

        $defaultQueryHost = trim((string)($this->serverConfig['query_host'] ?? ''));
        $nodeQueryHosts = is_array($this->serverConfig['node_query_hosts'] ?? null)
            ? $this->serverConfig['node_query_hosts']
            : [];
        $region = trim((string)($this->serverConfig['region'] ?? ''));

        $rows = [];
        $queryGroups = [];

        foreach ($candidates as $candidate) {
            $nodeId = (int)$candidate['node_id'];
            $publicHost = trim((string)($nodePublicHosts[(string)$nodeId] ?? $defaultPublicHost));
            $queryHost = trim((string)($nodeQueryHosts[(string)$nodeId] ?? $defaultQueryHost));

            if (!$this->isValidHost($publicHost)) {
                throw new RuntimeException('A valid public connect host is required for node ' . $nodeId . '.');
            }
            if (filter_var($queryHost, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) === false) {
                throw new RuntimeException('A valid internal IPv4 query host is required for node ' . $nodeId . '.');
            }

            $port = (int)$candidate['game_port'];
            $rowIndex = count($rows);
            $rows[] = [
                'id' => substr(hash('sha256', $candidate['server_uuid'] . ':' . $candidate['allocation_id']), 0, 16),
                'name' => $candidate['server_name'] !== '' ? $candidate['server_name'] : 'CS2 Community Server',
                'host' => $publicHost,
                'game_port' => $port,
                'query_port' => $port,
                'mode' => 'Community',
                'map' => 'Unknown',
                'status' => 'offline',
                'players' => 0,
                'max_players' => 0,
                'query_rtt_ms' => null,
                'vac' => null,
                'password' => null,
                'region' => $region,
                'country' => '',
                // This identifier is used only by signed, server-to-server admin requests.
                'pterodactyl_identifier' => (string)($candidate['server_identifier'] ?? ''),
            ];

            if (!$candidate['suspended'] && $candidate['installed']) {
                $queryGroups[$queryHost][] = [
                    'row_index' => $rowIndex,
                    'allocation_id' => (int)$candidate['allocation_id'],
                    'port' => $port,
                ];
            }
        }

        // Query all explicit allocations for each node in concurrent bounded batches.
        foreach ($queryGroups as $queryHost => $allocations) {
            foreach (array_chunk($allocations, 32) as $allocationBatch) {
                $ports = array_map(
                    static fn(array $allocation): int => (int)$allocation['port'],
                    $allocationBatch
                );

                try {
                    $results = $this->a2s->queryBatch((string)$queryHost, $ports);
                } catch (Throwable $e) {
                    error_log(
                        '[FragCity ServerBrowser] A2S batch failed for node query host; type='
                        . get_class($e)
                    );
                    continue;
                }

                foreach ($allocationBatch as $allocation) {
                    $port = (int)$allocation['port'];
                    $result = $results[$port] ?? [
                        'status' => 'error',
                        'error' => 'no_result',
                    ];
                    $info = is_array($result['info'] ?? null) ? $result['info'] : [];

                    if (($result['status'] ?? '') !== 'online'
                        || (int)($info['app_id'] ?? 0) !== 730) {
                        if (($result['error'] ?? '') !== 'timeout') {
                            error_log(
                                '[FragCity ServerBrowser] A2S response rejected for allocation '
                                . (int)$allocation['allocation_id']
                                . '; type=' . (string)($result['error'] ?? $result['status'] ?? 'unknown')
                            );
                        }
                        continue;
                    }

                    $rowIndex = (int)$allocation['row_index'];
                    $name = trim((string)($info['name'] ?? ''));
                    $maxPlayers = max(0, (int)($info['max_players'] ?? 0));
                    $players = max(0, (int)($info['players'] ?? 0));

                    $rows[$rowIndex]['name'] = $name !== ''
                        ? $name
                        : $rows[$rowIndex]['name'];
                    $rows[$rowIndex]['mode'] = $this->extractMode($rows[$rowIndex]['name']);
                    $rows[$rowIndex]['map'] = trim((string)($info['map'] ?? '')) ?: 'Unknown';
                    $rows[$rowIndex]['status'] = 'online';
                    $rows[$rowIndex]['players'] = $maxPlayers > 0
                        ? min($players, $maxPlayers)
                        : 0;
                    $rows[$rowIndex]['max_players'] = $maxPlayers;
                    $rows[$rowIndex]['query_rtt_ms'] = isset($info['query_rtt_ms'])
                        ? round(max(0, (float)$info['query_rtt_ms']), 2)
                        : null;
                    $rows[$rowIndex]['vac'] = (bool)($info['vac'] ?? false);
                    $rows[$rowIndex]['password'] = (bool)($info['password'] ?? false);
                }
            }
        }

        usort($rows, static function (array $left, array $right): int {
            $onlineCompare = ($right['status'] === 'online') <=> ($left['status'] === 'online');
            if ($onlineCompare !== 0) {
                return $onlineCompare;
            }
            return strcasecmp((string)$left['name'], (string)$right['name']);
        });

        return $rows;
    }

    private function extractMode(string $name): string
    {
        if (preg_match('/\\bmode\\s*[:=]\\s*([^|,\\r\\n]+)/iu', $name, $matches) === 1) {
            $mode = trim((string)$matches[1]);
            if ($mode !== '') {
                return function_exists('mb_substr')
                    ? mb_substr($mode, 0, 80, 'UTF-8')
                    : substr($mode, 0, 80);
            }
        }

        return 'Community';
    }

    private function isValidHost(string $host): bool
    {
        if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
            return true;
        }

        return (bool)preg_match(
            '/^(?=.{1,253}$)(?:[a-zA-Z0-9](?:[a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?)(?:\\.(?:[a-zA-Z0-9](?:[a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?))*$/D',
            $host
        );
    }
}
