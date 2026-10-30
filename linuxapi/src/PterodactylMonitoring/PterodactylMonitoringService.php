<?php
declare(strict_types=1);

require_once __DIR__ . '/PterodactylMonitoringPanelClient.php';
require_once __DIR__ . '/WingsMonitoringClient.php';
require_once __DIR__ . '/PterodactylHostMetrics.php';

/**
 * Builds an on-demand, sanitized monitoring snapshot for allowlisted CS2
 * allocations. No Cron process or persistent cache is involved.
 */
final class PterodactylMonitoringService
{
    private array $config;
    private array $monitorConfig;
    private FragCityPterodactylMonitoringPanelClient $panel;

    public function __construct(array $config)
    {
        $this->config = $config;
        $monitorConfig = is_array($config['pterodactyl_monitoring'] ?? null)
            ? $config['pterodactyl_monitoring']
            : [];

        if (empty($monitorConfig['enabled'])) {
            throw new RuntimeException('Pterodactyl monitoring is disabled in runtime config.');
        }

        $panelConfig = is_array($monitorConfig['pterodactyl'] ?? null)
            ? $monitorConfig['pterodactyl']
            : [];

        $this->monitorConfig = $monitorConfig;
        $this->panel = new FragCityPterodactylMonitoringPanelClient($panelConfig);
    }

    /** @return array<string, mixed> */
    public function snapshot(): array
    {
        $nodes = $this->panel->getAllowedNodes();
        $nodeIds = array_map(
            static fn(array $node): int => (int)($node['id'] ?? 0),
            $nodes
        );
        $maxServers = max(1, min(250, (int)($this->monitorConfig['max_servers'] ?? 100)));
        $candidates = $this->panel->getAllowedCs2Servers($nodeIds, $maxServers);

        $serversByNode = [];
        foreach ($candidates as $candidate) {
            $serversByNode[(int)$candidate['node_id']][] = $candidate;
        }

        $nodeSnapshots = [];
        $serverSnapshots = [];

        foreach ($nodes as $node) {
            $nodeId = (int)($node['id'] ?? 0);
            if ($nodeId < 1) {
                continue;
            }

            try {
                [$nodeSnapshot, $nodeServers] = $this->inspectNode(
                    $node,
                    $serversByNode[$nodeId] ?? []
                );
            } catch (Throwable $e) {
                // Do not expose internal URLs, node tokens, or raw upstream errors.
                error_log(
                    '[FragCity Pterodactyl Monitoring] Node '
                    . $nodeId . ' inspection failed; type=' . get_class($e)
                );
                $nodeSnapshot = $this->offlineNodeSnapshot($node, 'node_monitoring_unavailable');
                $nodeServers = $this->offlineServers(
                    $serversByNode[$nodeId] ?? [],
                    'node_unavailable'
                );
            }

            $nodeSnapshots[] = $nodeSnapshot;
            foreach ($nodeServers as $server) {
                $serverSnapshots[] = $server;
            }
        }

        usort($nodeSnapshots, static fn(array $a, array $b): int =>
            strcasecmp((string)$a['name'], (string)$b['name'])
        );
        usort($serverSnapshots, static function (array $a, array $b): int {
            $nodeCompare = strcasecmp((string)$a['node_name'], (string)$b['node_name']);
            return $nodeCompare !== 0
                ? $nodeCompare
                : strcasecmp((string)$a['name'], (string)$b['name']);
        });

        $runningServers = count(array_filter(
            $serverSnapshots,
            static fn(array $server): bool => $server['status'] === 'running'
        ));
        $onlineNodes = count(array_filter(
            $nodeSnapshots,
            static fn(array $node): bool => $node['status'] === 'online'
        ));

        return [
            'generated_at' => gmdate('c'),
            'nodes' => $nodeSnapshots,
            'servers' => $serverSnapshots,
            'summary' => [
                'nodes_total' => count($nodeSnapshots),
                'nodes_online' => $onlineNodes,
                'servers_total' => count($serverSnapshots),
                'servers_running' => $runningServers,
                'cpu_absolute' => round(array_sum(array_column($serverSnapshots, 'cpu_absolute')), 2),
                'memory_bytes' => array_sum(array_column($serverSnapshots, 'memory_bytes')),
                'disk_bytes' => array_sum(array_column($serverSnapshots, 'disk_bytes')),
                'network_rx_bytes' => array_sum(array_column($serverSnapshots, 'network_rx_bytes')),
                'network_tx_bytes' => array_sum(array_column($serverSnapshots, 'network_tx_bytes')),
            ],
        ];
    }

    /**
     * @param array<string, mixed> $node
     * @param array<int, array<string, mixed>> $candidates
     * @return array{0: array<string, mixed>, 1: array<int, array<string, mixed>>}
     */
    private function inspectNode(array $node, array $candidates): array
    {
        $nodeId = (int)$node['id'];
        $nodeConfig = $this->panel->getNodeWingsConfiguration($nodeId);
        $token = (string)$nodeConfig['token'];
        $baseUrl = $this->getWingsBaseUrl($node, $nodeConfig);

        $wings = new FragCityWingsMonitoringClient($baseUrl, $token);
        $systemPayload = $wings->getSystemInformation();
        $wingsServers = $wings->getServers();

        $hostMetrics = null;
        $localNodeId = (int)($this->monitorConfig['local_node_id'] ?? 0);
        if ($localNodeId > 0 && $localNodeId === $nodeId) {
            $systemConfig = is_array($nodeConfig['system'] ?? null)
                ? $nodeConfig['system']
                : [];
            $diskPath = trim((string)($this->monitorConfig['host_metrics_disk_path'] ?? ''));
            if ($diskPath === '') {
                $diskPath = trim((string)($systemConfig['data'] ?? '/'));
            }
            try {
                $hostMetrics = (new PterodactylHostMetrics())->collect($diskPath);
            } catch (Throwable $e) {
                error_log('[FragCity Pterodactyl Monitoring] Local host metrics unavailable; type=' . get_class($e));
            }
        }

        $serverByUuid = [];
        foreach ($wingsServers as $wingsServer) {
            $configuration = is_array($wingsServer['configuration'] ?? null)
                ? $wingsServer['configuration']
                : [];
            $uuid = strtolower(trim((string)($configuration['uuid'] ?? '')));
            if ($uuid !== '') {
                $serverByUuid[$uuid] = $wingsServer;
            }
        }

        $nodeServers = [];
        foreach ($candidates as $candidate) {
            $wingsServer = $serverByUuid[strtolower((string)$candidate['uuid'])] ?? null;
            $nodeServers[] = $this->sanitizeServer($candidate, $wingsServer, $node);
        }

        $system = is_array($systemPayload['system'] ?? null) ? $systemPayload['system'] : [];
        $docker = is_array($systemPayload['docker'] ?? null) ? $systemPayload['docker'] : [];
        $containers = is_array($docker['containers'] ?? null) ? $docker['containers'] : [];
        $resources = [
            'cpu_absolute' => round(array_sum(array_column($nodeServers, 'cpu_absolute')), 2),
            'memory_bytes' => array_sum(array_column($nodeServers, 'memory_bytes')),
            'disk_bytes' => array_sum(array_column($nodeServers, 'disk_bytes')),
            'network_rx_bytes' => array_sum(array_column($nodeServers, 'network_rx_bytes')),
            'network_tx_bytes' => array_sum(array_column($nodeServers, 'network_tx_bytes')),
        ];

        $memoryMb = max(0, (int)($node['memory'] ?? 0));
        $diskMb = max(0, (int)($node['disk'] ?? 0));
        $allocated = is_array($node['allocated_resources'] ?? null)
            ? $node['allocated_resources']
            : [];

        return [[
            'id' => $nodeId,
            'name' => trim((string)($node['name'] ?? 'Node ' . $nodeId)),
            'fqdn' => trim((string)($node['fqdn'] ?? '')),
            'status' => 'online',
            'maintenance_mode' => !empty($node['maintenance_mode']),
            'scheme' => strtolower((string)($node['scheme'] ?? 'https')),
            'daemon_port' => (int)($node['daemon_listen'] ?? ($nodeConfig['api']['port'] ?? 8080)),
            'daemon_sftp_port' => (int)($node['daemon_sftp'] ?? 2022),
            'wings_version' => (string)($systemPayload['version'] ?? ''),
            'system' => [
                'architecture' => (string)($system['architecture'] ?? ''),
                'cpu_threads' => max(0, (int)($system['cpu_threads'] ?? 0)),
                'memory_total_bytes' => max(0, (int)($system['memory_bytes'] ?? 0)),
                'kernel_version' => (string)($system['kernel_version'] ?? ''),
                'os' => (string)($system['os'] ?? ''),
                'os_type' => (string)($system['os_type'] ?? ''),
            ],
            'docker' => [
                'version' => (string)($docker['version'] ?? ''),
                'containers_total' => max(0, (int)($containers['total'] ?? 0)),
                'containers_running' => max(0, (int)($containers['running'] ?? 0)),
                'containers_paused' => max(0, (int)($containers['paused'] ?? 0)),
                'containers_stopped' => max(0, (int)($containers['stopped'] ?? 0)),
            ],
            'host_metrics' => $hostMetrics,
            'allocation' => [
                'memory_capacity_bytes' => $memoryMb * 1024 * 1024,
                'memory_allocated_bytes' => max(0, (int)($allocated['memory'] ?? 0)) * 1024 * 1024,
                'disk_capacity_bytes' => $diskMb * 1024 * 1024,
                'disk_allocated_bytes' => max(0, (int)($allocated['disk'] ?? 0)) * 1024 * 1024,
            ],
            'cs2' => [
                'servers_total' => count($nodeServers),
                'servers_running' => count(array_filter(
                    $nodeServers,
                    static fn(array $server): bool => $server['status'] === 'running'
                )),
                'resources' => $resources,
            ],
            'error' => null,
        ], $nodeServers];
    }

    private function getWingsBaseUrl(array $node, array $nodeConfig): string
    {
        $overrides = is_array($this->monitorConfig['wings_base_urls'] ?? null)
            ? $this->monitorConfig['wings_base_urls']
            : [];
        $nodeId = (string)(int)($node['id'] ?? 0);
        $override = trim((string)($overrides[$nodeId] ?? ''));

        if ($override !== '') {
            return $override;
        }

        $host = trim((string)($node['fqdn'] ?? ''));
        if ($host === '') {
            throw new RuntimeException('Pterodactyl node FQDN is missing.');
        }

        $api = is_array($nodeConfig['api'] ?? null) ? $nodeConfig['api'] : [];
        $ssl = is_array($api['ssl'] ?? null) ? $api['ssl'] : [];
        $sslEnabled = array_key_exists('enabled', $ssl)
            ? (bool)$ssl['enabled']
            : strtolower((string)($node['scheme'] ?? 'https')) === 'https';
        $scheme = $sslEnabled ? 'https' : 'http';
        $port = (int)($api['port'] ?? $node['daemon_listen'] ?? 8080);
        if ($port < 1 || $port > 65535) {
            throw new RuntimeException('Pterodactyl Wings port is invalid.');
        }

        $displayHost = str_contains($host, ':') && $host[0] !== '['
            ? '[' . $host . ']'
            : $host;

        return $scheme . '://' . $displayHost . ':' . $port;
    }

    private function sanitizeServer(array $candidate, ?array $wingsServer, array $node): array
    {
        $state = $wingsServer !== null
            ? strtolower(trim((string)($wingsServer['state'] ?? 'unknown')))
            : 'unknown';
        $validStates = ['running', 'starting', 'stopping', 'offline'];
        if (!in_array($state, $validStates, true)) {
            $state = 'unknown';
        }

        $utilization = is_array($wingsServer['utilization'] ?? null)
            ? $wingsServer['utilization']
            : [];
        $network = is_array($utilization['network'] ?? null)
            ? $utilization['network']
            : [];

        return [
            'id' => (int)$candidate['id'],
            'uuid' => (string)$candidate['uuid'],
            'identifier' => (string)$candidate['identifier'],
            'name' => (string)$candidate['name'],
            'node_id' => (int)$candidate['node_id'],
            'node_name' => trim((string)($node['name'] ?? 'Node ' . (int)$candidate['node_id'])),
            'status' => !empty($candidate['suspended']) ? 'suspended' : $state,
            'installed' => (bool)$candidate['installed'],
            'cpu_absolute' => max(0, (float)($utilization['cpu_absolute'] ?? 0)),
            'memory_bytes' => max(0, (int)($utilization['memory_bytes'] ?? 0)),
            'memory_limit_bytes' => max(
                0,
                (int)($utilization['memory_limit_bytes'] ?? 0)
                    ?: ((int)$candidate['limits']['memory_mb'] * 1024 * 1024)
            ),
            'disk_bytes' => max(0, (int)($utilization['disk_bytes'] ?? 0)),
            'disk_limit_bytes' => max(0, (int)$candidate['limits']['disk_mb'] * 1024 * 1024),
            'cpu_limit_percent' => max(0, (float)$candidate['limits']['cpu_percent']),
            'network_rx_bytes' => max(0, (int)($network['rx_bytes'] ?? 0)),
            'network_tx_bytes' => max(0, (int)($network['tx_bytes'] ?? 0)),
            'uptime_ms' => max(0, (int)($utilization['uptime'] ?? 0)),
            'error' => $wingsServer === null ? 'server_not_loaded_on_wings' : null,
        ];
    }

    /** @param array<string, mixed> $node */
    private function offlineNodeSnapshot(array $node, string $error): array
    {
        $memoryMb = max(0, (int)($node['memory'] ?? 0));
        $diskMb = max(0, (int)($node['disk'] ?? 0));
        $allocated = is_array($node['allocated_resources'] ?? null)
            ? $node['allocated_resources']
            : [];

        return [
            'id' => (int)($node['id'] ?? 0),
            'name' => trim((string)($node['name'] ?? 'Node')),
            'fqdn' => trim((string)($node['fqdn'] ?? '')),
            'status' => 'offline',
            'maintenance_mode' => !empty($node['maintenance_mode']),
            'scheme' => strtolower((string)($node['scheme'] ?? 'https')),
            'daemon_port' => (int)($node['daemon_listen'] ?? 8080),
            'daemon_sftp_port' => (int)($node['daemon_sftp'] ?? 2022),
            'wings_version' => '',
            'system' => [
                'architecture' => '',
                'cpu_threads' => 0,
                'memory_total_bytes' => 0,
                'kernel_version' => '',
                'os' => '',
                'os_type' => '',
            ],
            'docker' => [
                'version' => '',
                'containers_total' => 0,
                'containers_running' => 0,
                'containers_paused' => 0,
                'containers_stopped' => 0,
            ],
            'host_metrics' => null,
            'allocation' => [
                'memory_capacity_bytes' => $memoryMb * 1024 * 1024,
                'memory_allocated_bytes' => max(0, (int)($allocated['memory'] ?? 0)) * 1024 * 1024,
                'disk_capacity_bytes' => $diskMb * 1024 * 1024,
                'disk_allocated_bytes' => max(0, (int)($allocated['disk'] ?? 0)) * 1024 * 1024,
            ],
            'cs2' => [
                'servers_total' => 0,
                'servers_running' => 0,
                'resources' => [
                    'cpu_absolute' => 0,
                    'memory_bytes' => 0,
                    'disk_bytes' => 0,
                    'network_rx_bytes' => 0,
                    'network_tx_bytes' => 0,
                ],
            ],
            'error' => $error,
        ];
    }

    private function offlineServers(array $candidates, string $error): array
    {
        $servers = [];
        foreach ($candidates as $candidate) {
            $servers[] = [
                'id' => (int)$candidate['id'],
                'uuid' => (string)$candidate['uuid'],
                'identifier' => (string)$candidate['identifier'],
                'name' => (string)$candidate['name'],
                'node_id' => (int)$candidate['node_id'],
                'node_name' => '',
                'status' => !empty($candidate['suspended']) ? 'suspended' : 'unknown',
                'installed' => (bool)$candidate['installed'],
                'cpu_absolute' => 0,
                'memory_bytes' => 0,
                'memory_limit_bytes' => (int)$candidate['limits']['memory_mb'] * 1024 * 1024,
                'disk_bytes' => 0,
                'disk_limit_bytes' => (int)$candidate['limits']['disk_mb'] * 1024 * 1024,
                'cpu_limit_percent' => (float)$candidate['limits']['cpu_percent'],
                'network_rx_bytes' => 0,
                'network_tx_bytes' => 0,
                'uptime_ms' => 0,
                'error' => $error,
            ];
        }
        return $servers;
    }
}
