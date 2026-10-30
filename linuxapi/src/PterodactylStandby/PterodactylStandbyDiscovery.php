<?php
declare(strict_types=1);

require_once __DIR__ . '/../ServerBrowser/A2SInfoQuery.php';
require_once __DIR__ . '/PterodactylApplicationClient.php';

/**
 * Joins Pterodactyl's allocation inventory with live A2S_INFO responses.
 * The public website never triggers discovery or performs UDP requests.
 */
final class PterodactylStandbyDiscovery
{
    private array $serverConfig;
    private FragCityPterodactylApplicationClient $pterodactyl;
    private FragCityA2SInfoQuery $a2s;

    public function __construct(array $config)
    {
        $serverConfig = is_array($config['pterodactyl_standby'] ?? null)
            ? $config['pterodactyl_standby']
            : [];

        if (empty($serverConfig['enabled'])) {
            throw new RuntimeException('Server Browser discovery is disabled in runtime config.');
        }

        $pteroConfig = is_array($serverConfig['pterodactyl'] ?? null)
            ? $serverConfig['pterodactyl']
            : [];

        $this->serverConfig = $serverConfig;
        $this->pterodactyl = new FragCityPterodactylApplicationClient($pteroConfig);
        $this->a2s = new FragCityA2SInfoQuery((int)($serverConfig['query_timeout_ms'] ?? 1500));
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
        foreach ($candidates as $candidate) {
            $nodeId = (int)$candidate['node_id'];
            $publicHost = trim((string)($nodePublicHosts[(string)$nodeId] ?? $defaultPublicHost));
            $queryHost = trim((string)($nodeQueryHosts[(string)$nodeId] ?? $defaultQueryHost));

            if (!$this->isValidHost($publicHost)) {
                throw new RuntimeException('A valid public connect host is required for node ' . $nodeId . '.');
            }
            if (filter_var($queryHost, FILTER_VALIDATE_IP) === false) {
                throw new RuntimeException('A valid internal query host is required for node ' . $nodeId . '.');
            }

            $port = (int)$candidate['game_port'];
            $row = [
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
            ];

            if (!$candidate['suspended'] && $candidate['installed']) {
                try {
                    $info = $this->a2s->query($queryHost, $port);
                    if ((int)($info['app_id'] ?? 0) !== 730) {
                        throw new RuntimeException('Allocation did not answer as Counter-Strike 2.');
                    }

                    $name = trim((string)($info['name'] ?? ''));
                    $row['name'] = $name !== '' ? $name : $row['name'];
                    $row['mode'] = $this->extractMode($row['name']);
                    $row['map'] = trim((string)($info['map'] ?? '')) ?: 'Unknown';
                    $row['status'] = 'online';
                    $row['players'] = max(0, (int)($info['players'] ?? 0));
                    $row['max_players'] = max(0, (int)($info['max_players'] ?? 0));
                    if ($row['max_players'] > 0) {
                        $row['players'] = min($row['players'], $row['max_players']);
                    } else {
                        $row['players'] = 0;
                    }
                    $row['query_rtt_ms'] = isset($info['query_rtt_ms'])
                        ? round(max(0, (float)$info['query_rtt_ms']), 2)
                        : null;
                    $row['vac'] = (bool)($info['vac'] ?? false);
                    $row['password'] = (bool)($info['password'] ?? false);
                } catch (Throwable $e) {
                    // A failed query marks only this allocation offline. Log the
                    // allocation and exception type, never the Pterodactyl token.
                    error_log(
                        '[FragCity ServerBrowser] A2S query failed for allocation '
                        . (int)$candidate['allocation_id'] . '; type=' . get_class($e)
                    );
                }
            }

            $rows[] = $row;
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
        if (preg_match('/\bmode\s*[:=]\s*([^|,\r\n]+)/iu', $name, $matches) === 1) {
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
            '/^(?=.{1,253}$)(?:[a-zA-Z0-9](?:[a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?)(?:\.(?:[a-zA-Z0-9](?:[a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?))*$/D',
            $host
        );
    }
}
